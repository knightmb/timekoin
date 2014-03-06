<?PHP
//
// This is the long name of your plugin.
// PLUGIN_NAME=Transaction Foundation Data---END
//
// This is the tab text on the menu bar.
// PLUGIN_TAB=Trans Data---END
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
	$section_string = "Transaction Foundation Data Output";

	$foundation_no = $_POST["foundation_no"];

	if($foundation_no != "")
	{
		$foundation_no = intval($foundation_no);
		$current_generation_block = transaction_cycle(0, TRUE);

		// Start the process to rebuild the transaction foundation
		// but walk the history of that range first to check for errors.
		$foundation_time_start = $foundation_no * 500;
		$foundation_time_end = ($foundation_no * 500) + 500;

		$do_history_walk = walkhistory($foundation_time_start, $foundation_time_end);

		if($do_history_walk == 0)
		{
			// History walk checks out, start building the transaction foundation hash
			// out of every piece of data in the database
			$time1 = transaction_cycle(0 - $current_generation_block + $foundation_time_start);
			$time2 = transaction_cycle(0 - $current_generation_block + $foundation_time_end);

			$sql = "SELECT timestamp, public_key_from, public_key_to, hash, attribute FROM `transaction_history` WHERE `timestamp` >= $time1 AND `timestamp` <= $time2 ORDER BY `timestamp`, `hash` ASC";
			$sql_result2 = mysql_query($sql);
			$sql_num_results2 = mysql_num_rows($sql_result2);

			$hash = $sql_num_results2;

			for ($f = 0; $f < $sql_num_results2; $f++)
			{
				$sql_row2 = mysql_fetch_array($sql_result2);
				$hash .= $sql_row2["timestamp"] . $sql_row2["public_key_from"] . $sql_row2["public_key_to"] . $sql_row2["hash"] . $sql_row2["attribute"];
			}	
	
			$foundation_db = $hash;

			$symbols = array("\n");
			$foundation_db = str_replace($symbols, "&#92;n", $foundation_db);

			$symbols = array("\r");
			$foundation_db = str_replace($symbols, "&#92;r", $foundation_db);

			$hash = hash('sha256', $hash);
		}
		else
		{
			// Text Bar Txt
			$text_bar = "Transaction History Walk FAILED.<br>Examine Transaction Cycle #$do_history_walk";
		}
	}

	// Main Body Text
	$body_string = '<FORM ACTION="foundationdata.php?action=go" METHOD="post">
	Choose Foundation#: <input type="text" size="5" name="foundation_no" value="' . $foundation_no . '" /><br><br>
	<input type="submit" name="Submit" value="Output Transaction Foundation Data" /></FORM><hr>
	<p style="word-wrap:break-word; font-size:12px;"><strong>SHA256 From Data:</strong><br>' . $hash . '</p><hr>
	<p style="word-wrap:break-word; font-size:12px;"><strong>Transaction Foundation Data:</strong><br>' . $foundation_db . '</p>';

	// Quick Info Bar on Right
	$quick_info = 'Manually Output Transaction Foundation Data.';
	// Does the screen need to refresh every X seconds? 0 = Disable	
	$update = 0; 

	home_screen($section_string, $text_bar, $body_string, $quick_info , $update, TRUE);
	// The last variable TRUE is important to have Timekoin re-adjust pathing to make sure
	// menus and screens come up properly.

	exit; // All done processing
}

?>
