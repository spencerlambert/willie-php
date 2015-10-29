<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 5-10-2011
	*  Modified: 5-10-2011
	******************************************************************/
	
	//sleep(2);
	
	header("Cache-Control: no-cache, must-revalidate"); // Keep the page from caching
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");	
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','hover'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';

	try
	{
		$unencryptedMediaID = k_decrypt($mediaID); // Get the mediaID unencrypted
		
		$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$unencryptedMediaID}'";
		$mediaObj = new mediaList($sql); // Create a new mediaList object
		if($returnRows = $mediaObj->getRows())
		{
			$media = $mediaObj->getSingleMediaDetails();
		}
		
		$mediaInfo = new mediaTools($unencryptedMediaID);
		$thumb = $mediaInfo->getThumbInfoFromDB();
		$sample = $mediaInfo->getSampleInfoFromDB();
		
		if($media['dsp_type'] == 'video') // Make sure the DSP type is set to video
		{
			if($video = $mediaInfo->getVidSampleInfoFromDB()) // Make sure video file exists
			{
				
				$videoCheck = $mediaInfo->verifyVidSampleExists();
				if($videoCheck['status']) { // Make sure the video exists
				
					//print_k($videoCheck); exit;
				
					if($videoCheck['url'] and $config['passVideoThroughPHP'] === false)
						$video['url'] = $videoCheck['url']; // Use URL method
					else
						$video['url'] = $config['settings']['site_url'].'/video.php?mediaID='.$media['encryptedID']; // Use PHP pass-through
				
					//echo $video['url']; exit;
				
					//print_k($video);
					$media['videoStatus'] = 1;
					$media['videoInfo'] = $video;
					
				} else {
					$media['videoStatus'] = 0;	
				}
			}
			else
				$media['videoStatus'] = 0;
		}
		
		if(!$thumb)
		{
			$thumb['thumb_width'] = $config['settings']['rollover_size'];
			$thumb['thumb_height'] = round($config['settings']['rollover_size']*.75);
		}
		
		$crop = ($config['settings']['rollovercrop']) ? $config['settings']['rollovercrop_height'] : 0;
		
		$sized = getScaledSizeNoSource($thumb['thumb_width'],$thumb['thumb_height'],$config['settings']['rollover_size'],$crop); // Figure out the width and height this item will be		
		$media['width'] = $sized[0];
		$media['height'] = $sized[1];
		
		$smarty->assign('media',$media); // Assign to smarty
		$smarty->display('hover.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		die(exceptionError($e));
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>