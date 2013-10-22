<?PHP
//***********************************************************
//***********************************************************
function login_screen($error_message)
{

?>
<!DOCTYPE html>
<html>
<head>
<title>Timekoin Client Billfold</title>
<link rel="icon" type="image/x-icon" href="img/favicon.ico" />
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<link  href="css/admin.css" rel="stylesheet" type="text/css" />
<script language="JavaScript" type="text/javascript">
<!--
function breakout_of_frame()
{
	if (top.location != location)
	{
		top.location.href = document.location.href ;
	}
}
-->
</script>
</head>
<body onload="breakout_of_frame()">
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
	$address;
	$peerlist;
	$refresh_header;
	$send;
	$history;
	$queue;
	$script_headers;
	$balance_history;
	$counter;
	$amount;
	$graph_data_range_sent;
	$graph_data_range_recv;
	$graph_data_trans_total;
	$graph_data_amount_total;	
	$largest_sent = 10;
	$largest_recv = 10;
	$last = 20;
	$cache_refresh_time = 60;

	if($refresh != 0)
	{
		$refresh_header = '<meta http-equiv="refresh" content="' . $refresh . '" />';
	}

	switch($_GET["menu"])
	{
		case "home":
			$home = 'class="active"';
			$graph_data_range_recv = mysql_result(mysql_query("SELECT * FROM `data_cache` WHERE `field_name` = 'graph_data_range_recv' LIMIT 1"),0,"field_data");
			$timestamp_cache = intval(find_string("---time=", "---max", $graph_data_range_recv));

			if(time() - $cache_refresh_time > $timestamp_cache) // Cache TTL
			{
				// Old data needs to be refreshed
				$history_data_to = transaction_history_query(1, $last);
				$counter = 1;
				$graph_data_range_recv = NULL;
				while($counter <= $last) // History Limit
				{
					$amount = find_string("---AMOUNT$counter=", "---VERIFY", $history_data_to);					

					if($amount == "")
					{
						// No more data to search
						break;
					}

					$counter++;
					if($counter <= $last + 1)
					{
						if($amount > $largest_recv)
						{
							$largest_recv = $amount + 2;
						}
						$graph_data_range_recv .= ",$amount";
					}
				}
				// Update data cache
				if(empty($graph_data_range_recv) == FALSE)
				{
					mysql_query("UPDATE `data_cache` SET `field_data` = '---time=" . time() . "---max=$largest_recv---data=$graph_data_range_recv---end' WHERE `data_cache`.`field_name` = 'graph_data_range_recv' LIMIT 1");
				}
			}
			else
			{
				// Use cached data
				$largest_recv = find_string("---max=", "---data", $graph_data_range_recv);
				$graph_data_range_recv = find_string("---data=", "---end", $graph_data_range_recv);
			}

			$graph_data_range_sent = mysql_result(mysql_query("SELECT * FROM `data_cache` WHERE `field_name` = 'graph_data_range_sent' LIMIT 1"),0,"field_data");
			$timestamp_cache = intval(find_string("---time=", "---max", $graph_data_range_sent));

			if(time() - $cache_refresh_time > $timestamp_cache)// Cache TTL
			{
				// Old data needs to be refreshed
				$history_data_to = transaction_history_query(2, $last);
				$counter = 1;			
				$graph_data_range_sent = NULL;
				while($counter <= $last) // History Limit
				{
					$amount = find_string("---AMOUNT$counter=", "---VERIFY", $history_data_to);					

					if($amount == "")
					{
						// No more data to search
						break;
					}
					
					$counter++;
					if($counter <= $last + 1)
					{
						if($amount > $largest_sent)
						{
							$largest_sent = $amount + 2;
						}

						$graph_data_range_sent .= ",$amount";
					}
				}
				// Update data cache
				if(empty($graph_data_range_sent) == FALSE)
				{
					mysql_query("UPDATE `data_cache` SET `field_data` = '---time=" . time() . "---max=$largest_sent---data=$graph_data_range_sent---end' WHERE `data_cache`.`field_name` = 'graph_data_range_sent' LIMIT 1");
				}
			}
			else
			{
				// Use cached data
				$largest_sent = find_string("---max=", "---data", $graph_data_range_sent);
				$graph_data_range_sent = find_string("---data=", "---end", $graph_data_range_sent);
			}

			$graph_data_trans_total = mysql_result(mysql_query("SELECT * FROM `data_cache` WHERE `field_name` = 'graph_data_trans_total' LIMIT 1"),0,"field_data");
			$graph_data_amount_total = mysql_result(mysql_query("SELECT * FROM `data_cache` WHERE `field_name` = 'graph_data_amount_total' LIMIT 1"),0,"field_data");			
		
			$timestamp_cache = intval(find_string("---time=", "---max", $graph_data_trans_total));

			if(time() - $cache_refresh_time > $timestamp_cache)// Cache TTL
			{
				// Old data needs to be refreshed
				$total_trans_last = 25;
				$total_network_amounts_last = 20;
				$tk_trans_total_data = tk_trans_total($total_trans_last);
				$counter = 1;			
				$graph_data_trans_total = NULL;
				$graph_data_amount_total = NULL;
				$max_amount = 10;
				$max_transactions = 10;

				while($counter <= $total_trans_last) // History Limit
				{
					$timestamp = find_string("---TIMESTAMP$counter=", "---NUM$counter", $tk_trans_total_data);
					$total_transactions = find_string("---NUM$counter=", "---AMOUNT$counter", $tk_trans_total_data);
					$total_amount = find_string("---AMOUNT$counter=", "---END$counter", $tk_trans_total_data);

					if(empty($timestamp) == TRUE)
					{
						// No more data to search
						break;
					}

					$counter++;
					if($counter <= $total_trans_last + 1)
					{
						if($total_transactions > $max_transactions)
						{
							$max_transactions = $total_transactions + 2;
						}

						$graph_data_trans_total .= ",$total_transactions";
					}

					if($counter <= $total_network_amounts_last + 1)
					{
						if($total_amount > $max_amount)
						{
							$max_amount = $total_amount + 2;
						}						
						
						$graph_data_amount_total .= ",$total_amount";
					}

				}
				// Update data cache
				if(empty($graph_data_trans_total) == FALSE && empty($graph_data_amount_total) == FALSE)
				{
					mysql_query("UPDATE `data_cache` SET `field_data` = '---time=" . time() . "---max=$max_transactions---data=$graph_data_trans_total---end' WHERE `data_cache`.`field_name` = 'graph_data_trans_total' LIMIT 1");
					mysql_query("UPDATE `data_cache` SET `field_data` = '---time=" . time() . "---max=$max_amount---data=$graph_data_amount_total---end' WHERE `data_cache`.`field_name` = 'graph_data_amount_total' LIMIT 1");
				}
			}
			else
			{
				// Use cached data
				$max_transactions = find_string("---max=", "---data", $graph_data_trans_total);
				$graph_data_trans_total = find_string("---data=", "---end", $graph_data_trans_total);

				$max_amount = find_string("---max=", "---data", $graph_data_amount_total);
				$graph_data_amount_total = find_string("---data=", "---end", $graph_data_amount_total);				
			}

			// Cap largest chart values
			if($largest_recv > 5999) { $largest_recv = 5999; }
			if($largest_sent > 5999) { $largest_sent = 5999; }
			if($max_transactions > 999) { $max_transactions = 999; }
			if($max_amount > 7999) { $max_amount = 7999; }

			// Cap ranges
			$largest_recv_grid = $largest_recv * 0.1;
			if($largest_recv_grid > 300) { $largest_recv_grid = 300; }

			$largest_sent_grid = $largest_sent * 0.1;
			if($largest_sent_grid > 300) { $largest_sent_grid = 300; }

			$max_transactions_grid = $max_transactions * 0.12;

			$max_amount_grid = $max_amount * 0.075;
			if($max_amount_grid > 300) { $max_amount_grid = 300; }

			$script_headers = '<script type="text/javascript" src="js/tkgraph.js"></script>
<script type="text/javascript">
window.onload = function() {
	
g_graph = new Graph(
{
\'id\': "recv_graph",
\'strokeStyle\': "#FFA500",
\'fillStyle\': "rgba(0,127,0,0.20)",
\'grid\': [' . $largest_recv_grid . ',10],
\'range\': [0,' . $largest_recv . '],
\'data\': [' . $graph_data_range_recv . ']
});

g_graph = new Graph(
{
\'id\': "sent_graph",
\'strokeStyle\': "#FFA500",
\'fillStyle\': "rgba(0,0,255,0.20)",
\'grid\': [' . $largest_sent_grid . ',10],
\'range\': [0,' . $largest_sent . '],
\'data\': [' . $graph_data_range_sent . ']
});

g_graph = new Graph(
{
\'id\': "trans_total",
\'strokeStyle\': "#FFA500",
\'fillStyle\': "rgba(187,217,238,0.65)",
\'grid\': [' . $max_transactions_grid . ',10],
\'range\': [0,' . $max_transactions . '],
\'data\': [' . $graph_data_trans_total . ']
});

g_graph = new Graph(
{
\'id\': "amount_total",
\'strokeStyle\': "#FFA500",
\'fillStyle\': "rgba(130,127,0,0.20)",
\'grid\': [' . $max_amount_grid . ',10],
\'range\': [0,' . $max_amount . '],
\'data\': [' . $graph_data_amount_total . ']
});

}
</script>';
			break;

		case "address":
			$address = 'class="active"';
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
<title>Timekoin Client Billfold</title>
<link rel="icon" type="image/x-icon" href="img/favicon.ico" />
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<link  href="css/admin.css" rel="stylesheet" type="text/css" />
<?PHP echo $refresh_header; ?>
<?PHP echo $script_headers; ?>
</head>
<body>
<div id="main">
<div id="header">
<ul id="top-navigation">
<li><a href="index.php?menu=home" <?PHP echo $home; ?>>Home</a></li>
<li><a href="index.php?menu=address" <?PHP echo $address; ?>>Address Book</a></li>
<li><a href="index.php?menu=peerlist" <?PHP echo $peerlist; ?>>Peerlist</a></li>
<li><a href="index.php?menu=queue" <?PHP echo $queue; ?>>Queue</a></li>
<li><a href="index.php?menu=send" <?PHP echo $send; ?>>Send</a></li>
<li><a href="index.php?menu=history" <?PHP echo $history; ?>>History</a></li>
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
 </div>
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
	$max_active_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,"field_data");
	$max_new_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,"field_data");

	return '<table border="0"><FORM ACTION="index.php?menu=options&refresh=change" METHOD="post">
		<tr><td><strong>Home, Peerlist, & Queue Refresh Rate</strong> [Default 10]</td></tr>
		<tr><td style="width:415px" valign="bottom" align="right">Seconds: <input type="text" name="home_update" size="2" value="' . $home_update . '" /></td></tr>
		<tr><td></td></tr>
