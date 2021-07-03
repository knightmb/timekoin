<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(60);
//***********************************************************************************
//***********************************************************************************
if(PEERLIST_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
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
// Answer poll challenge
if($_GET["action"] == "poll" && empty($_GET["challenge"]) == FALSE)
{
	echo hash('crc32', intval($_GET["challenge"]));
	
	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'PL')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer a request to see our new/active peer list (Random 10 from new peer list, Random 5 from active peer list)
if($_GET["action"] == "new_peers")
{
	// Only show peers that have less than 10 poll failures
	$sql = "SELECT * FROM `new_peers_list` WHERE `poll_failures` < 10 ORDER BY RAND() LIMIT 10";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$peer_counter = 1;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);

		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];

		// Check for non-private IP range
		if(is_private_ip($ip_address) == FALSE)
		{
			echo "-----IP$peer_counter=$ip_address-----domain$peer_counter=$domain-----subfolder$peer_counter=$subfolder-----port_number$peer_counter=$port_number-----";
			$peer_counter++;
		}
	}

	$sql = "SELECT * FROM `active_peer_list` ORDER BY RAND() LIMIT 5";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);

		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];

		// Check for non-private IP range
		if(is_private_ip($ip_address) == FALSE)
		{		
			echo "-----IP$peer_counter=$ip_address-----domain$peer_counter=$domain-----subfolder$peer_counter=$subfolder-----port_number$peer_counter=$port_number-----";
			$peer_counter++;
		}
	}

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'PL')");

	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer join request
