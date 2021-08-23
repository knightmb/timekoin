<?PHP
include 'templates.php';
include 'function.php';
include 'configuration.php';
set_time_limit(60);
session_name("tkbillfoldserver");
session_start();

if($_SESSION["valid_login"] == FALSE && $_GET["action"] != "login" && $_GET["action"] != "create_account" && $_GET["action"] != "do_create_account" && $_GET["action"] != "confirm_account")
{
	$_SESSION["valid_session"] = TRUE;

	if($_SESSION["valid_session"] == TRUE)
	{
		// Not logged in, display login page
		login_screen();
	}

	sleep(1); // One second delay to help prevent brute force attack
	exit;
}

if($_SESSION["valid_session"] == TRUE && $_GET["action"] == "confirm_account")
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$tk_confirmation = $_POST["tk_confirmation"];
	$status = mysql_result(mysqli_query($db_connect, "SELECT status FROM `users` WHERE `username` = '" . $_SESSION["login_username"] . "' LIMIT 1"));

	if($status == $tk_confirmation)
	{
		// Confirmation Code Match, enable account
		$sql = "UPDATE `users` SET `status` = '1' WHERE `users`.`username` = '" . $_SESSION["login_username"] . "'";
		if(mysqli_query($db_connect, $sql) == TRUE)
		{
			unset($_SESSION["login_username"]);
			login_screen();
			exit;
		}
	}
	else
	{
		sleep(1);
		confirm_screen("Could Not Confirm Code!");
		exit;
	}
}

if($_SESSION["valid_session"] == TRUE && $_GET["action"] == "create_account" && $_GET["action"] != "do_create_account")
{
	create_account_screen();
	exit;
}

if($_SESSION["valid_session"] == TRUE && $_GET["action"] == "do_create_account")
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$new_timekoin_username = $_POST["new_timekoin_username"];
	$new_timekoin_password1 = $_POST["new_timekoin_password1"];
	$new_timekoin_password2 = $_POST["new_timekoin_password2"];
	$new_timekoin_email = $_POST["new_timekoin_email"];
	$new_timekoin_email = filter_sql($new_timekoin_email);

	// Check if username is already taken
	$new_timekoin_username = strtolower($new_timekoin_username);
	$username_SHA256 = hash('sha256', $new_timekoin_username);
	$username_hash = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'username' LIMIT 1"));

	if($username_SHA256 != $username_hash)
	{
		// No match for admin username, how
		// about the users?
		$username_hash = mysql_result(mysqli_query($db_connect, "SELECT id FROM `users` WHERE `username` = '$username_SHA256' LIMIT 1"));

		if($username_hash == "")
		{
			// No usernames match, ok to create a new one
			if($new_timekoin_password1 === $new_timekoin_password2)
			{
				$email_Required = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'email_Required' LIMIT 1")));
			
				// Password confirmed, is email valid?
				if($new_timekoin_email != "" || $email_Required == FALSE)
				{
					// Create Account and Schedule Confirmation E-mail
					$time = time();
					$password_SHA256 = hash('sha256', $new_timekoin_password1);
					$AES_SHA256_password = hash('sha256', $new_timekoin_password1 . $password_SHA256);

					// Encrypt default settings
					$default_settings = "---standard_tabs_settings=94---default_timezone=---public_key_font_size=3---refresh_realtime_home=10---END";
					$settings_AES = AesCtr::encrypt($default_settings, $AES_SHA256_password, 256);

					$default_address_book = "---id=1---name1=Example Name---easy_key1=---full_key1=ABCDEFG---END1";
					$address_book_AES = AesCtr::encrypt($default_address_book, $AES_SHA256_password, 256);

					$default_my_keys = "---id=1---name1=My Keys---private_key1=---public_key1=---END1";
					$my_keys_AES = AesCtr::encrypt($default_my_keys, $AES_SHA256_password, 256);

					// E-mail Activiation Required?
					if($email_Required == TRUE)
					{
						// Activation Code
						$active = rand(10000, 99999);
					}
					else
					{
						// None needed
						$active = 1;
					}

					// Insert into database and flag as new account that needs e-mail verification
					$sql = "INSERT INTO `users` (`id`, `timestamp`, `status`, `username`, `password`, `email`, `settings`, `address_book`, `my_keys`) 
					VALUES (NULL, '$time', '$active', '$username_SHA256', '$password_SHA256', '$new_timekoin_email', '$settings_AES', '$address_book_AES', '$my_keys_AES')";

					$sql2 = "INSERT INTO `data_cache` (`username`, `field_name`, `field_data`) VALUES
					('$username_SHA256', 'billfold_balance', ''),
					('$username_SHA256', 'graph_data_amount_total', ''),
					('$username_SHA256', 'graph_data_range_recv', ''),
					('$username_SHA256', 'graph_data_range_sent', ''),
					('$username_SHA256', 'graph_data_trans_total', ''),
					('$username_SHA256', 'trans_history_sent_from', ''),
					('$username_SHA256', 'trans_history_sent_to', '')";

					if(mysqli_query($db_connect, $sql) == TRUE && mysqli_query($db_connect, $sql2) == TRUE)
					{
						if($email_Required == TRUE)
						{
							$email_subject = 'Timekoin Client Activation Code';
							$email_message = 'Your Timekoin Client Activation Code: [ ' . $active . ' ]' ;

							if(email_notify($new_timekoin_email, $email_subject, $email_message) == TRUE)
							{
								write_log("New Account Confirmation Sent To [ $new_timekoin_email ]","S");
							}

							$account_creation = 'Account Creation Successful!<br>
							An activation code will be sent to your e-mail address.<br>
							You have 24 hours to activate your account before it is deleted.';

							create_account_screen($account_creation, TRUE);
						}
						else
						{
							create_account_screen("Account Creation Successful!", TRUE);
						}
						exit;
					}
					else
					{
						// Database Error
						create_account_screen("DATABASE ERROR: Account Could Not Be Created!");
						exit;
					}
				}
				else
				{
					// Email Empty
					create_account_screen("E-mail Address Empty!");
					exit;
				}
			}
			else
			{
				// Passwords did not match
				create_account_screen("Passwords Did Not Match!");
				exit;
			}
		}
		else
		{
			// Account match to admin username
			create_account_screen("Account Username Already Taken!");
			exit;
		}
	}
	else
	{
		// Account match to admin username
		create_account_screen("Account Username Already Taken!");
		exit;
	}

	create_account_screen("Account Creation FAILED!");
	exit;
}

if($_SESSION["valid_session"] == TRUE && $_GET["action"] == "login")
{
	$http_username = $_POST["timekoin_username"];
	$http_password = $_POST["timekoin_password"];

	if(empty($http_username) == FALSE && empty($http_password) == FALSE)
	{
		$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

		if($db_connect == FALSE)
		{
			login_screen('Could Not Connect To Database');
			exit;
		}
		
		$username_hash = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'username' LIMIT 1"));
		$password_hash = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'password' LIMIT 1"));

		if(hash('sha256', $http_username) == $username_hash)
		{
			//Username match, check password
			if(hash('sha256', $http_password) == $password_hash)
			{
				// All match, set login variable and store username in cookie
				$_SESSION["login_username"] = $http_username;
				$_SESSION["valid_login"] = TRUE;
				$_SESSION["admin_login"] = TRUE;

				initialization_database(); // Do any required data upgrades

				// Start any plugins
				$sql = "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' ORDER BY `options`.`field_name` ASC";
				$sql_result = mysqli_query($db_connect, $sql);
				$sql_num_results = mysqli_num_rows($sql_result);

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysqli_fetch_array($sql_result);

					$plugin_file = find_string("---file=", "---enable", $sql_row["field_data"]);
					$plugin_enable = intval(find_string("---enable=", "---show", $sql_row["field_data"]));
					$plugin_service = find_string("---service=", "---end", $sql_row["field_data"]);

					if($plugin_enable == TRUE && empty($plugin_service) == FALSE)
					{
						// Start Plugin Service
						call_script($plugin_file, 0, TRUE);

						// Log Service Start
						write_log("Started Plugin Service: $plugin_service", "S");
					}
				} // Finish Starting Plugin Services

				header("Location: index.php");
				exit;
			}
			else
			{
				// Log invalid attempts
				write_log("Invalid Login from IP: " . $_SERVER['REMOTE_ADDR'] . " with Username:[" . filter_sql($http_username) . "] and Password:[" . filter_sql($http_password) . "]" , "GU");
			}
		}
	} // This wasn't the Admin logging in.

	// Chek for regular user
	$http_username = strtolower($http_username);
	$username_SHA256 = hash('sha256', $http_username);
	$password_SHA256 = hash('sha256', $http_password);

	$username_hash = mysql_result(mysqli_query($db_connect, "SELECT id FROM `users` WHERE `username` = '$username_SHA256' LIMIT 1"));

	if($username_hash != "")
	{
		// Got a regular user, do passwords match?
		$password_hash = mysql_result(mysqli_query($db_connect, "SELECT password FROM `users` WHERE `username` = '$username_SHA256' LIMIT 1"));

		if($password_SHA256 === $password_hash)
		{
			// Got a regular user, load up settings
			$account_status = mysql_result(mysqli_query($db_connect, "SELECT status FROM `users` WHERE `username` = '$username_SHA256' LIMIT 1"));

			if($account_status > 9999)
			{
				// This account needs to be activated first
				$_SESSION["login_username"] = $username_SHA256;
				confirm_screen();
				exit;
			}
			else
			{
				$_SESSION["login_username"] = $http_username;
				$_SESSION["valid_login"] = TRUE;

				// Create decrypting password for settings and other features that only exist for that user
				$AES_SHA256_password = hash('sha256', $http_password . $password_SHA256);			
				$_SESSION["decrypt_password"] = $AES_SHA256_password;

				// Send to their own personal page
				header("Location: index.php");
				exit;
			}
		}
		else
		{
			// Log invalid attempts
			write_log("Invalid Login from IP: " . $_SERVER['REMOTE_ADDR'] . " with Username:[" . filter_sql($http_username) . "]" , "GU");
		}
	}

	sleep(1); // One second delay to help prevent brute force attack
	login_screen("Login Failed");
	exit;
}

if($_SESSION["valid_login"] == TRUE)
{
//****************************************************************************
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	if($db_connect == FALSE)
	{
		home_screen('ERROR', '<font color="red"><strong>Could Not Connect To Database</strong></font>', '', '');
		exit;
	}
	//****************************************************************************
	//
	if(empty($_GET["menu"]) == TRUE)
	{
		// Build frame box with the bottom self-refreshing frame for task
		?>
		<!DOCTYPE html>		
		<html>
		  <head>
			<title>Timekoin Client Billfold</title>			
			<link rel="icon" type="image/x-icon" href="img/favicon.ico" />
			<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
			<script type="text/javascript">
			window.onload = setupRefresh;
			function setupRefresh() 
			{
				 setInterval("refreshFrame();", 10000);
			}
			function refreshFrame() 
			{
				parent.bottom_frame.location.reload();
			}
		  </script>
		  </head>
		  <frameset id="timekoin" rows="*,1">
			<frame name="top_frame" src="index.php?menu=home" scrolling="auto" frameborder="0" />
			<frame name="bottom_frame" src="task.php?task=refresh" scrolling="none" noresize="noresize" frameborder="0" />
		  </frameset>
		</html>
		<?PHP
		return;
	}
//****************************************************************************
//****************************************************************************
	if(($_GET["menu"] == "home" || empty($_GET["menu"]) == TRUE) && $_SESSION["admin_login"] != TRUE)
	{
		$body_string = '<strong>Last 20 <font color="green">Received</font> Transaction Amounts to My Billfold</strong><br><canvas id="recv_graph" width="690" height="300">Your Web Browser does not support HTML5 Canvas.</canvas>';
		$body_string .= '<hr>';
		$body_string .= '<strong>Last 20 <font color="blue">Sent</font> Transaction Amounts from My Billfold</strong><br><canvas id="sent_graph" width="690" height="300">Your Web Browser does not support HTML5 Canvas.</canvas>';
		$body_string .= '<hr>';
		$body_string .= '<strong>Timekoin Network - Total Transactions per Cycle (Last 25 Cycles)</strong><br><canvas id="trans_total" width="690" height="200">Your Web Browser does not support HTML5 Canvas.</canvas>';
		$body_string .= '<hr>';
		$body_string .= '<strong>Timekoin Network - Total Amounts Sent per Cycle (Last 20 Cycles)</strong><br><canvas id="amount_total" width="690" height="400">Your Web Browser does not support HTML5 Canvas.</canvas>';

		$display_balance = db_cache_balance(my_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"]), 55, $_SESSION["login_username"]);
		$home_update = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "refresh_realtime_home");

		if($home_update < 60 && $home_update != 0) // Cap home updates refresh to 1 minute
		{
			$home_update = 60;
		}

		if($display_balance === "NA")
		{
			$display_balance_GUI = '<font color="red">Waiting For Network</font>';
			$home_update = 5;
		}
		else
		{
			$display_balance_GUI = number_format($display_balance);
		}

		$text_bar = '<table border="0"><tr><td style="width:325px"><strong>Current Billfold Balance: <font color="green">' . $display_balance_GUI . '</font> TK</strong></td></tr></table>';

		$quick_info = 'This section will contain helpful information about each tab in the software.';

		home_screen("Home", $text_bar, $body_string, $quick_info , $home_update);
		exit;

	}
//****************************************************************************
	if($_GET["menu"] == "address" && $_SESSION["admin_login"] != TRUE)
	{
		if($_GET["font"] == "public_key")
		{
			if(empty($_POST["font_size"]) == FALSE)
			{
				// Save value in encrypted database
				save_default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "public_key_font_size", intval($_POST["font_size"]));

				header("Location: index.php?menu=address");
				exit;
			}
		}
		else
		{
			$default_public_key_font = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "public_key_font_size");
		}

		if($_GET["task"] == "delete")
		{
			// Remove Address Entry
			delete_address_book($_SESSION["login_username"], $_SESSION["decrypt_password"], intval($_GET["name_id"]));
		}

		if($_GET["task"] == "save_new")
		{
			// Save New Address
			$full_key = $_POST["full_key"];
			
			if(empty($_POST["easy_key"]) == FALSE)
			{
				// Lookup Easy Key
				$easy_key = $_POST["easy_key"];

				// Translate Easy Key to Public Key and fill in field with
				$full_key = easy_key_lookup($easy_key);

				if($full_key === 0 || empty($full_key) == TRUE)
				{
					$easy_key_fail = TRUE;
				}
			}

			if($easy_key_fail == FALSE)
			{
				$address_book_data = address_book_data($_SESSION["login_username"], $_SESSION["decrypt_password"]);
				$counter = 1;

				// How Many Entries exist already?
				while($counter < 100)
				{
					$num_address_book = find_string("---id=$counter", "---easy_key$counter", $address_book_data);

					if($num_address_book == "")
					{
						// This number is available
						break;
					}
					
					$counter++;
				}

				if($counter < 100)
				{
					save_new_address_book($_SESSION["login_username"], $_SESSION["decrypt_password"], $counter, filter_sql($_POST["name"]), filter_sql($easy_key), filter_sql($full_key));
				}
				else
				{
					// Address Book Full
					$server_message = '<font color="red"><strong>Address Book Full!</strong></font>';
				}
			}
		}

		if($_GET["task"] == "new" || $easy_key_fail == TRUE)
		{
			if($easy_key_fail == TRUE)
			{
				$easy_messasge = '<font color="red"><strong>Easy Key Lookup Failed</strong></font>';
			}
			
			// New Address Form
			$body_string = '<FORM ACTION="index.php?menu=address&amp;task=save_new" METHOD="post">
			<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" >
			<tr><th>Address Name</th><th>Easy Key</th><th>Full Public Key</th><th></th><th></th></tr>
			<tr><td class="style2" valign="top"><input type="text" name="name" size="16" value="'.$_POST["name"].'" /></td>
			<td class="style2" valign="top"><input type="text" name="easy_key" size="16" value="'.$easy_key.'" /><br>'.$easy_messasge.'</td>
			<td class="style2"><textarea name="full_key" rows="6" style="width: 100%; max-width: 100%;"></textarea></td>			 
			<td valign="top"><input type="image" src="img/save-icon.gif" title="Save New Address" name="submit1" border="0" onclick="showWait()"></td>
			<td valign="top"></td></tr></table></div></FORM>';
		}

		if($_GET["task"] == "edit_save")
		{
			// Save New Address
			$full_key = $_POST["full_key"];
			
			if(empty($_POST["easy_key"]) == FALSE)
			{
				// Lookup Easy Key
				$easy_key = $_POST["easy_key"];
				$full_key = easy_key_lookup($easy_key);

				if($full_key === "" || empty($full_key) == TRUE)
				{
					$easy_key_edit_fail = TRUE;
				}
			}

			if($easy_key_edit_fail == FALSE)
			{
				edit_address_book($_SESSION["login_username"], $_SESSION["decrypt_password"], $_GET["name_id"], $_POST["name"], $easy_key, $full_key);
			}
		}

		if($_GET["task"] == "edit" || $easy_key_edit_fail == TRUE)
		{
			if($easy_key_edit_fail == TRUE)
			{
				$easy_edit_messasge = '<font color="red"><strong>Easy Key Lookup Failed!<BR>Spelling is Case Sensitive.</strong></font>';
				$name = $_POST["name"];
				$easy_key = $_POST["easy_key"];
				$full_key = $_POST["full_key"];
			}			
			else
			{
				// Edit Address
				$address_book_data = address_book_data($_SESSION["login_username"], $_SESSION["decrypt_password"]);
				$counter = 1;
				$address_book = array();
				$ab_id = intval($_GET["name_id"]);

				// Build address array
				while($counter < 100)
				{
					$num_address_book = find_string("---id=$counter", "---easy_key$counter", $address_book_data);

					if($num_address_book != "")
					{
						if($ab_id == $counter)
						{
							$address_book["id1"] = $counter;
							$address_book["name1"] = find_string("---name$counter=", "---easy_key$counter", $address_book_data);
							$address_book["easy_key1"] = find_string("---easy_key$counter=", "---full_key$counter", $address_book_data);
							$address_book["full_key1"] = find_string("---full_key$counter=", "---END$counter", $address_book_data);
							break;
						}
					}
					
					$counter++;
				}
			}

			// Edit Form
			$body_string = '<FORM ACTION="index.php?menu=address&amp;task=edit_save&amp;name_id=' . $_GET["name_id"] . '" METHOD="post">
			<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" >
			<tr><th>Address Name</th><th>Easy Key</th><th>Full Public Key</th><th></th><th></th></tr><tr>
			<td class="style2" valign="top"><input type="text" name="name" size="16" value="' . $address_book["name1"] . '"/></td>
			<td class="style2" valign="top"><input type="text" name="easy_key" size="16" value="' . $address_book["easy_key1"] . '" /><br>'.$easy_edit_messasge.'</td>
			<td class="style2"><textarea name="full_key" rows="6" style="width: 100%; max-width: 100%;">' . $address_book["full_key1"] . '</textarea></td>			 
			<td valign="top"><input type="image" src="img/edit-icon.gif" title="Edit Address" name="submit1" border="0" onclick="showWait()"></td>
			<td valign="top"></td></tr></table></div></FORM>';
		}

		if($_GET["task"] != "new" && $_GET["task"] != "edit" && $easy_key_fail == FALSE && $easy_key_edit_fail == FALSE) // Default View
		{
			$address_book_data = address_book_data($_SESSION["login_username"], $_SESSION["decrypt_password"]);
			$counter = 1;
			$display_counter = 0;
			$address_book = array();

			// Build address array
			while($counter < 100)
			{
				$num_address_book = find_string("---id=$counter", "---easy_key$counter", $address_book_data);

				if($num_address_book != "")
				{
					$display_counter++;
					$address_book["id$display_counter"] = $counter;
					$address_book["name$display_counter"] = find_string("---name$counter=", "---easy_key$counter", $address_book_data);
					$address_book["easy_key$display_counter"] = find_string("---easy_key$counter=", "---full_key$counter", $address_book_data);
					$address_book["full_key$display_counter"] = find_string("---full_key$counter=", "---END$counter", $address_book_data);
				}
				
				$counter++;
			}

			$body_string = '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" >
				<tr><th>Address Name</th><th>Easy Key</th><th>Full Public Key</th><th></th><th></th><th></th></tr>';

			for ($i = 1; $i < $display_counter + 1; $i++)
			{
				$body_string .= '<tr><td class="style2"><p style="word-wrap:break-word; width:175px; font-size:12px;">' . $address_book["name$i"] . 
				' <a href="index.php?menu=history&amp;name_id=' . $address_book["id$i"] . '" title="' . $address_book["name$i"] . ' History"><img src="img/timekoin_history.png" style="float: right;"></a></p>
				</td><td class="style1"><p style="word-wrap:break-word; width:175px; font-size:12px;">' . $address_book["easy_key$i"] . 
				'</p></td><td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . $address_book["full_key$i"] . '</p></td>
				<td><a href="index.php?menu=address&amp;task=delete&amp;name_id=' . $address_book["id$i"] . '" title="Delete ' . $address_book["name$i"] . '" onclick="return confirm(\'Delete ' . $address_book["name$i"] . '?\');"><img src="img/hr.gif"></a></td>
				<td><a href="index.php?menu=address&amp;task=edit&amp;name_id=' . $address_book["id$i"] . '" title="Edit ' . $address_book["name$i"] . '"><img src="img/edit-icon.gif"></a></td>
				<td><a href="index.php?menu=send&amp;name_id=' . $address_book["id$i"] . '" title="Send Koins to ' . $address_book["name$i"] . '"><img src="img/timekoin_send.png"></a></td></tr>';
			}

			$body_string .= '<tr><td colspan="6"><hr></td></tr><tr>
			<td colspan="6"><FORM ACTION="index.php?menu=address&amp;task=new" METHOD="post"><input type="submit" value="Add New Address" onclick="showWait()"/></FORM></td></tr></table></div>';
		}

		if($_GET["task"] != "new" && $easy_key_fail == FALSE && $easy_key_edit_fail == FALSE) // Default View
		{		
			$quick_info = "The <strong>Address Book</strong> allows long, obscure public keys to be translated to friendly names.<br><br>
			Transactions can also quickly be created from here.<br><br>
			The scribe next to the name can be clicked to bring up a custom history of all transactions to and from the name selected.";
		}
		else
		{
			$quick_info = "<strong>Address Name</strong> is friendly name to associate with the Public Key.<br><br>
			You can enter an <strong>Easy Key</strong> address and Timekoin will attempt to lookup the full key when saving.<br><br>
			If no Easy Key is known or needed, just enter the full Public Key instead.";
		}

		$text_bar = '<FORM ACTION="index.php?menu=address&amp;font=public_key" METHOD="post">
		<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td><td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" onclick="showWait()" /></td></tr></table></FORM>';

		home_screen("Address Book", $text_bar . $server_message, $body_string, $quick_info);
		exit;
	}	
