<?PHP
// PLUGIN_NAME=Auto Currency Transfer---END
// PLUGIN_TAB=AutoTX---END
// PLUGIN_SERVICE=Auto Currency Transfer---END
define("AUTOCHECK","60"); // How Often in seconds to Check Database
set_time_limit(99); // How many seconds to wait until timeout
session_name("timekoin"); // Continue Session Name, Default: [timekoin]
session_start(); // Continue Session

function tk_online()
{
	$time_resolution = 10; // How many sleep cycle divisions of the AUTOCHECK interval
	$sleep_counter = intval(AUTOCHECK / $time_resolution);

	while($time_resolution > 0)
	{
		// Are we to remain active?
		$timekoin_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,0);

		if($timekoin_active == FALSE)
		{
			// User has shutdown system
			return FALSE;
		}

		$time_resolution--;
		sleep($sleep_counter);
	}

	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'autotransfer.php' LIMIT 1");
	return TRUE; // Still Online
}

// Server does not login to start this plugin
if($_SESSION["valid_login"] == FALSE)
{
	// CLI Mode uses this path
	include 'templates.php';// Path to files already used by Timekoin
	include 'function.php';// Path to files already used by Timekoin
	include 'configuration.php';// Path to files already used by Timekoin

	// Non-CLI Mode uses this path
	include '../templates.php';// Path to files already used by Timekoin
	include '../function.php';// Path to files already used by Timekoin
	include '../configuration.php';// Path to files already used by Timekoin

	// Make DB Connection
	mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
	mysql_select_db(MYSQL_DATABASE);

	// Avoid stacking this many times
	$already_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'autotransfer.php' LIMIT 1"),0,"field_data");

	if($already_active === FALSE)
	{
		// Creating Status State - Timekoin Looks for the filename
		mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('autotransfer.php', '0')"); // Offline
	}
	else if($already_active > 0)
	{
		// Being called again while already running, just exit
		exit;
	}

	while(1) // Begin Infinite Loop :)
	{
		set_time_limit(999); // Reset Timeout

		// Idle State
		mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'autotransfer.php' LIMIT 1");

		// Are we to remain active?
		if(tk_online() == FALSE)
		{
			// Shutdown System
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'autotransfer.php' LIMIT 1");
			exit;
		}

		$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'auto_currency_transfer_%' ORDER BY `options`.`field_name` ASC";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$tx_record_name = $sql_row["field_name"];
			$tx_name = find_string("---name=", "---enable", $sql_row["field_data"]);
			$tx_enable = intval(find_string("---enable=", "---type", $sql_row["field_data"]));
			$tx_type = find_string("---type=", "---key1", $sql_row["field_data"]);
			$tx_key1 = find_string("---key1=", "---key2", $sql_row["field_data"]); // Private Key From
			$tx_key2 = find_string("---key2=", "---key3", $sql_row["field_data"]); // Public Key From
			$tx_key1 = mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key1' LIMIT 1"),0,0);
			$tx_key2 = mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key2' LIMIT 1"),0,0);

			if($tx_enable == TRUE)
			{
				if($tx_type == "onedelay")
				{
					$tx_key3 = find_string("---key3=", "---delay", $sql_row["field_data"]);
					$tx_key3 = mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0);
					$tx_delay = find_string("---delay=", "---amount", $sql_row["field_data"]);
					$tx_amount = find_string("---amount=", "---end", $sql_row["field_data"]);

					// Subtract from delay and update
					$new_delay = $tx_delay - AUTOCHECK;
					if($new_delay <= 0) { $new_delay = 0; } // Range Checking
						
					$new_string = str_replace("---delay=$tx_delay", "---delay=$new_delay", $sql_row["field_data"]);

					// Update DB
					mysql_query("UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$tx_record_name' LIMIT 1");

					if($new_delay <= 0)
					{
						// Complete Transaction Task
						if(check_crypt_balance($tx_key2) >= $tx_amount) // Check for valid balance
						{
							// Create Transaction
							if(send_timekoins($tx_key1, $tx_key2, $tx_key3, $tx_amount, NULL) == TRUE)
							{
								// Successful Queue for Transaction
								$new_string = str_replace("---enable=1", "---enable=0", $new_string);
								// Update DB to Disable Task to Avoid Looping Transactions
								mysql_query("UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$tx_record_name' LIMIT 1");
								write_log("Auto Transfer Task ($tx_name) Has Completed.", "T");
							}
						}
					}
				} // One Time Delay Transfer

				if($tx_type == "repeatdelay")
				{
					$tx_key3 = find_string("---key3=", "---delay_start", $sql_row["field_data"]);
					$tx_key3 = mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0);
					$tx_delay = find_string("---delay=", "---amount", $sql_row["field_data"]);
					$tx_start_delay = find_string("---delay_start=", "---delay", $sql_row["field_data"]);
					$tx_amount = find_string("---amount=", "---end", $sql_row["field_data"]);

					// Subtract from delay and update
					$new_delay = $tx_delay - AUTOCHECK;
					if($new_delay <= 0) { $new_delay = 0; } // Range Checking
						
					$new_string = str_replace("---delay=$tx_delay", "---delay=$new_delay", $sql_row["field_data"]);

					// Update DB
					mysql_query("UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$tx_record_name' LIMIT 1");

					if($new_delay <= 0)
					{
						// Complete Transaction Task
						if(check_crypt_balance($tx_key2) >= $tx_amount) // Check for valid balance
						{
							// Create Transaction
							if(send_timekoins($tx_key1, $tx_key2, $tx_key3, $tx_amount, NULL) == TRUE)
							{
								// Successful Queue for Transaction, Reset Timer
								$new_string = str_replace("---delay=0", "---delay=$tx_start_delay", $new_string);
								
								// Update DB to Reset Countdown
								mysql_query("UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$tx_record_name' LIMIT 1");
								write_log("Auto Transfer Task ($tx_name) Has Completed.", "T");
							}
						}
					}
				} // Repeating Delay Transfer

				if($tx_type == "oneamount")
				{
					$tx_key3 = find_string("---key3=", "---amount", $sql_row["field_data"]);
					$tx_key3 = mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0);
					$tx_amount = find_string("---amount=", "---amount_match", $sql_row["field_data"]);
					$amount_match = find_string("---amount_match=", "---end", $sql_row["field_data"]);

					if(check_crypt_balance($tx_key2) >= $amount_match) // Check for valid balance
					{
						// Create Transaction
						if(send_timekoins($tx_key1, $tx_key2, $tx_key3, $tx_amount, NULL) == TRUE)
						{
							// Successful Queue for Transaction
							$new_string = $sql_row["field_data"];
							$new_string = str_replace("---enable=1", "---enable=0", $new_string);

							// Update DB to Disable Task When Finished
							mysql_query("UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$tx_record_name' LIMIT 1");
							write_log("Auto Transfer Task ($tx_name) Has Completed.", "T");							
						}
					}
				} // One Shot Amount Match Transfer

				if($tx_type == "repeatamount")
				{
					$tx_key3 = find_string("---key3=", "---amount", $sql_row["field_data"]);
					$tx_key3 = mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0);
					$tx_amount = find_string("---amount=", "---amount_match", $sql_row["field_data"]);
					$amount_match = find_string("---amount_match=", "---end", $sql_row["field_data"]);

					// Only check once per transaction cycle or otherwise it will just make a transfer every time it scans
					// Check allowed 180 seconds before and 60 seconds after transaction cycle.
					if((transaction_cycle(1) - time()) > 180 && (time() - transaction_cycle(0)) >= 60)
					{
						if(check_crypt_balance($tx_key2) >= $amount_match) // Check for valid balance
						{
							// Create Transaction
							if(send_timekoins($tx_key1, $tx_key2, $tx_key3, $tx_amount, NULL) == TRUE)
							{
								write_log("Auto Transfer Task ($tx_name) Has Completed.", "T");
							}
						}
					}
				} // Repeating Amount Match Transfer

			} // Check if enabled

		} // Looping through all task

	} // Infinite Loop :)

	exit;
}

