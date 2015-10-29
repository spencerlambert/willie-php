<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','login'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	
	$setCurrency = new currencySetup;
	$activeCurrencies = $setCurrency->getActiveCurrencies();
	
	/*
	* Include manager language file
	*/
	if(file_exists(BASE_PATH."/assets/languages/" . $config['settings']['lang_file_mgr'] . "/lang.manager.php"))
		include(BASE_PATH."/assets/languages/" . $config['settings']['lang_file_mgr'] . "/lang.manager.php");
	else
		include(BASE_PATH."/assets/languages/english/lang.manager.php");
	
	if($_GET['jumpTo'] == 'cart') // Set go to cart on login if it is passed
		$_SESSION['jumpToOnLogin'] = 'cart.process.php';
	
	if(!$_SESSION['jumpToOnLogin'] or $_GET['jumpTo'] == 'members' or $_GET['cmd'] == 'logout') // Make sure a session for go to after login is set // xxxxxxxxxxxxxx use this if a direct link to the login page is used?
		$_SESSION['jumpToOnLogin'] = 'members.php';
	
	/*
	* Logout and destroy the session
	*/
	if($_GET['cmd'] == 'logout')
	{		
		save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLogin'],0,"<strong>{$mgrlang[pubLoggedOut]}</strong>"); // Make entry in the activity log db
		
		mysqli_query($db,"UPDATE {$dbinfo[pre]}orders SET member_id = '0' WHERE uorder_id = '{$_SESSION[uniqueOrderID]}'"); // Set cart back to guest
		
		memberSessionDestroy(); // Destroy the members session
		
		unset($_SESSION['shippingAddressSession']); // Unset any previous shipping address info
		unset($_SESSION['billingAddressSession']); // Unset any previous billing address info
		
		$_SESSION['loggedIn'] = 0;
		$logNotice = 'loggedOutMessage';
	}
	
	/*
	* Check login details for login
	*/
	if($_POST)
	{
		$loginPassword = k_encrypt($memberPassword); // Encrypt password
		$loginResult = mysqli_query($db,
		"
			SELECT *
			FROM {$dbinfo[pre]}members
			WHERE email = '{$memberEmail}' 
			AND password = '{$loginPassword}'
			LIMIT 1
		"
		); // Pull basic login info from the db
		$loginRows = mysqli_num_rows($loginResult); // Rows from query
		if($loginRows)
		{
			try
			{
				$loginMember = mysqli_fetch_array($loginResult);
				
				if($loginMember['status'] == 1) // Status is active
				{
					$loggedOutUMEMID = $_SESSION['member']['umem_id']; // Before overwriting this grab the umem_id from the session before the member logged in
					
					unset($_SESSION['member']);
					$memberSess = new memberTools($loginMember['mem_id']);	
					
					save_activity($loginMember['mem_id'],$mgrlang['pubLogin'],0,"<strong>{$mgrlang[pubLoggedIn]} ($_SERVER[REMOTE_ADDR])</strong>"); // Make entry in the activity log db
					
					$_SESSION['member'] = $memberSess->getMemberInfoFromDB($loginMember['umem_id']);
					if($_SESSION['member']['umem_id'])
					{
						if($loginMember['membership'] == 1 or ($nowGMT < $_SESSION['member']['ms_end_date']) or ($_SESSION['member']['ms_end_date'] == '0000-00-00 00:00:00'))
						{
							$_SESSION['member']['membershipDetails'] = $memberSess->getMembershipInfoFromDB($loginMember['membership']); // Get the membership info and add it to the member session array
						}
						else
						{
							$_SESSION['member']['membership'] = 1;
							$_SESSION['member']['membershipDetails'] = $memberSess->getMembershipInfoFromDB(1); // Membership is expired put them on basic free membership
						}
						
						//print_k($_SESSION['member']['membershipDetails']);
						//exit;
																		
						$_SESSION['loggedIn'] = 1; // Set the logged in session but make sure first	

						setcookie("member[umem_id]", $_SESSION['member']['umem_id'], time()+60*60*24*30, "/", $cookieHost[0]); // Set a new member id cookie

						unset($_SESSION['lightboxItems']); // Clear the current lightboxItems session so that a new one will be grabbed after login
						unset($_SESSION['shippingAddressSession']); // Unset any previous shipping address info
						unset($_SESSION['billingAddressSession']); // Unset any previous billing address info
						
						if($loginMember['currency']) // If the member has a currency preselected in their account set it
						{
							//print_r($activeCurrencies); exit; // testing							
							//echo $setCurrency->priCurrency['currency_id']; exit; // testing
							
							// Check if members chosen currency is active
							if(array_key_exists($loginMember['currency'],$activeCurrencies))
							{
								$_SESSION['selectedCurrencySession'] = $loginMember['currency'];
								$_SESSION['member']['currency'] = $loginMember['currency'];
							}
							else
							{
								$_SESSION['selectedCurrencySession'] = $setCurrency->priCurrency['currency_id'];
								$_SESSION['member']['currency'] = $setCurrency->priCurrency['currency_id'];
							}
							
							//echo $loginMember['currency']; exit; // testing
						}
						
						if($loginMember['language']) // If the member has a language preselected in their account set it
						{
							$_SESSION['selectedLanguageSession'] = $loginMember['language'];
							$_SESSION['member']['language'] = $loginMember['language']; // Added this just to keep it in the member session also
						}
						else // Make sure a language is set just in case the member doesn't have one selected
						{
							$_SESSION['selectedLanguageSession'] = $config['settings']['default_lang'];
							$_SESSION['member']['language'] = $config['settings']['default_lang']; // Added this just to keep it in the member session also
						}
						
						if($loginMember['com_source'] == 1) // Find the correct commision level for a member
							$_SESSION['member']['com_level'] = $_SESSION['member']['membershipDetails']['commission'];
							
						if(!$loginMember['paypal_email']) // Find the correct commision level for a member
							$_SESSION['member']['paypal_email'] = $_SESSION['member']['email'];
						
						$memberGroups = explode(",",$config['settings']['login_groups']); // Assign any login groups
						
						$membershipSigninGroups = explode(",",$loginMember['signin_groups']); // Get the member groups that are assigned from membership on signin
						if($membershipSigninGroups)
						{
							foreach($membershipSigninGroups as $group)
							{
								$memberGroups[] = $group;
							}
						}
						
						if($memGetGroups = $memberSess->getMemberGroups($loginMember['mem_id']))
						{
							foreach($memGetGroups as $memGRP)
							{
								$memberGroups[] = $memGRP; // Add member groups to the login groups
							}
						}
						
						$memberGroupsFixed = array_map("addPermPrefixGrp",$memberGroups); // Assign to member groups session			
						$_SESSION['member']['permmissions'][] = 'msp'.$loginMember['membership']; // Assign membership to permissions
						$_SESSION['member']['permmissions'][] = 'mem'.$loginMember['mem_id']; // Assign member ID as memXXX to permissions
						if($memberGroupsFixed)
						{
							foreach($memberGroupsFixed as $groups)
							{
								$_SESSION['member']['permmissions'][] = $groups;
							}
						}
						
						$iplogin = $_SERVER['REMOTE_ADDR'];
						mysqli_query($db,"UPDATE {$dbinfo[pre]}members SET last_login = '{$nowGMT}' WHERE mem_id = '{$loginMember[mem_id]}'"); // Set the last login time and date
						mysqli_query($db,"UPDATE {$dbinfo[pre]}members SET ip_login = '{$iplogin}' WHERE mem_id = '{$loginMember[mem_id]}'"); // Set the last login IP
						mysqli_query($db,"UPDATE {$dbinfo[pre]}orders SET member_id = '{$loginMember[mem_id]}' WHERE uorder_id = '{$_SESSION[uniqueOrderID]}'"); // reassign any carts to this member
						mysqli_query($db,"UPDATE {$dbinfo[pre]}lightboxes SET member_id = '{$loginMember[mem_id]}', umember_id ='{$loginMember[umem_id]}', guest='0' WHERE umember_id = '{$loggedOutUMEMID}' AND guest = '1'"); // Update guest lightboxes to this member
						
						$_SESSION['member']['primaryAddress'] = $memberSess->getPrimaryAddress(); // Get the members primary address
						
						if($config['settings']['tax_type'] == 0) // Tax by region
						{
							$_SESSION['tax'] = $memberSess->getMemberTaxValues();
						}
						
						$ratedMediaResult = mysqli_query($db,"SELECT member_id,media_id FROM {$dbinfo[pre]}media_ratings WHERE member_id = '{$loginMember[mem_id]}'"); // Find out which media a member already rated
						while($ratedMedia = mysqli_fetch_array($ratedMediaResult))
							$_SESSION['ratedMedia'][] = $ratedMedia['media_id']; // Moved out of member session array so it can be handled separately and between logged in and not logged in visitors
						
						header("location: {$_SESSION[jumpToOnLogin]}");
						exit;
					}
					else
					{
						$logNotice = 'loginFailedMessage'; // The session assign/login failed
					}
				}
				else if($loginMember['status'] == 0) // Closed
				{
					$logNotice = 'loginAccountClosed';
				}
				else if($loginMember['status'] == 2) // Pending
				{
					$logNotice = 'loginPending';
				}
			}
			catch(Exception $e)
			{
				die($e->getMessage());
			}
		}
		else
		{
			$logNotice = 'loginFailedMessage';
		}
	}
	
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';

	//define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	//define('META_DESCRIPTION','');
	//define('META_KEYWORDS','');
	//define('PAGE_ENCODING','');
	
	define('META_TITLE',$lang['login'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	if($_GET['logNotice'])
		$logNotice = $_GET['logNotice'];

	try
	{		
		$smarty->assign('logNotice',$logNotice); // Assign login notice message to smarty
		$smarty->display('login.tpl'); // Smarty template
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>