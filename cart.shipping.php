<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','cartShipping'); // Page ID
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
	
	define('META_TITLE',$lang['shipping'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	//require_once BASE_PATH.'/assets/classes/mediatools.php';
	
	if(!$_SESSION['uniqueOrderID']) // Make sure an order ID was created and if not die
		die("No order ID was passed to the checkout system");
	
	if($_POST['country'])
		$_SESSION['shippingAddressSession']['country'] = $_POST['country']; // Update selected country on post
		
	if($_POST['state'])
		$_SESSION['shippingAddressSession']['state'] = $_POST['state']; // Update selected state on post
		
	if($_POST['zip'])
		$_SESSION['shippingAddressSession']['zip'] = $_POST['zip']; // Update entered zip on post
	
	if($_SESSION['loggedIn']) // Member is logged in - grab address info
	{	
		if(!$_SESSION['shippingAddressSession'])
		{
			$_SESSION['shippingAddressSession']['firstName'] = $_SESSION['member']['f_name'];
			$_SESSION['shippingAddressSession']['lastName'] = $_SESSION['member']['l_name'];
			$_SESSION['shippingAddressSession']['name'] = $_SESSION['member']['f_name']." ".$_SESSION['member']['l_name'];		
			$_SESSION['shippingAddressSession']['address'] = $_SESSION['member']['primaryAddress']['address'];
			$_SESSION['shippingAddressSession']['address2'] = $_SESSION['member']['primaryAddress']['address_2'];
			$_SESSION['shippingAddressSession']['email'] = $_SESSION['member']['email'];
			$_SESSION['shippingAddressSession']['phone'] = $_SESSION['member']['phone'];
			$_SESSION['shippingAddressSession']['city'] = $_SESSION['member']['primaryAddress']['city'];
			$_SESSION['shippingAddressSession']['countryID'] = $_SESSION['member']['primaryAddress']['countryID'];
			$_SESSION['shippingAddressSession']['stateID'] = $_SESSION['member']['primaryAddress']['stateID'];
			$_SESSION['shippingAddressSession']['postalCode'] = $_SESSION['member']['primaryAddress']['postal_code'];
			//if($_SESSION['shippingAddressSession']['countryID'] or $_SESSION['shippingAddressSession']['stateID'] or $_SESSION['shippingAddressSession']['stateID']) // If no address info exists then we will have to default to them entering it manually
			//$addressExists = true;	
		}
	}
	
	/*
	* Get countries list
	*/
	$smarty->assign('countries',getCountryList($selectedLanguage));
	
	/*
	* Get shipping states list
	*/
	if($_SESSION['shippingAddressSession']['stateID']) // Only if state has already been selected
	{
		$stateResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE ((active = 1 AND deleted = 0) OR state_id = '{$_SESSION[shippingAddressSession][stateID]}') AND country_id = '{$_SESSION[shippingAddressSession][countryID]}'"); // Select states
		while($state = mysqli_fetch_assoc($stateResult))
		{
			$states[$state['state_id']] = $state['name']; // xxxxx languages	
		}
		$smarty->assign('shippingStates',$states);
		unset($states);
	}
	
	/*
	* Get billing states list
	*/
	if($_SESSION['billingAddressSession']['stateID']) // Only if state has already been selected
	{
		$stateResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE ((active = 1 AND deleted = 0) OR state_id = '{$_SESSION[billingAddressSession][stateID]}') AND country_id = '{$_SESSION[billingAddressSession][countryID]}'"); // Select states
		while($state = mysqli_fetch_assoc($stateResult))
		{
			$states[$state['state_id']] = $state['name']; // xxxxx languages	
		}
		$smarty->assign('billingStates',$states);
		unset($states);
	}	
	
	try
	{
		if($_SESSION['cartInfoSession']['selectedShippingMethodID']) $smarty->assign('selectedShippingMethodID',$_SESSION['cartInfoSession']['selectedShippingMethodID']);
		$smarty->assign('shippingAddress',$_SESSION['shippingAddressSession']);
		$smarty->assign('addressExists',$addressExists);
		$smarty->display('cart.shipping.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>