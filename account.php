<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','account'); // Page ID
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
		$customDate->setMemberSpecificDateInfo(); // Just in case
		
		//echo "GMT: ".$nowGMT; // Testing
		
		/*
		* Get membership details
		*/
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
		
		if($mode == 'avatar')
			$_SESSION['member']['avatar'] = 1; // Update the avatar session
		
		if($notice)	$smarty->assign('notice',$notice);

		$signupDateDisplay = $customDate->showdate($_SESSION['member']['signup_date'],0);
		$lastLoginDisplay = $customDate->showdate($_SESSION['member']['last_login'],1);
		
		$exampleDateDisplay = $customDate->showdate($nowGMT,0);
		
		/* Testing
		echo '<br><br>'.$exampleDateDisplay.'<br><br>';

		foreach($_SESSION['member'] as $key => $value)
		{
			echo "{$key}: {$value}<br>";
		}
		*/
		//exit;
		
		//$membershipName = $_SESSION['member']['membershipDetails']['name']; // xxxx Needs correct language
		
		switch($_SESSION['member']['compay']) // Find the commission selection for the member account
		{
			case 1:
				$commissionTypeName = $lang['paypal'];
			break;
			case 2:
				$commissionTypeName = $lang['checkMO']; // xxxx Lang
			break;
			case 3:
				$commissionTypeName = $config['settings']['compay_other']; // xxxx Lang
			break;
		}
		
		// Set the member uploader
		if(!$_SESSION['member']['uploader'])
			$_SESSION['member']['uploader'] = $config['settings']['pubuploader'];
		
		/*
		if($_SESSION['member']['com_source'] == 1) // Find the commission level for the member
			$commissionPercentage = $membershipDB['commission'];
		else
			$commissionPercentage = $_SESSION['member']['com_level'];
		*/
		
		$smarty->assign('bio',nl2br($_SESSION['member']['bio_content'])); // Clean bio display	
		$smarty->assign('commissionTypeName',$commissionTypeName);
		$smarty->assign('membershipName',$membershipName);
		$smarty->assign('signupDateDisplay',$signupDateDisplay);
		$smarty->assign('lastLoginDisplay',$lastLoginDisplay);
		$smarty->assign('exampleDateDisplay',$exampleDateDisplay);
		$smarty->display('account.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>