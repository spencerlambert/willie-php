<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 8-27-2012
	*  Modified: 8-27-2012
	******************************************************************/
	
	//sleep(2);
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','importList'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	
	try
	{	
		if(!$_SESSION['member']['mem_id'])
			die('No member id session exists');
		
		$contrFID = zerofill($_SESSION['member']['mem_id'],5);
		$incomingFolder = BASE_PATH.'/assets/contributors/contr'.$contrFID; // Search folder for files
		$incomingFilesGlob = glob($incomingFolder.'/*.*'); // Grab a list of all incoming files
		
		// print_r($incomingFilesGlob); exit; / Testing
		
		foreach($incomingFilesGlob as $value)
		{
			if(!strpos($value,'icon_') and !strpos($value,'sample_') and !strpos($value,'thumb_') and !strpos($value,'index.html')) // Make sure to only grab originals
			{
				$name = basename($value);
				
				$icon = (file_exists($incomingFolder.'/icon_'.$name)) ? 'icon_'.$name : 'none'; // Icon file
					
				$path = base64_encode($value);
				$incomingFiles[] = array(
										'name' => $name,
										'path' => $path,
										'icon' => $icon
									);
			}
		}
		
		$smarty->assign('incomingFilesCount',count($incomingFiles));
		$smarty->assign('contrFID',$contrFID);
		$smarty->assign('incomingFiles',$incomingFiles);
		$smarty->display('contributor.import.list.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}

	if($db) mysqli_close($db); // Close any database connections
	
?>