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
	echo mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'transaction_history_hash' LIMIT 1"),0,0);

	// Log inbound IP activity
	log_ip("TC", 1);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer super peer poll
if($_GET["action"] == "super_peer")
{
	echo mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'super_peer' LIMIT 1"),0,0);

	// Log inbound IP activity
	log_ip("TC", scale_trigger(200));
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
	log_ip("TC", scale_trigger(500));
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
	log_ip("TC", scale_trigger(500), TRUE);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// External Flood Protection
	log_ip("TC", scale_trigger(4));
//***********************************************************************************
// First time run check
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,0);
$last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transclerk_last_heartbeat' LIMIT 1"),0,0);

if($loop_active === FALSE && $last_heartbeat == 1)
{
	// Create record to begin loop
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('transclerk_heartbeat_active', '0')");
	// Update timestamp for starting
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'transclerk_last_heartbeat' LIMIT 1");
}
else
{
	// Record already exist, called while another process of this script
	// was already running.
	exit;
}

ini_set('default_socket_timeout', 3); // Timeout for request in seconds
ini_set('user_agent', 'Timekoin Server (Transclerk) v' . TIMEKOIN_VERSION);

while(1) // Begin Infinite Loop
{
set_time_limit(300);
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,0);

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

$foundation_active = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,0));
$treasurer_status = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,0));

// Can we work on the transactions in the database?
// Not allowed 30 seconds before and 30 seconds after transaction cycle.
if(($next_generation_cycle - time()) > 30 && (time() - $current_generation_cycle) > 30 && $foundation_active == 2 && $treasurer_status == 2)
{
	// Check if the transaction history is blank or not (either from reset or new setup)
	$trans_record_count = mysql_result(mysql_query("SELECT COUNT(*) FROM `transaction_history`"),0);
	$generation_arbitrary = ARBITRARY_KEY;
//***********************************************************************************
	if($trans_record_count == 0 && $trans_record_count !== FALSE) //New or blank transaction history
	{
		// Start by inserting the beginning arbitrary transaction from which all others will hash against 
		$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from` ,`public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
	VALUES ('" . TRANSACTION_EPOCH . "', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', 'B')";
	
		if(mysql_query($sql) == TRUE)
		{
			// Lock Treasurer script to prevent transaction processing during the initial phase
			activate(TREASURER, 0);
		}
	}
//***********************************************************************************
	if($trans_record_count > 0 && $trans_record_count < 4) // Write out some test hashes and compare to verify sha256 accuracy
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
				$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $first_generation_cycle AND `timestamp` < $second_generation_cycle";

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
				
				// Start a transaction history rebuild from this since a new database is going to be far
				// behind the history of the other active peers
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '3' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");
			}
			else
			{
				write_log("Server Failed Initial Encryption Generation and Verification Testing", "TC");
				$failed_crypt_test = TRUE;
			}
		}
	} // End database preparation and hash testing