if($_GET["action"] == "join")
{
	$max_active_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,"field_data");

	// How many active peers do we have?
	$sql = "SELECT * FROM `active_peer_list`";
	$active_peers = mysql_num_rows(mysql_query($sql));

	if($active_peers >= $max_active_peers)
	{
		// Server is full for active peers
		echo "FULL";
	}
	else
	{
		// Server has room for another peer
		echo "OK";
	}

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'PL')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer exchange request
if($_GET["action"] == "exchange")
{
	$max_active_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,"field_data");

	// How many active peers do we have?
	$sql = "SELECT * FROM `active_peer_list`";
	$active_peers = mysql_num_rows(mysql_query($sql));

	if($active_peers >= $max_active_peers)
	{
		// Server is full for active peers
		echo "FULL";
	}
	else
	{
		// Server has room for another peer
		$my_server_domain = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_domain' LIMIT 1"),0,"field_data");
		$my_server_subfolder = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_subfolder' LIMIT 1"),0,"field_data");
		$my_server_port_number = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,"field_data");

		$ip_address = $_SERVER['REMOTE_ADDR'];
		$domain = $_GET["domain"];
		$subfolder = $_GET["subfolder"];
		$port_number = $_GET["port_number"];

		// Check to make sure that this peer is not already in our active peer list
		$duplicate_check1 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' LIMIT 1"),0,"join_peer_list");
		$duplicate_check2 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `domain` LIKE '$domain' LIMIT 1"),0,"join_peer_list");

		if(empty($ip_address) == TRUE)
		{
			//Don't have an IP address, check for duplicate domain
			if(empty($duplicate_check2) == TRUE)
			{
				if($my_server_domain == $domain)
				{
					$duplicate_peer = TRUE;
				}
				else
				{
					// Neither IP nor Domain exist
					$duplicate_peer = FALSE;
				}
			}
			else
			{
				$duplicate_peer = TRUE;
			}
		}
		else
		{
			// Using IP only, is there a duplicate
			if(empty($duplicate_check1) == TRUE)
			{
				if($my_server_domain == $domain  || empty($duplicate_check2) == FALSE)
				{
					$duplicate_peer = TRUE;
				}
				else
				{
					// Check for non-private IP range
					if(is_private_ip($ip_address) == FALSE)
					{
						// Neither IP nor Domain exist
						$duplicate_peer = FALSE;
					}
					else
					{
						$duplicate_peer = TRUE;
					}
				}
			}
			else
			{
				$duplicate_peer = TRUE;
			}
		}

		if($duplicate_peer == FALSE)
		{
			$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat` ,`join_peer_list` ,`failed_sent_heartbeat`)
	VALUES ('$ip_address', '$domain', '$subfolder', '$port_number', '" . time() . "', '" . time() . "', '0');";		

			if(mysql_query($sql) == TRUE)
			{
				// Exchange was saved, now output our peer information
				echo "-----status=OK-----domain=$my_server_domain-----subfolder=$my_server_subfolder-----port_number=$my_server_port_number-----";
			}
			else
			{
				// Could not save peer, report error problem
				echo "-----status=FAILED-----domain";
			}
		}
		else
		{
			// Already in our list, might be a re-connect, so give the other peer the OK
			echo "-----status=OK-----domain=$my_server_domain-----subfolder=$my_server_subfolder-----port_number=$my_server_port_number-----";
		}
	}

	// Log inbound IP activity
	mysql_query("INSERT INTO `ip_activity` (`timestamp` ,`ip`, `attribute`)VALUES ('" . time() . "', '" . $_SERVER['REMOTE_ADDR'] . "', 'PL')");
	exit;
}
//***********************************************************************************
//***********************************************************************************
ini_set('user_agent', 'Timekoin Server (Peerlist) v' . TIMEKOIN_VERSION);
ini_set('default_socket_timeout', 3); // Timeout for request in seconds

$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'peerlist_heartbeat_active' LIMIT 1"),0,"field_data");

// Check if loop is already running
if($loop_active == 0)
{
	// Set the working status of 1
	$sql = "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'peerlist_heartbeat_active' LIMIT 1";
	mysql_query($sql);
}
else
{
	// Loop called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$max_active_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,"field_data");
$max_new_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,"field_data");

// How many active peers do we have?
$sql = "SELECT * FROM `active_peer_list`";
$active_peers = mysql_num_rows(mysql_query($sql));

$sql = "SELECT * FROM `new_peers_list`";
$new_peers = mysql_num_rows(mysql_query($sql));

if($active_peers == 0 && $new_peers == 0)
{
	// No active or new peers to poll from, start with the first contact servers
	// and copy them to the new peer list
	$sql = "SELECT * FROM `options` WHERE `field_name` = 'first_contact_server'";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	// First Contact Server Format
	//---ip=192.168.0.1---domain=timekoin.com---subfolder=timekoin---port=80---end
	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		
		preg_match_all('|---ip=(.*)---domain=|', $sql_row["field_data"], $matches);
		foreach($matches[1] as $peer_ip)
		{
		}

		preg_match_all('|---domain=(.*)---subfolder=|', $sql_row["field_data"], $matches);
		foreach($matches[1] as $peer_domain)
		{
		}

		preg_match_all('|---subfolder=(.*)---port=|', $sql_row["field_data"], $matches);
		foreach($matches[1] as $peer_subfolder)
		{
		}					

		preg_match_all('|---port=(.*)---end|', $sql_row["field_data"], $matches);
		foreach($matches[1] as $peer_port_number)
		{
		}

		// Insert into database as first contact server(s)
		$sql = "INSERT INTO `new_peers_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`poll_failures`)
		VALUES ('$peer_ip', '$peer_domain', '$peer_subfolder', '$peer_port_number', '0');";

		mysql_query($sql);
	}	
}

