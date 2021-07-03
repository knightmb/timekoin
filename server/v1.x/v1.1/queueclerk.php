<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(90);
//***********************************************************************************
//***********************************************************************************
if(QUEUECLERK_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Open persistent connection to database
mysql_pconnect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

// Check for banned IP address
$ip = mysql_result(mysql_query("SELECT * FROM `ip_banlist` WHERE `ip` = '" . $_SERVER['REMOTE_ADDR'] . "' LIMIT 1"),0,0);

if(empty($ip) == FALSE)
{
	// Sorry, your IP address has been banned :(
	exit;
}
//***********************************************************************************
// Answer transaction hash poll
if($_GET["action"] == "trans_hash")
{
	$transaction_queue_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,"field_data");

	echo $transaction_queue_hash;

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'QU')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction queue poll
if($_GET["action"] == "queue")
{
	$sql = "SELECT * FROM `transaction_queue` ORDER BY RAND() LIMIT 100";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$queue_number = 1;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			echo "---queue$queue_number=" . $sql_row["hash"] . "---end$queue_number";
			$queue_number++;
		}
	}

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'QU')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction details that match our hash number
if($_GET["action"] == "transaction" && empty($_GET["number"]) == FALSE)
{
	$current_hash = $_GET["number"];

	$sql = "SELECT * FROM `transaction_queue` WHERE `hash` = '$current_hash' LIMIT 1";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	if($sql_num_results > 0)
	{
		$sql_row = mysql_fetch_array($sql_result);

		echo "-----timestamp=" . $sql_row["timestamp"] . "-----public_key=" . base64_encode($sql_row["public_key"]) . "-----crypt1=" . $sql_row["crypt_data1"];
		echo "-----crypt2=" . $sql_row["crypt_data2"] . "-----crypt3=" . $sql_row["crypt_data3"] . "-----hash=" . $sql_row["hash"];
		echo "-----attribute=" . $sql_row["attribute"] . "-----end";
	}

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'QU')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Accept a transaction from a firewalled peer (behind a firewall with no inbound communication port open)
if($_GET["action"] == "input_transaction")
{
	$next_generation_cycle = transaction_cycle(1);
	$current_generation_cycle = transaction_cycle(0);

	// Can we work on the transactions in the database?
	// Not allowed 30 seconds before and 30 seconds after generation cycle.
	if(($next_generation_cycle - time()) > 30 && (time() - $current_generation_cycle) > 30)
	{
		$transaction_timestamp = $_POST["timestamp"];
		$transaction_public_key = $_POST["public_key"];
		$transaction_crypt1 = $_POST["crypt_data1"];
		$transaction_crypt2 = $_POST["crypt_data2"];
		$transaction_crypt3 = $_POST["crypt_data3"];
		$transaction_hash = $_POST["hash"];
		$transaction_attribute = $_POST["attribute"];

		$transaction_public_key = base64_decode($transaction_public_key);

		$hash_match = mysql_result(mysql_query("SELECT * FROM `transaction_queue` WHERE `hash` = '$transaction_hash' LIMIT 1"),0,0);
		
		if(empty($hash_match) == TRUE)
		{
			// No duplicate found, continue processing
			if($transaction_attribute == "R")
			{
				// Check to make sure this public key isn't forged or made up to win the list
				openssl_public_decrypt(base64_decode($transaction_crypt1), $inside_transaction_hash, $transaction_public_key);
				$final_hash_compare = $transaction_crypt2;
			}
			else
			{
				// Check to make sure attribute is valid
				if($transaction_attribute == "G" || $transaction_attribute == "T")
				{
					// Decrypt transaction information for regular transaction data
					// and check to make sure the public key that is being sent to
					// has not been tampered with.
					openssl_public_decrypt(base64_decode($transaction_crypt3), $transaction_info, $transaction_public_key);

					preg_match_all('|HASH=(.*)|', $transaction_info, $matches);
					foreach($matches[1] as $inside_transaction_hash)
					{
					}

					// Check if a message is encoded in this data as well
					if(strlen($inside_transaction_hash) != 64)
					{
						// A message is also encoded
						preg_match_all('|HASH=(.*)---MSG=|', $transaction_info, $matches);
						foreach($matches[1] as $inside_transaction_hash)
						{
						}
					}
				}

				$final_hash_compare = hash('sha256', $transaction_crypt1 . $transaction_crypt2);				
			}

			// Check to make sure this transaction is even valid
			if($inside_transaction_hash == $final_hash_compare && strlen($transaction_public_key) > 256)
			{
				// Check for 100 public key limit in the transaction queue
				$sql = "SELECT * FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);

				if($sql_num_results < 100)
				{						
					// Transaction hash and real hash match
					$sql = "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
					VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
					
					if(mysql_query($sql) == TRUE)
					{
						// Give confirmation of transaction insert accept
						echo "OK";
						write_log("Accepted Inbound Transaction from IP: " . $_SERVER['REMOTE_ADDR'], "QC");
					}
				}
			}

		} // End Duplicate check

	} // End time allowed check

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'QU')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,"field_data");

