<?PHP
define("TRANSACTION_EPOCH","1338576300"); // Epoch timestamp: 1338576300
define("TIMEKOIN_VERSION","1.0"); // This Timekoin Software Version
define("NEXT_VERSION","tk_android_current_version0.txt"); // What file to check for future versions

error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR); // Disable most error reporting except for fatal errors
ini_set('display_errors', FALSE);
//***********************************************************************************
//***********************************************************************************
function transaction_cycle($past_or_future = 0, $transacton_cycles_only = 0)
{
	$transacton_cycles = (time() - TRANSACTION_EPOCH) / 300;

	// Return the last transaction cycle
	if($transacton_cycles_only == TRUE)
	{
		return intval($transacton_cycles + $past_or_future);
	}
	else
	{
		return TRANSACTION_EPOCH + (intval($transacton_cycles + $past_or_future) * 300);
	}
}
//***********************************************************************************
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

	if(strtolower($domain) == "localhost")
	{
		$result = FALSE;
	}

	return $result;
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
function find_string($start_tag, $end_tag, $full_string, $end_match = FALSE)
{
	$delimiter = '|';
	
	if($end_match == FALSE)
	{
		$regex = $delimiter . preg_quote($start_tag, $delimiter) . '(.*?)'  . preg_quote($end_tag, $delimiter)  . $delimiter  . 's';
	}
	else
	{
		$regex = $delimiter . preg_quote($start_tag, $delimiter) . '(.*)'  . preg_quote($end_tag, $delimiter)  . $delimiter  . 's';
	}

	preg_match_all($regex,$full_string,$matches);

	foreach($matches[1] as $found_string)
	{
	}
	
	return $found_string;
}
//***********************************************************************************
//***********************************************************************************
function write_log($message, $type)
{
	// Write Log Entry
	mysql_query("INSERT DELAYED INTO `activity_logs` (`timestamp` ,`log` ,`attribute`)	
		VALUES ('" . time() . "', '" . substr($message, 0, 256) . "', '$type')");
	return;
}
//***********************************************************************************
//***********************************************************************************
function queue_hash()
{
	$sql = "SELECT public_key, crypt_data1, crypt_data2, crypt_data3, hash, attribute FROM `transaction_queue` ORDER BY `hash`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$transaction_queue_hash = 0;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$transaction_queue_hash .= $sql_row["public_key"] . $sql_row["crypt_data1"] . 
				$sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];
		}
		
		$transaction_queue_hash = hash('md5', $transaction_queue_hash);
	}

	return $transaction_queue_hash;
}
//***********************************************************************************
//***********************************************************************************
function my_public_key()
{
	return mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,1);
}
//***********************************************************************************
function my_private_key()
{
	return mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,1);
}
//***********************************************************************************
function poll_peer($ip_address, $domain, $subfolder, $port_number, $max_length, $poll_string, $custom_context)
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
function tk_encrypt($key, $crypt_data)
{
	if(function_exists('openssl_private_encrypt') == TRUE)
	{
		openssl_private_encrypt($crypt_data, $encrypted_data, $key, OPENSSL_PKCS1_PADDING);
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
function transaction_history_query($to_from, $last = 1)
{
	// Ask one of my active peers
	ini_set('user_agent', 'Timekoin Client v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 5); // Timeout for request in seconds
	$cache_refresh_time = 60; // Default cache time in seconds

	if($to_from == 1)
	{	
		$trans_history_sent_to = mysql_result(mysql_query("SELECT * FROM `data_cache` WHERE `field_name` = 'trans_history_sent_to' LIMIT 1"),0,"field_data");
		$timestamp_cache = intval(find_string("---time=", "---last", $trans_history_sent_to));
		$last_cache = intval(find_string("---last=", "---hdata", $trans_history_sent_to));

		if(time() - $cache_refresh_time < $timestamp_cache && $last == $last_cache) // Cache TTL
		{
			// Return Cache Data
			return find_string("---hdata=", "---hend", $trans_history_sent_to);
		}
	}

	if($to_from == 2)
	{
		$trans_history_sent_from = mysql_result(mysql_query("SELECT * FROM `data_cache` WHERE `field_name` = 'trans_history_sent_from' LIMIT 1"),0,"field_data");
		$timestamp_cache = intval(find_string("---time=", "---last", $trans_history_sent_from));
		$last_cache = intval(find_string("---last=", "---hdata", $trans_history_sent_from));

		if(time() - $cache_refresh_time < $timestamp_cache && $last == $last_cache) // Cache TTL
		{
			// Return Cache Data
			return find_string("---hdata=", "---hend", $trans_history_sent_from);
		}
	}

	$my_public_key = base64_encode(my_public_key());

	if($to_from == 1)
	{
		$params = array ('public_key' => $my_public_key, 
		'last' => $last, 
		'sent_to' => '1');
	}
	
	if($to_from == 2)
	{
		$params = array ('public_key' => $my_public_key, 
		'last' => $last, 
		'sent_from' => '1');
	}

	// Build Http query using params
	$query = http_build_query($params);
	 
	// Create Http context details
	$contextData = array (
						 'method' => 'POST',
						 'header' => "Connection: close\r\n".
										 "Content-Length: ".strlen($query)."\r\n",
						 'content'=> $query );
	 
	// Create context resource for our request
	$context = stream_context_create (array ( 'http' => $contextData ));

	$sql_result = mysql_query("SELECT * FROM `active_peer_list` ORDER BY RAND()");
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];
		$code = $sql_row["code"];
		$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 200000, "api.php?action=pk_history&hash=$code", $context));

		if(strlen($poll_peer) > 60)
		{
			// Update data cache
			if($to_from == 1)
			{
				mysql_query("UPDATE `data_cache` SET `field_data` = '---time=" . time() . "---last=$last---hdata=$poll_peer---hend' WHERE `data_cache`.`field_name` = 'trans_history_sent_to' LIMIT 1");
			}

			if($to_from == 2)
			{
				mysql_query("UPDATE `data_cache` SET `field_data` = '---time=" . time() . "---last=$last---hdata=$poll_peer---hend' WHERE `data_cache`.`field_name` = 'trans_history_sent_from' LIMIT 1");
			}			
			
			return $poll_peer;
		}
	}

	// No peers would respond
	write_log("No Peers Answered the Transaction History Poll", "GU");
	return;
}
//***********************************************************************************
//***********************************************************************************
function tk_trans_total($last = 1)
{
	// Ask one of my active peers
	ini_set('user_agent', 'Timekoin Client v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 5); // Timeout for request in seconds
	$sql_result = mysql_query("SELECT * FROM `active_peer_list` ORDER BY RAND()");
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];
		$code = $sql_row["code"];
		$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 7000, "api.php?action=tk_trans_total&last=$last&hash=$code"));

		if(strlen($poll_peer) > 40)
		{
			return $poll_peer;
		}
	}

	// No peers would respond
	write_log("No Peers Answered the Transaction Totals &amp; Amounts Query", "GU");
	return;
}
//***********************************************************************************
//***********************************************************************************
function verify_public_key($public_key)
{
	if(empty($public_key) == TRUE)
	{
		return 0;
	}

	// Ask one of my active peers
	ini_set('user_agent', 'Timekoin Client v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 3); // Timeout for request in seconds

	// Create map with request parameters
	$params = array ('public_key' => base64_encode($public_key));
	 
	// Build Http query using params
	$query = http_build_query($params);
	 
	// Create Http context details
	$contextData = array (
						 'method' => 'POST',
						 'header' => "Connection: close\r\n".
										 "Content-Length: ".strlen($query)."\r\n",
						 'content'=> $query );
	 
	// Create context resource for our request
	$context = stream_context_create (array ( 'http' => $contextData ));

	$sql_result = mysql_query("SELECT * FROM `active_peer_list` ORDER BY RAND()");
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];
		$code = $sql_row["code"];
		$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 2, "api.php?action=pk_valid&hash=$code", $context));

		if($poll_peer == 1)
		{
			return TRUE;
		}
	}

	// No peers would respond
	write_log("No Peers Answered the Public Key Verification Poll", "GU");
	return;
}
//***********************************************************************************
//***********************************************************************************
function check_crypt_balance($public_key)
{
	if(empty($public_key) == TRUE)
	{
		return 0;
	}

	// Ask one of my active peers
	ini_set('user_agent', 'Timekoin Client v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 4); // Timeout for request in seconds

	// Create map with request parameters
	$params = array ('public_key' => base64_encode($public_key));
	 
	// Build Http query using params
	$query = http_build_query($params);
	 
	// Create Http context details
	$contextData = array (
						 'method' => 'POST',
						 'header' => "Connection: close\r\n".
										 "Content-Length: ".strlen($query)."\r\n",
						 'content'=> $query );
	 
	// Create context resource for our request
	$context = stream_context_create (array ( 'http' => $contextData ));

	$sql_result = mysql_query("SELECT * FROM `active_peer_list` ORDER BY RAND()");
	$sql_num_results = mysql_num_rows($sql_result);
	$zero_balance; // Flag for true zero balance
	$zero_balance_counter; // Count true rezo balance responses

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];
		$code = $sql_row["code"];
		$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 20, "api.php?action=pk_balance&hash=$code", $context));

		if(strlen($poll_peer) >= 1)
		{
			if($poll_peer !== "0" || $zero_balance_counter > 1) // If the peer returns 0, try another peer just to make sure
			{
				// If enough peers are reporting a true zero balance, return it to speed up response
				return $poll_peer;
			}
			else
			{
				$zero_balance = TRUE;
				$zero_balance_counter++; // Count how many peers report a true zero balance
			}
		}
	}

	if($zero_balance == TRUE)
	{
		// Peers must have reported a true 0 balance
		return 0;
	}

	// No peers would respond
	write_log("No Peers Answered the Public Key Balance Poll", "GU");
	return "NA";
}
//***********************************************************************************
//***********************************************************************************
function tk_time_convert($time)
{
	if($time < 0)
	{
		return "0 sec";
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
function db_cache_balance($my_public_key)
{
	$cache_refresh_time = 30; // Refresh TTL in seconds
	
	// Check server balance via cache
	$billfold_balance = mysql_result(mysql_query("SELECT * FROM `data_cache` WHERE `field_name` = 'billfold_balance' LIMIT 1"),0,"field_data");
	$timestamp_cache = intval(find_string("---time=", "---data", $billfold_balance));

	if(time() - $cache_refresh_time <= $timestamp_cache) // Cache TTL
	{
		// Return Cache Data
		return intval(find_string("---data=", "---end", $billfold_balance));
	}

	$balance = check_crypt_balance($my_public_key); // Cache stale, refresh and update cache
	mysql_query("UPDATE `data_cache` SET `field_data` = '---time=" . time() . "---data=$balance---end' WHERE `data_cache`.`field_name` = 'billfold_balance' LIMIT 1");
	return $balance;
}
//***********************************************************************************
//***********************************************************************************
function send_timekoins($my_private_key, $my_public_key, $send_to_public_key, $amount, $message)
{
	if(empty($my_private_key) == TRUE || empty($my_public_key) == TRUE || empty($send_to_public_key) == TRUE)
	{
		return FALSE;
	}

	ini_set('user_agent', 'Timekoin Client v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 3); // Timeout for request in seconds

	$arr1 = str_split($send_to_public_key, 181);
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

	$timestamp = transaction_cycle(0) + 1;	
	$attribute = "T";

	$qhash = $timestamp . base64_encode($my_public_key) . $encryptedData64_1 . $encryptedData64_2 . $encryptedData64_3 . $triple_hash_check . $attribute;
	$qhash = hash('md5', $qhash);

	// Create map with request parameters
	$params = array ('timestamp' => $timestamp, 
		'public_key' => base64_encode($my_public_key), 
		'crypt_data1' => $encryptedData64_1, 
		'crypt_data2' => $encryptedData64_2, 
		'crypt_data3' => $encryptedData64_3, 
		'hash' => $triple_hash_check, 
		'attribute' => $attribute,
		'qhash' => $qhash);
	 
	// Build Http query using params
	$query = http_build_query($params);
	 
	// Create Http context details
	$contextData = array (
						 'method' => 'POST',
						 'header' => "Connection: close\r\n".
										 "Content-Length: ".strlen($query)."\r\n",
						 'content'=> $query );
	 
	// Create context resource for our request
	$context = stream_context_create (array ( 'http' => $contextData ));

	// Try all Active Peer Servers
	$sql_result = mysql_query("SELECT * FROM `active_peer_list` ORDER BY RAND()");
	$sql_num_results = mysql_num_rows($sql_result);
	$return_results;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];
		$code = $sql_row["code"];

		$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 5, "api.php?action=send_tk&hash=$code", $context));

		if($poll_peer == "OK")
		{
			write_log("Peer: [$ip_address$domain:$port_number/$subfolder] Accepted the Transaction for Processing", "T");
			$return_results = TRUE;
		}
	}

	if($return_results == TRUE)
	{
		// Success in sending transaction
		return TRUE;
	}
	else
	{
		// No peer servers accepted the transaction data :(
		write_log("No Peers Accepted the Transaction", "T");
		return FALSE;
	}
}
//***********************************************************************************
//***********************************************************************************
function unix_timestamp_to_human($timestamp = "", $format = 'D d M Y - H:i:s')
{
	$default_timezone = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"),0,"field_data");

	if(empty($default_timezone) == FALSE)
	{	
		date_default_timezone_set($default_timezone);
	}
	
	if (empty($timestamp) || ! is_numeric($timestamp)) $timestamp = time();
	return ($timestamp) ? date($format, $timestamp) : date($format, $timestamp);
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
	}
	
	return $result;
}
//***********************************************************************************
//***********************************************************************************
function call_script($script, $priority = 1, $plugin = FALSE)
{
	if($priority == 1)
	{
		// Normal Priority
		if(getenv("OS") == "Windows_NT")
		{
			pclose(popen("start php-win $script", "r"));// This will execute without waiting for it to finish
		}
		else
		{
			exec("php $script &> /dev/null &"); // This will execute without waiting for it to finish
		}
	}
	else if($plugin == TRUE)
	{
		// All Plugins Below Normal Priority
		if(getenv("OS") == "Windows_NT")
		{
			pclose(popen("start /BELOWNORMAL php-win plugins/$script", "r"));// This will execute without waiting for it to finish
		}
		else
		{
			exec("nice php plugins/$script &> /dev/null &"); // This will execute without waiting for it to finish
		}
	}
	else
	{
		// Below Normal Priority
		if(getenv("OS") == "Windows_NT")
		{
			pclose(popen("start /BELOWNORMAL php-win $script", "r"));// This will execute without waiting for it to finish
		}
		else
		{
			exec("nice php $script &> /dev/null &"); // This will execute without waiting for it to finish
		}
	}

	return;
}
//***********************************************************************************
//***********************************************************************************	
function generate_new_keys()
{
	require_once('RSA.php');

	$rsa = new Crypt_RSA();
	extract($rsa->createKey(1536));

	if(empty($privatekey) == FALSE && empty($publickey) == FALSE)
	{
		$symbols = array("\r");
		$new_publickey = str_replace($symbols, "", $publickey);
		$new_privatekey = str_replace($symbols, "", $privatekey);

		$sql = "UPDATE `my_keys` SET `field_data` = '$new_privatekey' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";

		if(mysql_query($sql) == TRUE)
		{
			// Private Key Update Success
			$sql = "UPDATE `my_keys` SET `field_data` = '$new_publickey' WHERE `my_keys`.`field_name` = 'server_public_key' LIMIT 1";
			
			if(mysql_query($sql) == TRUE)
			{
				// Blank reverse crypto data field
				mysql_query("UPDATE `options` SET `field_data` = '' WHERE `options`.`field_name` = 'generation_key_crypt' LIMIT 1");

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
	// Poll timekoin.com for any program updates
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Client (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 15); // Timeout for request in seconds

	$update_check1 = 'Checking for Updates....<br><br>';

	$poll_version = file_get_contents("https://timekoin.com/tkcliupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

	if($poll_version > TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		if($code_feedback == TRUE) { return 1; } // Code feedback only that update is available
		
		$update_check1 .= '<strong>New Version Available <font color="blue">' . $poll_version . '</font></strong><br><br>
		<FORM ACTION="index.php?menu=options&amp;upgrade=doupgrade" METHOD="post"><input type="submit" name="Submit3" value="Perform Software Update" /></FORM>';
	}
	else if($poll_version <= TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		// No update available
		$update_check1 .= 'Current Version: <strong>' . TIMEKOIN_VERSION . '</strong><br><br><font color="blue">No Update Necessary.</font>';	
		// Reset available update alert
		mysql_query("UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'update_available' LIMIT 1");
	}
	else
	{
		$update_check1 .= '<strong>ERROR: Could Not Contact Secure Server https://timekoin.com</strong>';
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
				return '<strong><font color="green">Update Complete...</strong></font><br><br>';
			}
			else
			{
				return '<strong><font color="red">ERROR: Update FAILED with a file Close Error.</strong></font><br><br>';
			}
		}
	}
	else
	{
		return '<strong><font color="red">ERROR: Update FAILED with unable to Open File Error.</strong></font><br><br>';
	}
}
//***********************************************************************************
//***********************************************************************************
function check_update_script($script_name, $script, $php_script_file, $poll_version, $context)
{
	$update_status_return = NULL;
	
	$poll_sha = file_get_contents("https://timekoin.com/tkcliupdates/v$poll_version/$script.sha", FALSE, $context, NULL, 64);

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
	return file_get_contents("https://timekoin.com/tkcliupdates/v$poll_version/$php_script.txt", FALSE, $context, NULL);
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
			return ' - <strong>ERROR: Unable to Download File Properly</strong>...<br><br>';
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
	// Poll timekoin.com for any program updates
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Client (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 10); // Timeout for request in seconds

	$poll_version = file_get_contents("https://timekoin.com/tkcliupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

	$update_status = 'Starting Update Process...<br><br>';

	if(empty($poll_version) == FALSE)
	{
		//****************************************************
		//Check for CSS updates
		$update_status .= 'Checking for <strong>CSS Template</strong> Update...<br>';
		$update_status .= run_script_update("CSS Template (admin.css)", "admin.css", $poll_version, $context, 0, "css");
		//****************************************************
		//****************************************************
		//Check for javascript updates
		$update_status .= 'Checking for <strong>Javascript Template</strong> Update...<br>';
		$update_status .= run_script_update("Javascript Template (tkgraph.js)", "tkgraph.js", $poll_version, $context, 0, "js");
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>RSA Code</strong> Update...<br>';
		$update_status .= run_script_update("RSA Code (RSA.php)", "RSA", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Openssl Template</strong> Update...<br>';
		$update_status .= run_script_update("Openssl Template (openssl.cnf)", "openssl.cnf", $poll_version, $context, 0);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Web Interface</strong> Update...<br>';
		$update_status .= run_script_update("Timekoin Web Interface (index.php)", "index", $poll_version, $context);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Background Task</strong> Update...<br>';
		$update_status .= run_script_update("Timekoin Background Task (task.php)", "task", $poll_version, $context);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Web Interface Template</strong> Update...<br>';
		$update_status .= run_script_update("Web Interface Template (templates.php)", "templates", $poll_version, $context);
		//****************************************************
		//****************************************************
		// We do the function storage last because it contains the version info.
		// That way if some unknown error prevents updating the files above, this
		// will allow the user to try again for an update without being stuck in
		// a new version that is half-updated.
		$update_status .= 'Checking for <strong>Function Storage</strong> Update...<br>';
		$update_status .= run_script_update("Function Storage (function.php)", "function", $poll_version, $context);
		//****************************************************
		$finish_message = file_get_contents("https://timekoin.com/tkcliupdates/v$poll_version/ZZZfinish.txt", FALSE, $context, NULL);
		$update_status .= '<br>' . $finish_message;

		// Reset available update alert
		mysql_query("UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'update_available' LIMIT 1");
	}
	else
	{
		$update_status .= '<strong>ERROR: Could Not Contact Secure Server https://timekoin.com</strong>';
	}

	return $update_status;
}
//***********************************************************************************
function initialization_database()
{
	if(is_dir('plugins') == FALSE) // Create /plugins directory if it does not exist
	{
		write_log("/plugins Directory Does Not Exist", "GU");
		
		// Create plugins directory if it does not exist
		if(mkdir('plugins') == TRUE)
		{
			write_log("/plugins Directory CREATED!", "GU");
		}
	}

	// Automatic Update Check Record
	$new_record_check = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'update_available' LIMIT 1"),0,0);
	if($new_record_check === FALSE)
	{
		// Does not exist, create it
		mysql_query("INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('update_available', '0')");
	}

	// Timezone Settings
	$new_record_check = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"),0,0);
	if($new_record_check === FALSE)
	{
		// Does not exist, create it
		mysql_query("INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('default_timezone', '')");
	}

	// Tab Management Settings
	$new_record_check = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'standard_tabs_settings' LIMIT 1"),0,0);
	if($new_record_check === FALSE)
	{
		// Does not exist, create it
		mysql_query("INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('standard_tabs_settings', '223')");
	}	
}
//***********************************************************************************
function standard_tab_settings($peerlist, $trans_queue, $send_receive, $history, $address, $system, $backup, $tools)
{
	$permissions_number = 0;

	if($peerlist == 1) { $permissions_number += 1; }
	if($trans_queue == 1) { $permissions_number += 2; }
	if($send_receive == 1) { $permissions_number += 4; }
	if($history == 1) { $permissions_number += 8; }
	if($address == 1) { $permissions_number += 16; }
	if($system == 1) { $permissions_number += 32; }
	if($backup == 1) { $permissions_number += 64; }
	if($tools == 1) { $permissions_number += 128; }

	return $permissions_number;
}
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
	
// Address Tab
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
function read_plugin($filename)
{
	$handle = fopen($filename, "r");
	$contents = stream_get_contents($handle);
	fclose($handle);
	return $contents;
}
//***********************************************************************************
//***********************************************************************************
class Aes {
	/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
	/*  AES implementation in PHP                                                                     */
	/*    (c) Chris Veness 2005-2011 www.movable-type.co.uk/scripts                                   */
	/*    Right of free use is granted for all commercial or non-commercial use providing this        */
	/*    copyright notice is retainded. No warranty of any form is offered.                          */
	/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */  
  /**
   * AES Cipher function: encrypt 'input' with Rijndael algorithm
   *
   * @param input message as byte-array (16 bytes)
   * @param w     key schedule as 2D byte-array (Nr+1 x Nb bytes) - 
   *              generated from the cipher key by keyExpansion()
   * @return      ciphertext as byte-array (16 bytes)
   */
  public static function cipher($input, $w) {    // main cipher function [§5.1]
    $Nb = 4;                 // block size (in words): no of columns in state (fixed at 4 for AES)
    $Nr = count($w)/$Nb - 1; // no of rounds: 10/12/14 for 128/192/256-bit keys
  
    $state = array();  // initialise 4xNb byte-array 'state' with input [§3.4]
    for ($i=0; $i<4*$Nb; $i++) $state[$i%4][floor($i/4)] = $input[$i];
  
    $state = self::addRoundKey($state, $w, 0, $Nb);
  
    for ($round=1; $round<$Nr; $round++) {  // apply Nr rounds
      $state = self::subBytes($state, $Nb);
      $state = self::shiftRows($state, $Nb);
      $state = self::mixColumns($state, $Nb);
      $state = self::addRoundKey($state, $w, $round, $Nb);
    }
  
    $state = self::subBytes($state, $Nb);
    $state = self::shiftRows($state, $Nb);
    $state = self::addRoundKey($state, $w, $Nr, $Nb);
  
    $output = array(4*$Nb);  // convert state to 1-d array before returning [§3.4]
    for ($i=0; $i<4*$Nb; $i++) $output[$i] = $state[$i%4][floor($i/4)];
    return $output;
  }
  
  private static function addRoundKey($state, $w, $rnd, $Nb) {  // xor Round Key into state S [§5.1.4]
    for ($r=0; $r<4; $r++) {
      for ($c=0; $c<$Nb; $c++) $state[$r][$c] ^= $w[$rnd*4+$c][$r];
    }
    return $state;
  }
  
  private static function subBytes($s, $Nb) {    // apply SBox to state S [§5.1.1]
    for ($r=0; $r<4; $r++) {
      for ($c=0; $c<$Nb; $c++) $s[$r][$c] = self::$sBox[$s[$r][$c]];
    }
    return $s;
  }
  
  private static function shiftRows($s, $Nb) {    // shift row r of state S left by r bytes [§5.1.2]
    $t = array(4);
    for ($r=1; $r<4; $r++) {
      for ($c=0; $c<4; $c++) $t[$c] = $s[$r][($c+$r)%$Nb];  // shift into temp copy
      for ($c=0; $c<4; $c++) $s[$r][$c] = $t[$c];           // and copy back
    }          // note that this will work for Nb=4,5,6, but not 7,8 (always 4 for AES):
    return $s;  // see fp.gladman.plus.com/cryptography_technology/rijndael/aes.spec.311.pdf 
  }
  
  private static function mixColumns($s, $Nb) {   // combine bytes of each col of state S [§5.1.3]
    for ($c=0; $c<4; $c++) {
      $a = array(4);  // 'a' is a copy of the current column from 's'
      $b = array(4);  // 'b' is a•{02} in GF(2^8)
      for ($i=0; $i<4; $i++) {
        $a[$i] = $s[$i][$c];
        $b[$i] = $s[$i][$c]&0x80 ? $s[$i][$c]<<1 ^ 0x011b : $s[$i][$c]<<1;
      }
      // a[n] ^ b[n] is a•{03} in GF(2^8)
      $s[0][$c] = $b[0] ^ $a[1] ^ $b[1] ^ $a[2] ^ $a[3]; // 2*a0 + 3*a1 + a2 + a3
      $s[1][$c] = $a[0] ^ $b[1] ^ $a[2] ^ $b[2] ^ $a[3]; // a0 * 2*a1 + 3*a2 + a3
      $s[2][$c] = $a[0] ^ $a[1] ^ $b[2] ^ $a[3] ^ $b[3]; // a0 + a1 + 2*a2 + 3*a3
      $s[3][$c] = $a[0] ^ $b[0] ^ $a[1] ^ $a[2] ^ $b[3]; // 3*a0 + a1 + a2 + 2*a3
    }
    return $s;
  }
  
  /**
   * Key expansion for Rijndael cipher(): performs key expansion on cipher key
   * to generate a key schedule
   *
   * @param key cipher key byte-array (16 bytes)
   * @return    key schedule as 2D byte-array (Nr+1 x Nb bytes)
   */
  public static function keyExpansion($key) {  // generate Key Schedule from Cipher Key [§5.2]
    $Nb = 4;              // block size (in words): no of columns in state (fixed at 4 for AES)
    $Nk = count($key)/4;  // key length (in words): 4/6/8 for 128/192/256-bit keys
    $Nr = $Nk + 6;        // no of rounds: 10/12/14 for 128/192/256-bit keys
  
    $w = array();
    $temp = array();
  
    for ($i=0; $i<$Nk; $i++) {
      $r = array($key[4*$i], $key[4*$i+1], $key[4*$i+2], $key[4*$i+3]);
      $w[$i] = $r;
    }
  
    for ($i=$Nk; $i<($Nb*($Nr+1)); $i++) {
      $w[$i] = array();
      for ($t=0; $t<4; $t++) $temp[$t] = $w[$i-1][$t];
      if ($i % $Nk == 0) {
        $temp = self::subWord(self::rotWord($temp));
        for ($t=0; $t<4; $t++) $temp[$t] ^= self::$rCon[$i/$Nk][$t];
      } else if ($Nk > 6 && $i%$Nk == 4) {
        $temp = self::subWord($temp);
      }
      for ($t=0; $t<4; $t++) $w[$i][$t] = $w[$i-$Nk][$t] ^ $temp[$t];
    }
    return $w;
  }
  
  private static function subWord($w) {    // apply SBox to 4-byte word w
    for ($i=0; $i<4; $i++) $w[$i] = self::$sBox[$w[$i]];
    return $w;
  }
  
  private static function rotWord($w) {    // rotate 4-byte word w left by one byte
    $tmp = $w[0];
    for ($i=0; $i<3; $i++) $w[$i] = $w[$i+1];
    $w[3] = $tmp;
    return $w;
  }
  
  // sBox is pre-computed multiplicative inverse in GF(2^8) used in subBytes and keyExpansion [§5.1.1]
  private static $sBox = array(
    0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,
    0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,
    0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,
    0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,
    0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,
    0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,
    0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,
    0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,
    0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,
    0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,
    0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,
    0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,
    0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,
    0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,
    0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,
    0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16);
  
  // rCon is Round Constant used for the Key Expansion [1st col is 2^(r-1) in GF(2^8)] [§5.2]
  private static $rCon = array( 
    array(0x00, 0x00, 0x00, 0x00),
    array(0x01, 0x00, 0x00, 0x00),
    array(0x02, 0x00, 0x00, 0x00),
    array(0x04, 0x00, 0x00, 0x00),
    array(0x08, 0x00, 0x00, 0x00),
    array(0x10, 0x00, 0x00, 0x00),
    array(0x20, 0x00, 0x00, 0x00),
    array(0x40, 0x00, 0x00, 0x00),
    array(0x80, 0x00, 0x00, 0x00),
    array(0x1b, 0x00, 0x00, 0x00),
    array(0x36, 0x00, 0x00, 0x00) ); 

} 

class AesCtr extends Aes {
	/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
	/*  AES counter (CTR) mode implementation in PHP                                                  */
	/*    (c) Chris Veness 2005-2011 www.movable-type.co.uk/scripts                                   */
	/*    Right of free use is granted for all commercial or non-commercial use providing this        */
	/*    copyright notice is retainded. No warranty of any form is offered.                          */
	/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */

	/** 
   * Encrypt a text using AES encryption in Counter mode of operation
   *  - see http://csrc.nist.gov/publications/nistpubs/800-38a/sp800-38a.pdf
   *
   * Unicode multi-byte character safe
   *
   * @param plaintext source text to be encrypted
   * @param password  the password to use to generate a key
   * @param nBits     number of bits to be used in the key (128, 192, or 256)
   * @return          encrypted text
   */
  public static function encrypt($plaintext, $password, $nBits) {
    $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
    if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
    // note PHP (5) gives us plaintext and password in UTF8 encoding!
    
    // use AES itself to encrypt password to get cipher key (using plain password as source for  
    // key expansion) - gives us well encrypted key
    $nBytes = $nBits/8;  // no bytes in key
    $pwBytes = array();
    for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
    $key = Aes::cipher($pwBytes, Aes::keyExpansion($pwBytes));
    $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long 
  
    // initialise 1st 8 bytes of counter block with nonce (NIST SP800-38A §B.2): [0-1] = millisec, 
    // [2-3] = random, [4-7] = seconds, giving guaranteed sub-ms uniqueness up to Feb 2106
    $counterBlock = array();
    $nonce = floor(microtime(true)*1000);   // timestamp: milliseconds since 1-Jan-1970
    $nonceMs = $nonce%1000;
    $nonceSec = floor($nonce/1000);
    $nonceRnd = floor(rand(0, 0xffff));
    
    for ($i=0; $i<2; $i++) $counterBlock[$i]   = self::urs($nonceMs,  $i*8) & 0xff;
    for ($i=0; $i<2; $i++) $counterBlock[$i+2] = self::urs($nonceRnd, $i*8) & 0xff;
    for ($i=0; $i<4; $i++) $counterBlock[$i+4] = self::urs($nonceSec, $i*8) & 0xff;
    
    // and convert it to a string to go on the front of the ciphertext
    $ctrTxt = '';
    for ($i=0; $i<8; $i++) $ctrTxt .= chr($counterBlock[$i]);
  
    // generate key schedule - an expansion of the key into distinct Key Rounds for each round
    $keySchedule = Aes::keyExpansion($key);
    //print_r($keySchedule);
    
    $blockCount = ceil(strlen($plaintext)/$blockSize);
    $ciphertxt = array();  // ciphertext as array of strings
    
    for ($b=0; $b<$blockCount; $b++) {
      // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
      // done in two stages for 32-bit ops: using two words allows us to go past 2^32 blocks (68GB)
      for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
      for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs($b/0x100000000, $c*8);
  
      $cipherCntr = Aes::cipher($counterBlock, $keySchedule);  // -- encrypt counter block --
  
      // block size is reduced on final block
      $blockLength = $b<$blockCount-1 ? $blockSize : (strlen($plaintext)-1)%$blockSize+1;
      $cipherByte = array();
      
      for ($i=0; $i<$blockLength; $i++) {  // -- xor plaintext with ciphered counter byte-by-byte --
        $cipherByte[$i] = $cipherCntr[$i] ^ ord(substr($plaintext, $b*$blockSize+$i, 1));
        $cipherByte[$i] = chr($cipherByte[$i]);
      }
      $ciphertxt[$b] = implode('', $cipherByte);  // escape troublesome characters in ciphertext
    }
  
    // implode is more efficient than repeated string concatenation
    $ciphertext = $ctrTxt . implode('', $ciphertxt);
    $ciphertext = base64_encode($ciphertext);
    return $ciphertext;
  }
  
  /** 
   * Decrypt a text encrypted by AES in counter mode of operation
   *
   * @param ciphertext source text to be decrypted
   * @param password   the password to use to generate a key
   * @param nBits      number of bits to be used in the key (128, 192, or 256)
   * @return           decrypted text
   */
  public static function decrypt($ciphertext, $password, $nBits) {
    $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
    if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
    $ciphertext = base64_decode($ciphertext);
  
    // use AES to encrypt password (mirroring encrypt routine)
    $nBytes = $nBits/8;  // no bytes in key
    $pwBytes = array();
    for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
    $key = Aes::cipher($pwBytes, Aes::keyExpansion($pwBytes));
    $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long
    
    // recover nonce from 1st element of ciphertext
    $counterBlock = array();
    $ctrTxt = substr($ciphertext, 0, 8);
    for ($i=0; $i<8; $i++) $counterBlock[$i] = ord(substr($ctrTxt,$i,1));
    
    // generate key schedule
    $keySchedule = Aes::keyExpansion($key);
  
    // separate ciphertext into blocks (skipping past initial 8 bytes)
    $nBlocks = ceil((strlen($ciphertext)-8) / $blockSize);
    $ct = array();
    for ($b=0; $b<$nBlocks; $b++) $ct[$b] = substr($ciphertext, 8+$b*$blockSize, 16);
    $ciphertext = $ct;  // ciphertext is now array of block-length strings
  
    // plaintext will get generated block-by-block into array of block-length strings
    $plaintxt = array();
    
    for ($b=0; $b<$nBlocks; $b++) {
      // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
      for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
      for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs(($b+1)/0x100000000-1, $c*8) & 0xff;
  
      $cipherCntr = Aes::cipher($counterBlock, $keySchedule);  // encrypt counter block
  
      $plaintxtByte = array();
      for ($i=0; $i<strlen($ciphertext[$b]); $i++) {
        // -- xor plaintext with ciphered counter byte-by-byte --
        $plaintxtByte[$i] = $cipherCntr[$i] ^ ord(substr($ciphertext[$b],$i,1));
        $plaintxtByte[$i] = chr($plaintxtByte[$i]);
      
      }
      $plaintxt[$b] = implode('', $plaintxtByte); 
    }
  
    // join array of blocks into single plaintext string
    $plaintext = implode('',$plaintxt);
    
    return $plaintext;
  }
  
  /*
   * Unsigned right shift function, since PHP has neither >>> operator nor unsigned ints
   *
   * @param a  number to be shifted (32-bit integer)
   * @param b  number of bits to shift a to the right (0..31)
   * @return   a right-shifted and zero-filled by b bits
   */
  private static function urs($a, $b) {
    $a &= 0xffffffff; $b &= 0x1f;  // (bounds check)
    if ($a&0x80000000 && $b>0) {   // if left-most bit set
      $a = ($a>>1) & 0x7fffffff;   //   right-shift one bit & clear left-most bit
      $a = $a >> ($b-1);           //   remaining right-shifts
    } else {                       // otherwise
      $a = ($a>>$b);               //   use normal right-shift
    } 
    return $a; 
  }
}  
//***********************************************************************************
//***********************************************************************************

?>
