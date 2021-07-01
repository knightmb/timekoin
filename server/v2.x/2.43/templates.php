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
Username: <input type="text" name="timekoin_username" /></br>
Password: <input type="password" name="timekoin_password" />	
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
function home_screen($contents, $select_bar, $body, $quick_info, $refresh = 0)
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

?>
<!DOCTYPE html>
<html>
<head>
<title>Timekoin Server Administration</title>
<link rel="icon" type="image/x-icon" href="img/favicon.ico" />
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<link  href="css/admin.css" rel="stylesheet" type="text/css" />
<?PHP echo $refresh_header; ?>
</head>
<body>
<div id="main">
<div id="header">
<ul id="top-navigation">
<li><a href="index.php?menu=home" <?PHP echo $home; ?>>Home</a></li>
<li><a href="index.php?menu=peerlist" <?PHP echo $peerlist; ?>>Peerlist</a></li>
<li><a href="index.php?menu=queue" <?PHP echo $queue; ?>>Transaction Queue</a></li>
<li><a href="index.php?menu=send" <?PHP echo $send; ?>>Send / Receive</a></li>
<li><a href="index.php?menu=history" <?PHP echo $history; ?>>History</a></li>
<li><a href="index.php?menu=generation" <?PHP echo $generation; ?>>Generation</a></li>
<li><a href="index.php?menu=system" <?PHP echo $system; ?>>System</a></li>
<li><a href="index.php?menu=options" <?PHP echo $options; ?>>Options</a></li>
<li><a href="index.php?menu=backup" <?PHP echo $backup; ?>>Backup</a></li>
<li><a href="index.php?menu=tools" <?PHP echo $tools; ?>>Tools</a></li>
<li><a href="index.php?menu=logoff">Log Out</a></li>					 
</ul>
</div>
<div id="middle">
<div id="left-column">
<img src="img/timekoin_logo.png" width="125" height="125" alt="" />
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
	$total_trans_hash = mysql_result(mysql_query("SELECT COUNT(attribute) FROM `transaction_history` WHERE `attribute` = 'H'"),0);

	$percent_update = $total_trans_hash / transaction_cycle(0, TRUE) * 100;

	if($percent_update == 100)
	{
		$status = '<font color="#818181"><strong>100%</strong></font>';
	}
	else if($percent_update < 100 && $percent_update >= 98)
	{
		$status = '<font color="#5858FA"><strong>' . number_format($percent_update, 3) . '%</font> (' . number_format(transaction_cycle(0, TRUE) - $total_trans_hash) . ' Transaction Cycles to Update)</strong>';
	}
	else
	{
		$status = '<font color="red"><strong>' . number_format($percent_update, 2) . '%</font> (' . number_format(transaction_cycle(0, TRUE) - $total_trans_hash) . ' Transaction Cycles to Update)</strong>';
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
		$confirm_message = '<strong><font color="red">Generating New Keys will delete the old keys in the database.</font></br>Be sure to make backups if you intend on keeping any balance associated with the current keys.</br><font color="blue">Continue?</font></strong>';
		$form_action = '<FORM ACTION="index.php?menu=options&newkeys=confirm" METHOD="post">';
	}
	else
	{
		$form_action = '<FORM ACTION="index.php?menu=options&newkeys=generate" METHOD="post">';
	}
	
return '<FORM ACTION="index.php?menu=options&password=change" METHOD="post">
<table border="0"><tr><td style="width:325px" valign="bottom" align="right">
Current Username: <input type="text" name="current_username" /></br>
New Username: <input type="text" name="new_username" /></br>
Confirm Username: <input type="text" name="confirm_username" />
</td></tr>
<tr><td></td></tr>
<tr><td align="right">
Current Password: <input type="password" name="current_password" /></br>
New Password: <input type="password" name="new_password" /></br>
Confirm Password: <input type="password" name="confirm_password" /></br></br>
<input type="submit" name="Submit" value="Change" />
</FORM></td></tr></table>
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
$php_location = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'php_location' LIMIT 1"),0,"field_data");
$super_peer = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'super_peer' LIMIT 1"),0,"field_data");
$peer_failure_grade = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'peer_failure_grade' LIMIT 1"),0,"field_data");

if($super_peer == 1)
{
	$super_peer = 500;
}

