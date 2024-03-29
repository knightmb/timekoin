<?PHP
include 'configuration.php';
include 'function.php';
//***********************************************************************************
//***********************************************************************************
if(QUEUECLERK_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit;
}

// Open persistent connection to database
$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);
//***********************************************************************************
// Answer transaction hash poll
if($_GET["action"] == "trans_hash")
{
	echo mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,0);

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP", 1);
	}
	else
	{
		log_ip("QU", 1);
	}

	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction hash poll
if($_GET["action"] == "reverse_queue")
{
	$next_transaction_cycle = transaction_cycle(1);
	$current_transaction_cycle = transaction_cycle(0);

	// Can we work on the transactions in the database?
	// Not allowed 30 seconds before and 30 seconds after transaction cycle.
	if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30)
	{
		$domain = filter_sql($_GET["domain"]);
		$ip = $_SERVER['REMOTE_ADDR'];
		$subfolder = filter_sql($_GET["subfolder"]);
		$port = intval($_GET["port"]);
		$reverse_queue_data = $_POST["reverse_queue_data"];
		$qhash = $_POST["qhash"];
		$connected_peer_check;

		if(empty($domain) == TRUE) //Only work with data from peers you are connected with, avoid Anonymous data from flooding in
		{
			// No Domain, IP Only
			$connected_peer_check = mysql_result(mysqli_query($db_connect, "SELECT failed_sent_heartbeat FROM `active_peer_list` WHERE `IP_Address` = '$ip' AND `subfolder` = '$subfolder' AND `port_number` = $port LIMIT 1"),0,0);
		}
		else
		{
			// Domain
			$connected_peer_check = mysql_result(mysqli_query($db_connect, "SELECT failed_sent_heartbeat FROM `active_peer_list` WHERE `domain` = '$domain' AND `subfolder` = '$subfolder' AND `port_number` = $port LIMIT 1"),0,0);
		}

		if($connected_peer_check != "" && empty($reverse_queue_data) == TRUE)
		{
			// Polling to check for active and working reverse queue possible
			echo "OK";

			// Log inbound IP activity
			log_ip("QU", 1);
			exit;
		}
		else if($connected_peer_check == "")
		{
			// Don't allow flood/spam data from unknown peers
			log_ip("QU", scale_trigger(100));
			exit;
		}

		if($connected_peer_check != "" && empty($reverse_queue_data) == FALSE && $qhash == hash('md5', $reverse_queue_data))// Connected Peer has Bulk Queue Data to Process
		{
			// Data passes MD5 check for Data Corruption
			session_write_close(); // Don't lock session, so other peer is not waiting for a finish reply while processing
			$match_number = 1;
			$good_transaction_insert = 0;
			$ignore_transaction_insert = 0;		
			$transaction_timestamp = intval(find_string("---timestamp$match_number=", "---public_key$match_number", $reverse_queue_data));
			$transaction_public_key = filter_sql(base64_decode(find_string("---public_key$match_number=", "---crypt1_data$match_number", $reverse_queue_data)));
			$transaction_crypt1 = find_string("---crypt1_data$match_number=", "---crypt2_data$match_number", $reverse_queue_data);
			$transaction_crypt2 = find_string("---crypt2_data$match_number=", "---crypt3_data$match_number", $reverse_queue_data);
			$transaction_crypt3 = find_string("---crypt3_data$match_number=", "---hash$match_number", $reverse_queue_data);
			$transaction_hash = filter_sql(find_string("---hash$match_number=", "---attribute$match_number", $reverse_queue_data));
			$transaction_attribute = filter_sql(find_string("---attribute$match_number=", "---end$match_number", $reverse_queue_data));

			while(empty($transaction_public_key) == FALSE && $transaction_attribute != "R")//Insert Queue Transactions that do not currently exist
			{
				// Make sure hash is actually valid and not made up to stop other transactions
				$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);

				if($transaction_hash == $crypt_hash_check)
				{
					// Hash check good, check for duplicate transaction already in queue
					$hash_match = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_queue` WHERE `timestamp`= $transaction_timestamp AND `hash` = '$transaction_hash' LIMIT 1"));
				}
				else
				{
					// Ok, something is very wrong here...
					$ignore_transaction_insert++;
					$hash_match = "mismatch";
				}

				if(empty($hash_match) == TRUE) // Duplicate Check
				{
					// No duplicate found, continue processing
					// Check to make sure attribute is valid
					if($transaction_attribute == "T" || $transaction_attribute == "G")
					{
						// Decrypt transaction information for regular transaction data
						// and check to make sure the public key that is being sent to
						// has not been tampered with.
						$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));
						
						// Find destination public key
						$public_key_to_1 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt1));
						$public_key_to_2 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt2));
						$public_key_to = $public_key_to_1 . $public_key_to_2;

						$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);

						$transaction_amount_sent_test = intval($transaction_amount_sent);

						if($transaction_amount_sent_test == $transaction_amount_sent)
						{
							// Is a valid integer, amount greater than zero?
							if($transaction_amount_sent > 0)
							{
								$valid_amount = TRUE;
							}
							else
							{
								$valid_amount = FALSE;
							}
						}
						else
						{
							// Is NOT a valid integer, fail check
							$valid_amount = FALSE;
						}

						if($transaction_attribute == "G")
						{
							if($transaction_amount_sent_test > 20)
							{
								// Filter silly generation amounts :p
								$valid_amount = FALSE;
							}
						}

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

					// Check to make sure this transaction is even valid
					if($transaction_hash == $crypt_hash_check 
						&& $inside_transaction_hash == $final_hash_compare 
						&& strlen($transaction_public_key) > 300 
						&& strlen($public_key_to) > 300 
						&& $transaction_timestamp >= $current_transaction_cycle 
						&& $transaction_timestamp < $next_transaction_cycle
						&& $valid_amount == TRUE)
					{
						// Check for 100 public key limit in the transaction queue
						$sql = "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
						$sql_result = mysqli_query($db_connect, $sql);
						$sql_num_results = mysqli_num_rows($sql_result);

						if($sql_num_results < 100)
						{						
							// Transaction hash and real hash match
							$sql = "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
							VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
							
							if(mysqli_query($db_connect, $sql) == TRUE)
							{
								// Transaction Insert Accepted
								$good_transaction_insert++;
							}
						}
						else
						{
							// More than 100 Transactions for Same Public Key
							$ignore_transaction_insert++;
						}
					}
					else
					{
						// Something was wrong with Transaction Data
						$ignore_transaction_insert++;
					}

				} // End Duplicate & Timestamp check
				else
				{
					// This Transaction is Already in the Queue
					$ignore_transaction_insert++;
				}

				// Cycle up next batch of data
				$match_number++;
				$transaction_timestamp = intval(find_string("---timestamp$match_number=", "---public_key$match_number", $reverse_queue_data));
				$transaction_public_key = filter_sql(base64_decode(find_string("---public_key$match_number=", "---crypt1_data$match_number", $reverse_queue_data)));
				$transaction_crypt1 = find_string("---crypt1_data$match_number=", "---crypt2_data$match_number", $reverse_queue_data);
				$transaction_crypt2 = find_string("---crypt2_data$match_number=", "---crypt3_data$match_number", $reverse_queue_data);
				$transaction_crypt3 = find_string("---crypt3_data$match_number=", "---hash$match_number", $reverse_queue_data);
				$transaction_hash = filter_sql(find_string("---hash$match_number=", "---attribute$match_number", $reverse_queue_data));
				$transaction_attribute = filter_sql(find_string("---attribute$match_number=", "---end$match_number", $reverse_queue_data));			

			} // End While Loop Cycling

			write_log("Reverse Queue Update For [$good_transaction_insert] Transactions Complete - [$ignore_transaction_insert] Ignored - From Peer [$domain] $ip:$port/$subfolder", "QC");

		} // End Valid Queue Data MD5 Check

	}// End Valid Transaction Cycle Check

	// Log inbound IP activity
	log_ip("QU", 1);
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction queue poll
if($_GET["action"] == "queue")
{
	$sql = "SELECT * FROM `transaction_queue` ORDER BY RAND() LIMIT 1000";

	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);
	$queue_number = 1;
	$transaction_queue_hash;

	if($sql_num_results > 0)
	{
		$echo_buffer = NULL;
		
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);

			$transaction_queue_hash.= $sql_row["timestamp"] . $sql_row["public_key"] . $sql_row["crypt_data1"] . 
			$sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];

			$echo_buffer.= "---queue$queue_number=" . hash('md5', $transaction_queue_hash) . "---end$queue_number";

			// Clear Variable
			$transaction_queue_hash = NULL;

			$queue_number++;
		}

		echo $echo_buffer;
	}

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP", 1);
	}
	else
	{
		log_ip("QU", 1);
	}
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer transaction details that match our hash number
if($_GET["action"] == "transaction" && empty($_GET["number"]) == FALSE)
{
	$current_hash = $_GET["number"];
	$sql = "SELECT * FROM `transaction_queue`";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);
	$transaction_queue_hash;
	$qhash;

	if($sql_num_results > 0)
	{
		$echo_buffer = NULL;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);
			$transaction_queue_hash = $sql_row["timestamp"] . $sql_row["public_key"] . $sql_row["crypt_data1"] . 
			$sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];		

			if(hash('md5', $transaction_queue_hash) == $current_hash)
			{
				$qhash = $sql_row["timestamp"] . base64_encode($sql_row["public_key"]) . $sql_row["crypt_data1"] . $sql_row["crypt_data2"] . $sql_row["crypt_data3"] . $sql_row["hash"] . $sql_row["attribute"];
				$qhash = hash('md5', $qhash);
				
				$echo_buffer.= "-----timestamp=" . $sql_row["timestamp"] . "-----public_key=" . base64_encode($sql_row["public_key"]) . "-----crypt1=" . $sql_row["crypt_data1"];
				$echo_buffer.= "-----crypt2=" . $sql_row["crypt_data2"] . "-----crypt3=" . $sql_row["crypt_data3"] . "-----hash=" . $sql_row["hash"];
				$echo_buffer.= "-----attribute=" . $sql_row["attribute"] . "-----end---qhash=$qhash---endqhash";
				break;
			}

			// No match, move on to next record
			$transaction_queue_hash = NULL;
		}

		echo $echo_buffer;
	}

	// Log inbound IP activity
	if($_GET["client"] == "api")
	{
		log_ip("AP", 1);
	}
	else
	{
		log_ip("QU", 1);
	}
	
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Accept a transaction from a firewalled peer (behind a firewall with no inbound communication port open)
if($_GET["action"] == "input_transaction")
{
	$next_transaction_cycle = transaction_cycle(1);
	$current_transaction_cycle = transaction_cycle(0);

	// Can we work on the transactions in the database?
	// Not allowed 180 seconds before and 15 seconds after transaction cycle.
	if(($next_transaction_cycle - time()) > 180 && (time() - $current_transaction_cycle) > 15)
	{
		$transaction_timestamp = intval($_POST["timestamp"]);
		$transaction_public_key = $_POST["public_key"];
		$transaction_crypt1 = $_POST["crypt_data1"];
		$transaction_crypt2 = $_POST["crypt_data2"];
		$transaction_crypt3 = $_POST["crypt_data3"];
		$transaction_hash = filter_sql($_POST["hash"]);
		$transaction_attribute = filter_sql($_POST["attribute"]);
		$transaction_qhash = $_POST["qhash"];

		// If a qhash is included, use this to verify the data
		if(empty($transaction_qhash) == FALSE)
		{
			$qhash = $transaction_timestamp . $transaction_public_key . $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3 . $transaction_hash . $transaction_attribute;
			$qhash = hash('md5', $qhash);

			// Compare hashes to make sure data is intact
			if($transaction_qhash != $qhash)
			{
				write_log("Queue Hash Data MisMatch from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: $transaction_public_key", "QC");
				$hash_match = "mismatch";
				log_ip("QU", scale_trigger(100));
			}
			else
			{
				// Make sure hash is actually valid and not made up to stop other transactions
				$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);

				if($transaction_hash == $crypt_hash_check)
				{
					// Hash check good, check for duplicate transaction already in queue
					$hash_match = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_queue` WHERE `timestamp`= $transaction_timestamp AND `hash` = '$transaction_hash' LIMIT 1"));
				}
				else
				{
					// Ok, something is very wrong here...
					write_log("Crypt Field Hash Check Failed from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: $transaction_public_key", "QC");
					$hash_match = "mismatch";
					log_ip("QU", scale_trigger(100));
				}
			}
		}
		else
		{
			// A qhash is required to verify the transaction
			write_log("Queue Hash Data Empty from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: $transaction_public_key", "QC");
			$hash_match = "mismatch";
			log_ip("QU", scale_trigger(100));
		}

		$transaction_public_key = filter_sql(base64_decode($transaction_public_key));

		if(empty($hash_match) == TRUE) // Duplicate Check
		{
			// No duplicate found, continue processing
			// Check to make sure attribute is valid
			if($transaction_attribute == "T" || $transaction_attribute == "G")
			{
				// Decrypt transaction information for regular transaction data
				// and check to make sure the public key that is being sent to
				// has not been tampered with.
				$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));
				
				// Find destination public key
				$public_key_to_1 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt1));
				$public_key_to_2 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt2));
				$public_key_to = $public_key_to_1 . $public_key_to_2;

				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);

				$transaction_amount_sent_test = intval($transaction_amount_sent);

				if($transaction_amount_sent_test == $transaction_amount_sent)
				{
					// Is a valid integer, amount greater than zero?
					if($transaction_amount_sent > 0)
					{
						$valid_amount = TRUE;
					}
					else
					{
						$valid_amount = FALSE;
					}
				}
				else
				{
					// Is NOT a valid integer, fail check
					$valid_amount = FALSE;
				}

				if($transaction_attribute == "G")
				{
					if($transaction_amount_sent_test > 20)
					{
						// Filter silly generation amounts :p
						$valid_amount = FALSE;
					}
				}

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

			// Check to make sure this transaction is even valid
			if($transaction_hash == $crypt_hash_check 
				&& $inside_transaction_hash == $final_hash_compare 
				&& strlen($transaction_public_key) > 300 
				&& strlen($public_key_to) > 300 
				&& $transaction_timestamp >= $current_transaction_cycle 
				&& $transaction_timestamp < $next_transaction_cycle
				&& $valid_amount == TRUE)
			{
				// Check for 100 public key limit in the transaction queue
				$sql = "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
				$sql_result = mysqli_query($db_connect, $sql);
				$sql_num_results = mysqli_num_rows($sql_result);

				if($sql_num_results < 100)
				{						
					// Transaction hash and real hash match
					$sql = "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
					VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')";
					
					if(mysqli_query($db_connect, $sql) == TRUE)
					{
						// Give confirmation of transaction insert accept
						echo "OK";
						write_log("Accepted Inbound Transaction from IP: " . $_SERVER['REMOTE_ADDR'], "QC");
					}
				}
				else
				{
					write_log("More Than 100 Transactions Trying to Queue from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
					log_ip("QU", scale_trigger(100));
				}
			}
			else
			{
				write_log("Invalid Transaction Queue Data Discarded from IP: " . $_SERVER['REMOTE_ADDR'] . " for Public Key: " . base64_encode($transaction_public_key), "QC");
				log_ip("QU", scale_trigger(100));
			}

		} // End Duplicate & Timestamp check
		else
		{
			// Respond that the transaction is already in the queue
			echo "DUP";
		}

	} // End time allowed check

	//Direct Input Transaction get a count boost
	//to help prevent direct Transaction spamming
	if($transaction_attribute == "T")
	{
		log_ip("QU", scale_trigger(200));
	}
	else if($transaction_attribute == "G")
	{
		log_ip("QU", scale_trigger(100));
	}
	else
	{
		log_ip("QU", scale_trigger(100));
	}

	exit;
}
//***********************************************************************************
// External Flood Protection
	log_ip("QU", scale_trigger(5));
