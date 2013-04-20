<?PHP
include 'templates.php';
include 'function.php';
set_time_limit(99);
session_name("tkclient");
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

		// Log invalid attempts
		write_log("Invalid Login from IP: " . $_SERVER['REMOTE_ADDR'] . " trying Username:[" . filter_sql($http_username) . "] with Password:[" . filter_sql($http_password) . "]", "GU");

	}

	sleep(1); // One second delay to help prevent brute force attack
	login_screen("Login Failed");
	exit;
}

if($_SESSION["valid_login"] == TRUE)
{
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
//****************************************************************************
	if($_GET["menu"] == "home" || empty($_GET["menu"]) == TRUE)
	{
		$body_string = '<table border="0" cellspacing="10" cellpadding="2" bgcolor="#FFFFFF"><tr><td align="center"><strong>Status</strong></td>
			<td align="center"><strong>Program</strong></td><td align="left"><strong>Message</strong></td></tr>';

		$body_string .= '<tr><td align="center"><img src="img/hr.gif" alt="" /></td><td><font color="red"><strong>Reserve Spot</strong></font></td>
				<td><strong>Status Goes Here</strong></td></tr>';

		$body_string = $body_string . '</table>';

		$display_balance = db_cache_balance(my_public_key());
		
		$text_bar = '<table border="0"><tr><td style="width:260px"><strong>Current Billfold Balance: <font color="green">' . number_format($display_balance) . '</font></strong></td></tr>
			<tr></table>';

		$quick_info = 'Current Status...';

		$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");

		home_screen("Realtime Status", $text_bar, $body_string, $quick_info , $home_update);
		exit;
	}
//****************************************************************************
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
				`IP_Address` = '" . $_POST["edit_ip"] . "', `domain` = '" . $_POST["edit_domain"] . "', `subfolder` = '" . $_POST["edit_subfolder"] . "', `port_number` = '" . $_POST["edit_port"] . "' , `code` = '" . $_POST["edit_code"] . "'
				WHERE `active_peer_list`.`IP_Address` = '" . $_POST["update_ip"] . "' AND `active_peer_list`.`domain` = '" . $_POST["update_domain"] . "' LIMIT 1";
			mysql_query($sql);
		}

		if($_GET["save"] == "newpeer" && empty($_POST["edit_port"]) == FALSE)
		{
			// Manually insert new peer
			$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat` ,`join_peer_list` ,`failed_sent_heartbeat` , `code`)
				VALUES ('" . $_POST["edit_ip"] . "', '" . $_POST["edit_domain"] . "', '" . $_POST["edit_subfolder"] . "', '" . $_POST["edit_port"] . "', UNIX_TIMESTAMP() , UNIX_TIMESTAMP() , '0', '" . $_POST["edit_code"] . "')";
			mysql_query($sql);
		}

		if($_GET["save"] == "firstcontact")
		{
			// Wipe Current First Contact Peers List and Save the New List
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
							"---port=" . $_POST["first_contact_port$field_numbers"] . "---code=" . 
							$_POST["first_contact_code$field_numbers"] . "---end')";

						mysql_query($sql);
					}
					
					$field_numbers--;
				}
			}
		}

		if($_GET["edit"] == "peer")
		{
			$body_string = '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0"><tr><th>IP Address</th>
				<th>Domain</th><th>Subfolder</th><th>Port Number</th><th>Code</th><th></th><th></th></tr>';

			if($_GET["type"] == "new")
			{
				// Manually add a peer
				$body_string .= '<FORM ACTION="index.php?menu=peerlist&save=newpeer" METHOD="post"><tr>
				 <td class="style2"><input type="text" name="edit_ip" size="13" /></td>
				 <td class="style2"><input type="text" name="edit_domain" size="14" /></td>
				 <td class="style2"><input type="text" name="edit_subfolder" size="10" /></td>
				 <td class="style2"><input type="text" name="edit_port" size="5" /></td>
				 <td class="style2"><input type="text" name="edit_code" size="5" value="guest"/></td>				 
				 <td><input type="image" src="img/save-icon.gif" title="Save New Peer" name="submit1" border="0"></FORM></td><td>
				 <FORM ACTION="index.php?menu=peerlist" METHOD="post">
				 <input type="image" src="img/hr.gif" title="Cancel" name="submit2" border="0"></FORM>
				 </td></tr>';

				$body_string .= '</table></div>';				
			}
			else if($_GET["type"] == "firstcontact")
			{
				$sql = "SELECT *  FROM `options` WHERE `field_name` = 'first_contact_server'";
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result) + 2;
				$counter = 1;
				$body_string = '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0"><tr><th>IP Address</th>
				<th>Domain</th><th>Subfolder</th><th>Port Number</th><th>Code</th><th></th></tr>';				
				$body_string .= '<FORM ACTION="index.php?menu=peerlist&save=firstcontact" METHOD="post">';

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);

					$peer_ip = find_string("---ip=", "---domain", $sql_row["field_data"]);
					$peer_domain = find_string("---domain=", "---subfolder", $sql_row["field_data"]);
					$peer_subfolder = find_string("---subfolder=", "---port", $sql_row["field_data"]);
					$peer_port_number = find_string("---port=", "---code", $sql_row["field_data"]);
					$peer_port_code = find_string("---code=", "---end", $sql_row["field_data"]);
				
					$body_string .= '<tr><td class="style2"><input type="text" name="first_contact_ip' . $counter . '" size="13" value="' . $peer_ip . '" /></br></br></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_domain' . $counter . '" size="20" value="' . $peer_domain . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_subfolder' . $counter . '" size="10" value="' . $peer_subfolder . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_port' . $counter . '" size="5" value="' . $peer_port_number . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_code' . $counter . '" size="10" value="' . $peer_port_code . '" /></td>
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

				if($sql_row["join_peer_list"] == 0)
				{
					$perm_peer1 = "SELECTED";
				}
				else
				{
					$perm_peer2 = "SELECTED";
				}

				$body_string .= '<FORM ACTION="index.php?menu=peerlist&save=peer" METHOD="post"><tr>
				<td class="style2"><input type="text" name="edit_ip" size="13" value="' . $sql_row["IP_Address"] . '" /></br></br>
				<select name="perm_peer"><option value="expires" ' . $perm_peer2 . '>Purge When Inactive</option><option value="perm" ' . $perm_peer1 . '>Permanent Peer</select></td>
				<td class="style2" valign="top"><input type="text" name="edit_domain" size="14" value="' . $sql_row["domain"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_subfolder" size="10" value="' . $sql_row["subfolder"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_port" size="5" value="' . $sql_row["port_number"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_code" size="5" value="' . $sql_row["code"] . '" /></td>
				<td valign="top"><input type="hidden" name="update_ip" value="' . $sql_row["IP_Address"] . '">
				<input type="hidden" name="update_domain" value="' . $sql_row["domain"] . '">
				<input type="image" src="img/save-icon.gif" title="Save Settings" name="submit1" border="0"></FORM></td>
				<td valign="top"><FORM ACTION="index.php?menu=peerlist" METHOD="post">
				<input type="image" src="img/hr.gif" title="Cancel Changes" name="submit2" border="0"></FORM>
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
				<th><p style="font-size:11px;">IP Address</p></th><th><p style="font-size:11px;">Domain</p></th>
				<th><p style="font-size:11px;">Subfolder</p></th><th><p style="font-size:11px;">Port Number</p></th>
				<th><p style="font-size:11px;">Last Heartbeat</p></th><th><p style="font-size:11px;">Joined</p></th>
				<th></th><th></th></tr>';			
			
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

					$failed_column_name = 'failed_sent_heartbeat';					
				}
				else
				{
					$failed_column_name = 'poll_failures';
				}

				$body_string .= '<tr>
				 <td class="style2"><p style="word-wrap:break-word; width:90px; font-size:11px;">' . $permanent1 . $sql_row["IP_Address"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; width:155px; font-size:11px;">' . $permanent1 . $sql_row["domain"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; width:60px; font-size:11px;">' . $permanent1 . $sql_row["subfolder"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $sql_row["port_number"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $last_heartbeat . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $joined . $permanent2 . '</p></td>';

				if($_GET["show"] == "reserve")
				{
					$body_string .= '<td></td><td></td></tr>';
				}
				else
				{
					$body_string .= '<td><FORM ACTION="index.php?menu=peerlist&remove=peer" METHOD="post"><input type="image" src="img/hr.gif" title="Delete Peer" name="remove' . $i . '" border="0">
					 <input type="hidden" name="ip" value="' . $sql_row["IP_Address"] . '">
					 <input type="hidden" name="domain" value="' . $sql_row["domain"] . '">
					 </FORM></td><td>
					 <FORM ACTION="index.php?menu=peerlist&edit=peer" METHOD="post"><input type="image" src="img/edit-icon.gif" title="Edit Peer" name="edit' . $i . '" border="0">
					 <input type="hidden" name="ip" value="' . $sql_row["IP_Address"] . '">
					 <input type="hidden" name="domain" value="' . $sql_row["domain"] . '">
					 </FORM>
					 </td></tr>';
				}
			}

			$body_string .= '<tr><td colspan="2"><FORM ACTION="index.php?menu=peerlist&show=reserve" METHOD="post"><input type="submit" value="Show Reserve Peers"/></FORM></td>
				<td colspan="3"><FORM ACTION="index.php?menu=peerlist&edit=peer&type=new" METHOD="post"><input type="submit" value="Add New Peer"/></FORM></td>
				<td colspan="4"><FORM ACTION="index.php?menu=peerlist&edit=peer&type=firstcontact" METHOD="post"><input type="submit" value="First Contact Peers"/></FORM></td></tr></table></div>';

			$sql = "SELECT * FROM `new_peers_list`";
			$new_peers = mysql_num_rows(mysql_query($sql));		

			if($_GET["show"] == "reserve")
			{
				$sql = "SELECT * FROM `active_peer_list`";
				$sql_num_results = mysql_num_rows(mysql_query($sql));
			}

			$peer_transaction_start_blocks = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_start_blocks' LIMIT 1"),0,"field_data");
			$peer_transaction_performance = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_performance' LIMIT 1"),0,"field_data");

			$peer_number_bar = '<table border="0" cellspacing="0" cellpadding="0"><tr><td style="width:125px"><strong>Active Peers: <font color="green">' . $sql_num_results . '</font></strong></td>
				<td style="width:175px"><strong>Peers in Reserve: <font color="blue">' . $new_peers . '</font></strong></td></tr></table>';

			$quick_info = 'Shows all Active Peers.</br></br>You can manually delete or edit peers in this section.
				</br></br>Peers in <font color="blue">Blue</font> will not expire after 5 minutes of inactivity.';

			$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");

			if($_GET["show"] == "reserve")
			{
				home_screen('Reserve Peer List', $peer_number_bar, $body_string , $quick_info);
			}
			else
			{
				home_screen('Network Peer List', $peer_number_bar, $body_string , $quick_info, $home_update);
			}
		}
		exit;
	}	
//****************************************************************************
//****************************************************************************
	if($_GET["menu"] == "options")
	{
		if($_GET["password"] == "change")
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

		if($_GET["refresh"] == "change")
		{
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["home_update"] . "' WHERE `options`.`field_name` = 'refresh_realtime_home' LIMIT 1";
			if(mysql_query($sql) == TRUE)
			{
				$refresh_change = TRUE;
			}

			$body_text = options_screen2();

			if($refresh_change == TRUE)
			{
				$body_text .= '<font color="blue"><strong>Refresh Settings Saved!</strong></font></br>';
			}
			else
			{
				$body_text .= '<strong>Update ERROR...</strong></br>';
			}
		} // End refresh update save
		else if(empty($_GET["password"]) == TRUE && empty($_GET["refresh"]) == TRUE)
		{
			$body_text = options_screen2();
		}

		if($_GET["newkeys"] == "confirm")
		{
			if(generate_new_keys() == TRUE)
			{
				$body_text .= '<font color="green"><strong>New Private & Public Key Pair Generated!</strong></font></br>';
			}
			else
			{
				$body_text .= '<font color="red"><strong>New Key Creation Failed!</strong></font></br>';
			}
		}

		$quick_info = 'You may change the username and password individually or at the same time.
			</br></br>Remember that usernames and passwords are Case Sensitive.
			</br></br><strong>Generate New Keys</strong> will create a new random key pair and save it in the database.
			</br></br><strong>Check for Updates</strong> will check for any program updates that can be downloaded directly into Timekoin.';

		if($_GET["upgrade"] == "check" || $_GET["upgrade"] == "doupgrade")
		{
			home_screen("Options & Personal Settings", options_screen3(), "" , $quick_info);
		}
		else
		{		
			home_screen("Options & Personal Settings", options_screen(), $body_text , $quick_info);
		}
		exit;
	}	
//****************************************************************************
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
					$valid_key_test = mysql_result(mysql_query("SELECT public_key_from, public_key_to FROM `transaction_history` WHERE `public_key_from` = '$public_key_to' OR `public_key_to` = '$public_key_to' LIMIT 1"),0,0);

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

		$text_bar = '<table border="0" cellpadding="6"><tr><td><strong>Current Billfold Balance: <font color="green">' . number_format($display_balance) . '</font></strong></td></tr>
			<tr><td><strong><font color="green">Public Key</font> to receive:</strong></td></tr>
			<tr><td><textarea readonly="readonly" rows="6" cols="75">' . base64_encode($my_public_key) . '</textarea></td></tr></table>';

		$quick_info = 'Send your own Timekoins to someone else.</br></br>
			Your server will attempt to verify if the public key is valid by examing the transaction history before sending.</br></br>
			New public keys with no history could appear invalid for this reason, so always double check.</br></br>
			You can enter an <strong>Easy Key</strong> and Timekoin will fill in the Public Key field for you.</br></br>
			Messages encoded into your transaction are limited to <strong>64</strong> characters and are visible to anyone.</br>No <strong>| ? = \' ` * %</strong> characters allowed.';

		home_screen('Send / Receive Timekoins', $text_bar, $body_string , $quick_info);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "history")
	{
		$my_public_key = my_public_key();
		set_time_limit(120);

		if($_GET["trans_browse"] == "open")
		{
			set_time_limit(300);
			
			//Open Transaction Browser
			if($_POST["show_more"] > 0)
			{
				$show_last = $_POST["show_more"];
			}
			else
			{
				$show_last = 10; // Default number of last items to show
			}			

			// Check if these graphic files exist
			if(file_exists("img/timekoin_green.png") == TRUE)
			{
				// Use graphic
				$timekoin_green = TRUE;			
			}

			if(file_exists("img/timekoin_blue.png") == TRUE)
			{
				// Use graphic
				$timekoin_blue = TRUE;			
			}			

			$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> Transaction Cycles</strong>';

			// Start the Transaction Browser section
			$body_string .= '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Transaction Cycle</th>
				<th>Transactions</th></tr>';

			// How many transactions back from the present time to display?
			$show_last_counter = $show_last;
			$counter = -1; // Transaction back from present cycle

			while($show_last_counter > 0)
			{
				$start_transaction_cycle = transaction_cycle($counter);
				$end_transaction_cycle = transaction_cycle($counter + 1);
				$jump_to_transaction = transaction_cycle($counter + 5);

				$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= '$start_transaction_cycle' AND `timestamp` < '$end_transaction_cycle'";
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);

				if($_POST["highlight_cycle"] - 1500 == $start_transaction_cycle)
				{
					$body_string .= '<tr><td class="style2"><p style="font-size: 12px;"><h9 id="' . $start_transaction_cycle . '"></h9><font color="blue">' . $start_transaction_cycle . '</br>' . unix_timestamp_to_human($start_transaction_cycle) . '</font></p></td>
						<td class="style2"><table border="0" cellspacing="0" cellpadding="0"><tr>';
				}
				else
				{
					$body_string .= '<tr><td class="style2"><p style="font-size: 12px;"><h9 id="' . $start_transaction_cycle . '"></h9>' . $start_transaction_cycle . '</br>' . unix_timestamp_to_human($start_transaction_cycle) . '</p></td>
						<td class="style2"><table border="0" cellspacing="0" cellpadding="0"><tr>';
				}

				$koin_kounter = 0;
				$row_count_limit = 12;

				if($sql_num_results > 1)
				{
					// Build row with icons
					for ($i = 0; $i < $sql_num_results; $i++)
					{
						$sql_row = mysql_fetch_array($sql_result);

						if($koin_kounter >= $row_count_limit)
						{
							$body_string .= '</tr><tr>';
							$koin_kounter = 0;
						}

						// Transaction Amount
						$transaction_info = tk_decrypt($sql_row["public_key_from"], base64_decode($sql_row["crypt_data3"]));

						$transaction_amount = find_string("AMOUNT=", "---TIME", $transaction_info);

						if($sql_row["attribute"] == 'G')
						{
							if($timekoin_green == TRUE)
							{
								$body_string .= '<td><FORM ACTION="index.php?menu=history&examine=transaction" METHOD="post">
									<input type="hidden" name="show_more" value="' . $show_last . '">
									<input type="hidden" name="trans_cycle" value="' . $jump_to_transaction . '">
									<input type="hidden" name="timestamp" value="' . $sql_row["timestamp"] . '">
									<input type="hidden" name="hash" value="' . $sql_row["hash"] . '">
									<input type="image" src="img/timekoin_green.png" title="Amount: ' . $transaction_amount . '" name="submit2" border="0"></FORM></td>';
							}
							else
							{
								$body_string .= '<td style="background-color:#c8ffc8"><FORM ACTION="index.php?menu=history&examine=transaction" METHOD="post">
									<input type="hidden" name="show_more" value="' . $show_last . '">
									<input type="hidden" name="trans_cycle" value="' . $jump_to_transaction . '">
									<input type="hidden" name="timestamp" value="' . $sql_row["timestamp"] . '">
									<input type="hidden" name="hash" value="' . $sql_row["hash"] . '">
									<input type="image" src="img/timekoin_logo.png" width="25" height="25" title="Amount: ' . $transaction_amount . '" name="submit2" border="0"></FORM></td>';
							}

							$koin_kounter++;
						}

						if($sql_row["attribute"] == 'T')
						{
							if($timekoin_blue == TRUE)
							{
								$body_string .= '<td><FORM ACTION="index.php?menu=history&examine=transaction" METHOD="post">
									<input type="hidden" name="show_more" value="' . $show_last . '">
									<input type="hidden" name="trans_cycle" value="' . $jump_to_transaction . '">
									<input type="hidden" name="timestamp" value="' . $sql_row["timestamp"] . '">
									<input type="hidden" name="hash" value="' . $sql_row["hash"] . '">
									<input type="image" src="img/timekoin_blue.png" title="Amount: ' . $transaction_amount . '" name="submit2" border="0"></FORM></td>';
							}
							else
							{
								$body_string .= '<td style="background-color:#b2b2ff"><FORM ACTION="index.php?menu=history&examine=transaction" METHOD="post">
									<input type="hidden" name="show_more" value="' . $show_last . '">
									<input type="hidden" name="trans_cycle" value="' . $jump_to_transaction . '">
									<input type="hidden" name="timestamp" value="' . $sql_row["timestamp"] . '">
									<input type="hidden" name="hash" value="' . $sql_row["hash"] . '">
									<input type="image" src="img/timekoin_logo.png" width="25" height="25" title="Amount: ' . $transaction_amount . '" name="submit2" border="0"></FORM></td>';
							}

							$koin_kounter++;
						}
					}
				}
				else
				{
					$body_string .= 'No Transactions';
				}

				$body_string .= '</tr></table></td></tr>';
				$counter--;
				$show_last_counter--;
			}

			$body_string .= '<tr><FORM ACTION="index.php?menu=history&trans_browse=open" METHOD="post">
				<td colspan="2" align="left"><input type="text" size="5" name="show_more" value="' . $show_last .'" /><input type="submit" name="Submit1" value="Show Last" /></FORM></td></tr>';

			$body_string .= '</table></div>';

			if($timekoin_green == TRUE)
			{
				$color_key1 = '<td><img src="img/timekoin_green.png" /></td>';
			}
			else
			{
				$color_key1 = '<td style="background-color:#c8ffc8"><img src="img/timekoin_logo.png" width="25" height="25" /></td>';
			}

			if($timekoin_blue == TRUE)
			{
				$color_key2 = '<td><img src="img/timekoin_blue.png" /></td>';
			}
			else
			{
				$color_key2 = '<td style="background-color:#b2b2ff"><img src="img/timekoin_logo.png" width="25" height="25" /></td>';
			}

			$text_bar = '<table border="0" cellspacing="0" cellpadding="0"><tr><td style="width:125px;"><strong>Color Chart:</strong></td>
				<td>New Currency</td>' . $color_key1 . '
				<td style="width:115px;" align="right">Transaction</td>' . $color_key2 . '
				</tr></table>';
			$quick_info = '<strong>Transaction History Browser</strong> allows the user to get a quick visual glance of past transactions.</br></br>
				The color code graphic shows various types of transactions.</br></br>
				Hovering the cursor over the icon will show the transaction amount.</br></br>
				Clicking the icon will display the full details of the selected transaction.';

			home_screen('Transaction History (Browser)', $text_bar, $body_string , $quick_info);
			exit;
		}
		else if($_GET["examine"] == "transaction")
		{
			// Examine Transaction Details
			$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` = '" . $_POST["timestamp"] . "' AND `hash` = '" . $_POST["hash"] . "'";
			$sql_result = mysql_query($sql);			
			$sql_row = mysql_fetch_array($sql_result);

			$crypt1_data = tk_decrypt($sql_row["public_key_from"], base64_decode($sql_row["crypt_data1"]));
			$crypt2_data = tk_decrypt($sql_row["public_key_from"], base64_decode($sql_row["crypt_data2"]));
			$transaction_info = tk_decrypt($sql_row["public_key_from"], base64_decode($sql_row["crypt_data3"]));

			$transaction_amount = find_string("AMOUNT=", "---TIME", $transaction_info);
			$timestamp_created = find_string("TIME=", "---HASH", $transaction_info);
			$inside_message = find_string("---MSG=", "", $transaction_info, TRUE);

			$inside_transaction_hash = find_string("HASH=", "", $transaction_info, TRUE);

			// Check if a message is encoded in this data as well
			if(strlen($inside_transaction_hash) != 64)
			{
				// A message is also encoded
				$inside_transaction_hash = find_string("HASH=", "---MSG", $transaction_info);
			}			
			
			if($sql_row["attribute"] == "T")
			{
				$body_string .= "<strong>Type:</strong> Transaction";
			}
			else if($sql_row["attribute"] == "G")
			{
				$body_string .= "<strong>Type:</strong> Currency Creation";
			}			

			$body_string .= "</br></br><strong>Amount:</strong> $transaction_amount";
			$body_string .= "</br></br><strong>Created:</strong> ($timestamp_created) " . unix_timestamp_to_human($timestamp_created);
			$body_string .= "</br></br><strong>Message:</strong> $inside_message";
			$body_string .= "</br></br><strong>Inside Hash:</strong> $inside_transaction_hash";

			// Check Inside Has for tampering comparison
			$crypt_1_2_hash = hash('sha256', $sql_row["crypt_data1"] . $sql_row["crypt_data2"]);

			if($inside_transaction_hash == $crypt_1_2_hash)
			{
				$body_string .= '</br><font color="green">(Match for Crypt Fields 1 & 2)</font>';
			}
			else
			{
				$body_string .= '</br><font color="red">(NO MATCH for Crypt Fields 1 & 2)</font>';
			}

			$body_string .= '<hr></hr><strong>Public Key From:</strong></br><p style="word-wrap:break-word;">' . base64_encode($sql_row["public_key_from"]) . '</p>';
			
			if($crypt1_data . $crypt2_data == $sql_row["public_key_to"])
			{
				$match_pub_key = '<font color="green">(Public Key Match for Crypt Fields 1 & 2)</font>';
			}
			else
			{
				$match_pub_key = '<font color="red">(Public Key NO MATCH for Crypt Fields 1 & 2)</font>';
			}			
			
			$body_string .= '<hr></hr><strong>Public Key To:</strong> ' . $match_pub_key . '</br><p style="word-wrap:break-word;">' . base64_encode($sql_row["public_key_to"]) . '</p>';

			$triple_hash_check = hash('sha256', $sql_row["crypt_data1"] . $sql_row["crypt_data2"] . $sql_row["crypt_data3"]);

			if($triple_hash_check == $_POST["hash"])
			{
				$triple1 = '<font color="green">';
				$triple2 = '</br>(Match for Crypt Fields 1,2,3)';
			}
			else
			{
				$triple1 = '<font color="red">';
				$triple2 = '</br>(NO MATCH for Crypt Fields 1,2,3)';				
			}

			// Return Button
			$body_string .= '<hr></hr><FORM ACTION="index.php?menu=history&trans_browse=open#' . $_POST["trans_cycle"] . '" METHOD="post">
				<input type="hidden" name="show_more" value="' . $_POST["show_more"] . '">
				<input type="hidden" name="highlight_cycle" value="' . $_POST["trans_cycle"] . '">				
				<input type="submit" name="Submit5" value="Return to Transaction Browser" /></FORM><hr></hr>';

			$text_bar = '<table border="0" cellspacing="0" cellpadding="0"><tr><td style="width:190px;"><strong>Timestamp:</strong> (' . $_POST["timestamp"] . 
				')</td><td>' . unix_timestamp_to_human($_POST["timestamp"]) . '</td></tr>
				<tr><td colspan="2"><strong>Hash:</strong>' . $triple1 . $_POST["hash"] . $triple2 . '</font></td></tr></table>';
			$quick_info = '<strong>Timestamp</strong> represents when the transaction request to be recorded in the transaction history.</br></br>
				<strong>Hash</strong> is included with the transaction to allow Timekoin to check if any of the encrypted fields have been tampered with.</br></br>
				<strong>Created</strong> is when Timekoin generated the transaction.</br></br>
				<strong>Message</strong> is any included text set by the user at the time of the transaction creation.</br></br>
				<strong>Inside Hash</strong> is included to make sure the destination public key for the transfer has not been tampered with.</br></br>
				<strong>Return to Transaction Browser</strong> will highlight in <font color="blue">blue</font>, the last transaction cycle this transaction came from.';

			home_screen('Transaction History (Examine Transaction)', $text_bar, $body_string , $quick_info);
			exit;
		}
		else
		{
			// Standard History View
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
					$sent_to_selected_trans;
					$sent_to_selected_gen;
					$sent_to_selected_both;

					switch($_POST['filter'])
					{
						case "transactions":
							$filter_results = "AND `attribute` = 'T'";
							$filter_GUI = "Transactions";
							$sent_to_selected_trans = "SELECTED";
							break;

						case "generation":
							$filter_results = "AND `attribute` = 'G'";
							$filter_GUI = "Currency Generation";
							$sent_to_selected_gen = "SELECTED";
							break;

						case "all":
							$filter_GUI = "Transactions & Currency Generation";
							$sent_to_selected_both = "SELECTED";
							break;							
					}
				}
				else
				{
					$filter_results = "AND `attribute` = 'T'";
					$filter_GUI = "Transactions";
					$sent_to_selected_trans = "SELECTED";
				}

				$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> ' . $filter_GUI . ' <font color="green">Sent To</font> This Server</strong></br>
					<FORM ACTION="index.php?menu=history&receive=listmore" METHOD="post"><select name="filter"><option value="transactions" ' . $sent_to_selected_trans . '>Transactions Only</option>
					<option value="generation" ' . $sent_to_selected_gen . '>Generation Only</option><option value="all" ' . $sent_to_selected_both . '>Both</option></option></select></br>
					</br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
					<th>Sent From</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

				// Find the last X transactions sent to this public key
				$sql = "SELECT timestamp, public_key_from, public_key_to, crypt_data3 FROM `transaction_history` WHERE `public_key_to` = '$my_public_key' $filter_results ORDER BY `transaction_history`.`timestamp` DESC LIMIT $show_last";
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$crypt3 = $sql_row["crypt_data3"];

					$transaction_info = tk_decrypt($sql_row["public_key_from"], base64_decode($crypt3));

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
					<td class="style2"><p style="word-wrap:break-word; width:140px; font-size: 11px;">' . $inside_message . '</p></td></tr>';
				}
				
				$body_string .= '<tr><td colspan="5"><input type="text" size="5" name="show_more_receive" value="' . $show_last .'" /><input type="submit" name="Submit1" value="Show Last" /></FORM></td></tr>';

				$body_string .= '</table></div>';

			} // End hide check for receive

			if($hide_send == FALSE)
			{
				$body_string .= '<strong>Showing Last <font color="blue">' . $show_last . '</font> Transactions <font color="blue">Sent From</font> This Server</strong></br></br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
					<th>Sent To</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

				// Find the last X transactions from to this public key
				$sql = "SELECT timestamp, public_key_from, public_key_to, crypt_data3 FROM `transaction_history` WHERE `public_key_from` = '$my_public_key' AND `attribute` = 'T' ORDER BY `transaction_history`.`timestamp` DESC LIMIT $show_last";
				
				$sql_result = mysql_query($sql);
				$sql_num_results = mysql_num_rows($sql_result);

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$crypt3 = $sql_row["crypt_data3"];

					$transaction_info = tk_decrypt($sql_row["public_key_from"], base64_decode($crypt3));

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
					<td class="style2"><p style="word-wrap:break-word; width:140px; font-size: 11px;">' . $inside_message . '</p></td></tr>';
				}

				$body_string .= '<tr><td colspan="5"><FORM ACTION="index.php?menu=history&send=listmore" METHOD="post"><input type="text" size="5" name="show_more_send" value="' . $show_last .'" /><input type="submit" name="Submit2" value="Show Last" /></FORM></td></tr>';

				$body_string .= '</table></div>';

			} // End hide check for send

			$text_bar = '<FORM ACTION="index.php?menu=history&font=public_key" METHOD="post">
				<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td>
				<td style="width:250px"><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></FORM></td>
				<td><FORM ACTION="index.php?menu=history&trans_browse=open" METHOD="post"><input type="submit" name="Submit4" value="Transaction Browser" /></FORM></td></tr></table>';

			$quick_info = 'Verification Level represents how deep in the transaction history the transaction exist.</br></br>
				The larger the number, the more time that all the peers have examined it and agree that it is a valid transaction.</br></br>
				<strong>Transaction Browser</strong> will allow the user to examine the details of the transaction history.';

			home_screen('Transaction History', $text_bar, $body_string , $quick_info);
		} // Check for which type of History Mode to open
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

		$my_public_key = my_public_key();

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
			$public_key_to_1 = tk_decrypt($public_key_trans, base64_decode($crypt1));
			$public_key_to_2 = tk_decrypt($public_key_trans, base64_decode($crypt2));
			
			$public_key_trans_to = $public_key_to_1 . $public_key_to_2;
			
			// Decode Amount
			$transaction_info = tk_decrypt($public_key_trans, base64_decode($crypt3));

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
					if($public_key_trans_to == $my_public_key)
					{
						$public_key_to = '<td class="style2"><font color="green">My Public Key</font>';
					}
					else
					{
						$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:195px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans_to) . '</p>';
					}
				}
				
				$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:195px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans) . '</p>';
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
		
		$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");

		home_screen('Transactions in Network Queue', $text_bar, $body_string , $quick_info, $home_update);
		exit;
	}
//****************************************************************************	
	if($_GET["menu"] == "tools")
	{
		if($_GET["action"] == "check_tables")
		{
			set_time_limit(300);
			write_log("A CHECK of the Entire Database & Tables Was Started.", "GU");

			$body_string = '<strong>Checking All Database Tables</strong></font></br></br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Check Database Results</th></tr><tr><td>';

			$db_check = mysql_query("CHECK TABLE `activity_logs` , `my_keys` , `my_transaction_queue` , `options` , `transaction_queue`");
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

			write_log("A CHECK of the Entire Database & Tables Was Finished.", "GU");			
		}

		if($_GET["action"] == "repair_tables")
		{
			set_time_limit(500);
			write_log("A REPAIR of the Entire Database & Tables Was Started.", "GU");

			$body_string = '<strong>Repair All Database Tables</strong></font></br></br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Repair Database Results</th></tr><tr><td>';

			$db_check = mysql_query("REPAIR TABLE `activity_logs` , `my_keys` , `my_transaction_queue` , `options` , `transaction_queue`");
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

		if($_GET["action"] == "optimize_tables")
		{
			set_time_limit(500);
			write_log("An OPTIMIZE of the Entire Database & Tables Was Started.", "GU");

			$body_string = '<strong>Optimize All Database Tables</strong></font></br></br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Optimize Database Results</th></tr><tr><td>';

			$db_check = mysql_query("OPTIMIZE TABLE `activity_logs` , `my_keys` , `my_transaction_queue` , `options` , `transaction_queue`");
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

			$body_string .= '<strong>OPTIMIZE FINISHED</strong></td></tr></table></div>';

			write_log("An OPTIMIZE of the Entire Database & Tables Was Finished.", "GU");			
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
					case "AA":
						$filter_by = ' (Filtered by <strong>New Filter 1</strong>)';
						break;

					case "BB":
						$filter_by = ' (Filtered by <strong>New Filter 2</strong>)';
						break;
				}
			}
			
			$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> Log Events</strong>' . $filter_by . '<table border="0" cellspacing="5"><tr><td>
				Filter By:</td><td><FORM ACTION="index.php?menu=tools&logs=listmore" METHOD="post"><select name="filter"><option value="all" SELECTED>Show All</option>
				<option value="AA">Filter 1</option>
				<option value="BB">Filter 2</option></select></td></tr></table>
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

		$quick_info = '<strong>Check DB</strong> will check the data integrity of all tables in the database.</br></br>
			<strong>Optimize DB</strong> will optimize all tables & indexes in the database.</br></br>
			<strong>Repair DB</strong> will attempt to repair all tables in the database.</br></br>			
			<i>Note:</i> The database utilities can take a long time to process and complete.';
		
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
				// Blank reverse crypto data field
				mysql_query("UPDATE `options` SET `field_data` = '' WHERE `options`.`field_name` = 'generation_key_crypt' LIMIT 1");				
				
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
				// Blank reverse crypto data field
				mysql_query("UPDATE `options` SET `field_data` = '' WHERE `options`.`field_name` = 'generation_key_crypt' LIMIT 1");

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
		mysql_query("TRUNCATE `active_peer_list`");
		mysql_query("TRUNCATE `new_peers_list`");
		header("Location: index.php");
		exit;		
	}
//****************************************************************************
} // End Valid Login Check
//****************************************************************************
//****************************************************************************

?>
