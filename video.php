<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 12-20-2011
	*  Modified: 12-20-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';	
	
	$mediaID = k_decrypt($mediaID);
	//$folderID = k_decrypt($folderID);
	
	try
	{
		$media = new mediaTools($mediaID);
		$mediaInfo = $media->getMediaInfoFromDB();					
		$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
		
		$useFolderName = ($folderInfo['encrypted']) ? $folderInfo['enc_name'] : $folderInfo['name']; // Check if it is encrypted or not
		
		// Check if the video sample file exists
		if($vidSampleInfo = $media->getVidSampleInfoFromDB())
		{
			$vidSampleVerify = $media->verifyVidSampleExists();
		}
		else
		{
			$vidSampleVerify['status'] = 0;	
		}
	}
	catch(Exception $e)
	{
		echo "<span style='color: #EEE'>" . $e->getMessage() . "</span>";	
	}
	
	$file = "{$config[settings][library_path]}/{$useFolderName}/samples/{$vidSampleInfo[vidsample_filename]}";
	
	//echo file_exists($file); exit;
	//echo is_readable($file); exit;
	//echo $file; exit;
	
	switch(filenameExt($file))
	{
		default: 
		case "mp4":
			header('Content-Type: video/mp4');
		break;
		case "flv":
		case "f4v":
			header('Content-Type: video/x-flv');
		break;
		case "mov":
			header('Content-Type: video/mov');
		break;
	}
		
	if(isset($_SERVER['HTTP_RANGE']))
	{ // do it for any device that supports byte-ranges not only iPhone
		rangeDownload($file);
	}
	else
	{
 		header("Content-Length: ".filesize($file));
		readfile($file);
	}
	
	function rangeDownload($file)
	{
		$fp = @fopen($file, 'rb');
		$size   = filesize($file); // File size
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte
	
		header("Accept-Ranges: 0-$length");
		
		if(isset($_SERVER['HTTP_RANGE']))
		{
			$c_start = $start;
			$c_end   = $end;
			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if (strpos($range, ',') !== false)
			{
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}
			if ($range0 == '-')
			{
				$c_start = $size - substr($range, 1);
			}
			else
			{
				$range  = explode('-', $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
		
			$c_end = ($c_end > $end) ? $end : $c_end;
			if($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size)
			{
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1; // Calculate new content length
			fseek($fp, $start);
			header('HTTP/1.1 206 Partial Content');
		}
		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: $length");
 
		// Start buffered download
		$buffer = 1024 * 8;
		while(!feof($fp) && ($p = ftell($fp)) <= $end)
		{
 			if($p + $buffer > $end)
			{
				$buffer = $end - $p + 1;
			}
			if(function_exists('set_time_limit')) set_time_limit(0); // Reset time limit for big files
			echo fread($fp, $buffer);
			flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
		}
		fclose($fp);
 	}

	@readfile($file);
?>