<?PHP
include 'configuration.php';
include 'function.php';
//***********************************************************************************
//***********************************************************************************
if(TRANSCLERK_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
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
// Answer transaction history hash poll
if($_GET["action"] == "history_hash")
{
	echo mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_history_hash' LIMIT 1"),0,"field_data");

	// Log inbound IP activity
	log_ip("TC");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer super peer poll
if($_GET["action"] == "super_peer")
{
	echo mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'super_peer' LIMIT 1"),0,"field_data");

	// Log inbound IP activity
	log_ip("TC");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer block hash poll
if($_GET["action"] == "block_hash" && $_GET["block_number"] >= 0)
{
	$block_number = intval($_GET["block_number"]);

	$block_number = TRANSACTION_EPOCH + ($block_number * 300) + 300;

	echo mysql_result(mysql_query("SELECT hash FROM `transaction_history` WHERE `timestamp` = $block_number LIMIT 1"),0,0);

	// Log inbound IP activity
	log_ip("TC");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction data poll
if($_GET["action"] == "transaction_data" && $_GET["block_number"] >= 0)
{
	$block_number = intval($_GET["block_number"]);

	$current_generation_block = transaction_cycle(0, TRUE);
	
	$time1 = transaction_cycle(0 - $current_generation_block + $block_number);
	$time2 = transaction_cycle(0 - $current_generation_block + 1 + $block_number);	

	$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$c = 1;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			
			echo "-----timestamp$c=" , $sql_row["timestamp"] , "-----public_key_from$c=" , base64_encode($sql_row["public_key_from"]) , "-----public_key_to$c=" , base64_encode($sql_row["public_key_to"]);
			echo "-----crypt1data$c=" , $sql_row["crypt_data1"] , "-----crypt2data$c=" , $sql_row["crypt_data2"] , "-----crypt3data$c=" , $sql_row["crypt_data3"] , "-----hash$c=" , $sql_row["hash"];
			echo "-----attribute$c=" , $sql_row["attribute"] , "-----end$c";			

			$c++;
		}		
	}

	// Log inbound IP activity
	log_ip("TC");
	exit;
}
//***********************************************************************************
while(1) // Begin Infinite Loop
{
set_time_limit(120);
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active === FALSE)
{
	// Time to exit
	exit;
}
else if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active' LIMIT 1");
}
else if($loop_active == 2) // Wake from sleep
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active' LIMIT 1");
}
else if($loop_active == 3) // Shutdown
{
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active'");
	exit;
}
else
{
	// Script called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$current_generation_cycle = transaction_cycle(0);
$next_generation_cycle = transaction_cycle(1);
$current_generation_block = transaction_cycle(0, TRUE);

$foundation_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data"));

// Can we work on the transactions in the database?
// Not allowed 30 seconds before and 30 seconds after transaction cycle.
if(($next_generation_cycle - time()) > 30 && (time() - $current_generation_cycle) > 30 && $foundation_active != 1)
{
	// Check if the transaction history is blank or not (either from reset or new setup)
	$sql = "SELECT timestamp FROM `transaction_history` LIMIT 5";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$generation_arbitrary = ARBITRARY_KEY;
//***********************************************************************************
	if($sql_num_results == 0 && $sql_result !== FALSE) //New or blank transaction history
	{
		// Start by inserting the beginning arbitrary transaction from which all others will hash against 
		$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from` ,`public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
	VALUES ('" . TRANSACTION_EPOCH . "', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', 'B')";
	
		mysql_query($sql);

		// Lock Treasurer script to prevent database chaos during the initial phase
		activate(TREASURER, 0);
	}
//***********************************************************************************
	if($sql_num_results > 0 && $sql_num_results < 4) // Write out some test hashes and compare to verify sha256 accuracy
	{
		// Beginning transaction should be in, check to make sure
		$beginning_transaction = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `public_key_from` = '$generation_arbitrary' AND `hash` = '$generation_arbitrary' LIMIT 1"),0,0);

		if($beginning_transaction == TRANSACTION_EPOCH)
		{
			// First transaction complete, continue processing
			$current_generation_block = transaction_cycle(0, TRUE);
			$cycles = 0;

			while($cycles < 3)
			{
				$first_generation_cycle = transaction_cycle((0 - $current_generation_block));	
				$second_generation_cycle = transaction_cycle((0 - $current_generation_block + 1));

				// Build Hash
				$sql = "SELECT timestamp, hash FROM `transaction_history` WHERE `timestamp` >= $first_generation_cycle AND `timestamp` < $second_generation_cycle";

				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);
				$hash = 0;

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$hash .= $sql_row["hash"];
				}

				// Transaction hash
				$hash = hash('sha256', $hash);

					$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from` ,`public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
				VALUES ('$second_generation_cycle', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$hash', 'H')";
		
				mysql_query($sql);

				$current_generation_block--;
				$cycles++;
			}

			$current_generation_block++;
			$second_generation_cycle = transaction_cycle((0 - $current_generation_block + 1));
			$hash_check = mysql_result(mysql_query("SELECT hash FROM `transaction_history` WHERE `timestamp` = '$second_generation_cycle' AND `attribute` = 'H' LIMIT 1"),0,0);

			// Now let's check the results to make sure they match what should be expected
			if($hash_check == SHA256TEST)
			{
				// Passed final hash checking.
				// Unlock Treasurer script to allow live processing
				activate(TREASURER, 1);
				
				// Start a block rebuild from this since a new database is going to be far
				// behind the history of the other active peers
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '3' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");
				mysql_query($sql);
			}
			else
			{
				write_log("Server Failed Initial Encryption Generation and Verification Testing", "TC");
			}
		}

	} // End database preparation and hash testing
//***********************************************************************************

	// Check the transaction hash generation
	if($sql_num_results > 3) // 4 or more transactions on a live database, let the magic begin
	{	
//***********************************************************************************		
// Update transaction history hash
		$current_history_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_history_hash' LIMIT 1"),0,"field_data");
		$transaction_history_block_check = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transaction_history_block_check' LIMIT 1"),0,"field_data"));
		$foundation_block_check = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check' LIMIT 1"),0,"field_data"));

		if($transaction_history_block_check != 0 || $foundation_block_check == 1)
		{
			//A random block check came up wrong, do a single error check sweep
			$error_check_active = TRUE;

			// Change hash to mismatch on purpose
			$current_history_hash = "ERROR_CHECK";

			if($transaction_history_block_check > 0 && $foundation_block_check == 0)
			{
				write_log("Starting History Check from Transaction Cycle #$transaction_history_block_check", "TC");
			}
			else
			{
				$foundation_block_check_start = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check_start' LIMIT 1"),0,"field_data"));
				write_log("Resuming History Check from Transaction Cycle #$foundation_block_check_start", "TC");
			}

			// Update database with ERROR_CHECK hash
			mysql_query("UPDATE `options` SET `field_data` = '$current_history_hash' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");
		}
		else
		{
			$error_check_active = FALSE;

			$history_hash = transaction_history_hash();

			if($history_hash != $current_history_hash)
			{
				$current_history_hash = $history_hash;
				
				// Update database with new hash
				mysql_query("UPDATE `options` SET `field_data` = '$history_hash' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");
			}
		}
//***********************************************************************************	
//***********************************************************************************
	// Does my current history hash match all my peers?
	// Ask all of my active peers
	ini_set('default_socket_timeout', 2); // Timeout for request in seconds
	ini_set('user_agent', 'Timekoin Server (Transclerk) v' . TIMEKOIN_VERSION);

	$sql = perm_peer_mode();

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$trans_list_hash_match = 0;
	$trans_list_hash_different = 0;

	// Keep track of when transaction data from peers is being polled
	$peer_performance = time();

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

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 65, "transclerk.php?action=history_hash");

			if(empty($poll_peer) == TRUE)
			{
				// Add failure points to the peer in case further issues
				modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
			}

			if($current_history_hash === $poll_peer)
			{
				$trans_list_hash_match++;
			}
			else
			{
				if(empty($poll_peer) == FALSE && strlen($poll_peer) > 30 && $poll_peer != "ERROR_CHECK")
				{
					$trans_list_hash_different++;

					$hash_different["ip_address$trans_list_hash_different"] = $ip_address;
					$hash_different["domain$trans_list_hash_different"] = $domain;
					$hash_different["subfolder$trans_list_hash_different"] = $subfolder;
					$hash_different["port_number$trans_list_hash_different"] = $port_number;				
				}
			}
		} // End for Loop

	} // End number of results check
