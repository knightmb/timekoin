<?PHP
include 'status.php';

define("TRANSACTION_EPOCH","1338576300"); // Epoch timestamp: 1338576300
define("TIMEKOIN_VERSION","1.6"); // Software Version
define("ARBITRARY_KEY","01110100011010010110110101100101"); // Space filler for non-encryption data
define("SHA256TEST","8c49a2b56ebd8fc49a17956dc529943eb0d73c00ee6eafa5d8b3ba1274eb3ea4"); // Known SHA256 Test Result

error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR); // Disable most error reporting except for fatal errors
ini_set('display_errors', FALSE);

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
	$sql_log = "INSERT INTO `activity_logs` (`timestamp` ,`log` ,`attribute`)
		VALUES ('" . time() . "', '" . substr($message, 0, 256) . "', '$type')";

	mysql_query($sql_log);
	return;
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
		$next_hash = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,"hash");

		$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

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
function check_crypt_balance_range($public_key, $block_start = 0, $block_end = 0)
{
	if($block_start == 0 && $block_end == 0)
	{
		// Find every Time Koin sent to this public Key
		$sql = "SELECT * FROM `transaction_history` WHERE `public_key_to` = '$public_key'";
	}
	else
	{
		// Find every Time Koin sent to this public Key in a certain time range.
		// Covert block to time.
		$start_time_range = TRANSACTION_EPOCH + ($block_start * 300);
		$end_time_range = TRANSACTION_EPOCH + ($block_end * 300);
		$sql = "SELECT * FROM `transaction_history` WHERE `public_key_to` = '$public_key' AND `timestamp` >= '$start_time_range' AND `timestamp` < '$end_time_range'";
	}

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$crypto_balance = 0;	

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		
		$public_key_from = $sql_row["public_key_from"];
		$public_key_to = $sql_row["public_key_to"];		
		$crypt1 = $sql_row["crypt_data1"];
		$crypt2 = $sql_row["crypt_data2"];
		$crypt3 = $sql_row["crypt_data3"];
		$hash = $sql_row["hash"];
		$attribute = $sql_row["attribute"];

		if($attribute == "G" && $public_key_from == $public_key_to && $hash == hash('sha256', $crypt1 . $crypt2 . $crypt3))
		{
			// Currency Generation
			// Find destination public key
			openssl_public_decrypt(base64_decode($crypt1), $public_key_to_1, $public_key_from);
			openssl_public_decrypt(base64_decode($crypt2), $public_key_to_2, $public_key_from);				
			$public_key_to = $public_key_to_1 . $public_key_to_2;

			// Decrypt transaction information
			openssl_public_decrypt(base64_decode($crypt3), $transaction_info, $public_key_from);

			$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
			$transaction_hash = find_string("HASH=", "", $transaction_info, TRUE);

			// Check if a message is encoded in this data as well
			if(strlen($transaction_hash) != 64)
			{
				// A message is also encoded
				$transaction_hash = find_string("HASH=", "---MSG", $transaction_info);
			}

			if($transaction_hash == hash('sha256', $crypt1 . $crypt2))
			{
				// Transaction hash and real hash match
				$crypto_balance += $transaction_amount_sent;
			}
		}

		if($attribute == "T" && $hash == hash('sha256', $crypt1 . $crypt2 . $crypt3))
		{
			// Decrypt transaction --
			// Find destination public key
			openssl_public_decrypt(base64_decode($crypt1), $public_key_to_1, $public_key_from);
			openssl_public_decrypt(base64_decode($crypt2), $public_key_to_2, $public_key_from);				
			$public_key_to = $public_key_to_1 . $public_key_to_2;

			// Decrypt transaction information
			openssl_public_decrypt(base64_decode($crypt3), $transaction_info, $public_key_from);

			$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
			$transaction_hash = find_string("HASH=", "", $transaction_info, TRUE);

			// Check if a message is encoded in this data as well
			if(strlen($transaction_hash) != 64)
			{
				// A message is also encoded
				$transaction_hash = find_string("HASH=", "---MSG", $transaction_info);
			}

			if($transaction_hash == hash('sha256', $crypt1 . $crypt2))
			{
				// Transaction hash and real hash match
				$crypto_balance += $transaction_amount_sent;
			}
		}
	}