//****************************************************************************
	if($_GET["menu"] == "queue" && $_SESSION["admin_login"] != TRUE)
	{
		if($_GET["font"] == "public_key")
		{
			if(empty($_POST["font_size"]) == FALSE)
			{
				// Save value in encrypted database
				save_default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "public_key_font_size", intval($_POST["font_size"]));
				header("Location: index.php?menu=queue");
				exit;
			}
		}
		else
		{
			$default_public_key_font = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "public_key_font_size");
		}

		$my_public_key = my_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"]);
		$default_timezone = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "default_timezone");

		// Find the last X amount of transactions sent to this public key
		$sql = "SELECT * FROM `transaction_queue` ORDER BY `transaction_queue`.`timestamp` DESC";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);

		$body_string = '<strong><font color="blue">( ' . number_format($sql_num_results) . ' )</font> Network Transactions Waiting for Processing</strong><br><br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
			<th>Send From</th><th>Send To</th><th>Amount</th></tr>';

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
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
					$public_key_from = '<td class="style2"><font color="green">My Public Key</font>';
					
					// Check if the key matches anyone in the address book
					$address_name = address_book_lookup($_SESSION["login_username"], $_SESSION["decrypt_password"], "full_key", base64_encode($public_key_trans_to), "name");

					if(empty($address_name) == TRUE)
					{
						$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans_to) . '</p>';
					}
					else
					{
						$public_key_to = '<td class="style2"><font color="blue">' . $address_name . '</font>';
					}
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
						// Check if the key matches anyone in the address book
						$address_name = address_book_lookup($_SESSION["login_username"], $_SESSION["decrypt_password"], "full_key", base64_encode($public_key_trans_to), "name");

						if(empty($address_name) == TRUE)
						{
							$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans_to) . '</p>';
						}
						else
						{
							$public_key_to = '<td class="style2"><font color="blue">' . $address_name . '</font>';
						}
					}
				}

				// Check if the key matches anyone in the address book
				$address_name = address_book_lookup($_SESSION["login_username"], $_SESSION["decrypt_password"], "full_key", base64_encode($public_key_trans), "name");

				if(empty($address_name) == TRUE)
				{
					$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans) . '</p>';
				}
				else
				{
					$public_key_from = '<td class="style2"><font color="blue">' . $address_name . '</font>';
				}
			}

			if($sql_row["attribute"] == "R")
			{
				$transaction_amount = "R";
				$public_key_to = '<td class="style1"><p style="font-size:12px;"><strong><font color="blue">Election Request</font></strong></p>';
			}

			$body_string .= '<tr>
			<td class="style2">' . unix_timestamp_to_human($sql_row["timestamp"], $default_timezone) . '</td>' 
			. $public_key_from . '</td>'
			. $public_key_to . '</td>
			<td class="style2">' . $transaction_amount . '</td></tr>';
		}
		
		$body_string .= '</table></div>';

		$text_bar = '<FORM ACTION="index.php?menu=queue&amp;font=public_key" METHOD="post">
			<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td><td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

		$quick_info = 'This section contains all the network transactions that are queued to be stored in the transaction history.';

		$home_update = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "refresh_realtime_home");

		home_screen('Transactions in Network Queue', $text_bar, $body_string , $quick_info, $home_update);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "send" && $_SESSION["admin_login"] != TRUE)
	{
		$my_public_key = my_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"]);
		$default_timezone = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "default_timezone");

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
				$body_string .= '<hr><font color="red"><strong>This exceeds your current balance, send failed...</strong></font><br><br>';
			}
			else
			{
				if($my_public_key == $public_key_to)
				{
					// Can't send to yourself
					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body();
					$body_string .= '<hr><font color="red"><strong>Can not send to yourself, send failed...</strong></font><br><br>';
				}
				else
				{
					// Check if public key is valid by searching for any transactions
					// that reference it
					$valid_key_test = verify_public_key($public_key_to);

					if($valid_key_test == TRUE)
					{
						// Key has a valid history
						$message = $_POST["send_message"];
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body($public_key_64, $send_amount, TRUE, NULL, $message, $_POST["name"]);
						$body_string .= '<hr><font color="green"><strong>This public key is valid.</strong></font><br>
						<strong>There is no way to recover Timekoins sent to the wrong public key.</strong><br>
						<font color="blue"><strong>Click "Send Timekoins" to send now.</strong></font><br><br>';
					}
					else
					{
						// No key history, might not be valid
						$message = $_POST["send_message"];
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body($public_key_64, $send_amount, TRUE, NULL, $message, $_POST["name"]);
						$body_string .= '<hr><font color="red"><strong>This public key has no existing history of transactions.<br>
						There is no way to recover Timekoins sent to the wrong public key.</strong></font><br>
						<strong>Click "Send Timekoins" to send now.</strong><br><br>';
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
					$body_string .= '<hr><font color="red"><strong>This exceeds your current balance, send failed...</strong></font><br><br>';
				}
				else
				{
					if($my_public_key == $public_key_to)
					{
						// Can't send to yourself
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body();
						$body_string .= '<hr><font color="red"><strong>Can not send to yourself, send failed...</strong></font><br><br>';
					}
					else
					{
						// Now it's time to send the transaction
						$my_private_key = my_private_key(FALSE, $_SESSION["login_username"], $_SESSION["decrypt_password"]);
						$private_key_crypt = my_private_key(TRUE, $_SESSION["login_username"], $_SESSION["decrypt_password"]);

						if($private_key_crypt == TRUE)
						{
							// Decrypt Private Key First
							$my_private_key = AesCtr::decrypt($my_private_key, $_POST["crypt_password"], 256);
							$valid_key = find_string("-----BEGIN", "KEY-----", $my_private_key); // Valid Decrypt?

							if(empty($valid_key) == TRUE)
							{
								// Decrypt Failed
								$display_balance = db_cache_balance($my_public_key);
								$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
								$body_string .= '<hr><font color="red"><strong>Send Failed. Wrong Password.</strong></font><br><br>';
							}
							else
							{
								if(send_timekoins($my_private_key, $my_public_key, $public_key_to, $send_amount, $message) == TRUE)
								{
									$display_balance = db_cache_balance($my_public_key);
									$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
									$body_string .= '<hr><font color="green"><strong>You just sent ' . $send_amount . ' timekoins to the above public key.</strong></font><br>
									<strong>Your balance will not reflect this until the transaction is recorded across the entire network.</strong><br><br>';
								}
								else
								{
									$display_balance = db_cache_balance($my_public_key);
									$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
									$body_string .= '<hr><font color="red"><strong>Send failed...</strong></font><br><br>';
								}

								// Clear Variable from RAM
								unset($my_private_key);
							}
						}
						else
						{
							if(send_timekoins($my_private_key, $my_public_key, $public_key_to, $send_amount, $message) == TRUE)
							{
								$display_balance = db_cache_balance($my_public_key);
								$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
								$body_string .= '<hr><font color="green"><strong>You just sent ' . $send_amount . ' timekoins to the above public key.</strong></font><br>
								<strong>Your balance will not reflect this until the transaction is recorded across the entire network.</strong><br><br>';
							}
							else
							{
								$display_balance = db_cache_balance($my_public_key);
								$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
								$body_string .= '<hr><font color="red"><strong>Send failed...</strong></font><br><br>';
							}

							// Clear Variable from RAM
							unset($my_private_key);
						}
					} // End duplicate self check
				} // End Balance Check
			} // End check send command
			else
			{
				if($_GET["easykey"] == "grab")
				{
					$message = $_POST["send_message"];
					$easy_key = $_POST["easy_key"];
					$last_easy_key = $_POST["easy_key"];
					$easy_key = easy_key_lookup($easy_key);

					if($easy_key === 0 || empty($easy_key) == TRUE)
					{
						$server_message = '<font color="red"><strong>' . $last_easy_key . ' Not Found!<BR>Spelling is Case Sensitive.</strong></font>';
						$easy_key = NULL;
					}
					else
					{
						$server_message = '<font color="blue"><strong>Easy Key Found</strong></font>';
					}
				}

				if(empty($_GET["name_id"]) == TRUE)
				{
					// No selections made, default screen
					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body($easy_key, NULL, NULL, $last_easy_key, $message);
					$body_string .= $server_message;
				}
				else
				{
					// Insert Address Book Entry
					$address_book_data = address_book_data($_SESSION["login_username"], $_SESSION["decrypt_password"]);
					$counter = 1;
					$id_lookup = intval($_GET["name_id"]);

					// Find Address Data
					while($counter < 100)
					{
						$num_address_book = find_string("---id=$counter", "---easy_key$counter", $address_book_data);

						if($num_address_book != "")
						{
							if($id_lookup == $counter)
							{
								$name = find_string("---name$counter=", "---easy_key$counter", $address_book_data);
								$easy_key = find_string("---easy_key$counter=", "---full_key$counter", $address_book_data);
								$full_key = find_string("---full_key$counter=", "---END$counter", $address_book_data);
								break;
							}
						}
						
						$counter++;
					}

					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body($full_key, NULL, NULL, $easy_key, $message, $name);
				}
			}
		}

		if($display_balance === "NA")
		{
			$display_balance = '<font color="red">NA</font>';
		}
		else
		{
			$display_balance = number_format($display_balance);
		}

		if($_GET["easy_key"] == "new")
		{
			$easy_key_fee = num_gen_peers(TRUE) + 1;

			if($easy_key_fee < 2)
			{
				$easy_key_fee = '<font color="red">No Network Response</font>';
			}

			$private_key_crypt = my_private_key(TRUE, $_SESSION["login_username"], $_SESSION["decrypt_password"]);

			if($private_key_crypt == TRUE)
			{
				$request_password = '<tr><td><strong><font color="blue">Password Required:</font></strong> <input type="password" name="crypt_password" /></td></tr>';
			}

			$body_string = '<FORM ACTION="index.php?menu=send&amp;easy_key=create" METHOD="post">
			<table border="0" cellpadding="6"><tr><td><font color="green"><strong>Create New Easy Key</strong></font></td></tr>
			<tr><td><strong>Creation Fee: <font color="green">' . $easy_key_fee . ' TK</font></strong></td></tr>
			<tr><td><strong><font color="blue">New Easy Key</font></strong><BR>
			<input type="text" maxlength="64" size="64" value="" name="new_easy_key" /></td></tr>' . $request_password . '</table>
			<input type="submit" value="Create New Easy Key" onclick="showWait()" /></FORM>';
			
			$quick_info = '<strong>Easy Keys</strong> are shortcuts enabling access to much longer <font color="blue">Public Keys</font> in Timekoin.</br><BR>
			A New <strong>Easy Key</strong> shortcut you create must be between 1 and 64 characters in length including spaces.</br></br>
			Each <strong>Easy Key</strong> shortcut may only contain letters, digits, or special characters.</br>No <strong>| ? = \' ` * %</strong> characters allowed.<BR><BR>
			All <strong>Easy Keys <font color="red">Expire</font></strong> after <strong><font color="blue">3 Months</font></strong> unless you renew the key by creating it again with the same <font color="blue">Public Key</font> as before.';
		}
		else
		{
			$quick_info = 'Send your own Timekoins to someone else.<br><br>
			Your client will attempt to verify if the public key is valid by examing the transaction history before sending.<br><br>
			New public keys with no history could appear invalid for this reason, so always double check.<br><br>
			You can enter an <strong>Easy Key</strong> and Timekoin will fill in the Public Key field for you.<br><br>
			Messages encoded into your transaction are limited to <strong>64</strong> characters. Messages are visible to anyone that examines your specific transaction details.<br><br>No <strong>| ? = \' ` * %</strong> characters allowed.';
		}

		if($_GET["easy_key"] == "create")
		{
			set_time_limit(999);
			$new_easy_key = $_POST["new_easy_key"];
			$private_key_crypt = my_private_key(TRUE, $_SESSION["login_username"], $_SESSION["decrypt_password"]);

			if($private_key_crypt == TRUE)
			{
				// Private Key Encrypted
				$my_private_key = my_private_key(FALSE, $_SESSION["login_username"], $_SESSION["decrypt_password"]);

				// Decrypt Private Key First
				$my_private_key = AesCtr::decrypt($my_private_key, $_POST["crypt_password"], 256);
				$valid_key = find_string("-----BEGIN", "KEY-----", $my_private_key); // Valid Decrypt?

				if(empty($valid_key) == TRUE)
				{
					// Decrypt Failed
					$create_easy_key = 8;
				}
				else
				{
					// Decrypt Good
					$create_easy_key = create_new_easy_key($my_private_key, $my_public_key, $new_easy_key);
				}
			}
			else
			{
				$my_private_key = my_private_key(FALSE, $_SESSION["login_username"], $_SESSION["decrypt_password"]);
				$create_easy_key = create_new_easy_key($my_private_key, $my_public_key, $new_easy_key);
			}

			// Clear Variable from RAM
			unset($my_private_key);
			
			$body_string;

			if($create_easy_key > 300)// Success time will always be at least more than 1 transaction cycle
			{
				$seconds_to_minutes = round($create_easy_key / 60);
				$body_string = '<BR><BR><font color="green"><strong>Easy Key [' . $new_easy_key . '] Has Been Submitted to the Timekoin Network!</font><BR><BR>
				Your Easy Key Should be Active Within ' . $seconds_to_minutes . ' Minutes.<BR>
				If You Are Renewing Your Key Before it Expires, then Expect No Delay.</strong>';

				$e_key_time = transaction_cycle($create_easy_key / 300) + 7889400;// Creation time plus 3 Months

				// Store Easy Key for later lookups
				save_easy_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $new_easy_key, $e_key_time);
			}
			else
			{
				switch($create_easy_key)
				{
					case 1:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] is Too Short or Too Long!</strong></font>';
					break;

					case 2:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] Has Invalid Characters!</strong></font>';
					break;

					case 3:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] is Taken Already!</strong></font>';
					break;

					case 4:
					$body_string = '<BR><BR><font color="red"><strong>Creation Fee of [' . (num_gen_peers(FALSE, TRUE) + 1) . '] TK Needed to Create This Easy Key!</strong></font>';
					break;

					case 5:
					$body_string = '<BR><BR><font color="red"><strong>Network Error! Easy Key Creation Was Not Started!</strong></font>';
					break;

					case 6:
					$body_string = '<BR><BR><font color="red"><strong>New Easy Key Fee Transaction Failed to Send to a Public Key</strong></font>';
					break;

					case 7:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key Transaction for Creation Failed to Send</strong></font>';
					break;

					case 8:
					$body_string = '<BR><BR><font color="red"><strong>Private Key Password Incorrect!</strong></font>';
					break;

					default:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] Unknown ERROR!</strong></font>';
					break;
				}
			}

		}// Easy Key Creation

		$clipboard_copy = '<script>
		function myPublicKey()
		{
			var copyText = document.getElementById("current_public_key");
			copyText.select();
			copyText.setSelectionRange(0, 99999)
			document.execCommand("copy");
			var tooltip = document.getElementById("myTooltip2");
			tooltip.innerHTML = "Copy Complete!";
		}</script>';

		// Show all Easy Keys associated with this Client Public Key
		$settings_data = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], NULL, TRUE);
		$easy_key_matches = find_string("---easy_key=", "---END", $settings_data, FALSE, TRUE);
		$easy_key_total_matches = count($easy_key_matches[0]);

		$clean_e_key_records = 9;

		for ($i = 0; $i < $easy_key_total_matches; $i++)
		{
			$easy_key_data = $easy_key_matches[0][$i];
			$easy_key_name = find_string("easy_key=", "---expires", $easy_key_data);
			$easy_key_expires = find_string("---expires=", "---END", $easy_key_data);
			$easy_key_lookup = easy_key_lookup($easy_key_name);

			// One exist, is it ours?
			if($easy_key_lookup == base64_encode($my_public_key))
			{
				$easy_key_list.= '<br><strong>Easy Key: [<font color="blue">' . $easy_key_name . '</font>] <font color="red">Expires:</font> ' . unix_timestamp_to_human($easy_key_expires, $default_timezone) . '</strong>';
			}
			else
			{
				// No match, should we delete this one?
				if($easy_key_total_matches > $clean_e_key_records && $easy_key_lookup == "0")
				{
					delete_easy_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $easy_key_name, $easy_key_expires);
				}
			}
		}

		$text_bar = $clipboard_copy . '<table border="0" cellpadding="6"><tr><td><strong>Current Billfold Balance: <font color="green">' . $display_balance . '</font> TK</strong></td></tr>
		<tr><td><strong><font color="green">Public Key</font> to receive:</strong></td></tr></table>
		<textarea id="current_public_key" readonly="readonly" rows="6" style="width: 100%; max-width: 100%;">' . base64_encode($my_public_key) . '</textarea><br>
		<button title="Copy Public Key to Clipboard" onclick="myPublicKey()"><span id="myTooltip2">Copy Public Key</span></button><br>' . $easy_key_list;

		home_screen('Send / Receive Timekoins', $text_bar, $body_string , $quick_info);
		exit;
	}
