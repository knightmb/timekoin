<?PHP
include 'configuration.php';
include 'function.php';

if($_GET["action"]=="begin_watchdog")
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

	// Check for banned IP address
	if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
	{
		// Sorry, your IP address has been banned :(
		exit ("Your IP Has Been Banned");
	}

	log_ip("WA", 50);// Avoid flood loading system process

	// Check last heartbeat and make sure it was more than X seconds ago
	$watchdog_heartbeat_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,0);

	if($watchdog_heartbeat_active == FALSE && $datbase_error == FALSE) // Not running currently
	{
		if($watchdog_heartbeat_active === FALSE) // No record exist yet, need to create one
		{
			mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('watchdog_heartbeat_active', '0')");
		}
		
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'watchdog_last_heartbeat' LIMIT 1");

		// Set loop at active now
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1");

		// CLI Mode selection
		$cli_mode = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'cli_mode' LIMIT 1"),0,0));

		// Start main system script
		if($cli_mode == TRUE)
		{
			call_script("watchdog.php", 0);
		}
		else
		{
			session_name("tkwatchcli");
			session_start();
			ini_set('default_socket_timeout', 1);
			call_script("watchdog.php", NULL, NULL, TRUE);			
		}

		header("Location: index.php?menu=system&code=2");
		exit;
	}
	else
	{
		header("Location: index.php?menu=system&code=89");
		exit;
	}
}

$mysql_link = mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit ("Your IP Has Been Banned");
}

log_ip("WA", 25);// Avoid flood loading system process

while(1)
{
	// Set timeout
	set_time_limit(300);
	
	// Are we to remain active?
	$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,0);

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
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1");
//*****************************************************************************************************
//*****************************************************************************************************	
		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Main should still be active
			if((time() - $script_last_heartbeat) > 30) // Greater than triple the loop time, something is wrong
			{
				// Main stop was unexpected
				write_log("Main Timekoin Processor is Inactive or Failed, will need a restart...", "WA");
			}
		}

		sleep(3);
//*****************************************************************************************************
//*****************************************************************************************************	

		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Generation should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Generation Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'generation_last_heartbeat' LIMIT 1");
			}
		}

		sleep(3);

		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Treasurer should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Treasurer has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'treasurer_last_heartbeat' LIMIT 1");
			}
		}

		sleep(4);

		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'peerlist_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'peerlist_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Peerlist should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Peer List Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'peerlist_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'peerlist_last_heartbeat' LIMIT 1");
			}
		}

		sleep(4);
		//**************************************
		//Mid-way check point to speed up watchdog shutdown
		$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,0);

		if($loop_active == 3) // Do a final check to make sure we shouldn't stop running instead
		{
			// Stop the loop and reset status back to 0
			mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active'");
			exit;
		}
		//**************************************
		//**************************************
		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Queueclerk should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Transaction Queue Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'queueclerk_last_heartbeat' LIMIT 1");
			}
		}

		sleep(4);

		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'genpeer_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Genpeer should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Generation Peer Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'genpeer_last_heartbeat' LIMIT 1");
			}
		}

		sleep(3);		

		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'transclerk_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Transclerk should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Transaction Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'transclerk_last_heartbeat' LIMIT 1");
			}
		}

		sleep(3);

		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Foundation should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Foundation Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'foundation_last_heartbeat' LIMIT 1");
			}
		}

		sleep(3);

		$script_loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,0);
		$script_last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'balance_last_heartbeat' LIMIT 1"),0,0);

		if($script_loop_active > 0)
		{
			// Balance Indexer should still be active
			if((time() - $script_last_heartbeat) > 999)
			{
				write_log("Balance Indexer has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active'");
				mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'balance_last_heartbeat' LIMIT 1");
			}
		}

		sleep(3);
//*****************************************************************************************************
//*****************************************************************************************************	
		// (Very Last Thing to do in Script)

		// Time to wake up and start again
		$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'watchdog_last_heartbeat' LIMIT 1";
		mysql_query($sql);

		// Mark this loop finished...
		$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,0);

		if($loop_active == 3) // Do a final check to make sure we shouldn't stop running instead
		{
			// Stop the loop and reset status back to 0
			mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active'");
			exit;
		}
		else
		{
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1");
		}
	} // Check if Active
	else
	{
		// Something is not working right, delay to avoid fast infinite loop
		if($datbase_error == TRUE && $database_error_counter < 6)
		{
			sleep(5);
		}
		else
		{
			// Script was called improperly from somewhere or while it was already running, exit to avoid loop stacking
			exit;
		}
	}

}// End infinite loop

?>
