<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','promo'); // Page ID
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
		
	try
	{	
		if($config['EncryptIDs']) // Decrypt IDs
			$id = k_decrypt($id);
		
		if(!is_numeric($id))
			$id = k_decrypt($id);
			
		idCheck($id); // Make sure ID is numeric
		
		$promoResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}promotions 
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}promotions.promo_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'promotions') 
			WHERE {$dbinfo[pre]}promotions.promo_id = {$id}
			AND ({$dbinfo[pre]}promotions.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
			"
		);
		if($returnRows = mysqli_num_rows($promoResult))
		{
			$promo = mysqli_fetch_assoc($promoResult);
			$promoArray = promotionsList($promo);
			
			if($promo['active'] == 1 and $promo['deleted'] == 0)
			{
				$smarty->assign('promo',$promoArray);
				$smarty->assign('promoRows',$returnRows);
			}
			else
				$smarty->assign('noAccess',1);
		}
		else
			$smarty->assign('noAccess',1);
			
		$smarty->display('promo.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>