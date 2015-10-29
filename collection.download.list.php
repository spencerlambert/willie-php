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
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';

	define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	define('META_DESCRIPTION','');
	define('META_KEYWORDS','');
	define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/invoicetools.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';
	
	//sleep(2);
	try
	{
		$queryStr = k_decrypt($dlKey);
		parse_str($queryStr,$downloadKey); // Parse the query string
		
		
		
		//$downloadKey['collectionID'];
		//$downloadKey['uorderID'];
		
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$downloadKey['collectionID'] = k_decrypt($downloadKey['collectionID']); // Collection ID
			$downloadKey['uorderID'] = k_decrypt($downloadKey['uorderID']); // Order ID
			$downloadKey['invoiceItemID'] = k_decrypt($downloadKey['invoiceItemID']); // Invoice Item ID
		}
		
		//print_r($downloadKey); exit;
				
		$invoice = new invoiceTools;
		$invoice->setOrderID($downloadKey['uorderID']); // Set the order ID
		$invoiceItem = $invoice->getSingleInvoiceItem($downloadKey['invoiceItemID']);
		//echo $downloadKey['invoiceItemID']; exit;
		
		if($orderInfo = $invoice->getOrderDetails())
		{
			$collectionResult = mysqli_query($db,
			"			
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}collections 
				WHERE coll_id = '{$downloadKey[collectionID]}'
			"
			);		
			if($returnRows = getRows())
			{
				$collection = mysqli_fetch_array($collectionResult);
	
					$collectionArray = collectionsList($collection);
					$smarty->assign('collectionRows',$returnRows);
					$smarty->assign('collection',$collectionArray);
				
					if($collection['colltype'] == 1)
					{
						$collectionGalleriesResult = mysqli_query($db,"SELECT gallery_id FROM {$dbinfo[pre]}item_galleries WHERE mgrarea = 'collections' AND item_id = '{$downloadKey[collectionID]}'");
						while($collectionGallery = mysqli_fetch_array($collectionGalleriesResult))
							$collectionGalleriesArray[] = $collectionGallery['gallery_id'];
						
						$collectionGalleries = implode(",",$collectionGalleriesArray);
						
						$sql = 
						"
							SELECT SQL_CALC_FOUND_ROWS * 
							FROM {$dbinfo[pre]}media 
							LEFT JOIN {$dbinfo[pre]}media_galleries 
							ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id 
							WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$collectionGalleries})
							AND {$dbinfo[pre]}media.active = 1 
							AND {$dbinfo[pre]}media.approval_status = 1 
							GROUP BY {$dbinfo[pre]}media.media_id
							ORDER BY {$dbinfo[pre]}media.date_added DESC
						"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
					}
					else
					{
						$sql = 
						"
							SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media 
							LEFT JOIN {$dbinfo[pre]}media_collections 
							ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_collections.cmedia_id
							WHERE {$dbinfo[pre]}media_collections.coll_id = '{$downloadKey[collectionID]}'
							AND {$dbinfo[pre]}media.active = 1 
							AND {$dbinfo[pre]}media.approval_status = 1 
							GROUP BY {$dbinfo[pre]}media.media_id
							ORDER BY {$dbinfo[pre]}media.date_added DESC
						"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
					} // Get the total number of items
	
					$media = new mediaList($sql); // Create a new mediaList object
					if($returnRows = $media->getRows()) // Continue only if results are found
					{	
						$media->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
						$mediaArray = $media->getMediaArray(); // Get the array of media		
						$thumbMediaDetailsArray = $media->getDetailsFields('thumb');
						
						//echo $nowGMT . " ---- " . $invoiceItem['expires']; exit; // Testing
						
						foreach($mediaArray as $key => $media)
						{
						
							if($orderInfo['order_status'] == 1)
							{
								// Downloadable Status - 0 = order not approved | 1 = active/ok | 2 = expired | 3 = downloads exceeded | 4 = Not available for download
								if($nowGMT > $invoiceItem['expires'] and $invoiceItem['expires'] != '0000-00-00 00:00:00')
									$downloadableStatus = 2; // Download expired
								else
								{	
									// Check if file is available
									try
									{
										// Get the media information
										$mediaObj = new mediaTools($key);
										$mediaInfo = $mediaObj->getMediaInfoFromDB($key,$media);
										$folderInfo = $mediaObj->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
										//print_r($folderInfo);
										$filecheck = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename]
										
										/*					
										if($itemValue['item_id']) // This is a variation of the original
											$filecheck = $mediaObj->verifyMediaDPFileExists($itemValue['item_id']); // Returns array [stauts,path,filename]
										else
											$filecheck = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename]
										//print_r($filecheck); exit; // Testing
										*/
										
									}
									catch(Exception $e)
									{
										echo $e->getMessage();
										exit;
									}
									
									if($filecheck['status'])
										$downloadableStatus = 1;
									else
										$downloadableStatus = 4;
								}
							}
							else
								$downloadableStatus = 0;
							
							if($config['EncryptIDs']) // Encrypt IDs
							{
								$downloadProfileID = k_encrypt(0); // Size ID
								$downloadMediaID = k_encrypt($key); // Media ID
								$downloadInvoiceItemID = k_encrypt($downloadKey['invoiceItemID']); // Invoice Item ID
								$downloadMemberID = k_encrypt($orderInfo['member_id']); // Member ID
								$downloadTypeID = k_encrypt($orderInfo['order_id']); // Download Type ID / Order ID
							}
							else
							{
								$downloadProfileID = 0; // Size ID
								$downloadMediaID = $key; // Media ID
								$downloadInvoiceItemID = $downloadKey['invoiceItemID']; // Invoice Item ID
								$downloadMemberID = $orderInfo['member_id']; // Member ID
								$downloadTypeID = $orderInfo['order_id']; // Download Type ID / Order ID
							}
														
							$mediaArray[$key]['downloadKey'] = k_encrypt("mediaID={$downloadMediaID}&profileID={$downloadProfileID}&downloadType=order&downloadTypeID={$downloadTypeID}&invoiceItemID={$downloadInvoiceItemID}&memberID={$downloadMemberID}&deliveryMethod=3&collectionDownload=1"); //k_encrypt
							$mediaArray[$key]['downloadableStatus'] = $downloadableStatus;								
						}
						
						$smarty->assign('thumbMediaDetails',$thumbMediaDetailsArray);
						$smarty->assign('mediaRows',$returnRows);
						$smarty->assign('mediaArray',$mediaArray);
					}
					
					//print_k($mediaArray);
					
					$smarty->display('collection.download.list.tpl');
			}
			else
			{
				// No collection with this id
			}
		}
		else
			echo "No Order Found";
		
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>