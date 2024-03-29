<?PHP
include 'status.php';

define("TRANSACTION_EPOCH","1338576300"); // Epoch timestamp: 1338576300
define("ARBITRARY_KEY","01110100011010010110110101100101"); // Space filler for non-encryption data
define("SHA256TEST","8c49a2b56ebd8fc49a17956dc529943eb0d73c00ee6eafa5d8b3ba1274eb3ea4"); // Known SHA256 Test Result
define("TIMEKOIN_VERSION","4.1"); // This Timekoin Software Version
define("NEXT_VERSION","next_version7.txt"); // What file to check for future versions
// Easy Key Blackhole Public Key
define("EASY_KEY_PUBLIC_KEY","LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS1UaW1la29pbitFYXN5K0tleStibGFjaytob2xlK2FkZHJlc3MrV3ViYmErbHViYmErZHViK2R1YitSaWtraSt0aWtraSt0YXZpK2JpdGNoK0FuZCt0aGF0cyt0aGUrd2F5K3RoZStuZXdzK2dvZXMrSGl0K3RoZStzYWNrK0phY2srVWgrb2grc29tZXJzYXVsdCtqdW1wK0FpZHMrU2h1bStzaHVtK3NobGlwcGVkeStkb3ArR3Jhc3MrdGFzdGVzK2JhZCtObytqdW1waW5nK2luK3RoZStzZXdlcitCdXJnZXIrdGltZStSdWJiZXIrYmFieStiYWJieStCdW5rZXJzK1llYWgrc2F5K3RoYXQrYWxsK3RoZSt0aW1lK1RpbWVrb2luK0Vhc3krS2V5LS0tLS1FTkQgUFVCTElDIEtFWS0tLS0t");

