<?PHP
include 'configuration.php';
include 'function.php';
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
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit;
}
//***********************************************************************************
// Answer transaction hash poll
if($_GET["action"] == "trans_hash")
{
	echo mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,0);

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP", 1);
	}
	else
	{
		log_ip("QU", 1);
	}

	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction queue poll
if($_GET["action"] == "queue")
{
	$sql = "SELECT * FROM `transaction_queue` ORDER BY RAND() LIMIT 1000";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$queue_number = 1;
	$transaction_queue_hash;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			$transaction_queue_hash.= $sql_row["timestamp"] . $sql_row["public_key"] . $sql_row["crypt_data1"] . 
			$sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];

			echo "---queue$queue_number=" , hash('md5', $transaction_queue_hash) , "---end$queue_number";

			// Clear Variable
			$transaction_queue_hash = NULL;

			$queue_number++;
		}
	}

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP", 1);
	}
	else
	{
		log_ip("QU", 1);
	}
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction details that match our hash number
if($_GET["action"] == "transaction" && empty($_GET["number"]) == FALSE)
{
	$current_hash = filter_sql($_GET["number"]);

	$sql = "SELECT * FROM `transaction_queue`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$transaction_queue_hash;
	$qhash;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			$transaction_queue_hash = $sql_row["timestamp"] . $sql_row["public_key"] . $sql_row["crypt_data1"] . 
			$sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];		

			if(hash('md5', $transaction_queue_hash) == $current_hash)
			{
				$qhash = $sql_row["timestamp"] . base64_encode($sql_row["public_key"]) . $sql_row["crypt_data1"] . $sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];
				$qhash = hash('md5', $qhash);

				echo "-----timestamp=" , $sql_row["timestamp"] , "-----public_key=" , base64_encode($sql_row["public_key"]) , "-----crypt1=" , $sql_row["crypt_data1"];
				echo "-----crypt2=" , $sql_row["crypt_data2"] , "-----crypt3=" , $sql_row["crypt_data3"] , "-----hash=" , $sql_row["hash"];
				echo "-----attribute=" , $sql_row["attribute"] , "-----end---qhash=$qhash---endqhash";
				break;
			}

			// No match, move on to next record
			$transaction_queue_hash = NULL;
		}
	}

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP", 1);
	}
	else
	{
		log_ip("QU", 1);
	}
	
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Accept a transaction from a firewalled peer (behind a firewall with no inbound communication port open)
if($_GET["action"] == "input_transaction")
{
	$next_transaction_cycle = transaction_cycle(1);
	$current_transaction_cycle = transaction_cycle(0);

	// Can we work on the transactions in the database?
	// Not allowed 180 seconds before and 20 seconds after transaction cycle.
	if(($next_transaction_cycle - time()) > 180 && (time() - $current_transaction_cycle) > 20)
	{
		$transaction_timestamp = intval($_POST["timestamp"]);
		$transaction_public_key = $_POST["public_key"];
		$transaction_crypt1 = filter_sql($_POST["crypt_data1"]);
		$transaction_crypt2 = filter_sql($_POST["crypt_data2"]);
		$transaction_crypt3 = filter_sql($_POST["crypt_data3"]);
		$transaction_hash = filter_sql($_POST["hash"]);
		$transaction_attribute = filter_sql($_POST["attribute"]);
		$transaction_qhash = $_POST["qhash"];

		// If a qhash is included, use this to verify the data
		if(empty($transaction_qhash) == FALSE)
		{
			$qhash = $transaction_timestamp . $transaction_public_key . $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3 . $transaction_hash . $transaction_attribute;
			$qhash = hash('md5', $qhash);

			// Compare hashes to make sure data is intact
			if($transaction_qhash != $qhash)
			{
				write_log("Queue Hash Data MisMatch from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
				$hash_match = "mismatch";
				log_ip("QU", scale_trigger(10));
			}
			else
			{
				// Make sure hash is actually valid and not made up to stop other transactions
				$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);

				if($transaction_hash == $crypt_hash_check)
				{
					// Hash check good, check for duplicate transaction already in queue
					$hash_match = mysql_result(mysql_query("SELECT timestamp FROM `transaction_queue` WHERE `timestamp`= $transaction_timestamp AND `hash` = '$transaction_hash' LIMIT 1"),0,0);
				}
				else
				{
					// Ok, something is very wrong here...
					write_log("Crypt Field Hash Check Failed from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
					$hash_match = "mismatch";
					log_ip("QU", scale_trigger(5));
				}
			}
		}
		else
		{
			// A qhash is required to verify the transaction
			write_log("Queue Hash Data Empty from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
			$hash_match = "mismatch";
			log_ip("QU", scale_trigger(10));
		}

		$transaction_public_key = filter_sql(base64_decode($transaction_public_key));

		if(empty($hash_match) == TRUE) // Duplicate Check
		{
			// No duplicate found, continue processing
			// Check to make sure attribute is valid
			if($transaction_attribute == "T" || $transaction_attribute == "G")
			{
				// Decrypt transaction information for regular transaction data
				// and check to make sure the public key that is being sent to
				// has not been tampered with.
				$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));
				
				// Find destination public key
				$public_key_to_1 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt1));
				$public_key_to_2 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt2));
				$public_key_to = filter_sql($public_key_to_1 . $public_key_to_2);

				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);

				$transaction_amount_sent_test = intval($transaction_amount_sent);

				if($transaction_amount_sent_test == $transaction_amount_sent)
				{
					// Is a valid integer, amount greater than zero?
					if($transaction_amount_sent > 0)
					{
						$valid_amount = TRUE;
					}
					else
					{
						$valid_amount = FALSE;
					}
				}
				else
				{
					// Is NOT a valid integer, fail check
					$valid_amount = FALSE;
				}

				if($transaction_attribute == "G")
				{
					if($transaction_amount_sent_test > 10)
					{
						// Filter silly generation amounts :p
						$valid_amount = FALSE;
					}
				}

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
				&& strlen($public_key_to) > 300 
				&& $transaction_timestamp >= $current_transaction_cycle 
				&& $transaction_timestamp < $next_transaction_cycle
				&& $valid_amount == TRUE)
			{
				// Check for 100 public key limit in the transaction queue
				$sql = "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
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
				else
				{
					write_log("More Than 100 Transactions Trying to Queue from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
					log_ip("QU", scale_trigger(5));
				}
			}
			else
			{
				write_log("Invalid Transaction Queue Data Discarded from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
				log_ip("QU", scale_trigger(5));
			}

		} // End Duplicate & Timestamp check
		else
		{
			// Respond that the transaction is already in the queue
			echo "DUP";
		}

	} // End time allowed check

	//Direct Input Transaction get a count boost
	//to help prevent direct Transaction spamming
	if($transaction_attribute == "T")
	{
		log_ip("QU", scale_trigger(100));
	}
	else if($transaction_attribute == "G")
	{
		log_ip("QU", scale_trigger(3));
	}
	else
	{
		log_ip("QU", scale_trigger(25));
	}

	exit;
}
//***********************************************************************************
// External Flood Protection
	log_ip("QU", scale_trigger(4));
