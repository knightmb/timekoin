<?PHP
include 'templates.php';

session_name("timekoin");
session_start();

if($_SESSION["valid_login"] == FALSE && $_GET["action"] != "login")
{
	sleep(1); // One second delay to help prevent brute force attack

	$_SESSION["valid_session"] = TRUE;

	if($_SESSION["valid_session"] == TRUE)
	{
		// Not logged in, display login page
		login_screen();
	}

	exit;
}

include 'configuration.php';

if($_SESSION["valid_session"] == TRUE && $_GET["action"] == "login")
{
	$http_username = $_POST["timekoin_username"];
	$http_password = $_POST["timekoin_password"];

	if(empty($http_username) == FALSE && empty($http_password) == FALSE)
	{
		if(mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD) == FALSE)
		{
			login_screen('Could Not Connect To Database');
			exit;
		}
		
		if(mysql_select_db(MYSQL_DATABASE) == FALSE)
		{
			login_screen('Could Not Select Database');
			exit;
		}

		$username_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'username' LIMIT 1"),0,"field_data");
		$password_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'password' LIMIT 1"),0,"field_data");

		if(hash('sha256', $http_username) == $username_hash)
		{
			//Username match, check password
			if(hash('sha256', $http_password) == $password_hash)
			{
				// All match, set login variable and store username in cookie
				$_SESSION["login_username"] = $http_username;
				$_SESSION["valid_login"] = TRUE;
				header("Location: index.php?menu=home");
				exit;
			}
		}
	}

	sleep(1); // One second delay to help prevent brute force attack
	login_screen("Login Failed");
	exit;
}

