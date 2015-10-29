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

		$orderTotal = new number_formatting; // Used to make sure the bills are showing in the admins currency
		$orderTotal->set_custom_cur_defaults($config['settings']['defaultcur']);
		$parms['noDefault'] = true;

		$orderResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}orders
			LEFT JOIN {$dbinfo[pre]}invoices ON {$dbinfo[pre]}orders.order_id = {$dbinfo[pre]}invoices.order_id
			WHERE {$dbinfo[pre]}orders.member_id = {$memberID} 
			AND {$dbinfo[pre]}orders.order_status != '2' 
			AND {$dbinfo[pre]}orders.deleted = 0  
			ORDER BY {$dbinfo[pre]}orders.order_date DESC
			"
		);
		
		if($returnRows = mysqli_num_rows($orderResult))
		{			
			while($order = mysqli_fetch_assoc($orderResult))
			{
				$ordersArray[$order['order_id']] = $order;
				
				$ordersArray[$order['order_id']]['order_date_display'] = $customDate->showdate($order['order_date'],1);	
				
				$cleanTotal['display'] = $orderTotal->currency_display($order['total'],1);
				$cleanTotal['raw'] = round($order['total'],2);
				$ordersArray[$order['order_id']]['total'] = $cleanTotal;
				
				$ordersArray[$order['order_id']]['order_payment_lang'] = orderPaymentNumToText($order['payment_status']);
				$ordersArray[$order['order_id']]['order_status_lang'] = orderStatusNumToText($order['order_status']);
			}
			
			$smarty->assign('ordersArray',$ordersArray);
			$smarty->assign('orderRows',$returnRows);
		}
		
		$smarty->display('orders.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>