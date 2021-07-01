<?PHP
include 'configuration.php';
include 'function.php';

if($_GET["action"]=="begin_main")
{
	if(mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD) == FALSE)
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
	$main_heartbeat_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data");

	if($main_heartbeat_active == FALSE && $datbase_error == FALSE)
	{
		// Database Initialization
		initialization_database();

		// Check if a custom PHP path is being used
		$php_location = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'php_location' LIMIT 1"),0,"field_data");
		
		if(empty($php_location) == FALSE)
		{
			// Check to make sure the binary/exe file exist before starting
			if(getenv("OS") == "Windows_NT")
			{
				if(file_exists($php_location . "php-win.exe") == FALSE)
				{
					set_time_limit(99);					
					// Can't start Timekoin, php-win.exe is missing or the path is wrong.
					// Try to find the file before starting.
					$find_php = find_file('C:', 'php-win.exe');

					if(empty($find_php[0]) == TRUE)
					{
						// Try D: if not found on C:
						$find_php = find_file('D:', 'php-win.exe');
					}

					// Filter strings
					$symbols = array("/");
					$find_php[0] = str_replace($symbols, "\\", $find_php[0]);

					// Filter for path setting
					$symbols = array("php-win.exe");
					$find_php[0] = str_replace($symbols, "", $find_php[0]);

					if(empty($find_php[0]) == TRUE)
					{
						// Could not find it anywhere :(
						header("Location: index.php?menu=system&code=98");
						exit;
					}
					else
					{
						// Found it! Save location and start Timekoin
						mysql_query("UPDATE `options` SET `field_data` = '" . addslashes($find_php[0]) . "' WHERE `options`.`field_name` = 'php_location' LIMIT 1");
					}
				} // Check if php-win.exe exist check

			} // Windows OS Check

		} // End Database Check for custom PHP location

		mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'main_last_heartbeat' LIMIT 1");

		// Set loop at active now
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1");

		call_script("main.php");

		activate(TIMEKOINSYSTEM, 1); // In case this was disabled from a emergency stop call in the server GUI

		// Use uPNP to map inbound ports for Windows systems
		if(getenv("OS") == "Windows_NT" && file_exists("utils\upnpc.exe") == TRUE)
		{
			$server_port_number = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,"field_data");
			$server_IP = gethostbyname(trim(`hostname`));
			pclose(popen("start /B utils\upnpc.exe -e Timekoin -a $server_IP $server_port_number $server_port_number TCP", "r"));
		}

		header("Location: index.php?menu=system&code=1");
		exit;
	}
	else
	{
		// Something failed or database error
		header("Location: index.php?menu=system&code=99");
		exit;
	}
}

$mysql_link = mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);
$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
ini_set('user_agent', 'Timekoin Server (Main) v' . TIMEKOIN_VERSION);
ini_set('default_socket_timeout', 3); // Timeout for request in seconds

