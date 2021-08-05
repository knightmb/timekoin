<?PHP
//
// This is the long name of your plugin.
// PLUGIN_NAME=Transaction Explorer---END
//
// This is the tab text on the menu bar.
// PLUGIN_TAB=TX Explore---END
//
//
include '../templates.php';// Path to files already used by Timekoin
include '../function.php';// Path to files already used by Timekoin
include '../configuration.php';// Path to files already used by Timekoin

set_time_limit(999); // How many seconds to wait until timeout
session_name("timekoin"); // Continue Session Name, Default: [timekoin]
session_start(); // Continue Session or Start a New Session

// Make DB Connection
$db_connect = mysqli_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD,MYSQL_DATABASE);

if($_SESSION["valid_login"] == TRUE) // Make Sure Login is Still Valid
{
	// What is the name of the section?
	$section_string = "Public Key Transaction History Explorer";

	// Text Bar Message
	$text_bar = 'Enter Public Key to Process';

	$public_key = $_POST["public_key"];	

	$body_string = '<FORM ACTION="transactionexplorer.php?action=run_history" METHOD="post">
	<strong>Public Key:<br></strong> <textarea name="public_key" rows="5" cols="90">' . $public_key . '</textarea><br>
	<input type="submit" name="Submit" value="Run History" /></FORM>';

	if($_GET["action"] == "run_history")
	{
		$user_timezone = mysql_result(mysqli_query($db_connect, "SELECT field_data FROM `options` WHERE `field_name` = 'default_timezone' LIMIT 1"),0,0);
		$start_scan_time = time();
		$public_key = $_POST["public_key"];

		$body_string.= '<br><strong>Decoded Key:</strong><p style="word-wrap:break-word; font-size:12px;">' . base64_decode($public_key) . "<p>";

		$public_key = base64_decode($public_key);

		set_decrypt_mode(); // Figure out which decrypt method can be best used

		//Initialize objects for Internal RSA decrypt
		if($GLOBALS['decrypt_mode'] == 2)
		{
			require_once('RSA.php');
			$rsa = new Crypt_RSA();
			$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		}

		$sql = "SELECT timestamp, public_key_from, public_key_to, crypt_data3, attribute FROM `transaction_history` WHERE `public_key_from` = '$public_key' OR `public_key_to` = '$public_key' ";

		$sql_result = mysqli_query($db_connect, $sql);
		$sql_num_results = mysqli_num_rows($sql_result);
		$transaction_info;
		$gen_results_string;
		$gen_currency_counter = 0;
		
		$trans_send_to_results_string;
		$trans_send_to_results_counter = 0;
		$trans_send_to_results_total = 0;

		$trans_send_from_results_string = '<hr>';
		$trans_send_from_results_counter = 0;
		$trans_send_from_results_total = 0;

		$trans_generate_results_counter = 0;

		for ($i = 0; $i < $sql_num_results; $i++)
		{
			$sql_row = mysqli_fetch_row($sql_result);

			$timestamp = $sql_row[0];
			$public_key_from = $sql_row[1];
			$public_key_to = $sql_row[2];
			$crypt3 = $sql_row[3];
			$attribute = $sql_row[4];

			if($attribute == "G" && $public_key_from == $public_key_to) // Everything generated by this public key
			{
				// Currency Generation
				// Decrypt transaction information
				if($GLOBALS['decrypt_mode'] == 2)
				{
					$rsa->loadKey($public_key_from);
					$transaction_info = $rsa->decrypt(base64_decode($crypt3));
				}
				else
				{
					$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
				} 

				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
				$gen_results_string.= 'Currency Generated [<font color="green">' . $transaction_amount_sent . '</font>] ' . unix_timestamp_to_human($timestamp, $user_timezone) . '<br>';
				$gen_currency_counter+= $transaction_amount_sent;
				$trans_generate_results_counter++;
			}

			if($attribute == "T" && $public_key_to == $public_key) // Everything given to this public key
			{
				// Decrypt transaction information
				if($GLOBALS['decrypt_mode'] == 2)
				{
					$rsa->loadKey($public_key_from);
					$transaction_info = $rsa->decrypt(base64_decode($crypt3));
				}
				else
				{
					$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
				}
		
				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
				$trans_send_to_results_string.= 'Received [<font color="green"><strong>' . $transaction_amount_sent . '</strong></font>] From Public Key ' . unix_timestamp_to_human($timestamp, $user_timezone) . '<p style="word-wrap:break-word; font-size:10px;">' . base64_encode($public_key_from) . '</p><hr>';
				$trans_send_to_results_counter+= $transaction_amount_sent;
				$trans_send_from_results_total++;
			}

			if($attribute == "T" && $public_key_from == $public_key) // Everything spent from this public key
			{
				// Decrypt transaction information
				$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3));

				if($GLOBALS['decrypt_mode'] == 2)
				{
					$rsa->loadKey($public_key_from);
					$transaction_info = $rsa->decrypt(base64_decode($crypt3));
				}
				else
				{
					$transaction_info = tk_decrypt($public_key_from, base64_decode($crypt3), TRUE);
				}

				$transaction_amount_sent = find_string("AMOUNT=", "---TIME", $transaction_info);
				$trans_send_from_results_string.= 'Sent [<font color="green"><strong>' . $transaction_amount_sent . '</strong></font>] To Public Key ' . unix_timestamp_to_human($timestamp, $user_timezone) . '<p style="word-wrap:break-word; font-size:10px;">' . base64_encode($public_key_to) . '</p><hr>';
				$trans_send_from_results_counter+= $transaction_amount_sent;
				$trans_send_to_results_total++;
			}
		}

		// Unset variable to free up RAM
		unset($sql_result);

		$gen_results_string.= '<hr>';
		$trans_send_from_results_string.= '<hr>';
		$trans_send_to_results_string.= '<hr>';

		$body_string.= '<strong>Public Key Balance: [<font color="green">' . ($gen_currency_counter + $trans_send_to_results_counter - $trans_send_from_results_counter) . "</font> TK]</strong>";
		$body_string.= '<br><strong>Total Currency Sent From This Public Key: [<font color="green">' . $trans_send_from_results_counter . '</font> TK] via [<font color="blue">' . $trans_send_to_results_total . '</font>] Transactions</strong>';
		$body_string.= '<br><strong>Total Currency Sent To This Public Key: [<font color="green">' . $trans_send_to_results_counter . '</font> TK] via [<font color="blue">' . $trans_send_from_results_total . '</font>] Transactions</strong>';
		$body_string.= '<br><strong>Total Currency Generated: [<font color="green">' . $gen_currency_counter . '</font> TK] via [<font color="blue">' . $trans_generate_results_counter . '</font>] Generating Events</strong>';
		$body_string.= '<br><strong>Processing Time: <font color="blue">' . (time() - $start_scan_time) . "</font> seconds</strong>";
		$body_string.=	$trans_send_from_results_string . $trans_send_to_results_string . $gen_results_string;
	}

	// Quick Info Bar on Right
	$quick_info = 'Scan and display the transaction history for all transactions associated with the provided Public Key.';

	home_screen($section_string, $text_bar, $body_string, $quick_info , 0, TRUE, "TX Explore");
	// The last variable TRUE is important to have Timekoin re-adjust pathing to make sure
	// menus and screens come up properly.

	exit; // All done processing
}

?>