return '<table border="0"><tr><td><strong>Refresh Rates (seconds) for Realtime Pages [0 = disable]</strong></br></br><FORM ACTION="index.php?menu=options&refresh=change" METHOD="post"></td></tr>
<tr><td style="width:415px" valign="bottom" align="right">
Home: <input type="text" name="home_update" size="2" value="' . $home_update . '" /></br>
Peerlist: <input type="text" name="peerlist_update" size="2" value="' . $peerlist_update . '" /></br>
Transaction Queue: <input type="text" name="queue_update" size="2" value="' . $queue_update . '" /></td></tr>
<tr><td></td></tr>
<tr><td align="right"><strong>Super Peer Limit (10 - 500)</strong></br><input type="text" name="super_peer_limit" size="3" value="' . $super_peer . '" /></br></td></tr>
<tr><td></td></tr>
<tr><td align="right"><strong>Peer Failure Limit (1 - 100)</strong></br><input type="text" name="peer_failure_grade" size="3" value="' . $peer_failure_grade . '" /></br></td>
<tr><td align="right"><input type="submit" name="Submit2" value="Save Options" /></FORM></td></tr>
<tr><td><hr></hr></td></tr>
<tr><td align="right"><FORM ACTION="index.php?menu=options&hashcode=manage" METHOD="post"><input type="submit" name="Submit3" value="Manage Hash Code Access" /></FORM></td>
</td><td style="width:215px" valign="bottom" align="right"><FORM ACTION="index.php?menu=options&upgrade=check" METHOD="post"><input type="submit" name="Submit3" value="Check for Updates" /></FORM></td></tr>
</table>
<table border="0"><tr><td colspan="2" style="width:630px"><hr></hr></td></tr>
<tr><td><FORM ACTION="index.php?menu=options&find=edit_php" METHOD="post">
<strong>PHP File Path:</strong> <input type="text" size="40" name="php_file_path" value="' . $php_location . '" /><input type="submit" name="edit_php_location" value="Change" /></FORM></td>
<td><FORM ACTION="index.php?menu=options&find=php" METHOD="post"><input type="submit" name="find_php_location" value="Find PHP" /></FORM></td></tr>
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
function system_screen()
{
$max = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,"field_data");
$new = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,"field_data");
$domain = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_domain' LIMIT 1"),0,"field_data");
$subfolder = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_subfolder' LIMIT 1"),0,"field_data");
$port = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,"field_data");
$gen_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'generating_peers_hash' LIMIT 1"),0,"field_data");
$trans_history_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_history_hash' LIMIT 1"),0,"field_data");
$trans_queue_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,"field_data");
$block_check_start = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data");
$uptime = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'timekoin_start_time' LIMIT 1"),0,"field_data");
$request_max = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_request_max' LIMIT 1"),0,"field_data");
$allow_lan_peers = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'allow_LAN_peers' LIMIT 1"),0,"field_data"));
$allow_ambient_peer_restart = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'allow_ambient_peer_restart' LIMIT 1"),0,"field_data"));
$trans_history_check = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'trans_history_check' LIMIT 1"),0,"field_data"));
$gen_list_no_sync = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_peer_list_no_sync' LIMIT 1"),0,"field_data");
$super_peer_mode = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'super_peer' LIMIT 1"),0,"field_data");
$perm_peer_priority = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'perm_peer_priority' LIMIT 1"),0,"field_data");
$auto_update_generation_IP = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'auto_update_generation_IP' LIMIT 1"),0,"field_data"));

if($auto_update_generation_IP == 1)
{
	$auto_update_generation_IP_1 = "CHECKED";
}
else
{
	$auto_update_generation_IP_0 = "CHECKED";
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
	$perm_peer_priority_1 = "CHECKED";
}
else
{
	$perm_peer_priority_0 = "CHECKED";
}

if($super_peer_mode >= 1)
{
	$super_peer_check_1 = "CHECKED";
}
else
{
	$super_peer_check_0 = "CHECKED";
}

if($allow_lan_peers == 1)
{
	$LAN_enable = "CHECKED";
}
else
{
	$LAN_disable = "CHECKED";
}

if($allow_ambient_peer_restart == 1)
{
	$ambient_restart_enable = "CHECKED";
}
else
{
	$ambient_restart_disable = "CHECKED";
}

