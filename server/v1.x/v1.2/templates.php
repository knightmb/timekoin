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
<li><a href="index.php?menu=tools" <?PHP echo $tools; ?>>Tools</a></li>
<li><a href="index.php?menu=logoff">Log Out</a></li>					 
</ul>
</div>
<div id="middle">
<div id="left-column">
<img src="img/timekoin_logo.png" width="150" height="150" alt="" />
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
function options_screen()
{
return '<FORM ACTION="index.php?menu=options&password=change" METHOD="post">
<table border="0"><tr><td align="right">
Current Username: <input type="text" name="current_username" /></br>
New Username: <input type="text" name="new_username" /></br>
Confirm Username: <input type="text" name="confirm_username" />
</td></tr>
<tr></tr>
<tr><td align="right">
Current Password: <input type="password" name="current_password" /></br>
New Password: <input type="password" name="new_password" /></br>
Confirm Password: <input type="password" name="confirm_password" /></br></br>
<input type="submit" name="Submit" value="Change" />
</FORM>
</td></tr>
</table>';
} 
//***********************************************************
//***********************************************************
function options_screen2()
{
$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");
$peerlist_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_peerlist' LIMIT 1"),0,"field_data");
$queue_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_queue' LIMIT 1"),0,"field_data");

return '<strong>Refresh Rates (seconds) for Realtime Pages [0 = disable]</strong></br></br><FORM ACTION="index.php?menu=options&refresh=change" METHOD="post">
<table border="0"><tr><td align="right">
Home: <input type="text" name="home_update" size="2" value="' . $home_update . '" /></br>
Peerlist: <input type="text" name="peerlist_update" size="2" value="' . $peerlist_update . '" /></br>
Transaction Queue: <input type="text" name="queue_update" size="2" value="' . $queue_update . '" /></br>
</td></tr>
<tr></tr>
<tr><td align="right">
<input type="submit" name="Submit2" value="Save" />
</FORM>
</td></tr>
</table>';
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
$block_check_start = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'block_check_start' LIMIT 1"),0,"field_data");
$uptime = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'timekoin_start_time' LIMIT 1"),0,"field_data");

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
$total_records = mysql_query("SELECT COUNT(*) FROM `transaction_history`");
$total_records = mysql_fetch_array($total_records); 
$total_records = $total_records[0];

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
Foundation Cycles:</br>
Uptime:
</td><td align="left">
<strong>Information</br></br>
' . $gen_hash . '</br>
' . $trans_history_hash_color1 . $trans_history_hash .  $trans_history_hash_color2 . '</br>
' . $trans_queue_hash . '</br>
' . number_format($total_records) . '</br>
' . transaction_cycle(0, TRUE) . '</br>
' . foundation_cycle(0, TRUE) . '</br>
' . tk_time_convert(time() - $uptime) . 
'</strong></td></tr></table><hr></hr>';
}
//***********************************************************
//***********************************************************
function system_service_bar()
{
	if(getenv("OS") == "Windows_NT")
	{
		// Microsoft Windows won't redirect for some reason, so the services can start, but there is no confirmation
		// to the user, so this kludge was created just for Windows users ;)
		return '<table cellspacing="10" border="0"><tr><td width="150"><FORM ACTION="index.php?menu=system&action=begin_main" METHOD="post"><input type="submit" value="Start Timekoin"/></FORM></td>
			<td width="150"><FORM ACTION="index.php?menu=system&stop=main" METHOD="post"><input type="submit" value="Stop Timekoin"/></FORM></td>
			<td><FORM ACTION="index.php?menu=system&stop=emergency" METHOD="post"><input type="submit" value="Emergency Stop"/></FORM></td></tr></table><hr></hr>
			<table cellspacing="10" border="0"><tr><td width="150"><FORM ACTION="index.php?menu=system&action=begin_watchdog" METHOD="post"><input type="submit" value="Start Watchdog"/></FORM></td>
			<td width="150"><FORM ACTION="index.php?menu=system&stop=watchdog" METHOD="post"><input type="submit" value="Stop Watchdog"/></FORM></td></tr></table>';
	}
	else
	{
		return '<table cellspacing="10" border="0"><tr><td width="150"><FORM ACTION="main.php?action=begin_main" METHOD="post"><input type="submit" value="Start Timekoin"/></FORM></td>
			<td width="150"><FORM ACTION="index.php?menu=system&stop=main" METHOD="post"><input type="submit" value="Stop Timekoin"/></FORM></td>
			<td><FORM ACTION="index.php?menu=system&stop=emergency" METHOD="post"><input type="submit" value="Emergency Stop"/></FORM></td></tr></table><hr></hr>
			<table cellspacing="10" border="0"><tr><td width="150"><FORM ACTION="watchdog.php?action=begin_watchdog" METHOD="post"><input type="submit" value="Start Watchdog"/></FORM></td>
			<td width="150"><FORM ACTION="index.php?menu=system&stop=watchdog" METHOD="post"><input type="submit" value="Stop Watchdog"/></FORM></td></tr></table>';
	}	
}
//***********************************************************
//***********************************************************
function generation_body($generate_currency)
{
	if($generate_currency == "1")
	{
		return '<table border="0"><tr><FORM ACTION="index.php?menu=generation&generate=disable" METHOD="post"><input type="submit" value="Disable Generation"/></FORM></td></tr></table>';
	}
	else
	{
		return '<table border="0"><tr><FORM ACTION="index.php?menu=generation&generate=enable" METHOD="post"><input type="submit" value="Enable Generation"/></FORM></td></tr></table>';
	}
}
//***********************************************************
//***********************************************************
function send_receive_body($fill_in_key, $amount, $cancel = FALSE)
{
	if($cancel == TRUE)
	{
		// Redo menu to show cancel or complete send buttons
		$cancel_button = '<FORM ACTION="index.php?menu=send" METHOD="post"><input type="submit" name="Submit2" value="Cancel" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&complete=send" METHOD="post">';
	}
	else
	{
		$form_action = '<FORM ACTION="index.php?menu=send&check=key" METHOD="post">';
	}

return '<strong><font color="blue">Public Key</font> to send transaction:</strong></br>' . $form_action . '<table border="0" cellpadding="6"><tr><td colspan="2">
<textarea name="send_public_key" rows="6" cols="75">' . $fill_in_key . '</textarea></td></tr><tr></td>
<tr><td width="320">
Amount: <input type="text" size="8" value="' . $amount . '" name="send_amount" />
<input type="submit" name="Submit1" value="Send Timekoins" /></FORM></td>
<td>' . $cancel_button  . '</td></tr></table>';
}
//***********************************************************
//***********************************************************
function tools_bar()
{
	$default_walk = foundation_cycle(0, TRUE) * 500;
	$default_check = transaction_cycle(0, TRUE) - 10;
	$default_current = transaction_cycle(0, TRUE);

	return '<table cellspacing="10" border="0"><tr><td><FORM ACTION="index.php?menu=tools&action=walk_history" METHOD="post"><input type="submit" value="History Walk"/></td>
		<td>Block#<input type="text" size="7" name="walk_history" value="' . $default_walk . '" /></td></FORM></tr></table><hr></hr>
		<table cellspacing="10" border="0"><tr><td><FORM ACTION="index.php?menu=tools&action=schedule_check" METHOD="post"><input type="submit" value="Schedule Check"/></td>
		<td>Block#<input type="text" size="7" name="schedule_check" value="' . $default_check . '" /></td></FORM><td>|</br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=repair" METHOD="post"><input type="submit" value="Repair"/></td>
		<td>From Block#<input type="text" size="7" name="repair_from" value="' . $default_check . '" /></td>
		</FORM></tr></table>';
}
//***********************************************************
//***********************************************************

?>
