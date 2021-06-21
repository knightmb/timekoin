<?PHP
// This file is an example plugin that uses Timekoin in a service
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

// CLI Mode uses this path
include 'templates.php';// Path to files already used by Timekoin
include 'function.php';// Path to files already used by Timekoin
include 'configuration.php';// Path to files already used by Timekoin

// Non-CLI Mode uses this path
include '../templates.php';// Path to files already used by Timekoin
include '../function.php';// Path to files already used by Timekoin
include '../configuration.php';// Path to files already used by Timekoin

// Make DB Connection
$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

// Avoid stacking this many times
$already_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'example_service.php' LIMIT 1"),0,0);

if($already_active === FALSE)
{
	// Creating Status State - Timekoin Looks for the filename
	mysqli_query($db_connect, "INSERT INTO `main_loop_status` (`field_name` ,`field_data`)VALUES ('example_service.php', '0')"); // Offline
}
else
{
	// Being called again while already running, just exit
	exit;
}

while(1) // Begin Infinite Loop :)
{
	// Are we to remain active?
	$timekoin_active = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `main_loop_status` WHERE `field_name` = 'main_heartbeat_active' LIMIT 1"),0,0);

	if($timekoin_active == FALSE)
	{
		// Begin Shutdown
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '3' WHERE `main_loop_status`.`field_name` = 'example_service.php' LIMIT 1");
		sleep(5); // Sleep X amount of seconds

		// User has shutdown system
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '0' WHERE `main_loop_status`.`field_name` = 'example_service.php' LIMIT 1");
		exit;
	}
	else
	{
		// Working State - Do Something
		mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'example_service.php' LIMIT 1");
	}

	// Write an entry to the log every X amount of seconds
	write_log("Example Service Plugin Did Something...", "MA");
	sleep(5); // Sleep X amount of seconds

	// Idle State
	mysqli_query($db_connect, "UPDATE `main_loop_status` SET `field_data` = '2' WHERE `main_loop_status`.`field_name` = 'example_service.php' LIMIT 1");
	sleep(10); // Sleep X amount of seconds
}

?>
