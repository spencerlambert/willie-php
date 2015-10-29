<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','orders'); // Page ID
	define('ACCESS','private'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';

	define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	define('META_DESCRIPTION','');
	define('META_KEYWORDS','');
	define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	try
	{
		$memberID = $_SESSION['member']['mem_id'];
		if(!$memberID) die('No member ID exists'); // Just to be safe make sure a memberID exists before continuing
		
		$downloadsResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}downloads 
			WHERE mem_id = {$memberID} 
			ORDER BY dl_id DESC
			"
		);
		if($returnRows = mysqli_num_rows($downloadsResult))
		{	
			while($downloads = mysqli_fetch_array($downloadsResult))
			{
				$downloadsArray[$downloads['dl_id']] = $downloads;
				$downloadsArray[$downloads['dl_id']]['download_date_display'] = $customDate->showdate($downloads['dl_date'],1);
				
				$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$downloads[asset_id]}'";
				$mediaObj = new mediaList($sql); // Create a new mediaList object
				if($returnMediaRows = $mediaObj->getRows())
				{
					$downloadsArray[$downloads['dl_id']]['media'] = $mediaObj->getSingleMediaDetails();
				}
				
				switch($downloads['dl_type'])
				{
					default:
						$downloadsArray[$downloads['dl_id']]['download_type_display'] = $lang['unknown'];
					break;
					case "free":
						$downloadsArray[$downloads['dl_id']]['download_type_display'] = $lang['freeDownload'];
					break;
					case "sub":
						$downloadsArray[$downloads['dl_id']]['download_type_display'] = $lang['subscription'];
					break;
					case "order":
						$downloadsArray[$downloads['dl_id']]['download_type_display'] = $lang['order'];
					break;
					case "credits":
						$downloadsArray[$downloads['dl_id']]['download_type_display'] = $lang['credits'];
					break;
					case "prevDown":
						$downloadsArray[$downloads['dl_id']]['download_type_display'] = $lang['prevDown'];
					break;
				}
			}
			
			$smarty->assign('downloadsArray',$downloadsArray);
			$smarty->assign('downloadsRows',$returnRows);
		}
		
		$smarty->display('download.history.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>