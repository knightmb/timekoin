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

while(1) // Begin Infinite Loop
{
set_time_limit(300);	
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,"field_data");

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
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");
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

$foundation_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data"));

// Can we work on the key balances in the database?
// Not allowed 120 seconds before and 40 seconds after transaction cycle.
if(($next_transaction_cycle - time()) > 120 && (time() - $current_transaction_cycle) > 40 && $foundation_active != 1)
{
	// Build Balance Index for Transactions about to be Processed in the Queue
	$sql = "SELECT public_key FROM `transaction_queue`";
	$sql_result = mysql_query($sql);
	$sql_num_results = mysql_num_rows($sql_result);
	$created = FALSE;

	if($sql_num_results > 0)
	{
		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);

			$public_key_from = hash('md5', $sql_row["public_key"]);

			// Run a balance index if one does not already exist
			$balance_index = mysql_result(mysql_query("SELECT public_key_hash FROM `balance_index` WHERE `public_key_hash` = '$public_key_from' AND `block` = '" . foundation_cycle(-1, TRUE) . "' LIMIT 1"),0,0);

			if(empty($balance_index) == TRUE)
			{
				// No index balance, go ahead and create one
				write_log("Updating Balance Index From Transaction Queue", "BA");
				check_crypt_balance($sql_row["public_key"]);
				$created = TRUE;
				break;
			}		
		}
	}

	if($created == FALSE) // Only do one or the other at a time
	{
		// 2000 Transaction Cycles Back in time to index
		$time_2000 = time() - 600000;

		$sql = "SELECT public_key_to FROM `transaction_history` WHERE `timestamp` > $time_2000 GROUP BY `public_key_to` ORDER BY RAND() LIMIT 1";
		$sql_result = mysql_query($sql);
		$sql_row = mysql_fetch_array($sql_result);
			
		$public_key_hash = hash('md5', $sql_row["public_key_to"]);

		// Run a balance index if one does not already exist
		$balance_index = mysql_result(mysql_query("SELECT public_key_hash FROM `balance_index` WHERE `public_key_hash` = '$public_key_from' AND `block` = '" . foundation_cycle(-1, TRUE) . "' LIMIT 1"),0,0);

		if(empty($balance_index) == TRUE)
		{
			// No index balance, go ahead and create one
			write_log("Updating Balance Index From Transaction History", "BA");
			check_crypt_balance($sql_row["public_key_to"]);
		}
	}
}
//***********************************************************************************
//***********************************************************************************
$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,"field_data");

// Check script status
if($loop_active == 3)
{
	// Time to exit
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");
	exit;
}

// Script finished, set standby status to 2
mysql_query("UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = '" . time() . "' WHERE `main_loop_status`.`field_name` = 'balance_last_heartbeat' LIMIT 1");

//***********************************************************************************
sleep(10);
} // End Infinite Loop
?>
