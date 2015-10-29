<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','billPayment'); // Page ID
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
	require_once BASE_PATH.'/assets/classes/invoicetools.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	if(!$billID) // Make sure this exists before going on
		die('No billID was passed. Cannot proceed.');
	
	$gatewayMode = "publicForm";
	
	/*
	* Get currency info from db
	*/
	$currency = getCurrencyInfo($config['settings']['defaultcur']);
	
	$invoice = new invoiceTools;
	$billInfo = $invoice->getBillDetails($billID); // Get the bill info using the passed bill ID
	$invoiceInfo = $invoice->getInvoiceDetailsViaBillDBID($billInfo['bill_id']); // Get invoice details

	// xxxxxx Check if this is a membership and if so then check to see if tax needs to be added

	$parms['noDefault'] = true; // Do not allow defaults just in case

	$cartInfo['orderNumber'] 				= $invoiceInfo['invoice_number'];
	$uniqueOrderID 							= "bill-{$billID}";
	$cartTotals['subtotalMinusDiscounts'] 	= $invoiceInfo['total']*1; // Round it
	$cartTotals['taxTotal'] 				= 0;
	$cartTotals['shippingTotal']			= 0;
	$cartTotals['cartGrandTotal'] 			= $invoiceInfo['total']*1; // Round it
	
	$cartTotals['billGrandTotalLocal'] 		= getCorrectedPrice($cartTotals['cartGrandTotal'],$parms);
	
	$memberAddress['country'] 				= $_SESSION['member']['primaryAddress']['country'];
	$memberAddress['state'] 				= $_SESSION['member']['primaryAddress']['state'];				
	$memberAddress['countryID'] 			= $_SESSION['member']['primaryAddress']['countryID'];
	$memberAddress['name'] 					= $_SESSION['member']['f_name']." ".$_SESSION['member']['l_name'];
	$memberAddress['firstName'] 			= $_SESSION['member']['f_name'];
	$memberAddress['lastName'] 				= $_SESSION['member']['l_name'];
	$memberAddress['address'] 				= $_SESSION['member']['primaryAddress']['address'];
	$memberAddress['address2'] 				= $_SESSION['member']['primaryAddress']['address2'];
	$memberAddress['city'] 					= $_SESSION['member']['primaryAddress']['city'];
	$memberAddress['stateID'] 				= $_SESSION['member']['primaryAddress']['stateID'];
	$memberAddress['postalCode']			= $_SESSION['member']['primaryAddress']['postal_code'];
	$memberAddress['email'] 				= $_SESSION['member'	]['email'];
	$memberAddress['phone'] 				= $_SESSION['member']['phone'];

	$shippingAddress = $memberAddress;
	$billingAddress = $memberAddress;
	
	$_SESSION['billTotalsSession'] = $cartTotals;
	$_SESSION['billInfoSession'] = $cartInfo;
	
	/*
	* Include the correct gateway
	*/
	switch($paymentType) // Determine the payment type
	{
		/* Not used to pay a bill
		case 'billMeLater':
			$ipnValue['paymentStatus'] 	= 3; // 3 = Bill me later
			$ipnValue['orderID'] 		= $uniqueOrderID; // Order id to pass to the ipn include
			
			require_once BASE_PATH.'/assets/includes/ipn.inc.php'; // ipn include file
			
			$gatewayForm = buildGatewayForm("order.details.php?orderID={$uniqueOrderID}", $formData); // Get form data for use			
			$submitSleep = 3000; // Delay 3 seconds
		break;
		*/
		case 'mailin':
			$ipnValue['paymentStatus'] 	= 2; // Unpaid
			$ipnValue['orderID'] 		= $uniqueOrderID; // Order id to pass to the ipn include
			
			require_once BASE_PATH.'/assets/includes/ipn.inc.php'; // ipn include file
			
			$gatewayForm = buildGatewayForm('bill.mailin.php', $formData); // Get form data for use			
			$submitSleep = 3000; // Delay 3 seconds
		break;
		case 'stripe':
			$gatewaySetting = getGatewayInfoFromDB($paymentType); // Get the gateway settings from the db
			require_once BASE_PATH."/assets/gateways/{$paymentType}/functions.php"; // Include the functions file for the gateway
			$gatewayForm = buildGatewayForm('stripe', $formData, $formSubmitMethod); // Get form data for use
			
			//print_k($gatewaySetting); exit;
			
			$smarty->assign('gatewaySetting',$gatewaySetting); // pass gateway form details
			$smarty->assign('gatewayForm',$gatewayForm); // pass gateway form details
			$smarty->assign('formData',$formData); // pass gateway form details
			$smarty->display('bill.payment_stripe.tpl');
			exit;
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