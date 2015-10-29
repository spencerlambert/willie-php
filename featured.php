<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','featured'); // Page ID
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
	
	if(preg_match("/[^A-Za-z0-9_-]/",$mode))
	{
		header("location: error.php?eType=invalidQuery");
		exit;
	}
	
	$_SESSION['backButtonSession']['linkto'] = pageLink(); // Update the back button link session
	
	// Assign proper meta titles
	switch($mode)
	{
		case 'prints':
			define('META_TITLE',$lang['featuredPrints'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
		case 'products':
			define('META_TITLE',$lang['featuredProducts'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
		case 'packages':
			define('META_TITLE',$lang['featuredPackages'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
		case 'collections':
			define('META_TITLE',$lang['featuredCollections'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
		case 'subscriptions':
			define('META_TITLE',$lang['featuredSubscriptions'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
		case 'credits':
			define('META_TITLE',$lang['featuredCredits'].' &ndash; '.$config['settings']['site_title']); // Override page title, description, keywords and page encoding here
		break;
	}
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	try
	{		
		switch($mode)
		{
			default:
				$templateFile = 'noaccess.tpl'; // No permissions - send to noaccess page
			break;
			/*
			* Featured Prints
			*/
			case "prints":
				$featuredPrintsResult = mysqli_query($db,
					"
					SELECT * 
					FROM {$dbinfo[pre]}prints
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}perms.item_id  AND {$dbinfo[pre]}perms.perm_area = 'prints')
					WHERE {$dbinfo[pre]}prints.active = 1 
					AND {$dbinfo[pre]}prints.featured = 1 
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
				if($returnRows = mysqli_fetch_row(mysqli_query($db,"SELECT FOUND_ROWS()"))) // mysqli_num_rows($featuredPrintsResult)
				{
					while($featuredPrints = mysqli_fetch_assoc($featuredPrintsResult))
						$featuredPrintsArray[] = printsList($featuredPrints);
					
					echo count($featuredPrintsArray);
					
					$smarty->assign('featuredPrintsRows',$returnRows);
					$smarty->assign('featuredPrints',$featuredPrintsArray);
				}
				*/
				$templateFile = 'featured.prints.tpl';
			break;
			/*
			* Featured Products
			*/
			case "products":
				$featuredProductsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}products
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}products.prod_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'products')
					WHERE {$dbinfo[pre]}products.active = 1 
					AND {$dbinfo[pre]}products.featured = 1 
					AND {$dbinfo[pre]}products.deleted = 0
					AND ({$dbinfo[pre]}products.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
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
				$templateFile = 'featured.products.tpl';
			break;
			/*
			* Featured Packages
			*/
			case "packages":
				$featuredPackagesResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}packages
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}packages.pack_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'packages')
					WHERE {$dbinfo[pre]}packages.active = 1 
					AND {$dbinfo[pre]}packages.featured = 1 
					AND {$dbinfo[pre]}packages.deleted = 0
					AND ({$dbinfo[pre]}packages.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
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
				$templateFile = 'featured.packages.tpl';
			break;
			case "collections":
				$featuredCollectionsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}collections 
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}collections.coll_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'collections')
					WHERE {$dbinfo[pre]}collections.active = 1 
					AND {$dbinfo[pre]}collections.featured = 1 
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
				$templateFile = 'featured.collections.tpl';
			break;
			case "subscriptions":
				$featuredSubscriptionsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}subscriptions 
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}subscriptions.sub_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'subscriptions')
					WHERE {$dbinfo[pre]}subscriptions.active = 1 
					AND {$dbinfo[pre]}subscriptions.featured = 1 
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
				$templateFile = 'featured.subscriptions.tpl';
			break;
			case "credits":
				$featuredCreditsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}credits  
					LEFT JOIN {$dbinfo[pre]}perms
					ON ({$dbinfo[pre]}credits.credit_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'credits')
					WHERE {$dbinfo[pre]}credits.active = 1 
					AND {$dbinfo[pre]}credits.featured = 1 
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
				$templateFile = 'featured.credits.tpl';
			break;
		}
		$smarty->display($templateFile); // Display template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>