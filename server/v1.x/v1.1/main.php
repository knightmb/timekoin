<?PHP
include 'configuration.php';
include 'function.php';

if($_GET["action"]=="begin_main")
{
	if(mysql_pconnect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD) == FALSE)
	{
		// Database connect error
		$datbase_error = TRUE;
	}

	if(mysql_select_db(MYSQL_DATABASE) == FALSE)
	{
		// Database select error
		$datbase_error = TRUE;
	}

	// Check last heartbeat and make sure it was more than X seconds ago
	$main_heartbeat_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data"));

	if($main_heartbeat_active == FALSE && $datbase_error == FALSE)
	{
		$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'main_last_heartbeat' LIMIT 1";

		mysql_query($sql);

		// Set loop at active now
		$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
		mysql_query($sql);

		// Clear IP Activity for next start
		mysql_query("TRUNCATE TABLE `ip_activity`");

		// Do any database patching from previous versions here
		mysql_query("DELETE FROM `transaction_history` WHERE `transaction_history`.`attribute` = 'H-'");
		mysql_query("DELETE FROM `transaction_history` WHERE `transaction_history`.`attribute` = 'G-'");
		mysql_query("DELETE FROM `transaction_history` WHERE `transaction_history`.`attribute` = 'T-'");		

		if(getenv("OS") == "Windows_NT")
		{
			$main_loop = "start /B php main.php"; // This will execute without waiting for it to finish
			exec($main_loop);
		}
		else
		{
			// There should not be any other loops running
			$main_loop = "php main.php &> /dev/null &"; // This will execute without waiting for it to finish
			exec($main_loop);
		}

		activate(TIMEKOINSYSTEM, 1); // In case this was disabled from a emergency stop call in the server GUI
		header("Location: index.php?menu=system&code=1");
		exit;
	}
	else
	{
		header("Location: index.php?menu=system&code=99");
		exit;
	}
}

$db_server = mysql_pconnect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
$db_select = mysql_select_db(MYSQL_DATABASE);

