<?PHP
include 'configuration.php';
include 'function.php';
//***********************************************************************************
//***********************************************************************************
if(FOUNDATION_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Open connection to database
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
// Answer block hash poll
if($_GET["action"] == "block_hash" && $_GET["block_number"] >= 0)
{
	$block_number = intval($_GET["block_number"]);

	echo mysql_result(mysql_query("SELECT hash FROM `transaction_foundation` WHERE `block` = $block_number LIMIT 1"),0,0);

	// Log inbound IP activity
	log_ip("FO");
	exit;
}
//***********************************************************************************
while(1) // Begin Infinite Loop
{
set_time_limit(300);	
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active === FALSE)
{
	// Time to exit
	exit;
}
else if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active' LIMIT 1");
}
else if($loop_active == 2) // Wake from sleep
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active' LIMIT 1");
}
else if($loop_active == 3) // Shutdown
{
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active'");
	exit;
}
else
{
	// Script called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$previous_foundation_block = foundation_cycle(-1, TRUE);
$current_foundation_block = foundation_cycle(0, TRUE);
$current_generation_cycle = transaction_cycle(0);
$current_generation_block = transaction_cycle(0, TRUE);
$next_generation_cycle = transaction_cycle(1);

$record_count = mysql_result(mysql_query("SELECT COUNT(*) FROM `transaction_history`"),0);
$treasurer_status = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,0));

if($record_count < 500)
{
	// Not enough records to warrant even doing foundation building or checking
	$foundation_task = 1;
}
else
{
	$foundation_task = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_block_check' LIMIT 1"),0,"field_data");
}

