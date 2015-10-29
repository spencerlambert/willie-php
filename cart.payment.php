<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','cartProcess'); // Page ID
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
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
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
	
	/*
	* Include the correct gateway
	*/
	switch($paymentType) // Determine the payment type
	{
		case 'freeCart':
			$ipnValue['paymentStatus'] 	= 1; // 3 = Bill me later
			$ipnValue['orderID'] 		= $uniqueOrderID; // Order id to pass to the ipn include
			
			require_once BASE_PATH.'/assets/includes/ipn.inc.php'; // ipn include file
			
			$gatewayForm = buildGatewayForm("order.details.php?orderID={$uniqueOrderID}", $formData); // Get form data for use			
			$submitSleep = 3000; // Delay 3 seconds
		break;	
		case 'billMeLater':
			
			$ipnValue['paymentStatus'] 	= 3; // 3 = Bill me later
			$ipnValue['orderID'] 		= $uniqueOrderID; // Order id to pass to the ipn include
			
			require_once BASE_PATH.'/assets/includes/ipn.inc.php'; // ipn include file
			
			$gatewayForm = buildGatewayForm("order.details.php?orderID={$uniqueOrderID}", $formData); // Get form data for use			
			$submitSleep = 3000; // Delay 3 seconds
		break;
		case 'mailin':
			$ipnValue['paymentStatus'] 	= 2; // Unpaid
			$ipnValue['orderID'] 		= $uniqueOrderID; // Order id to pass to the ipn include
			
			require_once BASE_PATH.'/assets/includes/ipn.inc.php'; // ipn include file
			
			$gatewayForm = buildGatewayForm('cart.mailin.php', $formData); // Get form data for use			
			$submitSleep = 3000; // Delay 3 seconds
		break;
		case 'mollieideal':
			$gatewaySetting = getGatewayInfoFromDB($paymentType);
			if (isset($_POST['bank_id']) and !empty($_POST['bank_id']))
			{
				$gatewayMode = 'redirectUser';
				require_once BASE_PATH."/assets/gateways/{$paymentType}/functions.php";
			}
			else
			{
				require_once BASE_PATH."/assets/gateways/{$paymentType}/functions.php";
				$smarty->assign(array(
					'banks' => $banks,
					'email' => $_POST['email']
				));
				$smarty->display('cart.payment_mollieideal.tpl');
				exit;
			}
		break;
		default:		
			if(file_exists(BASE_PATH."/assets/gateways/{$paymentType}/functions.php"))
			{
				$gatewaySetting = getGatewayInfoFromDB($paymentType); // Get the gateway settings from the db
				require_once BASE_PATH."/assets/gateways/{$paymentType}/functions.php"; // Include the functions file for the gateway
				$gatewayForm = buildGatewayForm($formSubmitURL, $formData, $formSubmitMethod); // Get form data for use
				$submitSleep = 0;
			}
			else
				die('Gateway functions file does not exist.'); // Make sure the gateway file exists before continuing		
		break;
	}
	
	//print_r($_SESSION['member']['permmissions']); exit;
		
	try
	{
		$smarty->assign('submitSleep',$submitSleep); // Time to delay the submit of the form
		$smarty->assign('gatewayForm',$gatewayForm); // pass gateway form details		
		$smarty->display('cart.payment.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>