function loop()
{
	if(mysql_ping($db_server) == FALSE)
	{
		// Database error, insert delay and try again
		sleep(5);
		$datbase_error = TRUE;
		$database_error_counter++;		
	}
	else
	{
		$datbase_error = FALSE;
		$database_error_counter = 0;		
	}

	$loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data"));

	if($loop_active == 3) // Do a final check to make sure we shouldn't stop running instead
	{
		// Stop the loop and reset status back to 0
		$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
		mysql_query($sql);
		exit;
	}

	if($loop_active == 1)
	{
		// Main loop work goes below
		// Set the working status of 2
		$sql = "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
		mysql_query($sql);
	//*****************************************************************************************************
	//*****************************************************************************************************	
	// Check for spamming IPs
		$sql = "SELECT * FROM `ip_activity` GROUP BY `ip`";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		$request_max = 100;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$select_IP = $sql_row["ip"];

			$sql = "SELECT * FROM `ip_activity` WHERE `ip` = '$select_IP'";
			$sql_num_results2 = mysql_num_rows(mysql_query($sql));

			if($sql_num_results2 > $request_max)
			{
				// More than X request per cycle means something is wrong
				// so this IP needs to be banned for a while
				mysql_query("INSERT INTO `ip_banlist` (`when` ,`ip`)VALUES (" . time() . ", '$select_IP')");
				write_log("IP Address $select_IP was added to the ban list due to excessive traffic. Default max query is $request_max per cycle, IP was doing $sql_num_results2 query per cycle instead.", "MA");
			}
		}

		// Clear IP Activity for next cycle
		mysql_query("TRUNCATE TABLE `ip_activity`");

		// Clear out ban list of IPs older than 1 day
		if(rand(1,60) == 30) // Randomize a little to save DB usage
		{
			mysql_query("DELETE FROM `ip_banlist` WHERE `ip_banlist`.`when` < " . (time() - 86400));
		}
	//*****************************************************************************************************
	//*****************************************************************************************************
		// Check to make sure we are not behind a firewall with no Inbound ports
		if($sql_num_results == 0) // Randomize a little
		{
			if(rand(1,3) == 2)
			{
				// No activity from any peer, keep track of this
				$no_peer_activity = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'no_peer_activity' LIMIT 1"),0,"field_data"));
				
				$no_peer_activity++;
				
				if($no_peer_activity < 9)
				{
					mysql_query("UPDATE `options` SET `field_data` = '$no_peer_activity' WHERE `options`.`field_name` = 'no_peer_activity' LIMIT 1");
				}
			}
		}
		else
		{
			if(rand(1,3) == 2)
			{
				$no_peer_activity = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'no_peer_activity' LIMIT 1"),0,"field_data"));
				
				if($no_peer_activity > 1)
				{
					// Disable Firewalled Mode, Inbound is working again
					mysql_query("UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
					mysql_query("UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'no_peer_activity' LIMIT 1");
					
					$no_peer_activity = FALSE;
				}
			}
		}

		if($no_peer_activity > 5 && $no_peer_activity < 7)
		{
			// No Inbound connection working, the only way to submit transactions is out remotely.
			// Switch to firewalled mode.
			mysql_query("UPDATE `options` SET `field_data` = '1' WHERE `options`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
		}
	//*****************************************************************************************************
	//*****************************************************************************************************
		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,"field_data"));
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(getenv("OS") == "Windows_NT")
			{
				$transclerk_loop = "start /B php transclerk.php"; // This will execute without waiting for it to finish
				exec($transclerk_loop);
			}
			else
			{
				// Call transclerk management script
				$transclerk_loop = "php transclerk.php &> /dev/null &"; // This will execute without waiting for it to finish
				exec($transclerk_loop);
			}			
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,"field_data"));
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(getenv("OS") == "Windows_NT")
			{
				$generation_loop = "start /B php generation.php"; // This will execute without waiting for it to finish
				exec($generation_loop);
			}
			else
			{
				// Call currency generation script
				$generation_loop = "php generation.php &> /dev/null &"; // This will execute without waiting for it to finish
				exec($generation_loop);
			}			
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,"field_data"));
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(getenv("OS") == "Windows_NT")
			{
				$treasurer_loop = "start /B php treasurer.php"; // This will execute without waiting for it to finish
				exec($treasurer_loop);
			}
			else
			{
				// Call treasurer transaction script
				$treasurer_loop = "php treasurer.php &> /dev/null &"; // This will execute without waiting for it to finish
				exec($treasurer_loop);
			}
		}

		sleep(2); // 1 second for sanity reasons

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peerlist_heartbeat_active' LIMIT 1"),0,"field_data"));
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(getenv("OS") == "Windows_NT")
			{
				$peerlist_loop = "start /B php peerlist.php"; // This will execute without waiting for it to finish
				exec($peerlist_loop);
			}
			else
			{
				// Call peerlist management script
				$peerlist_loop = "php peerlist.php &> /dev/null &"; // This will execute without waiting for it to finish
				exec($peerlist_loop);
			}
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,"field_data"));
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(getenv("OS") == "Windows_NT")
			{
				$queueclerk_loop = "start /B php queueclerk.php"; // This will execute without waiting for it to finish
				exec($queueclerk_loop);
			}
			else
			{
				// Call queueclerk management script
				$queueclerk_loop = "php queueclerk.php &> /dev/null &"; // This will execute without waiting for it to finish
				exec($queueclerk_loop);
			}			
		}

		sleep(2); // 1 second for sanity reasons

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,"field_data"));
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(getenv("OS") == "Windows_NT")
			{
				$genpeer_loop = "start /B php genpeer.php"; // This will execute without waiting for it to finish
				exec($genpeer_loop);
			}
			else
			{
				// Call genpeer management script
				$genpeer_loop = "php genpeer.php &> /dev/null &"; // This will execute without waiting for it to finish
				exec($genpeer_loop);
			}			
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data"));
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(getenv("OS") == "Windows_NT")
			{
				$transclerk_loop = "start /B php foundation.php"; // This will execute without waiting for it to finish
				exec($transclerk_loop);
			}
			else
			{
				// Call transclerk management script
				$transclerk_loop = "php foundation.php &> /dev/null &"; // This will execute without waiting for it to finish
				exec($transclerk_loop);
			}			
		}

		sleep(1); // 1 second for sanity reasons

		if(rand(1,3) == 2) // Randomize checking to keep database load down
		{
			// Check watchdog script to make sure it is still running
			$watchdog_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,"field_data"));
			$watchdog_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_last_heartbeat' LIMIT 1"),0,"field_data"));

			if($watchdog_loop_active > 0)
			{
				// Watchdog should still be active
				if((time() - $watchdog_last_heartbeat) > 60) // Greater than double the loop time, something is wrong
				{
					// Watchdog stop was unexpected
					write_log("Watchdog is Stalled...", "MA");
				}
			}
		}
	//*****************************************************************************************************
	//*****************************************************************************************************	
		// (Very Last Thing to do in Script)
		sleep(1);

		// Time to wake up and start again
		$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'main_last_heartbeat' LIMIT 1";
		mysql_query($sql);

		// Mark this loop finished...
		$loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data"));

		if($loop_active == 3) // Do a final check to make sure we shouldn't stop running instead
		{
			// Stop the loop and reset status back to 0
			$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
			mysql_query($sql);
			exit;
		}
		else
		{
			$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
			mysql_query($sql);
		}
	} // Check if Active
	else
	{
		// Something is not working right, delay to avoid fast infinite loop
		if($datbase_error == TRUE && $database_error_counter < 6)
		{
			// Wait 5 seconds for database to come back online
			sleep(5);
		}
		else
		{
			// Script was called improperly from somewhere or while it was already running, exit to avoid loop stacking
			exit;
		}
	}

	loop();
}

loop(); // Start infinite loop

?>