if($_SESSION["valid_login"] == TRUE)
{
	include 'function.php';
//****************************************************************************
	if(mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD) == FALSE)
	{
		home_screen('ERROR', '<font color="red"><strong>Could Not Connect To Database</strong></font>', '', '');
		exit;
	}
	
	if(mysql_select_db(MYSQL_DATABASE) == FALSE)
	{
		home_screen('ERROR','<font color="red"><strong>Could Not Select Database</strong></font>', '', '');
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "home" || empty($_GET["menu"]) == TRUE)
	{
		$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");

		$body_string = '<table border="0" cellspacing="10" cellpadding="2" bgcolor="#FFFFFF"><tr><td align="center"><strong>Status</strong></td>
			<td align="center"><strong>Program</strong></td><td align="left"><strong>Message</strong></td></tr>';

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Main should still be active
			if((time() - $script_last_heartbeat) > 30) // Greater than triple the loop time, something is wrong
			{
				// Main has stop was unexpected
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Main Program Processor</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Main processor script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Main Program Processor</strong></font></td>
					<td><strong>Normal Operations</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Main Program Processor</strong></font></td>
				<td><strong>Main Program Offline</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'treasurer_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Treasurer should still be active
			if((time() - $script_last_heartbeat) > 60)
			{
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Treasurer Processor</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Treasurer Processor</strong></font></td>
					<td><strong>Examining Transactions for Accuracy...</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/arrow.gif" alt="" /></td><td><font color="#b0a454"><strong>Treasurer Processor</strong></font></td>
				<td><strong>Idle</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peerlist_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peerlist_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Peerlist should still be active
			if((time() - $script_last_heartbeat) > 60)
			{
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Peer Processor</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Peer Processor</strong></font></td>
					<td><strong>Talking to Peers...</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/arrow.gif" alt="" /></td><td><font color="#b0a454"><strong>Peer Processor</strong></font></td>
				<td><strong>Idle</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'queueclerk_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Queueclerk should still be active
			if((time() - $script_last_heartbeat) > 90)
			{
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Transaction Queue Clerk</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Transaction Queue Clerk</strong></font></td>
					<td><strong>Consulting with Peers...</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/arrow.gif" alt="" /></td><td><font color="#b0a454"><strong>Transaction Queue Clerk</strong></font></td>
				<td><strong>Idle</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Genpeer should still be active
			if((time() - $script_last_heartbeat) > 90)
			{
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Generation Peer Manager</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Generation Peer Manager</strong></font></td>
					<td><strong>Consulting with Peers...</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/arrow.gif" alt="" /></td><td><font color="#b0a454"><strong>Generation Peer Manager</strong></font></td>
				<td><strong>Idle</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Generation should still be active
			if((time() - $script_last_heartbeat) > 60)
			{
				// Generation has stop was unexpected
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Generation Processor</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Generation processor script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Generation Processor</strong></font></td>
					<td><strong>Doing Crypto Magic...</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/arrow.gif" alt="" /></td><td><font color="#b0a454"><strong>Generation Processor</strong></font></td>
				<td><strong>Idle</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'transclerk_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Transclerk should still be active
			if((time() - $script_last_heartbeat) > 120)
			{
				// Script has stop was unexpected
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Transaction Clerk</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Transaction Clerk</strong></font></td>
					<td><strong>Consulting with Peers...</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/arrow.gif" alt="" /></td><td><font color="#b0a454"><strong>Transaction Clerk</strong></font></td>
				<td><strong>Idle</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Foundation should still be active
			if((time() - $script_last_heartbeat) > 100)
			{
				// Script has stop was unexpected
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Foundation Manager</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Foundation Manager</strong></font></td>
					<td><strong>Inspecting Transaction Foundations...</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/arrow.gif" alt="" /></td><td><font color="#b0a454"><strong>Foundation Manager</strong></font></td>
				<td><strong>Idle</strong></td></tr>';
		}

		$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,"field_data");
		$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_last_heartbeat' LIMIT 1"),0,"field_data");

		if($script_loop_active > 0)
		{
			// Watchdog should still be active
			if((time() - $script_last_heartbeat) > 60) // Greater than double the loop time, something is wrong
			{
				// Script has stop was unexpected
				$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Watchdog</strong></font></td>
					<td><strong>Program Stalled.</strong></td></tr>';
			}
			else
			{
				// Script is working properly
				$body_string .= '<tr><td align="center"><img src="img/wait16trans.gif" alt="" /></td><td><font color="green"><strong>Watchdog</strong></font></td>
					<td><strong>Active</strong></td></tr>';
			}
		}
		else
		{
			$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="#b0a454"><strong>Watchdog</strong></font></td>
				<td><strong>Disabled</strong></td></tr>';
		}

		$body_string = $body_string . '</table>';

		$display_balance = db_cache_balance($my_public_key);

		$firewall_blocked = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'firewall_blocked_peer' LIMIT 1"),0,"field_data");

		if($firewall_blocked == "1")
		{
			$firewall_blocked = '<tr><td colspan="3"><font color="#827f00"><strong>*** Operating in Outbound Only Mode ***</strong></font></td></tr>';
		}
		else
		{
			$firewall_blocked = NULL;
		}

		$time_sync_error = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'time_sync_error' LIMIT 1"),0,"field_data");

		if($time_sync_error == "1")
		{
			$time_sync_error = '<tr><td colspan="3"><font color="red"><strong>*** Timekoin Might Be Out of Sync with the Network Peers ***</strong></font></td></tr>';
		}
		else
		{
			$time_sync_error = NULL;
		}

		$text_bar = '<table border="0"><tr><td width="250"><strong>Current Server Balance: <font color="green">' . number_format($display_balance) . '</font></strong></td>
			<td width="180"><strong>Peer Time: <font color="blue">' . time() . '</font></strong></td>
			<td><strong><font color="#827f00">' . tk_time_convert(transaction_cycle(1) - time()) . '</font> until next cycle</strong></td></tr>
			' . $firewall_blocked . $time_sync_error . '</table>';

		$quick_info = 'Check on the Status of the Timekoin inner workings.';

		$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");

		home_screen("Realtime Server Status", $text_bar, $body_string, $quick_info , $home_update);
		exit;
	}
//****************************************************************************	
	if($_GET["menu"] == "peerlist")
	{
		if($_GET["remove"] == "peer")
		{
			// Manually remove this peer
			$sql = "DELETE FROM `active_peer_list` WHERE `active_peer_list`.`IP_Address` = '" . $_POST["ip"] . "' AND `active_peer_list`.`domain` = '" . $_POST["domain"] . "' LIMIT 1";
			mysql_query($sql);
		}

		if($_GET["save"] == "peer" && empty($_POST["edit_port"]) == FALSE)
		{
			// Save manual peer edit
			if($_POST["perm_peer"] == "perm")
			{
				$join_peer_list = '0';
			}
			else
			{
				$join_peer_list = 'UNIX_TIMESTAMP()';
			}
			
			$sql = "UPDATE `active_peer_list` SET `last_heartbeat` = UNIX_TIMESTAMP() ,`join_peer_list` = $join_peer_list , `failed_sent_heartbeat` = '0',
				`IP_Address` = '" . $_POST["edit_ip"] . "', `domain` = '" . $_POST["edit_domain"] . "', `subfolder` = '" . $_POST["edit_subfolder"] . "', `port_number` = '" . $_POST["edit_port"] . "'
				WHERE `active_peer_list`.`IP_Address` = '" . $_POST["update_ip"] . "' AND `active_peer_list`.`domain` = '" . $_POST["update_domain"] . "' LIMIT 1";
			mysql_query($sql);
		}

		if($_GET["save"] == "newpeer" && empty($_POST["edit_port"]) == FALSE)
		{
			// Manually insert new peer
			$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat` ,`join_peer_list` ,`failed_sent_heartbeat`)
				VALUES ('" . $_POST["edit_ip"] . "', '" . $_POST["edit_domain"] . "', '" . $_POST["edit_subfolder"] . "', '" . $_POST["edit_port"] . "', UNIX_TIMESTAMP() , UNIX_TIMESTAMP() , '0')";
			mysql_query($sql);
		}

		if($_GET["save"] == "firstcontact")
		{
			// Wipe Current First Contact Server List and Save the New List
			$field_numbers = intval($_POST["field_numbers"]);

			if($field_numbers > 0)
			{
				mysql_query("DELETE FROM `options` WHERE `options`.`field_name` = 'first_contact_server'");

				while($field_numbers > 0)
				{
					if(empty($_POST["first_contact_ip$field_numbers"]) == FALSE || empty($_POST["first_contact_domain$field_numbers"]) == FALSE)
					{
						$sql = "INSERT INTO `options` (`field_name` ,`field_data`) 
							VALUES ('first_contact_server', '---ip=" . $_POST["first_contact_ip$field_numbers"] . 
							"---domain=" . $_POST["first_contact_domain$field_numbers"] . 
							"---subfolder=" . $_POST["first_contact_subfolder$field_numbers"] . 
							"---port=" . $_POST["first_contact_port$field_numbers"] . "---end')";

						mysql_query($sql);
					}
					
					$field_numbers--;
				}
			}
		}

		if($_GET["edit"] == "peer")
		{
			$body_string = '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>IP Address</th>
				<th>Domain</th><th>Subfolder</th><th>Port Number</th><th></th><th></th></tr>';			

			if($_GET["type"] == "new")
			{
				// Manually add a peer
				$body_string .= '<FORM ACTION="index.php?menu=peerlist&save=newpeer" METHOD="post"><tr>
				 <td class="style2"><input type="text" name="edit_ip" size="13" /></td>
				 <td class="style2"><input type="text" name="edit_domain" size="20" /></td>
				 <td class="style2"><input type="text" name="edit_subfolder" size="10" /></td>
				 <td class="style2"><input type="text" name="edit_port" size="5" /></td>			 
				 <td><input type="image" src="img/save-icon.gif" name="submit1" border="0"></FORM></td><td>
				 <FORM ACTION="index.php?menu=peerlist" METHOD="post">
				 <input type="image" src="img/hr.gif" name="submit2" border="0"></FORM>
				 </td></tr>';

				$body_string .= '</table></div>';				
			}
			else if($_GET["type"] == "firstcontact")
			{
				$sql = "SELECT *  FROM `options` WHERE `field_name` = 'first_contact_server'";
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result) + 2;
				$counter = 1;
				$body_string .= '<FORM ACTION="index.php?menu=peerlist&save=firstcontact" METHOD="post">';

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);

					$peer_ip = find_string("---ip=", "---domain", $sql_row["field_data"]);
					$peer_domain = find_string("---domain=", "---subfolder", $sql_row["field_data"]);
					$peer_subfolder = find_string("---subfolder=", "---port", $sql_row["field_data"]);
					$peer_port_number = find_string("---port=", "---end", $sql_row["field_data"]);
				
					$body_string .= '<tr><td class="style2"><input type="text" name="first_contact_ip' . $counter . '" size="13" value="' . $peer_ip . '" /></br></br></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_domain' . $counter . '" size="20" value="' . $peer_domain . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_subfolder' . $counter . '" size="10" value="' . $peer_subfolder . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_port' . $counter . '" size="5" value="' . $peer_port_number . '" /></td>			 
					</td></tr>';

					$counter++;
				}

				$body_string .= '<input type="hidden" name="field_numbers" value="' . ($counter - 1) . '">
					<tr><td colspan="2"><input type="submit" value="Save First Contact Servers"/></FORM></td></tr>';
				$body_string .= '</table></div>';
			}
			else
			{
				// Manually edit this peer
				$sql = "SELECT * FROM `active_peer_list` WHERE `IP_Address` = '" . $_POST["ip"] ."' AND `domain` = '" . $_POST["domain"] ."' LIMIT 1";
				$sql_result = mysql_query($sql);
				$sql_row = mysql_fetch_array($sql_result);

				$body_string .= '<FORM ACTION="index.php?menu=peerlist&save=peer" METHOD="post"><tr>
				<td class="style2"><input type="text" name="edit_ip" size="13" value="' . $sql_row["IP_Address"] . '" /></br></br>
				<select name="perm_peer"><option value="expires" SELECTED>Purge When Inactive</option><option value="perm">Do Not Purge</select></td>
				<td class="style2" valign="top"><input type="text" name="edit_domain" size="20" value="' . $sql_row["domain"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_subfolder" size="10" value="' . $sql_row["subfolder"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_port" size="5" value="' . $sql_row["port_number"] . '" /></td>			 
				<td valign="top"><input type="hidden" name="update_ip" value="' . $sql_row["IP_Address"] . '">
				<input type="hidden" name="update_domain" value="' . $sql_row["domain"] . '">
				<input type="image" src="img/save-icon.gif" name="submit1" border="0"></FORM></td>
				<td valign="top"><FORM ACTION="index.php?menu=peerlist" METHOD="post">
				<input type="image" src="img/hr.gif" name="submit2" border="0"></FORM>
				</td></tr>';

				$body_string .= '</table></div>';
			}

			$sql = "SELECT * FROM `active_peer_list`";
			$active_peers = mysql_num_rows(mysql_query($sql));

			$sql = "SELECT * FROM `new_peers_list`";
			$new_peers = mysql_num_rows(mysql_query($sql));

			$peer_number_bar = '<strong>Active Peers: <font color="green">' . $active_peers . '</font>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Peers in Reserve: <font color="blue">' . $new_peers . '</font></strong>';

			$quick_info = 'Shows all Active Peers.</br></br>
				You can manually delete or edit peers in this section.</br></br>
				<font color="blue">First Contact Servers</font> can be changed, deleted, or new ones added to the bottom of the list.';

			home_screen('Realtime Network Peer List', $peer_number_bar, $body_string , $quick_info);
		}
		else
		{
			// Default screen
			$body_string = '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr>
				<th><p style="font-size:10px;">IP Address</p></th><th><p style="font-size:10px;">Domain</p></th>
				<th><p style="font-size:10px;">Subfolder</p></th><th><p style="font-size:10px;">Port Number</p></th>
				<th><p style="font-size:10px;">Last Heartbeat</p></th><th><p style="font-size:10px;">Joined</p></th>
				<th><p style="font-size:10px;">Failed Heartbeat</p></th><th></th><th></th></tr>';			
			
			if($_GET["show"] == "reserve")
			{
				$sql = "SELECT * FROM `new_peers_list`";
			}
			else
			{
				$sql = "SELECT * FROM `active_peer_list`";
			}

			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				if($_GET["show"] != "reserve")
				{
					$last_heartbeat = time() - $sql_row["last_heartbeat"];
					$last_heartbeat = tk_time_convert($last_heartbeat);

					if($sql_row["join_peer_list"] == 0)
					{
						$joined = 'P';
						$permanent1 = '<font color="blue">';
						$permanent2 = '</font>';
					}
					else
					{
						$joined = time() - $sql_row["join_peer_list"];
						$joined = tk_time_convert($joined);
						$permanent1 = NULL;
						$permanent2 = NULL;
					}

				}

				$body_string .= '<tr>
				 <td class="style2"><p style="word-wrap:break-word; width:85px; font-size:10px;">' . $permanent1 . $sql_row["IP_Address"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; width:130px; font-size:10px;">' . $permanent1 . $sql_row["domain"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; width:55px; font-size:10px;">' . $permanent1 . $sql_row["subfolder"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:10px;">' . $permanent1 . $sql_row["port_number"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $last_heartbeat . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $joined . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $sql_row["failed_sent_heartbeat"] . $permanent2 . '</p></td>';

				if($_GET["show"] == "reserve")
				{
					$body_string .= '<td></td><td></td></tr>';
				}
				else
				{
					$body_string .= '<td><FORM ACTION="index.php?menu=peerlist&remove=peer" METHOD="post"><input type="image" src="img/hr.gif" name="remove' . $i . '" border="0">
					 <input type="hidden" name="ip" value="' . $sql_row["IP_Address"] . '">
					 <input type="hidden" name="domain" value="' . $sql_row["domain"] . '">
					 </FORM></td><td>
					 <FORM ACTION="index.php?menu=peerlist&edit=peer" METHOD="post"><input type="image" src="img/edit-icon.gif" name="edit' . $i . '" border="0">
					 <input type="hidden" name="ip" value="' . $sql_row["IP_Address"] . '">
					 <input type="hidden" name="domain" value="' . $sql_row["domain"] . '">
					 </FORM>
					 </td></tr>';
				}
			}

			$body_string .= '<tr><td colspan="2"><FORM ACTION="index.php?menu=peerlist&show=reserve" METHOD="post"><input type="submit" value="Show Reserve Peers"/></FORM></td>
				<td colspan="3"><FORM ACTION="index.php?menu=peerlist&edit=peer&type=new" METHOD="post"><input type="submit" value="Add New Peer"/></FORM></td>
				<td colspan="4"><FORM ACTION="index.php?menu=peerlist&edit=peer&type=firstcontact" METHOD="post"><input type="submit" value="First Contact Servers"/></FORM></td></tr></table></div>';

			$sql = "SELECT * FROM `new_peers_list`";
			$new_peers = mysql_num_rows(mysql_query($sql));		

			if($_GET["show"] == "reserve")
			{
				$sql = "SELECT * FROM `active_peer_list`";
				$sql_num_results = mysql_num_rows(mysql_query($sql));
			}

			$peer_number_bar = '<strong>Active Peers: <font color="green">' . $sql_num_results . '</font>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Peers in Reserve: <font color="blue">' . $new_peers . '</font></strong>';

			$quick_info = 'Shows all Active Peers.</br></br>You can manually delete or edit peers in this section.
				</br></br>Peers in <font color="blue">Blue</font> will not expire after 5 minutes of inactivity.';

			$peerlist_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_peerlist' LIMIT 1"),0,"field_data");

			if($_GET["show"] == "reserve")
			{
				home_screen('Reserve Peer List', $peer_number_bar, $body_string , $quick_info);
			}
			else
			{
				home_screen('Realtime Network Peer List', $peer_number_bar, $body_string , $quick_info, $peerlist_update);
			}
		}
		exit;
	}	
//****************************************************************************
	if($_GET["menu"] == "system")
	{
		if($_GET["peer_settings"] == "change")
		{
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["max_peers"] . "' WHERE `options`.`field_name` = 'max_active_peers' LIMIT 1";
			if(mysql_query($sql) == TRUE)
			{
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["max_new_peers"] . "' WHERE `options`.`field_name` = 'max_new_peers' LIMIT 1";
				if(mysql_query($sql) == TRUE)
				{
					$server_code = '</br><font color="green"><strong>Peer Settings Updated...</strong></font></br></br>';
				}
			}
		}

		if($_GET["server_settings"] == "change")
		{
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["domain"] . "' WHERE `options`.`field_name` = 'server_domain' LIMIT 1";
			if(mysql_query($sql) == TRUE)
			{
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["subfolder"] . "' WHERE `options`.`field_name` = 'server_subfolder' LIMIT 1";
				if(mysql_query($sql) == TRUE)
				{
					$sql = "UPDATE `options` SET `field_data` = '" . $_POST["port"] . "' WHERE `options`.`field_name` = 'server_port_number' LIMIT 1";
					if(mysql_query($sql) == TRUE)
					{
						$sql = "UPDATE `options` SET `field_data` = '" . $_POST["max_request"] . "' WHERE `options`.`field_name` = 'server_request_max' LIMIT 1";
						if(mysql_query($sql) == TRUE)
						{
							$sql = "UPDATE `options` SET `field_data` = '" . $_POST["allow_LAN"] . "' WHERE `options`.`field_name` = 'allow_LAN_peers' LIMIT 1";
							if(mysql_query($sql) == TRUE)
							{
								$sql = "UPDATE `options` SET `field_data` = '" . $_POST["allow_ambient"] . "' WHERE `options`.`field_name` = 'allow_ambient_peer_restart' LIMIT 1";
								if(mysql_query($sql) == TRUE)
								{
									$server_code = '</br><font color="blue"><strong>Server Settings Updated...</strong></font></br></br>';
								}								
							}
						}
					}
				}
			}
		}

		if($_GET["stop"] == "watchdog")
		{
			$watchdog_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_heartbeat_active' LIMIT 1"),0,"field_data");			
			$watchdog_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'watchdog_last_heartbeat' LIMIT 1"),0,"field_data");

			if($watchdog_loop_active > 0)
			{
				// Watchdog should still be active
				if((time() - $watchdog_last_heartbeat) > 60) // Greater than double the loop time, something is wrong
				{
					// Watchdog stop was unexpected
					$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1";
					
					if(mysql_query($sql) == TRUE)
					{
						$server_code = '</br><font color="red"><strong>Watchdog was already Stopped...</strong></font></br></br>';
					}
				}
				else
				{
					// Set database to flag watchdog to stop
					$sql = "UPDATE `main_loop_status` SET `field_data` = '3' WHERE `main_loop_status`.`field_name` = 'watchdog_heartbeat_active' LIMIT 1";
					
					if(mysql_query($sql) == TRUE)
					{
						$server_code = '</br><font color="blue"><strong>Watchdog Stopping...</strong></font></br></br>';
					}
				}
			}
			else
			{
				$server_code = '</br><font color="red"><strong>Watchdog was already Stopped...</strong></font></br></br>';
			}
		}

		if($_GET["stop"] == "main")
		{
			$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data");
			$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_last_heartbeat' LIMIT 1"),0,"field_data");

			if($script_loop_active > 0)
			{
				// Main should still be active
				if((time() - $script_last_heartbeat) > 30) // Greater than triple the loop time, something is wrong
				{
					// Main stop was unexpected
					$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
					
					if(mysql_query($sql) == TRUE)
					{
						$server_code = '</br><font color="red"><strong>Timekoin Main Processor was already Stopped...</strong></font></br></br>';
						// Clear transaction queue to avoid unnecessary peer confusion
						mysql_query("TRUNCATE TABLE `transaction_queue`");
					}
				}
				else
				{
					// Set database to flag watchdog to stop
					$sql = "UPDATE `main_loop_status` SET `field_data` = '3' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
					
					if(mysql_query($sql) == TRUE)
					{
						$server_code = '</br><font color="blue"><strong>Timekoin Main Processor Stopping...</strong></font></br></br>';
						// Clear transaction queue to avoid unnecessary peer confusion
						mysql_query("TRUNCATE TABLE `transaction_queue`");
					}
				}
			}
			else
			{
				$server_code = '</br><font color="red"><strong>Timekoin Main Processor was already Stopped...</strong></font></br></br>';
				// Clear transaction queue to avoid unnecessary peer confusion
				mysql_query("TRUNCATE TABLE `transaction_queue`");				
			}
		}

		if($_GET["stop"] == "emergency")
		{
			$script_loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,"field_data");
			$script_last_heartbeat = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'main_last_heartbeat' LIMIT 1"),0,"field_data");

			if($script_loop_active > 0)
			{
				// Main should still be active
				if((time() - $script_last_heartbeat) > 30) // Greater than triple the loop time, something is wrong
				{
					// Main stop was unexpected
					$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
					
					if(mysql_query($sql) == TRUE)
					{
						$server_code = '</br><font color="red"><strong>Entire Timekoin System has been Halted!</strong></font></br></br>';
						activate(TIMEKOINSYSTEM, 0);
					}
				}
				else
				{
					// Set database to flag watchdog to stop
					$sql = "UPDATE `main_loop_status` SET `field_data` = '3' WHERE `main_loop_status`.`field_name` = 'main_heartbeat_active' LIMIT 1";
					
					if(mysql_query($sql) == TRUE)
					{
						$server_code = '</br><font color="red"><strong>Entire Timekoin System has been Halted!</strong></font></br></br>';
						activate(TIMEKOINSYSTEM, 0);
					}
				}
			}
			else
			{
				$server_code = '</br><font color="red"><strong>Entire Timekoin System has been Halted!</strong></font></br></br>';
				activate(TIMEKOINSYSTEM, 0);				
			}
		}

		if($_GET["code"] == "1")
		{
			$server_code = '</br><font color="green"><strong>Main Timekoin Processing Started...</strong></font></br></br>';
		}
		if($_GET["code"] == "99")
		{
			$server_code = '</br><font color="blue"><strong>Timekoin Already Active...</strong></font></br></br>';
		}		
		if($_GET["code"] == "2")
		{
			$server_code = '</br><font color="green"><strong>Watchdog Started...</strong></font></br></br>';
		}
		if($_GET["code"] == "89")
		{
			$server_code = '</br><font color="blue"><strong>Watchdog Already Active...</strong></font></br></br>';
		}

		if($_GET["time"] == "poll")
		{
			$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
			ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
			ini_set('default_socket_timeout', 3); // Timeout for request in seconds
			$body_string = '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" >
				<tr><th>Peer</th><th>Time</th><th>Variance</th></tr>';

			// Add more possible peers to the new peer list by polling what the active peers have
			$sql = "SELECT * FROM `active_peer_list`";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);
			$response_counter = 0;
			$variance_total = 0;

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

				//Use site address name to poll
				$poll_peer = filter_sql(file_get_contents("http$ssl://$site_address:$port_number/$subfolder/peerlist.php?action=polltime", FALSE, $context, NULL, 12));

				$my_time = time();
				
				if($my_time == $poll_peer && empty($poll_peer) == FALSE)
				{
					$variance = '0 seconds';
					$response_counter++;
				}
				else if(empty($poll_peer) == FALSE)
				{
					$variance = $my_time - $poll_peer;
					$response_counter++;
					$variance_total = $variance_total + abs($variance);

					if($variance > 1)
					{
						$variance = '+' . $variance . ' seconds';
					}
					else if($variance == 1)
					{
						$variance = '+' . $variance . ' second';
					}
					else if($variance == -1)
					{
						$variance = $variance . ' second';
					}
					else
					{
						$variance = $variance . ' seconds';
					}					
				}
				else
				{
					$variance = 'No Response';
				}

				$body_string .= '<tr><td class="style2"><p style="word-wrap:break-word; font-size:12px;">' . $site_address . ':' . $port_number . '/' . $subfolder . '</p></td>';
				$body_string .= '<td class="style2"><p style="font-size:12px;">' . $poll_peer . '</p></td>';
				$body_string .= '<td class="style2"><p style="font-size:12px;">' . $variance . '</p></td></tr>';
			
			}

			$body_string .= '</table></div>';

			$variance_average = round($variance_total / $response_counter);

			if($variance_average > 15)
			{
				$variance_average = '<font color="red">' . $variance_average . '</font> seconds';
			}
			else if($variance_average == 1)
			{
				$variance_average = '<font color="green">' . $variance_average . '</font> second';
			}
			else if($variance_average <= 15 && $variance_average > 1)
			{
				$variance_average = '<font color="blue">' . $variance_average . '</font> seconds';
			}
			else
			{
				$variance_average = '<font color="green">' . $variance_average . '</font> seconds';
			}

			$body_string .= '<strong>Variance Average: ' . $variance_average . '</strong></br></br>';
		}
		else
		{
			$body_string = system_screen();
			$body_string .= $server_code;
		}

		$quick_info = '<strong>Start</strong> will activate all Timekoin Processing.</br></br>
			<strong>Stop</strong> will halt Timekoin from further processing.</br></br>
			<strong>Emergency Stop</strong> will halt Timekoin from further processing and Block all Peer Internet activity.</br></br>
			<strong>Max Peer Query</strong> is the per 10 seconds limit imposed on each individual peer before being banned for 24 hours.</br></br>
			<strong>Allow LAN Peers</strong> controls if LAN peers will be allowed to populate the peer list.</br></br>
			<strong>Allow Ambient Peer Restarts</strong> controls if other peers can restart Timekoin from unknown failures.</br></br>
			<strong>Variance</strong> of 15 seconds or less with the other peers is good.';

		home_screen('System Settings', system_service_bar(), $body_string , $quick_info);
		exit;
	}

//****************************************************************************
	if($_GET["menu"] == "options")
	{
		if($_GET["menu"] == "options" && $_GET["password"] == "change")
		{
			if(empty($_POST["current_username"]) == FALSE && empty($_POST["new_username"]) == FALSE && empty($_POST["confirm_username"]) == FALSE)
			{
				// Attemping to change username
				if($_POST["current_username"] == $_SESSION["login_username"])
				{
					// Right username, does the new username match the confirmation username?
					if($_POST["new_username"] == $_POST["confirm_username"])
					{
						// Write new hash to database for username and change the session username
						$username_hash = hash('sha256', $_POST["confirm_username"]);

						$sql = "UPDATE `options` SET `field_data` = '$username_hash' WHERE `options`.`field_name` = 'username' LIMIT 1";

						if(mysql_query($sql) == TRUE)
						{
							// Update success, now change the session username
							$_SESSION["login_username"] = $_POST["confirm_username"];
							$username_change = TRUE;
						}
					}
				}
			}

			if(empty($_POST["current_password"]) == FALSE && empty($_POST["new_password"]) == FALSE && empty($_POST["confirm_password"]) == FALSE)
			{
				$password_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'password' LIMIT 1"),0,"field_data");
				$current_password_hash = hash('sha256', $_POST["current_password"]);
				$new_password_hash = hash('sha256', $_POST["new_password"]);

				// Attemping to change password
				if($current_password_hash == $password_hash)
				{
					// Right password, does the new password match the confirmation password?
					if($_POST["new_password"] == $_POST["confirm_password"])
					{
						// Write new hash to database for username and change the session username
						$sql = "UPDATE `options` SET `field_data` = '$new_password_hash' WHERE `options`.`field_name` = 'password' LIMIT 1";

						if(mysql_query($sql) == TRUE)
						{
							$password_change = TRUE;
						}
					}
				}
			}

			$body_text = options_screen2();

			if($username_change == TRUE)
			{
				$body_text = $body_text . '<font color="blue"><strong>Username Change Complete!</strong></font></br>';
			}
			else
			{
				$body_text = $body_text . '<strong>Username Has Not Been Changed</strong></br>';
			}

			if($password_change == TRUE)
			{
				$body_text = $body_text . '<font color="blue"><strong>Password Change Complete!</strong></font>';
			}
			else
			{
				$body_text = $body_text . '<strong>Password Has Not Been Changed</strong>';
			}
		} // End username/password change check

		if($_GET["menu"] == "options" && $_GET["refresh"] == "change")
		{
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["home_update"] . "' WHERE `options`.`field_name` = 'refresh_realtime_home' LIMIT 1";
			if(mysql_query($sql) == TRUE)
			{
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["peerlist_update"] . "' WHERE `options`.`field_name` = 'refresh_realtime_peerlist' LIMIT 1";
				if(mysql_query($sql) == TRUE)
				{
					$sql = "UPDATE `options` SET `field_data` = '" . $_POST["queue_update"] . "' WHERE `options`.`field_name` = 'refresh_realtime_queue' LIMIT 1";
					if(mysql_query($sql) == TRUE)
					{
						$hash_code = $_POST["hash_code"];
						
						// Sanitization of message !#$%&'*+-/=?^_`{|}~@.[] allowed 
						$hash_code = filter_var($hash_code, FILTER_SANITIZE_EMAIL);

						// Filter symbols that might lead to an HTML access error
						$symbols = array("'", "%", "*", "$", "`", "?", "=", "~", "&", "#", "/", "+",);

						$hash_code = str_replace($symbols, "", $hash_code);
						
						$sql = "UPDATE `options` SET `field_data` = '" . $hash_code . "' WHERE `options`.`field_name` = 'server_hash_code' LIMIT 1";
						if(mysql_query($sql) == TRUE)
						{						
							$refresh_change = TRUE;
						}						
					}
				}
			}

			$body_text = options_screen2();

			if($refresh_change == TRUE)
			{
				$body_text .= '<font color="blue"><strong>Refresh Settings & Hash Code Update Saved!</strong></font></br>';
			}
			else
			{
				$body_text .= '<strong>Refresh / Hash Code Update ERROR...</strong></br>';
			}
		} // End refresh update save
		else if(empty($_GET["password"]) == TRUE && empty($_GET["refresh"]) == TRUE)
		{
			$body_text = options_screen2();
		}

		$quick_info = 'You may change the username and password individually or at the same time.</br></br>
			Remember that usernames and passwords are Case Sensitive.
			</br></br><strong>Hash Code</strong> is a private code you create for any external program or server that request access to more advanced features of your Timekoin server.';

		home_screen("Options & Personal Settings", options_screen(), $body_text , $quick_info);
		exit;
	}	
//****************************************************************************	
	if($_GET["menu"] == "generation")
	{
		if($_GET["generate"] == "enable")
		{
			$sql = "UPDATE `options` SET `field_data` = '1' WHERE `options`.`field_name` = 'generate_currency' LIMIT 1";
			mysql_query($sql);
		}
		else if($_GET["generate"] == "disable")
		{
			$sql = "UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'generate_currency' LIMIT 1";
			mysql_query($sql);
		}

		$sql = "SELECT * FROM `generating_peer_queue`";
		$generate_peer_queue = mysql_num_rows(mysql_query($sql));

		$generate_currency_enabled = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'generate_currency' LIMIT 1"),0,"field_data");		

		$sql = "SELECT * FROM `generating_peer_list`";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		$generating_peers_now = $sql_num_results;

		if($generate_currency_enabled == "1")
		{
			$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");
			$join_peer_list = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,"join_peer_list");
			$last_generation = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,"last_generation");

			if(time() - $join_peer_list < 3600)
			{
				// Can't generate yet
				$generate_currency = 'Generation <font color="green"><strong>Enabled</strong></font>';
				$generate_rate = '@ <font color="green"><strong>' . peer_gen_amount($my_public_key) . '</strong></font> per Cycle';
				$continuous_production = '<font color="blue">Generation not allowed for ' . tk_time_convert(3600 - (time() - $join_peer_list)) . '</font>';
			}
			else if($join_peer_list === FALSE)
			{
				// Not elected to the generating peer list yet
				$generate_currency = 'Generation <font color="green"><strong>Enabled</strong></font>';
				$generate_rate = '@ <font color="green"><strong>' . peer_gen_amount($my_public_key) . '</strong></font> per Cycle';
				$continuous_production = '<font color="red"><strong>This Peer Has Not</br> Been Elected Yet</strong></font>';
			}
			else
			{
				$production_time = tk_time_convert(time() - $join_peer_list);
				$last_generation = tk_time_convert(time() - $last_generation);

				$generate_currency = 'Generation <font color="green"><strong>Enabled</strong></font>';
				$generate_rate = '@ <font color="green"><strong>' . peer_gen_amount($my_public_key) . '</strong></font> per Cycle';
				$continuous_production = 'Continuous Production for ' . $production_time . '</br>Last Generated ' . $last_generation . ' ago';
			}
		}
		else
		{
			$generate_currency = 'Generation <font color="red">Disabled</strong></font>';
		}

		$body_string = generation_body($generate_currency_enabled);

		if($_GET["generate"] == "showlist")
		{
			$default_public_key_font = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
			$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");

			$body_string = $body_string . '<hr></hr><strong>Current Generation List</strong>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Public Key</th><th>Joined</th><th>Last Generated</th></tr>';

			$sql = "SELECT * FROM `generating_peer_list` ORDER BY `join_peer_list` ASC";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				if($my_public_key == $sql_row["public_key"])
				{
					$public_key = '<p style="font-size:12px;"><font color="green"><strong>My Public Key</strong></font>';
				}
				else
				{
					$public_key = '<p style="word-wrap:break-word; width:325px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($sql_row["public_key"]);
				}

				$body_string .= '<tr>
				<td class="style2">' . $public_key . '</p></td>
				<td class="style2"><p style="font-size:10px;">' . unix_timestamp_to_human($sql_row["join_peer_list"]) . '</p></td>
				<td class="style2"><p style="font-size:10px;">' . tk_time_convert(time() - $sql_row["last_generation"]) . ' ago</p></td></tr>';
			}

			$body_string .= '</table></div>';
		}

		if($_GET["generate"] == "showqueue")
		{
			$default_public_key_font = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
			$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");

			$body_string .= '<hr></hr><strong>Election Queue List</strong>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Public Key</th><th>Join Queue</th></tr>';

			$sql = "SELECT * FROM `generating_peer_queue` ORDER BY `timestamp` ASC";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				if($my_public_key == $sql_row["public_key"])
				{
					$public_key = '<p style="font-size:12px;"><font color="green"><strong>My Public Key</strong></font>';
				}
				else
				{
					$public_key = '<p style="word-wrap:break-word; width:425px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($sql_row["public_key"]);
				}

				$body_string .= '<tr>
				<td class="style2">' . $public_key . '</p></td>
				<td class="style2"><p style="font-size:10px;">' . tk_time_convert(time() - $sql_row["timestamp"]) . ' ago</p></td></tr>';
			}

			$body_string .= '</table></div>';
		}

		// Next Election Calculator
		// Determine when to run this by comparing the last digit the current block and
		// the 3rd digit the generation time; when they match, run the gen key scoring.
		$max_cycles_ahead = 200;
		
		for ($i = 0; $i < $max_cycles_ahead; $i++)
		{
			$current_generation_cycle = transaction_cycle($i);
			
			$str = strval($current_generation_cycle);
			$last3_gen = $str[strlen($str)-3];

			$current_generation_block = transaction_cycle($i, TRUE);
			TKRandom::seed($current_generation_block);
			$tk_random_number = TKRandom::num(0, 9);

			if($last3_gen + $tk_random_number > 14)
			{
				$time_election = '<font color="blue"><strong>' . tk_time_convert($current_generation_cycle - time());
				break;
			}
		}

		if(empty($time_election) == TRUE)
		{
			$time_election = 'more than <font color="blue"><strong>' . tk_time_convert($max_cycles_ahead * 300);
		}

		$text_bar = '<table cellspacing="10" border="0"><tr><td valign="top" width="230">' . $generate_currency . '</td><td>Generating Peers: <font color="green"><strong>' . $generating_peers_now . '</strong></font></br>
			Queue for Election: <font color="blue"><strong>' . $generate_peer_queue . '</strong></font></td></tr>
			<tr><td align="right">' . $continuous_production . '</td><td>' . $generate_rate . '</td></tr></table>';

		$quick_info = 'You must remain online to generate currency.</br></br>The longer your server participates, the more it will be allowed to generate as time progresses.</br></br>
			If your server is offline for more than 2 hours, your server will have to rejoin the peer list and any time status will be lost.</br></br>
			Next Peer Election in ' . $time_election . '</strong></font>';
		
		home_screen('Crypto Currency Generation', $text_bar, $body_string , $quick_info);
		exit;
	}	
