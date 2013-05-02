<?PHP
include 'configuration.php';
include 'function.php';
//***********************************************************************************
//***********************************************************************************
if(TREASURER_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

while(1) // Begin Infinite Loop
{
set_time_limit(99);
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active === FALSE)
{
	// Time to exit
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");
	exit;
}
else if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");
}
else if($loop_active == 2) // Wake from sleep
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");
}
else if($loop_active == 3) // Shutdown
{
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");
	exit;
}
else
{
	// Script called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$previous_generation_cycle = transaction_cycle(-1);
$current_generation_cycle = transaction_cycle(0);
$next_generation_cycle = transaction_cycle(1);
$current_generation_block = transaction_cycle(0, TRUE);
//*****************************************************************************************************
//*****************************************************************************************************
// Check my transaction queue and copy pending transaction to the main transaction queue
$sql = "SELECT * FROM `my_transaction_queue` ORDER BY `my_transaction_queue`.`timestamp` ASC LIMIT 100";

$sql_result = mysql_query($sql);
$sql_num_results = mysql_num_rows($sql_result);

if($sql_num_results > 0)
{
	// Can we copy my transaction queue to the main queue in the allowed time?
	// Not allowed 120 seconds before and 20 seconds after transaction cycle.
	if(($next_generation_cycle - time()) > 120 && (time() - $current_generation_cycle) > 20)
	{
		$firewall_blocked = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'firewall_blocked_peer' LIMIT 1"),0,"field_data");
		
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$time_created = $sql_row["timestamp"];
			$public_key = $sql_row["public_key"];
			$crypt1 = $sql_row["crypt_data1"];
			$crypt2 = $sql_row["crypt_data2"];
			$crypt3 = $sql_row["crypt_data3"];
			$hash_check = $sql_row["hash"];
			$attribute = $sql_row["attribute"];			

			// Check to see if transaction is saved in the transaction history
			$public_key_to_1 = tk_decrypt($public_key, base64_decode($crypt1));
			$public_key_to_2 = tk_decrypt($public_key, base64_decode($crypt2));

			$public_key_to = $public_key_to_1 . $public_key_to_2;

			$found_transaction_history = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `public_key_from` = '$public_key' 
				AND `public_key_to` = '$public_key_to' AND `hash` = '$hash_check' LIMIT 1"),0,0);

			if(empty($found_transaction_history) == FALSE)
			{
				// This transaction is in the history now, let's wait about 2 hours before clearing
				// this from the transaction queue in case of network congestion or other factors
				// that somehow prevented the transaction from making into the network peer swarm
				if(time() - $found_transaction_history > 7200) // Recycle the variable ;)
				{
					$sql = "DELETE QUICK FROM `my_transaction_queue` WHERE `my_transaction_queue`.`public_key` = '$public_key' AND `my_transaction_queue`.`hash` = '$hash_check' LIMIT 1";

					if(mysql_query($sql) == FALSE)
					{
						//Something didn't work
						write_log("Could NOT Delete A Transaction Copy from MyQueue", "TR");
					}
				}
			}
			else
			{
				$timestamp = $current_generation_cycle + 4; // Format timestamp for a few seconds after transaction cycle

				if($firewall_blocked == "1" || ($next_generation_cycle - time()) > 230)// Mix outbound transaction broadcasting and regular polling
				{
					if($attribute == "T" || $attribute == "G")
					{
						// We are stuck behind a firewall with no inbound connections.
						// The best we can do is try to submit our transaction out to a peer
						// that is accepting inbound connections and hopefully they will replicate
						// out to the peer network.
						ini_set('user_agent', 'Timekoin Server (Treasurer) v' . TIMEKOIN_VERSION);
						ini_set('default_socket_timeout', 3); // Timeout for request in seconds
						
						$sql_result2 = mysql_query("SELECT * FROM `active_peer_list` ORDER BY RAND()");
						$sql_num_results2 = mysql_num_rows($sql_result2);							
						$peer_failure;

						// Broadcast to all active peers
						for ($i2 = 0; $i2 < $sql_num_results2; $i2++)
						{
							$sql_row2 = mysql_fetch_array($sql_result2);
							$ip_address = $sql_row2["IP_Address"];
							$domain = $sql_row2["domain"];
							$subfolder = $sql_row2["subfolder"];
							$port_number = $sql_row2["port_number"];

							$qhash = $timestamp . base64_encode($public_key) . $crypt1 . $crypt2 . $crypt3 . $hash_check . $attribute;
							$qhash = hash('md5', $qhash);

							// Create map with request parameters
							$params = array ('timestamp' => $timestamp, 
								'public_key' => base64_encode($public_key), 
								'crypt_data1' => $crypt1, 
								'crypt_data2' => $crypt2, 
								'crypt_data3' => $crypt3, 
								'hash' => $hash_check, 
								'attribute' => $attribute,
								'qhash' => $qhash);
							 
							// Build Http query using params
							$query = http_build_query($params);
							 
							// Create Http context details
							$contextData = array (
												 'method' => 'POST',
												 'header' => "Connection: close\r\n".
																 "Content-Length: ".strlen($query)."\r\n",
												 'content'=> $query );
							 
							// Create context resource for our request
							$context = stream_context_create (array ( 'http' => $contextData ));
			
							$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 5, "queueclerk.php?action=input_transaction", $context);

							if($poll_peer == "OK")
							{
								// Insert to the peer remotely was accepted
								switch($attribute)
								{
									case "G":
										write_log("Timekoin Currency Generation Broadcast Accepted by remote Peer $ip_address$domain:$port_number/$subfolder", "G");
										modify_peer_grade($ip_address, $domain, $subfolder, $port_number, -3);										
										break;

									case "T":
										write_log("Standard Transaction Broadcast Accepted by remote Peer $ip_address$domain:$port_number/$subfolder", "T");
										modify_peer_grade($ip_address, $domain, $subfolder, $port_number, -2);
										break;							
								}
							}
							else if($poll_peer == "DUP")
							{
								// Insert to the peer, transaction is already there
								switch($attribute)
								{
									case "G":
										write_log("Timekoin Currency Generation Already Exist at remote Peer $ip_address$domain:$port_number/$subfolder", "G");
										modify_peer_grade($ip_address, $domain, $subfolder, $port_number, -3);
										break;

									case "T":
										write_log("Standard Transaction Already Exist at remote Peer $ip_address$domain:$port_number/$subfolder", "T");
										modify_peer_grade($ip_address, $domain, $subfolder, $port_number, -2);
										break;							
								}
							}								
							else
							{
								// Failed, probably due to no inbound connection allowed at the other peer
								switch($attribute)
								{
									case "G":
										write_log("Timekoin Currency Generation Broadcast FAILED for remote Peer $ip_address$domain:$port_number/$subfolder", "G");
										// Add failure points to the peer in case further issues
										modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 5);
										break;

									case "T":
										write_log("Standard Transaction Broadcast FAILED for remote Peer $ip_address$domain:$port_number/$subfolder", "T");
										// Add failure points to the peer in case further issues
										modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 3);
										break;							
								}
							} // Failure/Success Check & Logging

						} // Transaction Attribute Check

					} // Cycle through Active Peers END

				} // Firewall Mode & Broadcast Session Check

				// Check to make sure there is not a duplicate transaction already
				$found_public_key_queue = mysql_result(mysql_query("SELECT * FROM `transaction_queue` WHERE `public_key` = '$public_key' AND `hash` = '$hash_check' LIMIT 1"),0,"timestamp");

				if(empty($found_public_key_queue) == TRUE) // Not in transaction queue
				{					
					if($firewall_blocked == "0") // Firewall blocked peers can not queue election request or transactions
					{
						// Full Internet exposure					
						$sql = "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
						VALUES ('" . $timestamp . "', '$public_key', '$crypt1', '$crypt2' , '$crypt3', '$hash_check' , '$attribute');";			

						if(mysql_query($sql) == TRUE)
						{
							switch($attribute)
							{
								case "R":
									write_log("Join Generation Peer Request Insert from MyQueue Complete", "R");
									break;

								case "G":
									write_log("Timekoin Generation Insert from MyQueue Complete", "G");
									break;

								case "T":
									write_log("Standard Transaction Insert from MyQueue Complete", "T");
									break;							
							}
						}
					}
				} // End Checking Transaction Queue for Transaction Data

			} // End Duplicate Transaction in Transaction History Check

		} // End for loop

	} // End timing allowed check

}
//*****************************************************************************************************
//*****************************************************************************************************
// Find all transactions between the Previous Transaction Cycle and the Current
$sql = "SELECT * FROM `transaction_queue` WHERE `timestamp` >= $previous_generation_cycle AND `timestamp` < $current_generation_cycle ORDER BY `attribute`, `hash`";

