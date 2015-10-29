<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 8-27-2012
	*  Modified: 8-27-2012
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','upload'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	//require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	//require_once BASE_PATH.'/assets/classes/mediatools.php';
	require_once BASE_PATH.'/assets/classes/imagetools.php';
	
	switch($handler)
	{
		default:
		break;
		case "addMedia":
			
			if($uploaderType == 'java')
			{	
				if(!$_SESSION['member']['mem_id']) die('No member id exists'); // Make sure a member id exists
				
				$contrFID = zerofill($_SESSION['member']['mem_id'],5);
				$incomingFolder = BASE_PATH.'/assets/contributors/contr'.$contrFID.'/'; // Search folder for files
				
				$filename = clean_filename($_FILES['file']['name']); // Clean the filename
				$uploadFile = $incomingFolder.$filename; // Get the full path for the file
				
				$basefilename = basefilename($filename); // Get the base filename
				
				$icon = $incomingFolder.basename("icon_".$basefilename.".jpg"); // Icon filename
				$thumb = $incomingFolder.basename("thumb_".$basefilename.".jpg"); // Thumb filename
				$sample = $incomingFolder.basename("sample_".$basefilename.".jpg"); // Sample filename
				
				//	Move original upload
				if(move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile))
					echo "success";
				else
					echo "failure";
				
				// Move icon
				if(move_uploaded_file($_FILES['icon']['tmp_name'], $icon))
					echo "success";
				else
					echo "failure";
				
				// Move thumb
				if(move_uploaded_file($_FILES['thumb']['tmp_name'], $thumb))
					echo "success";
				else
					echo "failure";
			
				// Move sample
				if(move_uploaded_file($_FILES['sample']['tmp_name'], $sample))
					echo "success";
				else
					echo "failure";
					
				$_SESSION['contrImportFiles'][] = base64_encode($uploadFile);
			}	
			
			if($uploaderType == 'plupload')
			{	
				if(!$_SESSION['member']['mem_id']) die('No member id exists'); // Make sure a member id exists
				
				header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
				header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
				header("Cache-Control: no-store, no-cache, must-revalidate");
				header("Cache-Control: post-check=0, pre-check=0", false);
				header("Pragma: no-cache");
				
				
				$contrFID = zerofill($_SESSION['member']['mem_id'],5);
				$targetDir = BASE_PATH.'/assets/contributors/contr'.$contrFID.'/'; // Search folder for files
				
				//$filename = clean_filename($_FILES['file']['name']); // Clean the filename
				//$uploadFile = $incomingFolder.$filename; // Get the full path for the file				
				//$basefilename = basefilename($filename); // Get the base filename
				
				// Settings
				//$targetDir = ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "plupload";
				//$targetDir = 'uploads';
				
				$cleanupTargetDir = true; // Remove old files
				$maxFileAge = 5 * 3600; // Temp file age in seconds
				
				// 5 minutes execution time
				@set_time_limit(5 * 60);
				
				// Uncomment this one to fake upload time
				// usleep(5000);
				
				// Get parameters
				$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
				$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
				$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';
				
				// Clean the fileName for security reasons
				$fileName = preg_replace('/[^\w\._]+/', '_', $fileName);
								
				// Make sure the fileName is unique but only if chunking is disabled
				if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
					$ext = strrpos($fileName, '.');
					$fileName_a = substr($fileName, 0, $ext);
					$fileName_b = substr($fileName, $ext);
				
					$count = 1;
					while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName_a . '_' . $count . $fileName_b))
						$count++;
				
					$fileName = $fileName_a . '_' . $count . $fileName_b;
				}
				
				$filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
				
				// Create target dir
				if (!file_exists($targetDir))
					@mkdir($targetDir);
				
				// Remove old temp files	
				if ($cleanupTargetDir) {
					if (is_dir($targetDir) && ($dir = opendir($targetDir))) {
						while (($file = readdir($dir)) !== false) {
							$tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;
				
							// Remove temp file if it is older than the max age and is not the current file
							if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge) && ($tmpfilePath != "{$filePath}.part")) {
								@unlink($tmpfilePath);
							}
						}
						closedir($dir);
					} else {
						die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
					}
				}	
				
				// Look for the content type header
				if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
					$contentType = $_SERVER["HTTP_CONTENT_TYPE"];
				
				if (isset($_SERVER["CONTENT_TYPE"]))
					$contentType = $_SERVER["CONTENT_TYPE"];
				
				// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
				if (strpos($contentType, "multipart") !== false) {
					if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
						// Open temp file
						$out = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
						if ($out) {
							// Read binary input stream and append it to temp file
							$in = @fopen($_FILES['file']['tmp_name'], "rb");
				
							if ($in) {
								while ($buff = fread($in, 4096))
									fwrite($out, $buff);
							} else
								die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
							@fclose($in);
							@fclose($out);
							@unlink($_FILES['file']['tmp_name']);
						} else
							die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
					} else
						die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
				} else {
					// Open temp file
					$out = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
					if ($out) {
						// Read binary input stream and append it to temp file
						$in = @fopen("php://input", "rb");
				
						if ($in) {
							while ($buff = fread($in, 4096))
								fwrite($out, $buff);
						} else
							die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
				
						@fclose($in);
						@fclose($out);
					} else
						die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
				}
				
				// Check if file has been uploaded
				if (!$chunks || $chunk == $chunks - 1) {
					// Strip the temp .part suffix off 
					rename("{$filePath}.part", $filePath);

					$fileNameParts = explode(".",$fileName);
					$creatableFiletypes = getCreatableFormats();				
					$iconImage = $targetDir . "icon_" . basefilename($fileName) . ".jpg";
					$thumbImage = $targetDir . "thumb_" . basefilename($fileName) . ".jpg";
					$sampleImage = $targetDir . "sample_" . basefilename($fileName) . ".jpg";
					$filenameExt = strtolower(array_pop($fileNameParts));
					
					# CALCULATE THE MEMORY NEEDED ONLY IF IT IS A CREATABLE FORMAT
					if(in_array(strtolower($filenameExt),$creatableFiletypes))
					{
						# FIGURE MEMORY NEEDED
						$mem_needed = figure_memory_needed($targetDir.$fileName);
						if(ini_get("memory_limit")){
							$memory_limit = ini_get("memory_limit");
						} else {
							$memory_limit = $config['DefaultMemory'];
						}
						# IF IMAGEMAGICK ALLOW TWEAKED MEMORY LIMIT
						if(class_exists('Imagick') and $config['settings']['imageproc'] == 2)
						{
							$memory_limit = $config['DefaultMemory'];
						}
						
						$autoCreateAvailable = 1;
					}	
					
					//test($filenameExt);			
					
					# CHECK TO SEE IF ONE CAN BE CREATED
					if(in_array(strtolower($filenameExt),$creatableFiletypes))
					{
						# CHECK THE MEMORY NEEDED TO CREATE IT
						if($memory_limit > $mem_needed)
						{
							// Create Icon
							$image = new imagetools($targetDir.$fileName);
							$image->setSize($config['IconDefaultSize']);
							$image->setQuality($config['SaveThumbQuality']);
							$image->createImage(0,$iconImage);
							
							// Create Thumb
							$image->setSize($config['ThumbDefaultSize']);
							$image->setQuality($config['SaveThumbQuality']);
							$image->createImage(0,$thumbImage);
							
							// Create Sample
							$image->setSize($config['SampleDefaultSize']);
							$image->setQuality($config['SaveSampleQuality']);
							$image->createImage(0,$sampleImage);
						}
						else
						{
							$errormessage[] = $mgrlang['not_enough_mem'];
						}
					}					
					
					$_SESSION['contrImportFiles'][] = base64_encode($filePath);
				}
				
				die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
				
				
			}
		break;
	}
	
	if($db) mysqli_close($db);	
?>