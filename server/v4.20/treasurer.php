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
// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit ("Your IP Has Been Banned");
}

// Open persistent connection to database
$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
//***********************************************************************************
// External Flood Protection
	log_ip("TR", scale_trigger(4));
//***********************************************************************************
// First time run check
$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,0);
$last_heartbeat = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_last_heartbeat' LIMIT 1"),0,0);

if($loop_active == "" && $last_heartbeat == 1)
{
	// Create record to begin loop
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('treasurer_heartbeat_active', '0')");
	// Update timestamp for starting
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'treasurer_last_heartbeat' LIMIT 1");
}
else
{
	// Record already exist, called while another process of this script
	// was already running.
	exit;
}

ini_set('user_agent', 'Timekoin Server (Treasurer) v' . TIMEKOIN_VERSION);
ini_set('default_socket_timeout', 3); // Timeout for request in seconds
$sql_max_allowed_packet = intval(mysql_result(mysqli_query($db_connect, "SHOW VARIABLES LIKE 'max_allowed_packet'"),0,1));

while(1) // Begin Infinite Loop
{
set_time_limit(300);
//***********************************************************************************
$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"));

// Check script status
if($loop_active == "")
{
	// Time to exit
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");
	exit;
}
else if($loop_active == 0)
{
	// Set the working status of 1
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");
}
else if($loop_active == 2) // Wake from sleep
{
	// Set the working status of 1
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");
}
else if($loop_active == 3) // Shutdown
{
	mysqli_query($db_connect, "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active'");
	exit;
}
else
{
	// Script called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$previous_transaction_cycle = transaction_cycle(-1);
$current_transaction_cycle = transaction_cycle(0);
$next_transaction_cycle = transaction_cycle(1);
//*****************************************************************************************************
//*****************************************************************************************************
// Check my transaction queue and copy pending transaction to the main transaction queue, giving priority
// to self created transactions over 3rd party submitted transactions
$my_public_key = my_public_key();
$easy_key_public_key = base64_decode(EASY_KEY_PUBLIC_KEY);
$sql = "(SELECT * FROM `my_transaction_queue` WHERE `public_key` = '$my_public_key' AND `timestamp` < '" . time() . "' ORDER BY `my_transaction_queue`.`timestamp` ASC) 
	UNION (SELECT * FROM `my_transaction_queue` WHERE `public_key` != '$my_public_key' AND `timestamp` < '" . time() . "' ORDER BY `my_transaction_queue`.`timestamp` ASC)";

$sql_result = mysqli_query($db_connect, $sql);
$sql_num_results = mysqli_num_rows($sql_result);

if($sql_num_results > 0)
{
	// Can we copy my transaction queue to the main queue in the allowed time?
	// Not allowed 150 seconds before and 15 seconds after transaction cycle.
	if(($next_transaction_cycle - time()) > 150 && (time() - $current_transaction_cycle) > 15)
	{
		$firewall_blocked = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'firewall_blocked_peer' LIMIT 1")));
		
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
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

			$found_transaction_history = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_history` WHERE `public_key_from` = '$public_key' 
				AND `public_key_to` = '$public_key_to' AND `hash` = '$hash_check' LIMIT 1"));

			if(empty($found_transaction_history) == FALSE)
			{
				// This transaction is in the history now, let's wait about 45 minutes before clearing
				// this from the transaction queue in case of network congestion or other factors
				// that somehow prevented the transaction from making into the network peer swarm
				if(time() - $found_transaction_history > 2700) // Recycle the variable ;)
				{
					$sql = "DELETE QUICK FROM `my_transaction_queue` WHERE `my_transaction_queue`.`public_key` = '$public_key' AND `my_transaction_queue`.`hash` = '$hash_check' LIMIT 1";

					if(mysqli_query($db_connect, $sql) == FALSE)
					{
						//Something didn't work
						write_log("Could NOT Delete A Transaction Copy from MyQueue", "TR");
					}
				}
			}
			else
			{
				$timestamp = $current_transaction_cycle + 1; // Format timestamp for a few seconds after transaction cycle

				if($firewall_blocked == TRUE || ($next_transaction_cycle - time()) > 210)// Mix outbound transaction broadcasting and regular polling
				{
					if($attribute == "T" || $attribute == "G")
					{
						// We are stuck behind a firewall with no inbound connections.
						// The best we can do is try to submit our transaction out to a peer
						// that is accepting inbound connections and hopefully they will replicate
						// out to the peer network.
						$sql_result2 = mysqli_query($db_connect, "SELECT * FROM `active_peer_list` ORDER BY RAND() LIMIT 25");
						$sql_num_results2 = mysqli_num_rows($sql_result2);
						$peer_failure;

						// Grab the data to the first contact servers
						$sql_first_contact = mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'first_contact_server'");
						$sql_num_first_contact = mysqli_num_rows($sql_first_contact);

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
						$contextData = array ('method' => 'POST',
						'header' => "Connection: close\r\n"."Content-Length: ".strlen($query)."\r\n",
						'content'=> $query);
						 
						// Create context resource for our request
						$context = stream_context_create(array('http' => $contextData));

						// Broadcast to First Contact Servers in one-shot no response needed form
						for ($i3 = 0; $i3 < $sql_num_first_contact; $i3++)
						{
							$sql_row3 = mysqli_fetch_array($sql_first_contact);

							$ip_address = find_string("---ip=", "---domain", $sql_row3["field_data"]);
							$domain = find_string("---domain=", "---subfolder", $sql_row3["field_data"]);
							$subfolder = find_string("---subfolder=", "---port", $sql_row3["field_data"]);
							$port_number = find_string("---port=", "---end", $sql_row3["field_data"]);

							$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 5, "queueclerk.php?action=input_transaction", $context);

							if($poll_peer == "OK")
							{
								// Insert to the First Contact Server was accepted
								switch($attribute)
								{
									case "G":
									write_log("Timekoin Currency Generation Broadcast Accepted by First Contact Server $ip_address$domain:$port_number/$subfolder", "G");
									break;

									case "T":
									write_log("Standard Transaction Broadcast Accepted by First Contact Server $ip_address$domain:$port_number/$subfolder", "T");
									break;							
								}
							}
						} // Cycle through First Contact Servers

						// Broadcast to all active peers
						for ($i2 = 0; $i2 < $sql_num_results2; $i2++)
						{
							$sql_row2 = mysqli_fetch_array($sql_result2);
							$ip_address = $sql_row2["IP_Address"];
							$domain = $sql_row2["domain"];
							$subfolder = $sql_row2["subfolder"];
							$port_number = $sql_row2["port_number"];

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

						} // Cycle Through Active Peers

					} // Transaction Attribute Check

				} // Firewall Mode & Broadcast Session Check

				// Check to make sure there is not a duplicate transaction already
				$found_public_key_queue = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$public_key' AND `hash` = '$hash_check' LIMIT 1"),0,0);

				if(empty($found_public_key_queue) == TRUE) // Not in transaction queue
				{					
					if($firewall_blocked == FALSE) // Firewall blocked peers can not queue election request or transactions
					{
						// Full Internet exposure					
						$sql = "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
						VALUES ('" . $timestamp . "', '$public_key', '$crypt1', '$crypt2' , '$crypt3', '$hash_check' , '$attribute');";			

						if(mysqli_query($db_connect, $sql) == TRUE)
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
$processed_transactions = FALSE;
$sql = "SELECT * FROM `transaction_queue` WHERE `attribute` = 'G' AND `timestamp` >= $previous_transaction_cycle AND `timestamp` < $current_transaction_cycle GROUP BY `public_key` ORDER BY `hash`, `timestamp` ASC";
$sql_result = mysqli_query($db_connect, $sql);
$sql_num_results = mysqli_num_rows($sql_result);

if($sql_num_results > 0)
{
	// Record how long transaction processing took in the logs
	$time_start = time();
	$record_insert_counter = 0;
	$record_failure_counter = 0;	
	$mass_insert_counter = 0;
	$sql_mass_insert;
	$generation_cycle = generation_cycle();

	// Special set the Transaction History Hash + Queue Hash so that slower peers don't confuse faster peers that poll
	// this hash if they complete before this peer does. This saves bandwidth and CPU overall since
	// it stops unnecessary polling until completion.
	mysqli_query($db_connect, "UPDATE `options` SET `field_data` = 'PROC' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");
	mysqli_query($db_connect, "UPDATE `options` SET `field_data` = 'PROC' WHERE `field_name` = 'transaction_queue_hash' LIMIT 1");	

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);
		$public_key = filter_public_key($sql_row["public_key"]);

		// Random generation time that can be duplicated across all servers
		if($generation_cycle == TRUE)
		{
			// Is this public key allowed to generate currency?
			$generation_public_key = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 1"));

			if(empty($generation_public_key) == TRUE)
			{
				//Not allowed to generate currency
				write_log("Key Not in Generation Peer List: " . base64_encode($public_key), "G");
				$record_failure_counter++;
			}
			else
			{
				// Generating Peers are limited to 100,000 transactions lifetime per public key
				$generation_records_total = gen_lifetime_transactions($public_key, TRUE, FALSE);

				if($generation_records_total >= 100000)
				{
					write_log("Public Key Has Reached the 100,000 Generating Transaction Lifetime Limit:<BR>" . base64_encode($public_key), "G");
				}
				else
				{
					// Check to make sure there is not a duplicate generation transaction already
					$found_public_key_queue = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY timestamp FROM `transaction_history` WHERE `timestamp` >= $previous_transaction_cycle AND `timestamp` < $current_transaction_cycle AND `public_key_from` = '$public_key' AND `attribute` = 'G' LIMIT 1"));
					$gen_ip_address1 = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY IP_Address FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 1"),0,0);
					$gen_ip_address2 = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY IP_Address FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),1,0);
					$gen_ip_counter1 = intval(mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY COUNT(*) FROM `generating_peer_list` WHERE `IP_Address` = '$gen_ip_address1'")));

					if(empty($gen_ip_address2) == FALSE)
					{
						// This Public Key has two IP address from both the ipv4 and ipv6 range
						$gen_ip_counter2 = intval(mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY COUNT(*) FROM `generating_peer_list` WHERE `IP_Address` = '$gen_ip_address2'")));

						if($gen_ip_counter1 > 1 || $gen_ip_counter2 > 1)
						{
							// More than one IP for different public keys is not allowed
							$gen_ip_counter1 = 2;
						}
					}

					if(empty($found_public_key_queue) == TRUE && $gen_ip_counter1 == 1)
					{
						// Check to make sure enough time has passed since this public key joined the network to allow currency generation
						// Default is 1 Hour or 3600 seconds
						$join_peer_list = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 1"),0,0);
						$join_peer_list2 = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),1,0);

						if(empty($join_peer_list2) == TRUE)
						{
							// Non-Gateway Peer
							$join_peer_list2 = $join_peer_list;
						}

						if((time() - $join_peer_list) >= 3600 && (time() - $join_peer_list2) >= 3600) // It's been more than 3600 seconds since this public key(s) joined the generating peer list
						{
							$time_created = $previous_transaction_cycle + 1; // Format timestamp for a 1 second after previous transaction cycle
							$crypt1 = $sql_row["crypt_data1"];
							$crypt2 = $sql_row["crypt_data2"];
							$crypt3 = $sql_row["crypt_data3"];
							$hash_check = $sql_row["hash"];

							// Check generation amount to make sure it has not been tampered with
							$transaction_info = tk_decrypt($public_key, base64_decode($crypt3));
							$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
							$transaction_timestamp = find_string("TIME=", "---HASH", $transaction_info);							
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

							// Find destination public key, it should be the same as the source public key
							$public_key_to_1 = tk_decrypt($public_key, base64_decode($crypt1));
							$public_key_to_2 = tk_decrypt($public_key, base64_decode($crypt2));
							$public_key_to = $public_key_to_1 . $public_key_to_2;

							if(hash('sha256', $crypt1 . $crypt2 . $crypt3) == $hash_check && 
								strlen($public_key) > 300 &&
								$amount_valid == TRUE && 
								$public_key_to == $public_key && 
								$time_created == $transaction_timestamp) // Check various parts of the generation transaction
							{
								// Public key not found, insert into final transaction history
								if(172 + strlen($sql_mass_insert . ",($time_created, '$public_key', '$public_key', '$crypt1', '$crypt2', '$crypt3', '$hash_check', 'G')") < $sql_max_allowed_packet)
								{
									if($mass_insert_counter == 0)
									{
										// First Insert
										$sql_mass_insert = "($time_created, '$public_key', '$public_key', '$crypt1', '$crypt2', '$crypt3', '$hash_check', 'G')";
										$mass_insert_counter = 1;
									}
									else
									{
										// Next Insert
										$sql_mass_insert.= ",($time_created, '$public_key', '$public_key', '$crypt1', '$crypt2', '$crypt3', '$hash_check', 'G')";
									}
								}
								else
								{
									// Mass Insert Buffer Full, Do Insert to Empty
									$sql = "INSERT HIGH_PRIORITY INTO `transaction_history` (`timestamp` ,`public_key_from`, `public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`) VALUES " . $sql_mass_insert;

									if(mysqli_query($db_connect, $sql) == FALSE)
									{
										//Something didn't work
										write_log("Mass Generation Database Insert Failed", "G");
									}

									$sql_mass_insert = "($time_created, '$public_key', '$public_key', '$crypt1', '$crypt2', '$crypt3', '$hash_check', 'G')";
								}

								$record_insert_counter++;

								// Update the last generation timestamp
								mysqli_query($db_connect, "UPDATE `generating_peer_list` SET `last_generation` = '$current_transaction_cycle' WHERE `generating_peer_list`.`public_key` = '$public_key' LIMIT 2");
							}
							else if($time_created != $transaction_timestamp)
							{
								// The timestamp format is invalid
								write_log("Generation Timestamp Invalid for this Key: " . base64_encode($public_key), "G");
								$record_failure_counter++;
							}
							else if($amount_valid == FALSE)
							{
								// Failed Hash check or Valid Amount check
								write_log("Generation Amount Invalid for this Key: " . base64_encode($public_key), "G");
								$record_failure_counter++;
							}
							else if(hash('sha256', $crypt1 . $crypt2 . $crypt3) != $hash_check)
							{
								// Failed Hash check or Valid Amount check
								write_log("Generation Hash Check Failed for this Key: " . base64_encode($public_key), "G");
								$record_failure_counter++;
							}
							else if($public_key_to != $public_key)
							{
								// Failed Hash check or Valid Amount check
								write_log("Generation Public Key Source and Destination DO NOT MATCH for this Key: " . base64_encode($public_key), "G");
								$record_failure_counter++;
							}							
						}
						else
						{
							// Not enough time has passed
							write_log("Generation Too Early for this Key: " . base64_encode($public_key), "G");
							$record_failure_counter++;
						}
					}
					else if($gen_ip_counter1 > 1)
					{
						// Duplicate generation IP already exist
						write_log("Generation Conflicting IP Address [$gen_ip_address1 $gen_ip_address2] With Key:<br>" . base64_encode($public_key), "G");
						$record_failure_counter++;
					}
					else
					{
						// Duplicate generation transaction already exist
						write_log("Generation Duplicate Discarded for this Key: " . base64_encode($public_key), "G");
						$record_failure_counter++;
					}

				}// End 100,000 Generation Limit Check

			}// End key allowed check

		} // End generation allowed check
		else
		{
			// Not enough time has passed
			write_log("Generation Wrong Time for this Key: " . base64_encode($public_key), "G");
			$record_failure_counter++;
		}

	}// End For Loop

	// Mass Generation Insert Buffer
	if($sql_mass_insert != "")
	{
		$sql = "INSERT HIGH_PRIORITY INTO `transaction_history` (`timestamp` ,`public_key_from`, `public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`) VALUES " . $sql_mass_insert;

		if(mysqli_query($db_connect, $sql) == FALSE)
		{
			//Something didn't work
			write_log("Mass Generation Database Insert Failed", "G");
		}
	}

	// Log transaction processing info
	write_log("Treasurer Processed " . ($record_insert_counter + $record_failure_counter) . " Currency Generation Records in " . (time() - $time_start) . " seconds.<br>[" . $record_insert_counter . "] Successful -- [" . $record_failure_counter . "] Denied", "TR");
	$processed_transactions = TRUE;
} // End Generation Transaction Processing

$sql = "SELECT * FROM `transaction_queue` WHERE `attribute` = 'T' AND `timestamp` >= $previous_transaction_cycle AND `timestamp` < $current_transaction_cycle ORDER BY `hash`, `timestamp` ASC";
$sql_result = mysqli_query($db_connect, $sql);
$sql_num_results = mysqli_num_rows($sql_result);

if($sql_num_results > 0)
{
	// Record how long transaction processing took in the logs
	$time_start = time();
	$record_insert_counter = 0;
	$record_failure_counter = 0;
	$previous_transaction_key = "";
	$previous_transaction_key_balance = 0;
	
	// Special set the Transaction History Hash + Queue Hash so that slower peers don't confuse faster peers that poll
	// this hash if they complete before this peer does. This saves bandwidth and CPU overall since
	// it stops unnecessary polling until completion.
	mysqli_query($db_connect, "UPDATE `options` SET `field_data` = 'PROC' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");
	mysqli_query($db_connect, "UPDATE `options` SET `field_data` = 'PROC' WHERE `field_name` = 'transaction_queue_hash' LIMIT 1");	

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);
		$safe_delete_transaction = FALSE;		
		$public_key = filter_public_key($sql_row["public_key"]);
		$hash_check = $sql_row["hash"];

		// Check to make sure there is not a duplicate transaction already
		$found_public_key_queue = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY timestamp FROM `transaction_history` WHERE `hash` = '$hash_check' LIMIT 1"));

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

			if($previous_transaction_key === $public_key)
			{
				// This key did a transaction earlier, use the RAM balance instead of a DB Lookup
				$transaction_key_balance = $previous_transaction_key_balance;
			}
			else
			{
				// Fresh Balance Lookup
				$transaction_key_balance = check_crypt_balance($public_key);
			}

			// Validate transaction against known public key balance
			if($transaction_key_balance >= $transaction_amount_sent && $transaction_amount_sent > 0 && $amount_valid == TRUE)
			{
				// Balance checks out...
				// Check hash value for tampering of crypt1, crypt2, or crypt3 fields
				if(hash('sha256', $crypt1 . $crypt2 . $crypt3) == $hash_check)
				{
					// Find destination public key
					$public_key_to_1 = tk_decrypt($public_key, base64_decode($crypt1));
					$public_key_to_2 = tk_decrypt($public_key, base64_decode($crypt2));
					
					$public_key_to = filter_public_key(filter_sql($public_key_to_1 . $public_key_to_2));

					if(strlen($public_key) > 300 && strlen($public_key_to) > 300 && $public_key !== $public_key_to) // Filter to/from self public keys
					{
						$sql = "INSERT HIGH_PRIORITY INTO `transaction_history` (`timestamp` ,`public_key_from` , `public_key_to` , `crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
						VALUES ($time_created, '$public_key', '$public_key_to' , '$crypt1', '$crypt2', '$crypt3', '$hash_check', 'T')";

						if($public_key_to !== $easy_key_public_key)
						{
							// Insert into final transaction history
							if(mysqli_query($db_connect, $sql) == FALSE)
							{
								//Something didn't work
								write_log("Transaction Database Insert Failed for this Key: " . base64_encode($public_key), "T");
								$record_failure_counter++;
							}
							else
							{
								$record_insert_counter++;

								// Record Public Key & Balance in RAM for future compares if multiple exist from the same public key
								$previous_transaction_key = $public_key;
								$previous_transaction_key_balance = $transaction_key_balance - $transaction_amount_sent;									
							}							
						}
						else
						{
							// Extra steps needed to verify Easy Key Transactions
							$transaction_message = find_string("---MSG=", "", $transaction_info, TRUE);
							$easy_key_lookup = easy_key_lookup($transaction_message);

							if($easy_key_lookup === $public_key)
							{
								// This public key already owns this easy key
								$easy_key_check = TRUE;
							}
							else if($easy_key_lookup !== $public_key && empty($easy_key_lookup) == FALSE)
							{
								// This other public key already owns this easy key
								$easy_key_check = FALSE;
							}
							else
							{
								// No Easy Key Exist by this Name
								$easy_key_check = TRUE;
							}

							if($easy_key_check == TRUE)
							{
								// Have all the generating peers been paid the Easy Key Fee?
								$pre_transaction_cycle = transaction_cycle(-10); // Check within the previous 45 minutes
								$payment_sql = "SELECT HIGH_PRIORITY public_key FROM `generating_peer_list` GROUP BY `public_key`";
								$payment_sql_result = mysqli_query($db_connect, $payment_sql);
								$payment_sql_num_results = mysqli_num_rows($payment_sql_result);
								$all_generating_peers_paid = TRUE;

								for ($i_pay = 0; $i_pay < $payment_sql_num_results; $i_pay++)
								{
									$payment_sql_row = mysqli_fetch_array($payment_sql_result);
									$pay_this_public_key = $payment_sql_row["public_key"];									
									
									if($pay_this_public_key !== $my_public_key)// You don't pay yourself
									{
										$sql_fee_result = mysql_result(mysqli_query($db_connect, "SELECT HIGH_PRIORITY timestamp FROM `transaction_history` WHERE `timestamp` >= '$pre_transaction_cycle' AND `public_key_from` = '$public_key' AND `public_key_to` = '$pay_this_public_key' LIMIT 1"));

										if(empty($sql_fee_result) == TRUE)
										{
											// Payment to this Generating Peer Does NOT Exist!
											$all_generating_peers_paid = FALSE;
											break;
										}
									}
								}

								if($all_generating_peers_paid == TRUE)
								{
									// Insert into final transaction history
									if(mysqli_query($db_connect, $sql) == FALSE)
									{
										//Something didn't work
										write_log("Transaction Database Insert Failed for this Key: " . base64_encode($public_key), "T");
										$record_failure_counter++;
									}
									else
									{
										// Successful Insert
										$record_insert_counter++;

										// Record Public Key & Balance in RAM for future compares if multiple exist from the same public key
										$previous_transaction_key = $public_key;
										$previous_transaction_key_balance = $transaction_key_balance - $transaction_amount_sent;
									}
								}
								else
								{
									// Transaction will NOT be recorded due to lack of payment to all Generating Peers
									write_log("Easy Key [$transaction_message] Failed for Lack of Payment to All Generating Peers by Public Key:<BR>" . base64_encode($public_key), "T");
									$safe_delete_transaction = TRUE;
									$record_failure_counter++;
								}
							}
							else
							{
								write_log("Easy Key [$transaction_message] Already Belongs To Public Key: " . base64_encode($easy_key_lookup), "T");
								$safe_delete_transaction = TRUE;
								$record_failure_counter++;
							}
						}
					}
					else
					{
						// Invalid or blank Public Key(s)
						write_log("Transaction Public Key Error for this Key: " . base64_encode($public_key), "T");
						$safe_delete_transaction = TRUE;
						$record_failure_counter++;
					}
				}
				else
				{
					// Hash check failed
					write_log("Transaction Hash Check Failed for this Key: " . base64_encode($public_key), "T");
					$safe_delete_transaction = TRUE;
					$record_failure_counter++;
				}
			}
			else
			{
				// Balance is incorrect, transaction invalid
				write_log("Transaction Balance Check Failed for this Key: " . base64_encode($public_key), "T");
				$safe_delete_transaction = TRUE;
				$record_failure_counter++;
			}
		}
		else
		{
			// Duplicate Transaction
			write_log("Duplicate Transaction Failed for this Key: " . base64_encode($public_key), "T");
			$safe_delete_transaction = TRUE;
			$record_failure_counter++;
		}

		// Safe to delete transaction from my transaction queue?
		if($safe_delete_transaction == TRUE)
		{
			// Delete any copies from my transaction queue
			$sql = "DELETE QUICK FROM `my_transaction_queue` WHERE `my_transaction_queue`.`public_key` = '$public_key' AND `my_transaction_queue`.`hash` = '$hash_check' LIMIT 1";

			if(mysqli_query($db_connect, $sql) == FALSE)
			{
				//Something didn't work
				write_log("Could NOT Delete A Transaction Copy from MyQueue", "TR");
			}
		}		

	} // End For Loop

	// Log transaction processing info
	write_log("Treasurer Processed " . ($record_insert_counter + $record_failure_counter) . " Transactions in " . (time() - $time_start) . " seconds.<br>[" . $record_insert_counter . "] Successful -- [" . $record_failure_counter . "] Denied", "TR");
	$processed_transactions = TRUE;
}// End Standard Transaction Processing

