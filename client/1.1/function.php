<?PHP
define("TRANSACTION_EPOCH","1338576300"); // Epoch timestamp: 1338576300
define("TIMEKOIN_VERSION","1.1"); // This Timekoin Software Version
define("NEXT_VERSION","tk_client_current_version1.txt"); // What file to check for future versions

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
	write_log("No Peers Answered the Transaction Totals & Amounts Query", "GU");
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

	$update_check1 = 'Checking for Updates....</br></br>';

	$poll_version = file_get_contents("https://timekoin.com/tkcliupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

	if($poll_version > TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		if($code_feedback == TRUE) { return 1; } // Code feedback only that update is available
		
		$update_check1 .= '<strong>New Version Available <font color="blue">' . $poll_version . '</font></strong></br></br>
		<FORM ACTION="index.php?menu=options&upgrade=doupgrade" METHOD="post"><input type="submit" name="Submit3" value="Perform Software Update" /></FORM>';
	}
	else if($poll_version <= TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		// No update available
		$update_check1 .= 'Current Version: <strong>' . TIMEKOIN_VERSION . '</strong></br></br><font color="blue">No Update Necessary.</font>';	
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
				return '<strong><font color="green">Update Complete...</strong></font></br></br>';
			}
			else
			{
				return '<strong><font color="red">ERROR: Update FAILED with a file Close Error.</strong></font></br></br>';
			}
		}
	}
	else
	{
		return '<strong><font color="red">ERROR: Update FAILED with unable to Open File Error.</strong></font></br></br>';
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
			$update_status_return .= 'Server SHA: <strong>' . $poll_sha . '</strong></br>Download SHA: <strong>' . $download_sha . '</strong></br>';
			$update_status_return .= '<strong>' . $script_name . '</strong> SHA Match...</br>';
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
		return ' - <strong>No Update Available</strong>...</br></br>';
	}
	else
	{
		// File exist, is the download valid?
		$sha_check = check_update_script($script_name, $script_php, $php_file, $poll_version, $context);

		if($sha_check == FALSE)
		{
			return ' - <strong>ERROR: Unable to Download File Properly</strong>...</br></br>';
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

	$update_status = 'Starting Update Process...</br></br>';

	if(empty($poll_version) == FALSE)
	{
		//****************************************************
		//Check for CSS updates
		$update_status .= 'Checking for <strong>CSS Template</strong> Update...</br>';
		$update_status .= run_script_update("CSS Template (admin.css)", "admin.css", $poll_version, $context, 0, "css");
		//****************************************************
		//****************************************************
		//Check for javascript updates
		$update_status .= 'Checking for <strong>Javascript Template</strong> Update...</br>';
		$update_status .= run_script_update("Javascript Template (tkgraph.js)", "tkgraph.js", $poll_version, $context, 0, "js");
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>RSA Code</strong> Update...</br>';
		$update_status .= run_script_update("RSA Code (RSA.php)", "RSA", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Openssl Template</strong> Update...</br>';
		$update_status .= run_script_update("Openssl Template (openssl.cnf)", "openssl.cnf", $poll_version, $context, 0);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Web Interface</strong> Update...</br>';
		$update_status .= run_script_update("Timekoin Web Interface (index.php)", "index", $poll_version, $context);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Background Task</strong> Update...</br>';
		$update_status .= run_script_update("Timekoin Background Task (task.php)", "task", $poll_version, $context);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Web Interface Template</strong> Update...</br>';
		$update_status .= run_script_update("Web Interface Template (templates.php)", "templates", $poll_version, $context);
		//****************************************************
		//****************************************************
		// We do the function storage last because it contains the version info.
		// That way if some unknown error prevents updating the files above, this
		// will allow the user to try again for an update without being stuck in
		// a new version that is half-updated.
		$update_status .= 'Checking for <strong>Function Storage</strong> Update...</br>';
		$update_status .= run_script_update("Function Storage (function.php)", "function", $poll_version, $context);
		//****************************************************
		$finish_message = file_get_contents("https://timekoin.com/tkcliupdates/v$poll_version/ZZZfinish.txt", FALSE, $context, NULL);
		$update_status .= '</br>' . $finish_message;

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
	// Automatic Update Check Record
	$new_record_check = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'update_available' LIMIT 1"),0,0);
	if($new_record_check === FALSE)
	{
		// Does not exist, create it
		mysql_query("INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('update_available', '0')");
	}
}
//***********************************************************************************
?>
