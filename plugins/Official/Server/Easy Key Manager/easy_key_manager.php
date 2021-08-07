<?PHP
// PLUGIN_NAME=Easy Key Manager---END
// PLUGIN_TAB=Easy Key---END
// PLUGIN_SERVICE=Easy Key Manager---END
define("AUTOCHECK","3600"); // How Often in seconds to Check Database
set_time_limit(99); // How many seconds to wait until timeout
session_name("timekoin"); // Continue Session Name, Default: [timekoin]
session_start(); // Continue Session

function tk_online()
{
	// Make DB Connection
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	$time_resolution = 500; // How many sleep cycle divisions of the AUTOCHECK interval
	$sleep_counter = intval(AUTOCHECK / $time_resolution);

	while($time_resolution > 0)
	{
		set_time_limit(99); // Reset Timeout
		// Are we to remain active?
		$timekoin_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"));

		if($timekoin_active == "")
		{
			// User has shutdown system
			return FALSE;
		}

		$time_resolution--;
		sleep($sleep_counter);
	}

	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'easy_key_manager.php' LIMIT 1");
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
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	// Avoid stacking this many times
	$already_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'easy_key_manager.php' LIMIT 1"));

	if($already_active == "")
	{
		// Creating Status State - Timekoin Looks for the filename
		mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`) VALUES ('easy_key_manager.php', '0')"); // Offline
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
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'easy_key_manager.php' LIMIT 1");

		// Are we to remain active?
		if(tk_online() == FALSE)
		{
			// Shutdown System
			mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'easy_key_manager.php' LIMIT 1");
			exit;
		}

		$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'easy_key_manager_%' ORDER BY `options`.`field_name` ASC";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			$easy_key_description = find_string("---name=", "---enable", $sql_row["field_data"]);
			$easy_key_enable = intval(find_string("---enable=", "---easy", $sql_row["field_data"]));
			$easy_key = find_string("---easy=", "---key1", $sql_row["field_data"]);
			$easy_key1_db = find_string("---key1=", "---key2", $sql_row["field_data"]); // Private Key
			$easy_key2_db = find_string("---key2=", "---delay", $sql_row["field_data"]); // Public Key
			$easy_key_delay = find_string("---delay=", "---end", $sql_row["field_data"]); // Days ahead of Expiration

			if($easy_key_enable == TRUE)
			{
				// Find the newest expiration in case of multiple renewals
				$counter = 1;
				$easy_key2 = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$easy_key2_db' LIMIT 1"));
				$easy_key_lookup = easy_key_reverse_lookup($easy_key2, $counter);
				$easy_key_expire;

				while($easy_key_lookup != "")
				{
					// Find last expiring key if copies exist from other renewals
					if($easy_key_lookup == $easy_key)
					{
						// Keys expire after 3 months or 7,889,400 seconds after timestamp in transaction history
						$easy_key_expire = easy_key_reverse_lookup($easy_key2, $counter, TRUE) + 7889400;
					}

					$counter++;
					$easy_key_lookup = easy_key_reverse_lookup($easy_key2, $counter);
				}

				// How close to expiration is the Easy Key? 86,400 seconds to 1 day
				$expire_time_remain = $easy_key_expire - time();

				if(86400 * $easy_key_delay > $expire_time_remain)
				{
					// Within the user set amount of days, re-new Easy Key
					$easy_key1 = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$easy_key1_db' LIMIT 1"));

					if(check_crypt_balance($easy_key2) >= (num_gen_peers(FALSE, TRUE) + 1))
					{
						$sql2 = "SELECT public_key FROM `generating_peer_list` GROUP BY `public_key`";
						$sql_result2 = mysqli_query($db_connect, $sql2);
						$sql_num_results2 = mysqli_num_rows($sql_result2);
						$send_failure = FALSE;

						for ($i2 = 0; $i2 < $sql_num_results2; $i2++)
						{
							$sql_row2 = mysqli_fetch_array($sql_result2);

							if($sql_row2["public_key"] != $easy_key2)
							{
								if(send_timekoins($easy_key1, $easy_key2, $sql_row2["public_key"], 1, "Easy Key Renewal Fee") == FALSE)
								{
									$send_failure = TRUE;
								}
							}
						}
						
						// Finally, send transaction to Easy Key Blackhole Address
						// with a 45 Minute Delay
						if(send_timekoins($easy_key1, $easy_key2, base64_decode(EASY_KEY_PUBLIC_KEY), 1, $easy_key, (time() + 2700)) == FALSE)
						{
							$send_failure = TRUE;
						}
					
						if($send_failure == FALSE)
						{
							write_log("Easy Key [$easy_key_description] Renewal Complete!","GU");
						}
						else
						{
							write_log("Easy Key [$easy_key_description] Renewal FAILURE!","GU");
						}
					}					
				}

			} // Check if enabled

		} // Looping through all easy keys

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
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	if($_GET["task"] == "disable")
	{
		// Disable selected task, search for script file name in database
		$easy_key_record_name = $_POST["easy_key_record_name"];
		$taskname_data = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = '$easy_key_record_name' LIMIT 1"));

		// Rewrite String to Disable plugin
		$new_string = str_replace("enable=1", "enable=0", $taskname_data);
	
		// Update String in Database
		mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$easy_key_record_name' LIMIT 1");
	}

	if($_GET["task"] == "enable")
	{
		// Enable selected task, search for script file name in database
		$easy_key_record_name = $_POST["easy_key_record_name"];
		$taskname_data = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = '$easy_key_record_name' LIMIT 1"));

		// Rewrite String to Enable plugin
		$new_string = str_replace("enable=0", "enable=1", $taskname_data);
	
		// Update String in Database
		mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$easy_key_record_name' LIMIT 1");
	}

	if($_GET["task"] == "delete_task")
	{
		// Enable selected task, search for script file name in database
		$easy_key_record_name = $_POST["easy_key_record_name"];
		$eas_key_name_data = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = '$easy_key_record_name' LIMIT 1"));

		// Grab Keys being used in this task
		$easy_key1 = find_string("---key1=", "---key2", $eas_key_name_data);
		$easy_key2 = find_string("---key2=", "---delay", $eas_key_name_data);

		// Delete Keys
		mysqli_query($db_connect, "DELETE FROM `my_keys` WHERE `my_keys`.`field_name` = '$easy_key1'");
		mysqli_query($db_connect, "DELETE FROM `my_keys` WHERE `my_keys`.`field_name` = '$easy_key2'");

		// Delete Task
		mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = '$easy_key_record_name'");
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
		<FORM ACTION="easy_key_manager.php?task=save_new" METHOD="post">
		<table border="0"><tr><td align="right">
		<strong>Description:</strong></td><td><input type="text" size="48" name="easy_key_description" /></td></tr>
		<tr><td align="right"><strong>Easy Key:</strong></td><td><input type="text" size="48" name="easy_key" /></td></tr>
		<tr><td>
		<strong><font color="blue">Easy Key - Private Key:</font></strong><br><br>
		<input type="checkbox" name="use_private" value="1">Use Server<br>Private & Public Key</td><td>
		<textarea name="fromprivatekey" rows="6" cols="62"></textarea>
		</td></tr>
		<tr><td>
		<strong><font color="blue">Easy Key - Public Key:</font></strong></td><td>
		<textarea name="frompublickey" rows="6" cols="62"></textarea>
		</td></tr>
		<tr><td colspan="2"><hr></td></tr>
		<td align="right">
		<strong>Renewal Advance:</strong></td><td>
		<select name="delay_days">
		<option value="1">1 Day</option>
		<option value="2">2 Days</option>
		<option value="3">3 Days</option>
		<option value="4">4 Days</option>
		<option value="5">5 Days</option>
		<option value="6">6 Days</option>
		<option value="7">7 Days</option>
		</select>
		</td></tr>
		<tr><td><input type="submit" name="Submit" value="Save Easy Key" /></td><td></td></tr></table>
		</FORM>';
		$quick_info = '<strong>Description:</strong> Your own personal notes for the Easy Key.<br><br>
		<strong>Easy Key:</strong> The shortcut being monitored.<br><br>
		<strong>Private Key:</strong> This is needed to create renewal transactions in the future.<br><br>
		<strong>Public Key:</strong> The Public Key that Matches with the Private Key.<br><br>
		<strong>Renewal Advance:</strong> How many days in advance of the expiration date should the monitored key be setup for renewal.<br><br>';

		home_screen("Easy Key Manager", NULL, $body_string, $quick_info , 0, TRUE, "Easy Key");
		exit;
	}

	if($_GET["task"] == "save_new")
	{
		$easy_key_description = $_POST["easy_key_description"];
		$fromprivatekey = base64_decode($_POST["fromprivatekey"]);
		$frompublickey = base64_decode($_POST["frompublickey"]);
		$delay_days = $_POST["delay_days"];
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
			$easy_key_lookup = easy_key_lookup($easy_key);

			if(empty($easy_key_lookup) == TRUE)
			{
				// No Response :(
				header("Location: easy_key_manager.php?task=new&error=2");
				exit;
			}
		}

		if(empty($fromprivatekey) == TRUE || empty($frompublickey) == TRUE)
		{
			// Missing Data Fields
			header("Location: easy_key_manager.php?task=new&error=1");
			exit;
		}

		// Find Empty Record Location
		$record_number = 1;
		$record_check = mysql_result(mysqli_query($db_connect, "SELECT field_name FROM `options` WHERE `field_name` = 'easy_key_manager_1' LIMIT 1"));
		
		while(empty($record_check) == FALSE)
		{
			$record_number++;
			$record_check = mysql_result(mysqli_query($db_connect, "SELECT field_name FROM `options` WHERE `field_name` = 'easy_key_manager_$record_number' LIMIT 1"));
		}

		// Find unused keys record name
		$sql = "SELECT field_name FROM `my_keys` WHERE `field_name` LIKE 'ekey_manager%' ORDER BY `my_keys`.`field_name` ASC";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		
		if($sql_num_results >= 99) { header("Location: easy_key_manager.php"); exit; } // 99 Record Limit Reached, Can't Add anymore

		$counter = 1;
		$easy_key1;
		$easy_key2;
		$found1;
		$found2;

		while(1)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			if($counter < 10) { $counter = "0" . $counter; }

			if($sql_row["field_name"] != "ekey_manager_$counter" && $found1 == TRUE)
			{
				// Free record name to use
				$easy_key2 = "ekey_manager_$counter";
				$found2 = TRUE;
			}

			if($sql_row["field_name"] != "ekey_manager_$counter" && $found1 == FALSE)
			{
				// Free record name to use
				$easy_key1 = "ekey_manager_$counter";
				$found1 = TRUE;
			}
		
			if($found1 == TRUE && $found2 == TRUE)
			{
				break;
			}

			$counter++;
		}

		$sql = "INSERT INTO `options` (`field_name`, `field_data`) VALUES 
		('easy_key_manager_$record_number', '---name=$easy_key_description---enable=1---easy=$easy_key---key1=$easy_key1---key2=$easy_key2---delay=$delay_days---end')";

		if(mysqli_query($db_connect, $sql) == TRUE)
		{
			// Option Record Insert Complete, now store keys
			$sql = "INSERT INTO `my_keys` (`field_name`, `field_data`) VALUES 
			('$easy_key1', '$fromprivatekey'), ('$easy_key2', '$frompublickey')";

			mysqli_query($db_connect, $sql);
		}

		header("Location: easy_key_manager.php");
		exit;
	}

function easy_key_manager_home()
{
	// Make DB Connection
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$default_public_key_font = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"));
	$user_timezone = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"));

	$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'easy_key_manager_%' ORDER BY `options`.`field_name` ASC";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);
	$plugin_output;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);
		$easy_key_record_name = $sql_row["field_name"];
		$easy_key_name = find_string("---name=", "---enable", $sql_row["field_data"]);
		$easy_key_enable = intval(find_string("---enable=", "---easy", $sql_row["field_data"]));
		$easy_key = find_string("---easy=", "---key1", $sql_row["field_data"]);
		$easy_key2_db = find_string("---key2=", "---delay", $sql_row["field_data"]);
		$easy_key_delay = find_string("---delay=", "---end", $sql_row["field_data"]);
		$easy_key2 = base64_encode(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$easy_key2_db' LIMIT 1")));

		$counter = 1;
		$easy_key_lookup = easy_key_reverse_lookup(base64_decode($easy_key2), $counter);
		$easy_key_expire = NULL;

		while($easy_key_lookup != "")
		{
			// Find last expiring key if copies exist from other renewals
			if($easy_key_lookup == $easy_key)
			{
				$easy_key_expire.= unix_timestamp_to_human(easy_key_reverse_lookup(base64_decode($easy_key2), $counter, TRUE) + 7889400, $user_timezone) . "<br>";
			}

			$counter++;
			$easy_key_lookup = easy_key_reverse_lookup(base64_decode($easy_key2), $counter);
		}		

		if($easy_key_enable == TRUE)
		{
			$easy_key_toggle = '<FORM ACTION="easy_key_manager.php?task=disable" METHOD="post"><font color="blue"><strong>Enabled</strong></font><br><input type="submit" name="Submit'.$i.'" value="Disable Here" />
			<input type="hidden" name="easy_key_record_name" value="' . $easy_key_record_name . '"></FORM>';
		}
		else
		{
			$easy_key_toggle = '<FORM ACTION="easy_key_manager.php?task=enable" METHOD="post"><font color="red">Disabled</font><br><input type="submit" name="Submit'.$i.'" value="Enable Here" />
			<input type="hidden" name="easy_key_record_name" value="' . $easy_key_record_name . '"></FORM>';
		}

		if($easy_key_delay == 1)
		{ 
			$easy_key_delay = "1 Day";
		}
		else
		{
			$easy_key_delay = "$easy_key_delay Days";
		}

		$plugin_output .= '<tr><td>' . $easy_key_name . '</td><td>' . $easy_key . '</td><td><p style="word-wrap:break-word; width:125px; font-size:' . $default_public_key_font . 'px;">' . $easy_key2 . '</p></td>
		<td>' . $easy_key_delay . '</td><td>' . $easy_key_expire . '</td><td valign="top" align="center">' . $easy_key_toggle . '</td>
		<td><FORM ACTION="easy_key_manager.php?task=delete_task" METHOD="post" onclick="return confirm(\'Delete ' . $easy_key_name . '?\');"><input type="image" src="../img/hr.gif" title="Delete ' . $easy_key_name . '" name="remove' . $i . '" border="0">
		<input type="hidden" name="easy_key_record_name" value="' . $easy_key_record_name . '"></FORM></td></tr>
		<tr><td colspan="8"><hr></td></tr>';
	}
	
	return '<table border="0" cellpadding="2" cellspacing="10"><tr><td valign="bottom" align="center" colspan="7"><strong>Easy Key Management List</strong>
	</td></tr>
	<tr><td align="center"><strong>Description</strong></td><td align="center"><strong>Easy Key</strong></td>
	<td align="center"><strong>Public Key</strong></td><td align="center"><strong>Renewal</strong></td><td align="center"><strong>Expires</strong></td></tr>' . $plugin_output . '
	<tr><td align="right" colspan="7"><FORM ACTION="easy_key_manager.php?task=new" METHOD="post"><input type="submit" name="SubmitNew" value="Manage New Easy Key" /></FORM></td></tr>
	</table>';
}

	if($_GET["font"] == "public_key")
	{
		if(empty($_POST["font_size"]) == FALSE)
		{
			// Save value in database
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["font_size"] . "' WHERE `options`.`field_name` = 'public_key_font_size' LIMIT 1";
			mysqli_query($db_connect, $sql);

			header("Location: easy_key_manager.php");
			exit;
		}
	}
	else
	{
		$default_public_key_font = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"));
	}

	$text_bar = '<FORM ACTION="easy_key_manager.php?font=public_key" METHOD="post">
	<table border="0" cellspacing="4">
	<tr><td><strong>Default Public Key Font Size</strong></td>
	<td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

	$body_string = easy_key_manager_home();

	$quick_info = '<strong>Description:</strong> Your own personal notes for the Easy Key.<br><br>
	<strong>Easy Key:</strong> The shortcut being monitored.<br><br>
	<strong>Public Key:</strong> The public key matching the monitored Easy Key.<br><br>
	<strong>Renewal:</strong> How many days in advance of the expiration date should the monitored key be setup for renewal.<br><br>
	<strong>Expires:</strong> The list of expiration dates for the monitored key. The oldest expiration is used until a newer one can take its place.';

	home_screen("Easy Key Manager", $text_bar, $body_string, $quick_info , 0, TRUE, "Easy Key");
	exit; // All done processing
}


?>
