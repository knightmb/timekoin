<?PHP
include 'configuration.php';
include 'function.php';
//***********************************************************************************
//***********************************************************************************
if(GENPEER_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Open persistent connection to database
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer generation hash poll
if($_GET["action"] == "gen_hash")
{
	echo mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generating_peers_hash' LIMIT 1"),0,0);

	// Log inbound IP activity
	log_ip("GP", 1);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer generation hash poll
if($_GET["action"] == "gen_key_crypt")
{
	echo mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generation_key_crypt' LIMIT 1"),0,0);

	// Log inbound IP activity
	log_ip("GP", 1);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer generation peer list poll
if($_GET["action"] == "gen_peer_list")
{
	$sql = "SELECT * FROM `generating_peer_list` ORDER BY RAND() LIMIT 150";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$queue_number = 1;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			echo "-----public_key$queue_number=" , base64_encode($sql_row["public_key"]) , "-----join$queue_number=" , $sql_row["join_peer_list"] , "-----last$queue_number=" , $sql_row["last_generation"] , "-----ip$queue_number=" , $sql_row["IP_Address"] , "-----END$queue_number";

			$queue_number++;
		}
	}

	// Log inbound IP activity
	log_ip("GP", 1);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer if a public key for the generation peer queue is valid
if($_GET["action"] == "elect_gen_peer_valid")
{
	$gen_IP = filter_sql($_POST["gen_IP"]);
	$gen_public_key = base64_decode(filter_sql($_POST["public_key"]));

	$peer_valid = mysql_result(mysql_query("SELECT timestamp FROM `generating_peer_queue` WHERE `public_key` = '$gen_public_key' AND `IP_Address` = '$gen_IP' LIMIT 1"),0,0);

	if($peer_valid > 1)
	{
		echo 1;
	}

	// Log inbound IP activity
	log_ip("GP", 1);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// External Flood Protection
log_ip("GP", scale_trigger(4));
//***********************************************************************************
// First time run check
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,0);
$last_heartbeat = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'genpeer_last_heartbeat' LIMIT 1"),0,0);

if($loop_active === FALSE && $last_heartbeat == 1)
{
	// Create record to begin loop
	mysql_query("INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('genpeer_heartbeat_active', '0')");
	// Update timestamp for starting
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'genpeer_last_heartbeat' LIMIT 1");
}
else
{
	// Record already exist, called while another process of this script
	// was already running.
	exit;
}

ini_set('user_agent', 'Timekoin Server (Genpeer) v' . TIMEKOIN_VERSION);	
ini_set('default_socket_timeout', 3); // Timeout for request in seconds

while(1) // Begin Infinite Loop
{
set_time_limit(300);	
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active === FALSE)
{
	// Time to exit
	exit;
}
else if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active' LIMIT 1");
}
else if($loop_active == 2) // Wake from sleep
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active' LIMIT 1");
}
else if($loop_active == 3) // Shutdown
{
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active'");
	exit;
}
else
{
	// Script called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$next_generation_cycle = transaction_cycle(1);
$current_generation_cycle = transaction_cycle(0);

// Can we work on the transactions in the database?
// Not allowed 35 seconds before and 35 seconds after generation cycle.
if(($next_generation_cycle - time()) > 35 && (time() - $current_generation_cycle) > 35)
{
//***********************************************************************************
	// IPv4 Election
	if(election_cycle() == TRUE)// IPv4 Peers
	{
		// Find all election request between the Previous Transaction Cycle and the Current		
		$sql = "SELECT * FROM `generating_peer_queue` WHERE `timestamp` < $current_generation_cycle ORDER BY `IP_Address` ASC";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		if($sql_num_results > 0)
		{
			if($sql_num_results == 1)// Lone IPv4 Address Public Key Won
			{
				$sql_row = mysql_fetch_array($sql_result);

				// Winner by default
				if(ipv6_test($sql_row["IP_Address"]) == FALSE)
				{
					$public_key = $sql_row["public_key"];
					$IP_Address = $sql_row["IP_Address"];
					mysql_query("INSERT INTO `generating_peer_list` (`public_key` ,`join_peer_list` ,`last_generation`, `IP_Address`) VALUES ('$public_key', '$current_generation_cycle', '$current_generation_cycle', '$IP_Address')");
					write_log("IPv4 Generation Peer Elected for Public Key: " . base64_encode($public_key), "GP");
				}
			}
			else
			{
				// More than 1 peer request, start a scoring of all public keys,
				// the public key with the most points win
				$highest_score = 0;
				$public_key_winner = NULL;
				write_log("IPv4 Peer Election Score Key: " . scorePublicKey(NULL, TRUE), "GP");

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					
					if(ipv6_test($sql_row["IP_Address"]) == FALSE)// IPv4 Only
					{	
						$public_key = $sql_row["public_key"];
				
						$public_key_score = scorePublicKey($public_key);
						write_log("IPv4 Key Score: [$public_key_score] for Public Key: " . base64_encode($public_key), "GP");

						if($public_key_score > $highest_score)
						{
							$public_key_winner = $public_key;
							$highest_score = $public_key_score;
							$IP_Address = $sql_row["IP_Address"];
						}
					}
				}

				if($public_key_score > 0)// Someone won
				{
					mysql_query("INSERT INTO `generating_peer_list` (`public_key` ,`join_peer_list` ,`last_generation`, `IP_Address`) VALUES ('$public_key_winner', '$current_generation_cycle', '$current_generation_cycle', '$IP_Address')");
					write_log("IPv4 Generation Peer Elected for Public Key: " . base64_encode($public_key_winner), "GP");
				}

			} // End if/then winner check

			// Clear out queue for next round of IPv4 Address only
			$sql = "SELECT * FROM `generating_peer_queue`";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				if(ipv6_test($sql_row["IP_Address"]) == FALSE)// IPv4 Only
				{
					// Remove IPv4 Key From Queue
					mysql_query("DELETE FROM `generating_peer_queue` WHERE `timestamp` = " . $sql_row["timestamp"] . " AND `public_key` = '" . $sql_row["public_key"] . "' AND `IP_Address` = '" . $sql_row["IP_Address"] . "' LIMIT 1");
				}
			}

			// Optimize Table when Finished
			mysql_query("OPTIMIZE TABLE `generating_peer_queue`");

			// Wait after generation election for DB sanity reasons
			sleep(1);

		}	//End if/then results check

	} // End if/then timing comparison check
