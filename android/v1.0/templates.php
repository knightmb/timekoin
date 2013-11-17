<?PHP
//***********************************************************
//***********************************************************
function login_screen($error_message)
{

?>
<!DOCTYPE html>
<html>
<head>
    <script type="text/javascript">
    function showWait(val){
    var oDiv = document.getElementById('overlay')
    oDiv.style.display='block'
    oDiv.style.opacity = 10;
    oDiv.style.filter = 'alpha(opacity=' + val*10 + ')';
    document.body.style.cursor = "wait";
    }
	 </script>
<title>Timekoin Android Billfold</title>
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
<div id='overlay' style="font-weight:900; background-color:powderblue; position:absolute; top:0; left:0; display:none">
&nbsp;Please Wait...&nbsp;</div>
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
<h1>Timekoin Android Billfold Login</h1>
</div>
<div class="select-bar">
<FORM ACTION="index.php?action=login" METHOD="post">
<table border="0"><tr><td align="right">
Username: <input type="text" name="timekoin_username" /><br>
Password: <input type="password" name="timekoin_password" />	
</td><td>
<input type="submit" name="Submit" value="Login" onclick="showWait()" /></td></tr></table>
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
	$cache_refresh_time = 60; // How many seconds for cache to remain valid before refresh is required

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
		$menu_output = '<li><a href="../index.php?menu=home" ' . $home . ' onclick="showWait()" >Home</a></li>';		
		if(check_standard_tab_settings($standard_settings_number, 16) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=address" ' . $address . ' onclick="showWait()" >Address Book</a></li>'; }		
		if(check_standard_tab_settings($standard_settings_number, 1) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=peerlist" ' . $peerlist . ' onclick="showWait()" >Peerlist</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 2) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=queue" ' . $queue . ' onclick="showWait()" >Queue</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 4) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=send" ' . $send . ' onclick="showWait()" >Send</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 8) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=history" ' . $history . '>History</a></li>'; }
		$menu_output .= '<li><a href="../index.php?menu=options" ' . $options . ' onclick="showWait()" >Options</a></li>';
		if(check_standard_tab_settings($standard_settings_number, 64) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=backup" ' . $backup . ' onclick="showWait()" >Backup</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 128) == TRUE) { $menu_output .= '<li><a href="../index.php?menu=tools" ' . $tools . ' onclick="showWait()" >Tools</a></li>'; }
		$menu_output .= $plugin_output;
		$menu_output .= '<li><a href="../index.php?menu=logoff" onclick="showWait()" >Log Out</a></li>';
	}
	else
	{
		$menu_output = '<li><a href="index.php?menu=home" ' . $home . ' onclick="showWait()" >Home</a></li>';		
		if(check_standard_tab_settings($standard_settings_number, 16) == TRUE) { $menu_output .= '<li><a href="index.php?menu=address" ' . $address . ' onclick="showWait()" >Address Book</a></li>'; }		
		if(check_standard_tab_settings($standard_settings_number, 1) == TRUE) { $menu_output .= '<li><a href="index.php?menu=peerlist" ' . $peerlist . ' onclick="showWait()" >Peerlist</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 2) == TRUE) { $menu_output .= '<li><a href="index.php?menu=queue" ' . $queue . ' onclick="showWait()" >Queue</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 4) == TRUE) { $menu_output .= '<li><a href="index.php?menu=send" ' . $send . ' onclick="showWait()" >Send</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 8) == TRUE) { $menu_output .= '<li><a href="index.php?menu=history" ' . $history . ' onclick="showWait()" >History</a></li>'; }
		$menu_output .= '<li><a href="index.php?menu=options" ' . $options . ' onclick="showWait()" >Options</a></li>';
		if(check_standard_tab_settings($standard_settings_number, 64) == TRUE) { $menu_output .= '<li><a href="index.php?menu=backup" ' . $backup . ' onclick="showWait()" >Backup</a></li>'; }
		if(check_standard_tab_settings($standard_settings_number, 128) == TRUE) { $menu_output .= '<li><a href="index.php?menu=tools" ' . $tools . ' onclick="showWait()" >Tools</a></li>'; }
		$menu_output .= $plugin_output;
		$menu_output .= '<li><a href="index.php?menu=logoff" onclick="showWait()" >Log Out</a></li>';
	}




?>
<!DOCTYPE html>
<html>
<head>
<script type="text/javascript">
    function showWait(val){
    var oDiv = document.getElementById('overlay')
    oDiv.style.display='block'
    oDiv.style.opacity = 10;
    oDiv.style.filter = 'alpha(opacity=' + val*10 + ')';
    document.body.style.cursor = "wait";
    }
</script>
<title>Timekoin Android Billfold</title>

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
<?PHP echo $script_headers; ?>
</head>
<body>
<div id='overlay' style="font-weight:900; background-color:powderblue; position:absolute; text-decoration:blink; top:0; left:0; display:none">
&nbsp;Please Wait...&nbsp;</div>
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
<div id="footer"><p>Timekoin Crypto Currency Android v<?PHP echo TIMEKOIN_VERSION; ?> - <a href="http://timekoin.org">http://timekoin.org</a> &copy; 2010&mdash;<?PHP echo date('Y'); ?> - ( You are logged in as <strong><?PHP echo $_SESSION["login_username"]; ?></strong> )</p>
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
	$private_key_crypt = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'private_key_crypt' LIMIT 1"),0,1);

	if($private_key_crypt == TRUE)
	{
		$disable_crypt_checkbox = '<input type="checkbox" name="disable_crypt" value="1">Remove Encryption<br><i>*Requires Current Password</i>';
	}
	
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
<table border="0"><tr><td style="width:330px" valign="bottom" align="right">
Current Username: <input type="text" name="current_username" /><br>
New Username: <input type="text" name="new_username" /><br>
Confirm Username: <input type="text" name="confirm_username" />
</td>
<td style="width:345px" valign="bottom" align="right"><strong>Encrypt Private Key</strong><br>
Current PK Password: <input type="password" name="current_private_key_password" /><br>
New PK Password: <input type="password" name="new_private_key_password" /><br>
Confirm PK Password: <input type="password" name="confirm_private_key_password" />
</td></tr>
<tr><td></td><td></td></tr>
<tr><td align="right">
Current Password: <input type="password" name="current_password" /><br>
New Password: <input type="password" name="new_password" /><br>
Confirm Password: <input type="password" name="confirm_password" /><br><br>
<input type="submit" onclick="showWait()" name="Submit" value="Change" />
</td><td align="right" valign="top">' . $disable_crypt_checkbox . '</td></tr></table></FORM>
<table border="0"><tr><td style="width:350px"></td>
<td valign="bottom" align="right" style="width:325px">' . $confirm_message . $form_action .'
<input type="submit" onclick="showWait()" name="Submit2" value="Generate New Keys" /></FORM></td></tr>
</table>';
} 
//***********************************************************
//***********************************************************
function options_screen2()
{
	$home_update = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");
	$max_active_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,"field_data");
	$max_new_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,"field_data");
	$default_timezone = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"),0,"field_data");

	if(empty($default_timezone) == FALSE)
	{
		$default_timezone = '<option value="' . $default_timezone . '">' . $default_timezone . '</option>';
	}

	return '<FORM ACTION="index.php?menu=options&amp;refresh=change" METHOD="post"><table border="0">
