<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(90);
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
$ip = mysql_result(mysql_query("SELECT * FROM `ip_banlist` WHERE `ip` = '" . $_SERVER['REMOTE_ADDR'] . "' LIMIT 1"),0,0);

if(empty($ip) == FALSE)
{
	// Sorry, your IP address has been banned :(
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer generation hash poll
if($_GET["action"] == "gen_hash")
{
	$transaction_queue_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'generating_peers_hash' LIMIT 1"),0,"field_data");

	echo $transaction_queue_hash;

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'GP')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer generation peer list poll
if($_GET["action"] == "gen_peer_list")
{
	$sql = "SELECT * FROM `generating_peer_list` ORDER BY RAND() LIMIT 50";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$queue_number = 1;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			echo "-----public_key$queue_number=" , base64_encode($sql_row["public_key"]) , "-----join$queue_number=" , $sql_row["join_peer_list"] , "-----last$queue_number=" , $sql_row["last_generation"] , "-----END$queue_number";

			$queue_number++;
		}
	}

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'GP')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'genpeer_heartbeat_active' LIMIT 1"),0,"field_data");

// Check if loop is already running
if($loop_active == 0)
{
	// Set the working status of 1
	$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active' LIMIT 1";
	mysql_query($sql);
}
else
{
	// Loop called while still working
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
	// Determine when to run this by comparing the last digit the current block and
	// the 3rd digit the generation time; when they match, run the gen key scoring.
	$str = strval($current_generation_cycle);
	$last3_gen = $str[strlen($str)-3];

	$current_generation_block = transaction_cycle(0, TRUE);
	TKRandom::seed($current_generation_block);
	$tk_random_number = TKRandom::num(0, 9);

	if($last3_gen + $tk_random_number > 14)
	{
		// Find all transactions between the Previous Transaction Cycle and the Current		
		$sql = "SELECT * FROM `generating_peer_queue` WHERE `timestamp` < $current_generation_cycle ORDER BY `timestamp`";

		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		if($sql_num_results > 0)
		{
			if($sql_num_results == 1)
			{
				// Winner by default
				$sql_row = mysql_fetch_array($sql_result);
				$public_key = $sql_row["public_key"];

				$sql = "INSERT INTO `generating_peer_list` (`public_key` ,`join_peer_list` ,`last_generation`)VALUES ('$public_key', '$current_generation_cycle', '$current_generation_cycle')";
				mysql_query($sql);

				write_log("Generation Peer Elected for Public Key: " . base64_encode($public_key), "GP");				
			}
			else
			{
				// More than 1 peer request, start a scoring of all public keys,
				// the public key with the most points win
				$highest_score = 0;
				$public_key_winner = "";

				for ($i = 0; $i < $sql_num_results; $i++)
				{
					$sql_row = mysql_fetch_array($sql_result);
					$public_key = $sql_row["public_key"];

					$public_key_score = scorePublicKey($public_key);

					if($public_key_score > $highest_score)
					{
						$public_key_winner = $public_key;
						$highest_score = $public_key_score;
					}
				}

				$sql = "INSERT INTO `generating_peer_list` (`public_key` ,`join_peer_list` ,`last_generation`)VALUES ('$public_key_winner', '$current_generation_cycle', '$current_generation_cycle')";
				mysql_query($sql);

				write_log("Generation Peer Elected for Public Key: " . base64_encode($public_key_winner), "GP");
			} // End if/then winner check

			// Clear out queue for next round			
			$sql = "TRUNCATE TABLE `generating_peer_queue`";
			mysql_query($sql);

			// Wait after generation election for sanity reasons
			sleep(2);

		}	//End if/then results check

	} // End if/then timing comparison check
//***********************************************************************************
// Store a hash of the current list of generating peers
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

	$generation_peer_hash = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'generating_peers_hash' LIMIT 1"),0,"field_data");

	if($generating_hash == $generation_peer_hash)
	{
		// Both match, no need to update database
	}
	else
	{
		// Store in database for quick reference from database
		$sql = "UPDATE `options` SET `field_data` = '$generating_hash' WHERE `options`.`field_name` = 'generating_peers_hash' LIMIT 1";
		mysql_query($sql);
	}
//***********************************************************************************
//***********************************************************************************
	// How does my generation peer list compare to others?
	// Ask all of my active peers
	ini_set('user_agent', 'Timekoin Server (Genpeer) v' . TIMEKOIN_VERSION);	
	ini_set('default_socket_timeout', 3); // Timeout for request in seconds
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	
	$sql = "SELECT * FROM `active_peer_list` ORDER BY RAND()";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$gen_list_hash_match = 0;
	$gen_list_hash_different = 0;
	$site_address;

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

			if(empty($domain) == TRUE)
			{
				$site_address = $ip_address;
			}
			else
			{
				$site_address = $domain;
			}

			$poll_peer = filter_sql(file_get_contents("http://$site_address:$port_number/$subfolder/genpeer.php?action=gen_hash", FALSE, $context, NULL, 100));

			if($generating_hash === $poll_peer)
			{
				$gen_list_hash_match++;
			}
			else
			{
				// Make sure both the response exist and that no connectoin error occurred
				if(empty($poll_peer) == FALSE && $poll_peer !== FALSE)
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

		$generation_peer_list_no_sync = intval(mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'generation_peer_list_no_sync' LIMIT 1"),0,"field_data"));
		if($generation_peer_list_no_sync > 20)
		{
			// Our generation peer list is out of sync for a long time, clear to list to start over
			mysql_query("TRUNCATE TABLE `generating_peer_list`");

			// Reset out of sync counter
			mysql_query("UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'generation_peer_list_no_sync' LIMIT 1");

			write_log("Generation Peer List has Become Stale, Attemping to Purge and Rebuild.", "GP");
		}
		else
		{
			$generation_peer_list_no_sync++;
			mysql_query("UPDATE `options` SET `field_data` = '$generation_peer_list_no_sync' WHERE `options`.`field_name` = 'generation_peer_list_no_sync' LIMIT 1");
		}

		$i = rand(1, $gen_list_hash_different); // Select Random Peer from Disagree List
		$ip_address = $hash_different["ip_address$i"];
		$domain = $hash_different["domain$i"];
		$subfolder = $hash_different["subfolder$i"];
		$port_number = $hash_different["port_number$i"];

		if(empty($domain) == TRUE)
		{
			$site_address = $ip_address;
		}
		else
		{
			$site_address = $domain;
		}

		$poll_peer = filter_sql(file_get_contents("http://$site_address:$port_number/$subfolder/genpeer.php?action=gen_peer_list", FALSE, $context, NULL, 100000));

		$match_number = 1;
		$gen_peer_public_key = "Start";

		while(empty($gen_peer_public_key) == FALSE)
		{
			$gen_peer_public_key = find_string("-----public_key$match_number=", "-----join$match_number", $poll_peer);
			$gen_peer_join_peer_list = find_string("-----join$match_number=", "-----last$match_number", $poll_peer);
			$gen_peer_last_generation = find_string("-----last$match_number=", "-----END$match_number", $poll_peer);

			$gen_peer_public_key = base64_decode($gen_peer_public_key);

			//Check if this public key is already in our peer list
			$public_key_match = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$gen_peer_public_key' LIMIT 1"),0,0);

			if(empty($public_key_match) == TRUE)
			{
				// No match in database to this public key
				if(strlen($gen_peer_public_key) > 256 && empty($gen_peer_public_key) == FALSE && $gen_peer_join_peer_list <= $current_generation_cycle && $gen_peer_join_peer_list > TRANSACTION_EPOCH)
				{
					$sql = "INSERT INTO `generating_peer_list` (`public_key`,`join_peer_list`,`last_generation`)
					VALUES ('$gen_peer_public_key', '$gen_peer_join_peer_list', '$gen_peer_last_generation')";
					mysql_query($sql);
				}
			}				

			$match_number++;

		} // End While Loop

	} // End Compare Tallies
	else
	{
		// Clear out any out of sync counts once the list is in sync
		if(rand(1,60) == 30) // Randomize to avoid spamming the DB
		{
			// Reset out of sync counter
			mysql_query("UPDATE `options` SET `field_data` = '0' WHERE `options`.`field_name` = 'generation_peer_list_no_sync' LIMIT 1");
		}
	}
//***********************************************************************************
	// Scan for new request of generating peers
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
			
			//Valid Public Key
			$public_key_found_peer = mysql_result(mysql_query("SELECT * FROM `generating_peer_list` WHERE `public_key` = '$public_key' LIMIT 1"),0,"join_peer_list");
			$public_key_found_timestamp = mysql_result(mysql_query("SELECT * FROM `generating_peer_queue` WHERE `public_key` = '$public_key' LIMIT 1"),0,"timestamp");

			if(empty($public_key_found_timestamp) == TRUE && empty($public_key_found_peer) == TRUE && $timestamp >= $current_generation_cycle)
			{
				// Not found, add to queue
				// Check to make sure this public key isn't forged or made up to win the list
				openssl_public_decrypt(base64_decode($crypt1), $transaction_info, $public_key);

				if($transaction_info == $crypt2)
				{
					$sql = "INSERT INTO `generating_peer_queue` (`timestamp` ,`public_key`)VALUES ('$timestamp', '$public_key')";
					mysql_query($sql);

					write_log("Generation Peer Queue List was updated with Public Key: " . base64_encode($public_key), "GP");
				}
			}

		} // End for loop

	} // Empty results check

//***********************************************************************************
} // End If/then Time Check

//***********************************************************************************
//***********************************************************************************
// Script finished, set status to 0
$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'genpeer_heartbeat_active' LIMIT 1";
mysql_query($sql);

// Record when this script finished
$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'genpeer_last_heartbeat' LIMIT 1";
mysql_query($sql);

?>
