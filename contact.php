<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','contact'); // Page ID
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
	
	define('META_TITLE',$lang['contactUs'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	/*
	* Smarty Template
	*/
	try
	{
		if($_POST)
		{
			foreach($_POST as $key => $value)
				$form[$key] = $value; // Create the form prefill values
				
			$error = 0;
			if($config['settings']['contactCaptcha'] == 1)
			{
				require_once BASE_PATH.'/assets/classes/recaptcha/recaptchalib.php'; // reCaptcha
 				$publickey = $config['captcha']['publickey'];
				$privatekey = $config['captcha']['privatekey'];
		
				$resp = recaptcha_check_answer($privatekey,$_SERVER["REMOTE_ADDR"],$_POST["recaptcha_challenge_field"],$_POST["recaptcha_response_field"]); // Check captcha
				//echo recaptcha_get_html($publickey); exit;
			if(!$resp->is_valid)
				$error = 1;
				
			}
			if($_POST['form']['email'] && $error == 0)
			{
				try
				{	
					$badChars = array('>','<','script','[',']');
					
					$_POST['form']['question'] = str_replace($badChars,'***',$_POST['form']['question']);
					
					foreach($badChars as $badChar)
					{
						if(strpos($_POST['form']['email'],$badChar) !== false or strpos($_POST['form']['name'],$badChar) !== false)
						{
							header("location: error.php?eType=invalidQuery");
							exit;
						}	
					}
					
					$smarty->assign('form',$_POST['form']);
					//$smarty->assign('formEmail',$_POST['form']['email']);
					//$smarty->assign('formQuestion',$_POST['form']['question']);
						
					$content = getDatabaseContent('contactFormEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
					
					$content['name'] = $smarty->fetch('eval:'.$content['name']);
					$content['body'] = $smarty->fetch('eval:'.$content['body']);
					
					$options['replyEmail'] = $_POST['form']['email'];
					$options['replyName'] = $_POST['form']['name'];
					
					kmail($config['settings']['sales_email'],$config['settings']['business_name'],$config['settings']['sales_email'],$lang['contactFromName'],$content['name'],$content['body'],$options); // Send email to sales email		
					
					$smarty->assign("contactNotice",'contactMessage');
					unset($form);
				}
				catch(Exception $e)
				{
					echo $e->getMessage();
					exit;
				}
			}
			else
				if($error == 1){
					$smarty->assign("contactNotice",'captchaError'); // Incorrect Captcha
				} else {
					$smarty->assign("contactNotice",'contactError'); // No email specified
				}
		}
		$smarty->assign('form',$form); // Assign values to prefill the form
		$smarty->assign("businessCountryName",getCountryName($config['settings']['business_country']));
		$smarty->assign("businessStateName",getStateName($config['settings']['business_state']));
		$smarty->display('contact.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>