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
	
	// See if a tax/vat ID or notes were passed
	if($taxID or $cartNotes)
	{
		$_SESSION['cartInfoSession']['taxID'] = $taxID;
		$_SESSION['cartInfoSession']['cartNotes'] = $cartNotes;		
		@mysqli_query($db,"UPDATE {$dbinfo[pre]}invoices SET taxid='{$taxID}',cart_notes='{$cartNotes}' WHERE invoice_id = '{$_SESSION[cartInfoSession][invoiceID]}'");
	}
	
	/*
	* An account must not be required or member is already logged in with an account go to shipping page
	*/
	if($_SESSION['cartTotalsSession']['shippingRequired'])
		header("location: cart.shipping.php");
	else
		header("location: cart.review.php");
	
	if($db) mysqli_close($db); // Close any database connections
?>