error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR); // Disable most error reporting except for fatal errors
ini_set('display_errors', FALSE);
//***********************************************************************************
use mersenne_twister\twister;
//***********************************************************************************
if(function_exists('mysql_result') == FALSE)
{
	function mysql_result($result, $number = 0, $field = 0)
	{
		$sql_num_results = mysqli_num_rows($result);

		if($sql_num_results <= $number)
		{
			return NULL;
		}
		else
		{
			mysqli_data_seek($result, $number);
			$row = mysqli_fetch_array($result);
			return $row[$field];
		}
	}
}
//***********************************************************************************
//***********************************************************************************
function ip_banned($ip)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	
	// Check for banned IP address
	$ip = mysql_result(mysqli_query($db_connect, "SELECT ip FROM `ip_banlist` WHERE `ip` = '$ip' LIMIT 1"),0,0);

	if(empty($ip) == TRUE)
	{
		return FALSE;
	}
	else
	{
		// Sorry, your IP address has been banned :(
		return TRUE;
	}
}
//***********************************************************************************
//***********************************************************************************
function filter_sql($string)
{
	// Filter symbols that might lead to an SQL injection attack
	$symbols = array("'", "%", "*", "`");
	$string = str_replace($symbols, "", $string);

	return $string;
}
//***********************************************************************************
//***********************************************************************************
function log_ip($attribute, $multiple = 1, $super_peer_check = FALSE)
{
	if($_SERVER['REMOTE_ADDR'] == "::1" || $_SERVER['REMOTE_ADDR'] == "127.0.0.1")
	{
		// Ignore Local Machine Address
		return;
	}

	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	if($super_peer_check == TRUE)
	{
		// Is Super Peer Enabled?
		$super_peer_mode = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'super_peer' LIMIT 1"),0,0);

		if($super_peer_mode > 0)
		{
			// Only count 1 in 4 IP for Super Peer Transaction Clerk to avoid
			// accidental banning of peers accessing high volume data.
			if(mt_rand(1,4) != 4)
			{
				return;
			}
		}
	}
	
	// Log IP Address Access
	$sql = "INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`) VALUES ";
	while($multiple >= 1)
	{
		if($multiple == 1)
		{
			$sql .= "('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', '$attribute')";
		}
		else
		{
			$sql .= "('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', '$attribute'),";
		}
		$multiple--;
	}
	
	mysqli_query($db_connect, $sql);
	return;
}
//***********************************************************************************
function scale_trigger($trigger = 100)
{
	// Scale the amount of copies of the IP based on the trigger set.
	// So for example, a trigger of 1 means that one event can trigger flood protection.
	// A trigger of 2 means 2 events will trigger flood protection. So only half as many
	// IP copies are returned in this function.
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$request_max = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'server_request_max' LIMIT 1"),0,0);

	return intval($request_max / $trigger);
}
//***********************************************************************************
function find_string($start_tag, $end_tag, $full_string, $end_match = FALSE)
{
	if($end_match == FALSE)
	{	
		preg_match('|' . $start_tag . '(.*?)' . $end_tag . '|', $full_string, $output);
		return $output[1];
	}
	else
	{
		preg_match('|' . $start_tag . '(.*)|', $full_string, $output);
		return $output[1];
	}
}
//***********************************************************************************
//***********************************************************************************
function write_log($message, $type)
{
	// Write Log Entry
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);	
	mysqli_query($db_connect, "INSERT INTO `activity_logs` (`timestamp` ,`log` ,`attribute`)	
		VALUES ('" . time() . "', '" . filter_sql(substr($message, 0, 256)) . "', '$type')");
	return;
}
//***********************************************************************************
//***********************************************************************************
function generation_peer_hash()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT public_key, join_peer_list FROM `generating_peer_list` ORDER BY `join_peer_list` ASC";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	$generating_hash = 0;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			$generating_hash .= $sql_row["public_key"] . $sql_row["join_peer_list"];
		}
	}

	return hash('md5', $generating_hash);
}
//***********************************************************************************
//***********************************************************************************
function transaction_cycle($past_or_future = 0, $transaction_cycles_only = 0)
{
	$transaction_cycles = (time() - TRANSACTION_EPOCH) / 300;

	// Return the last transaction cycle
	if($transaction_cycles_only == TRUE)
	{
		return intval($transaction_cycles + $past_or_future);
	}
	else
	{
		return TRANSACTION_EPOCH + (intval($transaction_cycles + $past_or_future) * 300);
	}
}
//***********************************************************************************
//***********************************************************************************
function foundation_cycle($past_or_future = 0, $foundation_cycles_only = 0)
{
	$foundation_cycles = (time() - TRANSACTION_EPOCH) / 150000;

	// Return the last transaction cycle
	if($foundation_cycles_only == TRUE)
	{
		return intval($foundation_cycles + $past_or_future);
	}
	else
	{
		return TRANSACTION_EPOCH + (intval($foundation_cycles + $past_or_future) * 150000);
	}
}
//***********************************************************************************
//***********************************************************************************
function transaction_history_hash()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$hash = mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `transaction_history`"),0);

	$previous_foundation_block = foundation_cycle(-1, TRUE);
	$current_foundation_cycle = foundation_cycle(0);
	$next_foundation_cycle = foundation_cycle(1);			

	$current_generation_block = transaction_cycle(0, TRUE);
	$current_foundation_block = foundation_cycle(0, TRUE);

	// Check to make sure enough lead time exist before another transaction foundation is built.
	// (50 blocks) or over 4 hours
	if($current_generation_block - ($current_foundation_block * 500) > 50)
	{
		$current_history_foundation = mysql_result(mysqli_query($db_connect, "SELECT hash FROM `transaction_foundation` WHERE `block` = $previous_foundation_block LIMIT 1"),0,0);
		$hash .= $current_history_foundation;
	}

	$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $current_foundation_cycle AND `timestamp` < $next_foundation_cycle AND `attribute` = 'H' ORDER BY `timestamp` ASC";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);
		$hash .= $sql_row["hash"];
	}	

	return hash('md5', $hash);
}
//***********************************************************************************
//***********************************************************************************
function queue_hash()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT * FROM `transaction_queue` ORDER BY `hash`, `timestamp` ASC";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	$transaction_queue_hash = 0;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			$transaction_queue_hash .= $sql_row["timestamp"] . $sql_row["public_key"] . $sql_row["crypt_data1"] . 
			$sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];
		}
	
		return hash('md5', $transaction_queue_hash);
	}

	return 0;
}
//***********************************************************************************
function filter_public_key($public_key)
{
	if($public_key != ARBITRARY_KEY)
	{
		// Filter any characters or values that do not belong in a public key
		$public_key = preg_replace("|[^\\a-zA-Z0-9\s\s+-/=]|", "", $public_key);
		return $public_key;
	}

	// Not a public key, return the original string
	return $public_key;
}
//***********************************************************************************
//***********************************************************************************
function perm_peer_mode()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$perm_peer_priority = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'perm_peer_priority' LIMIT 1"),0,0));

	if($perm_peer_priority == 1)
	{
		return "SELECT * FROM `active_peer_list` WHERE `join_peer_list` = 0 ORDER BY RAND()";
	}
	else
	{
		return "SELECT * FROM `active_peer_list` ORDER BY RAND()";
	}
}
//***********************************************************************************
function my_public_key()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	return mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,0);
}
//***********************************************************************************
function my_private_key()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);	
	return mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,0);
}
//***********************************************************************************
function my_subfolder()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	return mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'server_subfolder' LIMIT 1"),0,0);
}
//***********************************************************************************
function my_port_number()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	return mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,0);
}
//***********************************************************************************
function my_domain()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	return mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'server_domain' LIMIT 1"),0,0);
}
//***********************************************************************************
function modify_peer_grade($ip_address, $domain, $subfolder, $port_number, $grade)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$peer_failure = mysql_result(mysqli_query($db_connect, "SELECT failed_sent_heartbeat FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1"));
	$join_peer_list = mysql_result(mysqli_query($db_connect, "SELECT join_peer_list FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1"));

	if($join_peer_list > 0) // Don't change anything for permanent peers
	{
		$peer_failure+= $grade;

		// Range adjustment for first contact and gateway peers
		if($peer_failure > 63500 && $peer_failure < 64000) { return; }
		if($peer_failure > 64500 && $peer_failure < 65000) { return; }		
		
		if($peer_failure >= 0)
		{
			mysqli_query($db_connect, "UPDATE `active_peer_list` SET `failed_sent_heartbeat` = $peer_failure WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
		}
	}
	return;
}
//***********************************************************************************
function poll_peer($ip_address, $domain, $subfolder, $port_number, $max_length, $poll_string, $custom_context = "")
{
	if(empty($custom_context) == TRUE)
	{
		// Standard socket close
		$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	}
	else
	{
		// Custom Context Data
		$context = $custom_context;
	}

	if(empty($domain) == TRUE)
	{
		if(filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == TRUE)
		{
			// IP Address is IPv6
			// Fix up the format for proper polling
			$ip_address = "[" . $ip_address . "]";
		}
		
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

	if(empty($subfolder) == FALSE)
	{
		// Sub-folder included
		$poll_data = filter_sql(file_get_contents("http$ssl://$site_address:$port_number/$subfolder/$poll_string", FALSE, $context, NULL, $max_length));
	}
	else
	{
		// No sub-folder
		$poll_data = filter_sql(file_get_contents("http$ssl://$site_address:$port_number/$poll_string", FALSE, $context, NULL, $max_length));
	}

	return $poll_data;
}
//***********************************************************************************
//***********************************************************************************
function call_script($script, $priority = 1, $plugin = FALSE, $web_server_call = FALSE)
{
	if($web_server_call == TRUE)
	{
		// No Properly working PHP CLI Extensions for some odd reason, call from web server instead
		$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
		$cli_port = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'cli_port' LIMIT 1"),0,0);
		
		if(empty($cli_port) == TRUE)
		{
			// Use the same server port that is reported to other peers
			if($plugin == TRUE)
			{
				poll_peer(NULL, "localhost", my_subfolder() . "/plugins", my_port_number(), 1, $script);
			}
			else
			{
				poll_peer(NULL, "localhost", my_subfolder(), my_port_number(), 1, $script);
			}
		}
		else
		{
			// Use a different port number than what is reported to other peers.
			// Useful for port forwarding where the External Internet port is different than
			// the Internal web server port being forwarded through the router.
			if($plugin == TRUE)
			{
				poll_peer(NULL, "localhost", my_subfolder() . "/plugins", $cli_port, 1, $script);
			}
			else
			{
				poll_peer(NULL, "localhost", my_subfolder(), $cli_port, 1, $script);
			}			
		}
	}
	else if($priority == 1)
	{
		// Normal Priority
		if(getenv("OS") == "Windows_NT")
		{
			pclose(popen("start /B php-win $script", "r"));// This will execute without waiting for it to finish
		}
		else
		{
			exec("php $script &> /dev/null &"); // This will execute without waiting for it to finish
		}
	}
	else if($plugin == TRUE)
	{
		// Normal Priority
		if(getenv("OS") == "Windows_NT")
		{
			pclose(popen("start /B php-win plugins/$script", "r"));// This will execute without waiting for it to finish
		}
		else
		{
			exec("php plugins/$script &> /dev/null &"); // This will execute without waiting for it to finish
		}
	}
	else
	{
		// Below Normal Priority
		if(getenv("OS") == "Windows_NT")
		{
			pclose(popen("start /BELOWNORMAL /B php-win $script", "r"));// This will execute without waiting for it to finish
		}
		else
		{
			exec("nice php $script &> /dev/null &"); // This will execute without waiting for it to finish
		}
	}

	return;
}
//***********************************************************************************
function clone_script($script)
{
	// No Properly working PHP CLI Extensions for some odd reason, call from web server instead
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$cli_port = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'cli_port' LIMIT 1"),0,0);

	if(empty($cli_port) == TRUE)
	{
		// Use the same server port that is reported to other peers
		poll_peer(NULL, "localhost", my_subfolder(), my_port_number(), 1, $script);
	}
	else
	{
		// Use a different port number than what is reported to other peers.
		// Useful for port forwarding where the External Internet port is different than
		// the Internal web server port being forwarded through the router.
		poll_peer(NULL, "localhost", my_subfolder(), $cli_port, 1, $script);
	}
}
//***********************************************************************************
function walkhistory($block_start = 0, $block_end = 0)
{
	$current_generation_cycle = transaction_cycle(0);
	$current_generation_block = transaction_cycle(0, TRUE);	
	
	$wrong_timestamp = 0;
	$wrong_hash = 0;

	$first_wrong_block = 0;

	if($block_end == 0)
	{
		$block_counter = $current_generation_block;
	}
	else
	{
		$block_counter = $block_end + 1;
	}

	if($block_start == 0)
	{
		$next_timestamp = TRANSACTION_EPOCH;
	}
	else
	{
		$next_timestamp = TRANSACTION_EPOCH + ($block_start * 300);
	}

	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	for ($i = $block_start; $i < $block_counter; $i++)
	{
		$time1 = transaction_cycle(0 - $current_generation_block + $i);
		$time2 = transaction_cycle(0 - $current_generation_block + 1 + $i);	

		$time3 = transaction_cycle(0 - $current_generation_block + 1 + $i);
		$time4 = transaction_cycle(0 - $current_generation_block + 2 + $i);
		$next_hash = mysql_result(mysqli_query($db_connect, "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,0);

		$sql = "SELECT timestamp, public_key_from, public_key_to, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash` ASC";

		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		$my_hash = 0;

		$timestamp = 0;

		for ($h = 0; $h < $sql_num_results; $h++)
		{
			$sql_row = mysqli_fetch_array($sql_result);

			if($sql_row["attribute"] == "T" || $sql_row["attribute"] == "G")
			{
				if(strlen($sql_row["public_key_from"]) > 300 && strlen($sql_row["public_key_to"]) > 300)
				{
					$my_hash .= $sql_row["hash"];
				}
			}

			if($sql_row["attribute"] == "H" || $sql_row["attribute"] == "B")
			{
				$timestamp = $sql_row["timestamp"];

				$my_hash .= $sql_row["hash"];
			}
		}		

		if($next_timestamp != $timestamp)
		{
			$wrong_timestamp++;
			$first_wrong_block = $i;
			break;
		}
		
		$next_timestamp = $next_timestamp + 300;

		$my_hash = hash('sha256', $my_hash);

		if($my_hash == $next_hash)
		{
			// Good match for hash
		}
		else
		{
			// Wrong match for hash
			$wrong_hash++;
			$first_wrong_block = $i;
			break;
		}
	}

	if($wrong_timestamp > 0 || $wrong_hash > 0)
	{
		// Range of history walk contains errors, return the first block that the error
		// started at
		return $first_wrong_block;
	}
	else
	{
		// No errors found
		return 0;
	}
}
//***********************************************************************************
//***********************************************************************************
function count_transaction_hash()
{
	// Check server balance via custom memory index
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$count_transaction_hash = mysql_result(mysqli_query($db_connect, "SELECT balance FROM `balance_index` WHERE `public_key_hash` = 'count_transaction_hash' LIMIT 1"));
	$count_transaction_hash_last = mysql_result(mysqli_query($db_connect, "SELECT block FROM `balance_index` WHERE `public_key_hash` = 'count_transaction_hash' LIMIT 1"));

	if($count_transaction_hash == "")
	{
		// Does not exist, needs to be created
		mysqli_query($db_connect, "INSERT INTO `balance_index` (`block` ,`public_key_hash` ,`balance`) VALUES ('0', 'count_transaction_hash', '0')");

		// Update record with the latest total
		$total_trans_hash = mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `transaction_history` USE INDEX(attribute) WHERE `attribute` = 'H'"));
		mysqli_query($db_connect, "UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$total_trans_hash' WHERE `balance_index`.`public_key_hash` = 'count_transaction_hash' LIMIT 1");
	}
	else
	{
		if(time() - $count_transaction_hash_last > 300) // 300s cache time
		{
			// Update new hash count and cache time
			$total_trans_hash = mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `transaction_history` USE INDEX(attribute) WHERE `attribute` = 'H'"));
			mysqli_query($db_connect, "UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$total_trans_hash' WHERE `balance_index`.`public_key_hash` = 'count_transaction_hash' LIMIT 1");
		}
		else
		{
			$total_trans_hash = $count_transaction_hash;
		}
	}

	return $total_trans_hash;
}
//***********************************************************************************
//***********************************************************************************
function reset_transaction_hash_count()
{
	// Clear transaction count cache
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	mysqli_query($db_connect, "DELETE FROM `balance_index` WHERE `balance_index`.`public_key_hash` = 'count_transaction_hash' LIMIT 1");
	return;
}
//***********************************************************************************
//***********************************************************************************
function tk_encrypt($key, $crypt_data)
{
	if(function_exists('openssl_private_encrypt') == TRUE)
	{
		openssl_private_encrypt($crypt_data, $encrypted_data, $key, OPENSSL_PKCS1_PADDING);

		if(empty($encrypted_data) == TRUE)
		{
			// OpenSSL Encryption Limit Reached, try Native RSA
			require_once('RSA.php');
			$rsa = new Crypt_RSA();
			$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
			$rsa->loadKey($key);
			$encrypted_data = $rsa->encrypt($crypt_data);
		}
	}
	else
	{
		require_once('RSA.php');
		$rsa = new Crypt_RSA();
		$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		$rsa->loadKey($key);
		$encrypted_data = $rsa->encrypt($crypt_data);
	}

	return $encrypted_data;
}
//***********************************************************************************
//***********************************************************************************
function set_decrypt_mode()
{
	if(function_exists('openssl_public_decrypt') == TRUE)
	{
		$GLOBALS['decrypt_mode'] = 1;
	}
	else
	{
		$GLOBALS['decrypt_mode'] = 2;
	}
	return;
}
//***********************************************************************************
//***********************************************************************************
function tk_decrypt($key, $crypt_data, $skip_openssl_check = FALSE)
{
	$decrypt;

	if($skip_openssl_check == TRUE || function_exists('openssl_public_decrypt') == TRUE)
	{
		// Use OpenSSL if it is working
		openssl_public_decrypt($crypt_data, $decrypt, $key, OPENSSL_PKCS1_PADDING);

		if(empty($decrypt) == TRUE)
		{
			// OpenSSL can't decrypt this for some reason
			// Use built in Code instead
			require_once('RSA.php');
			$rsa = new Crypt_RSA();
			$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
			$rsa->loadKey($key);
			$decrypt = $rsa->decrypt($crypt_data);
		}
	}
	else
	{
		// Use built in Code
		require_once('RSA.php');
		$rsa = new Crypt_RSA();
		$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		$rsa->loadKey($key);
		$decrypt = $rsa->decrypt($crypt_data);
	}

	return $decrypt;
}
//***********************************************************************************
//***********************************************************************************
function check_crypt_balance_range($public_key, $block_start = 0, $block_end = 0)
{
	set_decrypt_mode(); // Figure out which decrypt method can be best used

	//Initialize objects for Internal RSA decrypt
	if($GLOBALS['decrypt_mode'] == 2)
	{
		require_once('RSA.php');
		$rsa = new Crypt_RSA();
		$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
	}

	if($block_start == 0 && $block_end == 0)// Find every TimeKoin ever sent to and from this public Key
	{
		$sql = "SELECT public_key_from, public_key_to, crypt_data3, attribute FROM `transaction_history` WHERE `public_key_from` = '$public_key' OR `public_key_to` = '$public_key' ";
	}
	else
	{
		// Find every TimeKoin sent to and from this public Key in a certain time range.
		// Covert block to time.
		$start_time_range = TRANSACTION_EPOCH + ($block_start * 300);
		$end_time_range = TRANSACTION_EPOCH + ($block_end * 300);

		$sql = "SELECT public_key_from, public_key_to, crypt_data3, attribute FROM `transaction_history` WHERE (`public_key_from` = '$public_key' AND `timestamp` >= '$start_time_range' AND `timestamp` < '$end_time_range')
		OR (`public_key_to` = '$public_key' AND `timestamp` >= '$start_time_range' AND `timestamp` < '$end_time_range')";
	}
	
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);
	$crypto_balance = 0;
	$transaction_info;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_row($sql_result);

		$public_key_from = $sql_row[0];
		$public_key_to = $sql_row[1];
		$crypt3 = $sql_row[2];
		$attribute = $sql_row[3];

		if($attribute == "G" && $public_key_from == $public_key_to) // Everything generated by this public key
		{
			// Currency Generation
			// Decrypt transaction information
			if($GLOBALS['decrypt_mode'] == 2)
			{
				$rsa->loadKey($public_key_from);
				$transaction_info = $rsa->decrypt(base64_decode($crypt3));
			}
			else
			{
				$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
			} 

			$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
			$crypto_balance += $transaction_amount_sent;
		}

		if($attribute == "T" && $public_key_to == $public_key) // Everything given to this public key
		{
			// Decrypt transaction information
			if($GLOBALS['decrypt_mode'] == 2)
			{
				$rsa->loadKey($public_key_from);
				$transaction_info = $rsa->decrypt(base64_decode($crypt3));
			}
			else
			{
				$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
			}
	
			$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
			$crypto_balance += $transaction_amount_sent;
		}

		if($attribute == "T" && $public_key_from == $public_key) // Everything spent from this public key
		{
			// Decrypt transaction information
			$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3));

			if($GLOBALS['decrypt_mode'] == 2)
			{
				$rsa->loadKey($public_key_from);
				$transaction_info = $rsa->decrypt(base64_decode($crypt3));
			}
			else
			{
				$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
			}

			$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
			$crypto_balance -= $transaction_amount_sent;
		}		
	}

	// Unset variable to free up RAM
	unset($sql_result);

	return $crypto_balance;
}
//***********************************************************************************
//***********************************************************************************
function check_crypt_balance($public_key)
{
	if(empty($public_key) == TRUE)
	{
		return 0;
	}

	// Do we already have an index to reference for faster access?
	$public_key_hash = hash('md5', $public_key);
	$current_transaction_block = transaction_cycle(0, TRUE);
	$current_foundation_block = foundation_cycle(0, TRUE);

	// Check to make sure enough lead time exist in advance to building
	// another balance index. (60 cycles) or 5 hours
	if($current_transaction_block - ($current_foundation_block * 500) > 60)
	{
		// -1 Foundation Blocks (Standard)
		$previous_foundation_block = foundation_cycle(-1, TRUE);
	}
	else
	{
		// -2 Foundation Blocks - Buffers 5 hours after the newest foundation block
		$previous_foundation_block = foundation_cycle(-2, TRUE);
	}

	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT block, balance FROM `balance_index` WHERE `block` = $previous_foundation_block AND `public_key_hash` = '$public_key_hash' LIMIT 1";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_row = mysqli_fetch_array($sql_result);

	if(empty($sql_row["block"]) == TRUE)// No index exist yet, so after the balance check is complete, record the result for later use
	{
		// Check if a Quantum Balance Index exist to shorten database access time
		$pk_md5 = hash('md5', $public_key);
		$sql2 = "SELECT max_foundation, balance FROM `quantum_balance_index` WHERE `public_key_hash` = '$pk_md5' LIMIT 1";
		$sql_result2 = mysqli_query($db_connect, $sql2);
		$sql_row2 = mysqli_fetch_array($sql_result2);

		if(empty($sql_row2["max_foundation"]) == TRUE)// No Quantum Balance Index exist for this Public Key
		{
			// How many Transaction Foundations Should QBI Cover for Range?
			// All Transaction Foundations up to the Last 500 
			// So 761 would only be the first 500, 1256 would only be the first 1000, etc.
			$qbi_max_foundation = (intval($current_foundation_block / 500)) * 500;

			// Does this many Transaction Foundations even exist to index against?
			$total_foundations = mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `transaction_foundation`"),0);

			if($total_foundations > $qbi_max_foundation)
			{
				// Create time range
				$qbi_end_time_range = $qbi_max_foundation * 500;
				$qbi_balance = check_crypt_balance_range($public_key, 0, $qbi_end_time_range);

				// Store QBI in database for more permanent future access
				mysqli_query($db_connect, "INSERT INTO `quantum_balance_index` (`public_key_hash` ,`max_foundation` ,`balance`)
					VALUES ('$pk_md5', '$qbi_max_foundation', '$qbi_balance')");
			}
			else
			{
				write_log("Incomplete Transaction History Unable to Create Quantum Balance Index", "BA");
			}
		}
		else
		{
			// Quantum Balance Index exist, use the balance recorded and the remaining time range afterwards that this QBI represents
			$qbi_max_foundation = $sql_row2["max_foundation"];
			$qbi_balance = $sql_row2["balance"];
		}		
		
		// Use QBI to Decrease DB time to calculate Public Key Balance
		// Create time range
		$start_time_range = $qbi_max_foundation * 500;
		$end_time_range = $previous_foundation_block * 500;
		$index_balance1 = check_crypt_balance_range($public_key, $start_time_range, $end_time_range);

		// Add in QBI Balance
		$index_balance1 += $qbi_balance;

		// Check balance between the last block and now
		$start_time_range = $end_time_range;
		$end_time_range = transaction_cycle(0, TRUE);
		$index_balance2 = check_crypt_balance_range($public_key, $start_time_range, $end_time_range);
		
		// Store index in database for future access
		mysqli_query($db_connect, "INSERT INTO `balance_index` (`block` ,`public_key_hash` ,`balance`)
		VALUES ('$previous_foundation_block', '$public_key_hash', '$index_balance1')");
		return ($index_balance1 + $index_balance2);
	}
	else // More Recent Index Available
	{
		$crypto_balance = $sql_row["balance"];

		// Check balance between the last block and now
		$start_time_range = $previous_foundation_block * 500;
		$end_time_range = transaction_cycle(0, TRUE);
		$index_balance = check_crypt_balance_range($public_key, $start_time_range, $end_time_range);		
		return ($crypto_balance + $index_balance);
	}
}
//***********************************************************************************
//***********************************************************************************
function num_gen_peers($exclude_last_24hours = FALSE, $group_public_key = FALSE)
{
	// How many peers are generating currency?
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	if($exclude_last_24hours == TRUE)
	{
		return intval(mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `generating_peer_list` WHERE `join_peer_list` < " . (time() - 86400))));
	}
	else if($group_public_key == TRUE)
	{
		return intval(mysql_result(mysqli_query($db_connect, "SELECT COUNT(DISTINCT `public_key`) FROM `generating_peer_list`")));
	}
	else
	{
		return intval(mysql_result(mysqli_query($db_connect, "SELECT COUNT(*) FROM `generating_peer_list`")));
	}
}
//***********************************************************************************
//***********************************************************************************
function easy_key_lookup($easy_key = "")
{
	// Lookup Easy Key in Transaction History
	if(empty($easy_key) == TRUE)
	{ 
		return;
	}
	else
	{
		$easy_key = filter_sql($easy_key);
	}

	// Does this easy key exist in the transaction history?
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	// Look back as far as 3 Months (26,298 Transaction Cycles or 7889400 Seconds)
	$month_back_cycles = transaction_cycle(-26298);
	$easy_key_public_key = base64_decode(EASY_KEY_PUBLIC_KEY);
	$sql = "SELECT public_key_from, crypt_data3 FROM `transaction_history` WHERE `timestamp` >= $month_back_cycles AND `public_key_to` = '$easy_key_public_key' ORDER BY `transaction_history`.`timestamp` ASC";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);
		$transaction_data = tk_decrypt($sql_row["public_key_from"], base64_decode($sql_row["crypt_data3"]));
		$transaction_message = find_string("---MSG=", "", $transaction_data, TRUE);

		if($transaction_message == $easy_key)
		{
			// Easy Key Found, Return Public Key Associated With It
			return $sql_row["public_key_from"];
		}
	}

	return; // No match found
}
//***********************************************************************************
//***********************************************************************************
function easy_key_reverse_lookup($public_key = "", $find_next = 1, $expire_timestamp = FALSE)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	// Look back as far as 3 Months (26,298 Transaction Cycles or 7,889,400 Seconds)
	$month_back_cycles = transaction_cycle(-26298);
	$easy_key_public_key = base64_decode(EASY_KEY_PUBLIC_KEY);

	if($expire_timestamp == TRUE)
	{
		$timestamp = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_history` WHERE `timestamp` >= $month_back_cycles AND `public_key_to` = '$easy_key_public_key' AND `public_key_from` = '$public_key' ORDER BY `transaction_history`.`timestamp` ASC LIMIT $find_next"),($find_next - 1),0);
		return $timestamp; // Return timestamp for creation to calculate expiration date
	}
	else
	{
		$crypt_data3 = mysql_result(mysqli_query($db_connect, "SELECT crypt_data3 FROM `transaction_history` WHERE `timestamp` >= $month_back_cycles AND `public_key_to` = '$easy_key_public_key' AND `public_key_from` = '$public_key' ORDER BY `transaction_history`.`timestamp` ASC LIMIT $find_next"),($find_next - 1),0);
		$transaction_data = tk_decrypt($public_key, base64_decode($crypt_data3));
		$transaction_message = find_string("---MSG=", "", $transaction_data, TRUE);
		return $transaction_message; // Matching Easy Key to Public Key
	}
}
//***********************************************************************************
//***********************************************************************************
function peer_gen_amount($public_key)
{
	// 1 week = 604,800 seconds
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$join_peer_list1 = mysql_result(mysqli_query($db_connect, "SELECT join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),0,0);
	$join_peer_list2 = mysql_result(mysqli_query($db_connect, "SELECT join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),1,0);
	$amount;

	if(empty($join_peer_list1) == TRUE || $join_peer_list1 < TRANSACTION_EPOCH)
	{
		// Not found in the generating peer list
		$amount = 0;
	}
	else
	{
		// How many weeks has this public key been in the peer list
		$peer_age = time() - $join_peer_list1;
		$peer_age = intval($peer_age / 604800);
		$amount = 0;

		switch($peer_age)
		{
			case 0:
				$amount = 1;
				break;

			case 1:
				$amount = 2;
				break;

			case ($peer_age >= 2 && $peer_age <= 3):
				$amount = 3;
				break;

			case ($peer_age >= 4 && $peer_age <= 7):
				$amount = 4;
				break;

			case ($peer_age >= 8 && $peer_age <= 15):
				$amount = 5;
				break;

			case ($peer_age >= 16 && $peer_age <= 31):
				$amount = 6;
				break;

			case ($peer_age >= 32 && $peer_age <= 63):
				$amount = 7;
				break;

			case ($peer_age >= 64 && $peer_age <= 127):
				$amount = 8;
				break;

			case ($peer_age >= 128 && $peer_age <= 255):
				$amount = 9;
				break;

			case ($peer_age >= 256):
				$amount = 10;
				break;

			default:
				$amount = 1;
				break;				
		}
	}

	if(empty($join_peer_list2) == TRUE || $join_peer_list2 < TRANSACTION_EPOCH)
	{
		// Not found in the generating peer list
		$amount+= 0;
	}
	else
	{
		// How many weeks has this public key been in the peer list
		$peer_age = time() - $join_peer_list2;
		$peer_age = intval($peer_age / 604800);
		$amount2 = 0;

		switch($peer_age)
		{
			case 0:
				$amount2 = 1;
				break;

			case 1:
				$amount2 = 2;
				break;

			case ($peer_age >= 2 && $peer_age <= 3):
				$amount2 = 3;
				break;

			case ($peer_age >= 4 && $peer_age <= 7):
				$amount2 = 4;
				break;

			case ($peer_age >= 8 && $peer_age <= 15):
				$amount2 = 5;
				break;

			case ($peer_age >= 16 && $peer_age <= 31):
				$amount2 = 6;
				break;

			case ($peer_age >= 32 && $peer_age <= 63):
				$amount2 = 7;
				break;

			case ($peer_age >= 64 && $peer_age <= 127):
				$amount2 = 8;
				break;

			case ($peer_age >= 128 && $peer_age <= 255):
				$amount2 = 9;
				break;

			case ($peer_age >= 256):
				$amount2 = 10;
				break;

			default:
				$amount2 = 1;
				break;				
		}
	}

	return $amount + $amount2;
}
//***********************************************************************************
//***********************************************************************************
function gen_lifetime_transactions($public_key, $high_priority = FALSE)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);	

	// Double md5 to keep key balances and lifetime transaction counts separate
	$public_key_hash = hash('md5', $public_key);
	$public_key_hash = hash('md5', $public_key_hash);
	$previous_foundation_block = foundation_cycle(-2, TRUE);
	$previous_foundation_block_time = foundation_cycle(-2);

	$generation_records_total = mysql_result(mysqli_query($db_connect, "SELECT balance FROM `balance_index` WHERE `public_key_hash` = '$public_key_hash' AND `block` = '$previous_foundation_block' LIMIT 1"));
	$generation_records_total2;

	if($high_priority == TRUE)
	{
		$high_priority = "HIGH_PRIORITY";
	}
	else
	{
		$high_priority = "";
	}

	if($generation_records_total == "")
	{
		// No cache exist yet, create a new one
		$generation_records_total = mysql_result(mysqli_query($db_connect, "SELECT $high_priority COUNT(*) FROM `transaction_history` WHERE `timestamp` <= $previous_foundation_block_time AND `public_key_to` = '$public_key' AND `attribute` = 'G'"));

		// Insert new Cache Total
		$sql = "INSERT $high_priority INTO `balance_index` (`block`, `public_key_hash`, `balance`) VALUES ('$previous_foundation_block', '$public_key_hash', '$generation_records_total')";
		mysqli_query($db_connect, $sql);

		// Find the remaining transaction counts
		$generation_records_total2 = mysql_result(mysqli_query($db_connect, "SELECT $high_priority COUNT(*) FROM `transaction_history` WHERE `timestamp` > $previous_foundation_block_time AND `public_key_to` = '$public_key' AND `attribute` = 'G'"));
	}
	else
	{
		// Use recent cache to speed up count
		$generation_records_total2 = mysql_result(mysqli_query($db_connect, "SELECT $high_priority COUNT(*) FROM `transaction_history` WHERE `timestamp` > $previous_foundation_block_time AND `public_key_to` = '$public_key' AND `attribute` = 'G'"));
	}

	return $generation_records_total + $generation_records_total2;
}
//***********************************************************************************
//***********************************************************************************
function getCharFreq($str, $chr = FALSE)
{
	$c = Array();
	if($chr !== FALSE) return substr_count($str, $chr);
	foreach(preg_split('//',$str,-1,1)as$v)($c[$v])?$c[$v]++ :$c[$v]=1;
	return $c;
}
//***********************************************************************************
//***********************************************************************************
function TKFoundationSeed()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$TK_foundation_seed = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'TKFoundationSeed' LIMIT 1"));
	return $TK_foundation_seed;
}
//***********************************************************************************
//***********************************************************************************
function scorePublicKey($public_key, $score_key = FALSE)
{
	// Get the last 343 characters of the public key to make it fair for those using longer or shorter public keys
	$public_key = substr($public_key, -343);

	$current_generation_block = transaction_cycle(0, TRUE);

	if(version_compare(PHP_VERSION, '7.1.0', '<') == TRUE)
	{
		require_once('mersenne_twister.php');// For Earlier PHP Versions (less than v7.1)
		$twister1 = new twister(TKFoundationSeed() + $current_generation_block);
		$mersenne_twister = TRUE;
	}
	else
	{
		mt_srand(TKFoundationSeed() + $current_generation_block);
	}

	$public_key_score = 0;
	$tkrandom_num = 0;
	$character = 0;

	if($score_key == TRUE)
	{
		$output_score_key;

		// Output what is being used to score the keys
		for ($i = 0; $i < 18; $i++)
		{
			if($mersenne_twister == FALSE)
			{
				$tkrandom_num = mt_rand(1, 35);
			}
			else
			{
				$tkrandom_num = $twister1->rangeint(1, 35);
			}

			$output_score_key .= " [" . base_convert($tkrandom_num, 10, 36) . "=$tkrandom_num]";  // Base 10 to Base 36 conversion
		}
		
		return $output_score_key;
	}

	for ($i = 0; $i < 18; $i++)
	{
		if($mersenne_twister == FALSE)
		{
			$tkrandom_num = mt_rand(1, 35);
		}
		else
		{
			$tkrandom_num = $twister1->rangeint(1, 35);
		}		

		$character = base_convert($tkrandom_num, 10, 36);  // Base 10 to Base 36 conversion
		$public_key_score += getCharFreq($public_key, $character);
	}

	return $public_key_score;
}
//***********************************************************************************
//***********************************************************************************
function tk_time_convert($time)
{
	if($time < 0)
	{
		return "Now";
	}
	
	if($time < 60)
	{
		if($time == 1)
		{
			$time .= " sec";
		}
		else
		{
			$time .= " secs";
		}
	}
	else if($time >= 60 && $time < 3600)
	{
		if($time >= 60 && $time < 120)
		{
			$time = intval($time / 60) . " min";
		}
		else
		{
			$time = intval($time / 60) . " mins";
		}
	}
	else if($time >= 3600 && $time < 86400)
	{
		if($time >= 3600 && $time < 7200)
		{
			$time = intval($time / 3600) . " hour";
		}
		else
		{
			$time = intval($time / 3600) . " hours";
		}
	}
	else if($time >= 86400)
	{
		if($time >= 86400 && $time < 172800)
		{
			$time = intval($time / 86400) . " day";
		}
		else
		{
			$time = intval($time / 86400) . " days";
		}		
	}

	return $time;
}
//***********************************************************************************
//***********************************************************************************
function election_cycle($when = 0, $ip_type = 1, $gen_peers_total = 0, $plugin_seed = FALSE)
{
	if($plugin_seed == FALSE)
	{
		$TKFoundationSeed = TKFoundationSeed();
	}
	else
	{
		$TKFoundationSeed = $plugin_seed;
	}
	
	if($ip_type == 1)
	{
		// IPv4 Election Cycle Checking
		// Check if a peer election should take place now or
		// so many cycles ahead in the future
		if($when == 0)
		{
			// Check right now
			$current_generation_cycle = transaction_cycle(0);
			$current_generation_block = transaction_cycle(0, TRUE);
		}
		else
		{
			// Sometime further in the future
			$current_generation_cycle = transaction_cycle($when);
			$current_generation_block = transaction_cycle($when, TRUE);
		}

		$str = strval($current_generation_cycle);
		$last3_gen = intval($str[strlen($str)-3]);

		if(version_compare(PHP_VERSION, '7.1.0', '<') == TRUE)
		{
			require_once('mersenne_twister.php');// For Earlier PHP Versions (less than v7.1)
			$twister1 = new twister($TKFoundationSeed + $current_generation_block);
			$mersenne_twister = TRUE;
		}
		else
		{
			mt_srand($TKFoundationSeed + $current_generation_block);
		}

		if($mersenne_twister == FALSE)
		{
			$tk_random_number = mt_rand(0, 9);
		}
		else
		{
			$tk_random_number = $twister1->rangeint(0, 9);
		}

		if($last3_gen + $tk_random_number > 16)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	else if($ip_type == 2)
	{
		// IPv6 Election Cycle Checking
		// Check if a peer election should take place now or
		// so many cycles ahead in the future
		if($when == 0)
		{
			// Check right now
			$current_generation_cycle = transaction_cycle(0);
			$current_generation_block = transaction_cycle(0, TRUE);
		}
		else
		{
			// Sometime further in the future
			$current_generation_cycle = transaction_cycle($when);
			$current_generation_block = transaction_cycle($when, TRUE);
		}

		$str = strval($current_generation_cycle);
		$last3_gen = intval($str[strlen($str)-3]);

		// Transpose waveform 180 degrees from IPv4 Generation
		if($last3_gen == 0)
		{
			$last3_gen = 5;
		}
		else if($last3_gen == 1)
		{
			$last3_gen = 6;
		}
		else if($last3_gen == 2)
		{
			$last3_gen = 7;
		}
		else if($last3_gen == 3)
		{
			$last3_gen = 8;
		}
		else if($last3_gen == 4)
		{
			$last3_gen = 9;
		}
		else
		{
			$last3_gen-= 5;
		}
		
		// Transpose waveform 180 degrees from IPv4 Generation
		if(version_compare(PHP_VERSION, '7.1.0', '<') == TRUE)
		{
			require_once('mersenne_twister.php');// For Earlier PHP Versions (less than v7.1)
			$twister1 = new twister($TKFoundationSeed + $current_generation_block);
			$mersenne_twister = TRUE;
		}
		else
		{		
			mt_srand($TKFoundationSeed + $current_generation_block);
		}

		if($mersenne_twister == FALSE)
		{
			$tk_random_number = mt_rand(0, 9);
			$ipv6_gen_peer_adapt = mt_rand(0, $gen_peers_total);
		}
		else
		{
			$tk_random_number = $twister1->rangeint(0, 9);
			$ipv6_gen_peer_adapt = $twister1->rangeint(0, $gen_peers_total);
		}

		// The more IPv6 Peers that Generate, the less often Peer Elections happen
		if($last3_gen + $tk_random_number > 16)
		{
			if($ipv6_gen_peer_adapt < 25)
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}
	}

	// No match to anything
	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function generation_cycle($when = 0)
{
	// Check if currency generation should take place now or
	// so many cycles ahead in the future
	if($when == 0)
	{
		// Check right now
		$current_generation_cycle = transaction_cycle(0);
		$current_generation_block = transaction_cycle(0, TRUE);
	}
	else
	{
		// Sometime further in the future
		$current_generation_cycle = transaction_cycle($when);
		$current_generation_block = transaction_cycle($when, TRUE);
	}

	$str = strval($current_generation_cycle);
	$last3_gen = intval($str[strlen($str)-3]);

	if(version_compare(PHP_VERSION, '7.1.0', '<') == TRUE)
	{
		require_once('mersenne_twister.php');// For Earlier PHP Versions (less than v7.1)
		$twister1 = new twister(TKFoundationSeed() + $current_generation_block);
		$mersenne_twister = TRUE;
	}
	else
	{
		mt_srand(TKFoundationSeed() + $current_generation_block);
	}
	
	if($mersenne_twister == FALSE)
	{
		$tk_random_number = mt_rand(0, 9);
	}
	else
	{
		$tk_random_number = $twister1->rangeint(0, 9);
	}

	if($last3_gen + $tk_random_number < 6)
	{
		return TRUE;
	}
	else
	{
		return FALSE;
	}

	// No match to anything
	return FALSE;	
}
//***********************************************************************************
//***********************************************************************************
function db_cache_balance($my_public_key)
{
	// Check server balance via custom memory index
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$my_server_balance = mysql_result(mysqli_query($db_connect, "SELECT balance FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,0);
	$my_server_balance_last = mysql_result(mysqli_query($db_connect, "SELECT block FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,0);

	if($my_server_balance == "")
	{
		// Does not exist, needs to be created
		mysqli_query($db_connect, "INSERT INTO `balance_index` (`block` ,`public_key_hash` ,`balance`) VALUES ('0', 'server_timekoin_balance', '0')");

		// Update record with the latest balance
		$display_balance = check_crypt_balance($my_public_key);

		mysqli_query($db_connect, "UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1");
	}
	else
	{
		if($my_server_balance_last < transaction_cycle(0) && time() - transaction_cycle(0) > 25) // Generate 25 seconds after cycle
		{
			// Last generated balance is older than the current cycle, needs to be updated
			// Update record with the latest balance
			$display_balance = check_crypt_balance($my_public_key);

			mysqli_query($db_connect, "UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1");
		}
		else
		{
			$display_balance = $my_server_balance;
		}
	}

	return $display_balance;
}
//***********************************************************************************
//***********************************************************************************
function send_timekoins($my_private_key, $my_public_key, $send_to_public_key, $amount, $message, $custom_timestamp = FALSE)
{
	$arr1 = str_split($send_to_public_key, round(strlen($send_to_public_key) / 2));

	$encryptedData1 = tk_encrypt($my_private_key, $arr1[0]);
	$encryptedData64_1 = base64_encode($encryptedData1);	

	$encryptedData2 = tk_encrypt($my_private_key, $arr1[1]);
	$encryptedData64_2 = base64_encode($encryptedData2);

	// Sanitization of message
	// Filter symbols that might lead to a transaction hack attack
	$symbols = array("|", "?", "="); // SQL + URL
	$message = str_replace($symbols, "", $message);

	// Trim any message to 64 characters max and filter any sql
	$message = filter_sql(substr($message, 0, 64));
	$transaction_data = "AMOUNT=$amount---TIME=" . time() . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2) . "---MSG=$message";
	$encryptedData3 = tk_encrypt($my_private_key, $transaction_data);

	$encryptedData64_3 = base64_encode($encryptedData3);
	$triple_hash_check = hash('sha256', $encryptedData64_1 . $encryptedData64_2 . $encryptedData64_3);

	if($custom_timestamp == FALSE)
	{
		// Standard Timestamp
		$custom_timestamp = time();
	}

	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES 
		('$custom_timestamp', '$my_public_key', '$encryptedData64_1', '$encryptedData64_2' , '$encryptedData64_3', '$triple_hash_check' , 'T')";

	if(mysqli_query($db_connect, $sql) == TRUE)
	{
		// Success code
		return TRUE;
	}
	else
	{
		return FALSE;
	}
}
//***********************************************************************************
//***********************************************************************************
function unix_timestamp_to_human($timestamp = "", $default_timezone, $format = 'D d M Y - H:i:s')
{
	if(empty($default_timezone) == FALSE)
	{	
		date_default_timezone_set($default_timezone);
	}
	
	if (empty($timestamp) || ! is_numeric($timestamp)) $timestamp = time();
	return ($timestamp) ? date($format, $timestamp) : date($format, $timestamp);
}
//***********************************************************************************
function gen_simple_poll_test($ip_address, $domain, $subfolder, $port_number)
{
	$simple_poll_fail = FALSE; // Reset Variable

	if(version_compare(PHP_VERSION, '7.1.0', '<') == TRUE)
	{
		require_once('mersenne_twister.php');// For Earlier PHP Versions (less than v7.1)
		$twister1 = new twister(TKFoundationSeed() + transaction_cycle(0, TRUE));
		$mersenne_twister = TRUE;
	}
	else
	{
		mt_srand(TKFoundationSeed() + transaction_cycle(0, TRUE));
	}

	// Grab random Transaction Foundation Hash
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	if($mersenne_twister == FALSE)
	{
		 // Range from Start to Last 5 Foundation Hash
		$rand_block = mt_rand(0,foundation_cycle(0, TRUE) - 5);
	}
	else
	{
		$rand_block = $twister1->rangeint(0,foundation_cycle(0, TRUE) - 5);
	}
	
	$random_foundation_hash = mysql_result(mysqli_query($db_connect, "SELECT hash FROM `transaction_foundation` WHERE `block` = $rand_block LIMIT 1"),0,0);

	// Grab random Transaction Hash
	if($mersenne_twister == FALSE)
	{	
		 // Range from Start to Last 1000 Transaction Hash
		$rand_block2 = mt_rand(transaction_cycle((0 - transaction_cycle(0, TRUE)), TRUE), transaction_cycle(-1000, TRUE));
	}
	else
	{
		$rand_block2 = $twister1->rangeint(transaction_cycle((0 - transaction_cycle(0, TRUE)), TRUE), transaction_cycle(-1000, TRUE));
	}

	$rand_block2 = transaction_cycle(0 - $rand_block2);
	$random_transaction_hash = mysql_result(mysqli_query($db_connect, "SELECT hash FROM `transaction_history` WHERE `timestamp` = $rand_block2 LIMIT 1"),0,0);
	$rand_block2 = ($rand_block2 - TRANSACTION_EPOCH - 300) / 300;

	if(empty($random_foundation_hash) == FALSE) // Make sure we had one to compare first
	{
		$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 64, "foundation.php?action=block_hash&block_number=$rand_block");

		// Is it valid?
		if(empty($poll_peer) == TRUE)
		{
			// No response?
			$simple_poll_fail = TRUE;
		}
		else
		{
			// Is it valid?
			if($poll_peer == $random_foundation_hash)
			{
				// Got a good response from an active Timekoin server
				$simple_poll_fail = FALSE;
			}
			else
			{
				// Wrong Response?
				$simple_poll_fail = TRUE;
			}
		}
	}

	if(empty($random_transaction_hash) == FALSE) // Make sure we had one to compare first
	{
		$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 64, "transclerk.php?action=block_hash&block_number=$rand_block2");

		// Is it valid?
		if(empty($poll_peer) == TRUE)
		{
			//No response?
			$simple_poll_fail = TRUE;
		}
		else
		{
			// Is it valid?
			if($poll_peer == $random_transaction_hash)
			{
				//Got a good response from an active Timekoin server
				$simple_poll_fail = FALSE;
			}
			else
			{
				//Wrong Response?
				$simple_poll_fail = TRUE;
			}
		}
	}

	return $simple_poll_fail;
}
//***********************************************************************************
function visual_walkhistory($transaction_cycle_start = 0, $block_end = 0)
{
	$output;

	$current_generation_block = transaction_cycle(0, TRUE);

	if($block_end <= $transaction_cycle_start)
	{
		$block_end = $transaction_cycle_start + 1;
	}

	if($block_end > $current_generation_block)
	{
		$block_end = $current_generation_block;
	}	

	$wrong_timestamp = 0;
	$wrong_block_numbers = NULL;
	$wrong_hash = 0;
	$wrong_hash_numbers = NULL;

	$next_timestamp = TRANSACTION_EPOCH + ($transaction_cycle_start * 300);
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	for ($i = $transaction_cycle_start; $i < $block_end; $i++)
	{
		$output .= '<tr><td class="style2">Transaction Cycle # ' . $i;
		$time1 = transaction_cycle(0 - $current_generation_block + $i);
		$time2 = transaction_cycle(0 - $current_generation_block + 1 + $i);	

		$time3 = transaction_cycle(0 - $current_generation_block + 1 + $i);
		$time4 = transaction_cycle(0 - $current_generation_block + 2 + $i);
		
		$next_hash = mysql_result(mysqli_query($db_connect, "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,0);

		$sql = "SELECT timestamp, public_key_from, public_key_to, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash` ASC";

		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		$my_hash = 0;
		$timestamp = 0;

		for ($h = 0; $h < $sql_num_results; $h++)
		{
			$sql_row = mysqli_fetch_array($sql_result);

			if($sql_row["attribute"] == "T" || $sql_row["attribute"] == "G")
			{
				if(strlen($sql_row["public_key_from"]) > 300 && strlen($sql_row["public_key_to"]) > 300)
				{
					$my_hash .= $sql_row["hash"];
				}
				else
				{
					$output .= '<br><font color=blue>Public Key Length Wrong<br>Timestamp: [' . $sql_row["timestamp"] . ']<br>Hash: [' . $sql_row["hash"] . ']</font><br>';
				}
			}

			if($sql_row["attribute"] == "H" || $sql_row["attribute"] == "B")
			{
				$timestamp = $sql_row["timestamp"];

				$my_hash .= $sql_row["hash"];
			}
		}		

		if($next_timestamp != $timestamp)
		{
			$output .= '<br><font color=red><strong>Hash Timestamp Sequence Wrong... Should Be: ' . $next_timestamp . '</strong></font>';
			$wrong_timestamp++;
			$wrong_block_numbers .= " " . $i;
		}
		
		$next_timestamp = $next_timestamp + 300;

		$my_hash = hash('sha256', $my_hash);

		$output .= '<br>Timestamp in Database: ' . $timestamp;
		$output .= '<br>Calculated Hash: ' . $my_hash;
		$output .= '<br>&nbsp;Database Hash : ' . $next_hash;

		if($my_hash == $next_hash)
		{
			$output .= '<br><font color=green>Hash Match...</font>';
		}
		else
		{
			$output .= '<br><font color=red><strong>Hash MISMATCH</strong></font></td></tr>';
			$wrong_hash++;
			$wrong_hash_numbers = $wrong_hash_numbers . " " . $i;			
		}
	}

	if(empty($wrong_block_numbers) == TRUE)
	{
		$wrong_block_numbers = '<font color="blue">None</font>';
	}

	if(empty($wrong_hash_numbers) == TRUE)
	{
		$wrong_hash_numbers = '<font color="blue">None</font>';
	}

	$finish_output;

	$finish_output .= '<tr><td class="style2"><font color="blue"><strong>Total Wrong Sequence: ' . $wrong_timestamp . '</strong></font>';
	$finish_output .= '<br><font color="red"><strong>Transaction Cycles Wrong:</strong></font><strong> ' . $wrong_block_numbers . '</strong></td></tr>';
	$finish_output .= '<tr><td class="style2"><font color="blue"><strong>Total Wrong Hash: ' . $wrong_hash . '</strong></font>';
	$finish_output .= '<br><font color="red"><strong>Transaction Cycles Wrong:</strong></font><strong> ' . $wrong_hash_numbers . '</strong></td></tr>';

	return $finish_output . $output . $finish_output;
}
//***********************************************************************************
//***********************************************************************************
function visual_repair($transaction_cycle_start = 0, $cycle_limit = 500)
{
	$current_transaction_cycle = transaction_cycle(0, TRUE);
	$output;

	if($cycle_limit == 0)
	{
		$cycle_limit = transaction_cycle(0, TRUE);
	}

	if($transaction_cycle_start == 0)
	{
		$transaction_cycle_start = 1;
	}

	$generation_arbitrary = ARBITRARY_KEY;

	// Wipe all blocks ahead
	$time_range1 = transaction_cycle(0 - $current_transaction_cycle + $transaction_cycle_start);
	$time_range2 = transaction_cycle(0 - $current_transaction_cycle + $transaction_cycle_start + $cycle_limit);

	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "DELETE QUICK FROM `transaction_history` WHERE `transaction_history`.`timestamp` >= $time_range1 AND `transaction_history`.`timestamp` <= $time_range2 AND `attribute` = 'H'";
	
	if(mysqli_query($db_connect, $sql) == TRUE)
	{
		$output .= '<tr><td class="style2">Clearing Hash Timestamps Ahead of Transaction Cycle #' . $transaction_cycle_start . '</td></tr>';
	}
	else
	{
		return '<tr><td class="style2">Database ERROR, stopping repair process...</td></tr>';
	}
	
	for ($t = $transaction_cycle_start; $t < $current_transaction_cycle; $t++)
	{
		if($cycle_limit < 0) // Finished
		{
			break;
		}

		$output .= "<tr><td><strong>Repairing Transaction Cycle# $t</strong>";

		$time1 = transaction_cycle(0 - $current_transaction_cycle - 1 + $t);
		$time2 = transaction_cycle(0 - $current_transaction_cycle + $t);

		$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash` ASC";

		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		$hash = 0;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			$hash.= $sql_row["hash"];
		}

		// Transaction hash
		$hash = hash('sha256', $hash);
		$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from` ,`public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
		VALUES ('$time2', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$hash', 'H')";

		if(mysqli_query($db_connect, $sql) == FALSE)
		{
			// Something failed
			$output .= '<br><strong><font color="red">Repair ERROR in Database</font></strong></td></tr>';
		}
		else
		{
			$output .= '<br><strong><font color="blue">Repair Complete...</font></strong></td></tr>';
		}

		$cycle_limit--;

	} // End for loop

	return $output;
}
//***********************************************************************************
//***********************************************************************************
function is_private_ip($ip, $ignore = FALSE)
{
	if(empty($ip) == TRUE)
	{
		return FALSE;
	}
	
	if($ignore == TRUE)
	{
		$result = FALSE;
	}
	else
	{
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) == FALSE)
		{
			$result = TRUE;
		}

		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) == FALSE)
		{
			$result = TRUE;
		}
	}
	
	return $result;
}
//***********************************************************************************
function is_domain_valid($domain)
{
	$result = TRUE;
	
	if(empty($domain) == TRUE)
	{
		$result = FALSE;		
	}

	if(filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == TRUE)
	{
		$result = FALSE;
	}

	if(filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == TRUE)
	{
		$result = FALSE;
	}

	if(is_private_ip($domain) == FALSE)
	{
		$result = FALSE;
	}

	if(strtolower($domain) == "localhost")
	{
		$result = FALSE;
	}

	return $result;
}
//***********************************************************************************
function auto_update_IP_address($new_start = FALSE)
{
	// IPv4 Update
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$generation_IPv4 = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'generation_IP' LIMIT 1"));
	$generation_IPv6 = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'generation_IP_v6' LIMIT 1"));

	if($new_start == TRUE)
	{
		$network_mode = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'network_mode' LIMIT 1")));

		if($network_mode == 1)
		{
			//Both IPv4 & IPv6 Mode
			if(empty($generation_IPv4) == FALSE && empty($generation_IPv6) == FALSE)
			{
				// No need to set first time
				return;
			}
		}
		else if($network_mode == 2)
		{
			//IPv4 Mode
			if(empty($generation_IPv4) == FALSE)
			{
				// No need to set first time
				return;
			}
		}
		else if($network_mode == 3)
		{
			//IPv6 Mode
			if(empty($generation_IPv6) == FALSE)
			{
				// No need to set first time
				return;
			}
		}
	}

	$poll_IP = filter_sql(poll_peer(NULL, 'ipv4.timekoin.net', NULL, 80, 46, "ipv4.php"));

	if(empty($generation_IPv4) == TRUE) // IP Field Empty
	{
		if(empty($poll_IP) == FALSE && ipv6_test($poll_IP) == FALSE)
		{
			if(mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$poll_IP' WHERE `options`.`field_name` = 'generation_IP' LIMIT 1") == TRUE)
			{
				write_log("Generation IPv4 Updated to ($poll_IP)", "GP");
			}
		}
	}
	else
	{
		// Check that existing IP still matches current IP and update if there is no match
		if($generation_IPv4 != $poll_IP)
		{
			if(empty($poll_IP) == FALSE && ipv6_test($poll_IP) == FALSE)
			{
				if(mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$poll_IP' WHERE `options`.`field_name` = 'generation_IP' LIMIT 1") == TRUE)
				{
					write_log("Generation IPv4 Updated from ($generation_IP) to ($poll_IP)", "GP");
				}
			}
		}
	}

	// IPv6 Update	
	$poll_IP = filter_sql(poll_peer(NULL, 'ipv6.timekoin.net', NULL, 80, 46, "ipv6.php"));

	if(empty($generation_IPv6) == TRUE) // IP Field Empty
	{
		if(empty($poll_IP) == FALSE && ipv6_test($poll_IP) == TRUE)
		{
			if(mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$poll_IP' WHERE `options`.`field_name` = 'generation_IP_v6' LIMIT 1") == TRUE)
			{
				write_log("Generation IPv6 Updated to ($poll_IP)", "GP");
			}
		}
	}
	else
	{
		// Check that existing IP still matches current IP and update if there is no match
		if($generation_IPv6 != $poll_IP)
		{
			if(empty($poll_IP) == FALSE && ipv6_test($poll_IP) == TRUE)
			{
				if(mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$poll_IP' WHERE `options`.`field_name` = 'generation_IP_v6' LIMIT 1") == TRUE)
				{
					write_log("Generation IPv6 Updated from ($generation_IP) to ($poll_IP)", "GP");
				}
			}
		}
	}	
}
//***********************************************************************************
function initialization_database()
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

	// Clear IP Activity and Banlist for next start
	mysqli_query($db_connect, "TRUNCATE TABLE `ip_activity`");
	mysqli_query($db_connect, "TRUNCATE TABLE `ip_banlist`");

	// Clear Active & New Peers List
	mysqli_query($db_connect, "DELETE FROM `active_peer_list` WHERE `active_peer_list`.`join_peer_list` != 0"); // Permanent Peers Ignored
	mysqli_query($db_connect, "TRUNCATE TABLE `new_peers_list`");

	// Record when started
	mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '" . time() . "' WHERE `options`.`field_name` = 'timekoin_start_time' LIMIT 1");
	// Main Loop Status & Active Options Setup
	// Truncate to Free RAM
	mysqli_query($db_connect, "TRUNCATE TABLE `main_loop_status`");
	$time = time();
	//**************************************
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('balance_last_heartbeat', '1')");	
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_last_heartbeat', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('generation_last_heartbeat', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('genpeer_last_heartbeat', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('main_heartbeat_active', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('main_last_heartbeat', '$time')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peerlist_last_heartbeat', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('queueclerk_last_heartbeat', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('transclerk_last_heartbeat', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('treasurer_last_heartbeat', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('watchdog_heartbeat_active', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('watchdog_last_heartbeat', '$time')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peer_transaction_start_blocks', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peer_transaction_performance', '10')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('block_check_back', '1')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('block_check_start', '0')");	
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('firewall_blocked_peer', '0')");	
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_block_check', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_block_check_end', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_block_check_start', '0')");	
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('generation_peer_list_no_sync', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('no_peer_activity', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('time_sync_error', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('transaction_history_block_check', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('update_available', '0')");
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('TKFoundationSeed', '0')");
	//**************************************
	// Copy values from Database to RAM Database
	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'allow_ambient_peer_restart' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('allow_ambient_peer_restart', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'allow_LAN_peers' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('allow_LAN_peers', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'server_request_max' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('server_request_max', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('max_active_peers', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('max_new_peers', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'trans_history_check' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('trans_history_check', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'super_peer' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('super_peer', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'perm_peer_priority' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('perm_peer_priority', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'auto_update_generation_IP' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('auto_update_generation_IP', '$db_to_RAM')");
	
	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'peer_failure_grade' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peer_failure_grade', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'network_mode' LIMIT 1"),0,0);
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('network_mode', '$db_to_RAM')");	
	//**************************************
	//***********************************************************************************
	// Check that the TK Foundation Seed is set in memory
	// Do we have a valid and recent transaction foundation to access?
	$TK_foundation_seed_block = foundation_cycle(-2, TRUE);
	$TK_foundation_seed_hash = mysql_result(mysqli_query($db_connect, "SELECT hash FROM `transaction_foundation` WHERE `block` = $TK_foundation_seed_block LIMIT 1"),0,0);
	
	if(empty($TK_foundation_seed_hash) == FALSE)
	{
		// Create a number from the hash to seed the TK random number generator
		$number_seed = NULL;
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 1);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 2);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 3);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 4);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 5);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 6);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 7);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 8);
		$number_seed.=	getCharFreq($TK_foundation_seed_hash, 9);

		// Save new seed number
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '$number_seed' WHERE `main_loop_status`.`field_name` = 'TKFoundationSeed' LIMIT 1");
	}
	else
	{
		write_log("Missing Transaction Foundation #$TK_foundation_seed_block to Build TK Foundation Seed", "MA");
	}
	//***********************************************************************************
	// Auto Detect IP Address on Start if Empty
	auto_update_IP_address(TRUE);
	//***********************************************************************************
	return 0;
}
//***********************************************************************************
//***********************************************************************************
function activate($component = "SYSTEM", $on_or_off = 1)
{
	// Turn the entire or a single script on or off
	$build_file = '<?PHP ';

	// Check what the current constants are
	if($component != "TIMEKOINSYSTEM")	{ $build_file = $build_file . ' define("TIMEKOIN_DISABLED","' . TIMEKOIN_DISABLED . '"); '; }
	if($component != "FOUNDATION") { $build_file = $build_file . ' define("FOUNDATION_DISABLED","' . FOUNDATION_DISABLED . '"); '; }
	if($component != "GENERATION") { $build_file = $build_file . ' define("GENERATION_DISABLED","' . GENERATION_DISABLED . '"); '; }
	if($component != "GENPEER") { $build_file = $build_file . ' define("GENPEER_DISABLED","' . GENPEER_DISABLED . '"); '; }
	if($component != "PEERLIST") { $build_file = $build_file . ' define("PEERLIST_DISABLED","' . PEERLIST_DISABLED . '"); '; }
	if($component != "QUEUECLERK") { $build_file = $build_file . ' define("QUEUECLERK_DISABLED","' . QUEUECLERK_DISABLED . '"); '; }
	if($component != "TRANSCLERK") { $build_file = $build_file . ' define("TRANSCLERK_DISABLED","' . TRANSCLERK_DISABLED . '"); '; }
	if($component != "TREASURER") { $build_file = $build_file . ' define("TREASURER_DISABLED","' . TREASURER_DISABLED . '"); '; }
	if($component != "BALANCE") { $build_file = $build_file . ' define("BALANCE_DISABLED","' . BALANCE_DISABLED . '"); '; }
	if($component != "API") { $build_file = $build_file . ' define("API_DISABLED","' . API_DISABLED . '"); '; }			

	switch($component)
	{
		case "TIMEKOINSYSTEM":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("TIMEKOIN_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("TIMEKOIN_DISABLED","0"); ';
			}
			break;

		case "FOUNDATION":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("FOUNDATION_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("FOUNDATION_DISABLED","0"); ';
			}
			break;

		case "GENERATION":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("GENERATION_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("GENERATION_DISABLED","0"); ';
			}
			break;

		case "GENPEER":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("GENPEER_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("GENPEER_DISABLED","0"); ';
			}
			break;

		case "PEERLIST":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("PEERLIST_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("PEERLIST_DISABLED","0"); ';
			}
			break;

		case "QUEUECLERK":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("QUEUECLERK_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("QUEUECLERK_DISABLED","0"); ';
			}
			break;

		case "TRANSCLERK":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("TRANSCLERK_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("TRANSCLERK_DISABLED","0"); ';
			}
			break;

		case "TREASURER":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("TREASURER_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("TREASURER_DISABLED","0"); ';
			}
			break;

		case "BALANCE":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("BALANCE_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("BALANCE_DISABLED","0"); ';
			}
			break;

		case "API":
			if($on_or_off == 0)
			{
				$build_file = $build_file . ' define("API_DISABLED","1"); ';
			}
			else
			{
				$build_file = $build_file . ' define("API_DISABLED","0"); ';
			}
			break;			
	}

	$build_file = $build_file . ' ?' . '>';

	// Save status.php file to the same directory the script was
	// called from.
	$fh = fopen('status.php', 'w');

	if($fh != FALSE)
	{
		if(fwrite($fh, $build_file) > 0)
		{
			if(fclose($fh) == TRUE)
			{
				return TRUE;
			}
		}
	}

	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function generate_new_keys($bits = 1536)
{
	require_once('RSA.php');

	$rsa = new Crypt_RSA();
	extract($rsa->createKey($bits));

	if(empty($privatekey) == FALSE && empty($publickey) == FALSE)
	{
		$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
		
		$symbols = array("\r");
		$new_publickey = str_replace($symbols, "", $publickey);
		$new_privatekey = str_replace($symbols, "", $privatekey);

		$sql = "UPDATE `my_keys` SET `field_data` = '$new_privatekey' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";

		if(mysqli_query($db_connect, $sql) == TRUE)
		{
			// Private Key Update Success
			$sql = "UPDATE `my_keys` SET `field_data` = '$new_publickey' WHERE `my_keys`.`field_name` = 'server_public_key' LIMIT 1";
			
			if(mysqli_query($db_connect, $sql) == TRUE)
			{
				// Blank reverse crypto data field
				mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '' WHERE `options`.`field_name` = 'generation_key_crypt' LIMIT 1");

				// Public Key Update Success				
				return 1;
			}
		}
	}
	else
	{
		// Key Pair Creation Error
		return 0;
	}

	return 0;
}
//***********************************************************************************
//***********************************************************************************
function check_for_updates($code_feedback = FALSE)
{
	// Poll timekoin.net for any program updates
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 10); // Timeout for request in seconds

	$update_check1 = 'Checking for Updates....<br><br>';

	$poll_version = file_get_contents("http://timekoin.net/tkupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

	if($poll_version > TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		if($code_feedback == TRUE) { return 1; } // Code feedback only that update is available
		
		$update_check1 .= '<strong>New Version Available <font color="blue">' . $poll_version . '</font></strong><br><br>
		<FORM ACTION="index.php?menu=options&upgrade=doupgrade" METHOD="post"><input type="submit" name="Submit3" value="Perform Software Update" /></FORM>';
	}
	else if($poll_version <= TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		$update_check1 .= 'Current Version: <strong>' . TIMEKOIN_VERSION . '</strong><br><br><font color="blue">No Update Necessary.</font>';	
	}
	else
	{
		$update_check1 .= '<strong><font color="red">ERROR: Could Not Contact the Server http://timekoin.net</font></strong>';
	}

	return $update_check1;
}
//***********************************************************************************
//***********************************************************************************
function install_update_script($script_name, $script_file)
{
	$fh = fopen($script_name, 'w');

	if($fh != FALSE)
	{
		if(fwrite($fh, $script_file) > 0)
		{
			if(fclose($fh) == TRUE)
			{
				// Update Complete
				return '<font color="green"><strong>Update Complete...</strong></font><br><br>';
			}
			else
			{
				return '<font color="red"><strong>ERROR: Update FAILED with a file Close Error.</strong></font><br><br>';
			}
		}
	}
	else
	{
		return '<font color="red"><strong>ERROR: Update FAILED with unable to Open File Error.</strong></font><br><br>';
	}
}
//***********************************************************************************
//***********************************************************************************
function check_update_script($script_name, $script, $php_script_file, $poll_version, $context)
{
	$update_status_return = NULL;
	
	$poll_sha = file_get_contents("http://timekoin.net/tkupdates/v$poll_version/$script.sha", FALSE, $context, NULL, 64);

	if(empty($poll_sha) == FALSE)
	{
		$download_sha = hash('sha256', $php_script_file);

		if($download_sha != $poll_sha)
		{
			// Error in SHA match, file corrupt
			return FALSE;
		}
		else
		{
			$update_status_return .= 'Server SHA: <strong>' . $poll_sha . '</strong><br>Download SHA: <strong>' . $download_sha . '</strong><br>';
			$update_status_return .= '<strong>' . $script_name . '</strong> SHA Match...<br>';
			return $update_status_return;
		}
	}

	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function get_update_script($php_script, $poll_version, $context)
{
	return file_get_contents("http://timekoin.net/tkupdates/v$poll_version/$php_script.txt", FALSE, $context, NULL);
}
//***********************************************************************************
//***********************************************************************************
function run_script_update($script_name, $script_php, $poll_version, $context, $php_format = 1, $sub_folder = "")
{
	$php_file = get_update_script($script_php, $poll_version, $context);
	
	if(empty($php_file) == TRUE)
	{
		return ' - <strong>No Update Available</strong>...<br><br>';
	}
	else
	{
		// File exist, is the download valid?
		$sha_check = check_update_script($script_name, $script_php, $php_file, $poll_version, $context);

		if($sha_check == FALSE)
		{
			return ' - <strong><font color="red">ERROR: Unable to Download File Properly</font></strong>...<br><br>';
		}
		else
		{
			$update_status .= $sha_check;
			
			if($php_format == 1)
			{
				// PHP Files are downloaded as text, then renamed to the .php extension
				$update_status .= install_update_script($script_php . '.php', $php_file);
			}
			else
			{
				if(empty($sub_folder) == FALSE)
				{
					// This file is installed to a sub-folder
					$update_status .= install_update_script("$sub_folder/" . $script_php, $php_file);
				}
				else
				{
					$update_status .= install_update_script($script_php, $php_file);
				}
			}

			return $update_status;
		}
	}
}
//***********************************************************************************
function do_updates()
{
	// Poll timekoin.net for any program updates
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 10); // Timeout for request in seconds

	$poll_version = file_get_contents("http://timekoin.net/tkupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

	$update_status = 'Starting Update Process...<br><br>';

	if(empty($poll_version) == FALSE)
	{
		//****************************************************
		//Check for CSS updates
		$update_status .= 'Checking for <strong>CSS Template</strong> Update...<br>';
		$update_status .= run_script_update("CSS Template (admin.css)", "admin.css", $poll_version, $context, 0, "css");
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>RSA Code</strong> Update...<br>';
		$update_status .= run_script_update("RSA Code (RSA.php)", "RSA", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Openssl Template</strong> Update...<br>';
		$update_status .= run_script_update("Openssl Template (openssl.cnf)", "openssl.cnf", $poll_version, $context, 0);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>API Access</strong> Update...<br>';
		$update_status .= run_script_update("API Access (api.php)", "api", $poll_version, $context);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Balace Indexer</strong> Update...<br>';
		$update_status .= run_script_update("Balance Indexer (balance.php)", "balance", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Transaction Foundation Manager</strong> Update...<br>';
		$update_status .= run_script_update("Transaction Foundation Manager (foundation.php)", "foundation", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Currency Generation Manager</strong> Update...<br>';
		$update_status .= run_script_update("Currency Generation Manager (generation.php)", "generation", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Generation Peer Manager</strong> Update...<br>';
		$update_status .= run_script_update("Generation Peer Manager (genpeer.php)", "genpeer", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Web Interface</strong> Update...<br>';
		$update_status .= run_script_update("Timekoin Web Interface (index.php)", "index", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Main Program</strong> Update...<br>';
		$update_status .= run_script_update("Main Program (main.php)", "main", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Mersenne Twister Random Number Generator</strong> Update...<br>';
		$update_status .= run_script_update("Mersenne Twister (mersenne_twister.php)", "mersenne_twister", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Peer List Manager</strong> Update...<br>';
		$update_status .= run_script_update("Peer List Manager (peerlist.php)", "peerlist", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Transaction Queue Manager</strong> Update...<br>';
		$update_status .= run_script_update("Transaction Queue Manager (queueclerk.php)", "queueclerk", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Module Status</strong> Update...<br>';
		$update_status .= run_script_update("Timekoin Module Status (status.php)", "status", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Web Interface Template</strong> Update...<br>';
		$update_status .= run_script_update("Web Interface Template (templates.php)", "templates", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Transaction Clerk</strong> Update...<br>';
		$update_status .= run_script_update("Transaction Clerk (transclerk.php)", "transclerk", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Treasurer Processor</strong> Update...<br>';
		$update_status .= run_script_update("Treasurer Processor (treasurer.php)", "treasurer", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Process Watchdog</strong> Update...<br>';
		$update_status .= run_script_update("Process Watchdog (watchdog.php)", "watchdog", $poll_version, $context);
		//****************************************************
		// We do the function storage last because it contains the version info.
		// That way if some unknown error prevents updating the files above, this
		// will allow the user to try again for an update without being stuck in
		// a new version that is half-updated.
		$update_status .= 'Checking for <strong>Function Storage</strong> Update...<br>';
		$update_status .= run_script_update("Function Storage (function.php)", "function", $poll_version, $context);
		//****************************************************

		$finish_message = file_get_contents("http://timekoin.net/tkupdates/v$poll_version/ZZZfinish.txt", FALSE, $context, NULL);
		$update_status .= '<br>' . $finish_message;
	}
	else
	{
		$update_status .= '<font color="red"><strong>ERROR: Could Not Contact the Server http://timekoin.net</strong></font>';
	}

	return $update_status;
}
//***********************************************************************************
//***********************************************************************************
function plugin_check_for_updates($http_url, $ssl_enable = FALSE)
{
	// Example Usage
	//
	// plugin_check_for_updates("mysite.blah/updates/plugin_update_01.txt", TRUE)
	//
	// This would return what was in the text file, such as a version number of the latest
	// plugin version for example.

	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Server (Plugin) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 10); // Timeout for request in seconds

	if($ssl_enable == TRUE)
	{
		return file_get_contents("https://$http_url", FALSE, $context, NULL);
	}
	else
	{
		return file_get_contents("http://$http_url", FALSE, $context, NULL);
	}
}
//***********************************************************************************
function plugin_download_update($http_url, $http_url_sha256, $ssl_enable = FALSE, $plugin_file)
{
	// Example Usage
	//
	// plugin_download_update("mysite.blah/updates/plugin.txt", "mysite.com/updates/plugin.sha", TRUE, "myplugin.php")
	//
	// This would first download the file plugin.txt and then plugin.sha into memory.
	// Then the SHA256 of the file plugin.txt is compared to value of plugin.sha for a match.
	// If no SHA256 URL is used (NULL setting), then the hash check will be ignored.
	// Once the check passes (or ignored), the file name myplugin.php will be opened up for writing.
	// The downloaded file will be overwritten on top of the myplugin.php and then closed to complete the write.
	// This function should return a TRUE / (1) if successful and anything else will be an error number (0,2,3,4,5)

	$download_file;
	$download_file_SHA256;
	$sha256_check_pass = TRUE; // Default Pass if No SHA256 Used

	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Server (Plugin) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 10); // Timeout for request in seconds

	if($ssl_enable == TRUE)
	{
		$download_file = file_get_contents("https://$http_url", FALSE, $context, NULL);
		$download_file_SHA256 = file_get_contents("https://$http_url_sha256", FALSE, $context, NULL);
	}
	else
	{
		$download_file =  file_get_contents("http://$http_url", FALSE, $context, NULL);
		$download_file_SHA256 = file_get_contents("http://$http_url_sha256", FALSE, $context, NULL);
	}

	if(empty($download_file) == FALSE && empty($http_url_sha256) == FALSE)
	{
		// Check file against SHA256 Hash to make sure of no file corruption/tampering
		if(hash('sha256', $download_file) != $download_file_SHA256)
		{
			// No SHA256 Match, Error Back
			return 2;
		}
	}

	if(empty($download_file) == FALSE) // Downloaded file exist in memory
	{
		$fh = fopen($plugin_file, 'w'); // Open Plugin File for Writing

		if($fh != FALSE)
		{
			if(fwrite($fh, $download_file) > 0) // Overwrite Downloaded File directly to Plugin File
			{
				if(fclose($fh) == TRUE)
				{
					// Update Complete
					return TRUE;
				}
				else
				{
					// Update FAILED with a File Close Error
					return 3;
				}
			}
		}
		else
		{
			// Update FAILED with Unable to Open File Error.
			return 4;
		}	
	}
	else
	{
		// File Download Failed
		return 5;
	}

	// Unknown Error
	return FALSE;
}
//***********************************************************************************
function update_windows_port($new_port)
{
	// Update the pms_config.ini file if it exist
	if(file_exists("../../pms_config.ini") == TRUE)
	{
		//Previous port number
		$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
		$old_port = mysql_result(mysqli_query($db_connect, "SELECT * FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,"field_data");

		if($old_port != $new_port)// Don't change unless different than before
		{
			$pms_config = file_get_contents('../../pms_config.ini');
			$new_pms_config = str_replace("Port=$old_port", "Port=$new_port", $pms_config);

			// Write new configuration file back to drive
			$fh = fopen('../../pms_config.ini', 'w');

			if($fh != FALSE)
			{
				if(fwrite($fh, $new_pms_config) > 0)
				{
					if(fclose($fh) == TRUE)
					{
						return TRUE;
					}
				}
			}
		}
	}
	return;
}
//***********************************************************************************
//***********************************************************************************
function generate_hashcode_permissions($pk_balance = "", $pk_gen_amt = "", $pk_recv = "", $send_tk = "", $pk_history = "", $pk_valid = "", $tk_trans_total = "", $pk_sent = "", $pk_gen_total = "", $tk_process_status = "", $tk_start_stop = "", $easy_key = "", $num_gen_peers = "")
{
	$permissions_number = 0;

	if($pk_balance == 1) { $permissions_number += 1; }
	if($pk_gen_amt == 1) { $permissions_number += 2; }
	if($pk_recv == 1) { $permissions_number += 4; }
	if($send_tk == 1) { $permissions_number += 8; }
	if($pk_history == 1) { $permissions_number += 16; }
	if($pk_valid == 1) { $permissions_number += 32; }
	if($tk_trans_total == 1) { $permissions_number += 64; }
	if($pk_sent == 1) { $permissions_number += 128; }
	if($pk_gen_total == 1) { $permissions_number += 256; }
	if($tk_process_status == 1) { $permissions_number += 512; }
	if($tk_start_stop == 1) { $permissions_number += 1024; }
	if($easy_key == 1) { $permissions_number += 2048; }
	if($num_gen_peers == 1) { $permissions_number += 4096; }	

	return $permissions_number;
}
//***********************************************************************************
function check_hashcode_permissions($permissions_number, $pk_api_check, $checkbox = FALSE)
{
	// num_gen_peers
	if($pk_api_check == "num_gen_peers")
	{ 
		if($permissions_number >= 4096) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 4096 >= 0) { $permissions_number -= 4096; } // Subtract Active Permission

	// easy_key
	if($pk_api_check == "easy_key")
	{ 
		if($permissions_number >= 2048) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 2048 >= 0) { $permissions_number -= 2048; } // Subtract Active Permission

	// tk_start_stop
	if($pk_api_check == "tk_start_stop")
	{ 
		if($permissions_number >= 1024) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 1024 >= 0) { $permissions_number -= 1024; } // Subtract Active Permission

	// tk_process_status
	if($pk_api_check == "tk_process_status")
	{ 
		if($permissions_number >= 512) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 512 >= 0) { $permissions_number -= 512; } // Subtract Active Permission

	// pk_gen_total
	if($pk_api_check == "pk_gen_total")
	{ 
		if($permissions_number >= 256) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 256 >= 0) { $permissions_number -= 256; } // Subtract Active Permission

	// pk_sent
	if($pk_api_check == "pk_sent")
	{ 
		if($permissions_number >= 128) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 128 >= 0) { $permissions_number -= 128; } // Subtract Active Permission

	// tk_trans_total
	if($pk_api_check == "tk_trans_total")
	{ 
		if($permissions_number >= 64) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 64 >= 0) { $permissions_number -= 64; } // Subtract Active Permission

	// pk_valid
	if($pk_api_check == "pk_valid")
	{ 
		if($permissions_number >= 32) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 32 >= 0) { $permissions_number -= 32; } // Subtract Active Permission

	// pk_history
	if($pk_api_check == "pk_history")
	{ 
		if($permissions_number >= 16) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 16 >= 0) { $permissions_number -= 16; } // Subtract Active Permission

	// send_tk
	if($pk_api_check == "send_tk")
	{ 
		if($permissions_number >= 8) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 8 >= 0) { $permissions_number -= 8; } // Subtract Active Permission

	// pk_recv
	if($pk_api_check == "pk_recv")
	{ 
		if($permissions_number >= 4) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 4 >= 0) { $permissions_number -= 4; } // Subtract Active Permission

	// pk_gen_amt
	if($pk_api_check == "pk_gen_amt")
	{ 
		if($permissions_number >= 2) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}
	if($permissions_number - 2 >= 0) { $permissions_number -= 2; } // Subtract Active Permission

	// pk_balance
	if($pk_api_check == "pk_balance") // Permission Granted
	{ 
		if($permissions_number >= 1) // Permission Granted
		{
			if($checkbox == TRUE)
			{
				return "CHECKED";
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
	}

	// Some other error
	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function standard_tab_settings($peerlist, $trans_queue, $send_receive, $history, $generation, $system, $backup, $tools)
{
	$permissions_number = 0;

	if($peerlist == 1) { $permissions_number += 1; }
	if($trans_queue == 1) { $permissions_number += 2; }
	if($send_receive == 1) { $permissions_number += 4; }
	if($history == 1) { $permissions_number += 8; }
	if($generation == 1) { $permissions_number += 16; }
	if($system == 1) { $permissions_number += 32; }
	if($backup == 1) { $permissions_number += 64; }
	if($tools == 1) { $permissions_number += 128; }

	return $permissions_number;
}
//***********************************************************************************
//***********************************************************************************
function check_standard_tab_settings($permissions_number, $standard_tab)
{
// Tools Tab
	if($permissions_number - 256 >= 0) { $permissions_number -= 256; } // Subtract Active Permission
	if($standard_tab == 128)
	{ 
		if($permissions_number >= 128) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

// Backup Tab
	if($permissions_number - 128 >= 0) { $permissions_number -= 128; } // Subtract Active Permission
	if($standard_tab == 64)
	{ 
		if($permissions_number >= 64) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

// System Tab
	if($permissions_number - 64 >= 0) { $permissions_number -= 64; } // Subtract Active Permission
	if($standard_tab == 32)
	{ 
		if($permissions_number >= 32) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}	
	
// Generation Tab
	if($permissions_number - 32 >= 0) { $permissions_number -= 32; } // Subtract Active Permission
	if($standard_tab == 16)
	{ 
		if($permissions_number >= 16) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
// History Tab
	if($permissions_number - 16 >= 0) { $permissions_number -= 16; } // Subtract Active Permission
	if($standard_tab == 8)
	{ 
		if($permissions_number >= 8) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
// Send / Receive Queue Tab
	if($permissions_number - 8 >= 0) { $permissions_number -= 8; } // Subtract Active Permission
	if($standard_tab == 4)
	{ 
		if($permissions_number >= 4) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
// Transaction Queue Tab
	if($permissions_number - 4 >= 0) { $permissions_number -= 4; } // Subtract Active Permission
	if($standard_tab == 2)
	{ 
		if($permissions_number >= 2) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
// Peerlist Tab
	if($permissions_number - 2 >= 0) { $permissions_number -= 2; } // Subtract Active Permission
	if($standard_tab == 1)
	{ 
		if($permissions_number >= 1) // Show Tab
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	// Some other error
	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function file_upload($http_file_name)
{
	$user_file_upload = strtolower(basename($_FILES[$http_file_name]['name']));

	if(move_uploaded_file($_FILES[$http_file_name]['tmp_name'], "plugins/" . $user_file_upload) == TRUE)
	{
		// Upload successful
		return $user_file_upload;
	}
	else
	{
		// Error during upload
		return FALSE;
	}	
}
//***********************************************************************************
//***********************************************************************************
function read_plugin($filename)
{
	$handle = fopen($filename, "r");
	$contents = stream_get_contents($handle);
	fclose($handle);
	return $contents;
}
//***********************************************************************************
//***********************************************************************************
function ipv6_test($ip_address)
{
	if(filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == TRUE)
	{
		// IP Address is IPv6
		return TRUE;
	}

	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function ipv6_compress($ip_address)
{
	if(filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == TRUE)
	{
		// IP Address is IPv6
		return inet_ntop(inet_pton($ip_address)); // Return Compressed Shorthand
	}

	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function find_v4_gen_key($my_public_key)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT IP_Address FROM `generating_peer_list` WHERE `public_key` = '$my_public_key'";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);

		if(ipv6_test($sql_row["IP_Address"]) == FALSE)
		{
			//IPv4 Address Associated with this Generating Public Key
			return TRUE;
		}
	}

	// No Matching Key with an IPv4 Address Found
	return;
}
//***********************************************************************************
//***********************************************************************************
function find_v6_gen_key($my_public_key)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT IP_Address FROM `generating_peer_list` WHERE `public_key` = '$my_public_key'";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);

		if(ipv6_test($sql_row["IP_Address"]) == TRUE)
		{
			//IPv6 Address Associated with this Generating Public Key
			return TRUE;
		}
	}

	// No Matching Keys with an IPv6 Address Found
	return;
}
//***********************************************************************************
//***********************************************************************************
function find_v4_gen_IP($my_public_key)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT IP_Address FROM `generating_peer_list` WHERE `public_key` = '$my_public_key'";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);

		if(ipv6_test($sql_row["IP_Address"]) == FALSE)
		{
			// Return IPv4 Address Associated with this Generating Public Key
			return $sql_row["IP_Address"];
		}
	}

	// No Matching Key with an IPv4 Address Found
	return;
}
//***********************************************************************************
//***********************************************************************************
function find_v6_gen_IP($my_public_key)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT IP_Address FROM `generating_peer_list` WHERE `public_key` = '$my_public_key'";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);

		if(ipv6_test($sql_row["IP_Address"]) == TRUE)
		{
			// Return IPv6 Address Associated with this Generating Public Key
			return $sql_row["IP_Address"];
		}
	}

	// No Matching Key with an IPv6 Address Found
	return;
}
//***********************************************************************************
//***********************************************************************************
function find_v4_gen_join($my_public_key)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT join_peer_list, IP_Address FROM `generating_peer_list` WHERE `public_key` = '$my_public_key'";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);

		if(ipv6_test($sql_row["IP_Address"]) == FALSE)
		{
			// Return IPv4 Address Associated with this Generating Public Key
			return $sql_row["join_peer_list"];
		}
	}

	// No Matching Key with an IPv4 Address Found
	return;
}
//***********************************************************************************
//***********************************************************************************
function find_v6_gen_join($my_public_key)
{
	$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
	$sql = "SELECT join_peer_list, IP_Address FROM `generating_peer_list` WHERE `public_key` = '$my_public_key'";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);

		if(ipv6_test($sql_row["IP_Address"]) == TRUE)
		{
			// Return IPv6 Address Associated with this Generating Public Key
			return $sql_row["join_peer_list"];
		}
	}

	// No Matching Key with an IPv6 Address Found
	return;
}
//***********************************************************************************

?>
