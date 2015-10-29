<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','download'); // Page ID
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
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	require_once BASE_PATH.'/assets/classes/invoicetools.php';
	
	// Check and make sure this file can be downloaded for extra security
		
	if(!$dlKey)
		die("No download key was passed");
		
	$queryStr = k_decrypt($dlKey);
	
	parse_str($queryStr,$download); // Parse the query string	
	
	//print_r($download); exit; // Testing
	
	if(!$download['mediaID']) // Make sure a mediaID is passed for security
		die("No media ID passed");
	
	$useMediaID = $download['mediaID']; // Original untouched media ID
	
	if(!$download['downloadTypeID'])
		$download['downloadTypeID'] = 0; // ID of the subscription or order that is allowing the download
	
	if($config['EncryptIDs']) // Decrypt IDs
	{
		if($download['profileID']) $download['profileID'] = k_decrypt($download['profileID']); // Size ID
		if($download['mediaID']) $download['mediaID'] = k_decrypt($download['mediaID']); // Media ID
		if($download['customizeID']) $download['customizeID'] = k_decrypt($download['customizeID']); // Customize ID
		
		if($download['invoiceItemID']) $download['invoiceItemID'] = k_decrypt($download['invoiceItemID']);
		if($download['memberID'])  $download['memberID'] = k_decrypt($download['memberID']);
		if($download['downloadTypeID'])  $download['downloadTypeID'] = k_decrypt($download['downloadTypeID']);
	}

	if($download['externalLink']) $download['externalLink'] = k_decrypt($download['externalLink']); // Decrypt External Link	
	
	//print_k($download); exit;
	
	function getHeaderscURL($url)
	{
	  $ch = curl_init($url);
	  curl_setopt( $ch, CURLOPT_NOBODY, true );
	  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
	  curl_setopt( $ch, CURLOPT_HEADER, false );
	  curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	  curl_setopt( $ch, CURLOPT_MAXREDIRS, 3 );
	  curl_exec( $ch );
	  $headers = curl_getinfo( $ch );
	  curl_close( $ch );
	
	  return $headers;
	}
	
	function downloadcURL($url, $path)
	{
	  # open file to write
	  $fp = fopen ($path, 'w+');
	  # start curl
	  $ch = curl_init();
	  curl_setopt( $ch, CURLOPT_URL, $url );
	  # set return transfer to false
	  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
	  curl_setopt( $ch, CURLOPT_BINARYTRANSFER, true );
	  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	  # increase timeout to download big file
	  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
	  # write data to local file
	  curl_setopt( $ch, CURLOPT_FILE, $fp );
	  # execute curl
	  curl_exec( $ch );
	  # close curl
	  curl_close( $ch );
	  # close local file
	  fclose( $fp );
	
	  if (filesize($path) > 0) return true;
	}
	
	if($download['externalLink']) // Check to make sure file exists
	{
		$tempPath = './assets/tmp/';
		$tempFileName = basename($download['externalLink']);
		
		$elHeaders = getHeaderscURL($download['externalLink']);
		
		if ($elHeaders['http_code'] === 200 and $elHeaders['download_content_length'] < 1024*1024)
			$filecheck['status'] = 1;
		else
			$filecheck['status'] = 0;
		
		//$filecheck['status'] = (checkExternalFile($download['externalLink']) > 400) ? 0 : 1; // Old
	}
	
	//echo $filecheck['status']; exit;
	//print_k($download); exit;
	
	/*
	* If the download type is order then check to make sure that the 
	* customer has access to download the file by doing these checks.
	*/	
	if($download['downloadType'] == 'order') {
		
		$maxDownloadAttempts = ($config['settings']['dl_attempts'] == 0 or $download['collectionDownload'] == 1) ? 9999 : $config['settings']['dl_attempts']; // Find the max download attempts - if unlimited use 999 
		
		//echo $maxDownloadAttempts; exit;
		
		// Check authorization
		if($_SESSION['downloadAuthorization'] != k_encrypt($download['downloadTypeID'])) {
			echo "You are not authorized to download this file";
			exit;	
		} else {
			
			try {
				$invoice = new invoiceTools;
				$digitalItem = $invoice->getSingleInvoiceItem($download['invoiceItemID']);
				
				// Check expiration
				if($nowGMT > $digitalItem['expires'] and $digitalItem['expires'] != '0000-00-00 00:00:00') {
					echo "This download has expired";
					exit;
				}
				
				// Check download count	
				if($digitalItem['downloads'] >= $maxDownloadAttempts) {
					echo "You have reached the maximum number of downloads for this file.";
					exit;	
				}
				
			} catch(Exception $e) {
				echo $e->getMessage();
				exit;
			}
			
			//print_k($digitalItem); // Testing
			//echo $_SESSION['downloadAuthorization']; exit; // Testing
			
		}
	} // End authorization check
	
	try
	{
		idCheck($download['mediaID']); // Make sure ID is numeric
		
		// Get the media information
		$media = new mediaTools($download['mediaID']);
		$mediaInfo = $media->getMediaInfoFromDB();
		$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
		
		
		if($download['externalLink']) // External Link
		{
			$externalLink = 1; // External Link			
			$filecheck['status'] = (checkExternalFile($download['externalLink']) > 400) ? 0 : 1;
			$filecheck['path'] = $download['externalLink'];
		}
		else
		{
			$filecheck = $media->verifyMediaFileExists(); // Returns array [stauts,path,filename]
		}
		//$filecheck = $media->verifyMediaFileExists(); // Returns array [stauts,path,filename] // Old
		
		//print_r($mediaInfo);
		//echo $_SESSION['member']['mem_id'];
		//exit;
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
		exit;
	}
	
	if($download['deliveryMethod'] == 0) // Attached file - override everything else
	{		
		/*
		// Currently just redirect to external link
		//header("location: {$download[externalLink]}");
		//exit;
		
		$output_filename = "testfile.jpg";
		$host = $download['externalLink'];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false);
		//curl_setopt($ch, CURLOPT_REFERER, "http://www.xcontest.org");
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		$result = curl_exec($ch);
		curl_close($ch);
	
		$ctype = "application/txt";
		
		header("Content-Type: $ctype");
		header("Content-Disposition: attachment; filename=\"".$output_filename."\"");
		header("Content-Transfer-Encoding: binary");
		//header("Content-Length: ".@filesize($file));
		if(function_exists('set_time_limit')) set_time_limit(0);
		//@readfile($file) or die("File not found.");
		
		print_r($result); // prints the contents of the collected file before writing..
		*/
		
		
		// Get the correct folder name based on if it is encrypted or not
		if($folderInfo['encrypted'])
			$folderName = $folderInfo['enc_name'];
		else
			$folderName = $folderInfo['name'];
		
		$folderPath = "{$config[settings][library_path]}/{$folderName}"; // The full folder path	
		
		// This is a digital variation of - get the details
		$dspResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}media_digital_sizes 
			WHERE ds_id = '{$download[profileID]}' 
			AND media_id = '{$download[mediaID]}'
			"
		);
		$dsp = mysqli_fetch_array($dspResult);
		
		if($dsp['filename']) // Make sure filename is in db
		{
			$filename = $dsp['filename'];
			$file = "{$folderPath}/variations/{$filename}";
			$ctype = "application/txt";
			
			$downloadFilename = ($dsp['ofilename']) ? $dsp['ofilename'] : $dsp['filename'];
			
			if (!file_exists($file))
				die("Error 4: This file cannot be found on the server.");
			
			header("Content-Type: $ctype");
			header("Content-Disposition: attachment; filename=\"".$downloadFilename."\"");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".@filesize($file));
			if(function_exists('set_time_limit')) set_time_limit(0);
			//@readfile($file) or die("File not found.");
			
		  	ob_end_clean();
			@readfileChunked($file);// or die("File not found.");
			
		}
		elseif($download['externalLink'])
		{
			// Check availability
			if($filecheck['status'])
			{
				if(downloadcURL($download['externalLink'], $tempPath.$tempFileName))
				{
					header("Content-Type: application/txt");
					header("Content-Disposition: attachment; filename=\"".$tempFileName."\"");
					header("Content-Transfer-Encoding: binary");
					header("Content-Length: ".@filesize($tempPath.$tempFileName));
					if(function_exists('set_time_limit')) set_time_limit(0);
					//@readfile($file) or die("File not found.");
					
					ob_end_clean();
					@readfileChunked($tempPath.$tempFileName);// or die("File not found.");
					
					@unlink($tempPath.$tempFileName);
				}
				else
					die('File download failed!');
			}
			else
				die("File cannot be found at link provided.");
		}
		else
		{
			echo "File not available for download.";
			exit;
		}	
	}
	
	if($download['profileID'] == '0' or $download['deliveryMethod'] == 3) // Original or delivery method original
	{	
		if($download['externalLink'])
		{
			// Check availability
			if($filecheck['status'])
			{
				if(downloadcURL($download['externalLink'], $tempPath.$tempFileName))
				{
					header("Content-Type: application/txt");
					header("Content-Disposition: attachment; filename=\"".$tempFileName."\"");
					header("Content-Transfer-Encoding: binary");
					header("Content-Length: ".@filesize($tempPath.$tempFileName));
					if(function_exists('set_time_limit')) set_time_limit(0);
					//@readfile($file) or die("File not found.");
					
					ob_end_clean();
					@readfileChunked($tempPath.$tempFileName);// or die("File not found.");
					
					@unlink($tempPath.$tempFileName);
				}
				else
					die('File download failed!');
			}
			else
				die("File cannot be found at link provided.");
		}
		else
		{
			$filename = $filecheck['filename'];
			$file = "{$filecheck[path]}{$filecheck[filename]}";
			$ctype = "application/txt";
			
			if(!file_exists($file))
				die("Error 1: This file cannot be found on the server.");
			
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private",false);
			header("Content-Type: $ctype");
			header("Content-Disposition: attachment; filename=\"".$filename."\";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".@filesize($file));
			if(function_exists('set_time_limit')) set_time_limit(0);
			
			//@readfile($file) or die("File not found."); 
			//unlink($filename);
			
			ob_end_clean();
			@readfileChunked($file);// or die("File not found.");
		}
		
		if($mediaInfo['quantity'] and $download['downloadType'] != 'prevDown' and $download['downloadType'] != 'order') // Quantity is limited - update the database
		{
			// Set the new quantity
			$newQuantity = $mediaInfo['quantity']-1;
			mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET quantity='{$newQuantity}' WHERE media_id = '{$mediaInfo[media_id]}'");
		}
	}
	else // Variation of the original
	{		
		
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false);
		
		// Get the correct folder name based on if it is encrypted or not
		if($folderInfo['encrypted'])
			$folderName = $folderInfo['enc_name'];
		else
			$folderName = $folderInfo['name'];
		
		
		if($download['externalLink'])
		{
			// Check availability
			if($filecheck['status'])
			{
				if(downloadcURL($download['externalLink'], $tempPath.$tempFileName))
				{
					$folderPath = $tempPath.$tempFileName; // The full folder path
				}
				else
					die('File download failed!');
				
			}
			else
				die("File cannot be found at link provided.");
			
		}
		else
		{
			$folderPath = "{$config[settings][library_path]}/{$folderName}"; // The full folder path
		}
				
		
		idCheck($download['profileID']); // Make sure ID is numeric
		
		// This is a digital variation of - get the details
		$dspResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}media_digital_sizes 
			WHERE ds_id = '{$download[profileID]}' 
			AND media_id = '{$download[mediaID]}'
			"
		);
		$dsp = mysqli_fetch_array($dspResult);
		
		//echo $download[profileID]; exit;
		
		// Get the original digital profile details
		$digitalResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}digital_sizes 
			WHERE ds_id = '{$download[profileID]}'
			"
		);
		if($digitalVarRows = mysqli_num_rows($digitalResult))
		{
			$digital = mysqli_fetch_array($digitalResult);
			$download['deliveryMethod'] = $digital['delivery_method']; // Take setting from dp
		}
		
		//print_r($digital); exit; // Testing
		
		if($dsp['customized']) // This is from a customized profile
		{
			
			$digital['width'] = ($dsp['width']) ? $dsp['width']: $digital['width']; // Width
			$digital['height'] = ($dsp['height']) ? $dsp['height']: $digital['height']; // Height
			
			if($dsp['quantity'] and $download['downloadType'] != 'prevDown' and $download['downloadType'] != 'order')
			{
				// Set the new quantity
				$newQuantity = $dsp['quantity']-1;
				mysqli_query($db,"UPDATE {$dbinfo[pre]}media_digital_sizes SET quantity='{$newQuantity}' WHERE mds_id = '{$dsp[mds_id]}'");
			}

			// Get the size
		}
		else // Get details directly from main profile
		{
			//
		}
		
		//echo 'dm: '.$download['deliveryMethod']; exit;
		
		if($download['deliveryMethod'] == 1) // Auto create this size
		{	
			//print_r($download); exit;
			
			if($digital['real_sizes'])
			{
				// Landscape
				if($mediaInfo['width'] >= $mediaInfo['height'])
				{
					$scaleRatio = $digital['width']/$mediaInfo['width'];									
					$width = $digital['width'];
					$height = round($mediaInfo['height']*$scaleRatio);
				}
				else // Portrait
				{
					$scaleRatio = $digital['height']/$mediaInfo['height'];									
					$width = round($mediaInfo['width']*$scaleRatio);
					$height = $digital['height'];
				}
			}
			else
			{
				$width = $digital['width'];
				$height = $digital['height'];	
			}
			
			if($width < 1 or $height < 1) // Make sure a width or height is defined
				die("No width or height defined for digital profile.");
			
			/* Testing
			echo $digital['width'];
			echo "x".$digital['height']; 
			exit;
			*/
			
			if($mediaInfo['width'] < $width and $mediaInfo['height'] < $height) // The original is smaller - send that
			{	
				
				// Check availability
				if($download['externalLink'])
				{
					if($filecheck['status'])
					{
						if(downloadcURL($download['externalLink'], $tempPath.$tempFileName))
						{
							header("Content-Type: application/txt");
							header("Content-Disposition: attachment; filename=\"".$tempFileName."\"");
							header("Content-Transfer-Encoding: binary");
							header("Content-Length: ".@filesize($tempPath.$tempFileName));
							if(function_exists('set_time_limit')) set_time_limit(0);
							//@readfile($file) or die("File not found.");
							
							ob_end_clean();
							@readfileChunked($tempPath.$tempFileName);// or die("File not found.");
							
							@unlink($tempPath.$tempFileName);
						}
						else
							die('File download failed!');
					}
					else
						die("File cannot be found at link provided.");
				}
				else
				{
					$filename = $filecheck['filename'];
					$file = "{$filecheck[path]}{$filecheck[filename]}";
					$ctype = "application/txt";
					
					if(!file_exists($file))
						die("Error 2: This file cannot be found on the server.");
					
					header("Pragma: public");
					header("Expires: 0");
					header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
					header("Cache-Control: private",false);
					header("Content-Type: $ctype");
					header("Content-Disposition: attachment; filename=\"".$filename."\";");
					header("Content-Transfer-Encoding: binary");
					header("Content-Length: ".@filesize($file));
					if(function_exists('set_time_limit')) set_time_limit(0);
					//@readfile($file) or die("File not found.");
					
					ob_end_clean();
					@readfileChunked($file);// or die("File not found.");
				}
			}
			else // Create additional size
			{			
				$tmpFilename = create_unique2().".jpg";
				$tmpFilenamePath = BASE_PATH."/assets/tmp/{$tmpFilename}";
				
				// Check availability
				if($download['externalLink'])
				{
					if($filecheck['status'])
					{
						if(downloadcURL($download['externalLink'], $tempPath.$tempFileName))
						{
							// Worked
							$imgPath = $tempPath.$tempFileName;
						}
						else
							die('File download failed!');
					}
					else
						die("File cannot be found at link provided.");
				}
				else
				{
					// Do resizing and downloading
					$imgPath = "{$folderPath}/originals/{$mediaInfo[filename]}";
				}
				
				try {				
					$mediaImage = new imagetools($imgPath);
					$mediaImage->setQuality(100);
					//$mediaImage->setCrop($crop);
					//$mediaImage->setHCrop($hcrop);
					$mediaImage->setSize($width);
					$mediaImage->setWidth($width);
					$mediaImage->setHeight($height);
					//$mediaImage->setSharpen($sharpen);
					$mediaImage->setWatermark($digital['watermark']);				
					$mediaImage->createImage(0,$tmpFilenamePath);					
				} catch(Exception $e) {
					die(exceptionError($e));
				}
				
				//$file = "./assets/tmp/{$tmpFilename}";
				$ctype = "application/txt";
				
				if(!file_exists($tmpFilenamePath))
					die("Error 3: This file cannot be found on the server.");
				
				$downloadFilename = basefilename($mediaInfo['ofilename']).".jpg";
				
				header("Content-Type: $ctype");
				header("Content-Disposition: attachment; filename=\"".$downloadFilename."\"");
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ".@filesize($tmpFilenamePath));
				if(function_exists('set_time_limit')) set_time_limit(0);
				//@readfile($tmpFilenamePath) or die("File not found.");
				
				ob_end_clean();
				@readfileChunked($tmpFilenamePath);// or die("File not found.");
				
				unlink($tmpFilenamePath); // Delete the temp file after it has been downloaded
			}
			
		}
		else
		{
			//echo "test"; exit;
			
			if($dsp['filename']) // Make sure filename is in db
			{
				$filename = $dsp['filename'];
				$file = "{$folderPath}/variations/{$filename}";
				$ctype = "application/txt";
				
				$downloadFilename = ($dsp['ofilename']) ? $dsp['ofilename'] : $dsp['filename'];
				
				if (!file_exists($file))
					die("Error 4: This file cannot be found on the server.");
				
				header("Content-Type: $ctype");
				header("Content-Disposition: attachment; filename=\"".$downloadFilename."\"");
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ".@filesize($file));
				if(function_exists('set_time_limit')) set_time_limit(0);
				//@readfile($file) or die("File not found.");
				
				ob_end_clean();
				@readfileChunked($file);// or die("File not found.");
			}
			elseif($download['externalLink'])
			{
				if($filecheck['status'])
				{
					if(downloadcURL($download['externalLink'], $tempPath.$tempFileName))
					{
						header("Content-Type: application/txt");
						header("Content-Disposition: attachment; filename=\"".$tempFileName."\"");
						header("Content-Transfer-Encoding: binary");
						header("Content-Length: ".@filesize($tempPath.$tempFileName));
						if(function_exists('set_time_limit')) set_time_limit(0);
						//@readfile($file) or die("File not found.");
						
						ob_end_clean();
						@readfileChunked($tempPath.$tempFileName);// or die("File not found.");
						
						@unlink($tempPath.$tempFileName);
					}
					else
						die('File download failed!');
				}
				else
					die("File cannot be found at link provided.");
			}
			else
			{
				echo "File not available for download.";
				exit;
			}
		}
	}
	
	// Track a sub download if the owner is a contributor
	if($download['downloadType'] == 'sub')
	{
		// Check downloader is not owner
		if($mediaInfo['owner'] != $_SESSION['member']['mem_id'])
		{
			$ownerResult = mysqli_query($db,
			"
				SELECT {$dbinfo[pre]}memberships.allow_selling FROM {$dbinfo[pre]}members 
				LEFT JOIN {$dbinfo[pre]}memberships 
				ON {$dbinfo[pre]}members.membership = {$dbinfo[pre]}memberships.ms_id 
				WHERE {$dbinfo[pre]}members.mem_id = '{$mediaInfo[owner]}'
			");
			$owner = mysqli_fetch_assoc($ownerResult);
			
			// Check if owner is contributor
			if($owner['allow_selling'])
			{
				// Make sure commission wasn't already given for this combo
				$dupCheckResult = mysqli_query($db,"SELECT com_id FROM {$dbinfo[pre]}commission WHERE omedia_id = '{$download[mediaID]}' AND dl_mem_id = '{$_SESSION[member][mem_id]}'");
				if(!$dupCheckRows = mysqli_num_rows($dupCheckResult))
				{	
					// Owner is contributor - make sure they get commission for this download 
					mysqli_query($db,
					"
						INSERT INTO {$dbinfo[pre]}commission 
						(
							contr_id,
							omedia_id,
							odsp_id,
							com_total,
							comtype,
							item_qty,
							order_status,
							order_date,
							item_percent,
							mem_percent,
							dl_sub_id,
							dl_mem_id
						) VALUES (
							'{$mediaInfo[owner]}',
							'{$download[mediaID]}',
							'0',
							'{$config[settings][sub_com]}',
							'cur',
							1,
							1,
							now(),
							100,
							100,
							'{$download[downloadTypeID]}',
							'{$_SESSION[member][mem_id]}'				
						)
					");
				}
			}
		}
		
		//echo "sub"; exit;
		//test('just testing1224');
	}
	
	if($download['downloadType'] == 'order') // Download type is an order so mark 1 download up in the invoiceItem table
		mysqli_query($db,"UPDATE {$dbinfo[pre]}invoice_items SET downloads=downloads+1 WHERE oi_id = '{$download[invoiceItemID]}'");
	
	// If not available do the check in the HTML
	
	if($_SESSION['loggedIn'])
		$download['memID'] = $_SESSION['member']['mem_id'];
	else
		$download['memID'] = $memID = 0;
		
		
	// Make sure the the file exists before tracking it in the db
	
	// Enter the download in the db
	if(mysqli_query($db,"INSERT INTO {$dbinfo[pre]}downloads (mem_id,asset_id,dsp_id,customize_id,dl_date,dl_type,dl_type_id) VALUES ('{$download[memID]}','{$download[mediaID]}','{$download[profileID]}','{$download[customizeID]}','{$nowGMT}','{$download[downloadType]}','{$download[downloadTypeID]}')")) // Track the download
	{
	}	
	
	/*
	# MEDIATOOLS CLASS
	require_once('../assets/classes/mediatools.php');
	
	$media = new mediaTools($_GET['mediaID']);
	$mediaInfo = $media->getMediaInfoFromDB();
	$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
	$filecheck = $media->verifyMediaFileExists(); // Returns array [stauts,path,filename]
	
	$filename = $filecheck['filename'];
	$file = "{$filecheck[path]}{$filecheck[filename]}";
	$ctype = "applicatoin/txt";
	
	if (!file_exists($file)) {
		die("NO FILE HERE");
	}

	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private",false);
	header("Content-Type: $ctype");
	header("Content-Disposition: attachment; filename=\"".$filename."\";");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: ".@filesize($file));
	set_time_limit(0);
	@readfile("$file") or die("File not found."); 
	//unlink($filename);
	exit;
	*/
?>