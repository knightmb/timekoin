<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(999);
//***********************************************************************************
//***********************************************************************************
if(API_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Open persistent connection to database
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer if Hashcode is accepted for any reason
if($_GET["action"] == "tk_hash_status")
{
	$hash_code = filter_sql(substr($_GET["hash"], 0, 256)); // Limit to 256 Characters
	$hash_code = mysql_result(mysql_query("SELECT field_name FROM `options` WHERE `field_name` LIKE 'hashcode%' AND `field_data` = '$hash_code' LIMIT 1"),0,0);

	if(empty($hash_code) == FALSE )
	{
		// This hashcode is valid
		echo TRUE;
	}

	// Log inbound IP activity x50 to Prevent Brute-Force Checking
	log_ip("AP", 50);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer public key balance request that match our hash code
if($_GET["action"] == "pk_balance")
{
	$hash_code = filter_sql(substr($_GET["hash"], 0, 256)); // Limit to 256 Characters
	$hash_code = mysql_result(mysql_query("SELECT field_name FROM `options` WHERE `field_name` LIKE 'hashcode%' AND `field_data` = '$hash_code' LIMIT 1"),0,0);

	$hash_permissions = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = '$hash_code" . "_permissions' LIMIT 1"),0,0);

	if(empty($hash_code) == FALSE && check_hashcode_permissions($hash_permissions, "pk_balance") == TRUE)
	{
		// Grab balance for public key and return value
		$public_key = substr($_POST["public_key"], 0, 500); // In case someone is trying to flood this function
		$public_key = filter_sql(base64_decode($public_key));

		echo check_crypt_balance($public_key);
	}

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Place Transaction Data Directly into the "my_transaction_queue" table
if($_GET["action"] == "send_tk")
{
	$hash_code = filter_sql(substr($_GET["hash"], 0, 256)); // Limit to 256 Characters
	$hash_code = mysql_result(mysql_query("SELECT field_name FROM `options` WHERE `field_name` LIKE 'hashcode%' AND `field_data` = '$hash_code' LIMIT 1"),0,0);

	$hash_permissions = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = '$hash_code" . "_permissions' LIMIT 1"),0,0);

	if(empty($hash_code) == FALSE && check_hashcode_permissions($hash_permissions, "send_tk") == TRUE)
	{
		$next_transaction_cycle = transaction_cycle(1);
		$current_transaction_cycle = transaction_cycle(0);		
		
		$transaction_timestamp = intval($_POST["timestamp"]);
		$transaction_public_key = $_POST["public_key"];
		$transaction_crypt1 = filter_sql($_POST["crypt_data1"]);
		$transaction_crypt2 = filter_sql($_POST["crypt_data2"]);
		$transaction_crypt3 = filter_sql($_POST["crypt_data3"]);
		$transaction_hash = filter_sql($_POST["hash"]);
		$transaction_attribute = $_POST["attribute"];
		$transaction_qhash = $_POST["qhash"];

		// If a qhash is included, use this to verify the data
		if(empty($transaction_qhash) == FALSE)
		{
			$qhash = $transaction_timestamp . $transaction_public_key . $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3 . $transaction_hash . $transaction_attribute;
			$qhash = hash('md5', $qhash);

			// Compare hashes to make sure data is intact
			if($transaction_qhash != $qhash)
			{
				write_log("Queue Hash Data MisMatch from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "AP");
				$hash_match = "mismatch";
			}
			else
			{
				$hash_match = mysql_result(mysql_query("SELECT * FROM `transaction_queue` WHERE `hash` = '$transaction_hash' LIMIT 1"),0,0);
			}
		}
		else
		{
			// A qhash is required to verify the transaction now
			write_log("Queue Hash Data Empty from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "AP");
			$hash_match = "mismatch";
		}

		$transaction_public_key = filter_sql(base64_decode($transaction_public_key));

		if(empty($hash_match) == TRUE)
		{
			// No duplicate found, continue processing
			// Check to make sure attribute is valid
			if($transaction_attribute == "T" || $transaction_attribute == "G")
			{
				// Decrypt transaction information for regular transaction data
				// and check to make sure the public key that is being sent to
				// has not been tampered with.
				$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));

				$inside_transaction_hash = find_string("HASH=", "", $transaction_info, TRUE);

				// Check if a message is encoded in this data as well
				if(strlen($inside_transaction_hash) != 64)
				{
					// A message is also encoded
					$inside_transaction_hash = find_string("HASH=", "---MSG", $transaction_info);
				}

				// Check Hash against 3 crypt fields
				$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);					
			}

			$final_hash_compare = hash('sha256', $transaction_crypt1 . $transaction_crypt2);

			// Check to make sure this transaction is even valid
			if($transaction_hash == $crypt_hash_check 
				&& $inside_transaction_hash == $final_hash_compare 
				&& strlen($transaction_public_key) > 300 
				&& $transaction_timestamp >= $current_transaction_cycle 
				&& $transaction_timestamp < $next_transaction_cycle)
			{
				// Check for 100 public key limit in the transaction queue
				$sql = "SELECT * FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);

				if($sql_num_results < 100)
				{						
					// Transaction hash and real hash match
					$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
					VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
					
					if(mysql_query($sql) == TRUE)
					{
						// Give confirmation of transaction insert accept
						echo "OK";
						write_log("Accepted Direct Transaction for My Transaction Queue from IP: " . $_SERVER['REMOTE_ADDR'], "AP");
					}
				}
				else
				{
					write_log("More Than 100 Transactions Trying to Queue from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "AP");
				}
			}
			else
			{
				write_log("Invalid Transaction Queue Data Discarded from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "AP");
			}

		} // End Has Check

	} // End Permission Check

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Log IP even when not using any functions
log_ip("AP");
?>