<tr><td style="width:500px" valign="bottom" align="right"><strong>Home, Peerlist, &amp; Queue Refresh Rate</strong> [Default 10s]
<input type="text" name="home_update" size="3" value="' . $home_update . '" /><br>
<strong>Maximum Active Peers</strong> [Default 5]: <input type="text" name="max_peers" size="3" value="' . $max_active_peers . '"/><br>
<strong>Maximum Reserve Peers</strong> [Default 10]: <input type="text" name="max_new_peers" size="3" value="' . $max_new_peers . '"/><br>
</td>
<td align="left" style="width:175px"><input type="submit" onclick="showWait()" name="Submit2" value="Save Options" />
</td></tr>
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
<option value="America/Los_Angeles">(GMT-08:00) Pacific Time (US &amp; Canada)</option>
<option value="America/Denver">(GMT-07:00) Mountain Time (US &amp; Canada)</option>
<option value="America/Chihuahua">(GMT-07:00) Chihuahua, La Paz, Mazatlan</option>
<option value="America/Dawson_Creek">(GMT-07:00) Arizona</option>
<option value="America/Belize">(GMT-06:00) Saskatchewan, Central America</option>
<option value="America/Cancun">(GMT-06:00) Guadalajara, Mexico City, Monterrey</option>
<option value="Chile/EasterIsland">(GMT-06:00) Easter Island</option>
<option value="America/Chicago">(GMT-06:00) Central Time (US &amp; Canada)</option>
<option value="America/New_York">(GMT-05:00) Eastern Time (US &amp; Canada)</option>
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
<table border="0"><tr><td style="width:500px" align="right"></td>
<td style="width:175px" valign="bottom" align="right"><FORM ACTION="index.php?menu=options&amp;upgrade=check" METHOD="post"><input type="submit" name="Submit3" onclick="showWait()" value="Check for Updates" /></FORM></td></tr>
<tr><td colspan="2"><hr></td></tr>
<tr><td align="right"><FORM ACTION="index.php?menu=options&amp;manage=tabs" METHOD="post"><input type="submit" onclick="showWait()" name="Submit4" value="Menu Tabs" /></FORM></td>
<td align="right"><FORM ACTION="index.php?menu=options&amp;manage=plugins" METHOD="post"><input type="submit" onclick="showWait()" name="Submit5" value="Manage Plugins" /></FORM></td></tr>
</table>';
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
	if(check_standard_tab_settings($standard_settings_number, 16) == TRUE) { $address_book_enable = "CHECKED"; }else{ $address_book_disable = "CHECKED"; }

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
	<tr><td valign="top" align="right">Address Book</td>
	<td valign="top" align="left"><input type="radio" name="tab_address" value="0" ' . $address_book_disable . '>Hide <input type="radio" name="tab_address" value="1" ' . $address_book_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Peerlist</td>
	<td valign="top" align="left" style="width:200px"><input type="radio" name="tab_peerlist" value="0" ' . $tab_peerlist_disable . '>Hide <input type="radio" name="tab_peerlist" value="1" ' . $tab_peerlist_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Transaction Queue</td>
	<td valign="top" align="left"><input type="radio" name="tab_trans_queue" value="0" ' . $trans_queue_disable . '>Hide <input type="radio" name="tab_trans_queue" value="1" ' . $trans_queue_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">Send</td>
	<td valign="top" align="left"><input type="radio" name="tab_send_receive" value="0" ' . $send_receive_disable . '>Hide <input type="radio" name="tab_send_receive" value="1" ' . $send_receive_enable . '>Show</td></tr>
	<tr><td valign="top" align="right">History</td>
	<td valign="top" align="left"><input type="radio" name="tab_history" value="0" ' . $history_disable . '>Hide <input type="radio" name="tab_history" value="1" ' . $history_enable . '>Show</td></tr>
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
function send_receive_body($fill_in_key, $amount, $cancel = FALSE, $easy_key, $message, $name)
{
	if(empty($name) == FALSE)
	{
		// Fill in address book name
		$hidden_name = $name;
		$name = ' to <font color="blue">' . $name . '</font>';
	}

	if($cancel == TRUE)
	{
		// Redo menu to show cancel or complete send buttons
		$private_key_crypt = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'private_key_crypt' LIMIT 1"),0,1);

		if($private_key_crypt == TRUE)
		{
			$request_password = '<strong>Password Required:</strong> <input type="password" name="crypt_password" />';
		}

		$cancel_button = '<FORM ACTION="index.php?menu=send" METHOD="post"><input type="submit" name="Submit2" value="Cancel" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&amp;complete=send" METHOD="post">';		
	}
	else
	{
		$cancel_button = '<FORM ACTION="index.php?menu=send&amp;easykey=grab" METHOD="post"><input type="text" size="24" name="easy_key" value="' . $easy_key . '" /><br>
			<input type="submit" value="Easy Key" /></FORM>';
		$form_action = '<FORM ACTION="index.php?menu=send&amp;check=key" METHOD="post">';
	}

	return '<strong><font color="blue">Public Key</font> to send transaction' . $name . ':</strong><br>' . $form_action . '<table border="0" cellpadding="6"><tr><td colspan="2">
	<textarea name="send_public_key" rows="6" cols="75">' . $fill_in_key . '</textarea></td></tr>
	<tr><td style="width:640px" colspan="2"><strong>Message:</strong><br><input type="text" maxlength="64" size="64" value="' . $message . '" name="send_message" /></td></tr>
	<tr><td valign="top" align="left"><strong>Amount:</strong> <input type="text" size="8" value="' . $amount . '" name="send_amount" />
	<input type="hidden" name="name" value="' . $hidden_name . '">
	<input type="submit" onclick="showWait()" name="Submit1" value="Send Timekoins" /></td><td valign="top" align="right">' . $request_password . '
	</td></tr></table></FORM>
	<table border="0" cellpadding="6"><tr><td style="width:580px" align="right">' . $cancel_button  . '</td></tr>
	<tr><td align="right">Create Your Own Here:<br><a target="_blank" href="http://easy.timekoin.net/">easy.timekoin.net</a></td></tr></table>';
}
//***********************************************************
//***********************************************************
function tools_bar()
{
	return '<table cellspacing="10" border="0">
	<tr><td><FORM ACTION="index.php?menu=tools&amp;action=check_tables" METHOD="post"><input type="submit" onclick="showWait()" value="Check DB"/></FORM></td><td>|<br>|</td>
	<td><FORM ACTION="index.php?menu=tools&amp;action=optimize_tables" METHOD="post"><input type="submit" onclick="showWait()" value="Optimize DB"/></FORM></td><td>|<br>|</td>
	<td><FORM ACTION="index.php?menu=tools&amp;action=repair_tables" METHOD="post"><input type="submit" onclick="showWait()" value="Repair DB"/></FORM></td></tr></table>';
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
