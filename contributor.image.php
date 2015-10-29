<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 8-27-2012
	*  Modified: 8-27-2012
	******************************************************************/

	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/tweak.php';
	
	$contrFID = $_GET['contrID'];
	$incomingFolder = BASE_PATH.'/assets/contributors/contr'.$contrFID; // Search folder for files
	
	/*
	* Caching of images
	*/
	$cacheFile = "ps-".md5("contr{$contrFID}-{$_GET[src]}-{$_GET[size]}{$_GET[crop]}{$_GET[quality]}").'.jpg'; // Name of cached file
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
	$crop = $_GET['crop'];
	$hcrop = $_GET['hcrop'];
	
	$path = $incomingFolder."/{$_GET[src]}"; // Direct path to product shot file to use
	
	if(!file_exists($path))
		$path = BASE_PATH."/assets/images/blank.png";
	
	// echo $path; exit; // Testing
	
	try
	{
		$contrImage = new imagetools($path);
		$contrImage->setQuality($quality);
		$contrImage->setCrop($crop);
		$contrImage->setHCrop($hcrop);
		$contrImage->setSize($size);
		$contrImage->setSharpen($sharpen);
		//$productShot->createImage(1,'');
		
		if($_SESSION['debugMode'] or $config['cacheImages'] == 0)
			$contrImage->createImage(1,''); // Do not cache
		else
			$contrImage->createImage(1,$cachePathFile); // Cache
		
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}
?>