//***********************************************************************************
//***********************************************************************************
	// IPv6 Election
	// Total Servers that have been Generating for at least 24 hours previous, excluding those that have just joined recently
	$gen_peers_total = mysql_result(mysql_query("SELECT COUNT(*) FROM `generating_peer_list` WHERE `join_peer_list` < " . (time() - 86400) . ""),0);

	if(election_cycle(0, 2, $gen_peers_total) == TRUE)// IPv6 Peers
	{
		// Find all election request between the Previous Transaction Cycle and the Current		
		$sql = "SELECT * FROM `generating_peer_queue` WHERE `timestamp` < $current_generation_cycle ORDER BY `IP_Address` ASC";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		if($sql_num_results > 0)
		{
			if($sql_num_results == 1)
			{
				$sql_row = mysql_fetch_array($sql_result);

				// Winner by default
				if(ipv6_test($sql_row["IP_Address"]) == TRUE)//Lone IPv6 Address Public Key Won
				{
					$public_key = $sql_row["public_key"];
					$IP_Address = ipv6_compress($sql_row["IP_Address"]);
					mysql_query("INSERT INTO `generating_peer_list` (`public_key` ,`join_peer_list` ,`last_generation`, `IP_Address`) VALUES ('$public_key', '$current_generation_cycle', '$current_generation_cycle', '$IP_Address')");
					write_log("IPv6 Generation Peer Elected for Public Key: " . base64_encode($public_key), "GP");
				}
			}
			else
			{
				// More than 1 peer request, start a scoring of all public keys,
				// the public key with the most points win
				$highest_score = 0;
				$public_key_winner = NULL;
				write_log("IPv6 Peer Election Score Key: " . scorePublicKey(NULL, TRUE), "GP");

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					
					if(ipv6_test($sql_row["IP_Address"]) == TRUE)// IPv6 Only
					{	
						$public_key = $sql_row["public_key"];
				
						$public_key_score = scorePublicKey($public_key);
						write_log("IPv6 Key Score: [$public_key_score] for Public Key: " . base64_encode($public_key), "GP");

						if($public_key_score > $highest_score)
						{
							$public_key_winner = $public_key;
							$highest_score = $public_key_score;
							$IP_Address = ipv6_compress($sql_row["IP_Address"]);
						}
					}
				}

				if($public_key_score > 0)// Someone won
				{
					mysql_query("INSERT INTO `generating_peer_list` (`public_key` ,`join_peer_list` ,`last_generation`, `IP_Address`) VALUES ('$public_key_winner', '$current_generation_cycle', '$current_generation_cycle', '$IP_Address')");
					write_log("IPv6 Generation Peer Elected for Public Key: " . base64_encode($public_key_winner), "GP");
				}

			} // End if/then winner check

			// Clear out queue for next round of IPv6 Address only
			$sql = "SELECT * FROM `generating_peer_queue`";
			$sql_result = mysql_query($sql);
			$sql_num_results = mysql_num_rows($sql_result);

			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				if(ipv6_test($sql_row["IP_Address"]) == TRUE)// IPv6 Only
				{
					// Remove IPv6 Key From Queue
					mysql_query("DELETE FROM `generating_peer_queue` WHERE `timestamp` = " . $sql_row["timestamp"] . " AND `public_key` = '" . $sql_row["public_key"] . "' AND `IP_Address` = '" . $sql_row["IP_Address"] . "' LIMIT 1");
				}
			}

			// Optimize Table when Finished
			mysql_query("OPTIMIZE TABLE `generating_peer_queue`");

			// Wait after generation election for DB sanity reasons
			sleep(1);

		}	//End if/then results check

	} // End if/then timing comparison check
//***********************************************************************************	
// Store a hash of the current list of generating peers
	$generating_hash = generation_peer_hash();

	$generation_peer_hash = mysql_result(mysql_query("SELECT field_data FROM `options` WHERE `field_name` = 'generating_peers_hash' LIMIT 1"),0,0);

	if($generating_hash !== $generation_peer_hash)
	{
		// Store in database for quick reference from database
		mysql_query("UPDATE `options` SET `field_data` = '$generating_hash' WHERE `options`.`field_name` = 'generating_peers_hash' LIMIT 1");
	}
//***********************************************************************************
//***********************************************************************************
// Generation IP Auto Update Detection
	$auto_update_generation_IP = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'auto_update_generation_IP' LIMIT 1"),0,0));
	
	if(rand(1,100) == 100 && $auto_update_generation_IP == 1) // Randomize to avoid spamming
	{
		auto_update_IP_address();
	}
