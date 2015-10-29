<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','print'); // Page ID
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
	
	$useMediaID = $mediaID;
	
	try
	{	
		if($config['EncryptIDs']) // Decrypt IDs
		{
			$id = k_decrypt($id);
			$mediaID = k_decrypt($mediaID);
		}
				
		if($id) idCheck($id); // Make sure ID is numeric
		if($mediaID) idCheck($mediaID); // Make sure ID is numeric
		
		$printResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}prints
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}prints.print_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'prints') 
			WHERE {$dbinfo[pre]}prints.print_id = {$id}
			AND ({$dbinfo[pre]}prints.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB}))
			"
		);
		if($returnRows = mysqli_num_rows($printResult))
		{	
			$print = mysqli_fetch_array($printResult);
			
			if($print['active'] == 1 and $print['deleted'] == 0)
			{
				
				/*
				* Get discounts
				*/
				$discountsResult = mysqli_query($db,
					"
					SELECT *
					FROM {$dbinfo[pre]}discount_ranges 
					WHERE item_type = 'prints' 
					AND start_discount_number > 0
					AND item_id = '{$id}' 
					ORDER BY start_discount_number
					"
				);
				if($discountReturnRows = mysqli_num_rows($discountsResult))
				{	
					while($discount = mysqli_fetch_array($discountsResult))
					{
						$discountsArray[$discount['dr_id']] = $discount;
					}
					$smarty->assign('discountRows',$discountReturnRows);
					$smarty->assign('discountsArray',$discountsArray);
				}
				
				if($edit) // We are editing this item
				{
					$smarty->assign('edit',k_encrypt($edit));
				
					$invoiceOptionsResult = mysqli_query($db,
						"
						SELECT *
						FROM {$dbinfo[pre]}invoice_options 
						WHERE invoice_item_id IN ({$edit})
						"
					);
					if($invoiceOptionsRows = mysqli_num_rows($invoiceOptionsResult))
					{
						while($invoiceOption = mysqli_fetch_array($invoiceOptionsResult))
							$optionSelections[$invoiceOption['option_gid'].'-'.$invoiceOption['option_id']] = true;
					}
				}
				
				//print_r($optionSelections);
				
				if($mediaID) // Building off of a media file and pricing
				{
					// select the media details
					$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
					$mediaInfo = new mediaList($sql);
					$media = $mediaInfo->getSingleMediaDetails('preview');
					
					$mediaPrice = getMediaPrice($media); // Get the media price based on the license
					$mediaCredits = getMediaCredits($media); // Get the media credits based on the license
					
					$print['price'] = defaultPrice($print['price']); // Make sure to assign a default price if needed
					$print['credits'] = defaultCredits($print['credits']); // Make sure to assign default credits if needed
					
					/*
					* Custom Pricing calculations
					*/
					$mediaPrintsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_prints WHERE media_id = '{$mediaID}' AND print_id = '{$id}'"); // Find if this has a customization
					if(mysqli_num_rows($mediaPrintsResult))
					{
						$mediaPrint = mysqli_fetch_array($mediaPrintsResult);
						
						if($mediaPrint['customized']) // See if this entry was customized
						{
							$print['price_calc'] = $mediaPrint['price_calc'];
							$print['price'] = defaultPrice($mediaPrint['price']);
							$print['credits'] = defaultCredits($mediaPrint['credits']);
							$print['credits_calc'] = $mediaPrint['credits_calc'];							
							$print['quantity'] = $mediaPrint['quantity']; 	
						}
					}
					
					/*
					* Advanced Pricing calculations
					*/
					switch($print['price_calc'])
					{
						case 'add':
							$print['price'] = $mediaPrice + $print['price'];
						break;
						case 'sub':
							$print['price'] = $mediaPrice - $print['price'];
						break;
						case 'mult':
							$print['price'] = $mediaPrice * $print['price'];
						break;
					}
					
					switch($print['credits_calc'])
					{
						case 'add':
							$print['credits'] = $mediaCredits + $print['credits'];
						break;
						case 'sub':
							$print['credits'] = $mediaCredits - $print['credits'];
						break;
						case 'mult':
							$print['credits'] = $mediaCredits * $print['credits'];
						break;
					}
					
					$smarty->assign('mediaID',$mediaID);
					$smarty->assign('media',$media);
				}
				
				$printArray = printsList($print);
				
				$printArray['options'] = getProductOptions('prints',$printArray['print_id'],$print['taxable']);
				
				/*
				* If editing this then select the correctly selected items
				*/
				if($edit)
				{
					if($printArray['options'])
					{
						foreach($printArray['options'] as $key => $value)
						{
							foreach($printArray['options'][$key]['options'] as $key2 => $value2)
							{	
								if($optionSelections[$key.'-'.$key2])
									$printArray['options'][$key]['options'][$key2]['selected'] = true; // Set selected option to true
							}
						}
					}
				}
				
				$smarty->assign('useMediaID',$useMediaID);
				$smarty->assign('print',$printArray);
				$smarty->assign('printRows',$returnRows);
			}
			else
				$smarty->assign('noAccess',1);
		}
		else
			$smarty->assign('noAccess',1);
			
		$smarty->display('print.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>