//***********************************************************************************
// First time run check
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,0);
$last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_last_heartbeat' LIMIT 1"),0,0);
$clone_id = $_GET["clone_id"];

if($loop_active === FALSE && $last_heartbeat == 1)
{
	// Create record to begin loop
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('queueclerk_heartbeat_active', '0')");
	// Update timestamp for starting
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'queueclerk_last_heartbeat' LIMIT 1");
}
else
{
	if(empty($clone_id) == TRUE)
	{
		// Record already exist, called while another process of this script
		// was already running.
		exit;
	}
	else
	{
		$crc32_password_hash = hash('crc32', mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'password' LIMIT 1"),0,0));

		if($clone_id == $crc32_password_hash)// Check if Process Cloning should take place
		{
			$process_clone = TRUE;
		}
		else
		{
			exit;
		}
	}
}

ini_set('user_agent', 'Timekoin Server (Queueclerk) v' . TIMEKOIN_VERSION);
ini_set('default_socket_timeout', 3); // Timeout for request in seconds

while(1) // Begin Infinite Loop
{
set_time_limit(300);
//***********************************************************************************
if($process_clone == FALSE) // No Activity Settings for Clone Process
{
	$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,0);

	// Check script status
	if($loop_active === FALSE)
	{
		// Time to exit
		exit;
	}
	else if($loop_active == 0)
	{
		// Set the working status of 1
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");
	}
	else if($loop_active == 2) // Wake from sleep
	{
		// Set the working status of 1
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");
	}
	else if($loop_active == 3) // Shutdown
	{
		mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active'");
		exit;
	}
	else
	{
		// Script called while still working
		exit;
	}
}
//***********************************************************************************
//***********************************************************************************
$next_transaction_cycle = transaction_cycle(1);
$current_transaction_cycle = transaction_cycle(0);
$treasurer_status = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,0));

