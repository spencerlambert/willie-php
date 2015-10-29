<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','cart'); // Page ID
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

	define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	define('META_DESCRIPTION','');
	define('META_KEYWORDS','');
	define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/invoicetools.php';
	
	try
	{
		if(!$itemType)
			die('No itemType passed!');

		$invoice = new invoiceTools;
		
		if($downloadOrderID) // If a cart ID was passed grab the order details
		{
			$invoice->setOrderID($downloadOrderID); // Set the order ID
			if($orderInfo = $invoice->getOrderDetails())
			{
				//print_r($orderInfo); exit;
				$smarty->assign('downloadOrderID',$downloadOrderID);
			}
		}
		
		$invoiceOptions = $invoice->getOnlyItemOptions($itemType,$itemID); // Get the options as a temp array
		$invoiceItem = $invoiceOptions[$itemID]; // Put those options into the invoice items array
		
		$invoiceItem['item_type'] = $itemType;
		
		$smarty->assign('invoiceItemID',$itemID);
		$smarty->assign('invoiceItem',$invoiceItem);
		$smarty->display('item.options.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>