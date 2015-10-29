<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','promotions'); // Page ID
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
	
	define('META_TITLE',$lang['promotions'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	try
	{
		if($promoID)
			$smarty->assign('promoID',$promoID);
			
		$promotionsResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}promotions 
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}promotions.promo_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'promotions') 
			WHERE {$dbinfo[pre]}promotions.active = 1 
			AND {$dbinfo[pre]}promotions.promopage = 1 
			AND {$dbinfo[pre]}promotions.deleted = 0
			AND ({$dbinfo[pre]}promotions.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
			AND ({$dbinfo[pre]}promotions.quantity = '' OR {$dbinfo[pre]}promotions.quantity > '0')
			ORDER BY {$dbinfo[pre]}promotions.sortorder
			"
		);
		if($returnRows = mysqli_num_rows($promotionsResult))
		{
			while($promotions = mysqli_fetch_assoc($promotionsResult))
				$promotionsArray[] = promotionsList($promotions);			

			$smarty->assign('promotionsRows',$returnRows);
			$smarty->assign('promotions',$promotionsArray);
		}
		
		$smarty->display('promotions.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>