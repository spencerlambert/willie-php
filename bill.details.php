<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','orderDetails'); // Page ID
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
	
	if(!$_GET['billID']) // Make sure a bill ID is passed and if not die
		die("No bill ID was passed");
		
	// Log member out to reset membership details
	memberSessionDestroy(); // Destroy the members session
	$_SESSION['loggedIn'] = 0;
	
	try
	{
		$invoice = new invoiceTools; // New invoiceTools object
		$billInfo = $invoice->getBillDetails($billID); // Get the bill info using the passed ubill ID		
		$invoiceInfo = $invoice->getInvoiceDetailsViaBillDBID($billInfo['bill_id']); // Get the invoice info using the passed ubill ID
		
		//print_r($invoiceInfo);
		
		$smarty->assign('billInfo',$billInfo);
		$smarty->assign('invoiceInfo',$invoiceInfo);		
		$smarty->display('bill.details.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>