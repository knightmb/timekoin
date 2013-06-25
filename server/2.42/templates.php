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
	<link href="css/bootstrap.css" rel="stylesheet" type="text/css" />
	<link href="css/admin.css" rel="stylesheet" type="text/css" />
</head>
<body>
	<div class="container">
		<h2>Timekoin Server Login</h2>
		<section id="content">
			<FORM ACTION="index.php?action=login" METHOD="post">
				<p><label>Username:</label> <input type="text" name="timekoin_username" /></p>
				<p><label>Password:</label> <input type="password" name="timekoin_password" /></p>
				<p><input type="submit" class="btn btn-primary" name="Submit" value="Login" /></p>
			</FORM>
			<font color="red"><strong><?PHP echo $error_message; ?></strong></font>
		</section>
	</div>
	<footer>
		<p>
			Timekoin Crypto Currency - 
			<a href="http://timekoin.org">http://timekoin.org</a> &copy; 2010 &mdash; <?PHP echo date('Y'); ?>
		</p>
	</footer>
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
	<link href="css/bootstrap.css" rel="stylesheet" type="text/css" />
	<link href="css/admin.css" rel="stylesheet" type="text/css" />
	<?PHP echo $refresh_header; ?>
</head>
<body>
	<nav>
		<ul class="nav nav-tabs">
			<li <?PHP echo $home; ?>><a href="index.php?menu=home">Home</a></li>
			<li <?PHP echo $peerlist; ?>><a href="index.php?menu=peerlist">Peerlist</a></li>
			<li <?PHP echo $queue; ?>><a href="index.php?menu=queue">Transaction Queue</a></li>
			<li <?PHP echo $send; ?>><a href="index.php?menu=send">Send / Receive</a></li>
			<li <?PHP echo $history; ?>><a href="index.php?menu=history">History</a></li>
			<li <?PHP echo $generation; ?>><a href="index.php?menu=generation">Generation</a></li>
			<li <?PHP echo $system; ?>><a href="index.php?menu=system">System</a></li>
			<li <?PHP echo $options; ?>><a href="index.php?menu=options">Options</a></li>
			<li <?PHP echo $backup; ?>><a href="index.php?menu=backup">Backup</a></li>
			<li <?PHP echo $tools; ?>><a href="index.php?menu=tools">Tools</a></li>
			<li><a href="index.php?menu=logoff">Log Out</a></li>					 
		</ul>
	</nav>
	<div class="container">
		<h2><?PHP echo $contents; ?></h2>
		<section class="panel">
			<?PHP echo $select_bar; ?>
		</section>
		<section id="content">
			<?PHP echo $body; ?>
		</section>
		<section id="info">
			<h3>Quick Info</h3>
			<p><?PHP echo $quick_info; ?></p>
		</section>
	</div>
	<footer>
		<p>
			Timekoin Crypto Currency v<?PHP echo TIMEKOIN_VERSION; ?> - 
			<a href="http://timekoin.org">http://timekoin.org</a> &copy; 2010 &mdash; <?PHP echo date('Y'); ?> - 
			( You are logged in as <strong><?PHP echo $_SESSION["login_username"]; ?></strong> )
		</p>
	</footer>
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
		$confirm_message = '<strong><font color="red">Generating New Keys will delete the old keys in the database.</font><br>Be sure to make backups if you intend on keeping any balance associated with the current keys.<br><font color="blue">Continue?</font></strong>';
		$form_action = '<FORM ACTION="index.php?menu=options&newkeys=confirm" METHOD="post">';
	}
	else
	{
		$form_action = '<FORM ACTION="index.php?menu=options&newkeys=generate" METHOD="post">';
	}
	
return '
<div class="row">
	<FORM ACTION="index.php?menu=options&password=change" METHOD="post">
		<div class="span6">
			<p>Current Username: <input type="text" name="current_username" /></p>
			<p>New Username: <input type="text" name="new_username" /></p>
			<p>Confirm Username: <input type="text" name="confirm_username" /></p>
		</div>
		<div class="span6">
			<p>Current Password: <input type="password" name="current_password" /></p>
			<p>New Password: <input type="password" name="new_password" /></p>
			<p>Confirm Password: <input type="password" name="confirm_password" /></p>
		</div>
		<div class="span12">
			<p><input type="submit" class="btn btn-success" name="Submit" value="Save username and password" /></p>
		</div>
	</FORM>
	' . $confirm_message . $form_action .'
		<div class="span12">
			<p><input type="submit" class="btn btn-primary" name="Submit2" value="Generate New Keys" /></p>
		</div>
	</FORM>
</div>