// Check if loop is already running
if($loop_active == 0)
{
	// Set the working status of 1
	$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1";
	mysql_query($sql);
}
else
{
	// Loop called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************

$next_generation_cycle = transaction_cycle(1);
$current_generation_cycle = transaction_cycle(0);

// Can we work on the transactions in the database?
// Not allowed 30 seconds before and 30 seconds after generation cycle.
if(($next_generation_cycle - time()) > 30 && (time() - $current_generation_cycle) > 30)
{
	// Create a hash of my own transaction queue
	$sql = "SELECT * FROM `transaction_queue` ORDER BY `hash`";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$transaction_queue_hash = 0;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			$transaction_queue_hash = $transaction_queue_hash . $sql_row["hash"];
		}

		$transaction_queue_hash = hash('md5', $transaction_queue_hash);
	}

	// Store in database for quick reference from database
	$sql = "UPDATE `options` SET `field_data` = '$transaction_queue_hash' WHERE `options`.`field_name` = 'transaction_queue_hash' LIMIT 1";
	mysql_query($sql);

	// How does my transaction queue compare to others?
	// Ask all of my active peers
	ini_set('user_agent', 'Timekoin Server (Queueclerk) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 3); // Timeout for request in seconds
	$sql = "SELECT * FROM `active_peer_list`";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$transaction_queue_hash_match = 0;
	$transaction_queue_hash_different = 0;
	$site_address;

	if($sql_num_results > 0)
	{
		$hash_different = array();
		
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			$ip_address = $sql_row["IP_Address"];
			$domain = $sql_row["domain"];
			$subfolder = $sql_row["subfolder"];
			$port_number = $sql_row["port_number"];

			if(empty($domain) == TRUE)
			{
				$site_address = $ip_address;
			}
			else
			{
				$site_address = $domain;
			}

			$poll_peer = file_get_contents("http://$site_address:$port_number/$subfolder/queueclerk.php?action=trans_hash", NULL, NULL, NULL, 40);

			if($transaction_queue_hash === $poll_peer)
			{
				$transaction_queue_hash_match++;
			}
			else
			{
				if(empty($poll_peer) == FALSE)
				{
					$transaction_queue_hash_different++;

					$hash_different["ip_address$transaction_queue_hash_different"] = $ip_address;
					$hash_different["domain$transaction_queue_hash_different"] = $domain;
					$hash_different["subfolder$transaction_queue_hash_different"] = $subfolder;
					$hash_different["port_number$transaction_queue_hash_different"] = $port_number;				
				}
			}

		} // End for Loop

	} // End number of results check

	// Compare tallies
	if($transaction_queue_hash_different > 0)
	{
		// Transaction Queue still not in sync with all peers
		$hash_array = array();

		for ($i = 1; $i < $transaction_queue_hash_different + 1; $i++)
		{
			$ip_address = $hash_different["ip_address$i"];
			$domain = $hash_different["domain$i"];
			$subfolder = $hash_different["subfolder$i"];
			$port_number = $hash_different["port_number$i"];

			if(empty($domain) == TRUE)
			{
				$site_address = $ip_address;
			}
			else
			{
				$site_address = $domain;
			}

			$poll_peer = file_get_contents("http://$site_address:$port_number/$subfolder/queueclerk.php?action=queue", NULL, NULL, NULL, 8200);

			// Bring up first match (if any) to compare agaist our database
			preg_match_all("|---queue1=(.*?)---end1|", $poll_peer, $matches);
			foreach($matches[1] as $current_hash)
			{
			}

			$match_number = 2;
			$last_hash = 0;

			while($current_hash !== $last_hash)
			{
				$last_hash = $current_hash; // Duplicate Check

				//Check if this transaction is already in our queue
				$hash_match = mysql_result(mysql_query("SELECT * FROM `transaction_queue` WHERE `hash` = '$current_hash' LIMIT 1"),0,"timestamp");
				
				if(empty($hash_match) == FALSE)
				{
					// A match means both I and the peer have the same transaction in our queue
				}
				else
				{
					// This peer has a different transaction, ask for the full details of it
					$poll_hash = file_get_contents("http://$site_address:$port_number/$subfolder/queueclerk.php?action=transaction&number=$current_hash", NULL, NULL, NULL, 1500);

					preg_match_all("|-----timestamp=(.*)-----public_key=|", $poll_hash, $hash_matches);
					foreach($hash_matches[1] as $transaction_timestamp)
					{
					}
					preg_match_all("|-----public_key=(.*)-----crypt1=|", $poll_hash, $hash_matches);
					foreach($hash_matches[1] as $transaction_public_key)
					{
					}
					preg_match_all("|-----crypt1=(.*)-----crypt2=|", $poll_hash, $hash_matches);
					foreach($hash_matches[1] as $transaction_crypt1)
					{
					}
					preg_match_all("|-----crypt2=(.*)-----crypt3=|", $poll_hash, $hash_matches);
					foreach($hash_matches[1] as $transaction_crypt2)
					{
					}
					preg_match_all("|-----crypt3=(.*)-----hash=|", $poll_hash, $hash_matches);
					foreach($hash_matches[1] as $transaction_crypt3)
					{
					}
					preg_match_all("|-----hash=(.*)-----attribute=|", $poll_hash, $hash_matches);
					foreach($hash_matches[1] as $transaction_hash)
					{
					}
					preg_match_all("|-----attribute=(.*)-----end|", $poll_hash, $hash_matches);
					foreach($hash_matches[1] as $transaction_attribute)
					{
					}

					$transaction_public_key = base64_decode($transaction_public_key);

					if($transaction_attribute == "R")
					{
						// Check to make sure this public key isn't forged or made up to win the list
						openssl_public_decrypt(base64_decode($transaction_crypt1), $inside_transaction_hash, $transaction_public_key);
						$final_hash_compare = $transaction_crypt2;
					}
					else
					{
						// Decrypt transaction information for regular transaction data
						// and check to make sure the public key that is being sent to
						// has not been tampered with.
						openssl_public_decrypt(base64_decode($transaction_crypt3), $transaction_info, $transaction_public_key);

						preg_match_all('|HASH=(.*)|', $transaction_info, $matches);
						foreach($matches[1] as $inside_transaction_hash)
						{
						}

						// Check if a message is encoded in this data as well
						if(strlen($inside_transaction_hash) != 64)
						{
							// A message is also encoded
							preg_match_all('|HASH=(.*)---MSG=|', $transaction_info, $matches);
							foreach($matches[1] as $inside_transaction_hash)
							{
							}
						}

						$final_hash_compare = hash('sha256', $transaction_crypt1 . $transaction_crypt2);
					}

					// Check to make sure this transaction is even valid
					if($inside_transaction_hash == $final_hash_compare && strlen($transaction_public_key) > 256)
					{
						// Check for 100 public key limit in the transaction queue
						$sql = "SELECT * FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
						$sql_result = mysql_query($sql);
						$sql_num_results = mysql_num_rows($sql_result);

						if($sql_num_results < 100)
						{						
							// Transaction hash and real hash match
							$sql = "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
							VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
							mysql_query($sql);
						}
					}
				}

				preg_match_all("|---queue$match_number=(.*?)---end$match_number|", $poll_peer, $matches);
				foreach($matches[1] as $current_hash)
				{
				}

				$match_number++;
			} // End While Loop

		} // End For Loop

	} // End Compare Tallies

} // If/then Check for valid times

//***********************************************************************************
//***********************************************************************************
// Script finished, set status to 0
$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1";
mysql_query($sql);

// Record when this script finished
$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'queueclerk_last_heartbeat' LIMIT 1";
mysql_query($sql);

?>
