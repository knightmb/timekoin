<?PHP
include 'configuration.php';

if($_GET["action"]=="begin_watchdog")
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
	$watchdog_heartbeat_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,"field_data"));

	if($watchdog_heartbeat_active == FALSE && $datbase_error == FALSE)
	{
		$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'watchdog_last_heartbeat' LIMIT 1";
		mysql_query($sql);

		// Set loop at active now
		$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1";
		mysql_query($sql);

		if(getenv("OS") == "Windows_NT")
		{
			$watchdog_loop = "start /B php watchdog.php"; // This will execute without waiting for it to finish
			exec($watchdog_loop);
		}
		else
		{
			// There should not be any other loops running
			$watchdog_loop = "php watchdog.php &> /dev/null &"; // This will execute without waiting for it to finish
			exec($watchdog_loop);
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

include 'function.php';

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

	$loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,"field_data"));

	if($loop_active == 3) // Do a check to make sure we shouldn't stop running instead
	{
		// Stop the loop and reset status back to 0
		$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1";
		mysql_query($sql);
		exit;
	}

	if($loop_active == 1)
	{
		// Main loop work goes below
		// Set the working status of 2
		$sql = "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1";

		mysql_query($sql);		
//*****************************************************************************************************
//*****************************************************************************************************	

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Main should still be active
			if((time() - $script_last_heartbeat) > 30) // Greater than triple the loop time, something is wrong
			{
				// Main stop was unexpected
				write_log("Main Timekoin Processor is Inactive or Failed, will need a manual restart...", "WA");
			}
		}

		sleep(3);

//*****************************************************************************************************
//*****************************************************************************************************	

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Generation should still be active
			if((time() - $script_last_heartbeat) > 60)
			{
				write_log("Generation Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active' LIMIT 1";
				mysql_query($sql);
			}
		}

		sleep(3);

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Treasurer should still be active
			if((time() - $script_last_heartbeat) > 60)
			{
				write_log("Treasurer has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'treasurer_heartbeat_active' LIMIT 1";
				mysql_query($sql);
			}
		}

		sleep(4);

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peerlist_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peerlist_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Peerlist should still be active
			if((time() - $script_last_heartbeat) > 60)
			{
				write_log("Peer List Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'peerlist_heartbeat_active' LIMIT 1";
				mysql_query($sql);
			}
		}

		sleep(4);

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Queueclerk should still be active
			if((time() - $script_last_heartbeat) > 90)
			{
				write_log("Transaction Queue Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1";
				mysql_query($sql);
			}
		}

		sleep(4);

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Genpeer should still be active
			if((time() - $script_last_heartbeat) > 90)
			{
				write_log("Generation Peer Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active' LIMIT 1";
				mysql_query($sql);
			}
		}

		sleep(4);		

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Transclerk should still be active
			if((time() - $script_last_heartbeat) > 120)
			{
				write_log("Transaction Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'transclerk_heartbeat_active' LIMIT 1";
				mysql_query($sql);
			}
		}

		sleep(4);

		$script_loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data"));
		$script_last_heartbeat = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_last_heartbeat' LIMIT 1"),0,"field_data"));

		if($script_loop_active > 0)
		{
			// Foundation should still be active
			if((time() - $script_last_heartbeat) > 300)
			{
				write_log("Foundation Clerk has become Stuck, going to Reset...", "WA");
				// Possible script failure, try reset the database to let it continue in the next loop
				$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'foundation_heartbeat_active' LIMIT 1";
				mysql_query($sql);
			}
		}

		sleep(4);

//*****************************************************************************************************
//*****************************************************************************************************	
		// (Very Last Thing to do in Script)

		// Time to wake up and start again
		$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'watchdog_last_heartbeat' LIMIT 1";
		mysql_query($sql);

		// Mark this loop finished...
		$loop_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,"field_data"));

		if($loop_active == 3) // Do a final check to make sure we shouldn't stop running instead
		{
			// Stop the loop and reset status back to 0
			$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1";
			mysql_query($sql);
			exit;
		}
		else
		{
			$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1";
			mysql_query($sql);
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

	loop();
}

loop(); // Start infinite loop

?>
