<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','mediaDetails'); // Page ID
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
	require_once BASE_PATH.'/assets/classes/mediatools.php';

	//echo $_GET['mediaID']; exit;
	/* testing
	$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '715'";
	$mediaObj = new mediaList($sql);
	
	if($mediaObj->getRows())
	{
		
		$mediaObj->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
		$media = $mediaObj->getMediaSingle(); // Get the array of media
		
		//$thumbObj = new mediaTools(715);
		//$thumbnail = $thumbObj->getThumbInfoFromDB();
		
		$smarty->assign('media',$media);
		
		print_r($media);
	}
	
	exit;
	*/
	
	if(strpos($_SERVER['HTTP_REFERER'],'cart.php') or strpos($_SERVER['HTTP_REFERER'],'index.php')) // Clear the crumbs if coming from the cart or index
		unset($_SESSION['crumbsSession']);

	try
	{	
		//$useGalleryID = $galleryID; // Original untouched gallery ID
		$useMediaID = $mediaID; // Original untouched media ID
		
		if(!$mediaID) // Make sure a media ID was passed
			$smarty->assign('noAccess',1);
		else
		{
			if($config['EncryptIDs']) // Decrypt IDs
			{
				$mediaID = k_decrypt($mediaID);
				$useGalleryID = k_encrypt($_SESSION['id']);
			}
			else
				$useGalleryID = $_SESSION['id'];

			//echo $mediaID;

			idCheck($mediaID); // Make sure ID is numeric

				
			$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
			$mediaInfo = new mediaList($sql);
			
			if($mediaInfo->getRows())
			{
				$media = $mediaInfo->getSingleMediaDetails('preview');
				$galleryIDArray = $mediaInfo->getMediaGalleryIDs(); // Get an array of galleries this media is in
				
				if(@!in_array($mediaID,$_SESSION['viewedMedia'])) // See if media has already been viewed
				{
					$newMediaViews = $media['views']+1;
					mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET views='{$newMediaViews}' WHERE media_id = '{$mediaID}'"); // Update views
					$media['views'] = $newMediaViews; // Update the array so the count shown is the new count
					$_SESSION['viewedMedia'][] = $mediaID;
				}
				
				//print_r($media); exit;
				
				/*
				if(!$_SESSION['crumbsSession']) // Get a crumb trail - doesn't work for contibutors yet
				{					
					@$galleryInfo = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_galleries WHERE gmedia_id = '{$mediaID}' ORDER BY mg_id LIMIT 1"));
					if($galleryInfo['gallery_id'])
					{
						$galleriesMainPageLink['page'] = "gallery.php?mode=gallery";
						$_SESSION['galleriesData'][0]['linkto'] = linkto($galleriesMainPageLink); // Check for SEO
						$_SESSION['galleriesData'][0]['name'] = $lang['galleries']; //
						
						$_SESSION['crumbsSession'] = galleryCrumbsFull($galleryInfo['gallery_id']);
					}
				}
				*/
				
				// Check for video sample
				$mediaInfo2 = new mediaTools($mediaID);				
				
				if($media['dsp_type'] == 'video') // Make sure the DSP type is set to video
				{
					if($video = $mediaInfo2->getVidSampleInfoFromDB()) // Make sure video file exists
					{
						
						$videoCheck = $mediaInfo2->verifyVidSampleExists();
						if($videoCheck['status']) { // Make sure the video exists
						
							//print_k($videoCheck); exit;
						
							if($videoCheck['url'] and $config['passVideoThroughPHP'] === false)
								$video['url'] = $videoCheck['url']; // Use URL method
							else
								$video['url'] = $config['settings']['site_url'].'/video.php?mediaID='.$media['encryptedID']; // Use PHP pass-through
						
							//echo $video['url']; exit;
						
							//print_k($video);
							$media['videoStatus'] = 1;
							$media['videoInfo'] = $video;
							
						} else {
							$media['videoStatus'] = 0;	
						}
					}
					else
						$media['videoStatus'] = 0;
				}
				else
				{
					/*
					* Get an estimated preview width and height
					*/
					$sample = $mediaInfo2->getSampleInfoFromDB();	
					$sampleSize = getScaledSizeNoSource($sample['sample_width'],$sample['sample_height'],$config['settings']['preview_size'],$crop=0);				
					$media['previewWidth'] = $sampleSize[0];
					$media['previewHeight'] = $sampleSize[1];
				}
				
				$mediaPrice = getMediaPrice($media); // Get the media price based on the license
				$mediaCredits = getMediaCredits($media); // Get the media credits based on the license
				
				// Get category ID - Make sure member has access to category - maybe add this later
				
				$galleryIDArrayFlat = ($galleryIDArray) ? implode(",",$galleryIDArray) : 0;
				
				/*
				* Prints *****************************************************************************************************************************
				*/
				$galleryPrintsResult = mysqli_query($db,
					"
					SELECT DISTINCT(item_id) 
					FROM {$dbinfo[pre]}item_galleries 
					LEFT JOIN {$dbinfo[pre]}prints 
					ON {$dbinfo[pre]}item_galleries.item_id = {$dbinfo[pre]}prints.print_id
					WHERE {$dbinfo[pre]}item_galleries.gallery_id IN ({$galleryIDArrayFlat}) 
					AND {$dbinfo[pre]}item_galleries.mgrarea = 'prints' 
					AND ({$dbinfo[pre]}prints.attachment = 'media' OR {$dbinfo[pre]}prints.attachment = 'both')
					"
				); // Find out which prints are assigned to galleries this photo is in
				$galleryPrintsRows = mysqli_num_rows($galleryPrintsResult);
				while($galleryPrint = mysqli_fetch_array($galleryPrintsResult))
					$printIDArray[] = $galleryPrint['item_id'];
					
				$mediaPrintsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_prints WHERE media_id = '{$mediaID}'"); // Find what prints have been directly assigned to this photo //  GROUP BY print_id
				$mediaPrintsRows = mysqli_num_rows($mediaPrintsResult);
				//echo $mediaPrintsRows; exit; // Testing
				while($mediaPrint = mysqli_fetch_array($mediaPrintsResult))
				{
					if($mediaPrint['printgrp_id']) // Is a group assignment
					{
						// Select print groups
						$mediaPrintsGroupsResult = mysqli_query($db,
							"
								SELECT * 
								FROM {$dbinfo[pre]}prints 
								LEFT JOIN {$dbinfo[pre]}groupids 
								ON {$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}groupids.item_id 
								WHERE {$dbinfo[pre]}groupids.group_id = '{$mediaPrint[printgrp_id]}' 
								AND {$dbinfo[pre]}prints.active = 1 
								AND {$dbinfo[pre]}prints.deleted = 0 
								AND {$dbinfo[pre]}groupids.mgrarea = 'prints'
							"
						);
						//$pgRows = mysqli_num_rows($mediaPrintsGroupsResult); // Testing
						//echo $pgRows;
						while($mediaPrintsGroup = mysqli_fetch_array($mediaPrintsGroupsResult))
							$printIDArray[] = $mediaPrintsGroup['print_id'];
					}
					else
					{
						$printIDArray[] = $mediaPrint['print_id'];
						
						if($mediaPrint['customized'])
						{
							$printCustomizedIDs[] =  $mediaPrint['print_id']; // Add this ID to the custom array list
							$customPrint[$mediaPrint['print_id']] = $mediaPrint; // Get the actual values for the custom item
						}
					}
				}
				
				if($printIDArray)
					$printsIDArrayFlat = implode(",",$printIDArray);
				else
					$printsIDArrayFlat = 0;
				
				// Now that we have the print ID array select the prints that the customer has access to and assign them to smarty
				$printsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}prints
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'prints') 
					WHERE ({$dbinfo[pre]}prints.print_id IN ({$printsIDArrayFlat}) OR {$dbinfo[pre]}prints.all_galleries = 1) 
					AND {$dbinfo[pre]}prints.active = 1 
					AND {$dbinfo[pre]}prints.deleted = 0
					AND ({$dbinfo[pre]}prints.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}prints.sortorder
					"
				);
				if($returnRows = mysqli_num_rows($printsResult))
				{
					while($print = mysqli_fetch_assoc($printsResult))
					{
						$print['price'] = defaultPrice($print['price']); // Make sure to assign a default price if needed
						$print['credits'] = defaultCredits($print['credits']); // Make sure to assign default credits if needed
						
						/*
						* Custom Pricing calculations
						*/
						if(@in_array($print['print_id'],$printCustomizedIDs))
						{
							$print['price_calc'] = $customPrint[$print['print_id']]['price_calc'];
							$print['price'] = defaultPrice($customPrint[$print['print_id']]['price']);
							$print['credits'] = defaultCredits($customPrint[$print['print_id']]['credits']);
							$print['credits_calc'] = $customPrint[$print['print_id']]['credits_calc'];							
							$print['quantity'] = $customPrint[$print['print_id']]['quantity']; 
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
						
						//echo $mediaCredits.'-'.$print['credits'].'-'.$print['credits_calc']."/";
						
						if($print['quantity'] != '0') // Make sure the quantity is other than 0
						{
							$printsArray[$print['print_id']] = printsList($print,$mediaID);
							
							$optionsResult = mysqli_query($db,"SELECT og_id FROM {$dbinfo[pre]}option_grp WHERE parent_type = 'prints' AND parent_id = '{$print[print_id]}' AND deleted = 0"); // See if there are any options for this item
							if(mysqli_num_rows($optionsResult))
							{
								$printsArray[$print['print_id']]['addToCartLink'] = $printsArray[$print['print_id']]['linkto'];	 // Workbox popup
								$printsArray[$print['print_id']]['directToCart'] = false;	 // Workbox popup
							}
							else
							{
								if($config['EncryptIDs'])
									$printsArray[$print['print_id']]['addToCartLink'] = "{$siteURL}/cart.php?mode=add&type=print&id=".$printsArray[$print['print_id']]['encryptedID']."&mediaID={$media[encryptedID]}"; // Direct to cart
								else
									$printsArray[$print['print_id']]['addToCartLink'] = "{$siteURL}/cart.php?mode=add&type=print&id={$print[print_id]}&mediaID={$media[media_id]}"; // Direct to cart								
								
								$printsArray[$print['print_id']]['directToCart'] = true;	 // Direct to cart
							}
						}
					}
					
					$smarty->assign('printRows',$returnRows);
					$smarty->assign('prints',$printsArray);
				}
				
				/*
				* Digital Files *****************************************************************************************************************************
				*/
				require_once 'media.details.inc.php';
								
				/*
				* Products *****************************************************************************************************************************
				*/
				$galleryProductsResult = mysqli_query($db,
					"
					SELECT DISTINCT(item_id) 
					FROM {$dbinfo[pre]}item_galleries 
					LEFT JOIN {$dbinfo[pre]}products 
					ON {$dbinfo[pre]}item_galleries.item_id = {$dbinfo[pre]}products.prod_id
					WHERE {$dbinfo[pre]}item_galleries.gallery_id IN ({$galleryIDArrayFlat}) 
					AND {$dbinfo[pre]}item_galleries.mgrarea = 'products' 
					AND ({$dbinfo[pre]}products.attachment = 'media' OR {$dbinfo[pre]}products.attachment = 'both')
					"
				); // Find out which products are assigned to galleries this photo is in
				$galleryProductsRows = mysqli_num_rows($galleryProductsResult);
				while($galleryProduct = mysqli_fetch_array($galleryProductsResult))
					$productIDsArray[] = $galleryProduct['item_id'];
					
				$mediaProductsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_products WHERE media_id = '{$mediaID}'"); // Find what products have been directly assigned to this photo //  GROUP BY prod_id
				$mediaProductsRows = mysqli_num_rows($mediaProductsResult);
				while($mediaProduct = mysqli_fetch_array($mediaProductsResult))
				{
					if($mediaProduct['prodgrp_id']) // Is a group assignment
					{
						// Select product groups
						$mediaProductsGroupsResult = mysqli_query($db,
							"
								SELECT * 
								FROM {$dbinfo[pre]}products 
								LEFT JOIN {$dbinfo[pre]}groupids 
								ON {$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}groupids.item_id 
								WHERE {$dbinfo[pre]}groupids.group_id = '{$mediaProduct[prodgrp_id]}' 
								AND {$dbinfo[pre]}products.active = 1 
								AND {$dbinfo[pre]}products.deleted = 0 
								AND {$dbinfo[pre]}groupids.mgrarea = 'products'
							"
						);
						while($mediaProductsGroup = mysqli_fetch_array($mediaProductsGroupsResult))
							$productIDsArray[] = $mediaProductsGroup['prod_id'];
					}
					else
					{
						$productIDsArray[] = $mediaProduct['prod_id'];
						
						if($mediaProduct['customized'])
						{
							$productCustomizedIDs[] =  $mediaProduct['prod_id']; // Add this ID to the custom array list
							$customProduct[$mediaProduct['prod_id']] = $mediaProduct; // Get the actual values for the custom item
						}
					}
				}
				
				if($productIDsArray)
					$productIDsArrayFlat = implode(",",$productIDsArray);
				else
					$productIDsArrayFlat = 0;
				
				//print_r($productCustomizedIDs); exit;
				
				// Now that we have the product ID array select the products that the customer has access to and assign them to smarty
				$productsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}products
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'products') 
					WHERE ({$dbinfo[pre]}products.prod_id IN ({$productIDsArrayFlat}) OR {$dbinfo[pre]}products.all_galleries = 1) 
					AND {$dbinfo[pre]}products.active = 1 
					AND {$dbinfo[pre]}products.deleted = 0
					AND ({$dbinfo[pre]}products.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}products.sortorder
					"
				);
				if($returnRows = mysqli_num_rows($productsResult))
				{	
					while($product = mysqli_fetch_array($productsResult))
					{
						$product['price'] = defaultPrice($product['price']); // Make sure to assign a default price if needed
						$product['credits'] = defaultCredits($product['credits']); // Make sure to assign default credits if needed
						
						/*
						* Custom Pricing calculations
						*/
						if(@in_array($product['prod_id'],$productCustomizedIDs))
						{
							$product['price_calc'] = $customProduct[$product['prod_id']]['price_calc'];
							$product['price'] = defaultPrice($customProduct[$product['prod_id']]['price']);
							$product['credits'] = defaultCredits($customProduct[$product['prod_id']]['credits']);
							$product['credits_calc'] = $customProduct[$product['prod_id']]['credits_calc'];							
							$product['quantity'] = $customProduct[$product['prod_id']]['quantity']; 
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
						
						if($product['quantity'] != '0') // Make sure the quantity is other than 0
						{
							
							if($product['product_type'] == '1') // Check if this is a media based product
								$productsArray[$product['prod_id']] = productsList($product,$mediaID); // Media based
							else
								$productsArray[$product['prod_id']] = productsList($product,false); // Stand Alone
							
							$optionsResult = mysqli_query($db,"SELECT og_id FROM {$dbinfo[pre]}option_grp WHERE parent_type = 'products' AND parent_id = '{$product[prod_id]}' AND deleted = 0"); // See if there are any options for this item
							if(mysqli_num_rows($optionsResult))
							{
								$productsArray[$product['prod_id']]['addToCartLink'] = $productsArray[$product['prod_id']]['linkto'];	 // Workbox popup
								$productsArray[$product['prod_id']]['directToCart'] = false;	 // Workbox popup
							}
							else
							{
								if($config['EncryptIDs'])
								{
									$cartLink = "{$siteURL}/cart.php?mode=add&type=product&id=".$productsArray[$product['prod_id']]['encryptedID'];
									if($product['product_type'] == '1') $cartLink .= "&mediaID={$media[encryptedID]}";
									$productsArray[$product['prod_id']]['addToCartLink'] = $cartLink; // Direct to cart
								}
								else
								{
									$cartLink = "{$siteURL}/cart.php?mode=add&type=product&id={$product[prod_id]}";
									if($product['product_type'] == '1') $cartLink .= "&mediaID={$media[media_id]}";
									$productsArray[$product['prod_id']]['addToCartLink'] = $cartLink; // Direct to cart
								}
								
								$productsArray[$product['prod_id']]['directToCart'] = true;	 // Direct to cart
							}
						}
					}
					$smarty->assign('productRows',$returnRows);
					$smarty->assign('products',$productsArray);
				}
				
				
				/*
				* Collections *****************************************************************************************************************************
				*/
				$galleryCollectionsResult = mysqli_query($db,"SELECT item_id FROM {$dbinfo[pre]}item_galleries WHERE mgrarea = 'collections' AND gallery_id IN ({$galleryIDArrayFlat})"); // Find collections from galleries
				$galleryCollectionsRows = mysqli_num_rows($galleryCollectionsResult);				
				if($galleryCollectionsRows)
				{
					while($galleryCollection = mysqli_fetch_array($galleryCollectionsResult))
						$collectionIDs[] = $galleryCollection['item_id']; 
				}
				
				$mediaCollectionsResult = mysqli_query($db,"SELECT coll_id FROM {$dbinfo[pre]}media_collections WHERE cmedia_id = '{$mediaID}'"); // Find collections this item is directly in
				$mediaCollectionsRows = mysqli_num_rows($mediaCollectionsResult);				
				if($mediaCollectionsRows)
				{
					while($mediaCollection = mysqli_fetch_array($mediaCollectionsResult))
						$collectionIDs[] = $mediaCollection['coll_id']; 
				}
				
				if($collectionIDs) // Only do if some were found
				{
					$collectionIDsFlat = implode(',',$collectionIDs);
					
					$collectionsResult = mysqli_query($db,
						"
						SELECT *
						FROM {$dbinfo[pre]}collections 
						LEFT JOIN {$dbinfo[pre]}perms
						ON ({$dbinfo[pre]}collections.coll_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'collections') 
						WHERE {$dbinfo[pre]}collections.active = 1 
						AND {$dbinfo[pre]}collections.deleted = 0
						AND ({$dbinfo[pre]}collections.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
						AND ({$dbinfo[pre]}collections.quantity = '' OR {$dbinfo[pre]}collections.quantity > '0') 
						AND {$dbinfo[pre]}collections.coll_id IN ({$collectionIDsFlat})
						ORDER BY {$dbinfo[pre]}collections.sortorder
						"
					); // Select collections that member has access to
					if($returnRows = mysqli_num_rows($collectionsResult))
					{
						while($collections = mysqli_fetch_array($collectionsResult))
						{
							$collectionsArray[$collections['coll_id']] = collectionsList($collections);
							$collectionsWithAccess[] = $collections['coll_id'];
						}
		
						$smarty->assign('collectionRows',$returnRows);
						$smarty->assign('collections',$collectionsArray);
					}
				}
				
				/*
				* Packages *****************************************************************************************************************************
				*/
				
				/*
				$galleryPackagesResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}packages 
					WHERE all_galleries = 1 
					AND (attachment = 'media' OR attachment = 'both')
					"
				); // Find packages that are assigned to all galleries and are attached to media or both
				$galleryPackagesRows = mysqli_num_rows($galleryPackagesResult);
				while($galleryPackage = mysqli_fetch_array($galleryPackagesResult))
					$packageIDsArray[] = $galleryPackage['pack_id'];
				*/
				
				$galleryPackagesResult = mysqli_query($db,
					"
					SELECT DISTINCT(item_id) 
					FROM {$dbinfo[pre]}item_galleries 
					LEFT JOIN {$dbinfo[pre]}packages 
					ON {$dbinfo[pre]}item_galleries.item_id = {$dbinfo[pre]}packages.pack_id
					WHERE {$dbinfo[pre]}item_galleries.gallery_id IN ({$galleryIDArrayFlat}) 
					AND {$dbinfo[pre]}item_galleries.mgrarea = 'packages' 
					AND ({$dbinfo[pre]}packages.attachment = 'media' OR {$dbinfo[pre]}packages.attachment = 'both') 
					"
				); // Find out which packages are assigned to galleries this photo is in or all galleries and attached to media or both
				$galleryPackagesRows = mysqli_num_rows($galleryPackagesResult);
				while($galleryPackage = mysqli_fetch_array($galleryPackagesResult))
					$packageIDsArray[] = $galleryPackage['item_id'];
				
				//print_r($packageIDsArray); // Testing
				
				$mediaPackagesResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_packages WHERE media_id = '{$mediaID}'"); // Find what packages have been directly assigned to this photo //  GROUP BY pack_id
				$mediaPackagesRows = mysqli_num_rows($mediaPackagesResult);
				while($mediaPackage = mysqli_fetch_array($mediaPackagesResult))
				{
					if($mediaPackage['packgrp_id']) // Is a group assignment
					{
						// Select package groups
						$mediaPackagesGroupsResult = mysqli_query($db,
							"
								SELECT * 
								FROM {$dbinfo[pre]}packages 
								LEFT JOIN {$dbinfo[pre]}groupids 
								ON {$dbinfo[pre]}packages.pack_id = {$dbinfo[pre]}groupids.item_id 
								WHERE {$dbinfo[pre]}groupids.group_id = '{$mediaPackage[packgrp_id]}' 
								AND {$dbinfo[pre]}packages.active = 1 
								AND {$dbinfo[pre]}packages.deleted = 0 
								AND {$dbinfo[pre]}groupids.mgrarea = 'packages'
							"
						);
						while($mediaPackagesGroup = mysqli_fetch_array($mediaPackagesGroupsResult))
							$packageIDsArray[] = $mediaPackagesGroup['pack_id'];
					}
					else
					{
						$packageIDsArray[] = $mediaPackage['pack_id'];
					}
				}
				
				// Select collections from this package and check those against the collection list already created
				if($collectionsWithAccess)
					$collectionsWithAccessFlat = implode(',',$collectionsWithAccess);
				else
					$collectionsWithAccessFlat = 0;				
				
				$mediaCollectionsPackagesResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}package_items WHERE item_type = 'coll' AND item_id IN ({$collectionsWithAccessFlat})"); // Select which collections in the package that contain this photo
				$mediaCollectionsRows = mysqli_num_rows($mediaCollectionsPackagesResult);
				while($mediaCollectionsPackage = mysqli_fetch_array($mediaCollectionsPackagesResult))
					$packageIDsArray[] = $mediaCollectionsPackage['pack_id'];
				
				if($packageIDsArray)
					$packagesIDsArrayFlat = implode(",",$packageIDsArray);
				else
					$packagesIDsArrayFlat = 0;
				
				// Now that we have the package ID array select the packages that the customer has access to and assign them to smarty
				$packagesResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}packages
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}packages.pack_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'packages') 
					WHERE ({$dbinfo[pre]}packages.pack_id IN ({$packagesIDsArrayFlat}) OR {$dbinfo[pre]}packages.all_galleries = 1)
					AND {$dbinfo[pre]}packages.active = 1 
					AND {$dbinfo[pre]}packages.deleted = 0
					AND ({$dbinfo[pre]}packages.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB})) 
					ORDER BY {$dbinfo[pre]}packages.sortorder
					"
				);
				if($returnRows = mysqli_num_rows($packagesResult))
				{
					while($package = mysqli_fetch_assoc($packagesResult))
					{
						$packagesArray[$package['pack_id']] = packagesList($package,$mediaID);
					}
					
					$smarty->assign('packageRows',$returnRows);
					$smarty->assign('packages',$packagesArray);
				}
				
				/*
				* IPTC
				*/
				if($config['settings']['display_iptc'])
				{					
					$iptcResult = mysqli_query($db,
						"
						SELECT *
						FROM {$dbinfo[pre]}media_iptc 
						WHERE media_id = '{$mediaID}'
						"
					);
					if($iptcRows = mysqli_num_rows($iptcResult))
					{	
						$iptc = mysqli_fetch_array($iptcResult);
						$media['iptc']['iptc_title'] 				= $iptc['title'];
						$media['iptc']['iptc_description'] 			= $iptc['description'];
						$media['iptc']['iptc_instructions'] 		= $iptc['instructions'];
						$media['iptc']['iptc_date_created'] 		= $iptc['date_created'];
						$media['iptc']['iptc_author'] 				= $iptc['author'];
						$media['iptc']['iptc_city'] 				= $iptc['city'];
						$media['iptc']['iptc_state'] 				= $iptc['state'];
						$media['iptc']['iptc_country'] 				= $iptc['country'];
						$media['iptc']['iptc_job_identifier']		= $iptc['job_identifier'];
						$media['iptc']['iptc_headline'] 			= $iptc['headline'];
						$media['iptc']['iptc_provider'] 			= $iptc['provider'];
						$media['iptc']['iptc_source'] 				= $iptc['source'];
						$media['iptc']['iptc_description_writer']	= $iptc['description_writer'];
						$media['iptc']['iptc_urgency'] 				= $iptc['urgency'];
						$media['iptc']['iptc_copyright_notice'] 	= $iptc['copyright_notice'];
						$smarty->assign('iptcRows',$iptcRows);
					}
				}
				
				/*
				* EXIF
				*/
				if($config['settings']['display_exif'] or $config['settings']['gpsonoff']) // Check to see if EXIF info needs to be queried
				{
					$exifResult = mysqli_query($db,
						"
						SELECT *
						FROM {$dbinfo[pre]}media_exif 
						WHERE media_id = '{$mediaID}'
						"
					);
					if($exifRows = mysqli_num_rows($exifResult))
					{
						$exif = mysqli_fetch_array($exifResult);
						$media['exif']['exif_FileName']					= $exif['FileName'];
						$media['exif']['exif_FileDateTime']				= $exif['FileDateTime'];
						$media['exif']['exif_FileSize']					= $exif['FileSize'];
						$media['exif']['exif_FileType']					= $exif['FileType'];
						$media['exif']['exif_MimeType']					= $exif['MimeType'];
						$media['exif']['exif_SectionsFound']			= $exif['SectionsFound'];
						$media['exif']['exif_ImageDescription']			= $exif['ImageDescription'];
						$media['exif']['exif_Make']						= $exif['Make'];
						$media['exif']['exif_Model']					= $exif['Model'];
						$media['exif']['exif_Orientation']				= $exif['Orientation'];
						$media['exif']['exif_XResolution']				= $exif['XResolution'];
						$media['exif']['exif_YResolution']				= $exif['YResolution'];
						$media['exif']['exif_ResolutionUnit']			= $exif['ResolutionUnit'];
						$media['exif']['exif_Software']					= $exif['Software'];
						$media['exif']['exif_DateTime']					= $exif['DateTime'];
						$media['exif']['exif_YCbCrPositioning']			= $exif['YCbCrPositioning'];
						$media['exif']['exif_Exif_IFD_Pointer']			= $exif['Exif_IFD_Pointer'];
						$media['exif']['exif_GPS_IFD_Pointer']			= $exif['GPS_IFD_Pointer'];
						$media['exif']['exif_ExposureTime']				= $exif['ExposureTime'];
						$media['exif']['exif_FNumber']					= $exif['FNumber'];
						$media['exif']['exif_ExposureProgram']			= $exif['ExposureProgram'];
						$media['exif']['exif_ISOSpeedRatings']			= $exif['ISOSpeedRatings'];
						$media['exif']['exif_ExifVersion']				= $exif['ExifVersion'];
						$media['exif']['exif_DateTimeOriginal']			= $exif['DateTimeOriginal'];
						$media['exif']['exif_DateTimeDigitized']		= $exif['DateTimeDigitized'];
						$media['exif']['exif_ComponentsConfiguration']	= $exif['ComponentsConfiguration'];
						$media['exif']['exif_ShutterSpeedValue']		= $exif['ShutterSpeedValue'];
						$media['exif']['exif_ApertureValue']			= $exif['ApertureValue'];
						$media['exif']['exif_MeteringMode']				= $exif['MeteringMode'];
						$media['exif']['exif_Flash']					= $exif['Flash'];
						$media['exif']['exif_FocalLength']				= $exif['FocalLength'];
						$media['exif']['exif_FlashPixVersion']			= $exif['FlashPixVersion'];
						$media['exif']['exif_ColorSpace']				= $exif['ColorSpace'];
						$media['exif']['exif_ExifImageWidth']			= $exif['ExifImageWidth'];
						$media['exif']['exif_ExifImageLength']			= $exif['ExifImageLength'];
						$media['exif']['exif_SensingMethod']			= $exif['SensingMethod'];
						$media['exif']['exif_ExposureMode']				= $exif['ExposureMode'];
						$media['exif']['exif_WhiteBalance']				= $exif['WhiteBalance'];
						$media['exif']['exif_SceneCaptureType']			= $exif['SceneCaptureType'];
						$media['exif']['exif_Sharpness']				= $exif['Sharpness'];
						$media['exif']['exif_GPSLatitudeRef']			= $exif['GPSLatitudeRef'];
						$media['exif']['exif_GPSLatitude_0']			= $exif['GPSLatitude_0'];
						$media['exif']['exif_GPSLatitude_1']			= $exif['GPSLatitude_1'];
						$media['exif']['exif_GPSLatitude_2']			= $exif['GPSLatitude_2'];
						$media['exif']['exif_GPSLongitudeRef']			= $exif['GPSLongitudeRef'];
						$media['exif']['exif_GPSLongitude_0']			= $exif['GPSLongitude_0'];
						$media['exif']['exif_GPSLongitude_1']			= $exif['GPSLongitude_1'];
						$media['exif']['exif_GPSLongitude_2']			= $exif['GPSLongitude_2'];
						$media['exif']['exif_GPSTimeStamp_0']			= $exif['GPSTimeStamp_0'];
						$media['exif']['exif_GPSTimeStamp_1']			= $exif['GPSTimeStamp_1'];
						$media['exif']['exif_GPSTimeStamp_2']			= $exif['GPSTimeStamp_2'];
						$media['exif']['exif_GPSImgDirectionRef']		= $exif['GPSImgDirectionRef'];
						$media['exif']['exif_GPSImgDirection']			= $exif['GPSImgDirection'];
						
						if($config['settings']['display_exif']) $smarty->assign('exifRows',$exifRows); // Only pass if EXIF info is found and should be displayed on the page
					}
				}
				
				//echo $config['settings']['gpsonoff']; exit;
				
				// Fetch and generate GPS latitude and longitude location for google maps
				if($config['settings']['gpsonoff'] == 1){
					if($exif['GPSLatitude_0'])
					{
						$geoLocation = readGPSinfoEXIF($exif['GPSLatitudeRef'],$exif['GPSLatitude_0'],$exif['GPSLatitude_1'],$exif['GPSLatitude_2'],$exif['GPSLongitudeRef'],$exif['GPSLongitude_0'],$exif['GPSLongitude_1'],$exif['GPSLongitude_2']);
						$media['latitude'] = $geoLocation[0];
						$media['longitude'] = $geoLocation[1];
						
						//echo "a"; exit;
					}
					else
					{
						if($iptc['city'] and $iptc['state'])
						{
							$media['latitude'] = $iptc['city'];
							$media['longitude'] = $iptc['state'];
						}
						
						//echo "b"; exit;
					}
				}
				
				//echo $_SESSION['backButtonSession']['linkto'];
				
				if($_SESSION['backButtonSession'])
					$smarty->assign('backButton',$_SESSION['backButtonSession']);
				
				/*
				$images = $_SESSION['imagenav'];
				$image = preg_split("/,/",$images);
				$ptr = $package->id;
				// Current image id being viewed out of the image array
				reset($image);
				while ($start = current($image)) {
				if ($start == $ptr) {
				$ptr1 = current($image);
				break;
				}
				next($image);
				
				reset($_SESSION['prevNextArraySess']);
				while($startPrevNext = current($_SESSION['prevNextArraySess'])
				*/
				
				if($_SESSION['prevNextArraySess'])
				{
					$findCurrentKey = array_search($media['media_id'],$_SESSION['prevNextArraySess']); // Find the current key for the media id in the prevNextArraySess
					$prevButtonID = $_SESSION['prevNextArraySess'][$findCurrentKey-1]; // Current key minus one
					$nextButtonID = $_SESSION['prevNextArraySess'][$findCurrentKey+1]; // Current key plus one
				}
				
				//echo 'prev'.$prevButtonID.'<br>';
				//echo 'next'.$nextButtonID.'<br>';
					
				//print_r($_SESSION['prevNextArraySess']);
				//echo "<br><br>".$_SESSION['currentMode'];
				
				//echo $media['details']['description']['value']; exit; // Testing
				
				if($media['details']['keywords']['value']) $keywordsFlat = implode(',',$media['details']['keywords']['value']); // Get a flattened version of the keywords				
				$smarty->assign('metaTitle',($media['details']['title']['value']) ? $media['details']['title']['value'] .' &ndash; '.$config['settings']['site_title'] : $config['settings']['site_title']); // Assign meta title
				$smarty->assign('metaKeywords',($media['details']['keywords']) ? $keywordsFlat : $config['settings']['site_keywords']); // Assign meta keywords
				$smarty->assign('metaDescription',($media['details']['description']['value']) ? strip_tags($media['details']['description']['value']) : strip_tags($config['settings']['site_description'])); // Assign meta description		
				
				//echo $media['title']; exit; // testing
				
				//print_r($media); exit;				
				if($prevButtonID)
				{
					if($config['EncryptIDs'])
						$prevButtonID = k_encrypt($prevButtonID);
						
					$smarty->assign('prevButtonID',$prevButtonID);
				}
				if($nextButtonID)
				{
					if($config['EncryptIDs'])
						$nextButtonID = k_encrypt($nextButtonID);
						
					$smarty->assign('nextButtonID',$nextButtonID);
				}
				
				$smarty->assign('crumbs',$_SESSION['crumbsSession']);
				
				//print_k($media); exit;
				
				$smarty->assign('media',$media);
				$smarty->assign('useGalleryID',$useGalleryID); // Gallery ID
				$smarty->assign('galleryMode',$_SESSION['currentMode']); // Gallery Mode
				$smarty->assign('useMediaID',$useMediaID); // Gallery ID
			}
			else
				$smarty->assign('noAccess',1);	
			
		}
		
		//$cleanvalues = new number_formatting; // Used to make sure the bills are showing in the admins currency
		//$cleanvalues->set_custom_cur_defaults($config['settings']['defaultcur']);		
		//require_once('mgr.defaultcur.php');		
		//$cleanvalues = new number_formatting;
		//$cleanvalues->set_num_defaults(); // SET THE DEFAULTS
		//$cleanvalues->set_cur_defaults(); // SET THE CURRENCY DEFAULTS
		//$cleanvalues->currency_clean($addshipping);
		//print_r($priCurrency['currency_id']); 
		
		//print_k($media);
		
		//print_r($media['details']['owner']);		
		$smarty->display('media.details.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>