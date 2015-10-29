<?php
	/******************************************************************
	*  Copyright 2013 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 5-24-2013
	*  Modified: 5-24-2013
	******************************************************************/
	
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','downloadMiniWindow'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	//require_once BASE_PATH.'/assets/includes/cart.inc.php';
	//require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	try
	{	
		$useMediaID = $mediaID; // Original untouched media ID
		
		if(!$mediaID) // Make sure a media ID was passed
			exit; //$smarty->assign('noAccess',1);
		else
		{	
			idCheck($mediaID); // Make sure ID is numeric
				
			$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
			$mediaInfo = new mediaList($sql);
			
			if($mediaInfo->getRows())
			{
				$media = $mediaInfo->getSingleMediaDetails('preview');
				$galleryIDArray = $mediaInfo->getMediaGalleryIDs(); // Get an array of galleries this media is in
				
				switch($incMode)
				{
					default:
					case 'digital':				
						$galleryIDArrayFlat = ($galleryIDArray) ? implode(",",$galleryIDArray) : 0; // Get the gallery IDs for this photo
						
						require_once 'media.details.inc.php';
						$smarty->assign('mediaID',$mediaID);
						$smarty->display('download.mini.window.tpl'); // Smarty template	
					break;
				}
			}
		}
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
?>