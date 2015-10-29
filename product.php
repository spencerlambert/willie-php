<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','product'); // Page ID
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
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$id = k_decrypt($id);
			$mediaID = k_decrypt($mediaID);
		}
		
		if($id) idCheck($id); // Make sure ID is numeric
		if($mediaID) idCheck($mediaID); // Make sure ID is numeric
		
		$productResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}products
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'products') 
			WHERE {$dbinfo[pre]}products.prod_id = {$id}
			AND ({$dbinfo[pre]}products.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
			"
		);
		if($returnRows = mysqli_num_rows($productResult))
		{	
			$product = mysqli_fetch_assoc($productResult);
			
			if($product['active'] == 1 and $product['deleted'] == 0 and ($product['quantity'] == '' or $product['quantity'] > 0))
			{	
				/*
				* Get discounts
				*/
				$discountsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}discount_ranges 
					WHERE item_type = 'products' 
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
				
				if($edit) // We are editing this item
				{
					$smarty->assign('edit',k_encrypt($edit));
					
					$invoiceOptionsResult = mysqli_query($db,
						"
						SELECT *
						FROM {$dbinfo[pre]}invoice_options 
						WHERE invoice_item_id IN ({$edit})
						"
					);
					if($invoiceOptionsRows = mysqli_num_rows($invoiceOptionsResult))
					{
						while($invoiceOption = mysqli_fetch_array($invoiceOptionsResult))
							$optionSelections[$invoiceOption['option_gid'].'-'.$invoiceOption['option_id']] = true;
					}
				}
				
				if($mediaID) // Building off of a media file and pricing
				{
					// select the media details
					$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
					$mediaInfo = new mediaList($sql);
					$media = $mediaInfo->getSingleMediaDetails('preview');
					
					$mediaPrice = getMediaPrice($media); // Get the media price based on the license
					$mediaCredits = getMediaCredits($media); // Get the media credits based on the license
					
					$product['price'] = defaultPrice($product['price']); // Make sure to assign a default price if needed
					$product['credits'] = defaultCredits($product['credits']); // Make sure to assign default credits if needed
					
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
					
					$smarty->assign('mediaID',$mediaID);
					$smarty->assign('media',$media);
				}
				
				$productArray = productsList($product);
				
				$productArray['options'] = getProductOptions('products',$productArray['prod_id'],$product['taxable']);
				
				/*
				* If editing this then select the correctly selected items
				*/
				if($edit)
				{
					if($productArray['options'])
					{
						foreach($productArray['options'] as $key => $value)
						{
							foreach($productArray['options'][$key]['options'] as $key2 => $value2)
							{	
								if($optionSelections[$key.'-'.$key2])
									$productArray['options'][$key]['options'][$key2]['selected'] = true; // Set selected option to true
							}
						}
					}
				}
				
				$smarty->assign('useMediaID',$useMediaID);
				$smarty->assign('product',$productArray);
				$smarty->assign('productRows',$returnRows);				
				
				if($mediaID or $product['product_type'] == 2) // See if the cart button should show
					$smarty->assign('cartButton',true);
			}
			else
				$smarty->assign('noAccess',1);
		}
		else
			$smarty->assign('noAccess',1);
			
		//print_k($productArray); // Testing
			
		$smarty->display('product.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>