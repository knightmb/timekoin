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
$hash_code = filter_sql(substr($_GET["hash"], 0, 256)); // Limit to 256 Characters
$hash_code = mysql_result(mysql_query("SELECT field_name FROM `options` WHERE `field_name` LIKE 'hashcode%' AND `field_data` = '$hash_code' LIMIT 1"),0,0);

if(empty($hash_code) == TRUE)
{
	// Invalid Hashcode
	// Log inbound IP activity x50 to Prevent Brute-Force Attacking
	log_ip("AP", 50);
	exit;
}
else
{
	$hash_permissions = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = '$hash_code" . "_permissions' LIMIT 1"),0,0);
}
//***********************************************************************************
// Answer if Hashcode is valid for any reason
if($_GET["action"] == "tk_hash_status")
{
	echo TRUE;

	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
if($_GET["action"] == "pk_valid")
{
	if(check_hashcode_permissions($hash_permissions, "pk_valid") == TRUE)
	{
		// Is this public key valid with any history?
		$public_key = substr($_POST["public_key"], 0, 500); // In case someone is trying to flood this function
		$public_key = filter_sql(base64_decode($public_key));

		$valid_key_test = mysql_result(mysql_query("SELECT public_key_from FROM `transaction_history` WHERE `public_key_from` = '$public_key' OR `public_key_to` = '$public_key' LIMIT 1"),0,0);

		if(empty($valid_key_test) == FALSE)
		{
			// Valid Key with History
			echo 1;
		}
		else
		{
			// No History for Key
			echo 0;
		}		
	}// Valid Permissions Check

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer public key balance request that match our hash code
if($_GET["action"] == "pk_balance")
{
	if(check_hashcode_permissions($hash_permissions, "pk_balance") == TRUE)
	{
		// Grab balance for public key and return value
		$public_key = substr($_POST["public_key"], 0, 500); // In case someone is trying to flood this function
		$public_key = filter_sql(base64_decode($public_key));

		echo check_crypt_balance($public_key);
	}// Valid Permissions Check

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Place Transaction Data Directly into the "my_transaction_queue" table
if($_GET["action"] == "send_tk")
{
	if(check_hashcode_permissions($hash_permissions, "send_tk") == TRUE)
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
			if($transaction_attribute == "T")
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
if($_GET["action"] == "pk_history")
{
	if(check_hashcode_permissions($hash_permissions, "pk_history") == TRUE)
	{
		// Output History of Transactions for the Public Key
		$last = intval($_POST["last"]);
		$public_key = filter_sql(base64_decode($_POST["public_key"]));
		$sent_to = intval($_POST["sent_to"]);
		$sent_from = intval($_POST["sent_from"]);		

		if($last < 1 || $last > 100) { $last = 1; } // Sanitize Number of Transactions to Output

		if($sent_to == TRUE) // Output all transactions sent TO this public key
		{
			// Find the last X transactions sent to this public key
			$sql = "SELECT timestamp, public_key_from, crypt_data3  FROM `transaction_history` WHERE `public_key_to` = '$public_key' ORDER BY `transaction_history`.`timestamp` DESC";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);
			$counter = 1;
			$result_limit = 0;

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				if($result_limit >= $last)
				{
					// Have the amount to show, break from the loop early
					break;
				}					
				
				$sql_row = mysql_fetch_array($sql_result);
				$crypt3 = $sql_row["crypt_data3"];
				$transaction_info = tk_decrypt($sql_row["public_key_from"], base64_decode($crypt3));
				$transaction_amount = find_string("AMOUNT=", "---TIME", $transaction_info);

				// Any encoded messages?
				$inside_message = find_string("---MSG=", "", $transaction_info, TRUE);

				// How many cycles back did this take place?
				$cycles_back = intval((time() - $sql_row["timestamp"]) / 300);

				echo "---TIMESTAMP$counter=" . $sql_row["timestamp"];
				echo "---FROM$counter=" . base64_encode($sql_row["public_key_from"]);
				echo "---AMOUNT$counter=$transaction_amount";
				echo "---VERIFY$counter=$cycles_back";
				echo "---MESSAGE$counter=$inside_message---END$counter";

				$counter++;
				$result_limit++;
			}

			// Log inbound IP activity
			log_ip("AP");
			exit;		
		
		} // Sent to Public Key

		if($sent_from == TRUE) // Output all transactions sent FROM this public key
		{
			// Find the last X transactions sent to this public key
			$sql = "SELECT timestamp, public_key_to, crypt_data3  FROM `transaction_history` WHERE `public_key_from` = '$public_key' ORDER BY `transaction_history`.`timestamp` DESC";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);
			$counter = 1;
			$result_limit = 0;

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				if($result_limit >= $last)
				{
					// Have the amount to show, break from the loop early
					break;
				}					
				
				$sql_row = mysql_fetch_array($sql_result);
				$crypt3 = $sql_row["crypt_data3"];
				$transaction_info = tk_decrypt($public_key, base64_decode($crypt3));
				$transaction_amount = find_string("AMOUNT=", "---TIME", $transaction_info);

				// Any encoded messages?
				$inside_message = find_string("---MSG=", "", $transaction_info, TRUE);


				// How many cycles back did this take place?
				$cycles_back = intval((time() - $sql_row["timestamp"]) / 300);

				echo "---TIMESTAMP$counter=" . $sql_row["timestamp"];
				echo "---TO$counter=" . base64_encode($sql_row["public_key_to"]);
				echo "---AMOUNT$counter=$transaction_amount";
				echo "---VERIFY$counter=$cycles_back";
				echo "---MESSAGE$counter=$inside_message---END$counter";

				$counter++;
				$result_limit++;
			}

			// Log inbound IP activity
			log_ip("AP");
			exit;		
		
		} // Sent from Public Key

	}// Valid Permissions Check

	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
if($_GET["action"] == "pk_gen_amt")
{
	if(check_hashcode_permissions($hash_permissions, "pk_gen_amt") == TRUE)
	{
		// The amount of Timekoins being generated by a public key
		$public_key = filter_sql(base64_decode($_POST["public_key"]));

		echo peer_gen_amount($public_key);
	}

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
if($_GET["action"] == "tk_trans_total")
{
	if(check_hashcode_permissions($hash_permissions, "tk_trans_total") == TRUE)
	{
		$last = intval($_GET["last"]);
		if($last > 100 || $last < 1) { $last = 1; }

		$counter = -1; // Transaction back from present cycle
		$output_counter = 1;

		while($last > 0)
		{
			$start_transaction_cycle = transaction_cycle($counter);
			$end_transaction_cycle = transaction_cycle($counter + 1);
			$transaction_counter = 0;
			$amount = 0;

			$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= '$start_transaction_cycle' AND `timestamp` < '$end_transaction_cycle'";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			if($sql_num_results > 1)
			{
				// Build row with icons
				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);

					if($sql_row["attribute"] == 'G' || $sql_row["attribute"] == 'T')
					{
						// Transaction Amount
						$transaction_info = tk_decrypt($sql_row["public_key_from"], base64_decode($sql_row["crypt_data3"]));
						$amount += intval(find_string("AMOUNT=", "---TIME", $transaction_info));
						$transaction_counter++;
					}
				}
			}

			echo "---TIMESTAMP$output_counter=" . transaction_cycle($counter);
			echo "---NUM$output_counter=$transaction_counter";
			echo "---AMOUNT$output_counter=$amount---END$output_counter";

			$output_counter++;
			$counter--;
			$last--;
		}

	}// End Permission Check

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
if($_GET["action"] == "pk_recv")
{
	if(check_hashcode_permissions($hash_permissions, "pk_recv") == TRUE)
	{
		// Total of *all* the Timekoins ever received by the provided public key via transactions
		$public_key = filter_sql(base64_decode($_POST["public_key"]));

		set_decrypt_mode(); // Figure out which decrypt method can be best used

		//Initialize objects for Internal RSA decrypt
		if($GLOBALS['decrypt_mode'] == 2)
		{
			require_once('RSA.php');
			$rsa = new Crypt_RSA();
			$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		}

		// Find every Time Koin sent to this public Key
		$sql = "SELECT crypt_data3, attribute FROM `transaction_history` WHERE `public_key_to` = '$public_key'";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		$crypto_balance = 0;
		$transaction_info;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_row($sql_result);

			$crypt3 = $sql_row[0];
			$attribute = $sql_row[1];

			if($attribute == "T")
			{
				// Decrypt transaction information
				if($GLOBALS['decrypt_mode'] == 2)
				{
					$rsa->loadKey($public_key_from);
					$transaction_info = $rsa->decrypt(base64_decode($crypt3));
				}
				else
				{
					$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
				}
		
				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
				$crypto_balance += $transaction_amount_sent;
			}
		}

		echo $crypto_balance;

	}// End Permission Check

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
if($_GET["action"] == "pk_sent")
{
	if(check_hashcode_permissions($hash_permissions, "pk_sent") == TRUE)
	{
		// Total of *all* the Timekoins ever sent by the provided public key via transactions
		$public_key = filter_sql(base64_decode($_POST["public_key"]));

		set_decrypt_mode(); // Figure out which decrypt method can be best used

		//Initialize objects for Internal RSA decrypt
		if($GLOBALS['decrypt_mode'] == 2)
		{
			require_once('RSA.php');
			$rsa = new Crypt_RSA();
			$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		}

		// Find every Time Koin sent to this public Key
		$sql = "SELECT crypt_data3, attribute FROM `transaction_history` WHERE `public_key_from` = '$public_key'";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		$crypto_balance = 0;
		$transaction_info;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_row($sql_result);

			$crypt3 = $sql_row[0];
			$attribute = $sql_row[1];

			if($attribute == "T")
			{
				// Decrypt transaction information
				if($GLOBALS['decrypt_mode'] == 2)
				{
					$rsa->loadKey($public_key_from);
					$transaction_info = $rsa->decrypt(base64_decode($crypt3));
				}
				else
				{
					$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
				}
		
				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
				$crypto_balance += $transaction_amount_sent;
			}
		}		

		echo $crypto_balance;

	} // End Permission Check

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
if($_GET["action"] == "pk_gen_total")
{
	if(check_hashcode_permissions($hash_permissions, "pk_sent") == TRUE)
	{
		// Total of *all* the Timekoins ever generated by the provided public key
		$public_key = filter_sql(base64_decode($_POST["public_key"]));

		set_decrypt_mode(); // Figure out which decrypt method can be best used

		//Initialize objects for Internal RSA decrypt
		if($GLOBALS['decrypt_mode'] == 2)
		{
			require_once('RSA.php');
			$rsa = new Crypt_RSA();
			$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		}

		// Find every Time Koin sent to this public Key
		$sql = "SELECT public_key_from, public_key_to, crypt_data3, attribute FROM `transaction_history` WHERE `public_key_from` = '$public_key'";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		$crypto_balance = 0;
		$transaction_info;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_row($sql_result);

			$public_key_from = $sql_row[0];			
			$public_key_to = $sql_row[1];
			$crypt3 = $sql_row[2];
			$attribute = $sql_row[3];

			if($attribute == "G" && $public_key_from == $public_key_to)
			{
				// Decrypt transaction information
				if($GLOBALS['decrypt_mode'] == 2)
				{
					$rsa->loadKey($public_key_from);
					$transaction_info = $rsa->decrypt(base64_decode($crypt3));
				}
				else
				{
					$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
				}
		
				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
				$crypto_balance += $transaction_amount_sent;
			}
		}		

		echo $crypto_balance;

	} // End Permission Check

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Log IP even when not using any functions, just in case
log_ip("AP");
?>
