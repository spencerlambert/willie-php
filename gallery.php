<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','gallery'); // Page ID
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
	require_once BASE_PATH.'/assets/classes/mediatools.php';

	//define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	//define('META_DESCRIPTION','');
	//define('META_KEYWORDS','');
	//define('PAGE_ENCODING','');
	
	$gallerySortByOptions['date_added'] = $lang['galSortDate'];
	$gallerySortByOptions['date_created'] = $lang['galSortCDate'];
	$gallerySortByOptions['media_id'] = $lang['galSortID'];
	$gallerySortByOptions['title'] = $lang['galSortTitle'];
	$gallerySortByOptions['filename'] = $lang['galSortFilename'];
	$gallerySortByOptions['filesize'] = $lang['galSortFilesize'];
	$gallerySortByOptions['sortorder'] = $lang['galSortSortNum'];
	$gallerySortByOptions['batch_id'] = $lang['galSortBatchID'];
	$gallerySortByOptions['featured'] = $lang['galSortFeatured'];
	$gallerySortByOptions['width'] = $lang['galSortWidth'];
	$gallerySortByOptions['height'] = $lang['galSortHeight'];
	$gallerySortByOptions['views'] = $lang['galSortViews'];	
	$gallerySortByTypeOptions['asc'] = $lang['galSortAsc']; 
	$gallerySortByTypeOptions['desc'] = $lang['galSortDesc'];
	
	if(preg_match("/[^A-Za-z0-9_-]/",$mode))
	{
		header("location: error.php?eType=invalidQuery");
		exit;
	}
	
	// Assign proper meta titles
	switch($mode)
	{
		case 'newest-media':
			define('META_TITLE',$lang['newestMedia'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
		case 'featured-media':
			define('META_TITLE',$lang['featuredMedia'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
		case 'popular-media':
			define('META_TITLE',$lang['popularMedia'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
	}
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';	
	require_once BASE_PATH.'/assets/classes/paging.php';

	$_SESSION['backButtonSession']['linkto'] = pageLink(); // Update the back button link session

	try
	{	
		$galleryID = $id; // Get the original ID no matter what the other settings are
		
		//echo $id;
		
		if($config['EncryptIDs'] and $id != '0') // Decrypt IDs
		{
			//echo "id : {$id} -";			
			$id = k_decrypt($id);
			if($id != '' and ($_SESSION['currentMode'] != 'lightbox' and $mode != 'lightbox')) idCheck($id); // Make sure ID is numeric
		}
		
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
		
		/*
		* Gallery Paging
		*/
		$galleryPerPage = $config['settings']['gallery_perpage']; // Set the default galleries per page amount
		$galleryPages = new paging('gallery');
		$galleryPages->setPerPage($galleryPerPage);
		
		if($_SESSION['currentMode'] != $mode) // Changing sections - reset everything
		{
			$_SESSION['currentMode'] = $mode;
			$mediaPages->setCurrentPage(1);
			$galleryPages->setCurrentPage(1);
			
			unset($_SESSION['prevNextArraySess']); // Clear any prevNextArraySess previously set
			unset($_SESSION['sessGallerySortBy']); // Clear sessGallerySortBy
			unset($_SESSION['sessGallerySortType']); // Clear sessGallerySortType
		}
		
		if(!$_SESSION['id'] or !$_GET['id'])
			$_SESSION['id'] = 0; // If there is no id then set the session to 0
	
		if($id != '' and ($_SESSION['id'] != $id)) // Contributor ID or gallery ID changed so reset current page
		{
			$_SESSION['id'] = $id;
			$mediaPages->setCurrentPage(1);
			$galleryPages->setCurrentPage(1);
			
			unset($_SESSION['prevNextArraySess']); // Clear any prevNextArraySess previously set
			unset($_SESSION['sessGallerySortBy']); // Clear sessGallerySortBy
			unset($_SESSION['sessGallerySortType']); // Clear sessGallerySortType
		}
		
		$mediaPages->setPageName('gallery.php?mode='.$_SESSION['currentMode']);
		$mediaPages->setPageVar();
		$galleryPages->setPageName('gallery.php?mode='.$_SESSION['currentMode']);
		$galleryPages->setPageVar('gpage');
		
		if($page)
		{
			if(!is_numeric($page))
			{
				header("location: error.php?eType=invalidQuery");
				exit;	
			}
			
			$mediaPages->setCurrentPage($page); // Set new current media page
			
		}
		else
			$mediaPages->setCurrentPage($_SESSION['mediaCurrentPage']); // Use session current page
			
		if($gpage)
		{
			if(!is_numeric($gpage))
			{
				header("location: error.php?eType=invalidQuery");
				exit;	
			}
			
			$galleryPages->setCurrentPage($gpage); // Set new current gallery page
		}
		else
			$galleryPages->setCurrentPage($_SESSION['galleryCurrentPage']); // Use session current page
			
		$galleryStartRecord = $galleryPages->getStartRecord(); // Get the record the db should start at
		$mediaStartRecord = $mediaPages->getStartRecord(); // Get the record the db should start at		
		$smarty->assign('galleryMode',$_SESSION['currentMode']); // Assign the gallery mode to smarty
		
		switch($_SESSION['currentMode'])
		{
			default:
				$templateFile = 'noaccess.tpl'; // No permissions - send to noaccess page
			break;
			/*
			* Gallery
			*/
			default:
			case "gallery": // xxxxxxxxxxxxx check for name and description languages
				
				if($_SESSION['galleriesData'][$_SESSION['id']] or $_SESSION['id'] == 0) // Do permissions check
				{
					if(@$_SESSION['galleriesData'][$_SESSION['id']]['password'] != '' and @!in_array($_SESSION['id'],$_SESSION['member']['memberPermGalleries'])) // Check if it is a password protected gallery and if member has already logged in
					{
						//$privateGalleryID = ($config['EncryptIDs']) ? : ; not needed
						header("location: {$siteURL}/gallery.login.php?id={$galleryID}"); // gallery login page
						exit;
					}
					
					if($_SESSION['id'] != 0)
					{
						$seoGalleryName = '';
						
						/*
						* Get the prints assigned to this gallery
						*/
						$printsResult = mysqli_query($db,
							"
							SELECT *
							FROM {$dbinfo[pre]}prints
							LEFT JOIN {$dbinfo[pre]}perms
							ON ({$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'prints')
							WHERE {$dbinfo[pre]}prints.active = 1 
							AND ({$dbinfo[pre]}prints.attachment = 'galleries' OR {$dbinfo[pre]}prints.attachment = 'both')
							AND	({$dbinfo[pre]}prints.all_galleries = 1 OR (SELECT item_id FROM {$dbinfo[pre]}item_galleries WHERE mgrarea = 'prints' AND item_id = {$dbinfo[pre]}prints.print_id AND gallery_id = '{$_SESSION[id]}'))
							AND {$dbinfo[pre]}prints.deleted = 0
							AND ({$dbinfo[pre]}prints.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
							ORDER BY {$dbinfo[pre]}prints.sortorder
							"
						); 
						if($returnRows = mysqli_num_rows($printsResult))
						{
							while($prints = mysqli_fetch_assoc($printsResult))
								$printsArray[] = printsList($prints);
								
							$smarty->assign('printRows',$returnRows);
							$smarty->assign('prints',$printsArray);
						}
						
						/*
						* Get the products assigned to this gallery
						*/
						$productsResult = mysqli_query($db,
							"
							SELECT *
							FROM {$dbinfo[pre]}products
							LEFT JOIN {$dbinfo[pre]}perms
							ON ({$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'products') 
							WHERE {$dbinfo[pre]}products.active = 1 
							AND ({$dbinfo[pre]}products.attachment = 'galleries' OR {$dbinfo[pre]}products.attachment = 'both')
							AND	({$dbinfo[pre]}products.all_galleries = 1 OR (SELECT item_id FROM {$dbinfo[pre]}item_galleries WHERE mgrarea = 'products' AND item_id = {$dbinfo[pre]}products.prod_id AND gallery_id = '{$_SESSION[id]}'))
							AND {$dbinfo[pre]}products.deleted = 0
							AND ({$dbinfo[pre]}products.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
							ORDER BY {$dbinfo[pre]}products.sortorder
							"
						); 
						if($returnRows = mysqli_num_rows($productsResult))
						{
							while($products = mysqli_fetch_assoc($productsResult))
								$productsArray[] = productsList($products);
								
							$smarty->assign('productRows',$returnRows);
							$smarty->assign('products',$productsArray);
						}
						
						/*
						* Get the packages assigned to this gallery
						*/
						$packagesResult = mysqli_query($db,
							"
							SELECT *
							FROM {$dbinfo[pre]}packages
							LEFT JOIN {$dbinfo[pre]}perms
							ON ({$dbinfo[pre]}packages.pack_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'packages') 
							WHERE {$dbinfo[pre]}packages.active = 1 
							AND ({$dbinfo[pre]}packages.attachment = 'galleries' OR {$dbinfo[pre]}packages.attachment = 'both')
							AND	({$dbinfo[pre]}packages.all_galleries = 1 OR (SELECT item_id FROM {$dbinfo[pre]}item_galleries WHERE mgrarea = 'packages' AND item_id = {$dbinfo[pre]}packages.pack_id AND gallery_id = '{$_SESSION['id']}'))
							AND {$dbinfo[pre]}packages.deleted = 0
							AND ({$dbinfo[pre]}packages.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
							ORDER BY {$dbinfo[pre]}packages.sortorder
							"
						); 
						if($returnRows = mysqli_num_rows($packagesResult))
						{
							while($packages = mysqli_fetch_assoc($packagesResult))
								$packagesArray[] = packagesList($packages);
								
							$smarty->assign('packageRows',$returnRows);
							$smarty->assign('packages',$packagesArray);
						}
						
						/*
						* Get the collections for this gallery
						*/
						$collectionsResult = mysqli_query($db,
							"
							SELECT *
							FROM {$dbinfo[pre]}collections
							LEFT JOIN {$dbinfo[pre]}perms
							ON ({$dbinfo[pre]}collections.coll_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'collections') 
							WHERE {$dbinfo[pre]}collections.active = 1 
							AND {$dbinfo[pre]}collections.deleted = 0 
							AND {$dbinfo[pre]}collections.colltype = 1
							AND (SELECT item_id FROM {$dbinfo[pre]}item_galleries WHERE mgrarea = 'collections' AND item_id = {$dbinfo[pre]}collections.coll_id AND gallery_id = '{$_SESSION[id]}')
							AND ({$dbinfo[pre]}collections.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
							ORDER BY {$dbinfo[pre]}collections.sortorder
							"
						);
						if($returnRows = mysqli_num_rows($collectionsResult))
						{
							while($collections = mysqli_fetch_assoc($collectionsResult))
								$collectionsArray[] = collectionsList($collections);
								
							$smarty->assign('collectionRows',$returnRows);
							$smarty->assign('collections',$collectionsArray);
						}
					}
					
					if($_SESSION['id'] != 0)
						$_SESSION['galleriesData'][$_SESSION['id']]['galleryIcon'] = galleryIcon($_SESSION['id']); // Get the current galleries galleryIcon details
					
					if($owner = $_SESSION['galleriesData'][$_SESSION['id']]['owner']) // Check for contributor
					{						
						$contrResult = mysqli_query($db,
							"
							SELECT SQL_CALC_FOUND_ROWS * 
							FROM {$dbinfo[pre]}members
							WHERE mem_id = '{$owner}'
							"
						);
						if($contrReturnRows = getRows())
						{							
							$contributor = contrList(mysqli_fetch_assoc($contrResult));
							
							$_SESSION['galleriesData'][0]['linkto'] = $contributor['profileLinkto']; // Check for SEO
							$_SESSION['galleriesData'][0]['name'] = $contributor['display_name']; // 
							
							$smarty->assign('contrReturnRows',$contrReturnRows);
							$smarty->assign('contributor',$contributor);
						}
						else
						{
							$owner = 0;	
						}
					}
					
					if(!$owner) // If there is no owner just put gallery on the front
					{
						$galleriesMainPageLink['page'] = "gallery.php?mode=gallery";
						$_SESSION['galleriesData'][0]['linkto'] = linkto($galleriesMainPageLink); // Check for SEO
						$_SESSION['galleriesData'][0]['name'] = $lang['galleries']; // 					
					}
					
					$crumbs = galleryCrumbs($_SESSION['id']); // Create the crumbs array
					
					$crumbsFull = galleryCrumbsFull($_SESSION['id']); // Create the crumbs array
					$_SESSION['crumbsSession'] = $crumbsFull; // Assign these to a session to be used elsewhere
					//print_r($crumbsFull); // Testing
					
					//echo $_SESSION['id']."<br />";
					//print_r($crumbs);
					
					$subGalleryCount = 0;
					$currentGalleryCount = 1;
					foreach($_SESSION['galleriesData'] as $key => $value) // Find subgalleries
					{
						if($value['parent_gal'] == $_SESSION['id'] and $value['gallery_id'] != 0) // Make sure it is a legitimate record with an ID - Fixes blank gallery problem
						{
							if($value['album'] == 0) // Only show those that aren't albums
							{
								if($currentGalleryCount > $galleryStartRecord and $currentGalleryCount <= ($galleryPerPage * $_SESSION['galleryCurrentPage'])) // and $currentGalleryCount < ($galleryPerPage + $currentGalleryCount)
								{
									$subGalleriesData[$key] = $value['gallery_id'];							
									$_SESSION['galleriesData'][$key]['galleryIcon'] = galleryIcon($key); // Get gallery icon details for subs if they exist
									
									//print_r($_SESSION['galleriesData'][$key]['galleryIcon']); echo "<br>";
									
									//$_SESSION['galleriesData'][$key]['name'] = ($galleryData['name_'.$galDefaultLang]) ? $galleryData['name_'.$galDefaultLang] : $galleryData['name']; // Get gallery icon details for subs if they exist
								}
								$currentGalleryCount++;
								$subGalleryCount++;
							}
						}
					}
					
					//print_k($subGalleriesData);
					
					$galleryPages->setTotalResults($subGalleryCount); // Set the total number of subgalleries
					
					if($_SESSION['id'] == 0)
					{
						$currentGallery['gallery_id'] = 0; // Assign the current gallery details
						$currentGallery['description'] = $lang['chooseGallery'];
					}
					else
					{	
						$currentGallery = $_SESSION['galleriesData'][$_SESSION['id']]; // Assign the current gallery details
						$currentGallery['description'] = $_SESSION['galleriesData'][$_SESSION['id']]['description'];
						$currentGallery['event_date_display'] = $customDate->showdate($_SESSION['galleriesData'][$_SESSION['id']]['event_date']);						
						
						if(!$_SESSION['sessGallerySortBy']) // If sessGallerySortBy isn't set then use the default
							$_SESSION['sessGallerySortBy'] = ($currentGallery['dsorting']) ? $currentGallery['dsorting'] : $config['settings']['dsorting'];
						
						if(!$_SESSION['sessGallerySortType']) // If sessGallerySortType isn't set then use the default
							$_SESSION['sessGallerySortType'] = ($currentGallery['dsorting2']) ? $currentGallery['dsorting2'] : $config['settings']['dsorting2'];
						
						//$orderBy = ($currentGallery['dsorting']) ? $currentGallery['dsorting'] : $config['settings']['dsorting']; // Set the order by value from the gallery info or default values
						//$orderType = ($currentGallery['dsorting2']) ? $currentGallery['dsorting2'] : $config['settings']['dsorting2']; // Set the order type value from the gallery info or default values
					}
	
					if($_SESSION['id'] != 0) // Only do this on subgalleries
					{
						//echo $orderType; exit;
						
						$sql = 
						"
							SELECT SQL_CALC_FOUND_ROWS *
							FROM {$dbinfo[pre]}media
							LEFT JOIN {$dbinfo[pre]}media_galleries 
							ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
							WHERE {$dbinfo[pre]}media_galleries.gallery_id = {$_SESSION[id]}
							AND {$dbinfo[pre]}media.active = 1 
							AND {$dbinfo[pre]}media.approval_status = 1 
							GROUP BY {$dbinfo[pre]}media.media_id
							ORDER BY {$dbinfo[pre]}media.{$_SESSION[sessGallerySortBy]} {$_SESSION[sessGallerySortType]}
						";
						/*
						$sql = 
						"
							SELECT SQL_CALC_FOUND_ROWS *
							FROM {$dbinfo[pre]}media
							WHERE {$dbinfo[pre]}media.active = 1 
							AND {$dbinfo[pre]}media.approval_status = 1 
							AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id = {$_SESSION[id]})
							ORDER BY {$dbinfo[pre]}media.{$_SESSION[sessGallerySortBy]} {$_SESSION[sessGallerySortType]}
						"; // New 4.3.2
						*/
						if($_SESSION['sessGallerySortBy'] != 'media_id') // Add a secondary ordering type just in case
							$sql.= ", {$dbinfo[pre]}media.media_id DESC";
					}
		
					// Title and description meta tags
					$smarty->assign('metaTitle',($_SESSION['galleriesData'][$_SESSION['id']]['name']) ? strip_tags($_SESSION['galleriesData'][$_SESSION['id']]['name'].' &ndash; '.$config['settings']['site_title']) : strip_tags($config['settings']['site_title'])); // Assign meta title
					$smarty->assign('metaDescription',($_SESSION['galleriesData'][$_SESSION['id']]['description']) ? strip_tags($_SESSION['galleriesData'][$_SESSION['id']]['description']) : strip_tags($config['settings']['site_description'])); // Assign meta description
					
					$smarty->assign('crumbs',$crumbs);
					$smarty->assign('currentGallery',$currentGallery);
					$smarty->assign('galleriesData',$_SESSION['galleriesData']);
					$smarty->assign('subGalleriesData',$subGalleriesData);
					$templateFile = 'gallery.tpl'; 
				}
				else
				{
					$templateFile = 'noaccess.tpl'; // No permissions - send to noaccess page
				}
			break;
			/*
			* Collection gallery
			*/
			case "collection":
				$collectionResult = mysqli_query($db,
				"			
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}collections 
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}collections.coll_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'collections') 
					WHERE {$dbinfo[pre]}collections.coll_id = {$_SESSION[id]}
					AND ({$dbinfo[pre]}collections.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				"
				);
				if($returnRows = getRows())
				{
					$collection = mysqli_fetch_array($collectionResult);
					/*
					// Update crumbs links
					unset($_SESSION['crumbsSession']);
					$newestMediaPageLink['page'] = "gallery.php?mode=newest-media&page=1";
					$crumbs[0]['linkto'] = linkto($newestMediaPageLink); // Check for SEO
					$crumbs[0]['name'] = $lang['newestMedia']; //				
					$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
					*/
					if($collection['active'] == 1 and $collection['deleted'] == 0 and ($collection['quantity'] == '' or $collection['quantity'] > 0))
					{
						$collectionArray = collectionsList($collection);
						$smarty->assign('collectionRows',$returnRows);
						$smarty->assign('collection',$collectionArray);
					
						// Update crumbs links
						unset($_SESSION['crumbsSession']);
						$collMediaPageLink['page'] = "gallery.php?mode=collection&id={$collectionArray[useCollectionID]}&page=1";
						$crumbs[0]['linkto'] = linkto($collMediaPageLink); // Check for SEO
						$crumbs[0]['name'] = $collectionArray['name']; //				
						$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
					
						if($collection['colltype'] == 1)
						{
							/* Only needed if we are checking active/inactive or expiration dates
								$collectionGalleriesResult = mysqli_query($db,
									"
									SELECT * FROM {$dbinfo[pre]}item_galleries 
									LEFT JOIN {$dbinfo[pre]}galleries 
									ON {$dbinfo[pre]}item_galleries.gallery_id  = {$dbinfo[pre]}galleries.gallery_id  
									WHERE {$dbinfo[pre]}item_galleries.mgrarea = 'collections' 
									AND {$dbinfo[pre]}item_galleries.item_id = '{$_SESSION[id]}'
									AND {$dbinfo[pre]}galleries.active = 1
									"
								);
							*/
							
							$collectionGalleriesResult = mysqli_query($db,"SELECT gallery_id FROM {$dbinfo[pre]}item_galleries WHERE mgrarea = 'collections' AND item_id = '{$_SESSION[id]}'");
							while($collectionGallery = mysqli_fetch_array($collectionGalleriesResult))
								$collectionGalleriesArray[] = $collectionGallery['gallery_id'];
							
							@$collectionGalleries = implode(",",$collectionGalleriesArray);
							if(!$collectionGalleries) $collectionGalleries = 0; // If no collection galleries pass 0
							
							// xxxxxxxxxxxxxxx - need to make sure the gallery is NOT deleted
							
							/*
							$sql = 
							"
								SELECT SQL_CALC_FOUND_ROWS * 
								FROM {$dbinfo[pre]}media 
								LEFT JOIN {$dbinfo[pre]}media_galleries 
								ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id 
								WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$collectionGalleries})
								AND {$dbinfo[pre]}media.active = 1 
								AND {$dbinfo[pre]}media.approval_status = 1 
								GROUP BY {$dbinfo[pre]}media.media_id
								ORDER BY {$dbinfo[pre]}media.date_added DESC
							"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
							*/
							$sql = 
							"
								SELECT SQL_CALC_FOUND_ROWS * 
								FROM {$dbinfo[pre]}media 
								WHERE {$dbinfo[pre]}media.active = 1 
								AND {$dbinfo[pre]}media.approval_status = 1 								
								AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$collectionGalleries}))
								ORDER BY {$dbinfo[pre]}media.date_added DESC
							"; // New 4.3.2
						}
						else
						{
							/*
							$sql = 
							"
								SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media 
								LEFT JOIN {$dbinfo[pre]}media_collections 
								ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_collections.cmedia_id
								WHERE {$dbinfo[pre]}media_collections.coll_id = '{$_SESSION[id]}'
								AND {$dbinfo[pre]}media.active = 1 
								AND {$dbinfo[pre]}media.approval_status = 1 
								GROUP BY {$dbinfo[pre]}media.media_id
								ORDER BY {$dbinfo[pre]}media.date_added DESC
							"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
							*/
							$sql = 
							"
								SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media 
								WHERE {$dbinfo[pre]}media.active = 1 
								AND {$dbinfo[pre]}media.approval_status = 1 
								AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(cmedia_id) FROM {$dbinfo[pre]}media_collections WHERE coll_id = '{$_SESSION[id]}')
								ORDER BY {$dbinfo[pre]}media.date_added DESC
							"; // New 4.3.2
						} // Get the total number of items
						
						// Set title and description meta tags
						$smarty->assign('metaTitle',($collectionArray['name']) ? $collectionArray['name'].' &ndash; '.$config['settings']['site_title'] : $config['settings']['site_title']); // Assign meta title
						$smarty->assign('metaDescription',($collectionArray['description']) ? $collectionArray['description'] : $config['settings']['site_description']); // Assign meta description
						
						//$mediaPages->setTotalResults($mediaCount); // Pass the total number of results to the $pages object
						$templateFile = 'view.collection.tpl';
					}
					else
					{
						$templateFile = 'noaccess.tpl'; // No permissions - send to noaccess page
					}
				}
				else
				{
					$templateFile = 'noaccess.tpl'; // No permissions - send to noaccess page
				}
			break;
			/*
			* Newest media gallery
			*/
			case "newest-media":
				unset($_SESSION['id']);
				
				// Update crumbs links
				unset($_SESSION['crumbsSession']);
				$newestMediaPageLink['page'] = "gallery.php?mode=newest-media&page=1";
				$crumbs[0]['linkto'] = linkto($newestMediaPageLink); // Check for SEO
				$crumbs[0]['name'] = $lang['newestMedia']; //				
				$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
				
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
					GROUP BY {$dbinfo[pre]}media.media_id
					ORDER BY {$dbinfo[pre]}media.date_added DESC
				"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
				*/
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media 
					WHERE {$dbinfo[pre]}media.active = 1 
					AND {$dbinfo[pre]}media.approval_status = 1 
					AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB})) 
					ORDER BY {$dbinfo[pre]}media.date_added DESC
				"; // New 4.3.2
				$templateFile = 'newest.media.tpl';
			break;
			/*
			* Popular media gallery
			*/
			case "popular-media":
				unset($_SESSION['id']);				
				// Update crumbs links
				unset($_SESSION['crumbsSession']);
				$popularMediaPageLink['page'] = "gallery.php?mode=popular-media&page=1";
				$crumbs[0]['linkto'] = linkto($popularMediaPageLink); // Check for SEO
				$crumbs[0]['name'] = $lang['popularMedia']; //				
				$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
				
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
					GROUP BY {$dbinfo[pre]}media.media_id
					ORDER BY {$dbinfo[pre]}media.views DESC
				";
				*/				
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media 
					WHERE {$dbinfo[pre]}media.active = 1 
					AND {$dbinfo[pre]}media.approval_status = 1 
					AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB})) 
					ORDER BY {$dbinfo[pre]}media.views DESC
				"; // New 4.3.2
				$templateFile = 'popular.media.tpl';
			break;
			/*
			* Popular media gallery
			*/
			case "featured-media":
				unset($_SESSION['id']);				
				
				// Update crumbs links
				unset($_SESSION['crumbsSession']);
				$featuredMediaPageLink['page'] = "gallery.php?mode=featured-media&page=1";
				$crumbs[0]['linkto'] = linkto($featuredMediaPageLink); // Check for SEO
				$crumbs[0]['name'] = $lang['featuredMedia']; //				
				$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
				
				/*
				$sql = 
				"
					SELECT *
					FROM {$dbinfo[pre]}media
					LEFT JOIN {$dbinfo[pre]}media_galleries 
					ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
					WHERE {$dbinfo[pre]}media.featured = 1
				";
				if($config['OverrideFMPerms'] == 0)	$sql .= " AND {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB})"; // Check for correct member permissions unless overridden by tweak: $config['OverrideFMPerms']
				$sql .= 
				" 
					GROUP BY {$dbinfo[pre]}media.media_id 
					ORDER BY {$dbinfo[pre]}media.views DESC 
				"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
				*/
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media 
					WHERE {$dbinfo[pre]}media.featured = 1
				"; // New 4.3.2
				if($config['OverrideFMPerms'] == 0)
					$sql .= " AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB}))"; // Check for correct member permissions unless overridden by tweak: $config['OverrideFMPerms']
				$sql .= " ORDER BY {$dbinfo[pre]}media.views DESC "; // New 4.3.2				
				
				$templateFile = 'featured.media.tpl';
			break;
			/*
			* Contributors media gallery
			*/
			case "contributor-media":
				/*
				* Select contributor details
				*/
				$contributorResult = mysqli_query($db,
					"
					SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}members 
					LEFT JOIN {$dbinfo[pre]}memberships 
					ON {$dbinfo[pre]}members.membership = {$dbinfo[pre]}memberships.ms_id 
					LEFT JOIN {$dbinfo[pre]}members_address
					ON {$dbinfo[pre]}members.mem_id = {$dbinfo[pre]}members_address.member_id
					WHERE {$dbinfo[pre]}members.mem_id = '{$_SESSION[id]}'
					"
				);
				if($contributorRows = getRows())
					$contributor = contrList(mysqli_fetch_array($contributorResult)); // Select the contributors details
				
				if($contributor['msfeatured'] == 1 and $contributor['status'] == 1 or ($contributor['showcase'] == 1 and $contributor['status'] == 1)) $publicAccess = true; // Make sure that the contributor can be displayed and is active
				
				if($publicAccess and $contributorRows) // Make sure that the contributor can be displayed and is active and that meta tags should be replaced
				{
					if($config['settings']['contr_metatags'])
					{
						define('META_TITLE',$contributor['f_name']." ".$contributor['l_name']); // Override page title, description, keywords and page encoding here
						define('META_DESCRIPTION',substr($contributor['bio_content'],0,200));
					}
					
					// Update crumbs links
					unset($_SESSION['crumbsSession']);
					$contrMediaPageLink['page'] = "gallery.php?mode=contributor-media&id={$contributor[useID]}&page=1";
					$crumbs[0]['linkto'] = linkto($contrMediaPageLink); // Check for SEO
					$crumbs[0]['name'] = $contributor['display_name']; //				
					$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
					
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
						AND {$dbinfo[pre]}media.owner = '{$_SESSION[id]}'
						GROUP BY {$dbinfo[pre]}media.media_id
						ORDER BY {$dbinfo[pre]}media.views DESC
					"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
					*/
					$sql = 
					"
						SELECT SQL_CALC_FOUND_ROWS *
						FROM {$dbinfo[pre]}media 
						WHERE {$dbinfo[pre]}media.active = 1 
						AND {$dbinfo[pre]}media.approval_status = 1 
						AND {$dbinfo[pre]}media.owner = '{$_SESSION[id]}' 						
						AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB}))						
						ORDER BY {$dbinfo[pre]}media.views DESC
					"; // New 4.3.2
					$smarty->assign("contributor",$contributor);
					$templateFile = 'contributor.media.tpl';
				}
				else
					$templateFile = 'noaccess.tpl';
			break;
			/*
			* Lightbox
			*/
			case "lightbox":
				$lightboxResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}lightboxes 
					WHERE ulightbox_id = '{$id}'
					"
				);
				if($returnRows = mysqli_num_rows($lightboxResult))
				{
					$lightbox = mysqli_fetch_array($lightboxResult);			
		
					$smarty->assign('lightboxRows',$returnRows);
					$smarty->assign('lightbox',$lightbox);
				}
				
				// Update crumbs links
				unset($_SESSION['crumbsSession']);
				$lightboxMediaPageLink['page'] = "gallery.php?mode=lightbox&page=1";
				$crumbs[0]['linkto'] = linkto($lightboxMediaPageLink); // Check for SEO
				$crumbs[0]['name'] = $lang['lightboxes']; //				
				$crumbs[1]['linkto'] = linkto($lightboxMediaPageLink); // Check for SEO
				$crumbs[1]['name'] = $lightbox['name']; //
				$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
				
				/*
				$sql = 
				"
					SELECT {$dbinfo[pre]}media.umedia_id
					FROM {$dbinfo[pre]}media
					LEFT JOIN {$dbinfo[pre]}lightbox_items  
					ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}lightbox_items.media_id
					WHERE {$dbinfo[pre]}lightbox_items.lb_id = '{$lightbox[lightbox_id]}'
					GROUP BY {$dbinfo[pre]}media.media_id
				";
				
				$mediaCount = mysqli_num_rows(mysqli_query($db,$sql)); // Get the total number of items
				$mediaPages->setTotalResults($mediaCount); // Pass the total number of results to the $pages object
				*/
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media
					LEFT JOIN {$dbinfo[pre]}lightbox_items  
					ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}lightbox_items.media_id
					WHERE {$dbinfo[pre]}lightbox_items.lb_id = '{$lightbox[lightbox_id]}'
					GROUP BY {$dbinfo[pre]}media.media_id 
				"; // LIMIT {$mediaStartRecord},{$mediaPerPage}
				
				// Set title and description meta tags
				$smarty->assign('metaTitle',($lightbox['name']) ? $lightbox['name'].' &ndash; '.$config['settings']['site_title'] : $config['settings']['site_title']); // Assign meta title
				$smarty->assign('metaDescription',($lightbox['description']) ? $lightbox['description'] : $config['settings']['site_description']); // Assign meta description
						
				$smarty->assign('lightboxPage',1);
				$templateFile = 'lightbox.media.tpl';
			break;
		}
		
		if($sql) // Only do the following if the gallery is other than the top level
		{
			
			/*
			* Previous and next button array
			*/
			if(!$_SESSION['prevNextArraySess']) // Only do this if it doesn't already exist
			{
				switch($mode)
				{
					case 'newest-media':
					case 'featured-media':
					case 'popular-media':
						$maxPrevNext = ' LIMIT '.($mediaPerPage * $config['specMediaPageLimit']); // case 'contributor-media':	
					break;
					default:
						$maxPrevNext = '';
					break;
				}
				
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
						if($returnRows > ($mediaPerPage * $config['specMediaPageLimit'])) $returnRows = $mediaPerPage * $config['specMediaPageLimit']; // Limit the results to 20 pages in certain areas //case 'contributor-media':	
					break;
				}
					
				//echo $returnRows; exit;
						
				$mediaPages->setTotalResults($returnRows); // Pass the total number of results to the $pages object
				
				$media->setGalleryDetails($galleryID,$_SESSION['currentMode']); // Pass gallery details to media class
				$media->addThumbDetails = true; // Get the thumb details as part of the array
				$media->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
				
				/*
					$sample = $mediaInfo2->getSampleInfoFromDB();	
					$sampleSize = getScaledSizeNoSource($sample['sample_width'],$sample['sample_height'],$config['settings']['preview_size'],$crop=0);				
					$media['previewWidth'] = $sampleSize[0];
					$media['previewHeight'] = $sampleSize[1];
				*/				
				
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
		
		$galleryPagingArray = $galleryPages->getPagingArray();
		$galleryPagingArray['pageNumbers'] = range(0,$galleryPagingArray['totalPages']);				
		unset($galleryPagingArray['pageNumbers'][0]); // Remove the 0 element from the beginning of the array
		$smarty->assign('galleryPaging',$galleryPagingArray);
		
		if($_SESSION['currentMode'] == 'lightbox' or $mode == 'lightbox')
			$smarty->assign('id',$_SESSION['id']);
		else
		{
			if($config['EncryptIDs'])
				$smarty->assign('id',k_encrypt($_SESSION['id'])); // Pass a gallery id or a contributor id encrypted
			else
				$smarty->assign('id',$_SESSION['id']); // Pass a gallery id or a contributor id
		}
		
		$smarty->assign('startrec',$mediaStartRecord);
		
		$smarty->assign('galleryID',$galleryID); // Gallery ID
		$smarty->assign('galleryMode',$_SESSION['currentMode']); // Gallery Mode

		
		$smarty->assign('selectedGallerySortBy',$_SESSION['sessGallerySortBy']); // Selected sort by
		$smarty->assign('selectedGallerySortType',$_SESSION['sessGallerySortType']); // Selected sort type
		$smarty->assign('gallerySortByOptions',$gallerySortByOptions); // Sorting by
		$smarty->assign('gallerySortByTypeOptions',$gallerySortByTypeOptions); // Sorting type
		
		$smarty->display($templateFile); // Display template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>