// END - Find every Time Koin sent to this public Key

 // Find every Time Koin sent FROM this public Key
	if($block_start == 0 && $block_end == 0)
	{
		// Find every Time Koin sent to this public Key
		$sql = "SELECT * FROM `transaction_history` WHERE `public_key_from` = '$public_key'";
	}
	else
	{
		// Find every Time Koin sent to this public Key in a certain time range
		$sql = "SELECT * FROM `transaction_history` WHERE `public_key_from` = '$public_key' AND `timestamp` >= '$start_time_range' AND `timestamp` < '$end_time_range'";
	}

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		
		$public_key_from = $sql_row["public_key_from"];
		$public_key_to = $sql_row["public_key_to"];		
		$crypt1 = $sql_row["crypt_data1"];
		$crypt2 = $sql_row["crypt_data2"];
		$crypt3 = $sql_row["crypt_data3"];
		$hash = $sql_row["hash"];
		$attribute = $sql_row["attribute"];

		if($attribute == "T" && $hash == hash('sha256', $crypt1 . $crypt2 . $crypt3))
		{
			// Decrypt transaction --
			// Find destination public key
			openssl_public_decrypt(base64_decode($crypt1), $public_key_to_1, $public_key_from);
			openssl_public_decrypt(base64_decode($crypt2), $public_key_to_2, $public_key_from);				
			$public_key_to = $public_key_to_1 . $public_key_to_2;

			// Decrypt transaction information
			openssl_public_decrypt(base64_decode($crypt3), $transaction_info, $public_key_from);

			$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
			$transaction_hash = find_string("HASH=", "", $transaction_info, TRUE);

			// Check if a message is encoded in this data as well
			if(strlen($transaction_hash) != 64)
			{
				// A message is also encoded
				$transaction_hash = find_string("HASH=", "---MSG", $transaction_info);
			}

			if($transaction_hash == hash('sha256', $crypt1 . $crypt2))
			{
				// Transaction hash and real hash match
				$crypto_balance -= $transaction_amount_sent;
			}
		}
	}
