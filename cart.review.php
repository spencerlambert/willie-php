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
	require_once BASE_PATH.'/mailchimp/Mailchimp.php';

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
		
	/*
	* Redo all checks just to make sure before checking out
	*/
	if(!$_SESSION['uniqueOrderID']) // Make sure an order ID was created and if not die
		die("No order ID was passed to the checkout system");
	
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
	
	if(!$_SESSION['cartInfoSession']['invoiceID']) // Make sure an invoice id exists
		die('No invoice ID exists');
	
	$countryName = getCountryName($shippingCountry);
	$stateName = getStateName($shippingState);
	
	if($_POST['shippingFirstName']) //!$_SESSION['shippingAddressSession'] and 
	{
		//echo "a";
		$_SESSION['shippingAddressSession']['country'] 		= $countryName;
		$_SESSION['shippingAddressSession']['state'] 		= $stateName;				
		$_SESSION['shippingAddressSession']['countryID'] 	= $shippingCountry;
		$_SESSION['shippingAddressSession']['name'] 		= $shippingFirstName." ".$shippingLastName;
		$_SESSION['shippingAddressSession']['firstName'] 	= $shippingFirstName;
		$_SESSION['shippingAddressSession']['lastName'] 	= $shippingLastName;
		$_SESSION['shippingAddressSession']['address'] 		= $shippingAddress;
		$_SESSION['shippingAddressSession']['address2'] 	= $shippingAddress2;
		$_SESSION['shippingAddressSession']['city'] 		= $shippingCity;
		$_SESSION['shippingAddressSession']['stateID'] 		= $shippingState;
		$_SESSION['shippingAddressSession']['postalCode'] 	= $shippingPostalCode;
		$_SESSION['shippingAddressSession']['email'] 		= $shippingEmail;
		$_SESSION['shippingAddressSession']['phone'] 		= $shippingPhone;

		$Mailchimp = new Mailchimp( $mailchimp_api_key );
    $Mailchimp_Lists = new Mailchimp_Lists( $Mailchimp );
    $Mailchimp_Lists->subscribe( $mailchimp_list_id, array( 'email' => $shippingEmail ) );
	}
	if($_POST['shippingFirstName']) //!$_SESSION['billingAddressSession'] and 
	{
		//echo "b";
		if($duplicateInfo) // Billing info same as shipping info
		{	
			$_SESSION['billingAddressSession']['country'] 	= $countryName;
			$_SESSION['billingAddressSession']['state'] 	= $stateName;	
			$_SESSION['billingAddressSession']['countryID'] = $shippingCountry;
			$_SESSION['billingAddressSession']['name'] 		= $shippingFirstName." ".$shippingLastName;
			$_SESSION['billingAddressSession']['firstName'] = $shippingFirstName;
			$_SESSION['billingAddressSession']['lastName'] 	= $shippingLastName;
			$_SESSION['billingAddressSession']['address'] 	= $shippingAddress;
			$_SESSION['billingAddressSession']['address2'] 	= $shippingAddress2;
			$_SESSION['billingAddressSession']['city'] 		= $shippingCity;
			$_SESSION['billingAddressSession']['stateID'] 	= $shippingState;
			$_SESSION['billingAddressSession']['postalCode']= $shippingPostalCode;
			$_SESSION['billingAddressSession']['email'] 	= $shippingEmail;
			$_SESSION['billingAddressSession']['phone'] 	= $shippingPhone;
		}
		else // Billing info is unique
		{
			
			$_SESSION['billingAddressSession']['country'] 	= getCountryName($billingCountry);
			$_SESSION['billingAddressSession']['state'] 	= getStateName($billingState);
			$_SESSION['billingAddressSession']['countryID'] = $billingCountry;
			$_SESSION['billingAddressSession']['name'] 		= $billingFirstName." ".$billingLastName;
			$_SESSION['billingAddressSession']['firstName'] = $billingFirstName;
			$_SESSION['billingAddressSession']['lastName'] 	= $billingLastName;
			$_SESSION['billingAddressSession']['address'] 	= $billingAddress;
			$_SESSION['billingAddressSession']['address2'] 	= $billingAddress2;
			$_SESSION['billingAddressSession']['city'] 		= $billingCity;
			$_SESSION['billingAddressSession']['stateID'] 	= $billingState;
			$_SESSION['billingAddressSession']['postalCode']= $billingPostalCode;
			$_SESSION['billingAddressSession']['email']		= $billingEmail;
			$_SESSION['billingAddressSession']['phone'] 	= $billingPhone;
		}
	}
	
	if($_SESSION['loggedIn'] and !$_SESSION['shippingAddressSession']) // If none try to get member info
	{
		//echo "c";
		$_SESSION['shippingAddressSession']['country'] 		= $_SESSION['member']['primaryAddress']['country'];
		$_SESSION['shippingAddressSession']['state'] 		= $_SESSION['member']['primaryAddress']['state'];				
		$_SESSION['shippingAddressSession']['countryID'] 	= $_SESSION['member']['primaryAddress']['countryID'];
		$_SESSION['shippingAddressSession']['name'] 		= $_SESSION['member']['f_name']." ".$_SESSION['member']['l_name'];
		$_SESSION['shippingAddressSession']['firstName'] 	= $_SESSION['member']['f_name'];
		$_SESSION['shippingAddressSession']['lastName'] 	= $_SESSION['member']['l_name'];
		$_SESSION['shippingAddressSession']['address'] 		= $_SESSION['member']['primaryAddress']['address'];
		$_SESSION['shippingAddressSession']['address2'] 	= $_SESSION['member']['primaryAddress']['address2'];
		$_SESSION['shippingAddressSession']['city'] 		= $_SESSION['member']['primaryAddress']['city'];
		$_SESSION['shippingAddressSession']['stateID'] 		= $_SESSION['member']['primaryAddress']['stateID'];
		$_SESSION['shippingAddressSession']['postalCode']	= $_SESSION['member']['primaryAddress']['postal_code'];
		$_SESSION['shippingAddressSession']['email'] 		= $_SESSION['member']['email'];
		$_SESSION['shippingAddressSession']['phone'] 		= $_SESSION['member']['phone'];
	}
	
	if($_SESSION['loggedIn'] and !$_SESSION['billingAddressSession']) // If none try to get member info
	{
		//echo "d";
		$_SESSION['billingAddressSession']['country'] 		= $_SESSION['member']['primaryAddress']['country'];
		$_SESSION['billingAddressSession']['state'] 		= $_SESSION['member']['primaryAddress']['state'];				
		$_SESSION['billingAddressSession']['countryID'] 	= $_SESSION['member']['primaryAddress']['countryID'];
		$_SESSION['billingAddressSession']['name'] 			= $_SESSION['member']['f_name']." ".$_SESSION['member']['l_name'];
		$_SESSION['billingAddressSession']['firstName'] 	= $_SESSION['member']['f_name'];
		$_SESSION['billingAddressSession']['lastName'] 		= $_SESSION['member']['l_name'];
		$_SESSION['billingAddressSession']['address'] 		= $_SESSION['member']['primaryAddress']['address'];
		$_SESSION['billingAddressSession']['address2'] 		= $_SESSION['member']['primaryAddress']['address2'];
		$_SESSION['billingAddressSession']['city'] 			= $_SESSION['member']['primaryAddress']['city'];
		$_SESSION['billingAddressSession']['stateID'] 		= $_SESSION['member']['primaryAddress']['stateID'];
		$_SESSION['billingAddressSession']['postalCode'] 	= $_SESSION['member']['primaryAddress']['postal_code'];
		$_SESSION['billingAddressSession']['email'] 		= $_SESSION['member']['email'];
		$_SESSION['billingAddressSession']['phone'] 		= $_SESSION['member']['phone'];	
	}
	 
	if((!$_SESSION['billingAddressSession']['email'] or !$_SESSION['shippingAddressSession']['email']) and $_SESSION['loggedIn']) // Make sure email is filled out
	{
		//echo "e";
		$_SESSION['shippingAddressSession']['email'] 		= $_SESSION['member']['email'];
		$_SESSION['billingAddressSession']['email'] 		= $_SESSION['member']['email'];
	}
	
	if($config['settings']['tax_type'] != 1) // Tax locally
	{
		
		// Billing info was passed refresh tax rates based on billing address
		if($_SESSION['billingAddressSession']['countryID'] or $_SESSION['billingAddressSession']['stateID'] or $_SESSION['billingAddressSession']['postalCode'])
		{
			$fauxMember = new memberTools;
			$newTax = $fauxMember->getMemberTaxValues($_SESSION['billingAddressSession']['countryID'],$_SESSION['billingAddressSession']['stateID'],$_SESSION['billingAddressSession']['postalCode']);
			
			$_SESSION['tax']['tax_inc'] = $newTax['tax_inc'];
			$_SESSION['tax']['tax_a_default'] = $newTax['tax_a_default'];
			$_SESSION['tax']['tax_b_default'] = $newTax['tax_b_default'];
			$_SESSION['tax']['tax_c_default'] = $newTax['tax_c_default'];			
			$_SESSION['tax']['tax_a_digital'] = $newTax['tax_a_digital'];
			$_SESSION['tax']['tax_b_digital'] = $newTax['tax_b_digital'];
			$_SESSION['tax']['tax_c_digital'] = $newTax['tax_c_digital'];			
			$_SESSION['tax']['tax_prints'] = $newTax['tax_prints'];
			$_SESSION['tax']['tax_digital'] = $newTax['tax_digital'];
			$_SESSION['tax']['tax_ms'] = $newTax['tax_ms'];
			$_SESSION['tax']['tax_subs'] = $newTax['tax_subs'];
			$_SESSION['tax']['tax_shipping'] = $newTax['tax_shipping'];
			$_SESSION['tax']['tax_credits'] = $newTax['tax_credits'];
			
			$smarty->assign('tax',$_SESSION['tax']); // Resend tax to smarty just in case
		}
	}


	if($_SESSION['cartTotalsSession']['shippingRequired']) // See if shipping is required
	{
		if($shippingMethod) // Shipping method was passed
		{
			$_SESSION['selectedShippingMethodSession'] = $shippingMethod;
			$shippingDetails = $_SESSION['shippingMethodsSession'][$shippingMethod]; // Selected shipping info from previous page
		}
		else
			$shippingDetails = $_SESSION['shippingMethodsSession'][$_SESSION['selectedShippingMethodSession']];
	}

	$_SESSION['cartInfoSession']['selectedShippingMethodID'] = $shippingMethod; // Assign the selected shipping method ID to the cart info session so it can be used back on the shipping page if needed	
	
	// Find out if member can do bill me later
	if($_SESSION['loggedIn'])
	{
		if($_SESSION['member']['bill_me_later'])
		{
			$gateways['billMeLater']['id'] = 'billMeLater';
			$gateways['billMeLater']['displayName'] = $lang['billMeLater'];
			$gateways['billMeLater']['publicDescription'] = $lang['billMeLaterDescription'];
			
			if(file_exists(BASE_PATH."/assets/themes/{$config[settings][style]}/{$colorSchemeImagesDirectory}/logos/billMeLater.png")) // See if the logo exists in the directory specific to this color scheme
				$gateways['billMeLater']['logo'] = true;
			else // Check to see if it exists in the main images directory for this theme
			{
				if(file_exists(BASE_PATH."/assets/themes/{$config[settings][style]}/images/logos/billMeLater.png")) // Check for gateway logo
					$gateways['billMeLater']['logo'] = true;
			}
		}
	}
	
	/*
	* Get the active payment gateways
	*/
	$paymentGatewaysResult = mysqli_query($db,"SELECT DISTINCT gateway FROM {$dbinfo[pre]}paymentgateways WHERE setting = 'active' AND value='1'");
	$paymentGatewaysRows = mysqli_num_rows($paymentGatewaysResult);
	while($paymentGateway = mysqli_fetch_array($paymentGatewaysResult))
	{
		$activeGateways[] = $paymentGateway['gateway'];
		
		if(file_exists(BASE_PATH."/assets/gateways/{$paymentGateway[gateway]}/config.php"))
		{
			require_once BASE_PATH."/assets/gateways/{$paymentGateway[gateway]}/config.php";
			$gateways[$paymentGateway[gateway]] = $gatewaymodule;
			$gateways[$paymentGateway[gateway]]['id'] = $paymentGateway['gateway'];
			
			if(file_exists(BASE_PATH."/assets/themes/{$config[settings][style]}/{$colorSchemeImagesDirectory}/logos/{$paymentGateway[gateway]}.png")) // See if the logo exists in the directory specific to this color scheme
				$gateways[$paymentGateway[gateway]]['logo'] = true;
			else // Check to see if it exists in the main images directory for this theme
			{
				if(file_exists(BASE_PATH."/assets/themes/{$config[settings][style]}/images/logos/{$paymentGateway[gateway]}.png")) // Check for gateway logo
					$gateways[$paymentGateway[gateway]]['logo'] = true;
			}
		}
	}
	
	//print_r($_SESSION['cartTotalsSession']); exit;
	
	/*//For testing
	echo "Shipping:<br />";
	foreach($shippingAddressFinal as $key => $value)
	{
		echo "{$key}: {$value}<br />";
	}
	echo "<br />Billing:<br />";
	foreach($billingAddressFinal as $key => $value)
	{
		echo "{$key}: {$value}<br />";
	}
	*/

	// Get new taxablePrice based on new settings	
	
	
	//echo $_SESSION['cartTotalsSession']['taxablePrice']; 
	//if($_SESSION['cartTotalsSession']['taxablePrice'] > 0)
	//{
	
	$newTaxablePrice = 0;	
	foreach($_SESSION['cartItemsSession'] as $key => $value)
	{
		$addPriceForTax = 0;
		$addPriceDigitalForTax = 0;		
		if($value['itemDetails']['taxable'] or $value['item_type'] == 'digital') // See if the item is either taxable or a digital item
		{			
			if($value['paytype'] != 'cred') // Make sure things paid for with credits aren't taxed
			{
				switch($value['item_type'])
				{
					case 'digital':
					case 'collection':
						$addPriceDigitalForTax = ($_SESSION['tax']['tax_digital']) ? $value['price_total'] : 0; //$value['price_total'] / lineItemPriceTotal
					break;				
					case 'print':
					case 'product':
					case 'package':
						$addPriceForTax = ($_SESSION['tax']['tax_prints']) ? $value['price_total'] : 0;
						//echo $value['price_total']; 
					break;
					case 'membership': // Not needed
						$addPriceDigitalForTax = ($_SESSION['tax']['tax_ms']) ? $value['price_total'] : 0;
					break;
					case 'subscription':
						$addPriceDigitalForTax = ($_SESSION['tax']['tax_subs']) ? $value['price_total'] : 0;
					break;
					case 'credits':
						$addPriceDigitalForTax = ($_SESSION['tax']['tax_credits']) ? $value['price_total'] : 0;
					break;
				}
				//echo $addPriceDigitalForTax . "<br>";
				$newTaxablePrice+= $addPriceForTax;
				$newTaxableDigitalPrice+= $addPriceDigitalForTax;				
			}
		}
	}
	
	//echo "<br>newTaxablePrice: {$newTaxablePrice}";
	//echo "<br>newTaxableDigitalPrice: {$newTaxableDigitalPrice}"; exit;
	
	//totalDiscounts	
	if($newTaxablePrice > 0)
		$newTaxablePrice = $newTaxablePrice - $_SESSION['cartTotalsSession']['totalPhysicalDiscounts'];
	if($newTaxableDigitalPrice > 0)
		$newTaxableDigitalPrice = $newTaxableDigitalPrice - $_SESSION['cartTotalsSession']['totalDigitalDiscounts'];
	
	//echo "<br>newTaxablePrice: {$newTaxablePrice}";
	//echo "<br>newTaxableDigitalPrice: {$newTaxableDigitalPrice}"; exit;
	
	//echo "<br>totalPhysicalDiscounts: ".$_SESSION['cartTotalsSession']['totalPhysicalDiscounts'];
	//echo "<br>totalDigitalDiscounts: ".$_SESSION['cartTotalsSession']['totalDigitalDiscounts']; exit;
	
	//echo $newTaxableDigitalPrice;
	
	$_SESSION['cartTotalsSession']['taxablePrice'] = ($_SESSION['cartTotalsSession']['clearTax']) ? 0 : $newTaxablePrice;
	$_SESSION['cartTotalsSession']['taxableDigitalPrice'] = ($_SESSION['cartTotalsSession']['clearTax']) ? 0 : $newTaxableDigitalPrice;
	
	//exit;

	//echo $_SESSION['cartTotalsSession']['taxablePrice'];

	// Get new taxable total in case things have changed
	$recalcTaxAphysical = round($_SESSION['cartTotalsSession']['taxablePrice']*($_SESSION['tax']['tax_a_default']/100),2);
	$recalcTaxBphysical = round($_SESSION['cartTotalsSession']['taxablePrice']*($_SESSION['tax']['tax_b_default']/100),2);
	$recalcTaxCphysical = round($_SESSION['cartTotalsSession']['taxablePrice']*($_SESSION['tax']['tax_c_default']/100),2);
	
	$recalcTaxAdigital = round($_SESSION['cartTotalsSession']['taxableDigitalPrice']*($_SESSION['tax']['tax_a_digital']/100),2);
	$recalcTaxBdigital = round($_SESSION['cartTotalsSession']['taxableDigitalPrice']*($_SESSION['tax']['tax_b_digital']/100),2);
	$recalcTaxCdigital = round($_SESSION['cartTotalsSession']['taxableDigitalPrice']*($_SESSION['tax']['tax_c_digital']/100),2);
	
	$recalcTaxA = $recalcTaxAphysical + $recalcTaxAdigital;
	$recalcTaxB = $recalcTaxBphysical + $recalcTaxBdigital;
	$recalcTaxC = $recalcTaxCphysical + $recalcTaxCdigital;

	if($shippingDetails['taxable'] and $_SESSION['tax']['tax_shipping']) // Check if shipping should be taxed
	{
		$recalcTaxA += round($shippingDetails['total']*($_SESSION['tax']['tax_a_default']/100),2);
		$recalcTaxB += round($shippingDetails['total']*($_SESSION['tax']['tax_b_default']/100),2);
		$recalcTaxC += round($shippingDetails['total']*($_SESSION['tax']['tax_c_default']/100),2);
	}
	
	//echo print_r($shippingDetails); exit;
	
	$recalcTaxTotal = $recalcTaxA + $recalcTaxB + $recalcTaxC; // Total tax	
	
	$cartGrandTotal = ($_SESSION['cartTotalsSession']['priceSubTotal']+$shippingDetails['total'])-$_SESSION['cartTotalsSession']['totalDiscounts'];// Add shipping cost and tax to get new total
	
	if(!$_SESSION['cartTotalsSession']['clearTax']) // No tax coupon
		$cartGrandTotal += $recalcTaxTotal;
	
	$parms['noDefault'] = true;
	
	// Update sessions
	$_SESSION['cartTotalsSession']['cartGrandTotal'] = $cartGrandTotal;
	$_SESSION['cartTotalsSession']['taxA'] = $recalcTaxA; // Add updated tax a to session
	$_SESSION['cartTotalsSession']['taxB'] = $recalcTaxB; // Add updated tax b to session
	$_SESSION['cartTotalsSession']['taxC'] = $recalcTaxC; // Add updated tax c to session
	$_SESSION['cartTotalsSession']['taxTotal'] = ($_SESSION['cartTotalsSession']['clearTax']) ? 0 : $recalcTaxTotal; // Add updated tax total to session
	$_SESSION['cartTotalsSession']['taxALocal'] = getCorrectedPrice($recalcTaxA,$parms); // Add updated local tax a to session
	$_SESSION['cartTotalsSession']['taxBLocal'] = getCorrectedPrice($recalcTaxB,$parms); // Add updated local tax b to session
	$_SESSION['cartTotalsSession']['taxCLocal'] = getCorrectedPrice($recalcTaxC,$parms); // Add updated local tax c to session
	$_SESSION['cartTotalsSession']['shippingTotal'] = $shippingDetails['total']; // Add shipping total to cartTotalsSession
	$_SESSION['cartTotalsSession']['cartGrandTotalLocal'] = getCorrectedPrice($cartGrandTotal,$parms);
	$_SESSION['cartTotalsSession']['shippingTotalLocal']['display'] = $shippingDetails['price']['display']; //$_SESSION['cartTotalsSession']['shippingTotalLocal'] = getCorrectedPrice($shippingDetails['price']['raw'],$parms);
	
	//echo $shippingDetails['price']['display'];

	if($_SESSION['cartTotalsSession']['shippingRequired']) // Create step numbers depending on if shipping is needed or not
		$stepNumber = array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4);
	else
		$stepNumber = array('a' => 1, 'b' => 0, 'c' => 2, 'd' => 3);
	
	$currency = getCurrencyInfo($_SESSION['selectedCurrencySession']); // Get the details of the currently selected currency

	//echo 'name: '.$_SESSION['shippingAddressSession']['name']; exit;

	// Update invoice with shipping and billing info if known
	mysqli_query($db,
	"
		UPDATE {$dbinfo[pre]}invoices SET 
		invoice_mem_id='{$_SESSION[member][mem_id]}',
		exchange_rate='{$currency[exchange_rate]}',
		total='{$cartGrandTotal}',
		subtotal='{$_SESSION[cartTotalsSession][priceSubTotal]}',
		taxa_cost='{$recalcTaxA}',
		taxb_cost='{$recalcTaxB}',
		taxc_cost='{$recalcTaxC}',
		tax_ratea='{$_SESSION[tax][tax_a_default]}',
		tax_rateb='{$_SESSION[tax][tax_b_default]}',
		tax_ratec='{$_SESSION[tax][tax_c_default]}',
		ship_id='{$_SESSION[cartInfoSession][selectedShippingMethodID]}',
		shipping_cost='{$shippingDetails[total]}',
		ship_name='{$_SESSION[shippingAddressSession][name]}',
		ship_address='{$_SESSION[shippingAddressSession][address]}',
		ship_address2='{$_SESSION[shippingAddressSession][address2]}',
		ship_city='{$_SESSION[shippingAddressSession][city]}',
		ship_state='{$_SESSION[shippingAddressSession][stateID]}',
		ship_country='{$_SESSION[shippingAddressSession][countryID]}',
		ship_zip='{$_SESSION[shippingAddressSession][postalCode]}',
		ship_phone='{$_SESSION[shippingAddressSession][phone]}',
		ship_email='{$_SESSION[shippingAddressSession][email]}',
		bill_name='{$_SESSION[billingAddressSession][name]}',
		bill_address='{$_SESSION[billingAddressSession][address]}',
		bill_address2='{$_SESSION[billingAddressSession][address2]}',
		bill_city='{$_SESSION[billingAddressSession][city]}',
		bill_state='{$_SESSION[billingAddressSession][stateID]}',
		bill_country='{$_SESSION[billingAddressSession][countryID]}',
		bill_zip='{$_SESSION[billingAddressSession][postalCode]}',
		bill_phone='{$_SESSION[billingAddressSession][phone]}',
		bill_email='{$_SESSION[billingAddressSession][email]}',
		shippable='{$_SESSION[cartTotalsSession][shippingRequired]}',
		shipping_summary='{$_SESSION[cartTotalsSession][shippingSummary]}'
		WHERE invoice_id = '{$_SESSION[cartInfoSession][invoiceID]}'
	");
	mysqli_query($db,"UPDATE {$dbinfo[pre]}orders SET member_id='{$_SESSION[member][mem_id]}',checkout_lang='{$_SESSION[member][language]}' WHERE uorder_id = '{$_SESSION[uniqueOrderID]}'"); // Upade orders db
	
	try
	{
		$invoice = new invoiceTools;
		$invoice->setInvoiceID($_SESSION['cartInfoSession']['invoiceID']);
		$invoiceItemsCount = $invoice->queryInvoiceItems(); // Number of invoice items total
		$digitalInvoiceItems = $invoice->getDigitalItems();
		$physicalInvoiceItems = $invoice->getPhysicalItems();
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	try
	{
		$smarty->assign('invoiceItemsCount',$invoiceItemsCount); // Number 
		$smarty->assign('digitalInvoiceItems',$digitalInvoiceItems); // Digital invoice items
		$smarty->assign('physicalInvoiceItems',$physicalInvoiceItems); // Physical invoice items
		$smarty->assign('stepNumber',$stepNumber); // Shipping address info
		$smarty->assign('shippingDetails',$shippingDetails); // Shipping address info
		$smarty->assign('gateways',$gateways); // Payment gateways info
		
		$smarty->assign('emailNeeded',true); // Email address is still needed before checking out
			
		if($_SESSION['cartTotalsSession']['cartGrandTotalLocal']['raw'] <= 0) // Only show the gateways if the total is greater than 0 // Used to use cartGrandTotalLocal - totalLocal
			$smarty->assign('freeCart',true); // Free cart?		
		
		$smarty->assign('shippingAddress',$_SESSION['shippingAddressSession']); // Shipping address info
		$smarty->assign('billingAddress',$_SESSION['billingAddressSession']); // Billing address info
		$smarty->assign('cartInfo',$_SESSION['cartInfoSession']); // Cart info session
		$smarty->assign('cartTotals',$_SESSION['cartTotalsSession']); // Cart totals session		
		$smarty->display('cart.review.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>