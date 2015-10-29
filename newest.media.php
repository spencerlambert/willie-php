<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','newest-media'); // Page ID
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
	
	//define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	//define('META_DESCRIPTION','');
	//define('META_KEYWORDS','');
	//define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';	
	require_once BASE_PATH.'/assets/classes/paging.php';
	
	try
	{
		$_SESSION['currentMode'] = 'newest-media';
		
		/*
		* Media Paging
		*/
		$mediaPerPage = $config['settings']['media_perpage']; // Set the default media per page amount
		$mediaPages = new paging('media');
		$mediaPages->setPerPage($mediaPerPage);
		
		if(!$_GET['page']) // No page passed go to page 1
			$mediaPages->setCurrentPage(1);
		
		$mediaPages->setPageName('newest.media.php'); //?mode='.$_SESSION['currentMode']
		$mediaPages->setPageVar();
		
		if($page)
			$mediaPages->setCurrentPage($page); // Set new current media page	
		else
			$mediaPages->setCurrentPage($_SESSION['mediaCurrentPage']); // Use session current page
			
		$mediaStartRecord = $mediaPages->getStartRecord(); // Get the record the db should start at		

		unset($_SESSION['id']);
		
		$mediaCount = mysqli_num_rows(mysqli_query($db,
		"
			SELECT {$dbinfo[pre]}media.umedia_id
			FROM {$dbinfo[pre]}media
			LEFT JOIN {$dbinfo[pre]}media_galleries 
			ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
			WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB})
			AND {$dbinfo[pre]}media.active = 1 
			AND {$dbinfo[pre]}media.approval_status = 1 
			GROUP BY {$dbinfo[pre]}media.media_id
		")); // Get the total number of items
		
		if($mediaCount > ($mediaPerPage * 20)) $mediaCount = $mediaPerPage * 20; // Limit the results to 20 pages				
		$mediaPages->setTotalResults($mediaCount); // Pass the total number of results to the $pages object
		
		$sql = 
		"
			SELECT SQL_CALC_FOUND_ROWS *
			FROM {$dbinfo[pre]}media
			LEFT JOIN {$dbinfo[pre]}media_galleries 
			ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
			WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB})
			AND {$dbinfo[pre]}media.active = 1 
			AND {$dbinfo[pre]}media.approval_status = 1 
			GROUP BY {$dbinfo[pre]}media.media_id
			ORDER BY {$dbinfo[pre]}media.date_added DESC
			LIMIT {$mediaStartRecord},{$mediaPerPage}
		";
		
		$templateFile = 'newest.media.tpl';
		
		if($sql) // Only do the following if the gallery is other than the top level
		{
			/*
			* Get all the media information and pass it to smarty
			*/
			$media = new mediaList($sql); // Create a new mediaList object
			if($returnRows = $media->getRows()) // Continue only if results are found
			{					
				$media->setGalleryDetails($galleryID,$_SESSION['currentMode']); // Pass gallery details to media class
				$media->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
				$mediaArray = $media->getMediaArray(); // Get the array of media
				
				// old $thumbMediaDetailsArray = $media->getThumbMediaDetailsArray(); // Get the output for the details shown under thumbnails
				
				$thumbMediaDetailsArray = $media->getDetailsFields('thumb');
				
				$smarty->assign('thumbMediaDetails',$thumbMediaDetailsArray);
				$smarty->assign('mediaRows',$returnRows);
				$smarty->assign('mediaArray',$mediaArray);
			}
			
			/*
			* Get paging info and pass it to smarty
			*/
			$mediaPagingArray = $mediaPages->getPagingArray();
			$mediaPagingArray['pageNumbers'] = range(0,$mediaPagingArray['totalPages']);				
			unset($mediaPagingArray['pageNumbers'][0]); // Remove the 0 element from the beginning of the array
			$smarty->assign('mediaPaging',$mediaPagingArray);
		}
		
		$smarty->assign('startrec',$mediaStartRecord);
		$smarty->display($templateFile); // Display template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>