<?php
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','mediaDetails'); // Page ID
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

	//define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	//define('META_DESCRIPTION','');
	//define('META_KEYWORDS','');
	//define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';
	
	/*
	$mediaObj = new mediaTools(588);
	
	$sample = $mediaObj->getSampleInfoFromDB();
	
	print_r($sample);
	*/
	
	//mediaImage mediaID=$media.encryptedID type=thumb folderID=$media.encryptedFID
	
	$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media";
	$mediaObject = new mediaList($sql);
	
	if($returnRows = $mediaObject->getRows())
	{
		$mediaObject->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
		$mediaArray = $mediaObject->getMediaArray(); // Get the array of media
		
		//print_r($mediaArray);
		
		foreach($mediaArray as $key => $media)
		{
			echo "{$media[media_id]} - {$media[linkto]}<br>";
		}
	}
	
	
	
?>