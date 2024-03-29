<?PHP
include 'configuration.php';
include 'function.php';

if($_GET["action"] == "begin_main")
{
	// Check for banned IP address
	if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
	{
		// Sorry, your IP address has been banned :(
		exit("Your IP Has Been Banned");
	}

	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	
	if($db_connect == FALSE)
	{
		// Database connect error
		$database_error = TRUE;
	}

	log_ip("MA", scale_trigger(5)); // Avoid flood loading system process

	// Check for active heartbeat
	$main_heartbeat_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,0);

	if($main_heartbeat_active == FALSE && $database_error == FALSE)
	{
		// Database Initialization
		initialization_database();

		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'main_last_heartbeat' LIMIT 1");

		// Set loop at active now
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1");

		activate(TIMEKOINSYSTEM, 1); // In case this was disabled from a emergency stop call in the server GUI

		// CLI Mode selection
		$cli_mode = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'cli_mode' LIMIT 1"),0,0));

		// Start main system script
		if($cli_mode == TRUE)
		{
			call_script("main.php");
		}
		else
		{
			session_name("tkmaincli");
			session_start();
			ini_set('default_socket_timeout', 1);
			call_script("main.php", NULL, NULL, TRUE);			
		}

		// Use uPNP to map inbound ports for Windows systems
		if(getenv("OS") == "Windows_NT" && file_exists("utils\upnpc.exe") == TRUE)
		{
			$server_port_number = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,0);
			$server_IP = gethostbyname(trim(`hostname`));
			pclose(popen("start /B utils\upnpc.exe -e Timekoin -a $server_IP $server_port_number $server_port_number TCP", "r"));
		}

		// Start any plugins
		$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' ORDER BY `options`.`field_name` ASC";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);

			$plugin_file = find_string("---file=", "---enable", $sql_row["field_data"]);		
			$plugin_enable = intval(find_string("---enable=", "---show", $sql_row["field_data"]));
			$plugin_service = find_string("---service=", "---end", $sql_row["field_data"]);

			if($plugin_enable == TRUE && empty($plugin_service) == FALSE)
			{
				if($cli_mode == TRUE)
				{
					// Start Plugin Service
					call_script($plugin_file, 0, TRUE);

					// Log Service Start
					write_log("Started Plugin Service: $plugin_service", "MA");
				}
				else
				{
					// Start Plugin Service
					call_script($plugin_file, 0, TRUE, TRUE);

					// Log Service Start
					write_log("Started Plugin Service: $plugin_service", "MA");
				}
			}
		}
		// Finish Starting Plugin Services
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

// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit ("Your IP Has Been Banned");
}

$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
ini_set('user_agent', 'Timekoin Server (Main) v' . TIMEKOIN_VERSION);
ini_set('default_socket_timeout', 1); // Timeout for request in seconds
$activity_log_max = 100000; // Maximum number of activity log entries to retain

// CLI Mode selection
$cli_mode = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'cli_mode' LIMIT 1"),0,0));

log_ip("MA", scale_trigger(5));// Avoid flood loading system process