if($processed_transactions == TRUE)
{
	// Do DB Work if Transactions of any kind were just processed
	// Wipe my transaction queue of all old election request and generation transactions
	$sql = "DELETE QUICK FROM `my_transaction_queue` WHERE `my_transaction_queue`.`attribute` = 'R' OR `my_transaction_queue`.`attribute` = 'G'";
	if(mysqli_query($db_connect, $sql) == FALSE)
	{
		//Something didn't work
		write_log("Could NOT Delete Old Election Request or Old Currency Generation from the My Transaction Queue!", "TR");
	}

	$sql = "DELETE QUICK FROM `transaction_queue` WHERE `transaction_queue`.`attribute` = 'R' OR `transaction_queue`.`attribute` = 'G' OR `transaction_queue`.`timestamp` < $current_transaction_cycle";
	if(mysqli_query($db_connect, $sql) == FALSE)
	{
		//Something didn't work
		write_log("Could NOT Delete Old Election Request, Currency Generation, or Transactions from the Transaction Queue!", "TR");
	}

	// Optimize Tables to Reclaim Space
	mysqli_query($db_connect, "OPTIMIZE TABLE `my_transaction_queue`");
	mysqli_query($db_connect, "OPTIMIZE TABLE `transaction_queue`");	
}
//***********************************************************************************	

