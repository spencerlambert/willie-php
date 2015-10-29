<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','package'); // Page ID
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
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	$useMediaID = $mediaID;

	try
	{	
		//$originalMediaID = $mediaID;
		//$originalPackID = $id;
		
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$id = k_decrypt($id);
			$mediaID = k_decrypt($mediaID);
		}
		
		if($id) idCheck($id); // Make sure ID is numeric
		if($mediaID) idCheck($mediaID); // Make sure ID is numeric
		
		$packageResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}packages
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}packages.pack_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'packages') 
			WHERE {$dbinfo[pre]}packages.pack_id = {$id}
			AND ({$dbinfo[pre]}packages.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
			"
		);
		if($returnRows = mysqli_num_rows($packageResult))
		{	
			$package = mysqli_fetch_assoc($packageResult);
			$packageArray = packagesList($package);
			
			/*
			* Get discounts
			*/
			$discountsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}discount_ranges 
				WHERE item_type = 'packages' 
				AND start_discount_number > 0
				AND item_id = '{$id}' 
				ORDER BY start_discount_number
				"
			);
			if($discountReturnRows = mysqli_num_rows($discountsResult))
			{	
				while($discount = mysqli_fetch_array($discountsResult))
				{
					$discountsArray[$discount['dr_id']] = $discount;
				}
				$smarty->assign('discountRows',$discountReturnRows);
				$smarty->assign('discountsArray',$discountsArray);
			}
			
			if($edit) // We are editing a package
			{
				//echo k_encrypt($edit); exit;
				$smarty->assign('edit',k_encrypt($edit));
				
				$invoiceItemsResult = mysqli_query($db,
					"
					SELECT asset_id,item_list_number,oi_id 
					FROM {$dbinfo[pre]}invoice_items 
					WHERE pack_invoice_id = {$edit}
					AND deleted = 0
					"
				); // Select any invoice items that are already in the cart
				if($invoiceItemsRows = mysqli_num_rows($invoiceItemsResult))
				{
					while($invoiceItem = mysqli_fetch_array($invoiceItemsResult))
					{
						if($invoiceItem['asset_id']) // Get the media details
						{
							$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$invoiceItem[asset_id]}'";
							$mediaInfo = new mediaList($sql);
							$media = $mediaInfo->getSingleMediaDetails('thumb');
						
							$mediaIDs[$invoiceItem['item_list_number']] = $media;
						}
						
						$packageItemInvoiceIDs[] = $invoiceItem['oi_id'];
					}
				}
				
				if($packageItemInvoiceIDs)
					$packageItemInvoiceIDsFlat = implode(',',$packageItemInvoiceIDs);
				else
					$packageItemInvoiceIDsFlat = '0';
				
				//print_r($packageItemInvoiceIDsFlat);
				
				$invoiceOptionsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}invoice_options 
					WHERE invoice_item_id IN ({$packageItemInvoiceIDsFlat})
					"
				);
				if($invoiceOptionsRows = mysqli_num_rows($invoiceOptionsResult))
				{
					while($invoiceOption = mysqli_fetch_array($invoiceOptionsResult))
					{
						$optionSelections[$invoiceOption['item_list_number'].'-'.$invoiceOption['option_gid'].'-'.$invoiceOption['option_id']] = true;
					}
				}
				
				//print_r($optionSelections)."<br /><br /><br /><br />";
			}
			
			if($package['active'] == 1 and $package['deleted'] == 0 and ($package['quantity'] == '' or $package['quantity'] > 0))
			{	
				if($mediaID) // Building off of a media file and pricing
				{
					// select the media details
					$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
					$mediaInfo = new mediaList($sql);
					$media = $mediaInfo->getSingleMediaDetails('preview');
					$smarty->assign('media',$media);
				}
				
				if(@in_array($id,$_SESSION['packagesInCartSession'])) // Assign packages in cart if there are any
				{
					foreach($_SESSION['packagesInCartSession'] as $key => $value) // Loop through packages in cart
					{
						if($value == $package['pack_id']) // Only list packages with the SAME ID
						{
							$cartItemResult = mysqli_query($db,
								"
								SELECT package_media_needed,package_media_filled
								FROM {$dbinfo[pre]}invoice_items
								WHERE oi_id = '{$key}'
								"
							);
							$cartItem = mysqli_fetch_array($cartItemResult);
							
							$package_media_remaining = $cartItem['package_media_needed'] - $cartItem['package_media_filled'];					
							
							if($package_media_remaining > 0)
							{
								$packageMediaFilledPercentage = $cartItem['package_media_filled']/$cartItem['package_media_needed'];
								$package_media_percentage = round(100*$packageMediaFilledPercentage);
							}
							else
							{
								$packageMediaFilledPercentage = 100;
								$package_media_percentage = 100;
							}
						
							$packagesInCart[$key]['originalValue'] = $value;
							$packagesInCart[$key]['package_media_percentage'] = $package_media_percentage;
						}
					}
					
					$smarty->assign('packagesInCart',$packagesInCart);
				}
				
				$arrayNum = 0;
				
				/*
				* Prints within the package
				*/
				$printsResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}package_items 
					LEFT JOIN {$dbinfo[pre]}prints 
					ON {$dbinfo[pre]}package_items.item_id = {$dbinfo[pre]}prints.print_id
					WHERE {$dbinfo[pre]}prints.deleted='0' 
					AND {$dbinfo[pre]}package_items.pack_id = '{$package[pack_id]}'
					AND {$dbinfo[pre]}package_items.item_type = 'print'
					ORDER BY {$dbinfo[pre]}prints.sortorder,{$dbinfo[pre]}prints.item_name
					"
				);
				if($printRows = mysqli_num_rows($printsResult))
				{
					while($print = mysqli_fetch_array($printsResult))
					{
						$arrayNum++;
						
						$printDetails = printsList($print);
						if($package['allowoptions'])
							$tempOptions = getProductOptions('prints',$print['print_id'],$print['taxable']);
						
						if($print['groupmult'] == 0) //$tempOptions and 
						{
							for($x=0;$x<$print['iquantity'];$x++)
							{
								$printsArray[$arrayNum] = $printDetails;
								$printsArray[$arrayNum]['options'] = $tempOptions;
								$printsArray[$arrayNum]['quantityDisplay'] = 1;
								$printsArray[$arrayNum]['selectedPhoto'] = 0;
								$printsArray[$arrayNum]['existingMedia'] = $mediaIDs[$arrayNum];
								
								/*
								* If editing this then select the correctly selected items
								*/
								if($edit)
								{
									if($printsArray[$arrayNum]['options'])
									{
										foreach($printsArray[$arrayNum]['options'] as $key => $value)
										{	
											foreach($printsArray[$arrayNum]['options'][$key]['options'] as $key2 => $value2)
											{	
												if($optionSelections[$arrayNum.'-'.$key.'-'.$key2])
													$printsArray[$arrayNum]['options'][$key]['options'][$key2]['selected'] = true; // Set selected option to true
											}
										}
									}
								}
								
								$arrayNum++;
							}
						}
						else
						{
							$printsArray[$arrayNum] = $printDetails;
							$printsArray[$arrayNum]['options'] = $tempOptions;							
							$printsArray[$arrayNum]['quantityDisplay'] = $print['iquantity'];
							$printsArray[$arrayNum]['selectedPhoto'] = 0;
							$printsArray[$arrayNum]['existingMedia'] = $mediaIDs[$arrayNum];
							
							/*
							* If editing this then select the correctly selected items
							*/
							if($edit)
							{
								if($printsArray[$arrayNum]['options'])
								{
									foreach(@$printsArray[$arrayNum]['options'] as $key => $value)
									{	
										foreach(@$printsArray[$arrayNum]['options'][$key]['options'] as $key2 => $value2)
										{	
											if($optionSelections[$arrayNum.'-'.$key.'-'.$key2])
												$printsArray[$arrayNum]['options'][$key]['options'][$key2]['selected'] = true; // Set selected option to true
										}
									}
								}
							}
							
							
						}
					}
				}
				$smarty->assign('prints',$printsArray);
				$smarty->assign('printRows',$printRows);
				
				/*
				* Products within the package
				*/
				$productsResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}package_items 
					LEFT JOIN {$dbinfo[pre]}products 
					ON {$dbinfo[pre]}package_items.item_id = {$dbinfo[pre]}products.prod_id
					WHERE {$dbinfo[pre]}products.deleted='0' 
					AND {$dbinfo[pre]}package_items.pack_id = '{$package[pack_id]}'
					AND {$dbinfo[pre]}package_items.item_type = 'prod'
					ORDER BY {$dbinfo[pre]}products.sortorder,{$dbinfo[pre]}products.item_name
					"
				);
				if($productRows = mysqli_num_rows($productsResult))
				{
					//$arrayNum = 1;
					while($product = mysqli_fetch_array($productsResult))
					{
						$arrayNum++;
						
						$productDetails = productsList($product);
						if($package['allowoptions'])
							$tempOptions = getProductOptions('products',$product['prod_id'],$product['taxable']);
						
						if($product['groupmult'] == 0) //$tempOptions and 
						{
							for($x=0;$x<$product['iquantity'];$x++)
							{
								$productsArray[$arrayNum] = $productDetails;
								$productsArray[$arrayNum]['options'] = $tempOptions;
								$productsArray[$arrayNum]['quantityDisplay'] = 1;
								$productsArray[$arrayNum]['existingMedia'] = $mediaIDs[$arrayNum];
								
								/*
								* If editing this then select the correctly selected items
								*/
								if($edit)
								{
									if($productsArray[$arrayNum]['options'])
									{
										foreach($productsArray[$arrayNum]['options'] as $key => $value)
										{	
											foreach($productsArray[$arrayNum]['options'][$key]['options'] as $key2 => $value2)
											{	
												if($optionSelections[$arrayNum.'-'.$key.'-'.$key2])
													$productsArray[$arrayNum]['options'][$key]['options'][$key2]['selected'] = true; // Set selected option to true
											}
										}
									}
								}
								
								$arrayNum++;
							}
						}
						else
						{
							$productsArray[$arrayNum] = $productDetails;
							$productsArray[$arrayNum]['options'] = $tempOptions;							
							$productsArray[$arrayNum]['quantityDisplay'] = $product['iquantity'];
							$productsArray[$arrayNum]['existingMedia'] = $mediaIDs[$arrayNum];
							
							/*
							* If editing this then select the correctly selected items
							*/
							if($edit)
							{
								if($productsArray[$arrayNum]['options'])
								{
									foreach($productsArray[$arrayNum]['options'] as $key => $value)
									{	
										foreach($productsArray[$arrayNum]['options'][$key]['options'] as $key2 => $value2)
										{	
											if($optionSelections[$arrayNum.'-'.$key.'-'.$key2])
												$productsArray[$arrayNum]['options'][$key]['options'][$key2]['selected'] = true; // Set selected option to true
										}
									}
								}
							}
							
						}
					}
				}
				$smarty->assign('products',$productsArray);
				$smarty->assign('productRows',$productRows);
				
				
				/*
				* Collections within the package
				*/
				$collectionsResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}package_items 
					LEFT JOIN {$dbinfo[pre]}collections 
					ON {$dbinfo[pre]}package_items.item_id = {$dbinfo[pre]}collections.coll_id
					WHERE {$dbinfo[pre]}collections.deleted='0' 
					AND {$dbinfo[pre]}package_items.pack_id = '{$package[pack_id]}'
					AND {$dbinfo[pre]}package_items.item_type = 'coll'
					ORDER BY {$dbinfo[pre]}collections.sortorder,{$dbinfo[pre]}collections.item_name
					"
				);
				if($collectionRows = mysqli_num_rows($collectionsResult))
				{
					while($collection = mysqli_fetch_array($collectionsResult))
					{
						$arrayNum++;
						$collectionsArray[$arrayNum] = collectionsList($collection);
					}
				}
				$smarty->assign('collections',$collectionsArray);
				$smarty->assign('collectionRows',$collectionRows);
				
				/*
				* Subscriptions within the package
				*/
				$subscriptionsResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}package_items 
					LEFT JOIN {$dbinfo[pre]}subscriptions 
					ON {$dbinfo[pre]}package_items.item_id = {$dbinfo[pre]}subscriptions.sub_id
					WHERE {$dbinfo[pre]}subscriptions.deleted='0' 
					AND {$dbinfo[pre]}package_items.pack_id = '{$package[pack_id]}'
					AND {$dbinfo[pre]}package_items.item_type = 'sub'
					ORDER BY {$dbinfo[pre]}subscriptions.sortorder,{$dbinfo[pre]}subscriptions.item_name
					"
				);
				if($subscriptionRows = mysqli_num_rows($subscriptionsResult))
				{
					while($subscription = mysqli_fetch_array($subscriptionsResult))
					{
						$subscriptionsArray[] = subscriptionsList($subscription);
					}
				}
				$smarty->assign('subscriptions',$subscriptionsArray);
				$smarty->assign('subscriptionRows',$subscriptionRows);
				
				
				//$packageArray['options'] = getProductOptions('packages',$packageArray['pack_id']);
				
				$smarty->assign('useMediaID',$useMediaID);
				$smarty->assign('package',$packageArray);
				$smarty->assign('packageRows',$returnRows);
				
				$template = 'package.tpl';
			}
			else
				$smarty->assign('noAccess',1);
		}
		else
			$smarty->assign('noAccess',1);
			
		$smarty->display($template); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>