<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 1-25-2011
	*  Modified: 1-25-2011
	******************************************************************/
	
	//sleep(3);
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','contact'); // Page ID
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
	
	$shipPercentage = 1;
	
	$_SESSION['cartTotalsSession']['clearShipping'] = false; // Reset free shipping to false first just in case
	
	if($_SESSION['cartCouponsArray'])
	{
		foreach($_SESSION['cartCouponsArray'] as $couponKey => $coupon) // Check for free shipping coupon
		{
			if($coupon['promotype'] == 'freeship')
				$_SESSION['cartTotalsSession']['clearShipping'] = true;
		}
	}
	
	//echo $_SESSION['cartTotalsSession']['clearShipping'];
	
	// Search for country
	if($shippingCountry)
	{
		$countryResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}countries WHERE country_id = '{$shippingCountry}' AND deleted = 0");
		$countryRows = mysqli_num_rows($countryResult);
		if($countryRows)
		{
			$country = mysqli_fetch_array($countryResult);
			$shipPercentage = $country['ship_percentage']/100;
		}
	}		
	
	// Search for state
	if($shippingState)
	{
		$stateResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE state_id = '{$shippingState}' AND deleted = 0");
		$stateRows = mysqli_num_rows($stateResult);
		if($stateRows)
		{
			$state = mysqli_fetch_array($stateResult);
			$shipPercentage = $state['ship_percentage']/100;
		}
	}
	
	// Search for zip
	if($shippingPostalCode)
	{
		$zipResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}zipcodes WHERE zipcode = '{$shippingPostalCode}' AND deleted = 0");
		$zipRows = mysqli_num_rows($zipResult);
		if($zipRows)
		{
			$zip = mysqli_fetch_array($zipResult);
			$shipPercentage = $zip['ship_percentage']/100;
		}
	}
	
	function shippingMethodsList($shipping)
	{
		global $shipPercentage, $config, $dbinfo, $selectedLanguage, $db;
		
		$shippableTotal = $_SESSION['cartTotalsSession']['shippableTotal'];
		$shippableCount = $_SESSION['cartTotalsSession']['shippableCount'];
		$shippableWeight = $_SESSION['cartTotalsSession']['shippableWeight'];
		
		//echo $shipping['calc_type']."<br>";
		
		switch($shipping['calc_type'])
		{
			case 1: // Weight
				$rangeResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}shipping_ranges WHERE ship_id = '{$shipping[ship_id]}' AND fromrange <= '{$shippableWeight}' AND torange >= '{$shippableWeight}'");
				$rangeRows = mysqli_num_rows($rangeResult);
				
				//echo $shipping['cost_type']; 
				
				if($rangeRows)
				{
					$range = mysqli_fetch_array($rangeResult);
					if($shipping['cost_type'] == 1) // Fixed amount
						$shippingPrice = $range['price']*$shipPercentage;
					else // Percentage
						$shippingPrice = ($shippableTotal*($range['price']/100))*$shipPercentage;
				}
				else // No range found
					$shipping['price']['raw'] = 0;
					
				$shippingSummary2=
				"<shipping>
				</shipping>
				";
				
			break;
			case 2: // Subtotal
				
				$rangeResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}shipping_ranges WHERE ship_id = '{$shipping[ship_id]}' AND fromrange <= '{$shippableTotal}' AND torange >= '{$shippableTotal}'");
				$rangeRows = mysqli_num_rows($rangeResult);
				if($rangeRows)
				{
					$range = mysqli_fetch_array($rangeResult);
					if($shipping['cost_type'] == 1) // Fixed amount
						$shippingPrice = $range['price']*$shipPercentage;
					else // Percentage
					{
						$shippingPrice = ($shippableTotal*($range['price']/100))*$shipPercentage;
						$shippingPrice = round($shippingPrice,2); // Round the shipping down to 2 decimals just in case	
					}
				}
				else // No range found
					$shippingPrice = 0;
				
				//echo "rr: {$rangeRows}: ship price: {$shippingPrice}";
				
				$shippingSummary2=
				"<shipping>
				</shipping>
				";
				
			break;
			case 3: // Flat Rate				
				if($shipping['cost_type'] == 1) // Fixed amount
					$shippingPrice = $shipping['flat_rate']*$shipPercentage;
				else // Percentage
				{
					$shippingPrice = ($shippableTotal*($shipping['flat_rate']/100))*$shipPercentage;
					$shippingPrice = round($shippingPrice,2); // Round the shipping down to 2 decimals just in case	
				}
				
				//echo "ship cost type: " . $shipping['flat_rate'];
					
				$shippingSummary2=
				"<shipping>
				</shipping>
				";
					
			break;
			case 4: // Quantity
				$rangeResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}shipping_ranges WHERE ship_id = '{$shipping[ship_id]}' AND fromrange <= '{$shippableCount}' AND torange >= '{$shippableCount}'");
				$rangeRows = mysqli_num_rows($rangeResult);
				
				//echo 'sc'.$shippableCount;
				
				if($rangeRows)
				{
					$range = mysqli_fetch_array($rangeResult);
					if($shipping['cost_type'] == 1) // Fixed amount
						$shippingPrice = $range['price']*$shipPercentage;
					else // Percentage
					{
						$shippingPrice = ($shippableTotal*($range['price']/100))*$shipPercentage;
						$shippingPrice = round($shippingPrice,2); // Round the shipping down to 2 decimals just in case	
					}
				}
				else // No range found
					$shippingPrice = 0;
					
				$shippingSummary2=
				"<shipping>
				</shipping>
				";
				
			break;
		}
				
		if($_SESSION['cartTotalsSession']['additionalShipping']) // Add any additional shipping from prints, products, packages
			$shippingPrice+=$_SESSION['cartTotalsSession']['additionalShipping'];
		
		$shipping['total'] = ($_SESSION['cartTotalsSession']['clearShipping']) ? 0 : $shippingPrice; // Clear the shipping?
		
		$shipping['title'] = ($shipping['title_'.$selectedLanguage]) ? $shipping['title_'.$selectedLanguage] : $shipping['title']; // Choose the correct language
		$shipping['description'] = ($shipping['description_'.$selectedLanguage]) ? $shipping['description_'.$selectedLanguage] : $shipping['description']; // Choose the correct language
		
		if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_shipping'] and $shipping['taxable']) // See if tax should be included in prices
		{
			$priceParms['taxInc'] = true;
			$shipping['taxInc'] = true;
		}
		
		$priceParms['noDefault'] = true;
		
		$shipping['price'] = getCorrectedPrice($shippingPrice,$priceParms);
		
		return $shipping;
	}
	
	// Find groups that this country belongs to
	$countryGroupsResult = mysqli_query($db,"SELECT group_id FROM {$dbinfo[pre]}groupids WHERE mgrarea = 'countries' AND item_id = '{$shippingCountry}' AND item_id != 0");
	while($countryGroup = mysqli_fetch_array($countryGroupsResult))
	{
		$countryGroups[] = $countryGroup['group_id'];
	}
	$countryGroups[] = 0; // Used to fix empty countryGroupsFlat value in query
	
	//print_r($countryGroups);
	
	$countryGroupsFlat = implode(",",$countryGroups);

	// Find shipping regions
	$shippingRegionResult = mysqli_query($db,
	"
		SELECT {$dbinfo[pre]}shipping.ship_id FROM {$dbinfo[pre]}regionids 
		LEFT JOIN {$dbinfo[pre]}shipping 
		ON {$dbinfo[pre]}regionids.item_id = {$dbinfo[pre]}shipping.ship_id  
		WHERE {$dbinfo[pre]}regionids.mgrarea='shipping' 
		AND {$dbinfo[pre]}shipping.active = '1' 
		AND {$dbinfo[pre]}shipping.deleted = '0' 
		AND (
			 ({$dbinfo[pre]}regionids.reg_type='z' AND {$dbinfo[pre]}regionids.reg_id = '{$zip[zipcode_id]}') 
			 OR ({$dbinfo[pre]}regionids.reg_type='g' AND {$dbinfo[pre]}regionids.reg_id IN ({$countryGroupsFlat})) 
			 OR ({$dbinfo[pre]}regionids.reg_type='c' AND {$dbinfo[pre]}regionids.reg_id = '{$country[country_id]}') 
			 OR ({$dbinfo[pre]}regionids.reg_type='s' AND {$dbinfo[pre]}regionids.reg_id = '{$state[state_id]}')
			 )
		ORDER BY {$dbinfo[pre]}shipping.sortorder
	");
	$shippingRegionRows = mysqli_num_rows($shippingRegionResult);
	while($shippingRegion = mysqli_fetch_array($shippingRegionResult))
	{
		//$shippingMethods[$shippingRegion['ship_id']] = shippingMethodsList($shippingRegion);
		$shippingIDs[] = $shippingRegion['ship_id'];
	}
	
	//echo "c{$countryRows}-s{$stateRows}-z{$zipRows}"; exit;
	
	// Find what shipping methods are available for the region selected
	// Check state first or state allows all
	// If no state go to country
	
	// Calculate shipping totals
	
	// Calculate percentage if needed
	
	// If no shipping through regions exists then we show all available shipping methods
	
	// Output values
	
	// Everywhere shipping methods
	$shippingEverywhereResult = mysqli_query($db,"SELECT ship_id FROM {$dbinfo[pre]}shipping WHERE region = 1 AND deleted = 0 ORDER BY sortorder");
	$shippingEverywhereRows = mysqli_num_rows($shippingEverywhereResult);
	while($shippingEverywhere = mysqli_fetch_assoc($shippingEverywhereResult))
	{
		//$shippingMethods[$shippingMethod['ship_id']] = shippingMethodsList($shippingMethod);
		$shippingIDs[] = $shippingEverywhere['ship_id'];
	}
	
	// New in 4.4.3
	if(count($shippingIDs) > 0)	
		$shippingIDFlat = implode(',',$shippingIDs);
	else
		$shippingIDFlat = 0;
	
	$shippingMethodResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}shipping WHERE ship_id IN ({$shippingIDFlat}) ORDER BY sortorder");
	$shippingMethodRows = mysqli_num_rows($shippingMethodResult);
	while($shippingMethod = mysqli_fetch_assoc($shippingMethodResult))
	{
		$shippingMethods[$shippingMethod['ship_id']] = shippingMethodsList($shippingMethod);
	}
	
	$_SESSION['shippingMethodsSession'] = $shippingMethods; // assign shipping methods to a session
	
	// Check for free shipping coupon
	
	//print_k($_SESSION['shippingMethodsSession']);
	
	try
	{
		$smarty->assign('cartTotals',$_SESSION['cartTotalsSession']);
		$smarty->assign('postVars',$_POST);
		$smarty->assign('shipPercentage',$shipPercentage);
		$smarty->assign('shippingMethods',$shippingMethods);
		$smarty->display('shipping.methods.tpl');
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>