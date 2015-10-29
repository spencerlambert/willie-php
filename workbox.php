<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	//sleep(2);
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','workbox'); // Page ID
	
	if($_REQUEST['mode'] == 'contrAssignMediaDetails' or $_REQUEST['mode'] == 'editContrMedia' ) // See if the workbox should be public or private
		define('ACCESS','private'); // Page access type - public|private
	else
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

	// Set the member uploader
	if(!$_SESSION['member']['uploader'])
		$_SESSION['member']['uploader'] = $config['settings']['pubuploader'];

	try
	{
		$smarty->assign('mode',$mode);
		switch($mode)
		{
			case "deleteLightbox":
				$smarty->assign('lightboxID',$lightboxID);
			break;
			case "editLightbox":
				$lightboxResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}lightboxes
					WHERE ulightbox_id = '{$lightboxID}'
					"
				);
				if($returnRows = mysqli_num_rows($lightboxResult))
				{
					$lightbox = mysqli_fetch_assoc($lightboxResult);
					$smarty->assign('lightbox',$lightbox);
				}
			break;
			case "addToLightbox":
				
				$guestLightbox = ($_SESSION['loggedIn']) ? 0 : 1;
				
				$lightboxResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}lightboxes
					WHERE umember_id = '{$_SESSION[member][umem_id]}' 
					AND deleted = 0 
					AND guest = '{$guestLightbox}'
					"
				);
				if($returnRows = mysqli_num_rows($lightboxResult))
				{
					while($lightbox = mysqli_fetch_assoc($lightboxResult))
						$lightboxesArray[$lightbox[lightbox_id]] = $lightbox['name'];			
		
					$smarty->assign('lightboxRows',$returnRows);
					$smarty->assign('lightboxes',$lightboxesArray);
				}
				$smarty->assign('selectedLightbox',$_SESSION['selectedLightbox']);
				$smarty->assign('mediaID',$mediaID);
			break;
			case "editLightboxItem":				
				$lightboxItemResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}lightbox_items 
					WHERE item_id = '{$lightboxItemID}'
					"
				);
				if($returnRows = mysqli_num_rows($lightboxItemResult))
				{
					$lightboxItem = mysqli_fetch_array($lightboxItemResult);			
		
					$smarty->assign('lightboxItemRows',$returnRows);
					$smarty->assign('lightboxItem',$lightboxItem);
				}
				$smarty->assign('mediaID',$mediaID);
				$smarty->assign('lightboxItemID',$lightboxItemID);
			break;
			case "assignPackage":
				$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
				$mediaInfo = new mediaList($sql);
				
				if($mediaInfo->getRows())
				{
					$media = $mediaInfo->getSingleMediaDetails('preview');
					$galleryIDArray = $mediaInfo->getMediaGalleryIDs(); // Get an array of galleries this media is in
					$galleryIDArrayFlat = implode(",",$galleryIDArray);
					
					/*
					* Find collections that this media may belong to
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
								$collectionsWithAccess[] = $collections['coll_id'];
						}
					}
					
					/*
					* Find which packages this media can belong to
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
					); // Find out which packages are assigned to galleries this photo is in
					$galleryPackagesRows = mysqli_num_rows($galleryPackagesResult);
					while($galleryPackage = mysqli_fetch_array($galleryPackagesResult))
						$packageIDsArray[] = $galleryPackage['item_id'];
					
					$mediaPackagesResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_packages WHERE media_id = '{$mediaID}'"); // Find what packages have been directly assigned to this photo
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
								
							//echo "pgrp,";
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
						
					//echo $packagesIDsArrayFlat; exit;
					
					/*
					* New packages that can be created
					*/
					$newPackagesResult = mysqli_query($db,
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
					if($newReturnRows = mysqli_num_rows($newPackagesResult))
					{
						while($newPackage = mysqli_fetch_array($newPackagesResult))
						{
							$newPackagesArray[$newPackage['pack_id']] = packagesList($newPackage,$mediaID);
							$availablePackages[] = $newPackage['pack_id'];
						}
						
						$smarty->assign('newPackageRows',$newReturnRows);
						$smarty->assign('newPackages',$newPackagesArray);
					}
					
					/*
					* Existing packages from cart
					*/
					if(@$_SESSION['packagesInCartSession'])
					{
						foreach($_SESSION['packagesInCartSession'] as $cartItemID => $packageID)
						{	
							if(@in_array($packageID,$availablePackages)) // Only show packages that it is elidgible for
							{
								$cartPackagesResult = mysqli_query($db,
									"
									SELECT *
									FROM {$dbinfo[pre]}packages
									WHERE pack_id = '{$packageID}'
									"
								);
								if($cartReturnRows = mysqli_num_rows($cartPackagesResult))
								{	
									$cartItemResult = mysqli_query($db,
										"
										SELECT package_media_needed,package_media_filled
										FROM {$dbinfo[pre]}invoice_items
										WHERE oi_id = '{$cartItemID}'
										"
									);
									$cartItem = mysqli_fetch_assoc($cartItemResult);
									//echo mysqli_num_rows($cartItemResult);
									
									$cartPackage = mysqli_fetch_array($cartPackagesResult);
									$cartPackage['package_media_remaining'] = $cartItem['package_media_needed'] - $cartItem['package_media_filled'];					
									/*old
									$packageMediaFilledPercentage = $cartItem['package_media_filled']/$cartItem['package_media_needed'];
									$cartPackage['package_media_percentage'] = round(50*$packageMediaFilledPercentage);
									*/
									
									//print_r($cartItem);
									//echo "<br>";
																	
									if($cartPackage['package_media_remaining'] > 0)
									{
										@$packageMediaFilledPercentage = $cartItem['package_media_filled']/$cartItem['package_media_needed'];
										@$cartPackage['package_media_percentage'] = round(100*$packageMediaFilledPercentage);
									}
									else
									{
										$packageMediaFilledPercentage = 100;
										$cartPackage['package_media_percentage'] = 100;
									}
																		
									$cartPackagesArray[$cartItemID] = packagesList($cartPackage,$mediaID);
								}
							}
						}
					}
					
					/* For testing
					echo "avail:";
					print_r($availablePackages)."<br />";
					
					foreach($cartPackagesArray as $key => $value)
					{
						echo $key."<br />";
					}
					exit;
					*/
					
					$smarty->assign('cartPackageRows',count($cartPackagesArray));
					$smarty->assign('cartPackages',$cartPackagesArray);
					
					$smarty->assign('useMediaID',($config['EncryptIDs']) ? k_encrypt($mediaID) : $mediaID);
					
					if(!$newReturnRows and !$cartPackageRows)
					{
						// Media cannot be assigned to any packages - output notice	
						$smarty->assign('notice', 'noPackages');
					}
				}
				else
					$smarty->assign('noAccess', true);
				
				$smarty->assign('mediaID',$mediaID);
			break;
			case "emailToFriend":
				$smarty->assign('mediaID',$mediaID);
			break;
			case "creditsWarning":			
			break;
			case "subtotalWarning":
				@$subTotalMin = getCorrectedPrice($config['settings']['min_total']);
				//print_r($subTotalMin); exit;
				//$cleanCurrency->currency_display($config['settings']['min_total'],1);
				$smarty->assign('subTotalMin',$subTotalMin);
			break;
			case "unfinishedPackage":
				
			break;
			case "createOrLogin":
				
			break;
			case "billPayment":
			
				/*
				* Get the active payment gateways
				*/
				$paymentGatewaysResult = mysqli_query($db,"SELECT DISTINCT gateway FROM {$dbinfo[pre]}paymentgateways WHERE setting = 'active' AND value='1'");
				$paymentGatewaysRows = mysqli_num_rows($paymentGatewaysResult);
				while($paymentGateway = mysqli_fetch_array($paymentGatewaysResult))
				{
					$activeGateways[] = $paymentGateway['gateway'];
					
					if(file_exists(BASE_PATH."/assets/gateways/{$paymentGateway[gateway]}/config.php"))
					{
						require_once BASE_PATH."/assets/gateways/{$paymentGateway[gateway]}/config.php";
						$gateways[$paymentGateway[gateway]] = $gatewaymodule;
						$gateways[$paymentGateway[gateway]]['id'] = $paymentGateway['gateway'];
						
						if(file_exists(BASE_PATH."/assets/themes/{$config[settings][style]}/{$colorSchemeImagesDirectory}/logos/{$paymentGateway[gateway]}.png")) // See if the logo exists in the directory specific to this color scheme
							$gateways[$paymentGateway[gateway]]['logo'] = true;
						else // Check to see if it exists in the main images directory for this theme
						{
							if(file_exists(BASE_PATH."/assets/themes/{$config[settings][style]}/images/logos/{$paymentGateway[gateway]}.png")) // Check for gateway logo
								$gateways[$paymentGateway[gateway]]['logo'] = true;
						}
					}
				}
				$smarty->assign('gateways',$gateways); // Payment gateways info
				$smarty->assign('billID',$billID);
			break;
			case "downloadExpired":				
			break;
			case "downloadsExceeded":				
			break;
			case "downloadNotAvailable":				
				$queryStr = k_decrypt($dlKey);
				parse_str($queryStr,$query); // Parse the query string
				$smarty->assign('mediaID',$query['mediaID']);
				$smarty->assign('profileID',k_decrypt($query['profileID']));							
			break;
			case "downloadNotApproved":				
			break;
			case "contrNewAlbum":				
			break;
			case "contrEditAlbum":
				//echo $contrAlbumID; // Testing				
				$albumResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}galleries WHERE ugallery_id = '{$contrAlbumID}'");
				$album = mysqli_fetch_array($albumResult);
				
				$shareLink = $_SESSION['member']['contrAlbumsData'][$album['gallery_id']]['linkto'];
				$smarty->assign('shareLink',$shareLink);
				
				if($album['password']) $album['password'] = k_decrypt($album['password']); // Decrypt password
				
				$smarty->assign('album',$album);
			break;
			case "contrDeleteAlbum":
				$smarty->assign('contrAlbumID',$contrAlbumID);		
			break;
			case "contrUploadMedia":
				
				//require_once BASE_PATH.'/assets/classes/foldertools.php'; // Include folder class
				
				$contr = checkContrDirectories();
				
				//echo 'test'.$contr['folderDBID']; exit; // Testing
				
				if($contr['error']) // Check and create contributor directories
					die($contr['error']); // On error exit
				
				/*
				$contrFID = zerofill($_SESSION['member']['mem_id'],5);
				$incomingFolder = BASE_PATH.'/assets/contributors/contr'.$contrFID;
				$libraryFolder = $config['settings']['library_path'].'/contr'.$contrFID;
								
				if(!file_exists($incomingFolder)) // See if incoming folder exists
				{
					@mkdir($incomingFolder); // Create contributors import folder
					@chmod($incomingFolder,0777);
					@copy(BASE_PATH.'/assets/index.html',$incomingFolder.'/index.html'); // copy an index.html into that dir
				}
				
				if(!file_exists($incomingFolder)) // Recheck if folder exists
					die('No import folder exists for this member.');
					
				if(!file_exists($libraryFolder)) // See if library folder exists
				{
					@mkdir($libraryFolder); // Create contributors library folder
					@chmod($libraryFolder,0777);
					
					foreach($librayFolders as $folderName) // Create each sub folder
					{
						@mkdir($libraryFolder.'/'.$folderName); // Create contributors library sub folder
						@chmod($libraryFolder.'/'.$folderName,0777);					
						@copy(BASE_PATH.'/assets/index.html',$libraryFolder.'/'.$folderName.'/index.html'); // copy an index.html into that dir
					}
				}
				
				if(!file_exists($libraryFolder)) // Recheck if folder exists
					die('No library folder exists for this member.');
				*/
				
									
				// Allowed file types
				$membershipFiletypes = $_SESSION['member']['membershipDetails']['file_types'];
				$fileTypes = explode(",",$membershipFiletypes);
				
				
				$maxFilesizeDefault = str_replace('M','',ini_get('upload_max_filesize')); // Max file size
				
				if($_SESSION['member']['membershipDetails']['fs_max'] < $maxFilesizeDefault) // Find the max file size that is allowed
					$maxFilesize = $maxFilesizeDefault;
				else
					$maxFilesize = $_SESSION['member']['membershipDetails']['fs_max'];
				
				
				
				//echo $upSet['maxFilesize']; exit; // Testing
				
				if($_SESSION['member']['uploader'] == 1)
				{
					foreach($fileTypes as $value)
					{
						$allowedFileTypes .= "($value)";
						$allowedFileTypes .= "|";
					}
					
					$upSet['maxFilesize'] = ($maxFilesize*1024)*1024; // Max file size in bytes
					
					$upSet['allowedFileTypes'] = substr($allowedFileTypes,0,strlen($allowedFileTypes)-1);				
					$upSet['maxfiles'] = 1000;
					$upSet['handler'] = "upload.php?handler=addMedia&uploaderType=java";							
					$upSet['uc_scaledInstanceNames'] = "icon,thumb,sample";
					$upSet['uc_scaledInstanceDimensions'] = "$config[IconDefaultSize]x$config[IconDefaultSize],$config[ThumbDefaultSize]x$config[ThumbDefaultSize],$config[SampleDefaultSize]x$config[SampleDefaultSize]";
					$upSet['uc_scaledInstanceQualityFactors'] = "1000,1000,1000";
				}
				if($_SESSION['member']['uploader'] == 2)
				{	
					$upSet['maxFilesize'] = $maxFilesize;					
					$upSet['allowedFileTypes'] = $membershipFiletypes;
					$upSet['handler'] = "upload.php?handler=addMedia&uploaderType=plupload";
				}
				
				// Allow selling
				
				$smarty->assign('upSet',$upSet);
			
			break;
			
			case "editContrMedia":
				
				$useMediaID = $mediaID; // Save original media ID
		
				//if($config['EncryptIDs']) // Decrypt IDs // I believe this is always passed as encrypted
					$mediaID = k_decrypt($mediaID);
					
				//echo $mediaID; exit;
				
				$smarty->assign('maxUploadSize',maxUploadSize('kb'));
				
				try
				{
					$cleanCurrency = new number_formatting; // Setup a cleanup object for converting currency to the admin currency
					$cleanCurrency->set_custom_cur_defaults($priCurrency['currency_id']);
					
					$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
					$mediaObj = new mediaList($sql);
				
					if($mediaObj->getRows())
					{
						$media = $mediaObj->getSingleMediaDetails();
						//$galleryIDArray = $mediaObj->getMediaGalleryIDs(); // Get an array of galleries this media is in
						
						$media['title'] = ($selectedLanguage == $config['settings']['lang_file_mgr']) ? $media['title'] : $media['title_'.$selectedLanguage];
						$media['description'] = ($selectedLanguage == $config['settings']['lang_file_mgr']) ? $media['description'] : $media['description_'.$selectedLanguage];
						
						foreach($activeLanguages as $key => $lang) // Pass title languages
							$media['titleLang'][$key] = $media['title_'.$key];
						
						foreach($activeLanguages as $key => $lang) // Pass description languages
							$media['descriptionLang'][$key] = $media['description_'.$key];
						
						//echo 'test'.$media['license_id']; exit;
						
						if($media['license'] != 'nfs') // This is selected
						{	
							$media['orgSelected'] = true;
							
							if($media['credits'] > 0)
								$media['setCredits'] = $media['credits'];
								
							if($media['price'] > 0)
								$media['setPrice'] = $cleanCurrency->currency_display($media['price'],0);;
						}
						else
							$media['orgSelected'] = false;
						
						$smarty->assign('media',$media);
						
						$keywordsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}keywords WHERE media_id = '{$mediaID}'");
						while($keywordDB = mysqli_fetch_assoc($keywordsResult))
						{
							if($keywordDB['language'])
							{
								$keyLang = strtolower($keywordDB['language']);
								$keywords[$keyLang][$keywordDB['key_id']] = $keywordDB['keyword'];
							}
							else
								$keywords[$config['settings']['lang_file_mgr']][$keywordDB['key_id']] = $keywordDB['keyword'];
						}
						//print_r($keywords);
						$smarty->assign('keywords',$keywords);
						
						//print_r($media['titleLang']);
					}
				}
				catch(Exception $e)
				{
					echo $e->getMessage();
				}
				
				/*
				* Get the galleries this media is in
				*/
				$mediaGalleriesResult = mysqli_query($db,
					"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media_galleries 
					LEFT JOIN {$dbinfo[pre]}galleries 
					ON {$dbinfo[pre]}media_galleries.gallery_id = {$dbinfo[pre]}galleries.gallery_id 
					WHERE {$dbinfo[pre]}media_galleries.gmedia_id = {$mediaID}			
					"
				);
				if(getRows())
				{
					while($mediaGalleries = mysqli_fetch_assoc($mediaGalleriesResult))	
					{
						if($mediaGalleries['album'])
							$selectedAlbum = $mediaGalleries['gallery_id']; // Album exists - set as selected album
						
						//echo $mediaGalleries['gallery_id'].'<br>';
						
						$selectedGalleries[] = $mediaGalleries['gallery_id']; // Selected galleries array						
					}
				}
				
				/*
				* Get the licenses available
				*/
				$licenseResult = mysqli_query($db,
					"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}licenses		
					"
				);
				if(getRows())
				{
					while($license = mysqli_fetch_assoc($licenseResult))	
					{	
						$license['name'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
						
						if($license['license_id'] == $media['license_id'])
							$license['selected'] = 1; // See if the license is selected in the list
						
						$licenses[$license['license_id']] = $license; // 				
					}
				}
				
				$smarty->assign('licenses',$licenses);
				$smarty->assign('selectedAlbum',$selectedAlbum);
				$smarty->assign('selectedGalleries',$selectedGalleries);
				
				/*
				* Get the media type this media is in
				*/
				$mediaTypesRefResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}media_types_ref  
					WHERE media_id = {$mediaID}
					"
				);
				while($mediaTypesRef = mysqli_fetch_assoc($mediaTypesRefResult))	
					$selectedMediaTypes[] = $mediaTypesRef['mt_id'];					
			
				/*
				* Get the active media types
				*/
				$mediaTypesResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}media_types 
					WHERE active = 1			
					"
				); 
				if($returnRows = mysqli_num_rows($mediaTypesResult))
				{
					while($mediaTypes = mysqli_fetch_assoc($mediaTypesResult))
					{
						$mediaTypesArray[$mediaTypes['mt_id']] = $mediaTypes; // xxxx Language
						
						if(@in_array($mediaTypes['mt_id'],$selectedMediaTypes))
							$mediaTypesArray[$mediaTypes['mt_id']]['selected'] = true;
						
					}
					$smarty->assign('mediaTypesRows',$returnRows);
					$smarty->assign('mediaTypes',$mediaTypesArray);
				}
				
				/*
				* Get the digitals assigned to this media
				*/
				$selectedDigitalsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}media_digital_sizes    
					WHERE media_id = {$mediaID}
					"
				);
				while($selectedDigitalsDB = mysqli_fetch_assoc($selectedDigitalsResult))	
					$selectedDigitals[$selectedDigitalsDB['ds_id']] = $selectedDigitalsDB;
				
				$digitalCurrencyCartStatus = (currencyCartStatus() and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
				$digitalCreditsCartStatus = (creditsCartStatus('digital') and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
				
				$smarty->assign('digitalCurrencyCartStatus',$digitalCurrencyCartStatus);
				$smarty->assign('digitalCreditsCartStatus',$digitalCreditsCartStatus);
				
				// Get the digital variations that the member has access to
				$digitalResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}digital_sizes  
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}digital_sizes.ds_id  = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'digital_sp') 
					WHERE {$dbinfo[pre]}digital_sizes.contr_sell = 1 
					AND {$dbinfo[pre]}digital_sizes.active = 1 
					AND {$dbinfo[pre]}digital_sizes.deleted = 0
					AND ({$dbinfo[pre]}digital_sizes.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}digital_sizes.sortorder
					"
				);				
				if($digitalRows = mysqli_num_rows($digitalResult))
				{
					$returnDigRows['all'] = $digitalRows;
					
					while($digital = mysqli_fetch_array($digitalResult))
					{	
						${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']] = $digital;
						${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']] = digitalsList($digital,0);
					
						if($selectedDigitals[$digital['ds_id']]) // This is selected
						{	
							
							$originalMediaDS.= $digital['ds_id'] . ",";
							
							${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['filename'] = $selectedDigitals[$digital['ds_id']]['filename'];
							
							$contrFolderInfo = checkContrDirectories();
							$variationsPath = "{$config[settings][library_path]}/contr{$contrFolderInfo[contrFID]}/variations/";
														
							if($selectedDigitals[$digital['ds_id']]['filename'] and file_exists($variationsPath.$selectedDigitals[$digital['ds_id']]['filename']))
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['fileExists'] = true;
							
							${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['selected'] = true;
							if($selectedDigitals[$digital['ds_id']]['credits'] > 0) // Check if credits is set
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['setCredits'] = $selectedDigitals[$digital['ds_id']]['credits'];
							
							if($selectedDigitals[$digital['ds_id']]['price'] > 0) // Check if price is set
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['setPrice'] = $cleanCurrency->currency_display($selectedDigitals[$digital['ds_id']]['price'],0);
								
						}
						else
						{
							${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['selected'] = false;
						}
						
						// File/profile type
						switch($digital['dsp_type'])
						{
							case "photo":
								$returnDigRows['photo']++;
							break;
							case "video":
								$returnDigRows['video']++;								
							break;
							case "other":
								$returnDigRows['other']++;
							break;
						}
						
						$licenseResult = mysqli_query($db,
							"
							SELECT SQL_CALC_FOUND_ROWS *
							FROM {$dbinfo[pre]}licenses	
							WHERE license_id = '{$digital[license]}'
							"
						);
						if(getRows())
						{
							$license = mysqli_fetch_assoc($licenseResult);	
							${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
						}
												
						/*
						// License type and name
						switch($digital['license'])
						{
							case "cu": // Contact us
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseCU';
							break;
							case "ex": // Extended
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseEX';
							break;
							case "eu": // Editorial Use
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseEU';
							break;
							case "rf": // Royalty Free
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseRF';
							break;
							case "rm": // Rights Managed
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseRM';
							break;
							case "fr": // Free Download
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseFR';
							break;						
						}
						*/
					}
					
					//print_r($photoProfilesArray);
										
					$smarty->assign('originalMediaDS',$originalMediaDS);
					$smarty->assign('digitalRows',$returnDigRows);
					$smarty->assign('photoProfiles',$photoProfilesArray);
					$smarty->assign('videoProfiles',$videoProfilesArray);
					$smarty->assign('otherProfiles',$otherProfilesArray);
				}
				
				/*
				* Get the prints assigned to this media
				*/
				$selectedPrintsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}media_prints   
					WHERE media_id = {$mediaID}
					"
				);
				while($selectedPrintsDB = mysqli_fetch_assoc($selectedPrintsResult))	
					$selectedPrints[$selectedPrintsDB['print_id']] = $selectedPrintsDB;
				
				/*
				* Get the prints contributors can sell
				*/
				$printsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}prints
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'prints')
					WHERE {$dbinfo[pre]}prints.active = 1 
					AND {$dbinfo[pre]}prints.deleted = 0 
					AND {$dbinfo[pre]}prints.contr_sell = 1 
					AND ({$dbinfo[pre]}prints.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}prints.sortorder
					"
				); 
				if($returnRows = mysqli_num_rows($printsResult))
				{
					while($prints = mysqli_fetch_assoc($printsResult))
					{
						$printsArray[$prints['print_id']] = printsList($prints);
					
						if($selectedPrints[$prints['print_id']]) // This is selected
						{
							$printsArray[$prints['print_id']]['selected'] = true;
							if($selectedPrints[$prints['print_id']]['credits'] > 0) // Check if credits is set
								$printsArray[$prints['print_id']]['setCredits'] = $selectedPrints[$prints['print_id']]['credits'];
							
							if($selectedPrints[$prints['print_id']]['price'] > 0) // Check if price is set
								$printsArray[$prints['print_id']]['setPrice'] = $cleanCurrency->currency_display($selectedPrints[$prints['print_id']]['price'],0);
								
						}
						else
						{
							$printsArray[$prints['print_id']]['selected'] = false;
						}
						
					}
					
					$printCurrencyCartStatus = (currencyCartStatus() and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					$printCreditsCartStatus = (creditsCartStatus('print') and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					
					$smarty->assign('printCurrencyCartStatus',$printCurrencyCartStatus);
					$smarty->assign('printCreditsCartStatus',$printCreditsCartStatus);
					
					$smarty->assign('printRows',$returnRows);
					$smarty->assign('prints',$printsArray);
				}
				
				/*
				* Get the products assigned to this media
				*/
				$selectedProdsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}media_products   
					WHERE media_id = {$mediaID}
					"
				);
				while($selectedProdsDB = mysqli_fetch_assoc($selectedProdsResult))	
					$selectedProds[$selectedProdsDB['prod_id']] = $selectedProdsDB;
				
				/*
				* Get the products contributors can sell
				*/
				$productsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}products
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'products')
					WHERE {$dbinfo[pre]}products.active = 1 
					AND {$dbinfo[pre]}products.deleted = 0 
					AND {$dbinfo[pre]}products.product_type = 1 
					AND {$dbinfo[pre]}products.contr_sell = 1 
					AND ({$dbinfo[pre]}products.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}products.sortorder
					"
				);				
				if($returnRows = mysqli_num_rows($productsResult))
				{
					while($products = mysqli_fetch_assoc($productsResult))
					{
						$productsArray[$products['prod_id']] = productsList($products);
					
						if($selectedProds[$products['prod_id']]) // This is selected
						{	
							$productsArray[$products['prod_id']]['selected'] = true;
							if($selectedProds[$products['prod_id']]['credits'] > 0) // Check if credits is set
								$productsArray[$products['prod_id']]['setCredits'] = $selectedProds[$products['prod_id']]['credits'];
							
							if($selectedProds[$products['prod_id']]['price'] > 0) // Check if price is set
								$productsArray[$products['prod_id']]['setPrice'] = $cleanCurrency->currency_display($selectedProds[$products['prod_id']]['price'],0);
								
						}
						else
						{
							$productsArray[$products['prod_id']]['selected'] = false;
						}
					}
					
					$prodCurrencyCartStatus = (currencyCartStatus() and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					$prodCreditsCartStatus = (creditsCartStatus('print') and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					
					$smarty->assign('prodCurrencyCartStatus',$prodCurrencyCartStatus);
					$smarty->assign('prodCreditsCartStatus',$prodCreditsCartStatus);
						
					$smarty->assign('productRows',$returnRows);
					$smarty->assign('products',$productsArray);
				}
								
				$sellDigital = ($_SESSION['member']['membershipDetails']['allow_selling']) ? true : false; // or $_SESSION['member']['membershipDetails']['contr_digital'] or $_SESSION['member']['membershipDetails']['additional_sizes']
				$smarty->assign('sellDigital',$sellDigital);
				
				$smarty->assign('contrSaveSessionForm',date("U")); // array unique				
				$smarty->assign('saveMode',$_REQUEST['saveMode']); // Pass the saveMode to smarty				
				
				//$smarty->assign('contrImportFiles',$_SESSION['contrImportFiles']);				
				//unset($_SESSION['contrImportFiles']); // Clear the import files session
				
			break;
			
			case "contrAssignMediaDetails":
				//echo $selectedLanguage;  // Testing
				switch($_REQUEST['saveMode'])
				{
					case 'import':  // Import mode - import from files already uploaded
						unset($_SESSION['contrImportFiles']);
					break;
				}
				
				/*
				* Get the active media types
				*/
				$mediaTypesResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}media_types 
					WHERE active = 1			
					"
				); 
				if($returnRows = mysqli_num_rows($mediaTypesResult))
				{
					while($mediaTypes = mysqli_fetch_assoc($mediaTypesResult))
					{
						
						$mediaTypes['name'] = ($mediaTypes['name_'.$selectedLanguage]) ? $mediaTypes['name_'.$selectedLanguage] : $mediaTypes['name'];
						$mediaTypesArray[$mediaTypes['mt_id']] = $mediaTypes;
						
						if(@in_array($mediaTypes['mt_id'],$_SESSION['searchForm']['mediaTypes']))
							$mediaTypesArray[$mediaTypes['mt_id']]['selected'] = true;
						
					}
					$smarty->assign('mediaTypesRows',$returnRows);
					$smarty->assign('mediaTypes',$mediaTypesArray);
				}
				
				/*
				* Get the licenses available
				*/
				$licenseResult = mysqli_query($db,
					"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}licenses		
					"
				);
				if(getRows())
				{
					while($license = mysqli_fetch_assoc($licenseResult))	
					{	
						$license['name'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
						$licenses[$license['license_id']] = $license; // 				
					}
				}							
				$smarty->assign('licenses',$licenses);
				
				/*
				function getTree($parentID,$level)
				{	
					//global $tree;
					$childCount = 0;
					$cCounter = 1;
					
					if($children = findChildren($parentID))
					{
						$childCount = count($children);
						$cCounter = 1;
						
						foreach($children as $value)
						{
							for($x=0;$x<$level;$x++)
							{
								//echo "&nbsp;";	
							}
							
							$galleryNameText = $_SESSION['galleriesData'][$value]['name'];
							if($_SESSION['galleriesData'][$value]['password']) $galleryNameText .= " <span class='treeLock'>&nbsp;&nbsp;&nbsp;</span>";
							$linkto =  $_SESSION['galleriesData'][$value]['linkto'];
							
							
							$tree['title'] = $galleryNameText;
							$tree['id'] = $value;
							$tree['level'] = $level;
							
							$cCounter++;
							
							//if($value)
								$tree['children'] = getTree($value,$level+1);
								
							return $tree;
							
						}
					}
				}
								
				function findChildren($parentID)
				{
					foreach($_SESSION['galleriesData'] as $key => $gallery)
					{
						if($gallery['parent_gal'] == $parentID and $gallery['gallery_id']) // avoid loading gallery called "gallery"
						{
							$children[] = $key;
						}
					}
					
					if(count($children) > 0)
						return $children;
					else
						return false;
				}
				
				$tree = getTree(0,0);
				
				echo "Tree";
				print_r($tree);
				exit;
				
				*/
				
				$digitalCurrencyCartStatus = (currencyCartStatus() and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
				$digitalCreditsCartStatus = (creditsCartStatus('digital') and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
				
				$smarty->assign('digitalCurrencyCartStatus',$digitalCurrencyCartStatus);
				$smarty->assign('digitalCreditsCartStatus',$digitalCreditsCartStatus);
				
				// Get the digital variations that the member has access to
				$digitalResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}digital_sizes  
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}digital_sizes.ds_id  = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'digital_sp') 
					WHERE {$dbinfo[pre]}digital_sizes.contr_sell = 1 
					AND {$dbinfo[pre]}digital_sizes.active = 1 
					AND {$dbinfo[pre]}digital_sizes.deleted = 0
					AND ({$dbinfo[pre]}digital_sizes.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}digital_sizes.sortorder
					"
				);				
				if($digitalRows = mysqli_num_rows($digitalResult))
				{
					$returnDigRows['all'] = $digitalRows;
					
					while($digital = mysqli_fetch_array($digitalResult))
					{	
						${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']] = $digital;
						${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']] = digitalsList($digital,0);
						
						// File/profile type
						switch($digital['dsp_type'])
						{
							case "photo":
								$returnDigRows['photo']++;
							break;
							case "video":
								$returnDigRows['video']++;								
							break;
							case "other":
								$returnDigRows['other']++;
							break;
						}
						
						$licenseResult = mysqli_query($db,
							"
							SELECT SQL_CALC_FOUND_ROWS *
							FROM {$dbinfo[pre]}licenses	
							WHERE license_id = '{$digital[license]}'
							"
						);
						if(getRows())
						{
							$license = mysqli_fetch_assoc($licenseResult);	
							${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = ($license['lic_name_'.$selectedLanguage]) ? $license['lic_name_'.$selectedLanguage] : $license['lic_name'];
						}
						
						/*
						// License type and name
						switch($digital['license'])
						{
							case "cu": // Contact us
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseCU';
							break;
							case "ex": // Extended
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseEX';
							break;
							case "eu": // Editorial Use
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseEU';
							break;
							case "rf": // Royalty Free
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseRF';
							break;
							case "rm": // Rights Managed
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseRM';
							break;
							case "fr": // Free Download
								${$digital['dsp_type'].'ProfilesArray'}[$digital['ds_id']]['licenseLang'] = 'mediaLicenseFR';
							break;						
						}
						*/
					}
					
					$smarty->assign('digitalRows',$returnDigRows);
					$smarty->assign('photoProfiles',$photoProfilesArray);
					$smarty->assign('videoProfiles',$videoProfilesArray);
					$smarty->assign('otherProfiles',$otherProfilesArray);
				}
				
				/*
				* Get the prints contributors can sell
				*/
				$printsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}prints
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'prints')
					WHERE {$dbinfo[pre]}prints.active = 1 
					AND {$dbinfo[pre]}prints.deleted = 0 
					AND {$dbinfo[pre]}prints.contr_sell = 1 
					AND ({$dbinfo[pre]}prints.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}prints.sortorder
					"
				); 
				if($returnRows = mysqli_num_rows($printsResult))
				{
					while($prints = mysqli_fetch_assoc($printsResult))
						$printsArray[] = printsList($prints);
						
					
					$printCurrencyCartStatus = (currencyCartStatus() and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					$printCreditsCartStatus = (creditsCartStatus('print') and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					
					$smarty->assign('printCurrencyCartStatus',$printCurrencyCartStatus);
					$smarty->assign('printCreditsCartStatus',$printCreditsCartStatus);
					
					$smarty->assign('printRows',$returnRows);
					$smarty->assign('prints',$printsArray);
				}
				
				/*
				* Get the products contributors can sell
				*/
				$productsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}products
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'products')
					WHERE {$dbinfo[pre]}products.active = 1 
					AND {$dbinfo[pre]}products.deleted = 0 
					AND {$dbinfo[pre]}products.product_type = 1 
					AND {$dbinfo[pre]}products.contr_sell = 1 
					AND ({$dbinfo[pre]}products.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
					ORDER BY {$dbinfo[pre]}products.sortorder
					"
				);				
				if($returnRows = mysqli_num_rows($productsResult))
				{
					while($products = mysqli_fetch_assoc($productsResult))
						$productsArray[] = productsList($products);
					
					$prodCurrencyCartStatus = (currencyCartStatus() and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					$prodCreditsCartStatus = (creditsCartStatus('print') and $_SESSION['member']['membershipDetails']['contr_col'] == '0') ? true : false;
					
					$smarty->assign('prodCurrencyCartStatus',$prodCurrencyCartStatus);
					$smarty->assign('prodCreditsCartStatus',$prodCreditsCartStatus);
						
					$smarty->assign('productRows',$returnRows);
					$smarty->assign('products',$productsArray);
				}
				
				//print_r($_SESSION['member']['membershipDetails']); exit; // Testing
				
				$sellDigital = ($_SESSION['member']['membershipDetails']['allow_selling']) ? true : false; // or $_SESSION['member']['membershipDetails']['contr_digital'] or $_SESSION['member']['membershipDetails']['additional_sizes']
				$smarty->assign('sellDigital',$sellDigital);
				
				//$selectableGalleries = $_SESSION['galleriesData'];				
				//$smarty->assign('selectableGalleries',$selectableGalleries); // Galleries				
				
				$smarty->assign('contrSaveSessionForm',date("U")); // array unique				
				$smarty->assign('saveMode',$_REQUEST['saveMode']); // Pass the saveMode to smarty
				$smarty->assign('contrImportFiles',$_SESSION['contrImportFiles']);
				
				unset($_SESSION['contrImportFiles']); // Clear the import files session
			break;
			case "contrMailinMedia":
			break;
			case "contrFailedApproval":
				/*
				$mediaResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}media 
					WHERE media_id = '{$id}'
					"
				);			
				if($returnRows = mysqli_num_rows($mediaResult))
				{
					$media = mysqli_fetch_assoc($mediaResult);						
					$smarty->assign('mediaRows',$returnRows);
					$smarty->assign('media',$media);
				}
				*/				
				$sql = 
				"
					SELECT *
					FROM {$dbinfo[pre]}media 
					WHERE media_id = '{$id}'
				";
				$mediaInfo = new mediaList($sql); // Create a new mediaList object
				$media = $mediaInfo->getSingleMediaDetails('thumb');
				$smarty->assign('media',$media);
				
			break;
			
			case "deleteContrMedia":
				$smarty->assign('mediaID',$_REQUEST['mediaID']); // Pass the mediaID to smarty
			break;
			
			case "editContrMedia":
				$smarty->assign('mediaID',$_REQUEST['mediaID']); // Pass the mediaID to smarty
			break;
			
			case "cartAddNotes":
				$cartNotesResult = mysqli_query($db,
					"
					SELECT cart_item_notes
					FROM {$dbinfo[pre]}invoice_items 
					WHERE oi_id = '{$cartItemID}'
					"
				);				
				if($returnRows = mysqli_num_rows($cartNotesResult))
				{
					$cartItem = mysqli_fetch_assoc($cartNotesResult);
					$smarty->assign('cartItemNotes',$cartItem['cart_item_notes']); // Pass the cartItemNotes to smarty
				}			
				$smarty->assign('cartItemID',$cartItemID); // Pass the cartItemID to smarty
			break;
		}		
		
		if(!$template) $template = 'workbox.tpl';
		
		$smarty->display($template);
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>