//***********************************************************************************
	// 4 or more transactions on a live database, let the magic begin
	if($trans_record_count > 3 && $failed_crypt_test == FALSE)
	{	
	//***********************************************************************************		
	// Update transaction history hash
		$current_history_hash = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'transaction_history_hash' LIMIT 1"),0,0);
		$transaction_history_block_check = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transaction_history_block_check' LIMIT 1"),0,0));
		$foundation_block_check = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check' LIMIT 1"),0,0));

		if($transaction_history_block_check != 0 || $foundation_block_check == 1)
		{
			//A random block check came up wrong, do a single error check sweep
			$error_check_active = TRUE;

			if($transaction_history_block_check > 0 && $foundation_block_check == 0)
			{
				write_log("Starting History Check from Transaction Cycle #$transaction_history_block_check", "TC");
				// Change hash to mismatch on purpose
				$current_history_hash = "ERROR_CHECK";
			}
			else
			{
				$foundation_block_check_start = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check_start' LIMIT 1"),0,0));
				write_log("Resuming History Check from Transaction Cycle #$foundation_block_check_start", "TC");

				// Change hash to mismatch on purpose
				$current_history_hash = "FOUNDATION_CHECK";
			}

			// Update database with Verbose Error hash
			mysql_query("UPDATE `options` SET `field_data` = '$current_history_hash' WHERE `field_name` = 'transaction_history_hash' LIMIT 1");
		}
		else
		{
			$error_check_active = FALSE;

			$history_hash = transaction_history_hash();

			if($history_hash !== $current_history_hash)
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

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 32, "transclerk.php?action=history_hash");

			if($poll_peer == "PROC" || $poll_peer == "ERROR_CHECK" || $poll_peer == "FOUNDATION_CHECK")
			{
				// Add *less* failure points to the peer for slower transaction processing or error checking own database
				modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 1);
			}
			else if(strlen($poll_peer) != 32)
			{
				// Peer Not Responding Properly
				modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 2);
			}

			if($current_history_hash === $poll_peer)
			{
				$trans_list_hash_match++;
			}
			else
			{
				if(strlen($poll_peer) == 32) // Only count valid data returns from polling
				{
					$trans_list_hash_different++;

					$hash_different["ip_address$trans_list_hash_different"] = $ip_address;
					$hash_different["domain$trans_list_hash_different"] = $domain;
					$hash_different["subfolder$trans_list_hash_different"] = $subfolder;
					$hash_different["port_number$trans_list_hash_different"] = $port_number;				
				}
			}
		} // End for Loop

		if($trans_list_hash_match == 0 && $trans_list_hash_different == 0)
		{
			// No peers are responding
			write_log("No Peers Are Responding to History Hash Polling", "TC");
		}

	} // End number of results check
	else
	{
		write_log("No Active Peers to Poll", "TC");
		$trans_list_hash_different = 0;
		$trans_list_hash_match = 0;
	}