// Can we work on the transactions in the database?
// Not allowed 60 seconds before and 45 seconds after transaction cycle.
// Don't build anything if a foundation check is already going on.
// Don't build anything is the Treasurer is still processing transactions (status = 1)
if(($next_generation_cycle - time()) > 60 && (time() - $current_generation_cycle) > 45 && $foundation_task == 0 && $treasurer_status == 2)
{
//***********************************************************************************
	// Does my current history hash match all my peers?
	// Ask all of my active peers
	ini_set('default_socket_timeout', 2); // Timeout for request in seconds
	ini_set('user_agent', 'Timekoin Server (Foundation) v' . TIMEKOIN_VERSION);

	$sql = perm_peer_mode();
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$foundation_hash_match = 0;
	$foundation_hash_different = 0;
	$poll_errors = 0;
	$repair_block = FALSE;

	if($sql_num_results > 0)
	{
		// Choose random transaction foundation
		if(rand(1,5) == 5)
		{
			// Check the most recent foundations more frequently than older foundations
			$rand_block = rand($previous_foundation_block - 4,$previous_foundation_block);
		}
		else
		{
			$rand_block = rand(0,$previous_foundation_block);
		}
		
		$current_foundation_hash = mysql_result(mysql_query("SELECT hash FROM `transaction_foundation` WHERE `block` = $rand_block LIMIT 1"),0,0);

		// Make sure we even have a hash to compare against
		if(empty($current_foundation_hash) == FALSE)
		{
			// How frequent the transaction foundation checks are set by the user
			$trans_history_check = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'trans_history_check' LIMIT 1"),0,"field_data"));
			$rand_freq = 99; // Rare - Default if no user set value

			if($trans_history_check == 1)
			{
				$rand_freq = 40; // Normal
			}
			else if($trans_history_check == 2)
			{
				$rand_freq = 15; // Frequent
			}

			// Check that a foundation block has not become corrupt due to unknown reasons
			if(rand(1,$rand_freq) == 15)
			{
				// Build an existing Foundation Block and compare to the Hash in the database.
				// If the two hash do not match, then some repairs need to be made to the transaction history.

				write_log("Testing Transaction Foundation #$rand_block", "FO");

				// Start the process to rebuild the transaction foundation
				// but walk the history of that range first to check for errors.
				$foundation_time_start = $rand_block * 500;
				$foundation_time_end = ($rand_block * 500) + 500;

				$do_history_walk = walkhistory($foundation_time_start, $foundation_time_end);

				if($do_history_walk == 0)
				{
					// History walk checks out, start building the transaction foundation hash
					// out of every piece of data in the database
					$time1 = transaction_cycle(0 - $current_generation_block + $foundation_time_start);
					$time2 = transaction_cycle(0 - $current_generation_block + $foundation_time_end);

					$sql = "SELECT timestamp, public_key_from, public_key_to, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` <= $time2 ORDER BY `timestamp`, `hash`";
					$sql_result2 = mysql_query($sql);
					$sql_num_results2 = mysql_num_rows($sql_result2);

					$hash = $sql_num_results2;

					for ($f = 0; $f < $sql_num_results2; $f++)
					{
						$sql_row2 = mysql_fetch_array($sql_result2);
						$hash .= $sql_row2["timestamp"] . $sql_row2["public_key_from"] . $sql_row2["public_key_to"] . $sql_row2["hash"] . $sql_row2["attribute"];
					}	

					$hash = hash('sha256', $hash);
				}

				if($hash == $current_foundation_hash)
				{
					write_log("Transaction Foundation #$rand_block checks out OK", "FO");
					$repair_block = FALSE;
				}
				else
				{
					write_log("Transaction Foundation #$rand_block did NOT pass verification test. Transactions in this Foundation will be repaired.", "FO");
					$repair_block = TRUE;
				}
			}
			else
			{
				// Check foundation hash with peers
				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);

					$ip_address = $sql_row["IP_Address"];
					$domain = $sql_row["domain"];
					$subfolder = $sql_row["subfolder"];
					$port_number = $sql_row["port_number"];

					$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 65, "foundation.php?action=block_hash&block_number=$rand_block");

					if(empty($poll_peer) == TRUE)
					{
						// Add failure points to the peer in case further issues
						modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
					}

					if($current_foundation_hash === $poll_peer)
					{
						$foundation_hash_match++;
					}
					else
					{
						if(empty($poll_peer) == FALSE && strlen($poll_peer) > 60)
						{
							$foundation_hash_different++;
						}
						else
						{
							// Polling Errors can cause false corruption assumptions
							$poll_errors++;
						}
					}
				} // End for Loop

				// Compare tallies
				if($rand_block == $previous_foundation_block)
				{
					// 2/3 of the peers must disagree to schedule a block wipe/repair
					// this recent.
					if($foundation_hash_different == 0)
					{
						// No peers disagrees, all is well
						$repair_block = FALSE;
					}
					else
					{
						if($foundation_hash_different / $sql_num_results >= 2 / 3)
						{
							// 2/3 or more of peers say something is wrong
							$repair_block = TRUE;
						}
					}
				}
				else
				{
					// Anything deeper than +1 block back requires 100% of the peers
					// to disagree before a block wipe/repair is scheduled.
					if($foundation_hash_match == 0 && $poll_errors == 0)
					{
						// 100% of all peers say something is wrong
						$repair_block = TRUE;
					}
				}
			} // End Foundation Compare check

			if($repair_block == TRUE)
			{
				write_log("Invalid Transaction Foundation Found, Starting Repair for #$rand_block", "FO");
				
				// Start by removing the transaction foundation block hash
				$sql = "DELETE QUICK FROM `transaction_foundation` WHERE `transaction_foundation`.`block` = $rand_block LIMIT 1";

				if(mysql_query($sql) == TRUE)
				{
					// Now wipe the range of transactions for this block
					$foundation_time_start = $rand_block * 500;
					$foundation_time_end = ($rand_block * 500) + 500;

					if($rand_block > 0)
					{
						$time1 = transaction_cycle(0 - $current_generation_block + $foundation_time_start);
					}
					else
					{
						// Prevent the Beginning Block from being wiped when repairing #0
						$time1 = 1338576600;
					}

					$time2 = transaction_cycle(0 - $current_generation_block + $foundation_time_end);

					$sql = "DELETE QUICK FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` <= $time2";

					if(mysql_query($sql) == TRUE)
					{
						// Schedule a block check starting at the first block the problem occurs
						$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `field_name` = 'foundation_block_check' LIMIT 1";

						if(mysql_query($sql) == TRUE)
						{
							mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'block_check_start' LIMIT 1");
							mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'block_check_back' LIMIT 1");
							mysql_query("UPDATE `main_loop_status` SET `field_data` = '$foundation_time_start' WHERE `main_loop_status`.`field_name` = 'foundation_block_check_start' LIMIT 1");
							mysql_query("UPDATE `main_loop_status` SET `field_data` = '$foundation_time_end' WHERE `main_loop_status`.`field_name` = 'foundation_block_check_end' LIMIT 1");
						}
					}
				}

			} // Repair foundation block check

		} // End empty hash check

} // End number of results check
//***********************************************************************************
// How many foundation blocks exist?
	$foundation_blocks = mysql_result(mysql_query("SELECT COUNT(*) FROM `transaction_foundation`"),0);

	// How does it compare to the current foundation cycle?
	if($foundation_blocks == $current_foundation_block)
	{
		// No need to run anything
	}
	else
	{
		// Check to make sure enough lead time exist in advance to building
		// another transaction foundation.
		if($current_generation_block - ($current_foundation_block * 500) > 2)
		{
			// Numbers don't match, what do we have?
			$sql = "SELECT * FROM `transaction_foundation` ORDER BY `transaction_foundation`.`block` ASC";
			$sql_result = mysql_query($sql);

			for ($i = 0; $i < $current_foundation_block; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$block = $sql_row["block"];
				$hash = $sql_row["hash"];

				if($i === intval($block))
				{
					// Block exist in the correct order
					if(empty($hash) == FALSE)
					{
						// Hash already exist, no need to check again
						$rebuild_foundation = FALSE;
					}
					else
					{
						// Need to build this transaction foundation
						$rebuild_foundation = TRUE;
					}
				} // End block exist check
				else
				{
					// Need to build this transaction foundation
					$rebuild_foundation = TRUE;
				}

				if($rebuild_foundation == TRUE)
				{
					// Don't do a history walk if the transclerk is currently working on the
					// transaction database
					$transclerk_block_check = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data");				

					if($transclerk_block_check < ($i + 1) * 500 && $transclerk_block_check != "0")
					{
						// Break out of loop; Don't do anything until transclerk is finished with this range
						break;
					}

					write_log("Building New Transaction Foundation #$i", "FO");

					// Start the process to rebuild the transaction foundation
					// but walk the history of that range first to check for errors.
					$foundation_time_start = $i * 500;
					$foundation_time_end = ($i * 500) + 500;

					$do_history_walk = walkhistory($foundation_time_start, $foundation_time_end);

					if($do_history_walk == 0)
					{
						// History walk checks out, start building the transaction foundation hash
						// out of every piece of data in the database
						$time1 = transaction_cycle(0 - $current_generation_block + $foundation_time_start);
						$time2 = transaction_cycle(0 - $current_generation_block + $foundation_time_end);

						$sql = "SELECT timestamp, public_key_from, public_key_to, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` <= $time2 ORDER BY `timestamp`, `hash`";
						$sql_result2 = mysql_query($sql);
						$sql_num_results2 = mysql_num_rows($sql_result2);

						$hash = $sql_num_results2;

						for ($f = 0; $f < $sql_num_results2; $f++)
						{
							$sql_row2 = mysql_fetch_array($sql_result2);
							$hash .= $sql_row2["timestamp"] . $sql_row2["public_key_from"] . $sql_row2["public_key_to"] . $sql_row2["hash"] . $sql_row2["attribute"];
						}	

						$hash = hash('sha256', $hash);

						$sql = "INSERT INTO `transaction_foundation` (`block` ,`hash`)VALUES ('$i', '$hash')";

						if(mysql_query($sql) == TRUE)
						{
							// Success
							write_log("New Transaction Foundation #$i Complete", "FO");

							// Wipe Balance Index table to reset index creation of public key balances
							if(mysql_query("TRUNCATE TABLE `balance_index`") == FALSE)
							{
								write_log("FAILED to Clear Balance Index Table after Transaction Foundation #$i was Created", "FO");
							}
							
							// Break out of this loop in case there is a lot
							// of history to catch up on. We don't want to tie
							// up the server with building many transaction foundations
							// in a row.
							break;
						}
					}
					else
					{
						write_log("Transaction History Walk FAILED. A Transaction History Check has been scheduled to Examine Transaction Cycle #$do_history_walk", "FO");
						
						// The history walk failed due to an error somewhere, can't continue.
						// Schedule a block check at the location -1 in hopes that it will be cleared up for the next loop
						$sql = "UPDATE `main_loop_status` SET `field_data` = '" . ($do_history_walk - 1) . "' WHERE `main_loop_status`.`field_name` = 'transaction_history_block_check' LIMIT 1";

						if(mysql_query($sql) == TRUE)
						{
							// Break out of this loop to prevent confusing block checks in the database
							break;
						}
					}

				} // End rebuild foundation check

			}	// End for loop

		} // End cycle greater than 2 blocks check		

	} // End foundation block totals vs current check

//***********************************************************************************
} // End transaction cycle allowed check

// Unset variable to free up RAM
	unset($sql_result);
	unset($sql_result2);

//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active == 3)
{
	// Time to exit
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active' LIMIT 1");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'foundation_last_heartbeat' LIMIT 1");

//***********************************************************************************
sleep(10);
} // End Infinite Loop
?>