//***********************************************************************************
//***********************************************************************************
	// Compare transaction history tallies
	if($trans_list_hash_different > $trans_list_hash_match)
	{
		//More than 50% of the active peers have a different transaction history list, start comparing your
		//transaction list with one that is different
		$hash_check_counter = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_start_blocks' LIMIT 1"),0,"field_data"));
		$peer_transaction_performance = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_performance' LIMIT 1"),0,"field_data"));

		//Scale the amount of transaction cycles to check based on the last peer performance reading
		if($peer_transaction_performance <= 10)
		{
			if($hash_check_counter < 50) // Cap limit 50
			{
				$new_peer_poll_blocks = $hash_check_counter + 1;
			}
			else
			{
				// Upper Limit Reached, might be a stalled Transaction Clerk
				// or really super fast peers? Reset back to 10, just in case.
				$new_peer_poll_blocks = 10;
			}
		}
		else
		{
			if($hash_check_counter <= 1) // Limit lowest poll to 1
			{
				$new_peer_poll_blocks = 1;
			}
			else
			{
				if($peer_transaction_performance > 20)
				{
					// A large rise in time means a sudden loss of performance, half the speed
					$new_peer_poll_blocks = intval($hash_check_counter / 2);
				}
				else
				{
					$new_peer_poll_blocks = $hash_check_counter - 1;
				}				
			}
		}

		$hash_check_counter = $new_peer_poll_blocks;

		if($error_check_active == FALSE)
		{
			$hash_number = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data"));

			if($hash_number == 0)
			{
				// A new check, start just 10 blocks from the end to avoid checking the entire history
				// from the beginning
				$hash_number = transaction_cycle(-10, TRUE);
			}

			write_log("Resuming History Check from Transaction Cycle #$hash_number", "TC");
		}
		else
		{
			if($foundation_block_check == 1)
			{
				// Start from the block that the foundation begins
				$foundation_block_check_start = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check_start' LIMIT 1"),0,"field_data");
				$foundation_block_check_end = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check_end' LIMIT 1"),0,"field_data");

				if($foundation_block_check_start > $foundation_block_check_end)
				{
					// Check is finished
					$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'foundation_block_check' LIMIT 1";
					mysql_query($sql);

					// Push forward any previous block checks that were in progress
					$sql = "UPDATE `main_loop_status` SET `field_data` = '$foundation_block_check_start' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1";
					mysql_query($sql);

					// Reset block back counter
					$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'block_check_back' LIMIT 1";
					mysql_query($sql);

					$hash_number = $foundation_block_check_start;
				}
				else
				{
					$hash_number = $foundation_block_check_start;
				}
			}
			else
			{
				$hash_number = ($transaction_history_block_check - 1); // Start back 1 block from the error section
			}
		}

		$double_check_counter = 0;
		$sync_block = 0;

		if(($hash_number + $hash_check_counter) >= $current_generation_block)
		{
			// Adjust for when near the end of the transaction history
			$hash_check_counter = $hash_check_counter - (($hash_number + $hash_check_counter) - $current_generation_block);

			if($hash_check_counter == 0)
			{
				// Reached the end, delete the most current block to allow an auto-recorrect
				$sql = "DELETE QUICK FROM `transaction_history` WHERE `timestamp` >= $current_generation_cycle AND `timestamp` < $next_generation_cycle";
				mysql_query($sql);
				
				// How many times have this checked reached the end and still not fixed the transaction history?
				$hash_number_back_database = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'block_check_back' LIMIT 1"),0,"field_data");
				
				$hash_number_back = $hash_number_back_database * 10;

				// Every time we reach the end and still the problem exist, go back another 10 blocks until eventually
				// we reach a point in history where the problem can be repaired
				$hash_number = $current_generation_block - $hash_number_back;

				$foundation_to_cycles = foundation_cycle(0, TRUE) * 500;

				if($current_generation_block - $hash_number < $foundation_to_cycles)
				{
					// Restart a check so that it doesn't dig into a valid
					// transaction foundation by accident
					$hash_number = $foundation_to_cycles;
				}

				if($hash_number_back > 30)
				{
					// More than 30 blocks back, start from the beginning of the current
					// transaction foundation as something might be wrong deeper in the history
					$hash_number = $foundation_to_cycles;
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'block_check_back' LIMIT 1");
				}
				else
				{
					// Increment back counter in case this was not far back enough
					// and it reaches this point again
					$hash_number_back_database++;
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '$hash_number_back_database' WHERE `main_loop_status`.`field_name` = 'block_check_back' LIMIT 1");
				}

				$hash_check_counter = 10;  // Reset to check another 10 blocks forward
			}
		}

		$hash_disagree_peers = array();

		for ($h = 0; $h < $hash_check_counter; $h++)
		{
			$hash_agree = 0;
			$hash_disagree = 0;
			$double_check_block = FALSE;

			$time1 = transaction_cycle(0 - $current_generation_block + $hash_number);
			$time2 = transaction_cycle(0 - $current_generation_block + 1 + $hash_number);

			$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);
			$my_hash = 0;

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$my_hash .= $sql_row["hash"];
			}		

			$my_hash = hash('sha256', $my_hash);

			for ($i = 1; $i < $trans_list_hash_different + 1; $i++)
			{
				$ip_address = $hash_different["ip_address$i"];
				$domain = $hash_different["domain$i"];
				$subfolder = $hash_different["subfolder$i"];
				$port_number = $hash_different["port_number$i"];

				$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 65, "transclerk.php?action=block_hash&block_number=$hash_number");

				if(empty($poll_peer) == TRUE)
				{
					// Add failure points to the peer in case further issues
					modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
				}

				if($my_hash === $poll_peer)
				{
					$hash_agree++;
				}
				else if($my_hash !== $poll_peer && empty($poll_peer) == FALSE)
				{
					$hash_disagree++;

					$hash_disagree_peers["ip_address$hash_disagree"] = $ip_address;
					$hash_disagree_peers["domain$hash_disagree"] = $domain;
					$hash_disagree_peers["subfolder$hash_disagree"] = $subfolder;
					$hash_disagree_peers["port_number$hash_disagree"] = $port_number;					
				}

			} // End For Loop
