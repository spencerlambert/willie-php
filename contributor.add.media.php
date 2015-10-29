<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','contributorAddMedia'); // Page ID
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
	
	$memberID = $_SESSION['member']['mem_id'];
	if(!$memberID) die('No member ID exists'); // Just to be safe make sure a memberID exists before continuing
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/init.contributor.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	// Set the member uploader
	if(!$_SESSION['member']['uploader'])
		$_SESSION['member']['uploader'] = $config['settings']['pubuploader'];

	try
	{	
		$contrFID = zerofill($_SESSION['member']['mem_id'],5);
		$incomingFolder = BASE_PATH.'/assets/contributors/contr'.$contrFID.'/*.*'; // Search folder for files
		$incomingFileCount = count(glob($incomingFolder))-1; // Number of files found
		
		$smarty->assign('incomingFileCount',$incomingFileCount);
		$smarty->display('contributor.add.media.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>