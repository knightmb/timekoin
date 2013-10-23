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
//***********************************************************************************
function transaction_queue()
{
	$next_transaction_cycle = transaction_cycle(1);
	$current_transaction_cycle = transaction_cycle(0);
	$results;

	// Wipe transaction queue of all old transaction from current to previous cycle
	if(rand(1,2) == 2) // Randomize a little
	{
		mysql_query("DELETE QUICK FROM `transaction_queue` WHERE `transaction_queue`.`timestamp` < $current_transaction_cycle");
	}

	// Create a hash of my own transaction queue
	$transaction_queue_hash = queue_hash();

	// How does my transaction queue compare to others?
	// Ask all of my active peers
	ini_set('user_agent', 'Timekoin Client (Queueclerk) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 2); // Timeout for request in seconds

	$transaction_queue_hash_match = 0;
	$transaction_queue_hash_different = 0;
	$hash_different = array();


	$sql = "SELECT * FROM `active_peer_list` ORDER BY RAND()";

	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);

	$transaction_queue_hash_match = 0;
	$transaction_queue_hash_different = 0;

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

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 40, "queueclerk.php?action=trans_hash&client=api");

			if($transaction_queue_hash === $poll_peer)
			{
				$transaction_queue_hash_match++;
			}
			else
			{
				if(empty($poll_peer) == FALSE)
				{
					$transaction_queue_hash_different++;

					$hash_different["ip_address$transaction_queue_hash_different"] = $ip_address;
					$hash_different["domain$transaction_queue_hash_different"] = $domain;
					$hash_different["subfolder$transaction_queue_hash_different"] = $subfolder;
					$hash_different["port_number$transaction_queue_hash_different"] = $port_number;				
				}
			}

		} // End for Loop

	} // End number of results check

	// Compare tallies
	if($transaction_queue_hash_different > 0)
	{
		// Transaction Queue still not in sync with all peers
		$hash_array = array();
		$transaction_counter = 0;

		for ($i = 1; $i < $transaction_queue_hash_different + 1; $i++)
		{
			$ip_address = $hash_different["ip_address$i"];
			$domain = $hash_different["domain$i"];
			$subfolder = $hash_different["subfolder$i"];
			$port_number = $hash_different["port_number$i"];

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 8200, "queueclerk.php?action=queue&client=api");

			// Bring up first match (if any) to compare agaist our database
			$match_number = 1;
			$current_hash = find_string("---queue$match_number=", "---end$match_number", $poll_peer);

			$transaction_counter = 0;
			$peer_transaction_limit = 100;
			$mismatch_error_count = 0;
			$mismatch_error_limit = 10;

			while(empty($current_hash) == FALSE)
			{
				// Count transactions coming from this peer
				$transaction_counter++;

				if($transaction_counter > $peer_transaction_limit)
				{
					break;					
				}

				if($mismatch_error_count > $mismatch_error_limit)
				{
					break;					
				}

				//Check if this transaction is already in our queue
				$hash_match = mysql_result(mysql_query("SELECT hash FROM `transaction_queue` WHERE `hash` = '$current_hash' LIMIT 1"),0,0);

				if(empty($hash_match) == TRUE)
				{
					// This peer has a different transaction, ask for the full details of it
					$poll_hash = poll_peer($ip_address, $domain, $subfolder, $port_number, 1500, "queueclerk.php?action=transaction&number=$current_hash&client=api");

					$transaction_timestamp = filter_sql(find_string("-----timestamp=", "-----public_key", $poll_hash));
					$transaction_public_key = find_string("-----public_key=", "-----crypt1", $poll_hash);
					$transaction_crypt1 = filter_sql(find_string("-----crypt1=", "-----crypt2", $poll_hash));
					$transaction_crypt2 = filter_sql(find_string("-----crypt2=", "-----crypt3", $poll_hash));
					$transaction_crypt3 = filter_sql(find_string("-----crypt3=", "-----hash", $poll_hash));
					$transaction_hash = filter_sql(find_string("-----hash=", "-----attribute", $poll_hash));
					$transaction_attribute = find_string("-----attribute=", "-----end", $poll_hash);
					$transaction_qhash = find_string("---qhash=", "---endqhash", $poll_hash);					

					// If a qhash is included, use this to verify the data
					if(empty($transaction_qhash) == FALSE)
					{
						$qhash = $transaction_timestamp . $transaction_public_key . $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3 . $transaction_hash . $transaction_attribute;
						$qhash = hash('md5', $qhash);

						// Compare hashes to make sure data is intact
						if($transaction_qhash != $qhash)
						{
							$transaction_attribute = "mismatch";
							$mismatch_error_count++;
						}
					}
					else
					{
						// Qhash is required to match hash now
						$transaction_attribute = "mismatch";
						$mismatch_error_count++;						
					}

					$transaction_public_key = filter_sql(base64_decode($transaction_public_key));

					if($transaction_attribute == "T" || $transaction_attribute == "G")
					{
						// Decrypt transaction information for regular transaction data
						// and check to make sure the public key that is being sent to
						// has not been tampered with.
						$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));

						$inside_transaction_hash = find_string("HASH=", "", $transaction_info, TRUE);

						// Check if a message is encoded in this data as well
						if(strlen($inside_transaction_hash) != 64)
						{
							// A message is also encoded
							$inside_transaction_hash = find_string("HASH=", "---MSG", $transaction_info);
						}

						// Check Hash against 3 crypt fields
						$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);
					}

					$final_hash_compare = hash('sha256', $transaction_crypt1 . $transaction_crypt2);


					// Check to make sure this transaction is even valid (hash check, length check, & timestamp)
					if($transaction_hash == $crypt_hash_check 
						&& $inside_transaction_hash == $final_hash_compare 
						&& strlen($transaction_public_key) > 300 
						&& $transaction_timestamp >= $current_transaction_cycle 
						&& $transaction_timestamp < $next_transaction_cycle)
					{
						// Check for 100 public key limit in the transaction queue
						$sql = "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
						$sql_result = mysql_query($sql);
						$sql_num_results = mysql_num_rows($sql_result);

						if($sql_num_results < 100)
						{						
							// Transaction hash and real hash match
							$sql = "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
							VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
							
							mysql_query($sql);
						}

					}
				} // End Empty Hash Check

				$match_number++;				
				$current_hash = find_string("---queue$match_number=", "---end$match_number", $poll_peer);

			} // End While Loop

		} // End For Loop

	} // End Compare Tallies

	return;
}
//***********************************************************************************
//***********************************************************************************
function peer_list()
{
	ini_set('user_agent', 'Timekoin Client (Peerlist) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 2); // Timeout for request in seconds

	$max_active_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_active_peers' LIMIT 1"),0,"field_data");
	$max_new_peers = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'max_new_peers' LIMIT 1"),0,"field_data");

	// How many active peers do we have?
	$sql = "SELECT * FROM `active_peer_list`";
	$active_peers = mysql_num_rows(mysql_query($sql));

	$sql = "SELECT * FROM `new_peers_list`";
	$new_peers = mysql_num_rows(mysql_query($sql));

	if($active_peers == 0)
	{
		// No active or new peers to poll from, start with the first contact servers
		// and copy them to the new peer list
		$sql = "SELECT * FROM `options` WHERE `field_name` = 'first_contact_server'";
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		write_log("Peer List Empty. Adding First Contact Servers.", "PL");

		// First Contact Server Format
		//---ip=192.168.0.1---domain=timekoin.com---subfolder=timekoin---port=80---code=guest---end
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
			$code = $sql_row["code"];			

			// Check to make sure that this peer is not already in our active peer list
			$duplicate_check1 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `IP_Address` = '$ip_address' LIMIT 1"),0,0);
			$duplicate_check2 = mysql_result(mysql_query("SELECT * FROM `active_peer_list` WHERE `domain` LIKE '$domain' LIMIT 1"),0,1);

			if(empty($ip_address) == TRUE)
			{
				//Don't have an IP address, check for duplicate domain or my own domain
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
				// Poll Peer for Access
				if(empty($code) == TRUE)
				{
					// Try guest access
					$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 5, "api.php?action=tk_hash_status&hash=guest");
				}
				else
				{
					// Using custom code for peer
					$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 5, "api.php?action=tk_hash_status&hash=$code");
				}

				if($poll_peer == TRUE)
				{
					// Add this peer to the active list
					// Insert this peer into our active peer table
					// Save only domain name if both IP and Domain exist
					if(empty($domain) == FALSE)
					{
						$ip_address = NULL;
					}

					if(empty($code) == TRUE)
					{
						$code = "guest";
					}

					// Store new peer in active list
					$sql = "INSERT INTO `active_peer_list` (`IP_Address` ,`domain` ,`subfolder` ,`port_number` ,`last_heartbeat` ,`join_peer_list` ,`failed_sent_heartbeat` ,`code`)
			VALUES ('$ip_address', '$domain', '$subfolder', '$port_number', '" . time() . "', '" . time() . "', '0', '$code');";

					if(mysql_query($sql) == TRUE)
					{
						// Subtract 1 from the peer difference count
						$peer_difference_count--;
						write_log("Joined with Peer $ip_address:$domain:$port_number/$subfolder", "PL");
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
				VALUES ('$peer_IP', '$peer_domain', '$peer_subfolder', '$peer_port_number', '0', 'guest')";

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
			$code = $sql_row["code"];			

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 5, "api.php?action=tk_hash_status&hash=$code");

			if($poll_peer == TRUE)
			{
				//Got a response from an active Timekoin server
				mysql_query("UPDATE `active_peer_list` SET `last_heartbeat` = '" . time() . "', `failed_sent_heartbeat` = 0 WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
			}		
			else
			{
				//No response, record polling failure for future reference
				$failed_sent_heartbeat++;

				mysql_query("UPDATE `active_peer_list` SET `failed_sent_heartbeat` = '$failed_sent_heartbeat' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
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

			// Query Server for valid Hashcode
			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 5, "api.php?action=tk_hash_status&hash=$code");

			if($poll_peer == TRUE)
			{
				//Got a response from an active Timekoin server
				mysql_query("UPDATE `new_peers_list` SET `poll_failures` = 0 WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
			}		
			else
			{
				//No response, record polling failure for future reference
				$poll_failures++;
				mysql_query("UPDATE `new_peers_list` SET `poll_failures` = '$poll_failures' WHERE `IP_Address` = '$ip_address' AND `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port_number LIMIT 1");
			}
		} // End Randomize Check
	} // End for Loop

	// Clean up reserve peer list by removing those that have no responded for over 6 poll attempts
	if(rand(1,2) == 2)// Randomize to avoid spamming DB
	{	
		mysql_query("DELETE QUICK FROM `new_peers_list` WHERE `poll_failures` > 6");
	}

	return;
}
//***********************************************************************************
//***********************************************************************************
function tk_client_task()
{
	// Repeat Task
	peer_list();
	transaction_queue();
	
	if(rand(1,300) == 100) // Check for updates
	{
		if(check_for_updates(TRUE) == 1)
		{
			// Update available, alert user
			mysql_query("UPDATE `options` SET `field_data` = '1' WHERE `options`.`field_name` = 'update_available' LIMIT 1");
		}
	}
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
	</head>
	<body>
	</body>
	</html>
	<?PHP
	flush();

	// After self-refreshing HTML, carry out background task.
	tk_client_task();
	exit;
}
//***********************************************************************************

?>
