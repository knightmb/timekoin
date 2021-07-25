<?PHP
// This file is an example plugin that uses Timekoin Client in a service
// type fashion.
//
// Timekoin will parse the file looking for these text strings
// to save into the database when installing. You need only
// leave them in the comment area.
//
// This is the long name of your plugin.
// PLUGIN_NAME=Example Service---END
//
// This is the tab text on the menu bar.
// PLUGIN_TAB=Example---END
//
// Start with Timekoin? Service Must Have Name or
// it is ignored on Startup.
// PLUGIN_SERVICE=Example Service---END
//
include 'templates.php';// Path to files already used by Timekoin
include 'function.php';// Path to files already used by Timekoin
include 'configuration.php';// Path to files already used by Timekoin

//***********************************************************************************
if(function_exists('mysql_result') == FALSE)
{
	function mysql_result($result, $number = 0, $field = 0)
	{
		$sql_num_results = mysqli_num_rows($result);

		if($sql_num_results <= $number)
		{
			return NULL;
		}
		else
		{
			mysqli_data_seek($result, $number);
			$row = mysqli_fetch_array($result);
			return $row[$field];
		}
	}
}
//***********************************************************************************

// Make DB Connection
$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

// Avoid stacking this many times
$already_active = mysql_result(mysqli_query($db_connect, "SELECT * FROM `data_cache` WHERE `field_name` = 'TKCS_example_service.php' LIMIT 1"),0,"field_data");

if($already_active == "")
{
	// Creating Status State - Timekoin Looks for the filename
	mysqli_query($db_connect, "INSERT INTO `data_cache` (`field_name` ,`field_data`)VALUES ('TKCS_example_service.php', '1')"); // Active
}
else
{
	// Being called again while already running, just exit
	exit;
}

while(1) // Begin Infinite Loop :)
{
	// Are we to remain active?
	$tkclient_active = mysql_result(mysqli_query($db_connect, "SELECT * FROM `data_cache` WHERE `field_name` = 'TKCS_example_service.php' LIMIT 1"),0,"field_data");

	if($tkclient_active == "")
	{
		// Shutdown
		exit;
	}

	// Write an entry to the log every X amount of seconds
	write_log("Example Service Plugin Did Something...", "GU");
	sleep(10); // Sleep X amount of seconds
}

?>
