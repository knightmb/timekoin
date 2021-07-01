<?PHP
//***********************************************************
//***********************************************************
function login_screen($error_message)
{

?>
<!DOCTYPE html>
<html>
<head>
<title>Timekoin</title>
<link rel="icon" type="image/x-icon" href="img/favicon.ico" />
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<link  href="css/admin.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div id="main">
<div id="header">
<ul id="top-navigation">
<li><a href="#" class="active">Login</a></li>
</ul>
</div>
<div id="middle">
<div id="left-column">
</div>
<div id="center-column">
<div class="top-bar">
<h1>Timekoin Server Login</h1>
</div>
<div class="select-bar">
<FORM ACTION="index.php?action=login" METHOD="post">
<table border="0"><tr><td align="right">
Username: <input type="text" size="20" name="timekoin_username" /><br>
Password: <input type="password" size="20" name="timekoin_password" />	
</td><td>
<input type="submit" name="Submit" value="Login" /></td></tr></table>
</FORM>
</div>
<font color="red"><strong><?PHP echo $error_message; ?></strong></font>
<center><img src="img/timekoin_logo.png" alt="" /></center>
</div>
</div>
<div id="footer"><p>Timekoin Crypto Currency - <a href="http://timekoin.org">http://timekoin.org</a> &copy; 2010&mdash;<?PHP echo date('Y'); ?></p></div>
</div>
</body>
</html>
<?PHP
} 
//***********************************************************
//***********************************************************
function home_screen($contents, $select_bar, $body, $quick_info, $refresh = 0, $plugin_reference = FALSE)
{
	$home;
	$options;
	$peerlist;
	$refresh_header;
	$system;
	$generation;
	$send;
	$history;
	$queue;

	if($refresh > 0)
	{
		$refresh_header = '<meta http-equiv="refresh" content="' . $refresh . '" />';
	}

	switch($_GET["menu"])
	{
		case "home":
			$home = 'class="active"';
			break;

		case "queue":
			$queue = 'class="active"';
			break;

		case "send":
			$send = 'class="active"';
			break;

		case "history":
			$history = 'class="active"';
			break;

		case "options":
			$options = 'class="active"';
			break;

		case "peerlist":
			$peerlist = 'class="active"';
			break;

		case "system":
			$system = 'class="active"';
			break;

		case "generation":
			$generation = 'class="active"';
			break;

		case "tools":
			$tools = 'class="active"';
			break;

		case "backup":
			$backup = 'class="active"';
			break;			
	}

	$standard_settings_number = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'standard_tabs_settings' LIMIT 1"),0,"field_data"));

	$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' ORDER BY `options`.`field_name` ASC";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$plugin_output;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);

		$plugin_file = find_string("---file=", "---enable", $sql_row["field_data"]);		
		$plugin_tab = find_string("---tab=", "---service", $sql_row["field_data"]);
		$plugin_enable = intval(find_string("---enable=", "---show", $sql_row["field_data"]));
		$plugin_show = intval(find_string("---show=", "---name", $sql_row["field_data"]));

		if($plugin_enable == TRUE && $plugin_show == TRUE)
		{
			if($plugin_reference == TRUE)
			{
				$plugin_output .= '<li><a href="' . $plugin_file . '">' . $plugin_tab . '</a></li>';
			}
			else
			{
				$plugin_output .= '<li><a href="plugins/' . $plugin_file . '">' . $plugin_tab . '</a></li>';
			}
		}
	}	

	if($plugin_reference == TRUE)
	{
		$menu_output = '<li><a href="../index.php?menu=home" ' . $home . '>Home</a></li>';		
if(check_standard_tab_settings($standard_settings_number, 1) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=peerlist" ' . $peerlist . '>Peerlist</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 2) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=queue" ' . $queue . '>Transaction Queue</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 4) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=send" ' . $send . '>Send / Receive</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 8) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=history" ' . $history . '>History</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 16) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=generation" ' . $generation . '>Generation</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 32) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=system" ' . $system . '>System</a></li>'; }
		$menu_output .= '<li><a href="../index.php?menu=options" ' . $options . '>Options</a></li>';
if(check_standard_tab_settings($standard_settings_number, 64) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=backup" ' . $backup . ' onclick="return confirm(\'This will load your private key! Only view this over your Private Network or SSL if accessing remotely from the Internet. Continue?\');">Backup</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 128) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=tools" ' . $tools . '>Tools</a></li>'; }
		$menu_output .= $plugin_output;
		$menu_output .= '<li><a href="../index.php?menu=logoff">Log Out</a></li>';
	}
	else
	{
		$menu_output = '<li><a href="index.php?menu=home" ' . $home . '>Home</a></li>';		
if(check_standard_tab_settings($standard_settings_number, 1) == TRUE) { $menu_output .= '<li><a href="index.php?menu=peerlist" ' . $peerlist . '>Peerlist</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 2) == TRUE) { $menu_output .= '<li><a href="index.php?menu=queue" ' . $queue . '>Transaction Queue</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 4) == TRUE) { $menu_output .= '<li><a href="index.php?menu=send" ' . $send . '>Send / Receive</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 8) == TRUE) { $menu_output .= '<li><a href="index.php?menu=history" ' . $history . '>History</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 16) == TRUE) { $menu_output .= '<li><a href="index.php?menu=generation" ' . $generation . '>Generation</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 32) == TRUE) { $menu_output .= '<li><a href="index.php?menu=system" ' . $system . '>System</a></li>'; }
		$menu_output .= '<li><a href="index.php?menu=options" ' . $options . '>Options</a></li>';
if(check_standard_tab_settings($standard_settings_number, 64) == TRUE) { $menu_output .= '<li><a href="index.php?menu=backup" ' . $backup . ' onclick="return confirm(\'This will load your private key! Only view this over your Private Network or SSL if accessing remotely from the Internet. Continue?\');">Backup</a></li>'; }
if(check_standard_tab_settings($standard_settings_number, 128) == TRUE) { $menu_output .= '<li><a href="index.php?menu=tools" ' . $tools . '>Tools</a></li>'; }
		$menu_output .= $plugin_output;
		$menu_output .= '<li><a href="index.php?menu=logoff">Log Out</a></li>';
	}