';
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

return '
<FORM ACTION="index.php?menu=options&refresh=change" METHOD="post">
<div class="row">
	<div class="span6">
		<h3>Refresh rates</h3>
	</div>
	<div class="span6">
		<h3>Peer limits</h3>
	</div>
	<div class="span2">
		<p>Home: <input type="text" name="home_update" size="2" value="' . $home_update . '" /></p>
	</div>
	<div class="span2">
		<p>Peerlist: <input type="text" name="peerlist_update" size="2" value="' . $peerlist_update . '" /></p>
	</div>
	<div class="span2">
		<p>Transaction Queue: <input type="text" name="queue_update" size="2" value="' . $queue_update . '" /></p>
	</div>
	<div class="span3">
		<p>Super Peer Limit (10 - 500): <input type="text" name="super_peer_limit" size="3" value="' . $super_peer . '" /></p>
	</div>
	<div class="span3">
		<p>Peer Failure Limit (1 - 100): <input type="text" name="peer_failure_grade" size="3" value="' . $peer_failure_grade . '" /></p>
	</div>
</div>
<p>Rates in <strong>seconds</strong> for Realtime Pages [0 = disable]</p>
<p><input type="submit" class="btn btn-success" name="Submit2" value="Save rates and limits" /></p>
</FORM>

<h3>Extra features</h3>
<div class="form-row">
	<FORM ACTION="index.php?menu=options&hashcode=manage" METHOD="post"><input type="submit" class="btn btn-primary" name="Submit3" value="Manage Hash Code Access" /></FORM>
	<FORM ACTION="index.php?menu=options&upgrade=check" METHOD="post"><input type="submit" class="btn btn-primary" name="Submit3" value="Check for Updates" /></FORM>
</div>

<h3>PHP location</h3>
<div class="row">
<FORM ACTION="index.php?menu=options&find=edit_php" METHOD="post">
	<div class="span4">
		<p><input type="text" class="btn-block" name="php_file_path" value="' . $php_location . '" /></p>
	</div>
	<div class="span4">
		<p><input type="submit" class="btn btn-success" name="edit_php_location" value="Update PHP location" /></p>
	</div>
</FORM>
	<div class="span4">
		<FORM ACTION="index.php?menu=options&find=php" METHOD="post"><p><input type="submit" class="btn btn-primary" name="find_php_location" value="Find PHP" /></p></FORM>
	</div>
</div>


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

return '

<h3>Peer settings</h3>
<FORM ACTION="index.php?menu=system&peer_settings=change" METHOD="post">
<div class="row">
	<div class="span4">
		<p><label>Maximum Active Peers:</label> <input type="text" name="max_peers" size="3" value="' . $max . '"/></p>
	</div>
	<div class="span8">
		<p><label>Maximum Reserve Peers:</label> <input type="text" name="max_new_peers" size="3" value="' . $new . '"/></p>
	</div>
	<div class="span12">
		<p><input type="submit" class="btn btn-success" name="Submit1" value="Update Peer settings" /></p>
	</div>
</div>
</FORM>

<h3>Server settings</h3>
<FORM ACTION="index.php?menu=system&server_settings=change" METHOD="post">
<div class="row">
	<div class="span4">
		<p><label>Server Domain</label> <input type="text" class="btn-block" name="domain" maxlength="256" value="' . $domain . '" placeholder="domain.org or ip address"/></p>
	</div>
	<div class="span4">
		<p><label>Timekoin Subfolder</label> <input type="text" class="btn-block" name="subfolder" maxlength="256" value="' . $subfolder . '" placeholder="timekoin (default)"/></p>
	</div>
	<div class="span2">
		<p><label>Server Port Number</label> <input type="text" class="btn-block" name="port" maxlength="5" value="' . $port . '" placeholder="1528 (default)"/></p>
	</div>
	<div class="span2">
		<p><label>Max Peer Query</label> <input type="text" class="btn-block" name="max_request" maxlength="4" value="' . $request_max . '" placeholder="200 (default)"/></p>
	</div>
	<div class="span4">
		<p><input type="checkbox" name="allow_LAN" value="0" ' . $LAN_enable . '> <label>Allow LAN Peers</label></p>
		<p><input type="checkbox" name="allow_ambient" value="0" ' . $ambient_restart_enable . '> <label>Allow Ambient Peer Restarts</label></p>
	</div>
	<div class="span4">
		<p><input type="checkbox" name="super_peer" value="0" ' . $super_peer_check_1 . '> <label>Enable Super Peer</label></p>
		<p><input type="checkbox" name="perm_peer_priority" value="0" ' . $perm_peer_priority_1 . '> <label>Enable Permanent Peer Priority</label></p>
	</div>
	<div class="span4">
		<p>Transaction History Checks:<br>
			<input type="radio" name="trans_history_check" value="0" ' . $trans_history_check_0 . '> <label>Rare</label><br>
			<input type="radio" name="trans_history_check" value="1" ' . $trans_history_check_1 . '> <label>Normal</label><br>
			<input type="radio" name="trans_history_check" value="2" ' . $trans_history_check_2 . '> <label>Frequent</label></p>
	</div>
