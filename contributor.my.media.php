<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','contributorMyMedia'); // Page ID
	define('ACCESS','private'); // Page access type - public|private
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
	
	$memID = $_SESSION['member']['mem_id'];
	if(!$memID) die('No member ID exists'); // Just to be safe make sure a member id exists before continuing
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/init.contributor.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/paging.php';

	if($_GET['delete'])
	{
		
	}

	try
	{		
		
		if($_GET['albumID'])
			$_SESSION['contrAlbumID'] = $_GET['albumID']; // See if the mode album ID changed

		if($gallerySortBy or $gallerySortType)
			unset($_SESSION['prevNextArraySess']); // Clear any prevNextArraySess previously set if the sort order is changed
		
		if($gallerySortBy)
			$_SESSION['sessGallerySortBy'] = $gallerySortBy; // If a gallerySortBy is passed update the session
		
		if($gallerySortType)
			$_SESSION['sessGallerySortType'] = $gallerySortType; // If gallerySortType is passed update the session

		/*
		* Media Paging
		*/
		$mediaPerPage = $config['settings']['media_perpage']; // Set the default media per page amount
		$mediaPages = new paging('media');
		$mediaPages->setPerPage($mediaPerPage);
		
		if($_SESSION['currentContrMode'] != $mode) // Changing sections - reset everything
		{
			$_SESSION['currentContrMode'] = $mode;
			$mediaPages->setCurrentPage(1);
			
			unset($_SESSION['prevNextArraySess']); // Clear any prevNextArraySess previously set
			unset($_SESSION['sessGallerySortBy']); // Clear sessGallerySortBy
			unset($_SESSION['sessGallerySortType']); // Clear sessGallerySortType
		}
		
		if(!$_SESSION['currentContrMode'])
			$_SESSION['currentContrMode'] = 'all'; // if no mode set a default
		
		// Update crumbs links
		unset($_SESSION['crumbsSession']);
		
		$crumbs[0]['linkto'] = $siteURL."/contributor.my.media.php?mode=all"; // Check for SEO
		$crumbs[0]['name'] = $lang['contMedia'];
		
		switch($_SESSION['currentContrMode'])
		{
			case "all":
				$currentAblum['name'] = '';

				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media 
					WHERE owner = {$memID} 
					AND active = 1
					GROUP BY media_id
					ORDER BY media_id DESC
				";

			break;
			case "pending":
				$currentAblum['name'] = $lang['approvalStatus0'];
				
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media 
					WHERE owner = {$memID} 
					AND active = 1 
					AND approval_status = 0 
					GROUP BY media_id
					ORDER BY media_id DESC
				";
				
				$crumbs[1]['linkto'] = $siteURL."/contributor.my.media.php?mode=pending"; // Check for SEO
				$crumbs[1]['name'] = $lang['approvalStatus0'];
				
			break;
			case "failed":
				$currentAblum['name'] = $lang['approvalStatus2'];

				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media 
					WHERE owner = {$memID} 
					AND active = 1 
					AND approval_status = 2 
					GROUP BY media_id
					ORDER BY media_id DESC
				";
				
				$crumbs[1]['linkto'] = $siteURL."/contributor.my.media.php?mode=failed"; // Check for SEO
				$crumbs[1]['name'] = $lang['approvalStatus2'];

			break;
			case "orphaned":
				$currentAblum['name'] = $lang['orphanedMedia'];
				
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *   
					FROM {$dbinfo[pre]}media 
					WHERE {$dbinfo[pre]}media.owner = '{$memID}' 
					AND {$dbinfo[pre]}media.media_id NOT IN (SELECT {$dbinfo[pre]}media_galleries.gmedia_id FROM {$dbinfo[pre]}media_galleries)
				";
				
				$crumbs[1]['linkto'] = $siteURL."/contributor.my.media.php?mode=orphaned"; // Check for SEO
				$crumbs[1]['name'] = $lang['orphanedMedia'];
				
			break;
			case "last":
				$currentAblum['name'] = $lang['lastBatch'];
				
				@$lastBatch = mysqli_result_patch(mysqli_query($db,"SELECT batch_id FROM {$dbinfo[pre]}media WHERE owner = '{$memID}' ORDER BY batch_id DESC"));
				
				if(!$lastBatch) $lastBatch = '0'; // Just in case there is no last batch yet
				
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media 
					WHERE owner = {$memID} 
					AND active = 1 
					AND batch_id = '{$lastBatch}' 
					GROUP BY media_id
					ORDER BY media_id DESC
				";
				
				$crumbs[1]['linkto'] = $siteURL."/contributor.my.media.php?mode=last"; // Check for SEO
				$crumbs[1]['name'] = $lang['lastBatch'];
				
			break;
			case "album":
				$currentAlbumID = getAlbumID($_SESSION['contrAlbumID']);
				$currentAblum['name'] = $_SESSION['member']['contrAlbumsData'][$currentAlbumID]['name'];
				
				$currentGallery = $_SESSION['galleriesData'][$currentAlbumID]; // Assign the current gallery details
		
				if(!$_SESSION['sessGallerySortBy']) // If sessGallerySortBy isn't set then use the default
					$_SESSION['sessGallerySortBy'] = ($currentGallery['dsorting']) ? $currentGallery['dsorting'] : $config['settings']['dsorting'];
				
				if(!$_SESSION['sessGallerySortType']) // If sessGallerySortType isn't set then use the default
					$_SESSION['sessGallerySortType'] = ($currentGallery['dsorting2']) ? $currentGallery['dsorting2'] : $config['settings']['dsorting2'];
				
				if($_SESSION['sessGallerySortBy'] != 'media_id') // Add a secondary ordering type just in case
					$sql.= ",{$dbinfo[pre]}media.media_id DESC";
		
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media
					LEFT JOIN {$dbinfo[pre]}media_galleries 
					ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
					WHERE {$dbinfo[pre]}media_galleries.gallery_id = {$currentAlbumID}
					AND {$dbinfo[pre]}media.active = 1
					GROUP BY {$dbinfo[pre]}media.media_id
					ORDER BY {$dbinfo[pre]}media.{$_SESSION[sessGallerySortBy]} {$_SESSION[sessGallerySortType]}
				"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
				
				$crumbs[1]['linkto'] = $siteURL."/contributor.my.media.php?mode=album&albumID={$albumID}"; // Check for SEO
				$crumbs[1]['name'] = $currentAblum['name'];
				
			break;
		}
		
		$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
		
		$mediaPages->setPageName('contributor.my.media.php?mode='.$_SESSION['currentContrMode']);
		$mediaPages->setPageVar();
		
		if($page)
			$mediaPages->setCurrentPage($page); // Set new current media page	
		else
			$mediaPages->setCurrentPage($_SESSION['mediaCurrentPage']); // Use session current page
		
		$mediaStartRecord = $mediaPages->getStartRecord(); // Get the record the db should start at	
		
		if($sql) // Only do the following if the gallery is other than the top level
		{
			/*
			* Previous and next button array
			*/
			if(!$_SESSION['prevNextArraySess']) // Only do this if it doesn't already exist
			{
				$prevNextResult = mysqli_query($db,str_replace('*',"{$dbinfo[pre]}media.media_id",$sql.$maxPrevNext));
				while($prevNext = mysqli_fetch_assoc($prevNextResult))
					$prevNextArray[] = $prevNext['media_id'];					
				$_SESSION['prevNextArraySess'] = $prevNextArray;
			}
			
			//print_r($_SESSION['prevNextArraySess']);
			
			$sql.=
			"
				LIMIT {$mediaStartRecord},{$mediaPerPage}
			"; // Add the limit code to the query
			
			//echo $sql; exit; // Testing
			
			/*
			* Get all the media information and pass it to smarty
			*/			
			$media = new mediaList($sql); // Create a new mediaList object
			if($returnRows = $media->getRows()) // Continue only if results are found
			{				
				//echo $sql; exit;
				
				switch($mode)
				{
					case 'newest-media':
					case 'featured-media':
					case 'popular-media':
					case 'contributor-media':
						if($returnRows > ($mediaPerPage * $config['specMediaPageLimit'])) $returnRows = $mediaPerPage * $config['specMediaPageLimit']; // Limit the results to 20 pages in certain areas	
					break;
				}
						
				$mediaPages->setTotalResults($returnRows); // Pass the total number of results to the $pages object
				
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
			
			//print_r($mediaPagingArray); exit; // Testing
			
			$mediaPagingArray['pageNumbers'] = range(0,$mediaPagingArray['totalPages']);				
			unset($mediaPagingArray['pageNumbers'][0]); // Remove the 0 element from the beginning of the array
			$smarty->assign('mediaPaging',$mediaPagingArray);
		}
		
		$smarty->assign('currentAblum',$currentAblum);
		$smarty->assign('contrAlbumID',$_SESSION['contrAlbumID']);
		$smarty->assign('contrMediaMode',$_SESSION['currentContrMode']);
		$smarty->display('contributor.my.media.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>