while(1) // Begin Infinite Loop :)
{
	// Are we to remain active?
	$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data");

	if($loop_active === FALSE) // Databaes Error
	{
		// Database Error, try to re-establish a connection after 5 seconds
		mysql_close($mysql_link);
		sleep(5);
		$mysql_link = mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
		mysql_select_db(MYSQL_DATABASE);

		// Keep track of errors in case this can't be recovered from
		$datbase_error = TRUE;
		$database_error_counter++;
	}
	else
	{
		$datbase_error = 0;
		$database_error_counter = 0;
	}

	if($loop_active == 1)
	{
		// Main loop work goes below
		// Set the working status of 2
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1");
	//*****************************************************************************************************
	//*****************************************************************************************************	
	// Do a random time sync check and report any errors to the user
	if(rand(1,99) == 30)
	{
		$poll_peer = filter_sql(file_get_contents("http://timekoin.net/time.php", FALSE, $context, NULL, 12));
		$my_time = time();

		if(abs($poll_peer - $my_time) > 15 && empty($poll_peer) == FALSE)
		{
			// Timekoin peer time is not in sync
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'time_sync_error' LIMIT 1");
		}
		else
		{
			// Timekoin peer time is in sync
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'time_sync_error' LIMIT 1");
		}
	}
	//*****************************************************************************************************
	//*****************************************************************************************************	
	// Do a update software check and report to user if one is available
	if(rand(1,300) == 100)
	{
		if(check_for_updates(TRUE) == 1)
		{
			// Update available, alert user
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'update_available' LIMIT 1");
		}
	}
	//*****************************************************************************************************
	//*****************************************************************************************************
	// Check for spamming IPs
		$sql = "SELECT * FROM `ip_activity` GROUP BY `ip`";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		$request_max = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'server_request_max' LIMIT 1"),0,"field_data");

		if($request_max > 0) // 0 means no limit
		{
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
					mysql_query("INSERT DELAYED INTO `ip_banlist` (`when` ,`ip`) VALUES (" . time() . ", '$select_IP')");
					write_log("IP Address $select_IP was added to the ban list due to excessive traffic. Default max query is $request_max per cycle, IP was doing $sql_num_results2 query per cycle instead.", "MA");
				}
			}
		}

		// Clear out ban list of IPs older than 1 day
		if(rand(1,99) == 30) // Randomize a little to save DB usage
		{
			mysql_query("DELETE FROM `ip_banlist` WHERE `ip_banlist`.`when` < " . (time() - 86400));
		}
	//*****************************************************************************************************
	//*****************************************************************************************************
		// Check to make sure we are not behind a firewall with no Inbound ports
		$sql_result = mysql_query("SELECT timestamp FROM `ip_activity` WHERE `attribute` = 'QU' OR `attribute` = 'TC' OR `attribute` = 'GP' LIMIT 1");
		$sql_num_results = mysql_num_rows($sql_result);		
		if($sql_num_results == 0) // Randomize a little
		{
			// No activity from any peer, keep track of this
			$no_peer_activity = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'no_peer_activity' LIMIT 1"),0,"field_data");
			$no_peer_activity++;
			
			if($no_peer_activity < 12)
			{
				// No Inbound connection traffic
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '$no_peer_activity' WHERE `main_loop_status`.`field_name` = 'no_peer_activity' LIMIT 1");
			}
		}
		else
		{
			$no_peer_activity = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'no_peer_activity' LIMIT 1"),0,"field_data");
			
			if($no_peer_activity > 10)
			{
				// Disable Firewalled Mode, Inbound is working again
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'no_peer_activity' LIMIT 1");
				$no_peer_activity = NULL;
				write_log("Inbound Activity Detected from Peers, Switching to Normal Operations Mode", "MA");
			}
			else if($no_peer_activity > 0) // Only some short delays of no activity
			{
				// Disable Firewalled Mode, Inbound is working again
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'no_peer_activity' LIMIT 1");
				$no_peer_activity = NULL;
			}
		}

		if($no_peer_activity == 10) // 10th failure triggers
		{
			// No Inbound connection working, the only way to submit transactions is out remotely.
			// Switch to firewalled mode.
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
			write_log("NO Inbound Activity from Peers, Switching to Outbound Mode", "MA");
		}

		// Clear IP Activity for next cycle
		mysql_query("TRUNCATE TABLE `ip_activity`");		
	//*****************************************************************************************************
	//*****************************************************************************************************
		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			call_script("transclerk.php");
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			call_script("foundation.php", 0);
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			call_script("generation.php");
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(extension_loaded('openssl') == TRUE || extension_loaded('gmp') == TRUE)
			{
				call_script("treasurer.php");
			}
			else
			{
				// No CLI Extensions, call script through web server instead
				call_script("treasurer.php", 2);
			}			
		}

		sleep(1); // 1 seconds for sanity reasons

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peerlist_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			call_script("peerlist.php");
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			call_script("queueclerk.php");			
		}

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if(extension_loaded('openssl') == TRUE || extension_loaded('gmp') == TRUE)
			{
				call_script("balance.php", 0);
			}
			else
			{
				// No CLI Extensions, call script through web server instead
				call_script("balance.php", 2);
			}
		}		

		sleep(1); // 1 second for sanity reasons

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,"field_data");
		// Check if script is already running
		if($script_loop_active == 0)
		{
			call_script("genpeer.php");
		}

		sleep(1); // 1 second for sanity reasons

		if(rand(1,3) == 2) // Randomize checking to keep database load down
		{
			// Check watchdog script to make sure it is still running
			$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,"field_data");
			$watchdog_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_last_heartbeat' LIMIT 1"),0,"field_data");

			if($script_loop_active > 0)
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
		sleep(2);

		// Time to wake up and start again
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'main_last_heartbeat' LIMIT 1");

		// Check loop status...
		$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data");

		if($loop_active == 3) // Do a final check to make sure we shouldn't stop running instead
		{
			// Stop the loop and reset status back to 0
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1");
			exit;
		}
		else
		{
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1");
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
	} // End Final Database Working Check
} // End Infinite Loop

?>
