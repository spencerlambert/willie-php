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

	//define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	//define('META_DESCRIPTION','');
	//define('META_KEYWORDS','');
	//define('PAGE_ENCODING','');
	
	//print_k($_POST); exit;
	
	define('META_TITLE',$lang['reviewOrder'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';
	require_once BASE_PATH.'/assets/classes/invoicetools.php';
		
	if(!$_SESSION['cartTotalsSession']) // Make sure this exists before going on
		die('No cartTotalsSession was passed. Cannot proceed.');
		
	if(!$_SESSION['cartInfoSession']) // Make sure this exists before going on
		die('No cartInfoSession was passed. Cannot proceed.');
	
	if($_SESSION['cartTotalsSession']['priceSubTotal'] < $config['settings']['min_total'] and $_SESSION['cartTotalsSession']['creditsTotal'] < 1) // Check to make sure subtotal is enough to continue checkout
		die('Minimum purchase not met. Cannot proceed.');
		
	if($_SESSION['cartTotalsSession']['creditsAvailableAtCheckout'] < $_SESSION['cartTotalsSession']['creditsTotal']) // Make sure the member has enough credits to checkout
		die('Not enough credits to checkout. Cannot proceed.');

	if($config['settings']['accounts_required'] and !$_SESSION['loggedIn']) // Find out if an account is required to purchase or if the user is already logged in
		die('You must be logged in to continue.');
	
	$gatewayMode = "publicForm";
	
	/*
	* Create an order number
	*/
	if(!$_SESSION['cartInfoSession']['orderNumber']) // Create an order number if one doesn't exist
	{
		if($config['settings']['order_num_type'] == 1) // Get sequential number
		{
			$orderNumber = $config['settings']['order_num_next'];
			$nextOrderNumber = $orderNumber+1;		
			mysqli_query($db,"UPDATE {$dbinfo[pre]}settings SET order_num_next='{$nextOrderNumber}' WHERE settings_id = 1"); // update settings db with next number		
		}
		else // Get random order number
		{
			$orderNumber = create_order_number();
		}
		
		mysqli_query($db,"UPDATE {$dbinfo[pre]}orders SET order_number='{$orderNumber}' WHERE uorder_id = '{$_SESSION[uniqueOrderID]}'"); // Update db with order number		
		
		$_SESSION['cartInfoSession']['orderNumber'] = $orderNumber; // Put this in the session
	}
	
	/*
	* Get currency info from db
	*/
	$currency = getCurrencyInfo($config['settings']['defaultcur']);
	
	/*
	* Change to local values
	*/
	$uniqueOrderID = $_SESSION['uniqueOrderID'];
	$cartInfo = $_SESSION['cartInfoSession'];
	$cartTotals = $_SESSION['cartTotalsSession'];
	$shippingAddress = $_SESSION['shippingAddressSession'];
	$billingAddress = $_SESSION['billingAddressSession'];
	
	if(!$_SESSION['shippingAddressSession']['email'] or !$_SESSION['billingAddressSession']['email']) // If no shipping address email then update invoice record with posted email address
	{ 
		mysqli_query($db,"UPDATE {$dbinfo[pre]}invoices SET ship_email='{$_POST[email]}',bill_email='{$_POST[email]}' WHERE invoice_id = '{$_SESSION[cartInfoSession][invoiceID]}'"); // Update db with order number
	}
	
	$_SESSION['payer_email'] = $_POST['email']; // If there is an email passed then store it in a session
	
	if($_SESSION['cartTotalsSession']['shippingRequired']) // Create step numbers depending on if shipping is needed or not
		$stepNumber = array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4);
	else
		$stepNumber = array('a' => 1, 'b' => 0, 'c' => 2, 'd' => 3);
	
	// Get the stripe db details
	$stripeResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}paymentgateways WHERE gateway = 'stripe' AND setting = 'pkey'");
	if($returnRows = mysqli_num_rows($stripeResult))			
		$stripe = mysqli_fetch_assoc($stripeResult);
	else
	{
		echo "There is no public key set for your stripe account.";
		exit;
	}
	
	$gatewaySetting = getGatewayInfoFromDB($paymentType); // Get the gateway settings from the db
	require_once BASE_PATH."/assets/gateways/{$paymentType}/functions.php"; // Include the functions file for the gateway
	$gatewayForm = buildGatewayForm('stripe', $formData, $formSubmitMethod); // Get form data for use
	
	try
	{
		$smarty->assign('gatewayForm',$gatewayForm); // pass gateway form details	
		
		$smarty->assign('invoiceItemsCount',$invoiceItemsCount); // Number 
		$smarty->assign('digitalInvoiceItems',$digitalInvoiceItems); // Digital invoice items
		$smarty->assign('physicalInvoiceItems',$physicalInvoiceItems); // Physical invoice items
		$smarty->assign('stepNumber',$stepNumber); // Shipping address info
		$smarty->assign('shippingDetails',$shippingDetails); // Shipping address info	
		
		$smarty->assign('shippingAddress',$_SESSION['shippingAddressSession']); // Shipping address info
		$smarty->assign('billingAddress',$_SESSION['billingAddressSession']); // Billing address info
		$smarty->assign('cartInfo',$_SESSION['cartInfoSession']); // Cart info session
		$smarty->assign('cartTotals',$_SESSION['cartTotalsSession']); // Cart totals session
		$smarty->assign('stripe',$stripe); // Pass stripe settings		
		$smarty->display('cart.payment_stripe.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>