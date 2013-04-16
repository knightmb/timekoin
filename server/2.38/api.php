<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(999);
//***********************************************************************************
//***********************************************************************************
if(API_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Open persistent connection to database
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

// Check for banned IP address
if(ip_banned($_SERVER['REMOTE_ADDR']) == TRUE)
{
	// Sorry, your IP address has been banned :(
	exit;
}
//***********************************************************************************
//***********************************************************************************
// Answer public key balance request that match our hash code
if($_GET["action"] == "key_balance")
{
	$hash_code = substr($_GET["hash"], 0, 256);
	$server_hash_code = mysql_result(mysql_query("SELECT * FROM `options` WHERE `field_name` = 'server_hash_code' LIMIT 1"),0,"field_data");

	if($hash_code == $server_hash_code && $server_hash_code != "0")
	{
		// Grab balance for public key and return back
		$public_key = substr($_POST["public_key"], 0, 500);
		$public_key = filter_sql(base64_decode($public_key));

		echo check_crypt_balance($public_key);
	}

	// Log inbound IP activity
	log_ip("AP");
	exit;
}
//***********************************************************************************


?>