if($active_peers < $max_active_peers)
{
	//Start polling peers from the new peers list
	$sql = "SELECT * FROM `new_peers_list` ORDER BY RAND() LIMIT 10";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$my_server_domain = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_domain' LIMIT 1"),0,"field_data");
	$my_server_subfolder = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_subfolder' LIMIT 1"),0,"field_data");
	$my_server_port_number = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_port_number' LIMIT 1"),0,"field_data");

	// Peer difference
	$peer_difference_count = $max_active_peers - $active_peers;

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);
		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];
		$poll_failures = $sql_row["poll_failures"];

		// Check to make sure that this peer is not already in our active peer list
		$duplicate_check1 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' LIMIT 1"),0,"join_peer_list");
		$duplicate_check2 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `domain` LIKE '$domain' LIMIT 1"),0,"join_peer_list");

		if(empty($ip_address) == TRUE)
		{
			//Don't have an IP address, check for duplicate domain or my own domain
			if(empty($duplicate_check2) == TRUE && $my_server_domain != $domain)
			{
				// Neither IP nor Domain exist
				$duplicate_peer = FALSE;
			}
			else
			{
				$duplicate_peer = TRUE;
			}
		}
		else
		{
			// Check for non-private IP range
			if(is_private_ip($ip_address) == FALSE)
			{
				// Using IP only, is there a duplicate IP or Domain
				if(empty($duplicate_check1) == TRUE && empty($duplicate_check2) == TRUE)
				{
					$duplicate_peer = FALSE;
				}
				else
				{
					$duplicate_peer = TRUE;
				}
			}
			else
			{
				// Filter private IP ranges
				$duplicate_peer = TRUE;
			}
		}

		if($duplicate_peer == FALSE)
		{
			//Send a challenge hash to see if a timekoin server is active
			$poll_challenge = rand(1, 999999);
			$hash_solution = hash('crc32', $poll_challenge);

			if(empty($domain) == TRUE)
			{
				$site_address = $ip_address;
			}
			else
			{
				$site_address = $domain;
			}

			//Use site address to poll
			$poll_peer = file_get_contents("http://$site_address:$port_number/$subfolder/peerlist.php?action=poll&challenge=$poll_challenge", NULL, NULL, NULL, 10);

			if($poll_peer == $hash_solution)
			{
				//Got a response from an active Timekoin server

				// Ask to be added to the other server's peerlist
				$poll_peer = file_get_contents("http://$site_address:$port_number/$subfolder/peerlist.php?action=join", NULL, NULL, NULL, 10);

				if($poll_peer == "OK")
				{
					// Add this peer to the active list
					$poll_peer = file_get_contents("http://$site_address:$port_number/$subfolder/peerlist.php?action=exchange&domain=$my_server_domain&subfolder=$my_server_subfolder&port_number=$my_server_port_number", NULL, NULL, NULL, 512);

					// Check to see if exchange was successful
					preg_match_all('|-----status=(.*)-----domain=|', $poll_peer, $matches);
					foreach($matches[1] as $exchange_status)
					{
					}

					if($exchange_status == "OK")
					{
						// Insert this peer into our active peer table
						preg_match_all('|-----domain=(.*)-----subfolder=|', $poll_peer, $matches);
						foreach($matches[1] as $peer_domain)
						{
						}

						preg_match_all('|-----subfolder=(.*)-----port_number=|', $poll_peer, $matches);
						foreach($matches[1] as $peer_subfolder)
						{
						}					

						preg_match_all('|-----port_number=(.*)-----|', $poll_peer, $matches);
						foreach($matches[1] as $peer_port_number)
						{
						}

						$peer_port_number = intval($peer_port_number);

						// Store new peer in active list
						$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat` ,`join_peer_list` ,`failed_sent_heartbeat`)
				VALUES ('$ip_address', '$peer_domain', '$peer_subfolder', '$peer_port_number', '" . time() . "', '" . time() . "', '0');";		

						if(mysql_query($sql) == TRUE)
						{
							// Subtract 1 from the peer difference count
							$peer_difference_count--;
						}
					}
					else if($exchange_status == "FULL")
					{
						// Server is full already
					}
				}
				else
				{
					// Server is either full or not responding, record polling failure
					$poll_failures++;

					$sql = "UPDATE `new_peers_list` SET `poll_failures` = '$poll_failures' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
					mysql_query($sql);
				}
			}
			else
			{
				//No response, record polling failure for future reference
				$poll_failures++;

				$sql = "UPDATE `new_peers_list` SET `poll_failures` = '$poll_failures' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}

		} // End Duplicate Peer Check
		else
		{
			// Active response will remove poll failures
			if($poll_failures > 0)
			{
				$poll_failures--;

				$sql = "UPDATE `new_peers_list` SET `poll_failures` = '$poll_failures' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}
		}

		// Clean up active peer list by removing those that have no responded for over 30 poll attempts
		if($poll_failures >= 30)
		{
			// Server has been offline too long, remove it from the active peers list
			$sql = "DELETE FROM `new_peers_list` WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
			mysql_query($sql);
		}

		// Check to see if enough peers have been added
		if($peer_difference_count <= 0)
		{
			// Break out of loop
			break;
		}

	} // End For Loop

} // End Active vs Max Peer Check

//***********************************************************************************
//***********************************************************************************
// Add more peers to the new peers list to satisfy new peer limit

// How many new peers do we have?
$sql = "SELECT * FROM `new_peers_list`";
$new_peers_numbers = mysql_num_rows(mysql_query($sql));

if($new_peers_numbers < $max_new_peers && rand(1,3) == 2)//Randomize a little to avoid spamming for new peers
{
	$my_server_domain = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_domain' LIMIT 1"),0,"field_data");

	// Add more possible peers to the new peer list by polling what the active peers have
	$sql = "SELECT * FROM `active_peer_list`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$new_peer_difference = $max_new_peers - $new_peers_numbers;

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

		//Use site address name to poll
		$poll_peer = file_get_contents("http://$site_address:$port_number/$subfolder/peerlist.php?action=new_peers", NULL, NULL, NULL, 10000);

		$peer_counter = 1; // Reset peer counter

		while($peer_counter <= 10) // Max response is 10 peers at a time
		{
			$peer_IP = "";
			$peer_domain = "";
			$peer_subfolder = "";
			$peer_port_number = "";			
			
			// Sort Data
			preg_match_all("|-----IP$peer_counter=(.*)-----domain$peer_counter=|", $poll_peer, $matches);
			foreach($matches[1] as $peer_IP)
			{
			}

			preg_match_all("|-----domain$peer_counter=(.*)-----subfolder$peer_counter=|", $poll_peer, $matches);
			foreach($matches[1] as $peer_domain)
			{
			}

			preg_match_all("|-----subfolder$peer_counter=(.*)-----port_number$peer_counter=|", $poll_peer, $matches);
			foreach($matches[1] as $peer_subfolder)
			{
			}					

			preg_match_all("|-----port_number$peer_counter=(.*)----------IP" . ($peer_counter + 1) ."|", $poll_peer, $matches);
			foreach($matches[1] as $peer_port_number)
			{
			}

			if(empty($peer_port_number) == TRUE)
			{
				// It's possible there are no more characters to compare against
				// for port number
				preg_match_all("|-----port_number$peer_counter=(.*)-----|", $poll_peer, $matches);
				foreach($matches[1] as $peer_port_number)
				{
				}
			}

			if(empty($peer_port_number) == TRUE && empty($peer_subfolder) == TRUE)
			{
				// No more peers, end this loop early
				break;
			}			

			// Check to make sure that this peer is not already in our new peer list
			$duplicate_check1 = mysql_result(mysql_query("SELECT * FROM `new_peers_list` WHERE `IP_Address` = '$peer_IP' LIMIT 1"),0,"port_number");
			$duplicate_check2 = mysql_result(mysql_query("SELECT * FROM `new_peers_list` WHERE `domain` LIKE '$peer_domain' LIMIT 1"),0,"port_number");

			if(empty($peer_IP) == TRUE)
			{
				//Don't have an IP address, check for duplicate domain
				if(empty($duplicate_check2) == TRUE)
				{
					if($my_server_domain == $peer_domain)
					{
						$duplicate_peer = TRUE;
					}
					else
					{
						// Neither IP nor Domain exist
						$duplicate_peer = FALSE;
					}
				}
				else
				{
					$duplicate_peer = TRUE;
				}
			}
			else
			{
				// Using IP only, is there a duplicate
				if(empty($duplicate_check1) == TRUE)
				{
					if($my_server_domain == $peer_domain || empty($duplicate_check2) == FALSE)
					{
						$duplicate_peer = TRUE;
					}
					else
					{
						// Check for non-private IP range
						if(is_private_ip($ip_address) == FALSE)
						{
							// Neither IP nor Domain exist
							$duplicate_peer = FALSE;
						}
						else
						{
							$duplicate_peer = TRUE;
						}
					}
				}
				else
				{
					$duplicate_peer = TRUE;
				}
			}

			if($duplicate_peer == FALSE)
			{
				// This is a fresh new peer, add it to the database list
				$sql = "INSERT INTO `new_peers_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`poll_failures`)
		VALUES ('$peer_IP', '$peer_domain', '$peer_subfolder', '$peer_port_number', '0');";
			
				if(mysql_query($sql) == TRUE)
				{
					// Subtract one from total left to find
					$new_peer_difference--;
				}
			}

			if($new_peer_difference <= 0)
			{
				// Enough new peers saved, break out of while loop early
				break;
			}

			$peer_counter++;
		} // End While loop check

		if($new_peer_difference <= 0)
		{
			// Enough new peers saved, break out of for loop early
			break;
		}

	} // End For loop check

} // End New Peers vs Max New Peers check

