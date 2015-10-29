<?php

	define('BASE_PATH',dirname(__FILE__)); // Define the base path

	define('PAGE_ID','iptc_exif'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';	
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	require_once BASE_PATH.'/assets/classes/metadata.php';

	echo "<html>";
	echo "<head><meta http-equiv='Content-Type' content='text/html; charset={$langset[lang_charset]}' /></head>";
	echo "<body>";

	if(function_exists('iptcparse'))
		echo "iptcparse Exists<br>";
	else
	{
		 echo "iptcparse Does not exist<br>";
	}
	
	if(function_exists('exif_read_data'))
		echo "exif_read_data Exists<br>";
	else
	{
		 echo "exif_read_data Does not exist<br>";
	}

	echo "<br>";

	try
	{
		$imagemetadata = new metadata('test.jpg');
		
		//$imagemetadata->charset = '';
		
		$iptc = $imagemetadata->getIPTC();
		
		if(function_exists('exif_read_data'))
			$exif = $imagemetadata->getEXIF();
		//print_r($iptc);
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	echo "<strong>IPTC:</strong> <br>";
	foreach($iptc as $key => $value)
	{
		echo "{$key}: {$value}<br>";
	}
	
	echo "<br><br><strong>EXIF</strong>: <br>";
	foreach($exif as $key => $value)
	{
		echo "{$key}: {$value}<br>";
	}
	
	print_r($exif['GPSLatitude']);
	
	echo "</body>";
	echo "</html>";
?>