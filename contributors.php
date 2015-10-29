<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 5-16-2011
	*  Modified: 5-16-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','contributors'); // Page ID
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

	if($id)
	{
		$useMemID = $id; // Save original member ID
		
		if($config['EncryptIDs']) // Decrypt IDs
			$id = k_decrypt($id);
		
		idCheck($id); // Make sure ID is numeric
		
		/*
		* Select contributor details
		*/
		$contributorResult = mysqli_query($db,
			"
			SELECT * FROM {$dbinfo[pre]}members 
			LEFT JOIN {$dbinfo[pre]}memberships 
			ON {$dbinfo[pre]}members.membership = {$dbinfo[pre]}memberships.ms_id 
			LEFT JOIN {$dbinfo[pre]}members_address
			ON {$dbinfo[pre]}members.mem_id = {$dbinfo[pre]}members_address.member_id
			WHERE {$dbinfo[pre]}members.mem_id = '{$id}'
			"
		);
		$contributor = contrList(mysqli_fetch_array($contributorResult));
		
		// Add a view to the contr account
		if($_SESSION['member']['mem_id'] != $id) // Make sure this isnt thier own profile
		{
			if(@!in_array($id,$_SESSION['viewedProfiles'])) // See if profile has already been viewed
			{
				$profileViews = $contributor['profile_views']+1;				
				mysqli_query($db,"UPDATE {$dbinfo[pre]}members SET profile_views='{$profileViews}' WHERE mem_id = '{$id}'"); // Update views
				$contributor['profile_views'] = $profileViews; // Update the array so the count shown is the new count				
				$_SESSION['viewedProfiles'][] = $id;
			}			
		}
		
		if(($contributor['msfeatured'] == 1 and $contributor['status'] == 1) or ($contributor['showcase'] == 1 and $contributor['status'] == 1)) $publicAccess = true; // Make sure that the contributor can be displayed and is active
		
		if($publicAccess and $config['settings']['contr_metatags']) // Make sure that the contributor can be displayed and is active and that meta tags should be replaced
		{
			define('META_TITLE',$contributor['f_name']." ".$contributor['l_name']); // Override page title, description, keywords and page encoding here
			if($contributor['bio_status'] == 1){
				define('META_DESCRIPTION',substr($contributor['bio_content'],0,200));
			}
		}
		
		$contributor['bio_content'] = nl2br($contributor['bio_content']);
	}
	
	define('META_KEYWORDS','');
	define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	try
	{
		if($id)
		{
			if($publicAccess) 
			{	
				if($contributor['avatar_status'] == 1) // Avatar Status
					$contributor['avatar'] = true;
				else
					$contributor['avatar'] = false;
				
				$customContributorDate = new kdate;
				$customContributorDate->setMemberSpecificDateInfo();
				$customContributorDate->distime = 0;
				
				//if(!$contributor['display_name']) // Set display name if none exists
				//	$contributor['display_name'] = $contributor['f_name'].' '.$contributor['l_name']; 
					
				$contributor['useMemID'] = $useMemID; // Get the original member id
					
				$contributor['memberSince'] = $customContributorDate->showdate($contributor['signup_date']);
				$contributor['country'] = getCountryName($contributor['country']);
				$contributor['state'] = getStateName($contributor['state']);
				
				// Redo this so only galleries that a member should be able to see are displayed XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
				
				// Contributor Albums
				if($_SESSION['galleriesData'])
				{
					foreach($_SESSION['galleriesData'] as $key => $value) // Find subgalleries
					{
						if($value['owner'] == $id and $value['publicgal'] == 1)
						{
							$countMediaAlbums[] = $key; // Galleries to count media for
							$contrAlbums[$key] = $value;
						}
					}
				}
				
				if(count($countMediaAlbums) > 0)
					@$flatMediaAlbums = implode(',',$countMediaAlbums);
				else
					$flatMediaAlbums = '0';
				/*
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media
					LEFT JOIN {$dbinfo[pre]}media_galleries 
					ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id 
					WHERE ({$dbinfo[pre]}media_galleries.gallery_id IN ({$flatMediaAlbums}) OR {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB}))
					AND {$dbinfo[pre]}media.active = 1 
					AND {$dbinfo[pre]}media.approval_status = 1 
					AND {$dbinfo[pre]}media.owner = {$id}
					GROUP BY {$dbinfo[pre]}media.media_id
				";
				*/
				$sql = 
				"
					SELECT SQL_CALC_FOUND_ROWS *
					FROM {$dbinfo[pre]}media  
					WHERE  {$dbinfo[pre]}media.active = 1 
					AND {$dbinfo[pre]}media.approval_status = 1 
					AND {$dbinfo[pre]}media.owner = {$id} 					
					AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$memberPermGalleriesForDB}) OR gallery_id IN ({$flatMediaAlbums}))
				"; // New 4.3.2
				$contrMediaCountResult = mysqli_query($db,$sql);
				$contrMediaCount = getRows();
				
				//$contrAlbumsObj = new galleryLists($id);
				//$contrAlbums = $contrAlbumsObj->getGalleryListData();
				$smarty->assign('contrAlbums',$contrAlbums); // Assign contributor albums to smarty
				$smarty->assign("contrMediaCount",$contrMediaCount);
				$smarty->assign("contributor",$contributor);
				$smarty->display('contributor.profile.tpl'); // Smarty template
			}
			else
			{
				$smarty->display('noaccess.tpl'); // Smarty template	
			}
		}
		else
		{
			/*
			* Select all active contributors
			*/
			$contributorsResult = mysqli_query($db,
				"
				SELECT * FROM {$dbinfo[pre]}members 
				LEFT JOIN {$dbinfo[pre]}memberships 
				ON {$dbinfo[pre]}members.membership = {$dbinfo[pre]}memberships.ms_id 
				WHERE {$dbinfo[pre]}memberships.msfeatured = 1
				AND {$dbinfo[pre]}memberships.deleted = 0 
				AND {$dbinfo[pre]}memberships.active = 1 
				AND {$dbinfo[pre]}members.status = 1 
				ORDER BY {$dbinfo[pre]}members.l_name
				"
			);
			while($contributor = mysqli_fetch_array($contributorsResult))
			{	
				$contributors[$contributor['mem_id']] = contrList($contributor);
			}
			$smarty->assign("contributorsList",$contributors);		
			$smarty->display('contributors.tpl'); // Smarty template
		}
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>