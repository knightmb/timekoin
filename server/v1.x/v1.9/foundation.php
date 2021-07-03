<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(100);
//***********************************************************************************
//***********************************************************************************
if(FOUNDATION_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
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
// Answer block hash poll
if($_GET["action"] == "block_hash" && $_GET["block_number"] >= 0)
{
	$block_number = intval($_GET["block_number"]);

	$hash = mysql_result(mysql_query("SELECT * FROM `transaction_foundation` WHERE `block` = $block_number LIMIT 1"),0,"hash");

	echo $hash;

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'FO')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data");

// Check if loop is already running
if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active' LIMIT 1");
}
else
{
	// Loop called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$previous_foundation_block = foundation_cycle(-1, TRUE);
$current_foundation_block = foundation_cycle(0, TRUE);
$current_generation_cycle = transaction_cycle(0);
$current_generation_block = transaction_cycle(0, TRUE);
$next_generation_cycle = transaction_cycle(1);

$total = mysql_query("SELECT COUNT(*) FROM `transaction_history`");
$total = mysql_fetch_array($total); 
$record_count = $total[0];

if($record_count < $current_generation_block)
{
	// Not enough records to warrant even doing foundation building or checking
	$foundation_task = 1;
}
else
{
	$foundation_task = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'foundation_block_check' LIMIT 1"),0,"field_data");
}


// Can we work on the transactions in the database?
// Not allowed 60 seconds before and 60 seconds after generation cycle.
// Don't build anything if a foundation check is already going on.
if(($next_generation_cycle - time()) > 60 && (time() - $current_generation_cycle) > 60 && $foundation_task == 0)
{
//***********************************************************************************
	// Does my current history hash match all my peers?
	// Ask all of my active peers
	ini_set('default_socket_timeout', 3); // Timeout for request in seconds
	ini_set('user_agent', 'Timekoin Server (Foundation) v' . TIMEKOIN_VERSION);
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete

	$sql = "SELECT * FROM `active_peer_list`";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$foundation_hash_match = 0;
	$foundation_hash_different = 0;
	$site_address;

	if($sql_num_results > 0)
	{
		// Choose random transaction foundation
		if(rand(1,3) == 3)
		{
			// Check the most recent foundations more frequently than older foundations
			$rand_block = rand($previous_foundation_block - 4,$previous_foundation_block);
		}
		else
		{
			$rand_block = rand(0,$previous_foundation_block);
		}
		
		$current_foundation_hash = mysql_result(mysql_query("SELECT * FROM `transaction_foundation` WHERE `block` = $rand_block LIMIT 1"),0,"hash");

		// Make sure we even have a hash to compare against
		if(empty($current_foundation_hash) == FALSE)
		{
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

				if($port_number == 443)
				{
					$ssl = "s";
				}
				else
				{
					$ssl = NULL;
				}

				$poll_peer = filter_sql(file_get_contents("http$ssl://$site_address:$port_number/$subfolder/foundation.php?action=block_hash&block_number=$rand_block", FALSE, $context, NULL, 65));
				
				if($current_foundation_hash === $poll_peer)
				{
					$foundation_hash_match++;
				}
				else
				{
					if(empty($poll_peer) == FALSE && strlen($poll_peer) > 50)
					{
						$foundation_hash_different++;
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
						$repair_block = TRUE;
					}
				}
			}
			else
			{
				// Anything deeper than +1 block back requires 100% of the peers
				// to disagree before a block wipe/repair is scheduled.
				if($foundation_hash_match == 0)
				{
					$repair_block = TRUE;
				}
			}

			if($repair_block == TRUE)
			{
				write_log("Invalid Foundation Block Found, Starting Repair for #$rand_block", "FO");				
				
				// Start by removing the transaction foundation block hash
				$sql = "DELETE FROM `transaction_foundation` WHERE `transaction_foundation`.`block` = $rand_block LIMIT 1";

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

					$sql = "DELETE FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` <= $time2";

					if(mysql_query($sql) == TRUE)
					{
						// Schedule a block check starting at the first block the problem occurs
						$sql = "UPDATE `options` SET `field_data` = '1' WHERE `field_name` = 'foundation_block_check' LIMIT 1";

						if(mysql_query($sql) == TRUE)
						{
							mysql_query("UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'block_check_start' LIMIT 1");
							mysql_query("UPDATE `options` SET `field_data` = '1' WHERE `options`.`field_name` = 'block_check_back' LIMIT 1");
							mysql_query("UPDATE `options` SET `field_data` = '$foundation_time_start' WHERE `options`.`field_name` = 'foundation_block_check_start' LIMIT 1");
							mysql_query("UPDATE `options` SET `field_data` = '$foundation_time_end' WHERE `options`.`field_name` = 'foundation_block_check_end' LIMIT 1");
						}
					}
				}

			} // Repair foundation block check

		} // End empty hash check

} // End number of results check
//***********************************************************************************
// How many foundation blocks exist?
	$total = mysql_query("SELECT COUNT(*) FROM `transaction_foundation`");
	$total = mysql_fetch_array($total); 
	$foundation_blocks = $total[0];

	// How does it compare to the current foundation cycle?
	if($foundation_blocks == $current_foundation_block)
	{
		// No need to run anything
	}
	else
	{
		// Check to make sure enough lead time exist in advance to building
		// another transaction foundation. (50 blocks) or over 4 hours
		// This can be bypassed if the server is building transaction foundations that are older
		// than the current foundation.
		if($current_generation_block - ($current_foundation_block * 500) > 50 || $current_foundation_block - $foundation_blocks > 1)
		{
			// Numbers don't match, what do we have?
			$sql = "SELECT * FROM `transaction_foundation` ORDER BY `block`";
			$sql_result = mysql_query($sql);

			for ($i = 0; $i < $current_foundation_block; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$block = $sql_row["block"];
				$hash = $sql_row["hash"];

				if($i === intval($block))
				{
					//Block exist in the correct order
					if(empty($hash) == FALSE)
					{
						// Hash already exist, no need to check again
						$rebuild_foundation = FALSE;
					}
					else
					{
						// Need to rebuild this transaction foundation
						$rebuild_foundation = TRUE;
					}
				} // End block exist check
				else
				{
					// Need to rebuild this transaction foundation
					$rebuild_foundation = TRUE;
				}

				if($rebuild_foundation == TRUE)
				{
					// Don't do a history walk if the transclerk is currently working on the
					// transaction database
					$transclerk_block_check = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data");				

					if($transclerk_block_check < ($i + 1) * 500 && $transclerk_block_check != "0")
					{
						// Break out of loop; doing anything until transclerk is finished with this range
						break;
					}
					write_log("Building a New Transaction Foundation for Block #$i", "FO");

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

						$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` <= $time2 ORDER BY `timestamp`, `hash`";
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
							write_log("New Transaction Foundation for Block #$i Complete", "FO");

							// Wipe Balance Index table to reset index creation of public key balances
							if(mysql_query("TRUNCATE TABLE `balance_index`") == FALSE)
							{
								write_log("FAILED to Clear Balance Index Table after Transaction Foundation Block #$i was Created", "FO");
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
						write_log("Transaction History Walk FAILED. A Transaction History Check has been scheduled to Examine Transaction Block #$do_history_walk", "FO");
						
						// The history walk failed due to an error somewhere, can't continue.
						// Schedule a block check at the location -1 in hopes that it will be cleared up for the next loop
						$sql = "UPDATE `options` SET `field_data` = '" . ($do_history_walk - 1) . "' WHERE `field_name` = 'transaction_history_block_check' LIMIT 1";

						if(mysql_query($sql) == TRUE)
						{
							// Break out of this loop to prevent confusing block checks in the database
							break;
						}
					}

				} // End rebuild foundation check

			}	// End for loop

		} // End cycle greater than 50 blocks check

	} // End foundation block totals vs current check

//***********************************************************************************
} // End generation cycle allowed check

//***********************************************************************************
//***********************************************************************************
// Script finished, set status to 0
$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active' LIMIT 1";

mysql_query($sql);

// Record when this script finished
$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'foundation_last_heartbeat' LIMIT 1";

mysql_query($sql);


?>