// Check to see if it is time to write a hash of the last cycle transactions
$generation_arbitrary = ARBITRARY_KEY;
$current_hash = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_history` WHERE `timestamp` >= $current_transaction_cycle AND `timestamp` < $next_transaction_cycle AND `attribute` = 'H' LIMIT 1"));
$past_hash = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_history` WHERE `timestamp` >= $previous_transaction_cycle AND `timestamp` < $current_transaction_cycle AND `attribute` = 'H' LIMIT 1"));

if(empty($current_hash) == TRUE)
{
	if(empty($past_hash) == FALSE)//If the past cycle hash is missing, can't move forward without it.
	{
		// A hash from the previous generation cycle does not exist yet, so create it
		$sql = "SELECT timestamp, hash FROM `transaction_history` WHERE `timestamp` >= $previous_transaction_cycle AND `timestamp` < $current_transaction_cycle ORDER BY `timestamp`, `hash` ASC";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		$hash = 0;

		if($sql_num_results != 0)
		{
			// Time to create next hash to lock transactions from this cycle
			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysqli_fetch_array($sql_result);
				$hash.= $sql_row["hash"];
			}

			// Transaction hash
			$hash = hash('sha256', $hash);

			$sql = "INSERT HIGH_PRIORITY INTO `transaction_history` (`timestamp` ,`public_key_from` ,`public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
			VALUES ('$current_transaction_cycle', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$hash', 'H')";
			mysqli_query($db_connect, $sql);

			// Update Transaction History Hash
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . transaction_history_hash() . "' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");

			// Reset Transction Hash Count Cache
			reset_transaction_hash_count();			
		} // End Previous Hash Missing Check

	} // Pass hash check for existance

} // End Empty Hash Check

//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"));

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysqli_query($db_connect, "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active'");
	exit;
}

// Script finished, set standby status to 2
mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = 2 WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1");

// Record when this script finished
mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = " . time() . " WHERE `main_loop_status`.`field_name` = 'treasurer_last_heartbeat' LIMIT 1");

//***********************************************************************************
// Memory Cleanup Before Sleep
unset($sql);
unset($sql_mass_insert);
unset($sql_result);
unset($sql_result2);
unset($payment_sql_result);
unset($crypt1);
unset($crypt2);
unset($crypt3);
unset($public_key_to_1);
unset($public_key_to_2);
unset($public_key_to);
unset($public_key);
//***********************************************************************************
if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30)
{
	sleep(10);

	$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"));
	// Check script status
	if($loop_active == 3)
	{
		// Time to exit
		mysqli_query($db_connect, "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active'");
		exit;
	}

	sleep(10);
}
else
{
	sleep(5);
}

} // End Infinite Loop
//***********************************************************************************
?>
