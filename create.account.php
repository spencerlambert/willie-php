<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','createAccount'); // Page ID
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
	require_once BASE_PATH.'/mailchimp/Mailchimp.php';
	//error_reporting(E_ALL & ~E_NOTICE);
	//ini_set("display_errors", 1);

	//define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	//define('META_DESCRIPTION','');
	//define('META_KEYWORDS','');
	//define('PAGE_ENCODING','');
	
	define('META_TITLE',$lang['createAccount'].' &ndash; '.$config['settings']['site_title']); // Assign proper meta titles
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	
	//if(!$jumpTo) $jumpTo = 'login.php'; // Page to jump to after signing up
	
	if($_GET['jumpTo'] == 'cart') // Set go to cart on login if it is passed
		$_SESSION['jumpToOnLogin'] = 'cart.process.php';
	
	$formNotice = array(); // Fix for array doesn't exist error
	
	/*
	* Get form requirements
	*/
	$regFormResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}registration_form WHERE custom = 0");
	while($regFormDB = mysqli_fetch_array($regFormResult))
		$regForm[$regFormDB['field_id']] = $regFormDB;
	
	if($config['settings']['captcha'])
	{
		require_once BASE_PATH.'/assets/classes/recaptcha/recaptchalib.php'; // reCaptcha
 		$publickey = $config['captcha']['publickey'];
		$privatekey = $config['captcha']['privatekey'];
		
		//echo recaptcha_get_html($publickey); exit;
	}
	
	if($_POST)
	{	
		foreach($_POST as $key => $value)
		{
			$badChars = array('>','<','script','[',']'); // Remove unwanted characters				
			$cleanVal = str_replace($badChars,'*',$value);			
			$form[$key] = $cleanVal; // Create the form prefill values
		}
		
		// Check email against block list
		$blockedEmails = explode("\n",$config['settings']['blockemails']);	
		$emailBlocked = 0;		
		
		//print_r($blockedEmails); exit; // Testing
		
		if(count($blockedEmails) > 0)
		{
			foreach($blockedEmails as $check)
			{
				if($check) // Make sure this isn't empty
				{
					if(strpos($email,$check) !== false)
						$emailBlocked = 1;
				}
			}
		}
		
		if($emailBlocked)
			$formNotice[] = 'emailBlocked'; // Email address is somehow blocked
		else
		{
			if($config['settings']['captcha'])
				$resp = recaptcha_check_answer($privatekey,$_SERVER["REMOTE_ADDR"],$_POST["recaptcha_challenge_field"],$_POST["recaptcha_response_field"]); // Check captcha
			
			$emailRows = mysqli_result_patch(mysqli_query($db,"SELECT COUNT(mem_id) FROM {$dbinfo[pre]}members WHERE email='{$email}'")); // Check if email already exists
			
			if(!$emailRows)
			{
				if($config['settings']['captcha'])
				{
					if(!$resp->is_valid) $formNotice[] = "captchaError"; // Incorrect Captcha
				}
				
				//$_SESSION['testing']['step1'] = '1';
				
				if(!$f_name) $formNotice[] = "noFirstName";				
				if(!$l_name) $formNotice[] = "noLastName";			
				if(!$email)	$formNotice[] = "noEmail";
				if(!$comp_name and $regForm['formCompanyName']['status'] == 2)	$formNotice[] = "noCompName";
				if(!$phone and $regForm['formPhone']['status'] == 2) $formNotice[] = "noPhone";
				if(!$website and $regForm['formWebsite']['status'] == 2) $formNotice[] = "noWebsite";			
				if(!$country and $regForm['formAddress']['status'] == 2) $formNotice[] = "noCountry";
				if(!$address and $regForm['formAddress']['status'] == 2) $formNotice[] = "noAddress";
				if(!$city and $regForm['formAddress']['status'] == 2) $formNotice[] = "noCity";
				if(!$postal_code and $regForm['formAddress']['status'] == 2) $formNotice[] = "noPostalCode";
				if(!$password) $formNotice[] = "noPassword";
				if(strlen($password) < 6) $formNotice[] = "shortPassword";
				if(!$signupAgreement and $regForm['formSignupAgreement']['status'] == 2) $formNotice[] = "noSignupAgreement";
				
				
				if(count($formNotice) < 1) // No errors - go ahead and create the account
				{
					$membershipResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}memberships WHERE ums_id = '{$membership}'");
					$membershipRows = mysqli_num_rows($membershipResult);
					$membershipDB = mysqli_fetch_array($membershipResult);
									
					if(!$membership or !$membershipRows)
						$membershipID = 1; // If no membership is passed put them on the default membership
					else
					{	
						if($membershipDB['mstype'] == 'free' or $membershipDB['trail_status']) // Put member on this membership right away
						{							
							$membershipID = $membershipDB['ms_id'];
							if($membershipDB['trail_status'])
							{
								$trialMembership = $membershipDB['ms_id']; // Set a trial membership id
								$trialEndDate = date("Y-m-d H:i:s",strtotime(gmt_date()." +{$membershipDB[trial_length_num]} {$membershipDB[trial_length_period]}")); // Figure trial end date
							}  
						}
						else // Paid membership
						{
							$paidMembership = true;	 // This is a paid membership - mark it as so						
							$membershipID = 1;
							$_SESSION['jumpToOnLogin'] = "cart.php";
						}
					}
				
					$umem_id = create_unique2(); // Create unique ID				
					$passwordEnc = k_encrypt($password); // Encrypt the password
					
					$signupDate = gmt_date();
					$ipsignup = $_SERVER['REMOTE_ADDR'];
					$accountStatus = ($config['settings']['email_conf']) ? 2 : 1; // If email confirmation is needed set the account status to 2 (pending)
					
					
					//CLEAN BEFORE INPUT
					$f_name = strip_tags($f_name);
					$l_name = strip_tags($l_name);
					$email = strip_tags($email);
					$phone = strip_tags($phone);
					$comp_name = strip_tags($comp_name);
					$website = strip_tags($website);
					$address = strip_tags($address);
					$address_2 = strip_tags($address_2);
					$city = strip_tags($city);
					$postal_code = strip_tags($postal_code);
					$country = strip_tags($country);
					$state = strip_tags($state);
					
					mysqli_query($db,
					"
						INSERT INTO {$dbinfo[pre]}members 
						(
							umem_id,
							f_name,
							l_name,
							email,
							password,
							phone,
							comp_name,
							website,
							membership,
							signup_date,
							ip_signup,
							referrer,
							status,
							trialed_memberships,
							ms_end_date,
							language
						) 
						VALUES 
						(
							'{$umem_id}',
							'{$f_name}',
							'{$l_name}',
							'{$email}',
							'{$passwordEnc}',
							'{$phone}',
							'{$comp_name}',
							'{$website}',
							'{$membershipID}',
							'{$signupDate}',
							'{$ipsignup}',
							'{$_SESSION['initReferrerURL']}',
							'{$accountStatus}',
							'{$trialMembership}',
							'{$trialEndDate}',
							'{$selectedLanguage}'
						)
					"); // Save member
					$saveID = mysqli_insert_id($db);

						$Mailchimp = new Mailchimp( $mailchimp_api_key );
						$Mailchimp_Lists = new Mailchimp_Lists( $Mailchimp );
						$Mailchimp_Lists->subscribe( $mailchimp_list_id, array( 'email' => $email ) );
				
					mysqli_query($db,
					"
						INSERT INTO {$dbinfo[pre]}members_address  
						(
							member_id,
							address,
							address_2,
							city,
							state,
							postal_code,
							country
						) 
						VALUES 
						(
							'{$saveID}',
							'{$address}',
							'{$address_2}',
							'{$city}',
							'{$state}',
							'{$postal_code}',
							'{$country}'
						)
					"); // Save member address
					
					$signupGroups = explode(",",$config['settings']['signup_groups']); // Assign any signup groups
					if($signupGroups)
					{
						foreach($signupGroups as $key => $value)
							mysqli_query($db,"INSERT INTO {$dbinfo[pre]}groupids (mgrarea,item_id,group_id) VALUES ('members','{$saveID}','{$value}')");
					}
					
					//$_SESSION['testing']['step3'] = '3';
					
					try
					{
						$memberObj = new memberTools($saveID);			
						$member = $memberObj->getMemberInfoFromDB($umem_id); // Get all the member info from the database
						$member['primaryAddress'] = $memberObj->getPrimaryAddress();
						
						if($paidMembership) // Create a bill
						{
							// Do paid membership stuff
							// $saveID
							// $membershipDB
							
							$ubill_id = create_unique2();
							
							$invoice_number = $config['settings']['invoice_prefix'] . $config['settings']['invoice_next'] . $config['settings']['invoice_suffix']; // Get new invoice number
							$cur_inv = $config['settings']['invoice_next'];
							$next_inv = $cur_inv+1;
							
							$billDate = $nowGMT;
							$dueDate = $nowGMT;
							
							$invoiceTotal = $membershipDB['price'];										
							$membershipPeriodName = $lang[$membershipDB['period']];
							$invoiceMembershipName = "{$lang[membership]}: {$membershipDB[name]} ({$membershipPeriodName})";
							
							// Create bill record
							mysqli_query($db,
								"
								INSERT INTO {$dbinfo[pre]}billings 
								(
									ubill_id,
									member_id,
									bill_type,
									membership
								)
								VALUES
								(
									'{$ubill_id}',
									'{$saveID}',
									'1',
									'{$membershipDB[ms_id]}'
								)
								"
							);
							$billID = mysqli_insert_id($db);
							
							// Create invoice
							mysqli_query($db,
								"
								INSERT INTO {$dbinfo[pre]}invoices 
								(
									invoice_number,
									invoice_mem_id,
									bill_id,
									invoice_date,
									due_date,
									payment_status,
									inv_f_name,
									inv_l_name,
									ship_name,
									ship_email,
									ship_address,
									ship_address2,
									ship_city,
									ship_country,
									ship_state,
									ship_zip,
									ship_phone,
									bill_name,
									bill_email,
									bill_address,
									bill_address2,
									bill_city,
									bill_country,
									bill_state,
									bill_zip,
									bill_phone
								)
								VALUES
								(
									'{$invoice_number}',
									'{$saveID}',
									'{$billID}',
									'{$billDate}',
									'{$dueDate}',
									'2',
									'{$invFirstName}',
									'{$invLastName}',
									'{$invFullName}',
									'{$invEmail}',
									'{$invAddress}',
									'{$invAddress2}',
									'{$invCity}',
									'{$invCountry}',
									'{$invState}',
									'{$invPostalCode}',
									'{$invPhone}',
									'{$invFullName}',
									'{$invEmail}',
									'{$invAddress}',
									'{$invAddress2}',
									'{$invCity}',
									'{$invCountry}',
									'{$invState}',
									'{$invPostalCode}',
									'{$invPhone}'
								)
								"
							);
							$saveid2 = mysqli_insert_id($db);
							

							if($membershipDB['mstype'] == 'recurring' and $invoiceTotal > 0) // Only create an item if there is a fee
							{
								// Create invoice items
								mysqli_query($db,
									"
									INSERT INTO {$dbinfo[pre]}invoice_items 
									(
										description,
										price_total,
										taxed,
										invoice_id
									)
									VALUES
									(
										'{$invoiceMembershipName}',
										'{$membershipDB[price]}',
										'{$membershipDB[taxable]}',
										'{$saveid2}'
									)
									"
								);
							}
							
							if(@!in_array($membership,$feeMemberships)) // Check if signup fee has already been paid in the past
							{
								mysqli_query($db,
									"
									INSERT INTO {$dbinfo[pre]}invoice_items 
									(
										description,
										price_total,
										taxed,
										invoice_id
									) VALUES (
										'{$lang[setupFee]}',
										'{$membershipDB[setupfee]}',
										'{$membershipDB[taxable]}',
										'{$saveid2}'
									)
									"
								);
								$invoiceTotal+= $membershipDB['setupfee'];
							}
							
							if($membershipDB['taxable'] and $_SESSION['tax']['tax_ms']) // Figure out tax
							{	
								
								$taxValueA = $_SESSION['tax']['tax_a_default'];
								$taxValueB = $_SESSION['tax']['tax_b_default'];
								$taxValueC = $_SESSION['tax']['tax_c_default'];
								
								if($_SESSION['tax']['tax_a_default'] > 0)
								{	
									$taxA = round(($_SESSION['tax']['tax_a_default']/100)*$invoiceTotal,2);
								}
								if($_SESSION['tax']['tax_b_default'] > 0)
								{	
									$taxB = round(($_SESSION['tax']['tax_b_default']/100)*$invoiceTotal,2);
								}
								if($_SESSION['tax']['tax_c_default'] > 0)
								{	
									$taxC = round(($_SESSION['tax']['tax_c_default']/100)*$invoiceTotal,2);
								}
								$totalTax = $taxA + $taxB + $taxC;
							}
							
							$invoiceSubTotal = $invoiceTotal;
							
							$invoiceTotal+= $totalTax; // Add the tax to the total
							
							$sql = "
									UPDATE {$dbinfo[pre]}invoices SET 
									subtotal='{$invoiceSubTotal}',
									total='{$invoiceTotal}',
									taxa_cost='{$taxA}',
									taxb_cost='{$taxB}',
									taxc_cost='{$taxC}',
									tax_ratea='{$taxValueA}',
									tax_rateb='{$taxValueB}',
									tax_ratec='{$taxValueC}'
									WHERE invoice_id='{$saveid2}'
									"; 
							$result = mysqli_query($db,$sql); // Update the invoice with the totals
							
							$sql = "UPDATE {$dbinfo[pre]}settings SET invoice_next = '{$next_inv}' WHERE settings_id  = '1'"; // Add 1 to the next invoice number
							$result = mysqli_query($db,$sql);
							
						}
						
						//$_SESSION['testing']['step4'] = '4';
						
						/*
						$countryResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}countries WHERE country_id = {$country}"); // Select country
						$dbcountry = mysqli_fetch_array($countryResult);
						
						$memberCountryName = $dbcountry['name']; // xxx Language
						$adminCountryName = $dbcountry['name'];
						
						$stateResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE state_id = {$state}"); // Select state
						$dbstate = mysqli_fetch_array($stateResult);
						
						$memberStateName = $dbstate['name']; // xxx Language
						$adminStateName = $dbstate['name'];
						
						$memberCountryName = getCountryName($member['country']);
						$memberStateName = getStateName($member['state']);
						
						$member['primaryAddress']['address'] = $address;
						$member['primaryAddress']['address_2'] = $address_2;
						$member['primaryAddress']['city'] = $city;
						$member['primaryAddress']['state'] = $memberStateName;
						$member['primaryAddress']['postal_code'] = $postal_code;
						$member['primaryAddress']['country'] = $memberCountryName;
						*/
						$smarty->assign('member',$member); // Assign member details to smarty
					}
					catch(Exception $e)
					{
						echo $e->getMessage();
					}
					
					if($config['settings']['email_conf']) // Send member a confirmation email
					{	
						try
						{
							/*
							$content = new databaseContent(50);
							$content->setMemberInfo($memberInfo);
							$memberEmailContent = $content->getContent();
							$memberSubject = $memberEmailContent['name'];
						
							$confirmLink = "{$siteURL}/actions.php?action=verifyAccount&id={$umem_id}";
							$memberBody = $memberEmailContent['content']; // Email body
							
							$memberBody = str_replace('{verifyLink}',$confirmLink,$memberBody); // Replace verifyLink with actual link
							*/
							
							//$_SESSION['testing']['step5'] = '5';
							
							$confirmLink = "{$siteURL}/actions.php?action=verifyAccount&id={$umem_id}";
							$smarty->assign('confirmLink',$confirmLink);
							$content = getDatabaseContent('verifyAccountEmail');
							$content['name'] = $smarty->fetch('eval:'.$content['name']);
							$content['body'] = $smarty->fetch('eval:'.$content['body']);
							
							//echo $smarty->fetch('eval:'.$content['body']); exit;
							
							//$member['f_name'].' '.$member['l_name'] - used to be name
							kmail($member['email'],'',$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email to confirm account and set jump to to confirm page notice
							//kmail($member['email'],$member['email'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email to confirm account and set jump to to confirm page notice
							
						}
						catch(Exception $e)
						{
							echo $e->getMessage();
							exit;
						}
						$jumpToOnCreate = 'create.account2.php?notice=activationEmail';
					}
					else // Send member a welcome email
					{
						/*
						$content = new databaseContent(8);
						$content->setMemberInfo($memberInfo);
						$memberEmailContent = $content->getContent();
						$memberSubject = $memberEmailContent['name'];
						$memberBody = $memberEmailContent['content']; // Email body
						*/
						
						//$_SESSION['testing']['step6'] = '6';
						
						try
						{
							$content = getDatabaseContent('welcomeEmail'); // Get content // $config['settings']['lang_file_mgr'] and force language for admin (removed)
							$content['name'] = $smarty->fetch('eval:'.$content['name']);
							$content['body'] = $smarty->fetch('eval:'.$content['body']);
							//$member['f_name'].' '.$member['l_name']
							kmail($member['email'],'',$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email to confirm account creation
							//kmail($member['email'],$member['email'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email to confirm account creation
						}
						catch(Exception $e)
						{
							echo $e->getMessage();
							exit;
						}
						$jumpToOnCreate = 'login.php?jumpTo=members';
					}
					
					if($config['settings']['notify_account']) // Notify admin that a new member account was created
					{
						try
						{
							$member['primaryAddress']['state'] = $adminStateName;
							$member['primaryAddress']['country'] = $adminCountryName;
							
							/*
							$content = new databaseContent(51);
							$content->setMemberInfo($memberInfo);
							$content->setLanguage();
							$content->includeManagerLang();
							$adminEmailContent = $content->getContent();
							$adminSubject = $adminEmailContent['name'];
							$adminBody = $adminEmailContent['content'];
							*/
							
							$content = getDatabaseContent('newMemberEmailAdmin');
							$content['body'] = $smarty->fetch('eval:'.$content['body']);
							kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Notify the admin that there is a new member
						}
						catch(Exception $e)
						{
							echo $e->getMessage();
							exit;
						}
					}
					
					
					//echo 'jt:'.$jumpToOnCreate; exit;
					header("location: {$jumpToOnCreate}");
					exit;
				}
			}
			else
				$formNotice[] = 'emailExists'; // Email already exists in the system
		}
	}
	
	try
	{
		$smarty->assign('form',$form); // Assign values to prefill the form
		
 		//$captcha = recaptcha_get_html($publickey);
		//$smarty->assign('captcha',$captcha);
		
		/*
		* Get countries list
		*/
		$smarty->assign('countries',getCountryList($selectedLanguage));
		
		/*
		* Get states only if $country is passed
		*/
		if($_POST['country'])
		{
			$stateResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE active = 1 AND deleted = 0 AND country_id = '{$_POST[country]}'"); // Select states
			while($state = mysqli_fetch_array($stateResult))
			{
				$states[$state['state_id']] = $state['name']; // xxxxx languages	
			}
			$smarty->assign('states',$states);
		}
		
		/*
		if(preg_match("/[^A-Za-z0-9_-]/",$msID))
		{
			header("location: error.php?eType=invalidQuery");
			exit;
		}
		*/
		
		/*
		* Get membership list
		*/
		$membershipDBResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}memberships
			LEFT JOIN {$dbinfo[pre]}perms
			ON ({$dbinfo[pre]}memberships.ms_id = {$dbinfo[pre]}perms.item_id AND {$dbinfo[pre]}perms.perm_area = 'memberships')
			WHERE {$dbinfo[pre]}memberships.deleted = 0
			AND ({$dbinfo[pre]}memberships.everyone = 1 OR {$dbinfo[pre]}perms.perm_value IN ({$memberPermissionsForDB})) 
			ORDER BY {$dbinfo[pre]}memberships.sortorder
			"
		);
		while($membershipDB = mysqli_fetch_array($membershipDBResult))
		{
			if($membershipDB['active'] == 1) // Only show those that are set to display or if one is passed in the URL
			{
				if($msID == $membershipDB['ums_id']) $msIDActive = 1; // See if the msID that was passed is actually active
				$memberships[$membershipDB['ms_id']] = membershipsList($membershipDB);
			}
		}
		$smarty->assign('memberships',$memberships);
		
		$smarty->assign('regForm',$regForm); // Assign form requirements to smarty
		
		if($showMemberships or $config['settings']['reg_memberships'] or $msID)
			$smarty->assign('showMemberships',1); // Show the membership choices

		if($msID and $msIDActive) $selectedMembership = $msID; // A msID was passed - make it selected		
		if($membership) $selectedMembership = $membership; // A membership was passed - make it selected		
		if(!$selectedMembership) $selectedMembership = 'D6E90466E66396F2B0A860D86C9EF0B1'; // No memberships were already selected - preselect the default
		
		$smarty->assign('msID',$msID); // Pass a $msID if one is detected
		$smarty->assign('selectedMembership',$selectedMembership); // Pass the preselected membership
		
		$smarty->assign('formNotice',$formNotice);
		
		$smarty->display('create.account.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>