// Can we work on the transactions in the database?
// Not allowed 30 seconds before and 30 seconds after transaction cycle.
if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30 && $treasurer_status == 2)
{
	// Create a hash of my own transaction queue
	$transaction_queue_hash = queue_hash();

	$db_queue_hash = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,0);

	if($db_queue_hash !== $transaction_queue_hash)
	{
		// Store in database for proper update when peers are polling this info
		mysql_query("UPDATE `options` SET `field_data` = '$transaction_queue_hash' WHERE `options`.`field_name` = 'transaction_queue_hash' LIMIT 1");
	}

	// How does my transaction queue compare to others?
	// Ask all of my active peers
	$sql = "SELECT * FROM `active_peer_list` ORDER BY RAND() LIMIT 10";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$transaction_queue_hash_match = 0;
	$transaction_queue_hash_different = 0;

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

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 32, "queueclerk.php?action=trans_hash");

			if($transaction_queue_hash === $poll_peer)
			{
				$transaction_queue_hash_match++;
			}
			else
			{
				if(strlen($poll_peer) == 32) // Ignore Peers with improper responses
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
		$hash_array = array(); // Empty Array
		$transaction_counter = 0;
		$peer_transaction_limit = 1000;
		$mismatch_error_limit = 3;

		for ($i = 1; $i < $transaction_queue_hash_different + 1; $i++)
		{
			if($next_transaction_cycle - time() < 10)
			{
				// Transaction Cycle has almost ended, break from loop early
				break;
			}			
			
			$ip_address = $hash_different["ip_address$i"];
			$domain = $hash_different["domain$i"];
			$subfolder = $hash_different["subfolder$i"];
			$port_number = $hash_different["port_number$i"];

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 83000, "queueclerk.php?action=queue");

			// Bring up first match (if any) to compare agaist our database
			$match_number = 1;
			$current_hash = find_string("---queue$match_number=", "---end$match_number", $poll_peer);

			$transaction_counter = 0;
			$mismatch_error_count = 0;

			// Load queue data from database first, then recycle it for seeking to avoid a constant DB I/O hit
			$sql2 = "SELECT * FROM `transaction_queue`";
			$sql_result2 = mysql_query($sql2);
			$sql_num_results2 = mysql_num_rows($sql_result2);

			while(empty($current_hash) == FALSE)
			{
				if($next_transaction_cycle - time() < 10)
				{
					// Transaction Cycle has almost ended, break from loop early
					break;
				}
				
				// Count transactions coming from this peer
				$transaction_counter++;

				if($transaction_counter > $peer_transaction_limit)
				{
					write_log("$peer_transaction_limit Transaction limit reached from Peer: $ip_address:$domain:$port_number/$subfolder", "QC");
					// Add failure points to the peer in case further issues
					modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 3);
					break;
				}

				if($mismatch_error_count > $mismatch_error_limit)
				{
					write_log("$mismatch_error_limit Transaction Error limit reached from Peer: $ip_address:$domain:$port_number/$subfolder", "QC");
					// Add failure points to the peer in case further issues
					modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 5);
					break;
				}

				if(strlen($current_hash) == 64)
				{
					// Old Queue System Check
					//Check if this transaction is already in our queue
					$hash_match = mysql_result(mysql_query("SELECT timestamp FROM `transaction_queue` WHERE `hash` = '$current_hash' LIMIT 1"),0,0);
				}
				else
				{
					// New Queue System Check
					$queue_hash_test = NULL;
					$hash_match = NULL;					
					
					mysql_data_seek($sql_result2, 0); // Reset pointer back to beginning of data

					if($sql_num_results2 > 0)
					{
						for ($i2 = 0; $i2 < $sql_num_results2; $i2++)
						{
							$sql_row2 = mysql_fetch_array($sql_result2);

							$queue_hash_test = $sql_row2["timestamp"] . $sql_row2["public_key"] . $sql_row2["crypt_data1"] . 
							$sql_row2["crypt_data2"] . $sql_row2["crypt_data3"] . $sql_row2["hash"] . $sql_row2["attribute"];		

							if(hash('md5', $queue_hash_test) == $current_hash)
							{
								// This Transaction Already Exist in the Queue
								$hash_match = TRUE;
								break;
							}

							// No match, move on to next record
							$queue_hash_test = NULL;
						}

						// No match found, empty string
						$hash_match = NULL;
					}
				}

				if(empty($hash_match) == TRUE)
				{
					// This peer has a different transaction, ask for the full details of it
					$poll_hash = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 1500, "queueclerk.php?action=transaction&number=$current_hash"));

					$transaction_timestamp = intval(find_string("-----timestamp=", "-----public_key", $poll_hash));
					$transaction_public_key = find_string("-----public_key=", "-----crypt1", $poll_hash);
					$transaction_crypt1 = find_string("-----crypt1=", "-----crypt2", $poll_hash);
					$transaction_crypt2 = find_string("-----crypt2=", "-----crypt3", $poll_hash);
					$transaction_crypt3 = find_string("-----crypt3=", "-----hash", $poll_hash);
					$transaction_hash = find_string("-----hash=", "-----attribute", $poll_hash);
					$transaction_attribute = find_string("-----attribute=", "-----end", $poll_hash);
					$transaction_qhash = find_string("---qhash=", "---endqhash", $poll_hash);					

					// If a qhash is included, use this to verify the data
					if(empty($transaction_qhash) == FALSE)
					{
						$qhash = $transaction_timestamp . $transaction_public_key . $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3 . $transaction_hash . $transaction_attribute;
						$qhash = hash('md5', $qhash);

						// Compare hashes to make sure data is intact
						if($transaction_qhash != $qhash)
						{
							write_log("Queue Hash Data MisMatch for Public Key: " . $transaction_public_key, "QC");
							$transaction_attribute = "mismatch";
							$mismatch_error_count++;

							// Add failure points to the peer in case further issues
							modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 3);
						}
						else
						{
							// Make sure hash is actually valid and not made up to stop other transactions
							$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);

							if($crypt_hash_check != $transaction_hash)
							{
								// Ok, something is wrong here...
								write_log("Crypt Field Hash Check Failed for Public Key: " . $transaction_public_key, "QC");
								$transaction_attribute = "mismatch";
								$mismatch_error_count++;

								// Add failure points to the peer in case further issues
								modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 6);
							}
							else
							{
								$last_hash_match = mysql_result(mysql_query("SELECT timestamp FROM `transaction_queue` WHERE `timestamp`= $transaction_timestamp AND `hash` = '$transaction_hash' LIMIT 1"),0,0);

								if(empty($last_hash_match) == FALSE)
								{
									// Duplicate Already in the Transaction Queue
									$transaction_attribute = "mismatch";
								}
							}
						}
					}
					else
					{
						// Qhash is required to match hash
						write_log("Queue Hash Data MisMatch for Public Key: " . $transaction_public_key, "QC");
						$transaction_attribute = "mismatch";
						$mismatch_error_count++;

						// Add failure points to the peer in case further issues
						modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 2);
					}

					$transaction_public_key = filter_sql(base64_decode($transaction_public_key));

					if($transaction_attribute == "R")
					{
						// Check to make sure this public key isn't forged or made up to win the list
						$inside_transaction_hash = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt1));
						
						$final_hash_compare = $transaction_crypt2;
						$crypt_hash_check = $transaction_hash;
						$valid_amount = TRUE; // No amount, but needs this to pass amount test
						$public_key_to = $transaction_public_key; // None is used, but needs this to pass the key length test
					}
					else
					{
						if($transaction_attribute == "T" || $transaction_attribute == "G")
						{
							// Decrypt transaction information for regular transaction data
							// and check to make sure the public key that is being sent to
							// has not been tampered with.
							$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));

							// Find destination public key
							$public_key_to_1 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt1));
							$public_key_to_2 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt2));
							$public_key_to = filter_sql($public_key_to_1 . $public_key_to_2);

							$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);

							$transaction_amount_sent_test = intval($transaction_amount_sent);

							if($transaction_amount_sent_test == $transaction_amount_sent)
							{
								// Is a valid integer, amount greater than zero?
								if($transaction_amount_sent > 0)
								{
									$valid_amount = TRUE;
								}
								else
								{
									$valid_amount = FALSE;
								}
							}
							else
							{
								// Is NOT a valid integer, fail check
								$valid_amount = FALSE;
							}

							if($transaction_attribute == "G")
							{
								if($transaction_amount_sent_test > 10)
								{
									// Filter silly generation amounts :p
									$valid_amount = FALSE;
								}
							}

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
						else
						{
							// Attribute does not match anything valid
							$valid_amount = FALSE;
						}

						$final_hash_compare = hash('sha256', $transaction_crypt1 . $transaction_crypt2);
					}

					// Check to make sure this transaction is even valid (hash check, length check, & timestamp)
					if($transaction_hash == $crypt_hash_check 
						&& $inside_transaction_hash == $final_hash_compare 
						&& strlen($transaction_public_key) > 300 
						&& strlen($public_key_to) > 300 
						&& $transaction_timestamp >= $current_transaction_cycle 
						&& $transaction_timestamp < $next_transaction_cycle 
						&& $valid_amount == TRUE)
					{
						// Check for 100 public key limit in the transaction queue
						$sql = "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
						$sql_result = mysql_query($sql);
						$sql_num_results = mysql_num_rows($sql_result);

						if($sql_num_results < 100)
						{						
							// Transaction hash and real hash match.
							mysql_query("INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
							VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')");
						}
						else
						{
							write_log("More Than 100 Transactions Trying to Queue for Key: " . base64_encode($transaction_public_key), "QC");
						}
					}
				} // End Empty Hash Check

				$match_number++;				
				$current_hash = find_string("---queue$match_number=", "---end$match_number", $poll_peer);

			} // End While Loop

		} // End For Loop

		// Build queue hash after completion
		$transaction_queue_hash = queue_hash();

		// Store in database for quick reference from database
		mysql_query("UPDATE `options` SET `field_data` = '$transaction_queue_hash' WHERE `options`.`field_name` = 'transaction_queue_hash' LIMIT 1");

	} // End Compare Tallies
} // If/then Check for valid times

//***********************************************************************************
//***********************************************************************************
if($process_clone == FALSE)
{
	$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,0);

	// Check script status
	if($loop_active == 3)
	{
		// Time to exit
		mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active'");
		exit;
	}

	// Script finished, set standby status to 2
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");

	// Record when this script finished
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'queueclerk_last_heartbeat' LIMIT 1");
}
else
{
	exit; // Exit Clone Process
}
//***********************************************************************************
if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30)
{
	// Launch Extra Process into Web Server to better poll more peers at once
	$crc32_password_hash = hash('crc32', mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'password' LIMIT 1"),0,0));
	clone_script("queueclerk.php?clone_id=$crc32_password_hash");
	sleep(1);
}
else
{
	sleep(10);
}
} // End Infinite Loop
?>
