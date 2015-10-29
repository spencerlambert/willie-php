<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','bills'); // Page ID
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
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	try
	{
		$memberID = $_SESSION['member']['mem_id'];	
		if(!$memberID) die('No member ID exists'); // Just to be safe make sure a memberID exists before continuing
		
		$billTotal = new number_formatting; // Used to make sure the bills are showing in the admins currency
		$billTotal->set_custom_cur_defaults($config['settings']['defaultcur']);
		$parms['noDefault'] = true;
		
		$billResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}billings
			LEFT JOIN {$dbinfo[pre]}invoices 
			ON {$dbinfo[pre]}billings.bill_id = {$dbinfo[pre]}invoices.bill_id
			WHERE {$dbinfo[pre]}billings.member_id = {$memberID}
			AND {$dbinfo[pre]}billings.deleted = 0
			ORDER BY {$dbinfo[pre]}invoices.invoice_date DESC
			"
		);
		if($returnRows = mysqli_num_rows($billResult))
		{	
			while($bill = mysqli_fetch_assoc($billResult))
			{
				$billsArray[$bill['bill_id']] = $bill;
				
				$billsArray[$bill['bill_id']]['invoice_date_display'] = $customDate->showdate($bill['invoice_date'],0);
				$billsArray[$bill['bill_id']]['due_date_display'] = $customDate->showdate($bill['due_date'],0);				
				
				if($bill['payment_status'] != 2) // Other than unpaid - make sure the correct amount is shown - the amount should be shown in the admins currency
				{
					$cleanTotal['display'] = $billTotal->currency_display($bill['total'],1);
					$cleanTotal['raw'] = round($price,2);
					$billsArray[$bill['bill_id']]['total'] = $cleanTotal;
				}
				else
					$billsArray[$bill['bill_id']]['total'] = getCorrectedPrice($bill['total'],$parms); // Unpaid invoice can show in the members currency
				
				$billsArray[$bill['bill_id']]['payment_status_lang'] = billStatusNumToText($bill['payment_status']);
				
				if($nowGMT > $bill['due_date'] and ($bill['payment_status'] == 2 or $bill['payment_status'] == 4))
					$billsArray[$bill['bill_id']]['past_due'] = true; // See if the bill is past due
			}
			
			$smarty->assign('notice',$notice);
			$smarty->assign('billsArray',$billsArray);
			$smarty->assign('billRows',$returnRows);
		}
		
		$smarty->display('bills.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>