//***********************************************************************************
			// Compare peers that agree and disagree
			if($hash_disagree > $hash_agree)
			{
				//More than 50% of the active peers disagree on the hash value
				//so poll the transaction data from one them randomly

				// Clear out transaction block to allow the new one to be
				// inserted in place
				$sql = "DELETE QUICK FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2";

				if(mysql_query($sql) == FALSE)
				{
					//Something didn't work, database error?
					write_log("Error removing corrupted transaction data WHERE timestamp >= $time1 AND timestamp < $time2", "TC");
				}
				else
				{
					$peer_number = rand(1,$hash_disagree);// Random peer from array
					$ip_address = $hash_disagree_peers["ip_address$peer_number"];
					$domain = $hash_disagree_peers["domain$peer_number"];
					$subfolder = $hash_disagree_peers["subfolder$peer_number"];
					$port_number = $hash_disagree_peers["port_number$peer_number"];
					$block_number = $hash_number;
				
//************************************************************
					// Check for blank data ahead (Super Peer Mode)
					$time1_ahead = transaction_cycle(0 - $current_generation_block + 1 + $hash_number);
					$time2_ahead = transaction_cycle(0 - $current_generation_block + 2 + $hash_number);
					$no_cycles_ahead = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE timestamp >= $time1_ahead AND timestamp < $time2_ahead AND `attribute` = 'H'"),0,0);

					if(empty($no_cycles_ahead) == TRUE) // No data ahead, lots of blank space
					{
						// Is this a Super Peer?
						$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 3, "transclerk.php?action=super_peer");

						if(empty($poll_peer) == TRUE)
						{
							// Add failure points to the peer in case further issues
							modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 2);
						}

						if($poll_peer >= 1)// This is a super peer that will allow mass downloading of transactions
						{
							// How far behind in the transaction history are we?
							$total_trans_hash = mysql_result(mysql_query("SELECT COUNT(attribute) FROM `transaction_history` WHERE `attribute` = 'H'"),0);

							if(transaction_cycle(0, TRUE) - $total_trans_hash > 500)
							{
								// Far enough behind to use a boost, how close to the end?
								if($block_number + 500 < transaction_cycle(0, TRUE))
								{
									if($poll_peer == 1) // Sanity check on cycles allowed to donwload
									{
										$super_peer_cycles = 500;
									}
									else if($poll_peer > 1 && $poll_peer <= 500)
									{
										$super_peer_cycles = $poll_peer;
									}
									else
									{
										// Something wrong? Default to 2 cycles bulk download
										$super_peer_cycles = 2;
									}
									
									// Not too close to the end, start at the current transaction cycle
									// and donwload X transaction cycles going forward.
									write_log("Connecting with SUPER Peer ($super_peer_cycles Transaction Cycles Limit): $ip_address$domain:$port_number/$subfolder", "TC");
									set_time_limit(300); // Increase script processing time
									$super_transaction_cycle = $block_number;

									while($super_transaction_cycle < $block_number + $super_peer_cycles)
									{
										$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 2000000, "transclerk.php?action=transaction_data&block_number=$super_transaction_cycle");

										if(empty($poll_peer) == TRUE)
										{
											// Add failure points to the peer in case further issues
											modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 1);
										}

										$tc = 1;

										// Check cycle time to avoid over-run near the end
										if(($next_generation_cycle - time()) < 20)
										{
											break;
										}

										while(empty($poll_peer) == FALSE)
										{
											$transaction_timestamp = filter_sql(find_string("-----timestamp$tc=", "-----public_key_from$tc", $poll_peer));
											$transaction_public_key_from = filter_sql(find_string("-----public_key_from$tc=", "-----public_key_to$tc", $poll_peer));
											$transaction_public_key_to = filter_sql(find_string("-----public_key_to$tc=", "-----crypt1data$tc", $poll_peer));
											$transaction_crypt1 = filter_sql(find_string("-----crypt1data$tc=", "-----crypt2data$tc", $poll_peer));
											$transaction_crypt2 = filter_sql(find_string("-----crypt2data$tc=", "-----crypt3data$tc", $poll_peer));
											$transaction_crypt3 = filter_sql(find_string("-----crypt3data$tc=", "-----hash$tc", $poll_peer));
											$transaction_hash = filter_sql(find_string("-----hash$tc=", "-----attribute$tc", $poll_peer));
											$transaction_attribute = filter_sql(find_string("-----attribute$tc=", "-----end$tc", $poll_peer));

											if(empty($transaction_public_key_from) == TRUE && empty($transaction_public_key_to) == TRUE)
											{
												// No more data, break while loop
												break;
											}

											$transaction_public_key_from = filter_sql(base64_decode($transaction_public_key_from));
											$transaction_public_key_to = filter_sql(base64_decode($transaction_public_key_to));

											$found_duplicate = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `timestamp` = '$transaction_timestamp' AND `hash` = '$transaction_hash' LIMIT 1"),0,0);

											// Check for valid attribute
											if($transaction_attribute == "G" || $transaction_attribute == "T" || $transaction_attribute == "H")
											{
												if(empty($found_duplicate) == TRUE)
												{
													mysql_query("INSERT DELAYED INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
													VALUES ('$transaction_timestamp', '$transaction_public_key_from', '$transaction_public_key_to', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')");
												}
											}

											$tc++;

										} // End while loop

										// Jump ahead transaction cycle checking start position for next cycle
										mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . ($super_transaction_cycle - 1) . "' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");										
										
										$super_transaction_cycle++;

									} // Transaction Cycles Ahead Loop

									write_log("Detach SUPER Peer: $ip_address$domain:$port_number/$subfolder", "TC");

								} // End first valid range check

							} // End second valid range check
						
						} // End Super Peer Check

					} // End blank data ahead check
