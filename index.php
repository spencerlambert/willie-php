<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','homepage'); // Page ID
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
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	unset($_SESSION['currentMode']); // Unset the gallery mode

	$_SESSION['backButtonSession']['linkto'] = pageLink(); // Update the back button link session

	$thumbMediaDetailsArray = array();
	
	/*
	* Featured galleries
	*/
	if($_SESSION['galleriesData'])
	{
		foreach(@$_SESSION['galleriesData'] as $key => $value) // Find subgalleries
		{
			if($value['album'] == 0 and $value['feature'] == 1)
			{
				$subGalleriesData[$key] = $value['gallery_id'];							
				$_SESSION['galleriesData'][$key]['galleryIcon'] = galleryIcon($key); // Get gallery icon details for subs if they exist
			}
		}	
		if(count($subGalleriesData) > 0)
		{
			$smarty->assign('galleriesData',$_SESSION['galleriesData']);
			$smarty->assign('subGalleriesData',$subGalleriesData);	
		}
	}
		
	/*
	* Homepage news
	*/
	if($config['settings']['hpnews'])
	{
		try
		{
			$featuredNewsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}news
				WHERE homepage = 1
				AND active = 1
				AND add_date < '{$nowGMT}'
				AND (expire_type = 0 OR expire_date > '{$nowGMT}') 
				ORDER BY sortorder,add_date DESC
				"
			);
			$customNewsDate = new kdate;
			$customNewsDate->setMemberSpecificDateInfo();
			$customNewsDate->distime = 0;
			while($featuredNews = mysqli_fetch_assoc($featuredNewsResult))
			{
				$featuredNews['title'] = ($featuredNews['title_'.$selectedLanguage]) ? $featuredNews['title_'.$selectedLanguage] : $featuredNews['title']; // Choose the correct language
				$featuredNews['short'] = ($featuredNews['short_'.$selectedLanguage]) ? $featuredNews['short_'.$selectedLanguage] : $featuredNews['short']; // Choose the correct language
				$featuredNews['seoTitle'] = cleanForSEO($featuredNews['title']); // Name cleaned for SEO usage
				
				$parms['page'] = "news.php?id={$featuredNews[news_id]}"; // Link to page
				if($modRewrite) $parms['page'].="&seoTitle={$featuredNews[seoTitle]}"; // Link to page with seoName added
				$featuredNews['linkto'] = linkto($parms); // Create the link using SEO if needed
				
				$featuredNews['display_date'] = $customNewsDate->showdate($featuredNews['add_date']); // Create a local time and date
				$featuredNewsArray[] = $featuredNews;
			}
			$smarty->assign('featuredNewsRows',count($featuredNewsArray));
			$smarty->assign('featuredNews',$featuredNewsArray);
		}
		catch(Exception $e)
		{
			die(exceptionError($e));
		}
	}

	/*
	* Get featured homepage prints
	*/
	if($config['settings']['hpprints'])
	{
		try
		{
			$featuredPrintsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}prints
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'prints') 
				WHERE {$dbinfo[pre]}prints.active = 1 
				AND {$dbinfo[pre]}prints.homepage = 1 
				AND {$dbinfo[pre]}prints.deleted = 0
				AND ({$dbinfo[pre]}prints.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				ORDER BY {$dbinfo[pre]}prints.sortorder
				"
			);
			while($featuredPrints = mysqli_fetch_assoc($featuredPrintsResult))
				$featuredPrintsArray[] = printsList($featuredPrints);
					
			$smarty->assign('featuredPrintsRows',count($featuredPrintsArray));
			$smarty->assign('featuredPrints',$featuredPrintsArray);
			/*
			if($returnRows = mysqli_num_rows($featuredPrintsResult))
			{
				while($featuredPrints = mysqli_fetch_assoc($featuredPrintsResult))
					$featuredPrintsArray[] = printsList($featuredPrints);
					
				$smarty->assign('featuredPrintsRows',$returnRows);
				$smarty->assign('featuredPrints',$featuredPrintsArray);
			}
			*/
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	/*
	* Get featured homepage products
	*/
	if($config['settings']['hpprods'])
	{
		try
		{
			$featuredProductsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}products
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'products') 
				WHERE {$dbinfo[pre]}products.active = 1 
				AND {$dbinfo[pre]}products.homepage = 1 
				AND {$dbinfo[pre]}products.deleted = 0
				AND ({$dbinfo[pre]}products.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				AND ({$dbinfo[pre]}products.quantity = '' OR {$dbinfo[pre]}products.quantity > '0' OR {$dbinfo[pre]}products.product_type = 1)
				ORDER BY {$dbinfo[pre]}products.sortorder
				"
			);
			while($featuredProducts = mysqli_fetch_assoc($featuredProductsResult))
				$featuredProductsArray[] = productsList($featuredProducts);

			$smarty->assign('featuredProductsRows',count($featuredProductsArray));
			$smarty->assign('featuredProducts',$featuredProductsArray);
			/*
			if($returnRows = mysqli_num_rows($featuredProductsResult))
			{
				while($featuredProducts = mysqli_fetch_assoc($featuredProductsResult))
					$featuredProductsArray[] = productsList($featuredProducts);

				$smarty->assign('featuredProductsRows',$returnRows);
				$smarty->assign('featuredProducts',$featuredProductsArray);
			}
			*/
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	/*
	* Get featured homepage packages
	*/
	if($config['settings']['hppacks'])
	{
		try
		{
			$featuredPackagesResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}packages
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}packages.pack_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'packages') 
				WHERE {$dbinfo[pre]}packages.active = 1 
				AND {$dbinfo[pre]}packages.homepage = 1 
				AND {$dbinfo[pre]}packages.deleted = 0
				AND ({$dbinfo[pre]}packages.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				AND ({$dbinfo[pre]}packages.quantity = '' OR {$dbinfo[pre]}packages.quantity > '0')
				ORDER BY {$dbinfo[pre]}packages.sortorder
				"
			);
			while($featuredPackages = mysqli_fetch_assoc($featuredPackagesResult))
				$featuredPackagesArray[] = packagesList($featuredPackages);

			$smarty->assign('featuredPackagesRows',count($featuredPackagesArray));
			$smarty->assign('featuredPackages',$featuredPackagesArray);
			/*
			if($returnRows = mysqli_num_rows($featuredPackagesResult))
			{
				while($featuredPackages = mysqli_fetch_assoc($featuredPackagesResult))
					$featuredPackagesArray[] = packagesList($featuredPackages);

				$smarty->assign('featuredPackagesRows',$returnRows);
				$smarty->assign('featuredPackages',$featuredPackagesArray);
			}
			*/
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	/*
	* Get featured homepage collections
	*/	
	if($config['settings']['hpcolls'])
	{
		try
		{
			$featuredCollectionsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}collections 
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}collections.coll_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'collections') 
				WHERE {$dbinfo[pre]}collections.active = 1 
				AND {$dbinfo[pre]}collections.homepage = 1 
				AND {$dbinfo[pre]}collections.deleted = 0
				AND ({$dbinfo[pre]}collections.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				AND ({$dbinfo[pre]}collections.quantity = '' OR {$dbinfo[pre]}collections.quantity > '0')
				ORDER BY {$dbinfo[pre]}collections.sortorder
				"
			);
			while($featuredCollections = mysqli_fetch_assoc($featuredCollectionsResult))
				$featuredCollectionsArray[] = collectionsList($featuredCollections);

			$smarty->assign('featuredCollectionsRows',count($featuredCollectionsArray));
			$smarty->assign('featuredCollections',$featuredCollectionsArray);
			/*
			if($returnRows = mysqli_num_rows($featuredCollectionsResult))
			{
				while($featuredCollections = mysqli_fetch_assoc($featuredCollectionsResult))
					$featuredCollectionsArray[] = collectionsList($featuredCollections);

				$smarty->assign('featuredCollectionsRows',$returnRows);
				$smarty->assign('featuredCollections',$featuredCollectionsArray);
			}
			*/
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}

	/*
	* Featured media
	*/
	if($config['settings']['hpfeaturedmedia'])
	{
		try
		{
			/*
			$sql = 
				"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}media
				LEFT JOIN {$dbinfo[pre]}media_galleries 
				ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id
				WHERE {$dbinfo[pre]}media.featured = 1 
				AND {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1 
				";
			if($config['OverrideFMPerms'] == 0)
				$sql .= " AND {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB})"; // Check for correct member permissions unless overridden by tweak: $config['OverrideFMPerms']
			$sql .= " GROUP BY {$dbinfo[pre]}media.media_id ORDER BY RAND()";
			*/
			
			$sql = 
			"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}media 
				WHERE {$dbinfo[pre]}media.featured = 1 
				AND {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1 
			"; // New 4.3.2
			if($config['OverrideFMPerms'] == 0)
				$sql .= " AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB}))"; // Check for correct member permissions unless overridden by tweak: $config['OverrideFMPerms']
			$sql .= " ORDER BY RAND()";
			
			$featuredMedia = new mediaList($sql); // Create a new mediaList object
			
			//echo $featuredMedia->getRows(); exit;
			
			if($returnRows = $featuredMedia->getRows()) // Continue only if results are found
			{	
				$featuredMedia->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
				$featuredMedia->addThumbDetails = true; // Get the thumb details as part of the array
				$featuredMediaArray = $featuredMedia->getMediaArray(); // Get the array of featured media
				
				$featuredMediaDetailsFields  = $featuredMedia->getDetailsFields('thumb');
							
				if($featuredMediaDetailsFields) // Make sure they both exist before trying to combine them //$thumbMediaDetailsArray and 
					$thumbMediaDetailsArray = $thumbMediaDetailsArray + $featuredMediaDetailsFields; // Get the output for the details shown under thumbnails // Old getThumbMediaDetailsArray()
				
				//echo 'tda: '.$thumbMediaDetailsArray; // Testing
				//echo '<br>fa: '.$featuredMedia->getDetailsFields('thumb');
				//exit;
				
				foreach($featuredMediaArray as $key => $value) // Force title and description for featured
				{
					if($value['dsp_type'] == 'video')
					{
						// check for video file xxxxxxxxxxxxxx
						$featuredMediaArray[$key]['sampleVideo'] = 1;
					}
					else
						$featuredMediaArray[$key]['sampleVideo'] = 0;
						
					$featuredMediaArray[$key]['title'] = getMediaDetails('title',$featuredMediaArray[$key]);
					$featuredMediaArray[$key]['description'] = getMediaDetails('description',$featuredMediaArray[$key]);
				}
				
				$smarty->assign('featuredMediaRows',$returnRows); // Assign to smarty
				$smarty->assign('featuredMedia',$featuredMediaArray); // Assign to smarty
				$featuredOne = array_rand($featuredMediaArray); // Choose 1 random featured media from the array
				$smarty->assign('featuredOne',$featuredOne);
			}
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
		
	/*
	* Newest media
	*/
	if($config['settings']['hpnewestmedia'] and $config['settings']['new_media_count'] > 0)
	{
		try
		{
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
				LIMIT {$config[settings][new_media_count]}
				";
			*/
			$sql = 
			"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}media 
				WHERE {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1 
				AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB})) 
				ORDER BY {$dbinfo[pre]}media.date_added DESC
				LIMIT {$config[settings][new_media_count]}
			"; // New 4.3.2
			$newestMedia = new mediaList($sql); // Create a new mediaList object
			if($returnRows = $newestMedia->getRows()) // Continue only if results are found
			{	
				//echo $returnRows; exit; // Testing
				$newestMedia->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
				$newestMedia->addThumbDetails = true; // Get the thumb details as part of the array
				$newestMediaArray = $newestMedia->getMediaArray(); // Get the array of newest media
				
				$newestMediaDetailsFields = $newestMedia->getDetailsFields('thumb'); 
				if($newestMediaDetailsFields)
					$thumbMediaDetailsArray = $thumbMediaDetailsArray + $newestMediaDetailsFields; // Get the output for the details shown under thumbnails
			
				$smarty->assign('newestMediaRows',$returnRows); // Assign to smarty
				$smarty->assign('newestMedia',$newestMediaArray); // Assign to smarty
			}
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	/*
	* Random media
	*/
	if($config['settings']['hprandmedia'] and $config['settings']['random_media_count'] > 0)
	{
		try
		{
			/*
			$sql = 
			"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}media 
				WHERE {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1 				
				AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB})) 				
				ORDER BY RAND()
				LIMIT {$config[settings][random_media_count]}
			";
			*/
			
			/* START: New random function for home page random media - added in 4.6 */
			$sql = 
			"
				SELECT SQL_CALC_FOUND_ROWS media_id
				FROM {$dbinfo[pre]}media 
				WHERE {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1 				
				AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB})) 				
			";
			$tempResult = mysqli_query($db,$sql);
			while($tempMedia = mysqli_fetch_array($tempResult))
				$tempArray[$tempMedia['media_id']] = $tempMedia['media_id']; // Temporary Array

			if($returnedTempRows = getRows())
			{
				if($config['settings']['random_media_count'] > $returnedTempRows)
					$fetchAmount = $returnedTempRows;
				else
					$fetchAmount = $config['settings']['random_media_count'];				
	
				shuffle($tempArray);			
				$randIDs = array_slice($tempArray,0,$fetchAmount);
				$flatRandIDs = implode(',',$randIDs);
			}
			else
				$flatRandIDs = '0';
			
			$sql = 
			"
				SELECT SQL_CALC_FOUND_ROWS *
				FROM {$dbinfo[pre]}media 
				WHERE {$dbinfo[pre]}media.active = 1 
				AND {$dbinfo[pre]}media.approval_status = 1  
				AND {$dbinfo[pre]}media.media_id IN ({$flatRandIDs})	
				ORDER BY RAND()
				LIMIT {$config[settings][random_media_count]}
			";
			/* END: New random function for home page random media - added in 4.6 */
			
			$randomMedia = new mediaList($sql); // Create a new mediaList object
			if($returnRows = $randomMedia->getRows()) // Continue only if results are found
			{	
				$randomMedia->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
				$randomMedia->addThumbDetails = true; // Get the thumb details as part of the array
				$randomMediaArray = $randomMedia->getMediaArray(); // Get the array of random media				
				$randomMediaDetailsFields = $randomMedia->getDetailsFields('thumb');
				if($randomMediaDetailsFields)
					$thumbMediaDetailsArray = $thumbMediaDetailsArray + $randomMediaDetailsFields; // Get the output for the details shown under thumbnails
				$smarty->assign('randomMediaRows',$returnRows); // Assign to smarty
				$smarty->assign('randomMedia',$randomMediaArray); // Assign to smarty
			}
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	/*
	* Popular media
	*/
	if($config['settings']['hppopularmedia'] and $config['settings']['popular_media_count'] > 0)
	{
		try
		{
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
				LIMIT {$config[settings][popular_media_count]}
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
				LIMIT {$config[settings][popular_media_count]}
			"; // New 4.3.2
			$popularMedia = new mediaList($sql); // Create a new mediaList object
			if($returnRows = $popularMedia->getRows()) // Continue only if results are found
			{
				//echo "test: ".$returnRows; exit;
				
				$popularMedia->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
				$popularMedia->addThumbDetails = true; // Get the thumb details as part of the array
				$popularMediaArray = $popularMedia->getMediaArray(); // Get the array of popular media
				
				$popularMediaDetailsFields = $popularMedia->getDetailsFields('thumb');
				if($popularMediaDetailsFields)
					$thumbMediaDetailsArray = $thumbMediaDetailsArray + $popularMediaDetailsFields; // Get the output for the details shown under thumbnails
				$smarty->assign('popularMediaRows',$returnRows); // Assign to smarty
				$smarty->assign('popularMedia',$popularMediaArray); // Assign to smarty
			}
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	//print_r($featuredMediaDetailsArray);
	//$thumbMediaDetailsArray = array_merge($featuredMediaDetailsArray,$newestMediaDetailsArray,$randomMediaDetailsArray,$popularMediaDetailsArray);
	
	/*
	* Get featured homepage promotions
	*/
	if($config['settings']['hppromos'])
	{
		try
		{
			$featuredPromotionsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}promotions 
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}promotions.promo_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'promotions') 
				WHERE {$dbinfo[pre]}promotions.active = 1 
				AND {$dbinfo[pre]}promotions.homepage = 1 
				AND {$dbinfo[pre]}promotions.deleted = 0
				AND ({$dbinfo[pre]}promotions.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				AND ({$dbinfo[pre]}promotions.quantity = '' OR {$dbinfo[pre]}promotions.quantity > '0')
				ORDER BY {$dbinfo[pre]}promotions.sortorder
				"
			);
			while($featuredPromotions = mysqli_fetch_assoc($featuredPromotionsResult))
				$featuredPromotionsArray[] = promotionsList($featuredPromotions);

			$smarty->assign('featuredPromotionsRows',count($featuredPromotionsArray));
			$smarty->assign('featuredPromotions',$featuredPromotionsArray);
			/*
			if($returnRows = mysqli_num_rows($featuredPromotionsResult))
			{
				while($featuredPromotions = mysqli_fetch_assoc($featuredPromotionsResult))
					$featuredPromotionsArray[] = promotionsList($featuredPromotions);

				$smarty->assign('featuredPromotionsRows',$returnRows);
				$smarty->assign('featuredPromotions',$featuredPromotionsArray);
			}
			*/
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	/*
	* Get featured homepage subscriptions
	*/
	if($config['settings']['hpsubs'] and $config['settings']['subscriptions'])
	{
		try
		{
			$featuredSubscriptionsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}subscriptions 
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}subscriptions.sub_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'subscriptions') 
				WHERE {$dbinfo[pre]}subscriptions.active = 1 
				AND {$dbinfo[pre]}subscriptions.homepage = 1 
				AND {$dbinfo[pre]}subscriptions.deleted = 0
				AND ({$dbinfo[pre]}subscriptions.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				ORDER BY {$dbinfo[pre]}subscriptions.sortorder
				"
			);
			while($featuredSubscriptions = mysqli_fetch_assoc($featuredSubscriptionsResult))
				$featuredSubscriptionsArray[] = subscriptionsList($featuredSubscriptions);

			$smarty->assign('featuredSubscriptionsRows',count($featuredSubscriptionsArray));
			$smarty->assign('featuredSubscriptions',$featuredSubscriptionsArray);
			/*
			if($returnRows = mysqli_num_rows($featuredSubscriptionsResult))
			{
				while($featuredSubscriptions = mysqli_fetch_assoc($featuredSubscriptionsResult))
					$featuredSubscriptionsArray[] = subscriptionsList($featuredSubscriptions);

				$smarty->assign('featuredSubscriptionsRows',$returnRows);
				$smarty->assign('featuredSubscriptions',$featuredSubscriptionsArray);
			}
			*/
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	/*
	* Get featured homepage credits
	*/
	if($config['settings']['hpcredits'])
	{
		try
		{
			$featuredCreditsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}credits  
				LEFT JOIN {$dbinfo[pre]}perms
				ON ({$dbinfo[pre]}credits.credit_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'credits') 
				WHERE {$dbinfo[pre]}credits.active = 1 
				AND {$dbinfo[pre]}credits.homepage = 1 
				AND {$dbinfo[pre]}credits.deleted = 0
				AND ({$dbinfo[pre]}credits.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
				ORDER BY {$dbinfo[pre]}credits.sortorder
				"
			);
			while($featuredCredits = mysqli_fetch_assoc($featuredCreditsResult))
				$featuredCreditsArray[] = creditsList($featuredCredits);

			$smarty->assign('featuredCreditsRows',count($featuredCreditsArray));
			$smarty->assign('featuredCredits',$featuredCreditsArray);
			/*
			if($returnRows = mysqli_num_rows($featuredCreditsResult))
			{
				while($featuredCredits = mysqli_fetch_assoc($featuredCreditsResult))
					$featuredCreditsArray[] = creditsList($featuredCredits);

				$smarty->assign('featuredCreditsRows',$returnRows);
				$smarty->assign('featuredCredits',$featuredCreditsArray);
			}
			*/
		}
		catch(Exception $e)
		{
			die(exceptionError($e));	
		}
	}
	
	try
	{	
		//print_r($thumbMediaDetailsArray);
		//$smarty->assign('rolloverMediaDetails',$rolloverMediaDetailsArray);
		$smarty->assign('thumbMediaDetails',$thumbMediaDetailsArray);	
		$smarty->display('index.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		die(exceptionError($e));
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>