//****************************************************************************	
	if($_GET["menu"] == "history" && $_SESSION["admin_login"] != TRUE)
	{
		$my_public_key = my_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"]);
		$default_timezone = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "default_timezone");
		$address_name;

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
				// Save value in encrypted database
				save_default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "public_key_font_size", intval($_POST["font_size"]));

				header("Location: index.php?menu=history");
				exit;
			}
		}
		else
		{
			$default_public_key_font = default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "public_key_font_size");
		}

		if(empty($_GET["name_id"]) == FALSE)
		{
			$address_book_data = address_book_data($_SESSION["login_username"], $_SESSION["decrypt_password"]);
			$counter = 1;

			// Build address array
			while($counter < 100)
			{
				if($_GET["name_id"] == $counter)
				{
					$name = find_string("---name$counter=", "---easy_key$counter", $address_book_data);
					$full_key = find_string("---full_key$counter=", "---END$counter", $address_book_data);
					break;
				}
				
				$counter++;
			}
			
			$show_last = 100;
			$name_from = ' from <font color="blue">' . $name . '</font>';
			$name_to = ' to <font color="blue">' . $name . '</font>';			
		}

		// Range to 100 max
		if($show_last > 100) { $show_last = 100; }

		if($hide_receive == FALSE)
		{
			$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> Transactions <font color="green">Sent To</font> this Billfold' . $name_from . '</strong><br>
			<FORM ACTION="index.php?menu=history&amp;receive=listmore" METHOD="post">
			<br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
			<th>Sent From</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

			$history_data_to = transaction_history_query(1, $show_last, $_SESSION["login_username"], $my_public_key);
			$counter = 1;

			while($counter <= 100) // 100 History Limit
			{
				$timestamp = find_string("---TIMESTAMP$counter=", "---FROM", $history_data_to);
				$public_key_from = find_string("---FROM$counter=", "---AMOUNT", $history_data_to);
				$amount = find_string("---AMOUNT$counter=", "---VERIFY", $history_data_to);					
				$verify = find_string("---VERIFY$counter=", "---MESSAGE", $history_data_to);
				$message = find_string("---MESSAGE$counter=", "---END$counter", $history_data_to);					

				if(empty($timestamp) == TRUE && empty($amount) == TRUE)
				{
					// No more data to search
					break;
				}

				if(empty($_GET["name_id"]) == TRUE)
				{
					if($public_key_from == base64_encode($my_public_key))
					{
						// Self Generated
						$public_key_from = '<td class="style2">My Public Key';
					}
					else
					{
						// Check if the key matches anyone in the address book
						$address_name = address_book_lookup($_SESSION["login_username"], $_SESSION["decrypt_password"], "full_key", $public_key_from, "name");

						if(empty($address_name) == TRUE)
						{
							$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:150px; font-size:' . $default_public_key_font . 'px;">' . $public_key_from . '</p>';
						}
						else
						{
							$public_key_from = '<td class="style2"><font color="blue">' . $address_name . '</font>';
						}
					}
					$body_string .= '<tr>
					<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp, $default_timezone) . '</p></td>' 
					. $public_key_from . '</td>
					<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
					<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
					<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';
				}
				else
				{
					// Match Friendly Name to Key
					if($public_key_from == $full_key)
					{
						$public_key_from = '<td class="style2"><font color="blue">' . $name . '</font>';

						$body_string .= '<tr>
						<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp, $default_timezone) . '</p></td>' 
						. $public_key_from . '</td>
						<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
						<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
						<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';						
					}
				}

				$counter++;
			}
			
			$body_string .= '<tr><td colspan="5"><hr></td></tr>
			<tr><td colspan="5"><input type="text" size="5" name="show_more_receive" value="' . $show_last .'" />
			<input type="submit" name="Submit1" value="Show Last" /></td></tr></table></div></FORM>';

		} // End hide check for receive

		if($hide_send == FALSE)
		{
			$body_string .= '<strong>Showing Last <font color="blue">' . $show_last . '</font> Transactions <font color="blue">Sent From</font> this Billfold' . $name_to . '</strong><br><br>
			<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
			<th>Sent To</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

			$history_data_to = transaction_history_query(2, $show_last, $_SESSION["login_username"], $my_public_key);
			$counter = 1;

			while($counter <= 100) // 100 History Limit
			{
				$timestamp = find_string("---TIMESTAMP$counter=", "---TO", $history_data_to);
				$public_key_to = find_string("---TO$counter=", "---AMOUNT", $history_data_to);
				$amount = find_string("---AMOUNT$counter=", "---VERIFY", $history_data_to);					
				$verify = find_string("---VERIFY$counter=", "---MESSAGE", $history_data_to);
				$message = find_string("---MESSAGE$counter=", "---END$counter", $history_data_to);					

				if(empty($timestamp) == TRUE && empty($amount) == TRUE)
				{
					// No more data to search
					break;
				}

				if(empty($_GET["name_id"]) == TRUE)
				{				
					// Check if the key matches anyone in the address book
					$address_name = address_book_lookup($_SESSION["login_username"], $_SESSION["decrypt_password"], "full_key", $public_key_to, "name");

					if($public_key_to == EASY_KEY_PUBLIC_KEY)
					{
						$public_key_to = '<td class="style2"><font color="green">Your Easy Key Shortcut</font>';
					}
					else if(empty($address_name) == TRUE)
					{
						$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:150px; font-size:' . $default_public_key_font . 'px;">' . $public_key_to . '</p>';
					}
					else
					{
						$public_key_to = '<td class="style2"><font color="blue">' . $address_name . '</font>';
					}

					$body_string .= '<tr>
					<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp, $default_timezone) . '</p></td>' 
					. $public_key_to . '</td>
					<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
					<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
					<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';					
				}
				else
				{
					// Match Friendly Name to Key
					if($public_key_to == $full_key)
					{
						$public_key_to = '<td class="style2"><font color="blue">' . $name . '</font>';

						$body_string .= '<tr>
						<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp, $default_timezone) . '</p></td>' 
						. $public_key_to . '</td>
						<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
						<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
						<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';						
					}
				}

				$counter++;
			}

			$body_string .= '<tr><td colspan="5"><hr></td></tr><tr><td colspan="5"><FORM ACTION="index.php?menu=history&amp;send=listmore" METHOD="post"><input type="text" size="5" name="show_more_send" value="' . $show_last .'" /><input type="submit" name="Submit2" value="Show Last" /></FORM></td></tr>';
			$body_string .= '</table></div>';

		} // End hide check for send

		$text_bar = '<FORM ACTION="index.php?menu=history&amp;font=public_key" METHOD="post">
		<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td>
		<td style="width:250px"><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

		$quick_info = 'Verification Level represents how deep in the history the transaction exist.<br><br>
		The larger the number, the more time that all the peers have examined it and agree that it is a valid transaction.<br><br>
		You can view up to 100 past transactions that have been <u>sent from</u> or <u>sent to</u> your Billfold.';

		home_screen('Transaction History', $text_bar, $body_string , $quick_info);

		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "options" && $_SESSION["admin_login"] != TRUE)
	{
		// Username & Password Changing
		if($_GET["password"] == "change")
		{
			// Create or Change Private Key Encryption
			if(empty($_POST["current_private_key_password"]) == FALSE && empty($_POST["new_private_key_password"]) == FALSE && empty($_POST["confirm_private_key_password"]) == FALSE)
			{
				// Encrypt Private Key for first time
				$private_key_crypt = my_private_key(TRUE, $_SESSION["login_username"], $_SESSION["decrypt_password"]);
				
				if($private_key_crypt == FALSE && $_POST["new_private_key_password"] == $_POST["confirm_private_key_password"])
				{
					// First Time Encryption
					// Grab Currency Private Key, encrypt, then update database
					$my_new_crypt_private_key = AesCtr::encrypt(my_private_key(FALSE, $_SESSION["login_username"], $_SESSION["decrypt_password"]), $_POST["new_private_key_password"], 256);
					save_private_key($_SESSION["login_username"], $_SESSION["decrypt_password"], base64_encode($my_new_crypt_private_key));
					$encrypt_private_key = TRUE;
				}
				else
				{
					// Decrypt Existing Private Key, Re-encrypt with new Password
					if($_POST["new_private_key_password"] == $_POST["confirm_private_key_password"])
					{
						// Decrypt Private Key
						$decrypt_private_key = AesCtr::decrypt(my_private_key(FALSE, $_SESSION["login_username"], $_SESSION["decrypt_password"]), $_POST["current_private_key_password"], 256);
						$valid_key = find_string("-----BEGIN", "KEY-----", $decrypt_private_key); // Valid Decrypt?

						if(empty($valid_key) == FALSE) // If Empty means decrypt password was wrong
						{
							$my_new_crypt_private_key = AesCtr::encrypt($decrypt_private_key, $_POST["new_private_key_password"], 256);
							save_private_key($_SESSION["login_username"], $_SESSION["decrypt_password"], base64_encode($my_new_crypt_private_key));
							$encrypt_private_key = TRUE;
						}
					}
				}
			}

			// Remove Private Key Encryption
			if(empty($_POST["current_private_key_password"]) == FALSE && $_POST["disable_crypt"] == TRUE)
			{
				// Remove Encryption
				$decrypt_private_key = AesCtr::decrypt(my_private_key(FALSE, $_SESSION["login_username"], $_SESSION["decrypt_password"]), $_POST["current_private_key_password"], 256);
				$valid_key = find_string("-----BEGIN", "KEY-----", $decrypt_private_key); // Valid Decrypt?

				if(empty($valid_key) == FALSE) // If Empty means decrypt password was wrong
				{
					save_private_key($_SESSION["login_username"], $_SESSION["decrypt_password"], base64_encode($decrypt_private_key));
					$encrypt_private_key = 2;
				}
			}

			// Change Login Password
			if(empty($_POST["current_username"]) == FALSE && empty($_POST["new_username"]) == FALSE && empty($_POST["confirm_username"]) == FALSE)
			{
				// Attemping to change username
				if($_POST["current_username"] == $_SESSION["login_username"])
				{
					// Right username, does the new username match the confirmation username?
					if($_POST["new_username"] == $_POST["confirm_username"])
					{
						// Check if username is already taken
						$new_username = strtolower($_POST["confirm_username"]);
						$username_SHA256 = hash('sha256', $new_username);
						$username_hash = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'username' LIMIT 1"));

						if($username_SHA256 != $username_hash)
						{
							// No match for admin username, how
							// about the users?
							$username_hash = mysql_result(mysqli_query($db_connect, "SELECT id FROM `users` WHERE `username` = '$username_SHA256' LIMIT 1"));
					
							if($username_hash == "")
							{
								// Write new hash to database for username and change the session username
								$current_username_hash = hash('sha256', $_SESSION["login_username"]);

								$sql = "UPDATE `users` SET `username` = '$username_SHA256' WHERE `users`.`username` = '$current_username_hash' LIMIT 1";
								$sql2 = "UPDATE `data_cache` SET `username` = '$username_SHA256' WHERE `data_cache`.`username` = '$current_username_hash'";

								if(mysqli_query($db_connect, $sql) == TRUE)
								{
									if(mysqli_query($db_connect, $sql2) == FALSE)
									{
										write_log("Data Cache Change Failed for Old Username<br>" . $current_username_hash, "S");
									}

									// Update success, now change the session username
									$_SESSION["login_username"] = $new_username;
									$username_change = TRUE;
								}

							}
							else
							{
								// Username Already Taken
								$username_change = 2;
							}						
						}
						else
						{
							// Username Already Taken
							$username_change = 2;
						}
					}
				}
			}

			// Change Login Password
			if(empty($_POST["current_password"]) == FALSE && empty($_POST["new_password"]) == FALSE && empty($_POST["confirm_password"]) == FALSE)
			{
				$username_hash = hash('sha256', $_SESSION["login_username"]);
				$password_hash = mysql_result(mysqli_query($db_connect, "SELECT password FROM `users` WHERE `username` = '$username_hash' LIMIT 1"));
			
				$current_password_hash = hash('sha256', $_POST["current_password"]);
				$new_password_hash = hash('sha256', $_POST["new_password"]);

				// Attemping to change password
				if($current_password_hash == $password_hash)
				{
					// Right password, does the new password match the confirmation password?
					if($_POST["new_password"] == $_POST["confirm_password"])
					{
						$sql = "UPDATE `users` SET `password` = '$new_password_hash' WHERE `users`.`username` = '$username_hash' LIMIT 1";

						if(mysqli_query($db_connect, $sql) == TRUE)
						{
							$old_decrypt_password = $_SESSION["decrypt_password"];
						
							// Create decrypting password for settings and other features that only exist for that user
							$AES_SHA256_password = hash('sha256', $_POST["new_password"] . $new_password_hash);			
							$_SESSION["decrypt_password"] = $AES_SHA256_password;							
						
							// Decrypt and Re-encrypt All User Data
							$settings = mysql_result(mysqli_query($db_connect, "SELECT settings FROM `users` WHERE `username` = '$username_hash' LIMIT 1"));
							$settings = AesCtr::decrypt($settings, $old_decrypt_password, 256);
							$settings_AES = AesCtr::encrypt($settings, $AES_SHA256_password, 256);
							$sql = "UPDATE `users` SET `settings` = '$settings_AES' WHERE `users`.`username` = '$username_hash' LIMIT 1";
							mysqli_query($db_connect, $sql);
							
							$address_book = mysql_result(mysqli_query($db_connect, "SELECT address_book FROM `users` WHERE `username` = '$username_hash' LIMIT 1"));
							$address_book = AesCtr::decrypt($address_book, $old_decrypt_password, 256);
							$address_book_AES = AesCtr::encrypt($address_book, $AES_SHA256_password, 256);
							$sql = "UPDATE `users` SET `address_book` = '$address_book_AES' WHERE `users`.`username` = '$username_hash' LIMIT 1";
							mysqli_query($db_connect, $sql);

							$my_keys = mysql_result(mysqli_query($db_connect, "SELECT my_keys FROM `users` WHERE `username` = '$username_hash' LIMIT 1"));
							$my_keys = AesCtr::decrypt($my_keys, $old_decrypt_password, 256);							
							$my_keys_AES = AesCtr::encrypt($my_keys, $AES_SHA256_password, 256);
							$sql = "UPDATE `users` SET `my_keys` = '$my_keys_AES' WHERE `users`.`username` = '$username_hash' LIMIT 1";
							mysqli_query($db_connect, $sql);

							$password_change = TRUE;
						}
					}
				}
			}

			$body_text = options_screen2_user();

			if($username_change == 1)
			{
				$body_text.= '<font color="blue"><strong>Username Change Complete!</strong></font><br>';
			}
			else if($username_change == 2)
			{
				$body_text.= '<strong><font color="red">ERROR: Username [ ' . $new_username . ' ] Has Been Taken!</font></strong><br>';
			}
			else
			{
				$body_text.= '<strong>Username Has Not Been Changed</strong><br>';
			}

			if($password_change == TRUE)
			{
				$body_text.= '<font color="blue"><strong>Password Change Complete!</strong></font><br>';
			}
			else
			{
				$body_text.= '<strong>Password Has Not Been Changed</strong><br>';
			}

			if($encrypt_private_key === TRUE)
			{
				$body_text.= '<font color="blue"><strong>Private Key Encryption Complete!</strong></font>';
			}
			else if($encrypt_private_key === 2)
			{
				$body_text.= '<font color="red"><strong>Private Key Encryption Has Been Removed</strong></font>';
			}
			else
			{
				$body_text.= '<strong>Private Key Encryption Has Not Been Changed</strong>';
			}

		} // End username/password change check

		if($_GET["refresh"] == "change")
		{
			// Save value in encrypted database
			save_default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "refresh_realtime_home", intval($_POST["home_update"]));
			save_default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "default_timezone", $_POST["timezone"]);
			
			$body_text = options_screen2_user();
			$body_text .= '<font color="blue"><strong>Settings Saved!</strong></font><br>';			
		} // End refresh update save
		else if(empty($_GET["password"]) == TRUE && empty($_GET["refresh"]) == TRUE)
		{
			$body_text = options_screen2_user();
		}

		if($_GET["newkeys"] == "confirm")
		{
			set_time_limit(999);
			$bits_level = intval($_POST["new_key_bits"]);

			if($bits_level == "")
			{
				$bits_level = 1536;
			}

			$time1 = time();
			$keys = generate_new_keys($bits_level, TRUE);
			$new_private_key = base64_encode($keys[0]);
			$new_public_key = base64_encode($keys[1]);

			if($new_private_key != "" && $new_public_key != "")
			{
				save_private_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $new_private_key);
				save_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $new_public_key);
				$body_text .= '<font color="green"><strong>New Private &amp; Public Key Pair Generated! (It Took ' . (time() - $time1) . ' Second(s) To Generate)</strong></font><br>';
			}
			else
			{
				$body_text .= '<font color="red"><strong>New Key Creation Failed!</strong></font><br>';
			}
		}

		if($_GET["manage"] == "tabs")
		{
			home_screen("Show/Hide Tabs", NULL, options_screen4_user() , "You can hide or show certain tabs at the top.");
			exit;
		}

		if($_GET["tabs"] == "change")
		{
			$standard_tabs_settings = standard_tab_settings($_POST["tab_peerlist"], $_POST["tab_trans_queue"], $_POST["tab_send_receive"], 
			$_POST["tab_history"], $_POST["tab_address"], $_POST["tab_system"], $_POST["tab_backup"], $_POST["tab_tools"]);

			save_default_settings($_SESSION["login_username"], $_SESSION["decrypt_password"], "standard_tabs_settings", $standard_tabs_settings);

			$text_bar = '<font color="blue"><strong>Standard Tab Settings Updated</strong></font><br>';
			
			home_screen("Show/Hide Tabs", $text_bar, options_screen4_user() , "You can hide or show certain tabs at the top.");
			exit;
		}

		$quick_info = 'You may change the username and password individually or at the same time.
		<br><br>Remember that usernames and passwords are Case Sensitive.
		<br><br><strong>Private Key</strong> password will use AES-256 bit encryption to save your private key in the database.
		<br>You will be required to enter a password anytime you send currency to another public key.
		<br><i><strong>Note:</strong> First time creating password, use the same password in all three fields.</i>
		<br><br><strong>Generate New Keys</strong> will create a new random key pair and save it in the database.
		<br><br><strong>Check for Updates</strong> will check for any program updates that can be downloaded directly into Timekoin.';

		if($_GET["storage_key"] == "new")
		{
			$bits_level = intval($_POST["crypt_bits"]);

			if($bits_level == "")
			{
				$bits_level = 1536;
			}
			else
			{
				// Create New Key Pair
				set_time_limit(999);
				if($bits_level < 1536) { $bits_level = 1536; }
				$time1 = time();
				$keys = generate_new_keys($bits_level, TRUE);
				$new_private_key = base64_encode($keys[0]);
				$new_public_key = base64_encode($keys[1]);
				$message = '<br><font color="green"><strong>New Private &amp; Public Key Pair Generated! (It Took ' . (time() - $time1) . ' Second(s) To Create)</strong></font>';
			}

			$clipboard_copy = '<script>
			function myPrivateKey()
			{
				var copyText = document.getElementById("current_private_key");
				copyText.select();
				copyText.setSelectionRange(0, 99999)
				document.execCommand("copy");
				var tooltip = document.getElementById("myTooltip");
				tooltip.innerHTML = "Copy Complete!";
			}
			function myPublicKey()
			{
				var copyText = document.getElementById("current_public_key");
				copyText.select();
				copyText.setSelectionRange(0, 99999)
				document.execCommand("copy");
				var tooltip = document.getElementById("myTooltip2");
				tooltip.innerHTML = "Copy Complete!";
			}</script>';

			$body_string = '<FORM ACTION="index.php?menu=options&amp;storage_key=new" METHOD="post">
			<strong>Bits Size [1,536 to 17,408]</strong> (Caution: High Values Take a Lot of Time to Create New Keys!)<br>
			<input type="number" name="crypt_bits" min="1536" max="17408" size="6" value="' . $bits_level . '"/>
			<input type="submit" name="Submit" value="Create New Key Pair" onclick="showWait()" /></FORM><br>' . $clipboard_copy . '
			<strong><font color="blue">New Private Key</font></strong><br>
			<textarea id="current_private_key" rows="10" cols="90" READONLY>' . $new_private_key . '</textarea><br>
			<button title="Copy Private Key to Clipboard" onclick="myPrivateKey()"><span id="myTooltip">Copy Private Key</span></button><hr>
			<strong><font color="green">New Public Key</font></strong><br>
			<textarea id="current_public_key" rows="8" cols="90" READONLY>' . $new_public_key . '</textarea><br>
			<button title="Copy Public Key to Clipboard" onclick="myPublicKey()"><span id="myTooltip2">Copy Public Key</span></button><br>' . $message;

			$quick_info = '<strong>Storage Keys</strong> can be created to store a balance offline.<br><br>
			<strong>Do Not</strong> share your <strong>Private Key</strong> with anyone for any reason.<br><br>
			The <strong>Private Key</strong> encrypts all transactions for the given <strong>Public Key</strong>.<br><br>
			Save both keys in a password protected document or external device that you can secure (CD, Flash Drive, Printed Paper, etc.)';

			home_screen("Options &amp; Personal Settings", '<strong><font color="purple">Create New Storage Keys</font></strong>', $body_string , $quick_info);
			exit;
		}

		home_screen("Options &amp; Personal Settings", options_screen(), $body_text , $quick_info);
		exit;
	}	
