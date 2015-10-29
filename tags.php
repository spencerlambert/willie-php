<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 10-13-2011
	*  Modified: 10-13-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','tags'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	//require_once BASE_PATH.'/assets/includes/commands.php';
	//require_once BASE_PATH.'/assets/includes/init.member.php';
	//require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	//require_once BASE_PATH.'/assets/includes/cart.inc.php';
	//require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	//echo $_SESSION['errorcount'];
	//$_SESSION['errorcount']++;
	
	try
	{	
		if($config['EncryptIDs']) // Decrypt IDs
			$mediaID = k_decrypt($mediaID);
		
		$language = strtoupper($_SESSION['member']['language']);
		
		//echo 'lang:'.$language; // Testing
		
		/*
		* Media Tags
		*/
		if($taggingSystem)
		{
			$tagResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}keywords  
				WHERE media_id = '{$mediaID}' 
				AND status = 1 
				AND language = '{$language}' 
				GROUP BY keyword 
				ORDER BY posted DESC
				"
			);
			if($tagRows = mysqli_num_rows($tagResult))
			{
				while($tag = mysqli_fetch_array($tagResult))
				{
					$tagsArray[] = 	$tag;
				}
				$smarty->assign('tagRows',$tagRows);
				$smarty->assign('tagsArray',$tagsArray);
			}
		}
		
		$smarty->display('tags.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
?>