//***********************************************************************************
//***********************************************************************************
	// Compare transaction history tallies
	if($trans_list_hash_different > $trans_list_hash_match)
	{
		//More than 50% of the active peers have a different transaction history list, start comparing your
		//transaction list with one that is different
		$hash_check_counter = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_start_blocks' LIMIT 1"),0,0));
		$peer_transaction_performance = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_performance' LIMIT 1"),0,0));

		//Scale the amount of transaction cycles to check based on the last peer performance reading
		if($peer_transaction_performance <= 10)
		{
			if($hash_check_counter < 50) // Cap limit 50
			{
				$new_peer_poll_blocks = $hash_check_counter + 1;
			}
			else
			{
				// Upper Limit Reached
				// Really super fast peers? Keep at 50, just in case.
				$new_peer_poll_blocks = 50;
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
				$foundation_block_check_start = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check_start' LIMIT 1"),0,0);
				$foundation_block_check_end = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check_end' LIMIT 1"),0,0);

				if($foundation_block_check_start > $foundation_block_check_end)
				{
					// Check is finished
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'foundation_block_check' LIMIT 1");
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'foundation_block_check_start' LIMIT 1");
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'foundation_block_check_end' LIMIT 1");

					// Reset any previous block checks that were in progress
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'transaction_history_block_check' LIMIT 1");					

					// Reset block back counter
					mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'block_check_back' LIMIT 1");

					// Foundation Data Repair Complete
					write_log("Foundation Data Repair Complete", "TC");

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
				$hash_number_back_database = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'block_check_back' LIMIT 1"),0,0);
				
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

				$hash_check_counter = 1;  // Reset to check another 1 block forward
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

			$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash` ASC";

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

				$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 64, "transclerk.php?action=block_hash&block_number=$hash_number");

				if(empty($poll_peer) == TRUE)
				{
					// Add failure points to the peer in case further issues
					modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
				}

				if($my_hash === $poll_peer)
				{
					$hash_agree++;
				}
				else if($my_hash !== $poll_peer && strlen($poll_peer) == 64)
				{
					$hash_disagree++;

					$hash_disagree_peers["ip_address$hash_disagree"] = $ip_address;
					$hash_disagree_peers["domain$hash_disagree"] = $domain;
					$hash_disagree_peers["subfolder$hash_disagree"] = $subfolder;
					$hash_disagree_peers["port_number$hash_disagree"] = $port_number;					
				}

			} // End For Loop

			if($hash_agree == 0 && $hash_disagree == 0)
			{
				// No peers are responding
				write_log("No Peers Are Responding to Transaction Cycle Hash Polling", "TC");
			}
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

						if($poll_peer === FALSE)
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
									if($poll_peer == 1) // Sanity check on cycles allowed to download
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
									set_time_limit(300); // Reset script processing time
									$super_transaction_cycle = $block_number;
									$super_peer_insert;
									$super_peer_record_count = 0;

									while($super_transaction_cycle < $block_number + $super_peer_cycles)
									{
										$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 5000000, "transclerk.php?action=transaction_data&block_number=$super_transaction_cycle"));

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

										$one_hash_limit = 0; // Only one hash per cycle allowed, extra/duplicates are ignored
										$transaction_time_range_start = TRANSACTION_EPOCH + ($super_transaction_cycle * 300);// Valid Transaction Start Time Range
										$transaction_time_range_end = TRANSACTION_EPOCH + (($super_transaction_cycle + 1) * 300);// Valid Transaction End Time Range

										while(empty($poll_peer) == FALSE)
										{
											$transaction_timestamp = intval(find_string("-----timestamp$tc=", "-----public_key_from$tc", $poll_peer));
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

											$transaction_public_key_from = filter_sql(base64_decode($transaction_public_key_from));
											$transaction_public_key_to = filter_sql(base64_decode($transaction_public_key_to));

											// Time-stamp range checking
											if($transaction_timestamp < $transaction_time_range_start || $transaction_timestamp >= $transaction_time_range_end)
											{
												// This data is not in the correct time range, needs to be ignored
												$transaction_attribute = "INVALID";
											}

											// Check for valid attribute
											if($transaction_attribute == "G" || $transaction_attribute == "T" || $transaction_attribute == "H")
											{
												if($transaction_attribute == "G" || $transaction_attribute == "T")
												{
													// Check that verification hash for transaction data matches
													$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);													

													// Find destination public key
													$public_key_to_1 = tk_decrypt($transaction_public_key_from, base64_decode($transaction_crypt1));
													$public_key_to_2 = tk_decrypt($transaction_public_key_from, base64_decode($transaction_crypt2));
													$internal_public_key_to = $public_key_to_1 . $public_key_to_2;

													if($transaction_hash == $crypt_hash_check && 
														strlen($transaction_public_key_from) > 300 && 
														strlen($transaction_public_key_to) > 300 && 
														$internal_public_key_to == $transaction_public_key_to)
													{
														// Continue with duplicate record test
														$found_duplicate = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `timestamp` = '$transaction_timestamp' AND `hash` = '$transaction_hash' LIMIT 1"),0,0);
													}
													else
													{
														// Use duplicate test to fail this transaction data
														$found_duplicate = "INVALID";
													}
												}
												else
												{
													// Transaction Cycle Hash, continue duplicate record test
													$one_hash_limit++;

													if($one_hash_limit == 1)// First Transaction Hash
													{
														// First Hash in Transaction Cycle Data
														$found_duplicate = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `timestamp` = '$transaction_timestamp' AND `hash` = '$transaction_hash' LIMIT 1"),0,0);
													}
													else
													{
														// Another Hash in the same Transaction Cycle Data?
														$found_duplicate = "DUPHASH";
													}
												}

												if(empty($found_duplicate) == TRUE)
												{
													// Limit Max Query String to 1MB (1,024,000 bytes)
													// Many DB have this limit by default and most users may not know how to set it higher :(
													if(strlen($super_peer_insert . ",('$transaction_timestamp', '" . filter_public_key($transaction_public_key_from) . "', '" . filter_public_key($transaction_public_key_to) . "', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')") <= 1024000)
													{
														// Query still under 1MB in size
														$super_peer_record_count++;

														if($super_peer_record_count == 1)
														{
															$super_peer_insert = "('$transaction_timestamp', '" . filter_public_key($transaction_public_key_from) . "', '" . filter_public_key($transaction_public_key_to) . "', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
														}
														else
														{
															$super_peer_insert.= ",('$transaction_timestamp', '" . filter_public_key($transaction_public_key_from) . "', '" . filter_public_key($transaction_public_key_to) . "', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
														}
													}
													else
													{
														// Max query size reached, write to database
														// Do mass record insert
														if(mysql_query("INSERT INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES " . $super_peer_insert) == TRUE)
														{
															write_log("Wrote $super_peer_record_count Records From SUPER Peer: $ip_address$domain:$port_number/$subfolder", "TC");
														}
														else
														{
															write_log("Database Insert FAILED From SUPER Peer: $ip_address$domain:$port_number/$subfolder", "TC");
														}

														// Clear variable from RAM
														unset($super_peer_insert);

														// Reset Record Counter
														$super_peer_record_count = 1;

														// Start New INSERT Query
														$super_peer_insert.= "('$transaction_timestamp', '" . filter_public_key($transaction_public_key_from) . "', '" . filter_public_key($transaction_public_key_to) . "', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
													}
												}
											}

											$tc++;

										} // End while loop

										// Jump ahead transaction cycle checking start position for next cycle
										mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . ($super_transaction_cycle - 1) . "' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");										
										
										$super_transaction_cycle++;

									} // Transaction Cycles Ahead Loop

									// Do mass record insert if query is finished
									if(empty($super_peer_insert) == FALSE)
									{
										if(mysql_query("INSERT INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES " . $super_peer_insert) == TRUE)
										{
											write_log("Wrote $super_peer_record_count Records From SUPER Peer: $ip_address$domain:$port_number/$subfolder", "TC");
										}
										else
										{
											write_log("Database Insert FAILED From SUPER Peer: $ip_address$domain:$port_number/$subfolder", "TC");
										}
									}

									write_log("Detach SUPER Peer: $ip_address$domain:$port_number/$subfolder", "TC");

									// Clear variable from RAM
									unset($super_peer_insert);

								} // End first valid range check

							} // End second valid range check
						
						} // End Super Peer Check

					} // End blank data ahead check to allow Super Peer
	//************************************************************

					$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 5000000, "transclerk.php?action=transaction_data&block_number=$block_number"));
					$tc = 1;

					if(empty($poll_peer) == TRUE)
					{
						// Add failure points to the peer in case further issues
						modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
					}					

					$norm_record_insert_counter = 0;
					$norm_record_insert = NULL;
					$one_hash_limit = 0; // Only one hash per cycle allowed, extra/duplicates are ignored
					$transaction_time_range_start = TRANSACTION_EPOCH + ($block_number * 300);// Valid Transaction Start Time Range
					$transaction_time_range_end = TRANSACTION_EPOCH + (($block_number + 1) * 300);// Valid Transaction End Time Range

					while(empty($poll_peer) == FALSE)
					{
						$transaction_timestamp = intval(find_string("-----timestamp$tc=", "-----public_key_from$tc", $poll_peer));
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

						$transaction_public_key_from = filter_sql(base64_decode($transaction_public_key_from));
						$transaction_public_key_to = filter_sql(base64_decode($transaction_public_key_to));

						// Time-stamp range checking
						if($transaction_timestamp < $transaction_time_range_start || $transaction_timestamp >= $transaction_time_range_end)
						{
							// This data is not in the correct time range, needs to be ignored
							$transaction_attribute = "INVALID";
						}

						// Check for valid attribute
						if($transaction_attribute == "G" || $transaction_attribute == "T" || $transaction_attribute == "H")
						{
							if($transaction_attribute == "G" || $transaction_attribute == "T")
							{
								// Check that verification hash for transaction data matches
								$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);													

								// Find destination public key
								$public_key_to_1 = tk_decrypt($transaction_public_key_from, base64_decode($transaction_crypt1));
								$public_key_to_2 = tk_decrypt($transaction_public_key_from, base64_decode($transaction_crypt2));
								$internal_public_key_to = filter_public_key($public_key_to_1 . $public_key_to_2);

								if($transaction_hash == $crypt_hash_check && 
									strlen($transaction_public_key_from) > 300 && 
									strlen($transaction_public_key_to) > 300 && 
									$internal_public_key_to == $transaction_public_key_to)
								{
									// Continue with duplicate record test
									$found_duplicate = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `timestamp` = '$transaction_timestamp' AND `hash` = '$transaction_hash' LIMIT 1"),0,0);
								}
								else
								{
									// Use duplicate test to fail this transaction data
									$found_duplicate = "INVALID";
								}
							}
							else
							{
								$one_hash_limit++; // First Transaction Hash

								if($one_hash_limit == 1)
								{
									// First Hash in Transaction Cycle Data
									$found_duplicate = mysql_result(mysql_query("SELECT timestamp FROM `transaction_history` WHERE `timestamp` = '$transaction_timestamp' AND `hash` = '$transaction_hash' LIMIT 1"),0,0);
								}
								else
								{
									// Another Hash in the same Transaction Cycle Data?
									$found_duplicate = "DUPHASH";
								}							
							}

							if(empty($found_duplicate) == TRUE) // No duplicate found
							{
								$norm_record_insert_counter++; // How many records are spooling up

								if($norm_record_insert_counter == 1)
								{
									$norm_record_insert = "('$transaction_timestamp', '" . filter_public_key($transaction_public_key_from) . "', '" . filter_public_key($transaction_public_key_to) . "', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
								}
								else
								{
									$norm_record_insert.= ",('$transaction_timestamp', '" . filter_public_key($transaction_public_key_from) . "', '" . filter_public_key($transaction_public_key_to) . "', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
								}

								if($norm_record_insert_counter >= 500)
								{
									// Insert Spooling Finished, write to database
									$sql = "INSERT INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES " . $norm_record_insert;

									if(mysql_query($sql) == TRUE)
									{
										// Flag for a re-check afterwards
										$double_check_block = TRUE;

										// Reset Counter
										$norm_record_insert_counter = 0;
										$norm_record_insert = NULL;
									}
								}
							}
						}

						$tc++;

					} // End while loop

					// Check for data to insert if the while loop was broken before 500 records
					if(empty($norm_record_insert) == FALSE)
					{
						// Still something left to insert
						$sql = "INSERT INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES " . $norm_record_insert;

						if(mysql_query($sql) == TRUE)
						{
							// Flag for a re-check afterwards
							$double_check_block = TRUE;
						}
					}
	//************************************************************
				}//End Database clear block check
	//************************************************************
				
				if($double_check_counter != 0 && empty($poll_peer) == FALSE) // Don't run this check unless necessary
				{
					// Double check the new hash against the last block transanstion(s) in case of tampering
					$time3 = transaction_cycle(0 - $current_generation_block + 1 + $hash_number);
					$time4 = transaction_cycle(0 - $current_generation_block + 2 + $hash_number);
					$double_check_hash = mysql_result(mysql_query("SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,0);

					// Build Hash from previous transaction block data
					$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash` ASC";
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
				// Do not assume that no responding peers is the same as agreement
				if($hash_agree > 0)
				{
					// Majority peers agree +1 to sync blocks
					$sync_block++;
				}
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

				write_log("Too Much Peer Conflict for Transaction Cycle #$block_number.<br>This cycle will remain empty until repaired with valid data.", "TC");

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
					write_log("Automatic History Check From Transaction Cycle #" . ($hash_number - $hash_check_counter) . " to #" . ($hash_number - 1) . " Completed With Repairs", "TC");

					// Reset Transction Hash Count Cache
					reset_transaction_hash_count();
				}
				else
				{
					write_log("Automatic History Check Complete.<br>No Errors Found from Transaction Cycle #" . ($hash_number - $hash_check_counter) . " to #" . ($hash_number - 1), "TC");
				}

				// Reset Repair Notification Flag
				$transaction_repair_made = FALSE;
			}

			// The number of block checks equals the number in sync
			// so store the last block number in the database so that
			// the server will know where to start from on the next cycle
			if($foundation_block_check == 1)
			{
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '$hash_number' WHERE `main_loop_status`.`field_name` = 'foundation_block_check_start' LIMIT 1");
				write_log("Foundation Repair Complete at Transaction #" . ($hash_number - 1), "TC");

				// Reset Transction Hash Count Cache
				reset_transaction_hash_count();
			}
			else
			{
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '$hash_number' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");
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
				write_log("Manual History Check From Transaction Cycle #" . ($hash_number - $hash_check_counter) . " to #" . ($hash_number - 1) . " Completed With Repairs", "TC");
				// Reset Transction Hash Count Cache
				reset_transaction_hash_count();
			}
			else
			{
				write_log("Manual History Check Complete.<br>No Errors Found with Transaction Cycle #" . ($hash_number - $hash_check_counter) . " to #" . ($hash_number - 1), "TC");
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
		$block_check_start = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,0);

		if($block_check_start > 0)
		{
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'block_check_back' LIMIT 1");

			// Reset Performance Data
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '10' WHERE `main_loop_status`.`field_name` = 'peer_transaction_performance' LIMIT 1");
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'peer_transaction_start_blocks' LIMIT 1");
		}

		if(rand(1,10) == 10) // Randomize to avoid spamming checks all the time
		{
			// Poll a random block from a random peer for random accuracy :)
			// Within the range of the current foundation block to now
			$current_foundation_block = foundation_cycle(0, TRUE) * 500;
			$random_block = rand($current_foundation_block, transaction_cycle(-1, TRUE));

			// Do a real hash compare
			$current_generation_block = transaction_cycle(0, TRUE);
			
			$time1 = transaction_cycle(0 - $current_generation_block + $random_block);
			$time2 = transaction_cycle(0 - $current_generation_block + 1 + $random_block);	

			$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash` ASC";

			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);
			$random_hash_build = 0;

			if($sql_num_results > 0)
			{
				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$random_hash_build.= $sql_row["hash"];
				}
			}

			$random_hash_build = hash('sha256', $random_hash_build);

			$sql = perm_peer_mode();
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			$target_number = intval((3 / 5) * $sql_num_results); // 3/5 of peers must disagree to trigger check
			$peer_disagree = 0;
			$peer_disagree_list = NULL;

			if($sql_num_results > 0)
			{
				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$ip_address = $sql_row["IP_Address"];
					$domain = $sql_row["domain"];
					$subfolder = $sql_row["subfolder"];
					$port_number = $sql_row["port_number"];
					$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 64, "transclerk.php?action=block_hash&block_number=$random_block");

					if(strlen($poll_peer) == 64)
					{
						if($poll_peer !== $random_hash_build)
						{
							$peer_disagree++;
							$peer_disagree_list.= "($ip_address$domain) ";

							if($peer_disagree >= $target_number)
							{
								// Something is wrong, transaction history has an error.
								// Schedule a check in case the peer has an error and not us.
								mysql_query("UPDATE `main_loop_status` SET `field_data` = '$random_block' WHERE `main_loop_status`.`field_name` = 'transaction_history_block_check' LIMIT 1");
								write_log("These Peers $peer_disagree_list Report that My Transaction Block #$random_block is Wrong.<br>Will Double Check with other Peers before making any changes.", "TC");
								break;
							}
						}
					}
				}
			}
		} // Random Transaction Cycle Check
		
	//***********************************************************************************

	} // End else

	//***********************************************************************************

	} // End if/then check for processing 4 or more records - live database mode
	
	//***********************************************************************************	

} // End if/then time check

//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,0);

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active'");
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
	set_time_limit(300); // Reset timeout so timeout does not occur during sleep
	sleep(10);
}

//***********************************************************************************
} // End Infinite Loop
?>
