<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(30);
session_name("tkclitask");
session_start();

//***********************************************************************************
if(mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD) == FALSE)
{
	exit;
}

if(mysql_select_db(MYSQL_DATABASE) == FALSE)
{
	exit;
}
//***********************************************************************************

function peer_list()
{
	write_log("Starting PeerList Task","PL");
	$start_time = time();

	ini_set('user_agent', 'Timekoin Client (Peerlist) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 2); // Timeout for request in seconds
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
		//---ip=192.168.0.1---domain=timekoin.com---subfolder=timekoin---port=80---code=open---end
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);
			
			$peer_ip = find_string("---ip=", "---domain", $sql_row["field_data"]);
			$peer_domain = find_string("---domain=", "---subfolder", $sql_row["field_data"]);
			$peer_subfolder = find_string("---subfolder=", "---port", $sql_row["field_data"]);
			$peer_port_number = find_string("---port=", "---code", $sql_row["field_data"]);
			$peer_code = find_string("---code=", "---end", $sql_row["field_data"]);

			// Insert into database as first contact server(s)
			$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat`, `join_peer_list`, `failed_sent_heartbeat`, `code`)
			VALUES ('$peer_ip', '$peer_domain', '$peer_subfolder', '$peer_port_number', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '0', '$peer_code');";

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
			$duplicate_check1 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' LIMIT 1"),0,0);
			$duplicate_check2 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `domain` LIKE '$domain' LIMIT 1"),0,1);

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

			if($duplicate_peer == FALSE)
			{
				//Send a challenge hash to see if a timekoin server is active
				$poll_challenge = rand(1, 999999);
				$hash_solution = hash('crc32', $poll_challenge);

				$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 10, "peerlist.php?action=poll&challenge=$poll_challenge");

				if($poll_peer == $hash_solution)
				{
					//Got a response from an active Timekoin server

					// Ask to be added to the other server's peerlist
					$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 10, "peerlist.php?action=join");

					if($poll_peer == "OK")
					{
						// Add this peer to the active list

							// Insert this peer into our active peer table
							// Save only domain name if both IP and Domain exist
							if(empty($domain) == FALSE)
							{
								$ip_address = NULL;
							}

							// Store new peer in active list
							$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat` ,`join_peer_list` ,`failed_sent_heartbeat` ,`code`)
					VALUES ('$ip_address', '$domain', '$subfolder', '$port_number', '" . time() . "', '" . time() . "', '0', 'open');";

							if(mysql_query($sql) == TRUE)
							{
								// Subtract 1 from the peer difference count
								$peer_difference_count--;

								write_log("Joined with Peer $ip_address:$domain:$port_number/$subfolder", "PL");
							}
					}
					else
					{
						// Server is either full or not responding, record polling failure
						$poll_failures++;

						mysql_query("UPDATE `new_peers_list` SET `poll_failures` = '$poll_failures' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
					}
				}
				else
				{
					//No response, record polling failure for future reference
					$poll_failures++;

					mysql_query("UPDATE `new_peers_list` SET `poll_failures` = '$poll_failures' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
				}

			} // End Duplicate Peer Check
			else
			{
				// Active response will remove poll failures
				mysql_query("UPDATE `new_peers_list` SET `poll_failures` = 0 WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
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
	// Add more peers to the new peers list to satisfy new peer limit
	// How many new peers do we have now?
	$sql = "SELECT * FROM `new_peers_list`";
	$new_peers_numbers = mysql_num_rows(mysql_query($sql));

	if($new_peers_numbers < $max_new_peers && rand(1,3) == 2)//Randomize a little to avoid spamming for new peers
	{
		// Add more possible peers to the new peer list by polling what the active peers have
		$sql = "SELECT * FROM `active_peer_list` ORDER BY RAND() LIMIT 10";
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

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 10000, "peerlist.php?action=new_peers");

			$peer_counter = 1; // Reset peer counter

			while($peer_counter <= 15) // Max response is 15 peers at any one time
			{
				$peer_IP = NULL;
				$peer_domain = NULL;
				$peer_subfolder = NULL;
				$peer_port_number = NULL;
				
				// Sort Data
				$peer_IP = find_string("-----IP$peer_counter=", "-----domain$peer_counter", $poll_peer);
				$peer_domain = find_string("-----domain$peer_counter=", "-----subfolder$peer_counter", $poll_peer);
				$peer_subfolder = find_string("-----subfolder$peer_counter=", "-----port_number$peer_counter", $poll_peer);
				$peer_port_number = find_string("-----port_number$peer_counter=", "-----", $poll_peer);

				if(is_domain_valid($peer_domain) == FALSE)
				{
					// Someone is using an IP address or Localhost :p
					$peer_domain = NULL;
				}

				if(empty($peer_port_number) == TRUE && empty($peer_subfolder) == TRUE)
				{
					// No more peers, end this loop early
					break;
				}

				if(empty($peer_IP) == TRUE && empty($peer_domain) == TRUE) // Check for blank fields in both IP/Domain
				{
					$duplicate_peer == TRUE; // Flag to avoid putting blank entry in database
				}
				else
				{
					// Check to make sure that this peer is not already in our new peer list
					$duplicate_check1 = mysql_result(mysql_query("SELECT * FROM `new_peers_list` WHERE `IP_Address` = '$peer_IP' LIMIT 1"),0,0);
					$duplicate_check2 = mysql_result(mysql_query("SELECT * FROM `new_peers_list` WHERE `domain` LIKE '$peer_domain' LIMIT 1"),0,1);

					if(empty($peer_IP) == TRUE)
					{
						//Don't have an IP address, check for duplicate domain
						if(empty($duplicate_check2) == TRUE)
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
						// Using IP only, is there a duplicate
						if(empty($duplicate_check1) == TRUE)
						{
							if(empty($duplicate_check2) == FALSE)
							{
								$duplicate_peer = TRUE;
							}
							else
							{
								$duplicate_peer = TRUE;
							}
						}
						else
						{
							$duplicate_peer = TRUE;
						}
					}
				}

				if($duplicate_peer == FALSE)
				{
					// Save only domain name if both IP and Domain exist
					if(empty($peer_domain) == FALSE)
					{
						$peer_IP = NULL;
					}				

					if(empty($peer_IP) == FALSE || empty($peer_domain) == FALSE) // Check for blank fields in both IP/Domain
					{
						// This is a fresh new peer, add it to the database list
						$sql = "INSERT INTO `new_peers_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`poll_failures` ,`code`)
				VALUES ('$peer_IP', '$peer_domain', '$peer_subfolder', '$peer_port_number', '0', 'open')";

						if(mysql_query($sql) == TRUE)
						{
							// Subtract one from total left to find
							$new_peer_difference--;
						}
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
// Send a heartbeat to all active peers in our list to make sure they are still online
	$sql = "SELECT * FROM `active_peer_list`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);

		if(rand(1,3) == 2)// Randomize to avoid spamming
		{
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

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 10, "peerlist.php?action=poll&challenge=$poll_challenge");

			if($poll_peer == $hash_solution)
			{
				//Got a response from an active Timekoin server
				$sql = "UPDATE `active_peer_list` SET `last_heartbeat` = '" . time() . "', `failed_sent_heartbeat` = 0 WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}		
			else
			{
				//No response, record polling failure for future reference
				$failed_sent_heartbeat++;

				$sql = "UPDATE `active_peer_list` SET `failed_sent_heartbeat` = '$failed_sent_heartbeat' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}
		} // End Randomize Check

	} // End for Loop

	// Remove all active peers that are offline for more than 5 minutes
	if(rand(1,2) == 2)// Randomize to avoid spamming DB
	{
		mysql_query("DELETE QUICK FROM `active_peer_list` WHERE `last_heartbeat` < " . (time() - 300) . " AND `join_peer_list` != 0");
	}

//***********************************************************************************
// Send a heartbeat to all reserve peers in our list to make sure they are still online
	$sql = "SELECT * FROM `new_peers_list`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysql_fetch_array($sql_result);

		if(rand(1,3) == 2)// Randomize to avoid spamming
		{
			$ip_address = $sql_row["IP_Address"];
			$domain = $sql_row["domain"];
			$subfolder = $sql_row["subfolder"];
			$port_number = $sql_row["port_number"];
			$poll_failures = $sql_row["poll_failures"];

			//Send a challenge hash to see if a timekoin server is active
			$poll_challenge = rand(1, 999999);
			$hash_solution = hash('crc32', $poll_challenge);

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 10, "peerlist.php?action=poll&challenge=$poll_challenge");

			if($poll_peer == $hash_solution)
			{
				//Got a response from an active Timekoin server
				$sql = "UPDATE `new_peers_list` SET `poll_failures` = 0 WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}		
			else
			{
				//No response, record polling failure for future reference
				$poll_failures++;

				$sql = "UPDATE `new_peers_list` SET `poll_failures` = '$poll_failures' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1";
				mysql_query($sql);
			}
		} // End Randomize Check
	} // End for Loop

	// Clean up reserve peer list by removing those that have no responded for over 30 poll attempts
	if(rand(1,2) == 2)// Randomize to avoid spamming DB
	{	
		mysql_query("DELETE QUICK FROM `new_peers_list` WHERE `poll_failures` > 30");
	}

	write_log("FINISHED PeerList Task (" . (time() - $start_time) . " seconds)","PL");
	return time() - $start_time; // Return Time to Process
}
//***********************************************************************************
//***********************************************************************************
function tk_client_task()
{
	// Repeat Task
	echo "</br>Running Peerlist Task (" . peer_list() . " seconds)";
	return;
}
//***********************************************************************************
//***********************************************************************************
if($_GET["task"] == "refresh")
{
	$refresh_header = '<meta http-equiv="refresh" content="' . rand(10,15) . '" />';

	?>
	<!DOCTYPE html>
	<html>
	<head>
	<title>Timekoin Client Task</title>
	<?PHP echo $refresh_header; ?>
	</head>
	<body>
	</body>
	</html>
	<?PHP

	// After self-refreshing HTML, carry out background task.
	tk_client_task();
	exit;
}
//***********************************************************************************

?>
