<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(120);
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
$ip = mysql_result(mysql_query("SELECT * FROM `ip_banlist` WHERE `ip` = '" . $_SERVER['REMOTE_ADDR'] . "' LIMIT 1"),0,0);

if(empty($ip) == FALSE)
{
	// Sorry, your IP address has been banned :(
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction history hash poll
if($_GET["action"] == "history_hash")
{
	$current_history_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_history_hash' LIMIT 1"),0,"field_data");

	echo $current_history_hash;

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'TR')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer block hash poll
if($_GET["action"] == "block_hash" && $_GET["block_number"] >= 0)
{
	$block_number = intval($_GET["block_number"]);

	$current_generation_block = transaction_cycle(0, TRUE);
	
	$time1 = transaction_cycle(0 - $current_generation_block + 1 + $block_number);
	$time2 = transaction_cycle(0 - $current_generation_block + 2 + $block_number);	

	$hash = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 AND `attribute` = 'H' LIMIT 1"),0,"hash");

	echo $hash;

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'TR')");
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
			
			echo "-----timestamp$c=" . $sql_row["timestamp"] . "-----public_key_from$c=" . base64_encode($sql_row["public_key_from"]) . "-----public_key_to$c=" . base64_encode($sql_row["public_key_to"]);
			echo "-----crypt1data$c=" . $sql_row["crypt_data1"] . "-----crypt2data$c=" . $sql_row["crypt_data2"] . "-----crypt3data$c=" . $sql_row["crypt_data3"] . "-----hash$c=" . $sql_row["hash"];
			echo "-----attribute$c=" . $sql_row["attribute"] . "-----end$c";			

			$c++;
		}		
	}

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'TR')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
$loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,"field_data"));

