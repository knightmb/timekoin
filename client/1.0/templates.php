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
<h1>Timekoin Client Billfold Login</h1>
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
<title>Timekoin Billfold Administration</title>
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
<li><a href="index.php?menu=send" <?PHP echo $send; ?>>Send</a></li>
<li><a href="index.php?menu=history" <?PHP echo $history; ?>>Transaction History</a></li>
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
<div id="footer"><p>Timekoin Crypto Currency Client v<?PHP echo TIMEKOIN_VERSION; ?> - <a href="http://timekoin.org">http://timekoin.org</a> &copy; 2010&mdash;<?PHP echo date('Y'); ?> - ( You are logged in as <strong><?PHP echo $_SESSION["login_username"]; ?></strong> )</p>
<IFRAME src="task.php?task=refresh" frameborder="0"></IFRAME></div>
</div>
</body>
</html>
<?PHP
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
<tr></tr>
<tr><td align="right">
Current Password: <input type="password" name="current_password" /></br>
New Password: <input type="password" name="new_password" /></br>
Confirm Password: <input type="password" name="confirm_password" /></br></br>
<input type="submit" name="Submit" value="Change" />
</FORM>
</td><td style="width:305px" valign="bottom" align="right">' . $confirm_message . $form_action .'
<input type="submit" name="Submit2" value="Generate New Keys" /></FORM></td></tr>
</table>';
} 
//***********************************************************
//***********************************************************
function options_screen2()
{
$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");

return '<table border="0"><tr><td><strong>Refresh Rates (seconds) [10 = default]</strong></br></br><FORM ACTION="index.php?menu=options&refresh=change" METHOD="post"></td></tr>
<tr><td style="width:415px" valign="bottom" align="right">
Refresh: <input type="text" name="home_update" size="2" value="' . $home_update . '" /></br></tr>
<tr><td align="right">
<input type="submit" name="Submit2" value="Save Options" />
</FORM>
</td><td style="width:215px" valign="bottom" align="right"><FORM ACTION="index.php?menu=options&upgrade=check" METHOD="post"><input type="submit" name="Submit3" value="Check for Updates" /></FORM></td></tr>
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
	return '<table cellspacing="10" border="0"><tr><td><FORM ACTION="index.php?menu=tools&action=check_tables" METHOD="post"><input type="submit" value="Check DB"/></td></FORM></td><td>|</br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=optimize_tables" METHOD="post"><input type="submit" value="Optimize DB"/></td></FORM></td><td>|</br>|</td>
		<td><FORM ACTION="index.php?menu=tools&action=repair_tables" METHOD="post"><input type="submit" value="Repair DB"/></td></FORM>
		</tr></table>';
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
		$are_you_sure2 = '</br><font color="red"><strong>This will over-write the Public Key</br> for your billfold. Are you sure?</strong></font>';		
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