//****************************************************************************	
	if($_GET["menu"] == "send")
	{
		$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");

		if($_GET["check"] == "key")
		{
			$send_amount = $_POST["send_amount"];
			$public_key_64 = $_POST["send_public_key"];			
			$public_key_to = base64_decode($public_key_64);
			$current_balance = db_cache_balance($my_public_key);			

			if($send_amount > $current_balance)
			{
				// Can't send this much silly
				$display_balance = db_cache_balance($my_public_key);
				$body_string = send_receive_body($public_key_64);
				$body_string .= '<hr></hr><font color="red"><strong>This exceeds your current balance, send failed...</strong></font></br></br>';
			}
			else
			{
				if($my_public_key == $public_key_to)
				{
					// Can't send to yourself
					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body();
					$body_string .= '<hr></hr><font color="red"><strong>Can not send to yourself, send failed...</strong></font></br></br>';
				}
				else
				{
					// Check if public key is valid by searching for any transactions
					// that reference it
					$valid_key_test = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `public_key_from` = '$public_key_to' OR `public_key_to` = '$public_key_to' LIMIT 1"),0,"timestamp");

					if(empty($valid_key_test) == TRUE)
					{
						// No key history, might not be valid
						$message = $_POST["send_message"];
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body($public_key_64, $send_amount, TRUE, NULL, $message);
						$body_string .= '<hr></hr><font color="red"><strong>This public key may not be valid as it has no existing history of transactions.</br>
							There is no way to recover timekoins sent to the wrong public key.</br>
							Click "Send Timekoins" to send now.</strong></font></br></br>';
					}
					else
					{
						// Key has a valid history
						$message = $_POST["send_message"];
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body($public_key_64, $send_amount, TRUE, NULL, $message);
						$body_string .= '<hr></hr><font color="blue"><strong>This public key is valid.</font></br>
							<font color="red">There is no way to recover timekoins sent to the wrong public key.</font></br>
							<font color="blue">Click "Send Timekoins" to send now.</strong></font></br></br>';
					}
				} // End self check
			} // End balance check
		}
		else
		{
			if($_GET["complete"] == "send")
			{
				// Build the transaction and insert into the queue
				$send_amount = $_POST["send_amount"];
				$public_key_64 = $_POST["send_public_key"];
				$message = $_POST["send_message"];
				$public_key_to = base64_decode($public_key_64);
				$current_balance = db_cache_balance($my_public_key);			

				if($send_amount > $current_balance)
				{
					// Can't send this much silly
					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body($public_key_64);
					$body_string .= '<hr></hr><font color="red"><strong>This exceeds your current balance, send failed...</strong></font></br></br>';
				}
				else
				{
					if($my_public_key == $public_key_to)
					{
						// Can't send to yourself
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body();
						$body_string .= '<hr></hr><font color="red"><strong>Can not send to yourself, send failed...</strong></font></br></br>';
					}
					else
					{
						// Now it's time to send the transaction
						$my_private_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,"field_data");

						if(send_timekoins($my_private_key, $my_public_key, $public_key_to, $send_amount, $message) == TRUE)
						{
							$display_balance = db_cache_balance($my_public_key);
							$body_string = send_receive_body($public_key_64, $send_amount);
							$body_string .= '<hr></hr><font color="green"><strong>You just sent ' . $send_amount . ' timekoins to the above public key.</font></br>
								Your balance will not reflect this until the transation is recorded across the entire network.</strong></br></br>';
						}
						else
						{
							$display_balance = db_cache_balance($my_public_key);
							$body_string = send_receive_body($public_key_64, $send_amount);
							$body_string .= '<hr></hr><font color="red"><strong>Send failed...</strong></font></br></br>';
						}
					} // End duplicate self check
				} // End Balance Check
			} // End check send command
			else
			{
				if($_GET["easykey"] == "grab")
				{
					ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
					ini_set('default_socket_timeout', 10); // Timeout for request in seconds
					$message = $_POST["send_message"];
					$easy_key = filter_sql($_POST["easy_key"]); // Filter SQL just in case
					$last_easy_key = filter_sql($_POST["easy_key"]); // Filter SQL just in case

					// Translate Easy Key to Public Key and fill in field with
					$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
					$easy_key = filter_sql(file_get_contents("http://timekoin.net/easy.php?s=$easy_key", FALSE, $context, NULL, 500));
					$easy_key = filter_sql($easy_key);

					if($easy_key == "ERROR" || empty($easy_key) == TRUE)
					{
						$server_message = '<font color="red"><strong>' . $last_easy_key . ' Not Found. Check Your Spelling.</strong></font>';
						$easy_key = NULL;
					}
					else
					{
						$server_message = '<font color="blue"><strong>Easy Key Found</strong></font>';
					}
				}
				
				// No selections made, default screen
				$display_balance = db_cache_balance($my_public_key);
				$body_string = send_receive_body($easy_key, NULL, NULL, $last_easy_key, $message);
				$body_string .= $server_message;
			}
		}

		$text_bar = '<table border="0" cellpadding="6"><tr><td><strong>Current Server Balance: <font color="green">' . number_format($display_balance) . '</font></strong></td></tr>
			<tr><td><strong><font color="green">Public Key</font> to receive:</strong></td></tr>
			<tr><td><textarea readonly="readonly" rows="6" cols="75">' . base64_encode($my_public_key) . '</textarea></td></tr></table>';

		$quick_info = 'Send your own Timekoins to someone else.</br></br>
			Your server will attempt to verify if the public key is valid by examing the transaction history before sending.</br></br>
			New public keys with no history could appear invalid for this reason, so always double check.</br></br>
			You can enter an <strong>Easy Key</strong> and Timekoin will fill in the Public Key field for you.</br></br>
			Messages encoded into your transaction are limited to <strong>64</strong> characters and are visible to anyone.</br>No | ? = \' ` * % characters allowed.';

		home_screen('Send / Receive Timekoins', $text_bar, $body_string , $quick_info);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "history")
	{
		if($_GET["receive"] == "listmore" || $_GET["send"] == "listmore")
		{
			if(empty($_GET["send"]) == TRUE)
			{
				$show_last = $_POST["show_more_receive"];
				$hide_send = TRUE;
			}
			else
			{
				$show_last = $_POST["show_more_send"];
				$hide_receive = TRUE;				
			}
		}
		else
		{
			$show_last = 5; // Default number of last items to show
		}
		
		$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");

		if($_GET["font"] == "public_key")
		{
			if(empty($_POST["font_size"]) == FALSE)
			{
				// Save value in database
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["font_size"] . "' WHERE `options`.`field_name` = 'public_key_font_size' LIMIT 1";
				mysql_query($sql);

				$default_public_key_font = $_POST["font_size"];
			}
		}
		else
		{
			$default_public_key_font = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
		}

		if($hide_receive == FALSE)
		{
			if(empty($_POST['filter']) == FALSE)
			{
				$filter_results;
				$filter_GUI;

				switch($_POST['filter'])
				{
					case "transactions":
						$filter_results = "AND `attribute` = 'T'";
						$filter_GUI = "Transactions";						
						break;

					case "generation":
						$filter_results = "AND `attribute` = 'G'";
						$filter_GUI = "Currency Generation";						
						break;
				}
			}
			else
			{
				$filter_GUI = "Transactions & Currency Generation";
			}

			$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> ' . $filter_GUI . ' <font color="green">Sent To</font> This Server</strong></br>
				<FORM ACTION="index.php?menu=history&receive=listmore" METHOD="post"><select name="filter"><option value="transactions">Transactions Only</option>
				<option value="generation">Generation Only</option><option value="all" SELECTED>Both</option></option></select></br>
				</br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
				<th>Sent From</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

			// Find the last 5 transactions sent to this public key
			$sql = "SELECT * FROM `transaction_history` WHERE `public_key_to` = '$my_public_key' $filter_results ORDER BY `transaction_history`.`timestamp` DESC LIMIT $show_last";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$crypt3 = $sql_row["crypt_data3"];

				openssl_public_decrypt(base64_decode($crypt3), $transaction_info, $sql_row["public_key_from"]);
				$transaction_amount = find_string("AMOUNT=", "---TIME", $transaction_info);

				// Any encoded messages?
				$inside_message = find_string("---MSG=", "", $transaction_info, TRUE);

				if($sql_row["public_key_from"] == $my_public_key)
				{
					// Self Generated
					$public_key_from = '<td class="style2">Self Generated';
				}
				else
				{
					// Everyone else
					$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:150px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($sql_row["public_key_from"]) . '</p>';
				}

				// How many cycles back did this take place?
				$cycles_back = intval((time() - $sql_row["timestamp"]) / 300);

				$body_string .= '<tr>
				<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($sql_row["timestamp"]) . '</p></td>' 
				. $public_key_from . '</td>
				<td class="style2"><p style="font-size: 11px;">' . $transaction_amount . '</p></td>
				<td class="style2"><p style="font-size: 11px;">' . $cycles_back . '</p></td>
				<td class="style2"><p style="word-wrap:break-word; width:110px; font-size: 11px;">' . $inside_message . '</p></td></tr>';
			}
			
			$body_string .= '<tr><td colspan="5"><input type="text" size="5" name="show_more_receive" value="' . $show_last .'" /><input type="submit" name="Submit1" value="Show Last" /></FORM></td></tr>';

			$body_string .= '</table></div>';

		} // End hide check for receive

		if($hide_send == FALSE)
		{
			$body_string .= '<strong>Showing Last <font color="blue">' . $show_last . '</font> Transactions <font color="blue">Sent From</font> This Server</strong></br></br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
				<th>Sent To</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

			// Find the last 5 transactions from to this public key
			$sql = "SELECT * FROM `transaction_history` WHERE `public_key_from` = '$my_public_key' AND `public_key_to` != '$my_public_key' ORDER BY `transaction_history`.`timestamp` DESC LIMIT $show_last";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);
				$crypt3 = $sql_row["crypt_data3"];

				openssl_public_decrypt(base64_decode($crypt3), $transaction_info, $sql_row["public_key_from"]);

				$transaction_amount = find_string("AMOUNT=", "---TIME", $transaction_info);

				// Any encoded messages?
				$inside_message = find_string("---MSG=", "", $transaction_info, TRUE);				

				// Everyone else
				$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:150px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($sql_row["public_key_to"]) . '</p>';

				// How many cycles back did this take place?
				$cycles_back = intval((time() - $sql_row["timestamp"]) / 300);

				$body_string .= '<tr>
				<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($sql_row["timestamp"]) . '</p></td>' 
				. $public_key_from . '</td>
				<td class="style2"><p style="font-size: 11px;">' . $transaction_amount . '</p></td>
				<td class="style2"><p style="font-size: 11px;">' . $cycles_back . '</p></td>
				<td class="style2"><p style="word-wrap:break-word; width:110px; font-size: 11px;">' . $inside_message . '</p></td></tr>';
			}

			$body_string .= '<tr><td colspan="5"><FORM ACTION="index.php?menu=history&send=listmore" METHOD="post"><input type="text" size="5" name="show_more_send" value="' . $show_last .'" /><input type="submit" name="Submit2" value="Show Last" /></FORM></td></tr>';

			$body_string .= '</table></div>';

		} // End hide check for send

		$text_bar = '<FORM ACTION="index.php?menu=history&font=public_key" METHOD="post">
			<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td><td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

		$quick_info = 'Verification Level represents how deep in the transaction history the transaction exist.</br></br>
			The larger the number, the more time that all the peers have examined it and agree that it is a valid transaction.';

		home_screen('Transaction History', $text_bar, $body_string , $quick_info);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "queue")
	{
		if($_GET["font"] == "public_key")
		{
			if(empty($_POST["font_size"]) == FALSE)
			{
				// Save value in database
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["font_size"] . "' WHERE `options`.`field_name` = 'public_key_font_size' LIMIT 1";
				mysql_query($sql);

				header("Location: index.php?menu=queue");
				exit;
			}
		}
		else
		{
			$default_public_key_font = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
		}

		$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");

		// Find the last X amount of transactions sent to this public key
		$sql = "SELECT * FROM `transaction_queue` ORDER BY `transaction_queue`.`timestamp` DESC";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		$body_string = '<strong><font color="blue">( ' . number_format($sql_num_results) . ' )</font> Network Transactions Waiting for Processing</strong></br></br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
			<th>Sent From</th><th>Sent To</th><th>Amount</th></tr>';

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$crypt1 = $sql_row["crypt_data1"];
			$crypt2 = $sql_row["crypt_data2"];
			$crypt3 = $sql_row["crypt_data3"];
			$public_key_trans = $sql_row["public_key"];
			
			// Decode the public key this transaction is being sent to
			openssl_public_decrypt(base64_decode($crypt1), $public_key_to_1, $public_key_trans);
			openssl_public_decrypt(base64_decode($crypt2), $public_key_to_2, $public_key_trans);				
			$public_key_trans_to = $public_key_to_1 . $public_key_to_2;
			
			// Decode Amount
			openssl_public_decrypt(base64_decode($crypt3), $transaction_info, $public_key_trans);

			$transaction_amount = find_string("AMOUNT=", "---TIME", $transaction_info);

			if($public_key_trans == $my_public_key)
			{
				if($public_key_trans_to == $my_public_key)
				{
					// Currency Generation
					$public_key_from = '<td class="style2"><font color="blue">Currency Generation</font>';
					$public_key_to = '<td class="style2"><font color="green">Self</font>';
				}
				else
				{
					// Self Generated to someone else
					$public_key_from = '<td class="style2"><font color="blue">Self Generated Transaction</font>';
					$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:175px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans_to) . '</p>';
				}
			}
			else
			{
				// Everyone else
				if($sql_row["attribute"] == "G")
				{
					$public_key_to = '<td class="style2"><font color="green">Currency Generation</font>';
				}
				else
				{
					$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:170px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans_to) . '</p>';
				}
				
				$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:170px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans) . '</p>';
			}

			if($sql_row["attribute"] == "R")
			{
				$transaction_amount = "R";
				$public_key_to = '<td class="style1"><p style="font-size:12px;"><strong><font color="blue">Election Request</font></strong></p>';
			}

			$body_string .= '<tr>
			<td class="style2">' . unix_timestamp_to_human($sql_row["timestamp"]) . '</td>' 
			. $public_key_from . '</td>'
			. $public_key_to . '</td>
			<td class="style2">' . $transaction_amount . '</td></tr>';
		}
		
		$body_string .= '</table></div>';

		$text_bar = '<FORM ACTION="index.php?menu=queue&font=public_key" METHOD="post">
			<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td><td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

		$quick_info = 'This section contains all the network transactions that are queued to be stored in the transaction history.';
		
		$queue_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_queue' LIMIT 1"),0,"field_data");

		home_screen('Realtime Transactions in Network Queue', $text_bar, $body_string , $quick_info, $queue_update);
		exit;
	}