//***********************************************************************************
// First time run check
$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,0);
$last_heartbeat = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_last_heartbeat' LIMIT 1"),0,0);
$clone_id = $_GET["clone_id"];

if($loop_active == "" && $last_heartbeat == 1)
{
	// Create record to begin loop
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('queueclerk_heartbeat_active', '0')");
	// Update timestamp for starting
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'queueclerk_last_heartbeat' LIMIT 1");
}
else
{
	if(empty($clone_id) == TRUE)
	{
		// Record already exist, called while another process of this script
		// was already running.
		exit;
	}
	else
	{
		$crc32_password_hash = hash('crc32', mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'password' LIMIT 1"),0,0));

		if($clone_id == $crc32_password_hash)// Check if Process Cloning should take place
		{
			$process_clone = TRUE;
			session_write_close(); // Don't lock session, let multiple instances run
		}
		else
		{
			exit;
		}
	}
}

ini_set('user_agent', 'Timekoin Server (Queueclerk) v' . TIMEKOIN_VERSION);
ini_set('default_socket_timeout', 3); // Timeout for request in seconds

while(1) // Begin Infinite Loop
{
set_time_limit(300);
//***********************************************************************************
if($process_clone == FALSE) // No Activity Settings for Clone Process
{
	$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"),0,0);

	// Check script status
	if($loop_active == "")
	{
		// Time to exit
		exit;
	}
	else if($loop_active == 0)
	{
		// Set the working status of 1
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");
	}
	else if($loop_active == 2) // Wake from sleep
	{
		// Set the working status of 1
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");
	}
	else if($loop_active == 3) // Shutdown
	{
		mysqli_query($db_connect, "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active'");
		exit;
	}
	else
	{
		// Script called while still working
		exit;
	}
}
//***********************************************************************************
//***********************************************************************************
$next_transaction_cycle = transaction_cycle(1);
$current_transaction_cycle = transaction_cycle(0);
$treasurer_status = intval(mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'treasurer_heartbeat_active' LIMIT 1")));

// Can we work on the transactions in the database?
// Not allowed 30 seconds before and 30 seconds after transaction cycle.
if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30 && $treasurer_status == 2)
{
	// Create a hash of my own transaction queue
	$transaction_queue_hash = queue_hash();

	$db_queue_hash = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'transaction_queue_hash' LIMIT 1"),0,0);

	if($db_queue_hash !== $transaction_queue_hash)
	{
		// Store in database for proper update when peers are polling this info
		mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$transaction_queue_hash' WHERE `options`.`field_name` = 'transaction_queue_hash' LIMIT 1");
	}

	$my_server_domain = my_domain();
	$my_server_subfolder = my_subfolder();
	$my_server_port_number = my_port_number();

	if($process_clone == FALSE)
	{
		// Decrease Timeout for process cloning
		ini_set('default_socket_timeout', 1);
		
		// How many active peers do we have?
		$active_peers = mysqli_num_rows(mysqli_query($db_connect, "SELECT join_peer_list FROM `active_peer_list`"));

		// Launch Extra Process into Web Server to better poll more peers at once
		$crc32_password_hash = hash('crc32', mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'password' LIMIT 1"),0,0));

		// Scale clones to number of active peers to avoid clones ganging up on a single peer
		$scale_clones = intval($active_peers / 5);

		if($scale_clones > 5) { $scale_clones = 5; }// Set Max Limit Range

		while($scale_clones > 0)
		{
			clone_script("queueclerk.php?clone_id=$crc32_password_hash");
			$scale_clones--;
		}

		// Reset Default Socket Timeout
		ini_set('default_socket_timeout', 3);
	}

	// How does my transaction queue compare to others?
	// Ask all of my active peers
	$sql = "SELECT * FROM `active_peer_list` ORDER BY RAND() LIMIT 10";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);

	$transaction_queue_hash_match = 0;
	$transaction_queue_hash_different = 0;

	if($sql_num_results > 0)
	{
		$hash_different = array();
		
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_array($sql_result);

			$ip_address = $sql_row["IP_Address"];
			$domain = $sql_row["domain"];
			$subfolder = $sql_row["subfolder"];
			$port_number = $sql_row["port_number"];

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 32, "queueclerk.php?action=trans_hash");

			if($transaction_queue_hash === $poll_peer)
			{
				$transaction_queue_hash_match++;
			}
			else
			{
				if(strlen($poll_peer) == 32) // Ignore Peers with improper responses
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
		$hash_array = array(); // Empty Array
		$transaction_counter = 0;
		$peer_transaction_limit = 1000;
		$mismatch_error_limit = 3;
		$max_peer_sync_time = 30; // Maximum seconds to allow Peer before moving to another peer

		for ($i = 1; $i < $transaction_queue_hash_different + 1; $i++)
		{
			if($next_transaction_cycle - time() < 10)
			{
				// Transaction Cycle has almost ended, break from loop early
				break;
			}			
			
			$ip_address = $hash_different["ip_address$i"];
			$domain = $hash_different["domain$i"];
			$subfolder = $hash_different["subfolder$i"];
			$port_number = $hash_different["port_number$i"];

			$peer_clock_start = time();

			$poll_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 20000000, "queueclerk.php?action=queue");

			// Bring up first match (if any) to compare agaist our database
			$match_number = 1;
			$current_hash = find_string("---queue$match_number=", "---end$match_number", $poll_peer);

			$transaction_counter = 0;
			$mismatch_error_count = 0;

			// Load queue data from database first, then recycle it for seeking to avoid a constant DB I/O hit
			$sql2 = "SELECT * FROM `transaction_queue`";
			$sql_result2 = mysqli_query($db_connect, $sql2);
			$sql_num_results2 = mysqli_num_rows($sql_result2);
	//***********************************************************
			if($process_clone == TRUE)// Only clone process do Reverse Queue Bulk Transactions
			{
				// Check to make sure this peer is not already being polled by another clone process
				$peer_md5 = hash('md5', $ip_address . $domain . $subfolder . $port_number);
				$clone_peer_busy = mysql_result(mysqli_query($db_connect, "SELECT block FROM `balance_index` WHERE `block` = 5 AND `public_key_hash` = '$peer_md5' LIMIT 1"),0,0);

				if(empty($clone_peer_busy) == TRUE)
				{
					// Check if Peer supports Reverse Queue Processing
					// The two peers must be connected to each other, won't send bulk data to unknown peers
					$reverse_queue_peer = poll_peer($ip_address, $domain, $subfolder, $port_number, 2, "queueclerk.php?action=reverse_queue&domain=$my_server_domain&subfolder=$my_server_subfolder&port=$my_server_port_number");
				}
				else
				{
					// Bypass Reverse Queue Polling, Go to Next Peer
					$reverse_queue_peer = NULL;
					$current_hash = NULL;
				}

				if($reverse_queue_peer == "OK") // Check to make sure this is an active/connected peer
				{
					// Store that this peer is being polled so other clone process don't poll the same peer at the same time
					mysqli_query($db_connect, "INSERT INTO `balance_index` (`block`, `public_key_hash`, `balance`) VALUES ('5', '$peer_md5', '0')");
					
					$reverse_queue_data = NULL;
					$reverse_queue_data_counter = 1;

					mysqli_data_seek($sql_result2, 0); // Reset pointer back to beginning of data

					if($sql_num_results2 > 0)
					{
						for ($i2 = 0; $i2 < $sql_num_results2; $i2++)
						{
							$sql_row2 = mysqli_fetch_array($sql_result2);

							if($sql_row2["attribute"] == "G" || $sql_row2["attribute"] == "T")
							{
								$reverse_match_number = 1;
								$reverse_current_hash = find_string("---queue$reverse_match_number=", "---end$reverse_match_number", $poll_peer);

								$queue_hash_test = $sql_row2["timestamp"] . $sql_row2["public_key"] . $sql_row2["crypt_data1"] . 
								$sql_row2["crypt_data2"] . $sql_row2["crypt_data3"] . $sql_row2["hash"] . $sql_row2["attribute"];

								while(empty($reverse_current_hash) == FALSE)
								{
									if(hash('md5', $queue_hash_test) == $reverse_current_hash)
									{
										// This Peer Already Has This Transaction in Queue
										$hash_match = TRUE;
										break;
									}
									else
									{
										$hash_match = NULL;
									}

									$reverse_match_number++;				
									$reverse_current_hash = find_string("---queue$reverse_match_number=", "---end$reverse_match_number", $poll_peer);					
								}

								if(empty($hash_match) == TRUE)
								{
									// Build Data String to Send to Peer
									$reverse_queue_data.= "---timestamp$reverse_queue_data_counter=" . $sql_row2["timestamp"] . "---public_key$reverse_queue_data_counter=" . base64_encode($sql_row2["public_key"]) . 
									"---crypt1_data$reverse_queue_data_counter=" . $sql_row2["crypt_data1"] . "---crypt2_data$reverse_queue_data_counter=" . $sql_row2["crypt_data2"] .
									"---crypt3_data$reverse_queue_data_counter=" . $sql_row2["crypt_data3"] . "---hash$reverse_queue_data_counter=" . $sql_row2["hash"] . 
									"---attribute$reverse_queue_data_counter=" . $sql_row2["attribute"] . "---end$reverse_queue_data_counter";
									$reverse_queue_data_counter++;
								}

							}// Only build reverse queue data for Transactions & Generation

							// No match, move on to next record
							$queue_hash_test = NULL;

						} // Finished Scanning Transactions

						$qhash = hash('md5', $reverse_queue_data);

						// Create map with request parameters
						$params = array ('reverse_queue_data' => $reverse_queue_data, 
						'qhash' => $qhash);
						 
						// Build Http query using params
						$query = http_build_query($params);
						 
						// Create Http context details
						$contextData = array ('method' => 'POST',
						'header' => "Connection: close\r\n"."Content-Length: ".strlen($query)."\r\n",
						'content'=> $query);
						 
						// Create context resource for our request
						$context = stream_context_create(array('http' => $contextData));
						
						if(empty($reverse_queue_data) == FALSE)
						{
							// Send Bulk Transaction Queue Data
							poll_peer($ip_address, $domain, $subfolder, $port_number, 2, "queueclerk.php?action=reverse_queue&domain=$my_server_domain&subfolder=$my_server_subfolder&port=$my_server_port_number", $context);
							write_log("Sent [" . ($reverse_queue_data_counter - 1) . "] Bulk Queue Transactions to Peer $ip_address$domain:$port_number/$subfolder","QC");
						}

					} // More than 0 results returned from Queue

					// Finished Polling this Peer, Remove from Peer Polling Check List
					mysqli_query($db_connect, "DELETE FROM `balance_index` WHERE `block` = 5 AND `public_key_hash` = '$peer_md5'");
				
				} // Connected Peer Check for Reverse Queue Bulk Sending

			} // Clone Process Valid Check

	//***********************************************************
			while(empty($current_hash) == FALSE)
			{
				if($next_transaction_cycle - time() < 10)
				{
					// Transaction Cycle has almost ended, break from loop early
					break;
				}

				if(time() - $peer_clock_start > $max_peer_sync_time)
				{
					// Peer is too slow, break away queue collection
					break;
				}

				// Count transactions coming from this peer
				$transaction_counter++;

				if($transaction_counter > $peer_transaction_limit)
				{
					write_log("$peer_transaction_limit Transaction limit reached from Peer: $ip_address:$domain:$port_number/$subfolder", "QC");
					// Add failure points to the peer in case further issues
					modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 3);
					break;
				}

				if($mismatch_error_count > $mismatch_error_limit)
				{
					write_log("$mismatch_error_limit Transaction Error limit reached from Peer: $ip_address:$domain:$port_number/$subfolder", "QC");
					// Add failure points to the peer in case further issues
					modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 5);
					break;
				}

				// New Queue System Check
				$queue_hash_test = NULL;
				$hash_match = NULL;					
				
				mysqli_data_seek($sql_result2, 0); // Reset pointer back to beginning of data

				if($sql_num_results2 > 0)
				{
					for ($i2 = 0; $i2 < $sql_num_results2; $i2++)
					{
						$sql_row2 = mysqli_fetch_array($sql_result2);

						$queue_hash_test = $sql_row2["timestamp"] . $sql_row2["public_key"] . $sql_row2["crypt_data1"] . 
						$sql_row2["crypt_data2"] . $sql_row2["crypt_data3"] . $sql_row2["hash"] . $sql_row2["attribute"];		

						if(hash('md5', $queue_hash_test) == $current_hash)
						{
							// This Transaction Already Exist in the Queue
							$hash_match = TRUE;
							break;
						}

						// No match, move on to next record
						$queue_hash_test = NULL;
					}

					// No match found, empty string
					$hash_match = NULL;
				}

				if(empty($hash_match) == TRUE)
				{
					// This peer has a different transaction, ask for the full details of it
					$poll_hash = filter_sql(poll_peer($ip_address, $domain, $subfolder, $port_number, 20000, "queueclerk.php?action=transaction&number=$current_hash"));

					$transaction_timestamp = intval(find_string("-----timestamp=", "-----public_key", $poll_hash));
					$transaction_public_key = find_string("-----public_key=", "-----crypt1", $poll_hash);
					$transaction_crypt1 = find_string("-----crypt1=", "-----crypt2", $poll_hash);
					$transaction_crypt2 = find_string("-----crypt2=", "-----crypt3", $poll_hash);
					$transaction_crypt3 = find_string("-----crypt3=", "-----hash", $poll_hash);
					$transaction_hash = find_string("-----hash=", "-----attribute", $poll_hash);
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
							write_log("Queue Hash Data MisMatch for Public Key: " . $transaction_public_key, "QC");
							$transaction_attribute = "mismatch";
							$mismatch_error_count++;

							// Add failure points to the peer in case further issues
							modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 3);
						}
						else
						{
							// Make sure hash is actually valid and not made up to stop other transactions
							$crypt_hash_check = hash('sha256', $transaction_crypt1 . $transaction_crypt2 . $transaction_crypt3);

							if($crypt_hash_check != $transaction_hash)
							{
								// Ok, something is wrong here...
								write_log("Crypt Field Hash Check Failed for Public Key: " . $transaction_public_key, "QC");
								$transaction_attribute = "mismatch";
								$mismatch_error_count++;

								// Add failure points to the peer in case further issues
								modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 6);
							}
							else
							{
								$last_hash_match = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_queue` WHERE `timestamp`= $transaction_timestamp AND `hash` = '$transaction_hash' LIMIT 1"),0,0);

								if(empty($last_hash_match) == FALSE)
								{
									// Duplicate Already in the Transaction Queue
									$transaction_attribute = "mismatch";
								}
							}
						}
					}
					else
					{
						// Qhash is required to match hash
						write_log("Queue Hash Data MisMatch for Public Key: " . $transaction_public_key, "QC");
						$transaction_attribute = "mismatch";
						$mismatch_error_count++;

						// Add failure points to the peer in case further issues
						modify_peer_grade($ip_address, $domain, $subfolder, $port_number, 2);
					}

					$transaction_public_key = filter_sql(base64_decode($transaction_public_key));

					if($transaction_attribute == "R")
					{
						// Check to make sure this public key isn't forged or made up to win the list
						$inside_transaction_hash = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt1));
						
						$final_hash_compare = $transaction_crypt2;
						$crypt_hash_check = $transaction_hash;
						$valid_amount = TRUE; // No amount, but needs this to pass amount test
						$public_key_to = $transaction_public_key; // None is used, but needs this to pass the key length test
					}
					else
					{
						if($transaction_attribute == "T" || $transaction_attribute == "G")
						{
							// Decrypt transaction information for regular transaction data
							// and check to make sure the public key that is being sent to
							// has not been tampered with.
							$transaction_info = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt3));

							// Find destination public key
							$public_key_to_1 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt1));
							$public_key_to_2 = tk_decrypt($transaction_public_key, base64_decode($transaction_crypt2));
							$public_key_to = $public_key_to_1 . $public_key_to_2;

							$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);

							$transaction_amount_sent_test = intval($transaction_amount_sent);

							if($transaction_amount_sent_test == $transaction_amount_sent)
							{
								// Is a valid integer, amount greater than zero?
								if($transaction_amount_sent > 0)
								{
									$valid_amount = TRUE;
								}
								else
								{
									$valid_amount = FALSE;
								}
							}
							else
							{
								// Is NOT a valid integer, fail check
								$valid_amount = FALSE;
							}

							if($transaction_attribute == "G")
							{
								if($transaction_amount_sent_test > 20)
								{
									// Filter silly generation amounts :p
									$valid_amount = FALSE;
								}
							}

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
						else
						{
							// Attribute does not match anything valid
							$valid_amount = FALSE;
						}

						$final_hash_compare = hash('sha256', $transaction_crypt1 . $transaction_crypt2);
					}

					// Check to make sure this transaction is even valid (hash check, length check, & timestamp)
					if($transaction_hash == $crypt_hash_check 
						&& $inside_transaction_hash == $final_hash_compare 
						&& strlen($transaction_public_key) > 300 
						&& strlen($public_key_to) > 300 
						&& $transaction_timestamp >= $current_transaction_cycle 
						&& $transaction_timestamp < $next_transaction_cycle 
						&& $valid_amount == TRUE)
					{
						// Check for 100 public key limit in the transaction queue
						$sql = "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$transaction_public_key'";
						$sql_result = mysqli_query($db_connect, $sql);
						$sql_num_results = mysqli_num_rows($sql_result);

						if($sql_num_results < 100)
						{						
							// Transaction hash and real hash match.
							mysqli_query($db_connect, "INSERT INTO `transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`)
							VALUES ('$transaction_timestamp', '$transaction_public_key', '$transaction_crypt1', '$transaction_crypt2' , '$transaction_crypt3', '$transaction_hash' , '$transaction_attribute')");
						}
						else
						{
							write_log("More Than 100 Transactions Trying to Queue from Key: " . base64_encode($transaction_public_key), "QC");
						}
					}
				} // End Empty Hash Check

				$match_number++;				
				$current_hash = find_string("---queue$match_number=", "---end$match_number", $poll_peer);

			} // End While Loop

		} // End For Loop

		// Build queue hash after completion
		$transaction_queue_hash = queue_hash();

		// Store in database for quick reference from database
		mysqli_query($db_connect, "UPDATE `options` SET `field_data` = '$transaction_queue_hash' WHERE `options`.`field_name` = 'transaction_queue_hash' LIMIT 1");

	} // End Compare Tallies
} // If/then Check for valid times

//***********************************************************************************
//***********************************************************************************
if($process_clone == FALSE)
{
	// Clear any duplicate transactions
	$sql = "SELECT public_key, hash FROM `transaction_queue`";
	$sql_result = mysqli_query($db_connect, $sql);
	$sql_num_results = mysqli_num_rows($sql_result);
	
	for ($i = 0; $i < $sql_num_results; $i++)
	{
		$sql_row = mysqli_fetch_array($sql_result);
		$hash = $sql_row["hash"];
		$public_key = $sql_row["public_key"];

		// Is there more than one of this hash?
		$duplicate_hash = mysql_result(mysqli_query($db_connect, "SELECT timestamp FROM `transaction_queue` WHERE `public_key` = '$public_key' AND `hash` = '$hash' LIMIT 2"),1,0);

		if($duplicate_hash != "")
		{
			// Remove duplicate
			mysqli_query($db_connect, "DELETE QUICK FROM `transaction_queue` WHERE `public_key` = '$public_key' AND `hash` = '$hash' LIMIT 1");
		}
	}

	$loop_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'queueclerk_heartbeat_active' LIMIT 1"));

	// Check script status
	if($loop_active == 3)
	{
		// Time to exit
		mysqli_query($db_connect, "DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active'");
		exit;
	}

	// Script finished, set standby status to 2
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'queueclerk_heartbeat_active' LIMIT 1");

	// Record when this script finished
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'queueclerk_last_heartbeat' LIMIT 1");
}
else
{
	exit; // Exit Clone Process
}
//***********************************************************************************
if(($next_transaction_cycle - time()) > 30 && (time() - $current_transaction_cycle) > 30)
{
	sleep(1);
}
else
{
	sleep(10);
}

} // End Infinite Loop
//***********************************************************************************
?>