</div>
<p><input type="submit" class="btn btn-success" name="Submit2" value="Change Server settings" /></p>
</FORM>

<h3>Server Info</h3>
<div class="row">
	<div class="span4">
		<p>
			Generating Peers List Hash:<br>
			Transaction History Hash:<br>
			Transaction Queue Hash:<br>
			Transaction History Records:<br>
			Transaction Cycles:<br>
			Transaction Foundations:<br>
			Uptime:<br>
			Database Size:
		</p>
	</div>
	<div class="span4">
		<p>
			' . $gen_hash . '<br>
			' . $trans_history_hash_color1 . $trans_history_hash . $trans_history_hash_color2 . '<br>
			' . $trans_queue_hash . '<br>
			' . number_format($total_records) . '<br>
			' . $total_trans_hash . ' of ' . number_format(transaction_cycle(0, TRUE)) . '<br>
			' . $total_foundations . ' of ' . number_format(foundation_cycle(0, TRUE)) . '<br>
			' . tk_time_convert(time() - $uptime) . '<br>
			' . $db_size . '
		</p>
	</div>
</div>';
}
//***********************************************************
//***********************************************************
function system_service_bar()
{
return '
<div class="row">
	<div class="span6">
		<h3>Watchdog</h3>
		<p>Restarts processes if not running.</p>
		<form action="watchdog.php?action=begin_watchdog" method="post">
			<p><input type="submit" class="btn btn-success" value="Start Watchdog"/></p>
		</form>
		<form action="index.php?menu=system&stop=watchdog" method="post">
			<p><input type="submit" class="btn btn-danger" value="Stop Watchdog"/></p>
		</form>
	</div>
	<div class="span6">
		<h3>Timekoin</h3>
		<p>Connect to nodes, verify transactions and update transaction history.</p>
		<form action="main.php?action=begin_main" method="post">
			<p><input type="submit" class="btn btn-success" value="Start Timekoin"/>
		</form>
		<form action="index.php?menu=system&stop=main" method="post">
			<p><input type="submit" class="btn btn-danger" value="Stop Timekoin"/>
		</form>
	</div>
</div>
';
}
//***********************************************************
//***********************************************************
function generation_body($generate_currency)
{
	if($generate_currency == "1")
	{
		return '
<div class="form-row">
	<FORM ACTION="index.php?menu=generation&generate=disable" METHOD="post"><input type="submit" class="btn btn-danger" value="Disable Generation"/></FORM>
	<FORM ACTION="index.php?menu=generation&generate=showlist" METHOD="post"><input type="submit" class="btn" value="Show Generation List"/></FORM>
	<FORM ACTION="index.php?menu=generation&generate=showqueue" METHOD="post"><input type="submit" class="btn" value="Show Election Queue List"/></FORM>
</div>';
	}
	else
	{
		return '<div class="form-row">
			<FORM ACTION="index.php?menu=generation&generate=enable" METHOD="post"><input type="submit" class="btn btn-success" value="Enable Generation"/></FORM>
			<FORM ACTION="index.php?menu=generation&generate=showlist" METHOD="post"><input type="submit" class="btn" value="Show Generation List"/></FORM>
			<FORM ACTION="index.php?menu=generation&generate=showqueue" METHOD="post"><input type="submit" class="btn" value="Show Election Queue List"/></FORM>
			</div>';
	}
}
//***********************************************************
//***********************************************************
function send_receive_body($fill_in_key, $amount, $cancel = FALSE, $easy_key, $message)
{
	if($cancel == TRUE)
	{
		// Redo menu to show cancel or complete send buttons
		$cancel_button = '<FORM ACTION="index.php?menu=send" METHOD="post"><input type="submit" class="btn btn-primary" name="Submit2" value="Cancel" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&complete=send" METHOD="post">';
	}
	else
	{
		$cancel_button = '<FORM ACTION="index.php?menu=send&easykey=grab" METHOD="post"><input type="text" size="24" name="easy_key" value="' . $easy_key . '" /><br>
			<input type="submit" class="btn btn-primary" value="Easy Key" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&check=key" METHOD="post">';
	}

return '<strong><font color="blue">Public Key</font> to send transaction:</strong><br>' . $form_action . '<table border="0" class="table"><tr><td colspan="2">
<textarea name="send_public_key" rows="6" cols="75">' . $fill_in_key . '</textarea></td></tr>
<tr><td colspan="2"><strong>Message:</strong><br><input type="text" maxlength="64" size="64" value="' . $message . '" name="send_message" /></td></tr>
<tr><td width="320" valign="top"><strong>Amount:</strong> <input type="text" size="8" value="' . $amount . '" name="send_amount" />
<input type="submit" class="btn btn-primary" name="Submit1" value="Send Timekoins" /></FORM></td>
<td>' . $cancel_button . '</td></tr>
<tr><td></td><td>Create Your Own Here:<br><a target="_blank" href="http://easy.timekoin.net/">easy.timekoin.net</a></td></tr></table>';
}
//***********************************************************
//***********************************************************
function tools_bar()
{
	$default_walk = foundation_cycle(0, TRUE) * 500;
	$default_check = transaction_cycle(0, TRUE) - 10;
	$default_current = transaction_cycle(0, TRUE);

	return '<table border="0" class="table"><tr><td><FORM ACTION="index.php?menu=tools&action=walk_history" METHOD="post"><input type="submit" class="btn btn-primary" value="History Walk"/></td>
		<td>Block#<input type="text" size="7" name="walk_history" value="' . $default_walk . '" /></td></FORM><td>|<br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=check_tables" METHOD="post"><input type="submit" class="btn btn-primary" value="Check DB"/></td></FORM></td><td>|<br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=optimize_tables" METHOD="post"><input type="submit" class="btn btn-primary" value="Optimize DB"/></td></FORM></td><td>|<br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=repair_tables" METHOD="post"><input type="submit" class="btn btn-primary" value="Repair DB"/></td></FORM>
		</tr></table><hr></hr>
		<table border="0" class="table"><tr><td><FORM ACTION="index.php?menu=tools&action=schedule_check" METHOD="post"><input type="submit" class="btn btn-primary" value="Schedule Check"/></td>
		<td>Block#<input type="text" size="7" name="schedule_check" value="' . $default_check . '" /></td></FORM><td>|<br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=repair" METHOD="post"><input type="submit" class="btn btn-primary" value="Repair"/></td>
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
		$private_cancel_button = '<FORM ACTION="index.php?menu=backup" METHOD="post"><input type="submit" class="btn btn-primary" value="Cancel" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=backup&dorestore=private" METHOD="post">';
		$are_you_sure = '<br><font color="red"><strong>This will over-write the Private Key<br> for your server. Are you sure?</strong></font>';
	}
	else
	{
		$form_action = '<FORM ACTION="index.php?menu=backup&restore=private" METHOD="post">';
	}

	if($cancel_public == TRUE)
	{
		// Redo menu to show cancel or complete buttons
		$public_cancel_button = '<FORM ACTION="index.php?menu=backup" METHOD="post"><input type="submit" class="btn btn-primary" value="Cancel" /></FORM>';
		$form_action2 = '<FORM ACTION="index.php?menu=backup&dorestore=public" METHOD="post">';
		$are_you_sure2 = '<br><font color="red"><strong>This will over-write the Public Key<br> for your server. Are you sure?</strong></font>';		
	}
	else
	{
		$form_action2 = '<FORM ACTION="index.php?menu=backup&restore=public" METHOD="post">';
	}

return '<table border="0" class="table"><tr><td colspan="2"><strong><font color="blue">Restore Private Key</font></strong></td></tr>
			<tr><td colspan="2">' . $form_action . '<textarea name="restore_private_key" rows="5" cols="75">' . $private_key . '</textarea></td></tr>
			<tr><td><input type="submit" class="btn btn-primary" value="Restore Private Key"/></FORM>' . $are_you_sure . '</td><td align="left" valign="top">' . $private_cancel_button . '</td></tr>
			<tr><td colspan="2"><hr></hr></td></tr>
			<tr><td colspan="2"><strong><font color="green">Restore Public Key</font></strong></td></tr>
			<tr><td colspan="2">' . $form_action2 . '<textarea name="restore_public_key" rows="5" cols="75">' . $public_key . '</textarea></td></tr>
			<tr><td><input type="submit" class="btn btn-primary" value="Restore Public Key"/></FORM>' . $are_you_sure2 . '<td align="left" valign="top">' . $public_cancel_button . '</td></tr></table>';
}
//***********************************************************
//***********************************************************

?>
