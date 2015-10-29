<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-25-2011
	*  Modified: 4-25-2011
	******************************************************************/

	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/tweak.php';
	
	/*
	* Caching of images
	*/
	$cacheFile = "ps{$_GET[photoID]}-".md5("{$_GET[photoID]}-{$_GET[itemID]}-{$_GET[itemType]}-{$_GET[size]}-{$_GET[crop]}-{$_GET[quality]}-{$_GET[sizeType]}").'.jpg'; // Name of cached file
	$cachePathFile = BASE_PATH."/assets/cache/{$cacheFile}";
	if(file_exists($cachePathFile))
	{
		if(!$_SESSION['debugMode'] and $config['cacheImages']) // Check for debug mode
		{
			$cacheTime = gmdate("U")-$config['cacheImagesTime'];
			$fileTime = filemtime($cachePathFile);
			
			if($cacheTime < $fileTime)
			{	
				header("Content-type: image/jpeg");
				//header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($thumbnail)) . ' GMT');
				readfile($cachePathFile);
				exit;
			}
			else // Cleanup old cached file
				@unlink($cachePathFile);
		}
	}
	
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	
	/*
	* Convert to global variables
	*/
	$size = ($_GET['size'])? $_GET['size'] : '100';
	$quality = $config['settings']['thumb_quality'];
	$sharpen = $config['settings']['thumb_sharpen'];	
	$photoID = zerofill($_GET['photoID'],4);
	$itemID = zerofill($_GET['itemID'],4);
	$itemType = $_GET['itemType'];
	$crop = $_GET['crop'];
	$hcrop = $_GET['hcrop'];
	$sizeType = ($_GET['sizeType'])? $_GET['sizeType'] : 'small';
	
	if($size > 149 and file_exists(BASE_PATH."/assets/item_photos/{$itemType}{$itemID}_ip{$photoID}_med.jpg")) // If size setting is larger then 200 use the medium size instead
		$sizeType = 'med';
	if($size > 500 and file_exists(BASE_PATH."/assets/item_photos/{$itemType}{$itemID}_ip{$photoID}_org.jpg")) // If size setting is larger then 500 use the original size instead
		$sizeType = 'org'; // xxxxxxxxxxxxxx check to make sure of memory limit

	$path = BASE_PATH."/assets/item_photos/{$itemType}{$itemID}_ip{$photoID}_{$sizeType}.jpg"; // Direct path to product shot file to use
	
	if(!file_exists($path))
		$path = BASE_PATH."/assets/images/blank.png";
	
	if($itemType == 'gallery' and $config['settings']['gallerythumbcrop'] and !$crop) // If this is a gallery icon and thumb cropping is turned on figure out the size
		$crop = $config['settings']['gallerythumbcrop_height'];
	
	try
	{
		$productShot = new imagetools($path);
		$productShot->setQuality($quality);
		$productShot->setCrop($crop);
		$productShot->setHCrop($hcrop);
		$productShot->setSize($size);
		$productShot->setSharpen($sharpen);
		//$productShot->createImage(1,'');
		
		if($_SESSION['debugMode'] or $config['cacheImages'] == 0)
			$productShot->createImage(1,''); // Do not cache
		else
			$productShot->createImage(1,$cachePathFile); // Cache
		
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}
?>