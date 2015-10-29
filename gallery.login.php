<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','galleryLogin'); // Page ID
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
	
	define('META_TITLE',$lang['galleryLogin'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	$useID = $id; // Original ID before any conversion
	
	$id = $_REQUEST['id'];

	try
	{	
		if($config['EncryptIDs']) // Decrypt IDs
			$id = k_decrypt($id);
			
		idCheck($id); // Make sure ID is numeric
	
		$currentGallery = $_SESSION['galleriesData'][$id];
		
		$crumbs = galleryCrumbs($id); // Get the crumb trail
		
		//print_r($crumbs);
		
		if($_POST)
		{
			if(k_encrypt($galleryPassword) == $currentGallery['password'])
			{
				$_SESSION['member']['memberPermGalleries'][] = $id; // Add this ID to the permission string
				header("location: gallery.php?mode=gallery&id={$useID}&page=1"); // Redirect back to the galleries page
				exit;
			}
			else
			{
				$logNotice = 'galleryWrongPass'; // The session assign/login failed
			}
		}
		
		$smarty->assign('crumbs',$crumbs); // Assign crumbs to smarty
		$smarty->assign('logNotice',$logNotice); // Assign login notice message to smarty
		$smarty->assign('currentGallery',$currentGallery);
		$smarty->assign('useID',$useID); // Assign original id to smarty
		$smarty->assign('id',$id); // Assign id to smarty
		$smarty->display('gallery.login.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>