//************************************************************

					$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 2000000, "transclerk.php?action=transaction_data&block_number=$block_number");
					$tc = 1;

					if(empty($poll_peer) == TRUE)
					{
						// Add failure points to the peer in case further issues
						modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
					}					

					while(empty($poll_peer) == FALSE)
					{
						$transaction_timestamp = filter_sql(find_string("-----timestamp$tc=", "-----public_key_from$tc", $poll_peer));
						$transaction_public_key_from = find_string("-----public_key_from$tc=", "-----public_key_to$tc", $poll_peer);
						$transaction_public_key_to = find_string("-----public_key_to$tc=", "-----crypt1data$tc", $poll_peer);
						$transaction_crypt1 = filter_sql(find_string("-----crypt1data$tc=", "-----crypt2data$tc", $poll_peer));
						$transaction_crypt2 = filter_sql(find_string("-----crypt2data$tc=", "-----crypt3data$tc", $poll_peer));
						$transaction_crypt3 = filter_sql(find_string("-----crypt3data$tc=", "-----hash$tc", $poll_peer));
						$transaction_hash = filter_sql(find_string("-----hash$tc=", "-----attribute$tc", $poll_peer));
						$transaction_attribute = find_string("-----attribute$tc=", "-----end$tc", $poll_peer);

						if(empty($transaction_public_key_from) == TRUE && empty($transaction_public_key_to) == TRUE)
						{
							// No more data, break while loop
							break;
						}

						$transaction_public_key_from = filter_sql(base64_decode($transaction_public_key_from));
						$transaction_public_key_to = filter_sql(base64_decode($transaction_public_key_to));

						$found_duplicate = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `timestamp` = '$transaction_timestamp' AND `public_key_from` = '$transaction_public_key_from' AND `hash` = '$transaction_hash' LIMIT 1"),0,0);

						// Check for valid attribute
						if($transaction_attribute == "G" || $transaction_attribute == "T" || $transaction_attribute == "H")
						{
							if(empty($found_duplicate) == TRUE)
							{
								$sql = "INSERT INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
								VALUES ('$transaction_timestamp', '$transaction_public_key_from', '$transaction_public_key_to', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";

								if(mysql_query($sql) == TRUE)
								{
									// Flag for a re-check afterwards
									$double_check_block = TRUE;
								}
							}
						}

						$tc++;

					} // End while loop

				}//End Database clear block check

				if($double_check_counter != 0 && empty($poll_peer) == FALSE) // Don't run this check unless necessary
				{
					// Double check the new hash against the last block transanstion(s) in case of tampering
					$time3 = transaction_cycle(0 - $current_generation_block + 1 + $hash_number);
					$time4 = transaction_cycle(0 - $current_generation_block + 2 + $hash_number);
					$double_check_hash = mysql_result(mysql_query("SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,0);

					// Build Hash from previous transaction block data
					$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";
					$sql_result = mysql_query($sql);
					$sql_num_results = mysql_num_rows($sql_result);
					$build_hash = 0;

					for ($i = 0; $i < $sql_num_results; $i++)
					{
						$sql_row = mysql_fetch_array($sql_result);
						$build_hash .= $sql_row["hash"];
					}

					// Transaction(s) hash
					$build_hash = hash('sha256', $build_hash);

					if($double_check_hash != $build_hash)
					{
						// Hash is invalid, something is wrong, trigger another double check
						$double_check_block = TRUE;
					}

				} // End Double Check Active

			} // End Hash agree/disagree
			else
			{
				// Majority peers agree +1 to sync blocks
				$sync_block++;
			}

			if($double_check_block == TRUE && $double_check_counter < 2)
			{
				//Reset to run loop again
				if(empty($poll_peer) == FALSE) // Skip double checks on peers that don't respond
				{
					$double_check_counter++;
				}
				
				$hash_number--;
				$h--;
			}
			else if($double_check_block == TRUE && $double_check_counter >= 2)
			{
				// There is too much conflict between the peers
				$double_check_counter = 0;

				// Wipe this block and hope that a future check
				// will have the peer conflict resolved by polling different peers
				$sql = "DELETE QUICK FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2";
				mysql_query($sql);

				write_log("Too Much Peer Conflict for Transaction Cycle #$block_number. This will remain empty until repaired.", "TC");

				break; // Break loop because future transaction blocks won't compare without the previous being corrected
			}
			else
			{
				// Reset Failsafe Counter
				$double_check_counter = 0;
				// Log Repair Success
				if($block_number == $hash_number)
				{
					write_log("Repair For Transaction Cycle #$block_number Complete.", "TC");
					$transaction_repair_made = TRUE;
				}
			}

			$hash_number++;

		} // End for Loop - Hash check cycling
