<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','digital'); // Page ID
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
	require_once BASE_PATH.'/assets/classes/mediatools.php';
		
	try
	{	
		$useMediaID = $mediaID; // Original untouched media ID
		$useID = $id; // Original untouched digital profile ID
		$useCustomizeID = $customizeID; // Original untouched customize ID
		
		//echo 'id'.k_decrypt($id); exit; // Testing
		
		if($edit) // We are editing this item
			$smarty->assign('edit',k_encrypt($edit));
		
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$id = k_decrypt($id);
			$mediaID = k_decrypt($mediaID);
			//$customizeID = k_decrypt($customizeID);
		}
		
		idCheck($id); // Make sure ID is numeric
		idCheck($mediaID); // Make sure ID is numeric
		
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$downloadProfileID = k_encrypt($id); // Size ID
			$downloadMediaID = k_encrypt($mediaID); // Media ID
			//$downloadInvoiceItemID = k_encrypt($itemKey); // Invoice Item ID
			//$downloadMemberID = k_encrypt($orderInfo['member_id']); // Member ID
			//$downloadTypeID = k_encrypt($orderInfo['order_id']); // Download Type ID / Order ID
		}
		else
		{
			$downloadProfileID = $id; // Size ID
			$downloadMediaID = $mediaID; // Media ID
			//$downloadInvoiceItemID = $itemKey; // Invoice Item ID
			//$downloadMemberID = $orderInfo['member_id']; // Member ID
			//$downloadTypeID = $orderInfo['order_id']; // Download Type ID / Order ID
		}
		
		// Check if download is available
		
		
		// Select all the details about the media file
		$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
		//$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media LEFT JOIN {$dbinfo[pre]}licenses ON {$dbinfo[pre]}media.license = {$dbinfo[pre]}licenses.license_id  WHERE media_id = '{$mediaID}'";
		$mediaInfo = new mediaList($sql);
			
		if($mediaInfo->getRows())
		{
			$media = $mediaInfo->getSingleMediaDetails('thumb');
			$galleryIDArray = $mediaInfo->getMediaGalleryIDs(); // Get an array of galleries this media is in			
			$mediaPrice = getMediaPrice($media); // Get the media price based on the license
			$mediaCredits = getMediaCredits($media); // Get the media credits based on the license
			
			// Grab the correct title for this language
			$media['title'] = ($media['title_'.$selectedLanguage]) ? $media['title_'.$selectedLanguage] : $media['title'];
		}
				
		// Get the media information
		$mediaObj = new mediaTools($mediaID);
		$mediaObj->getMediaInfoFromDB($mediaID,$media);
		$folderInfo = $mediaObj->getFolderStorageInfoFromDB($media['folder_id']);
		
		//print_r($filecheck);
		
		$totalPixels = $media['width']*$media['height'];
		
		if($id == 0) // This is an original
		{	
			// Check if this original is instantly available
			if($media['external_link'])
			{
				$externalLink = 1; // External Link
				$filecheck['status'] = (checkExternalFile($media['external_link']) > 400) ? 0 : 1;
				$filecheck['path'] = $media['external_link'];
			}
			else
			{
				$filecheck = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename]
			}
			
			$digital['ds_id'] = 0;
			$digital = digitalsList($media,$mediaID,true);
			$digital['fileCheck'] = $filecheck;
			$digital['width'] = $media['width'];
			$digital['height'] = $media['height'];
			$digital['format'] = $media['format'];
			$digital['license'] = $media['license'];
			$digital['name'] = $lang['original'];
			$digital['quantity'] = ($media['quantity'] == '') ? '1000000' : $media['quantity'];
			$digital['quantityText'] = ($media['quantity'] == '') ? $lang['unlimited'] : $media['quantity'];
			$digital['description'] = ''; // Clear description
			
			$deliveryMethod = 3;
			
			$digital['licenseDescLang'] = ($media['lic_description_'.$selectedLanguage]) ? $media['lic_description_'.$selectedLanguage] : $media['lic_description'];
			$digital['licenseLang'] = ($media['lic_name_'.$selectedLanguage]) ? $media['lic_name_'.$selectedLanguage] : $media['lic_name'];

			//print_r($topLevelRM);		
			
			// File/profile type
			switch($media['dsp_type'])
			{
				case "photo":
					// Get print sizes
					if($config['digitalSizeCalc'] == 'i')
					{
						$digitalsArray[0]['widthIC'] = round($media['width']/$config['dpiCalc'],1).'"';
						$digitalsArray[0]['heightIC'] = round($media['height']/$config['dpiCalc'],1).'"';
					}
					else
					{
						$digitalsArray[0]['widthIC'] = round(($media['width']/$config['dpiCalc']*2.54),1).'cm';
						$digitalsArray[0]['heightIC'] = round(($media['height']/$config['dpiCalc']*2.54),1).'cm';
					}
				break;
				case "video":
					// Print sizes not needed
				break;
				case "other":
					// Print sizes not needed
				break;
			}
		}
		else
		{	
			//$digitalOriginal = digitalsList($media,$mediaID,true);
			//$mediaPrice = $digitalOriginal['price']['raw'];
			//$mediaCredits = $digitalOriginal['credits'];
			// This is a digital variation of - get the details
			$dspResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}media_digital_sizes 
				WHERE ds_id = '{$id}' 
				AND media_id = '{$mediaID}'
				"
			);
			if($dspRows = mysqli_num_rows($dspResult)) // Directly attached to media
				$dsp = mysqli_fetch_array($dspResult);
			else // Attached globally or to category
				$dsp['ds_id'] = $id;
			
			// Get the original digital profile details
			$digitalResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}digital_sizes 
				WHERE ds_id = '{$dsp[ds_id]}'
				"
			);
			if($digitalVarRows = mysqli_num_rows($digitalResult))
			{
				$digital = mysqli_fetch_array($digitalResult);
				
				$deliveryMethod = $digital['delivery_method']; // Delivery method of file
				
				//echo $deliveryMethod;
				
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
					
					// License
					$licenseResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}licenses WHERE license_id = '{$dsp[license]}'");
					$license = mysqli_fetch_assoc($licenseResult);					
					$digital['license_id'] = $license['license_id'];
					$digital['license'] = $license['lic_purchase_type'];
					$digital['lic_purchase_type'] = $license['lic_purchase_type'];
					$digital['licenseDescLang'] = ($license['lic_description_'.$selectedLanguage]) ? $license['lic_description_'.$selectedLanguage] : $license['lic_description'];
					$digital['licenseLang'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
					
					$digital['width'] = ($dsp['width']) ? $dsp['width']: $digital['width'];
					$digital['height'] = ($dsp['height']) ? $dsp['height']: $digital['height'];
					$digital['format'] = ($dsp['format']) ? $dsp['format']: $digital['format'];					
					$digital['running_time'] = ($dsp['running_time']) ? $dsp['running_time']: $digital['running_time'];
					$digital['hd'] = $dsp['hd'];
					$digital['fps'] = ($dsp['fps']) ? $dsp['fps']: $digital['fps'];
					
					//echo "A";
				}
				else
				{
					$digital['quantity'] = '1000000'; // Unlimited
					$digital['quantityText'] = $lang['unlimited'];
					$digital['customizeID'] = 0;
					$digital['useCustomizeID'] = ($config['EncryptIDs']) ? k_encrypt(0) : 0;
					$digital['customized'] = false;
					
					// License
					$licenseResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}licenses WHERE license_id = '{$digital[license]}'");
					$license = mysqli_fetch_assoc($licenseResult);					
					$digital['license_id'] = $license['license_id'];
					$digital['license'] = $license['lic_purchase_type'];
					$digital['lic_purchase_type'] = $license['lic_purchase_type'];
					$digital['licenseDescLang'] = ($license['lic_description_'.$selectedLanguage]) ? $license['lic_description_'.$selectedLanguage] : $license['lic_description'];
					$digital['licenseLang'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
					
					//echo "B";
				}
				
				//echo $digital['license_id'];
				
				if($dsp['filename'] or $dsp['external_link']) // Check if attached file exists
				{					
					// Check if this variation is instantly available
					if($dsp['external_link'])
					{
						$externalLink = 1; // External Link
						$filecheck['status'] = (checkExternalFile($dsp['external_link']) > 400) ? 0 : 1;
						$filecheck['path'] = $dsp['external_link'];
					}
					else
					{
						$filecheck = $mediaObj->verifyMediaDPFileExists($digital['ds_id']); // Returns array [stauts,path,filename]
					}
					
									
					$digital['fileCheck'] = $filecheck; // Check for attached file
					
					$deliveryMethod = 0;
				}
				else
				{					
					if($digital['delivery_method'] != 2)
					{
						if($media['external_link'])
						{
							$externalLink = 1; // External Link
							$filecheck['status'] = (checkExternalFile($media['external_link']) > 400) ? 0 : 1;
							$filecheck['path'] = $media['external_link'];
						}
						else
						{
							$digital['fileCheck'] = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename]
						}
					}
					
					switch($digital['delivery_method'])
					{
						case '0': // Attached file
							// Not needed - checked above
						break;
						case '1': // Create Automatically
							if(in_array($media['file_ext'],getCreatableFormats())) // Check original format
								$digital['autoCreate'] = true;
						break;
						case '2': // Deliver Manually
							
						break;
						case '3': // Deliver Original
							// Check for original
							//$digital['fileCheck'] = $mediaObj->verifyMediaFileExists(); // Returns array [stauts,path,filename];
							
						break;
					}
					
					$deliveryMethod = $digital['delivery_method']; // Shortcut
				}
				
				//$digital['autoCreate'] = $autoCreate; // Auto create setting - set above instead
				
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
				
				$digitalSizePixels = $digital['width']*$digital['height'];
				
				$longestDigitalSizeSide = ($digital['width'] >=  $digital['height']) ? $digital['width'] : $digital['height'];

				if($longestDigitalSizeSide <= $media['width'] or $longestDigitalSizeSide <= $media['height'])
					$validSize = true;
				else
					$validSize = false;
					
				// Make sure that this size is big enough to list or if force list is in effect
				if($validSize == true or $digital['force_list'])
				{	
					$digital = digitalsList($digital,$mediaID);
					
					// If real_sizes is set then calculate the real width and height of this size after it is scaled from the original
					if($digital['real_sizes'])
					{
						// Landscape
						if($media['width'] >= $media['height'])
						{
							@$scaleRatio = $digital['width']/$media['width'];									
							$width = $digital['width'];
							$height = round($media['height']*$scaleRatio);
						}
						// Portrait
						else
						{
							@$scaleRatio = $digital['height']/$media['height'];									
							$width = round($media['width']*$scaleRatio);
							$height = $digital['height'];
						}
					}
					else
					{
						$width = $digital['width'];
						$height = $digital['height'];	
					}
					
					$digital['width'] = $width;
					$digital['height'] = $height;
					
					//echo $digital['license']; exit;
					
					// File/profile type
					switch($digital['dsp_type'])
					{
						case "photo":
							if($config['digitalSizeCalc'] == 'i')
							{
								$digital['widthIC'] = round($width/$config['dpiCalc'],1).'"';
								$digital['heightIC'] = round($height/$config['dpiCalc'],1).'"';
							}
							else
							{
								$digital['widthIC'] = round(($width/$config['dpiCalc']*2.54),1).'cm';
								$digital['heightIC'] = round(($height/$config['dpiCalc']*2.54),1).'cm';
							}
						break;
						case "video":
							
						break;
						case "other":
							
						break;
					}
				}
				
				
				
				/*
				// License type and name
				switch($digital['license'])
				{
					case "cu": // Contact us
						$digital['licenseLang'] = 'mediaLicenseCU';
					break;
					case "ex": // Extended License
						$digital['licenseLang'] = 'mediaLicenseEX';
					break;
					case "eu": // Editorial Use
						$digital['licenseLang'] = 'mediaLicenseEU';
					break;
					case "rf": // Royalty Free
						$digital['licenseLang'] = 'mediaLicenseRF';
					break;
					case "rm": // Rights Managed
						$digital['licenseLang'] = 'mediaLicenseRM';
					break;
					case "fr": // Free Download
						$digital['licenseLang'] = 'mediaLicenseFR';
					break;						
				}
				*/
			}
		}
		
		//print_r($digital);
		
		// RM Calc
		if($digital['lic_purchase_type'] == 'rm')
		{			
			$topLevelResult = mysqli_query($db,
			"
				SELECT * FROM {$dbinfo[pre]}rm_option_grp 
				WHERE og_id NOT IN (SELECT DISTINCT(group_id) FROM {$dbinfo[pre]}rm_ref) 				
				AND license_id = '{$digital[license_id]}' 
			");
			$tlRows = mysqli_num_rows($topLevelResult);
			while($topLevel = mysqli_fetch_assoc($topLevelResult))
			{
				$topLevel['og_name'] = ($topLevel['og_name_'.$selectedLanguage]) ? $topLevel['og_name_'.$selectedLanguage] : $topLevel['og_name'];				
				$topLevelRM[$topLevel['og_id']] = $topLevel;
				
				$topLevelOptionsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}rm_options WHERE og_id = {$topLevel['og_id']}");
				while($topLevelOption = mysqli_fetch_assoc($topLevelOptionsResult))
				{	
					$topLevelOption['op_name'] = ($topLevelOption['op_name_'.$selectedLanguage]) ? $topLevelOption['op_name_'.$selectedLanguage] : $topLevelOption['op_name'];	
					if(!$topLevelOption['price_mod']) $topLevelOption['price_mod'] = '+';
					$topLevelRM[$topLevel['og_id']]['options'][$topLevelOption['op_id']] = $topLevelOption;
				}
			}
			
			//print_k($digital);
			
			//print_r($topLevelRM);
			$smarty->assign('topLevelRM',$topLevelRM);
			
			if($digital['rm_base_type'] == 'cp') // Use custom base price
			{
				$smarty->assign('rmBasePrice',$digital['rm_base_price']);
				$smarty->assign('rmBaseCredits',$digital['rm_base_credits']);
			}
		}
		
		
		
		if($digital['license'] == 'fr')
		{
			$downloadVariableString = "mediaID={$downloadMediaID}&profileID={$downloadProfileID}&downloadType=free&deliveryMethod={$deliveryMethod}";
			if($externalLink == 1) $downloadVariableString .= "&externalLink=".k_encrypt($filecheck['path']); // Add the external link
			
			$digital['downloadKey'] = k_encrypt($downloadVariableString); // Free download key
		}
		else
		{
			$downloadVariableString = "mediaID={$downloadMediaID}&profileID={$downloadProfileID}&downloadType=prevDown&deliveryMethod={$deliveryMethod}";
			if($externalLink == 1) $downloadVariableString .= "&externalLink=".k_encrypt($filecheck['path']); // Add the external link
			
			$digital['downloadKey'] = k_encrypt($downloadVariableString); // Previous download key
		}
		
		//echo $downloadVariableString; exit;
		
		if($_SESSION['loggedIn']) // Check if the member is logged in or not
		{
			// Find active subs that this member has
			if($_SESSION['member']['mem_id'])
			{
				$subscriptionResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}memsubs 
					LEFT JOIN {$dbinfo[pre]}subscriptions 
					ON {$dbinfo[pre]}memsubs.sub_id  = {$dbinfo[pre]}subscriptions.sub_id 
					WHERE {$dbinfo[pre]}memsubs.expires > '{$nowGMT}' 
					AND {$dbinfo[pre]}memsubs.mem_id = '{$_SESSION[member][mem_id]}'
					"
				);
				if($returnRows = mysqli_num_rows($subscriptionResult))
				{
					while($subscription = mysqli_fetch_array($subscriptionResult))
					{
						$downitems = explode(',',$subscription['downitems']);
						
						if(in_array($id,$downitems)) // Check and make sure that they can download this on a sub
						{
							$subsArray[$subscription['msub_id']] = $subscription;
							
							if($subscription['perday'])
							{
								// Find out how many downloads have been done in the last 24 hours and how many are available
								$dateMinus24Hours = date("Y-m-d H:i:s", strtotime("{$nowGMT} -24 hours"));
								$todayDownloadsQuery = mysqli_query($db,"SELECT dl_id FROM {$dbinfo[pre]}downloads WHERE mem_id = '{$_SESSION[member][mem_id]}' AND dl_type = 'sub' AND dl_type_id = '{$subscription[msub_id]}' AND dl_date > '{$dateMinus24Hours}'");
								$todayDownloads = mysqli_num_rows($todayDownloadsQuery);
								
								$subsArray[$subscription['msub_id']]['todayLimit'] = $subscription['perday']; // Total number of downloads available for today
								//$subsArray[$subscription['msub_id']]['todayLimitText'] = $subscription['perday'];								
								$subsArray[$subscription['msub_id']]['todayRemaining'] = $subscription['perday']-$todayDownloads; // Total number of downloads remaining for today
								//$subsArray[$subscription['msub_id']]['todayRemainingText'] = $subscription['perday']-$todayDownloads;
							}
							else
							{
								// Unlimited	
								$subsArray[$subscription['msub_id']]['todayLimit'] = 1000000;
								//$subsArray[$subscription['msub_id']]['todayLimitText'] = $lang['unlimited'];								
								$subsArray[$subscription['msub_id']]['todayRemaining'] = 1000000; // Total number of downloads remaining
								//$subsArray[$subscription['msub_id']]['todayRemainingText'] = $lang['unlimited'];
							}
							
							if($subscription['total_downloads'])
							{
								$totalDownloadsQuery = mysqli_query($db,"SELECT dl_id FROM {$dbinfo[pre]}downloads WHERE mem_id = '{$_SESSION[member][mem_id]}' AND dl_type = 'sub' AND dl_type_id = '{$subscription[msub_id]}'");
								$totalDownloads = mysqli_num_rows($totalDownloadsQuery);
								
								$subsArray[$subscription['msub_id']]['totalLimit'] = $subscription['total_downloads']; // Total number of downloads limit
								$subsArray[$subscription['msub_id']]['totalRemaining'] = $subscription['total_downloads']-$totalDownloads; // Total number of downloads remaining
								//$subsArray[$subscription['msub_id']]['totalRemainingText'] = $subscription['perday']-$todayDownloads;
							}
							else
							{
								$subsArray[$subscription['msub_id']]['totalLimit'] = $subscription['total_downloads']; // Total number of downloads limit
								$subsArray[$subscription['msub_id']]['totalRemaining'] = $subscription['total_downloads']-$totalDownloads; // Total number of downloads remaining
							}
							
							$subsArray[$subscription['msub_id']]['todayDownloads'] = $todayDownloads;
							
							if((($todayDownloads < $subscription['perday']) or !$subscription['perday']) and ($totalDownloads < $subscription['total_downloads'] or !$subscription['total_downloads'])) // Check and see if this subscription is available and has enough downloads remaining to download this file or if it is an unlimited sub
							{
								$subsArray[$subscription['msub_id']]['available'] = true; 
								$subsAvailable++; // Send how many subs are available to smarty
							}
							else
								$subsArray[$subscription['msub_id']]['available'] = false; 
							
							$downloadsUsed = 0; // Reset downloads used
							
							if($config['EncryptIDs']) // Decrypt IDs
								$downloadSubID = k_encrypt($subscription['msub_id']);
							else
								$downloadSubID = $subscription['msub_id'];
							
							$downloadVariableString = "mediaID={$downloadMediaID}&profileID={$downloadProfileID}&downloadType=sub&downloadTypeID={$downloadSubID}&deliveryMethod={$deliveryMethod}";
							if($externalLink == 1) $downloadVariableString .= "&externalLink=".k_encrypt($filecheck['path']); // Add the external link
							
							$subsArray[$subscription['msub_id']]['downloadKey'] = k_encrypt($downloadVariableString);
							
							// xxxxxxxx Get the correct subscription name based on the language file
							$subsArray[$subscription['msub_id']]['expireDate'] = $customDate->showdate($subscription['expires'],0);							
							$subsArray[$subscription['msub_id']]['downitems'] = $downitems; // Find the items that this subscription has access to download
							$subRows++;
						}
					}
				}
				$smarty->assign('subsAvailable',$subsAvailable);
				$smarty->assign('subsArray',$subsArray);
				$smarty->assign('subRows',$subRows);

				// Find previous downloads of this file
				$downloadsResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}downloads 
					WHERE mem_id = '{$_SESSION[member][mem_id]}' 
					AND asset_id = '{$mediaID}' 
					AND dsp_id = '{$id}'
					"
				);
				if($returnRows = mysqli_num_rows($downloadsResult))
				{
					while($download = mysqli_fetch_array($downloadsResult))
					{
						$downloadsArray[$download['dl_id']] = $download;
					}
				}
				$smarty->assign('downloadsArray',$downloadsArray);
				$smarty->assign('downloadRows',$returnRows);
			}
			
			// Credits
		}
			
		//echo $deliveryMethod; exit;
		
		// Check permissions to download this file
		
		// Setup the correct license type and add to cart link
		
		//print_r($digital); exit;
		
		if($digital['rm_base_type'] != 'cp') // Use media base price
		{
			$smarty->assign('rmBasePrice',$digital['price']['preConvNoTax']); // Price with no conversion and no tax
			$smarty->assign('rmBaseCredits',$digital['credits']);
		}
		
		//print_r($digital['price']);
		
		$smarty->assign('digital',$digital);
		$smarty->assign('media',$media);
		//$smarty->assign('useCustomizeID',$useCustomizeID);
		//$smarty->assign('customizeID',$customizeID);
		$smarty->assign('useMediaID',$useMediaID);
		$smarty->assign('mediaID',$mediaID);
		$smarty->assign('useID',$useID);
		$smarty->assign('id',$id);
		$smarty->display('digital.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>