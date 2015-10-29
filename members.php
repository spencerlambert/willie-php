<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','members'); // Page ID
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

	$memberID = $_SESSION['member']['mem_id'];	
	if(!$memberID) die('No member ID exists'); // Just to be safe make sure a memberID exists before continuing

	if($ticketSystem)
	{
		$tickets = mysqli_result_patch(mysqli_query($db,
			"
			SELECT COUNT(ticket_id) 
			FROM {$dbinfo[pre]}tickets 
			WHERE member_id = {$memberID}
			AND viewed = 0 
			"
		)); // Count new messages
	}
	
	$bills = mysqli_result_patch(mysqli_query($db,
		"
		SELECT COUNT({$dbinfo[pre]}billings.bill_id) 
		FROM {$dbinfo[pre]}billings 
		LEFT JOIN {$dbinfo[pre]}invoices 
		ON {$dbinfo[pre]}billings.bill_id = {$dbinfo[pre]}invoices.bill_id 
		WHERE {$dbinfo[pre]}invoices.payment_status = 2 
		AND {$dbinfo[pre]}billings.deleted = 0
		AND member_id = {$memberID}
		"
	)); // Count bills due
	
	$sales = mysqli_result_patch(mysqli_query($db,
		"
		SELECT COUNT(com_id) 
		FROM {$dbinfo[pre]}commission 
		WHERE contr_id = {$memberID} 
		AND order_status = '1' 
		AND order_date > '{$_SESSION[member][last_login]}'
		"
	)); // Count new sales
	
	// Get membership that member is currently on
	
	// Get membership that member is assigned to in DB
	
	$membership['expires'] = '';
	
	/*
	* Get membership details
	*/
	if($_SESSION['member']['membership'] != 1)
	{
		//echo $_SESSION['member']['membershipDetails']['ms_id']; exit;
		
		$membershipResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}memberships
			WHERE ms_id = '{$_SESSION[member][membership]}'
			"
		);
		$membershipDB = mysqli_fetch_array($membershipResult);
		$membership = membershipsList($membershipDB);

		$membership['msExpired'] = ($_SESSION['member']['ms_end_date'] > $nowGMT or $_SESSION['member']['ms_end_date'] == '0000-00-00 00:00:00') ? false : true;
		$membership['msExpireDate'] = ($_SESSION['member']['ms_end_date'] == '0000-00-00 00:00:00') ? $lang['never'] : $customDate->showdate($_SESSION['member']['ms_end_date'],1);
		
		$smarty->assign('membership',$membership);
	}
	
	// Check galleries for member
	if($_SESSION['galleriesData'])
	{
		foreach($_SESSION['galleriesData'] as $key => $gallery) // Find galleries with permissions specific to the member
		{	
			if($gallery['memSpec'] == $_SESSION['member']['mem_id'])
				$memberSpecGallery[$gallery['gallery_id']] = $_SESSION['galleriesData'][$gallery['gallery_id']];
		}
	}
	
	//print_r($memberSpecGallery); // Testing
	
	try
	{
		//$signupDate = $cleanDates->showdate($_SESSION['member']['signup_date'],0);
		$lastLoginDisplay = ($_SESSION['member']['last_login'] == "0000-00-00 00:00:00") ? $lang['never'] : $customDate->showdate($_SESSION['member']['last_login'],1);
		
		$smarty->assign('memberSpecGallery',$memberSpecGallery); // Assign any member specific galleries to smarty
		//$smarty->assign('galleriesData',$_SESSION['galleriesData']);
		$smarty->assign('lastLoginDisplay',$lastLoginDisplay);
		$smarty->assign('bills',$bills);
		$smarty->assign('sales',$sales);
		$smarty->assign('tickets',$tickets);
		$smarty->display('members.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>