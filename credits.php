<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','credits'); // Page ID
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
	
	//print_r($_GET);
		
	try
	{	
		//echo $id; exit;
		
		if($config['EncryptIDs']) // Decrypt IDs
			$id = k_decrypt($id);
			
		idCheck($id); // Make sure ID is numeric
		
		$creditResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}credits  
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}credits.credit_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'credits')
			WHERE {$dbinfo[pre]}credits.credit_id = {$id}
			AND ({$dbinfo[pre]}credits.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
			"
		);
		if(@$returnRows = mysqli_num_rows($creditResult))
		{	
			if($edit) // We are editing this item
				$smarty->assign('edit',k_encrypt($edit));
			
			$credit = mysqli_fetch_assoc($creditResult);
			$creditArray = creditsList($credit);
			
			if($credit['active'] == 1 and $credit['deleted'] == 0)
			{
				$smarty->assign('credit',$creditArray);
				$smarty->assign('creditRows',$returnRows);
			}
			else
				$smarty->assign('noAccess',1);
		}
		else
			$smarty->assign('noAccess',1);
		
		$smarty->display('credits.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>