$sql_result = mysql_query($sql);
$sql_num_results = mysql_num_rows($sql_result);

if($sql_num_results > 0)
{
	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$safe_delete_transaction = FALSE;		
		$public_key = $sql_row["public_key"];
		$hash_check = $sql_row["hash"];

		//Copy transaction to final transaction history
		if($sql_row["attribute"] == "G") // Currency Generation Transaction
		{
			// Random generation time that can be duplicated across all servers
			if(generation_cycle() == TRUE)
			{
				// Is this public key allowed to generate currency?
				$generation_public_key = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 1"),0,"public_key");			
				
				if(empty($generation_public_key) == TRUE)
				{
					//Not allowed to generate currency
					write_log("Key Not in Generation Peer List: " . base64_encode($public_key), "G");
				}
				else
				{
					// Check to make sure there is not a duplicate generation transaction already
					$found_public_key_queue = mysql_result(mysql_query("SELECT timestamp, public_key_from, attribute FROM `transaction_history` WHERE `public_key_from` = '$public_key' AND `attribute` = 'G' AND `timestamp` >= $previous_generation_cycle AND `timestamp` < $current_generation_cycle LIMIT 1"),0,"timestamp");

					if(empty($found_public_key_queue) == TRUE)
					{
						// Check to make sure enough time has passed since this public key joined the network to allow currency generation
						// Default is 1 Hour or 3600 seconds
						$join_peer_list = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 1"),0,"join_peer_list");

						if((time() - $join_peer_list) >= 3600) // It's been more than 3600 seconds since this public key joined the generating peer list
						{
							$time_created = $sql_row["timestamp"];
							$crypt1 = $sql_row["crypt_data1"];
							$crypt2 = $sql_row["crypt_data2"];
							$crypt3 = $sql_row["crypt_data3"];
							$hash_check = $sql_row["hash"];

							// Check generation amount to make sure it has not been tampered with
							$transaction_info = tk_decrypt($public_key, base64_decode($crypt3));

							$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
							$transaction_amount_sent_test = intval($transaction_amount_sent);

							if($transaction_amount_sent_test == $transaction_amount_sent && $transaction_amount_sent > 0)
							{
								// Is a valid integer
								$amount_valid = TRUE;
							}
							else
							{
								// Is NOT a valid integer
								$amount_valid = FALSE;
							}

							if($transaction_amount_sent <= peer_gen_amount($public_key) && $amount_valid == TRUE)
							{
								// Everything checks out for valid integer and generation amount
							}
							else
							{
								// Either the amount to generate was wrong or the amount itself is not an integer
								$amount_valid = FALSE;
							}

							if(hash('sha256', $crypt1 . $crypt2 . $crypt3) == $hash_check && $amount_valid == TRUE) // Hash check for tampering
							{
								// Public key not found, insert into final transaction history
								$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from`, `public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
									VALUES ($time_created, '$public_key', '$public_key', '$crypt1', '$crypt2', '$crypt3', '$hash_check', 'G');";

								if(mysql_query($sql) == FALSE)
								{
									//Something didn't work
									write_log("Generation Insert Failed for this Key:" . base64_encode($public_key), "G");
								}

								// Update the last generation timestamp
								$sql = "UPDATE `generating_peer_list` SET `last_generation` = '$current_generation_cycle' WHERE `generating_peer_list`.`public_key` = '$public_key' LIMIT 1";

								if(mysql_query($sql) == FALSE)
								{
									//Something didn't work
									write_log("Generation Timestamp Update Failed for this Key:" . base64_encode($public_key), "G");
								}
							}
							else
							{
								// Failed Hash check or Valid Amount check
								write_log("Generation Hash or Amount Check Failed for this Key:" . base64_encode($public_key), "G");
							}
						}
						else
						{
							// Not enough time has passed
							write_log("Generation Too Early for this Key:" . base64_encode($public_key), "G");
						}
					}
					else
					{
						// Duplicate generation transaction already exist
						write_log("Generation Duplicate Discarded for this Key:" . base64_encode($public_key), "G");
					}

				}// End generation allowed check

			} // End random time check

		} // End Transaction type G check

		if($sql_row["attribute"] == "T") // Regular Transaction
		{
			// Check to make sure there is not a duplicate transaction already
			$found_public_key_queue = mysql_result(mysql_query("SELECT timestamp, public_key_from, hash FROM `transaction_history` WHERE `public_key_from` = '$public_key' AND `hash` = '$hash_check' LIMIT 1"),0,"timestamp");

			if(empty($found_public_key_queue) == TRUE)
			{
				// Transaction isn't a duplicate, continue processing...
				$time_created = $sql_row["timestamp"];
				$crypt1 = $sql_row["crypt_data1"];
				$crypt2 = $sql_row["crypt_data2"];
				$crypt3 = $sql_row["crypt_data3"];

				// How much is this public key trying to send to another public key?
				$transaction_info = tk_decrypt($public_key, base64_decode($crypt3));

				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);

				$transaction_amount_sent_test = intval($transaction_amount_sent);

				if($transaction_amount_sent_test == $transaction_amount_sent)
				{
					// Is a valid integer
					$amount_valid = TRUE;
				}
				else
				{
					// Is NOT a valid integer
					$amount_valid = FALSE;
				}

				// Validate transaction against known public key balance
				if(check_crypt_balance($public_key) >= $transaction_amount_sent && $transaction_amount_sent > 0 && $amount_valid == TRUE)
				{
					// Balance checks out
				
					// Check hash value for tampering of crypt1, crypt2, or crypt3 fields
					if(hash('sha256', $crypt1 . $crypt2 . $crypt3) == $hash_check)
					{
						// Find destination public key
						$public_key_to_1 = tk_decrypt($public_key, base64_decode($crypt1));
						$public_key_to_2 = tk_decrypt($public_key, base64_decode($crypt2));
						
						$public_key_to = $public_key_to_1 . $public_key_to_2;

						if(strlen($public_key) > 300 && strlen($public_key_to) > 300 && $public_key !== $public_key_to) // Filter to/from self public keys
						{
							// Public key not found, insert into final transaction history
							$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from` , `public_key_to` , `crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
								VALUES ($time_created, '$public_key', '$public_key_to' , '$crypt1', '$crypt2', '$crypt3', '$hash_check', 'T');";

							if(mysql_query($sql) == FALSE)
							{
								//Something didn't work
								write_log("Transaction Database Insert Failed for this Key:" . base64_encode($public_key), "T");
							}
						}
						else
						{
							// Invalid or blank Public Key(s)
							write_log("Transaction Public Key Error for this Key:" . base64_encode($public_key), "T");
							$safe_delete_transaction = TRUE;
						}
					}
					else
					{
						// Hash check failed
						write_log("Transaction Hash Check Failed for this Key:" . base64_encode($public_key), "T");
						$safe_delete_transaction = TRUE;						
					}				
				}
				else
				{
					// Balance is incorrect, transaction invalid
					write_log("Transaction Balance Check Failed for this Key:" . base64_encode($public_key), "T");
					$safe_delete_transaction = TRUE;
				}
			}
			else
			{
				// Duplicate Transaction
				write_log("Duplicate Transaction Failed for this Key:" . base64_encode($public_key), "T");
				$safe_delete_transaction = TRUE;
			}

		} // Regular Transaction Check

		// Safe to delete transaction from my transaction queue?
		if($safe_delete_transaction == TRUE)
		{
			// Delete any copies from my transaction queue
			$sql = "DELETE QUICK FROM `my_transaction_queue` WHERE `my_transaction_queue`.`public_key` = '$public_key' AND `my_transaction_queue`.`hash` = '$hash_check' LIMIT 1";

			if(mysql_query($sql) == FALSE)
			{
				//Something didn't work
				write_log("Could NOT Delete A Transaction Copy from MyQueue", "TR");
			}
		}		

	} // End for Loop

	// Wipe transaction queue of all old transaction from current to previous cycle
	$sql = "DELETE QUICK FROM `transaction_queue` WHERE `transaction_queue`.`timestamp` < $current_generation_cycle";
	if(mysql_query($sql) == FALSE)
	{
		//Something didn't work
		write_log("Could NOT Delete Old Transactions from the Transaction Queue", "TR");
	}

	$sql = "DELETE QUICK FROM `my_transaction_queue` WHERE `my_transaction_queue`.`attribute` = 'R' OR `my_transaction_queue`.`attribute` = 'G'";
	if(mysql_query($sql) == FALSE)
	{
		//Something didn't work
		write_log("Could NOT Delete Old Generation Join Request or Currency Generation from the MyQueue", "TR");
	}
}
else
{
	// Wipe transaction that are too old to be used in the next transaction cycle
	mysql_query("DELETE QUICK FROM `transaction_queue` WHERE `transaction_queue`.`timestamp` < $previous_generation_cycle");
}

