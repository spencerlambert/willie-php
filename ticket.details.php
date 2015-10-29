<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','ticketDetails'); // Page ID
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
	
	/*
	* Close support ticket
	*/
	if($closeTicket)
	{
		mysqli_query($db,"UPDATE {$dbinfo[pre]}tickets SET lastupdated='{$nowGMT}',status=0,updatedby=0 WHERE ticket_id = {$closeTicket}"); // Close ticket
		$smarty->assign('notice','ticketClosed');
		$id = $closeTicket;
	}

	if($_POST)
	{
		if($ticketReply)
		{			
			mysqli_query($db,
				"
				INSERT INTO {$dbinfo[pre]}ticket_messages
				(
				 	ticket_id,
					message,
					submit_date,
					admin_response
				)
				VALUES 
				(
				 	'{$id}',
					'{$ticketReply}',
					'{$nowGMT}',
					0
				)
				"
			); // Insert new support ticket message
			
			mysqli_query($db,"UPDATE {$dbinfo[pre]}tickets SET lastupdated='{$nowGMT}',status=2,updatedby=0 WHERE ticket_id = {$id}"); // Update the ticket status and last updated time
			
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
			
			$smarty->assign('notice','ticketUpdated');
		}
	}

	try
	{
		$memberID = $_SESSION['member']['mem_id'];
		if(!$memberID) die('No member ID exists'); // Just to be safe make sure a memberID exists before continuing
		
		$ticketsResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}tickets
			WHERE member_id = {$memberID}
			AND ticket_id = {$id}
			"
		);
		if($returnRows = mysqli_num_rows($ticketsResult))
		{		
			$ticket = mysqli_fetch_array($ticketsResult);
			
			$ticket['lastupdated'] = $customDate->showdate($ticket['lastupdated'],1); // Format date
			$ticket['opened'] = $customDate->showdate($ticket['opened'],1); // Format date
			
			/*
			* Update the ticket as viewed and update the last read date
			*/
			$sql = "UPDATE {$dbinfo[pre]}tickets SET viewed='1',lastread='{$nowGMT}' WHERE ticket_id = {$id}";
			$result = mysqli_query($db,$sql);
			
			$messagesResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}ticket_messages 
				WHERE ticket_id = {$ticket[ticket_id]}
				ORDER BY submit_date DESC
				"
			);
			if($messageRows = mysqli_num_rows($messagesResult))
			{			
				while($message = mysqli_fetch_assoc($messagesResult))
				{
					$messagesArray[$message['message_id']] = $message;
					$messagesArray[$message['message_id']]['submit_date'] = $customDate->showdate($message['submit_date'],1); // Format date
				}
				
				$smarty->assign('messagesArray',$messagesArray);
				$smarty->assign('messageRows',$messageRows);
			}
			
			$ticketFilesResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}ticket_files  
				WHERE ticket_id = {$ticket[ticket_id]}
				ORDER BY uploaddate DESC
				"
			);
			if($ticketFileRows = mysqli_num_rows($ticketFilesResult))
			{			
				while($ticketFile = mysqli_fetch_assoc($ticketFilesResult))
				{
					$ticketFilesArray[$ticketFile['file_id']] = $ticketFile;
					$ticketFilesArray[$ticketFile['file_id']]['uploaddate'] = $customDate->showdate($ticketFile['uploaddate'],1); // Format date
					$ticketFilesArray[$ticketFile['file_id']]['filesize'] = convertFilesizeToKB(filesize(BASE_PATH.'/assets/files/'.$ticketFile['saved_name']));
				}
				
				$smarty->assign('ticketFilesArray',$ticketFilesArray);
				$smarty->assign('ticketFileRows',$ticketFileRows);
			}
			
			$smarty->assign('ticket',$ticket);
			$smarty->assign('ticketRows',$returnRows);
			
			$smarty->display('ticket.details.tpl');
		}
		else
			$smarty->display('noaccess.tpl');
		
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>