while(1) // Begin Infinite Loop :)
{
	// Set timeout
	set_time_limit(300);

	// Are we to remain active?
	$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,0);

	if($loop_active == "") // Database Error
	{
		// Database Error, try to re-establish a connection after 5 seconds
		mysqli_close($db_connect);
		sleep(5);
		$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

		// Keep track of errors in case this can't be recovered from
		$database_error = TRUE;
		$database_error_counter++;
	}
	else
	{
		$database_error = 0;
		$database_error_counter = 0;
	}

	if($loop_active == 1)
	{
		// Main loop work goes below
		// Set the working status of 2
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1");
	//*****************************************************************************************************
	//*****************************************************************************************************	
	// Do a random time sync check and report any errors to the user
	if(mt_rand(1,100) == 50)
	{
		$poll_peer = intval(filter_sql(file_get_contents("http://timekoin.net/time.php", FALSE, $context, NULL, 12)));
		$my_time = time();

		if($poll_peer != 0)
		{
			if(abs($poll_peer - $my_time) > 15 && empty($poll_peer) == FALSE)
			{
				// Timekoin peer time is not in sync
				mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'time_sync_error' LIMIT 1");
			}
			else
			{
				// Timekoin peer time is in sync
				mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'time_sync_error' LIMIT 1");
			}
		}
	}
	//*****************************************************************************************************
	//*****************************************************************************************************	
	// Do a update software check and report to user if one is available
	if(mt_rand(1,300) == 100)
	{
		if(check_for_updates(TRUE) == 1)
		{
			// Update available, alert user
			mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'update_available' LIMIT 1");
		}
	}
	//*****************************************************************************************************
	//*****************************************************************************************************
	// Check for spamming IPs
		$request_max = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'server_request_max' LIMIT 1"));

		$sql = "SELECT ip, attribute FROM `ip_activity` WHERE `timestamp` >= " . (time() - 10) . " GROUP BY `ip`";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);

		if($request_max > 0) // 0 means no limit
		{
			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysqli_fetch_array($sql_result);
				$select_IP = $sql_row["ip"];
				$attribute_IP = $sql_row["attribute"];				

				$sql = "SELECT timestamp FROM `ip_activity` WHERE `ip` = '$select_IP'";
				$sql_num_results2 = mysqli_num_rows(mysqli_query($db_connect, $sql));

				if($sql_num_results2 > $request_max && empty($select_IP) == FALSE && $select_IP != "127.0.0.1" && $select_IP != "::1")
				{
					// More than X request per cycle means something is wrong
					// so this IP needs to be banned for a while
					mysqli_query($db_connect, "INSERT INTO `ip_banlist` (`when` ,`ip`) VALUES (" . time() . ", '$select_IP')");
					write_log("IP Address $select_IP was added to the ban list due to excessive traffic. Default max query is $request_max per 10 second cycle, IP was doing $sql_num_results2 query per cycle instead with [$attribute_IP].", "MA");
				}
			}
		}

		// Clear out ban list of IPs older than 1 day
		if(mt_rand(1,200) == 30) // Randomize a little to save DB usage
		{
			mysqli_query($db_connect, "DELETE FROM `ip_banlist` WHERE `ip_banlist`.`when` < " . (time() - 86400));
		}
	//*****************************************************************************************************
	//*****************************************************************************************************		
		// Trim old activity logs to prevent database from filling up too much space
		// Retain last X number of activity logs
		if(mt_rand(1,500) == 100) // Randomize a little to save DB usage
		{
			$activity_log_count = mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `activity_logs`"),0);

			if($activity_log_count > $activity_log_max)
			{
				// Trim the oldest records
				mysqli_query($db_connect, "DELETE FROM `activity_logs` ORDER BY `timestamp` ASC LIMIT " . ($activity_log_count - $activity_log_max) . "");

				// Optimize Table to Reclaim Space
				mysqli_query($db_connect, "OPTIMIZE TABLE `activity_logs`");

				// Log Activity Log Maintenance
				write_log("Activity Log Purged of the Last [" . ($activity_log_count - $activity_log_max) . "] Oldest Records", "MA");
			}
		}
	//*****************************************************************************************************		
	//*****************************************************************************************************
		// Check to make sure we are not behind a firewall with no Inbound ports
		$sql_result = mysqli_query($db_connect, "SELECT timestamp FROM `ip_activity` WHERE `attribute` = 'QU' OR `attribute` = 'TC' OR `attribute` = 'GP' LIMIT 1");
		$sql_num_results = mysqli_num_rows($sql_result);		
		
		if($sql_num_results == 0) // Randomize a little
		{
			// No activity from any peer, keep track of this
			$no_peer_activity = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'no_peer_activity' LIMIT 1"),0,0);
			$no_peer_activity++;
			
			if($no_peer_activity < 12)
			{
				// No Inbound connection traffic
				mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '$no_peer_activity' WHERE `main_loop_status`.`field_name` = 'no_peer_activity' LIMIT 1");
			}
		}
		else
		{
			$no_peer_activity = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'no_peer_activity' LIMIT 1"),0,0);
			
			if($no_peer_activity > 10)
			{
				// Disable Firewalled Mode, Inbound is working again
				mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
				mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'no_peer_activity' LIMIT 1");
				$no_peer_activity = NULL;
				write_log("Inbound Activity Detected from Peers, Switching to Normal Operations Mode", "MA");
			}
			else if($no_peer_activity > 0) // Only some short delays of no activity
			{
				// Disable Firewalled Mode, Inbound is working again
				mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
				mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'no_peer_activity' LIMIT 1");
				$no_peer_activity = NULL;
			}
		}

		if($no_peer_activity == 10) // 10th failure triggers
		{
			// No Inbound connection working, the only way to submit transactions is out remotely.
			// Switch to firewalled mode.
			mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'firewall_blocked_peer' LIMIT 1");
			write_log("NO Inbound Activity from Peers, Switching to Outbound Mode", "MA");
		}

		// Clear IP Activity for next 10 second cycle
		mysqli_query($db_connect, "TRUNCATE TABLE `ip_activity`");		
	//*****************************************************************************************************
	//*****************************************************************************************************
		sleep(2);
		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,0);

		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("transclerk.php");
			}
			else
			{
				call_script("transclerk.php", NULL, NULL, TRUE);			
			}
		}

		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,0);
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("foundation.php", 0);
			}
			else
			{
				call_script("foundation.php", NULL, NULL, TRUE);			
			}
		}

		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,0);
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("generation.php");
			}
			else
			{
				call_script("generation.php", NULL, NULL, TRUE);			
			}
		}

		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,0);
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("treasurer.php");
			}
			else
			{
				call_script("treasurer.php", NULL, NULL, TRUE);			
			}
		}

		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'peerlist_heartbeat_active' LIMIT 1"),0,0);
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("peerlist.php");
			}
			else
			{
				call_script("peerlist.php", NULL, NULL, TRUE);			
			}
		}

		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,0);
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("queueclerk.php");
			}
			else
			{
				call_script("queueclerk.php", NULL, NULL, TRUE);			
			}
		}

		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,0);
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("balance.php", 0);
			}
			else
			{
				call_script("balance.php", NULL, NULL, TRUE);			
			}
		}		

		$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,0);
		// Check if script is already running
		if($script_loop_active == 0)
		{
			if($cli_mode == TRUE)
			{
				call_script("genpeer.php");
			}
			else
			{
				call_script("genpeer.php", NULL, NULL, TRUE);			
			}
		}

		if(mt_rand(1,4) == 3) // Randomize checking to keep database load down
		{
			// Check watchdog script to make sure it is still running
			$script_loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,0);
			$watchdog_last_heartbeat = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'watchdog_last_heartbeat' LIMIT 1"),0,0);

			if($script_loop_active > 0)
			{
				// Watchdog should still be active
				if((time() - $watchdog_last_heartbeat) > 90) // Greater than triple the loop time, something is wrong
				{
					// Watchdog stop was unexpected
					write_log("Watchdog is Stalled...", "MA");
				}
			}
		}
	//*****************************************************************************************************
	//*****************************************************************************************************	
		// (Very Last Thing to do in Script)
		sleep(8);

		// Time to wake up and start again
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'main_last_heartbeat' LIMIT 1");

		// Check loop status...
		$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,0);

		if($loop_active == 3) // Do a final check to make sure we shouldn't stop running instead
		{
			// Stop the loop and reset status back to 0
			mysqli_query($db_connect, "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active'");
			exit;
		}
		else
		{
			mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1");
		}
	} // Check if Active
	else
	{
		// Something is not working right, delay to avoid fast infinite loop
		if($database_error == TRUE && $database_error_counter < 6)
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