//****************************************************************************	
	if($_GET["menu"] == "tools")
	{
		if($_GET["action"] == "walk_history")
		{
			set_time_limit(100);
			$body_string = '<strong>History Walk from Block #<font color="blue">' . $_POST["walk_history"] . '</font> can take some time, please be patient...</font></strong></br></br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>History Walk</th></tr>';
			$block_end = $_POST["walk_history"] + 500;

			$body_string .= visual_walkhistory($_POST["walk_history"], $block_end);
			$body_string .= '</table></div>';
		}

		if($_GET["action"] == "schedule_check")
		{
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["schedule_check"] . "' WHERE `options`.`field_name` = 'transaction_history_block_check' LIMIT 1";
			
			if(mysql_query($sql) == TRUE)
			{
				$body_string = '<strong>A Block Check has been scheduled for #<font color="blue">' . $_POST["schedule_check"] . '</font></strong>';
				write_log("A History Check was Scheduled for Block #" . $_POST["schedule_check"], "GU");
			}
			else
			{
				$body_string = '<strong><font color="red">There was a Database ERROR to schedule Block #<font color="blue">' . $_POST["schedule_check"] . '</font></strong></font>';
			}
		}

		if($_GET["action"] == "repair")
		{
			set_time_limit(100);
			$body_string = '<strong>Start Repair from Block #<font color="blue">' . $_POST["repair_from"] . '</font></br>
				This can take some time, please be patient...</strong></br></br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Repair History</th></tr>';

			$body_string .= visual_repair($_POST["repair_from"]);
			$body_string .= '</table></div>';

			write_log("A History Block Repair was started from #" . $_POST["repair_from"], "GU");
		}

		if($_GET["action"] == "check_tables")
		{
			set_time_limit(120);
			write_log("A Check of the Entire Database & Tables Was Started.", "GU");

			$body_string = '<strong>Checking All Database Tables</strong></font></br></br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Check Database Results</th></tr><tr><td>';

			$db_check = mysql_query("CHECK TABLE `activity_logs` , `generating_peer_list` , `generating_peer_queue` , `my_keys` , `my_transaction_queue` , `options` , `transaction_foundation` , `transaction_history` , `transaction_queue`");
			$db_check_info = mysql_fetch_array($db_check);
			$db_check_count = 0;
			
			while(empty($db_check_info["$db_check_count"]) == FALSE)
			{
				$body_string .= $db_check_info["$db_check_count"] . " ";
				$db_check_count++;

				if(empty($db_check_info["$db_check_count"]) == TRUE)
				{
					// Move to next array
					$db_check_info = mysql_fetch_array($db_check);
					$db_check_count = 0;
					$body_string .= "</td></tr><tr><td>";
				}
			}

			$body_string .= '<strong>CHECK COMPLETE</strong></td></tr></table></div>';

			write_log("A Check of the Entire Database & Tables Was Finished.", "GU");			
		}

		if($_GET["action"] == "repair_tables")
		{
			set_time_limit(300);
			write_log("A REPAIR of the Entire Database & Tables Was Started.", "GU");

			$body_string = '<strong>Repair All Database Tables</strong></font></br></br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Check Database Results</th></tr><tr><td>';

			$db_check = mysql_query("REPAIR TABLE `activity_logs` , `generating_peer_list` , `generating_peer_queue` , `my_keys` , `my_transaction_queue` , `options` , `transaction_foundation` , `transaction_history` , `transaction_queue`");
			$db_check_info = mysql_fetch_array($db_check);
			$db_check_count = 0;
			
			while(empty($db_check_info["$db_check_count"]) == FALSE)
			{
				$body_string .= $db_check_info["$db_check_count"] . " ";
				$db_check_count++;

				if(empty($db_check_info["$db_check_count"]) == TRUE)
				{
					// Move to next array
					$db_check_info = mysql_fetch_array($db_check);
					$db_check_count = 0;
					$body_string .= "</td></tr><tr><td>";
				}
			}

			$body_string .= '<strong>REPAIR FINISHED</strong></td></tr></table></div>';

			write_log("A REPAIR of the Entire Database & Tables Was Finished.", "GU");			
		}

		if($_GET["logs"] == "listmore")
		{
			$show_last = $_POST["show_more_logs"];
		}
		else
		{
			$show_last = 5; // Default number of last logs to show
		}

		if($_GET["logs"] == "clear")
		{
			mysql_query("TRUNCATE TABLE `activity_logs`");
		}

		if(empty($_GET["action"]) == TRUE)
		{
			// Show log history
			if(empty($_POST["filter"]) == FALSE)
			{
				$filter_by;
				switch($_POST["filter"])
				{
					case "FO":
						$filter_by = ' (Filtered by <strong>Foundation Manager</strong>)';
						break;

					case "G":
						$filter_by = ' (Filtered by <strong>Generation Events</strong>)';
						break;

					case "GP":
						$filter_by = ' (Filtered by <strong>Generation Peer Manager</strong>)';
						break;

					case "R":
						$filter_by = ' (Filtered by <strong>Generation Request</strong>)';
						break;

					case "MA":
						$filter_by = ' (Filtered by <strong>Main Program</strong>)';
						break;

					case "PL":
						$filter_by = ' (Filtered by <strong>Peer Processor</strong>)';
						break;

					case "QC":
						$filter_by = ' (Filtered by <strong>Transaction Queue Clerk</strong>)';
						break;

					case "TC":
						$filter_by = ' (Filtered by <strong>Transaction Queue Clerk</strong>)';
						break;

					case "T":
						$filter_by = ' (Filtered by <strong>Transactions</strong>)';
						break;

					case "TR":
						$filter_by = ' (Filtered by <strong>Treasurer Processor</strong>)';
						break;

					case "WA":
						$filter_by = ' (Filtered by <strong>Watchdog</strong>)';
						break;

				}
			}
			
			$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> Log Events</strong>' . $filter_by . '<table border="0" cellspacing="5"><tr><td>
				Filter By:</td><td><FORM ACTION="index.php?menu=tools&logs=listmore" METHOD="post"><select name="filter"><option value="all" SELECTED>Show All</option>
				<option value="FO">Foundation Manager</option><option value="G">Generation Events</option><option value="GP">Generation Peer Manager</option>
				<option value="R">Generation Request</option><option value="MA">Main Program</option><option value="PL">Peer Processor</option><option value="QC">Transaction Queue Clerk</option>
				<option value="TC">Transaction Clerk</option><option value="T">Transactions</option><option value="TR">Treasurer Processor</option><option value="WA">Watchdog</option></select></td></tr></table>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th><th>Log</th><th>Attribute</th></tr>';

			// Find the last X amount of log events
			if($_POST["filter"] == "all" || empty($_POST["filter"]) == TRUE)
			{
				$sql = "SELECT * FROM `activity_logs` ORDER BY `activity_logs`.`timestamp` DESC LIMIT $show_last";
			}
			else
			{
				$sql = "SELECT * FROM `activity_logs` WHERE `attribute` = '" . $_POST["filter"] . "' ORDER BY `activity_logs`.`timestamp` DESC LIMIT $show_last";
			}
			
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				$body_string .= '<tr>
				<td class="style2"><p style="width:160px;">' . unix_timestamp_to_human($sql_row["timestamp"]) . '</p></td>
				<td class="style2"><p style="word-wrap:break-word; width:360px;">' . $sql_row["log"] . '</p></td>
				<td class="style2">' . $sql_row["attribute"] . '</td></tr>';
			}

			$body_string .= '<tr><td><input type="text" size="5" name="show_more_logs" value="' . $show_last .'" /><input type="submit" name="show_last" value="Show Last" /></FORM></td>
				<td colspan="2"><FORM ACTION="index.php?menu=tools&logs=clear" METHOD="post"><input type="submit" name="clear_logs" value="Clear All Logs" /></FORM></td></tr>';
			$body_string .= '</table></div>';
		}
		
		$text_bar = tools_bar();

		$quick_info = '<strong>History Walk</strong> will manually test all transactions starting at the specified block and give a status for each block.</br></br>
			<strong>Schedule Check</strong> will schedule Timekoin to check and repair the specified block.</br></br>
			<strong>Repair</strong> will force Timekoin to recalculate all verification hashes from the specified block to now.</br></br>
			<strong>Check Database</strong> will check the data integrity of all tables in the database.</br></br>
			<strong>Repair Database</strong> will attempt to repair all tables in the database.</br></br>			
			<i>Note:</i> The repair utilities can take a long time to process and complete.';
		
		home_screen('Tools & Utilities', $text_bar, $body_string , $quick_info);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "backup")
	{
		if($_GET["dorestore"] == "private" && empty($_POST["restore_private_key"]) == FALSE)
		{
			$sql = "UPDATE `my_keys` SET `field_data` = '" . base64_decode($_POST["restore_private_key"]) . "' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";

			if(mysql_query($sql) == TRUE)
			{
				$server_message = '</br><font color="blue"><strong>Private Key Restore Complete!</strong></font></br></br>';
			}
			else
			{
				$server_message = '</br><font color="red"><strong>Private Key Restore FAILED!</strong></font></br></br>';
			}
		}

		if($_GET["dorestore"] == "public" && empty($_POST["restore_public_key"]) == FALSE)
		{
			$sql = "UPDATE `my_keys` SET `field_data` = '" . base64_decode($_POST["restore_public_key"]) . "' WHERE `my_keys`.`field_name` = 'server_public_key' LIMIT 1";

			if(mysql_query($sql) == TRUE)
			{
				$server_message = '</br><font color="blue"><strong>Public Key Restore Complete!</strong></font></br></br>';
			}
			else
			{
				$server_message = '</br><font color="red"><strong>Public Key Restore FAILED!</strong></font></br></br>';
			}
		}

		$my_private_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,"field_data");
		$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");

		if($_GET["restore"] == "private" && empty($_POST["restore_private_key"]) == FALSE)
		{
			$body_string = backup_body($_POST["restore_private_key"], NULL, TRUE, NULL);
		}
		else if($_GET["restore"] == "public" && empty($_POST["restore_public_key"]) == FALSE)
		{
			$body_string = backup_body(NULL, $_POST["restore_public_key"], NULL, TRUE);
		}
		else
		{
			$body_string = backup_body();
		}

		$body_string .= $server_message;

		$text_bar = '<table border="0" cellpadding="6"><tr><td><strong><font color="blue">Private Key</font> to send transactions:</strong></td></tr>
			<tr><td><textarea readonly="readonly" rows="8" cols="75">' . base64_encode($my_private_key) . '</textarea></td></tr></table>
			<table border="0" cellpadding="6"><tr><td><strong><font color="green">Public Key</font> to receive:</strong></td></tr>
			<tr><td><textarea readonly="readonly" rows="6" cols="75">' . base64_encode($my_public_key) . '</textarea></td></tr></table>';

		$quick_info = '<strong>Do Not</strong> share your Private Key with anyone for any reason.</br></br>
			The Private Key encrypts all transactions from your server.</br></br>
			You should make a backup of both keys in case you want to transfer your balance to a new server or restore from a server failure.</br></br>
			Save both keys in a password protected text file or external device that you can secure (CD, Flash Drive, Printed Paper, etc.)';

		home_screen('Backup & Restore Keys', $text_bar, $body_string , $quick_info);
		exit;		
	}
//****************************************************************************
	if($_GET["menu"] == "logoff")
	{
		unset($_SESSION["valid_login"]);
		unset($_SESSION["login_username"]);
		header("Location: index.php");
		exit;		
	}
//****************************************************************************
} // End Valid Login Check
//****************************************************************************
//****************************************************************************

?>