// Check if loop is already running
if($loop_active == 0)
{
	// Set the working status of 1
	$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active' LIMIT 1";
	mysql_query($sql);
}
else
{
	// Loop called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************

$current_generation_cycle = transaction_cycle(0);
$next_generation_cycle = transaction_cycle(1);

$current_generation_block = transaction_cycle(0, TRUE);

$foundation_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data"));

// Can we work on the transactions in the database?
// Not allowed 30 seconds before and 30 seconds after generation cycle.
if(($next_generation_cycle - time()) > 30 && (time() - $current_generation_cycle) > 30 && $foundation_active == 0)
{
	// Check if the transaction history is blank or not (either from reset or new setup)
	$sql = "SELECT * FROM `transaction_history` LIMIT 5";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$generation_arbitrary = ARBITRARY_KEY;
//***********************************************************************************
	if($sql_num_results == 0) //New or blank transaction history
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
		$beginning_transaction = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `public_key_from` = '$generation_arbitrary' AND `hash` = '$generation_arbitrary' LIMIT 1"),0,"timestamp");

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
				$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $first_generation_cycle AND `timestamp` < $second_generation_cycle";

				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);
				$hash = 0;

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$hash = $hash . $sql_row["hash"];
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
			$hash_check = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `timestamp` = '$second_generation_cycle' AND `attribute` = 'H' LIMIT 1"),0,"hash");

			// Now let's check the results to make sure they match what should be expected
			if($hash_check == SHA256TEST)
			{
				// Passed final hash checking.
				// Unlock Treasurer script to allow live processing
				activate(TREASURER, 1);
				
				// Start a block rebuild from this since a new database is going to be far
				// behind the history of the other active peers
				$sql = "UPDATE `options` SET `field_data` = '3' WHERE `options`.`field_name` = 'block_check_start' LIMIT 1";
				mysql_query($sql);
			}
			else
			{
				write_log("Server Failed Initial Encryption Generation and Verification Testing", "TR");
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
		$transaction_history_block_check = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_history_block_check' LIMIT 1"),0,"field_data"));
		$foundation_block_check = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'foundation_block_check' LIMIT 1"),0,"field_data"));

		if($transaction_history_block_check != 0 || $foundation_block_check == 1)
		{
			//A random block check came up wrong, do a single error check sweep
			$error_check_active = TRUE;

			// Change hash to mismatch on purpose
			$current_history_hash = "ERROR_CHECK";

			if($transaction_history_block_check > 0 && $foundation_block_check == 0)
			{
				write_log("Starting History Check from Block #$transaction_history_block_check", "TC");
			}
			else
			{
				$foundation_block_check_start = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'foundation_block_check_start' LIMIT 1"),0,"field_data"));
				write_log("Resuming History Check from Block #$foundation_block_check_start", "TC");
			}

			// Update database with ERROR_CHECK hash
			$sql = "UPDATE `options` SET `field_data` = '$current_history_hash' WHERE `field_name` = 'transaction_history_hash' LIMIT 1";
			mysql_query($sql);
		}
		else
		{
			$total = mysql_query("SELECT COUNT(*) FROM `transaction_history`");
			$total = mysql_fetch_array($total); 
			$hash = $total[0];

			$previous_foundation_block = foundation_cycle(-1, TRUE);
			$current_foundation_cycle = foundation_cycle(0);
			$next_foundation_cycle = foundation_cycle(1);			
			$current_history_foundation = mysql_result(mysql_query("SELECT * FROM `transaction_foundation` WHERE `block` = $previous_foundation_block LIMIT 1"),0,"hash");

			$hash = $hash . $current_history_foundation;

			$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $current_foundation_cycle AND `timestamp` < $next_foundation_cycle AND `attribute` = 'H' ORDER BY `timestamp`";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$hash = $hash . $sql_row["hash"];
			}	

			$history_hash = hash('md5', $hash);

			if($history_hash == $current_history_hash)
			{
				// Already in database, no need to update
			}
			else
			{
				$current_history_hash = $history_hash;
				
				// Update database with new hash
				$sql = "UPDATE `options` SET `field_data` = '$history_hash' WHERE `field_name` = 'transaction_history_hash' LIMIT 1";
				mysql_query($sql);
			}
		}
//***********************************************************************************	
//***********************************************************************************
	// Does my current history hash match all my peers?
	// Ask all of my active peers
	ini_set('default_socket_timeout', 3); // Timeout for request in seconds
	ini_set('user_agent', 'Timekoin Server (Transclerk) v' . TIMEKOIN_VERSION);
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete

	$sql = "SELECT * FROM `active_peer_list`";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$trans_list_hash_match = 0;
	$trans_list_hash_different = 0;
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

			$poll_peer = filter_sql(file_get_contents("http://$site_address:$port_number/$subfolder/transclerk.php?action=history_hash", FALSE, $context, NULL, 65));
			
			if($current_history_hash === $poll_peer)
			{
				$trans_list_hash_match++;
			}
			else
			{
				if(empty($poll_peer) == FALSE && strlen($poll_peer) > 5 && $poll_peer != "ERROR_CHECK")
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
	// Compare tallies
	if($trans_list_hash_different > $trans_list_hash_match)
	{
		//More than 50% of the active peers have a different transaction history list, start comparing your
		//transaction list with one that is different
		$hash_check_counter = 15; // How many blocks to check at each cycle (default)

		if($error_check_active == FALSE)
		{
			$hash_number = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data"));

			if($hash_number == 0)
			{
				// A new check, start just 10 blocks from the end to avoid checking the entire history
				// from the beginning
				$hash_number = transaction_cycle(-10, TRUE);
			}
			else
			{
				write_log("Resuming History Check from Block #$hash_number", "TC");
			}
		}
		else
		{
			if($foundation_block_check == 1)
			{
				// Start from the block that the foundation begins
				$foundation_block_check_start = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'foundation_block_check_start' LIMIT 1"),0,"field_data");
				$foundation_block_check_end = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'foundation_block_check_end' LIMIT 1"),0,"field_data");

				if($foundation_block_check_start > $foundation_block_check_end)
				{
					// Check is finished
					$sql = "UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'foundation_block_check' LIMIT 1";
					mysql_query($sql);

					// Push forward any previous block checks that were in progress
					$sql = "UPDATE `options` SET `field_data` = '$foundation_block_check_start' WHERE `options`.`field_name` = 'block_check_start' LIMIT 1";
					mysql_query($sql);

					// Reset block back counter
					$sql = "UPDATE `options` SET `field_data` = '1' WHERE `options`.`field_name` = 'block_check_back' LIMIT 1";
					mysql_query($sql);

					$hash_number = $foundation_block_check_start;
				}
				else
				{
					$hash_check_counter = 20;
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
				$sql = "DELETE FROM `transaction_history` WHERE `timestamp` >= $current_generation_cycle AND `timestamp` < $next_generation_cycle";
				mysql_query($sql);
				
				// How many times have this checked reached the end and still not fixed the transaction history?
				$hash_number_back_database = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'block_check_back' LIMIT 1"),0,"field_data");
				
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
					mysql_query("UPDATE `options` SET `field_data` = '1' WHERE `options`.`field_name` = 'block_check_back' LIMIT 1");
				}
				else
				{
					// Increment back counter in case this was not far back enough
					// and it reaches this point again
					$hash_number_back_database++;
					mysql_query("UPDATE `options` SET `field_data` = '$hash_number_back_database' WHERE `options`.`field_name` = 'block_check_back' LIMIT 1");
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

			$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);
			$my_hash = 0;

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$my_hash = $my_hash . $sql_row["hash"];
			}		

			$my_hash = hash('sha256', $my_hash);

			for ($i = 1; $i < $trans_list_hash_different + 1; $i++)
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

				// Start with the first hash and work our way up
				$poll_peer = filter_sql(file_get_contents("http://$site_address:$port_number/$subfolder/transclerk.php?action=block_hash&block_number=$hash_number", FALSE, $context, NULL, 65));

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
				$sql = "DELETE FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2";

				if(mysql_query($sql) == FALSE)
				{
					//Something didn't work
				}
				else
				{
					$peer_number = rand(1,$hash_disagree);// Random peer from array
					$ip_address = $hash_disagree_peers["ip_address$peer_number"];
					$domain = $hash_disagree_peers["domain$peer_number"];
					$subfolder = $hash_disagree_peers["subfolder$peer_number"];
					$port_number = $hash_disagree_peers["port_number$peer_number"];
					$block_number = $hash_number;

					if(empty($domain) == TRUE)
					{
						$site_address = $ip_address;
					}
					else
					{
						$site_address = $domain;
					}

					$poll_peer = filter_sql(file_get_contents("http://$site_address:$port_number/$subfolder/transclerk.php?action=transaction_data&block_number=$block_number", FALSE, $context, NULL, 200000));

					$tc = 1;

					while(empty($poll_peer) == FALSE)
					{
						$transaction_timestamp = find_string("-----timestamp$tc=", "-----public_key_from$tc", $poll_peer);
						$transaction_public_key_from = find_string("-----public_key_from$tc=", "-----public_key_to$tc", $poll_peer);
						$transaction_public_key_to = find_string("-----public_key_to$tc=", "-----crypt1data$tc", $poll_peer);
						$transaction_crypt1 = find_string("-----crypt1data$tc=", "-----crypt2data$tc", $poll_peer);
						$transaction_crypt2 = find_string("-----crypt2data$tc=", "-----crypt3data$tc", $poll_peer);
						$transaction_crypt3 = find_string("-----crypt3data$tc=", "-----hash$tc", $poll_peer);
						$transaction_hash = find_string("-----hash$tc=", "-----attribute$tc", $poll_peer);
						$transaction_attribute = find_string("-----attribute$tc=", "-----end$tc", $poll_peer);

						if(empty($transaction_public_key_from) == TRUE && empty($transaction_public_key_to) == TRUE)
						{
							// No more data, break while loop
							break;
						}

						$transaction_public_key_from = base64_decode($transaction_public_key_from);
						$transaction_public_key_to = base64_decode($transaction_public_key_to);				

						$found_duplicate = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `timestamp` = '$transaction_timestamp' AND `public_key_from` = '$transaction_public_key_from' AND `hash` = '$transaction_hash' LIMIT 1"),0,0);

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

				// Double check the new hash against the last block transanstion(s) in case of tampering
				$time3 = transaction_cycle(0 - $current_generation_block + 1 + $hash_number);
				$time4 = transaction_cycle(0 - $current_generation_block + 2 + $hash_number);
				$double_check_hash = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,"hash");

				// Build Hash from previous transaction block data
				$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);
				$build_hash = 0;

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$build_hash = $build_hash . $sql_row["hash"];
				}

				// Transaction(s) hash
				$build_hash = hash('sha256', $build_hash);

				if($double_check_hash == $build_hash)
				{
					// Hash matches up to previous transaction data
				}
				else
				{
					// Hash is invalid, something is wrong, trigger another double check
					$double_check_block = TRUE;
				}

			} // End Hash agree/disagree
			else
			{
				// Majority peers agree +1 to sync blocks
				$sync_block++;
			}

			if($double_check_block == TRUE && $double_check_counter < 2)
			{
				//Reset to run loop again
				$hash_number--;
				$h--;
				$double_check_counter++;
			}
			else if($double_check_block == TRUE && $double_check_counter >= 2)
			{
				// There is too much conflict between the peers
				$double_check_counter = 0;

				// Wipe this block and hope that a future check
				// will have the peer conflict resolved
				$sql = "DELETE FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2";
				mysql_query($sql);

				write_log("Too Much Peer Conflict for Block #$block_number. This block will remain empty until repaired.", "TC");				
			}
			else
			{
				//Reset failsafe counter
				$double_check_counter = 0;
			}

			$hash_number++;

		} // End for Loop - Hash check cycling
//***********************************************************************************
		if($sync_block == $hash_check_counter)
		{
			if($error_check_active == FALSE)
			{
				write_log("Automatic History Check Complete. No Errors Found with Block #" . ($hash_number - ($hash_check_counter - 1)) . " to Block #" . $hash_number, "TC");
			}
			
			// The number of block checks equals the number in sync
			// so store the last block number in the database so that
			// the server will know where to start from on the next cycle
			if($foundation_block_check == 1)
			{
				$sql = "UPDATE `options` SET `field_data` = '$hash_number' WHERE `options`.`field_name` = 'foundation_block_check_start' LIMIT 1";
				mysql_query($sql);
			}
			else
			{
				$sql = "UPDATE `options` SET `field_data` = '$hash_number' WHERE `options`.`field_name` = 'block_check_start' LIMIT 1";
				mysql_query($sql);
			}
		}

		if($error_check_active == TRUE 
			&& $foundation_block_check != 1 
			&& $sync_block == $hash_check_counter) // Reset transaction history hash after error check completes
		{
			$total = mysql_query("SELECT COUNT(*) FROM `transaction_history`");
			$total = mysql_fetch_array($total); 
			$hash = $total[0];

			$previous_foundation_block = foundation_cycle(-1, TRUE);
			$current_foundation_cycle = foundation_cycle(0);
			$next_foundation_cycle = foundation_cycle(1);			
			$current_history_foundation = mysql_result(mysql_query("SELECT * FROM `transaction_foundation` WHERE `block` = $previous_foundation_block LIMIT 1"),0,"hash");

			$hash = $hash . $current_history_foundation;

			$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $current_foundation_cycle AND `timestamp` < $next_foundation_cycle AND `attribute` = 'H' ORDER BY `timestamp`";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$hash = $hash . $sql_row["hash"];	
			}	

			$history_hash = hash('md5', $hash);

			// Update database with new hash
			$sql = "UPDATE `options` SET `field_data` = '$history_hash' WHERE `field_name` = 'transaction_history_hash' LIMIT 1";
			mysql_query($sql);

			// Reset error block
			$sql = "UPDATE `options` SET `field_data` = '0' WHERE `field_name` = 'transaction_history_block_check' LIMIT 1";
			mysql_query($sql);

			write_log("Manual History Check Complete. No Errors Found with Block #$transaction_history_block_check to Block #" . ($transaction_history_block_check + $hash_check_counter - 1), "TC");
		}

