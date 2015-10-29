<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','orderDetails'); // Page ID
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
	
	if(!$_GET['orderID']) // Make sure an order ID is passed and if not die
		die("No order ID was passed");
	
	try
	{
		$invoice = new invoiceTools;
		$invoice->setOrderID($orderID); // Set the order ID
		
		$invoiceTotals = new number_formatting; // Used to make sure the bills are showing in the admins currency
		$invoiceTotals->set_custom_cur_defaults($config['settings']['defaultcur']);
		$parms['noDefault'] = true;
		
		$adminCurrency = getCurrencyInfo($config['settings']['defaultcur']);
				
		if($orderInfo = $invoice->getOrderDetails())
		{
			$invoiceInfo = $invoice->getInvoiceDetailsViaOrderDBID($orderInfo['order_id']);
			$invoiceItemsCount = $invoice->queryInvoiceItems(); // Number of invoice items total
			
			//print_r($invoiceInfo); exit;
			
			$invoice->options = false; // Do not select options here
			
			$digitalInvoiceItems = $invoice->getDigitalItems();
			$physicalInvoiceItems = $invoice->getPhysicalItems();
			
			$orderInfo['orderPlacedDate'] = $customDate->showdate($orderInfo['order_date'],0); // Convert to local date
			
			$invoiceInfo['total'] = $invoiceTotals->currency_display($invoiceInfo['total'],1);
			$invoiceInfo['priceSubTotal'] = $invoiceTotals->currency_display($invoiceInfo['subtotal'],1);
			$invoiceInfo['shippingTotal'] = $invoiceTotals->currency_display($invoiceInfo['shipping_cost'],1);			
			$invoiceInfo['taxA'] = $invoiceTotals->currency_display($invoiceInfo['taxa_cost'],1);
			$invoiceInfo['taxB'] = $invoiceTotals->currency_display($invoiceInfo['taxb_cost'],1);
			$invoiceInfo['taxC'] = $invoiceTotals->currency_display($invoiceInfo['taxc_cost'],1);
			$invoiceInfo['discountsTotal'] = $invoiceTotals->currency_display($invoiceInfo['discounts_total']*-1,1);
			
			$maxDownloadAttempts = ($config['settings']['dl_attempts'] == 0) ? 999 : $config['settings']['dl_attempts']; // Find the max download attempts - if unlimited use 999
			
			// Added the download authorization to prevent sharing of download links
			if($orderInfo['order_status'] == 1) { // Order is approved
				//print_k($orderInfo); exit;
				$_SESSION['downloadAuthorization'] = k_encrypt($orderInfo['order_id']);
			
			} else {
				$_SESSION['downloadAuthorization'] = false;	// Download not authorized
			}
			
			if($digitalInvoiceItems)
			{				
				foreach($digitalInvoiceItems as $itemKey => $itemValue) // Format item prices in admins currency
				{				
					if($itemValue['item_type'] == 'collection')
					{
						if($config['EncryptIDs']) // Decrypt IDs
						{
							$collectionID = k_encrypt($itemValue['item_id']);
							$uorderID = k_encrypt($orderInfo['uorder_id']);
							$invoiceItemID = k_encrypt($itemKey);
						}
						else
						{
							$collectionID = $itemValue['item_id'];
							$uorderID = $orderInfo['uorder_id'];
							$invoiceItemID = $itemKey;
						}
						
						$digitalInvoiceItems[$itemKey]['downloadKey'] = k_encrypt("collectionID={$collectionID}&uorderID={$uorderID}&invoiceItemID={$invoiceItemID}"); //k_encrypt
						$downloadableStatus = 5; // Collection
					}
					else
					{
						if($orderInfo['order_status'] == 1)
						{
							if($config['EncryptIDs']) // Decrypt IDs
							{
								$downloadProfileID = k_encrypt($itemValue['item_id']); // Size ID
								$downloadMediaID = k_encrypt($itemValue['asset_id']); // Media ID
								$downloadInvoiceItemID = k_encrypt($itemKey); // Invoice Item ID
								$downloadMemberID = k_encrypt($orderInfo['member_id']); // Member ID
								$downloadTypeID = k_encrypt($orderInfo['order_id']); // Download Type ID / Order ID
							}
							else
							{
								$downloadProfileID = $itemValue['item_id']; // Size ID
								$downloadMediaID = $itemValue['asset_id']; // Media ID
								$downloadInvoiceItemID = $itemKey; // Invoice Item ID
								$downloadMemberID = $orderInfo['member_id']; // Member ID
								$downloadTypeID = $orderInfo['order_id']; // Download Type ID / Order ID
							}
							
							 // Downloadable Status - 0 = order not approved | 1 = active/ok | 2 = expired | 3 = downloads exceeded | 4 = Not available for download
							if($nowGMT > $itemValue['expires'] and $itemValue['expires'] != '0000-00-00 00:00:00')
								$downloadableStatus = 2; // Download expired
							elseif($itemValue['downloads'] >= $maxDownloadAttempts)
								$downloadableStatus = 3; // Downloads exceeded
							else
							{	
								// Check if file is available
								try
								{
									// Get the media information
									$mediaObj = new mediaTools($itemValue['asset_id']);
									$media = $mediaObj->getMediaInfoFromDB($itemValue['asset_id']);
									$folderInfo = $mediaObj->getFolderStorageInfoFromDB($media['folder_id']);
									
									//echo $itemValue['asset_id']; print_r($folderInfo); echo "<br /><br /><br /><br />"; // testing
														
									if($itemValue['item_id']) // This is a variation of the original
									{	
										$mdspResult = mysqli_query($db,
											"
											SELECT *
											FROM {$dbinfo[pre]}media_digital_sizes 
											WHERE ds_id = '{$itemValue[item_id]}' 
											AND media_id = '{$itemValue[asset_id]}'
											"
										);
										if($mdspRows = mysqli_num_rows($mdspResult))
											$mdsp = mysqli_fetch_assoc($mdspResult); // Get the digital profile details
										
										$dspResult = mysqli_query($db,
											"
											SELECT * 
											FROM {$dbinfo[pre]}digital_sizes 
											WHERE ds_id = '{$itemValue[item_id]}'
											"
										);
										$dsp = mysqli_fetch_assoc($dspResult); // Get the digital profile details
										
										$deliveryMethod = $dsp['delivery_method']; // Delivery method of file
										
										// echo $deliveryMethod.'<br>'; // Testing
										
										switch($deliveryMethod)
										{
											case '0': // Attached file
											
												if($mdsp['filename']) // Check if attached file exists
												{
													// Check if this variation is instantly available
													$filecheck = $mediaObj->verifyMediaDPFileExists($itemValue['item_id']); // Returns array [stauts,path,filename]				
												}
												elseif($mdsp['external_link'])
												{
													$externalLink = 1; // External Link
													
													$filecheck['status'] = (checkExternalFile($mdsp['external_link']) > 400) ? 0 : 1;
													$filecheck['path'] = $mdsp['external_link'];
												}	
												
											break;
											case '1': // Create Automatically
												if(in_array($media['file_ext'],getCreatableFormats())) // Check original format
													$dsp['auto_create'] = true;
										
												// Check for original at either external link or local
												if($media['external_link']) // External Link
												{
													$externalLink = 1; // External Link
													
													$filecheck['status'] = (checkExternalFile($media['external_link']) > 400) ? 0 : 1;
													$filecheck['path'] = $media['external_link'];
												}
												else
												{
													$filecheck = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename]
												}
												
											break;
											case '2': // Deliver Manually
												$filecheck['status'] = 0;
											break;
											case '3': // Deliver Original
												// Check for original
												//$filecheck = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename];
												
												$deliveryMethod = 3; // Added just so there is a setting for this value
												
												// Check for original at either external link or local
												if($media['external_link']) // External Link
												{
													$externalLink = 1; // External Link
													
													$filecheck['status'] = (checkExternalFile($media['external_link']) > 400) ? 0 : 1;
													$filecheck['path'] = $media['external_link'];
												}
												else
												{
													$filecheck = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename]
												}
																							
											break;
										}
									}
									else
									{
										$deliveryMethod = 3; // Added just so there is a setting for this value
										
										if($media['external_link']) // External Link
										{
											$externalLink = 1; // External Link
											
											$filecheck['status'] = (checkExternalFile($media['external_link']) > 400) ? 0 : 1;
											$filecheck['path'] = $media['external_link'];
										}
										else
										{
											$filecheck = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename]
										}
									}
									
									//print_k($filecheck); exit;
									
									//print_r($filecheck); exit; // Testing
								}
								catch(Exception $e)
								{
									echo $e->getMessage();
									exit;
								}
								
								if($filecheck['status'])
									$downloadableStatus = 1;
								else
								{	
									if($dsp['auto_create']) // Attempt auto create
										$downloadableStatus = 1;
									else
										$downloadableStatus = 4;
								}
							}
							
							$downloadVariableString = "mediaID={$downloadMediaID}&profileID={$downloadProfileID}&downloadType=order&downloadTypeID={$downloadTypeID}&invoiceItemID={$downloadInvoiceItemID}&memberID={$downloadMemberID}&deliveryMethod={$deliveryMethod}";
							if($externalLink == 1) $downloadVariableString .= "&externalLink=".k_encrypt($filecheck['path']); // Add the external link
							
							//echo $downloadVariableString; exit;
							
							$digitalInvoiceItems[$itemKey]['downloadKey'] = k_encrypt($downloadVariableString); //k_encrypt
						}
						else
							$downloadableStatus = 0;
					}
						
					//$downloadableStatus = 4; // Testing
					
					$digitalInvoiceItems[$itemKey]['useMediaID'] = ($config['EncryptIDs']) ? k_encrypt($itemValue['asset_id']) : $itemValue['asset_id']; // Add useMediaID 
					$digitalInvoiceItems[$itemKey]['lineItemPriceTotal'] = $invoiceTotals->currency_display($digitalInvoiceItems[$itemKey]['price_total'],1);
					$digitalInvoiceItems[$itemKey]['downloadableStatus'] = $downloadableStatus;
				}
			}
				
			if($physicalInvoiceItems)
			{
				foreach($physicalInvoiceItems as $itemKey => $itemValue) // Format item prices in admins currency
				{
					$physicalInvoiceItems[$itemKey]['lineItemPriceTotal'] = $invoiceTotals->currency_display($physicalInvoiceItems[$itemKey]['price_total'],1);
					
					$shippingStatus = ($itemValue['shipping_status'] == 0) ? $invoiceInfo['ship_status'] : $itemValue['shipping_status'];
					
					if($itemValue['item_type'] == 'subscription' or $itemValue['item_type'] == 'credits') // Make special status types for subscriptions
					{
						$physicalInvoiceItems[$itemKey]['shippingStatusLang'] = ($orderInfo['order_status'] == 1) ? 'active' : 'pending';
					}
					else
					{
						$physicalInvoiceItems[$itemKey]['shippingStatusLang']= shippingStatusNumToText($shippingStatus);
					}
				}
			}
			
			$invoiceInfo['creditsSubTotal'] = $invoiceInfo['credits_total']+$invoiceInfo['discounts_credits_total'];
			$invoiceInfo['totalCreditsDiscounts'] = $invoiceInfo['discounts_credits_total']*-1;
			$invoiceInfo['creditsTotal'] = $invoiceInfo['credits_total'];		
		}
		else
			die("An order with this order id does not exist in our system.");
		
		$smarty->assign('adminCurrency',$adminCurrency); // Admins currency info 
		$smarty->assign('invoiceItemsCount',$invoiceItemsCount); // Number of invoice items 
		$smarty->assign('digitalInvoiceItems',$digitalInvoiceItems); // Digital invoice items
		$smarty->assign('physicalInvoiceItems',$physicalInvoiceItems); // Physical invoice items		
		$smarty->assign('orderInfo',$orderInfo);	
		$smarty->assign('invoiceInfo',$invoiceInfo);			
		$smarty->display('order.details.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
		
	include BASE_PATH.'/assets/includes/debug.php';
	
	clearCartSession();	 // Clear the cart session after loading everything
	
	if($db) mysqli_close($db); // Close any database connections
?>