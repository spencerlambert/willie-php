<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','lightboxes'); // Page ID
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
	
	define('META_TITLE',$lang['lightboxes'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	if($delete)
	{
		$lightboxDeleteResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}lightboxes WHERE ulightbox_id = '{$delete}' AND umember_id = '{$_SESSION[member][umem_id]}'");
		$deleteRows = mysqli_num_rows($lightboxDeleteResult);
		$lightboxDelete = mysqli_fetch_array($lightboxDeleteResult);
		
		if($deleteRows)
		{			
			if($_SESSION['selectedLightbox'] == $lightboxDelete['lightbox_id']) $_SESSION['selectedLightbox'] = 0; // Reset selectedLightbox session if this lightbox is deleted
			
			$lightboxItemsDeleteResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}lightbox_items WHERE lb_id = '{$lightboxDelete[lightbox_id]}'");
			if(mysqli_num_rows($lightboxItemsDeleteResult))
			{
				while($lightboxItemsDelete = mysqli_fetch_array($lightboxItemsDeleteResult))
				{
					if($deleteKey = array_search($lightboxItemsDelete['media_id'],$_SESSION['lightboxItems']))
						unset($_SESSION['lightboxItems'][$deleteKey]);					
				}
			}
			
			mysqli_query($db,"DELETE FROM {$dbinfo[pre]}lightbox_items WHERE lb_id = '{$lightboxDelete[lightbox_id]}'"); // Delete lightbox items		
			mysqli_query($db,"UPDATE {$dbinfo[pre]}lightboxes SET deleted='1' WHERE ulightbox_id = '{$lightboxDelete[ulightbox_id]}'"); // Mark selected lightbox as deleted
			$notice = 'lightboxDeleted';
		}
	}
	
	//print_r($_SESSION['lightboxItems']);
	
	try
	{
		$umemberID = $_SESSION['member']['umem_id'];	
		if(!$umemberID) die('No unique member ID exists'); // Just to be safe make sure a memberID exists before continuing
		
		//echo "cookie: " . $_COOKIE['member']['umem_id'];
		//echo $_SESSION['member']['umem_id'];
		
		$guestLightbox = ($_SESSION['loggedIn']) ? 0 : 1; // Determine the guest lightbox status
		
		//echo $guestLightbox;
		
		$lightboxResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}lightboxes
			WHERE umember_id = '{$umemberID}'
			AND deleted = 0 
			AND guest = '{$guestLightbox}' 
			ORDER BY created DESC
			"
		);
		if($returnRows = mysqli_num_rows($lightboxResult))
		{	
			while($lightbox = mysqli_fetch_array($lightboxResult))
			{	
				$itemCount = mysqli_result_patch(mysqli_query($db,"SELECT COUNT(item_id) FROM {$dbinfo[pre]}lightbox_items WHERE lb_id = '{$lightbox[lightbox_id]}'"));
				
				$lightboxArray[$lightbox['lightbox_id']] = $lightbox;
				$lightboxArray[$lightbox['lightbox_id']]['items'] = $itemCount;
				$lightboxArray[$lightbox['lightbox_id']]['create_date_display'] = $customDate->showdate($lightbox['created'],0);
				
				if($config['EncryptIDs']) // Encrypt IDs
					$lightboxArray[$lightbox['lightbox_id']]['linkto'] = "gallery.php?mode=lightbox&id=".k_encrypt($lightbox['ulightbox_id'])."&page=1";
				else
					$lightboxArray[$lightbox['lightbox_id']]['linkto'] = "gallery.php?mode=lightbox&id=".$lightbox['ulightbox_id']."&page=1";
			}
			
			/* Testing
			foreach($lightboxArray as $lb)
			{
				print_r($lb);
				echo "<br><br>";
			}
			*/
			
			$smarty->assign('lightboxArray',$lightboxArray);
			$smarty->assign('lightboxRows',$returnRows);
		}
		
		$smarty->assign('notice',$notice);
		$smarty->display('lightboxes.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>