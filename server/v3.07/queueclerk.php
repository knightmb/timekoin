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
	echo mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,"field_data");

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP");
	}
	else
	{
		log_ip("QU");
	}

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
			echo "---queue$queue_number=" , $sql_row["hash"] , "---end$queue_number";
			$queue_number++;
		}
	}

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP");
	}
	else
	{
		log_ip("QU");
	}
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction details that match our hash number
if($_GET["action"] == "transaction" && empty($_GET["number"]) == FALSE)
{
	$current_hash = filter_sql($_GET["number"]);

	$sql = "SELECT * FROM `transaction_queue` WHERE `hash` = '$current_hash' LIMIT 1";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	if($sql_num_results > 0)
	{
		$sql_row = mysql_fetch_array($sql_result);

		$qhash = $sql_row["timestamp"] . base64_encode($sql_row["public_key"]) . $sql_row["crypt_data1"] . $sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];
		$qhash = hash('md5', $qhash);

		echo "-----timestamp=" , $sql_row["timestamp"] , "-----public_key=" , base64_encode($sql_row["public_key"]) , "-----crypt1=" , $sql_row["crypt_data1"];
		echo "-----crypt2=" , $sql_row["crypt_data2"] , "-----crypt3=" , $sql_row["crypt_data3"] , "-----hash=" , $sql_row["hash"];
		echo "-----attribute=" , $sql_row["attribute"] , "-----end---qhash=$qhash---endqhash";
	}

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP");
	}
	else
	{
		log_ip("QU");
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
	// Not allowed 120 seconds before and 20 seconds after transaction cycle.
	if(($next_transaction_cycle - time()) > 120 && (time() - $current_transaction_cycle) > 20)
	{
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
				write_log("Queue Hash Data MisMatch from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
				$hash_match = "mismatch";
			}
			else
			{
				// Make sure hash is actually valid and not made up to stop other transactions
				$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);				

				if($transaction_hash == $crypt_hash_check)
				{
					// Hash check good
					$hash_match = mysql_result(mysql_query("SELECT timestamp FROM `transaction_queue` WHERE `hash` = '$transaction_hash' LIMIT 1"),0,0);
				}
				else
				{
					// Ok, something is wrong here...
					write_log("Crypt Field Hash Check Failed from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
					$hash_match = "mismatch";
				}
			}
		}
		else
		{
			// A qhash is required to verify the transaction
			write_log("Queue Hash Data Empty from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
			$hash_match = "mismatch";
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
				&& $transaction_timestamp >= $current_transaction_cycle 
				&& $transaction_timestamp < $next_transaction_cycle
				&& $valid_amount == TRUE)
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
				else
				{
					write_log("More Than 100 Transactions Trying to Queue from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
				}
			}
			else
			{
				write_log("Invalid Transaction Queue Data Discarded from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
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
		log_ip("QU", 2);
	}
	else if($transaction_attribute == "G")
	{
		log_ip("QU", 50);
	}
	else
	{
		log_ip("QU", 1);
	}		

	exit;
}
//***********************************************************************************
while(1) // Begin Infinite Loop
{
set_time_limit(300);
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,"field_data");

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
//***********************************************************************************
//***********************************************************************************
$next_transaction_cycle = transaction_cycle(1);
$current_transaction_cycle = transaction_cycle(0);

// Can we work on the transactions in the database?
// Not allowed 30 seconds before and 30 seconds after transaction cycle.
if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30)
{
	// Create a hash of my own transaction queue
	$transaction_queue_hash = queue_hash();

	// Store in database for quick reference from database
	mysql_query("UPDATE `options` SET `field_data` = '$transaction_queue_hash' WHERE `options`.`field_name` = 'transaction_queue_hash' LIMIT 1");

	// How does my transaction queue compare to others?
	// Ask all of my active peers
	ini_set('user_agent', 'Timekoin Server (Queueclerk) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 2); // Timeout for request in seconds

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

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 40, "queueclerk.php?action=trans_hash");

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
		$transaction_counter = 0;

		for ($i = 1; $i < $transaction_queue_hash_different + 1; $i++)
		{
			$ip_address = $hash_different["ip_address$i"];
			$domain = $hash_different["domain$i"];
			$subfolder = $hash_different["subfolder$i"];
			$port_number = $hash_different["port_number$i"];

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 8300, "queueclerk.php?action=queue");

			// Bring up first match (if any) to compare agaist our database
			$match_number = 1;
			$current_hash = find_string("---queue$match_number=", "---end$match_number", $poll_peer);

			$transaction_counter = 0;
			$peer_transaction_limit = 100;
			$mismatch_error_count = 0;
			$mismatch_error_limit = 10;

			while(empty($current_hash) == FALSE)
			{
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

				//Check if this transaction is already in our queue
				$hash_match = mysql_result(mysql_query("SELECT timestamp FROM `transaction_queue` WHERE `hash` = '$current_hash' LIMIT 1"),0,0);

				if(empty($hash_match) == TRUE)
				{
					// This peer has a different transaction, ask for the full details of it
					$poll_hash = poll_peer($ip_address, $domain, $subfolder, $port_number, 1500, "queueclerk.php?action=transaction&number=$current_hash");

					$transaction_timestamp = filter_sql(find_string("-----timestamp=", "-----public_key", $poll_hash));
					$transaction_public_key = find_string("-----public_key=", "-----crypt1", $poll_hash);
					$transaction_crypt1 = filter_sql(find_string("-----crypt1=", "-----crypt2", $poll_hash));
					$transaction_crypt2 = filter_sql(find_string("-----crypt2=", "-----crypt3", $poll_hash));
					$transaction_crypt3 = filter_sql(find_string("-----crypt3=", "-----hash", $poll_hash));
					$transaction_hash = filter_sql(find_string("-----hash=", "-----attribute", $poll_hash));
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
								write_log("Crypt Field Hash Check Failed for Public Key: " . base64_encode($transaction_public_key), "QC");
								$transaction_attribute = "mismatch";
								$mismatch_error_count++;

								// Add failure points to the peer in case further issues
								modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 6);								
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
					}
					else
					{
						if($transaction_attribute == "T" || $transaction_attribute == "G")
						{
							// Decrypt transaction information for regular transaction data
							// and check to make sure the public key that is being sent to
							// has not been tampered with.
							$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));

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
					}

					// Check to make sure this transaction is even valid (hash check, length check, & timestamp)
					if($transaction_hash == $crypt_hash_check 
						&& $inside_transaction_hash == $final_hash_compare 
						&& strlen($transaction_public_key) > 300 
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
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'queueclerk_last_heartbeat' LIMIT 1");

//***********************************************************************************
if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30)
{
	sleep(1);
}
else
{
	set_time_limit(99);	// Reset Timer to avoid sleep timeout
	sleep(10);
}
} // End Infinite Loop
?>
