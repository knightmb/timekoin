<?PHP
//
// This is the long name of your plugin.
// PLUGIN_NAME=Crypto Benchmark---END
//
// This is the tab text on the menu bar.
// PLUGIN_TAB=Crypt Benchmark---END
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
	$section_string = "Crypto Benchmark";

	// Text Bar Txt
	$text_bar = 'Benchmark Results:<br>';
	$bits_level = 1536;
	$decrypted_data = 'Decrypted Data Appears Here';

	if($_GET["action"] == "crypt")
	{
		$bits_level = intval($_POST["crypt_bits"]);

		if($bits_level < 10) { $bits_level = 10; }

		$encrypt_data = $_POST["encrypt_me"];

		if(empty($encrypt_data) == TRUE) { $encrypt_data = "empty"; }

		$key_create_micro_time = microtime(TRUE);

		require_once('../RSA.php');

		$rsa = new Crypt_RSA();
		
		extract($rsa->createKey($bits_level));

		$key_create_micro_time_done = microtime(TRUE);

		if(empty($privatekey) == FALSE && empty($publickey) == FALSE)
		{
			$symbols = array("\r");
			$new_publickey = str_replace($symbols, "", $publickey);
			$new_privatekey = str_replace($symbols, "", $privatekey);

			$encrypt_create_micro_time = microtime(TRUE);			
			// Encrypt New Data
			$encrypt_data_new = tk_encrypt($new_privatekey, $encrypt_data);
			$encrypt_create_micro_time_done = microtime(TRUE);

			$decrypt_create_micro_time = microtime(TRUE);			
			// Now Decrypt the same Data
			$decrypted_data = tk_decrypt($new_publickey, $encrypt_data_new);
			$decrypt_create_micro_time_done = microtime(TRUE);

			if(empty($decrypted_data) == TRUE) { $decrypted_data = '***DATA STRING TOO LONG FOR BITS ENTERED***'; }

				$micro_time_variance = "Key Pair Creation [<strong>" . round(($key_create_micro_time_done - $key_create_micro_time) * 1000) . "</strong>] ms<br>
				Data Encryption [<strong>" . round(($encrypt_create_micro_time_done - $encrypt_create_micro_time) * 1000) . "</strong>] ms<br>
				Data Decryption [<strong>" . round(($decrypt_create_micro_time_done - $decrypt_create_micro_time) * 1000) . "</strong>] ms<br>
				Total Time [<strong>" . round((microtime(TRUE) - $key_create_micro_time) * 1000) . "</strong>] ms";
			$text_bar.= " $micro_time_variance for <strong>$bits_level</strong> bit RSA Encryption";			
		}
		else
		{
			// Key Pair Creation Error
			$text_bar = 'Key Creation Failed';
		}

	}

	// Main Body Text
	$body_string = '<FORM ACTION="cryptobenchmark.php?action=crypt" METHOD="post">
	Choose bits: <input type="text" size="20" name="crypt_bits" value="' . $bits_level . '" /><br><br>
	Data to Encrypt: <textarea name="encrypt_me" rows="6" cols="75">' . $encrypt_data . '</textarea><hr>
	Data to After Decryption: <textarea name="decrypt_me" rows="6" cols="75">' . $decrypted_data . '</textarea><br><br>
	<input type="submit" name="Submit" value="Run Benchmark" /></FORM><hr>
	<p style="word-wrap:break-word; font-size:12px;"><strong>Private Key Used:</strong><br>' . base64_encode($new_privatekey) . '</p><hr>
	<p style="word-wrap:break-word; font-size:12px;"><strong>Public Key Used:</strong><br>' . base64_encode($new_publickey) . '</p>';

	// Quick Info Bar on Right
	$quick_info = 'This will create a random key pair using the set bits, encrypt the data you entered, and then decrypt the same data and output it back.';
	// Does the screen need to refresh every X seconds? 0 = Disable	
	$update = 0; 

	home_screen($section_string, $text_bar, $body_string, $quick_info , $update, TRUE);
	// The last variable TRUE is important to have Timekoin re-adjust pathing to make sure
	// menus and screens come up properly.

	exit; // All done processing
}

?>