//****************************************************************************
	if($_GET["menu"] == "backup" && $_SESSION["admin_login"] != TRUE)
	{
		if($_GET["dorestore"] == "private" && empty($_POST["restore_private_key"]) == FALSE)
		{
			save_private_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $_POST["restore_private_key"]);

			if(my_private_key(TRUE, $_SESSION["login_username"], $_SESSION["decrypt_password"]) == TRUE)
			{
				// Private Key Encrypted
				$key_message = '<font color="green"><strong>*** <font color="red">Encrypted</font> Private Key Restore Complete! ***</strong></font><br>';
			}
			else
			{
				$key_message = '<font color="green"><strong>*** Private Key Restore Complete! ***</strong></font><br>';
			}
		}

		if($_GET["dorestore"] == "public" && empty($_POST["restore_public_key"]) == FALSE)
		{
			save_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $_POST["restore_public_key"]);
			$key_message = '<font color="green"><strong>*** Public Key Restore Complete! ***</strong></font><br>';
		}

		$my_public_key = base64_encode(my_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"]));
		$my_private_key = base64_encode(my_private_key(FALSE, $_SESSION["login_username"], $_SESSION["decrypt_password"]));

		if(my_private_key(TRUE, $_SESSION["login_username"], $_SESSION["decrypt_password"]) == TRUE && $_GET["keys"] != "restore")
		{
			$key_encrypted = '<br><font color="red"><strong>WARNING:</strong></font> <font color="blue"><strong><i>Private Key Is Encrypted</i></strong></font>';
		}

		$clipboard_copy = '<script>
		function myPrivateKey()
		{
			var copyText = document.getElementById("current_private_key");
			copyText.select();
			copyText.setSelectionRange(0, 99999)
			document.execCommand("copy");
			var tooltip = document.getElementById("myTooltip");
			tooltip.innerHTML = "Copy Complete!";
		}
		function myPublicKey()
		{
			var copyText = document.getElementById("current_public_key");
			copyText.select();
			copyText.setSelectionRange(0, 99999)
			document.execCommand("copy");
			var tooltip = document.getElementById("myTooltip2");
			tooltip.innerHTML = "Copy Complete!";
		}</script>';

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

		if($_GET["keys"] == "download")
		{
			$content = '---TKPRIVATEKEY=' . $my_private_key . '---ENDTKPRIVATEKEY' . "\n\r";
			$content.= '---TKPUBLICKEY=' . $my_public_key . '---ENDTKPUBLICKEY';			
			$length = strlen($content);
			header('Content-Description: File Transfer');
			header('Content-Type: text/plain');
			header('Content-Disposition: attachment; filename=TK-Client-Keys.txt');
			header('Content-Transfer-Encoding: binary');
			header('Content-Length: ' . $length);
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Expires: 0');
			header('Pragma: public');
			echo $content;
			exit;
		}

		if($_GET["keys"] == "restore")
		{
			// Restore Server Private & Public Keys
			$new_server_keys = file_upload("key_file", TRUE);
			if($new_server_keys != 1)
			{
				$restore_private_key = find_string("---TKPRIVATEKEY=", "---ENDTKPRIVATEKEY", $new_server_keys);
				$restore_public_key = find_string("---TKPUBLICKEY=", "---ENDTKPUBLICKEY", $new_server_keys);

				if(empty($restore_private_key) == FALSE && empty($restore_public_key) == FALSE)
				{
					save_private_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $restore_private_key);
					save_public_key($_SESSION["login_username"], $_SESSION["decrypt_password"], $restore_public_key);

					$my_private_key = $restore_private_key;
					$my_public_key = $restore_public_key;

					if(my_private_key(TRUE, $_SESSION["login_username"], $_SESSION["decrypt_password"]) == TRUE)
					{
						// Private Key Encrypted
						$key_message = '<font color="green"><strong>*** <font color="red">Encrypted</font> Private & Public Key Restore Complete! ***</strong></font><br>';
					}
					else
					{
						$key_message = '<font color="green"><strong>*** Private & Public Key Restore Complete! ***</strong></font><br>';
					}
				}
				else
				{
					$key_message = '<font color="red"><strong>COULD NOT FIND THE PRIVATE & PUBLIC KEYS</strong></font><br>';
				}
			}
			else
			{
				// Delete Error, Alert User!
				$key_message = '<font color="red"><strong>SECURITY ISSUE! The Key File Was NOT Deleted After Upload.<br>You Will Need to Manually Delete It From the Plugins Folder.</strong></font><br>';
			}
		}

		$text_bar = '<table border="0" cellpadding="6"><tr><td><strong><font color="blue">Private Key</font> to encrypt transactions:</strong>' . $key_encrypted . '</td></tr>
		<tr><td style="width:672px"><textarea id="current_private_key" readonly="readonly" rows="8" style="width: 100%; max-width: 100%;">' . $my_private_key . '</textarea><br>
		<button title="Copy Private Key to Clipboard" onclick="myPrivateKey()"><span id="myTooltip">Copy Private Key</span></button></td></tr></table>
		<table border="0" cellpadding="6"><tr><td colspan="2"><strong><font color="green">Public Key</font> to receive:</strong></td></tr>
		<tr><td colspan="2" style="width:672px"><textarea id="current_public_key" readonly="readonly" rows="6" style="width: 100%; max-width: 100%;">' . $my_public_key . '</textarea><br>
		<button title="Copy Public Key to Clipboard" onclick="myPublicKey()"><span id="myTooltip2">Copy Public Key</span></button></td></tr>
		<tr><td colspan="2"><hr></td></tr><tr><td><FORM ACTION="index.php?menu=backup&amp;keys=download" METHOD="post"><input type="submit" value="Download Keys"/></FORM></td>
		<td>' . $key_message . '<strong>Use the Browse Button to Select the Key File to Restore</strong><br><br>
		<FORM ENCTYPE="multipart/form-data" METHOD="POST" ACTION="index.php?menu=backup&amp;keys=restore">
		<INPUT NAME="key_file" TYPE="file" SIZE=32><br><br>
		<input type="submit" name="SubmitNew" value="Restore Keys" onclick="return confirm(\'This Will Over-Write Your Existing Private & Public Keys. Continue?\');" /></FORM>
		</td></tr></table>';

		$quick_info = '<strong>Do Not</strong> share your <strong>Private Key</strong> with anyone for any reason.<br><br>
		The <strong>Private Key</strong> encrypts all transactions from your server.<br><br>
		You should make a backup of both keys in case you want to transfer your balance to a new client or restore from a client failure.<br><br>
		<strong><font color="blue">Download Keys</font></strong> will create a text file that can be used to restore the keys on this or a different client.<br><br>
		Save both keys in a password protected document or external device that you can secure (CD, Flash Drive, Printed Paper, etc.)';

		home_screen('Backup &amp; Restore Keys', $clipboard_copy . $text_bar, $body_string , $quick_info);
		exit;		
	}
//****************************************************************************
//****************************************************************************
	if(($_GET["menu"] == "home" || empty($_GET["menu"]) == TRUE) && $_SESSION["admin_login"] == TRUE)
	{
		$body_string = '<strong>Last 20 <font color="green">Received</font> Transaction Amounts to My Billfold</strong><br><canvas id="recv_graph" width="690" height="300">Your Web Browser does not support HTML5 Canvas.</canvas>';
		$body_string .= '<hr>';
		$body_string .= '<strong>Last 20 <font color="blue">Sent</font> Transaction Amounts from My Billfold</strong><br><canvas id="sent_graph" width="690" height="300">Your Web Browser does not support HTML5 Canvas.</canvas>';
		$body_string .= '<hr>';
		$body_string .= '<strong>Timekoin Network - Total Transactions per Cycle (Last 25 Cycles)</strong><br><canvas id="trans_total" width="690" height="200">Your Web Browser does not support HTML5 Canvas.</canvas>';
		$body_string .= '<hr>';
		$body_string .= '<strong>Timekoin Network - Total Amounts Sent per Cycle (Last 20 Cycles)</strong><br><canvas id="amount_total" width="690" height="400">Your Web Browser does not support HTML5 Canvas.</canvas>';

		$display_balance = db_cache_balance(my_public_key());

		$update_available = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'update_available' LIMIT 1"));

		if($update_available == TRUE)
		{
			$update_available = '<tr><td><font color="green"><strong>*** NEW SOFTWARE UPDATE AVAILABLE ***</strong></font></td></tr>';
		}
		else
		{
			$update_available = NULL;
		}

		$text_bar = '<table border="0"><tr><td style="width:325px"><strong>Current Billfold Balance: <font color="green">' . $display_balance_GUI . '</font> TK</strong></td></tr>
			<tr>' . $update_available . '</table>';

		$quick_info = 'This section will contain helpful information about each tab in the software.';

		$home_update = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,0);

		if($home_update < 60 && $home_update != 0) // Cap home updates refresh to 1 minute
		{
			$home_update = 60;
		}
		
		if($display_balance === "NA")
		{
			$display_balance_GUI = '<font color="red">Waiting For Network</font>';
			$home_update = 5;
		}
		else
		{
			$display_balance_GUI = number_format($display_balance);
		}

		home_screen("Home", $text_bar, $body_string, $quick_info , $home_update);
		exit;
	}