//***********************************************************************************	
//***********************************************************************************
	// How does my generation peer list compare to others?
	// Ask all of my active peers
	$sql = perm_peer_mode();
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$gen_list_hash_match = 0;
	$gen_list_hash_different = 0;

	if($sql_num_results > 0)
	{
		$hash_different = array();
		
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			$ip_address = $sql_row["IP_Address"];
			$domain = $sql_row["domain"];
			$subfolder = $sql_row["subfolder"];
			$port_number = $sql_row["port_number"];

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 32, "genpeer.php?action=gen_hash");

			if($generating_hash === $poll_peer)
			{
				$gen_list_hash_match++;
			}
			else
			{
				// Make sure both the response exist and that no connection error occurred
				if(strlen($poll_peer) == 32)
				{
					$gen_list_hash_different++;

					$hash_different["ip_address$gen_list_hash_different"] = $ip_address;
					$hash_different["domain$gen_list_hash_different"] = $domain;
					$hash_different["subfolder$gen_list_hash_different"] = $subfolder;
					$hash_different["port_number$gen_list_hash_different"] = $port_number;				
				}
			}

		} // End for Loop

	} // End number of results check

	// Compare tallies
	if($gen_list_hash_different > $gen_list_hash_match)
	{
		//50% over more of the active peers have a different gen list, start comparing your
		//gen list with one that is different
		$generation_peer_list_no_sync = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'generation_peer_list_no_sync' LIMIT 1"),0,"field_data"));
		if($generation_peer_list_no_sync > 20)
		{
			// Our generation peer list is out of sync for a long time, clear list to start over
			mysql_query("TRUNCATE TABLE `generating_peer_list`");

			// Reset out of sync counter
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'generation_peer_list_no_sync' LIMIT 1");

			write_log("Generation Peer List has Become Stale, Attemping to Purge and Rebuild.", "GP");
		}
		else
		{
			$generation_peer_list_no_sync++;
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '$generation_peer_list_no_sync' WHERE `main_loop_status`.`field_name` = 'generation_peer_list_no_sync' LIMIT 1");
		}

		$i = rand(1, $gen_list_hash_different); // Select Random Peer from Disagree List
		$ip_address = $hash_different["ip_address$i"];
		$domain = $hash_different["domain$i"];
		$subfolder = $hash_different["subfolder$i"];
		$port_number = $hash_different["port_number$i"];

		$poll_peer = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 90000, "genpeer.php?action=gen_peer_list"));

		if(empty($poll_peer) == TRUE)
		{
			// Add failure points to the peer in case further issues
			modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 4);
		}

		$match_number = 1;
		$gen_peer_public_key = "Start";

		$counter = 0;

		while(empty($gen_peer_public_key) == FALSE)
		{
			if($counter > 150) // Peer should never give more than 150 peers at a time
			{
				// Too many loops for peers, something is wrong or peer
				// is giving out garbage information, break from loop
				modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 5);
				break;
			}
			
			$gen_peer_public_key = find_string("-----public_key$match_number=", "-----join$match_number", $poll_peer);
			$gen_peer_join_peer_list = find_string("-----join$match_number=", "-----last$match_number", $poll_peer);
			$gen_peer_last_generation = find_string("-----last$match_number=", "-----ip$match_number", $poll_peer);
			$gen_peer_IP = find_string("-----ip$match_number=", "-----END$match_number", $poll_peer);

			$gen_peer_public_key = filter_sql(base64_decode($gen_peer_public_key));

			if(empty($gen_peer_last_generation) == TRUE)
			{
				// Old format compatible
				$gen_peer_last_generation = filter_sql(find_string("-----last$match_number=", "-----END$match_number", $poll_peer));
			}

			//Check if this public key is already in our peer list
			$public_key_match = mysql_result(mysql_query("SELECT join_peer_list FROM `generating_peer_list` WHERE `public_key` = '$gen_peer_public_key' AND `join_peer_list` = '$gen_peer_join_peer_list' AND `IP_Address` = '$gen_peer_IP' LIMIT 1"),0,0);

			if(empty($public_key_match) == TRUE)
			{
				// No match in database to this public key
				if(strlen($gen_peer_public_key) > 256 && empty($gen_peer_public_key) == FALSE && $gen_peer_join_peer_list <= $current_generation_cycle && $gen_peer_join_peer_list > TRANSACTION_EPOCH)
				{
					$sql = "INSERT INTO `generating_peer_list` (`public_key`,`join_peer_list`,`last_generation`,`IP_Address`)
					VALUES ('$gen_peer_public_key', '$gen_peer_join_peer_list', '$gen_peer_last_generation', '$gen_peer_IP')";
					mysql_query($sql);
				}
			}

			$counter++;
			$match_number++;

		} // End While Loop

		// Update Generation Peer List Hash
		$generating_hash = generation_peer_hash();

		// Store in database for quick reference from database
		mysql_query("UPDATE `options` SET `field_data` = '$generating_hash' WHERE `options`.`field_name` = 'generating_peers_hash' LIMIT 1");

	} // End Compare Tallies
	else
	{
		// Clear out any out of sync counts once the list is in sync
		if(rand(1,3) == 2) // Randomize to avoid spamming the DB
		{
			// Reset out of sync counter
			mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'generation_peer_list_no_sync' LIMIT 1");
		}
	}
