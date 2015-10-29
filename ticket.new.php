<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','ticketNew'); // Page ID
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

	try
	{	
		if($_POST)
		{
			if($message)
			{
				mysqli_query($db,
					"
					INSERT INTO {$dbinfo[pre]}tickets 
					(
					 	member_id,
						summary,
						opened,
						lastupdated,
						status,
						updatedby,
						viewed
					)
					VALUES
					(
					 	'{$memberID}',
						'{$summary}',
						'{$nowGMT}',
						'{$nowGMT}',
						'2',
						'0',
						'1'
					)				
					"
				); // Insert ticket
				$ticketID = mysqli_insert_id($db);
				
				mysqli_query($db,
					"
					INSERT INTO {$dbinfo[pre]}ticket_messages  
					(
					 	ticket_id,
						message,
						submit_date,
						admin_response,
						admin_id
					)
					VALUES
					(
					 	'{$ticketID}',
						'{$message}',
						'{$nowGMT}',
						'0',
						'0'
					)				
					"
				); // Insert message
				
				// xxxx Notify admin of new support ticket response
				$notify = 1;
				if($notify && $_SESSION['member']['email'] != "")
				{
					// Build email
					$toEmail = $config['settings']['support_email'];
					$content = getDatabaseContent('newAdminTicketResponse'); // Get content from db				
					$content['name'] = $smarty->fetch('eval:'.$content['name']);
					$content['body'] = $smarty->fetch('eval:'.$content['body']);
					$options['replyEmail'] = $config['settings']['support_email'];
					$options['replyName'] = $config['settings']['business_name'];
					kmail($toEmail,$toEmail,$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body'],$options); // Send email
				}
				
				$notice = 'ticketSubmitted';
				$smarty->assign('notice',$notice);
			}
		}
		$smarty->display('ticket.new.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>