if($trans_history_check == 2)
{
	$trans_history_check_2 = "CHECKED";
}
else if($trans_history_check == 1)
{
	$trans_history_check_1 = "CHECKED";
}
else
{
	$trans_history_check_0 = "CHECKED";
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
$total_trans_hash = mysql_result(mysql_query("SELECT COUNT(attribute) FROM `transaction_history` WHERE `attribute` = 'H'"),0);

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

return '<FORM ACTION="index.php?menu=system&peer_settings=change" METHOD="post">
<table border="0"><tr><td align="right">
Maximum Active Peers: <input type="text" name="max_peers" size="3" value="' . $max . '"/></br>
Maximum Reserve Peers: <input type="text" name="max_new_peers" size="3" value="' . $new . '"/></br>
</td><td align="right">
<input type="submit" name="Submit1" value="Change Peer Settings" />
</FORM>
</td></tr>
</table>
<hr></hr>
<FORM ACTION="index.php?menu=system&server_settings=change" METHOD="post">
<table border="0"><tr><td align="right">
Server Domain: <input type="text" name="domain" size="25" maxlength="256" value="' . $domain . '"/></br>
Timekoin Subfolder: <input type="text" name="subfolder" size="25" maxlength="256" value="' . $subfolder . '"/></br>
Server Port Number: <input type="text" name="port" size="6" maxlength="5" value="' . $port . '"/></br>
Max Peer Query: <input type="text" name="max_request" size="6" maxlength="4" value="' . $request_max . '"/></br>
</br>Allow LAN Peers: <input type="radio" name="allow_LAN" value="0" ' . $LAN_disable . '>Disable <input type="radio" name="allow_LAN" value="1" ' . $LAN_enable . '>Enable
</br></br>Allow Ambient Peer Restarts: <input type="radio" name="allow_ambient" value="0" ' . $ambient_restart_disable . '>Disable <input type="radio" name="allow_ambient" value="1" ' . $ambient_restart_enable . '>Enable
</br></br>Super Peer: <input type="radio" name="super_peer" value="0" ' . $super_peer_check_0 . '>Disabled <input type="radio" name="super_peer" value="1" ' . $super_peer_check_1 . '> Enable
</br></br>Permanent Peer Priority: <input type="radio" name="perm_peer_priority" value="0" ' . $perm_peer_priority_0 . '>Disabled <input type="radio" name="perm_peer_priority" value="1" ' . $perm_peer_priority_1 . '> Enable
</br></br>Auto Generation IP Update: <input type="radio" name="auto_update_IP" value="0" ' . $auto_update_generation_IP_0 . '>Disabled <input type="radio" name="auto_update_IP" value="1" ' . $auto_update_generation_IP_1 . '> Enable
</br></br>Transaction History Checks: <input type="radio" name="trans_history_check" value="0" ' . $trans_history_check_0 . '>Rare <input type="radio" name="trans_history_check" value="1" ' . $trans_history_check_1 . '> Normal <input type="radio" name="trans_history_check" value="2" ' . $trans_history_check_2 . '>Frequent
</td><td align="right">
<input type="submit" name="Submit2" value="Change Server Settings" />
</FORM>
</td></tr>
</table>
<hr></hr>
<table border="0"><tr><td align="right">
<strong>Miscellaneous Server</strong></br></br>
Generating Peers List Hash:</br>
Transaction History Hash:</br>
Transaction Queue Hash:</br>
Transaction History Records:</br>
Transaction Cycles:</br>
Transaction Foundations:</br>
Uptime:</br>
Database Size:
</td><td align="left">
<strong>Information</br></br>
' . $gen_hash . '</br>
' . $trans_history_hash_color1 . $trans_history_hash .  $trans_history_hash_color2 . '</br>
' . $trans_queue_hash . '</br>
' . number_format($total_records) . '</br>
' . $total_trans_hash . ' of ' . number_format(transaction_cycle(0, TRUE)) . '</br>
' . $total_foundations . ' of ' . number_format(foundation_cycle(0, TRUE)) . '</br>
' . tk_time_convert(time() - $uptime) . '</br>
' . $db_size .
'</strong></td></tr></table><hr></hr>';
}
//***********************************************************
//***********************************************************
function system_service_bar()
{
return '<table cellspacing="10" border="0"><tr><td width="150"><FORM ACTION="main.php?action=begin_main" METHOD="post"><input type="submit" value="Start Timekoin"/></FORM></td>
	<td width="150"><FORM ACTION="index.php?menu=system&stop=main" METHOD="post"><input type="submit" value="Stop Timekoin"/></FORM></td></tr></table><hr></hr>
	<table cellspacing="10" border="0"><tr><td width="150"><FORM ACTION="watchdog.php?action=begin_watchdog" METHOD="post"><input type="submit" value="Start Watchdog"/></FORM></td>
	<td width="150"><FORM ACTION="index.php?menu=system&stop=watchdog" METHOD="post"><input type="submit" value="Stop Watchdog"/></FORM></td></tr></table>';
}
//***********************************************************
//***********************************************************
function generation_body($generate_currency)
{
	$return_html;
	if($generate_currency == "1")
	{
		$return_html = '<table border="0" cellspacing="10"><tr><td><FORM ACTION="index.php?menu=generation&generate=disable" METHOD="post"><input type="submit" value="Disable Generation"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&generate=showlist" METHOD="post"><input type="submit" value="Show Generation List"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&generate=showqueue" METHOD="post"><input type="submit" value="Show Election Queue List"/></FORM></td></tr></table>';
	}
	else
	{
		$return_html = '<table border="0"><tr><td><FORM ACTION="index.php?menu=generation&generate=enable" METHOD="post"><input type="submit" value="Enable Generation"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&generate=showlist" METHOD="post"><input type="submit" value="Show Generation List"/></FORM></td>
			<td><FORM ACTION="index.php?menu=generation&generate=showqueue" METHOD="post"><input type="submit" value="Show Election Queue List"/></FORM></td></tr></table>';
	}

	if($_GET["generate"] == "")
	{
		$return_html .= '<p><strong>How Generation Works</strong></br><ol>
		<li>The server must be accessible from the Internet and be able to accept and respond to HTTP requests on the port designated in the System tab. This allows peer servers to validate the existence of your server. You may test you router/firewall settings using the <a target="_blank" href="https://timekoin.com/utility/firewall.php"><font color="blue"><strong>Firewall Tool</strong></font></a>.  If your server fails this test, you must modify your router or firewall settings to allow inbound TCP connections on your chosen port.</li>
		<li>A single server key is chosen randomly for generation during an election cycle. Elections are pseudo-randomized. You may use the <a target="_blank" href="http://timekoin.com/test/eclock.php?max_cycles=288"><font color="blue"><strong>Election Calendar</strong></font></a> to see upcoming elections in the next 24 hours.</li>
		<li>Once elected, your server will create generation transactions during generation cycles. Generation cycles occur at pseudo-random times.  Use the <a target="_blank" href="http://timekoin.com/test/gclock.php?max_cycles=288"><font color="blue"><strong>Generation Calendar</strong></font></a> to see the upcoming generation cycles in the next 24 hours.</li>
		<li>The server may continue to generate currency as long as it stays online.  If the server does not generate currency for 2 hours, the network assumes it has gone offline and the server key will be removed from the Generating Peer List. Once the server comes back online, it will need to be re-elected before generation can begin again.</li>
		</ol></p>
		<p>
		<strong>Generation Amount Schedule</strong></br>
		The amount a server can generate is directly related to the length of time it has been online and generating currency in the Timekoin network.</br>
		<table border="0" cellpadding="2"><tr><td><I>Time Generating</I></td><td><I>Currency per Generation Cycle</I></td></tr>
		<tr><td>0 - 1 week</td><td><font color="green"><strong>1</font></strong></td></tr>
		<tr><td>1 - 2 weeks</td><td><font color="green"><strong>2</font></strong></td></tr>
		<tr><td>2 - 4 weeks</td><td><font color="green"><strong>3</font></strong></td></tr>
		<tr><td>4 - 8 weeks</td><td><font color="green"><strong>4</font></strong></td></tr>
		<tr><td>8 - 16 weeks</td><td><font color="green"><strong>5</font></strong></td></tr>
		<tr><td>16 - 32 weeks</td><td><font color="green"><strong>6</font></strong></td></tr>
		<tr><td>32 - 64 weeks</td><td><font color="green"><strong>7</font></strong></td></tr>
		<tr><td>64 - 128 weeks</td><td><font color="green"><strong>8</font></strong></td></tr>
		<tr><td>128 - 256 weeks</td><td><font color="green"><strong>9</font></strong></td></tr>
		<tr><td>256 or more weeks</td><td><font color="green"><strong>10</font></strong></td></tr>
		</table></p>';
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
		$form_action = '<FORM ACTION="index.php?menu=send&complete=send" METHOD="post">';
	}
	else
	{
		$cancel_button = '<FORM ACTION="index.php?menu=send&easykey=grab" METHOD="post"><input type="text" size="24" name="easy_key" value="' . $easy_key . '" /></br>
			<input type="submit" value="Easy Key" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&check=key" METHOD="post">';
	}

return '<strong><font color="blue">Public Key</font> to send transaction:</strong></br>' . $form_action . '<table border="0" cellpadding="6"><tr><td colspan="2">
<textarea name="send_public_key" rows="6" cols="75">' . $fill_in_key . '</textarea></td></tr>
<tr><td colspan="2"><strong>Message:</strong></br><input type="text" maxlength="64" size="64" value="' . $message . '" name="send_message" /></td></tr>
<tr><td width="320" valign="top"><strong>Amount:</strong> <input type="text" size="8" value="' . $amount . '" name="send_amount" />
<input type="submit" name="Submit1" value="Send Timekoins" /></FORM></td>
<td>' . $cancel_button  . '</td></tr>
<tr><td></td><td>Create Your Own Here:</br><a target="_blank" href="http://easy.timekoin.net/">easy.timekoin.net</a></td></tr></table>';
}
//***********************************************************
//***********************************************************
function tools_bar()
{
	$default_walk = foundation_cycle(0, TRUE) * 500;
	$default_check = transaction_cycle(0, TRUE) - 10;
	$default_current = transaction_cycle(0, TRUE);

	return '<table cellspacing="10" border="0"><tr><td><FORM ACTION="index.php?menu=tools&action=walk_history" METHOD="post"><input type="submit" value="History Walk"/></td>
		<td>Block#<input type="text" size="7" name="walk_history" value="' . $default_walk . '" /></td></FORM><td>|</br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=check_tables" METHOD="post"><input type="submit" value="Check DB"/></td></FORM></td><td>|</br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=optimize_tables" METHOD="post"><input type="submit" value="Optimize DB"/></td></FORM></td><td>|</br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=repair_tables" METHOD="post"><input type="submit" value="Repair DB"/></td></FORM>
		</tr></table><hr></hr>
		<table cellspacing="10" border="0"><tr><td><FORM ACTION="index.php?menu=tools&action=schedule_check" METHOD="post"><input type="submit" value="Schedule Check"/></td>
		<td>Block#<input type="text" size="7" name="schedule_check" value="' . $default_check . '" /></td></FORM><td>|</br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=repair" METHOD="post"><input type="submit" value="Repair"/></td>
		<td>From Block#<input type="text" size="7" name="repair_from" value="' . $default_check . '" /></td>
		</FORM></tr></table>';
}
//***********************************************************
//***********************************************************
function backup_body($private_key, $public_key, $cancel_private = FALSE, $cancel_public = FALSE)
{
	if($cancel_private == TRUE)
	{
		// Redo menu to show cancel or complete buttons
		$private_cancel_button = '<FORM ACTION="index.php?menu=backup" METHOD="post"><input type="submit" value="Cancel" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=backup&dorestore=private" METHOD="post">';
		$are_you_sure = '</br><font color="red"><strong>This will over-write the Private Key</br> for your server. Are you sure?</strong></font>';
	}
	else
	{
		$form_action = '<FORM ACTION="index.php?menu=backup&restore=private" METHOD="post">';
	}

	if($cancel_public == TRUE)
	{
		// Redo menu to show cancel or complete buttons
		$public_cancel_button = '<FORM ACTION="index.php?menu=backup" METHOD="post"><input type="submit" value="Cancel" /></FORM>';
		$form_action2 = '<FORM ACTION="index.php?menu=backup&dorestore=public" METHOD="post">';
		$are_you_sure2 = '</br><font color="red"><strong>This will over-write the Public Key</br> for your server. Are you sure?</strong></font>';		
	}
	else
	{
		$form_action2 = '<FORM ACTION="index.php?menu=backup&restore=public" METHOD="post">';
	}

return '<table border="0" cellpadding="6"><tr><td colspan="2"><strong><font color="blue">Restore Private Key</font></strong></td></tr>
			<tr><td colspan="2">' . $form_action . '<textarea name="restore_private_key" rows="5" cols="75">' . $private_key . '</textarea></td></tr>
			<tr><td><input type="submit" value="Restore Private Key"/></FORM>' . $are_you_sure . '</td><td align="left" valign="top">' . $private_cancel_button . '</td></tr>
			<tr><td colspan="2"><hr></hr></td></tr>
			<tr><td colspan="2"><strong><font color="green">Restore Public Key</font></strong></td></tr>
			<tr><td colspan="2">' . $form_action2 . '<textarea name="restore_public_key" rows="5" cols="75">' . $public_key . '</textarea></td></tr>
			<tr><td><input type="submit" value="Restore Public Key"/></FORM>' . $are_you_sure2 . '<td align="left" valign="top">' . $public_cancel_button . '</td></tr></table>';
}
//***********************************************************
//***********************************************************

?>