//***********************************************************************************
//***********************************************************************************
// Send a heartbeat to all peers in our list to make sure they are still online
	$sql = "SELECT * FROM `active_peer_list`";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);

		$ip_address = $sql_row["IP_Address"];
		$domain = $sql_row["domain"];
		$subfolder = $sql_row["subfolder"];
		$port_number = $sql_row["port_number"];
		$last_heartbeat = $sql_row["last_heartbeat"];
		$join_peer_list = $sql_row["join_peer_list"];
		$failed_sent_heartbeat = $sql_row["failed_sent_heartbeat"];

		//Send a challenge hash to see if a timekoin server is active
		$poll_challenge = rand(1, 999999);
		$hash_solution = hash('crc32', $poll_challenge);

		if(empty($domain) == TRUE)
		{
			$site_address = $ip_address;
		}
		else
		{
			$site_address = $domain;
		}

		if(rand(1,3) == 2)// Randomize to avoid spamming
		{
			//Send out poll request
			$poll_peer = file_get_contents("http://$site_address:$port_number/$subfolder/peerlist.php?action=poll&challenge=$poll_challenge", NULL, NULL, NULL, 10);

			if($poll_peer == $hash_solution)
			{
				//Got a response from an active Timekoin server
				$sql = "UPDATE `active_peer_list` SET `last_heartbeat` = '" . time() . "' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}		
			else
			{
				//No response, record polling failure for future reference
				$failed_sent_heartbeat++;

				$sql = "UPDATE `active_peer_list` SET `failed_sent_heartbeat` = '$failed_sent_heartbeat' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}
		}

		// Clean up active peer list by removing those that have no responded for over 5 minutes
		if((time() - $last_heartbeat) > 300)
		{
			// Server has been offline too long, remove it from the active peers list
			$sql = "DELETE FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number AND `join_peer_list` = $join_peer_list LIMIT 1";
			mysql_query($sql);
		}

	} // End for Loop
//***********************************************************************************
//***********************************************************************************
// Script finished, set status to 0
$sql = "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'peerlist_heartbeat_active' LIMIT 1";
mysql_query($sql);

// Record when this script finished
$sql = "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'peerlist_last_heartbeat' LIMIT 1";
mysql_query($sql);

?>
