<?php
	switch($incMode)
	{
		default:
		case "digital":
		
		/*
		* Digital Files *****************************************************************************************************************************
		*/
		
		//if($media['owner'] != $_SESSION['member']['mem_id'] or $media['owner'] == 0) // Hide digitals if owner is the one viewing them
		//{
			// Check for original file for sale
			/*
			if($media['license'] != 'nfs')
			{
				
				$digitalsArray[0]['name'] = 'Original';
				$digitalsArray[0]['license'] = $media['license'];
				$digitalsArray[0]['price'] = $mediaPrice;
				
				$digitalsArray[0]['width'] = '';
				$digitalsArray[0]['height'] = '';
				$digitalsArray[0]['type'] = 'image';
				
				if($digitalsArray[0]['type'] == 'video')
				{
					// xxxxx video settings
				}					
				
				$digitalRows++; // Add 1 to the number of digital items available
				// xxx Check quantity?
			}
			*/
			
			// Add original if it is for sale
			if($media['license'] != 'nfs' and ($media['quantity'] > 0 or $media['quantity'] == ''))
			{	
				$media['ds_id'] = 0;
				$media['customizeID'] = 0;
				//$digital['ds_id'] = 0;
				//$digital['customizeID'] = 0;
				$digitalsArray[0] = digitalsList($media,$mediaID,true);
				$digitalsArray[0]['width'] = $media['width'];
				$digitalsArray[0]['height'] = $media['height'];
				$digitalsArray[0]['format'] = $media['format'];
				$digitalsArray[0]['license'] = $media['license'];
				$digitalsArray[0]['name'] = $lang['original'];
				$digitalsArray[0]['quantity'] = $media['quantity'];
				$digitalsArray[0]['description'] = ''; // Clear description
				
				$digitalRows = 1;
				
				$digitalsArray[0]['licenseLang'] = ($media['lic_name_'.$selectedLanguage]) ? $media['lic_name_'.$selectedLanguage] : $media['lic_name']; // License name
				
				//print_r($digitalsArray[0]);
				
				/*
				// License
				$licenseResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}licenses WHERE license_id = '{$digital[license]}'");
				$license = mysqli_fetch_assoc($licenseResult);					
				$digital['license'] = $license['lic_purchase_type'];
				$digital['licenseLang'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
				*/
				
				/*
				// License type and name
				switch($media['license'])
				{
					case "cu": // Contact us
						$digitalsArray[0]['licenseLang'] = 'mediaLicenseCU';
					break;
					case "rf": // Royalty Free
						$digitalsArray[0]['licenseLang'] = 'mediaLicenseRF';
					break;
					case "ex": // Extended
						$digitalsArray[0]['licenseLang'] = 'mediaLicenseEX';
					break;
					case "eu": // Editorial Use
						$digitalsArray[0]['licenseLang'] = 'mediaLicenseEU';
					break;
					case "rm": // Rights Managed
						$digitalsArray[0]['licenseLang'] = 'mediaLicenseRM';
					break;
					case "fr": // Free Download
						$digitalsArray[0]['licenseLang'] = 'mediaLicenseFR';
					break;						
				}
				*/
				/*
				if($media['license'] == 'rf' or $media['license'] == 'rm')
				{
					// Get the pricing information
					$digitalsArray[0]['price'] = defaultPrice($media['price']); // Make sure to assign a default price if needed
					$digitalsArray[0]['credits'] = defaultCredits($media['credits']); // Make sure to assign default credits if needed
				}
				*/
				
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
			
			// Get the digital sizes assigned to this media file
			$digitalIDsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}media_digital_sizes 
				WHERE media_id = '{$mediaID}'
				"
			);
			if($digitalIDsRows = mysqli_num_rows($digitalIDsResult))
			{
				while($digitalIDs = mysqli_fetch_array($digitalIDsResult))
				{
					
					if($digitalIDs['dsgrp_id']) // Is a group assignment
					{
						// Select print groups
						$mediaDigitalsGroupsResult = mysqli_query($db,
							"
								SELECT * 
								FROM {$dbinfo[pre]}digital_sizes 
								LEFT JOIN {$dbinfo[pre]}groupids 
								ON {$dbinfo[pre]}digital_sizes.ds_id = {$dbinfo[pre]}groupids.item_id 
								WHERE {$dbinfo[pre]}groupids.group_id = '{$digitalIDs['dsgrp_id']}' 
								AND {$dbinfo[pre]}digital_sizes.active = 1 
								AND {$dbinfo[pre]}digital_sizes.deleted = 0 
								AND {$dbinfo[pre]}groupids.mgrarea = 'digital_sp'
							"
						);
						//$pgRows = mysqli_num_rows($mediaDigitalsGroupsResult); // Testing
						//echo $pgRows;
						while($mediaDigitalsGroup = mysqli_fetch_array($mediaDigitalsGroupsResult))
							$digitalIDsArray[$mediaDigitalsGroup['ds_id']] = $mediaDigitalsGroup['ds_id'];
					}
					else
					{
						$digitalIDsArray[$digitalIDs['ds_id']] = $digitalIDs['ds_id'];
					}
					
					if($digitalIDs['customized']) // See if there is customization on this item
					{
						$digitalCustomizedIDs[] = $digitalIDs['ds_id'];
						$customDigitalItems[$digitalIDs['ds_id']] = $digitalIDs;
					}
				}
			}
			
			//$digitalIDsArrayFlat = implode(",",$digitalIDsArray); // Flatten the array
			
			$totalPixels = $media['width']*$media['height'];
			
			// Find digital profiles that are assigned directly to galleries
			$galleryDigitalsResult = mysqli_query($db,
				"
				SELECT DISTINCT({$dbinfo[pre]}item_galleries.item_id) 
				FROM {$dbinfo[pre]}item_galleries 
				LEFT JOIN {$dbinfo[pre]}digital_sizes 
				ON {$dbinfo[pre]}item_galleries.item_id = {$dbinfo[pre]}digital_sizes.ds_id 
				WHERE {$dbinfo[pre]}item_galleries.gallery_id IN ({$galleryIDArrayFlat}) 
				AND ({$dbinfo[pre]}item_galleries.mgrarea = 'digital_sp' OR {$dbinfo[pre]}digital_sizes.all_galleries = 1)
				"
			); // Find out which digital sizes are assigned to galleries this photo is in
			$galleryDigitalsRows = mysqli_num_rows($galleryDigitalsResult);
			while($galleryDigital = mysqli_fetch_array($galleryDigitalsResult))
			{
				//$galleryDigitalsIDsArray[] = $galleryDigital['item_id'];
				$digitalIDsArray[$galleryDigital['item_id']] = $galleryDigital['item_id'];
			}
			//$galleryDigitalsIDsArrayFlat = implode(",",$galleryDigitalsIDsArray); // Flatten the array
			
			if($digitalIDsArray)
				$digitalIDsArrayFlat = implode(",",$digitalIDsArray); // Flatten the array
			else
				$digitalIDsArrayFlat = 0;
			
			// Get the digital variations that the member has access to
			$digitalResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}digital_sizes  
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}digital_sizes.ds_id  = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'digital_sp') 
				WHERE ({$dbinfo[pre]}digital_sizes.ds_id IN ({$digitalIDsArrayFlat}) OR {$dbinfo[pre]}digital_sizes.all_galleries = 1)
				AND {$dbinfo[pre]}digital_sizes.active = 1 
				AND {$dbinfo[pre]}digital_sizes.deleted = 0
				AND ({$dbinfo[pre]}digital_sizes.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				ORDER BY {$dbinfo[pre]}digital_sizes.sortorder
				"
			);
			
			//echo $digitalVarRows = mysqli_num_rows($digitalResult); exit;
			
			if($digitalVarRows = mysqli_num_rows($digitalResult))
			{	
				$digitalRows = $digitalRows+$digitalVarRows; // Add digital variation count to digital rows count
				
				while($digital = mysqli_fetch_array($digitalResult))
				{	
					//echo $digital['ds_id'].', ';
					$digitalSizePixels = $digital['width']*$digital['height'];
					
					//$media['width'] = intval($media['width']);
					//$media['height'] = intval($media['height']);
					
					$longestDigitalSizeSide = ($digital['width'] >=  $digital['height']) ? $digital['width'] : $digital['height'];
					//$longestDigitalSizeSide = intval($longestDigitalSizeSide);
					
					/*
					echo "longestDigitalSizeSide: ";
					var_dump($longestDigitalSizeSide);
					echo "<br>";
					echo "width: ";
					var_dump($media['width']);
					echo "<br>";
					echo "height: ";
					var_dump($media['height']);
					echo "<br>";
					*/
					
					if($longestDigitalSizeSide <= $media['width'] or $longestDigitalSizeSide <= $media['height'])
						$validSize = true;
					else
						$validSize = false;
					
					// Make sure that this size is big enough to list or if force list is in effect
					if($validSize == true or $digital['force_list']) //$totalPixels >= $digitalSizePixels - old
					{
						//echo "id".$digital['ds_id']." {$longestDigitalSizeSide} <= ({$media[width]} or {$media[height]})".'<br>';
						//echo $digital['ds_id'].'yes<br>';
						
						/*
						* Custom Pricing calculations
						*/
						if(@in_array($digital['ds_id'],$digitalCustomizedIDs))
						{
							$digital['price_calc'] = $customDigitalItems[$digital['ds_id']]['price_calc'];
							$digital['price'] = defaultPrice($customDigitalItems[$digital['ds_id']]['price']);
							$digital['credits'] = defaultCredits($customDigitalItems[$digital['ds_id']]['credits']);
							$digital['credits_calc'] = $customDigitalItems[$digital['ds_id']]['credits_calc'];							
							$digital['quantity'] = $customDigitalItems[$digital['ds_id']]['quantity'];
							$digital['customized'] = true;
							$digital['customizeID'] = $customDigitalItems[$digital['ds_id']]['mds_id'];
							
							//print_r($customDigitalItems);
							//echo $customDigitalItems[$digital['ds_id']]['license'];
														
							// License
							$licenseResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}licenses WHERE license_id = '".$customDigitalItems[$digital['ds_id']]['license']."'");
							$license = mysqli_fetch_assoc($licenseResult);
							
							$digital = array_merge($digital,$license); // Merge the digital profile and license
							
							$digital['license'] = $license['lic_purchase_type'];
							$digital['licenseLang'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
							
							$digital['width'] = ($customDigitalItems[$digital['ds_id']]['width']) ? $customDigitalItems[$digital['ds_id']]['width']: $digital['width'];
							$digital['height'] = ($customDigitalItems[$digital['ds_id']]['height']) ? $customDigitalItems[$digital['ds_id']]['height']: $digital['height'];
							
							$digital['format'] = ($customDigitalItems[$digital['ds_id']]['format']) ? $customDigitalItems[$digital['ds_id']]['format']: $digital['format'];
							
							$digital['running_time'] = ($customDigitalItems[$digital['ds_id']]['running_time']) ? $customDigitalItems[$digital['ds_id']]['running_time']: $digital['running_time'];
							$digital['hd'] = $customDigitalItems[$digital['ds_id']]['hd'];
							$digital['fps'] = ($customDigitalItems[$digital['ds_id']]['fps']) ? $customDigitalItems[$digital['ds_id']]['fps']: $digital['fps'];
							
							$digital['fps'] = trim($digital['fps'],"0,.");
						}
						else
						{
							$digital['quantity'] = ''; // Unlimited
							$digital['customizeID'] = 0;
							$digital['customized'] = false;
							
							// License
							$licenseResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}licenses WHERE license_id = '{$digital[license]}'");
							$license = mysqli_fetch_assoc($licenseResult);					
							
							$digital = array_merge($digital,$license); // Merge the digital profile and license
							
							$digital['license'] = $license['lic_purchase_type'];
							
							//$digital['license'] = $license['lic_purchase_type'];
							$digital['licenseLang'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
						}
						
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
						
						//echo $digital['price_calc'];
						
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
						
						if($digital['quantity'] > 0 or $digital['quantity'] == "") // Make sure there is enough left to show this size
						{						
							$digitalsArray[$digital['ds_id']] = $digital;
							$digitalsArray[$digital['ds_id']] = digitalsList($digital,$mediaID);
							
							//print_k($digitalsArray); // Testing
							
							// If real_sizes is set then calculate the real width and height of this size after it is scaled from the original
							if($digital['real_sizes'] && $digital['delivery_method'] != 3)
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
								if($digital['delivery_method'] == 3){
									$width = $media['width'];
									$height = $media['height'];
								} else {
									$width = $digital['width'];
									$height = $digital['height'];
								}
							}
							
							$digitalsArray[$digital['ds_id']]['width'] = $width;
							$digitalsArray[$digital['ds_id']]['height'] = $height;
							
							
							//$digitalsArray[$digital['ds_id']]['licenseLang'] = $digital['licenseLang'];
							
							/*
							// License type and name
							switch($digital['license'])
							{
								case "cu": // Contact us
									$digitalsArray[$digital['ds_id']]['licenseLang'] = 'mediaLicenseCU';
								break;
								case "rf": // Royalty Free
									$digitalsArray[$digital['ds_id']]['licenseLang'] = 'mediaLicenseRF';
								break;
								case "ex": // Extended
									$digitalsArray[$digital['ds_id']]['licenseLang'] = 'mediaLicenseEX';
								break;
								case "eu": // Editorial Use
									$digitalsArray[$digital['ds_id']]['licenseLang'] = 'mediaLicenseEU';
								break;
								case "rm": // Rights Managed
									$digitalsArray[$digital['ds_id']]['licenseLang'] = 'mediaLicenseRM';
								break;
								case "fr": // Free Download
									$digitalsArray[$digital['ds_id']]['licenseLang'] = 'mediaLicenseFR';
								break;						
							}
							*/
							
							// File/profile type
							switch($digital['dsp_type'])
							{
								case "photo":
									if($config['digitalSizeCalc'] == 'i')
									{
										$digitalsArray[$digital['ds_id']]['widthIC'] = round($width/$config['dpiCalc'],1).'"';
										$digitalsArray[$digital['ds_id']]['heightIC'] = round($height/$config['dpiCalc'],1).'"';
									}
									else
									{
										$digitalsArray[$digital['ds_id']]['widthIC'] = round(($width/$config['dpiCalc']*2.54),1).'cm';
										$digitalsArray[$digital['ds_id']]['heightIC'] = round(($height/$config['dpiCalc']*2.54),1).'cm';
									}
								break;
								case "video":
									
								break;
								case "other":
									
								break;
							}
							
							/*
							// See if this digital variation is customized
							if(in_array($digital['ds_id'],$digitalCustomizedIDs))
							{
								// Customized pricing and settings
								$digital['price_calc'] = $customDigitalItems[$digital['ds_id']]['price_calc'];
								$digital['price'] = defaultPrice($customDigitalItems[$digital['ds_id']]['price']);
								$digital['credits'] = defaultCredits($customDigitalItems[$digital['ds_id']]['credits']);
								$digital['credits_calc'] = $customDigitalItems[$digital['ds_id']]['credits_calc'];							
								$digital['quantity'] = $customDigitalItems[$digital['ds_id']]['quantity']; 
							}
							*/
						}
					}
				}
			}
			
			/* Testing			
			foreach($digitalsArray as $key => $value)
			{
				echo "{$key}:";
				print_r($value);
				echo "<br><br><br><br>";
			}
			*/		
					
			$smarty->assign('digitalRows',count($digitalsArray));
			$smarty->assign('digitalsArray',$digitalsArray);
		//}
		
		break;		
	}
?>