// Normal GUI User Interaction
if($_SESSION["valid_login"] == TRUE)
{
	include '../templates.php';// Path to files already used by Timekoin
	include '../function.php';// Path to files already used by Timekoin
	include '../configuration.php';// Path to files already used by Timekoin

	// Make DB Connection
	mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
	mysql_select_db(MYSQL_DATABASE);

	if($_GET["task"] == "disable")
	{
		// Disable selected task, search for script file name in database
		$tx_record_name = $_POST["tx_record_name"];
		$taskname_data = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = '$tx_record_name' LIMIT 1"),0,0);

		// Rewrite String to Disable plugin
		$new_string = str_replace("enable=1", "enable=0", $taskname_data);
	
		// Update String in Database
		mysql_query("UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$tx_record_name' LIMIT 1");
	}

	if($_GET["task"] == "enable")
	{
		// Enable selected task, search for script file name in database
		$tx_record_name = $_POST["tx_record_name"];
		$taskname_data = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = '$tx_record_name' LIMIT 1"),0,0);

		// Rewrite String to Enable plugin
		$new_string = str_replace("enable=0", "enable=1", $taskname_data);
	
		// Update String in Database
		mysql_query("UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$tx_record_name' LIMIT 1");
	}

	if($_GET["task"] == "delete_task")
	{
		// Enable selected task, search for script file name in database
		$tx_record_name = $_POST["tx_record_name"];
		$taskname_data = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = '$tx_record_name' LIMIT 1"),0,0);

		// Grab Keys being used in this task
		$tx_key1 = find_string("---key1=", "---key2", $taskname_data);
		$tx_key2 = find_string("---key2=", "---key3", $taskname_data);
		$tx_type = find_string("---type=", "---key1", $taskname_data);

		if($tx_type == "onedelay")
		{
			$tx_key3 = find_string("---key3=", "---delay", $taskname_data);
		}

		if($tx_type == "repeatdelay")
		{
			$tx_key3 = find_string("---key3=", "---delay_start", $taskname_data);
		}

		if($tx_type == "oneamount")
		{
			$tx_key3 = find_string("---key3=", "---amount", $taskname_data);
		}

		if($tx_type == "repeatamount")
		{
			$tx_key3 = find_string("---key3=", "---amount", $taskname_data);
		}

		// Delete Keys
		mysql_query("DELETE FROM `my_keys` WHERE `my_keys`.`field_name` = '$tx_key1'");
		mysql_query("DELETE FROM `my_keys` WHERE `my_keys`.`field_name` = '$tx_key2'");
		mysql_query("DELETE FROM `my_keys` WHERE `my_keys`.`field_name` = '$tx_key3'");

		// Delete Task
		mysql_query("DELETE FROM `options` WHERE `options`.`field_name` = '$tx_record_name'");
	}

	if($_GET["task"] == "new")
	{

		if($_GET["error"] == "1")
		{
			// Missing Data Field
			$missing_field = '<font color="red">A Key Field is Missing</font>';			
		}

		if($_GET["error"] == "2")
		{
			// Easy Key Not Found
			$missing_easy_key = '<font color="red">Easy Key Not Found</font>';
		}		
	$body_string = $missing_field . $missing_easy_key . '
	<FORM ACTION="autotransfer.php?task=save_new" METHOD="post">
	<table border="0"><tr><td align="right">
	<strong>Task Name:</strong></td><td><input type="text" size="20" name="taskname" /></td></tr>
	<tr><td align="right">
	<strong>Type:</strong></td><td><select name="type">
	<option value="onedelay">One Time Countdown Delay</option>
	<option value="repeatdelay">Repeating Countdown Delay</option>
	<option value="oneamount">One Time Amount Equal or Greater Than --></option>
	<option value="repeatamount">Repeating Amount Equal or Greater Than --></option>
	</select><input type="text" size="10" name="amount_match" /></td></tr>
	<tr><td>
	<strong><font color="blue">From Private Key:</font></strong><br>
	<input type="checkbox" name="use_private" value="1">Use Server<br>Private & Public Key</td><td>
	<textarea name="fromprivatekey" rows="6" cols="62"></textarea>
	</td></tr>
	<tr><td>
	<strong><font color="blue">From Public Key:</font></strong></td><td>
	<textarea name="frompublickey" rows="6" cols="62"></textarea>
	</td></tr>
	<tr><td colspan="2"><hr></td></tr>
	<tr><td>
	<strong><font color="green">To Public Key:</font></strong><br><br>
	<strong>Easy Key:</strong><input type="text" size="16" name="easy_key"></td><td>
	<textarea name="topublickey" rows="6" cols="62"></textarea>
	</td></tr>
	<tr><td align="right">
	<strong><font color="green">Amount:</font></strong></td><td><input type="text" size="16" name="amount" /></td></tr>
	<td align="right">
	<strong>Delay:</strong></td><td>
	<select name="delay_days">
	<option value="0">Days</option>
	<option value="1">1 Day</option>
	<option value="2">2 Days</option>
	<option value="3">3 Days</option>
	<option value="4">4 Days</option>
	<option value="5">5 Days</option>
	<option value="6">6 Days</option>
	<option value="7">7 Days</option>
	</select>
	<select name="delay_hours">
	<option value="0">Hours</option>
	<option value="1">1 Hour</option>
	<option value="2">2 Hours</option>
	<option value="3">3 Hours</option>
	<option value="4">4 Hours</option>
	<option value="5">5 Hours</option>
	<option value="6">6 Hours</option>
	<option value="7">7 Hours</option>
	<option value="8">8 Hours</option>
	<option value="9">9 Hours</option>
	<option value="10">10 Hours</option>
	<option value="11">11 Hours</option>
	<option value="12">12 Hours</option>
	<option value="13">13 Hours</option>
	<option value="14">14 Hours</option>
	<option value="15">15 Hours</option>
	<option value="16">16 Hours</option>
	<option value="17">17 Hours</option>
	<option value="18">18 Hours</option>
	<option value="19">19 Hours</option>
	<option value="20">20 Hours</option>
	<option value="21">21 Hours</option>
	<option value="22">22 Hours</option>
	<option value="23">23 Hours</option>
	</select>
	<select name="delay_minutes">
	<option value="0">Minutes</option>
	<option value="5">5 Minutes</option>
	<option value="10">10 Minutes</option>
	<option value="15">15 Minutes</option>
	<option value="20">20 Minutes</option>
	<option value="25">25 Minutes</option>
	<option value="30">30 Minutes</option>
	<option value="35">35 Minutes</option>
	<option value="40">40 Minutes</option>
	<option value="45">45 Minutes</option>
	<option value="50">50 Minutes</option>
	<option value="55">55 Minutes</option>
	</select>
	</td></tr>
	<tr><td><input type="submit" name="Submit" value="Save Task" /></td><td></td></tr></table>
	</FORM>';
	$quick_info = 'Auto Transfer task are scanned every minute.<br><br>
	<strong>One Time Delay</strong> transfers countdown and self-disable after doing one transaction.<br><br>
	<strong>Repeating Delay</strong> transfers will reset after doing one transaction and begin another countdown.<br><br>
	<strong>One Time Amount Match</strong> transfers will do one transaction after the key balance is equal to or greater than the target balance.<br><br>
	<strong>Repeating Amount Match</strong> transfer will do one transaction every transaction cycle when the key balance remains equal to or greater than the target balance';

		home_screen("Auto Currency Transfer", NULL, $body_string, $quick_info , 0, TRUE);
		exit;
	}

	if($_GET["task"] == "save_new")
	{
		$taskname = $_POST["taskname"];
		$type = $_POST["type"];
		$fromprivatekey = base64_decode($_POST["fromprivatekey"]);
		$frompublickey = base64_decode($_POST["frompublickey"]);
		$topublickey = base64_decode($_POST["topublickey"]);
		$amount = intval($_POST["amount"]);
		$amount_match = intval($_POST["amount_match"]);
		$delay_days = $_POST["delay_days"];
		$delay_hours = $_POST["delay_hours"];
		$delay_minutes = $_POST["delay_minutes"];
		$easy_key = $_POST["easy_key"];
		$user_server_keys = intval($_POST["use_private"]);

		if($user_server_keys == TRUE)
		{
			$fromprivatekey = my_private_key();
			$frompublickey = my_public_key();
		}

		if(empty($easy_key) == FALSE)
		{
			// Look up destination public key from Easy Key database
			ini_set('user_agent', 'Timekoin Server (AutoTransfer Plugin) v' . TIMEKOIN_VERSION);
			ini_set('default_socket_timeout', 7); // Timeout for request in seconds

			// Translate Easy Key to Public Key and fill in field with
			$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
			$easy_key = filter_sql(file_get_contents("http://timekoin.net/easy.php?s=$easy_key", FALSE, $context, NULL, 500));

			if($easy_key == "ERROR" || empty($easy_key) == TRUE)
			{
				// No Response :(
				header("Location: autotransfer.php?task=new&error=2");
				exit;
			}
			else
			{
				// Copy to public key destination
				$topublickey = base64_decode($easy_key);
			}
		}

		if(empty($fromprivatekey) == TRUE || empty($frompublickey) == TRUE || empty($topublickey) == TRUE)
		{
			// Missing Data Fields
			header("Location: autotransfer.php?task=new&error=1");
			exit;
		}

		// Find Empty Record Location
		$record_number = 1;
		$record_check = mysql_result(mysql_query("SELECT field_name FROM `options` WHERE `field_name` = 'auto_currency_transfer_1' LIMIT 1"),0,0);
		
		while(empty($record_check) == FALSE)
		{
			$record_number++;
			$record_check = mysql_result(mysql_query("SELECT field_name FROM `options` WHERE `field_name` = 'auto_currency_transfer_$record_number' LIMIT 1"),0,0);
		}

		// Find unused keys record name
		$sql = "SELECT field_name FROM `my_keys` WHERE `field_name` LIKE 'auto_tx_%' ORDER BY `my_keys`.`field_name` ASC";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		
		if($sql_num_results >= 99) { header("Location: autotransfer.php"); exit; } // 99 Record Limit Reached, Can't Add anymore

		$counter = 1;

		$autotx1;
		$autotx2;
		$autotx3;
		$found1;
		$found2;

		while(1)
		{
			$sql_row = mysql_fetch_array($sql_result);
			if($counter < 10) { $counter = "0" . $counter; }

			if($sql_row["field_name"] != "auto_tx_$counter" && $found2 == TRUE)
			{
				// Free record name to use
				$autotx3 = "auto_tx_$counter";
				break;
			}

			if($sql_row["field_name"] != "auto_tx_$counter" && $found1 == TRUE)
			{
				// Free record name to use
				$autotx2 = "auto_tx_$counter";
				$found2 = TRUE;
			}

			if($sql_row["field_name"] != "auto_tx_$counter" && $found2 == FALSE)
			{
				// Free record name to use
				$autotx1 = "auto_tx_$counter";
				$found1 = TRUE;
			}

			$counter++;
		}

		// Calculate Delay Seconds
		$delay_seconds = (86400 * $delay_days) + (3600 * $delay_hours) + (60 * $delay_minutes);
		if($delay_seconds == 0) { $delay_seconds = 300; } // Check for zero
		if($amount <= 0) { $amount = 1; } // Check for zero

		if($type == "onedelay") // One shot delay
		{
			$sql = "INSERT INTO `options` (`field_name`, `field_data`) VALUES 
			('auto_currency_transfer_$record_number', '---name=$taskname---enable=0---type=$type---key1=$autotx1---key2=$autotx2---key3=$autotx3---delay=$delay_seconds---amount=$amount---end')";
		}

		if($type == "repeatdelay") // Repeating transfer delay
		{
			$sql = "INSERT INTO `options` (`field_name`, `field_data`) VALUES 
			('auto_currency_transfer_$record_number', '---name=$taskname---enable=0---type=$type---key1=$autotx1---key2=$autotx2---key3=$autotx3---delay_start=$delay_seconds---delay=$delay_seconds---amount=$amount---end')";
		}

		if($type == "oneamount") // One shot transfer when amount reaches target
		{
			if($amount_match <= 0) { $amount_match = 1; } // No zero amount allowed
			
			$sql = "INSERT INTO `options` (`field_name`, `field_data`) VALUES 
			('auto_currency_transfer_$record_number', '---name=$taskname---enable=0---type=$type---key1=$autotx1---key2=$autotx2---key3=$autotx3---amount=$amount---amount_match=$amount_match---end')";
		}

		if($type == "repeatamount") // Repeating transfer when amount reaches target
		{
			if($amount_match <= 0) { $amount_match = 1; } // No zero amount allowed
			
			$sql = "INSERT INTO `options` (`field_name`, `field_data`) VALUES 
			('auto_currency_transfer_$record_number', '---name=$taskname---enable=0---type=$type---key1=$autotx1---key2=$autotx2---key3=$autotx3---amount=$amount---amount_match=$amount_match---end')";
		}		

		if(mysql_query($sql) == TRUE)
		{
			// Option Record Insert Complete, now store keys
			$sql = "INSERT INTO `my_keys` (`field_name`, `field_data`) VALUES 
			('$autotx1', '$fromprivatekey'), ('$autotx2', '$frompublickey'), ('$autotx3', '$topublickey')";

			mysql_query($sql);
		}

		header("Location: autotransfer.php");
		exit;
	}

function autotx_home()
{
	$default_public_key_font = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");

	$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'auto_currency_transfer_%' ORDER BY `options`.`field_name` ASC";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$plugin_output;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$tx_record_name = $sql_row["field_name"];
		$tx_name = find_string("---name=", "---enable", $sql_row["field_data"]);
		$tx_enable = intval(find_string("---enable=", "---type", $sql_row["field_data"]));
		$tx_type = find_string("---type=", "---key1", $sql_row["field_data"]);
		$tx_key2 = find_string("---key2=", "---key3", $sql_row["field_data"]);
		$tx_key2 = base64_encode(mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key2' LIMIT 1"),0,0));

		if($tx_type == "onedelay")
		{
			$tx_key3 = find_string("---key3=", "---delay", $sql_row["field_data"]);
			$tx_key3 = base64_encode(mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0));
			$tx_delay = find_string("---delay=", "---amount", $sql_row["field_data"]);
			$tx_amount = find_string("---amount=", "---end", $sql_row["field_data"]);
			$tx_type = "One Time<br>Delay";
			if($tx_delay == 0)
			{
				$tx_conditions = "Finished";
			}
			else
			{
				$tx_conditions = tk_time_convert($tx_delay) . " Remain";
			}
		}

		if($tx_type == "repeatdelay")
		{
			$tx_key3 = find_string("---key3=", "---delay_start", $sql_row["field_data"]);
			$tx_key3 = base64_encode(mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0));
			$tx_delay = find_string("---delay=", "---amount", $sql_row["field_data"]);
			$tx_amount = find_string("---amount=", "---end", $sql_row["field_data"]);
			$tx_type = "Repeating<br>Delay";
			$tx_conditions = tk_time_convert($tx_delay) . " Remain";
		}

		if($tx_type == "oneamount")
		{
			$tx_key3 = find_string("---key3=", "---amount", $sql_row["field_data"]);
			$tx_key3 = base64_encode(mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0));
			$tx_amount = find_string("---amount=", "---amount_match", $sql_row["field_data"]);
			$amount_match = find_string("---amount_match=", "---end", $sql_row["field_data"]);

			$tx_type = "One Time<br>Amount Match";
			if($tx_amount == 0)
			{
				$tx_conditions = "Finished";
			}
			else
			{
				$tx_conditions = "Amount >= $amount_match";
			}
		}

		if($tx_type == "repeatamount")
		{
			$tx_key3 = find_string("---key3=", "---amount", $sql_row["field_data"]);
			$tx_key3 = base64_encode(mysql_result(mysql_query("SELECT field_data FROM `my_keys` WHERE `field_name` = '$tx_key3' LIMIT 1"),0,0));
			$tx_amount = find_string("---amount=", "---amount_match", $sql_row["field_data"]);
			$amount_match = find_string("---amount_match=", "---end", $sql_row["field_data"]);
			$tx_type = "Repeating<br>Amount Match";
			$tx_conditions = "Amount >= $amount_match";
		}

		if($tx_enable == TRUE)
		{
			$tx_toggle = '<FORM ACTION="autotransfer.php?task=disable" METHOD="post"><font color="blue"><strong>Enabled</strong></font><br><input type="submit" name="Submit'.$i.'" value="Disable Here" />
				<input type="hidden" name="tx_record_name" value="' . $tx_record_name . '"></FORM>';
		}
		else
		{
			$tx_toggle = '<FORM ACTION="autotransfer.php?task=enable" METHOD="post"><font color="red">Disabled</font><br><input type="submit" name="Submit'.$i.'" value="Enable Here" />
				<input type="hidden" name="tx_record_name" value="' . $tx_record_name . '"></FORM>';
		}

		$plugin_output .= '<tr><td>' . $tx_name . '</td><td>' . $tx_type . '</td><td>' . $tx_conditions . '</td><td><p style="word-wrap:break-word; width:90px; font-size:' . $default_public_key_font . 'px;">' . $tx_key2 . '</p></td>
		<td><p style="word-wrap:break-word; width:90px; font-size:' . $default_public_key_font . 'px;">' . $tx_key3 . '</p></td><td align="center">' . $tx_amount . '</td><td valign="top" align="center">' . $tx_toggle . '</td>
		<td><FORM ACTION="autotransfer.php?task=delete_task" METHOD="post" onclick="return confirm(\'Delete ' . $tx_name . '?\');"><input type="image" src="../img/hr.gif" title="Delete ' . $tx_name . '" name="remove' . $i . '" border="0">
		<input type="hidden" name="tx_record_name" value="' . $tx_record_name . '"></FORM></td></tr>
		<tr><td colspan="8"><hr></td></tr>';
	}
	return '<table border="0" cellpadding="2" cellspacing="10"><tr><td valign="bottom" align="center" colspan="8"><strong>Auto Currency Transfer Task List</strong>
	</td></tr>
	<tr><td align="center"><strong>Name</strong></td><td align="center"><strong>Type</strong></td><td align="center"><strong>Conditions</strong></td>
	<td align="center"><strong>Key From</strong></td><td align="center"><strong>Key To</strong></td><td align="center"><strong>Transfer<br>Amount</strong></td>
	<td align="center"><strong>Status</strong></td><td></td></tr>' . $plugin_output . '
	<tr><td align="right" colspan="8"><FORM ACTION="autotransfer.php?task=new" METHOD="post"><input type="submit" name="SubmitNew" value="Create New Task" /></FORM></td></tr>
	</table>';
}
	if($_GET["font"] == "public_key")
	{
		if(empty($_POST["font_size"]) == FALSE)
		{
			// Save value in database
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["font_size"] . "' WHERE `options`.`field_name` = 'public_key_font_size' LIMIT 1";
			mysql_query($sql);

			header("Location: autotransfer.php");
			exit;
		}
	}
	else
	{
		$default_public_key_font = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
	}

	$text_bar = '<FORM ACTION="autotransfer.php?font=public_key" METHOD="post">
		<table border="0" cellspacing="4">
		<tr><td><strong>Default Public Key Font Size</strong></td>
		<td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

	$body_string = autotx_home();

	$quick_info = 'Auto Transfer task are scanned every minute.<br><br>
		<strong>One Time Delay</strong> transfers countdown and self-disable after doing one transaction.<br><br>
		<strong>Repeating Delay</strong> transfers will reset after doing one transaction and begin another countdown.<br><br>
		<strong>One Time Amount Match</strong> transfers will do one transaction after the key balance is equal to or greater than the target balance.<br><br>
		<strong>Repeating Amount Match</strong> transfer will do one transaction every transaction cycle when the key balance remains equal to or greater than the target balance.';

	home_screen("Auto Currency Transfer", $text_bar, $body_string, $quick_info , 0, TRUE);
	exit; // All done processing
}


?>
