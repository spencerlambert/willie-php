<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-25-2011
	*  Modified: 4-25-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	
	/*
	* Convert to global variables
	*/
	$size = ($_GET['size'])? $_GET['size'] : '100';
	$quality = 90;
	$memID = $_GET['memID'];
	$crop = $_GET['crop'];
	$hcrop = $_GET['hcrop'];
	$sizeType = 'small';

	if($size > 17 and file_exists(BASE_PATH."/assets/avatars/{$memID}_large.png")) // If size setting is larger then 17 use the large size instead
		$sizeType = 'large';

	$path = BASE_PATH."/assets/avatars/{$memID}_{$sizeType}.png"; // Direct path to avatar file to use
	
	if(!file_exists($path))
		$path = BASE_PATH.'/assets/themes/'.$config['settings']['style'].'/images/avatar.png';
	
	if(!file_exists($path))
		$path = BASE_PATH."/assets/images/blank.png";
	
	try
	{
		$avatar = new imagetools($path);
		$avatar->setQuality($quality);
		$avatar->setCrop($crop);
		$avatar->setHCrop($hcrop);
		$avatar->setSize($size);
		$avatar->createImage(1,'');
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}
?>