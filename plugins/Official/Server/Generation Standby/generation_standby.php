<?PHP
// PLUGIN_NAME=Generation Standby---END
// PLUGIN_TAB=Gen Standby---END
// PLUGIN_SERVICE=Generation Standby---END
define("AUTOCHECK","30"); // How Often in seconds to Check Database
set_time_limit(99); // How many seconds to wait until timeout
session_name("timekoin"); // Continue Session Name, Default: [timekoin]
session_start(); // Continue Session

function tk_online()
{
	// Make DB Connection
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	$time_resolution = 5; // How many sleep cycle divisions of the AUTOCHECK interval
	$sleep_counter = intval(AUTOCHECK / $time_resolution);

	while($time_resolution > 0)
	{
		set_time_limit(999); // Reset Timeout
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

	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'generation_standby.php' LIMIT 1");
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
	$already_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_standby.php' LIMIT 1"));

	if($already_active == "")
	{
		// Creating Status State - Timekoin Looks for the filename
		mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`) VALUES ('generation_standby.php', '0')"); // Offline
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
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'generation_standby.php' LIMIT 1");

		// Are we to remain active?
		if(tk_online() == FALSE)
		{
			// Shutdown System
			mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'generation_standby.php' LIMIT 1");
			exit;
		}

		$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'generation_standby_%' ORDER BY RAND()";// Randomize in case of large list
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		$current_generation_cycle = transaction_cycle(0);
		$next_generation_cycle = generation_cycle(1);

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			$generation_standby_description = find_string("---name=", "---enable", $sql_row["field_data"]);
			$generation_standby_enable = intval(find_string("---enable=", "---key1", $sql_row["field_data"]));
			$generation_standby1_db = find_string("---key1=", "---key2", $sql_row["field_data"]); // Private Key
			$generation_standby2_db = find_string("---key2=", "---end", $sql_row["field_data"]); // Public Key

			if($generation_standby_enable == TRUE)
			{
				// How long since the Generation Key created currency last?
				$my_public_key = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$generation_standby2_db' LIMIT 1"));
				$last_generation = mysql_result(mysqli_query($db_connect, "SELECT last_generation FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"));

				if($last_generation == "")
				{
					// Key Does Not Exist in Generation List
					$last_generation = time();
				}

				if(time() - $last_generation > 14400)// Create minimum needed after 4 Hours (14,400 Seconds)
				{
					// More than 5 Hours has passed,
					// schedule generation creation if it will happen in the next cycle
					if($next_generation_cycle == TRUE)// Check 1 cycle ahead
					{
						$my_private_key = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$generation_standby1_db' LIMIT 1"));

						// Server public key is listed as a qualified generation server.
						// Has the server submitted it's currency generation to the transaction queue?
						$found_public_key_my_queue = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `my_transaction_queue` WHERE `attribute` = 'G' LIMIT 1"));
						$found_public_key_trans_queue = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$my_public_key' AND `attribute` = 'G' LIMIT 1"));

						if(empty($found_public_key_my_queue) == TRUE && empty($found_public_key_trans_queue) == TRUE)
						{
							$creation_time = $current_generation_cycle + 1;

							//Not found, add it to transaction queue
							$arr1 = str_split($my_public_key, round(strlen($my_public_key) / 2));
							$encryptedData1 = tk_encrypt($my_private_key, $arr1[0]);
							$encryptedData64_1 = base64_encode($encryptedData1);
							$encryptedData2 = tk_encrypt($my_private_key, $arr1[1]);					
							$encryptedData64_2 = base64_encode($encryptedData2);
							$transaction_data = "AMOUNT=1---TIME=" . $creation_time . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2);
							$encryptedData3 = tk_encrypt($my_private_key, $transaction_data);
							$encryptedData64_3 = base64_encode($encryptedData3);
							$duplicate_hash_check = hash('sha256', $encryptedData64_1 . $encryptedData64_2 . $encryptedData64_3);

							$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
							VALUES ('" . $creation_time . "', '$my_public_key', '$encryptedData64_1', '$encryptedData64_2' , '$encryptedData64_3', '$duplicate_hash_check' , 'G')";
							
							if(mysqli_query($db_connect, $sql) == TRUE)
							{
								write_log("Generation Standby [$generation_standby_description] Has Completed.","T");
								break;
							}
						}

					}// Generation Avaiable Next Cycle?

				}// How much time has passed since last generation

			} // Check if enabled

		} // Looping through all generation keys

		unset($my_private_key); // Clear Private Key From RAM
		unset($encryptedData1);
		unset($encryptedData2);
		unset($encryptedData3);
		unset($encryptedData64_1);
		unset($encryptedData64_2);
		unset($encryptedData64_3);
		unset($arr1);
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
		$generation_standby_record_name = $_POST["generation_standby_record_name"];
		$taskname_data = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = '$generation_standby_record_name' LIMIT 1"));

		// Rewrite String to Disable plugin
		$new_string = str_replace("enable=1", "enable=0", $taskname_data);
	
		// Update String in Database
		mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$generation_standby_record_name' LIMIT 1");
	}

	if($_GET["task"] == "enable")
	{
		// Enable selected task, search for script file name in database
		$generation_standby_record_name = $_POST["generation_standby_record_name"];
		$taskname_data = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = '$generation_standby_record_name' LIMIT 1"));

		// Rewrite String to Enable plugin
		$new_string = str_replace("enable=0", "enable=1", $taskname_data);
	
		// Update String in Database
		mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_string' WHERE `options`.`field_name` = '$generation_standby_record_name' LIMIT 1");
	}

	if($_GET["task"] == "delete_task")
	{
		// Enable selected task, search for script file name in database
		$generation_standby_record_name = $_POST["generation_standby_record_name"];
		$eas_key_name_data = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = '$generation_standby_record_name' LIMIT 1"));

		// Grab Keys being used in this task
		$generation_standby1 = find_string("---key1=", "---key2", $eas_key_name_data);
		$generation_standby2 = find_string("---key2=", "---end", $eas_key_name_data);

		// Delete Keys
		mysqli_query($db_connect, "DELETE FROM `my_keys` WHERE `my_keys`.`field_name` = '$generation_standby1'");
		mysqli_query($db_connect, "DELETE FROM `my_keys` WHERE `my_keys`.`field_name` = '$generation_standby2'");

		// Delete Task
		mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = '$generation_standby_record_name'");
	}

	if($_GET["task"] == "new")
	{
		if($_GET["error"] == "1")
		{
			// Missing Data Field
			$missing_field = '<font color="red">A Key Field is Missing</font>';			
		}

		$body_string = $missing_field . '
		<FORM ACTION="generation_standby.php?task=save_new" METHOD="post">
		<table border="0"><tr><td align="right">
		<strong>Description:</strong></td><td><input type="text" size="48" name="generation_standby_description" /></td></tr>
		<tr><td>
		<strong><font color="blue">Generation - Private Key:</font></strong><br><br>
		<input type="checkbox" name="use_private" value="1">Use Server<br>Private & Public Key</td><td>
		<textarea name="fromprivatekey" rows="6" cols="62"></textarea>
		</td></tr>
		<tr><td>
		<strong><font color="blue">Generation - Public Key:</font></strong></td><td>
		<textarea name="frompublickey" rows="6" cols="62"></textarea>
		</td></tr>
		<tr><td><input type="submit" name="Submit" value="Save Generation Key" /></td><td></td></tr></table>
		</FORM>';
		$quick_info = '<strong>Description:</strong> Your own personal notes for the Generation Key.<br><br>
		<strong>Private Key:</strong> This is needed to create renewal generation transactions in the future.<br><br>
		<strong>Public Key:</strong> The Public Key that Matches with the Private Key.';

		home_screen("Generation Standby Manager", NULL, $body_string, $quick_info , 0, TRUE, "Gen Standby");
		exit;
	}

	if($_GET["task"] == "save_new")
	{
		$generation_standby_description = $_POST["generation_standby_description"];
		$fromprivatekey = base64_decode($_POST["fromprivatekey"]);
		$frompublickey = base64_decode($_POST["frompublickey"]);
		$user_server_keys = intval($_POST["use_private"]);

		if($user_server_keys == TRUE)
		{
			$fromprivatekey = my_private_key();
			$frompublickey = my_public_key();
		}

		if(empty($fromprivatekey) == TRUE || empty($frompublickey) == TRUE)
		{
			// Missing Data Fields
			header("Location: generation_standby.php?task=new&error=1");
			exit;
		}

		// Find Empty Record Location
		$record_number = 1;
		$record_check = mysql_result(mysqli_query($db_connect, "SELECT field_name FROM `options` WHERE `field_name` = 'generation_standby_1' LIMIT 1"));
		
		while(empty($record_check) == FALSE)
		{
			$record_number++;
			$record_check = mysql_result(mysqli_query($db_connect, "SELECT field_name FROM `options` WHERE `field_name` = 'generation_standby_$record_number' LIMIT 1"));
		}

		// Find unused keys record name
		$sql = "SELECT field_name FROM `my_keys` WHERE `field_name` LIKE 'gkey_standby%' ORDER BY `my_keys`.`field_name` ASC";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		
		if($sql_num_results >= 99) { header("Location: generation_standby.php"); exit; } // 99 Record Limit Reached, Can't Add anymore

		$counter = 1;
		$generation_standby1;
		$generation_standby2;
		$found1;
		$found2;

		while(1)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			if($counter < 10) { $counter = "0" . $counter; }

			if($sql_row["field_name"] != "gkey_standby_$counter" && $found1 == TRUE)
			{
				// Free record name to use
				$generation_standby2 = "gkey_standby_$counter";
				$found2 = TRUE;
			}

			if($sql_row["field_name"] != "gkey_standby_$counter" && $found1 == FALSE)
			{
				// Free record name to use
				$generation_standby1 = "gkey_standby_$counter";
				$found1 = TRUE;
			}
		
			if($found1 == TRUE && $found2 == TRUE)
			{
				break;
			}

			$counter++;
		}

		$sql = "INSERT INTO `options` (`field_name`, `field_data`) VALUES 
		('generation_standby_$record_number', '---name=$generation_standby_description---enable=1---key1=$generation_standby1---key2=$generation_standby2---end')";

		if(mysqli_query($db_connect, $sql) == TRUE)
		{
			// Option Record Insert Complete, now store keys
			$sql = "INSERT INTO `my_keys` (`field_name`, `field_data`) VALUES 
			('$generation_standby1', '$fromprivatekey'), ('$generation_standby2', '$frompublickey')";

			mysqli_query($db_connect, $sql);
		}

		header("Location: generation_standby.php");
		exit;
	}

	if($_GET["task"] == "download_keys")
	{
		$generation_standby_record_name = $_POST["generation_standby_download_record_name"];
		$taskname_data = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = '$generation_standby_record_name' LIMIT 1"));
		$generation_standby_description = find_string("---name=", "---enable", $taskname_data);
		$generation_private_key = find_string("---key1=", "---key2", $taskname_data); // Private Key
		$generation_public_key = find_string("---key2=", "---end", $taskname_data); // Public Key
		$generation_private_key = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$generation_private_key' LIMIT 1"));
		$generation_public_key = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$generation_public_key' LIMIT 1"));

		$content = '---TKPRIVATEKEY=' . base64_encode($generation_private_key) . '---ENDTKPRIVATEKEY' . "\n\r";
		$content.= '---TKPUBLICKEY=' . base64_encode($generation_public_key) . '---ENDTKPUBLICKEY';			
		$length = strlen($content);
		header('Content-Description: File Transfer');
		header('Content-Type: text/plain');
		header('Content-Disposition: attachment; filename=TK-Generation-Keys-' . $generation_standby_description . '.txt');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . $length);
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Expires: 0');
		header('Pragma: public');
		echo $content;
		exit;		
	}	

function generation_standby_home()
{
	// Make DB Connection
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$default_public_key_font = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"));
	$user_timezone = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"));

	$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'generation_standby_%' ORDER BY `options`.`field_name` ASC";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);
	$plugin_output;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);
		$generation_standby_record_name = $sql_row["field_name"];
		$generation_standby_name = find_string("---name=", "---enable", $sql_row["field_data"]);
		$generation_standby_enable = intval(find_string("---enable=", "---key1", $sql_row["field_data"]));
		$generation_standby2_db = find_string("---key2=", "---end", $sql_row["field_data"]);
		$generation_standby2 = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = '$generation_standby2_db' LIMIT 1"));
		$last_generation = mysql_result(mysqli_query($db_connect, "SELECT last_generation FROM `generating_peer_list` WHERE `public_key` = '$generation_standby2' LIMIT 1"));

		if($last_generation == "")
		{
			// Key Does Not Exist in Generation List
			$last_generation = time();
		}		
		
		$last_generation = tk_time_convert(time() - $last_generation);
		$generation_standby2 = base64_encode($generation_standby2);

		if($generation_standby_enable == TRUE)
		{
			$generation_standby_toggle = '<FORM ACTION="generation_standby.php?task=disable" METHOD="post"><font color="blue"><strong>Enabled</strong></font><br><input type="submit" name="Submit'.$i.'" value="Disable Here" />
			<input type="hidden" name="generation_standby_record_name" value="' . $generation_standby_record_name . '"></FORM>';
		}
		else
		{
			$generation_standby_toggle = '<FORM ACTION="generation_standby.php?task=enable" METHOD="post"><font color="red">Disabled</font><br><input type="submit" name="Submit'.$i.'" value="Enable Here" />
			<input type="hidden" name="generation_standby_record_name" value="' . $generation_standby_record_name . '"></FORM>';
		}

		$plugin_output.= '<tr><td>' . $generation_standby_name . '</td><td><p style="word-wrap:break-word; width:250px; font-size:' . $default_public_key_font . 'px;">' . $generation_standby2 . '</p>
		<FORM ACTION="generation_standby.php?task=download_keys" METHOD="post"><input type="submit" name="Submit" value="Download Keys" />
		<input type="hidden" name="generation_standby_download_record_name" value="' . $generation_standby_record_name . '"></FORM></td>
		<td>' . $last_generation . ' ago</td><td valign="top" align="center">' . $generation_standby_toggle . '</td>
		<td><FORM ACTION="generation_standby.php?task=delete_task" METHOD="post" onclick="return confirm(\'Delete ' . $generation_standby_name . '?\');"><input type="image" src="../img/hr.gif" title="Delete ' . $generation_standby_name . '" name="remove' . $i . '" border="0">
		<input type="hidden" name="generation_standby_record_name" value="' . $generation_standby_record_name . '"></FORM></td></tr>
		<tr><td colspan="5"><hr></td></tr>';
	}
	
	return '<table border="0" cellpadding="2" cellspacing="10"><tr><td valign="bottom" align="center" colspan="5"><strong>Generation Standby Management List</strong>
	</td></tr>
	<tr><td align="center"><strong>Description</strong></td>
	<td align="center"><strong>Public Key</strong></td><td align="center"><strong>Last Generated</strong></td></tr>' . $plugin_output . '
	<tr><td align="right" colspan="5"><FORM ACTION="generation_standby.php?task=new" METHOD="post"><input type="submit" name="SubmitNew" value="Manage New Generation Key" /></FORM></td></tr>
	</table>';
}

	if($_GET["font"] == "public_key")
	{
		if(empty($_POST["font_size"]) == FALSE)
		{
			// Save value in database
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["font_size"] . "' WHERE `options`.`field_name` = 'public_key_font_size' LIMIT 1";
			mysqli_query($db_connect, $sql);

			header("Location: generation_standby.php");
			exit;
		}
	}
	else
	{
		$default_public_key_font = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"));
	}

	$text_bar = '<FORM ACTION="generation_standby.php?font=public_key" METHOD="post">
	<table border="0" cellspacing="4">
	<tr><td><strong>Default Public Key Font Size</strong></td>
	<td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

	$body_string = generation_standby_home();

	$quick_info = '<strong>Description:</strong> Your own personal notes for the Generation Key.<br><br>
	<strong>Public Key:</strong> The public key matching the monitored Generation Key.<br><br>
	<strong>Last Generated:</strong> How long since this key created the minimum amount of currency.<br><br>
	<strong>Download Keys:</strong> This will create an export of the Private &amp; Public Keys into a text file that can be imported elsewhere.';

	home_screen("Generation Standby Manager", $text_bar, $body_string, $quick_info , 300, TRUE, "Gen Standby");
	exit; // All done processing
}


?>