<tr><td align="right">
<strong>Maximum Active Peers</strong> [Default 5]: <input type="text" name="max_peers" size="3" value="' . $max_active_peers . '"/></br>
<strong>Maximum Reserve Peers</strong> [Default 10]: <input type="text" name="max_new_peers" size="3" value="' . $max_new_peers . '"/></br>
</td>
<tr><td align="right"><input type="submit" name="Submit2" value="Save Options" /></FORM>
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
function send_receive_body($fill_in_key, $amount, $cancel = FALSE, $easy_key, $message, $name)
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
			<input type="submit" value="Find Easy Key" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&check=key" METHOD="post">';
	}

	if(empty($name) == FALSE)
	{
		// Fill in address book name
		$hidden_name = $name;
		$name = ' to <font color="blue">' . $name . '</font>';
	}

return '<strong><font color="blue">Public Key</font> to send transaction' . $name . ':</strong></br>' . $form_action . '<table border="0" cellpadding="6"><tr><td colspan="2">
<textarea name="send_public_key" rows="6" cols="75">' . $fill_in_key . '</textarea></td></tr>
<tr><td colspan="2"><strong>Message:</strong></br><input type="text" maxlength="64" size="64" value="' . $message . '" name="send_message" /></td></tr>
<tr><td width="320" valign="top"><strong>Amount:</strong> <input type="text" size="8" value="' . $amount . '" name="send_amount" />
<input type="hidden" name="name" value="' . $hidden_name . '">
<input type="submit" name="Submit1" value="Send Timekoins" /></FORM></td>
<td>' . $cancel_button  . '</td></tr>
<tr><td></td><td>Create Your Own Here:</br><a target="_blank" href="http://easy.timekoin.net/">easy.timekoin.net</a></td></tr></table>';
}
//***********************************************************
//***********************************************************
function tools_bar()
{
	return '<table cellspacing="10" border="0">
	<tr><td><FORM ACTION="index.php?menu=tools&action=check_tables" METHOD="post"><input type="submit" value="Check DB"/></FORM></td><td>|</br>|</td>
	<td><FORM ACTION="index.php?menu=tools&action=optimize_tables" METHOD="post"><input type="submit" value="Optimize DB"/></FORM></td><td>|</br>|</td>
	<td><FORM ACTION="index.php?menu=tools&action=repair_tables" METHOD="post"><input type="submit" value="Repair DB"/></FORM></td></tr></table>';
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