?>
<!DOCTYPE html>
<html>
<head>
<title>Timekoin Server Administration</title>
<?PHP 

	if($plugin_reference == TRUE)
	{
		// Redirect File Reference for Plugin
		?>
<link rel="icon" type="image/x-icon" href="../img/favicon.ico" />
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<link  href="../css/admin.css" rel="stylesheet" type="text/css" />
		<?PHP
	}
	else
	{
		// No Plugin
		?>
<link rel="icon" type="image/x-icon" href="img/favicon.ico" />
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<link  href="css/admin.css" rel="stylesheet" type="text/css" />
		<?PHP
	}

?>

<?PHP echo $refresh_header; ?>
</head>
<body>
<div id="main">
<div id="header">
<ul id="top-navigation">
<?PHP echo $menu_output; ?>
</ul>
</div>
<div id="middle">
<div id="left-column">
<?PHP
	if($plugin_reference == TRUE)
	{
		echo '<img src="../img/timekoin_logo.png" width="80" height="80" alt="" />';
	}
	else
	{
		echo '<img src="img/timekoin_logo.png" width="80" height="80" alt="" />';
	}	
?>
</div>
<div id="center-column">
<div class="top-bar">
<h1><?PHP echo $contents; ?></h1>
</div>
<div class="select-bar">
<?PHP echo $select_bar; ?>
</div>
<?PHP echo $body; ?>
</div>
<div id="right-column">
<strong class="h">Quick Info</strong>
<div class="box"><?PHP echo $quick_info; ?></div>
</div>
</div>
<div id="footer"><p>Timekoin Crypto Currency v<?PHP echo TIMEKOIN_VERSION; ?> - <a href="http://timekoin.org">http://timekoin.org</a> &copy; 2010&mdash;<?PHP echo date('Y'); ?> - ( You are logged in as <strong><?PHP echo $_SESSION["login_username"]; ?></strong> )</p></div>
</div>
</body>
</html>
<?PHP
} 
//***********************************************************
//***********************************************************
function trans_percent_status()
{
	// Total number of transaction cycle hashes in database
	$total_trans_hash = count_transaction_hash();

	$percent_update = count_transaction_hash() / transaction_cycle(0, TRUE) * 100;

	if($percent_update == 100)
	{
		$status = '<font color="#818181"><strong>100%</strong></font>';
	}
	else if($percent_update < 100 && $percent_update >= 99)
	{
		$status = '<font color="#5858FA"><strong>' . number_format($percent_update, 3) . '%</strong></font><strong> (' . number_format(transaction_cycle(0, TRUE) - $total_trans_hash) . ' Transaction Cycles to Update)</strong>';
	}
	else
	{
		$status = '<font color="red"><strong>' . number_format($percent_update, 2) . '%</strong></font><strong> (' . number_format(transaction_cycle(0, TRUE) - $total_trans_hash) . ' Transaction Cycles to Update)</strong>';
	}

	return $status;
}
//***********************************************************
//***********************************************************
function options_screen()
{
	if($_GET["newkeys"] == "generate")
	{
		// Offer Confirmation Screen
		$confirm_message = '<strong><font color="red">Generating New Keys will delete the old keys in the database.</font><br>Be sure to make backups if you intend on keeping any balance associated with the current keys.<br><font color="blue">Continue?</font></strong>';
		$form_action = '<FORM ACTION="index.php?menu=options&amp;newkeys=confirm" METHOD="post">';
	}
	else
	{
		$form_action = '<FORM ACTION="index.php?menu=options&amp;newkeys=generate" METHOD="post">';
	}
	
	return '<FORM ACTION="index.php?menu=options&amp;password=change" METHOD="post">
	<table border="0"><tr><td style="width:325px" valign="bottom" align="right">
	Current Username: <input type="text" name="current_username" /><br>
	New Username: <input type="text" name="new_username" /><br>
	Confirm Username: <input type="text" name="confirm_username" />
	</td></tr>
	<tr><td></td></tr>
	<tr><td align="right">
	Current Password: <input type="password" name="current_password" /><br>
	New Password: <input type="password" name="new_password" /><br>
	Confirm Password: <input type="password" name="confirm_password" /><br><br>
	<input type="submit" name="Submit" value="Change" />
	</td></tr></table></FORM>
	<table border="0"><tr><td style="width:630px" valign="bottom" align="right">' . $confirm_message . $form_action .'
	<input type="submit" name="Submit2" value="Generate New Keys" /></FORM></td></tr>
	</table>';
} 
//***********************************************************
//***********************************************************
function options_screen2()
{
	$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");
	$peerlist_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_peerlist' LIMIT 1"),0,"field_data");
	$queue_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_queue' LIMIT 1"),0,"field_data");
	$super_peer = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'super_peer' LIMIT 1"),0,"field_data");
	$peer_failure_grade = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'peer_failure_grade' LIMIT 1"),0,"field_data");
	$default_timezone = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"),0,"field_data");

	if(empty($default_timezone) == FALSE)
	{
		$default_timezone = '<option value="' . $default_timezone . '">' . $default_timezone . '</option>';
	}

	if($super_peer == 1)
	{
		$super_peer = 500;
	}

	return '<FORM ACTION="index.php?menu=options&amp;refresh=change" METHOD="post">
	<table border="0"><tr><td style="width:415px" valign="bottom" align="right"><strong>Refresh Rates (seconds) for Realtime Pages [0 = disable]</strong><br><br>
	</td><td style="width:215px"></td></tr>
	<tr><td valign="bottom" align="right">
	Home: <input type="text" name="home_update" size="2" value="' . $home_update . '" /><br>
	Peerlist: <input type="text" name="peerlist_update" size="2" value="' . $peerlist_update . '" /><br>
	Transaction Queue: <input type="text" name="queue_update" size="2" value="' . $queue_update . '" /></td><td></td></tr>
	<tr><td></td><td></td></tr>
	<tr><td align="right"><strong>Super Peer Limit (10 - 500)</strong><br><input type="text" name="super_peer_limit" size="3" value="' . $super_peer . '" /><br></td>
	<td><input type="submit" name="Submit2" value="Save Options" /></td></tr>
	<tr><td></td><td></td></tr>
	<tr><td align="right"><strong>Peer Failure Limit (1 - 100)</strong><br><input type="text" name="peer_failure_grade" size="3" value="' . $peer_failure_grade . '" /><br></td><td></td>
	<tr><td align="right"><strong>Timezone</strong><br>
	<select name="timezone">
	' . $default_timezone . '
	<option value="">Use System Default</option>
	<option value="Pacific/Midway">(GMT-11:00) Midway Island, Samoa</option>
	<option value="America/Adak">(GMT-10:00) Hawaii-Aleutian</option>
	<option value="Etc/GMT+10">(GMT-10:00) Hawaii</option>
	<option value="Pacific/Marquesas">(GMT-09:30) Marquesas Islands</option>
	<option value="Pacific/Gambier">(GMT-09:00) Gambier Islands</option>
	<option value="America/Anchorage">(GMT-09:00) Alaska</option>
	<option value="America/Ensenada">(GMT-08:00) Tijuana, Baja California</option>
	<option value="Etc/GMT+8">(GMT-08:00) Pitcairn Islands</option>
	<option value="America/Los_Angeles">(GMT-08:00) Pacific Time (US & Canada)</option>
	<option value="America/Denver">(GMT-07:00) Mountain Time (US & Canada)</option>
	<option value="America/Chihuahua">(GMT-07:00) Chihuahua, La Paz, Mazatlan</option>
	<option value="America/Dawson_Creek">(GMT-07:00) Arizona</option>
	<option value="America/Belize">(GMT-06:00) Saskatchewan, Central America</option>
	<option value="America/Cancun">(GMT-06:00) Guadalajara, Mexico City, Monterrey</option>
	<option value="Chile/EasterIsland">(GMT-06:00) Easter Island</option>
	<option value="America/Chicago">(GMT-06:00) Central Time (US & Canada)</option>
	<option value="America/New_York">(GMT-05:00) Eastern Time (US & Canada)</option>
	<option value="America/Havana">(GMT-05:00) Cuba</option>
	<option value="America/Bogota">(GMT-05:00) Bogota, Lima, Quito, Rio Branco</option>
	<option value="America/Caracas">(GMT-04:30) Caracas</option>
	<option value="America/Santiago">(GMT-04:00) Santiago</option>
	<option value="America/La_Paz">(GMT-04:00) La Paz</option>
	<option value="Atlantic/Stanley">(GMT-04:00) Faukland Islands</option>
	<option value="America/Campo_Grande">(GMT-04:00) Brazil</option>
	<option value="America/Goose_Bay">(GMT-04:00) Atlantic Time (Goose Bay)</option>
	<option value="America/Glace_Bay">(GMT-04:00) Atlantic Time (Canada)</option>
	<option value="America/St_Johns">(GMT-03:30) Newfoundland</option>
	<option value="America/Araguaina">(GMT-03:00) UTC-3</option>
	<option value="America/Montevideo">(GMT-03:00) Montevideo</option>
	<option value="America/Miquelon">(GMT-03:00) Miquelon, St. Pierre</option>
	<option value="America/Godthab">(GMT-03:00) Greenland</option>
	<option value="America/Argentina/Buenos_Aires">(GMT-03:00) Buenos Aires</option>
	<option value="America/Sao_Paulo">(GMT-03:00) Brasilia</option>
	<option value="America/Noronha">(GMT-02:00) Mid-Atlantic</option>
	<option value="Atlantic/Cape_Verde">(GMT-01:00) Cape Verde Is.</option>
	<option value="Atlantic/Azores">(GMT-01:00) Azores</option>
	<option value="Europe/Belfast">(GMT) Greenwich Mean Time : Belfast</option>
	<option value="Europe/Dublin">(GMT) Greenwich Mean Time : Dublin</option>
	<option value="Europe/Lisbon">(GMT) Greenwich Mean Time : Lisbon</option>
	<option value="Europe/London">(GMT) Greenwich Mean Time : London</option>
	<option value="Africa/Abidjan">(GMT) Monrovia, Reykjavik</option>
	<option value="Europe/Amsterdam">(GMT+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna</option>
	<option value="Europe/Belgrade">(GMT+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague</option>
	<option value="Europe/Brussels">(GMT+01:00) Brussels, Copenhagen, Madrid, Paris</option>
	<option value="Africa/Algiers">(GMT+01:00) West Central Africa</option>
	<option value="Africa/Windhoek">(GMT+01:00) Windhoek</option>
	<option value="Asia/Beirut">(GMT+02:00) Beirut</option>
	<option value="Africa/Cairo">(GMT+02:00) Cairo</option>
	<option value="Asia/Gaza">(GMT+02:00) Gaza</option>
	<option value="Africa/Blantyre">(GMT+02:00) Harare, Pretoria</option>
	<option value="Asia/Jerusalem">(GMT+02:00) Jerusalem</option>
	<option value="Europe/Minsk">(GMT+02:00) Minsk</option>
	<option value="Asia/Damascus">(GMT+02:00) Syria</option>
	<option value="Europe/Moscow">(GMT+03:00) Moscow, St. Petersburg, Volgograd</option>
	<option value="Africa/Addis_Ababa">(GMT+03:00) Nairobi</option>
	<option value="Asia/Tehran">(GMT+03:30) Tehran</option>
	<option value="Asia/Dubai">(GMT+04:00) Abu Dhabi, Muscat</option>
	<option value="Asia/Yerevan">(GMT+04:00) Yerevan</option>
	<option value="Asia/Kabul">(GMT+04:30) Kabul</option>
	<option value="Asia/Yekaterinburg">(GMT+05:00) Ekaterinburg</option>
	<option value="Asia/Tashkent">(GMT+05:00) Tashkent</option>
	<option value="Asia/Kolkata">(GMT+05:30) Chennai, Kolkata, Mumbai, New Delhi</option>
	<option value="Asia/Katmandu">(GMT+05:45) Kathmandu</option>
	<option value="Asia/Dhaka">(GMT+06:00) Astana, Dhaka</option>
	<option value="Asia/Novosibirsk">(GMT+06:00) Novosibirsk</option>
	<option value="Asia/Rangoon">(GMT+06:30) Yangon (Rangoon)</option>
	<option value="Asia/Bangkok">(GMT+07:00) Bangkok, Hanoi, Jakarta</option>
	<option value="Asia/Krasnoyarsk">(GMT+07:00) Krasnoyarsk</option>
	<option value="Asia/Hong_Kong">(GMT+08:00) Beijing, Chongqing, Hong Kong, Urumqi</option>
	<option value="Asia/Irkutsk">(GMT+08:00) Irkutsk, Ulaan Bataar</option>
	<option value="Australia/Perth">(GMT+08:00) Perth</option>
	<option value="Australia/Eucla">(GMT+08:45) Eucla</option>
	<option value="Asia/Tokyo">(GMT+09:00) Osaka, Sapporo, Tokyo</option>
	<option value="Asia/Seoul">(GMT+09:00) Seoul</option>
	<option value="Asia/Yakutsk">(GMT+09:00) Yakutsk</option>
	<option value="Australia/Adelaide">(GMT+09:30) Adelaide</option>
	<option value="Australia/Darwin">(GMT+09:30) Darwin</option>
	<option value="Australia/Brisbane">(GMT+10:00) Brisbane</option>
	<option value="Australia/Hobart">(GMT+10:00) Hobart</option>
	<option value="Asia/Vladivostok">(GMT+10:00) Vladivostok</option>
	<option value="Australia/Lord_Howe">(GMT+10:30) Lord Howe Island</option>
	<option value="Etc/GMT-11">(GMT+11:00) Solomon Is., New Caledonia</option>
	<option value="Asia/Magadan">(GMT+11:00) Magadan</option>
	<option value="Pacific/Norfolk">(GMT+11:30) Norfolk Island</option>
	<option value="Asia/Anadyr">(GMT+12:00) Anadyr, Kamchatka</option>
	<option value="Pacific/Auckland">(GMT+12:00) Auckland, Wellington</option>
	<option value="Etc/GMT-12">(GMT+12:00) Fiji, Kamchatka, Marshall Is.</option>
	<option value="Pacific/Chatham">(GMT+12:45) Chatham Islands</option>
	<option value="Pacific/Tongatapu">(GMT+13:00) Nuku\'alofa</option>
	<option value="Pacific/Kiritimati">(GMT+14:00) Kiritimati</option>
	</select>
	</td><td></td>
	<tr><td colspan="2"><hr></td></tr></table></FORM>
	<table border="0"><tr><td style="width:415px" align="right"><FORM ACTION="index.php?menu=options&amp;hashcode=manage" METHOD="post"><input type="submit" name="Submit3" value="Manage Hash Code Access" /></FORM></td>
	<td style="width:215px" valign="bottom" align="right"><FORM ACTION="index.php?menu=options&amp;upgrade=check" METHOD="post"><input type="submit" name="Submit3" value="Check for Updates" /></FORM></td></tr>
	<tr><td colspan="2"><hr></td></tr>
	<tr><td align="right"><FORM ACTION="index.php?menu=options&amp;manage=tabs" METHOD="post"><input type="submit" name="Submit4" value="Menu Tabs" /></FORM></td>
	<td align="right"><FORM ACTION="index.php?menu=options&amp;manage=plugins" METHOD="post"><input type="submit" name="Submit5" value="Manage Plugins" /></FORM></td></tr>
	</table>
	';

} 
//***********************************************************
//***********************************************************
function options_screen3()
{
	if($_GET["upgrade"] == "check")
	{
		return check_for_updates();
	}
	else if($_GET["upgrade"] == "doupgrade")
	{		
		return do_updates();
	}	
	
	return;
} 
//***********************************************************
//***********************************************************
function options_screen4()
{
	$standard_settings_number = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'standard_tabs_settings' LIMIT 1"),0,"field_data"));
		
	if(check_standard_tab_settings($standard_settings_number, 1) == TRUE) { $tab_peerlist_enable = "CHECKED"; }else{ $tab_peerlist_disable = "CHECKED"; }
	if(check_standard_tab_settings($standard_settings_number, 2) == TRUE) { $trans_queue_enable = "CHECKED"; }else{ $trans_queue_disable = "CHECKED"; }
	if(check_standard_tab_settings($standard_settings_number, 4) == TRUE) { $send_receive_enable = "CHECKED"; }else{ $send_receive_disable = "CHECKED"; }			
	if(check_standard_tab_settings($standard_settings_number, 8) == TRUE) { $history_enable = "CHECKED"; }else{ $history_disable = "CHECKED"; }
	if(check_standard_tab_settings($standard_settings_number, 16) == TRUE) { $generation_enable = "CHECKED"; }else{ $generation_disable = "CHECKED"; }
	if(check_standard_tab_settings($standard_settings_number, 32) == TRUE) { $system_enable = "CHECKED"; }else{ $system_disable = "CHECKED"; }
	if(check_standard_tab_settings($standard_settings_number, 64) == TRUE) { $backup_enable = "CHECKED"; }else{ $backup_disable = "CHECKED"; }
	if(check_standard_tab_settings($standard_settings_number, 128) == TRUE) { $tools_enable = "CHECKED"; }else{ $tools_disable = "CHECKED"; }

//	Plugin Tabs
	$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' ORDER BY `options`.`field_name` ASC";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$plugin_output;

	if($sql_num_results > 0) { $plugin_output .= '<input type="hidden" name="plugins_installed" value="1">'; }
	
	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);

		$plugin_file = find_string("---file=", "---enable", $sql_row["field_data"]);		
		$plugin_name = find_string("---name=", "---tab", $sql_row["field_data"]);
		$plugin_show = intval(find_string("---show=", "---name", $sql_row["field_data"]));

		if($plugin_show == TRUE)
		{
			$plugin_output .= '<tr><td valign="top" align="right">' . $plugin_name . '</td>
	<td valign="top" align="left"><input type="radio" name="plugins_status_' . $i . '" value="0">Hide <input type="radio" name="plugins_status_' . $i . '" value="1" CHECKED>Show</td></tr>
	<input type="hidden" name="plugins_'. $i .'" value="' . $plugin_file . '">';
		}
		else
		{
			$plugin_output .= '<tr><td valign="top" align="right">' . $plugin_name . '</td>
	<td valign="top" align="left"><input type="radio" name="plugins_status_' . $i . '" value="0" CHECKED>Hide <input type="radio" name="plugins_status_' . $i . '" value="1">Show</td></tr>
	<input type="hidden" name="plugins_'. $i .'" value="' . $plugin_file . '">';
		}
	}

	return '<FORM ACTION="index.php?menu=options&amp;tabs=change" METHOD="post">
	<table border="0" cellpadding="3"><tr><td style="width:200px" valign="bottom" align="center" colspan="2"><strong>Standard Tabs</strong></td></tr>
	<tr><td valign="top" align="right">Peerlist</td>
	<td valign="top" align="left" style="width:200px"><input type="radio" name="tab_peerlist" value="0" ' . $tab_peerlist_disable . '>Hide <input type="radio" name="tab_peerlist" value="1" ' . $tab_peerlist_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Transaction Queue</td>
	<td valign="top" align="left"><input type="radio" name="tab_trans_queue" value="0" ' . $trans_queue_disable . '>Hide <input type="radio" name="tab_trans_queue" value="1" ' . $trans_queue_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Send / Receive</td>
	<td valign="top" align="left"><input type="radio" name="tab_send_receive" value="0" ' . $send_receive_disable . '>Hide <input type="radio" name="tab_send_receive" value="1" ' . $send_receive_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">History</td>
	<td valign="top" align="left"><input type="radio" name="tab_history" value="0" ' . $history_disable . '>Hide <input type="radio" name="tab_history" value="1" ' . $history_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Generation</td>
	<td valign="top" align="left"><input type="radio" name="tab_generation" value="0" ' . $generation_disable . '>Hide <input type="radio" name="tab_generation" value="1" ' . $generation_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">System</td>
	<td valign="top" align="left"><input type="radio" name="tab_system" value="0" ' . $system_disable . '>Hide <input type="radio" name="tab_system" value="1" ' . $system_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Backup</td>
	<td valign="top" align="left"><input type="radio" name="tab_backup" value="0" ' . $backup_disable . '>Hide <input type="radio" name="tab_backup" value="1" ' . $backup_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Tools</td>
	<td valign="top" align="left"><input type="radio" name="tab_tools" value="0" ' . $tools_disable . '>Hide <input type="radio" name="tab_tools" value="1" ' . $tools_enable . '>Show</td></tr>
	<tr><td colspan="2"><hr></td></tr>
	<tr><td valign="bottom" align="center" colspan="2"><strong>Plugin Tabs</strong></td></tr>
	' . $plugin_output . '
	<tr><td align="right" colspan="2"><input type="submit" name="Submit1" value="Save Tabs" /></td></tr>
	</table></FORM>
	';

} 
//***********************************************************
//***********************************************************
function options_screen5()
{
	$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' ORDER BY `options`.`field_name` ASC";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$plugin_output;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$plugin_file = find_string("---file=", "---enable", $sql_row["field_data"]);
		$plugin_enable = intval(find_string("---enable=", "---show", $sql_row["field_data"]));
		$plugin_name = find_string("---name=", "---tab", $sql_row["field_data"]);
		$plugin_tab = find_string("---tab=", "---service", $sql_row["field_data"]);
		$plugin_service = find_string("---service=", "---end", $sql_row["field_data"]);

		if(empty($plugin_service) == TRUE) { $plugin_service = '<font color="red">NA</font>'; }

		if($plugin_enable == TRUE)
		{
			$plugin_toggle = '<FORM ACTION="index.php?menu=options&amp;plugin=disable" METHOD="post"><font color="blue"><strong>Enabled</strong></font><br><input type="submit" name="Submit'.$i.'" value="Disable Here" />
				<input type="hidden" name="pluginfile" value="' . $plugin_file . '"></FORM>';
		}
		else
		{
			$plugin_toggle = '<FORM ACTION="index.php?menu=options&amp;plugin=enable" METHOD="post"><font color="red">Disabled</font><br><input type="submit" name="Submit'.$i.'" value="Enable Here" />
				<input type="hidden" name="pluginfile" value="' . $plugin_file . '"></FORM>';
		}

		$plugin_output .= '<tr><td>' . $plugin_name . '</td><td>' . $plugin_tab . '</td><td>' . $plugin_file . '</td><td>' . $plugin_service . '</td>
		<td valign="top" align="center">' . $plugin_toggle . '</td>
		<td><FORM ACTION="index.php?menu=options&amp;remove=plugin" METHOD="post" onclick="return confirm(\'Delete ' . $plugin_name . '?\');"><input type="image" src="img/hr.gif" title="Delete ' . $plugin_name . '" name="remove' . $i . '" border="0">
		<input type="hidden" name="pluginfile" value="' . $plugin_file . '"></FORM></td></tr>
		<tr><td colspan="6"><hr></td></tr>';
	}

	return '<table border="0" cellpadding="2" cellspacing="10"><tr><td valign="bottom" align="center" colspan="6"><strong>Plugin Information</strong>
	</td></tr>
	<tr><td align="center"><strong>Name</strong></td><td align="center"><strong>Tab</strong></td><td align="center"><strong>File</strong></td>
	<td align="center"><strong>Service</strong></td><td align="center"><strong>Status</strong></td><td></td></tr>
	' . $plugin_output . '
	<tr><td align="right" colspan="6"><FORM ACTION="index.php?menu=options&amp;plugin=new" METHOD="post"><input type="submit" name="SubmitNew" value="Install New Plugin" /></FORM></td></tr>
	</table>
	';
} 
//***********************************************************
//***********************************************************
function options_screen6()
{
	return '<strong>Use the Browse Button to Select the Plugin File to Install</strong><br><br>
	<FORM ENCTYPE="multipart/form-data" METHOD="POST" ACTION="index.php?menu=options&amp;plugin=install">
	<INPUT NAME="plugin_file" TYPE="file" SIZE=32><br><br>
	<input type="submit" name="SubmitNew" value="Install New Plugin" onclick="return confirm(\'Always Use Caution When Installing Plugins From Untrusted Sources.\');" /></FORM>';
} 
//***********************************************************
//***********************************************************
function system_screen()
{
	$max = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,0);
	$new = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,0);
	$domain = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'server_domain' LIMIT 1"),0,0);
	$subfolder = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'server_subfolder' LIMIT 1"),0,0);
	$port = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,0);
	$gen_hash = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generating_peers_hash' LIMIT 1"),0,0);
	$trans_history_hash = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'transaction_history_hash' LIMIT 1"),0,0);
	$trans_queue_hash = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,0);
	$block_check_start = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,0);
	$uptime = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'timekoin_start_time' LIMIT 1"),0,0);
	$request_max = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'server_request_max' LIMIT 1"),0,0);
	$allow_lan_peers = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'allow_LAN_peers' LIMIT 1"),0,0));
	$allow_ambient_peer_restart = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'allow_ambient_peer_restart' LIMIT 1"),0,0));
	$trans_history_check = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'trans_history_check' LIMIT 1"),0,0));
	$gen_list_no_sync = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_peer_list_no_sync' LIMIT 1"),0,0);
	$super_peer_mode = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'super_peer' LIMIT 1"),0,0);
	$perm_peer_priority = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'perm_peer_priority' LIMIT 1"),0,0);
	$auto_update_generation_IP = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'auto_update_generation_IP' LIMIT 1"),0,0));
	$cli_mode = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'cli_mode' LIMIT 1"),0,0));
	$cli_port = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'cli_port' LIMIT 1"),0,0);
	$network_mode = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'network_mode' LIMIT 1"),0,0));

	if($network_mode == 3)
	{
		$network_mode_3 = "SELECTED";
	}
	else if($network_mode == 2)
	{
		$network_mode_2 = "SELECTED";
	}
	else
	{
		$network_mode_1 = "SELECTED";
	}

	if($cli_mode == 1)
	{
		$cli_mode_1 = "SELECTED";
	}
	else
	{
		$cli_mode_0 = "SELECTED";
	}

	if($auto_update_generation_IP == 1)
	{
		$auto_update_generation_IP_1 = "SELECTED";
	}
	else
	{
		$auto_update_generation_IP_0 = "SELECTED";
	}

	if($gen_list_no_sync == 0)
	{
		$gen_hash = '<font color="green">' . $gen_hash . '</font>';
	}
	else
	{
		$gen_hash = '<font color="red">' . $gen_hash . '</font>';
	}

	if($perm_peer_priority == 1)
	{
		$perm_peer_priority_1 = "SELECTED";
	}
	else
	{
		$perm_peer_priority_0 = "SELECTED";
	}

	if($super_peer_mode >= 1)
	{
		$super_peer_check_1 = "SELECTED";
	}
	else
	{
		$super_peer_check_0 = "SELECTED";
		$super_peer_mode = 1;
	}

	if($allow_lan_peers == 1)
	{
		$LAN_enable = "SELECTED";
	}
	else
	{
		$LAN_disable = "SELECTED";
	}

	if($allow_ambient_peer_restart == 1)
	{
		$ambient_restart_enable = "SELECTED";
	}
	else
	{
		$ambient_restart_disable = "SELECTED";
	}

	if($trans_history_check == 2)
	{
		$trans_history_check_2 = "SELECTED";
	}
	else if($trans_history_check == 1)
	{
		$trans_history_check_1 = "SELECTED";
	}
	else
	{
		$trans_history_check_0 = "SELECTED";
	}

	if($block_check_start == "0")
	{
		$trans_history_hash_color1 = '<font color="green">';
		$trans_history_hash_color2 = '</font>';
	}
	else
	{
		$trans_history_hash_color1 = '<font color="red">';
		$trans_history_hash_color2 = '</font>';
	}

	// Total number of records
	$total_records = mysql_result(mysql_query("SELECT COUNT(*) FROM `transaction_history`"),0);

	// Total number of transaction foundations in database
	$total_foundations = mysql_result(mysql_query("SELECT COUNT(*) FROM `transaction_foundation`"),0);

	if($total_foundations == foundation_cycle(0, TRUE))
	{
		$total_foundations = '<font color="green">' . number_format($total_foundations) . '</font>';
	}
	else
	{
		$total_foundations = '<font color="red">' . number_format($total_foundations) . '</font>';
	}

	// Total number of transaction cycle hashes in database
	$total_trans_hash = count_transaction_hash();

	if($total_trans_hash == transaction_cycle(0, TRUE))
	{
		$total_trans_hash = '<font color="green">' . number_format($total_trans_hash) . '</font>';
	}
	else
	{
		$total_trans_hash = '<font color="red">' . number_format($total_trans_hash) . '</font>';
	}

	// Database Size
	$db_size = mysql_result(mysql_query("SELECT CONCAT(SUM(ROUND(((DATA_LENGTH + INDEX_LENGTH - DATA_FREE) / 1024 / 1024),2)),\" MB\") AS Size FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA LIKE '" . MYSQL_DATABASE . "'"),0);

	$html_return = '<FORM ACTION="index.php?menu=system&amp;server_settings=change" METHOD="post">
	<table border="0"><tr><td align="right" style="width:325px">
	Maximum Active Peers: <input type="text" name="max_peers" size="3" value="' . $max . '"/><br>
	Maximum Reserve Peers: <input type="text" name="max_new_peers" size="3" value="' . $new . '"/><br><br>
	Domain: <input type="text" name="domain" size="25" maxlength="256" value="' . $domain . '"/><br>
	Subfolder: <input type="text" name="subfolder" size="12" maxlength="256" value="' . $subfolder . '"/><br><br>
	CLI Mode: <select name="cli_mode"><option value="0" ' . $cli_mode_0 . '>Disable</option><option value="1" ' . $cli_mode_1 . '>Enable</option></select><br><br>
	Allow LAN Peers: <select name="allow_LAN"><option value="0" ' . $LAN_disable . '>Disable</option><option value="1" ' . $LAN_enable . '>Enable</option></select><br><br>
	Allow Ambient Peer Restarts: <select name="allow_ambient"><option value="0" ' . $ambient_restart_disable . '>Disable</option><option value="1" ' . $ambient_restart_enable . '>Enable</option></select><br><br>
	Super Peer: <select name="super_peer"><option value="0" ' . $super_peer_check_0 . '>Disable</option><option value="' . $super_peer_mode . '" ' . $super_peer_check_1 . '>Enable</option></select><br><br>
	</td>
	<td valign="top" align="right" style="width:300px">
	Public Server Port: <input type="text" name="port" size="6" maxlength="5" value="' . $port . '"/><br>
	Max Peer Query: <input type="text" name="max_request" size="6" maxlength="6" value="' . $request_max . '"/><br>
	Local Server Port: <input type="text" name="cli_port" size="6" maxlength="5" value="' . $cli_port . '"/><br><br>
	Network Mode: <select name="network_mode"><option value="1" ' . $network_mode_1 . '>IPv4 + IPv6 Gateway</option><option value="2" ' . $network_mode_2 . '>IPv4 Only</option><option value="3" ' . $network_mode_3 . '>IPv6 Only</option></select><br><br>
	Permanent Peer Priority: <select name="perm_peer_priority"><option value="0" ' . $perm_peer_priority_0 . '>Disable</option><option value="1" ' . $perm_peer_priority_1 . '>Enable</option></select><br><br>
	Auto Generation IP Update: <select name="auto_update_IP"><option value="0" ' . $auto_update_generation_IP_0 . '>Disable</option><option value="1" ' . $auto_update_generation_IP_1 . '>Enable</option></select><br><br>
	Transaction History Checks: <select name="trans_history_check"><option value="0" ' . $trans_history_check_0 . '>Rare</option><option value="1" ' . $trans_history_check_1 . '>Normal</option><option value="2" ' . $trans_history_check_2 . '>Frequent</option></select><br><br>
	</td></tr></table><input type="submit" name="submit_server" value="Update System Settings" /></FORM>
	<hr>
	<table border="0"><tr><td align="right">
	<strong>Miscellaneous Server</strong><br><br>
	Generating Peers List Hash:<br>
	Transaction History Hash:<br>
	Transaction Queue Hash:<br>
	Transaction History Records:<br>
	Transaction Cycles:<br>
	Transaction Foundations:<br>
	Uptime:<br>
	Database Size:
	</td><td align="left">
	<strong>Information<br><br>
	' . $gen_hash . '<br>
	' . $trans_history_hash_color1 . $trans_history_hash .  $trans_history_hash_color2 . '<br>
	' . $trans_queue_hash . '<br>
	' . number_format($total_records) . '<br>
	' . $total_trans_hash . ' of ' . number_format(transaction_cycle(0, TRUE)) . '<br>
	' . $total_foundations . ' of ' . number_format(foundation_cycle(0, TRUE)) . '<br>
	' . tk_time_convert(time() - $uptime) . '<br>
	' . $db_size .
	'</strong></td></tr></table><hr>';

	return $html_return;
}
//***********************************************************
//***********************************************************
function system_service_bar()
{
	return '<table cellspacing="10" border="0"><tr><td style="width:150px"><FORM ACTION="main.php?action=begin_main" METHOD="post"><input type="submit" value="START Timekoin"/></FORM></td>
	<td style="width:150px"><FORM ACTION="index.php?menu=system&amp;stop=main" METHOD="post" onclick="return confirm(\'Are You Sure? This Will Stop Timekoin and All Running Process.\');"><input type="submit" value="STOP Timekoin"/></FORM></td></tr></table><hr>
	<table cellspacing="10" border="0"><tr><td style="width:150px"><FORM ACTION="watchdog.php?action=begin_watchdog" METHOD="post"><input type="submit" value="Start Watchdog"/></FORM></td>
	<td style="width:150px"><FORM ACTION="index.php?menu=system&amp;stop=watchdog" METHOD="post" onclick="return confirm(\'Are You Sure? This Will Stop the Watchdog Process.\');"><input type="submit" value="Stop Watchdog"/></FORM></td></tr></table>';
}
//***********************************************************
//***********************************************************
function generation_body($generate_currency)
{
	$return_html;
	if($generate_currency == "1")
	{
		$return_html = '<table border="0" cellspacing="10"><tr><td><FORM ACTION="index.php?menu=generation&amp;generate=disable" METHOD="post"><input type="submit" value="Disable Generation"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&amp;generate=showlist" METHOD="post"><input type="submit" value="Show Generation List"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&amp;generate=showqueue" METHOD="post"><input type="submit" value="Show Election Queue List"/></FORM></td></tr></table>';
	}
	else
	{
		$return_html = '<table border="0"><tr><td><FORM ACTION="index.php?menu=generation&amp;generate=enable" METHOD="post"><input type="submit" value="Enable Generation"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&amp;generate=showlist" METHOD="post"><input type="submit" value="Show Generation List"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&amp;generate=showqueue" METHOD="post"><input type="submit" value="Show Election Queue List"/></FORM></td></tr></table>';
	}

	if($_GET["generate"] == "")
	{
		$return_html .= '<br><strong>How Generation Works</strong><br><ol>
		<li>The server must be accessible from the Internet and be able to accept and respond to HTTP requests on the port designated in the System tab. 
		This allows peer servers to validate the existence of your server. 
		You may test you router/firewall settings using the <a href="index.php?menu=generation&amp;firewall=tool"><font color="blue"><strong>Firewall Tool</strong></font></a>. 
		If your server fails this test, you must modify your router or firewall settings to allow inbound TCP connections on your chosen port.</li>
		<li>A single server key is chosen randomly for generation during an election cycle. Elections are pseudo-randomized. 
		You may use the <a href="index.php?menu=generation&amp;elections=show"><font color="blue"><strong>Election Calendar</strong></font></a> to see upcoming elections in the next 48 hours.</li>
		<li>Once elected, your server will create generation transactions during generation cycles. Generation cycles occur at pseudo-random times. 
		Use the <a href="index.php?menu=generation&amp;generations=show"><font color="blue"><strong>Generation Calendar</strong></font></a> to see the upcoming generation cycles in the next 24 hours.</li>
		<li>The server may continue to generate currency as long as it stays online. 
		If the server does not generate currency for 2 hours, the network assumes it has gone offline and the server key will be removed from the Generating Peer List. 
		Once the server comes back online, it will need to be re-elected before generation can begin again.</li>
		</ol>
		<strong>Generation Amount Schedule</strong><br>
		The amount a server can generate is directly related to the length of time it has been online and generating currency in the Timekoin network.<br>
		<table border="0" cellpadding="2"><tr><td><I>Time Generating</I></td><td><I>Currency per Generation Cycle</I></td></tr>
		<tr><td>0 - 1 week</td><td><font color="green"><strong>1</strong></font></td></tr>
		<tr><td>1 - 2 weeks</td><td><font color="green"><strong>2</strong></font></td></tr>
		<tr><td>2 - 4 weeks</td><td><font color="green"><strong>3</strong></font></td></tr>
		<tr><td>4 - 8 weeks</td><td><font color="green"><strong>4</strong></font></td></tr>
		<tr><td>8 - 16 weeks</td><td><font color="green"><strong>5</strong></font></td></tr>
		<tr><td>16 - 32 weeks</td><td><font color="green"><strong>6</strong></font></td></tr>
		<tr><td>32 - 64 weeks</td><td><font color="green"><strong>7</strong></font></td></tr>
		<tr><td>64 - 128 weeks</td><td><font color="green"><strong>8</strong></font></td></tr>
		<tr><td>128 - 256 weeks</td><td><font color="green"><strong>9</strong></font></td></tr>
		<tr><td>256 or more weeks</td><td><font color="green"><strong>10</strong></font></td></tr>
		</table><br>';
	}
    
    return $return_html;
}
//***********************************************************
//***********************************************************
function send_receive_body($fill_in_key, $amount, $cancel = FALSE, $easy_key, $message)
{
	if($cancel == TRUE)
	{
		// Redo menu to show cancel or complete send buttons
		$cancel_button = '<FORM ACTION="index.php?menu=send" METHOD="post"><input type="submit" name="Submit2" value="Cancel" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&amp;complete=send" METHOD="post">';
	}
	else
	{
		$cancel_button = '<FORM ACTION="index.php?menu=send&amp;easykey=grab" METHOD="post"><input type="text" size="24" name="easy_key" value="' . $easy_key . '" /><br>
			<input type="submit" value="Easy Key" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&amp;check=key" METHOD="post">';
	}

	return '<strong><font color="blue">Public Key</font> to send transaction:</strong><br>' . $form_action . '<table border="0" cellpadding="6"><tr><td colspan="2">
	<textarea name="send_public_key" rows="6" cols="75">' . $fill_in_key . '</textarea></td></tr>
	<tr><td style="width:580px" colspan="2"><strong>Message:</strong><br><input type="text" maxlength="64" size="64" value="' . $message . '" name="send_message" /></td></tr>
	<tr><td valign="top"><strong>Amount:</strong> <input type="text" size="8" value="' . $amount . '" name="send_amount" />
	<input type="submit" name="Submit1" value="Send Timekoins" /></td></tr></table></FORM>
	<table border="0" cellpadding="6"><tr><td style="width:580px" align="right">' . $cancel_button  . '</td></tr>
	<tr><td align="right">Create Your Own Here:<br><a target="_blank" href="http://easy.timekoin.net/">easy.timekoin.net</a></td></tr></table>';
}
//***********************************************************
//***********************************************************
function tools_bar()
{
	$default_walk = foundation_cycle(0, TRUE) * 500;
	$default_check = transaction_cycle(0, TRUE) - 10;
	$default_current = transaction_cycle(0, TRUE);

	$main_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,0);
	
	if($main_active != FALSE)
	{
		$disable_db_util = "DISABLED";
	}

	return '<FORM ACTION="index.php?menu=tools&amp;action=walk_history" METHOD="post">
	<table style="float: left;" cellspacing="5" border="0"><tr><td><input type="submit" value="History Walk"/></td>
	<td>Cycle#<input type="text" size="6" name="walk_history" value="' . $default_walk . '" /></td></tr></table></FORM>
	<FORM ACTION="index.php?menu=tools&amp;action=schedule_check" METHOD="post">
	<table style="float: left;" cellspacing="5" border="0"><tr><td><input type="submit" value="Check"/></td>
	<td>Cycle#<input type="text" size="6" name="schedule_check" value="' . $default_check . '" /></td></tr></table></FORM>
	<FORM ACTION="index.php?menu=tools&amp;action=repair" METHOD="post">
	<table style="float: left;" cellspacing="5" border="0"><tr><td><input type="submit" value="Repair" onclick="return confirm(\'Transaction Repair Can Take a Long Time. Continue?\');" /></td>
	<td>From Cycle#<input type="text" size="6" name="repair_from" value="' . $default_check . '" /></td></tr></table></FORM>
	<br><br><hr>
	<FORM ACTION="index.php?menu=tools&amp;action=check_tables" METHOD="post" onclick="return confirm(\'Database Check Can Take a Long Time. Continue?\');">
	<table style="float: left;" cellspacing="6" border="0"><tr><td><input type="submit" value="Check DB"/ '.$disable_db_util.'></td></tr></table></FORM>
	<FORM ACTION="index.php?menu=tools&amp;action=optimize_tables" METHOD="post" onclick="return confirm(\'Database Optimize Can Take a Long Time. Continue?\');">
	<table style="float: left;" cellspacing="6" border="0"><tr><td><input type="submit" value="Optimize DB" '.$disable_db_util.'/></td></tr></table></FORM>
	<FORM ACTION="index.php?menu=tools&amp;action=repair_tables" METHOD="post" onclick="return confirm(\'Database Repair Can Take a Long Time. Continue?\');">
	<table style="float: left;" cellspacing="6" border="0"><tr><td><input type="submit" value="Repair DB" '.$disable_db_util.'/></td></tr></table></FORM>
	<FORM ACTION="index.php?menu=tools&amp;action=clear_foundation" METHOD="post" onclick="return confirm(\'This Will Clear All Foundation Hashes. Continue?\');">
	<table style="float: left;" cellspacing="6" border="0"><tr><td><input type="submit" value="Clear Foundation"/></td></tr></table></FORM>
	<FORM ACTION="index.php?menu=tools&amp;action=clear_banlist" METHOD="post" onclick="return confirm(\'This Will Clear All Banned IPs. Continue?\');">
	<table style="float: left;" cellspacing="6" border="0"><tr><td><input type="submit" value="Clear Banlist"/></td></tr></table></FORM>
	<FORM ACTION="index.php?menu=tools&amp;action=clear_gen" METHOD="post" onclick="return confirm(\'This Will Clear The Peer Generation List & Election Queue. Continue?\');">
	<table style="float: left;" cellspacing="6" border="0"><tr><td><input type="submit" value="Clear Gen"/></td></tr></table></FORM>
	<br><br>';
}
//***********************************************************
//***********************************************************
function backup_body($private_key, $public_key, $cancel_private = FALSE, $cancel_public = FALSE)
{
	if($cancel_private == TRUE)
	{
		// Redo menu to show cancel or complete buttons
		$form_action = '<FORM ACTION="index.php?menu=backup&amp;dorestore=private" METHOD="post">';
		$are_you_sure = '<br><font color="red"><strong>This will over-write the Private Key<br> for your server. Are you sure?</strong></font>';
	}
	else
	{
		$form_action = '<FORM ACTION="index.php?menu=backup&amp;restore=private" METHOD="post">';
	}

	if($cancel_public == TRUE)
	{
		// Redo menu to show cancel or complete buttons
		$form_action2 = '<FORM ACTION="index.php?menu=backup&amp;dorestore=public" METHOD="post">';
		$are_you_sure2 = '<br><font color="red"><strong>This will over-write the Public Key<br> for your server. Are you sure?</strong></font>';		
	}
	else
	{
		$form_action2 = '<FORM ACTION="index.php?menu=backup&amp;restore=public" METHOD="post">';
	}

	return $form_action . '<table border="0" cellpadding="6"><tr><td colspan="2"><strong><font color="blue">Restore Private Key</font></strong></td></tr>
	<tr><td colspan="2"><textarea name="restore_private_key" rows="5" cols="75">' . $private_key . '</textarea></td></tr>
	<tr><td><input type="submit" value="Restore Private Key"/>' . $are_you_sure . '</td></tr>
	<tr><td colspan="2"><hr></td></tr></table></FORM>
	' . $form_action2 . '<table border="0" cellpadding="6">
	<tr><td colspan="2"><strong><font color="green">Restore Public Key</font></strong></td></tr>
	<tr><td colspan="2"><textarea name="restore_public_key" rows="5" cols="75">' . $public_key . '</textarea></td></tr>
	<tr><td><input type="submit" value="Restore Public Key"/>' . $are_you_sure2 . '</td></tr></table></FORM>';
}
//***********************************************************
//***********************************************************

?>
