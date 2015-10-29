<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 10-13-2011
	*  Modified: 10-13-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','similarMedia'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	//require_once BASE_PATH.'/assets/includes/cart.inc.php';
	//require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
		
	try
	{	
		
		if(is_array($_SESSION['member']['memberPermGalleries']))
			$memberPermGalleriesForDB = implode(",",array_map("wrapSingleQuotes",$_SESSION['member']['memberPermGalleries'])); // Galleries that member has permissions to converted for DB use
		else
		{
			$memberPermGalleriesForDB = 0;
		}
		
		/*
		$useGalleryID = $galleryID; // Pre decrypted gallery id
		
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$mediaID = k_decrypt($mediaID);
			$galleryID = k_decrypt($galleryID);
		}
		
		$sql = 
		"
			SELECT SQL_CALC_FOUND_ROWS *
			FROM {$dbinfo[pre]}media
			LEFT JOIN {$dbinfo[pre]}media_galleries 
			ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
			WHERE {$dbinfo[pre]}media_galleries.gallery_id = {$_SESSION[id]}
			AND {$dbinfo[pre]}media.active = 1 
			AND {$dbinfo[pre]}media.media_id != {$mediaID} 
			GROUP BY {$dbinfo[pre]}media.media_id
			ORDER BY rand() 
			LIMIT 9
		";
		
		$media = new mediaList($sql); // Create a new mediaList object
		if($returnRows = $media->getRows()) // Continue only if results are found
		{					
			$media->setGalleryDetails($useGalleryID,$_SESSION['currentMode']); // Pass gallery details to media class
			$media->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
			$mediaArray = $media->getMediaArray(); // Get the array of media
			$thumbMediaDetailsArray = $media->getThumbMediaDetailsArray(); // Get the output for the details shown under thumbnails
			$smarty->assign('thumbMediaDetails',$thumbMediaDetailsArray);
			$smarty->assign('mediaRows',$returnRows);
			$smarty->assign('mediaArray',$mediaArray);
		}
		*/
	
		if($config['EncryptIDs']) // Decrypt IDs
			$mediaID = k_decrypt($mediaID);
		
		$keywordsResult = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS keyword,media_id FROM {$dbinfo[pre]}keywords WHERE media_id = '{$mediaID}'");
		if($keywordRows = getRows())
		{
			while($keyword = mysqli_fetch_assoc($keywordsResult))
				$keywordArray[] = "'".str_replace("'","\'",$keyword['keyword'])."'"; // Replace ' in keywords
				
			$keywordString = implode(",",$keywordArray);

			//echo $keywordRows;
	
			if(!$keywordString) $keywordString = 0;
			
			//echo $keywordString; // Testing
			
			$similarKeywordsResult = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS media_id FROM {$dbinfo[pre]}keywords WHERE keyword IN ({$keywordString}) AND media_id != '{$mediaID}'");
			$similarKeywordRows = getRows();
			while($similarKeyword = mysqli_fetch_assoc($similarKeywordsResult))
				$similarKeywordsArray[$similarKeyword['media_id']]++;
			
			if(count($similarKeywordsArray) > 0)
			{
				arsort($similarKeywordsArray); // Sort them from most hits to less hits
				$mostSimilarKeywordsKeys = array_keys($similarKeywordsArray); // Get the keys
				$mostSimilarKeywordsString = implode(",",$mostSimilarKeywordsKeys); // Make a string out of it
			}
			else
				$mostSimilarKeywordsString = 0;

			//echo $mostSimilarKeywordsString; // Testing
			/*
			$sql = 
			"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}media
				LEFT JOIN {$dbinfo[pre]}media_galleries
				ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
				WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB})				
				AND {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1 
				AND {$dbinfo[pre]}media.media_id IN ({$mostSimilarKeywordsString})
				GROUP BY {$dbinfo[pre]}media.media_id
				ORDER BY FIELD({$dbinfo[pre]}media.media_id, ".$mostSimilarKeywordsString.")
				LIMIT 9
			";
			*/
			$sql = 
			"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}media 
				WHERE {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1 
				AND {$dbinfo[pre]}media.media_id IN ({$mostSimilarKeywordsString})
				AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB}))
				ORDER BY FIELD({$dbinfo[pre]}media.media_id, ".$mostSimilarKeywordsString.")
				LIMIT 9
			";	// New 4.3.2	
			$media = new mediaList($sql); // Create a new mediaList object
			if($returnRows = $media->getRows()) // Continue only if results are found
			{					
				$media->setGalleryDetails($useGalleryID,$_SESSION['currentMode']); // Pass gallery details to media class
				$media->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
				$mediaArray = $media->getMediaArray(); // Get the array of media
				$thumbMediaDetailsArray = $media->getThumbMediaDetailsArray(); // Get the output for the details shown under thumbnails
				$smarty->assign('thumbMediaDetails',$thumbMediaDetailsArray);
				$smarty->assign('mediaRows',$returnRows);
				$smarty->assign('mediaArray',$mediaArray);
			}
		}
		
		$smarty->display('similar.media.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
?>