//***********************************************************************************
		// Store peer performance data for later tuning
		$peer_performance = (time() - $peer_performance);
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '$peer_performance' WHERE `main_loop_status`.`field_name` = 'peer_transaction_performance' LIMIT 1");
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '$new_peer_poll_blocks' WHERE `main_loop_status`.`field_name` = 'peer_transaction_start_blocks' LIMIT 1");

		if($sync_block == $hash_check_counter)
		{
			if($error_check_active == FALSE)
			{
				if($transaction_repair_made == TRUE)
				{
					write_log("Automatic History Check From Transaction Cycle #" . ($hash_number - ($hash_check_counter - 1)) . " to #" . $hash_number . " Completed With Repairs", "TC");
				}
				else
				{
					write_log("Automatic History Check Complete. No Errors Found from Transaction Cycle #" . ($hash_number - ($hash_check_counter - 1)) . " to #" . $hash_number, "TC");
				}

				// Reset Repair Notification Flag
				$transaction_repair_made = FALSE;
			}

			// The number of block checks equals the number in sync
			// so store the last block number in the database so that
			// the server will know where to start from on the next cycle
			if($foundation_block_check == 1)
			{
				$sql = "UPDATE `main_loop_status` SET `field_data` = '$hash_number' WHERE `main_loop_status`.`field_name` = 'foundation_block_check_start' LIMIT 1";
				mysql_query($sql);
				write_log("Foundation Check Complete at Block #$hash_number", "TC");
			}
			else
			{
				$sql = "UPDATE `main_loop_status` SET `field_data` = '$hash_number' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1";
				mysql_query($sql);
			}
		}

		if($error_check_active == TRUE 
			&& $foundation_block_check != 1 
			&& $sync_block == $hash_check_counter) // Reset transaction history hash after error check completes
		{
			$history_hash = transaction_history_hash();

			// Update database with new hash
			mysql_query("UPDATE `options` SET `field_data` = '$history_hash' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");

			// Reset error block
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'transaction_history_block_check' LIMIT 1");

			if($transaction_repair_made == TRUE)
			{
				write_log("Manual History Check From Transaction Cycle #$transaction_history_block_check to #" . ($transaction_history_block_check + $hash_check_counter - 1) . " Completed With Repairs", "TC");
			}
			else
			{
				write_log("Manual History Check Complete. No Errors Found with Transaction Cycle #$transaction_history_block_check to #" . ($transaction_history_block_check + $hash_check_counter - 1), "TC");
			}

			// Reset Repair Notification Flag
			$transaction_repair_made = FALSE;
		}

		// Flag that high speed peer checking should be used
		$transaction_multi = TRUE;
