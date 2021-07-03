<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(60);
//***********************************************************************************
//***********************************************************************************
if(GENERATION_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,"field_data");

// Check if loop is already running
if($loop_active == 0)
{
	// Set the working status of 1
	$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active' LIMIT 1";
	mysql_query($sql);
}
else
{
	// Loop called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Is generation turned on for our server key?
$next_generation_cycle = transaction_cycle(1);
$current_generation_cycle = transaction_cycle(0);

// Can we work on the transactions in the database?
// Not allowed 60 seconds before and 35 seconds after generation cycle.
if(($next_generation_cycle - time()) > 60 && (time() - $current_generation_cycle) > 35)
{
	// Generation Peer Check	
	$sql = "SELECT * FROM `generating_peer_list`";
	
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	if($sql_num_results > 0 )
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			$public_key = $sql_row["public_key"];
			$last_generation = $sql_row["last_generation"];

			if(time() - $last_generation > 7200) // 2 Hours without generation gets the peer removed
			{
				$sql = "DELETE FROM `generating_peer_list` WHERE `generating_peer_list`.`public_key` = '$public_key'";

				mysql_query($sql);

				$peer_purge = TRUE;				
			}
		}

		if($peer_purge == TRUE)
		{
			// Update peer list hash to avoid a race condition
			$sql = "SELECT * FROM `generating_peer_list` ORDER BY `join_peer_list`";

			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			$generating_hash = 0;

			if($sql_num_results > 0)
			{
				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$generating_hash = $generating_hash . $sql_row["public_key"] . $sql_row["join_peer_list"];
				}

				$generating_hash = hash('md5', $generating_hash);
			}

			$sql = "UPDATE `options` SET `field_data` = '$generating_hash' WHERE `options`.`field_name` = 'generating_peers_hash' LIMIT 1";
			mysql_query($sql);

		} // End peer purge check

	} // End results check
//***********************************************************************************	
	// Generation Check
	$generation_option = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'generate_currency' LIMIT 1"),0,"field_data");

	if($generation_option == "0")
	{
		// Generation is turned off
	}
	else
	{
		// Check to see if we are in the allowed generation peer list
		$my_public_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,"field_data");
		$found_public_key = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,"join_peer_list");

		if(empty($found_public_key) == TRUE)
		{
			// Is my public key alrady in the generating peer queue for election?
			$found_public_key_queue = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,"timestamp");

			if(empty($found_public_key_queue) == TRUE)
			{
				// Check to see if this request is already in my transaction queue
				$found_public_trans_queue = mysql_result(mysql_query("SELECT * FROM `my_transaction_queue` WHERE `attribute` = 'R' LIMIT 1"),0,"timestamp");				

				if(empty($found_public_trans_queue) == TRUE)
				{
					$my_private_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,"field_data");

					// Generate a network request to be added to the generation peer list
					$generation_request = ARBITRARY_KEY . rand(1, 999999);

					openssl_private_encrypt($generation_request, $encryptedData1, $my_private_key);
					$encryptedData64_1 = base64_encode($encryptedData1);				
					$duplicate_hash_check = hash('sha256', $generation_request . $generation_request . $generation_request);

					$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
					VALUES ('" . time() . "', '$my_public_key', '$encryptedData64_1', '$generation_request' , '$generation_request', '$duplicate_hash_check' , 'R')";

					mysql_query($sql);
				}
			}
		}
		else
		{
			// Look into the future with the magic of math to see if the next generation will even be allowed
			$str = strval(transaction_cycle(1));
			$last3_gen = $str[strlen($str)-3];

			TKRandom::seed(transaction_cycle(1, TRUE));
			$tk_random_number = TKRandom::num(0, 9);

			// Random generation time that can be duplicated across all servers
			if($last3_gen + $tk_random_number < 10)
			{
				$my_private_key = mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,"field_data");

				// Server public key is listed as a qualified generation server.
				// Has the server submitted it's currency generation to the transaction queue?
				$found_public_key_my_queue = mysql_result(mysql_query("SELECT * FROM `my_transaction_queue` WHERE `attribute` = 'G' LIMIT 1"),0,"timestamp");
				$found_public_key_trans_queue = mysql_result(mysql_query("SELECT * FROM `transaction_queue` WHERE `public_key` = '$my_public_key' AND `attribute` = 'G' LIMIT 1"),0,"timestamp");				
				$join_peer_list = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,"join_peer_list");

				if(empty($found_public_key_my_queue) == TRUE && empty($found_public_key_trans_queue) == TRUE && (time() - $join_peer_list) >= 3600)
				{
					// How much can be generated at one time?
					$allowed_amount = peer_gen_amount($my_public_key);

					//Not found, add it to transaction queue
					$arr1 = str_split($my_public_key, 181);

					openssl_private_encrypt($arr1[0], $encryptedData1, $my_private_key);
					$encryptedData64_1 = base64_encode($encryptedData1);
					openssl_private_encrypt($arr1[1], $encryptedData2, $my_private_key);
					$encryptedData64_2 = base64_encode($encryptedData2);
					$transaction_data = "AMOUNT=$allowed_amount---TIME=" . time() . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2);
					openssl_private_encrypt($transaction_data, $encryptedData3, $my_private_key);
					$encryptedData64_3 = base64_encode($encryptedData3);
					$duplicate_hash_check = hash('sha256', $encryptedData64_1 . $encryptedData64_2 . $encryptedData64_3);

					$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
					VALUES ('" . time() . "', '$my_public_key', '$encryptedData64_1', '$encryptedData64_2' , '$encryptedData64_3', '$duplicate_hash_check' , 'G')";
					
					mysql_query($sql);
				}

			} // Future generation allowed check

		} // Public Key Check

	} // Generation enabled check
//***********************************************************************************

} // End Time allowed check
//***********************************************************************************
//***********************************************************************************
// Script finished, set status to 0
$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active' LIMIT 1";
mysql_query($sql);

// Record when this script finished
$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'generation_last_heartbeat' LIMIT 1";
mysql_query($sql);

?>
