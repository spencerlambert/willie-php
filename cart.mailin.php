<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','cartReview'); // Page ID
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
	require_once BASE_PATH.'/assets/classes/mediatools.php';

	if($_SESSION['cartTotalsSession']['shippingRequired']) // Create step numbers depending on if shipping is needed or not
		$stepNumber = array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4);
	else
		$stepNumber = array('a' => 1, 'b' => 0, 'c' => 2, 'd' => 3);

	try
	{
		$smarty->assign('stepNumber',$stepNumber); // Shipping address info
		$smarty->assign('shippingDetails',$shippingDetails); // Shipping address info
		$smarty->assign('gateways',$gateways); // Shipping address info
		$smarty->assign('shippingAddress',$_SESSION['shippingAddressSession']); // Shipping address info
		$smarty->assign('billingAddress',$_SESSION['billingAddressSession']); // Billing address info
		$smarty->assign('cartInfo',$_SESSION['cartInfoSession']); // Cart info session
		$smarty->assign('cartTotals',$_SESSION['cartTotalsSession']); // Cart totals session		
		
		$content = getDatabaseContent('checkConfirmPage');
		$content['body'] = $smarty->fetch('eval:'.$content['body']);		
		$smarty->assign('content',$content);
		
		$smarty->display('cart.mailin.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	
	clearCartSession();	 // Clear the cart session after loading everything
	
	if($db) mysqli_close($db); // Close any database connections
?>