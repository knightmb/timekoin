<?PHP
// This file is an example plugin that uses the current Timekoin Theme,
// Menu, and Interface. This plugin has full access to all the existing
// functions, database, and templates of Timekoin.
//
// Timekoin will parse the file looking for these text strings
// to save into the database when installing. You need only
// leave them in the comment area.
//
// This is the long name of your plugin.
// PLUGIN_NAME=Turbo History Update---END
//
// This is the tab text on the menu bar.
// PLUGIN_TAB=Turbo History---END
//
//
include '../templates.php';// Path to files already used by Timekoin
include '../function.php';// Path to files already used by Timekoin
include '../configuration.php';// Path to files already used by Timekoin

set_time_limit(999); // How many seconds to wait until timeout
session_name("timekoin"); // Continue Session Name, Default: [timekoin]
session_start(); // Continue Session or Start a New Session
ini_set('default_socket_timeout', 5);

// Make DB Connection
$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

if($_SESSION["valid_login"] == TRUE) // Make Sure Login is Still Valid
{
	// Does the screen need to refresh every X seconds? 0 = Disable	
	$update = 0;
	$timekoin_live = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"));
	$sql_max_allowed_packet = intval(mysql_result(mysqli_query($db_connect, "SHOW VARIABLES LIKE 'max_allowed_packet'"),0,1));	
	$task_level = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'turbo_history_update' LIMIT 1")));

	if($timekoin_live != "")
	{
		$warning_message = '<strong><font color="red">Do Not Start This On A Live Running System. Stop Timekoin First!</font></strong><br><br>';
	}

	if($task_level == 2)
	{
		$warning_message = '<strong><font color="purple">If You Close or Change This Tab, Turbo History Stops Automatically!</font></strong><br><br>';
	}

	$super_peer_ip = $_POST["super_peer_ip"];
	$super_peer_domain = $_POST["super_peer_domain"];
	$super_peer_subfolder = $_POST["super_peer_subfolder"];
	$super_peer_port = $_POST["super_peer_port"];

	if($super_peer_ip == "" && $_GET["task"] != "" && $_GET["begin"] != "new")
	{
		$super_peer_ip = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'turbo_ip' LIMIT 1"));
	}

	if($super_peer_domain == "" && $_GET["task"] != "" && $_GET["begin"] != "new")
	{
		$super_peer_domain = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'turbo_domain' LIMIT 1"));
	}

	if($super_peer_subfolder == "" && $_GET["task"] != "" && $_GET["begin"] != "new")
	{
		$super_peer_subfolder = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'turbo_subfolder' LIMIT 1"));
	}

	if($super_peer_port == "" && $_GET["task"] != "" && $_GET["begin"] != "new")
	{
		$super_peer_port = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'turbo_port' LIMIT 1"));
	}	

	if($task_level == 99 && $_GET["task"] == "start_transfer" && $_GET["begin"] == "new")
	{
		$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'turbo_history_update' LIMIT 1";
		mysqli_query($db_connect, $sql);		

		$sql = "UPDATE `options` SET `field_data` = '$super_peer_ip' WHERE `options`.`field_name` = 'turbo_ip' LIMIT 1";
		mysqli_query($db_connect, $sql);

		$sql = "UPDATE `options` SET `field_data` = '$super_peer_domain' WHERE `options`.`field_name` = 'turbo_domain' LIMIT 1";
		mysqli_query($db_connect, $sql);

		$sql = "UPDATE `options` SET `field_data` = '$super_peer_subfolder' WHERE `options`.`field_name` = 'turbo_subfolder' LIMIT 1";
		mysqli_query($db_connect, $sql);

		$sql = "UPDATE `options` SET `field_data` = '$super_peer_port' WHERE `options`.`field_name` = 'turbo_port' LIMIT 1";
		mysqli_query($db_connect, $sql);

		header("Location: turbo_history.php?task=start_transfer");
		exit;
	}

	// Text Bar Text
	$text_bar = $warning_message . '<div class="table">
	<FORM ACTION="turbo_history.php?task=start_transfer&amp;begin=new" METHOD="post">
	<table class="listing" border="0" cellspacing="0" cellpadding="0"><tr><th>IP Address</th>
	<th>Domain</th><th>Subfolder</th><th>Port Number</th></tr>
	<tr><td class="style2"><input type="text" name="super_peer_ip" size="25" value="' . $super_peer_ip . '"/></td>
	<td class="style2"><input type="text" name="super_peer_domain" size="20" value="' . $super_peer_domain . '"/></td>
	<td class="style2"><input type="text" name="super_peer_subfolder" size="8" value="' . $super_peer_subfolder . '"/></td>
	<td class="style2"><input type="number" name="super_peer_port" min="1" max="65535" size="6" value="' . $super_peer_port . '"/></td>
	</tr></table><input type="submit" name="Submit4" value="Start Turbo History" /></FORM></div><br>
	<FORM ACTION="turbo_history.php?task=stop_transfer" METHOD="post">
	<input type="hidden" name="super_peer_ip" value="' . $super_peer_ip . '">
	<input type="hidden" name="super_peer_domain" value="' . $super_peer_domain . '">
	<input type="hidden" name="super_peer_subfolder" value="' . $super_peer_subfolder . '">
	<input type="hidden" name="super_peer_port" value="' . $super_peer_port . '">
	<input type="submit" name="Submit1" value="STOP Turbo History" /></FORM><br>';

	if($_GET["task"] == "start_transfer")
	{
		if($task_level == 0)
		{
			$trans_record_count = mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `transaction_history`"),0);

			if($trans_record_count >= 4) // System must have at least the first 4 start records
			{
				$sql = "INSERT INTO `main_loop_status` (`field_name`, `field_data`) VALUES ('turbo_history_update', '1')";
				mysqli_query($db_connect, $sql);

				// Clear Any Existing Data
				$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_ip'";
				mysqli_query($db_connect, $sql);

				$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_domain'";
				mysqli_query($db_connect, $sql);

				$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_subfolder'";
				mysqli_query($db_connect, $sql);

				$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_port'";
				mysqli_query($db_connect, $sql);

				$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_block_num'";
				mysqli_query($db_connect, $sql);

				// Create New Data
				$sql = "INSERT INTO `options` (`field_name`, `field_data`) 
				VALUES ('turbo_block_num', '0'), ('turbo_ip', '$super_peer_ip'), ('turbo_domain', '$super_peer_domain'), ('turbo_subfolder', '$super_peer_subfolder'), ('turbo_port', '$super_peer_port')";
				mysqli_query($db_connect, $sql);

				header("Location: turbo_history.php?task=start_transfer");
				exit;
			}
			else
			{
				$body_string.= '<strong><font color="red">Your Server Must Have Atleast the First (4) Starting Transaction History Records in the Database!</font></strong>';
				$quick_info = 'These Records are Created the <strong>First Time</strong> you Run Timekoin with an <strong>Empty Transaction History</strong>.';
				home_screen("Turbo History Updater", $text_bar, $body_string, $quick_info , 0, TRUE, "Turbo History");
				exit;
			}
		}

		if($task_level == 1)
		{
			$body_string = '<strong><font color="blue">Turbo History Update Will Begin in 5 Seconds...</font></strong>';
			$update = 5;
			$sql = "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'turbo_history_update' LIMIT 1";
			mysqli_query($db_connect, $sql);
		}

		if($task_level == 2)
		{
			// How many foundation blocks exist?
			$foundation_blocks = mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `transaction_foundation`"));
			$do_history_walk = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'turbo_block_num' LIMIT 1")));
			$foundation_time_start = $foundation_blocks * 500;
			$foundation_time_end = ($foundation_blocks * 500) + 500;

			if($do_history_walk == 0)
			{
				$do_history_walk = walkhistory($foundation_time_start, $foundation_time_end); // Where is the end of the transaction history?
			}

			$body_string.= '<img src="../img/wait16trans.gif" alt="" /> <strong>Starting From Transaction Cycle# <font color="blue">' . number_format($do_history_walk) . '</font></strong><br><br>';

			// Is this a Super Peer?
			$poll_super_peer = intval(poll_peer($super_peer_ip, $super_peer_domain, $super_peer_subfolder, $super_peer_port, 3, "transclerk.php?action=super_peer"));

			if($poll_super_peer >= 10 && $poll_peer <= 500)// Sanity check on cycles allowed to download
			{
				$super_peer_cycles = $poll_super_peer;
			}
			else
			{
				// Something wrong? Default to 2 cycles bulk download
				$super_peer_cycles = 2;
			}			

			if($do_history_walk >= transaction_cycle(0 - $super_peer_cycles, TRUE))
			{
				// Transaction Data all the way up to within 500 cycles
				header("Location: turbo_history.php?task=transfer_finished");
				exit;
			}

			while($super_peer_cycles > 0)
			{
				$do_history_walk++;
				$poll_peer = filter_sql(poll_peer($super_peer_ip, $super_peer_domain, $super_peer_subfolder, $super_peer_port, 15000000, "transclerk.php?action=transaction_data&block_number=$do_history_walk"));

				if(empty($poll_peer) == TRUE)
				{
					// Data transfer error, save place and stop
					// Store where the record download left off
					$do_history_walk--;
					$sql = "UPDATE `options` SET `field_data` = '$do_history_walk' WHERE `options`.`field_name` = 'turbo_block_num' LIMIT 1";
					mysqli_query($db_connect, $sql);

					$sql = "UPDATE `main_loop_status` SET `field_data` = '99' WHERE `main_loop_status`.`field_name` = 'turbo_history_update' LIMIT 1";
					mysqli_query($db_connect, $sql);

					$body_string = '<strong><font color="red">DATA TRANSFER ERROR: Stopping At Transaction Cycle# ' . $do_history_walk . '</font></strong><br>';
					home_screen("Turbo History Updater", $text_bar, $body_string, "Data Transfer Error!" , 0, TRUE, "Turbo History");
					exit;
				}

				$tc = 1;
				while(empty($poll_peer) == FALSE)
				{
					$transaction_timestamp = intval(find_string("-----timestamp$tc=", "-----public_key_from$tc", $poll_peer));
					$transaction_public_key_from = find_string("-----public_key_from$tc=", "-----public_key_to$tc", $poll_peer);
					$transaction_public_key_to = find_string("-----public_key_to$tc=", "-----crypt1data$tc", $poll_peer);
					$transaction_crypt1 = find_string("-----crypt1data$tc=", "-----crypt2data$tc", $poll_peer);
					$transaction_crypt2 = find_string("-----crypt2data$tc=", "-----crypt3data$tc", $poll_peer);
					$transaction_crypt3 = find_string("-----crypt3data$tc=", "-----hash$tc", $poll_peer);
					$transaction_hash = find_string("-----hash$tc=", "-----attribute$tc", $poll_peer);
					$transaction_attribute = find_string("-----attribute$tc=", "-----end$tc", $poll_peer);

					if(empty($transaction_public_key_from) == TRUE && empty($transaction_public_key_to) == TRUE)
					{
						// No more data, break while loop
						break;
					}

					$transaction_public_key_from = filter_sql(base64_decode($transaction_public_key_from));
					$transaction_public_key_to = filter_sql(base64_decode($transaction_public_key_to));

					// Limit Max Query String to $sql_max_allowed_packet - The start of the query is 153 characters long
					// Many DB have this limit by default and most users may not know how to set it higher :(
					if(153 + strlen($super_peer_insert . ",('$transaction_timestamp', '$transaction_public_key_from', '$transaction_public_key_to', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')") < $sql_max_allowed_packet)
					{
						// Query still under max_allowed_packet in size
						$super_peer_record_count++;

						if($super_peer_record_count == 1)
						{
							$super_peer_insert = "('$transaction_timestamp', '$transaction_public_key_from', '$transaction_public_key_to', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
						}
						else
						{
							$super_peer_insert.= ",('$transaction_timestamp', '$transaction_public_key_from', '$transaction_public_key_to', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
						}
					}
					else
					{
						// Max query size reached, write to database
						$super_peer_full_query = "INSERT INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES " . $super_peer_insert;

						if(mysqli_query($db_connect, $super_peer_full_query) == TRUE)
						{
							$body_string.= '<strong><font color="green">Wrote [' . number_format($super_peer_record_count) . '] Records 
							From SUPER Peer: <font color="blue">' . $super_peer_ip . $super_peer_domain . ':' . $super_peer_port . '/' . $super_peer_subfolder . '</font></font></strong><br><br>';
						}

						// Clear variable from RAM
						unset($super_peer_insert);
						unset($super_peer_full_query);

						// Reset Record Counter
						$super_peer_record_count = 1;

						// Start New INSERT Query
						$super_peer_insert.= "('$transaction_timestamp', '$transaction_public_key_from', '$transaction_public_key_to', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
					}

					$tc++;

				} // End while loop

				$super_peer_cycles--;
			} // End Super Peer Cycles

			// Do mass record insert if query is finished
			if(empty($super_peer_insert) == FALSE)
			{
				if(mysqli_query($db_connect, "INSERT INTO `transaction_history` (`timestamp`,`public_key_from`,`public_key_to`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES " . $super_peer_insert) == TRUE)
				{
					$body_string.= '<strong><font color="green">Wrote [' . number_format($super_peer_record_count) . '] Records 
					From SUPER Peer: <font color="blue">' . $super_peer_ip . $super_peer_domain . ':' . $super_peer_port . '/' . $super_peer_subfolder . '</font></font></strong><br><br>';
				}

				if($super_peer_record_count < 600)
				{
					// For really fast connections, some random delay to avoid getting banned by the server
					if(rand(1,4) == 4)
					{
						$body_string.= '<strong>1 Second Delay to Avoid Flooding Super Peer...</strong><br><br>';
						sleep(1);
					}
				}				
			}

			// Store where the record download left off
			$sql = "UPDATE `options` SET `field_data` = '$do_history_walk' WHERE `options`.`field_name` = 'turbo_block_num' LIMIT 1";
			mysqli_query($db_connect, $sql);

			$body_string.= '<strong>Resuming At Transaction Cycle# <font color="blue">' . number_format($do_history_walk) . '</font> of <font color="blue">' . number_format(transaction_cycle(0, TRUE)) . '</font> [<font color="green">' . number_format($do_history_walk / transaction_cycle(0, TRUE) * 100, 2) . '%</font>]</strong>';

			$update = 1;

		} // Task Level 2

	}// Start Transfer Check

	if($_GET["task"] == "stop_transfer")
	{
		$sql = "UPDATE `main_loop_status` SET `field_data` = '99' WHERE `main_loop_status`.`field_name` = 'turbo_history_update' LIMIT 1";
		mysqli_query($db_connect, $sql);

		$body_string.= '<strong><font color="red">Turbo History STOP!</font><br><br>
		Press "Start Turbo History" Button to Resume</strong>';
	}

	if($_GET["task"] == "transfer_finished")
	{
		// Clear Any Leftover Data Settings
		$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_ip'";
		mysqli_query($db_connect, $sql);

		$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_domain'";
		mysqli_query($db_connect, $sql);

		$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_subfolder'";
		mysqli_query($db_connect, $sql);

		$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_port'";
		mysqli_query($db_connect, $sql);

		$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'turbo_block_num'";
		mysqli_query($db_connect, $sql);

		$sql = "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'turbo_history_update'";
		mysqli_query($db_connect, $sql);

		$body_string = '<strong><font color="blue">Turbo History is Finished!!</font></strong>';
	}

	$quick_info = 'This will use a <strong>Super Peer</strong> as a pipeline to download transaction data directly into your database.<br><br>
	Only use a Super Peer that you <strong>Trust</strong> as this does not perform transaction verification!<br><br>
	<strong><font color="red">Do Not</font></strong> run this on a Live system, it will make a mess of the database!</strong><br><br>';

	home_screen("Turbo History Updater", $text_bar, $body_string, $quick_info , $update, TRUE, "Turbo History");
	exit; // All done processing
}

?>
