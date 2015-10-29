<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','memberSubs'); // Page ID
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
		
		$memsubResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}memsubs 
			LEFT JOIN {$dbinfo[pre]}subscriptions  
			ON {$dbinfo[pre]}memsubs.sub_id = {$dbinfo[pre]}subscriptions.sub_id 
			WHERE {$dbinfo[pre]}memsubs.mem_id = {$memberID}
			"
		);
		if($returnRows = mysqli_num_rows($memsubResult))
		{	
			while($memsub = mysqli_fetch_array($memsubResult))
			{
				$memsubArray[$memsub['msub_id']] = subscriptionsList($memsub);
				
				$memsubArray[$memsub['msub_id']]['expire_date_display'] = $customDate->showdate($memsub['expires'],0);
				
				if($nowGMT > $memsub['expires'])
				{
					$memsubArray[$memsub['msub_id']]['expired'] = true; // See if the subscription is expired
					$memsubArray[$memsub['msub_id']]['status_lang'] = 'expired';
				}
				else
					$memsubArray[$memsub['msub_id']]['status_lang'] = 'active';
					
				if($memsub['perday'])
					$memsubArray[$memsub['msub_id']]['downloads_per_day'] = $memsub['perday']; // Downloads per day
				else
					$memsubArray[$memsub['msub_id']]['downloads_per_day'] = 0; // Unlimited
				
				//$today = explode(" ",$nowGMT);
				$dateMinus24Hours = date("Y-m-d H:i:s", strtotime("{$nowGMT} -24 hours"));

				$todayDownloads = mysqli_result_patch(mysqli_query($db,"SELECT COUNT(*) FROM {$dbinfo[pre]}downloads WHERE dl_type = 'sub' AND dl_type_id = '{$memsub[msub_id]}' AND mem_id = '{$memberID}' AND dl_date > '{$dateMinus24Hours}'"));
				$totalDownloads = mysqli_result_patch(mysqli_query($db,"SELECT COUNT(*) FROM {$dbinfo[pre]}downloads WHERE dl_type = 'sub' AND dl_type_id = '{$memsub[msub_id]}' AND mem_id = '{$memberID}'"));	
				
				//echo $downloads; exit;
				
				@$totalRemaining = $memsub['total_downloads'] - $totalDownloads;			
				@$todayRemaining = $memsub['perday'] - $todayDownloads;
				
				$memsubArray[$memsub['msub_id']]['totalDownloads'] = $totalDownloads;
				$memsubArray[$memsub['msub_id']]['todayDownloads'] = $todayDownloads;
				$memsubArray[$memsub['msub_id']]['todayRemaining'] = $todayRemaining;
				$memsubArray[$memsub['msub_id']]['totalRemaining'] = $totalRemaining;
			}
			
			$smarty->assign('memsubArray',$memsubArray);
			$smarty->assign('memsubRows',$returnRows);
		}
		
		$smarty->display('member.subs.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>