// END - Find every Time Koin sent FROM this public Key

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
		$sql = "INSERT INTO `balance_index` (`block` ,`public_key_hash` ,`balance`)
		VALUES ('$previous_foundation_block', '$public_key_hash', '$index_balance1')";
		
		mysql_query($sql);

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
function scorePublicKey($public_key)
{
	$current_generation_block = transaction_cycle(0, TRUE);	

	TKRandom::seed($current_generation_block);

	$public_key_score = 0;
	$tkrandom_num = 0;
	$character = 0;

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
function db_cache_balance($my_public_key)
{
	// Check server balance via custom memory index
	$my_server_balance = mysql_result(mysql_query("SELECT * FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,"balance");
	$my_server_balance_last = mysql_result(mysql_query("SELECT * FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,"block");

	if($my_server_balance === FALSE)
	{
		// Does not exist, needs to be created
		$sql = "INSERT INTO `timekoin`.`balance_index` (`block` ,`public_key_hash` ,`balance`)VALUES ('0', 'server_timekoin_balance', '0')";
		mysql_query($sql);

		// Update record with the latest balance
		$display_balance = check_crypt_balance($my_public_key);

		$sql = "UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1";
		mysql_query($sql);
	}
	else
	{
		if($my_server_balance_last < transaction_cycle(0) && time() - transaction_cycle(0) > 25) // Generate 25 seconds after cycle
		{
			// Last generated balance is older than the current cycle, needs to be updated
			// Update record with the latest balance
			$display_balance = check_crypt_balance($my_public_key);

			$sql = "UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1";
			mysql_query($sql);
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
	openssl_private_encrypt($arr1[0], $encryptedData1, $my_private_key);
	$encryptedData64_1 = base64_encode($encryptedData1);
	openssl_private_encrypt($arr1[1], $encryptedData2, $my_private_key);
	$encryptedData64_2 = base64_encode($encryptedData2);

	if(empty($message) == TRUE)
	{
		$transaction_data = "AMOUNT=$amount---TIME=" . time() . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2);
	}
	else
	{
		// Sanitization of message
		// Filter symbols that might lead to a transaction hack attack
		$symbols = array("|", "?", "="); // SQL + URL
		$message = str_replace($symbols, "", $message);

		// Trim any message to 64 characters max and filter any sql
		$message = filter_sql(substr($message, 0, 64));
		
		$transaction_data = "AMOUNT=$amount---TIME=" . time() . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2) . "---MSG=$message";
	}

	openssl_private_encrypt($transaction_data, $encryptedData3, $my_private_key);
	$encryptedData64_3 = base64_encode($encryptedData3);
	$triple_hash_check = hash('sha256', $encryptedData64_1 . $encryptedData64_2 . $encryptedData64_3);

	$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
VALUES ('" . time() . "', '$my_public_key', '$encryptedData64_1', '$encryptedData64_2' , '$encryptedData64_3', '$triple_hash_check' , 'T')";

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
		$output = $output . '<tr><td class="style2">Block # ' . $i;
		$time1 = transaction_cycle(0 - $current_generation_block + $i);
		$time2 = transaction_cycle(0 - $current_generation_block + 1 + $i);	

		$time3 = transaction_cycle(0 - $current_generation_block + 1 + $i);
		$time4 = transaction_cycle(0 - $current_generation_block + 2 + $i);
		
		$next_hash = mysql_result(mysql_query("SELECT * FROM `transaction_history` WHERE `timestamp` >= $time3 AND `timestamp` < $time4 AND `attribute` = 'H' LIMIT 1"),0,"hash");

		$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

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
			$output = $output . '</br><strong>Hash Timestamp Sequence Wrong... Should Be: ' . $next_timestamp . '</strong>';
			$wrong_timestamp++;
			$wrong_block_numbers .= " " . $i;
		}
		
		$next_timestamp = $next_timestamp + 300;

		$my_hash = hash('sha256', $my_hash);

		$output .= '</br>Timestamp in Database: ' . $timestamp;
		$output .= '</br>Calculated Hash: ' . $my_hash;
		$output .= '</br>&nbsp;Database Hash : ' . $next_hash;

		if($my_hash == $next_hash)
		{
			$output .= '</br><font color=green>Hash Match...</font>';
		}
		else
		{
			$output .= '</br><strong><font color=red>Hash MISMATCH</font></strong></td></tr>';
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

	$output .= '<tr><td class="style2"><strong><font color="blue">Total Wrong Sequence: ' . $wrong_timestamp . '</strong></font>';
	$output .= '</br><strong><font color="red">Blocks Wrong:</font> ' . $wrong_block_numbers . '</strong></td></tr>';
	$output .= '<tr><td class="style2"><strong><font color="blue">Total Wrong Hash: ' . $wrong_hash . '</strong></font>';
	$output .= '</br><strong><font color="red">Blocks Wrong:</font> ' . $wrong_hash_numbers . '</strong></td></tr>';	

	return $output;

}
//***********************************************************************************
//***********************************************************************************
function visual_repair($block_start = 0)
{
	$current_generation_block = transaction_cycle(0, TRUE);
	$output;

	// Wipe all blocks ahead
	$time_range = transaction_cycle(0 - $current_generation_block + $block_start);

	$sql = "DELETE FROM `transaction_history` WHERE `transaction_history`.`timestamp` >= $time_range AND `attribute` = 'H'";

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

		$sql = "SELECT * FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` < $time2 ORDER BY `timestamp`, `hash`";

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
			$output .= '</br><strong><font color="red">Repair ERROR in Database</font></strong></td></tr>';
		}
		else
		{
			$output .= '</br><strong><font color="blue">Repair Complete...</font></strong></td></tr>';
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
	}
	
	return $result;
}
//***********************************************************************************
//***********************************************************************************
?>