//****************************************************************************	
	if($_GET["menu"] == "address" && $_SESSION["admin_login"] == TRUE)
	{
		if($_GET["font"] == "public_key")
		{
			if(empty($_POST["font_size"]) == FALSE)
			{
				// Save value in database
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["font_size"] . "' WHERE `options`.`field_name` = 'public_key_font_size' LIMIT 1";
				mysqli_query($db_connect, $sql);

				header("Location: index.php?menu=address");
				exit;
			}
		}
		else
		{
			$default_public_key_font = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
		}

		if($_GET["task"] == "delete")
		{
			// Remove Address Entry
			mysqli_query($db_connect, "DELETE FROM `address_book` WHERE `address_book`.`id` = " . $_GET["name_id"]);
		}

		if($_GET["task"] == "save_new")
		{
			// Save New Address
			$full_key = $_POST["full_key"];
			
			if(empty($_POST["easy_key"]) == FALSE)
			{
				// Lookup Easy Key
				$easy_key = $_POST["easy_key"];

				// Translate Easy Key to Public Key and fill in field with
				$full_key = filter_sql(easy_key_lookup($easy_key));

				if($full_key === 0 || empty($full_key) == TRUE)
				{
					$easy_key_fail = TRUE;
				}
			}

			if($easy_key_fail == FALSE)
			{
				mysqli_query($db_connect, "INSERT INTO `address_book` (`id`, `name`, `easy_key`, `full_key`) VALUES
					(NULL, '" . $_POST["name"] . "', '$easy_key', '$full_key')");
			}
		}

		if($_GET["task"] == "new" || $easy_key_fail == TRUE)
		{
			if($easy_key_fail == TRUE)
			{
				$easy_messasge = '<font color="red"><strong>Easy Key Lookup Failed</strong></font>';
			}
			
			// New Address Form
			$body_string = '<FORM ACTION="index.php?menu=address&amp;task=save_new" METHOD="post">
			<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" >
			<tr><th>Address Name</th><th>Easy Key</th><th>Full Public Key</th><th></th><th></th></tr>
			<tr><td class="style2" valign="top"><input type="text" name="name" size="16" value="'.$_POST["name"].'" /></td>
			<td class="style2" valign="top"><input type="text" name="easy_key" size="16" value="'.$easy_key.'" /><br>'.$easy_messasge.'</td>
			<td class="style2"><textarea name="full_key" rows="6" style="width: 100%; max-width: 100%;"></textarea></td>			 
			<td valign="top"><input type="image" src="img/save-icon.gif" title="Save New Address" name="submit1" border="0"></td>
			<td valign="top"></td></tr></table></div></FORM>';
		}

		if($_GET["task"] == "edit_save")
		{
			// Save New Address
			$full_key = $_POST["full_key"];
			
			if(empty($_POST["easy_key"]) == FALSE)
			{
				// Lookup Easy Key
				$easy_key = $_POST["easy_key"];
				$full_key = easy_key_lookup($easy_key);

				if($full_key === 0 || empty($full_key) == TRUE)
				{
					$easy_key_edit_fail = TRUE;
				}
			}

			if($easy_key_edit_fail == FALSE)
			{
				mysqli_query($db_connect, "UPDATE `address_book` SET `name` = '" . $_POST["name"] . "', `easy_key` = '$easy_key', `full_key` = '$full_key' WHERE `address_book`.`id` = " . $_GET["name_id"]);
			}
		}

		if($_GET["task"] == "edit" || $easy_key_edit_fail == TRUE)
		{
			if($easy_key_edit_fail == TRUE)
			{
				$easy_edit_messasge = '<font color="red"><strong>Easy Key Lookup Failed!<BR>Spelling is Case Sensitive.</strong></font>';
				$name = $_POST["name"];
				$easy_key = $_POST["easy_key"];
				$full_key = $_POST["full_key"];
			}			
			else
			{
				// Edit Address
				$name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
				$easy_key = mysql_result(mysqli_query($db_connect, "SELECT easy_key FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
				$full_key = mysql_result(mysqli_query($db_connect, "SELECT full_key FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
			}

			$body_string = '<FORM ACTION="index.php?menu=address&amp;task=edit_save&amp;name_id=' . $_GET["name_id"] . '" METHOD="post">
			<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" >
			<tr><th>Address Name</th><th>Easy Key</th><th>Full Public Key</th><th></th><th></th></tr><tr>
			<td class="style2" valign="top"><input type="text" name="name" size="16" value="' . $name . '"/></td>
			<td class="style2" valign="top"><input type="text" name="easy_key" size="16" value="' . $easy_key . '" /><br>'.$easy_edit_messasge.'</td>
			<td class="style2"><textarea name="full_key" rows="6" style="width: 100%; max-width: 100%;">' . $full_key . '</textarea></td>			 
			<td valign="top"><input type="image" src="img/edit-icon.gif" title="Edit Address" name="submit1" border="0"></td>
			<td valign="top"></td></tr></table></div></FORM>';
		}

		if($_GET["task"] != "new" && $_GET["task"] != "edit" && $easy_key_fail == FALSE && $easy_key_edit_fail == FALSE) // Default View
		{
			$sql = "SELECT * FROM `address_book` ORDER BY `address_book`.`name` ASC";
			$sql_result = mysqli_query($db_connect, $sql);
			$sql_num_results = mysqli_num_rows($sql_result);

			$body_string = '<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" >
				<tr><th>Address Name</th><th>Easy Key</th><th>Full Public Key</th><th></th><th></th><th></th></tr>';

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysqli_fetch_array($sql_result);
				$body_string .= '<tr><td class="style2"><p style="word-wrap:break-word; width:175px; font-size:12px;">' . 	
					$sql_row["name"] . 
					' <a href="index.php?menu=history&amp;name_id=' . $sql_row["id"] . '" title="' . $sql_row["name"] . ' History"><img src="img/timekoin_history.png" style="float: right;"></a></p>
					</td><td class="style1"><p style="word-wrap:break-word; width:175px; font-size:12px;">' . $sql_row["easy_key"] . 
					'</p></td><td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . $sql_row["full_key"] . '</p></td>
					<td><a href="index.php?menu=address&amp;task=delete&amp;name_id=' . $sql_row["id"] . '" title="Delete ' . $sql_row["name"] . '" onclick="return confirm(\'Delete ' . $sql_row["name"] . '?\');"><img src="img/hr.gif"></a></td>
					<td><a href="index.php?menu=address&amp;task=edit&amp;name_id=' . $sql_row["id"] . '" title="Edit ' . $sql_row["name"] . '"><img src="img/edit-icon.gif"></a></td>
					<td><a href="index.php?menu=send&amp;name_id=' . $sql_row["id"] . '" title="Send Koins to ' . $sql_row["name"] . '"><img src="img/timekoin_send.png"></a></td></tr>';
			}

			$body_string .= '<tr><td colspan="6"><hr></td></tr><tr>
				<td colspan="6"><FORM ACTION="index.php?menu=address&amp;task=new" METHOD="post"><input type="submit" value="Add New Address"/></FORM></td></tr></table></div>';
		}

		if($_GET["task"] != "new" && $easy_key_fail == FALSE && $easy_key_edit_fail == FALSE) // Default View
		{		
			$quick_info = "The <strong>Address Book</strong> allows long, obscure public keys to be translated to friendly names.<br><br>
	Transactions can also quickly be created from here.<br><br>
	The scribe next to the name can be clicked to bring up a custom history of all transactions to and from the name selected.";
		}
		else
		{
			$quick_info = "<strong>Address Name</strong> is friendly name to associate with the Public Key.<br><br>
				You can enter an <strong>Easy Key</strong> address and Timekoin will attempt to lookup the full key when saving.<br><br>
				If no Easy Key is known or needed, just enter the full Public Key instead.";
		}

		$text_bar = '<FORM ACTION="index.php?menu=address&amp;font=public_key" METHOD="post">
			<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td><td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

		home_screen("Address Book", $text_bar, $body_string, $quick_info);
		exit;
	}	
//****************************************************************************
	if($_GET["menu"] == "peerlist" && $_SESSION["admin_login"] == TRUE)
	{
		if($_GET["remove"] == "peer")
		{
			// Manually remove this peer
			$sql = "DELETE FROM `active_peer_list` WHERE `active_peer_list`.`IP_Address` = '" . $_POST["ip"] . "' AND `active_peer_list`.`domain` = '" . $_POST["domain"] . "' LIMIT 1";
			mysqli_query($db_connect, $sql);
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
			mysqli_query($db_connect, $sql);
		}

		if($_GET["save"] == "newpeer" && empty($_POST["edit_port"]) == FALSE)
		{
			// Manually insert new peer
			$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat` ,`join_peer_list` ,`failed_sent_heartbeat` , `code`)
				VALUES ('" . $_POST["edit_ip"] . "', '" . $_POST["edit_domain"] . "', '" . $_POST["edit_subfolder"] . "', '" . $_POST["edit_port"] . "', UNIX_TIMESTAMP() , UNIX_TIMESTAMP() , '0', '" . $_POST["edit_code"] . "')";
			mysqli_query($db_connect, $sql);
		}

		if($_GET["save"] == "firstcontact")
		{
			// Wipe Current First Contact Servers List and Save the New List
			$field_numbers = intval($_POST["field_numbers"]);

			if($field_numbers > 0)
			{
				mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = 'first_contact_server'");

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

						mysqli_query($db_connect, $sql);
					}
					
					$field_numbers--;
				}
			}
		}

		if($_GET["edit"] == "peer")
		{
			if($_GET["type"] == "new")
			{
				// Manually add a new peer
				$body_string = '<FORM ACTION="index.php?menu=peerlist&amp;save=newpeer" METHOD="post">
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0"><tr><th>IP Address</th>
				<th>Domain</th><th>Subfolder</th><th>Port Number</th><th>Code</th><th></th><th></th></tr><tr>
				<td class="style2"><input type="text" name="edit_ip" size="22" /></td>
				<td class="style2"><input type="text" name="edit_domain" size="14" /></td>
				<td class="style2"><input type="text" name="edit_subfolder" size="10" /></td>
				<td class="style2"><input type="text" name="edit_port" size="5" /></td>
				<td class="style2"><input type="text" name="edit_code" size="5" value="guest"/></td>				 
				<td><input type="image" src="img/save-icon.gif" title="Save New Peer" name="submit1" border="0"></td><td>
				 </td></tr></table></div></FORM>';
			}
			else if($_GET["type"] == "firstcontact")
			{
				$sql = "SELECT *  FROM `options` WHERE `field_name` = 'first_contact_server'";
				$sql_result = mysqli_query($db_connect, $sql);
				$sql_num_results = mysqli_num_rows($sql_result) + 2;
				$counter = 1;
				$body_string = '<FORM ACTION="index.php?menu=peerlist&amp;save=firstcontact" METHOD="post">
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0"><tr><th>IP Address</th>
				<th>Domain</th><th>Subfolder</th><th>Port Number</th><th>Code</th><th></th></tr>';				

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysqli_fetch_array($sql_result);

					$peer_ip = find_string("---ip=", "---domain", $sql_row["field_data"]);
					$peer_domain = find_string("---domain=", "---subfolder", $sql_row["field_data"]);
					$peer_subfolder = find_string("---subfolder=", "---port", $sql_row["field_data"]);
					$peer_port_number = find_string("---port=", "---code", $sql_row["field_data"]);
					$peer_port_code = find_string("---code=", "---end", $sql_row["field_data"]);
				
					$body_string .= '<tr><td class="style2"><input type="text" name="first_contact_ip' . $counter . '" size="13" value="' . $peer_ip . '" /><br><br></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_domain' . $counter . '" size="20" value="' . $peer_domain . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_subfolder' . $counter . '" size="10" value="' . $peer_subfolder . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_port' . $counter . '" size="5" value="' . $peer_port_number . '" /></td>
					<td class="style2" valign="top"><input type="text" name="first_contact_code' . $counter . '" size="10" value="' . $peer_port_code . '" /></td>
					</tr>';

					$counter++;
				}

				$body_string .= '<input type="hidden" name="field_numbers" value="' . ($counter - 1) . '">
					<tr><td colspan="2"><input type="submit" value="Save First Contact Servers"/></td></tr>';
				$body_string .= '</table></div></FORM>';
			}
			else
			{
				// Manually edit this peer
				$sql = "SELECT * FROM `active_peer_list` WHERE `IP_Address` = '" . $_POST["ip"] ."' AND `domain` = '" . $_POST["domain"] ."' LIMIT 1";
				$sql_result = mysqli_query($db_connect, $sql);
				$sql_row = mysqli_fetch_array($sql_result);

				if($sql_row["join_peer_list"] == 0)
				{
					$perm_peer1 = "SELECTED";
				}
				else
				{
					$perm_peer2 = "SELECTED";
				}

				$body_string = '<FORM ACTION="index.php?menu=peerlist&amp;save=peer" METHOD="post">
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0"><tr><th>IP Address</th>
				<th>Domain</th><th>Subfolder</th><th>Port Number</th><th>Code</th><th></th><th></th></tr><tr>
				<td class="style2"><input type="text" name="edit_ip" size="22" value="' . $sql_row["IP_Address"] . '" /><br><br>
				<select name="perm_peer"><option value="expires" ' . $perm_peer2 . '>Purge When Inactive</option><option value="perm" ' . $perm_peer1 . '>Permanent Peer</select></td>
				<td class="style2" valign="top"><input type="text" name="edit_domain" size="14" value="' . $sql_row["domain"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_subfolder" size="10" value="' . $sql_row["subfolder"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_port" size="5" value="' . $sql_row["port_number"] . '" /></td>
				<td class="style2" valign="top"><input type="text" name="edit_code" size="5" value="' . $sql_row["code"] . '" /></td>
				<td valign="top"><input type="hidden" name="update_ip" value="' . $sql_row["IP_Address"] . '">
				<input type="hidden" name="update_domain" value="' . $sql_row["domain"] . '">
				<input type="image" src="img/save-icon.gif" title="Save Settings" name="submit1" border="0">
				</td></tr></table></div></FORM>';
			}

			$sql = "SELECT * FROM `active_peer_list`";
			$active_peers = mysqli_num_rows(mysqli_query($db_connect, $sql));

			$sql = "SELECT * FROM `new_peers_list`";
			$new_peers = mysqli_num_rows(mysqli_query($db_connect, $sql));

			$peer_number_bar = '<strong>Active Peers: <font color="green">' . $active_peers . '</font>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Peers in Reserve: <font color="blue">' . $new_peers . '</font></strong>';

			$quick_info = 'Shows all Active Peers.<br><br>
				You can manually delete or edit peers in this section.<br><br>
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

			$sql_result = mysqli_query($db_connect, $sql);
			$sql_num_results = mysqli_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysqli_fetch_array($sql_result);

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
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $sql_row["IP_Address"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; width:155px; font-size:11px;">' . $permanent1 . $sql_row["domain"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; width:80px; font-size:11px;">' . $permanent1 . $sql_row["subfolder"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $sql_row["port_number"] . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $last_heartbeat . $permanent2 . '</p></td>
				 <td class="style2"><p style="word-wrap:break-word; font-size:11px;">' . $permanent1 . $joined . $permanent2 . '</p></td>';

				if($_GET["show"] == "reserve")
				{
					$body_string .= '<td></td><td></td></tr>';
				}
				else
				{
					$body_string .= '<td><FORM ACTION="index.php?menu=peerlist&amp;remove=peer" METHOD="post"><input type="image" src="img/hr.gif" title="Delete Peer" name="remove' . $i . '" border="0">
					 <input type="hidden" name="ip" value="' . $sql_row["IP_Address"] . '">
					 <input type="hidden" name="domain" value="' . $sql_row["domain"] . '">
					 </FORM></td><td>
					 <FORM ACTION="index.php?menu=peerlist&amp;edit=peer" METHOD="post"><input type="image" src="img/edit-icon.gif" title="Edit Peer" name="edit' . $i . '" border="0">
					 <input type="hidden" name="ip" value="' . $sql_row["IP_Address"] . '">
					 <input type="hidden" name="domain" value="' . $sql_row["domain"] . '">
					 </FORM>
					 </td></tr>';
				}
			}

			$body_string .= '<tr><td colspan="8"><hr></td></tr><tr><td colspan="2"><FORM ACTION="index.php?menu=peerlist&amp;show=reserve" METHOD="post"><input type="submit" value="Show Reserve Peers"/></FORM></td>
				<td colspan="3"><FORM ACTION="index.php?menu=peerlist&amp;edit=peer&amp;type=new" METHOD="post"><input type="submit" value="Add New Peer"/></FORM></td>
				<td colspan="4"><FORM ACTION="index.php?menu=peerlist&amp;edit=peer&amp;type=firstcontact" METHOD="post"><input type="submit" value="First Contact Servers"/></FORM></td></tr></table></div>';

			$sql = "SELECT * FROM `new_peers_list`";
			$new_peers = mysqli_num_rows(mysqli_query($db_connect, $sql));		

			if($_GET["show"] == "reserve")
			{
				$sql = "SELECT * FROM `active_peer_list`";
				$sql_num_results = mysqli_num_rows(mysqli_query($db_connect, $sql));
			}

			$peer_transaction_start_blocks = mysql_result(mysqli_query($db_connect, "SELECT * FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_start_blocks' LIMIT 1"),0,"field_data");
			$peer_transaction_performance = mysql_result(mysqli_query($db_connect, "SELECT * FROM `main_loop_status` WHERE `field_name` = 'peer_transaction_performance' LIMIT 1"),0,"field_data");

			$peer_number_bar = '<table border="0" cellspacing="0" cellpadding="0"><tr><td style="width:125px"><strong>Active Peers: <font color="green">' . $sql_num_results . '</font></strong></td>
				<td style="width:175px"><strong>Peers in Reserve: <font color="blue">' . $new_peers . '</font></strong></td></tr></table>';

			$quick_info = 'Shows all Active Peers.<br><br>You can manually delete or edit peers in this section.
				<br><br>Peers in <font color="blue">Blue</font> will not expire after 5 minutes of inactivity.';

			$home_update = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");

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
	if($_GET["menu"] == "system" && $_SESSION["admin_login"] == TRUE)
	{
		if($_GET["server_settings"] == "change")
		{
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_require"] . "' WHERE `options`.`field_name` = 'email_Required' LIMIT 1");
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_from_address"] . "' WHERE `options`.`field_name` = 'email_FromAddress' LIMIT 1");			
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_from_name"] . "' WHERE `options`.`field_name` = 'email_FromName' LIMIT 1");
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_host_address"] . "' WHERE `options`.`field_name` = 'email_Host' LIMIT 1");
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_password"] . "' WHERE `options`.`field_name` = 'email_Password' LIMIT 1");
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_port"] . "' WHERE `options`.`field_name` = 'email_Port' LIMIT 1");
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_auth"] . "' WHERE `options`.`field_name` = 'email_SMTPAuth' LIMIT 1");
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . $_POST["email_username"] . "' WHERE `options`.`field_name` = 'email_Username' LIMIT 1");

			$server_code .= '<br><font color="blue"><strong>System Settings Updated...</strong></font>';
		}

		$body_string = system_screen();

		$select_bar = '<strong><font color="green">E-Mail SMTP Settings</font></strong>';
		$quick_info = 'If <strong>Email Confirmation</strong> is enabled, all new accounts will be e-mailed an activation code via PHPMailer.<br><br>
		All non-activated accounts are removed after 24 hours.<br><br>
		Gmail Reference:<br>
		<strong>SMTP:</strong><br>smtp.gmail.com<br>
		<strong>Port:</strong><br>587<br>
		<strong>Email Auth:</strong><br>tls';

		home_screen('System Settings', $select_bar . $server_code, $body_string , $quick_info);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "options" && $_SESSION["admin_login"] == TRUE)
	{
		if($_GET["password"] == "change")
		{
			if(empty($_POST["current_private_key_password"]) == FALSE && empty($_POST["new_private_key_password"]) == FALSE && empty($_POST["confirm_private_key_password"]) == FALSE)
			{
				// Encrypt Private Key for first time
				$new_record_check = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'private_key_crypt' LIMIT 1"));

				if($new_record_check == "" && $_POST["new_private_key_password"] == $_POST["confirm_private_key_password"])
				{
					// Encrypted Private Key Marker does not exist, create it
					if(mysqli_query($db_connect, "INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('private_key_crypt', '1')") == TRUE)
					{
						// First Time Encryption
						// Grab Currency Private Key, encrypt, then update database
						$my_new_crypt_private_key = AesCtr::encrypt(my_private_key(), $_POST["new_private_key_password"], 256);
						
						$sql = "UPDATE `my_keys` SET `field_data` = '$my_new_crypt_private_key' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";

						if(mysqli_query($db_connect, $sql) == TRUE)
						{
							$encrypt_private_key = TRUE;
						}
					}
				}
				else
				{
					// Decrypt Existing Private Key, Re-encrypt with new Password
					if($_POST["new_private_key_password"] == $_POST["confirm_private_key_password"])
					{
						// Decrypt Private Key
						$decrypt_private_key = AesCtr::decrypt(my_private_key(), $_POST["current_private_key_password"], 256);
						$valid_key = find_string("-----BEGIN", "KEY-----", $decrypt_private_key); // Valid Decrypt?

						if(empty($valid_key) == FALSE) // If Empty means decrypt password was wrong
						{
							$my_new_crypt_private_key = AesCtr::encrypt($decrypt_private_key, $_POST["new_private_key_password"], 256);
							
							$sql = "UPDATE `my_keys` SET `field_data` = '$my_new_crypt_private_key' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";
							if(mysqli_query($db_connect, $sql) == TRUE)
							{
								$encrypt_private_key = TRUE;
							}
						}
					}
				}
			}

			if(empty($_POST["current_private_key_password"]) == FALSE && $_POST["disable_crypt"] == TRUE)
			{
				// Remove Encryption
				$decrypt_private_key = AesCtr::decrypt(my_private_key(), $_POST["current_private_key_password"], 256);
				$valid_key = find_string("-----BEGIN", "KEY-----", $decrypt_private_key); // Valid Decrypt?

				if(empty($valid_key) == FALSE) // If Empty means decrypt password was wrong
				{
					$sql = "UPDATE `my_keys` SET `field_data` = '$decrypt_private_key' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";
					if(mysqli_query($db_connect, $sql) == TRUE)
					{
						if(mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = 'private_key_crypt'") == TRUE)
						{
							$encrypt_private_key = 2;							
						}
					}
				}
			}

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

						if(mysqli_query($db_connect, $sql) == TRUE)
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
				$password_hash = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'password' LIMIT 1"),0,"field_data");
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

						if(mysqli_query($db_connect, $sql) == TRUE)
						{
							$password_change = TRUE;
						}
					}
				}
			}

			$body_text = options_screen2();

			if($username_change == TRUE)
			{
				$body_text.= '<font color="blue"><strong>Username Change Complete!</strong></font><br>';
			}
			else
			{
				$body_text.= '<strong>Username Has Not Been Changed</strong><br>';
			}

			if($password_change == TRUE)
			{
				$body_text.= '<font color="blue"><strong>Password Change Complete!</strong></font><br>';
			}
			else
			{
				$body_text.= '<strong>Password Has Not Been Changed</strong><br>';
			}

			if($encrypt_private_key === TRUE)
			{
				$body_text.= '<font color="blue"><strong>Private Key Encryption Complete!</strong></font>';
			}
			else if($encrypt_private_key === 2)
			{
				$body_text.= '<font color="red"><strong>Private Key Encryption Has Been Removed</strong></font>';
			}
			else
			{
				$body_text.= '<strong>Private Key Encryption Has Not Been Changed</strong>';
			}

		} // End username/password change check

		if($_GET["refresh"] == "change")
		{
			$sql = "UPDATE `options` SET `field_data` = '" . $_POST["home_update"] . "' WHERE `options`.`field_name` = 'refresh_realtime_home' LIMIT 1";
			if(mysqli_query($db_connect, $sql) == TRUE)
			{
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["max_peers"] . "' WHERE `options`.`field_name` = 'max_active_peers' LIMIT 1";
				if(mysqli_query($db_connect, $sql) == TRUE)
				{
					$sql = "UPDATE `options` SET `field_data` = '" . $_POST["max_new_peers"] . "' WHERE `options`.`field_name` = 'max_new_peers' LIMIT 1";
					if(mysqli_query($db_connect, $sql) == TRUE)
					{
						$sql = "UPDATE `options` SET `field_data` = '" . $_POST["timezone"] . "' WHERE `options`.`field_name` = 'default_timezone' LIMIT 1";
						if(mysqli_query($db_connect, $sql) == TRUE)
						{
							$refresh_change = TRUE;
						}
					}
				}
			}

			$body_text = options_screen2();

			if($refresh_change == TRUE)
			{
				$body_text .= '<font color="blue"><strong>Settings Saved!</strong></font><br>';
			}
			else
			{
				$body_text .= '<strong>Update ERROR...</strong><br>';
			}
		} // End refresh update save
		else if(empty($_GET["password"]) == TRUE && empty($_GET["refresh"]) == TRUE)
		{
			$body_text = options_screen2();
		}

		if($_GET["newkeys"] == "confirm")
		{
			set_time_limit(999);
			$time1 = time();

			if(generate_new_keys(intval($_POST["new_key_bits"])) == TRUE)
			{
				mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = 'private_key_crypt'");
				$body_text .= '<font color="green"><strong>New Private &amp; Public Key Pair Generated! (It Took ' . (time() - $time1) . ' Second(s) To Generate)</strong></font><br>';
			}
			else
			{
				$body_text .= '<font color="red"><strong>New Key Creation Failed!</strong></font><br>';
			}
		}

		if($_GET["plugin"] == "install")
		{
			// Install New Plugin
			$plugin_install = file_upload("plugin_file");
			
			if($plugin_install == FALSE)
			{
				$plugin_install_output .= '<font color="red">Plugin File (' . $plugin_install . ') Install FAILED!</font><br>';
			}
			else
			{
				$plugin_install_output .= '<font color="blue">Plugin File (' . $plugin_install . ') Install Complete</font><br>';
			}

			// Scan file to find variables to create database variables
			$new_plugin_contents = read_plugin("plugins/" . $plugin_install);

			$plugin_name = find_string("PLUGIN_NAME=", "---END", $new_plugin_contents);
			$plugin_tab = find_string("PLUGIN_TAB=", "---END", $new_plugin_contents);
			$plugin_service = find_string("PLUGIN_SERVICE=", "---END", $new_plugin_contents);

			// Find Empty Record Location
			$record_number = 1;
			$record_check = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'installed_plugins_1' LIMIT 1"),0,0);
			
			while(empty($record_check) == FALSE)
			{
				$record_number++;
				$record_check = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'installed_plugins_$record_number' LIMIT 1"),0,0);
			}

			if(empty($plugin_service) == TRUE)
			{
				$sql = "INSERT INTO `options` (`field_name` ,`field_data`)VALUES 
					('installed_plugins_$record_number', '---file=$plugin_install---enable=0---show=1---name=$plugin_name---tab=$plugin_tab---service=$plugin_service---end')";
			}
			else
			{
				$sql = "INSERT INTO `options` (`field_name` ,`field_data`)VALUES 
					('installed_plugins_$record_number', '---file=$plugin_install---enable=0---show=0---name=$plugin_name---tab=$plugin_tab---service=$plugin_service---end')";
			}

			if(mysqli_query($db_connect, $sql) == TRUE)
			{
				$plugin_install_output .= '<font color="blue">Plugin (' . $plugin_name . ') Install Into Database Complete</font><br>';
			}
			else
			{
				$plugin_install_output .= '<font color="red">Plugin (' . $plugin_name . ') Install Into Database FAILED?</font><br>';
			}

			$quick_info = 'You can enable or disable plugins.<br><br>
			<strong>Plugin Services</strong> are started when you login. To shutdown plugin services, log out.<br><br>
			When installing a new plugin service, you must log out first. Log back in and the plugin service will be started.';

			home_screen("Plugin Manager", $plugin_install_output, options_screen5() , $quick_info);
			exit;
		}

		if($_GET["plugin"] == "new")
		{
			// New Plugin Install Screen
			home_screen("Plugin Manager", NULL, options_screen6() , "This will allow a new plugin to be installed.");
			exit;
		}

		if($_GET["manage"] == "plugins")
		{
			$quick_info = 'You can enable or disable plugins.<br><br>
			<strong>Plugin Services</strong> are started when you login. To shutdown plugin services, log out.<br><br>
			When installing a new plugin service, you must log out first. Log back in and the plugin service will be started.';
			
			home_screen("Plugin Manager", NULL, options_screen5() , $quick_info);
			exit;
		}

		if($_GET["plugin"] == "disable")
		{
			// Disable selected plugin, search for script file name in database
			$plugin_filename = $_POST["pluginfile"];
			$installed_plugins = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' AND `field_data` LIKE '%$plugin_filename%' LIMIT 1"),0,"field_data");

			// Rewrite String to Disable plugin
			$new_disable_string = str_replace("enable=1", "enable=0", $installed_plugins);
		
			// Update String in Database
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_disable_string' WHERE `options`.`field_name` LIKE 'installed_plugins%' AND `options`.`field_data` = '$installed_plugins' LIMIT 1");

			home_screen("Plugin Manager", NULL, options_screen5() , "You can enable or disable plugins.");
			exit;
		}

		if($_GET["plugin"] == "enable")
		{
			// Enable selected plugin, search for script file name in database
			$plugin_filename = $_POST["pluginfile"];
			$installed_plugins = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' AND `field_data` LIKE '%$plugin_filename%' LIMIT 1"),0,"field_data");

			// Rewrite String to Enable plugin
			$new_disable_string = str_replace("enable=0", "enable=1", $installed_plugins);
		
			// Update String in Database
			mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_disable_string' WHERE `options`.`field_name` LIKE 'installed_plugins%' AND `options`.`field_data` = '$installed_plugins' LIMIT 1");

			$quick_info = 'You can enable or disable plugins.<br><br>
			<strong>Plugin Services</strong> are started when you login. To shutdown plugin services, log out.<br><br>
			When installing a new plugin service, you must log out first. Log back in and the plugin service will be started.';

			home_screen("Plugin Manager", NULL, options_screen5() , $quick_info);
			exit;
		}

		if($_GET["remove"] == "plugin")
		{
			// Enable selected plugin, search for script file name in database
			$plugin_filename = $_POST["pluginfile"];
			$installed_plugins = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' AND `field_data` LIKE '%$plugin_filename%' LIMIT 1"),0,"field_data");

			// Find the file name for the plugin
			$plugin_file = find_string("---file=", "---enable", $installed_plugins);

			$plugin_remove_output;

			// Check if the file exist
			if(file_exists("plugins/" . $plugin_file) == TRUE)
			{
				if(unlink("plugins/" . $plugin_file) == TRUE)
				{
					$plugin_remove_output .= '<font color="blue">Plugin File (' . $plugin_file . ') Deleted</font><br>';
				}
				else
				{
					$plugin_remove_output .= '<font color="red"><strong>Plugin File (' . $plugin_file . ') Could NOT Be Deleted?</strong></font><br>';
				}
			}
			else
			{
				$plugin_remove_output .= '<font color="red">Plugin File (' . $plugin_file . ') Did Not Exist to Delete?</font><br>';
			}

			// Delete Database Entry
			$sql = "DELETE FROM `options` WHERE `options`.`field_name` LIKE 'installed_plugins%' AND `options`.`field_data` = '$installed_plugins' LIMIT 1";
			
			if(mysqli_query($db_connect, $sql) == TRUE)
			{
				$plugin_remove_output .= '<font color="blue">Plugin Database Entry Deleted</font><br>';
			}
			else
			{
				$plugin_remove_output .= '<font color="red"><strong>Plugin Database Entry Could NOT Be Deleted?</strong></font><br>';
			}

			$quick_info = 'You can enable or disable plugins.<br><br>
			<strong>Plugin Services</strong> are started when you login. To shutdown plugin services, log out.<br><br>
			When installing a new plugin service, you must log out first. Log back in and the plugin service will be started.';

			home_screen("Plugin Manager", $plugin_remove_output, options_screen5() , $quick_info);
			exit;
		}


		if($_GET["manage"] == "tabs")
		{
			home_screen("Show/Hide Tabs", NULL, options_screen4() , "You can hide or show certain tabs at the top.");
			exit;
		}

		if($_GET["tabs"] == "change")
		{
			$standard_tabs_settings = standard_tab_settings($_POST["tab_peerlist"], $_POST["tab_trans_queue"], $_POST["tab_send_receive"], 
				$_POST["tab_history"], $_POST["tab_address"], $_POST["tab_system"], $_POST["tab_backup"], $_POST["tab_tools"]);

			$sql = "UPDATE `options` SET `field_data` = '$standard_tabs_settings' WHERE `options`.`field_name` = 'standard_tabs_settings' LIMIT 1";

			if(mysqli_query($db_connect, $sql) == TRUE)
			{
				$text_bar = '<font color="blue"><strong>Standard Tab Settings Updated</strong></font><br>';

				if($_POST["plugins_installed"] == "1")
				{
					// Cycle through all plugins and set hide/show status for tabs
					$cycle_counter = 0;
					while(empty($_POST["plugins_$cycle_counter"]) == FALSE)
					{
						$plugin_filename = $_POST["plugins_$cycle_counter"];

						$show_status = $_POST["plugins_status_$cycle_counter"];

						if($show_status == TRUE)
						{
							// Show Plugin Tab
							$installed_plugins = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' AND `field_data` LIKE '%$plugin_filename%' LIMIT 1"),0,"field_data");

							// Rewrite String to Show Plugin Tab
							$new_disable_string = str_replace("show=0", "show=1", $installed_plugins);
						
							// Update String in Database
							mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_disable_string' WHERE `options`.`field_name` LIKE 'installed_plugins%' AND `options`.`field_data` = '$installed_plugins' LIMIT 1");
						}
						else
						{
							// Hide Plugin Tab
							$installed_plugins = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` LIKE 'installed_plugins%' AND `field_data` LIKE '%$plugin_filename%' LIMIT 1"),0,"field_data");

							// Rewrite String to Show Plugin Tab
							$new_disable_string = str_replace("show=1", "show=0", $installed_plugins);
						
							// Update String in Database
							mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$new_disable_string' WHERE `options`.`field_name` LIKE 'installed_plugins%' AND `options`.`field_data` = '$installed_plugins' LIMIT 1");
						}

						$cycle_counter++; // Next Plugin
					}

					$text_bar .= '<font color="blue"><strong>Plugin Tab Settings Updated</strong></font><br>';

				}
				
				home_screen("Show/Hide Tabs", $text_bar, options_screen4() , "You can hide or show certain tabs at the top.");
				exit;
			}
		}

		$quick_info = 'You may change the username and password individually or at the same time.
		<br><br>Remember that usernames and passwords are Case Sensitive.
		<br><br><strong>Private Key</strong> password will use AES-256 bit encryption to save your private key in the database.
		<br>You will be required to enter a password anytime you send currency to another public key.
		<br><i><strong>Note:</strong> First time creating password, use the same password in all three fields.</i>
		<br><br><strong>Generate New Keys</strong> will create a new random key pair and save it in the database.
		<br><br><strong>Check for Updates</strong> will check for any program updates that can be downloaded directly into Timekoin.';

		if($_GET["storage_key"] == "new")
		{
			$bits_level = intval($_POST["crypt_bits"]);

			if($bits_level == "")
			{
				$bits_level = 1536;
			}
			else
			{
				// Create New Key Pair
				set_time_limit(999);
				if($bits_level < 1536) { $bits_level = 1536; }
				$time1 = time();
				$keys = generate_new_keys($bits_level, TRUE);
				$new_private_key = base64_encode($keys[0]);
				$new_public_key = base64_encode($keys[1]);
				$message = '<br><font color="green"><strong>New Private &amp; Public Key Pair Generated! (It Took ' . (time() - $time1) . ' Second(s) To Create)</strong></font>';
			}

			$clipboard_copy = '<script>
			function myPrivateKey()
			{
				var copyText = document.getElementById("current_private_key");
				copyText.select();
				copyText.setSelectionRange(0, 99999)
				document.execCommand("copy");
				var tooltip = document.getElementById("myTooltip");
				tooltip.innerHTML = "Copy Complete!";
			}
			function myPublicKey()
			{
				var copyText = document.getElementById("current_public_key");
				copyText.select();
				copyText.setSelectionRange(0, 99999)
				document.execCommand("copy");
				var tooltip = document.getElementById("myTooltip2");
				tooltip.innerHTML = "Copy Complete!";
			}</script>';

			$body_string = '<FORM ACTION="index.php?menu=options&amp;storage_key=new" METHOD="post">
			<strong>Bits Size [1,536 to 17,408]</strong> (Caution: High Values Take a Lot of Time to Create New Keys!)<br>
			<input type="number" name="crypt_bits" min="1536" max="17408" size="6" value="' . $bits_level . '"/>
			<input type="submit" name="Submit" value="Create New Key Pair" /></FORM><br>' . $clipboard_copy . '
			<strong><font color="blue">New Private Key</font></strong><br>
			<textarea id="current_private_key" rows="10" cols="90" READONLY>' . $new_private_key . '</textarea><br>
			<button title="Copy Private Key to Clipboard" onclick="myPrivateKey()"><span id="myTooltip">Copy Private Key</span></button><hr>
			<strong><font color="green">New Public Key</font></strong><br>
			<textarea id="current_public_key" rows="8" cols="90" READONLY>' . $new_public_key . '</textarea><br>
			<button title="Copy Public Key to Clipboard" onclick="myPublicKey()"><span id="myTooltip2">Copy Public Key</span></button><br>' . $message;

			$quick_info = '<strong>Storage Keys</strong> can be created to store a balance offline.<br><br>
			<strong>Do Not</strong> share your <strong>Private Key</strong> with anyone for any reason.<br><br>
			The <strong>Private Key</strong> encrypts all transactions for the given <strong>Public Key</strong>.<br><br>
			Save both keys in a password protected document or external device that you can secure (CD, Flash Drive, Printed Paper, etc.)';

			home_screen("Options &amp; Personal Settings", '<strong><font color="purple">Create New Storage Keys</font></strong>', $body_string , $quick_info);
			exit;
		}

		if($_GET["upgrade"] == "check" || $_GET["upgrade"] == "doupgrade")
		{
			home_screen("Options &amp; Personal Settings", options_screen3(), "" , $quick_info);
		}
		else
		{		
			home_screen("Options &amp; Personal Settings", options_screen(), $body_text , $quick_info);
		}
		exit;
	}	
