<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 10-13-2011
	*  Modified: 10-13-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','comments'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	//require_once BASE_PATH.'/assets/includes/cart.inc.php';
	//require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	//echo $_SESSION['errorcount'];
	//$_SESSION['errorcount']++;
	
	try
	{	
		if($config['EncryptIDs']) // Decrypt IDs
			$mediaID = k_decrypt($mediaID);
			
		idCheck($mediaID); // Make sure ID is numeric
		
		$limit = ($limit) ? $limit : 5; // Set the amount of comments to initially show
		$commentCounter = 0;
		
		$commentResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}media_comments
			WHERE media_id = {$mediaID}
			AND status = 1 
			ORDER BY posted DESC
			"
		);
		if($returnRows = mysqli_num_rows($commentResult))
		{	
			while($comment = mysqli_fetch_array($commentResult))
			{
				if($limit > $commentCounter)
				{
					$memberResult = mysqli_query($db,
						"
						SELECT display_name
						FROM {$dbinfo[pre]}members
						WHERE mem_id = {$comment[member_id]}
						"
					);
					if($memberRows = mysqli_num_rows($memberResult))
					{
						$member = mysqli_fetch_array($memberResult);
						$comment['memberName'] = $member['display_name'];
					}
					else
						$comment['memberName'] = $lang['guest'];
					
					$comment['posted'] = $customDate->showdate($comment['posted'],1);				
					$commentsArray[] = $comment;
					$commentCounter++;
				}
			}
			
			$moreComments = ($returnRows > $limit) ? $returnRows - $limit : false;
			$smarty->assign('moreComments',$moreComments);
		}		
		$smarty->assign('commentRows',$returnRows);
		$smarty->assign('commentsArray',$commentsArray);
		
		$smarty->display('comments.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
?>