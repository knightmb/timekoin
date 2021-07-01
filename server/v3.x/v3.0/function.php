<?PHP
include 'status.php';

define("TRANSACTION_EPOCH","1338576300"); // Epoch timestamp: 1338576300
define("ARBITRARY_KEY","01110100011010010110110101100101"); // Space filler for non-encryption data
define("SHA256TEST","8c49a2b56ebd8fc49a17956dc529943eb0d73c00ee6eafa5d8b3ba1274eb3ea4"); // Known SHA256 Test Result
define("TIMEKOIN_VERSION","3.0"); // This Timekoin Software Version
define("NEXT_VERSION","current_version20.txt"); // What file to check for future versions

error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR); // Disable most error reporting except for fatal errors
ini_set('display_errors', FALSE);

//***********************************************************************************
//***********************************************************************************
function ip_banned($ip)
{
	// Check for banned IP address
	$ip = mysql_result(mysql_query("SELECT ip FROM `ip_banlist` WHERE `ip` = '$ip' LIMIT 1"),0,0);

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
function log_ip($attribute, $multiple = 1)
{
	if($attribute == "TC")
	{
		// Is Super Peer Enabled?
		$super_peer_mode = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'super_peer' LIMIT 1"),0,0);

		if($super_peer_mode > 0)
		{
			// Only count 1 in 3 IP for Transaction Clerk to avoid
			// accidental banning of peers accessing high volume data.
			if(rand(1,3) != 3)
			{
				return;
			}
		}
	}
	
	// Log IP Address Access
	$sql = "INSERT DELAYED INTO `ip_activity` (`timestamp` ,`ip`, `attribute`) VALUES ";
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
	
	mysql_query($sql);
	return;
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
function generation_peer_hash()
{
	$sql = "SELECT * FROM `generating_peer_list` ORDER BY `join_peer_list`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$generating_hash = 0;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$generating_hash .= $sql_row["public_key"] . $sql_row["join_peer_list"];
		}

		$generating_hash = hash('md5', $generating_hash);
	}

	return $generating_hash;
}
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
	$hash = mysql_result(mysql_query("SELECT COUNT(*) FROM `transaction_history`"),0);

	$previous_foundation_block = foundation_cycle(-1, TRUE);
	$current_foundation_cycle = foundation_cycle(0);
	$next_foundation_cycle = foundation_cycle(1);			

	$current_generation_block = transaction_cycle(0, TRUE);
	$current_foundation_block = foundation_cycle(0, TRUE);

	// Check to make sure enough lead time exist before another transaction foundation is built.
	// (50 blocks) or over 4 hours
	if($current_generation_block - ($current_foundation_block * 500) > 50)
	{
		$current_history_foundation = mysql_result(mysql_query("SELECT * FROM `transaction_foundation` WHERE `block` = $previous_foundation_block LIMIT 1"),0,"hash");
		$hash .= $current_history_foundation;
	}

	$sql = "SELECT hash FROM `transaction_history` WHERE `timestamp` >= $current_foundation_cycle AND `timestamp` < $next_foundation_cycle AND `attribute` = 'H' ORDER BY `timestamp`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$hash .= $sql_row["hash"];
	}	

	return hash('md5', $hash);
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
function perm_peer_mode()
{
	$perm_peer_priority = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'perm_peer_priority' LIMIT 1"),0,"field_data"));

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
	return mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,1);
}
//***********************************************************************************
function my_private_key()
{
	return mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,1);
}
//***********************************************************************************
function my_subfolder()
{
	return mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_subfolder' LIMIT 1"),0,1);
}
//***********************************************************************************
function my_port_number()
{
	return mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,1);
}
//***********************************************************************************
function my_domain()
{
	return mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_domain' LIMIT 1"),0,1);
}
//***********************************************************************************
function modify_peer_grade($ip_address, $domain, $subfolder, $port_number, $grade)
{
	$peer_failure = mysql_result(mysql_query("SELECT failed_sent_heartbeat FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1"),0,0);
	$peer_failure += $grade;
	if($peer_failure >= 0)
	{
		mysql_query("UPDATE `active_peer_list` SET `failed_sent_heartbeat` = $peer_failure WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
	}
	return;
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
	else if($priority == 2)
	{
		// No PHP CLI Extensions for some odd reason, call from web server instead
		poll_peer(NULL, "localhost", my_subfolder(), my_port_number(), 1, $script);
	}
	else if($plugin == TRUE)
	{
		// Normal Priority
		if(getenv("OS") == "Windows_NT")
		{
			pclose(popen("start php-win plugins/$script", "r"));// This will execute without waiting for it to finish
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

	for ($i = $block_start; $i < $block_counter; $i++)
	{
		$time1 = transaction_cycle(0 - $current_generation_block + $i);
		$time2 = transaction_cycle(0 - $current_generation_block + 1 + $i);	

		$time3 = transaction_cycle(0 - $current_generation_block + 1 + $i);
		$time4 = transaction_cycle(0 - $current_generation_block + 2 + $i);
		$next_hash = mysql_result(mysql_query("SELECT timestamp, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,"hash");

		$sql = "SELECT timestamp, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		$my_hash = 0;

		$timestamp = 0;

		for ($h = 0; $h < $sql_num_results; $h++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			
			if($sql_row["attribute"] == "H" || $sql_row["attribute"] == "B")
			{
				$timestamp = $sql_row["timestamp"];
			}

			$my_hash .= $sql_row["hash"];
		}		

		if($next_timestamp != $timestamp)
		{
			$wrong_timestamp++;

			if($first_wrong_block == 0)
			{
				$first_wrong_block = $i;
			}
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

			if($first_wrong_block == 0)
			{
				$first_wrong_block = $i;
			}			
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

	if($block_start == 0 && $block_end == 0)
	{
		// Find every Time Koin sent to this public Key
		$sql = "SELECT public_key_from, public_key_to, crypt_data3, hash, attribute FROM `transaction_history` WHERE `public_key_to` = '$public_key'";
	}
	else
	{
		// Find every TimeKoin sent to this public Key in a certain time range.
		// Covert block to time.
		$start_time_range = TRANSACTION_EPOCH + ($block_start * 300);
		$end_time_range = TRANSACTION_EPOCH + ($block_end * 300);
		$sql = "SELECT public_key_from, public_key_to, crypt_data3, hash, attribute FROM `transaction_history` WHERE `public_key_to` = '$public_key' AND `timestamp` >= '$start_time_range' AND `timestamp` < '$end_time_range'";
	}

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$crypto_balance = 0;
	$transaction_info;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_row($sql_result);

		$public_key_from = $sql_row[0];
		$public_key_to = $sql_row[1];
		$crypt3 = $sql_row[2];
		$hash = $sql_row[3];
		$attribute = $sql_row[4];

		if($attribute == "G" && $public_key_from == $public_key_to)
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

		if($attribute == "T")
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
	}
//
// Unset variable to free up RAM
	unset($sql_result);

// END - Find every TimeKoin sent to this public Key

 // Find every TimeKoin sent FROM this public Key
	if($block_start == 0 && $block_end == 0)
	{
		// Find every Time Koin sent to this public Key
		$sql = "SELECT public_key_from, public_key_to, crypt_data3, hash, attribute FROM `transaction_history` WHERE `public_key_from` = '$public_key'";
	}
	else
	{
		// Find every Time Koin sent to this public Key in a certain time range
		$sql = "SELECT public_key_from, public_key_to, crypt_data3, hash, attribute FROM `transaction_history` WHERE `public_key_from` = '$public_key' AND `timestamp` >= '$start_time_range' AND `timestamp` < '$end_time_range'";
	}

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_row($sql_result);

		$public_key_from = $sql_row[0];
		$public_key_to = $sql_row[1];		
		$crypt3 = $sql_row[2];
		$hash = $sql_row[3];
		$attribute = $sql_row[4];

		if($attribute == "T")
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
// END - Find every TimeKoin sent FROM this public Key
	
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
	$current_generation_block = transaction_cycle(0, TRUE);
	$current_foundation_block = foundation_cycle(0, TRUE);

	// Check to make sure enough lead time exist in advance to building
	// another balance index. (60 blocks) or 5 hours
	if($current_generation_block - ($current_foundation_block * 500) > 60)
	{
		// -1 Foundation Blocks (Standard)
		$previous_foundation_block = foundation_cycle(-1, TRUE);
	}
	else
	{
		// -2 Foundation Blocks - Buffers 5 hours after the newest foundation block
		$previous_foundation_block = foundation_cycle(-2, TRUE);
	}

	$sql = "SELECT * FROM `balance_index` WHERE `block` = $previous_foundation_block AND `public_key_hash` = '$public_key_hash' LIMIT 1";
	$sql_result = mysql_query($sql);
	$sql_row = mysql_fetch_array($sql_result);

	if(empty($sql_row["block"]) == TRUE)
	{
		// No index exist yet, so after the balance check is complete, record the result
		// for later use
		$crypto_balance = 0;

		// Create time range
		$end_time_range = $previous_foundation_block * 500;
		$index_balance1 = check_crypt_balance_range($public_key, 0, $end_time_range);

		// Check balance between the last block and now
		$start_time_range = $end_time_range;
		$end_time_range = transaction_cycle(0, TRUE);
		$index_balance2 = check_crypt_balance_range($public_key, $start_time_range, $end_time_range);

		// Store index in database for future access
		mysql_query("INSERT INTO `balance_index` (`block` ,`public_key_hash` ,`balance`)
		VALUES ('$previous_foundation_block', '$public_key_hash', '$index_balance1')");
		return ($index_balance1 + $index_balance2);
	}
	else
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
function peer_gen_amount($public_key)
{
	// 1 week = 604,800 seconds
	$join_peer_list = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 1"),0,"join_peer_list");

	if(empty($join_peer_list) == TRUE || $join_peer_list < TRANSACTION_EPOCH)
	{
		// Not found in the generating peer list
		return 0;
	}
	else
	{
		// How many weeks has this public key been in the peer list
		$peer_age = time() - $join_peer_list;
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

	return $amount;
}
//***********************************************************************************
//***********************************************************************************
class TKRandom
{
	// random seed
	private static $RSeed = 0;
	// set seed
	public static function seed($s = 0)
  	{
		self::$RSeed = abs(intval($s)) % 9999999 + 1;
		self::num();
	}
	// generate random number
	public static function num($min = 0, $max = 9999999)
  	{
		if (self::$RSeed == 0) self::seed(mt_rand());
		self::$RSeed = (self::$RSeed * 125) % 2796203;
		return self::$RSeed % ($max - $min + 1) + $min;
	}
}
//***********************************************************************************
//***********************************************************************************
function getCharFreq($str,$chr=false)
{
	$c = Array();
	if ($chr!==false) return substr_count($str, $chr);
	foreach(preg_split('//',$str,-1,1)as$v)($c[$v])?$c[$v]++ :$c[$v]=1;
	return $c;
}
//***********************************************************************************
//***********************************************************************************
function scorePublicKey($public_key, $score_key = FALSE)
{
	$current_generation_block = transaction_cycle(0, TRUE);	

	TKRandom::seed($current_generation_block);

	$public_key_score = 0;
	$tkrandom_num = 0;
	$character = 0;

	if($score_key == TRUE)
	{
		$output_score_key;

		// Output what is being used to score the keys
		for ($i = 0; $i < 18; $i++)
		{
			$tkrandom_num = TKRandom::num(1, 35);
			$output_score_key .= "[$tkrandom_num=" . base_convert($tkrandom_num, 10, 36) . "]";  // Base 10 to Base 36 conversion
		}
		
		return $output_score_key;
	}

	for ($i = 0; $i < 18; $i++)
	{
		$tkrandom_num = TKRandom::num(1, 35);
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
function election_cycle($when = 0)
{
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
	$last3_gen = $str[strlen($str)-3];

	TKRandom::seed($current_generation_block);
	$tk_random_number = TKRandom::num(0, 9);

	if($last3_gen + $tk_random_number > 16)
	{
		return TRUE;
	}
	else
	{
		return FALSE;
	}
}
//***********************************************************************************
//***********************************************************************************
function generation_cycle($when = 0)
{
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
	$last3_gen = $str[strlen($str)-3];

	TKRandom::seed($current_generation_block);
	$tk_random_number = TKRandom::num(0, 9);

	if($last3_gen + $tk_random_number < 6)
	{
		return TRUE;
	}
	else
	{
		return FALSE;
	}
}
//***********************************************************************************
//***********************************************************************************
function db_cache_balance($my_public_key)
{
	// Check server balance via custom memory index
	$my_server_balance = mysql_result(mysql_query("SELECT * FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,"balance");
	$my_server_balance_last = mysql_result(mysql_query("SELECT * FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,"block");

	if($my_server_balance === FALSE)
	{
		// Does not exist, needs to be created
		mysql_query("INSERT INTO `balance_index` (`block` ,`public_key_hash` ,`balance`) VALUES ('0', 'server_timekoin_balance', '0')");

		// Update record with the latest balance
		$display_balance = check_crypt_balance($my_public_key);

		mysql_query("UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1");
	}
	else
	{
		if($my_server_balance_last < transaction_cycle(0) && time() - transaction_cycle(0) > 25) // Generate 25 seconds after cycle
		{
			// Last generated balance is older than the current cycle, needs to be updated
			// Update record with the latest balance
			$display_balance = check_crypt_balance($my_public_key);

			mysql_query("UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1");
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
function send_timekoins($my_private_key, $my_public_key, $send_to_public_key, $amount, $message)
{
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

	$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES 
		('" . time() . "', '$my_public_key', '$encryptedData64_1', '$encryptedData64_2' , '$encryptedData64_3', '$triple_hash_check' , 'T')";

	if(mysql_query($sql) == TRUE)
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
function visual_walkhistory($block_start = 0, $block_end = 0)
{
	$output;

	$current_generation_block = transaction_cycle(0, TRUE);

	if($block_end <= $block_start)
	{
		$block_end = $block_start + 1;
	}

	if($block_end > $current_generation_block)
	{
		$block_end = $current_generation_block;
	}	

	$wrong_timestamp = 0;
	$wrong_block_numbers = NULL;
	$wrong_hash = 0;
	$wrong_hash_numbers = NULL;

	$next_timestamp = TRANSACTION_EPOCH + ($block_start * 300);

	for ($i = $block_start; $i < $block_end; $i++)
	{
		$output .= '<tr><td class="style2">Transaction Cycle # ' . $i;
		$time1 = transaction_cycle(0 - $current_generation_block + $i);
		$time2 = transaction_cycle(0 - $current_generation_block + 1 + $i);	

		$time3 = transaction_cycle(0 - $current_generation_block + 1 + $i);
		$time4 = transaction_cycle(0 - $current_generation_block + 2 + $i);
		
		$next_hash = mysql_result(mysql_query("SELECT timestamp, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,"hash");

		$sql = "SELECT timestamp, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		$my_hash = 0;
		$timestamp = 0;

		for ($h = 0; $h < $sql_num_results; $h++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			
			if($sql_row["attribute"] == "H" || $sql_row["attribute"] == "B")
			{
				$timestamp = $sql_row["timestamp"];
			}

			$my_hash .= $sql_row["hash"];
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
function visual_repair($block_start = 0)
{
	$current_generation_block = transaction_cycle(0, TRUE);
	$output;

	// Wipe all blocks ahead
	$time_range = transaction_cycle(0 - $current_generation_block + $block_start);

	$sql = "DELETE QUICK FROM `transaction_history` WHERE `transaction_history`.`timestamp` >= $time_range AND `attribute` = 'H'";

	if(mysql_query($sql) == TRUE)
	{
		$output .= '<tr><td class="style2">Clearing Hash Timestamps Ahead of Block #' . $block_start . '</td></tr>';
	}
	else
	{
		return '<tr><td class="style2">Database ERROR, stopping repair process...</td></tr>';
	}

	$generation_arbitrary = ARBITRARY_KEY;

	for ($t = $block_start; $t < $current_generation_block; $t++)
	{
		$output .= "<tr><td><strong>Repairing Block# $t</strong>";

		$time1 = transaction_cycle(0 - $current_generation_block - 1 + $t);
		$time2 = transaction_cycle(0 - $current_generation_block + $t);

		$sql = "SELECT timestamp, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);
		$hash = 0;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$hash .= $sql_row["hash"];
		}

		// Transaction hash
		$hash = hash('sha256', $hash);

		$sql = "INSERT INTO `transaction_history` (`timestamp` ,`public_key_from` ,`public_key_to` ,`crypt_data1` ,`crypt_data2` ,`crypt_data3` ,`hash` ,`attribute`)
		VALUES ('$time2', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$generation_arbitrary', '$hash', 'H')";

		if(mysql_query($sql) == FALSE)
		{
			// Something failed
			$output .= '<br><strong><font color="red">Repair ERROR in Database</font></strong></td></tr>';
		}
		else
		{
			$output .= '<br><strong><font color="blue">Repair Complete...</font></strong></td></tr>';
		}
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
function initialization_database()
{
	// Clear IP Activity and Banlist for next start
	mysql_query("TRUNCATE TABLE `ip_activity`");
	mysql_query("TRUNCATE TABLE `ip_banlist`");

	// Clear Active & New Peers List
	mysql_query("DELETE FROM `active_peer_list` WHERE `active_peer_list`.`join_peer_list` != 0"); // Permanent Peers Ignored
	mysql_query("TRUNCATE TABLE `new_peers_list`");

	// Record when started
	mysql_query("UPDATE `options` SET `field_data` = '" . time() . "' WHERE `options`.`field_name` = 'timekoin_start_time' LIMIT 1");
//**************************************
// Upgrade Database from v2.x

	// Standard Tabs Settings
	$new_record_check = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'standard_tabs_settings' LIMIT 1"),0,0);
	if($new_record_check === FALSE)
	{
		// Does not exist, create it
		mysql_query("INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('standard_tabs_settings', '255')");
	}

	$new_record_check = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"),0,0);
	if($new_record_check === FALSE)
	{
		// Does not exist, create it
		mysql_query("INSERT INTO `options` (`field_name` ,`field_data`) VALUES ('default_timezone', '')");
	}

	if(is_dir('plugins') == FALSE) // Create /plugins directory if it does not exist
	{
		write_log("/plugins Directory Does Not Exist", "MA");
		
		// Create plugins directory if it does not exist
		if(mkdir('plugins') == TRUE)
		{
			write_log("/plugins Directory CREATED!", "MA");
		}
	}
//**************************************
	// Check for an empty generation IP address,
	// if none exist, attempt to auto-detect one
	// and fill in the field.
	$poll_IP = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'generation_IP' LIMIT 1"),0,"field_data");
	
	if(empty($poll_IP) == TRUE)
	{
		ini_set('user_agent', 'Timekoin Server (Main) v' . TIMEKOIN_VERSION);
		ini_set('default_socket_timeout', 3); // Timeout for request in seconds
		
		$poll_IP = filter_sql(poll_peer(NULL, 'timekoin.net', NULL, 80, 46, "ipv4.php"));

		if(empty($poll_IP) == FALSE)
		{
			mysql_query("UPDATE `options` SET `field_data` = '$poll_IP' WHERE `options`.`field_name` = 'generation_IP' LIMIT 1");			
		}
	}
//**************************************
// Main Loop Status & Active Options Setup

	// Truncate to Free RAM
	mysql_query("TRUNCATE TABLE `main_loop_status`");
	$time = time();
//**************************************
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('balance_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('balance_last_heartbeat', '$time')");	
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('generation_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('generation_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('genpeer_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('genpeer_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('main_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('main_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peerlist_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peerlist_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('queueclerk_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('queueclerk_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('transclerk_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('transclerk_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('treasurer_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('treasurer_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('watchdog_heartbeat_active', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('watchdog_last_heartbeat', '$time')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peer_transaction_start_blocks', '10')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('peer_transaction_performance', '10')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('block_check_back', '1')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('block_check_start', '0')");	
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('firewall_blocked_peer', '0')");	
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_block_check', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_block_check_end', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('foundation_block_check_start', '0')");	
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('generation_peer_list_no_sync', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('no_peer_activity', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('time_sync_error', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('transaction_history_block_check', '0')");
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('update_available', '0')");
//**************************************
// Copy values from Database to RAM Database
	$db_to_RAM = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'allow_ambient_peer_restart' LIMIT 1"),0,1);
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('allow_ambient_peer_restart', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'allow_LAN_peers' LIMIT 1"),0,1);
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('allow_LAN_peers', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_request_max' LIMIT 1"),0,1);
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('server_request_max', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,1);
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('max_active_peers', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,1);
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('max_new_peers', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'trans_history_check' LIMIT 1"),0,1);
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('trans_history_check', '$db_to_RAM')");

	$db_to_RAM = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'super_peer' LIMIT 1"),0,1);
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('super_peer', '$db_to_RAM')");	
//**************************************
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
	ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 10); // Timeout for request in seconds

	$update_check1 = 'Checking for Updates....<br><br>';

	$poll_version = file_get_contents("https://timekoin.com/tkupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

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
	
	$poll_sha = file_get_contents("https://timekoin.com/tkupdates/v$poll_version/$script.sha", FALSE, $context, NULL, 64);

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
	return file_get_contents("https://timekoin.com/tkupdates/v$poll_version/$php_script.txt", FALSE, $context, NULL);
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
	ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 10); // Timeout for request in seconds

	$poll_version = file_get_contents("https://timekoin.com/tkupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

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

		$finish_message = file_get_contents("https://timekoin.com/tkupdates/v$poll_version/ZZZfinish.txt", FALSE, $context, NULL);
		$update_status .= '<br>' . $finish_message;
	}
	else
	{
		$update_status .= '<font color="red"><strong>ERROR: Could Not Contact Secure Server https://timekoin.com</strong></font>';
	}

	return $update_status;
}
//***********************************************************************************
//***********************************************************************************
function find_file($dir, $pattern)
{
	// Get a list of all matching files in the current directory
	$files = glob("$dir/$pattern");

	// Find a list of all directories in the current directory
	foreach (glob("$dir/{*}", GLOB_BRACE|GLOB_ONLYDIR) as $sub_dir)
	{
		$arr = find_file($sub_dir, $pattern);  // Resursive call
		$files = array_merge($files, $arr); // Merge array with files from subdirectory
	}

	return $files;
}
//***********************************************************************************
//***********************************************************************************
function update_windows_port($new_port)
{
	// Update the pms_config.ini file if it exist
	if(file_exists("../../pms_config.ini") == TRUE)
	{
		//Previous port number
		$old_port = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,"field_data");

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
function generate_hashcode_permissions($pk_balance, $pk_gen_amt, $pk_recv, $send_tk, $pk_history, $pk_valid, $tk_trans_total, $pk_sent, $pk_gen_total)
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

	return $permissions_number;
}
//***********************************************************************************
function check_hashcode_permissions($permissions_number, $pk_api_check, $checkbox = FALSE)
{
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
?>
