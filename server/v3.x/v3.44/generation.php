<?PHP
include 'configuration.php';
include 'function.php';
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

// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit ("Your IP Has Been Banned");
}
//***********************************************************************************
// External Flood Protection
log_ip("GE", scale_trigger(4));
//***********************************************************************************

// First time run check
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,0);
$last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_last_heartbeat' LIMIT 1"),0,0);

if($loop_active === FALSE && $last_heartbeat == 1)
{
	// Create record to begin loop
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('generation_heartbeat_active', '0')");
	// Update timestamp for starting
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'generation_last_heartbeat' LIMIT 1");
}
else
{
	// Record already exist, called while another process of this script
	// was already running.
	exit;
}

while(1) // Begin Infinite Loop
{
set_time_limit(300);	
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,0);

// Check script status
if($loop_active === FALSE)
{
	// Time to exit
	exit;
}
else if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active' LIMIT 1");
}
else if($loop_active == 2) // Wake from sleep
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active' LIMIT 1");
}
else if($loop_active == 3) // Shutdown
{
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active'");
	exit;
}
else
{
	// Script called while still working
	exit;
}
//***********************************************************************************
// Is generation turned on for our server key?
$next_generation_cycle = transaction_cycle(1);
$current_generation_cycle = transaction_cycle(0);

