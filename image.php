<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-25-2011
	*  Modified: 4-25-2011
	******************************************************************/
	
	//sleep(2);
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/tweak.php';
	
	/*
	* Attempt to prevent linking
	*/	
	if($config['disableLinking'])
	{	
		$thisURL = $_SERVER['HTTP_HOST']; //parse_url($_SERVER['HTTP_HOST'],PHP_URL_HOST);
		$referralURL = parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST);
		/*
		echo 'this'.$thisURL;
		echo "<br>";
		echo 'refer'.$referralURL;
		exit;
		*/
		$referralURL = str_replace('www.','',$referralURL); // Remove www.
		$thisURL = str_replace('www.','',$thisURL); // Remove www.
		
		/* Testing
		$_SESSION['testing']['referralURL'] = $referralURL;
		$_SESSION['testing']['thisURL'] = $thisURL;
		$_SESSION['testing']['HTTP_HOST'] = $_SERVER['HTTP_HOST'];
		*/
		
		if($referralURL and ($thisURL != $referralURL))
			$offSiteRequest = true;
	}
	
	/*
	* Caching of images
	*/
	$cacheFile = "id{$_GET[mediaID]}-".md5("{$_GET[type]}-{$_GET[mediaID]}-{$_GET[folderID]}-{$_GET[size]}-{$_GET[crop]}").'.jpg'; // Name of cached file
	
	//$_SESSION['testing'][$_GET['mediaID']] = $cacheFile; // Testing
	
	$cachePathFile = BASE_PATH."/assets/cache/{$cacheFile}";
	
	if(file_exists($cachePathFile))
	{	
		if(!$_SESSION['debugMode'] and $config['cacheImages'] and !$offSiteRequest) // Check for debug mode
		{
			$cacheTime = gmdate("U")-$config['cacheImagesTime'];
			$fileTime = filemtime($cachePathFile);
			
			if($cacheTime < $fileTime)
			{	
				header("Content-type: image/jpeg");
				//header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($thumbnail)) . ' GMT');
				
				//ob_clean();
				//flush();

				readfile($cachePathFile);
				exit;
			}
			else // Cleanup old cached file
				@unlink($cachePathFile);
		}
	}
		
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';	
	
	$mediaID = k_decrypt($mediaID);
	$folderID = k_decrypt($folderID);
	$hcrop = $_GET['hcrop'];
	
	//echo 'mid'.$mediaID; exit;
	
	if(!is_numeric($mediaID) or !is_numeric($folderID)) die('An error has occurred!'); // Check to make sure the IDs are numeric
	
	try
	{		
		$mediaInfo = new mediaTools($mediaID);
		$folderInfo = $mediaInfo->getFolderInfoFromDB($folderID);
		$folderName = $mediaInfo->getFolderName();
		
		switch($type)
		{
			default:
			case "thumbnail":								
				
				if($size and !is_numeric($size))
				{
					header("location: error.php?eType=invalidQuery");
					exit;	
				}			
				
				$size = ($size and $size <= $config['settings']['thumb_size']) ? $size : $config['settings']['thumb_size'];
				$quality = $config['settings']['thumb_quality'];
				$sharpen = $config['settings']['thumb_sharpen'];
				$watermark = ($config['settings']['thumb_wm']) ? $config['settings']['thumb_wm'] : false; // Determine if a watermark is needed
				
				if($config['settings']['thumbcrop'] or $crop)
				{
					$thumbCropValue = $config['settings']['thumbcrop_height'];
					$crop = ($crop) ? $crop : $thumbCropValue; // Set cropping if crop was passed or $config['settings']['thumbcrop'] is on
				}
				else
					$crop = 0;
					
				if($size <= 150)
				{
					$iconDetails = $mediaInfo->getIconInfoFromDB(); // Get an array of the icon details from the db
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . $iconDetails['thumb_filename'];
				}
				else
				{
					$thumbDetails = $mediaInfo->getThumbInfoFromDB(); // Get an array of the thumbnail details from the db
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "thumbs" . DIRECTORY_SEPARATOR . $thumbDetails['thumb_filename'];
				}
			break;
			case "rollover":			
				if($size and !is_numeric($size))
				{
					header("location: error.php?eType=invalidQuery");
					exit;	
				}
				
				$size = ($size and $size <= $config['settings']['rollover_size']) ? $size : $config['settings']['rollover_size'];
				$quality = $config['settings']['rollover_quality'];
				$sharpen = $config['settings']['rollover_sharpen'];
				$watermark = ($config['settings']['rollover_wm']) ? $config['settings']['rollover_wm'] : false; // Determine if a watermark is needed
				
				if($config['settings']['rollovercrop'] or $crop)
				{
					//$rolloverCropValue = round($config['settings']['rollover_size']*$config['CropPercentage']);
					$rolloverCropValue = $config['settings']['rollovercrop_height'];					
					$crop = ($crop) ? $crop : $rolloverCropValue; // Set cropping if crop was passed or $config['settings']['thumbcrop'] is on
				}
				else
					$crop = 0;
				
				if($size <= 150)
				{
					$iconDetails = $mediaInfo->getIconInfoFromDB(); // Get an array of the icon details from the db
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "icons" . DIRECTORY_SEPARATOR . $iconDetails['thumb_filename'];
				}
				else
				{
					$thumbDetails = $mediaInfo->getThumbInfoFromDB(); // Get an array of the thumbnail details from the db
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "thumbs" . DIRECTORY_SEPARATOR . $thumbDetails['thumb_filename'];
				}
			break;
			case "sample":
				if($size and !is_numeric($size))
				{
					header("location: error.php?eType=invalidQuery");
					exit;	
				}
				
				$size = ($size) ? $size : $config['settings']['preview_size'];
				$quality = $config['settings']['preview_quality'];
				$sharpen = $config['settings']['preview_sharpen'];
				$crop = ($crop) ? $crop : 0; // Set cropping if crop was passed				
				$watermark = ($config['settings']['preview_wm']) ? $config['settings']['preview_wm'] : false; // Determine if a watermark is needed
				
				//"./assets/watermarks/" . $config['settings']['preview_wm'];
				
				$sampleDetails = $mediaInfo->getSampleInfoFromDB(); // Get an array of the thumbnail details from the db
				
				if($size <= 500)
				{
					$thumbDetails = $mediaInfo->getThumbInfoFromDB(); // Get an array of the thumbnail details from the db
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "thumbs" . DIRECTORY_SEPARATOR . $thumbDetails['thumb_filename'];
				}
				else
				{
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "samples" . DIRECTORY_SEPARATOR . $sampleDetails['sample_filename'];
				}
				
				//echo $path; exit;
				
			break;
			case "featured":
				if($size and !is_numeric($size))
				{
					header("location: error.php?eType=invalidQuery");
					exit;	
				}
				
				$size = ($size) ? $size : $config['settings']['preview_size'];
				$quality = $config['settings']['preview_quality'];
				$sharpen = $config['settings']['preview_sharpen'];
				$crop = ($crop) ? $crop : 0; // Set cropping if crop was passed				
				$watermark = ($config['settings']['featured_wm']) ? $config['settings']['preview_wm'] : false; // Determine if a watermark is needed
				
				//"./assets/watermarks/" . $config['settings']['preview_wm'];
				
				$sampleDetails = $mediaInfo->getSampleInfoFromDB(); // Get an array of the thumbnail details from the db
				
				if($size <= 500)
				{
					$thumbDetails = $mediaInfo->getThumbInfoFromDB(); // Get an array of the thumbnail details from the db
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "thumbs" . DIRECTORY_SEPARATOR . $thumbDetails['thumb_filename'];
				}
				else
				{
					$path = $config['settings']['library_path'] . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . "samples" . DIRECTORY_SEPARATOR . $sampleDetails['sample_filename'];
				}
			break;
		}
		
		//echo $size; exit;
		
		/*
		* Do a little error checking to make sure that the file exists and that the server can process it
		*/
		//echo $path; exit; // Testing		
		if(!file_exists($path) or is_dir($path) == true)
		{
			$path = BASE_PATH."/assets/images/blank.png"; // File doesn't exist or a folder was returned - use blank.png
			$config['cacheImages'] = 0; // Override caching
		}
		else if($offSiteRequest == true)
		{
			$path = BASE_PATH."/assets/images/link.png"; // External linking detected - use link.png
			$config['cacheImages'] = 0; // Override caching
		}
		else if(figure_memory_needed($path) > getMemoryLimit())
		{
			$path = BASE_PATH."/assets/images/error.png"; // Not enough memory - use error.png
			$config['cacheImages'] = 0; // Override caching
		}

		/* // Testing
		echo 'path: '.$path.'<br>';
		echo 'quality: '.$quality.'<br>';
		echo 'crop: '.$crop.'<br>';
		echo 'hcrop: '.$hcrop.'<br>';
		echo 'sharpen: '.$sharpen.'<br>';
		echo 'watermark: '.$watermark.'<br>';
		exit;
		*/

		$mediaImage = new imagetools($path);
		$mediaImage->setQuality($quality);
		$mediaImage->setCrop($crop);
		$mediaImage->setDebugMode($_SESSION['debugMode'],$plstart);
		$mediaImage->setHCrop($hcrop);
		$mediaImage->setSize($size);
		$mediaImage->setSharpen($sharpen);
		$mediaImage->setWatermark($watermark);
		
		if($_SESSION['debugMode'] or $config['cacheImages'] == 0 or $offSiteRequest)
			$mediaImage->createImage(1,''); // Do not cache
		else
			$mediaImage->createImage(1,$cachePathFile); // Cache
		
		//file_get_contents xxxxxxxxxx for reading cached file
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>