//****************************************************************************
	if($_GET["menu"] == "send" && $_SESSION["admin_login"] == TRUE)
	{
		$my_public_key = my_public_key();

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
				$body_string .= '<hr><font color="red"><strong>This exceeds your current balance, send failed...</strong></font><br><br>';
			}
			else
			{
				if($my_public_key == $public_key_to)
				{
					// Can't send to yourself
					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body();
					$body_string .= '<hr><font color="red"><strong>Can not send to yourself, send failed...</strong></font><br><br>';
				}
				else
				{
					// Check if public key is valid by searching for any transactions
					// that reference it
					$valid_key_test = verify_public_key($public_key_to);

					if($valid_key_test == TRUE)
					{
						// Key has a valid history
						$message = $_POST["send_message"];
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body($public_key_64, $send_amount, TRUE, NULL, $message, $_POST["name"]);
						$body_string .= '<hr><font color="green"><strong>This public key is valid.</strong></font><br>
						<strong>There is no way to recover Timekoins sent to the wrong public key.</strong><br>
						<font color="blue"><strong>Click "Send Timekoins" to send now.</strong></font><br><br>';
					}
					else
					{
						// No key history, might not be valid
						$message = $_POST["send_message"];
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body($public_key_64, $send_amount, TRUE, NULL, $message, $_POST["name"]);
						$body_string .= '<hr><font color="red"><strong>This public key has no existing history of transactions.<br>
						There is no way to recover Timekoins sent to the wrong public key.</strong></font><br>
						<strong>Click "Send Timekoins" to send now.</strong><br><br>';
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
					$body_string .= '<hr><font color="red"><strong>This exceeds your current balance, send failed...</strong></font><br><br>';
				}
				else
				{
					if($my_public_key == $public_key_to)
					{
						// Can't send to yourself
						$display_balance = db_cache_balance($my_public_key);
						$body_string = send_receive_body();
						$body_string .= '<hr><font color="red"><strong>Can not send to yourself, send failed...</strong></font><br><br>';
					}
					else
					{
						// Now it's time to send the transaction
						$my_private_key = my_private_key();
						$private_key_crypt = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'private_key_crypt' LIMIT 1"),0,1);

						if($private_key_crypt == TRUE)
						{
							// Decrypt Private Key First
							$my_private_key = AesCtr::decrypt($my_private_key, $_POST["crypt_password"], 256);
							$valid_key = find_string("-----BEGIN", "KEY-----", $my_private_key); // Valid Decrypt?

							if(empty($valid_key) == TRUE)
							{
								// Decrypt Failed
								$display_balance = db_cache_balance($my_public_key);
								$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
								$body_string .= '<hr><font color="red"><strong>Send Failed. Wrong Password.</strong></font><br><br>';
							}
							else
							{
								if(send_timekoins($my_private_key, $my_public_key, $public_key_to, $send_amount, $message) == TRUE)
								{
									$display_balance = db_cache_balance($my_public_key);
									$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
									$body_string .= '<hr><font color="green"><strong>You just sent ' . $send_amount . ' timekoins to the above public key.</strong></font><br>
									<strong>Your balance will not reflect this until the transaction is recorded across the entire network.</strong><br><br>';
								}
								else
								{
									$display_balance = db_cache_balance($my_public_key);
									$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
									$body_string .= '<hr><font color="red"><strong>Send failed...</strong></font><br><br>';
								}

								// Clear Variable from From RAM
								unset($my_private_key);
							}
						}
						else
						{
							if(send_timekoins($my_private_key, $my_public_key, $public_key_to, $send_amount, $message) == TRUE)
							{
								$display_balance = db_cache_balance($my_public_key);
								$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
								$body_string .= '<hr><font color="green"><strong>You just sent ' . $send_amount . ' timekoins to the above public key.</strong></font><br>
								<strong>Your balance will not reflect this until the transaction is recorded across the entire network.</strong><br><br>';
							}
							else
							{
								$display_balance = db_cache_balance($my_public_key);
								$body_string = send_receive_body($public_key_64, $send_amount, NULL, NULL, NULL, $_POST["name"]);
								$body_string .= '<hr><font color="red"><strong>Send failed...</strong></font><br><br>';
							}
						}
					} // End duplicate self check
				} // End Balance Check
			} // End check send command
			else
			{
				if($_GET["easykey"] == "grab")
				{
					$message = $_POST["send_message"];
					$easy_key = filter_sql($_POST["easy_key"]); // Filter SQL just in case
					$last_easy_key = filter_sql($_POST["easy_key"]); // Filter SQL just in case
					$easy_key = filter_sql(easy_key_lookup($easy_key));

					if($easy_key === 0 || empty($easy_key) == TRUE)
					{
						$server_message = '<font color="red"><strong>' . $last_easy_key . ' Not Found!<BR>Spelling is Case Sensitive.</strong></font>';
						$easy_key = NULL;
					}
					else
					{
						$server_message = '<font color="blue"><strong>Easy Key Found</strong></font>';
					}
				}

				if(empty($_GET["name_id"]) == TRUE)
				{
					// No selections made, default screen
					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body($easy_key, NULL, NULL, $last_easy_key, $message);
					$body_string .= $server_message;
				}
				else
				{
					// Insert Address Book Entry
					$name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
					$easy_key = mysql_result(mysqli_query($db_connect, "SELECT easy_key FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
					$full_key = mysql_result(mysqli_query($db_connect, "SELECT full_key FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
					
					$display_balance = db_cache_balance($my_public_key);
					$body_string = send_receive_body($full_key, NULL, NULL, $easy_key, $message, $name);
				}
			}
		}

		if($display_balance === "NA")
		{
			$display_balance = '<font color="red">NA</font>';
		}
		else
		{
			$display_balance = number_format($display_balance);
		}

		if($_GET["easy_key"] == "new")
		{
			$easy_key_fee = num_gen_peers(TRUE) + 1;

			if($easy_key_fee < 2)
			{
				$easy_key_fee = '<font color="red">No Network Response</font>';
			}

			$private_key_crypt = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'private_key_crypt' LIMIT 1"),0,1);

			if($private_key_crypt == TRUE)
			{
				$request_password = '<tr><td><strong><font color="blue">Password Required:</font></strong> <input type="password" name="crypt_password" /></td></tr>';
			}

			$body_string = '<FORM ACTION="index.php?menu=send&amp;easy_key=create" METHOD="post">
			<table border="0" cellpadding="6"><tr><td><font color="green"><strong>Create New Easy Key</strong></font></td></tr>
			<tr><td><strong>Creation Fee: <font color="green">' . $easy_key_fee . ' TK</font></strong></td></tr>
			<tr><td><strong><font color="blue">New Easy Key</font></strong><BR>
			<input type="text" maxlength="64" size="64" value="" name="new_easy_key" /></td></tr>' . $request_password . '</table>
			<input type="submit" value="Create New Easy Key" onclick="showWait()" /></FORM>';

			$quick_info = '<strong>Easy Keys</strong> are shortcuts enabling access to much longer <font color="blue">Public Keys</font> in Timekoin.</br><BR>
			A New <strong>Easy Key</strong> shortcut you create must be between 1 and 64 characters in length including spaces.</br></br>
			Each <strong>Easy Key</strong> shortcut may only contain letters, digits, or special characters.</br>No <strong>| ? = \' ` * %</strong> characters allowed.<BR><BR>
			All <strong>Easy Keys <font color="red">Expire</font></strong> after <strong><font color="blue">3 Months</font></strong> unless you renew the key by creating it again with the same <font color="blue">Public Key</font> as before.';
		}
		else
		{
		$quick_info = 'Send your own Timekoins to someone else.<br><br>
			Your client will attempt to verify if the public key is valid by examing the transaction history before sending.<br><br>
			New public keys with no history could appear invalid for this reason, so always double check.<br><br>
			You can enter an <strong>Easy Key</strong> and Timekoin will fill in the Public Key field for you.<br><br>
			Messages encoded into your transaction are limited to <strong>64</strong> characters. Messages are visible to anyone that examines your specific transaction details.<br><br>No <strong>| ? = \' ` * %</strong> characters allowed.';			
		}

		if($_GET["easy_key"] == "create")
		{
			set_time_limit(999);
			$new_easy_key = $_POST["new_easy_key"];
			$private_key_crypt = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'private_key_crypt' LIMIT 1"),0,1);

			if($private_key_crypt == TRUE)
			{
				// Private Key Encrypted
				$my_private_key = my_private_key();

				// Decrypt Private Key First
				$my_private_key = AesCtr::decrypt($my_private_key, $_POST["crypt_password"], 256);
				$valid_key = find_string("-----BEGIN", "KEY-----", $my_private_key); // Valid Decrypt?

				if(empty($valid_key) == TRUE)
				{
					// Decrypt Failed
					$create_easy_key = 8;
				}
				else
				{
					// Decrypt Good
					$create_easy_key = create_new_easy_key($my_private_key, $my_public_key, $new_easy_key);
				}
			}
			else
			{
				$my_private_key = my_private_key();
				$create_easy_key = create_new_easy_key($my_private_key, $my_public_key, $new_easy_key);
			}

			// Clear Variable from RAM
			unset($my_private_key);

			$body_string;

			if($create_easy_key > 300)// Success time will always be at least more than 1 transaction cycle
			{
				$seconds_to_minutes = round($create_easy_key / 60);
				$body_string = '<BR><BR><font color="green"><strong>Easy Key [' . $new_easy_key . '] Has Been Submitted to the Timekoin Network!</font><BR><BR>
				Your Easy Key Should be Active Within ' . $seconds_to_minutes . ' Minutes.<BR>
				If You Are Renewing Your Key Before it Expires, then Expect No Delay.</strong>';

				$e_key_time = transaction_cycle($create_easy_key / 300) + 7889400;// Creation time plus 3 Months
				// Store Easy Key for later lookups
				$sql = "INSERT INTO `options` (`field_name`, `field_data`) VALUES ('easy_key', 'easy_key=$new_easy_key---expires=$e_key_time---END')";
				mysqli_query($db_connect, $sql);
			}
			else
			{
				switch($create_easy_key)
				{
					case 1:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] is Too Short or Too Long!</strong></font>';
					break;

					case 2:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] Has Invalid Characters!</strong></font>';
					break;

					case 3:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] is Taken Already!</strong></font>';
					break;

					case 4:
					$body_string = '<BR><BR><font color="red"><strong>Creation Fee of [' . (num_gen_peers(FALSE, TRUE) + 1) . '] TK Needed to Create This Easy Key!</strong></font>';
					break;

					case 6:
					$body_string = '<BR><BR><font color="red"><strong>New Easy Key Fee Transaction Failed to Send to a Public Key</strong></font>';
					break;

					case 7:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key Transaction for Creation Failed to Send</strong></font>';
					break;

					case 8:
					$body_string = '<BR><BR><font color="red"><strong>Private Key Password Incorrect!</strong></font>';
					break;

					default:
					$body_string = '<BR><BR><font color="red"><strong>Easy Key: [' . $new_easy_key . '] Unknown ERROR!</strong></font>';
					break;
				}
			}

		}// Easy Key Creation

		$clipboard_copy = '<script>
		function myPublicKey()
		{
			var copyText = document.getElementById("current_public_key");
			copyText.select();
			copyText.setSelectionRange(0, 99999)
			document.execCommand("copy");
			var tooltip = document.getElementById("myTooltip2");
			tooltip.innerHTML = "Copy Complete!";
		}</script>';

		// Show all Easy Keys associated with this Client Public Key
		$sql = "SELECT field_data  FROM `options` WHERE `field_name` = 'easy_key'";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		$clean_e_key_records = 9;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			$easy_key_data = $sql_row["field_data"];
			$easy_key_name = find_string("easy_key=", "---expires", $easy_key_data);
			$easy_key_expires = find_string("---expires=", "---END", $easy_key_data);
			$easy_key_lookup = easy_key_lookup($easy_key_name);

			// One exist, is it ours?
			if($easy_key_lookup == base64_encode($my_public_key))
			{
				$easy_key_list.= '<br><strong>Easy Key: [<font color="blue">' . $easy_key_name . '</font>] <font color="red">Expires:</font> ' . unix_timestamp_to_human($easy_key_expires) . '</strong>';
			}
			else
			{
				// No match, should we delete this one?
				if($sql_num_results > $clean_e_key_records && $easy_key_lookup == "0")
				{
					$sql = "DELETE FROM `options` WHERE `options`.`field_name` = 'easy_key' AND `options`.`field_data` = 'easy_key=$easy_key_name---expires=$easy_key_expires---END'";
					mysqli_query($db_connect, $sql);
				}
			}
		}

		$text_bar = $clipboard_copy . '<table border="0" cellpadding="6"><tr><td><strong>Current Billfold Balance: <font color="green">' . $display_balance . '</font> TK</strong></td></tr>
		<tr><td><strong><font color="green">Public Key</font> to receive:</strong></td></tr></table>
		<textarea id="current_public_key" readonly="readonly" rows="6" style="width: 100%; max-width: 100%;">' . base64_encode($my_public_key) . '</textarea><br>
		<button title="Copy Public Key to Clipboard" onclick="myPublicKey()"><span id="myTooltip2">Copy Public Key</span></button><br>' . $easy_key_list;

		home_screen('Send / Receive Timekoins', $text_bar, $body_string , $quick_info);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "history" && $_SESSION["admin_login"] == TRUE)
	{
		$my_public_key = my_public_key();
		$address_name;

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
				mysqli_query($db_connect, $sql);

				$default_public_key_font = $_POST["font_size"];
			}
		}
		else
		{
			$default_public_key_font = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
		}

		if(empty($_GET["name_id"]) == FALSE)
		{
			$name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
			$full_key = mysql_result(mysqli_query($db_connect, "SELECT full_key FROM `address_book` WHERE `id` = " . $_GET["name_id"]),0,0);
			$show_last = 100;
			$name_from = ' from <font color="blue">' . $name . '</font>';
			$name_to = ' to <font color="blue">' . $name . '</font>';			
		}

		if($hide_receive == FALSE)
		{
			$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> Transactions <font color="green">Sent To</font> this Billfold' . $name_from . '</strong><br>
				<FORM ACTION="index.php?menu=history&amp;receive=listmore" METHOD="post">
				<br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
				<th>Sent From</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

			$history_data_to = transaction_history_query(1, $show_last);
			$counter = 1;

			while($counter <= 100) // 100 History Limit
			{
				$timestamp = find_string("---TIMESTAMP$counter=", "---FROM", $history_data_to);
				$public_key_from = find_string("---FROM$counter=", "---AMOUNT", $history_data_to);
				$amount = find_string("---AMOUNT$counter=", "---VERIFY", $history_data_to);					
				$verify = find_string("---VERIFY$counter=", "---MESSAGE", $history_data_to);
				$message = find_string("---MESSAGE$counter=", "---END$counter", $history_data_to);					

				if(empty($timestamp) == TRUE && empty($amount) == TRUE)
				{
					// No more data to search
					break;
				}

				if(empty($_GET["name_id"]) == TRUE)
				{
					if($public_key_from == base64_encode($my_public_key))
					{
						// Self Generated
						$public_key_from = '<td class="style2">My Public Key';
					}
					else
					{
						// Check if the key matches anyone in the address book
						$address_name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `full_key` = '$public_key_from'"),0,0);

						if(empty($address_name) == TRUE)
						{
							$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:150px; font-size:' . $default_public_key_font . 'px;">' . $public_key_from . '</p>';
						}
						else
						{
							$public_key_from = '<td class="style2"><font color="blue">' . $address_name . '</font>';
						}
					}
					$body_string .= '<tr>
					<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp) . '</p></td>' 
					. $public_key_from . '</td>
					<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
					<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
					<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';
				}
				else
				{
					// Match Friendly Name to Key
					if($public_key_from == $full_key)
					{
						$public_key_from = '<td class="style2"><font color="blue">' . $name . '</font>';

						$body_string .= '<tr>
						<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp) . '</p></td>' 
						. $public_key_from . '</td>
						<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
						<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
						<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';						
					}
				}

				$counter++;
			}
			
			$body_string .= '<tr><td colspan="5"><hr></td></tr>
			<tr><td colspan="5"><input type="text" size="5" name="show_more_receive" value="' . $show_last .'" />
			<input type="submit" name="Submit1" value="Show Last" /></td></tr></table></div></FORM>';

		} // End hide check for receive

		if($hide_send == FALSE)
		{
			$body_string .= '<strong>Showing Last <font color="blue">' . $show_last . '</font> Transactions <font color="blue">Sent From</font> this Billfold' . $name_to . '</strong><br><br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
				<th>Sent To</th><th>Amount</th><th>Verification Level</th><th>Message</th></tr>';

			$history_data_to = transaction_history_query(2, $show_last);
			$counter = 1;

			while($counter <= 100) // 100 History Limit
			{
				$timestamp = find_string("---TIMESTAMP$counter=", "---TO", $history_data_to);
				$public_key_to = find_string("---TO$counter=", "---AMOUNT", $history_data_to);
				$amount = find_string("---AMOUNT$counter=", "---VERIFY", $history_data_to);					
				$verify = find_string("---VERIFY$counter=", "---MESSAGE", $history_data_to);
				$message = find_string("---MESSAGE$counter=", "---END$counter", $history_data_to);					

				if(empty($timestamp) == TRUE && empty($amount) == TRUE)
				{
					// No more data to search
					break;
				}

				if(empty($_GET["name_id"]) == TRUE)
				{				
					// Check if the key matches anyone in the address book
					$address_name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `full_key` = '$public_key_to'"),0,0);

					if($public_key_to == EASY_KEY_PUBLIC_KEY)
					{
						$public_key_to = '<td class="style2"><font color="green">Your Easy Key Shortcut</font>';
					}
					else if(empty($address_name) == TRUE)
					{
						$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:150px; font-size:' . $default_public_key_font . 'px;">' . $public_key_to . '</p>';
					}
					else
					{
						$public_key_to = '<td class="style2"><font color="blue">' . $address_name . '</font>';
					}

					$body_string .= '<tr>
					<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp) . '</p></td>' 
					. $public_key_to . '</td>
					<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
					<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
					<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';					
				}
				else
				{
					// Match Friendly Name to Key
					if($public_key_to == $full_key)
					{
						$public_key_to = '<td class="style2"><font color="blue">' . $name . '</font>';

						$body_string .= '<tr>
						<td class="style2"><p style="font-size: 11px;">' . unix_timestamp_to_human($timestamp) . '</p></td>' 
						. $public_key_to . '</td>
						<td class="style2"><p style="font-size: 11px;">' . $amount . '</p></td>
						<td class="style2"><p style="font-size: 11px;">' . $verify . '</p></td>
						<td class="style2"><p style="word-wrap:break-word; width:150px; font-size: 11px;">' . $message . '</p></td></tr>';						
					}
				}

				$counter++;
			}

			$body_string .= '<tr><td colspan="5"><hr></td></tr><tr><td colspan="5"><FORM ACTION="index.php?menu=history&amp;send=listmore" METHOD="post"><input type="text" size="5" name="show_more_send" value="' . $show_last .'" /><input type="submit" name="Submit2" value="Show Last" /></FORM></td></tr>';
			$body_string .= '</table></div>';

		} // End hide check for send

		$text_bar = '<FORM ACTION="index.php?menu=history&amp;font=public_key" METHOD="post">
			<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td>
			<td style="width:250px"><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

		$quick_info = 'Verification Level represents how deep in the history the transaction exist.<br><br>
			The larger the number, the more time that all the peers have examined it and agree that it is a valid transaction.<br><br>
			You can view up to 100 past transactions that have been <u>sent from</u> or <u>sent to</u> your Billfold.';

		home_screen('Transaction History', $text_bar, $body_string , $quick_info);

		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "queue" && $_SESSION["admin_login"] == TRUE)
	{
		if($_GET["font"] == "public_key")
		{
			if(empty($_POST["font_size"]) == FALSE)
			{
				// Save value in database
				$sql = "UPDATE `options` SET `field_data` = '" . $_POST["font_size"] . "' WHERE `options`.`field_name` = 'public_key_font_size' LIMIT 1";
				mysqli_query($db_connect, $sql);

				header("Location: index.php?menu=queue");
				exit;
			}
		}
		else
		{
			$default_public_key_font = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'public_key_font_size' LIMIT 1"),0,"field_data");
		}

		$my_public_key = my_public_key();

		// Find the last X amount of transactions sent to this public key
		$sql = "SELECT * FROM `transaction_queue` ORDER BY `transaction_queue`.`timestamp` DESC";
		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);

		$body_string = '<strong><font color="blue">( ' . number_format($sql_num_results) . ' )</font> Network Transactions Waiting for Processing</strong><br><br><div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Date</th>
			<th>Send From</th><th>Send To</th><th>Amount</th></tr>';

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
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
					$public_key_from = '<td class="style2"><font color="blue">My Public Key</font>';
					
					// Check if the key matches anyone in the address book
					$address_name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `full_key` = '" . base64_encode($public_key_trans_to) . "'"),0,0);

					if(empty($address_name) == TRUE)
					{
						$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans_to) . '</p>';
					}
					else
					{
						$public_key_to = '<td class="style2"><font color="blue">' . $address_name . '</font>';
					}
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
						// Check if the key matches anyone in the address book
						$address_name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `full_key` = '" . base64_encode($public_key_trans_to) . "'"),0,0);

						if(empty($address_name) == TRUE)
						{
							$public_key_to = '<td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans_to) . '</p>';
						}
						else
						{
							$public_key_to = '<td class="style2"><font color="blue">' . $address_name . '</font>';
						}
					}
				}

				// Check if the key matches anyone in the address book
				$address_name = mysql_result(mysqli_query($db_connect, "SELECT name FROM `address_book` WHERE `full_key` = '" . base64_encode($public_key_trans) . "'"),0,0);

				if(empty($address_name) == TRUE)
				{
					$public_key_from = '<td class="style1"><p style="word-wrap:break-word; width:225px; font-size:' . $default_public_key_font . 'px;">' . base64_encode($public_key_trans) . '</p>';
				}
				else
				{
					$public_key_from = '<td class="style2"><font color="blue">' . $address_name . '</font>';
				}
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

		$text_bar = '<FORM ACTION="index.php?menu=queue&amp;font=public_key" METHOD="post">
			<table border="0" cellspacing="4"><tr><td><strong>Default Public Key Font Size</strong></td><td><input type="text" size="2" name="font_size" value="' . $default_public_key_font .'" /><input type="submit" name="Submit3" value="Save" /></td></tr></table></FORM>';

		$quick_info = 'This section contains all the network transactions that are queued to be stored in the transaction history.';
		
		$home_update = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'refresh_realtime_home' LIMIT 1"),0,"field_data");

		home_screen('Transactions in Network Queue', $text_bar, $body_string , $quick_info, $home_update);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "tools" && $_SESSION["admin_login"] == TRUE)
	{
		if($_GET["action"] == "check_tables")
		{
			set_time_limit(999);
			write_log("A CHECK of the Entire Database &amp; Tables Was Started.", "GU");

			$body_string = '<strong>Checking All Database Tables</strong><br><br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Check Database Results</th></tr><tr><td>';

			$db_check = mysqli_query($db_connect, "CHECK TABLE `activity_logs`, `address_book`, `data_cache`, `my_keys`, `options`, `transaction_queue`, `users`");
			$db_check_info = mysqli_fetch_array($db_check);
			$db_check_count = 0;
			
			while(empty($db_check_info["$db_check_count"]) == FALSE)
			{
				$body_string .= $db_check_info["$db_check_count"] . " ";
				$db_check_count++;

				if(empty($db_check_info["$db_check_count"]) == TRUE)
				{
					// Move to next array
					$db_check_info = mysqli_fetch_array($db_check);
					$db_check_count = 0;
					$body_string .= "</td></tr><tr><td>";
				}
			}

			$body_string .= '<strong>CHECK COMPLETE</strong></td></tr></table></div>';

			write_log("A CHECK of the Entire Database &amp; Tables Was Finished.", "GU");			
		}

		if($_GET["action"] == "repair_tables")
		{
			set_time_limit(999);
			write_log("A REPAIR of the Entire Database &amp; Tables Was Started.", "GU");

			$body_string = '<strong>Repair All Database Tables</strong><br><br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Repair Database Results</th></tr><tr><td>';

			$db_check = mysqli_query($db_connect, "REPAIR TABLE `activity_logs`, `address_book`, `data_cache`, `my_keys`, `options`, `transaction_queue`, `users`");
			$db_check_info = mysqli_fetch_array($db_check);
			$db_check_count = 0;
			
			while(empty($db_check_info["$db_check_count"]) == FALSE)
			{
				$body_string .= $db_check_info["$db_check_count"] . " ";
				$db_check_count++;

				if(empty($db_check_info["$db_check_count"]) == TRUE)
				{
					// Move to next array
					$db_check_info = mysqli_fetch_array($db_check);
					$db_check_count = 0;
					$body_string .= "</td></tr><tr><td>";
				}
			}

			$body_string .= '<strong>REPAIR FINISHED</strong></td></tr></table></div>';

			write_log("A REPAIR of the Entire Database &amp; Tables Was Finished.", "GU");			
		}

		if($_GET["action"] == "optimize_tables")
		{
			set_time_limit(999);
			write_log("An OPTIMIZE of the Entire Database &amp; Tables Was Started.", "GU");

			$body_string = '<strong>Optimize All Database Tables</strong><br><br>
				<div class="table"><table class="listing" border="0" cellspacing="0" cellpadding="0" ><tr><th>Optimize Database Results</th></tr><tr><td>';

			$db_check = mysqli_query($db_connect, "OPTIMIZE TABLE `activity_logs`, `address_book`, `data_cache`, `my_keys`, `options`, `transaction_queue`, `users`");
			$db_check_info = mysqli_fetch_array($db_check);
			$db_check_count = 0;
			
			while(empty($db_check_info["$db_check_count"]) == FALSE)
			{
				$body_string .= $db_check_info["$db_check_count"] . " ";
				$db_check_count++;

				if(empty($db_check_info["$db_check_count"]) == TRUE)
				{
					// Move to next array
					$db_check_info = mysqli_fetch_array($db_check);
					$db_check_count = 0;
					$body_string .= "</td></tr><tr><td>";
				}
			}

			$body_string .= '<strong>OPTIMIZE FINISHED</strong></td></tr></table></div>';

			write_log("An OPTIMIZE of the Entire Database &amp; Tables Was Finished.", "GU");
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
			mysqli_query($db_connect, "TRUNCATE TABLE `activity_logs`");
			write_log("All Logs Cleared.", "GU");			
		}

		if(empty($_GET["action"]) == TRUE)
		{
			// Show log history
			if(empty($_POST["filter"]) == FALSE)
			{
				$filter_by;
				switch($_POST["filter"])
				{
					case "GU":
						$filter_by = ' (Filtered by <strong>Graphical User Interface</strong>)';
						break;

					case "PL":
						$filter_by = ' (Filtered by <strong>Peer List</strong>)';
						break;

					case "S":
						$filter_by = ' (Filtered by <strong>System</strong>)';
						break;

					case "T":
						$filter_by = ' (Filtered by <strong>Transactions</strong>)';
						break;
				}
			}
			
			$body_string = '<strong>Showing Last <font color="blue">' . $show_last . '</font> Log Events</strong>' . $filter_by . '<FORM ACTION="index.php?menu=tools&amp;logs=listmore" METHOD="post">
			<table border="0" cellspacing="5">
			<tr><td>Filter By:</td><td><select name="filter"><option value="all" SELECTED>Show All</option>
			<option value="GU">GUI - Graphical User Interface</option>
			<option value="PL">Peer Processor</option>
			<option value="S">System</option>
			<option value="T">Transactions</option>
			</select></td></tr></table>
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
			
			$sql_result = mysqli_query($db_connect, $sql);
			$sql_num_results = mysqli_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysqli_fetch_array($sql_result);

				$body_string .= '<tr>
				<td class="style2"><p style="width:160px;">' . unix_timestamp_to_human($sql_row["timestamp"]) . '</p></td>
				<td class="style2"><p style="word-wrap:break-word; width:450px;">' . $sql_row["log"] . '</p></td>
				<td class="style2">' . $sql_row["attribute"] . '</td></tr>';
			}

			$body_string .= '<tr><td colspan="3"><hr></td></tr><tr><td><input type="text" size="5" name="show_more_logs" value="' . $show_last .'" /><input type="submit" name="show_last" value="Show Last" /></td>
			<td colspan="2"></td></tr>';
			
			$body_string .= '</table></div></FORM>
			<table border="0" align="right" ><tr><td>
			<FORM ACTION="index.php?menu=tools&amp;logs=clear" METHOD="post"><input type="submit" name="clear_logs" value="Clear All Logs" onclick="return confirm(\'Clear All Logs?\');" /></FORM></td></tr></table>';
		}
		
		$text_bar = tools_bar();

		$quick_info = '<strong>Check DB</strong> will check the data integrity of all tables in the database.<br><br>
			<strong>Optimize DB</strong> will optimize all tables &amp; indexes in the database.<br><br>
			<strong>Repair DB</strong> will attempt to repair all tables in the database.';
		
		home_screen('Tools &amp; Utilities', $text_bar, $body_string , $quick_info);
		exit;
	}