//***********************************************************************************	
// Check to see if it is time to write a hash of the last cycle transactions

$generation_arbitrary = ARBITRARY_KEY;
$current_hash = mysql_result(mysql_query("SELECT timestamp, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $current_generation_cycle AND `timestamp` < $next_generation_cycle AND `attribute` = 'H' LIMIT 1"),0,"hash");
$past_hash = mysql_result(mysql_query("SELECT timestamp, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $previous_generation_cycle AND `timestamp` < $current_generation_cycle AND `attribute` = 'H' LIMIT 1"),0,"hash");

if(empty($current_hash) == TRUE)
{
	if(empty($past_hash) == FALSE)//If the past cycle hash is missing, can't move forward without it.
	{
		//A hash from the previous generation cycle does not exist yet, so create it
		$sql = "SELECT timestamp, hash FROM `transaction_history` WHERE `timestamp` >= $previous_generation_cycle AND `timestamp` < $current_generation_cycle ORDER BY `timestamp`, `hash`";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		$hash = 0;

		if($sql_num_results == 0)
		{
			// Transaction history is incomplete
		}
		else
		{
			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$hash .= $sql_row["hash"];
			}

			// Transaction hash
			$hash = hash('sha256', $hash);

			$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from` ,`public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
			VALUES ('$current_generation_cycle', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$hash', 'H')";
			mysql_query($sql);

			// Update Transaction History Hash
			mysql_query("UPDATE `options` SET `field_data` = '" . transaction_history_hash() . "' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");

		} // End Previous Hash Missing Check

	} // Pass hash check for existance

} // End Empty Hash Check
//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = 2 WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = " . time() . " WHERE `main_loop_status`.`field_name` = 'treasurer_last_heartbeat' LIMIT 1");

//***********************************************************************************
sleep(10);
} // End Infinite Loop
?>
