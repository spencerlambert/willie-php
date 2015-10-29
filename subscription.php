<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','subscription'); // Page ID
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
		
		$subscriptionResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}subscriptions 
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}subscriptions.sub_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'subscriptions') 
			WHERE {$dbinfo[pre]}subscriptions.sub_id = {$id}
			AND ({$dbinfo[pre]}subscriptions.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
			"
		);
		if($returnRows = mysqli_num_rows($subscriptionResult))
		{	
			/*
			* Get discounts
			*/
			$discountsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}discount_ranges 
				WHERE item_type = 'subscriptions' 
				AND start_discount_number > 0
				AND item_id = '{$id}' 
				ORDER BY start_discount_number
				"
			);
			if($discountReturnRows = mysqli_num_rows($discountsResult))
			{	
				while($discount = mysqli_fetch_array($discountsResult))
				{
					$discountsArray[$discount['dr_id']] = $discount;
				}
				$smarty->assign('discountRows',$discountReturnRows);
				$smarty->assign('discountsArray',$discountsArray);
			}
			
			if($edit) // We are editing this item
				$smarty->assign('edit',k_encrypt($edit));
			
			$subscription = mysqli_fetch_assoc($subscriptionResult);
			$subscriptionArray = subscriptionsList($subscription);
			
			if($subscription['active'] == 1 and $subscription['deleted'] == 0)
			{
				$smarty->assign('subscription',$subscriptionArray);
				$smarty->assign('subscriptionRows',$returnRows);
			}
			else
				$smarty->assign('noAccess',1);
		}
		else
			$smarty->assign('noAccess',1);
			
		$smarty->display('subscription.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>