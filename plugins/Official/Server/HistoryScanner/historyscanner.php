<?PHP
//
// This is the long name of your plugin.
// PLUGIN_NAME=History Scanner---END
//
// This is the tab text on the menu bar.
// PLUGIN_TAB=History Scanner---END
//
//
include '../templates.php';// Path to files already used by Timekoin
include '../function.php';// Path to files already used by Timekoin
include '../configuration.php';// Path to files already used by Timekoin

set_time_limit(999); // How many seconds to wait until timeout
session_name("timekoin"); // Continue Session Name, Default: [timekoin]
session_start(); // Continue Session or Start a New Session

// Make DB Connection
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

if($_SESSION["valid_login"] == TRUE) // Make Sure Login is Still Valid
{
	// What is the name of the section?
	$section_string = "History Scanner";

	// Text Bar Txt
	$text_bar = 'WARNING: DO NOT RUN THIS ON A LIVE SYSTEM WITH LOW RESOURCES!!';

	$body_string = '<FORM ACTION="historyscanner.php?action=run_check" METHOD="post">
	Limit Start: <input type="text" size="10" name="limit1" value="0" /><br>
	Limit End: <input type="text" size="10" name="limit2" value="2" /><br>
	<input type="submit" name="Submit" value="Run Scanner" /></FORM>';

	if($_GET["action"] == "run_check")
	{
		$start_scan_time = time();

		// Group all public keys every used
		$sql = "SELECT * FROM `transaction_history` WHERE `attribute` = 'T' GROUP BY `public_key_from` LIMIT " . $_POST["limit1"] . ", " . $_POST["limit2"];
		$sql_result = mysql_query($sql);
		$sql_num_results = mysql_num_rows($sql_result);

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysql_fetch_array($sql_result);			
			
			$body_string.= '<hr><strong>Scanning Public Key:</strong><br><p style="word-wrap:break-word; font-size:12px;">' . 
				base64_encode($sql_row["public_key_from"]) . '</p>';

			$body_string.= '<br><strong>Ghost Self Transaction Test:</strong> ';

			$sql2 = "SELECT * FROM `transaction_history` WHERE `public_key_from` = '" . $sql_row["public_key_from"] . "'";
			$sql_result2 = mysql_query($sql2);
			$sql_num_results2 = mysql_num_rows($sql_result2);

			$body_string.= 'Scanning [' . $sql_num_results2 . '] Transactions<br>';

			for ($i2 = 0; $i2 < $sql_num_results2; $i2++)
			{
				$sql_row2 = mysql_fetch_array($sql_result2);

				$body_string.= '#' . ($i2 + 1) . ' ';

				if($sql_row2["public_key_from"] == $sql_row2["public_key_to"] && $sql_row2["attribute"] == "T")
				{
					// Transaction from self to self, illegal
					$body_string.= '<br><strong>Illegal Self Transaction Found for Public Key - Hash: ' . $sql_row2["hash"] . '</strong><br>';
				}
			}
		}

		$text_bar.= '<br><strong><font color="blue">Scan Time: ' . (time() - $start_scan_time) . ' seconds</font></strong>';
	}

	// Quick Info Bar on Right
	$quick_info = 'Scan the transaction history for invalid or illegal transactions of any type.';
	// Does the screen need to refresh every X seconds? 0 = Disable	
	$update = 0; 

	home_screen($section_string, $text_bar, $body_string, $quick_info , $update, TRUE);
	// The last variable TRUE is important to have Timekoin re-adjust pathing to make sure
	// menus and screens come up properly.

	exit; // All done processing
}

?>