// Can we work on the transactions in the database?
// Not allowed 120 seconds before and 35 seconds after generation cycle.
if(($next_generation_cycle - time()) > 120 && (time() - $current_generation_cycle) > 35)
{
	// Generation Peer Check	
	$peer_purge = FALSE;
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
				$sql = "DELETE QUICK FROM `generating_peer_list` WHERE `generating_peer_list`.`public_key` = '$public_key'";
				if(mysql_query($sql) == TRUE)
				{
					// Delete successful, flag to update hash
					$peer_purge = TRUE;
					write_log("Public Key Removed Due to 2 Hour Idle Limit:<br>" . base64_encode($public_key),"G");
				}
			}
		}

		if($peer_purge == TRUE)
		{
			// Update peer list hash to avoid a race condition
			$generating_hash = generation_peer_hash();
			mysql_query("UPDATE `options` SET `field_data` = '$generating_hash' WHERE `options`.`field_name` = 'generating_peers_hash' LIMIT 1");
		} // End peer purge check

	} // End results check
	//***********************************************************************************	
	// Generation Check
	$generation_option = intval(mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generate_currency' LIMIT 1"),0,0));

	if($generation_option == TRUE) // Generation Enabled
	{
		// Check to see if we are in the allowed generation peer list
		$my_public_key = my_public_key();
		$network_mode = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'network_mode' LIMIT 1"),0,0));
		
		if($network_mode == 1 || $network_mode == 2)
		{
			// IPv4 Generation
			//$found_public_key = mysql_result(mysql_query("SELECT join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,"join_peer_list");
			$found_public_key = find_v4_gen_key($my_public_key);

			// Check that the IP address for generation is up to date
			//$my_peer_generation_IP = mysql_result(mysql_query("SELECT IP_Address FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,0);
			$my_peer_generation_IP = find_v4_gen_IP($my_public_key);

			// My Own IPv4 Address
			$my_generation_IP = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generation_IP' LIMIT 1"),0,0);

			// What Public Key is using my IPv4 Address?			
			$key_generation_IP = mysql_result(mysql_query("SELECT public_key FROM `generating_peer_list` WHERE `IP_Address` = '$my_generation_IP' LIMIT 1"),0,0);

			if($my_generation_IP != $my_peer_generation_IP && empty($found_public_key) == FALSE)
			{
				// My IP is not the same as the one recorded in the generation peer list.
				// Use an election request to update all the peers to the new address.
				$update_generation_IP = TRUE;
			}
			else if($key_generation_IP != $my_public_key)
			{
				// Someone else is using my IP address to generate currency.
				// Submit a delete request to have this key removed from the list.
				$create_delete_request = TRUE;
			}
			else
			{
				// Reset these when no issues exist
				$update_generation_IP = FALSE;
				$create_delete_request = FALSE;
			}

			if(empty($found_public_key) == TRUE && $create_delete_request == TRUE)
			{
				// Create request to delete my IP from the generating list.
				if(election_cycle(1) == TRUE ||
					election_cycle(2) == TRUE ||
					election_cycle(3) == TRUE ||
					election_cycle(4) == TRUE ||
					election_cycle(5) == TRUE ||
					election_cycle(6) == TRUE) // Check 1-6 cycles ahead (30 minutes)
				{			
					// Check to see if this request is already in my transaction queue.
					$found_public_trans_queue = mysql_result(mysql_query("SELECT timestamp FROM `my_transaction_queue` WHERE `attribute` = 'R' LIMIT 1"),0,0);				

					if(empty($found_public_trans_queue) == TRUE)
					{
						$my_private_key = my_private_key();

						// Generate a network request to be added to the generation peer list
						$generation_request = ARBITRARY_KEY . rand(1, 999999);

						// Update Reverse Crypto Testing Data
						$generation_key_crypt = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generation_key_crypt' LIMIT 1"),0,0);

						if(empty($generation_key_crypt) == TRUE)
						{
							// Reverse Crypto Test is empty, create a new one.
							// This is just the first 181 characters of the public key encrypted via the private key.
							// This is then stored as a data field that is easy to access and quickly output to any
							// peer that is going to query this one as a potential generating peer.
							$arr1 = str_split($my_public_key, 181);
							$encryptedPublicKey = tk_encrypt($my_private_key, $arr1[0]);
							$encryptedPublicKey = base64_encode($encryptedPublicKey);
							
							// Update in the database.
							mysql_query("UPDATE `options` SET `field_data` = '$encryptedPublicKey' WHERE `options`.`field_name` = 'generation_key_crypt' LIMIT 1");
						}

						// Crypt3 field will contain the IP address/Domain/etc of where the election request originates from.
						// This will allow a reverse check for a valid Timekoin server.
						$my_domain = my_domain();
						$my_subfolder = my_subfolder();
						$my_port = my_port_number();

						// All request have the DELETE_IP attached to the end to clear out someone using a previous IP to generate currency from
						$crypt3_data = "---ip=$my_generation_IP---domain=$my_domain---subfolder=$my_subfolder---port=$my_port---end=DELETE_IP---end2";
						$encryptedData3 = tk_encrypt($my_private_key, $crypt3_data);
						$encryptedData64_3 = base64_encode($encryptedData3);

						// Encrypt Generation Request into Crypt1 field
						$encryptedData1 = tk_encrypt($my_private_key, $generation_request);
						$encryptedData64_1 = base64_encode($encryptedData1);
						$duplicate_hash_check = hash('sha256', $encryptedData64_1 . $generation_request . $encryptedData64_3);

						mysql_query("INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
						VALUES ('" . time() . "', '$my_public_key', '$encryptedData64_1', '$generation_request' , '$encryptedData64_3', '$duplicate_hash_check' , 'R')");

					} // End duplicate request check
				} // End Election cycle available check
			}

			if(empty($found_public_key) == TRUE || $update_generation_IP == TRUE)
			{
				// Not in the allowed generation list, send a request to be elected
				// when the next transaction cycle does an election.
				if(election_cycle(1) == TRUE ||
					election_cycle(2) == TRUE ||
					election_cycle(3) == TRUE ||
					election_cycle(4) == TRUE ||
					election_cycle(5) == TRUE ||
					election_cycle(6) == TRUE) // Check 1-6 cycles ahead (30 minutes)
				{
					// Check to see if this request is already in my transaction queue
					$found_public_trans_queue = mysql_result(mysql_query("SELECT timestamp FROM `my_transaction_queue` WHERE `attribute` = 'R' LIMIT 1"),0,0);				

					if(empty($found_public_trans_queue) == TRUE)
					{
						$my_private_key = my_private_key();

						// Generate a network request to be added to the generation peer list
						$generation_request = ARBITRARY_KEY . rand(1, 999999);

						// Update Reverse Crypto Testing Data
						$generation_key_crypt = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generation_key_crypt' LIMIT 1"),0,0);

						if(empty($generation_key_crypt) == TRUE)
						{
							// Reverse Crypto Test is empty, create a new one.
							// This is just the first 181 characters of the public key encrypted via the private key.
							// This is then stored as a data field that is easy to access and quickly output to any
							// peer that is going to query this one as a potential generating peer.
							$arr1 = str_split($my_public_key, 181);
							$encryptedPublicKey = tk_encrypt($my_private_key, $arr1[0]);
							$encryptedPublicKey = base64_encode($encryptedPublicKey);
							
							// Update in the database.
							mysql_query("UPDATE `options` SET `field_data` = '$encryptedPublicKey' WHERE `options`.`field_name` = 'generation_key_crypt' LIMIT 1");
						}

						// Crypt3 field will contain the IP address/Domain/etc of where the election request originates from.
						// This will allow a reverse check for a valid Timekoin server.
						$my_domain = my_domain();
						$my_subfolder = my_subfolder();
						$my_port = my_port_number();

						$crypt3_data = "---ip=$my_generation_IP---domain=$my_domain---subfolder=$my_subfolder---port=$my_port---end";
						$encryptedData3 = tk_encrypt($my_private_key, $crypt3_data);
						$encryptedData64_3 = base64_encode($encryptedData3);

						// Encrypt Generation Request into Crypt1 field
						$encryptedData1 = tk_encrypt($my_private_key, $generation_request);
						$encryptedData64_1 = base64_encode($encryptedData1);
						$duplicate_hash_check = hash('sha256', $encryptedData64_1 . $generation_request . $encryptedData64_3);

						mysql_query("INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
						VALUES ('" . time() . "', '$my_public_key', '$encryptedData64_1', '$generation_request' , '$encryptedData64_3', '$duplicate_hash_check' , 'R')");
					} // End duplicate request check
				} // End Election cycle available check
			}
			
			if(empty($found_public_key) == FALSE) // Already elected to generate currency
			{
				// Look into the future with the magic of math to see if the next generation will even be allowed.
				if(generation_cycle(1) == TRUE)// Check 1 cycle ahead
				{
					$my_private_key = my_private_key();

					// Server public key is listed as a qualified generation server.
					// Has the server submitted it's currency generation to the transaction queue?
					$found_public_key_my_queue = mysql_result(mysql_query("SELECT timestamp FROM `my_transaction_queue` WHERE `attribute` = 'G' LIMIT 1"),0,0);
					$found_public_key_trans_queue = mysql_result(mysql_query("SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$my_public_key' AND `attribute` = 'G' LIMIT 1"),0,0);
					//$join_peer_list = mysql_result(mysql_query("SELECT join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$my_public_key' LIMIT 1"),0,0);
					$join_peer_list = find_v4_gen_join($my_public_key);

					if(empty($found_public_key_my_queue) == TRUE && empty($found_public_key_trans_queue) == TRUE && (time() - $join_peer_list) >= 3600)
					{
						// How much can be generated at one time?
						$allowed_amount = peer_gen_amount($my_public_key);

						$creation_time = $current_generation_cycle + 1;

						//Not found, add it to transaction queue
						$arr1 = str_split($my_public_key, 181);

						$encryptedData1 = tk_encrypt($my_private_key, $arr1[0]);

						$encryptedData64_1 = base64_encode($encryptedData1);
						$encryptedData2 = tk_encrypt($my_private_key, $arr1[1]);					
						
						$encryptedData64_2 = base64_encode($encryptedData2);
						$transaction_data = "AMOUNT=$allowed_amount---TIME=" . $creation_time . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2);
						$encryptedData3 = tk_encrypt($my_private_key, $transaction_data);
						
						$encryptedData64_3 = base64_encode($encryptedData3);
						$duplicate_hash_check = hash('sha256', $encryptedData64_1 . $encryptedData64_2 . $encryptedData64_3);

						$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
						VALUES ('" . $creation_time . "', '$my_public_key', '$encryptedData64_1', '$encryptedData64_2' , '$encryptedData64_3', '$duplicate_hash_check' , 'G')";
						
						mysql_query($sql);
					}

				} // Future generation allowed check

			} // Public Key Check

		}// Generation IPv4 Enabled Check

		if($network_mode == 1 || $network_mode == 3)
		{
			// IPv6 Generation

		}

	} // Generation enabled check

} // End Time allowed check
//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'generation_heartbeat_active' LIMIT 1"),0,0);

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active'");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'generation_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'generation_last_heartbeat' LIMIT 1");

//***********************************************************************************
sleep(10);
} // End Infinite Loop
?>