//****************************************************************************
	if($_GET["menu"] == "backup" && $_SESSION["admin_login"] == TRUE)
	{
		if($_GET["dorestore"] == "private" && empty($_POST["restore_private_key"]) == FALSE)
		{
			$sql = "UPDATE `my_keys` SET `field_data` = '" . base64_decode($_POST["restore_private_key"]) . "' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";

			if(mysqli_query($db_connect, $sql) == TRUE)
			{
				if(my_private_key(TRUE) == TRUE)
				{
					// Private Key Encrypted
					mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = 'private_key_crypt'");
					mysqli_query($db_connect, "INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('private_key_crypt', '1')");
					$key_message = '<font color="green"><strong>*** <font color="red">Encrypted</font> Private Key Restore Complete! ***</strong></font><br>';
				}
				else
				{
					mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = 'private_key_crypt'");
					$key_message = '<font color="green"><strong>*** Private Key Restore Complete! ***</strong></font><br>';
				}
			}
			else
			{
				$key_message = '<font color="red"><strong>Private Key Restore FAILED!</strong></font><br>';
			}
		}

		if($_GET["dorestore"] == "public" && empty($_POST["restore_public_key"]) == FALSE)
		{
			$sql = "UPDATE `my_keys` SET `field_data` = '" . base64_decode($_POST["restore_public_key"]) . "' WHERE `my_keys`.`field_name` = 'server_public_key' LIMIT 1";

			if(mysqli_query($db_connect, $sql) == TRUE)
			{
				$key_message = '<font color="green"><strong>*** Public Key Restore Complete! ***</strong></font><br>';
			}
			else
			{
				$key_message = '<font color="red"><strong>Public Key Restore FAILED!</strong></font><br>';
			}
		}

		$my_public_key = base64_encode(my_public_key());
		$my_private_key = base64_encode(my_private_key());

		if(my_private_key(TRUE) == TRUE && $_GET["keys"] != "restore")
		{
			$key_encrypted = '<br><font color="red"><strong>WARNING:</strong></font> <font color="blue"><strong><i>Private Key Is Encrypted</i></strong></font>';
		}

		$clipboard_copy = '<script>
		function myPrivateKey()
		{
			var copyText = document.getElementById("current_private_key");
			copyText.select();
			copyText.setSelectionRange(0, 99999)
			document.execCommand("copy");
			var tooltip = document.getElementById("myTooltip");
			tooltip.innerHTML = "Copy Complete!";
		}
		function myPublicKey()
		{
			var copyText = document.getElementById("current_public_key");
			copyText.select();
			copyText.setSelectionRange(0, 99999)
			document.execCommand("copy");
			var tooltip = document.getElementById("myTooltip2");
			tooltip.innerHTML = "Copy Complete!";
		}</script>';

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

		if($_GET["keys"] == "download")
		{
			$content = '---TKPRIVATEKEY=' . $my_private_key . '---ENDTKPRIVATEKEY' . "\n\r";
			$content.= '---TKPUBLICKEY=' . $my_public_key . '---ENDTKPUBLICKEY';			
			$length = strlen($content);
			header('Content-Description: File Transfer');
			header('Content-Type: text/plain');
			header('Content-Disposition: attachment; filename=TK-Client-Keys.txt');
			header('Content-Transfer-Encoding: binary');
			header('Content-Length: ' . $length);
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Expires: 0');
			header('Pragma: public');
			echo $content;
			exit;
		}

		if($_GET["keys"] == "restore")
		{
			// Restore Server Private & Public Keys
			$new_server_keys = file_upload("key_file", TRUE);
			if($new_server_keys != 1)
			{
				$restore_private_key = find_string("---TKPRIVATEKEY=", "---ENDTKPRIVATEKEY", $new_server_keys);
				$restore_public_key = find_string("---TKPUBLICKEY=", "---ENDTKPUBLICKEY", $new_server_keys);

				if(empty($restore_private_key) == FALSE && empty($restore_public_key) == FALSE)
				{
					$sql = "UPDATE `my_keys` SET `field_data` = '" . base64_decode($restore_private_key) . "' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";
					$sql2 = "UPDATE `my_keys` SET `field_data` = '" . base64_decode($restore_public_key) . "' WHERE `my_keys`.`field_name` = 'server_public_key' LIMIT 1";

					if(mysqli_query($db_connect, $sql) == TRUE && mysqli_query($db_connect, $sql2) == TRUE)
					{
						$my_private_key = $restore_private_key;
						$my_public_key = $restore_public_key;

						if(my_private_key(TRUE) == TRUE)
						{
							// Private Key Encrypted
							mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = 'private_key_crypt'");
							mysqli_query($db_connect, "INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('private_key_crypt', '1')");
							$key_message = '<font color="green"><strong>*** <font color="red">Encrypted</font> Private & Public Key Restore Complete! ***</strong></font><br>';
						}
						else
						{
							mysqli_query($db_connect, "DELETE FROM `options` WHERE `options`.`field_name` = 'private_key_crypt'");
							$key_message = '<font color="green"><strong>*** Private & Public Key Restore Complete! ***</strong></font><br>';
						}						
					}
					else
					{
						$key_message = '<font color="red"><strong>PRIVATE & PUBLIC KEY RESTORE FAILED!</strong></font><br>';
					}
				}
				else
				{
					$key_message = '<font color="red"><strong>COULD NOT FIND THE PRIVATE & PUBLIC KEYS</strong></font><br>';
				}
			}
			else
			{
				// Delete Error, Alert User!
				$key_message = '<font color="red"><strong>SECURITY ISSUE! The Key File Was NOT Deleted After Upload.<br>You Will Need to Manually Delete It From the Plugins Folder.</strong></font><br>';
			}
		}

		$text_bar = '<table border="0" cellpadding="6"><tr><td><strong><font color="blue">Private Key</font> to encrypt transactions:</strong>' . $key_encrypted . '</td></tr>
		<tr><td style="width:672px"><textarea id="current_private_key" readonly="readonly" rows="8" style="width: 100%; max-width: 100%;">' . $my_private_key . '</textarea><br>
		<button title="Copy Private Key to Clipboard" onclick="myPrivateKey()"><span id="myTooltip">Copy Private Key</span></button></td></tr></table>
		<table border="0" cellpadding="6"><tr><td colspan="2"><strong><font color="green">Public Key</font> to receive:</strong></td></tr>
		<tr><td colspan="2" style="width:672px"><textarea id="current_public_key" readonly="readonly" rows="6" style="width: 100%; max-width: 100%;">' . $my_public_key . '</textarea><br>
		<button title="Copy Public Key to Clipboard" onclick="myPublicKey()"><span id="myTooltip2">Copy Public Key</span></button></td></tr>
		<tr><td colspan="2"><hr></td></tr><tr><td><FORM ACTION="index.php?menu=backup&amp;keys=download" METHOD="post"><input type="submit" value="Download Keys"/></FORM></td>
		<td>' . $key_message . '<strong>Use the Browse Button to Select the Key File to Restore</strong><br><br>
		<FORM ENCTYPE="multipart/form-data" METHOD="POST" ACTION="index.php?menu=backup&amp;keys=restore">
		<INPUT NAME="key_file" TYPE="file" SIZE=32><br><br>
		<input type="submit" name="SubmitNew" value="Restore Keys" onclick="return confirm(\'This Will Over-Write Your Existing Private & Public Keys. Continue?\');" /></FORM>
		</td></tr></table>';

		$quick_info = '<strong>Do Not</strong> share your <strong>Private Key</strong> with anyone for any reason.<br><br>
		The <strong>Private Key</strong> encrypts all transactions from your server.<br><br>
		You should make a backup of both keys in case you want to transfer your balance to a new client or restore from a client failure.<br><br>
		<strong><font color="blue">Download Keys</font></strong> will create a text file that can be used to restore the keys on this or a different client.<br><br>
		Save both keys in a password protected document or external device that you can secure (CD, Flash Drive, Printed Paper, etc.)';

		home_screen('Backup &amp; Restore Keys', $clipboard_copy . $text_bar, $body_string , $quick_info);
		exit;		
	}
//****************************************************************************
	if($_GET["menu"] == "logoff")
	{
		if($_SESSION["admin_login"] == TRUE)
		{
			// Stop all plugin services
			mysqli_query($db_connect, "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` LIKE 'TKBS_%'");
		}
		
		unset($_SESSION["valid_login"]);
		unset($_SESSION["login_username"]);
		unset($_SESSION["decrypt_password"]);
		unset($_SESSION["admin_login"]);
		header("Location: index.php");
		exit;		
	}
//****************************************************************************
} // End Valid Login Check
//****************************************************************************
?>
