<?PHP
include 'configuration.php';
include 'function.php';
//***********************************************************************************
//***********************************************************************************
if(BALANCE_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
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

log_ip("BA", 100);

while(1) // Begin Infinite Loop
{
set_time_limit(300);	
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,0);

// Check script status
if($loop_active === FALSE)
{
	// Time to exit
	exit;
}
else if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");
}
else if($loop_active == 2) // Wake from sleep
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");
}
else if($loop_active == 3) // Shutdown
{
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active'");
	exit;
}
else
{
	// Script called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$current_transaction_cycle = transaction_cycle(0);
$next_transaction_cycle = transaction_cycle(1);

$foundation_status = intval(mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,0));

// Can we work on the key balances in the database?
// Not allowed 120 seconds before and 40 seconds after transaction cycle.
// Don't run if the Foundation Manager is busy (idle = 2)
if(($next_transaction_cycle - time()) > 120 && (time() - $current_transaction_cycle) > 40 && $foundation_status == 2)
{
	$current_transaction_block = transaction_cycle(0, TRUE);
	$current_foundation_block = foundation_cycle(0, TRUE);

	// Check to make sure enough lead time exist in advance to building
	// another balance index. (60 cycles) or 5 hours
	if($current_transaction_block - ($current_foundation_block * 500) > 60)
	{
		// -1 Foundation Blocks (Standard)
		$cache_block = foundation_cycle(-1, TRUE);
	}
	else
	{
		// -2 Foundation Blocks - Buffers 5 hours after the newest foundation block
		$cache_block = foundation_cycle(-2, TRUE);
	}	
	
	// Build self balance index first
	$public_key_hash = hash('md5', my_public_key());
	$balance_index = mysql_result(mysql_query("SELECT block FROM `balance_index` WHERE `public_key_hash` = '$public_key_hash' AND `block` = $cache_block LIMIT 1"),0,0);

	if($balance_index === FALSE)
	{
		// Create self first :)
		check_crypt_balance(my_public_key());
	}	

	// Build Balance Index for Transactions about to be Processed in the Queue
	$sql = "SELECT public_key FROM `transaction_queue` WHERE `attribute` = 'T'";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$queue_index_created = FALSE;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			if(($next_transaction_cycle - time()) > 120)// Keep looping until time runs out, then stop early
			{
				$sql_row = mysql_fetch_array($sql_result);
				$public_key_hash = hash('md5', $sql_row["public_key"]);

				// Run a balance index if one does not already exist
				$balance_index = mysql_result(mysql_query("SELECT block FROM `balance_index` WHERE `public_key_hash` = '$public_key_hash' AND `block` = $cache_block LIMIT 1"),0,0);

				if($balance_index === FALSE)
				{
					// No index balance, go ahead and create one
					write_log("Updating Balance Index From Transaction Queue", "BA");
					check_crypt_balance($sql_row["public_key"]);
					$queue_index_created = TRUE;
				}
			}
			else
			{
				break; // Break from loop early
			}
		}
	}

	if($queue_index_created == FALSE) // Only do one or the other at a time
	{
		// 1000 Transaction Cycles Back in time to index
		$time_back = time() - 300000;

		$public_key_from = mysql_result(mysql_query("SELECT public_key_to FROM `transaction_history` WHERE `timestamp` > $time_back AND `attribute` = 'T' GROUP BY `public_key_to` ORDER BY RAND() LIMIT 1"),0,0);
		$public_key_hash = hash('md5', $public_key_from);

		// Run a balance index if one does not already exist
		$balance_index = mysql_result(mysql_query("SELECT block FROM `balance_index` WHERE `public_key_hash` = '$public_key_hash' AND `block` = $cache_block LIMIT 1"),0,0);

		if($balance_index === FALSE)
		{
			// No index balance, go ahead and create one
			write_log("Updating Balance Index From Transaction History", "BA");
			check_crypt_balance($public_key_from);
		}
	}
}
//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,0);

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("DELETE FROM `main_loop_status` WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active'");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'balance_last_heartbeat' LIMIT 1");

//***********************************************************************************
sleep(rand(10,11));
} // End Infinite Loop
?>
