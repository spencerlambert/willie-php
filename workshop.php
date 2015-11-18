<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','content'); // Page ID
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
	
	define('META_TITLE',"Workshop"); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	/*
	if($config['settings']['cache_pages'])
		$smarty->setCaching(Smarty::CACHING_LIFETIME_CURRENT); // Set this page to cache if it is turned on
	*/
	
	//print_k($activeLanguages[$selectedLanguage]);  // Testing
	
	//print_k($activeCurrencies);
	//print_k($displayCurrencies);
	

	/*
	* Get the products assigned to this gallery
	*/
	$productsResult = mysqli_query($db,"SELECT item_id FROM ps4_item_galleries WHERE gallery_id='16' AND mgrarea='products'");
	if($returnRows = mysqli_num_rows($productsResult))
	{
		while($products = mysqli_fetch_assoc($productsResult))
			$productsArray[] = $products['item_id'];
			foreach ($productsArray as $key => $value) {
				$prod_id = $productsArray[$key];
				//$productsDetails = mysqli_query($db,"SELECT * FROM ps4_products WHERE prod_id='$prod_id'");
				$productsDetails = mysqli_query($db,"SELECT * FROM 
																						`ps4_products`, `ps4_item_photos` 
																						WHERE `ps4_products`.`prod_id` = `ps4_item_photos`.`item_id` 
																						AND(`ps4_products`.`prod_id` = $prod_id AND `ps4_item_photos`.`mgrarea`='prod')");
				$productData[] = mysqli_fetch_assoc($productsDetails);
			}
		$smarty->assign('productRows',$returnRows);
		$smarty->assign('productsData1',$productData);
	}

	try
	{	
			
		$smarty->display('workshop.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
	
?>