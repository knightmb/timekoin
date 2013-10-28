<?PHP
// This file is an example plugin that uses the current Timekoin Theme,
// Menu, and Interface. This plugin has full access to all the existing
// functions, database, and templates of Timekoin.
//
// Timekoin will parse the file looking for these text strings
// to save into the database when installing. You need only
// leave them in the comment area.
//
// This is the long name of your plugin.
// PLUGIN_NAME=Example Plugin---END
//
// This is the tab text on the menu bar.
// PLUGIN_TAB=Example---END
//
//
include '../templates.php';// Path to files already used by Timekoin
include '../function.php';// Path to files already used by Timekoin
include '../configuration.php';// Path to files already used by Timekoin

set_time_limit(99); // How many seconds to wait until timeout
session_name("timekoin"); // Continue Session Name, Default: [timekoin]
session_start(); // Continue Session or Start a New Session

// Make DB Connection
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

if($_SESSION["valid_login"] == TRUE) // Make Sure Login is Still Valid
{
	// What is the name of the section?
	$section_string = "My Example Plugin";

	// Text Bar Txt
	$text_bar = 'Text Bar Data Goes Here';
	// Main Body Text
	$body_string = 'Data for the Body Goes Here';
	// Quick Info Bar on Right
	$quick_info = 'Quick Info Data Goes Here';
	// Does the screen need to refresh every X seconds? 0 = Disable	
	$update = 0; 

	home_screen($section_string, $text_bar, $body_string, $quick_info , $update, TRUE);
	// The last variable TRUE is important to have Timekoin re-adjust pathing to make sure
	// menus and screens come up properly.

	exit; // All done processing
}

?>
