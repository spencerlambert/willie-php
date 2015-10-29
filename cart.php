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
	define('INIT_SMARTY',true); // Use smarty
	
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

	define('META_TITLE',$lang['cart'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';
	
	//echo $_COOKIE['cart']['uniqueOrderID']; exit;
	
	if(!$_SESSION['uniqueOrderID']) // Create a unique order ID if one isn't already created
	{
		// Check cookie
		if($_COOKIE['cart']['uniqueOrderID'])
			$_SESSION['uniqueOrderID'] = $_COOKIE['cart']['uniqueOrderID'];
		else
		{
			$newUniqueOrderID = create_unique2();			
			$_SESSION['uniqueOrderID'] = $newUniqueOrderID;	
			
			// Set Cookie
			if($config['useCookies']) setcookie("cart[uniqueOrderID]", $newUniqueOrderID, time()+60*60*24*30, "/", $host[0]); // Set a cart id cookie		
		}
	}
	
	// Check if this unique order id is already in the db with a status other than incomplete
	$orderCheckResult = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS uorder_id FROM {$dbinfo[pre]}orders WHERE uorder_id = '{$_SESSION[uniqueOrderID]}' AND order_status != '2'");
	if(getRows())
		$_SESSION['uniqueOrderID'] = create_unique2(); // The unique order id is already in the db - create a new one
		
	if(!$_SESSION['uniqueOrderID']) // Make sure an order ID was created and if not die
		die("No order ID was ever created");

	if(!$miniCart)
		unset($_SESSION['currentMode']); // Unset the gallery mode

	//print_k($_SESSION['cartTotalsSession']); exit; // Testing

	try
	{	
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$id = k_decrypt($id);
			if($mediaID)
			{
				$mediaID = k_decrypt($mediaID);
				idCheck($mediaID); // Make sure ID is numeric
			}
			if($profileID)
			{
				$profileID = k_decrypt($profileID);
				idCheck($profileID); // Make sure ID is numeric
			}
		}
				
		/*
		* Update the pay type on a cart item
		*/
		if($cartMode == 'updatePayType')
		{
			$cid = k_decrypt($cid);
			mysqli_query($db,"UPDATE {$dbinfo[pre]}invoice_items SET paytype='{$payType}' WHERE oi_id = '{$cid}'");			
			@mysqli_query($db,"UPDATE {$dbinfo[pre]}commission SET comtype='{$payType}' WHERE oitem_id = '{$cid}'"); // Update the pay type in the commissions table
		}
		
		/*
		* Apply a coupon code
		*/
		if($cartMode == 'applyCouponCode')
		{	
			$couponCode = strtoupper($couponCode); // Make sure coupon code is uppercase

			$promotionsResult = mysqli_query($db,
				"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}promotions 
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}promotions.promo_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'promotions')
				WHERE {$dbinfo[pre]}promotions.active = 1 
				AND {$dbinfo[pre]}promotions.deleted = 0
				AND ({$dbinfo[pre]}promotions.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				AND ({$dbinfo[pre]}promotions.quantity = '' OR {$dbinfo[pre]}promotions.quantity > '0') 
				AND {$dbinfo[pre]}promotions.promo_code = '{$couponCode}'
				"
			);
			if($returnRows = getRows()) //$returnRows = mysqli_num_rows($promotionsResult)
			{
				// Remove previous coupon first
				unset($_SESSION['cartCouponsArray']);
				
				$promotions = mysqli_fetch_array($promotionsResult);
				
				if(!$_SESSION['cartCouponsArray'][$promotions['promo_id']]) // Make sure coupon wasn't already added
				{
					if($promotions['quantity'] > 0) // Make sure there are some remaining
					{
						if($promotions['oneuse'] and !$_SESSION['loggedIn'])
							$messageArray[] = 'couponNeedsLogin'; // Need to login warning
						else
						{						
							$memberCouponUsageRows = 0; // No usage
							
							if($promotions['oneuse'])
							{
								// Check to make sure member did not already use this coupon
								$memberCouponUsageResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}usedcoupons WHERE mem_id = '{$_SESSION[member][mem_id]}' AND promo_id = '{$promotions[promo_id]}'");
								$memberCouponUsageRows = mysqli_num_rows($memberCouponUsageResult);
							}
							
							if($memberCouponUsageRows == 0) // User is ok to use this coupon
							{
								if($promotions['minpurchase'] and $promotions['minpurchase'] > $_SESSION['cartTotalsSession']['priceSubTotal'] and $promotions['promotype'] != 'bulk') // Check if the minimum is met
								{
									$messageArray[] = 'couponMinumumWarn'; // Not enough total warning
								}
								else
								{								
									$_SESSION['cartCouponsArray'][$promotions['promo_id']] = promotionsList($promotions);
									$messageArray[] = 'couponApplied';
								}
							}
							else
								$messageArray[] = 'couponAlreadyUsed'; // Already used coupon warning	
						}
					}
					else
						$messageArray[] = 'couponFailed'; // All used up
				}
			}
			else
			{
				$messageArray[] = 'couponFailed';
			}
			
			$smarty->assign('message',$messageArray); // Assign the coupon status to smarty
		}
		
		/*
		* Remove coupon
		*/
		if($cartMode == 'removeCoupon')
		{
			unset($_SESSION['cartCouponsArray'][$couponID]);
		}
		
		/*
		* Update cart quantities
		*/
		if($cartMode == 'updateQuantities')
		{	
			if($quantity)
			{
				foreach($quantity as $quantityCartID => $quantityCount)
				{
					if($quantityCount > 0)
					{
						// [todo] check to make sure if there are enough available first
						// [todo] should we update price_total here???
						
						// Update the price_totals
						$cartItemResult = mysqli_query($db,"SELECT price,paytype,credits FROM {$dbinfo[pre]}invoice_items WHERE oi_id = '{$quantityCartID}'");
						$cartItem = mysqli_fetch_assoc($cartItemResult);
						
						$newPriceTotal = $cartItem['price']*$quantityCount;
						$newCreditTotal = $cartItem['credits']*$quantityCount;
						/*
						if($cartItem['paytype'] == 'cur')
							$newPriceTotal = $cartItem['price']*$quantityCount;
						else
							$newCreditTotal = $cartItem['credits']*$quantityCount;
						*/
						//mysqli_query($db,"UPDATE {$dbinfo[pre]}invoice_items SET quantity='{$quantityCount}' WHERE oi_id = '{$quantityCartID}'");
						mysqli_query($db,"UPDATE {$dbinfo[pre]}invoice_items SET quantity='{$quantityCount}',price_total='{$newPriceTotal}',credits_total='{$newCreditTotal}' WHERE oi_id = '{$quantityCartID}'");
						
						@mysqli_query($db,"UPDATE {$dbinfo[pre]}commission SET item_qty='{$quantityCount}' WHERE oitem_id = '{$quantityCartID}'"); // Update the quantity in the commissions table
						
					}
				}
			}
		}
		
		/*
		* Select cart from database
		*/
		$cartResult = mysqli_query($db,
			"
			SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}orders 
			LEFT JOIN {$dbinfo[pre]}invoices 
			ON {$dbinfo[pre]}orders.order_id = {$dbinfo[pre]}invoices.order_id
			WHERE {$dbinfo[pre]}orders.uorder_id = '{$_SESSION[uniqueOrderID]}'
			"
		);
		//$cartRows = mysqli_num_rows($cartResult);
		if($cartRows = getRows())
		{
			$cart = mysqli_fetch_array($cartResult); // Select cart details
			$cartID = $cart['order_id'];
			$invoiceID = $cart['invoice_id'];
		}
		else
		{
			if(mysqli_query($db,"INSERT INTO {$dbinfo[pre]}orders (uorder_id,order_status) VALUES ('{$_SESSION[uniqueOrderID]}','2')")) // No cart exists - create one
			{
				$cartID = mysqli_insert_id($db);
				
				mysqli_query($db,"INSERT INTO {$dbinfo[pre]}invoices (order_id) VALUES ('{$cartID}')"); // Create an invoice
				$invoiceID = mysqli_insert_id($db);
			}
			else
				die("Creating a cart entry in the DB failed"); // Fail if a cart/order cannot be created in the db
		}
		
		/*
		* Remove an item from the cart 
		*/
		if($mode == 'remove' or $edit) 
		{
			if($edit) $cid = $edit; // Set the cart ID from the edit field
			
			$cid = k_decrypt($cid);// Cart item ID
			
			$removeItemResult = mysqli_query($db,
				"
				SELECT SQL_CALC_FOUND_ROWS oi_id,item_type FROM {$dbinfo[pre]}invoice_items 
				WHERE oi_id = '{$cid}'
				"
			); // First grab the item from the invoice items db
			if($removeItemRows = getRows()) // $removeItemRows = mysqli_num_rows($removeItemResult)
			{
				$removeItem = mysqli_fetch_array($removeItemResult);
				
				$deleteArray[] = $removeItem['oi_id']; // Add id to the delete array
					
				if($removeItem['item_type'] == 'package') // Check if this is a package
				{
					$removePackageItemResult = mysqli_query($db,
						"
						SELECT oi_id,pack_invoice_id FROM {$dbinfo[pre]}invoice_items 
						WHERE pack_invoice_id = '{$removeItem[oi_id]}'
						"
					); // Select package items and add them to the array
					while($removePackageItem = mysqli_fetch_array($removePackageItemResult))
						$deleteArray[] = $removePackageItem['oi_id']; // Add id to the delete array					
				}
				
				$deleteArrayFlat = implode(",",$deleteArray);
				
				mysqli_query($db,"DELETE FROM {$dbinfo[pre]}invoice_items WHERE oi_id IN ($deleteArrayFlat)"); // Delete invoice items
				mysqli_query($db,"DELETE FROM {$dbinfo[pre]}invoice_options WHERE invoice_item_id IN ($deleteArrayFlat)"); // Delete invoice options				
				@mysqli_query($db,"DELETE FROM {$dbinfo[pre]}commission WHERE oitem_id IN ($deleteArrayFlat)"); // Delete commission entry
			}			
			//print_r($deleteArray); exit;
			//mysqli_query($db,"UPDATE {$dbinfo[pre]}invoice_items SET deleted='1' WHERE oi_id = '{$cid}'"); // Mark the invoice item as deleted in the DB
			unset($_SESSION['packagesInCartSession'][$cid]); // Remove this package from the session
		}
		
		/*
		* Add an item to the cart
		*/
		if($mode == 'add')
		{			
			if(preg_match("/[^A-Za-z0-9_-]/",$type))
			{
				header("location: error.php?eType=invalidQuery");
				exit;
			}			
			
			if($option){ foreach($option as $optionValue){ if($optionValue) $optionsPassed = 1; } } // Check to see if any options were passed
			
			if($optionsPassed or $type == 'package')
				$hasOptions = 1;
			else
				$hasOptions = 0;
				
			if($mediaID) // Has a media ID check the owner
			{
				$mediaOwnerResult = mysqli_query($db,"SELECT owner FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'");	
				$mediaOwner = mysqli_fetch_assoc($mediaOwnerResult);
				$owner = $mediaOwner['owner'];
				
				if($owner) // There is a contributor on the purchase - find commission and make entry into commissions table
				{
					if($owner != $_SESSION['member']['mem_id']) // Make sure the owner is not the current member
					{
						$commission['mediaID'] = $mediaID;					
						$commission['owner'] = $owner;
						
						// Select member account
						$contrObj = new memberTools($owner);
						$contr = $contrObj->getMemberInfoFromDB($owner);
						
						if($contr) // Make sure member exists
						{						
							$contrMembership =  $contrObj->getMembershipInfoFromDB($contr['membership']); // Select membership
						
							if($contrMembership['allow_selling']) // Check if membership allows selling
							{
								$commission['status'] = true; // Member can receive commission
								
								if($contr['com_source'] == 1) // Use membership comlevel
									$commission['memPercent'] = $contrMembership['commission'];
								else
									$commission['memPercent'] = $contr['com_level'];
							}
						}
						//print_r($member); // testing
						//print_r($membership);
						//exit;					
						//echo $memberCommissionLevel; exit;
					}
				}
			}
							
			if($type == 'package')
			{
				$packageTotalMediaNeeded = count($packageItemMedia);
				$packageTotalMediaFilled = 0;
				if($packageItemMedia)
				{
					foreach($packageItemMedia as $packageItemMediaValue)
					{
						if($packageItemMediaValue)
							$packageTotalMediaFilled++;
					}
				}
			}
			
			switch($type) // Set only digital to a non physical item and make everything else physical
			{
				case 'digital':
				case 'collection':
					$physicalItem = 0;
				break;
				default:
					$physicalItem = 1;
				break;
			}
			
			$defaultPayType = (currencyCartStatus()) ? 'cur' : 'cred'; // Find the default pay type that this item should use			
			$commission['comtype'] = $defaultPayType; // Set the correct paytype for commissions
			
			if($type == 'credits') $defaultPayType = 'cur'; // Force credits to use currency type
			
			mysqli_query($db,
				"
				INSERT INTO {$dbinfo[pre]}invoice_items 
				(
					invoice_id,
					item_type,
					item_id,
					asset_id,
					quantity,
					physical_item,
					has_options,
					item_added,
					package_media_needed,
					package_media_filled,
					paytype,
					contributor_id
				) 
				VALUES 
				(
					'{$invoiceID}',
					'{$type}',
					'{$id}',
					'{$mediaID}',
					'1',
					'{$physicalItem}',
					'{$hasOptions}',
					'{$nowGMT}',
					'{$packageTotalMediaNeeded}',
					'{$packageTotalMediaFilled}',
					'{$defaultPayType}',
					'{$owner}'
				 )
				"
			); // Insert the item into the DB
			$cartItemID = mysqli_insert_id($db);
			
			if($type == 'package') // If this is a package input all package items into the invoice items table
			{
				
				$packageResult = mysqli_query($db,
					"
					SELECT * FROM {$dbinfo[pre]}packages  
					WHERE pack_id = '{$id}'
					"
				); // Select item pricing from the db
				$package = mysqli_fetch_array($packageResult);
				
				$package['price'] = defaultPrice($package['price']); // Make sure to assign a default price if needed
														
				$package['credits'] = defaultCredits($package['credits']); // Make sure to assign default credits if needed
								
				$itemPrice = $package['price'];
				$itemCredits = $package['credits'];
				
				$optionsPrice = 0;
				$optionsCredits = 0;
				
				if($_POST['packageItem'])
				{
					foreach($_POST['packageItem'] as $key => $value)
					{
						$packageItemDetailsArray = explode("-",$value);
						/*
						echo "Item ID: {$packageItemDetailsArray[1]}<br />";
						echo "Item Type: {$packageItemDetailsArray[0]}<br />";
						echo "Key: {$key}<br />";
						echo "Quantity: {$_POST[quantity][$key]}<br /><br />";
						*/
						
						if($packageItemDetailsArray[0] == 'collection') // See if this is a physical item or not
							$packageItemPhysical = 0;
						else
							$packageItemPhysical = 1;
						
						mysqli_query($db,
							"
							INSERT INTO {$dbinfo[pre]}invoice_items 
							(
								invoice_id,
								item_type,
								item_id,
								asset_id,
								quantity,
								physical_item,
								pack_invoice_id,
								item_added,
								item_list_number
							) 
							VALUES 
							(
								'{$invoiceID}',
								'{$packageItemDetailsArray[0]}',
								'{$packageItemDetailsArray[1]}',
								'{$packageItemMedia[$key]}',
								'{$_POST[packageItemQuantity][$key]}',
								'{$packageItemPhysical}',
								'{$cartItemID}',
								'{$nowGMT}',
								'{$key}'
							 )
							"
						);
						$packageCartItemID = mysqli_insert_id($db);
						
						/*
						* Save any options that were selected
						*/
						if(${'option'.$key})
						{
							foreach(${'option'.$key} as $optionKey => $optionValue)
							{	
								if($optionValue)
								{
									$optionKeyArray = explode("-",$optionKey);
									
									if($optionKeyArray[1])
										$opSelection = $optionKeyArray[1];
									else
										$opSelection = $optionValue;
										
									// Get option price
									$optionResult = mysqli_query($db,
										"
										SELECT * FROM {$dbinfo[pre]}options   
										WHERE op_id = '{$opSelection}'
										"
									); // Select item pricing from the db
									$option = mysqli_fetch_array($optionResult);
									switch($option['price_mod'])
									{
										case "add":
											$optionsPrice+=$option['price'];
										break;
										case "sub":
											$optionsPrice-=$option['price'];
										break;
									}
									switch($option['credits_mod'])
									{
										case "add":
											$optionsCredits+=$option['credits'];
										break;
										case "sub":
											$optionsCredits-=$option['credits'];
										break;
									}
									
									mysqli_query($db,
									"
										INSERT INTO {$dbinfo[pre]}invoice_options  
										(
											option_gid,
											item_list_number,
											option_id,
											invoice_item_id,
											option_price,
											option_price_calc,
											option_credits,
											option_credits_calc
										) 
										VALUES 
										(
											'{$optionKeyArray[0]}',
											'{$key}',
											'{$opSelection}',
											'$packageCartItemID',
											'{$option[price]}',
											'{$option[price_mod]}',
											'{$option[credits]}',
											'{$option[credits_mod]}'
										 )
										"
									); // Insert the options selected into the cart
								}
							}
						}
					}
				}
				$_SESSION['packagesInCartSession'][$cartItemID] = $id; // Add an item to the packages in cart session
				
				$itemPrice+= $optionsPrice; // Price with options
				$itemCredits+= $optionsCredits; // Credits with options
				
				mysqli_query($db,
					"
					UPDATE {$dbinfo[pre]}invoice_items SET 
					price='{$itemPrice}',
					price_total='{$itemPrice}',
					credits='{$itemCredits}',
					credits_total='{$itemCredits}'
					WHERE oi_id = '{$cartItemID}'
					"
				); // Update the invoice item with the prices
			}
			else
			{
				switch($type)
				{
					/*
					* Get the subscription price and update the invoice item
					*/
					case "subscription":
						$addItemResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}subscriptions  
							WHERE sub_id = '{$id}'
							"
						); // Select item pricing from the db
						$addItem = mysqli_fetch_array($addItemResult);
						
						$itemPrice = defaultPrice($addItem['price']); // Get the price of this item
						$itemCredits = defaultCredits($addItem['credits']); // Get the credit value of this item
						
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price='{$itemPrice}',
							price_total='{$itemPrice}',
							credits='{$itemCredits}',
							credits_total='{$itemCredits}'
							WHERE oi_id = '{$cartItemID}'
							"
						); // Update the invoice item with the prices
					break;
					/*
					* Get the credits price and update the invoice item
					*/
					case "credits":
						$addItemResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}credits  
							WHERE credit_id = '{$id}'
							"
						); // Select item pricing from the db
						$addItem = mysqli_fetch_array($addItemResult);
						
						$itemPrice = defaultPrice($addItem['price']); // Get the price of this item
						
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price='{$itemPrice}',
							price_total='{$itemPrice}',
							credits='{$itemCredits}',
							credits_total='{$itemCredits}'
							WHERE oi_id = '{$cartItemID}'
							"
						); // Update the invoice item with the prices
					break;
					/*
					* Get the collection price and update the invoice item
					*/
					case "collection":
						$addItemResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}collections  
							WHERE coll_id = '{$id}'
							"
						); // Select item pricing from the db
						$addItem = mysqli_fetch_array($addItemResult);
						
						$itemPrice = defaultPrice($addItem['price']); // Get the price of this item
						$itemCredits = defaultCredits($addItem['credits']); // Get the credit value of this item
						
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price='{$itemPrice}',
							price_total='{$itemPrice}',
							credits='{$itemCredits}',
							credits_total='{$itemCredits}'
							WHERE oi_id = '{$cartItemID}'
							"
						); // Update the invoice item with the prices
					break;
					case "print":
						
						// select the media details
						$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
						$mediaInfo = new mediaList($sql);
						$media = $mediaInfo->getSingleMediaDetails('thumb');
						
						$mediaPrice = getMediaPrice($media); // Get the media price based on the license
						$mediaCredits = getMediaCredits($media); // Get the media credits based on the license
						
						$printResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}prints  
							WHERE print_id = '{$id}'
							"
						); // Select item pricing from the db
						$print = mysqli_fetch_array($printResult);
						
						$print['price'] = defaultPrice($print['price']); // Make sure to assign a default price if needed
																
						$print['credits'] = defaultCredits($print['credits']); // Make sure to assign default credits if needed
																	
						/*
						* Custom Pricing calculations
						*/
						$mediaPrintsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_prints WHERE media_id = '{$mediaID}' AND print_id = '{$id}'"); // Find if this has a customization
						if(mysqli_num_rows($mediaPrintsResult))
						{
							$mediaPrint = mysqli_fetch_array($mediaPrintsResult);
							
							if($mediaPrint['customized']) // See if this entry was customized
							{
								$print['price_calc'] = $mediaPrint['price_calc'];
								$print['price'] = defaultPrice($mediaPrint['price']);

								$print['credits'] = defaultCredits($mediaPrint['credits']);
								$print['credits_calc'] = $mediaPrint['credits_calc'];
								
								$print['quantity'] = $mediaPrint['quantity']; 	
							}
						}
						
						/*
						* Advanced Pricing calculations
						*/
						switch($print['price_calc'])
						{
							case 'add':
								$print['price'] = $mediaPrice + $print['price'];
							break;
							case 'sub':
								$print['price'] = $mediaPrice - $print['price'];
							break;
							case 'mult':
								$print['price'] = $mediaPrice * $print['price'];
							break;
						}
						
						switch($print['credits_calc'])
						{
							case 'add':
								$print['credits'] = $mediaCredits + $print['credits'];
							break;
							case 'sub':
								$print['credits'] = $mediaCredits - $print['credits'];
							break;
							case 'mult':
								$print['credits'] = $mediaCredits * $print['credits'];
							break;
						}
						
						$itemPrice = $print['price'];
						$itemCredits = $print['credits'];
						
						$optionsPrice = 0;
						$optionsCredits = 0;
						
						if($option)
						{
							// Get the price of all options - Save options
							foreach($option as $optionKey => $optionValue)
							{	
								if($optionValue)
								{
									$optionKeyArray = explode("-",$optionKey);
									
									if($optionKeyArray[1])
										$opSelection = $optionKeyArray[1];
									else
										$opSelection = $optionValue;
									
									// Get option price
									$optionResult = mysqli_query($db,
										"
										SELECT * FROM {$dbinfo[pre]}options   
										WHERE op_id = '{$opSelection}'
										"
									); // Select item pricing from the db
									$option = mysqli_fetch_array($optionResult);
									switch($option['price_mod'])
									{
										case "add":
											$optionsPrice+=$option['price'];
										break;
										case "sub":
											$optionsPrice-=$option['price'];
										break;
									}
									switch($option['credits_mod'])
									{
										case "add":
											$optionsCredits+=$option['credits'];
										break;
										case "sub":
											$optionsCredits-=$option['credits'];
										break;
									}
									
									mysqli_query($db,
									"
										INSERT INTO {$dbinfo[pre]}invoice_options  
										(
											option_gid,
											item_list_number,
											option_id,
											invoice_item_id,
											option_price,
											option_price_calc,
											option_credits,
											option_credits_calc
										) 
										VALUES 
										(
											'{$optionKeyArray[0]}',
											'{$key}',
											'{$opSelection}',
											'{$cartItemID}',
											'{$option[price]}',
											'{$option[price_mod]}',
											'{$option[credits]}',
											'{$option[credits_mod]}'
										 )
										"
									); // Insert the options selected into the cart
								}
							}
						}
						
						$itemPrice+= $optionsPrice; // Price with options
						$itemCredits+= $optionsCredits; // Credits with options
						
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price='{$itemPrice}',
							price_total='{$itemPrice}',
							credits='{$itemCredits}',
							credits_total='{$itemCredits}'
							WHERE oi_id = '{$cartItemID}'
							"
						); // Update the invoice item with the prices
						
						if($owner) // Add commission record
						{
							$commission['oitemID'] = $cartItemID;
							$commission['comTotal'] = $itemPrice;
							$commission['comCredits'] = $itemCredits;
							
							if($print['commission_type'] == 1) // 1 = Percentage | 2 = Dollar Value
							{
								$commission['itemPercent'] = $print['commission']; //com_percentage
								$commission['comTotal'] = $itemPrice;	
							}
							else
							{
								$commission['itemPercent'] = 100; // Item commission is 100% of dollar value
								$commission['comTotal'] = $print['commission_dollar'];
							}
							
							//print_k($commission); exit;
							
							if($owner != $_SESSION['member']['mem_id']) addCommissionRecord($commission); // Make sure the owner is not the current member
							
						}
					break;
					case "product":
						// select the media details
						if($mediaID)
						{
							$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
							$mediaInfo = new mediaList($sql);
							$media = $mediaInfo->getSingleMediaDetails('thumb');
						
							$mediaPrice = getMediaPrice($media); // Get the media price based on the license
							$mediaCredits = getMediaCredits($media); // Get the media credits based on the license
						}
						
						$productResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}products  
							WHERE prod_id = '{$id}'
							"
						); // Select item pricing from the db
						$product = mysqli_fetch_array($productResult);
						
						$product['price'] = defaultPrice($product['price']); // Make sure to assign a default price if needed
																		
						$product['credits'] = defaultCredits($product['credits']); // Make sure to assign default credits if needed
												
						if($product['product_type'] == '1') // Only do the following if this is a media based product
						{
							/*
							* Custom Pricing calculations
							*/
							$mediaProductsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_products WHERE media_id = '{$mediaID}' AND prod_id = '{$id}'"); // Find if this has a customization
							if(mysqli_num_rows($mediaProductsResult))
							{
								$mediaProduct = mysqli_fetch_array($mediaProductsResult);
								
								if($mediaProduct['customized']) // See if this entry was customized
								{
									$product['price_calc'] = $mediaProduct['price_calc'];
									$product['price'] = defaultPrice($mediaProduct['price']);
									
									$product['credits'] = defaultCredits($mediaProduct['credits']);
									$product['credits_calc'] = $mediaProduct['credits_calc'];
									
									$product['quantity'] = $mediaProduct['quantity']; 	
								}
							}
						
							/*
							* Advanced Pricing calculations
							*/
							switch($product['price_calc'])
							{
								case 'add':
									$product['price'] = $mediaPrice + $product['price'];
								break;
								case 'sub':
									$product['price'] = $mediaPrice - $product['price'];
								break;
								case 'mult':
									$product['price'] = $mediaPrice * $product['price'];
								break;
							}
							
							switch($product['credits_calc'])
							{
								case 'add':
									$product['credits'] = $mediaCredits + $product['credits'];
								break;
								case 'sub':
									$product['credits'] = $mediaCredits - $product['credits'];
								break;
								case 'mult':
									$product['credits'] = $mediaCredits * $product['credits'];
								break;
							}
						}
						
						$itemPrice = $product['price'];
						$itemCredits = $product['credits'];
						
						$optionsPrice = 0;
						$optionsCredits = 0;
						
						// Get the price of all options - Save options
						if($option)
						{
							foreach($option as $optionKey => $optionValue)
							{	
								if($optionValue)
								{
									$optionKeyArray = explode("-",$optionKey);
									
									if($optionKeyArray[1])
										$opSelection = $optionKeyArray[1];
									else
										$opSelection = $optionValue;
									
									// Get option price
									$optionResult = mysqli_query($db,
										"
										SELECT * FROM {$dbinfo[pre]}options   
										WHERE op_id = '{$opSelection}'
										"
									); // Select item pricing from the db
									$option = mysqli_fetch_array($optionResult);
									switch($option['price_mod'])
									{
										case "add":
											$optionsPrice+=$option['price'];
										break;
										case "sub":
											$optionsPrice-=$option['price'];
										break;
									}
									switch($option['credits_mod'])
									{
										case "add":
											$optionsCredits+=$option['credits'];
										break;
										case "sub":
											$optionsCredits-=$option['credits'];
										break;
									}
									
									mysqli_query($db,
									"
										INSERT INTO {$dbinfo[pre]}invoice_options  
										(
											option_gid,
											item_list_number,
											option_id,
											invoice_item_id,
											option_price,
											option_price_calc,
											option_credits,
											option_credits_calc
										) 
										VALUES 
										(
											'{$optionKeyArray[0]}',
											'{$key}',
											'{$opSelection}',
											'{$cartItemID}',
											'{$option[price]}',
											'{$option[price_mod]}',
											'{$option[credits]}',
											'{$option[credits_mod]}'
										 )
										"
									); // Insert the options selected into the cart
								}
							}
						}
						
						$itemPrice+= $optionsPrice; // Price with options
						$itemCredits+= $optionsCredits; // Credits with options
						
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price='{$itemPrice}',
							price_total='{$itemPrice}',
							credits='{$itemCredits}',
							credits_total='{$itemCredits}'
							WHERE oi_id = '{$cartItemID}'
							"
						); // Update the invoice item with the prices
						
						if($owner) // Add commission record
						{
							$commission['oitemID'] = $cartItemID;
							$commission['comTotal'] = $itemPrice;
							$commission['comCredits'] = $itemCredits;
							
							if($print['commission_type'] == 1) // 1 = Percentage | 2 = Dollar Value
							{
								$commission['itemPercent'] = $product['commission']; //com_percentage
								$commission['comTotal'] = $itemPrice;	
							}
							else
							{
								$commission['itemPercent'] = 100; // Item commission is 100% of dollar value
								$commission['comTotal'] = $product['commission_dollar'];
							}
							
							addCommissionRecord($commission);
						}
					break;
					case "digital":
						$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
						$mediaInfo = new mediaList($sql);
						$media = $mediaInfo->getSingleMediaDetails('thumb');
						
						$mediaPrice = getMediaPrice($media); // Get the media price based on the license
						$mediaCredits = getMediaCredits($media); // Get the media credits based on the license

						//echo $mediaPrice; exit;

						//echo $id;

						if($id == 0) // This is an original
						{							
							$digital['ds_id'] = 0;
							//$digital = digitalsList($media,$mediaID,true);
							//$digital['fileCheck'] = $filecheck;
							$digital['width'] = $media['width'];
							$digital['height'] = $media['height'];
							$digital['format'] = $media['format'];
							$digital['license'] = $media['license'];
							$digital['name'] = $lang['original'];
							$digital['quantity'] = ($media['quantity'] == '') ? '1000000' : $media['quantity'];
							
							// Percentage
							$commissionItemPrice = $mediaPrice;	// Added this because there is no way to set the item commission level for originals
							$commissionItemPercent = 100; // Always lock it to 100 percent							
							
							$itemPrice = $mediaPrice; // Used this because digitalsList would give an error - error log shows "Unsupported operand types"
							$itemCredits = $mediaCredits;
						}
						else
						{
							$dspResult = mysqli_query($db,
								"
								SELECT *
								FROM {$dbinfo[pre]}media_digital_sizes 
								WHERE ds_id = '{$id}' 
								AND media_id = '{$mediaID}'
								"
							);
							$dsp = mysqli_fetch_array($dspResult);
							
							// Get the original digital profile details
							$digitalResult = mysqli_query($db,
								"
								SELECT *
								FROM {$dbinfo[pre]}digital_sizes 
								WHERE ds_id = '{$id}'
								"
							);
							if($digitalVarRows = mysqli_num_rows($digitalResult))
							{
								$digital = mysqli_fetch_array($digitalResult);
								
								if($dsp['customized']) // This is a customized profile - get the updated info
								{
									$digital['price_calc'] = $dsp['price_calc'];
									$digital['price'] = defaultPrice($dsp['price']);
									$digital['credits'] = defaultCredits($dsp['credits']);
									$digital['credits_calc'] = $dsp['credits_calc'];							
									$digital['quantity'] = $dsp['quantity'];
									$digital['quantityText'] = $dsp['quantity'];
									$digital['customized'] = true;
									$digital['customizeID'] = $dsp['mds_id'];
									$digital['useCustomizeID'] = ($config['EncryptIDs']) ? k_encrypt($dsp['mds_id']) : $dsp['mds_id'];
									$digital['license'] = $dsp['license'];
									$digital['width'] = ($dsp['width']) ? $dsp['width']: $digital['width'];
									$digital['height'] = ($dsp['height']) ? $dsp['height']: $digital['height'];
									$digital['format'] = ($dsp['format']) ? $dsp['format']: $digital['format'];					
									$digital['running_time'] = ($dsp['running_time']) ? $dsp['running_time']: $digital['running_time'];
									$digital['hd'] = $dsp['hd'];
									$digital['fps'] = ($dsp['fps']) ? $dsp['fps']: $digital['fps'];
								}
								else
								{	
									$digital['price'] = defaultPrice($digital['price']);
									$digital['credits'] = defaultCredits($digital['credits']);
									$digital['quantity'] = '1000000'; // Unlimited
									$digital['quantityText'] = $lang['unlimited'];
									$digital['customizeID'] = 0;
									$digital['useCustomizeID'] = ($config['EncryptIDs']) ? k_encrypt(0) : 0;
									$digital['customized'] = false;
								}
								
								//echo $digital['license']; exit;
								
								/*
								* Advanced Pricing calculations
								*/
								switch($digital['price_calc'])
								{
									case 'add':
										$digital['price'] = $mediaPrice + $digital['price'];
									break;
									case 'sub':
										$digital['price'] = $mediaPrice - $digital['price'];
									break;
									case 'mult':
										$digital['price'] = $mediaPrice * $digital['price'];
									break;
								}
																
								switch($digital['credits_calc'])
								{
									case 'add':
										$digital['credits'] = round($mediaCredits + $digital['credits']);
									break;
									case 'sub':
										$digital['credits'] = round($mediaCredits - $digital['credits']);
									break;
									case 'mult':
										$digital['credits'] = round($mediaCredits * $digital['credits']);
									break;
								}
								
								$itemPrice = $digital['price'];
								$itemCredits = $digital['credits'];
								
								if($digital['commission_type'] == 1)
								{
									// Percentage
									$commissionItemPrice = $itemPrice;	
									$commissionItemPercent = $digital['commission'];								
									//$commissionItemPrice = round($digital['price']*($digital['commission']/100),2);
								}
								else
								{
									// Dollar Value
									$commissionItemPrice = $digital['commission_dollar'];
									$commissionItemPercent = 100;
								}
								
								//echo $itemPrice; exit; // Testing
								
								$digital = digitalsList($digital,$mediaID);
								
								//print_r($digital['price']);
							}							
						}
						
						if($licenseType == 'rm') // Check for RM pricing
						{
							$itemPrice = k_decrypt($rmPriceEnc);
							$itemCredits = k_decrypt($rmCreditsEnc);
							
							//echo $itemPrice; exit;
							
							// Create RM selections string
							foreach($rmGroup as $grpID => $selectedID)
								$rmSelections.= "{$grpID}:{$selectedID},";
						}
												
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price='{$itemPrice}',
							price_total='{$itemPrice}',
							credits='{$itemCredits}',
							credits_total='{$itemCredits}',
							rm_selections='{$rmSelections}'
							WHERE oi_id = '{$cartItemID}'
							"
						); // Update the invoice item with the prices
						
						if($owner) // Add commission record
						{
							//echo $commission[memPercent]; exit; // testing							
							$commission['oitemID'] = $cartItemID;
							
							//commission // for commission percentage
							//commission_type = 1 // dollar
							//commission_dollar
							
							$commission['comTotal'] = $commissionItemPrice; //$itemPrice;							
							
							$commission['comCredits'] = $itemCredits;
							$commission['itemPercent'] = $commissionItemPercent; // New 4.5.5
							
							//print_r($commission);
							
							addCommissionRecord($commission);
						}
						
					break;
				}
			}
		}
		
		/*
		* Get all the cart details on totals
		*/
		$cartTotals['priceSubTotal'] = 0;
		$cartTotals['creditsTotal'] = 0;
		$cartTotals['taxA'] = 0;
		$cartTotals['taxB'] = 0;
		$cartTotals['taxC'] = 0;
		$cartTotals['taxInPrices'] = 0;
		$cartTotals['taxablePrice'] = 0;
		$cartTotals['taxableDigitalPrice'] = 0;
		
		/*
		* Get cart items
		*/
		$cartItemsSQL = 
			"
			SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}invoice_items 
			WHERE invoice_id = '{$invoiceID}' 
			AND deleted = 0 
			AND pack_invoice_id = 0 
			ORDER BY item_added DESC
			";
		$cartItemsResult = mysqli_query($db,$cartItemsSQL);
		if($cartItemRows = getRows()) // mysqli_num_rows($cartItemsResult)
		{	
			/*
			* Get promotions
			*/
			$promotionsResult = mysqli_query($db,
				"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}promotions 
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}promotions.promo_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'promotions')
				WHERE {$dbinfo[pre]}promotions.active = 1 
				AND {$dbinfo[pre]}promotions.deleted = 0
				AND ({$dbinfo[pre]}promotions.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				AND ({$dbinfo[pre]}promotions.quantity = '' OR {$dbinfo[pre]}promotions.quantity > '0')
				ORDER BY {$dbinfo[pre]}promotions.sortorder 
				"
			);
			if($returnRows = getRows()) // mysqli_num_rows($promotionsResult)
			{
				while($promotions = mysqli_fetch_assoc($promotionsResult))
				{					
					$promotionDetails = promotionsList($promotions);
					
					if($promotions['cartpage']) // Add this coupon to the array for promotions that will display in the cart
						$promotionsArray[] = $promotionDetails;
					
					if($promotions['autoapply'] and !$_SESSION['cartCouponsArray'][$promotions['promo_id']]) // See if any coupons are auto apply - make sure they weren't already added
					{
						// If one use per member then do not apply until member is logged in
						if($promotions['oneuse'] and !$_SESSION['loggedIn'])
						{
							// need to login
						}
						else
						{
							$memberCouponUsageRows = 0; // No usage
							
							if($promotions['oneuse'])
							{
								// Check to make sure member did not already use this coupon
								$memberCouponUsageResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}usedcoupons WHERE mem_id = '{$_SESSION[member][mem_id]}' AND promo_id = '{$promotions[promo_id]}'");
								$memberCouponUsageRows = mysqli_num_rows($memberCouponUsageResult);
							}
							
							if($memberCouponUsageRows == 0) // User is ok to use this coupon
								$_SESSION['cartCouponsArray'][$promotions['promo_id']] = $promotionDetails;
						}
					}
				}
				$smarty->assign('promotionsRows',$returnRows);
				$smarty->assign('promotions',$promotionsArray);
			}
			
			if($_SESSION['cartCouponsArray']) // Assign cart coupons to smarty
			{
				foreach($_SESSION['cartCouponsArray'] as $key => $value)
				{
					$cartCouponIDs[] = $key;
				}
			}
			
			/*
			* Find any discounts
			*/
			$cartItemsListResult = mysqli_query($db,$cartItemsSQL);
			while($cartItemListList = mysqli_fetch_array($cartItemsListResult))
			{
				$discountItems[$cartItemListList['item_type'].'-'.$cartItemListList['item_id']]['numOf']+=$cartItemListList['quantity'];
				$discountItems[$cartItemListList['item_type'].'-'.$cartItemListList['item_id']]['discount'] = 0;
			}			
			foreach($discountItems as $key => $value)
			{
				$keyArray = explode('-',$key);
				$discountType = $keyArray[0].'s'; // Add an ending S - Used to cleanup difference in item types from db table to table
				
				$discountRangeResult = mysqli_query($db,
					"
					SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}discount_ranges 
					WHERE item_type = '{$discountType}' 
					AND item_id = '{$keyArray[1]}'
					AND start_discount_number <= '{$value[numOf]}' 
					ORDER BY start_discount_number DESC 
					LIMIT 1
					"
				);
				if($discountRangeRows = getRows()) //mysqli_num_rows($discountRangeResult)
				{
					$discountRange = mysqli_fetch_array($discountRangeResult);
					$discountItems[$keyArray[0].'-'.$keyArray[1]]['discount'] = $discountRange['discount_percent'];
				}
			}
			
			while($cartItem = mysqli_fetch_assoc($cartItemsResult))
			{
				
				if($cartItem['item_type'] == 'package')
				{
					$cartItem['package_media_remaining'] = $cartItem['package_media_needed'] - $cartItem['package_media_filled'];
					
					if($cartItem['package_media_remaining'] > 0)
					{
						$packageMediaFilledPercentage = $cartItem['package_media_filled']/$cartItem['package_media_needed'];
						$cartItem['package_media_percentage'] = round(100*$packageMediaFilledPercentage);
					}
					else
					{
						$packageMediaFilledPercentage = 0;
						$cartItem['package_media_percentage'] = 100;
					}
				}
				
				$cartItemsArray[$cartItem['oi_id']] = $cartItem;
				$cartItemsArray[$cartItem['oi_id']]['encryptedID'] = k_encrypt($cartItem['oi_id']);
				
				$physicalItemTypeArray = array('print','product','package'); // Item types that are physical product and may need to be treated differently - such as shipping added
				
				if(in_array($cartItem['item_type'],$physicalItemTypeArray))
				{
					if($config['settings']['skip_shipping'] and $cartItem['paytype'] == 'cred')
					{
						// The skip shipping when using credits setting is on	
					}
					else
					{
						$cartTotals['shippingRequired'] = true; // This is a physical item - shipping will be required					
						$cartItemsArray[$cartItem['oi_id']]['shippingRequired'] = true; // Add it at the item level just in case it is needed in the future
					}
				}
				
				$discountForThisItem = '';
				
				switch($cartItem['item_type'])
				{
					case "print":
						$printResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}prints 
							WHERE print_id = '{$cartItem[item_id]}'
							"
						); // Select print here
						$print = mysqli_fetch_assoc($printResult);
						
						$printDetails = printsList($print,$cartItem['asset_id']);
						
						$printDetails['cartEditLink'].='&edit='.$cartItem['oi_id']; // Add edit var
						
						if(!$cartItem['paytype'])
							$cartItem['paytype'] = 'cur'; // Make sure the payType is set just in case
						
						$cartItemsArray[$cartItem['oi_id']]['usePayType'] = $cartItem['paytype']; // Set the payType to the item
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount'] = 0; // Number of options available to pay
						
						$numOfPrints+= $cartItem['quantity']; // Count the number of print items in the cart
						
						/*
						* Currency
						*/
						if(currencyCartStatus()) // Check if currency is available
						{
							if($discountItems['print-'.$print['print_id']]['discount'])
							{
								$discountPricePerentage = (100-$discountItems['print-'.$print['print_id']]['discount'])/100;
								$lineItemPriceEach = round($cartItem['price']*$discountPricePerentage,$priCurrency['decimal_places']); // Make sure to round the correct places based on the primary currency
								$cartItemsArray[$cartItem['oi_id']]['discountPercentage'] = $discountItems['print-'.$print['print_id']]['discount'];
							}
							else
								$lineItemPriceEach = $cartItem['price'];
							
							$lineItemPriceEachLocalCalc =  $lineItemPriceEach;
							
							$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_default']/100)),2);
							
							if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_prints'] and $print['taxable']) // See if tax should be displayed in price
							{
								$lineItemPriceEachLocalCalc = $lineItemPriceEachLocalCalc + $taxOnItem;
								$cartTotals['taxInPrices'] = 1; // Update global taxInPrices setting
								$cartItemsArray[$cartItem['oi_id']]['taxInc'] = true; // This item includes tax
							}
							
							// Do exchange rate if needed - round to local cur
							$lineItemPriceEachLocalCalc = ($config['settings']['cur_decimal_places']) ? round($lineItemPriceEachLocalCalc/$exchangeRate,$config['settings']['cur_decimal_places']) : round($lineItemPriceEachLocalCalc/$exchangeRate);
							$lineItemPriceEachLocal['display'] = $cleanCurrency->currency_display($lineItemPriceEachLocalCalc,1); // In local currency
							$lineItemPriceTotal = $lineItemPriceEach * $cartItem['quantity'];
							$lineItemPriceTotalLocalCalc = $lineItemPriceEachLocalCalc*$cartItem['quantity'];
							
							if($_SESSION['tax']['tax_prints'] and $print['taxable'] and $cartItem['paytype'] == 'cur') // See if price should be added to everything that will be taxed - need to come after the code above
							{
								$cartTotals['taxablePrice']+= $lineItemPriceTotal;
								$cartItemsArray[$cartItem['oi_id']]['taxed'] = true;
							}
							
							$lineItemPriceTotalLocal['display'] = $cleanCurrency->currency_display($lineItemPriceTotalLocalCalc,1); // In local currency
							
							$cartItemsArray[$cartItem['oi_id']]['canUseCurrency'] = true; // See if currrency can be used
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cur')
						{
							$cartTotals['priceSubTotal']+= $lineItemPriceTotal; // Add these values to the totals							
							$cartTotals['physicalSubTotal']+= $lineItemPriceTotal; // Add these values to the digital totals
						}
							
						/*
						* Add shippable details to the cartTotals array
						*/
						if($cartItemsArray[$cartItem['oi_id']]['shippingRequired'])
						{
							if($print['addshipping']) // Any additional shipping costs
								$cartTotals['additionalShipping']+=($print['addshipping']*$cartItem['quantity']);
								
							$cartTotals['shippableTotal']+=$lineItemPriceTotal;
							$cartTotals['shippableWeight']+=($print['weight']*$cartItem['quantity']);
							$cartTotals['shippableCount']+=(1*$cartItem['quantity']);
							
							$cartTotals['shippingSummary'].=
							"<item>
								<itemType>Print</cartItemID>
								<cartItemID>{$cartItem[oi_id]}</cartItemID>
								<itemID>{$print[print_id]}</itemID>
								<shippableTotal>{$lineItemPriceTotal}</shippableTotal>
								<additionalShipping>{$print[addshipping]}*{$cartItem[quantity]}(quantity)</additionalShipping>
								<weightEach>{$print[weight]}</weightEach>
								<weightTotal>Weight Each({$print[weight]})*{$cartItem[quantity]}(quantity)</weightTotal>
								<quantity>{$cartItem[quantity]}</quantity>
							</item>
							";
						}
						
						/*
						* Credits
						*/
						if(creditsCartStatus('print')) // Check if credits are available on this item
						{
							if($discountItems['print-'.$print['print_id']]['discount'])
							{
								$lineItemCreditsEach = round($cartItem['credits']*((100-$discountItems['print-'.$print['print_id']]['discount'])/100));
							}
							else
								$lineItemCreditsEach =  $cartItem['credits'];
									
							$lineItemCreditsTotal =  $lineItemCreditsEach  * $cartItem['quantity'];	
							$cartItemsArray[$cartItem['oi_id']]['canUseCredits'] = true; // See if credits can be used	
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cred')
							$cartTotals['creditsSubTotal']+= $lineItemCreditsTotal; // Add these values to the totals
						
						/*
						* Assign to item
						*/
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotal']		= $lineItemPriceTotal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotalLocal']	= $lineItemPriceTotalLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEach']		= $lineItemPriceEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEachLocal']	= $lineItemPriceEachLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsEach']		= $lineItemCreditsEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsTotal']		= $lineItemCreditsTotal;
						
						$cartItemsArray[$cartItem['oi_id']]['itemDetails'] = $printDetails;
						
						if($cartItem['asset_id']) // Check if a media file has been attached to this item
							$cartItemsArray[$cartItem['oi_id']]['itemDetails']['media'] = getMediaDetailsForCart($cartItem['asset_id']);
							
						$cartItemsArray[$cartItem['oi_id']]['itemTypeShort'] = 'print';
						
						// Update prices and credits for invoice item in the db
						mysqli_query($db,
						"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price_total='{$lineItemPriceTotal}',
							price='{$lineItemPriceEach}',
							credits_total='{$lineItemCreditsTotal}',
							credits='{$lineItemCreditsEach}'
							WHERE oi_id = '{$cartItem[oi_id]}'
						");
					break;
					case "product":
						$productResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}products 
							WHERE prod_id = '{$cartItem[item_id]}'
							"
						); // Select product here
						$product = mysqli_fetch_assoc($productResult);
						
						$productDetails = productsList($product,$cartItem['asset_id']);
						
						$productDetails['cartEditLink'].='&edit='.$cartItem['oi_id']; // Add edit var
						
						if(!$cartItem['paytype'])
							$cartItem['paytype'] = 'cur'; // Make sure the payType is set just in case
						
						$cartItemsArray[$cartItem['oi_id']]['usePayType'] = $cartItem['paytype']; // Set the payType to the item
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount'] = 0; // Number of options available to pay
						
						$numOfProducts+= $cartItem['quantity']; // Count the number of product items in the cart
						
						/*
						* Currency
						*/
						if(currencyCartStatus()) // Check if currency is available
						{
							if($discountItems['product-'.$product['prod_id']]['discount'])
							{
								$discountPricePerentage = (100-$discountItems['product-'.$product['prod_id']]['discount'])/100;
								$lineItemPriceEach = round($cartItem['price']*$discountPricePerentage,$priCurrency['decimal_places']); // Make sure to round the correct places based on the primary currency
								$cartItemsArray[$cartItem['oi_id']]['discountPercentage'] = $discountItems['product-'.$product['prod_id']]['discount'];
							}
							else
								$lineItemPriceEach = $cartItem['price'];
							
							$lineItemPriceEachLocalCalc =  $lineItemPriceEach;
							
							$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_default']/100)),2);
							
							if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_prints'] and $product['taxable']) // See if tax should be displayed in price
							{
								$lineItemPriceEachLocalCalc = $lineItemPriceEachLocalCalc + $taxOnItem;
								$cartTotals['taxInPrices'] = 1; // Update global taxInPrices setting
								$cartItemsArray[$cartItem['oi_id']]['taxInc'] = true; // This item includes tax
							}
							
							// Do exchange rate if needed - round to local cur
							$lineItemPriceEachLocalCalc = ($config['settings']['cur_decimal_places']) ? round($lineItemPriceEachLocalCalc/$exchangeRate,$config['settings']['cur_decimal_places']) : round($lineItemPriceEachLocalCalc/$exchangeRate);
							$lineItemPriceEachLocal['display'] = $cleanCurrency->currency_display($lineItemPriceEachLocalCalc,1); // In local currency
							$lineItemPriceTotal = $lineItemPriceEach * $cartItem['quantity'];
							$lineItemPriceTotalLocalCalc = $lineItemPriceEachLocalCalc*$cartItem['quantity'];
							
							if($_SESSION['tax']['tax_prints'] and $product['taxable'] and $cartItem['paytype'] == 'cur') // See if price should be added to everything that will be taxed - need to come after the code above
							{
								$cartTotals['taxablePrice']+= $lineItemPriceTotal;
								$cartItemsArray[$cartItem['oi_id']]['taxed'] = true;
							}
							
							$lineItemPriceTotalLocal['display'] = $cleanCurrency->currency_display($lineItemPriceTotalLocalCalc,1); // In local currency
							
							$cartItemsArray[$cartItem['oi_id']]['canUseCurrency'] = true; // See if currrency can be used
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cur')
						{
							$cartTotals['priceSubTotal']+= $lineItemPriceTotal; // Add these values to the totals
							$cartTotals['physicalSubTotal']+= $lineItemPriceTotal; // Add these values to the physical totals
						}
						
						/*
						* Add shippable details to the cartTotals array
						*/
						if($cartItemsArray[$cartItem['oi_id']]['shippingRequired'])
						{
							if($product['addshipping']) // Any additional shipping costs
								$cartTotals['additionalShipping']+=($product['addshipping']*$cartItem['quantity']);
							
							$cartTotals['shippableTotal']+=$lineItemPriceTotal;
							$cartTotals['shippableWeight']+=($product['weight']*$cartItem['quantity']);
							$cartTotals['shippableCount']+=(1*$cartItem['quantity']);
							
							$cartTotals['shippingSummary'].=
							"<item>
								<itemType>Product</cartItemID>
								<cartItemID>{$cartItem[oi_id]}</cartItemID>
								<itemID>{$product[prod_id]}</itemID>
								<shippableTotal>{$lineItemPriceTotal}</shippableTotal>
								<additionalShipping>{$product[addshipping]}*{$cartItem[quantity]}(quantity)</additionalShipping>
								<weightEach>{$product[weight]}</weightEach>
								<weightTotal>Weight Each({$product[weight]})*{$cartItem[quantity]}(quantity)</weightTotal>
								<quantity>{$cartItem[quantity]}</quantity>
							</item>
							";
						}
						
						/*
						* Credits
						*/
						if(creditsCartStatus('prod')) // Check if credits are available on this item
						{
							if($discountItems['product-'.$product['prod_id']]['discount'])
							{
								$lineItemCreditsEach = round($cartItem['credits']*((100-$discountItems['prod-'.$product['prod_id']]['discount'])/100));
							}
							else
								$lineItemCreditsEach =  $cartItem['credits'];
									
							$lineItemCreditsTotal =  $lineItemCreditsEach  * $cartItem['quantity'];	
							$cartItemsArray[$cartItem['oi_id']]['canUseCredits'] = true; // See if credits can be used	
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cred')
							$cartTotals['creditsSubTotal']+= $lineItemCreditsTotal; // Add these values to the totals
						
						/*
						* Assign to item
						*/
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotal']		= $lineItemPriceTotal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotalLocal']	= $lineItemPriceTotalLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEach']		= $lineItemPriceEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEachLocal']	= $lineItemPriceEachLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsEach']		= $lineItemCreditsEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsTotal']		= $lineItemCreditsTotal;

						$cartItemsArray[$cartItem['oi_id']]['itemDetails'] = $productDetails;
						
						if($cartItem['asset_id']) // Check if a media file has been attached to this item
							$cartItemsArray[$cartItem['oi_id']]['itemDetails']['media'] = getMediaDetailsForCart($cartItem['asset_id']);
							
						$cartItemsArray[$cartItem['oi_id']]['itemTypeShort'] = 'prod';
						
						// Update prices and credits for invoice item in the db
						mysqli_query($db,
						"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price_total='{$lineItemPriceTotal}',
							price='{$lineItemPriceEach}',
							credits_total='{$lineItemCreditsTotal}',
							credits='{$lineItemCreditsEach}'
							WHERE oi_id = '{$cartItem[oi_id]}'
						");
					break;
					case "package":
						$packageResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}packages 
							WHERE pack_id = '{$cartItem[item_id]}'
							"
						); // Select package here
						$package = mysqli_fetch_assoc($packageResult);
						
						$packageDetails = packagesList($package,0);
						
						$packageDetails['cartEditLink'].='&edit='.$cartItem['oi_id']; // Add edit var
						
						// xxxx See if we should include tax or not?
						
						if(!$cartItem['paytype'])
							$cartItem['paytype'] = 'cur'; // Make sure the payType is set just in case
						
						$cartItemsArray[$cartItem['oi_id']]['usePayType'] = $cartItem['paytype']; // Set the payType to the item
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount'] = 0; // Number of options available to pay
						
						$numOfPackages+= $cartItem['quantity']; // Count the number of package items in the cart
						
						/*
						* Currency
						*/
						if(currencyCartStatus()) // Check if currency is available
						{
							if($discountItems['package-'.$package['pack_id']]['discount'])
							{
								$discountPricePerentage = (100-$discountItems['package-'.$package['pack_id']]['discount'])/100;
								$lineItemPriceEach = round($cartItem['price']*$discountPricePerentage,$priCurrency['decimal_places']); // Make sure to round the correct places based on the primary currency
								$cartItemsArray[$cartItem['oi_id']]['discountPercentage'] = $discountItems['package-'.$package['pack_id']]['discount'];
							}
							else
								$lineItemPriceEach = $cartItem['price'];
							
							$lineItemPriceEachLocalCalc =  $lineItemPriceEach;
							
							$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_default']/100)),2);
							
							if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_prints'] and $package['taxable']) // See if tax should be displayed in price
							{
								$lineItemPriceEachLocalCalc = $lineItemPriceEachLocalCalc + $taxOnItem;
								$cartTotals['taxInPrices'] = 1; // Update global taxInPrices setting
								$cartItemsArray[$cartItem['oi_id']]['taxInc'] = true; // This item includes tax
							}
							
							// Do exchange rate if needed - round to local cur
							$lineItemPriceEachLocalCalc = ($config['settings']['cur_decimal_places']) ? round($lineItemPriceEachLocalCalc/$exchangeRate,$config['settings']['cur_decimal_places']) : round($lineItemPriceEachLocalCalc/$exchangeRate);
							$lineItemPriceEachLocal['display'] = $cleanCurrency->currency_display($lineItemPriceEachLocalCalc,1); // In local currency
							$lineItemPriceTotal = $lineItemPriceEach * $cartItem['quantity'];
							$lineItemPriceTotalLocalCalc = $lineItemPriceEachLocalCalc*$cartItem['quantity'];
							
							if($_SESSION['tax']['tax_prints'] and $package['taxable'] and $cartItem['paytype'] == 'cur') // See if price should be added to everything that will be taxed - need to come after the code above
							{
								$cartTotals['taxablePrice']+= $lineItemPriceTotal;
								$cartItemsArray[$cartItem['oi_id']]['taxed'] = true;
							}
							
							$lineItemPriceTotalLocal['display'] = $cleanCurrency->currency_display($lineItemPriceTotalLocalCalc,1); // In local currency
							
							$cartItemsArray[$cartItem['oi_id']]['canUseCurrency'] = true; // See if currrency can be used
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cur')
						{
							$cartTotals['priceSubTotal']+= $lineItemPriceTotal; // Add these values to the totals
							$cartTotals['physicalSubTotal']+= $lineItemPriceTotal; // Add these values to the digital totals
						}
						
						/*
						* Add shippable details to the cartTotals array
						*/
						if($cartItemsArray[$cartItem['oi_id']]['shippingRequired'])
						{
							if($package['addshipping']) // Any additional shipping costs
								$cartTotals['additionalShipping']+=($package['addshipping']*$cartItem['quantity']);
							
							$cartTotals['shippableTotal']+=$lineItemPriceTotal;
							$cartTotals['shippableWeight']+=($package['weight']*$cartItem['quantity']);
							$cartTotals['shippableCount']+=(1*$cartItem['quantity']);
							
							$cartTotals['shippingSummary'].=
							"<item>
								<itemType>Package</cartItemID>
								<cartItemID>{$cartItem[oi_id]}</cartItemID>
								<itemID>{$package[pack_id]}</itemID>
								<shippableTotal>{$lineItemPriceTotal}</shippableTotal>
								<additionalShipping>{$package[addshipping]}*{$cartItem[quantity]}(quantity)</additionalShipping>
								<weightEach>{$package[weight]}</weightEach>
								<weightTotal>Weight Each({$package[weight]})*{$cartItem[quantity]}(quantity)</weightTotal>
								<quantity>{$cartItem[quantity]}</quantity>
							</item>
							";
						}
						
						/*
						* Credits
						*/
						if(creditsCartStatus('pack')) // Check if credits are available on this item
						{
							if($discountItems['package-'.$package['pack_id']]['discount'])
							{
								$lineItemCreditsEach = round($cartItem['credits']*((100-$discountItems['package-'.$package['pack_id']]['discount'])/100));
							}
							else
								$lineItemCreditsEach =  $cartItem['credits'];
									
							$lineItemCreditsTotal =  $lineItemCreditsEach  * $cartItem['quantity'];	
							$cartItemsArray[$cartItem['oi_id']]['canUseCredits'] = true; // See if credits can be used	
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cred')
							$cartTotals['creditsSubTotal']+= $lineItemCreditsTotal; // Add these values to the totals
						
						/*
						* Assign to item
						*/
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotal']		= $lineItemPriceTotal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotalLocal']	= $lineItemPriceTotalLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEach']		= $lineItemPriceEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEachLocal']	= $lineItemPriceEachLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsEach']		= $lineItemCreditsEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsTotal']		= $lineItemCreditsTotal;

						
						$cartItemsArray[$cartItem['oi_id']]['itemDetails'] = $packageDetails;
							
						$cartItemsArray[$cartItem['oi_id']]['itemTypeShort'] = 'pack';
						
						// Update prices and credits for invoice item in the db
						mysqli_query($db,
						"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price_total='{$lineItemPriceTotal}',
							price='{$lineItemPriceEach}',
							credits_total='{$lineItemCreditsTotal}',
							credits='{$lineItemCreditsEach}'
							WHERE oi_id = '{$cartItem[oi_id]}'
						");
					break;
					case "collection":
						$collectionResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}collections 
							WHERE coll_id = '{$cartItem[item_id]}'
							"
						); // Select collection here
						$collection = mysqli_fetch_assoc($collectionResult);
						
						$collectionDetails = collectionsList($collection,0);
						
						$collectionDetails['cartEditLink'].='&edit='.$cartItem['oi_id']; // Add edit var
						
						if(!$cartItem['paytype'])
							$cartItem['paytype'] = 'cur'; // Make sure the payType is set just in case
						
						$cartItemsArray[$cartItem['oi_id']]['usePayType'] = $cartItem['paytype']; // Set the payType to the item
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount'] = 0; // Number of options available to pay
						
						$numOfCollections+= $cartItem['quantity']; // Count the number of collection items in the cart
						
						/*
						* Currency
						*/
						if(currencyCartStatus()) // Check if currency is available
						{
							
							$lineItemPriceEach = $cartItem['price'];
							
							$lineItemPriceEachLocalCalc =  $lineItemPriceEach;
							
							//$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_default']/100)),2);
							$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_digital']/100)),2);
							
							if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_digital'] and $collection['taxable']) // See if tax should be displayed in price
							{
								$lineItemPriceEachLocalCalc = $lineItemPriceEachLocalCalc + $taxOnItem;
								$cartTotals['taxInPrices'] = 1; // Update global taxInPrices setting
								$cartItemsArray[$cartItem['oi_id']]['taxInc'] = true; // This item includes tax
							}
							
							// Do exchange rate if needed - round to local cur
							$lineItemPriceEachLocalCalc = ($config['settings']['cur_decimal_places']) ? round($lineItemPriceEachLocalCalc/$exchangeRate,$config['settings']['cur_decimal_places']) : round($lineItemPriceEachLocalCalc/$exchangeRate);
							$lineItemPriceEachLocal['display'] = $cleanCurrency->currency_display($lineItemPriceEachLocalCalc,1); // In local currency
							$lineItemPriceTotal = $lineItemPriceEach * $cartItem['quantity'];
							$lineItemPriceTotalLocalCalc = $lineItemPriceEachLocalCalc*$cartItem['quantity'];
							
							if($_SESSION['tax']['tax_digital'] and $collection['taxable'] and $cartItem['paytype'] == 'cur') // See if price should be added to everything that will be taxed - need to come after the code above
							{
								$cartTotals['taxableDigitalPrice']+= $lineItemPriceTotal;
								$cartItemsArray[$cartItem['oi_id']]['taxed'] = true;
							}
							
							$lineItemPriceTotalLocal['display'] = $cleanCurrency->currency_display($lineItemPriceTotalLocalCalc,1); // In local currency
							
							$cartItemsArray[$cartItem['oi_id']]['canUseCurrency'] = true; // See if currrency can be used
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cur')
						{
							$cartTotals['priceSubTotal']+= $lineItemPriceTotal; // Add these values to the totals
							$cartTotals['digitalSubTotal']+= $lineItemPriceTotal; // Add these values to the digital totals
						}
						
						/*
						* Credits
						*/
						if(creditsCartStatus('coll')) // Check if credits are available on this item
						{
							$lineItemCreditsEach =  $cartItem['credits'];
									
							$lineItemCreditsTotal =  $lineItemCreditsEach  * $cartItem['quantity'];	
							$cartItemsArray[$cartItem['oi_id']]['canUseCredits'] = true; // See if credits can be used	
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cred')
							$cartTotals['creditsSubTotal']+= $lineItemCreditsTotal; // Add these values to the totals
						
						/*
						* Assign to item
						*/
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotal']		= $lineItemPriceTotal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotalLocal']	= $lineItemPriceTotalLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEach']		= $lineItemPriceEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEachLocal']	= $lineItemPriceEachLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsEach']		= $lineItemCreditsEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsTotal']		= $lineItemCreditsTotal;

						$cartItemsArray[$cartItem['oi_id']]['itemDetails'] = $collectionDetails;
							
						$cartItemsArray[$cartItem['oi_id']]['itemTypeShort'] = 'coll';
					break;
					case "subscription":
						
						$accountWorkbox = 1; // Must require an account if this is a sub
						
						$subscriptionResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}subscriptions 
							WHERE sub_id = '{$cartItem[item_id]}'
							"
						); // Select subscription here
						$subscription = mysqli_fetch_assoc($subscriptionResult);
						
						$subscriptionDetails = subscriptionsList($subscription,0);
						
						$subscriptionDetails['cartEditLink'].='&edit='.$cartItem['oi_id']; // Add edit var
						
						if(!$cartItem['paytype'])
							$cartItem['paytype'] = 'cur'; // Make sure the payType is set just in case
						
						$cartItemsArray[$cartItem['oi_id']]['usePayType'] = $cartItem['paytype']; // Set the payType to the item
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount'] = 0; // Number of options available to pay
						
						$numOfSubscriptions+= $cartItem['quantity']; // Count the number of subscriptions items in the cart
						
						/*
						* Currency
						*/
						if(currencyCartStatus()) // Check if currency is available
						{
							if($discountItems['subscription-'.$subscription['sub_id']]['discount'])
							{
								$discountPricePerentage = (100-$discountItems['subscription-'.$subscription['sub_id']]['discount'])/100;
								$lineItemPriceEach = round($cartItem['price']*$discountPricePerentage,$priCurrency['decimal_places']); // Make sure to round the correct places based on the primary currency
								$cartItemsArray[$cartItem['oi_id']]['discountPercentage'] = $discountItems['subscription-'.$subscription['sub_id']]['discount'];
							}
							else
								$lineItemPriceEach = $cartItem['price'];
							
							$lineItemPriceEachLocalCalc =  $lineItemPriceEach;
							
							//$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_default']/100)),2);
							$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_digital']/100)),2);
							
							if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_subs'] and $subscription['taxable']) // See if tax should be displayed in price
							{
								$lineItemPriceEachLocalCalc = $lineItemPriceEachLocalCalc + $taxOnItem;
								$cartTotals['taxInPrices'] = 1; // Update global taxInPrices setting
								$cartItemsArray[$cartItem['oi_id']]['taxInc'] = true; // This item includes tax
							}
							
							// Do exchange rate if needed - round to local cur
							$lineItemPriceEachLocalCalc = ($config['settings']['cur_decimal_places']) ? round($lineItemPriceEachLocalCalc/$exchangeRate,$config['settings']['cur_decimal_places']) : round($lineItemPriceEachLocalCalc/$exchangeRate);
							$lineItemPriceEachLocal['display'] = $cleanCurrency->currency_display($lineItemPriceEachLocalCalc,1); // In local currency
							$lineItemPriceTotal = $lineItemPriceEach * $cartItem['quantity'];
							$lineItemPriceTotalLocalCalc = $lineItemPriceEachLocalCalc*$cartItem['quantity'];
							
							if($_SESSION['tax']['tax_subs'] and $subscription['taxable'] and $cartItem['paytype'] == 'cur') // See if price should be added to everything that will be taxed - need to come after the code above
							{
								$cartTotals['taxableDigitalPrice']+= $lineItemPriceTotal;
								$cartItemsArray[$cartItem['oi_id']]['taxed'] = true;
							}
							
							$lineItemPriceTotalLocal['display'] = $cleanCurrency->currency_display($lineItemPriceTotalLocalCalc,1); // In local currency
							
							$cartItemsArray[$cartItem['oi_id']]['canUseCurrency'] = true; // See if currrency can be used
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cur')
						{
							$cartTotals['priceSubTotal']+= $lineItemPriceTotal; // Add these values to the totals
							$cartTotals['digitalSubTotal']+= $lineItemPriceTotal; // Add these values to the digital totals
						}
						
						/*
						* Credits
						*/
						if(creditsCartStatus('sub')) // Check if credits are available on this item
						{
							if($discountItems['subscription-'.$subscription['sub_id']]['discount'])
							{
								$lineItemCreditsEach = round($cartItem['credits']*((100-$discountItems['subscription-'.$subscription['sub_id']]['discount'])/100));
							}
							else
								$lineItemCreditsEach =  $cartItem['credits'];
									
							$lineItemCreditsTotal =  $lineItemCreditsEach  * $cartItem['quantity'];	
							$cartItemsArray[$cartItem['oi_id']]['canUseCredits'] = true; // See if credits can be used	
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cred')
							$cartTotals['creditsSubTotal']+= $lineItemCreditsTotal; // Add these values to the totals
						
						/*
						* Assign to item
						*/
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotal']		= $lineItemPriceTotal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotalLocal']	= $lineItemPriceTotalLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEach']		= $lineItemPriceEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEachLocal']	= $lineItemPriceEachLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsEach']		= $lineItemCreditsEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsTotal']		= $lineItemCreditsTotal;
						
						$cartItemsArray[$cartItem['oi_id']]['itemDetails'] = $subscriptionDetails;
						
						$cartItemsArray[$cartItem['oi_id']]['itemTypeShort'] = 'sub';
						
						// Update prices and credits for invoice item in the db
						mysqli_query($db,
						"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price_total='{$lineItemPriceTotal}',
							price='{$lineItemPriceEach}',
							credits_total='{$lineItemCreditsTotal}',
							credits='{$lineItemCreditsEach}'
							WHERE oi_id = '{$cartItem[oi_id]}'
						");
					break;
					case "credits":
						
						$accountWorkbox = 1; // If they are buying credits we need to force an account
						
						$creditsResult = mysqli_query($db,
							"
							SELECT * FROM {$dbinfo[pre]}credits 
							WHERE credit_id = '{$cartItem[item_id]}'
							"
						); // Select credits here
						$credits = mysqli_fetch_assoc($creditsResult);
						
						$creditsDetails = creditsList($credits,0);
						
						$creditsDetails['cartEditLink'].='&edit='.$cartItem['oi_id']; // Add edit var
						
						$creditsInCart+=$creditsDetails['credits']*$cartItem['quantity']; // Add these to the number of credits that are available in the cart to checkout with
						
						if(!$cartItem['paytype'])
							$cartItem['paytype'] = 'cur'; // Make sure the payType is set just in case
						
						$cartItemsArray[$cartItem['oi_id']]['usePayType'] = $cartItem['paytype']; // Set the payType to the item
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount'] = 0; // Number of options available to pay
						
						$numOfCreditPacks+= $cartItem['quantity']; // Count the number of credit packages items in the cart
						
						/*
						* Currency
						*/
						if($discountItems['credits-'.$credits['credit_id']]['discount'])
						{
							$discountPricePerentage = (100-$discountItems['credits-'.$credits['credit_id']]['discount'])/100;
							$lineItemPriceEach = round($cartItem['price']*$discountPricePerentage,$priCurrency['decimal_places']); // Make sure to round the correct places based on the primary currency
							$cartItemsArray[$cartItem['oi_id']]['discountPercentage'] = $discountItems['credits-'.$credits['credit_id']]['discount'];
						}
						else
							$lineItemPriceEach = $cartItem['price'];
						
						$lineItemPriceEachLocalCalc =  $lineItemPriceEach;
						
						//$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_default']/100)),2);
						$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_digital']/100)),2);
						
						if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_credits'] and $credits['taxable']) // See if tax should be displayed in price
						{
							$lineItemPriceEachLocalCalc = $lineItemPriceEachLocalCalc + $taxOnItem;
							$cartTotals['taxInPrices'] = 1; // Update global taxInPrices setting
							$cartItemsArray[$cartItem['oi_id']]['taxInc'] = true; // This item includes tax
						}
						
						// Do exchange rate if needed - round to local cur
						$lineItemPriceEachLocalCalc = ($config['settings']['cur_decimal_places']) ? round($lineItemPriceEachLocalCalc/$exchangeRate,$config['settings']['cur_decimal_places']) : round($lineItemPriceEachLocalCalc/$exchangeRate);
						$lineItemPriceEachLocal['display'] = $cleanCurrency->currency_display($lineItemPriceEachLocalCalc,1); // In local currency
						$lineItemPriceTotal = $lineItemPriceEach * $cartItem['quantity'];
						$lineItemPriceTotalLocalCalc = $lineItemPriceEachLocalCalc*$cartItem['quantity'];
						
						if($_SESSION['tax']['tax_credits'] and $credits['taxable'] and $cartItem['paytype'] == 'cur') // See if price should be added to everything that will be taxed - need to come after the code above
						{
							$cartTotals['taxableDigitalPrice']+= $lineItemPriceTotal;
							$cartItemsArray[$cartItem['oi_id']]['taxed'] = true;
						}
						
						$lineItemPriceTotalLocal['display'] = $cleanCurrency->currency_display($lineItemPriceTotalLocalCalc,1); // In local currency
						
						$cartItemsArray[$cartItem['oi_id']]['canUseCurrency'] = true; // See if currrency can be used
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
					
						$cartTotals['priceSubTotal']+= $lineItemPriceTotal; // Add these values to the totals
						$cartTotals['digitalSubTotal']+= $lineItemPriceTotal; // Add these values to the digital totals
						
						/*
						* Assign to item
						*/
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotal']		= $lineItemPriceTotal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotalLocal']	= $lineItemPriceTotalLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEach']		= $lineItemPriceEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEachLocal']	= $lineItemPriceEachLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsEach']		= $lineItemCreditsEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsTotal']		= $lineItemCreditsTotal;
						
						$cartItemsArray[$cartItem['oi_id']]['itemDetails'] = $creditsDetails;
						
						$cartItemsArray[$cartItem['oi_id']]['itemTypeShort'] = 'credit';
						
						// Update prices and credits for invoice item in the db
						mysqli_query($db,
						"
							UPDATE {$dbinfo[pre]}invoice_items SET 
							price_total='{$lineItemPriceTotal}',
							price='{$lineItemPriceEach}',
							credits_total='{$lineItemCreditsTotal}',
							credits='{$lineItemCreditsEach}'
							WHERE oi_id = '{$cartItem[oi_id]}'
						");
					break;
					case "digital":
						//$mediaObj = new mediaTools($cartItem['asset_id']);
						//$mediaObj->getMediaInfoFromDB($cartItem['asset_id'],$media);
						
						$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$cartItem[asset_id]}'";
						$mediaInfo = new mediaList($sql);
						
						// Check for customized profile
						/*
						$customizedDSPResult = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media_digital_sizes WHERE ds_id = '{$cartItem[item_id]}' AND media_id = '{$cartItem[asset_id]}'");
						if(getRows())
							$customizedDSP = mysqli_fetch_assoc($customizedDSPResult);
						*/
							
						//print_r($customizedDSP);
						//echo "rows: {$customizedRows}";
						
						if($mediaInfo->getRows())
						{
							$media = $mediaInfo->getSingleMediaDetails('thumb');
						}
						
						$digital = digitalPrep($cartItem['item_id'],$media);
						
						$numOfDigitals+= $cartItem['quantity']; // Count the number of product items in the cart
						
						//$isOriginal = ($cartItem['item_id']) ? true : false;
						
						$digitalDetails = digitalsList($digital,$cartItem['asset_id']);
						
						//print_r($digitalDetails); exit;
						
						//print_r($digitalDetails); exit;
						
						$digitalDetails['cartEditLink'].='&edit='.$cartItem['oi_id']; // Add edit var
						
						if(!$cartItem['paytype'])
							$cartItem['paytype'] = 'cur'; // Make sure the payType is set just in case
						
						$cartItemsArray[$cartItem['oi_id']]['usePayType'] = $cartItem['paytype']; // Set the payType to the item
						
						$cartItemsArray[$cartItem['oi_id']]['payTypeCount'] = 0; // Number of options available to pay
						
						$numOfDigitalFiles+= $cartItem['quantity']; // Count the number of digital items in the cart
						
						/*
						* Currency
						*/
						if(currencyCartStatus()) // Check if currency is available
						{
							if($discountItems['digital-'.$digital['sub_id']]['discount'])
							{
								$discountPricePerentage = (100-$discountItems['digital-'.$subscription['ds_id']]['discount'])/100;
								$lineItemPriceEach = round($cartItem['price']*$discountPricePerentage,$priCurrency['decimal_places']); // Make sure to round the correct places based on the primary currency
								$cartItemsArray[$cartItem['oi_id']]['discountPercentage'] = $discountItems['digital-'.$digital['ds_id']]['discount'];
							}
							else
								$lineItemPriceEach = $cartItem['price'];
							
							$lineItemPriceEachLocalCalc =  $lineItemPriceEach;
							
							//$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_default']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_default']/100)),2);
							$taxOnItem = round(($lineItemPriceEach*($_SESSION['tax']['tax_a_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_b_digital']/100)) + ($lineItemPriceEach*($_SESSION['tax']['tax_c_digital']/100)),2);
							
							if($_SESSION['tax']['tax_inc'] and $_SESSION['tax']['tax_digital']) // See if tax should be displayed in price
							{
								$lineItemPriceEachLocalCalc = $lineItemPriceEachLocalCalc + $taxOnItem;
								$cartTotals['taxInPrices'] = 1; // Update global taxInPrices setting
								$cartItemsArray[$cartItem['oi_id']]['taxInc'] = true; // This item includes tax
							}
							
							// Do exchange rate if needed - round to local cur
							$lineItemPriceEachLocalCalc = ($config['settings']['cur_decimal_places']) ? round($lineItemPriceEachLocalCalc/$exchangeRate,$config['settings']['cur_decimal_places']) : round($lineItemPriceEachLocalCalc/$exchangeRate);
							$lineItemPriceEachLocal['display'] = $cleanCurrency->currency_display($lineItemPriceEachLocalCalc,1); // In local currency
							$lineItemPriceTotal = $lineItemPriceEach * $cartItem['quantity'];
							$lineItemPriceTotalLocalCalc = $lineItemPriceEachLocalCalc*$cartItem['quantity'];
							
							if($_SESSION['tax']['tax_digital'] and $cartItem['paytype'] == 'cur') // See if price should be added to everything that will be taxed - need to come after the code above
							{
								$cartTotals['taxableDigitalPrice']+= $lineItemPriceTotal;
								$cartItemsArray[$cartItem['oi_id']]['taxed'] = true;
							}
							
							$lineItemPriceTotalLocal['display'] = $cleanCurrency->currency_display($lineItemPriceTotalLocalCalc,1); // In local currency
							
							$cartItemsArray[$cartItem['oi_id']]['canUseCurrency'] = true; // See if currrency can be used
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cur')
						{
							$cartTotals['priceSubTotal']+= $lineItemPriceTotal; // Add these values to the totals							
							$cartTotals['digitalSubTotal']+= $lineItemPriceTotal; // Add these values to the digital totals
						}
						
						/*
						* Credits
						*/
						if(creditsCartStatus('digital')) // Check if credits are available on this item
						{
							if($discountItems['digital-'.$digital['ds_id']]['discount'])
							{
								$lineItemCreditsEach = round($cartItem['credits']*((100-$discountItems['digital-'.$digital['ds_id']]['discount'])/100));
							}
							else
								$lineItemCreditsEach =  $cartItem['credits'];
									
							$lineItemCreditsTotal =  $lineItemCreditsEach  * $cartItem['quantity'];	
							$cartItemsArray[$cartItem['oi_id']]['canUseCredits'] = true; // See if credits can be used	
							
							$cartItemsArray[$cartItem['oi_id']]['payTypeCount']++; // Add a payType
						}
						if($cartItem['paytype'] == 'cred')
							$cartTotals['creditsSubTotal']+= $lineItemCreditsTotal; // Add these values to the totals
						
						/*
						* Assign to item
						*/
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotal']		= $lineItemPriceTotal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceTotalLocal']	= $lineItemPriceTotalLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEach']		= $lineItemPriceEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemPriceEachLocal']	= $lineItemPriceEachLocal;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsEach']		= $lineItemCreditsEach;
						$cartItemsArray[$cartItem['oi_id']]['lineItemCreditsTotal']		= $lineItemCreditsTotal;
						
						$cartItemsArray[$cartItem['oi_id']]['itemDetails'] = $digitalDetails;
						$cartItemsArray[$cartItem['oi_id']]['itemDetails']['media'] = getMediaDetailsForCart($cartItem['asset_id']);
						
						$cartItemsArray[$cartItem['oi_id']]['itemTypeShort'] = 'digital';
					break;
				}
			}
			
			/*
			echo 'digital: '.$cartTotals['digitalSubTotal'];
			echo "<br>";
			echo 'physical: '.$cartTotals['physicalSubTotal'];
			exit;
			*/
			
			// Find number of digital and physical items in the cart
			$physicalItemsCount = 0;
			$digitalItemsCount = 0;
			foreach($cartItemsArray as $countCartItem)
			{
				if($countCartItem['physical_item'])
					$physicalItemsCount++;
				else
					$digitalItemsCount++;	
			}
			//echo $physicalItemsCount; exit;
			
			/*
			* Deduct coupons
			*/
			if($_SESSION['cartCouponsArray'])
			{
				foreach($_SESSION['cartCouponsArray'] as $couponKey => $coupon) // Check to make sure minimum is met
				{
					if($coupon['minpurchase'] and $coupon['minpurchase'] > $cartTotals['priceSubTotal'] and $promotions['promotype'] != 'bulk') // Check if the minimum is met
						unset($_SESSION['cartCouponsArray'][$coupon['promo_id']]); // remove coupon before using it
				}
				foreach($_SESSION['cartCouponsArray'] as $couponKey => $coupon) // No tax
				{
					if($coupon['promotype'] == 'notax')
						$cartTotals['clearTax'] = true;
				}
				foreach($_SESSION['cartCouponsArray'] as $couponKey => $coupon) // Percentage off coupon
				{
					if($coupon['promotype'] == 'peroff')
					{
						if($cartTotals['taxablePrice'] > 0 or $cartTotals['taxableDigitalPrice'] > 0)
						{
							//$cartTotals['taxableDigitalPrice']-= ($priCurrency['decimal_places']) ? round($cartTotals['taxableDigitalPrice']*($coupon['peroff']/100),$priCurrency['decimal_places']) : $cartTotals['taxableDigitalPrice']*($coupon['peroff']/100);							
							$discountOnTaxableTotal+= ($priCurrency['decimal_places']) ? round($cartTotals['taxablePrice']*($coupon['peroff']/100),$priCurrency['decimal_places']) : $cartTotals['taxablePrice']*($coupon['peroff']/100);							
							$discountOnTaxableDigitalTotal+= ($priCurrency['decimal_places']) ? round($cartTotals['taxableDigitalPrice']*($coupon['peroff']/100),$priCurrency['decimal_places']) : $cartTotals['taxableDigitalPrice']*($coupon['peroff']/100);
						}
						
						if($cartTotals['physicalSubTotal'])
							$discountOnPhysicalTotal+= ($priCurrency['decimal_places']) ? round($cartTotals['physicalSubTotal']*($coupon['peroff']/100),$priCurrency['decimal_places']) : $cartTotals['physicalSubTotal']*($coupon['peroff']/100); // Discount on physical
							
						if($cartTotals['digitalSubTotal'])
							$discountOnDigitalTotal+= ($priCurrency['decimal_places']) ? round($cartTotals['digitalSubTotal']*($coupon['peroff']/100),$priCurrency['decimal_places']) : $cartTotals['digitalSubTotal']*($coupon['peroff']/100); // Discount on digital
						
						/*
						if($cartTotals['priceSubTotal'] > 0)
						{
							$discountOnSubTotal+= ($priCurrency['decimal_places']) ? round($cartTotals['priceSubTotal']*($coupon['peroff']/100),$priCurrency['decimal_places']) : $cartTotals['priceSubTotal']*($coupon['peroff']/100);
						}
						*/
						if($cartTotals['creditsSubTotal'] > 0)
						{
							$discountOnCreditsSubTotal+= round($cartTotals['creditsSubTotal']*($coupon['peroff']/100));
						}
					}
				}
				
				foreach($_SESSION['cartCouponsArray'] as $couponKey => $coupon) // Dollar off coupon
				{					
					if($coupon['promotype'] == 'dollaroff')
					{	
						$couponRemaining = $coupon['dollaroff'];
						
						foreach($cartItemsArray as $thisCartItem)
						{	
							if($thisCartItem['usePayType'] == 'cur') // Make sure this is using currency payment type
							{
								//echo $couponRemaining;
								if($couponRemaining >= $thisCartItem['price_total']) // Coupon remaining
								{
									if($thisCartItem['physical_item']) // Physical total
									{	
										$discountOnPhysicalTotal+= $thisCartItem['price_total'];
										
										if($thisCartItem['taxed']) // check if this item is taxed
											$discountOnTaxableTotal+= $thisCartItem['price_total'];
									}
									else // Digital Item
									{
										$discountOnDigitalTotal+= $thisCartItem['price_total'];
										
										if($thisCartItem['taxed']) // check if this item is taxed
											$discountOnTaxableDigitalTotal+= $thisCartItem['price_total'];
									}
									
									$couponRemaining = $couponRemaining - $thisCartItem['price_total'];	
															
								}
								else // Less than item price coupon remaining
								{
									if($thisCartItem['physical_item']) // Physical total
									{	
										$discountOnPhysicalTotal+= $couponRemaining;
										
										if($thisCartItem['taxed']) // check if this item is taxed
											$discountOnTaxableTotal+= $couponRemaining;
									}
									else // Digital Item
									{
										$discountOnDigitalTotal+= $couponRemaining;
										
										if($thisCartItem['taxed']) // check if this item is taxed
											$discountOnTaxableDigitalTotal+= $couponRemaining;	
									}
									
									$couponRemaining = 0;
								}
							}
						}
						
						/*
						if($cartTotals['taxablePrice'] > 0)
						{
							$discountOnTaxableTotal+= ($coupon['dollaroff']/count($cartItemsArray));
						}
						if($cartTotals['taxableDigitalPrice'] > 0)
						{
							$discountOnTaxableDigitalTotal+= ($coupon['dollaroff']/count($cartItemsArray));
						}
						if($cartTotals['physicalSubTotal'])
							$discountOnPhysicalTotal+= ($coupon['dollaroff']/count($cartItemsArray));
						
						if($cartTotals['digitalSubTotal'])
							$discountOnDigitalTotal+= ($coupon['dollaroff']/count($cartItemsArray));
						*/
					}
				}
				
				//echo $discountOnDigitalTotal; exit;
				
				//print_r($_SESSION['cartCouponsArray']);
				
				foreach($_SESSION['cartCouponsArray'] as $couponKey => $coupon) // Bulk discount
				{
					if($coupon['promotype'] == 'bulk')
					{
						//echo $coupon['bulktype'];
						
						switch($coupon['bulktype'])
						{
							case "digital":
								$bestDealDigitalQuantityID = 0;
								$bestDealDigitalQuantity = 0;
								
								foreach($_SESSION['cartCouponsArray'] as $couponKey2 => $coupon2) // Find best deal
								{	
									if($coupon2['promotype'] == 'bulk' and $coupon2['bulktype'] == 'digital')
									{
										//echo $numOfDigitals; exit;
										if($numOfDigitals > $coupon2['bulkbuy'])
										{	
											if($bestDealDigitalQuantity < $coupon2['bulkbuy'])
											{
												$bestDealDigitalQuantityID = $couponKey2;
												$bestDealDigitalQuantity = $coupon2['bulkbuy']; // Set the new quantity to beat
											}
										}
									}
								}
								
								if($bestDealDigitalQuantityID == $couponKey) // Check to see if there are enough in the cart
								{
									$freeEligible = $numOfDigitals-$bestDealDigitalQuantity; // How many items can be free
									$freeAvailable = $freeEligible-$coupon['bulkfree'];
									
									/*if($freeAvailable < 0)
										$freeAvailable = $freeEligible;*/
									
									//echo $freeAvailable;
									
									if($freeEligible >= $coupon['bulkfree']) // Calculate how many should be free
										$freeAvailable = $coupon['bulkfree'];
									else
										$freeAvailable = $freeEligible; // $coupon['bulkfree'] - 
									
									//echo $freeEligible." + ".$freeAvailable;
									
									$bulkDiscounts = findLowestCartItem('digital',$freeAvailable);// Find lowest price of X amount of digitals
									
									//print_r($bulkDiscounts);
									
									if($cartTotals['taxableDigitalPrice'] > 0 and $bulkDiscounts['pricesTotal'] > 0)
										$discountOnTaxableDigitalTotal+= ($priCurrency['decimal_places']) ? round($bulkDiscounts['pricesTotal'],$priCurrency['decimal_places']) : $bulkDiscounts['pricesTotal'];
									
									if($cartTotals['digitalSubTotal'] > 0 and $bulkDiscounts['pricesTotal'] > 0)
										$discountOnDigitalTotal+= ($priCurrency['decimal_places']) ? round($bulkDiscounts['pricesTotal'],$priCurrency['decimal_places']) : $bulkDiscounts['pricesTotal'];
									
									if($cartTotals['creditsSubTotal'] > 0 and $bulkDiscounts['creditsTotal'] > 0)
										$discountOnCreditsSubTotal+= round($bulkDiscounts['creditsTotal']);
									
									//echo $discountOnTaxableDigitalTotal;
									
									/* For testing
									echo "credits free array: "; print_r($bulkDiscounts['creditsFreeArray']); echo "<br /><br />";
									echo "prices free array: "; print_r($bulkDiscounts['pricesFreeArray']); echo "<br /><br />";
									echo("cred total: " . $bulkDiscounts['creditsTotal'])."<br /><br />";
									echo("price total: " . $bulkDiscounts['pricesTotal'])."<br /><br />";
									*/
								}
							break;
							case "print":
								$bestDealPrintQuantityID = 0;
								$bestDealPrintQuantity = 0;
								
								foreach($_SESSION['cartCouponsArray'] as $couponKey2 => $coupon2) // Find best deal
								{
									if($coupon2['promotype'] == 'bulk' and $coupon2['bulktype'] == 'print')
									{
										if($numOfPrints > $coupon2['bulkbuy'])
										{
											if($bestDealPrintQuantity < $coupon2['bulkbuy'])
											{
												$bestDealPrintQuantityID = $couponKey2;
												$bestDealPrintQuantity = $coupon2['bulkbuy']; // Set the new quantity to beat
											}
										}
									}
								}
								
								//echo "----bdq: $bestDealPrintQuantity - bdID: $bestDealPrintQuantityID <br /><br />";						
								
								if($bestDealPrintQuantityID == $couponKey) // Check to see if there are enough in the cart
								{
									$freeEligible = $numOfPrints-$bestDealPrintQuantity;
									$freeAvailable = $freeEligible-$coupon['bulkfree'];
									
									if($freeEligible >= $coupon['bulkfree']) // Calculate how many should be free
										$freeAvailable = $coupon['bulkfree'];
									else
										$freeAvailable = $freeEligible;	// $coupon['bulkfree'] - 
									
									//echo $freeEligible." + ".$freeAvailable;
									
									$bulkDiscounts = findLowestCartItem('print',$freeAvailable);// Find lowest price of X amount of prints
									
									if($cartTotals['taxablePrice'] > 0 and $bulkDiscounts['pricesTotal'] > 0)
										$discountOnTaxableTotal+= ($priCurrency['decimal_places']) ? round($bulkDiscounts['pricesTotal'],$priCurrency['decimal_places']) : $bulkDiscounts['pricesTotal'];
									if($cartTotals['priceSubTotal'] > 0 and $bulkDiscounts['pricesTotal'] > 0)
										$discountOnPhysicalTotal+= ($priCurrency['decimal_places']) ? round($bulkDiscounts['pricesTotal'],$priCurrency['decimal_places']) : $bulkDiscounts['pricesTotal'];
									if($cartTotals['creditsSubTotal'] > 0 and $bulkDiscounts['creditsTotal'] > 0)
										$discountOnCreditsSubTotal+= round($bulkDiscounts['creditsTotal']);
									
									/* For testing
									echo "credits free array: "; print_r($bulkDiscounts['creditsFreeArray']); echo "<br /><br />";
									echo "prices free array: "; print_r($bulkDiscounts['pricesFreeArray']); echo "<br /><br />";
									echo("cred total: " . $bulkDiscounts['creditsTotal'])."<br /><br />";
									echo("price total: " . $bulkDiscounts['pricesTotal'])."<br /><br />";
									*/
								}
							break;
							case "prod":
								$bestDealProductQuantityID = 0;
								$bestDealProductQuantity = 0;
								
								foreach($_SESSION['cartCouponsArray'] as $couponKey2 => $coupon2) // Find best deal
								{
									if($coupon2['promotype'] == 'bulk' and $coupon2['bulktype'] == 'prod')
									{
										
										
										if($numOfProducts > $coupon2['bulkbuy'])
										{
											if($bestDealProductQuantity < $coupon2['bulkbuy'])
											{
												$bestDealProductQuantityID = $couponKey2;
												$bestDealProductQuantity = $coupon2['bulkbuy']; // Set the new quantity to beat
											}
										}
									}
								}
								
								//echo "----bdq: $bestDealProductQuantity - bdID: $bestDealProductQuantityID <br /><br />";						
								
								if($bestDealProductQuantityID == $couponKey) // Check to see if there are enough in the cart
								{
									$freeEligible = $numOfProducts-$bestDealProductQuantity;
									$freeAvailable = $freeEligible-$coupon['bulkfree'];
									
									if($freeEligible >= $coupon['bulkfree']) // Calculate how many should be free
										$freeAvailable = $coupon['bulkfree'];
									else
										$freeAvailable = $freeEligible;	//  $coupon['bulkfree'] - 
									
									//echo $freeEligible." + ".$freeAvailable;
									
									$bulkDiscounts = findLowestCartItem('product',$freeAvailable);// Find lowest price of X amount of product
									
									if($cartTotals['taxablePrice'] > 0 and $bulkDiscounts['pricesTotal'] > 0)
										$discountOnTaxableTotal+= ($priCurrency['decimal_places']) ? round($bulkDiscounts['pricesTotal'],$priCurrency['decimal_places']) : $bulkDiscounts['pricesTotal'];
									if($cartTotals['priceSubTotal'] > 0 and $bulkDiscounts['pricesTotal'] > 0)
										$discountOnPhysicalTotal+= ($priCurrency['decimal_places']) ? round($bulkDiscounts['pricesTotal'],$priCurrency['decimal_places']) : $bulkDiscounts['pricesTotal'];
									if($cartTotals['creditsSubTotal'] > 0 and $bulkDiscounts['creditsTotal'] > 0)
										$discountOnCreditsSubTotal+= round($bulkDiscounts['creditsTotal']);
									
									/* For testing
									echo "credits free array: "; print_r($bulkDiscounts['creditsFreeArray']); echo "<br /><br />";
									echo "prices free array: "; print_r($bulkDiscounts['pricesFreeArray']); echo "<br /><br />";
									echo("cred total: " . $bulkDiscounts['creditsTotal'])."<br /><br />";
									echo("price total: " . $bulkDiscounts['pricesTotal'])."<br /><br />";
									*/
								}
							break;
						}
					}
				}
				foreach($_SESSION['cartCouponsArray'] as $couponKey => $coupon) // Free shipping
				{
					if($coupon['promotype'] == 'freeship')
					{
						
					}
				}
			}
			
			//echo $cartTotals['taxableDigitalPrice']; exit;
			
			$parms['noDefault'] = true;
			
			/*
			* Get taxes
			*/
			if($cartTotals['taxablePrice'] > 0 or $cartTotals['taxableDigitalPrice'] > 0)
			{
				//echo $cartTotals['taxableDigitalPrice']-$discountOnTaxableDigitalTotal;
				
				$cartTotals['taxAdigital'] = round(($cartTotals['taxableDigitalPrice']-$discountOnTaxableDigitalTotal)*($_SESSION['tax']['tax_a_digital']/100),2);
				$cartTotals['taxBdigital'] = round(($cartTotals['taxableDigitalPrice']-$discountOnTaxableDigitalTotal)*($_SESSION['tax']['tax_b_digital']/100),2);
				$cartTotals['taxCdigital'] = round(($cartTotals['taxableDigitalPrice']-$discountOnTaxableDigitalTotal)*($_SESSION['tax']['tax_c_digital']/100),2);
				
				//$cartTotals['taxAdigital'] = round(($cartTotals['taxableDigitalPrice'])*($_SESSION['tax']['tax_a_digital']/100),2);
				//$cartTotals['taxBdigital'] = round(($cartTotals['taxableDigitalPrice'])*($_SESSION['tax']['tax_b_digital']/100),2);
				//$cartTotals['taxCdigital'] = round(($cartTotals['taxableDigitalPrice'])*($_SESSION['tax']['tax_c_digital']/100),2);
				
				//echo $cartTotals['taxBdigital'];
				
				$cartTotals['taxAphysical'] = round(($cartTotals['taxablePrice']-$discountOnTaxableTotal)*($_SESSION['tax']['tax_a_default']/100),2);
				$cartTotals['taxBphysical'] = round(($cartTotals['taxablePrice']-$discountOnTaxableTotal)*($_SESSION['tax']['tax_b_default']/100),2);
				$cartTotals['taxCphysical'] = round(($cartTotals['taxablePrice']-$discountOnTaxableTotal)*($_SESSION['tax']['tax_c_default']/100),2);
				
				//$cartTotals['taxAphysical'] = round(($cartTotals['taxablePrice'])*($_SESSION['tax']['tax_a_default']/100),2);
				//$cartTotals['taxBphysical'] = round(($cartTotals['taxablePrice'])*($_SESSION['tax']['tax_b_default']/100),2);
				//$cartTotals['taxCphysical'] = round(($cartTotals['taxablePrice'])*($_SESSION['tax']['tax_c_default']/100),2);
				
				$cartTotals['taxA'] = $cartTotals['taxAphysical'] + $cartTotals['taxAdigital'];
				$cartTotals['taxB'] = $cartTotals['taxBphysical'] + $cartTotals['taxBdigital'];
				$cartTotals['taxC'] = $cartTotals['taxCphysical'] + $cartTotals['taxCdigital'];				
				
				//echo $cartTotals['taxB'];
				
				$cartTotals['taxTotal'] = $cartTotals['taxA'] + $cartTotals['taxB'] + $cartTotals['taxC'];
				
				$cartTotals['taxALocal'] = getCorrectedPrice($cartTotals['taxA'],$parms); // For display
				$cartTotals['taxBLocal'] = getCorrectedPrice($cartTotals['taxB'],$parms); // For display
				$cartTotals['taxCLocal'] = getCorrectedPrice($cartTotals['taxC'],$parms); // For display
			}
			
			if($cartTotals['clearTax']) // No tax is going to be added
				$totalTaxUse = 0;
			else
				$totalTaxUse = $cartTotals['taxTotal'];
			

			/*
			echo 'discountOnPhysicalTotal: '.$discountOnPhysicalTotal;
			echo '<br>';
			echo 'discountOnDigitalTotal: '.$discountOnDigitalTotal;
			exit;
			*/
			
			$cartTotals['total'] = $cartTotals['priceSubTotal']-($discountOnPhysicalTotal+$discountOnDigitalTotal)+$totalTaxUse;
			$cartTotals['creditsTotal'] = $cartTotals['creditsSubTotal']-$discountOnCreditsSubTotal;
			
			$cartTotals['totalCreditsDiscounts'] = $discountOnCreditsSubTotal;
			$cartTotals['totalDiscounts'] = $discountOnPhysicalTotal+$discountOnDigitalTotal;
			$cartTotals['totalPhysicalDiscounts'] = $discountOnPhysicalTotal;		
			$cartTotals['totalDigitalDiscounts'] = $discountOnDigitalTotal;
			
			// xxx make sure to make this a session also just in case someone tries to change the input values on the cart page
			$creditsAvailableAtCheckout =  $_SESSION['member']['credits'] + $creditsInCart;	
			$cartTotals['creditsAvailableAtCheckout'] = ($creditsAvailableAtCheckout) ? $creditsAvailableAtCheckout : 0; // Check how many credits the member has at checkout - include those in the current cart
			
			// Set display totals
			$cartTotals['totalDiscountsLocal'] = getCorrectedPrice($cartTotals['totalDiscounts'],$parms); // For display
			$cartTotals['subTotalLocal'] = getCorrectedPrice($cartTotals['priceSubTotal'],$parms); // For display
			$cartTotals['totalLocal'] = getCorrectedPrice($cartTotals['total'],$parms); // For display
			
			$cartTotals['priceSubTotalPreview'] = getCorrectedPrice($cartTotals['priceSubTotal'],$parms); // For preview
			$cartTotals['creditsSubTotalPreview'] = ($cartTotals['creditsSubTotal'] <= 0) ? 0 : $cartTotals['creditsSubTotal']; // Do extra check for no credits - default to 0
			
			$smarty->assign('cartItemRows',$cartItemRows); // Assign a count of the number of items in the cart
			
			 // Assign the entire array unless we are asking for only the last item
			if($onlyLastAdded)
				$smarty->assign('cartItems',array_slice($cartItemsArray,0,1));
			else
				$smarty->assign('cartItems',$cartItemsArray);
				
			$_SESSION['cartItemsSession'] = $cartItemsArray; // Put the cart items into a session to use them later
		
			$cartTotals['itemsInCart'] = $cartItemRows;
			
			//$_SESSION['itemsInCart'] = $cartItemRows;
		}
		else
		{
			$cartTotals['priceSubTotalPreview'] = 0; // For preview
			$cartTotals['creditsSubTotalPreview'] = 0; // For preview
			$cartTotals['itemsInCart'] = 0;				
		}
		
		$cartTotals['subtotalMinusDiscounts'] = $cartTotals['priceSubTotal']-$cartTotals['totalDiscounts']; // Get a subtotal minus discounts
		
		$_SESSION['cartTotalsSession'] = $cartTotals; // Assign cart totals to the session
		
		$smarty->assign('cartTotals',$cartTotals);

		if($cartTotals['priceSubTotal'] < $config['settings']['min_total'] and $cartTotals['creditsTotal'] < 1) // Check to make sure subtotal is enough to continue checkout
			$smarty->assign('lowSubtotalWarning',1);
		else
			$smarty->assign('lowSubtotalWarning',0);
		
		/*
		* Cart Info - Put all collected cart info into a session
		*/
		$_SESSION['cartInfoSession']['uniqueOrderID'] = $_SESSION['uniqueOrderID'];
		$_SESSION['cartInfoSession']['cartID'] = $cartID;
		$_SESSION['cartInfoSession']['invoiceID'] = $invoiceID;
		$_SESSION['cartInfoSession']['cartItemRows'] = $cartItemRows;
		
		// xxxxxxxxxxxx Invoice date needs to be recorded
		
		/*
		* Update orders database
		*/
		mysqli_query($db,
		"
			UPDATE {$dbinfo[pre]}orders SET 
			order_date='{$nowGMT}'
			WHERE uorder_id = '{$_SESSION[uniqueOrderID]}'
		");
		
		/*
		* Build a tax summary
		* Summary of the tax session at the time the cart was updated
		*/
		foreach($_SESSION['tax'] as $taxKey => $taxValue)
			$taxSummary.= "{$taxKey}: {$taxValue} | ";
		
		@$discountIDs = implode(",",$cartCouponIDs); // Coupon IDs used in this cart
		
		// xxxxxxxxxxxxxxxxxxxx // Check for shipping cost when using credits above - setting not yet created in the management area
		
		
		/*
		* Update invoice database
		*/
		mysqli_query($db,
		"
			UPDATE {$dbinfo[pre]}invoices SET 
			invoice_date='{$nowGMT}',
			due_date='{$nowGMT}',
			total='{$cartTotals[total]}',
			subtotal='{$cartTotals[priceSubTotal]}',
			credits_total='{$cartTotals[creditsTotal]}',
			discounts_total='{$cartTotals[totalDiscounts]}',
			discounts_credits_total='{$cartTotals[totalCreditsDiscounts]}',
			taxa_cost='{$cartTotals[taxA]}',
			taxb_cost='{$cartTotals[taxB]}',
			taxc_cost='{$cartTotals[taxC]}',
			tax_ratea='{$_SESSION[tax][tax_a_default]}',
			tax_rateb='{$_SESSION[tax][tax_b_default]}',
			tax_ratec='{$_SESSION[tax][tax_c_default]}',
			tax_summary='{$taxSummary}',
			discount_ids_used='{$discountIDs}',
			customer_currency='{$_SESSION[member][currency]}'
			WHERE invoice_id = '{$invoiceID}'
		");
		
		$accountWorkbox = (($config['settings']['accounts_required'] and !$_SESSION['loggedIn']) or ($accountWorkbox and !$_SESSION['loggedIn'])) ? 1 : 0; // See if we need to open the account workbox - or if accountWorkbox was already set above
		
		$smarty->assign('cartCouponsArray',$_SESSION['cartCouponsArray']);
		$smarty->assign('accountWorkbox',$accountWorkbox);
		$smarty->assign('cartID',$cartID);
		$smarty->assign('invoiceID',$invoiceID);
		$smarty->assign('uniqueOrderID',$_SESSION['uniqueOrderID']);
		$smarty->assign('mediaID',$mediaID);
		$smarty->assign('id',$id);
		$smarty->assign('type',$type);
		
		if($mode)
		{
			if(preg_match("/[^A-Za-z0-9_-]/",$mode))
			{
				header("location: error.php?eType=invalidQuery");
				exit;
			}
		}
		
		$smarty->assign('mode',$mode);
		$smarty->assign('continueShoppingButton',$_SESSION['backButtonSession']);
		$smarty->assign('packagesInCartSession',$_SESSION['packagesInCartSession']);
		$smarty->assign('cartInfo',$_SESSION['cartInfoSession']);
		
		if($miniCart)
		{
			if($mode == 'add') // New item added
			{
				if($_SESSION['tax']['tax_inc']) // If tax is included in prices
					$miniCartPreviewPrice = $cartTotals['totalLocal']['display'];
				else
					$miniCartPreviewPrice = $cartTotals['priceSubTotalPreview']['display'];
					 
				$miniCartPreviewCredits = $cartTotals['creditsSubTotalPreview'];
				
				$cartPreviewString = '';
				
				echo
					"{
						\"cartDetails\" : { 
							\"items\" : \"{$cartItemRows}\", 
							\"price\" : \"{$miniCartPreviewPrice}\", 
							\"credits\" : \"{$miniCartPreviewCredits}\",
							\"string\" : \"{$cartPreviewString}\"
						}
					}";
			}
			else // list	
			{
				if($onlyLastAdded)
				{
					// Take only the last item added
					//$singleCartItem = reset($cartItemsArray);					
					$smarty->assign('onlyLastAdded',$onlyLastAdded);
					//$smarty->assign('cartItems',reset($cartItemsArray));
				}
					
				$smarty->display('minicart.tpl');
			}
		}
		else		
			$smarty->display('cart.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>