//***********************************************************************************
//***********************************************************************************
	} // End Transaction History Compare Tallies
	else
	{
		// Entire Transaction History in sync, reset block check start to 0
		$block_check_start = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data");		

		if($block_check_start > 0)
		{
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'block_check_back' LIMIT 1");

			// Reset Performance Data
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '10' WHERE `main_loop_status`.`field_name` = 'peer_transaction_performance' LIMIT 1");
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '10' WHERE `main_loop_status`.`field_name` = 'peer_transaction_start_blocks' LIMIT 1");
		}

		if(rand(1,4) == 2)
		{
			// Poll a random block from a random peer for random accuracy :)
			// Within the range of the current foundation block to now
			$current_foundation_block = foundation_cycle(0, TRUE) * 500;
			$random_block = rand($current_foundation_block, transaction_cycle(-1, TRUE));

			$sql = perm_peer_mode();
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			if($sql_num_results > 0)
			{
				$sql_row = mysql_fetch_array($sql_result);

				$ip_address = $sql_row["IP_Address"];
				$domain = $sql_row["domain"];
				$subfolder = $sql_row["subfolder"];
				$port_number = $sql_row["port_number"];

				$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 65, "transclerk.php?action=block_hash&block_number=$random_block");

				if(empty($poll_peer) == TRUE)
				{
					// Add failure points to the peer in case further issues
					modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
				}

				if(empty($poll_peer) == FALSE && strlen($poll_peer) > 60)
				{
					// Do a real hash compare
					$current_generation_block = transaction_cycle(0, TRUE);
					
					$time1 = transaction_cycle(0 - $current_generation_block + $random_block);
					$time2 = transaction_cycle(0 - $current_generation_block + 1 + $random_block);	

					$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

					$sql_result = mysql_query($sql);
					$sql_num_results = mysql_num_rows($sql_result);
					$random_hash_build = 0;

					if($sql_num_results > 0)
					{
						for ($i = 0; $i < $sql_num_results; $i++)
						{
							$sql_row = mysql_fetch_array($sql_result);
							$random_hash_build .= $sql_row["hash"];
						}		
					}

					$random_hash_build = hash('sha256', $random_hash_build);

					if($poll_peer !== $random_hash_build && empty($poll_peer) == FALSE)
					{
						// Something is wrong, transaction history has an error.
						// Schedule a check in case the peer has an error and not us.
						mysql_query("UPDATE `main_loop_status` SET `field_data` = '$random_block' WHERE `main_loop_status`.`field_name` = 'transaction_history_block_check' LIMIT 1");

						write_log("This Peer ($ip_address$domain) Reports that My Transaction Block #$random_block is Invalid.</br>Will Double Check with other Peers before making any corrections.", "TC");
					}
				} // End empty poll check
			} // End if/then record count check
		} // Random chance check

	} // End else

//***********************************************************************************

	} // End if/then check for processing for more than 3 records - live database

//***********************************************************************************	
} // End if/then time check

//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active' LIMIT 1");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'transclerk_last_heartbeat' LIMIT 1");

//**********
// Start working right away when getting
// transaction history in sync with the network
if($transaction_multi == TRUE)
{
	sleep(1); // Busy updating
}
else
{
	sleep(10);
}

//***********************************************************************************
} // End Infinite Loop
?>