//***********************************************************************************
	// Scan for new election request of IPv4 generating peers
	if(election_cycle(1) == TRUE ||
		election_cycle(2) == TRUE ||
		election_cycle(3) == TRUE ||
		election_cycle(4) == TRUE ||
		election_cycle(5) == TRUE ||
		election_cycle(6) == TRUE) // Don't queue election request until 1-6 cycles before election (30 minutes)
	{
		$network_mode = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'network_mode' LIMIT 1"),0,0));

		$sql = "SELECT * FROM `transaction_queue` WHERE `attribute` = 'R'";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		if($sql_num_results > 0)
		{
			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				$public_key = $sql_row["public_key"];
				$timestamp = $sql_row["timestamp"];
				$crypt1 = $sql_row["crypt_data1"];
				$crypt2 = $sql_row["crypt_data2"];
				$crypt3 = $sql_row["crypt_data3"];

				//Valid Public Key
				$public_key_found_peer = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),0,"join_peer_list");

				if(empty($public_key_found_peer) == FALSE)
				{
					// Is this an existing IPv6 Address
					$public_key_IP = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),0,"IP_Address");

					if(ipv6_test($public_key_IP) == TRUE)
					{
						// This is a IPv6 Gen Peer, ignore it, try for a second Gen Peer that would be IPv4
						$public_key_found_peer = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),1,"join_peer_list");
					}
				}

				$public_key_found_timestamp = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$public_key' LIMIT 2"),0,"timestamp");

				if(empty($public_key_found_timestamp) == FALSE)
				{
					// Is this an existing IPv6 Address
					$public_key_IP = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$public_key' LIMIT 2"),0,"IP_Address");

					if(ipv6_test($public_key_IP) == TRUE)
					{
						// This is a IPv6 Gen Peer, ignore it, try for a second Gen Peer that would be IPv4
						$public_key_found_timestamp = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$public_key' LIMIT 2"),1,"timestamp");
					}
				}

				if(empty($public_key_found_timestamp) == TRUE &&
					empty($public_key_found_peer) == TRUE &&
					$timestamp >= $current_generation_cycle)
				{
					// Not found, add to queue
					// Check to make sure this public key isn't forged or made up to win the list
					$transaction_info = tk_decrypt($public_key, base64_decode($crypt1));

					if($transaction_info == $crypt2)// Valid Crypt2 field check
					{
						// Check the IP/Domain field and poll the IP to see if
						// there is a valid Timekoin server at the address.
						$crypt3_data = filter_sql(tk_decrypt($public_key, base64_decode($crypt3)));

						$peer_ip = find_string("---ip=", "---domain", $crypt3_data);
						$peer_domain = find_string("---domain=", "---subfolder", $crypt3_data);
						$peer_subfolder = find_string("---subfolder=", "---port", $crypt3_data);
						$peer_port_number = find_string("---port=", "---end", $crypt3_data);
						$delete_request = find_string("---end=", "---end2", $crypt3_data);						

						// Ignore IPv6 Address
						if(ipv6_test($peer_ip) == TRUE)
						{
							// Bypass Checking Election Request
							$ipv6_peer = TRUE;
						}
						else
						{
							write_log("IPv4 Decrypting Election Request Data: [$crypt3_data] for Public Key: " . base64_encode($public_key),"GP");
							$ipv6_peer = FALSE;							
						}

						if($ipv6_peer == FALSE)//IPv4 Peer Test
						{
							// Check if IP is already in the queue or generation peer list
							$IP_exist1 = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `IP_Address` = '$peer_ip' LIMIT 1"),0,0);
							$IP_exist2 = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `IP_Address` = '$peer_ip' LIMIT 1"),0,0);

							// Calculate public key half-crypt-hash
							$arr1 = str_split($public_key, 181);
							
							if($network_mode == 3) // IPv6 Only Server
							{
								// Running IPv6 Only Mode, use gateway server to act as proxy poll check
								$gen_sql = "SELECT IP_Address, domain, subfolder, port_number FROM `active_peer_list` WHERE `failed_sent_heartbeat` = 65534 ORDER BY RAND() LIMIT 1";
								$gen_sql_row = mysql_fetch_array(mysql_query($gen_sql));

								$gateway_IP = $gen_sql_row["IP_Address"];
								$gateway_domain = $gen_sql_row["domain"];
								$gateway_subfolder = $gen_sql_row["subfolder"];
								$gateway_port_number = $gen_sql_row["port_number"];
								$gateway_voucher = FALSE;

								if($gateway_IP == "" && $gateway_domain == "")
								{
									// No gateway peers to act as a proxy poll check
									write_log("No Active Gateway Peers To Reverse Verify Half-Crypt String for IPv4 Public Key: " . base64_encode($public_key), "GP");
								}
								else
								{
									// Create map with request parameters
									$params = array ('gen_IP' => $peer_ip, 
									'public_key' => base64_encode($public_key));
									 
									// Build Http query using params
									$query = http_build_query($params);
									 
									// Create Http context details
									$contextData = array ('method' => 'POST',
									'header' => "Connection: close\r\n"."Content-Length: ".strlen($query)."\r\n",
									'content'=> $query);
									 
									// Create context resource for our request
									$context = stream_context_create(array('http' => $contextData));

									$poll_peer = poll_peer($gateway_IP, $gateway_domain, $gateway_subfolder, $gateway_port_number, 5, "genpeer.php?action=elect_gen_peer_valid", $context);

									if($poll_peer == TRUE)
									{
										// Gateway Peer can vouch for this Public Key Election Request
										write_log("IPv4 Public Key Voucher by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
										$gateway_voucher = TRUE;
										// Create Pass Voucher
										$gen_key_crypt = $arr1[0];
									}
									else
									{
										// Gateway Peer can NOT vouch for this Public Key Election Request
										write_log("IPv4 Public Key REFUSED by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
									}
								}
							}
							else
							{
								// Poll the IPv4 address that was encrypted to check for valid Timekoin server
								$gen_key_crypt = base64_decode(poll_peer($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number, 256, "genpeer.php?action=gen_key_crypt"));
								$gen_key_crypt = tk_decrypt($public_key, $gen_key_crypt);
								$gateway_voucher = FALSE;
							}

							$domain_fail = FALSE; // Reset Variable
							if(empty($peer_domain) == FALSE)
							{
								// Check if the hostname and IP fields actually match
								// and not made up or unrelated.
								$dns_ip = gethostbyname($peer_domain);
								
								if($dns_ip != $peer_ip)
								{
									// No match between Domain IP and Encoded IP
									$domain_fail = TRUE;
								}
								else
								{
									$domain_fail = FALSE;
								}
							}

							if($gateway_voucher == TRUE)
							{
								// Gateway Peer can vouch for this Public Key Election Request
								// Create Pass Voucher
								$simple_poll_fail = FALSE;
							}
							else
							{
								// Do simple poll test from server
								$simple_poll_fail = gen_simple_poll_test($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number);
							}

							// Does the public key half match what is encrypted in the 3rd crypt field from
							// the same peer? This is to process the DELETE_IP request
							if($arr1[0] == $gen_key_crypt && 
								empty($peer_ip) == FALSE && 
								empty($IP_exist2) == TRUE && 
								$domain_fail == FALSE && 
								$simple_poll_fail == FALSE &&
								is_private_ip($peer_ip) == FALSE) // Filter private IPs
							{
								if($delete_request == "DELETE_IP")
								{
									// Delete the IPv4 and any public key linked to it as it belongs to a previous unknown owner
									mysql_query("DELETE FROM `generating_peer_list` WHERE `generating_peer_list`.`IP_Address` = '$peer_ip' LIMIT 1");
									write_log("IPv4 DELETE IP Request ($peer_ip) was allowed for Public Key: " . base64_encode($public_key), "GP");
								}
							}

							// Does the public key half match what is encrypted in the 3rd crypt field from
							// the same peer?
							if($arr1[0] == $gen_key_crypt && 
								empty($peer_ip) == FALSE && 
								empty($IP_exist1) == TRUE && 
								empty($IP_exist2) == TRUE && 
								$domain_fail == FALSE && 
								$simple_poll_fail == FALSE &&
								is_private_ip($peer_ip) == FALSE) // Filter private IPs
							{
								mysql_query("INSERT INTO `generating_peer_queue` (`timestamp` ,`public_key`, `IP_Address`) VALUES ('$timestamp', '$public_key', '$peer_ip')");
								write_log("IPv4 Generation Peer Queue List was updated with Public Key: " . base64_encode($public_key), "GP");
							}
							else if(my_public_key() == $public_key)
							{
								mysql_query("INSERT INTO `generating_peer_queue` (`timestamp` ,`public_key`, `IP_Address`) VALUES ('$timestamp', '$public_key', '$peer_ip')");
								write_log("IPv4 Generation Peer Queue List was updated with My Public Key", "GP");
							}
							else
							{
								// Log Error Reasons for Reverse Verification Issues
								if($arr1[0] != $gen_key_crypt)
								{
									write_log("Could Not Reverse Verify Half-Crypt String for IPv4 Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($peer_ip) == TRUE)
								{
									write_log("No IPv4 IP Address To Reverse Verify Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($IP_exist1) == FALSE)
								{
									write_log("IPv4 Address ($peer_ip) Already Exist in the Generation List for Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($IP_exist2) == FALSE)
								{
									write_log("IPv4 Address ($peer_ip) Already Exist in the Election Queue for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($domain_fail == TRUE)
								{
									write_log("IPv4 Domain ($peer_domain) IP ($dns_ip) & Encoded IP ($peer_ip) DO NOT MATCH for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($simple_poll_fail == TRUE)
								{
									write_log("IPv4 Simple Poll Failure for Public Key: " . base64_encode($public_key), "GP");
								}
							}

						} //IPv4 Peer Test

					} // Valid Crypt2 field check

				} // Check for existing public key
				
				if(empty($public_key_found_peer) == FALSE)
				{
					// This peer is already in the generation list, so normally it would
					// not be sending another election request unless to update the
					// IP address from which it generates currency from.
					$transaction_info = tk_decrypt($public_key, base64_decode($crypt1));

					if($transaction_info == $crypt2)// Valid Crypt2 field check
					{
						// Check the IP/Domain field and poll the IP to see if
						// there is a valid Timekoin server at the address.
						$crypt3_data = filter_sql(tk_decrypt($public_key, base64_decode($crypt3)));

						$peer_ip = find_string("---ip=", "---domain", $crypt3_data);
						$peer_domain = find_string("---domain=", "---subfolder", $crypt3_data);
						$peer_subfolder = find_string("---subfolder=", "---port", $crypt3_data);
						$peer_port_number = find_string("---port=", "---end", $crypt3_data);
						$delete_request = find_string("---end=", "---end2", $crypt3_data);						

						// Ignore IPv6 Address
						if(ipv6_test($peer_ip) == TRUE)
						{
							// Bypass Checking Election Request
							$ipv6_peer = TRUE;
						}
						else
						{
							write_log("IPv4 Decrypting Election Request Data: [$crypt3_data] for Public Key: " . base64_encode($public_key),"GP");
							$ipv6_peer = FALSE;							
						}

						if($ipv6_peer == FALSE)//IPv4 Peer Test
						{
							// Check if IP is already in the generation peer list
							$IP_exist1 = mysql_result(mysql_query("SELECT join_peer_list FROM `generating_peer_list` WHERE `IP_Address` = '$peer_ip' LIMIT 1"),0,1);

							// Calculate public key half-crypt-hash
							$arr1 = str_split($public_key, 181);

							if($network_mode == 3) // IPv6 Only Server
							{
								// Running IPv6 Only Mode, use gateway server to act as proxy poll check
								$gen_sql = "SELECT IP_Address, domain, subfolder, port_number FROM `active_peer_list` WHERE `failed_sent_heartbeat` = 65534 ORDER BY RAND() LIMIT 1";
								$gen_sql_row = mysql_fetch_array(mysql_query($gen_sql));

								$gateway_IP = $gen_sql_row["IP_Address"];
								$gateway_domain = $gen_sql_row["domain"];
								$gateway_subfolder = $gen_sql_row["subfolder"];
								$gateway_port_number = $gen_sql_row["port_number"];
								$gateway_voucher = FALSE;

								if($gateway_IP == "" && $gateway_domain == "")
								{
									// No gateway peers to act as a proxy poll check
									write_log("No Active Gateway Peers To Reverse Verify Half-Crypt String for IPv4 Public Key: " . base64_encode($public_key), "GP");
								}
								else
								{
									// Create map with request parameters
									$params = array ('gen_IP' => $peer_ip, 
									'public_key' => base64_encode($public_key));
									 
									// Build Http query using params
									$query = http_build_query($params);
									 
									// Create Http context details
									$contextData = array ('method' => 'POST',
									'header' => "Connection: close\r\n"."Content-Length: ".strlen($query)."\r\n",
									'content'=> $query);
									 
									// Create context resource for our request
									$context = stream_context_create(array('http' => $contextData));

									$poll_peer = poll_peer($gateway_IP, $gateway_domain, $gateway_subfolder, $gateway_port_number, 5, "genpeer.php?action=elect_gen_peer_valid", $context);

									if($poll_peer == TRUE)
									{
										// Gateway Peer can vouch for this Public Key Election Request
										write_log("IPv4 Public Key Voucher by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
										$gateway_voucher = TRUE;
										// Create Pass Voucher
										$gen_key_crypt = $arr1[0];
									}
									else
									{
										// Gateway Peer can NOT vouch for this Public Key Election Request
										write_log("IPv4 Public Key REFUSED by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
									}
								}
							}
							else
							{
								// Poll the IPv4 address that was encrypted to check for valid Timekoin server
								$gen_key_crypt = base64_decode(poll_peer($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number, 256, "genpeer.php?action=gen_key_crypt"));
								$gen_key_crypt = tk_decrypt($public_key, $gen_key_crypt);
								$gateway_voucher = FALSE;
							}

							$domain_fail = FALSE; // Reset Variable
							if(empty($peer_domain) == FALSE)
							{
								// Check if the hostname and IP fields actually match
								// and not made up or unrelated.
								$dns_ip = gethostbyname($peer_domain);
								
								if($dns_ip != $peer_ip)
								{
									// No match between Domain IP and Encoded IP
									$domain_fail = TRUE;
								}
								else
								{
									$domain_fail = FALSE;
								}							
							}

							if($gateway_voucher == TRUE)
							{
								// Gateway Peer can vouch for this Public Key Election Request
								// Create Pass Voucher
								$simple_poll_fail = FALSE;
							}
							else
							{
								// Do simple poll test from server
								$simple_poll_fail = gen_simple_poll_test($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number);
							}

							// Does the public key half match what is encrypted in the 3rd crypt field from
							// the same peer?
							if($arr1[0] == $gen_key_crypt && 
								empty($peer_ip) == FALSE && 
								empty($IP_exist1) == TRUE && 
								$domain_fail == FALSE && 
								$simple_poll_fail == FALSE &&
								is_private_ip($peer_ip) == FALSE) // Filter private IPs
							{
								if($delete_request == "DELETE_IP")
								{
									// Delete my IP and any public key linked to it as it belongs to a previous unknown owner
									mysql_query("DELETE FROM `generating_peer_list` WHERE `generating_peer_list`.`IP_Address` = '$peer_ip' LIMIT 1");
									write_log("IPv4 DELETE IP Request ($peer_ip) was allowed for Public Key: " . base64_encode($public_key), "GP");
								}
								else
								{
									// My server has moved to another IP, update the list
									mysql_query("UPDATE `generating_peer_list` SET `IP_Address` = '$peer_ip' WHERE `generating_peer_list`.`public_key` = '$public_key' LIMIT 1");
									write_log("IPv4 New Generation Peer IP Address ($peer_ip) was updated for Public Key: " . base64_encode($public_key), "GP");
								}
							}
							else if(my_public_key() == $public_key) // This is my own public key, automatic update
							{
								if(election_cycle(1) == TRUE) // Don't update myself until right before the peer election
								{
									if(empty($IP_exist1) == TRUE)// Check to make sure this isn't already in the database
									{
										mysql_query("UPDATE `generating_peer_list` SET `IP_Address` = '$peer_ip' WHERE `generating_peer_list`.`public_key` = '$public_key' LIMIT 1");
										write_log("IPv4 Generation Peer List was updated with My New IP Address ($peer_ip)", "GP");
									}
								}
							}
							else
							{
								// Log Error Reasons for Reverse Verification Issues
								if($arr1[0] != $gen_key_crypt)
								{
									write_log("Could Not Reverse Verify Half-Crypt String for IPv4 Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($peer_ip) == TRUE)
								{
									write_log("No IPv4 IP Address To Reverse Verify Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($IP_exist1) == FALSE)
								{
									write_log("IPv4 Address ($peer_ip) Already Exist in the Generation List for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($domain_fail == TRUE)
								{
									write_log("IPv4 Domain ($peer_domain) IP ($dns_ip) & Encoded IP ($peer_ip) DO NOT MATCH for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($simple_poll_fail == TRUE)
								{
									write_log("IPv4 Simple Poll Failure for Public Key: " . base64_encode($public_key), "GP");
								}							
							}

						} //IPv4 Peer Test

					} // Valid Crypt2 field check

				} // Update Generatin IP Check

			} // End for loop through Election Request

		} // Empty results check

	} // Election Cycle Check IPv4 Peers
//***********************************************************************************	
	// Scan for new election request of IPv6 generating peers

	// Total Servers that have been Generating for at least 24 hours previous, excluding those that have just joined recently
	$gen_peers_total = mysql_result(mysql_query("SELECT COUNT(*) FROM `generating_peer_list` WHERE `join_peer_list` < " . (time() - 86400) . ""),0);

	if(election_cycle(1, 2, $gen_peers_total) == TRUE ||
		election_cycle(2, 2, $gen_peers_total) == TRUE ||
		election_cycle(3, 2, $gen_peers_total) == TRUE ||
		election_cycle(4, 2, $gen_peers_total) == TRUE ||
		election_cycle(5, 2, $gen_peers_total) == TRUE ||
		election_cycle(6, 2, $gen_peers_total) == TRUE) // Don't queue election request until 1-6 cycles before election (30 minutes)
	{
		$network_mode = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'network_mode' LIMIT 1"),0,0));

		$sql = "SELECT * FROM `transaction_queue` WHERE `attribute` = 'R'";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		if($sql_num_results > 0)
		{
			for ($i = 0; $i < $sql_num_results; $i++)
			{
				$sql_row = mysql_fetch_array($sql_result);

				$public_key = $sql_row["public_key"];
				$timestamp = $sql_row["timestamp"];
				$crypt1 = $sql_row["crypt_data1"];
				$crypt2 = $sql_row["crypt_data2"];
				$crypt3 = $sql_row["crypt_data3"];

				//Valid Public Key
				$public_key_found_peer = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),0,"join_peer_list");

				if(empty($public_key_found_peer) == FALSE)
				{
					// Is this an existing IPv4 Address
					$public_key_IP = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),0,"IP_Address");

					if(ipv6_test($public_key_IP) == FALSE)
					{
						// This is a IPv4 Gen Peer, ignore it, try for a second Gen Peer that would be IPv6
						$public_key_found_peer = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 2"),1,"join_peer_list");
					}
				}

				$public_key_found_timestamp = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$public_key' LIMIT 2"),0,"timestamp");

				if(empty($public_key_found_timestamp) == FALSE)
				{
					// Is this an existing IPv4 Address
					$public_key_IP = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$public_key' LIMIT 2"),0,"IP_Address");

					if(ipv6_test($public_key_IP) == FALSE)
					{
						// This is a IPv4 Gen Peer, ignore it, try for a second Gen Peer that would be IPv6
						$public_key_found_timestamp = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$public_key' LIMIT 2"),1,"timestamp");
					}
				}

				if(empty($public_key_found_timestamp) == TRUE &&
					empty($public_key_found_peer) == TRUE &&
					$timestamp >= $current_generation_cycle)
				{
					// Not found, add to queue
					// Check to make sure this public key isn't forged or made up to win the list
					$transaction_info = tk_decrypt($public_key, base64_decode($crypt1));

					if($transaction_info == $crypt2)// Valid Crypt2 field check
					{
						// Check the IP/Domain field and poll the IP to see if
						// there is a valid Timekoin server at the address.
						$crypt3_data = filter_sql(tk_decrypt($public_key, base64_decode($crypt3)));

						$peer_ip = ipv6_compress(find_string("---ip=", "---domain", $crypt3_data));
						$peer_domain = find_string("---domain=", "---subfolder", $crypt3_data);
						$peer_subfolder = find_string("---subfolder=", "---port", $crypt3_data);
						$peer_port_number = find_string("---port=", "---end", $crypt3_data);
						$delete_request = find_string("---end=", "---end2", $crypt3_data);						

						// Ignore IPv4 Address
						if(ipv6_test($peer_ip) == FALSE)
						{
							// Prevent further checking of IPv4 Address
							$ipv6_peer = FALSE;
						}
						else
						{
							write_log("IPv6 Decrypting Election Request Data: [$crypt3_data] for Public Key: " . base64_encode($public_key),"GP");
							$ipv6_peer = TRUE;							
						}

						if($ipv6_peer == TRUE)//IPv6 Peer Test
						{
							// Check if IP is already in the queue or generation peer list
							$IP_exist1 = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `IP_Address` = '$peer_ip' LIMIT 1"),0,0);
							$IP_exist2 = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `IP_Address` = '$peer_ip' LIMIT 1"),0,0);

							// Calculate public key half-crypt-hash
							$arr1 = str_split($public_key, 181);

							if($network_mode == 2) // IPv4 Only Server
							{
								// Running IPv4 Only Mode, use gateway server to act as proxy poll check
								$gen_sql = "SELECT IP_Address, domain, subfolder, port_number FROM `active_peer_list` WHERE `failed_sent_heartbeat` = 65534 ORDER BY RAND() LIMIT 1";
								$gen_sql_row = mysql_fetch_array(mysql_query($gen_sql));

								$gateway_IP = $gen_sql_row["IP_Address"];
								$gateway_domain = $gen_sql_row["domain"];
								$gateway_subfolder = $gen_sql_row["subfolder"];
								$gateway_port_number = $gen_sql_row["port_number"];
								$gateway_voucher = FALSE;

								if($gateway_IP == "" && $gateway_domain == "")
								{
									// No gateway peers to act as a proxy poll check
									write_log("No Active Gateway Peers To Reverse Verify Half-Crypt String for IPv6 Public Key: " . base64_encode($public_key), "GP");
								}
								else
								{
									// Create map with request parameters
									$params = array ('gen_IP' => $peer_ip, 
									'public_key' => base64_encode($public_key));
									 
									// Build Http query using params
									$query = http_build_query($params);
									 
									// Create Http context details
									$contextData = array ('method' => 'POST',
									'header' => "Connection: close\r\n"."Content-Length: ".strlen($query)."\r\n",
									'content'=> $query);
									 
									// Create context resource for our request
									$context = stream_context_create(array('http' => $contextData));

									$poll_peer = poll_peer($gateway_IP, $gateway_domain, $gateway_subfolder, $gateway_port_number, 5, "genpeer.php?action=elect_gen_peer_valid", $context);

									if($poll_peer == TRUE)
									{
										// Gateway Peer can vouch for this Public Key Election Request
										write_log("IPv6 Public Key Voucher by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
										$gateway_voucher = TRUE;
										// Create Pass Voucher
										$gen_key_crypt = $arr1[0];
									}
									else
									{
										// Gateway Peer can NOT vouch for this Public Key Election Request
										write_log("IPv6 Public Key REFUSED by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
									}
								}
							}
							else
							{
								// Poll the IPv6 address that was encrypted to check for valid Timekoin server
								$gen_key_crypt = base64_decode(poll_peer($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number, 256, "genpeer.php?action=gen_key_crypt"));
								$gen_key_crypt = tk_decrypt($public_key, $gen_key_crypt);
								$gateway_voucher = FALSE;
							}

							$domain_fail = FALSE; // Reset Variable
							if(empty($peer_domain) == FALSE)
							{
								// Check if the hostname and IP fields actually match
								// and not made up or unrelated.
								$dns_ip = gethostbyname($peer_domain);
								
								if($dns_ip != $peer_ip)
								{
									// No match between Domain IP and Encoded IP
									$domain_fail = TRUE;
								}
								else
								{
									$domain_fail = FALSE;
								}
							}

							if($gateway_voucher == TRUE)
							{
								// Gateway Peer can vouch for this Public Key Election Request
								// Create Pass Voucher
								$simple_poll_fail = FALSE;
							}
							else
							{
								// Do simple poll test from server
								$simple_poll_fail = gen_simple_poll_test($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number);
							}

							// Does the public key half match what is encrypted in the 3rd crypt field from
							// the same peer? This is to process the DELETE_IP request
							if($arr1[0] == $gen_key_crypt && 
								empty($peer_ip) == FALSE && 
								empty($IP_exist2) == TRUE && 
								$domain_fail == FALSE && 
								$simple_poll_fail == FALSE &&
								is_private_ip($peer_ip) == FALSE) // Filter private IPs
							{
								if($delete_request == "DELETE_IP")
								{
									// Delete the IPv6 and any public key linked to it as it belongs to a previous unknown owner
									mysql_query("DELETE FROM `generating_peer_list` WHERE `generating_peer_list`.`IP_Address` = '$peer_ip' LIMIT 1");
									write_log("IPv6 DELETE IP Request ($peer_ip) was allowed for Public Key: " . base64_encode($public_key), "GP");
								}
							}

							// Does the public key half match what is encrypted in the 3rd crypt field from
							// the same peer?
							if($arr1[0] == $gen_key_crypt && 
								empty($peer_ip) == FALSE && 
								empty($IP_exist1) == TRUE && 
								empty($IP_exist2) == TRUE && 
								$domain_fail == FALSE && 
								$simple_poll_fail == FALSE &&
								is_private_ip($peer_ip) == FALSE) // Filter private IPs
							{
								mysql_query("INSERT INTO `generating_peer_queue` (`timestamp` ,`public_key`, `IP_Address`) VALUES ('$timestamp', '$public_key', '$peer_ip')");
								write_log("IPv6 Generation Peer Queue List was updated with Public Key: " . base64_encode($public_key), "GP");
							}
							else if(my_public_key() == $public_key)
							{
								mysql_query("INSERT INTO `generating_peer_queue` (`timestamp` ,`public_key`, `IP_Address`) VALUES ('$timestamp', '$public_key', '$peer_ip')");
								write_log("IPv6 Generation Peer Queue List was updated with My Public Key", "GP");
							}
							else
							{
								// Log Error Reasons for Reverse Verification Issues
								if($arr1[0] != $gen_key_crypt)
								{
									write_log("Could Not Reverse Verify Half-Crypt String for IPv6 Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($peer_ip) == TRUE)
								{
									write_log("No IPv6 IP Address To Reverse Verify Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($IP_exist1) == FALSE)
								{
									write_log("IPv6 Address ($peer_ip) Already Exist in the Generation List for Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($IP_exist2) == FALSE)
								{
									write_log("IPv6 Address ($peer_ip) Already Exist in the Election Queue for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($domain_fail == TRUE)
								{
									write_log("IPv6 Domain ($peer_domain) IP ($dns_ip) & Encoded IP ($peer_ip) DO NOT MATCH for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($simple_poll_fail == TRUE)
								{
									write_log("IPv6 Simple Poll Failure for Public Key: " . base64_encode($public_key), "GP");
								}
							}

						} //IPv6 Peer Test

					} // Valid Crypt2 field check

				} // Check for existing public key
				
				if(empty($public_key_found_peer) == FALSE)
				{
					// This peer is already in the generation list, so normally it would
					// not be sending another election request unless to update the
					// IP address from which it generates currency from.
					$transaction_info = tk_decrypt($public_key, base64_decode($crypt1));

					if($transaction_info == $crypt2)// Valid Crypt2 field check
					{
						// Check the IP/Domain field and poll the IP to see if
						// there is a valid Timekoin server at the address.
						$crypt3_data = filter_sql(tk_decrypt($public_key, base64_decode($crypt3)));

						$peer_ip = ipv6_compress(find_string("---ip=", "---domain", $crypt3_data));
						$peer_domain = find_string("---domain=", "---subfolder", $crypt3_data);
						$peer_subfolder = find_string("---subfolder=", "---port", $crypt3_data);
						$peer_port_number = find_string("---port=", "---end", $crypt3_data);
						$delete_request = find_string("---end=", "---end2", $crypt3_data);

						// Ignore IPv4 Address
						if(ipv6_test($peer_ip) == FALSE)
						{
							// Prevent further checking of IPv4 Address
							$ipv6_peer = FALSE;
						}
						else
						{
							write_log("IPv6 Decrypting Election Request Data: [$crypt3_data] for Public Key: " . base64_encode($public_key),"GP");
							$ipv6_peer = TRUE;							
						}

						if($ipv6_peer == TRUE)//IPv6 Peer Test
						{
							// Check if IP is already in the generation peer list
							$IP_exist1 = mysql_result(mysql_query("SELECT join_peer_list FROM `generating_peer_list` WHERE `IP_Address` = '$peer_ip' LIMIT 1"),0,1);

							// Calculate public key half-crypt-hash
							$arr1 = str_split($public_key, 181);

							if($network_mode == 2) // IPv4 Only Server
							{
								// Running IPv4 Only Mode, use gateway server to act as proxy poll check
								$gen_sql = "SELECT IP_Address, domain, subfolder, port_number FROM `active_peer_list` WHERE `failed_sent_heartbeat` = 65534 ORDER BY RAND() LIMIT 1";
								$gen_sql_row = mysql_fetch_array(mysql_query($gen_sql));

								$gateway_IP = $gen_sql_row["IP_Address"];
								$gateway_domain = $gen_sql_row["domain"];
								$gateway_subfolder = $gen_sql_row["subfolder"];
								$gateway_port_number = $gen_sql_row["port_number"];
								$gateway_voucher = FALSE;

								if($gateway_IP == "" && $gateway_domain == "")
								{
									// No gateway peers to act as a proxy poll check
									write_log("No Active Gateway Peers To Reverse Verify Half-Crypt String for IPv6 Public Key: " . base64_encode($public_key), "GP");
								}
								else
								{
									// Create map with request parameters
									$params = array ('gen_IP' => $peer_ip, 
									'public_key' => base64_encode($public_key));
									 
									// Build Http query using params
									$query = http_build_query($params);
									 
									// Create Http context details
									$contextData = array ('method' => 'POST',
									'header' => "Connection: close\r\n"."Content-Length: ".strlen($query)."\r\n",
									'content'=> $query);
									 
									// Create context resource for our request
									$context = stream_context_create(array('http' => $contextData));

									$poll_peer = poll_peer($gateway_IP, $gateway_domain, $gateway_subfolder, $gateway_port_number, 5, "genpeer.php?action=elect_gen_peer_valid", $context);

									if($poll_peer == TRUE)
									{
										// Gateway Peer can vouch for this Public Key Election Request
										write_log("IPv6 Public Key Voucher by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
										$gateway_voucher = TRUE;
										// Create Pass Voucher
										$gen_key_crypt = $arr1[0];
									}
									else
									{
										// Gateway Peer can NOT vouch for this Public Key Election Request
										write_log("IPv6 Public Key REFUSED by Active Gateway Peer[$gateway_domain$gateway_IP:$gateway_port_number/$gateway_subfolder] for Public Key: " . base64_encode($public_key), "GP");
									}
								}
							}
							else
							{
								// Poll the IPv6 address that was encrypted to check for valid Timekoin server
								$gen_key_crypt = base64_decode(poll_peer($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number, 256, "genpeer.php?action=gen_key_crypt"));
								$gen_key_crypt = tk_decrypt($public_key, $gen_key_crypt);
								$gateway_voucher = FALSE;
							}

							$domain_fail = FALSE; // Reset Variable
							if(empty($peer_domain) == FALSE)
							{
								// Check if the hostname and IP fields actually match
								// and not made up or unrelated.
								$dns_ip = gethostbyname($peer_domain);
								
								if($dns_ip != $peer_ip)
								{
									// No match between Domain IP and Encoded IP
									$domain_fail = TRUE;
								}
								else
								{
									$domain_fail = FALSE;
								}							
							}

							if($gateway_voucher == TRUE)
							{
								// Gateway Peer can vouch for this Public Key Election Request
								// Create Pass Voucher
								$simple_poll_fail = FALSE;
							}
							else
							{
								// Do simple poll test from server
								$simple_poll_fail = gen_simple_poll_test($peer_ip, $peer_domain, $peer_subfolder, $peer_port_number);
							}

							// Does the public key half match what is encrypted in the 3rd crypt field from
							// the same peer?
							if($arr1[0] == $gen_key_crypt && 
								empty($peer_ip) == FALSE && 
								empty($IP_exist1) == TRUE && 
								$domain_fail == FALSE && 
								$simple_poll_fail == FALSE &&
								is_private_ip($peer_ip) == FALSE) // Filter private IPs
							{
								if($delete_request == "DELETE_IP")
								{
									// Delete my IP and any public key linked to it as it belongs to a previous unknown owner
									mysql_query("DELETE FROM `generating_peer_list` WHERE `generating_peer_list`.`IP_Address` = '$peer_ip' LIMIT 1");
									write_log("IPv6 DELETE IP Request ($peer_ip) was allowed for Public Key: " . base64_encode($public_key), "GP");
								}
								else
								{
									// My server has moved to another IP, update the list
									mysql_query("UPDATE `generating_peer_list` SET `IP_Address` = '$peer_ip' WHERE `generating_peer_list`.`public_key` = '$public_key' LIMIT 1");
									write_log("IPv6 New Generation Peer IP Address ($peer_ip) was updated for Public Key: " . base64_encode($public_key), "GP");
								}
							}
							else if(my_public_key() == $public_key) // This is my own public key, automatic update
							{
								if(election_cycle(1) == TRUE) // Don't update myself until right before the peer election
								{
									if(empty($IP_exist1) == TRUE)// Check to make sure this isn't already in the database
									{
										mysql_query("UPDATE `generating_peer_list` SET `IP_Address` = '$peer_ip' WHERE `generating_peer_list`.`public_key` = '$public_key' LIMIT 1");
										write_log("IPv6 Generation Peer List was updated with My New IP Address ($peer_ip)", "GP");
									}
								}
							}
							else
							{
								// Log Error Reasons for Reverse Verification Issues
								if($arr1[0] != $gen_key_crypt)
								{
									write_log("Could Not Reverse Verify Half-Crypt String for IPv6 Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($peer_ip) == TRUE)
								{
									write_log("No IPv6 IP Address To Reverse Verify Public Key: " . base64_encode($public_key), "GP");
								}
								else if(empty($IP_exist1) == FALSE)
								{
									write_log("IPv6 Address ($peer_ip) Already Exist in the Generation List for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($domain_fail == TRUE)
								{
									write_log("IPv6 Domain ($peer_domain) IP ($dns_ip) & Encoded IP ($peer_ip) DO NOT MATCH for Public Key: " . base64_encode($public_key), "GP");
								}
								else if($simple_poll_fail == TRUE)
								{
									write_log("IPv6 Simple Poll Failure for Public Key: " . base64_encode($public_key), "GP");
								}							
							}

						} //IPv6 Peer Test

					} // Valid Crypt2 field check

				} // Update Generatin IP Check

			} // End for loop

		} // Empty results check

	} // Election Cycle Check IPv6 Peers
//***********************************************************************************
} // End If/then Time Check

//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,0);

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active'");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'genpeer_last_heartbeat' LIMIT 1");

//***********************************************************************************
sleep(10);
} // End Infinite Loop
?>