//***********************************************************************************
	} // End Compare Tallies
	else
	{
		// Entire Transaction History in sync, reset block check start to 0
		$block_check_start = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data");		

		if($block_check_start > 0)
		{
			$sql = "UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'block_check_start' LIMIT 1";
			mysql_query($sql);

			$sql = "UPDATE `options` SET `field_data` = '1' WHERE `options`.`field_name` = 'block_check_back' LIMIT 1";
			mysql_query($sql);
		}

		if(rand(1,4) == 2)
		{
			// Poll a random block from a random peer for random accuracy :)
			// Within the range of the current foundation block to now
			$current_foundation_block = foundation_cycle(0, TRUE) * 500;

			$random_block = rand($current_foundation_block, transaction_cycle(-1, TRUE));

			$sql = "SELECT * FROM `active_peer_list` ORDER BY RAND()";

			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			if($sql_num_results > 0)
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

				// Start with this hash
				$poll_peer = filter_sql(file_get_contents("http://$site_address:$port_number/$subfolder/transclerk.php?action=block_hash&block_number=$random_block", FALSE, $context, NULL, 65));

				if(empty($poll_peer) == FALSE && strlen($poll_peer) > 32)
				{
					// Do a real hash compare
					$current_generation_block = transaction_cycle(0, TRUE);
					
					$time1 = transaction_cycle(0 - $current_generation_block + $random_block);
					$time2 = transaction_cycle(0 - $current_generation_block + 1 + $random_block);	

					$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

					$sql_result = mysql_query($sql);
					$sql_num_results = mysql_num_rows($sql_result);
					$random_hash_build = 0;

					if($sql_num_results > 0)
					{
						for ($i = 0; $i < $sql_num_results; $i++)
						{
							$sql_row = mysql_fetch_array($sql_result);
							$random_hash_build = $random_hash_build . $sql_row["hash"];
						}		
					}

					$random_hash_build = hash('sha256', $random_hash_build);

					if($poll_peer === $random_hash_build)
					{
						// All is well in the transaction history
					}
					else if($poll_peer !== $random_hash_build && empty($poll_peer) == FALSE)
					{
						// Something is wrong, transaction history has an error.
						// Schedule a check in case the peer has an error and not us.
						$sql = "UPDATE `options` SET `field_data` = '$random_block' WHERE `field_name` = 'transaction_history_block_check' LIMIT 1";
						mysql_query($sql);

						write_log("One of my Peers ($site_address) Reports that My Block #$random_block is Wrong. Will Double Check with other Peers before making any corrections.", "TC");
					}
				} // End empty poll check
			} // End if/then record count check
		} // Random chance check

	} // End else

//***********************************************************************************

	} // End if/then check for processing for more than 3 records

//***********************************************************************************	
} // End if/then time check

//***********************************************************************************
//***********************************************************************************
// Script finished, set status to 0
$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active' LIMIT 1";
mysql_query($sql);

// Record when this script finished
$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'transclerk_last_heartbeat' LIMIT 1";
mysql_query($sql);

?>
