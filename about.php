<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','content'); // Page ID
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
	
	define('META_TITLE',$lang['aboutUs'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	/*
	if($config['settings']['cache_pages'])
		$smarty->setCaching(Smarty::CACHING_LIFETIME_CURRENT); // Set this page to cache if it is turned on
	*/
	
	//print_k($activeLanguages[$selectedLanguage]);  // Testing
	
	//print_k($activeCurrencies);
	//print_k($displayCurrencies);
	
	try
	{	
		if(!$smarty->isCached('about.tpl',$pageCacheID)) // Page not cached
		{
			//echo $config['settings']['logo'].'test'; exit; // Testing
			
			$content = getDatabaseContent('aboutUs');
			$content['body'] = $smarty->fetch('eval:'.$content['body']);		
			$smarty->assign('content',$content);
			//$smarty->display('about.tpl'); // About template
			
			$pageIsCached = false;
		}
		else
			$pageIsCached = true;
			
		$smarty->display('about.tpl',$pageCacheID);
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
	
?>