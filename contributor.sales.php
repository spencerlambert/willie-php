<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','contributorSales'); // Page ID
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
	
	$memberID = $_SESSION['member']['mem_id'];
	if(!$memberID) die('No member ID exists'); // Just to be safe make sure a memberID exists before continuing
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/init.contributor.php';
	require_once BASE_PATH.'/assets/includes/errors.php';	
	require_once BASE_PATH.'/assets/classes/mediatools.php';

	try
	{		
		$runningPaidTotal = 0;
		$runningUnpaidTotal = 0;
		
		$saleCur = new number_formatting; // Used to make sure the bills are showing in the admins currency
		$saleCur->set_custom_cur_defaults($config['settings']['defaultcur']);
		$parms['noDefault'] = true;
		
		$contrSalesResult = mysqli_query($db,
		"
			SELECT * FROM {$dbinfo[pre]}commission 
			LEFT JOIN {$dbinfo[pre]}invoice_items 
			ON {$dbinfo[pre]}commission.oitem_id = {$dbinfo[pre]}invoice_items.oi_id  
			WHERE {$dbinfo[pre]}commission.contr_id = '{$_SESSION[member][mem_id]}' 
			AND {$dbinfo[pre]}commission.order_status = '1'  
			ORDER BY {$dbinfo[pre]}commission.com_id DESC
		");
		if($saleRows = mysqli_num_rows($contrSalesResult))
		{
			while($contrSales = mysqli_fetch_assoc($contrSalesResult))
			{
				if($contrSales['omedia_id'])
				{
					/*
					$media = new mediaTools($contrSales['omedia_id']);
					$mediaInfo = $media->getMediaInfoFromDB();
					$thumbInfo = $media->getIconInfoFromDB();										
					$verify = $media->verifyMediaSubFileExists('icons');										
					$mediaStatus = $verify['status'];
					*/
					
					$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$contrSales[omedia_id]}'";
					$mediaObj = new mediaList($sql); // Create a new mediaList object
					if($returnMediaRows = $mediaObj->getRows())
					{
						$contrSales['media'] = $mediaObj->getSingleMediaDetails();
					}
				}
				
				//print_r($contrSales['media']);	
					
				switch($contrSales['comtype']) // Type of purchase or download
				{
					default:
					case "cur": // Currency based payment
						$total = ($contrSales['com_total']*$contrSales['item_qty']);
						
						if($contrSales['item_percent'] == 0) // Change a 0 to a 100%
							$contrSales['item_percent'] = 100;
						
						$itemCommission = round(($total*($contrSales['item_percent']/100)*($contrSales['mem_percent']/100)),2);								
					break;
					case "cred": // Credit based commission
						$itemCommission = round(($contrSales['com_credits']*$contrSales['item_qty'])*$contrSales['per_credit_value'],2);
					break;
					case "sub": // Subscription download commission
						$itemCommission = $contrSales['com_total'];
					break;	
				}
								
				if($contrSales['item_type'] != 'digital')
				{
					if($contrSales['item_type'] == 'print')
					{								
						$printResult = mysqli_query($db,"SELECT item_name,print_id FROM {$dbinfo[pre]}prints WHERE print_id = '{$contrSales[item_id]}'");
						$print = mysqli_fetch_assoc($printResult);
						$contrSales['itemName'] = $print['item_name'];
					}
					if($contrSales['item_type'] == 'product')
					{								
						$prodResult = mysqli_query($db,"SELECT item_name,prod_id FROM {$dbinfo[pre]}products WHERE prod_id = '{$contrSales[item_id]}'");
						$prod = mysqli_fetch_assoc($prodResult);
						$contrSales['itemName'] = $prod['item_name'];
					}
				}
				
				if($contrSales['item_type'] == 'digital')
				{
					if($contrSales['item_id'])
					{
						$dspResult = mysqli_query($db,"SELECT name,ds_id FROM {$dbinfo[pre]}digital_sizes WHERE ds_id = '{$contrSales[item_id]}'");
						$dsp = mysqli_fetch_assoc($dspResult);
						$contrSales['itemName'] = $dsp['name'];
					}
					else
						$contrSales['itemName'] = 'orig';
				}

				switch($contrSales['compay_status']) // Payment status language to use
				{
					case 0: // Unpaid
						$textMatch = 'unpaid';
					break;
					case 1: // PAID/APPROVED
						$textMatch = 'paid';
					break;
				}
				
				$contrSales['statusDisplayLang'] = $textMatch;
				
				$contrSales['commissionDisplay'] = $saleCur->currency_display($itemCommission,1);
				$contrSales['orderDateDisplay'] = $customDate->showdate($contrSales['order_date'],0);
				
				if($contrSales['compay_status'] == 1)
					$runningPaidTotal+= $itemCommission;
				else
					$runningUnpaidTotal+= $itemCommission;

					
				$salesArray[$contrSales['com_id']] = $contrSales;
			}
		}
		
		$runningPaidTotalDisplay = $saleCur->currency_display($runningPaidTotal,1);
		$runningUnpaidTotalDisplay = $saleCur->currency_display($runningUnpaidTotal,1);
		
		$smarty->assign('runningPaidTotalDisplay',$runningPaidTotalDisplay);
		$smarty->assign('runningUnpaidTotalDisplay',$runningUnpaidTotalDisplay);
		
		//print_r($salesArray);
		
		$smarty->assign('salesArray',$salesArray);
		$smarty->assign('saleRows',$saleRows);
		$smarty->display('contributor.sales.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>