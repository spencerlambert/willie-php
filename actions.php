<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-25-2011
	*  Modified: 5-26-2011
	******************************************************************/
	
	//sleep(1); // Testing

	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	
	//if($_GET['action'] != 'sessionDestroy') // Do not initiate a session if destroying it below
		require_once BASE_PATH.'/assets/includes/session.php';

	$skipInitialize = array('sessionDestroy'); // Action cases when the includes will be skipped
	
	if(@!in_array($_GET['action'],$skipInitialize)) // Speed things up by skipping these includes if not needed
	{
		require_once BASE_PATH.'/assets/includes/initialize.php';
		//require_once BASE_PATH.'/assets/includes/language.inc.php';
		require_once BASE_PATH.'/assets/includes/commands.php';
		require_once BASE_PATH.'/assets/includes/init.member.php';
		require_once BASE_PATH.'/assets/includes/security.inc.php';
		require_once BASE_PATH.'/assets/includes/language.inc.php';
		require_once BASE_PATH.'/assets/includes/cart.inc.php';
		require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
		require_once BASE_PATH.'/assets/includes/header.inc.php';
		require_once BASE_PATH.'/assets/includes/errors.php';
		require_once BASE_PATH.'/assets/classes/mediatools.php';
	}
	
	$_SESSION['testing']['hp'] = $_REQUEST['action'];
	
	/*
	* Include manager language file
	*/
	if(file_exists(BASE_PATH."/assets/languages/" . $config['settings']['lang_file_mgr'] . "/lang.manager.php"))
		include BASE_PATH."/assets/languages/" . $config['settings']['lang_file_mgr'] . "/lang.manager.php";
	else
		include BASE_PATH."/assets/languages/english/lang.manager.php";

	try
	{	
		switch($_REQUEST['action'])
		{
			case "rateMedia": // Submit a rating for media
				if(@!in_array($mediaID,$_SESSION['ratedMedia'])) // Hasn't already been voted for
				{
					$autoApprove = ($_SESSION['member']['membershipDetails']['rating_approval']) ? 1 : 0; // Check if this should auto approve					
					if
					(
						($_SESSION['member']['membershipDetails']['rating'] and $config['settings']['rating_system']) or
						($config['settings']['rating_system'] and $config['settings']['rating_system_lr'])
				   	)
					{
						if(mysqli_query($db,"INSERT INTO {$dbinfo[pre]}media_ratings (member_id,media_id,rating,posted,status) VALUES ('{$_SESSION['member']['mem_id']}','{$mediaID}','{$starValue}','{$nowGMT}','{$autoApprove}')"))					
							$_SESSION['ratedMedia'][] = $mediaID; // Add this to the members rated media array
					}
					
					if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubNewRating'],0,"<strong>{$starValue}/10 ({$mediaID})</strong>"); // Make entry in the activity log db - only if member is logged in
				}
				echo "<span class='mediaDetailValue'>{$lang[ratingSubmitted]}</span>";
				
				if($config['settings']['notify_rating'])
				{
					if($_SESSION['loggedIn'])
						$user = $_SESSION['member']['f_name'].' '.$_SESSION['member']['l_name']; // User name from member name	
					else
						$user = $mgrlang['gen_visitor']; // User is guest
					
					try
					{
						$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
						$mediaObj = new mediaList($sql);
						
						if($mediaObj->getRows())
						{
							$mediaObj->getMediaDetails(); // Run the getMediaDetails function so I can grab the info later
							$media = $mediaObj->getMediaSingle(); // Grab the single returned media info
							$smarty->assign('media',$media);
						}
						
						if($config['RatingStars'] == 10)
							$rating = $starValue;
						else
							$rating = $starValue/2;
							
						$smarty->assign('user',$user);
						$smarty->assign('rating',"{$rating}/{$config[RatingStars]}");
						$smarty->assign('language',$language);
						$smarty->assign('autoApprove',$autoApprove);
						
						$content = getDatabaseContent('newRatingEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
						$content['name'] = $smarty->fetch('eval:'.$content['name']);
						$content['body'] = $smarty->fetch('eval:'.$content['body']);
						kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email about new tag submitted
					}
					catch(Exception $e)
					{
						//echo $e->getMessage();
					}
				}
				
			break;
			case "updateCartNotes":				
				$_SESSION['cartInfoSession']['cartNotes'] = $cartNotes;		
				@mysqli_query($db,"UPDATE {$dbinfo[pre]}invoices SET cart_notes='{$cartNotes}' WHERE invoice_id = '{$_SESSION[cartInfoSession][invoiceID]}'");
				
			break;
			case "changeLanguage": // Change the selected language
				if(preg_match("/[^A-Za-z0-9_-]/",$_GET['setLanguage']))
				{
					header("location: error.php?eType=invalidQuery");
					exit;
				}
				
				$_SESSION['selectedLanguageSession'] = $_GET['setLanguage'];
				$_SESSION['member']['language'] = $_GET['setLanguage'];
				
				if($_SESSION['loggedIn'])
					mysqli_query($db,"UPDATE {$dbinfo[pre]}members SET language='{$_GET[setLanguage]}' WHERE umem_id = '{$_SESSION[member][umem_id]}'"); // Update the selected langauge in the db
					
				unset($_SESSION['galleriesData']); // Clear galleries data because we are grabbing a new language
				
				if(strpos($_SERVER['HTTP_REFERER'],'cart.php'))
					$referer = 'cart.php';
				else
					$referer = $_SERVER['HTTP_REFERER'];
				
				if(!$referer) $referer = 'index.php';
				
				header("location: {$referer}");
				exit;
			break;
			case "changeCurrency": // Change the selected currency
				$_SESSION['selectedCurrencySession'] = $_GET['setCurrency'];
				$_SESSION['member']['currency'] = $_GET['setCurrency'];
				
				if($_SESSION['loggedIn'])
					mysqli_query($db,"UPDATE {$dbinfo[pre]}members SET currency='{$_GET[setCurrency]}' WHERE umem_id = '{$_SESSION[member][umem_id]}'"); // Update the selected currency in the db
				
				if(strpos($_SERVER['HTTP_REFERER'],'cart.php'))
					$referer = 'cart.php';
				else
					$referer = $_SERVER['HTTP_REFERER'];
				
				header("location: {$referer}");
				exit;
			break;
			case "sessionDestroy": // Destroy the session
				require_once BASE_PATH.'/assets/includes/public.functions.php'; // Include this so I can use the memberSessionDestroy function
				memberSessionDestroy(); // Added this as a backup
				session_destroy();				
				header('location: index.php');
				exit;
			break;
			case "memberSessionDestroy": // Destroy the members session
				memberSessionDestroy();
				header('location: index.php');
				exit;
			break;
			case "verifyAccount": // Verify a new member account
				if(!$id)
					die('No ID passed for member account to be activated');
				
				
				$memberObj = new memberTools();			
				$member = $memberObj->getMemberInfoFromDB($id); // Get all the member info from the database				
				$member['primaryAddress'] = $memberObj->getPrimaryAddress();
				
				/*
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
				
				//$memberResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}members WHERE umem_id = '{$id}'"); // Select the member info from the database just to be sure
				//$memberRows = mysqli_num_rows($memberResult);
				//$member = mysqli_fetch_array($memberResult);
				
				$sendEmailInLang = ($member['language']) ? $member['language'] : '';
				
				if(!$member['umem_id'])
					die('Member account does not exist and cannot be activated.');
				else
				{
					$content = getDatabaseContent('welcomeEmail',$sendEmailInLang); // Get content  // and force language for admin - removed $config['settings']['lang_file_mgr']
					$content['name'] = $smarty->fetch('eval:'.$content['name']);
					$content['body'] = $smarty->fetch('eval:'.$content['body']);
					//$member['f_name'].' '.$member['l_name']
					kmail($member['email'],$member['email'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email to confirm account creation
					
					mysqli_query($db,"UPDATE {$dbinfo[pre]}members SET status='1' WHERE umem_id  = '{$id}'");					
					header('location: login.php?logNotice=accountActivated');
					exit;	
				}
			break;
			case "stateList": // Get a list of states
				echo "{\"states\": {";
				
				$stateResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE country_id = '{$_GET[countryID]}' AND active = 1 AND deleted = 0");
				while($state = mysqli_fetch_array($stateResult))
				{
					$stateName =  ($state['name_'.$selectedLanguage]) ? $state['name_'.$selectedLanguage] : $state['name'];
					
					$stateList.= "\"".$state['state_id']."\" : \"".$stateName."\","; // [todo] Choose the right language
				}
				
				$states = substr($stateList,0,-1);
				echo $states;
				
				echo "},\"errorCode\": \"{$_GET[countryID]}\",\"errorMessage\": \"\"}"; // No error messages - everything is OK
				exit;
			break;
			case "checkEmail":
				$emailRows = mysqli_result_patch(mysqli_query($db,"SELECT COUNT(mem_id) FROM {$dbinfo[pre]}members WHERE email='{$email}'"));				
				echo '{"emailsReturned": '.$emailRows.'}';
				exit;
			break;
			case "updateAccountInfo": // Update members account info
				
				if(!$umem_id) exit; // Make sure a umem_id was passed
				
				//$_SESSION['testing']['umem_id'] = $_POST['umem_id'];
				
				$memberResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}members WHERE umem_id = '{$umem_id}'"); // Select the member info from the database just to be sure
				$memberRows = mysqli_num_rows($memberResult);
				$memberDB = mysqli_fetch_assoc($memberResult);
				
				//$_SESSION['testing']['memberRows'] = $memberRows;
				
				$memberAddressResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}members_address WHERE member_id = '{$memberDB[mem_id]}'"); // Select the member address info from the database just to be sure
				$memberAddressRows = mysqli_num_rows($memberAddressResult);
				$memberAddress = mysqli_fetch_assoc($memberAddressResult);
				
				$invFirstName = $memberDB['f_name'];
				$invLastName = $memberDB['l_name'];
				$invFullName = $memberDB['f_name'].' '.$memberDB['l_name'];
				$invEmail = $memberDB['email'];
				$invPhone = $memberDB['phone'];
				$invAddress = $memberAddress['address'];
				$invAddress2 = $memberAddress['address2'];
				$invCity = $memberAddress['city'];
				$invState = $memberAddress['state'];
				$invCountry = $memberAddress['country'];
				$invPostalCode = $memberAddress['postal_code'];
				
				if(!$memberRows) exit; // Make sure a member was selected
				
				if($config['settings']['notify_profile'])
				{
					try
					{		
						$smarty->assign('member',$memberDB);
						$smarty->assign('mode',$_REQUEST['mode']);
						
						$content = getDatabaseContent('memInfoUpdateEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
						$content['name'] = $smarty->fetch('eval:'.$content['name']);
						$content['body'] = $smarty->fetch('eval:'.$content['body']);
						kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email about new tag submitted
					}
					catch(Exception $e)
					{
						//echo $e->getMessage();
					}
				}
				
				switch($_REQUEST['mode'])
				{
					case "membership":
						$membershipDBResult = mysqli_query($db,
							"
							SELECT *
							FROM {$dbinfo[pre]}memberships
							WHERE ums_id = '{$membership}'
							"
						);
						$membershipDB = mysqli_fetch_array($membershipDBResult);
						
						$trialedMemberships = explode(",",$memberDB['trialed_memberships']);
						$usedMemberships = explode(",",$memberDB['used_memberships']);
						$feeMemberships = explode(",",$memberDB['fee_memberships']);
						
						if($membership) // Only attempt to change if the membership is passed ($memberDB['membership'] != $membershipDB['ms_id'])
						{
							switch($membershipDB['mstype'])
							{
								case "free":
									/*
									* Free membership - update
									*/
									$sql = 
									"
										UPDATE {$dbinfo[pre]}members SET 
										ms_end_date='0000-00-00 00:00:00',
										membership='{$membershipDB[ms_id]}'
										WHERE mem_id = '{$memberDB[mem_id]}'
									";
									$result = mysqli_query($db,$sql); // Update database
									
									$newMembership = new memberTools($memberDB['mem_id']);
									$_SESSION['member']['membershipDetails'] = $newMembership->getMembershipInfoFromDB($membershipDB['ms_id']); // Update session
									$_SESSION['member']['membership'] = $membershipDB['ms_id'];
									$_SESSION['member']['ms_end_date'] = '0000-00-00 00:00:00';
									
									if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubUpdateMembership'],0,"<strong>{$membershipDB[name]} ({$membershipDB[ms_id]})</strong>"); // Make entry in the activity log db - only if member is logged in
								break;
								case "onetime":
								case "recurring":
									if($membershipDB['trail_status'] and !in_array($membership,$trialedMemberships)) // OK for trial
									{
										/*
										* Trial available - upgrade
										*/
										$trialEndDate = gmdate("Y-m-d h:i:s",strtotime("+{$membershipDB[trial_length_num]} {$membershipDB[trial_length_period]}"));
										
										$trialedMembershipsUpdated = $memberDB['trialed_memberships'].",".$membership;
										
										$sql = 
										"
											UPDATE {$dbinfo[pre]}members SET 
											ms_end_date='{$trialEndDate}',
											membership='{$membershipDB[ms_id]}',
											trialed_memberships='{$trialedMembershipsUpdated}'
											WHERE mem_id = '{$memberDB[mem_id]}'
										";
										$result = mysqli_query($db,$sql); // Update database
										
										$newMembership = new memberTools($memberDB['mem_id']);
										$_SESSION['member']['membershipDetails'] = $newMembership->getMembershipInfoFromDB($membershipDB['ms_id']); // Update session
										$_SESSION['member']['membership'] = $membershipDB['ms_id'];
										$_SESSION['member']['ms_end_date'] = $trialEndDate;
										$_SESSION['member']['trialed_memberships'][] = $membership;
									}
									else
									{	
										$billResult = mysqli_query($db,
											"
											SELECT *
											FROM {$dbinfo[pre]}billings
											LEFT JOIN {$dbinfo[pre]}invoices
											ON {$dbinfo[pre]}invoices.bill_id = {$dbinfo[pre]}billings.bill_id
											WHERE {$dbinfo[pre]}billings.membership != 0
											AND {$dbinfo[pre]}invoices.invoice_mem_id = '{$memberDB[mem_id]}'
											AND {$dbinfo[pre]}invoices.payment_status = 2 
											"
										);
										if(mysqli_num_rows($billResult)) // Check to make sure that a membership bill wasn't already created 
										{	
											while($bill = mysqli_fetch_array($billResult)) // Delete any membership bills that are unpaid
											{
												//$sql = "UPDATE {$dbinfo[pre]}billings SET deleted='1' WHERE bill_id  = '{$bill[bill_id]}'";
												//$result = mysqli_query($db,$sql);
												
												$sql = "UPDATE {$dbinfo[pre]}invoices SET payment_status='6' WHERE invoice_id  = '{$bill[invoice_id]}'";
												$result = mysqli_query($db,$sql);
											}	
										}
										
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
												'{$memberDB[mem_id]}',
												'1',
												'{$membershipDB[ms_id]}'
											)
											"
										);
										$saveid = mysqli_insert_id($db);
										
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
												'{$memberDB[mem_id]}',
												'{$saveid}',
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
										
										if(!in_array($membership,$feeMemberships)) // Check if signup fee has already been paid in the past
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
										
										if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubUpdateMembership'],0,"<strong>{$membershipDB[name]} ({$membershipDB[ms_id]})</strong>"); // Make entry in the activity log db - only if member is logged in
										
										echo '{"errorCode": "createBill","errorMessage": ""}';
										exit;
									}
								break;
							}
						}
					break;
					case "personalInfo":						
						if($_SESSION['member']['email'] != $email) // Email has changed - check db to make sure the new one doesn't already exist
						{
							$emailRows = mysqli_result_patch(mysqli_query($db,"SELECT COUNT(mem_id) FROM {$dbinfo[pre]}members WHERE email='{$email}'"));
							
							if($emailRows > 0)
							{
								echo '{"errorCode": "emailExists","errorMessage": "'.$lang['accountInfoError12'].'"}';
								exit;
							}
						}
						
						$_SESSION['testing']['pi'] = 1;
						
						$sql = 
						"
							UPDATE {$dbinfo[pre]}members SET 
							f_name='{$f_name}',
							l_name='{$l_name}',
							display_name='{$display_name}',
							email='{$email}',
							comp_name='{$comp_name}',
							website='{$website}',
							phone='{$phone}'
							WHERE umem_id = '{$umem_id}'
						";
						$result = mysqli_query($db,$sql); // Update database
						
						$_SESSION['member']['f_name']	= $f_name;
						$_SESSION['member']['l_name']	= $l_name;
						$_SESSION['member']['email']	= $email;
						$_SESSION['member']['display_name']	= $display_name;
						$_SESSION['member']['comp_name']= $comp_name;
						$_SESSION['member']['website']	= $website;
						$_SESSION['member']['phone']	= $phone;
						
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubAccountInfo'],0,"<strong>$mgrlang[pubUpdate]</strong>"); // Make entry in the activity log db - only if member is logged in
						
					break;
					case "batchUploader":						
						
						$sql = 
						"
							UPDATE {$dbinfo[pre]}members SET 
							uploader='{$batchUploader}' 
							WHERE umem_id = '{$umem_id}'
						";
						$result = mysqli_query($db,$sql); // Update database
						
						$_SESSION['member']['uploader'] = $batchUploader;
						
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubAccountInfo'],0,"<strong>$mgrlang[pubUpdate]</strong>"); // Make entry in the activity log db - only if member is logged in
						
					break;										
					case "bio":
						if($_SESSION['member']['membershipDetails']['ms_id'])
						{
							$membershipResult = mysqli_query($db,
							"
								SELECT bio,bio_approval
								FROM {$dbinfo[pre]}memberships 
								WHERE ms_id = '{$_SESSION[member][membershipDetails][ms_id]}'
								LIMIT 1
							"
							); // Pull membership info
							$membershipRows = mysqli_num_rows($membershipResult); // Rows from query
							if($membershipRows)
								$membership = mysqli_fetch_array($membershipResult);
							
							if($membership['bio'])
							{
								$sql = 
								"
									UPDATE {$dbinfo[pre]}members SET 
									bio_content='{$bio_content}',
									bio_status='{$membership[bio_approval]}'
									WHERE umem_id = '{$umem_id}'
								";
								$result = mysqli_query($db,$sql); // Update database						
								$_SESSION['member']['bio_content']	= $bio_content;
							
								if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubBio'],0,"<strong>$mgrlang[pubUpdate]</strong>"); // Make entry in the activity log db - only if member is logged in
							}
						}
					break;					
					case "address":
						if($_SESSION['member']['primaryAddress'])
						{
							$sql = 
							"
								UPDATE {$dbinfo[pre]}members_address SET 
								address='{$address}',
								address_2='{$address_2}',
								city='{$city}',
								state='{$state}',
								postal_code='{$postal_code}',
								country='{$country}'
								WHERE member_id='{$memberDB[mem_id]}'
							";
							$result = mysqli_query($db,$sql); // Update database	
							
							$stateDBResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE state_id = '{$state}'"); // Select state info
							$stateDB = mysqli_fetch_assoc($stateDBResult);
							
							$countryDBResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}countries WHERE country_id = '{$country}'"); // Select country info
							$countryDB = mysqli_fetch_assoc($countryDBResult);
												
							$_SESSION['member']['primaryAddress']['address']	= $address;
							$_SESSION['member']['primaryAddress']['address_2']	= $address_2;
							$_SESSION['member']['primaryAddress']['city']		= $city;
							$_SESSION['member']['primaryAddress']['state']		= $stateDB['name']; // [todo] Select the correct language
							$_SESSION['member']['primaryAddress']['stateID']	= $state;
							$_SESSION['member']['primaryAddress']['postal_code']= $postal_code;
							$_SESSION['member']['primaryAddress']['country']	= $countryDB['name']; // [todo] Select the correct language
							$_SESSION['member']['primaryAddress']['countryID']	= $country;
							
							if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubAddress'],0,"<strong>$mgrlang[pubUpdate]</strong>"); // Make entry in the activity log db - only if member is logged in
						}
					break;					
					case "password":
						$currentPassEnc = k_encrypt($currentPass); // Encrypt password
						
						if($currentPassEnc != $memberDB['password']) // Make sure the current password matches what the members entered
						{
							echo '{"errorCode": "incorrectPassword","errorMessage": "{$lang[accountInfoError5]}"}'; // Return error
							exit;
						}
						
						if($newPass != $vNewPass) // Make sure the new password and verify passwords match - already checked in JS so this is a backup
						{
							echo '{"errorCode": "newPasswordDiff","errorMessage": "'.$lang['accountInfoError1'].'"}'; // Return error
							exit;
						}
						
						if(strlen($newPass) < 6) // Check the password length
						{
							echo '{"errorCode": "shortPass","errorMessage": "'.$lang['accountInfoError2'].'"}'; // Return error
							exit;
						}
						
						$newPassEnc = k_encrypt($newPass); // Encrypt new pass
						
						$sql = 
						"
							UPDATE {$dbinfo[pre]}members SET 
							password='{$newPassEnc}'
							WHERE mem_id='{$memberDB[mem_id]}'
						";
						$result = mysqli_query($db,$sql); // Update database	
						
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubPassword'],0,"<strong>$mgrlang[pubUpdate]</strong>"); // Make entry in the activity log db - only if member is logged in
					break;
					case "dateTime":						
						switch($numberDateSep)
						{
							case 'slash':
								$numberDateSepUpdated = '/';
							break;
							case 'period':
								$numberDateSepUpdated = '.';
							break;
							case 'dash':
								$numberDateSepUpdated = '-';
							break;
						}
						
						$sql = 
						"
							UPDATE {$dbinfo[pre]}members SET 
							customized_date='1',
							time_zone='{$timeZone}',
							daylight_savings='{$daylightSavings}',
							date_format='{$dateFormat}',
							date_display='{$dateDisplay}',
							clock_format='{$clockFormat}',
							number_date_sep='{$numberDateSepUpdated}'
							WHERE umem_id = '{$umem_id}'
						";
						$result = mysqli_query($db,$sql); // Update database
						
						$_SESSION['member']['time_zone']	= $timeZone;
						$_SESSION['member']['daylight_savings']	= ($daylightSavings) ? 1 : 0;
						$_SESSION['member']['date_format']	= $dateFormat;
						$_SESSION['member']['date_display']	= $dateDisplay;
						$_SESSION['member']['clock_format']= $clockFormat;
						$_SESSION['member']['number_date_sep']	= $numberDateSepUpdated;
						
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubDateTime'],0,"<strong>$mgrlang[pubUpdate]</strong>"); // Make entry in the activity log db - only if member is logged in
					break;
					case "avatar":
						if($delete)
						{
							$memberResult = mysqli_query($db,"SELECT mem_id,avatar,f_name,l_name FROM {$dbinfo[pre]}members WHERE umem_id = '{$umem_id}'");
							$memberRows = mysqli_num_rows($memberResult);
							$member = mysqli_fetch_array($memberResult);
							
							if(file_exists(BASE_PATH."/assets/avatars/".$memberDB['mem_id']."_large.png"))
							{
								if(unlink(BASE_PATH."/assets/avatars/".$memberDB['mem_id']."_large.png")) // Delete large version of the avatar
									mysqli_query($db,"UPDATE {$dbinfo[pre]}members SET avatar='0',avatar_status='0' WHERE umem_id = '{$umem_id}'");
							}
							
							if(file_exists(BASE_PATH."/assets/avatars/".$memberDB['mem_id']."_small.png")) // Delete small version of the avatar
								@unlink(BASE_PATH."/assets/avatars/".$memberDB['mem_id']."_small.png");
								
							$_SESSION['member']['avatar'] = ''; // Clean up member session
							
							if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubAvatar'],0,"<strong>$mgrlang[pubDelete]</strong>"); // Make entry in the activity log db - only if member is logged in
						}
					break;
					
					case "avatarUpload":
						//kmail($config['settings']['sales_email'],$config['settings']['business_name'],'mail@jonkent.com','jon kent','test',"test-{$umem_id}-end"); // Send email to sales email
						//exit;
						
						/*
						* Security check
						*/
						$checkToken = md5($config['settings']['serial_number'].$_POST['securityTimestamp']);
						if($checkToken != $_POST['securityToken']) exit;
						
						$membershipResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}memberships WHERE ms_id = '{$ms_id}'"); // Select the membership info from the database
						$membershipRows = mysqli_num_rows($membershipResult);
						$membership = mysqli_fetch_assoc($membershipResult);
						
						$temp_filename = strtolower($_FILES['Filedata']['name']);
						
						$_SESSION['testing']['name'] = $_FILES['Filedata']['name'];
						$_SESSION['testing']['ms_id'] = $ms_id;
						
						$temp_array = explode(".",$temp_filename);
						$avatar_extension = $temp_array[count($temp_array)-1];
						$avatar_filename = $memberDB['mem_id'] . "_tmp_avatar." . $avatar_extension;
						move_uploaded_file($_FILES['Filedata']['tmp_name'], BASE_PATH."/assets/avatars/".$avatar_filename);
						
						# CREATE SMALL ICON
						if(file_exists(BASE_PATH."/assets/avatars/" . $avatar_filename)){
							# FIGURE MEMORY NEEDED
							$mem_needed = figure_memory_needed(BASE_PATH."/assets/avatars/" . $avatar_filename);
							if(ini_get("memory_limit")){
								$memory_limit = ini_get("memory_limit");
							} else {
								$memory_limit = $config['DefaultMemory'];
							}
							if($memory_limit > $mem_needed){
								$src = BASE_PATH."/assets/avatars/" . $avatar_filename;
								$size = getimagesize($src);			
								switch($avatar_extension){
									case "jpeg":
									case "jpg":
										$src_img = imagecreatefromjpeg($src);
									break;
									case "gif":
										$src_img = imagecreatefromgif($src);
									break;
									case "png":
										$src_img = imagecreatefrompng($src);
									break;
								}
								
								# CREATE THE LARGE AVATAR
								$icon_width = 500;
								//FIND THE SCALE RATIOS		
								if($size[0] >= $size[1]){
									if($size[0] > $icon_width){
										$width = $icon_width;
									} else {
										$width = $size[0];
									}
									$ratio = $width/$size[0];
									$height = $size[1] * $ratio;				
								} else {
									if($size[1] > $icon_width){
										$height = $icon_width;	
									} else {
										$height = $size[1];	
									}
									$ratio = $height/$size[1];
									$width = $size[0] * $ratio;
								}
								
								$dst_img = imagecreatetruecolor($width, $height);	
								
								# KEEP TRANSPARENCY
								imagealphablending($dst_img, false);
								imagesavealpha($dst_img,true);
								$transparent = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
								imagefilledrectangle($dst_img, 0, 0, $width, $height, $transparent);
								# END KEEP TRANSPARENCY
												
								imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $width, $height, imagesx($src_img), imagesy($src_img));						
								
								imagepng($dst_img,BASE_PATH."/assets/avatars/" . $memberDB['mem_id'] . '_large.png', $config['SaveAvatarQuality']); // SAVE THIS OUT
								imagedestroy($dst_img);
								
								# CREATE THE SMALL AVATAR
								$icon_width = 19;
								//FIND THE SCALE RATIOS		
								if($size[0] >= $size[1]){
									if($size[0] > $icon_width){
										$width = $icon_width;
									} else {
										$width = $size[0];
									}
									$ratio = $width/$size[0];
									$height = $size[1] * $ratio;				
								} else {
									if($size[1] > $icon_width){
										$height = $icon_width;	
									} else {
										$height = $size[1];	
									}
									$ratio = $height/$size[1];
									$width = $size[0] * $ratio;
								}
								$dst_img = imagecreatetruecolor($width, $height);
								
								# KEEP TRANSPARENCY
								imagealphablending($dst_img, false);
								imagesavealpha($dst_img,true);
								$transparent = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
								imagefilledrectangle($dst_img, 0, 0, $width, $height, $transparent);
								# END KEEP TRANSPARENCY
													
								imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $width, $height, imagesx($src_img), imagesy($src_img));						
								# SAVE AND DESTROY
								imagepng($dst_img,BASE_PATH."/assets/avatars/" . $memberDB['mem_id'] . '_small.png', $config['SaveAvatarQuality']); // SAVE THIS OUT
								imagedestroy($src_img); 
								imagedestroy($dst_img);
								
								$avatarApproval = ($membership['avatar_approval'] == 1) ? 1 : 2;
								
								# UPDATE THE DATABASE
								$sql = "UPDATE {$dbinfo[pre]}members SET avatar='1',avatar_updated='" . gmt_date() . "',avatar_status='{$avatarApproval}' WHERE umem_id  = '{$umem_id}'";
								$result = mysqli_query($db,$sql);
							}
							
							# DELETE THE ORIGINAL
							@unlink(BASE_PATH."/assets/avatars/" . $avatar_filename);
							
							if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubAvatar'],0,"<strong>$mgrlang[pubNew]</strong>"); // Make entry in the activity log db - only if member is logged in
						}
					break;
					case "commission":
						if($umem_id) // Make sure info is passed
						{
							$sql = 
							"
								UPDATE {$dbinfo[pre]}members SET 
								compay='{$commissionType}',
								paypal_email='{$paypalEmail}'
								WHERE umem_id = '{$umem_id}'
							";
							$result = mysqli_query($db,$sql); // Update database
							
							$_SESSION['member']['paypal_email']	= $paypalEmail;
							$_SESSION['member']['compay']	= $commissionType;
						}
					break;	
				}
				
				echo '{"errorCode": "0","errorMessage": ""}'; // No error messages - everything is OK
				
			break;
			
			case "newLightbox":
				
				$ulightbox_id = create_unique2();
			
				$guestLightbox = ($_SESSION['loggedIn']) ? 0 : 1;
			
				// Create lightbox record
				mysqli_query($db,
					"
					INSERT INTO {$dbinfo[pre]}lightboxes 
					(
						ulightbox_id,
						member_id,
						umember_id,
						name,
						created,
						notes,
						guest
					)
					VALUES
					(
						'{$ulightbox_id}',
						'{$mem_id}',
						'{$umem_id}',
						'{$lightboxName}',
						'{$nowGMT}',
						'{$lightboxNotes}',
						'{$guestLightbox}'
					)
					"
				);
				$saveid = mysqli_insert_id($db);
				
				if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubCreate]} > <strong>{$lightboxName} ({$saveid})</strong>"); // Make entry in the activity log db - only if member is logged in
			
				echo '{"errorCode": "lightboxCreated","errorMessage": ""}'; // No error messages - everything is OK
				
				if($config['settings']['notify_lightbox'])
				{
					if($_SESSION['loggedIn'])
						$user = $_SESSION['member']['f_name'].' '.$_SESSION['member']['l_name']; // User name from member name	
					else
						$user = $mgrlang['gen_visitor']; // User is guest
					
					try
					{
						if($config['EncryptIDs'])
							$useLightboxID = k_encrypt($ulightbox_id);
						else
							$useLightboxID = $ulightbox_id;
						
						$lightboxLink = "gallery.php?mode=lightbox&id={$useLightboxID}&page=1";
						
						$smarty->assign('lightboxLink',$lightboxLink);
						$smarty->assign('user',$user);
						$smarty->assign('lightboxName',$lightboxName);
						$smarty->assign('description',$lightboxNotes);
						$smarty->assign('language',$language);
						$smarty->assign('autoApprove',$autoApprove);
						
						$content = getDatabaseContent('newLightboxEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
						$content['name'] = $smarty->fetch('eval:'.$content['name']);
						$content['body'] = $smarty->fetch('eval:'.$content['body']);
						kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email about new tag submitted
					}
					catch(Exception $e)
					{
						//echo $e->getMessage();
					}
					
				}
				
			break;
			
			case "editLightbox":
				
				$lightboxResult = mysqli_query($db,
				"
					SELECT lightbox_id
					FROM {$dbinfo[pre]}lightboxes  
					WHERE ulightbox_id = '{$ulightbox_id}'
					AND umember_id = '{$umem_id}'
				"
				); 
				$lightboxRows = mysqli_num_rows($lightboxResult); // Rows from query
				
				if($lightboxRows)
				{
					$lightbox = mysqli_fetch_array($lightboxResult);
					
					mysqli_query($db,"UPDATE {$dbinfo[pre]}lightboxes SET name='{$lightboxName}',notes='{$lightboxNotes}' WHERE umember_id = '{$umem_id}' AND ulightbox_id = '{$ulightbox_id}'"); // Update the lightbox in the db				
					echo '{"errorCode": "lightboxSaved","errorMessage": ""}'; // No error messages - everything is OK
				
					if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubUpdate]} > <strong>{$lightboxName} ({$lightbox[lightbox_id]})</strong>"); // Make entry in the activity log db - only if member is logged in
				}
				
			break;
			
			case "addToLightbox":
				if($mediaID and $umem_id) // Make sure a mediaID and umem_id was passed
				{
					if($lightbox) // Current lightbox
					{
						$lbItemResult = mysqli_query($db,
						"
							SELECT item_id
							FROM {$dbinfo[pre]}lightbox_items  
							WHERE lb_id = '{$lightbox}'
							AND media_id = '{$mediaID}'
						"
						); // Make sure the item is not already in this lightbox
						$lbItemRows = mysqli_num_rows($lbItemResult); // Rows from query

						if(!$lbItemRows) // Only add if it isn't already in there
						{
							// Create lightbox item record
							mysqli_query($db,
								"
								INSERT INTO {$dbinfo[pre]}lightbox_items 
								(
									media_id,
									lb_id,
									date_added,
									notes
								)
								VALUES
								(
									'{$mediaID}',
									'{$lightbox}',
									'{$nowGMT}',
									'{$mediaNotes}'
								)
								"
							);
							$saveid = mysqli_insert_id($db);
							
							$_SESSION['selectedLightbox'] = $lightbox; // Update selected lightbox
							
							$_SESSION['lightboxItems'][$saveid] = $mediaID; // Add item to the lightboxItems session array
							
							if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubAddItem]} > <strong>{$mgrlang[pubMedia]} ({$mediaID})</strong>"); // Make entry in the activity log db - only if member is logged in
						}
					}
					else // New lightbox
					{
						$ulightbox_id = create_unique2();
						
						$guestLightbox = ($_SESSION['loggedIn']) ? 0 : 1;
						
						// Create lightbox record
						mysqli_query($db,
							"
							INSERT INTO {$dbinfo[pre]}lightboxes 
							(
								ulightbox_id,
								member_id,
								umember_id,
								name,
								created,
								notes,
								guest
							)
							VALUES
							(
								'{$ulightbox_id}',
								'{$mem_id}',
								'{$umem_id}',
								'{$lightboxName}',
								'{$nowGMT}',
								'{$lightboxNotes}',
								'{$guestLightbox}'
							)
							"
						);
						$saveid = mysqli_insert_id($db);
						
						$_SESSION['selectedLightbox'] = $saveid; // Update selected lightbox
						
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubNew]} > <strong>{$lightboxName} ({$saveid})</strong>"); // Make entry in the activity log db - only if member is logged in
						
						
						if($config['settings']['notify_lightbox'])
						{
							if($_SESSION['loggedIn'])
								$user = $_SESSION['member']['f_name'].' '.$_SESSION['member']['l_name']; // User name from member name	
							else
								$user = $mgrlang['gen_visitor']; // User is guest
							
							try
							{
								
								$lightboxLink = "gallery.php?mode=lightbox&id={$ulightbox_id}&page=1";
								
								$smarty->assign('lightboxLink',$lightboxLink);
								$smarty->assign('user',$user);
								$smarty->assign('lightboxName',$lightboxName);
								$smarty->assign('description',$lightboxNotes);
								$smarty->assign('language',$language);
								$smarty->assign('autoApprove',$autoApprove);
								
								$content = getDatabaseContent('newLightboxEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
								$content['name'] = $smarty->fetch('eval:'.$content['name']);
								$content['body'] = $smarty->fetch('eval:'.$content['body']);
								kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email about new tag submitted
							}
							catch(Exception $e)
							{
								//echo $e->getMessage();
							}
							
						}
						
						// Create lightbox item record
						mysqli_query($db,
							"
							INSERT INTO {$dbinfo[pre]}lightbox_items 
							(
								media_id,
								lb_id,
								date_added,
								notes
							)
							VALUES
							(
								'{$mediaID}',
								'{$saveid}',
								'{$nowGMT}',
								'{$mediaNotes}'
							)
							"
						);
						$saveid = mysqli_insert_id($db);
						
						$_SESSION['lightboxItems'][$saveid] = $mediaID; // Add item to the lightboxItems session array
						
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubAddItem]} > <strong>{$mgrlang[pubMedia]} ({$mediaID})</strong>"); // Make entry in the activity log db - only if member is logged in
					}
					
					echo '{"errorCode": "addedToLightbox","errorMessage": "","mediaID": "'.$mediaID.'","lightboxItemID": "'.$saveid.'"}'; // No error messages - everything is OK
				}
			break;
			case "removeItemFromLightbox":
				if($umem_id == $_SESSION['member']['umem_id']) // Check post member against session for extra security
				{
					$lightboxItemResult = mysqli_query($db,
					"
						SELECT media_id
						FROM {$dbinfo[pre]}lightbox_items  
						WHERE item_id = '{$lightboxItemID}'
					"
					); 
					$lightboxItemRows = mysqli_num_rows($lightboxItemResult); // Rows from query
					
					if($lightboxItemRows)
					{
						$lightboxItem = mysqli_fetch_array($lightboxItemResult);
						
						mysqli_query($db,"DELETE FROM {$dbinfo[pre]}lightbox_items WHERE item_id = '{$lightboxItemID}'"); // Remove lightbox item from DB
					
						unset($_SESSION['lightboxItems'][$lightboxItemID]); // Remove lightbox item from session
					
						echo '{"errorCode": "removeItemFromLightbox","errorMessage": "","lightboxItemID": "'.$lightboxItemID.'"}'; // No error messages - everything is OK
					
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubRemoveItem]} > <strong>{$mgrlang[pubMedia]} ({$lightboxItem[media_id]})</strong>"); // Make entry in the activity log db - only if member is logged in
					}
				}
			break;
			case "editLightboxItem":
				if($umem_id == $_SESSION['member']['umem_id']) // Check post member against session for extra security
				{
					$lightboxItemResult = mysqli_query($db,
					"
						SELECT media_id
						FROM {$dbinfo[pre]}lightbox_items  
						WHERE item_id = '{$lightboxItemID}'
					"
					); 
					$lightboxItemRows = mysqli_num_rows($lightboxItemResult); // Rows from query
					
					if($lightboxItemRows)
					{
						$lightboxItem = mysqli_fetch_array($lightboxItemResult);
						
						mysqli_query($db,"UPDATE {$dbinfo[pre]}lightbox_items SET notes='{$mediaNotes}' WHERE item_id = '{$lightboxItemID}'");
						echo '{"errorCode": "editLightboxItem","errorMessage": "","lightboxItemID": "'.$lightboxItemID.'"}'; // No error messages - everything is OK
					
						if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubUpdate]} > <strong>{$mgrlang[pubMedia]} ({$lightboxItem[media_id]})</strong>"); // Make entry in the activity log db - only if member is logged in
					}
				}
			break;
			case "emailToFriend":
				if($config['EncryptIDs']) // Decrypt IDs
					$mediaID = k_decrypt($mediaID);
					
				idCheck($mediaID); // Make sure ID is numeric
					
				
				$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
				$mediaObj = new mediaList($sql);
				
				if($mediaObj->getRows())
				{
					$mediaObj->getMediaDetails(); // Run the getMediaDetails function so I can grab the info later
					$media = $mediaObj->getMediaSingle(); // Grab the single returned media info
					$smarty->assign('media',$media);
				}
				
				/*old				
				$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'"; // Grab some info about the photo
				$mediaInfo = new mediaList($sql);
				
				if($mediaInfo->getRows())
				{
					$media = $mediaInfo->getSingleMediaDetails('thumb');
					$media['useMediaID'] =($config['EncryptIDs']) ? k_encrypt($media['media_id']) : $media['media_id']; // See if media ID should be encrypted or not	
				}
				*/
				
				if($_REQUEST['form']['toEmail'])
				{	
					if($_SESSION['loggedIn'])
					{
						// If logged in use customer name
						$form['fromEmail'] = $_SESSION['member']['email'];
						$form['fromName'] = $_SESSION['member']['f_name'].' '.$_SESSION['member']['l_name'];
					}
					
					$form['message'] = str_replace('\r\n', "<br>", $form['message']); // Replace break characters
					
					$smarty->assign('form',$form);
					
					$content = getDatabaseContent('emailFriendMedia'); // Get content from db				
					$content['name'] = $smarty->fetch('eval:'.$content['name']);
					$content['body'] = $smarty->fetch('eval:'.$content['body']);
					
					$options['replyEmail'] = $form['fromEmail'];
					$options['replyName'] = $form['fromName'];
					
					//kmail($config['settings']['sales_email'],$config['settings']['business_name'],$config['settings']['sales_email'],$lang['contactFromName'],$content['name'],$content['body'],$options); // Send email to sales email		

					/*old
					$subject = "{$lang[linkSentBy]} {$fromName}"; // Create a subject for the email
					$mediaThumb = "{$config[settings][site_url]}/image.php?mediaID={$media[encryptedID]}&type=thumb&folderID={$media[encryptedFID]}"; // Link to the thumbnail
					$mediaPageLink = "{$config[settings][site_url]}/media.details.php?mediaID={$media[useMediaID]}";
					$message = "<table cellpadding='10'><tr><td valign='top'><a href='{$mediaPageLink}'><img src='{$mediaThumb}'></a></td><td valign='top'>{$subject}<br /><br />{$emailMessage}<br /><br /><a href='{$mediaPageLink}'>{$mediaPageLink}</a></td></tr></table>";
					*/
					
					kmail($form['toEmail'],$form['toEmail'],$config['settings']['support_email'],$form['fromName'],$content['name'],$content['body'],$options); // Send email
					
					echo '{"errorCode": "sentEmailToFriend","errorMessage": ""}'; // No error messages - everything is OK
					
					//if($_SESSION['loggedIn']) save_activity($_SESSION['member']['mem_id'],$mgrlang['pubLightbox'],0,"{$mgrlang[pubUpdate]} > <strong>{$mgrlang[pubMedia]} ({$lightboxItem[media_id]})</strong>"); // Make entry in the activity log db - only if member is logged in
				}
				
			break;
			case "newComment":
				if($config['EncryptIDs']) // Decrypt IDs
					$mediaID = k_decrypt($mediaID);
					
				idCheck($mediaID); // Make sure ID is numeric
				
				if($mediaID and $newComment and $formKey == $_SESSION['formKey']) // Make sure both the media id and a new comment were passed and that the protective formKey is correct
				{
					$newComment = strip_tags(str_ireplace($blockedWords,'****',$newComment));
					
					if($_SESSION['loggedIn'])
					{
						$user = $_SESSION['member']['f_name'].' '.$_SESSION['member']['l_name']; // User name from member name
						
						save_activity($_SESSION['member']['mem_id'],$mgrlang['pubNewComment'],0,"<strong>({$mediaID})</strong>"); // Make entry in the activity log db - only if member is logged in
						$autoApprove = ($_SESSION['member']['membershipDetails']['comment_approval']) ? 1 : 0; // Check if this should auto approve - check membership	
					}
					else
					{
						$user = $mgrlang['gen_visitor']; // User is guest
						
						$autoApprove = ($config['settings']['comment_system_aa']) ? 1 : 0; // Check if this should auto approve - check guest settings
					}
					
					$memberID = $_SESSION['member']['mem_id']; // member id
					
					$language = strtoupper($_SESSION['member']['language']); // current member language
				
					// Insert comment to db
					mysqli_query($db,
						"
						INSERT INTO {$dbinfo[pre]}media_comments 
						(
							media_id,
							comment,
							posted,
							language,
							status,
							member_id
						)
						VALUES
						(
							'{$mediaID}',
							'{$newComment}',
							'{$nowGMT}',
							'{$language}',
							'{$autoApprove}',
							'{$memberID}'
						)
						"
					);
					$saveid = mysqli_insert_id($db);
					
					if($autoApprove)
						echo '{"errorCode": "newCommentApproved","errorMessage": "'.$lang['commentPosted'].'","commentID": "'.$saveid.'"}'; // Send confirmation
					else
						echo '{"errorCode": "newCommentPending","errorMessage": "'.$lang['commentPending'].'","commentID": "'.$saveid.'"}'; // Send confirmation
						
						
					if($config['settings']['notify_comment'])
					{
						
						try
						{
							$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
							$mediaObj = new mediaList($sql);
							
							if($mediaObj->getRows())
							{
								$mediaObj->getMediaDetails(); // Run the getMediaDetails function so I can grab the info later
								$media = $mediaObj->getMediaSingle(); // Grab the single returned media info
								
								//$thumbObj = new mediaTools($mediaID);
								//$thumbnail = $thumbObj->getThumbInfoFromDB();
								
								$smarty->assign('media',$media);
							}
	
							$smarty->assign('user',$user);
							$smarty->assign('comment',$newComment);
							$smarty->assign('language',$language);
							$smarty->assign('autoApprove',$autoApprove);
							
							$content = getDatabaseContent('newCommentEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
							$content['name'] = $smarty->fetch('eval:'.$content['name']);
							$content['body'] = $smarty->fetch('eval:'.$content['body']);
							kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email about new tag submitted
						}
						catch(Exception $e)
						{
							//echo $e->getMessage();
						}
						
					}
				}
				else
					echo '{"errorCode": "newCommentFailed","errorMessage": "'.$lang['commentError'].'","commentID": "0"}'; // Send error
			break;
			case "newTag":
				if($config['EncryptIDs']) // Decrypt IDs
					$mediaID = k_decrypt($mediaID);
					
				idCheck($mediaID); // Make sure ID is numeric
				
				$newTag = trim($newTag); // Trim any spaces from the ends
				
				if($config['keywordsToLower'])
				{
					if($selectedLanguage == 'russian')
						$newTag = mb_convert_case($newTag, MB_CASE_LOWER, "UTF-8");
					else
						$newTag = strtolower($newTag);
					
					//$newTag = strtolower($newTag); // Make sure it is lower case
				}
				
				if($mediaID and $newTag and $formKey == $_SESSION['formKey']) // Make sure both the media id and a new tag were passed and that the protective formKey is correct
				{	
					// Check word filter
					$tagSplit = explode(' ',$newTag);
					foreach($tagSplit as $testTag)
					{
						if(in_array($testTag,$blockedWords))
						{
							echo '{"errorCode": "newTagPending","errorMessage": "'.$lang['tagNotAccepted'].'","tagID": "0"}'; // // Word blocked
							exit;
						}
					}
					
					// Check if tag already exists
					$checkTagResult = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}keywords WHERE media_id = '{$mediaID}' AND keyword = '{$newTag}'");
					if(!getRows())
					{
						if($_SESSION['loggedIn'])
						{
							$user = $_SESSION['member']['f_name'].' '.$_SESSION['member']['l_name']; // User name from member name
							save_activity($_SESSION['member']['mem_id'],$mgrlang['pubNewTag'],0,"<strong>({$mediaID})</strong>"); // Make entry in the activity log db - only if member is logged in
							$autoApprove = ($_SESSION['member']['membershipDetails']['tagging_approval']) ? 1 : 0; // Check if this should auto approve - check membership	
						}
						else
						{
							$user = $mgrlang['gen_visitor']; // User is guest						
							$autoApprove = ($config['settings']['tagging_system_aa']) ? 1 : 0; // Check if this should auto approve - check guest settings
						}
						
						$memberID = $_SESSION['member']['mem_id']; // member id
						
						$language = strtoupper($_SESSION['member']['language']); // current member language
					
						// Insert comment to db
						mysqli_query($db,
							"
							INSERT INTO {$dbinfo[pre]}keywords 
							(
								media_id,
								keyword,
								posted,
								language,
								status,
								member_id,
								memtag 
							)
							VALUES
							(
								'{$mediaID}',
								'{$newTag}',
								'{$nowGMT}',
								'{$language}',
								'{$autoApprove}',
								'{$memberID}',
								1
							)
							"
						);
						$saveid = mysqli_insert_id($db);
						
						if($autoApprove)
							echo '{"errorCode": "newTagApproved","errorMessage": "'.$lang['tagPosted'].'","tagID": "'.$saveid.'"}'; // Send confirmation
						else
							echo '{"errorCode": "newTagPending","errorMessage": "'.$lang['tagPending'].'","tagID": "'.$saveid.'"}'; // Send confirmation
						
						
						if($config['settings']['notify_tags'])
						{
							
							try
							{
								$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
								$mediaObj = new mediaList($sql);
								
								if($mediaObj->getRows())
								{
									$mediaObj->getMediaDetails(); // Run the getMediaDetails function so I can grab the info later
									$media = $mediaObj->getMediaSingle(); // Grab the single returned media info
									
									//$thumbObj = new mediaTools($mediaID);
									//$thumbnail = $thumbObj->getThumbInfoFromDB();
									
									$smarty->assign('media',$media);
								}
		
								$smarty->assign('user',$user);
								$smarty->assign('tag',$newTag);
								$smarty->assign('language',$language);
								$smarty->assign('autoApprove',$autoApprove);
								
								$content = getDatabaseContent('newTagEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
								$content['name'] = $smarty->fetch('eval:'.$content['name']);
								$content['body'] = $smarty->fetch('eval:'.$content['body']);
								kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body']); // Send email about new tag submitted
							}
							catch(Exception $e)
							{
								//echo $e->getMessage();
							}
							
						}
					}
					else
						echo '{"errorCode": "newTagFailed","errorMessage": "'.$lang['tagDuplicate'].'","tagID": "0"}'; // Tag already exists
				}
				else
					echo '{"errorCode": "newTagFailed","errorMessage": "'.$lang['tagError'].'","tagID": "0"}'; // Send error
			break;
			case "emailForQuote":
				if($config['EncryptIDs']) // Decrypt IDs
					$mediaID = k_decrypt($mediaID);
				else
					$mediaID = $mediaID;
					
				idCheck($mediaID); // Make sure ID is numeric
				
				$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
				$mediaObj = new mediaList($sql);
				
				if($mediaObj->getRows())
				{
					$mediaObj->getMediaDetails(); // Run the getMediaDetails function so I can grab the info later
					$media = $mediaObj->getMediaSingle(); // Grab the single returned media info
					
					$smarty->assign('media',$media);
				}
				
				$smarty->assign('user',$contactForm['name']);				
				$smarty->assign('contactForm',$contactForm);
				
				if($profileID)
				{				
					$dspResult = mysqli_query($db,"SELECT name,ds_id FROM {$dbinfo[pre]}digital_sizes WHERE ds_id = '{$profileID}'");
					$dsp = mysqli_fetch_assoc($dspResult);
				}
				else
					$dsp['name'] = $mgrlang['gen_base_price'];
				
				$smarty->assign('dsp',$dsp);
				
				$content = getDatabaseContent('quoteEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
				$content['name'] = $smarty->fetch('eval:'.$content['name']);
				$content['body'] = $smarty->fetch('eval:'.$content['body']);
				
				/*
				$_SESSION['testing']['support_email'] = $config['settings']['support_email'];
				$_SESSION['testing']['business_name'] = $config['settings']['business_name'];
				$_SESSION['testing']['email'] = $contactForm['email'];
				$_SESSION['testing']['name'] = $contactForm['name'];
				$_SESSION['testing']['subject'] = $content['name'];
				*/
				
				kmail($config['settings']['support_email'],$config['settings']['business_name'],$contactForm['email'],$contactForm['name'],$content['name'],$content['body']); // Send email about new tag submitted
				
				echo '{"errorCode": "emailSent","errorMessage": "emailSent"}'; // Send confirmation
			break;
			case "downloadNotAvailable":
			case "emailForFile":
				
				if($config['EncryptIDs']) // Decrypt IDs
					$mediaID = k_decrypt($mediaID);
				else
					$mediaID = $mediaID;
					
				idCheck($mediaID); // Make sure ID is numeric
				
				$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'";
				$mediaObj = new mediaList($sql);
				
				if($mediaObj->getRows())
				{
					$mediaObj->getMediaDetails(); // Run the getMediaDetails function so I can grab the info later
					$media = $mediaObj->getMediaSingle(); // Grab the single returned media info
					
					$smarty->assign('media',$media);
				}
				
				
				//test('workedA');
				
				if($profileID)
				{				
					$dspResult = mysqli_query($db,"SELECT name,ds_id FROM {$dbinfo[pre]}digital_sizes WHERE ds_id = '{$profileID}'");
					$dsp = mysqli_fetch_assoc($dspResult);
				}
				else
					$dsp['name'] = $mgrlang['gen_base_price'];
				
				$smarty->assign('dsp',$dsp);
				
				if($_SESSION['loggedIn'])
				{
					$user = $_SESSION['member']['f_name'].' '.$_SESSION['member']['l_name']; // User name from member name
					$email = $_SESSION['member']['email'];
				}
				else
				{
					$user = $mgrlang['gen_visitor']; // User is guest
					$email = $requestDownloadEmail;
				}
				
				$smarty->assign('member',$_SESSION['member']);
				$smarty->assign('user',$user);
				$smarty->assign('email',$email);
				
				$content = getDatabaseContent('requestFileEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
				$content['name'] = $smarty->fetch('eval:'.$content['name']);
				$content['body'] = $smarty->fetch('eval:'.$content['body']);
				kmail($config['settings']['support_email'],$config['settings']['business_name'],$email,$user,$content['name'],$content['body']); // Send email about new tag submitted
				
				echo '{"errorCode": "emailSent","errorMessage": "emailSent"}'; // Send confirmation
				//echo '{"errorCode": "newTagApproved","errorMessage": "'.$lang['tagPosted'].'","tagID": "'.$saveid.'"}'; // Send confirmation
			break;
			case "contrNewAlbum":
				if(!$_SESSION['member']['mem_id']) exit; // Don't continue if no session member id exists
				
				if($newAlbumPublic) // Set the correct permissions for the gallery
				{
					$everyone = 1;
					$perm = 'everyone';
					
					if($newAlbumPassword) $newAlbumPassword = k_encrypt($newAlbumPassword); // Encrypt new album password
				}
				else
				{
					$everyone = 0;
					$perm = 'mem'.$_SESSION['member']['mem_id'];
				}
				
				$ugalleryID = create_unique2();
				
				// Create Gallery
				mysqli_query($db,
					"
					INSERT INTO {$dbinfo[pre]}galleries  
					(
						name,
						owner,
						created,
						active,
						description,
						publicgal,
						password,
						everyone,
						album,
						ugallery_id
					)
					VALUES
					(
						'{$newAlbumName}',
						'{$_SESSION[member][mem_id]}',
						'{$nowGMT}',
						'1',
						'{$newAlbumDescription}',
						'{$newAlbumPublic}',
						'{$newAlbumPassword}',
						'{$everyone}',
						'1',
						'{$ugalleryID}'
					)
					"
				);
				$saveid = mysqli_insert_id($db);
				$page = 'galleries';
				
				save_mem_permissions();
				
				$_SESSION['member']['contrAlbumsQueried'] = 0; // Make sure the albums are reloaded
				
				echo '{"errorCode": "newAlbumCreated","uAlbumID": "'.$ugalleryID.'"}';
				exit;
			break;
			case "contrEditAlbum":
				if(!$_SESSION['member']['mem_id']) exit; // Don't continue if no session member id exists
				if(!$albumID) exit; // Don't continue if no album ID exists
				
				if($albumPublic) // Set the correct permissions for the gallery
				{
					$everyone = 1;
					$perm = 'everyone';
				}
				else
				{
					$everyone = 0;
					$perm = 'mem'.$_SESSION['member']['mem_id'];
				}
				
				if($albumPassword) $password = k_encrypt($albumPassword);
				
				mysqli_query($db,"UPDATE {$dbinfo[pre]}galleries SET name='{$albumName}',description='{$albumDescription}',publicgal='{$albumPublic}',everyone='{$everyone}',password='{$password}' WHERE ugallery_id = '{$albumID}'"); // Update the album in the db
				$saveid = mysqli_insert_id($db);
				$page = 'galleries';
				
				save_mem_permissions();
				
				$_SESSION['member']['contrAlbumsQueried'] = 0; // Make sure the albums are reloaded
				
				echo '{"errorCode": "editAlbumCompleted","uAlbumID": "'.$albumID.'"}';
				exit;
			break;
			case "contrDeleteAlbum":
				
				$galleryResult = mysqli_query($db,"SELECT gallery_id FROM {$dbinfo[pre]}galleries WHERE ugallery_id = '{$albumID}'"); // Get the actual gallery ID
				$gallery = mysqli_fetch_assoc($galleryResult);
				
				$mediaResult = mysqli_query($db,"SELECT gmedia_id FROM {$dbinfo[pre]}media_galleries WHERE gallery_id = '{$gallery[gallery_id]}'"); // Find media in that gallery
				while($media = mysqli_fetch_assoc($mediaResult))
				{
					try
					{
						$media = new mediaTools($media['gmedia_id']);
						$media->deleteMedia();						
						//echo '{"errorCode": "0","mediaID":"'.$mediaID.'"}';
					}
					catch(Exception $e)
					{
						$errorMessage = $e->getMessage();
						//echo '{"errorCode": "1","mediaID":"'.$mediaID.'","errorMessage":"'.$errorMessage.'"}';
					}
				}
				
				@mysqli_query($db,"DELETE FROM {$dbinfo[pre]}galleries WHERE ugallery_id = '{$albumID}' AND owner = '{$_SESSION[member][mem_id]}'"); // Remove gallery item from DB
				
				unset($_SESSION['contrMediaMode']); // Unset the contributors media mode
				unset($_SESSION['contrAlbumID']); // Unset the album ID
				
				$_SESSION['member']['contrAlbumsQueried'] = false; // Force the contributor albums to be reloaded
				
				header("location: contributor.my.media.php");
				exit;
			break;
			case "deleteImportMedia":
				if($files)
				{
					foreach($files as $encFile)
					{
						$file = base64_decode($encFile);
						
						
						$directoryPath = dirname($file);
						$baseName = basename($file);
						
						$icon = $directoryPath.'/icon_'.$baseName;
						$sample = $directoryPath.'/sample_'.$baseName;
						$thumb = $directoryPath.'/thumb_'.$baseName;
						
						//$_SESSION['testing']['tester'][] = $baseName; // Testing
						
						if(file_exists($file)) // Delete original
							@unlink($file);
							
						if(file_exists($icon)) // Delete icon
							@unlink($icon);
							
						if(file_exists($sample)) // Delete sample
							@unlink($sample);
							
						if(file_exists($thumb)) // Delete thumb
							@unlink($thumb);
					}
				}

				//$filesPassed = count($files);	// Testing		
				//echo '{"filesPassed" : "'.$filesPassed.'"}';
			break;
			case "contrAssignMediaDetails":
				
				require_once BASE_PATH.'/assets/classes/metadata.php'; // Include meta data class
				require_once BASE_PATH.'/assets/classes/colors.php'; // Include colors class
				require_once BASE_PATH.'/assets/classes/imagetools.php'; // Include imagetools class
				
				if(!$_SESSION['member']['mem_id']) exit; // Don't continue if no session member id exists
				if(!$_SESSION['loggedIn']) exit; // Don't continue if member is not logged in
				
				$cleanCurrency = new number_formatting; // Setup a cleanup object for converting currency to the admin currency
				$cleanCurrency->set_custom_cur_defaults($priCurrency['currency_id']);
				
				$memID = $_SESSION['member']['mem_id']; // Shortcut
				
				if($_SESSION['contrSaveSession'] != $contrSaveSessionForm) // Only do this part if the session is new
				{
					$_SESSION['contrSaveSession'] = $contrSaveSessionForm;
					$newSessionFound = true;
				}
				else
					$newSessionFound = false;
				
				switch($saveMode)
				{
					case "import":
					case "newUpload":
						switch($albumType)
						{
							case "none": // No album selected
								$albumID = 0;
							break;
							case "new": // Create a new album								
								if($newSessionFound) // Only do this part if the session is new
								{
									//$_SESSION['contrSaveSession'] = $contrSaveSessionForm;
									
									$everyone = 0; // Private album
									$perm = 'mem'.$_SESSION['member']['mem_id']; // Permissions						
									$ugalleryID = create_unique2(); // Unique gallery ID
									
									if(!$newAlbumName) // If no name entered then use date
										$newAlbumName = date("Y-m-d");	
									
									// Create Gallery
									mysqli_query($db,
										"
										INSERT INTO {$dbinfo[pre]}galleries  
										(
											name,
											owner,
											created,
											active,
											description,
											publicgal,
											password,
											everyone,
											album,
											ugallery_id
										)
										VALUES
										(
											'{$newAlbumName}',
											'{$_SESSION[member][mem_id]}',
											'{$nowGMT}',
											'1',
											'{$newAlbumDescription}',
											'{$newAlbumPublic}',
											'{$newAlbumPassword}',
											'{$everyone}',
											'1',
											'{$ugalleryID}'
										)
										"
									);
									$albumID = mysqli_insert_id($db); // New album ID
									$page = 'galleries';							
									save_mem_permissions(); // Save member permissions
									$_SESSION['member']['contrAlbumsQueried'] = 0; // Make sure the albums are reloaded
									
									$_SESSION['albumIDSess'] = $albumID;
								}
								else
									$albumID = $_SESSION['albumIDSess'];
							break;
							case "existing": // Use an existing album - $albumID passed as unique ID
								//$albumResult = mysqli_query($db,"SELECT gallery_id FROM {$dbinfo[pre]}galleries WHERE owner = '{$memID}' AND ugallery_id = '{$albumID}'");
								//$albumDetails = mysqli_fetch_assoc($albumResult);									
								//$albumID = 	$albumDetails['gallery_id'];		
							break;
						}						
					break;
				}
				
				$contrGalleries[] = $albumID; // Add the album ID to the contrGalleries array
				
				if($saveMode == 'import' or $saveMode == 'newUpload') // If saveMode is import or newUpload then do these actions
				{
					$contr = checkContrDirectories(); // Check and create contributor directories
				
					if($contr['error']) // If there is an error on create die and output the error
						die($contr['error']); // On error exit
				
					if($contr['error']) // Check and create contributor directories
					{
						echo '{"errorCode":"1","message":"'.$contr['error'].'"}';
						exit;	
					}
					
					if($_POST['file']) // Make sure $file was passed
					{
						$filePath = base64_decode($_POST['file']);
						$fileSize = round((filesize($filePath)/1024)/1024,3);
						$minFileSize = $_SESSION['member']['membershipDetails']['fs_min'];
						$maxFileSize = $_SESSION['member']['membershipDetails']['fs_max'];
						
						if(!file_exists($filePath)) // Make sure file exists
						{
							$errorMessage = 'File does not exist!';
							echo '{"errorCode":"1","message":"'.$errorMessage.'"}';
							exit;	
						}
						
						if($fileSize < $minFileSize) // Check the file size
						{	
							echo '{"errorCode":"1","message":"'.$lang['contrSmallFileSize'].' '.$minFileSize.$lang['MB'].'"}';
							exit;	
						}
					}
					else
					{
						$errorMessage = 'No file passed!';
						echo '{"errorCode":"1","message":"'.$errorMessage.'"}';
						exit;
					}
					
					$fileName = basename($filePath); // Name of file being moved
					$cleanFileName = clean_filename($fileName); // Clean odd characters out of the filename
					$fileType = getMimeType($cleanFileName); // Get the mime type of file extension
					$fileDir = dirname($filePath); // Get the file directory					
					
					$fileExplode = explode(".",$fileName); // Explode the file name by a period
					$extension = strtolower(array_pop($fileExplode)); // remove the last array element which is the extension
					$fileNameNoExt = implode(".",$fileExplode); // glue the file name peices back together
					
					$typeOfFile = getdTypeOfExtension($extension); // Find the type of the file video, photo, other
					$metadataFileTypes = array('jpg','jpe','jpeg');
				
					$iconImage = $fileDir . "/icon_" . $fileNameNoExt . ".jpg"; // Path to icon
					$thumbImage = $fileDir . "/thumb_" . $fileNameNoExt . ".jpg"; // Path to thumb
					$sampleImage = $fileDir . "/sample_" . $fileNameNoExt . ".jpg"; // Path to sample					
					
					// Specifically for clean filename
					$cleanFileExplode = explode(".",$cleanFileName); // Explode the file name by a period
					$cleanExtension = strtolower(array_pop($cleanFileExplode)); // remove the last array element which is the extension
					$cleanFileNameNoExt = implode(".",$cleanFileExplode); // glue the file name peices back together
					
					$creatableFileTypes = getCreatableFormats();
					
					if(in_array(strtolower($extension),$creatableFileTypes))
					{
						$memoryNeeded = figure_memory_needed($filePath);
						
						if(ini_get("memory_limit"))
							$memoryLimit = ini_get("memory_limit");
						else
							$memoryLimit = $config['DefaultMemory'];

						if(class_exists('Imagick') and $config['settings']['imageproc'] == 2)
							$memoryLimit = $config['DefaultMemory'];
						
						$autoCreateAvailable = 1;
					}
					
					//test($memoryNeeded,'mem_needed');
					//test($memoryLimit,'mem_limit');
					
					if(!file_exists($iconImage)) // Check for existing image
					{
						if(in_array(strtolower($extension),$creatableFileTypes)) // Check to see if this is creatable
						{
							if($memoryLimit > $memoryNeeded) // Check memory available
							{
								// Create icon
								$image = new imagetools($filePath);
								$image->setSize($config['IconDefaultSize']);
								$image->setQuality($config['SaveThumbQuality']);
								$image->createImage(0,$iconImage);
							}
							else
							{
								$errorMessage = 'Not enough memory available to create thumbnails.';
							}
						}
						else
						{
							$errorMessage = 'Thumbnails cannot be automatically created from this format.';
						}
					}
					
					if(!file_exists($thumbImage)) // Check for existing image
					{
						if(in_array(strtolower($extension),$creatableFileTypes)) // Check to see if this is creatable
						{
							if($memoryLimit > $memoryNeeded) // Check memory available
							{
								// Create icon
								$image = new imagetools($filePath);
								$image->setSize($config['ThumbDefaultSize']);
								$image->setQuality($config['SaveThumbQuality']);
								$image->createImage(0,$thumbImage);
							}
							else
							{
								$errorMessage = 'Not enough memory available to create thumbnails.';
							}
						}
						else
						{
							$errorMessage = 'Thumbnails cannot be automatically created from this format.';
						}
					}
					
					if(!file_exists($sampleImage)) // Check for existing image
					{
						if(in_array(strtolower($extension),$creatableFileTypes)) // Check to see if this is creatable
						{
							if($memoryLimit > $memoryNeeded) // Check memory available
							{
								// Create icon
								$image = new imagetools($filePath);
								$image->setSize($config['SampleDefaultSize']);
								$image->setQuality($config['SaveThumbQuality']);
								$image->createImage(0,$sampleImage);
							}
							else
							{
								$errorMessage = 'Not enough memory available to create thumbnails.';
							}
						}
						else
						{
							$errorMessage = 'Thumbnails cannot be automatically created from this format.';
						}
					}
					
					// Check if media already exists
					$mediaCheck = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS filename FROM {$dbinfo[pre]}media WHERE owner = '{$memID}' AND filename = '{$cleanFileName}'");
					if($mediaCheckRows = getRows())
					{
						$x=1;
						while($mediaCheckRows > 0) // File name already exists. Might want to use something different
						{
							$newFileName = $cleanFileNameNoExt.$x.'.'.$extension;
							$mediaCheckNew = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS filename FROM {$dbinfo[pre]}media WHERE owner = '{$memID}' AND filename = '{$newFileName}'");
							if(getRows())
								$x++;
							else
								$mediaCheckRows = 0;
							
						}
						
						$cleanFileNameNoExt = $cleanFileNameNoExt.$x;
						$cleanFileName = $cleanFileNameNoExt.'.'.$extension;
					}
					
					/*
					* Get image meta data
					*/
					if(in_array(strtolower($extension),$metadataFileTypes))
					{	
						$imageMetadata = new metadata($filePath);
						
						// UTF8 Detection
						if($config['settings']['iptc_utf8']) // utf-8
							$imageMetadata->setCharset('utf-8');
						else
							$imageMetadata->setCharset('off'); // utf8_encode off
						
						if($config['settings']['readiptc'])
						{
							$iptc = $imageMetadata->getIPTC();						
							if($iptc) $iptc = array_map("addSlashesMap",$iptc); // fix ' and " issues
						}
						if(function_exists('exif_read_data') and $config['settings']['readexif'])
						{
							$exif = $imageMetadata->getEXIF();							
							if($exif) $exif = array_map("addSlashesMap",$exif); // fix ' and " issues
						}
					}
					
					if(in_array(strtolower($extension),$metadataFileTypes))
					{
						if($exif['DateTimeOriginal']) // Find the date the photo was taken
						{
							$dateCreatedParts = explode(' ',$exif['DateTimeOriginal']);					
							$dateCreatedYMD = str_replace(':','-',$dateCreatedParts[0]);
							$dateCreatedString = "{$dateCreatedYMD} {$dateCreatedParts[1]}";
							//$dateCreated = date("Y-m-d H:m:s",filemtime($decoded_path)); // Date file was created
							//$exif['DateTimeOriginal'];
						}
						else
							$dateCreatedString = '0000-00-00 00:00:00'; //date("Y-m-d H:m:s",filemtime($decoded_path))
					}
					else
						$dateCreatedString = '0000-00-00 00:00:00'; //date("Y-m-d H:m:s",filemtime($decoded_path))
						
					if($dateCreatedString != '0000-00-00 00:00:00') // Check if there is a date to work with
					{
						$ndate = new kdate;
						$dateCreated = $ndate->formdate_to_gmt($dateCreatedString);
					}
					else
						$dateCreated = $dateCreatedString;
					
					
					$resolution = getimagesize($filePath); // Get resolution of original
					$filesize = filesize($filePath); // Get file size of original
					
					//$_SESSION['testing']['filePath'] = $filePath; // Testing
					//$_SESSION['testing']['writePath'] = $config['settings']['library_path']."/{$contr[contrFolderName]}/originals/{$cleanFileName}";
					
					if($config['mediaMoveFunction']($filePath,$config['settings']['library_path']."/{$contr[contrFolderName]}/originals/{$cleanFileName}"))
					{
						@unlink($filePath); // Delete incoming file
						$originalSuccess = true;
					}
					else
					{
						$errorMessage = 'Could not move original file to library';
						echo '{"errorCode":"1","message":"'.$errorMessage.'"}';
						exit;
					}
					
					if(file_exists($iconImage) and $originalSuccess)
					{
						$newIconImage = 'icon_'.$cleanFileNameNoExt.'.jpg'; // New icon image name
						
						if(copy($iconImage,$config['settings']['library_path']."/{$contr[contrFolderName]}/icons/{$newIconImage}"))
						{
							$iconFilesize = filesize($iconImage);
							$iconResolution = getimagesize($iconImage);
							$iconWidth = $iconResolution[0];
							$iconHeight = $iconResolution[1];
							@unlink($iconImage);
							$iconSuccess = true;
						}
					}					
					
					if(file_exists($thumbImage) and $originalSuccess)
					{
						$newThumbImage = 'thumb_'.$cleanFileNameNoExt.'.jpg'; // New icon image name
						$thumbSavePath = $config['settings']['library_path']."/{$contr[contrFolderName]}/thumbs/{$newThumbImage}";
						
						if(copy($thumbImage,$thumbSavePath))
						{
							$thumbFilesize = filesize($thumbImage);
							$thumbResolution = getimagesize($thumbImage);
							$thumbWidth = $thumbResolution[0];
							$thumbHeight = $thumbResolution[1];
							@unlink($thumbImage);
							$thumbSuccess = true;
						}
					}
					
					if(file_exists($sampleImage) and $originalSuccess)
					{
						$newSampleImage = 'sample_'.$cleanFileNameNoExt.'.jpg'; // New icon image name
						
						if(copy($sampleImage,$config['settings']['library_path']."/{$contr[contrFolderName]}/samples/{$newSampleImage}"))
						{
							$sampleFilesize = filesize($sampleImage);
							$sampleResolution = getimagesize($sampleImage);
							$sampleWidth = $sampleResolution[0];
							$sampleHeight = $sampleResolution[1];
							@unlink($sampleImage);
							$sampleSuccess = true;
						}
					}
					
					$umediaID = create_unique2(); // Create a unique media ID
					$batchID = $_SESSION['contrSaveSession'];
					
					
					//test($_SESSION['contrSaveSession'].'-'.$contrSaveSessionForm);
					
					/*
					if($active_langs)
					{
						foreach($active_langs as $value) // Support for additional languages
						{ 
							$title_val = ${"title_" . $value};
							$description_val = ${"description_" . $value};
							$addsqla.= ",title_$value";
							$addsqlb.= ",'$title_val'";
							$addsqla.= ",description_$value";
							$addsqlb.= ",'$description_val'";
						}
					}
					*/
					
					//test($iptc['title']);
					
					if($config['iptcTitleField'] == 'headline') // Use headline for title instead of title field
						$iptc['title'] = $iptc['headline'];	
					
					if($iptc['title']) // Override with IPTC data
					{
						if($config['iptcTitleHandler'] == 'R')
							$title[$selectedLanguage] = $iptc['title'];
						else
							$title[$selectedLanguage] = $title[$selectedLanguage] . $config['iptcSepChar'] . $iptc['title'];
					}
					if($iptc['description'])
					{					
						if($config['iptcDescHandler'] == 'R')
							$description[$selectedLanguage] = $iptc['description'];
						else
							$description[$selectedLanguage] = $description[$selectedLanguage] . $config['iptcSepChar'] . $iptc['description'];
					}
					if($iptc['copyright_notice'])
					{					
						if($config['iptcCopyRightHandler'] == 'R')
							$copyright = $iptc['copyright_notice'];
						else
							$copyright = $copyright . $config['iptcSepChar'] . $iptc['copyright_notice'];
					}
					
					$dateAdded = gmt_date(); // Get date added to library
					
					if($original) // Find correct license
						$license = $originalLicense;
					else
						$license = 'nfs';
					
					$approvalStatus = ($_SESSION['member']['membershipDetails']['approval']) ? 1 : 0; // Check the approval status
					
					if($approvalStatus)
						$approvalDate = $nowGMT;
					
					$cleanOriginalPrice = $cleanCurrency->currency_clean($originalPrice);
					
					// Insert into the database
					$sql = "INSERT INTO {$dbinfo[pre]}media (
							umedia_id,
							owner,
							width,
							height,
							date_added,
							date_created,
							filesize,
							filename,
							ofilename,
							file_ext,
							folder_id,
							batch_id,
							license,
							rm_license,
							quantity,
							price,
							sortorder,
							credits,
							active,
							dsp_type,
							model_release_status,
							copyright,
							usage_restrictions,
							approval_status,
							approval_date";
					$sql.= $addsqla;
					$sql.= ") VALUES (
							'{$umediaID}',
							'{$memID}',
							'{$resolution[0]}',
							'{$resolution[1]}',
							'{$dateAdded}',
							'{$dateCreated}',
							'{$filesize}',
							'{$cleanFileName}',
							'{$fileName}',
							'{$extension}',
							'{$contr[folderDBID]}',
							'{$batchID}',
							'{$license}',
							'',
							'{$quantity}',
							'{$cleanOriginalPrice}',
							'{$sortorder}',
							'{$originalCredits}',
							'1',
							'{$typeOfFile}',
							'',
							'{$copyright}',
							'',
							'{$approvalStatus}',
							'{$approvalDate}'";
					$sql.= $addsqlb;
					$sql.= ")";
					if($result = mysqli_query($db,$sql))
					{
						$mediaID = mysqli_insert_id($db); // Get the ID of this new entry
						
						// Titles
						$defaultTitle = $title[$config['settings']['lang_file_mgr']];
						foreach($title as $key => $dbTitle)
						{
							if($key != $config['settings']['lang_file_mgr']) // Make sure the key is other than the default lang
								$updateTitleSQL.= ",title_{$key}='{$dbTitle}'";
						}
						@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET title='{$defaultTitle}'{$updateTitleSQL} WHERE media_id = '{$mediaID}'"); // Update the titles in the db
						
						//$_SESSION['testing']['db'] = "UPDATE {$dbinfo[pre]}media SET title='{$defaultTitle}'{$updateTitleSQL} WHERE media_id = '{$mediaID}'"; // Testing
						
						// Descriptions
						$defaultDescription = $description[$config['settings']['lang_file_mgr']]; // $description[$selectedLanguage]				
						foreach($description as $key => $dbDescription)
						{
							if($key != $config['settings']['lang_file_mgr']) // Make sure the key is other than the default lang
								$updateDescriptionSQL.= ",description_{$key}='{$dbDescription}'";
						}
						@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET description='{$defaultDescription}'{$updateDescriptionSQL} WHERE media_id = '{$mediaID}'"); // Update the descriptions in the db
						
						//$_SESSION['testing']['db'] = $description;						
						//$_SESSION['testing']['db'] = "UPDATE {$dbinfo[pre]}media SET description='{$defaultDescription}'{$updateDescriptionSQL} WHERE media_id = '{$mediaID}'"; // Testing
						
						// Insert keywords
						if($_POST['keyword'])
						{
							foreach($_POST['keyword'] as $key => $keyArray)
							{
								foreach($keyArray as $dbKeyword)
								{	
									if($key == $config['settings']['lang_file_mgr'])
										$keyLang = '';
									else
										$keyLang = strtoupper($key);
									
									//$_SESSION['testing']['keywords'][$key][] = $dbKeyword; // Testing								
									mysqli_query($db,
									"
										INSERT INTO {$dbinfo[pre]}keywords (
										media_id,
										keyword,
										language
										) VALUES (
										'{$mediaID}',
										'{$dbKeyword}',
										'{$keyLang}')
									");
								}
							}
						}
						
						
						if($thumbSuccess and $config['cpResults'] > 0)
						{
							$colorPalette = new GetMostCommonColors();
							$colors = $colorPalette->Get_Color($thumbSavePath, $config['cpResults'], $config['cpReduceBrightness'], $config['cpReduceGradients'], $config['cpDelta']);
		
							if(count($colors) > 0)
							{
								// Save color palette
								foreach($colors as $hex => $percentage)
								{
									if($percentage > 0)
									{
										$percentage = round($percentage,6);
										$rgb = html2rgb($hex);
										
										mysqli_query($db,
										"
											INSERT INTO {$dbinfo[pre]}color_palettes (
											media_id,
											hex,
											red,
											green,
											blue,
											percentage
											) VALUES (
											'{$mediaID}',
											'{$hex}',
											'{$rgb[red]}',
											'{$rgb[green]}',
											'{$rgb[blue]}',
											'{$percentage}')
										");
									}
								}
							}
						}
						
						// Galleries
						if($contrGalleries)
						{
							foreach($contrGalleries as $value) // Save gallery selections
							{
								$galleryID = str_replace('galleryTree','',$value);
								$sql = "INSERT INTO {$dbinfo[pre]}media_galleries (
										gmedia_id,
										gallery_id
										) VALUES (
										'{$mediaID}',
										'{$galleryID}'
										)";
								$result = mysqli_query($db,$sql);
							}
						}
						
						// Media types
						if($mediaTypes)
						{
							foreach($mediaTypes as $value)
							{
								$sql = "INSERT INTO {$dbinfo[pre]}media_types_ref (
										media_id,
										mt_id
										) VALUES (
										'{$mediaID}',
										'{$value}'
										)";
								$result = mysqli_query($db,$sql);
							}
						}
						
						// Products
						if($product)
						{
							foreach($product as $key => $value)
							{
								$cleanProductPrice = '';
								
								
								//$prod_price_clean = $cleanvalues->currency_clean(${'prod_price_'.$value});							
								//$productPrice[$value]
								if($_SESSION['member']['membershipDetails']['contr_col'] == 0)
								{
									$cleanProductPrice = $cleanCurrency->currency_clean($productPrice[$value]);
									
									$sql = "INSERT INTO {$dbinfo[pre]}media_products(
											media_id,
											prod_id,
											price,
											credits,
											customized
											) VALUES (
											'{$mediaID}',
											'{$value}',
											'{$cleanProductPrice}',
											'{$productCredits[$value]}',
											'1'
											)";
									$result = mysqli_query($db,$sql);
								}
								else
								{
									$sql = "INSERT INTO {$dbinfo[pre]}media_products(
											media_id,
											prod_id
											) VALUES (
											'{$mediaID}',
											'{$value}'
											)";
									$result = mysqli_query($db,$sql);
								}
							}
						}
						
						// Prints
						if($print)
						{
							foreach($print as $key => $value)
							{								
								$cleanPrintPrice = '';
								
								if($_SESSION['member']['membershipDetails']['contr_col'] == 0)
								{
									$cleanPrintPrice = $cleanCurrency->currency_clean($printPrice[$value]);
									
									$sql = "INSERT INTO {$dbinfo[pre]}media_prints(
											media_id,
											print_id,
											price,
											credits,
											customized
											) VALUES (
											'{$mediaID}',
											'{$value}',
											'{$cleanPrintPrice}',
											'{$printCredits[$value]}',
											'1'
											)";
									$result = mysqli_query($db,$sql);
								}
								else
								{
									$sql = "INSERT INTO {$dbinfo[pre]}media_prints(
											media_id,
											print_id
											) VALUES (
											'{$mediaID}',
											'{$value}'
											)";
									$result = mysqli_query($db,$sql);
								}
							}
						}
						
						// Digital Sizes
						if($digital)
						{
							foreach($digital as $key => $value)
							{
								$cleanDigitalPrice = '';
								
								//$dsp_price_clean = $cleanvalues->currency_clean(${'dsp_price_'.$value});
								if($_SESSION['member']['membershipDetails']['contr_col'] == 0)
								{
									$cleanDigitalPrice = $cleanCurrency->currency_clean($digitalPrice[$value]);
									
									$sql = "INSERT INTO {$dbinfo[pre]}media_digital_sizes(
											media_id,
											ds_id,
											price,
											credits,
											license,
											customized
											) VALUES (
											'{$mediaID}',
											'{$value}',
											'{$cleanDigitalPrice}',
											'{$digitalCredits[$value]}',
											'{$digitalLicense[$value]}',
											'1'
											)";
									$result = mysqli_query($db,$sql);
								}
								else
								{
									$sql = "INSERT INTO {$dbinfo[pre]}media_digital_sizes(
											media_id,
											ds_id
											) VALUES (
											'{$mediaID}',
											'{$value}'
											)";
									$result = mysqli_query($db,$sql);
								}
							}
						}
						
						// Save IPTC Info
						if($iptc)
						{
							$date_created_year = substr($iptc['date_created'],0,4);
							$date_created_month = substr($iptc['date_created'],4,2);
							$date_created_day = substr($iptc['date_created'],6,2);
							$date_created = "$date_created_year-$date_created_month-$date_created_day 00:00:00";
							
							# INSERT INFO INTO THE DATABASE
							$sql = "INSERT INTO {$dbinfo[pre]}media_iptc(
									media_id,
									description,
									title,
									instructions,
									date_created,
									author,
									creator_title,
									city,
									state,
									country,
									job_identifier,
									headline,
									provider,
									source,
									description_writer,
									urgency,
									copyright_notice
									) VALUES (
									'{$mediaID}',
									'{$iptc[description]}',
									'{$iptc[title]}',
									'{$iptc[instructions]}',
									'{$date_created}',
									'{$iptc[author]}',
									'{$iptc[creator_title]}',
									'{$iptc[city]}',
									'{$iptc[state]}',
									'{$iptc[country]}',
									'{$iptc[job_identifier]}',
									'{$iptc[headline]}',
									'{$iptc[provider]}',
									'{$iptc[source]}',
									'{$iptc[description_writer]}',
									'{$iptc[urgency]}',
									'{$iptc[copyright_notice]}'
									)";
							$result = mysqli_query($db,$sql);	
						}
						
						// Save EXIF info
						if($exif)
						{	
							# INSERT INFO INTO THE DATABASE
							$sql = "INSERT INTO {$dbinfo[pre]}media_exif(
									media_id,
									FileName,
									FileDateTime,
									FileSize,
									FileType,
									MimeType,
									SectionsFound,
									ImageDescription,
									Make,
									Model,
									Orientation,
									XResolution,
									YResolution,
									ResolutionUnit,
									Software,
									DateTime,
									YCbCrPositioning,
									Exif_IFD_Pointer,
									GPS_IFD_Pointer,
									ExposureTime,
									FNumber,
									ExposureProgram,
									ISOSpeedRatings,
									ExifVersion,
									DateTimeOriginal,
									DateTimeDigitized,
									ComponentsConfiguration,
									ShutterSpeedValue,
									ApertureValue,
									MeteringMode,
									Flash,
									FocalLength,
									FlashPixVersion,
									ColorSpace,
									ExifImageWidth,
									ExifImageLength,
									SensingMethod,
									ExposureMode,
									WhiteBalance,
									SceneCaptureType,
									Sharpness,
									GPSLatitudeRef,
									GPSLatitude_0,
									GPSLatitude_1,
									GPSLatitude_2,
									GPSLongitudeRef,
									GPSLongitude_0,
									GPSLongitude_1,
									GPSLongitude_2,
									GPSTimeStamp_0,
									GPSTimeStamp_1,
									GPSTimeStamp_2,
									GPSImgDirectionRef,
									GPSImgDirection
									) VALUES (
									'{$mediaID}',
									'{$exif[FileName]}',
									'{$exif[FileDateTime]}',
									'{$exif[FileSize]}',
									'{$exif[FileType]}',
									'{$exif[MimeType]}',
									'{$exif[SectionsFound]}',
									'{$exif[ImageDescription]}',
									'{$exif[Make]}',
									'{$exif[Model]}',
									'{$exif[Orientation]}',
									'{$exif[XResolution]}',
									'{$exif[YResolution]}',
									'{$exif[ResolutionUnit]}',
									'{$exif[Software]}',
									'{$exif[DateTime]}',
									'{$exif[YCbCrPositioning]}',
									'{$exif[Exif_IFD_Pointer]}',
									'{$exif[GPS_IFD_Pointer]}',
									'{$exif[ExposureTime]}',
									'{$exif[FNumber]}',
									'{$exif[ExposureProgram]}',
									'{$exif[ISOSpeedRatings]}',
									'{$exif[ExifVersion]}',
									'{$exif[DateTimeOriginal]}',
									'{$exif[DateTimeDigitized]}',
									'{$exif[ComponentsConfiguration]}',
									'{$exif[ShutterSpeedValue]}',
									'{$exif[ApertureValue]}',
									'{$exif[MeteringMode]}',
									'{$exif[Flash]}',
									'{$exif[FocalLength]}',
									'{$exif[FlashPixVersion]}',
									'{$exif[ColorSpace]}',
									'{$exif[ExifImageWidth]}',
									'{$exif[ExifImageLength]}',
									'{$exif[SensingMethod]}',
									'{$exif[ExposureMode]}',
									'{$exif[WhiteBalance]}',
									'{$exif[SceneCaptureType]}',
									'{$exif[Sharpness]}',
									'{$exif[GPSLatitudeRef]}',
									'{$exif[GPSLatitude][0]}',
									'{$exif[GPSLatitude][1]}',
									'{$exif[GPSLatitude][2]}',
									'{$exif[GPSLongitudeRef]}',
									'{$exif[GPSLongitude][0]}',
									'{$exif[GPSLongitude][1]}',
									'{$exif[GPSLongitude][2]}',
									'{$exif[GPSTimeStamp][0]}',
									'{$exif[GPSTimeStamp][1]}',
									'{$exif[GPSTimeStamp][2]}',
									'{$exif[GPSImgDirectionRef]}',
									'{$exif[GPSImgDirection]}'
									)";
							$result = mysqli_query($db,$sql);	
						}
						
												
						// IPTC keywords
						if($iptc['keywords'])
						{
							$iptcKeyLang = ($selectedLanguage == $config['settings']['lang_file_mgr']) ? '' : $selectedLanguage; // Check if the language is the default
							$iptcKeyLang = strtoupper($iptcKeyLang);							
							
							foreach($iptc['keywords'] as $key => $value)
							{
								if(@!in_array($key,$keyword_DEFAULT))
								{
									if($config['keywordsToLower'])
										$keyDB = strtolower($value);
									else
										$keyDB = $value;
									
									$sql = "INSERT INTO {$dbinfo[pre]}keywords (
											media_id,
											keyword,
											language
											) VALUES (
											'{$mediaID}',
											'{$keyDB}',
											'{$iptcKeyLang}'
											)";
									$result = mysqli_query($db,$sql);
								}
							}
						}
						
						// Save icon info
						if($iconSuccess)
						{
							$sql = "INSERT INTO {$dbinfo[pre]}media_thumbnails (
									media_id,
									thumbtype,
									thumb_filename,
									thumb_width,
									thumb_height,
									thumb_filesize
									) VALUES (
									'{$mediaID}',
									'icon',
									'{$newIconImage}',
									'{$iconWidth}',
									'{$iconHeight}',
									'{$iconFilesize}'
									)";
							$result = mysqli_query($db,$sql);
						}
						// Save thumb info
						if($thumbSuccess)
						{
							$sql = "INSERT INTO {$dbinfo[pre]}media_thumbnails (
									media_id,
									thumbtype,
									thumb_filename,
									thumb_width,
									thumb_height,
									thumb_filesize
									) VALUES (
									'{$mediaID}',
									'thumb',
									'{$newThumbImage}',
									'{$thumbWidth}',
									'{$thumbHeight}',
									'{$thumbFilesize}'
									)";
							$result = mysqli_query($db,$sql);
						}
						
						//$_SESSION['testing']['step3'] = 'works';
						
						// Save sample info
						if($sampleSuccess)
						{
							$sql = "INSERT INTO {$dbinfo[pre]}media_samples (
									media_id,
									sample_filename,
									sample_width,
									sample_height,
									sample_filesize
									) VALUES (
									'{$mediaID}',
									'{$newSampleImage}',
									'{$sampleWidth}',
									'{$sampleHeight}',
									'{$sampleFilesize}'
									)";
							$result = mysqli_query($db,$sql);
						}						
					}
					else
					{
						// Error here
					}
					
					//if($_SESSION['contrSaveSession'] != $contrSaveSessionForm) // Only do this part if the session is new
					if($newSessionFound)
					{
						test('new sess');
						try
						{	
							$smarty->assign('approvalStatus',$approvalStatus); 
							
							$content = getDatabaseContent('newContrUploadEmailAdmin',$config['settings']['lang_file_mgr']); // Get content and force language for admin
							
							$content['name'] = $smarty->fetch('eval:'.$content['name']);
							$content['body'] = $smarty->fetch('eval:'.$content['body']);						
							//$options['replyEmail'] = $_POST['form']['email'];
							//$options['replyName'] = $_POST['form']['name'];
							
							if($config['settings']['notify_contrup'])
								kmail($config['settings']['support_email'],$config['settings']['business_name'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body'],$options); // Send email to sales email		
	
						}
						catch(Exception $e)
						{
							test('err','errorfound');
							echo $e->getMessage();
							exit;
						}
					}
					
					if($errorMessage)
					{
						$message = $errorMessage;
						$errorCode = 1;
					}
					else
					{
						$message = $lang['importSuccessMes'];
						$errorCode = 0;
					}
					
					// saveMode
					echo '{"errorCode":"'.$errorCode.'","fileName":"'.urlencode($fileName).'","message":"'.$message.'"}';
					
					exit;
				}				
				
			break;
			
			case "forgotPassword":
				$memberResult = mysqli_query($db,"SELECT f_name,l_name,password FROM {$dbinfo[pre]}members WHERE email = '{$form['toEmail']}'");
				$memberRows = mysqli_num_rows($memberResult);
				$member = mysqli_fetch_array($memberResult);
				
				if($memberRows > 0)
				{
					$form['memberName'] = $member['f_name']." ".$member['l_name'];
					$form['password'] = k_decrypt($member['password']);
					$smarty->assign('form',$form);
					$content = getDatabaseContent('emailForgottenPassword'); // Get content from db				
					$content['name'] = $smarty->fetch('eval:'.$content['name']);
					$content['body'] = $smarty->fetch('eval:'.$content['body']);
					$options['replyEmail'] = $config['settings']['support_email'];
					$options['replyName'] = $config['settings']['business_name'];
					//$form['toEmail']
					kmail($form['toEmail'],$form['toEmail'],$config['settings']['support_email'],$config['settings']['business_name'],$content['name'],$content['body'],$options); // Send email
					echo '{"errorCode": "sentPasswordToEmail"}';
				} 
				else 
				{
					echo '{"errorCode": "passwordToEmailFailed"}';
				}
			break;
			
			case "deleteContrMedia":
				$mediaID = k_decrypt($_REQUEST['mediaID']);
				
				try
				{
					$media = new mediaTools($mediaID);
					$media->deleteMedia();
					
					echo '{"errorCode": "0","mediaID":"'.$mediaID.'"}';
				}
				catch(Exception $e)
				{
					$errorMessage = $e->getMessage();
					echo '{"errorCode": "1","mediaID":"'.$mediaID.'","errorMessage":"'.$errorMessage.'"}';
				}
			break;
			
			case "removeContrKeyword":
				mysqli_query($db,"DELETE FROM {$dbinfo[pre]}keywords WHERE key_id = '{$keyID}'"); // Remove keyword from DB
				echo '{"errorCode": "1","keyID":"'.$keyID.'","errorMessage":""}';
			break;
			
			case "addContrKeyword":
				
				if($config['keywordsToLower'])
					$keywordDB = strtolower($keyword); // Make sure keyword is lower case
				else
					$keywordDB = $keyword;
				
				if($config['settings']['lang_file_mgr'] == $keyLang)
					$keyLang = '';
				else
					$keyLang = strtoupper($keyLang);
				
				//test($keyLang,'lang');
				
				$sql = "INSERT INTO {$dbinfo[pre]}keywords (
						media_id,
						keyword,
						language
						) VALUES (
						'{$mediaID}',
						'{$keywordDB}',
						'{$keyLang}'
						)";
				$result = mysqli_query($db,$sql);
				$keyID = mysqli_insert_id($db);
				echo '{"errorCode": "1","keyID":"'.$keyID.'","errorMessage":""}';				
			break;
			
			case "editContrMedia":
				
				if(!$_SESSION['member']['mem_id']) exit; // Don't continue if no session member id exists
				if(!$_SESSION['loggedIn']) exit; // Don't continue if member is not logged in
				
				$memID = $_SESSION['member']['mem_id']; // Shortcut
				
				$cleanCurrency = new number_formatting; // Setup a cleanup object for converting currency to the admin currency
				$cleanCurrency->set_custom_cur_defaults($priCurrency['currency_id']);
				
				$approvalStatus = ($_SESSION['member']['membershipDetails']['approval']) ? 1 : 0; // Check the approval status
				
				if($_SESSION['member']['membershipDetails']['admin_galleries']) // Admin lets contributor assign galleries - Delete them first
				{
					mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_galleries WHERE gmedia_id = '{$mediaID}'"); // Do delete
				}
				else // If contr cannot select galleries then only delete album
				{
					// Delete any previous album assignment
					$albumResult = mysqli_query($db,
						"
						SELECT {$dbinfo[pre]}galleries.gallery_id 
						FROM {$dbinfo[pre]}galleries
						LEFT JOIN {$dbinfo[pre]}media_galleries 
						ON {$dbinfo[pre]}galleries.gallery_id = {$dbinfo[pre]}media_galleries.gallery_id 
						WHERE {$dbinfo[pre]}media_galleries.gmedia_id = '{$mediaID}' 
						AND {$dbinfo[pre]}galleries.album = 1 
						AND {$dbinfo[pre]}galleries.owner = '{$memID}' 
						"
					);
					if(mysqli_num_rows($albumResult)) // Check to make sure that a membership bill wasn't already created 
					{	
						while($album = mysqli_fetch_array($albumResult)) // Delete any membership bills that are unpaid
						{
							mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_galleries WHERE gmedia_id = '{$mediaID}' AND gallery_id = '{$album[gallery_id]}'"); // Do delete
							//$sql = "UPDATE {$dbinfo[pre]}invoices SET payment_status='6' WHERE invoice_id  = '{$bill[invoice_id]}'";
							//$result = mysqli_query($db,$sql);
						}	
					}
				}
				
				switch($albumType)
				{
					case "none": // No album selected
						$albumID = 0;
					break;
					case "new": // Create a new album								
						$everyone = 0; // Private album
						$perm = 'mem'.$_SESSION['member']['mem_id']; // Permissions						
						$ugalleryID = create_unique2(); // Unique gallery ID
						
						if(!$newAlbumName) // If no name entered then use date
							$newAlbumName = date("Y-m-d");							
							
						// Create Gallery
						mysqli_query($db,
							"
							INSERT INTO {$dbinfo[pre]}galleries  
							(
								name,
								owner,
								created,
								active,
								description,
								publicgal,
								everyone,
								album,
								ugallery_id
							)
							VALUES
							(
								'{$newAlbumName}',
								'{$memID}',
								'{$nowGMT}',
								'1',
								'{$newAlbumDescription}',
								'{$newAlbumPublic}',
								'{$everyone}',
								'1',
								'{$ugalleryID}'
							)
							"
						);
						$albumID = mysqli_insert_id($db); // New album ID
						$page = 'galleries';							
						save_mem_permissions(); // Save member permissions
						$_SESSION['member']['contrAlbumsQueried'] = 0; // Make sure the albums are reloaded
						
						/*
						// Insert entry						
						mysqli_query($db,
							"
							INSERT INTO {$dbinfo[pre]}media_galleries  
							(
								gmedia_id,
								gallery_id
							)
							VALUES
							(
								'{$mediaID}',
								'{$albumID}'
							)
							"
						);
						*/

					break;
					case "existing": // Use an existing album - $albumID passed as unique ID
						
						//$_SESSION['testing']['albumid'] = '{$albumID}';
						
						/*
						// Insert entry						
						mysqli_query($db,
							"
							INSERT INTO {$dbinfo[pre]}media_galleries  
							(
								gmedia_id,
								gallery_id
							)
							VALUES
							(
								'{$mediaID}',
								'{$albumID}'
							)
							"
						);	
						*/	
					break;
				}
				
				$contrGalleries[] = $albumID; // Add the album ID to the contrGalleries array
				
				// Save the galleries
				if($contrGalleries)
				{
					foreach($contrGalleries as $value) // Save gallery selections
					{
						if($value) // Make sure it is other than 0
						{
							$galleryID = str_replace('galleryTree','',$value);
							$sql = "INSERT INTO {$dbinfo[pre]}media_galleries (
									gmedia_id,
									gallery_id
									) VALUES (
									'{$mediaID}',
									'{$galleryID}'
									)";
							$result = mysqli_query($db,$sql);
						}
					}
				}
				
				/*
				$media = new mediaTools($mediaID);
				$mediaInfo = $media->getMediaInfoFromDB();			
				$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
				
				if($folderInfo['encrypted'])
					$folderName = $folderInfo['enc_name'];
				else
					$folderName = $folderInfo['name'];
				*/
				
				$media = new mediaTools($mediaID);
				$mediaInfo = $media->getMediaInfoFromDB();
				
				$fileNameNoExtensionArray = explode(".",$mediaInfo['filename']);
				array_pop($fileNameNoExtensionArray);
				$fileNameNoExtension = implode(".",$fileNameNoExtensionArray);
				
				$contrFolderInfo = checkContrDirectories();
				$variationsPath = "{$config[settings][library_path]}/contr{$contrFolderInfo[contrFID]}/variations/";
				
				$originallySelectedDigitalSizes = explode(",",$originalMediaDS);
				
				if($digital)
				{	
					// Get new or updated digital SP entries
					foreach($digital as $key => $value)
					{
						$cleanDigitalPrice = ''; // Clear digital price just in case
						
						// Check to see if temp file exists for this one
						$tempDSPresult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_dsp_temp WHERE media_id = '{$mediaID}' AND dsp_id = '{$value}' ORDER BY tmpid DESC");
						$tempDSProws = mysqli_num_rows($tempDSPresult);
						
						if($tempDSProws)
						{	
							$tempDSP = mysqli_fetch_array($tempDSPresult);
							
							$dspExt = strtolower(end(explode(".",$tempDSP[filename]))); // Find the extension of the dsp file
							$newDSPname = zerofill($mediaID,6)."_".zerofill($value,3)."_{$fileNameNoExtension}.{$dspExt}";
							
							// Move temp file to correct location
							copy("./assets/tmp/{$tempDSP[filename]}","{$variationsPath}{$newDSPname}");

							// Delete temp file
							unlink("./assets/tmp/{$tempDSP[filename]}");
							
							$origDSPname = $tempDSP['ofilename'];
						}
						else
						{
							$newDSPname = $digitalFilename[$value];
							$origDSPname = $digitalFilename[$value];
						}
												
						if(in_array($value,$originallySelectedDigitalSizes))
						{
							//$dsp_price_clean = $cleanvalues->currency_clean(${'dsp_price_'.$value});
							
							$cleanDigitalPrice = $cleanCurrency->currency_clean($digitalPrice[$value]);
							
							if($_SESSION['member']['membershipDetails']['contr_col'] == 0) // Contributors assign prices
							{
								$sql = "UPDATE {$dbinfo[pre]}media_digital_sizes SET 
									media_id='{$mediaID}',
									ds_id='{$value}',
									license='{$digitalLicense[$value]}',
									price='{$cleanDigitalPrice}',
									price_calc='norm',
									credits='{$digitalCredits[$value]}',
									credits_calc='norm',
									customized='1',
									filename='{$newDSPname}',
									ofilename='{$origDSPname}'
									WHERE media_id = '{$mediaID}' AND ds_id = '{$value}'";
								$result = mysqli_query($db,$sql);
							}
							else // admin sets prices
							{
								$sql = "UPDATE {$dbinfo[pre]}media_digital_sizes SET 
									media_id='{$mediaID}',
									ds_id='{$value}',
									filename='{$newDSPname}',
									ofilename='{$origDSPname}'
									WHERE media_id = '{$mediaID}' AND ds_id = '{$value}'";
								$result = mysqli_query($db,$sql);
							}
							
							/*							
							$sql = "UPDATE {$dbinfo[pre]}media_digital_sizes SET 
								license='".${'dsp_license_'.$value}."',
								rm_license='".${'dsp_rm_license_'.$value}."',
								price='$dsp_price_clean',
								price_calc='".${'dsp_price_calc_'.$value}."',
								credits='".${'dsp_credits_'.$value}."',
								credits_calc='".${'dsp_credits_calc_'.$value}."',
								customized='".${'dsp_customized_'.$value}."',
								quantity='".${'dsp_quantity_'.$value}."',
								width='".${'dsp_width_'.$value}."',
								height='".${'dsp_height_'.$value}."',
								format='".${'dsp_format_'.$value}."',
								hd='".${'dsp_hd_'.$value}."',
								running_time='".${'dsp_running_time_'.$value}."',
								fps='".${'dsp_fps_'.$value}."',
								auto_create='".${'dsp_autocreate_'.$value}."',
								filename='{$newDSPname}',
								ofilename='{$origDSPname}'
								WHERE media_id = '{$mediaID}' AND ds_id = '{$value}'";
							$result = mysqli_query($db,$sql);
							*/
						}
						else
						{	
							//test($newDSPname,'newNameB');
							//test($origDSPname,'origNameB');	
							
							$cleanDigitalPrice = $cleanCurrency->currency_clean($digitalPrice[$value]);
							
							//$dsp_price_clean = $cleanvalues->currency_clean(${'dsp_price_'.$value});
							if($_SESSION['member']['membershipDetails']['contr_col'] == 0)
							{
								$sql = "INSERT INTO {$dbinfo[pre]}media_digital_sizes(
										media_id,
										ds_id,
										price,
										credits,
										license,
										customized,
										filename,
										ofilename
										) VALUES (
										'{$mediaID}',
										'{$value}',
										'{$cleanDigitalPrice}',
										'{$digitalCredits[$value]}',
										'{$digitalLicense[$value]}',
										'1',
										'{$newDSPname}',
										'{$origDSPname}'
										)";
								$result = mysqli_query($db,$sql);
							}
							else
							{
								$sql = "INSERT INTO {$dbinfo[pre]}media_digital_sizes(
										media_id,
										ds_id,
										filename,
										ofilename
										) VALUES (
										'{$mediaID}',
										'{$value}',
										'{$newDSPname}',
										'{$origDSPname}'
										)";
								$result = mysqli_query($db,$sql);
							}
							
							/*
							$dsp_price_clean = $cleanvalues->currency_clean(${'dsp_price_'.$value});
							
							# INSERT INFO INTO THE DATABASE
							$sql = "INSERT INTO {$dbinfo[pre]}media_digital_sizes (
									media_id,
									ds_id,
									license,
									rm_license,
									price,
									price_calc,
									credits,
									credits_calc,
									customized,
									quantity,
									width,
									height,
									format,
									hd,
									running_time,
									fps,
									auto_create,
									filename,
									ofilename
									) VALUES (
									'{$mediaID}',
									'$value',
									'".${'dsp_license_'.$value}."',
									'".${'dsp_rm_license_'.$value}."',
									'$dsp_price_clean',
									'".${'dsp_price_calc_'.$value}."',
									'".${'dsp_credits_'.$value}."',
									'".${'dsp_credits_calc_'.$value}."',
									'".${'dsp_customized_'.$value}."',
									'".${'dsp_quantity_'.$value}."',
									'".${'dsp_width_'.$value}."',
									'".${'dsp_height_'.$value}."',
									'".${'dsp_format_'.$value}."',
									'".${'dsp_hd_'.$value}."',
									'".${'dsp_running_time_'.$value}."',
									'".${'dsp_fps_'.$value}."',
									'".${'dsp_autocreate_'.$value}."',
									'{$newDSPname}',
									'{$origDSPname}'
									)";
							$result = mysqli_query($db,$sql);
							*/
						}
					}
				
				}
				
				// Get removed dsp entries
				foreach($originallySelectedDigitalSizes as $value)
				{
					if(@!in_array($value,$digital))
					{
						$dspResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_digital_sizes WHERE media_id = '{$mediaID}' AND ds_id = '{$value}'"); // Find the details for the media size
						$dspRows = mysqli_num_rows($dspResult);
						if($dspRows)
						{
							$dsp = mysqli_fetch_array($dspResult);							
							@mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_digital_sizes WHERE media_id  = '{$mediaID}' AND ds_id = '{$value}'"); // Delete the record							
							@unlink("{$variationsPath}{$dsp[filename]}");// Delete the file
						}			
					}
				}
				
				// Check for and delete any temp records
				mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_dsp_temp WHERE media_id  = '{$mediaID}'");
				
				/*
				// Digital Sizes
				if($_SESSION['member']['membershipDetails']['additional_sizes']) // Make sure contr can save digital profiles
					mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_digital_sizes WHERE media_id = '{$mediaID}'"); // Do delete
									
				if($digital)
				{
					
					
					foreach($digital as $key => $value)
					{
						//$dsp_price_clean = $cleanvalues->currency_clean(${'dsp_price_'.$value});
						$sql = "INSERT INTO {$dbinfo[pre]}media_digital_sizes(
								media_id,
								ds_id,
								price,
								credits,
								license,
								customized
								) VALUES (
								'{$mediaID}',
								'{$value}',
								'{$digitalPrice[$value]}',
								'{$digitalCredits[$value]}',
								'{$digitalLicense[$value]}',
								'1'
								)";
						$result = mysqli_query($db,$sql);
					}
				}
				*/
				
				// Media types
				mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_types_ref WHERE media_id = '{$mediaID}'"); // Do delete
				if($mediaTypes)
				{
					foreach($mediaTypes as $value)
					{
						$sql = "INSERT INTO {$dbinfo[pre]}media_types_ref (
								media_id,
								mt_id
								) VALUES (
								'{$mediaID}',
								'{$value}'
								)";
						$result = mysqli_query($db,$sql);
					}
				}
				
				// Products
				if($_SESSION['member']['membershipDetails']['admin_products']) // Make sure contr can save products
					mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_products WHERE media_id = '{$mediaID}'"); // Do delete
				if($product)
				{
					foreach($product as $key => $value)
					{
						$cleanProductPrice = '';
						
						//$prod_price_clean = $cleanvalues->currency_clean(${'prod_price_'.$value});							
						//$productPrice[$value]
						
						if($_SESSION['member']['membershipDetails']['contr_col'] == 0)
						{
							$cleanProductPrice = $cleanCurrency->currency_clean($productPrice[$value]);
							
							$sql = "INSERT INTO {$dbinfo[pre]}media_products(
									media_id,
									prod_id,
									price,
									credits,
									customized
									) VALUES (
									'{$mediaID}',
									'{$value}',
									'{$cleanProductPrice}',
									'{$productCredits[$value]}',
									'1'
									)";
							$result = mysqli_query($db,$sql);
						}
						else
						{
							$sql = "INSERT INTO {$dbinfo[pre]}media_products(
									media_id,
									prod_id
									) VALUES (
									'{$mediaID}',
									'{$value}'
									)";
							$result = mysqli_query($db,$sql);
						}
					}
				}
				
				// Prints
				if($_SESSION['member']['membershipDetails']['admin_prints']) // Make sure contr can save prints
					mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_prints WHERE media_id = '{$mediaID}'"); // Do delete
				if($print)
				{
					foreach($print as $key => $value)
					{								
						$cleanPrintPrice = '';
						
						if($_SESSION['member']['membershipDetails']['contr_col'] == 0)
						{
							$cleanPrintPrice = $cleanCurrency->currency_clean($printPrice[$value]);
							
							$sql = "INSERT INTO {$dbinfo[pre]}media_prints(
									media_id,
									print_id,
									price,
									credits,
									customized
									) VALUES (
									'{$mediaID}',
									'{$value}',
									'{$cleanPrintPrice}',
									'{$printCredits[$value]}',
									'1'
									)";
							$result = mysqli_query($db,$sql);
						}
						else
						{
							$sql = "INSERT INTO {$dbinfo[pre]}media_prints(
									media_id,
									print_id
									) VALUES (
									'{$mediaID}',
									'{$value}'
									)";
							$result = mysqli_query($db,$sql);
						}
					}
				}
				
				if($original) // Find correct license
					$license = $originalLicense;
				else
					$license = 'nfs';
					
				//$_SESSION['testing']['lic'] = $originalLicense;
				
				$cleanOriginalPrice = $cleanCurrency->currency_clean($originalPrice);
				
				$sql = 
				"
					UPDATE {$dbinfo[pre]}media SET 
					dsp_type='{$dsp_type}',
					width='{$width}',
					height='{$height}',
					hd='{$hd}',
					fps='{$fps}',
					format='{$format}',
					running_time='{$running_time}',					
					model_release_status='{$modelRelease}',
					prop_release_status='{$propRelease}',
					copyright='{$copyright }',					
					approval_status='{$approvalStatus}',
					license='{$license}',
					price='{$cleanOriginalPrice}',
					credits='{$originalCredits}'
					WHERE media_id = '{$mediaID}'
				";
				$result = mysqli_query($db,$sql); // Update database
				
				// Titles
				$defaultTitle = $title[$config['settings']['lang_file_mgr']];
				foreach($title as $key => $dbTitle)
				{
					if($key != $config['settings']['lang_file_mgr']) // Make sure the key is other than the default lang
						$updateTitleSQL.= ",title_{$key}='{$dbTitle}'";
				}
				@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET title='{$defaultTitle}'{$updateTitleSQL} WHERE media_id = '{$mediaID}'"); // Update the titles in the db
			
				// Descriptions
				$defaultDescription = $description[$config['settings']['lang_file_mgr']]; // $description[$selectedLanguage]				
				foreach($description as $key => $dbDescription)
				{
					if($key != $config['settings']['lang_file_mgr']) // Make sure the key is other than the default lang
						$updateDescriptionSQL.= ",description_{$key}='{$dbDescription}'";
				}
				@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET description='{$defaultDescription}'{$updateDescriptionSQL} WHERE media_id = '{$mediaID}'"); // Update the descriptions in the db
						
				echo '{"errorCode": "contrEditMediaCompleted"}';
				exit;				
			break;
			
			case "uploadThumb":
			
				/*
				* Security check
				*/
				$checkToken = md5($config['settings']['serial_number'].$_POST['securityTimestamp']);
				if($checkToken != $_POST['securityToken']) exit;
				
				require_once BASE_PATH.'/assets/classes/imagetools.php';
				require_once BASE_PATH.'/assets/classes/colors.php';
				
				$approvalStatus = ($_SESSION['member']['membershipDetails']['approval']) ? 1 : 0; // Check the approval status
				
				if($approvalStatus == 0)
					@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET approval_status='0' WHERE media_id = '{$mediaID}'"); // Update the approval_status in the db
				
				$media = new mediaTools($mediaID);
				$mediaInfo = $media->getMediaInfoFromDB();			
				$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
				
				if($folderInfo['encrypted'])
					$folderName = $folderInfo['enc_name'];
				else
					$folderName = $folderInfo['name'];
				
				$folderPath = "{$config[settings][library_path]}/{$folderName}/";				
				$filename = $mediaInfo['filename']; // Shortcut
				
				// Temp filename
				$temp_filename = clean_filename(strtolower($_FILES['Filedata']['name']));
				$temp_filename_parts = explode(".",$temp_filename);
				$temp_filename_ext = strtolower(array_pop($temp_filename_parts));
				
				$origFilenameParts = explode(".",$filename);
				$origFilenameExt = strtolower(array_pop($origFilenameParts));
				$origFilenameWOExt = implode(".",$origFilenameParts);
				
				$newFilename = $origFilenameWOExt.'.jpg';
				
				//test($origFilenameWOExt,'origFilenameWOExt');
				
				/*
				// Original filename
				$clean_filename = $mediaInfo['filename'];
				$filename = explode(".",$clean_filename);
				$filename_ext = strtolower(array_pop($filename));				
				$filename_glued = implode(".",$filename);
				*/
				
				$_SESSION['testing']['filename'] = $mediaInfo['filename'];
				
				$temp_file_path = realpath("./assets/tmp/")."/".$temp_filename;
				
				// Move the uploaded file so we can work with it
				move_uploaded_file($_FILES['Filedata']['tmp_name'], $temp_file_path);

				# IF GD OR IMAGEMAGIK
				$creatable_filetypes = getCreatableFormats();
					
				# CALCULATE THE MEMORY NEEDED ONLY IF IT IS A CREATABLE FORMAT
				if(@in_array(strtolower($temp_filename_ext),$creatable_filetypes))
				{
					# FIGURE MEMORY NEEDED
					$mem_needed = figure_memory_needed($temp_file_path);
					if(ini_get("memory_limit")){
						$memory_limit = ini_get("memory_limit");
					} else {
						$memory_limit = $config['DefaultMemory'];
					}
					# IF IMAGEMAGICK ALLOW TWEAKED MEMORY LIMIT
					if(class_exists('Imagick') and $config['settings']['imageproc'] == 2)
					{
						$memory_limit = $config['DefaultMemory'];
					}
				}
				
				//test($filename,'filenamePass');
	
				$iconImage = "{$folderPath}icons/icon_{$newFilename}";
				$iconImageName = "icon_{$newFilename}";
				$thumbImage = "{$folderPath}thumbs/thumb_{$newFilename}";
				$thumbImageName = "thumb_{$newFilename}";
				$sampleImage = "{$folderPath}samples/sample_{$newFilename}";
				$sampleImageName = "sample_{$newFilename}";
					
				# CHECK FOR EXISTING ICON
				if(file_exists($temp_file_path))
				{
					# CHECK TO SEE IF ONE CAN BE CREATED
					if(@in_array(strtolower($temp_filename_ext),$creatable_filetypes))
					{
						// Check the memory needed
						if($memory_limit > $mem_needed){
							// Create Icon
							$image = new imagetools($temp_file_path);
							$image->setSize($config['IconDefaultSize']);
							$image->setQuality($config['SaveThumbQuality']);
							$image->createImage(0,$iconImage);
							// Create Thumb
							$image->setSize($config['ThumbDefaultSize']);
							$image->setQuality($config['SaveThumbQuality']);
							$image->createImage(0,$thumbImage);
							
							@mysqli_query($db,"DELETE FROM {$dbinfo[pre]}color_palettes WHERE media_id = '{$mediaID}'"); // Delete old color palette first
							
							if($config['cpResults'] > 0)
							{
								$colorPalette = new GetMostCommonColors();
								$colors = $colorPalette->Get_Color($thumbImage, $config['cpResults'], $config['cpReduceBrightness'], $config['cpReduceGradients'], $config['cpDelta']);
							}
		
							if(count($colors) > 0)
							{
								// Save color palette
								foreach($colors as $hex => $percentage)
								{
									if($percentage > 0)
									{
										$percentage = round($percentage,6);
										$rgb = html2rgb($hex);
										
										mysqli_query($db,
										"
											INSERT INTO {$dbinfo[pre]}color_palettes (
											media_id,
											hex,
											red,
											green,
											blue,
											percentage
											) VALUES (
											'{$mediaID}',
											'{$hex}',
											'{$rgb[red]}',
											'{$rgb[green]}',
											'{$rgb[blue]}',
											'{$percentage}')
										");
									}
								}
							}
							
							// CREATE SAMPLE
							$image->setSize($config['SampleDefaultSize']);
							$image->setQuality($config['SaveSampleQuality']);
							$image->createImage(0,$sampleImage);
						}
						else
						{
							$status = '0';
							$errormessage[] = $mgrlang['gen_not_enough_mem'];
						}
					}
					else
					{
						$status = '0';
						//$errormessage[] = 'An icon image cannot be automatically created from this filetype: ' . $filename_ext;
					}
				}			
				
				$iconFilesize = filesize($iconImage);
				$iconSize = getimagesize($iconImage);
				
				$thumbFilesize = filesize($thumbImage);
				$thumbSize = getimagesize($thumbImage);
				
				$sampleFilesize = filesize($sampleImage);
				$sampleSize = getimagesize($sampleImage);
				
				if(file_exists($iconImage))
				{
					if($thumbInfo = $media->getThumbInfoFromDB()) // Thumb exists
					{
						// Update thumb
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}media_thumbnails SET 
							thumb_filename='{$thumbImageName}',
							thumb_width='{$thumbSize[0]}',
							thumb_height='{$thumbSize[1]}',
							thumb_filesize='{$thumbFilesize}'
							WHERE media_id = '{$mediaID}' 
							AND thumbtype = 'thumb'
							"
						);
						
						// Update icon
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}media_thumbnails SET 
							thumb_filename='{$iconImageName}',
							thumb_width='{$iconSize[0]}',
							thumb_height='{$iconSize[1]}',
							thumb_filesize='{$iconFilesize}'
							WHERE media_id = '{$mediaID}' 
							AND thumbtype = 'icon'
							"
						);
					}
					else
					{
						// No sample - upload and create
						
						# INSERT THUMB INFO INTO THE DATABASE
						$sql = "INSERT INTO {$dbinfo[pre]}media_thumbnails (
								media_id,
								thumbtype,
								thumb_filename,
								thumb_width,
								thumb_height,
								thumb_filesize
								) VALUES (
								'{$mediaID}',
								'thumb',
								'{$thumbImageName}',
								'{$thumbSize[0]}',
								'{$thumbSize[1]}',
								'{$thumbFilesize}'
								)";
						$result = mysqli_query($db,$sql);
						$thumbSaveID = mysqli_insert_id($db);
						
						# INSERT ICON INFO INTO THE DATABASE
						$sql = "INSERT INTO {$dbinfo[pre]}media_thumbnails (
								media_id,
								thumbtype,
								thumb_filename,
								thumb_width,
								thumb_height,
								thumb_filesize
								) VALUES (
								'{$mediaID}',
								'icon',
								'{$iconImageName}',
								'{$iconSize[0]}',
								'{$iconSize[1]}',
								'{$iconFilesize}'
								)";
						$result = mysqli_query($db,$sql);
						$iconSaveID = mysqli_insert_id($db);
					}
				}
				
				if(file_exists($sampleImage))
				{
					if($sampleInfo = $media->getSampleInfoFromDB()) // Sample exists
					{
						// Update sample
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}media_samples SET 
							sample_filename='{$sampleImageName}',
							sample_width='{$sampleSize[0]}',
							sample_height='{$sampleSize[1]}',
							sample_filesize='{$sampleFilesize}'
							WHERE media_id = '{$mediaID}'
							"
						);
					}
					else
					{
						// No sample - upload and create
						# INSERT SAMPLE INFO INTO THE DATABASE
						$sql = "INSERT INTO {$dbinfo[pre]}media_samples (
								media_id,
								sample_filename,
								sample_width,
								sample_height,
								sample_filesize
								) VALUES (
								'{$mediaID}',
								'{$sampleImageName}',
								'{$sampleSize[0]}',
								'{$sampleSize[1]}',
								'{$sampleFilesize}'
								)";
						$result = mysqli_query($db,$sql);
						$thumbSaveID = mysqli_insert_id($db);
					}
				}
				
				// Remove mgr caches
				
				if($cacheA = glob("./assets/cache/id{$mediaID}-*"))
				{
					foreach($cacheA as $filename)
						@unlink($filename);
				}
				
				$encCacheID = k_encrypt($mediaID);				
				if($cacheB = glob("./assets/cache/id{$encCacheID}-*"))
				{
					foreach($cacheB as $filename)
						@unlink($filename);
				}
			break;
			case "uploadVideoPreview":
			
				/*
				* Security check
				*/
				$checkToken = md5($config['settings']['serial_number'].$_POST['securityTimestamp']);
				if($checkToken != $_POST['securityToken']) exit;
				
				$approvalStatus = ($_SESSION['member']['membershipDetails']['approval']) ? 1 : 0; // Check the approval status
				
				if($approvalStatus == 0)
					@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET approval_status='0' WHERE media_id = '{$mediaID}'"); // Update the approval_status in the db
				
				$media = new mediaTools($mediaID);
				$mediaInfo = $media->getMediaInfoFromDB();
				$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
				
				if($folderInfo['encrypted'])
					$folderName = $folderInfo['enc_name'];
				else
					$folderName = $folderInfo['name'];
				
				$folderPath = "{$config[settings][library_path]}/{$folderName}/samples/";
				
				$newFilename = basefilename($mediaInfo['filename']);
				
				if($vidSampleInfo = $media->getVidSampleInfoFromDB())
					@unlink($folderPath.$vidSampleInfo['vidsample_filename']); // It already exists - delete the old one first	
				
				$temp_filename = strtolower($_FILES['Filedata']['name']);
				$temp_array = explode(".",$temp_filename);
				$video_extension = $temp_array[count($temp_array)-1];
				$video_filename = "video_".$newFilename.".".$video_extension;
				move_uploaded_file($_FILES['Filedata']['tmp_name'], $folderPath.$video_filename);
				
				if($vidSampleInfo) // Update
				{
					# UPDATE THE DATABASE
					$sql = "UPDATE {$dbinfo[pre]}media_vidsamples SET 
								media_id='{$mediaID}',
								vidsampletype='sample',
								vidsample_filename='{$video_filename}',
								vidsample_width='0',
								vidsample_height='0',
								vidsample_filesize='{}',
								vidsample_extension='{$video_extension}'
								WHERE media_id  = '{$mediaID}'
								AND vidsampletype = 'sample'";
					$result = mysqli_query($db,$sql);
				}
				else // Insert
				{
					# INSERT ICON INFO INTO THE DATABASE
					$sql = "INSERT INTO {$dbinfo[pre]}media_vidsamples (
							media_id,
							vidsampletype,
							vidsample_filename,
							vidsample_width,
							vidsample_height,
							vidsample_filesize,
							vidsample_extension
							) VALUES (
							'{$mediaID}',
							'sample',
							'{$video_filename}',
							'0',
							'0',
							'{}',
							'{$video_extension}'
							)";
					$result = mysqli_query($db,$sql);
					$iconSaveID = mysqli_insert_id($db);
				}
			break;
			case "uploadDSP":
				/*
				* Security check
				*/
				$checkToken = md5($config['settings']['serial_number'].$_POST['securityTimestamp']);
				if($checkToken != $_POST['securityToken']) exit;
				
				$approvalStatus = ($_SESSION['member']['membershipDetails']['approval']) ? 1 : 0; // Check the approval status
				
				if($approvalStatus == 0)
					@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET approval_status='0' WHERE media_id = '{$mediaID}'"); // Update the approval_status in the db
				
				/*
				$media = new mediaTools($mediaID);
				$mediaInfo = $media->getMediaInfoFromDB();
				$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
				
				if($folderInfo['encrypted'])
					$folderName = $folderInfo['enc_name'];
				else
					$folderName = $folderInfo['name'];
				
				$folderPath = "{$config[settings][library_path]}/{$folderName}/variations/";
				*/
				
				$folderPath = "./assets/tmp/";
				
				$cleanFilename = clean_filename($_FILES['Filedata']['name']);
				$extArray = explode(".",$cleanFilename);
				$ext = strtolower(array_pop($extArray));
				$newFilename = "{$mediaID}_{$dspID}.{$ext}";
				move_uploaded_file($_FILES['Filedata']['tmp_name'],$folderPath.$newFilename);
				
				//test($folderPath);
				
				// Check for and delete any prior records
				mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_dsp_temp WHERE media_id  = '{$mediaID}' AND dsp_id = '{$dspID}'");
				
				// Insert new record
				$sql = "INSERT INTO {$dbinfo[pre]}media_dsp_temp (
						media_id,
						dsp_id,
						filename,
						ofilename
						) VALUES (
						'{$mediaID}',
						'{$dspID}',
						'{$newFilename}',
						'{$_FILES[Filedata][name]}'
						)";
				$result = mysqli_query($db,$sql);
				
			break;
			
			case "uploadPropRelease":
				/*
				* Security check
				*/
				$checkToken = md5($config['settings']['serial_number'].$_POST['securityTimestamp']);
				if($checkToken != $_POST['securityToken']) exit;
			
				
				$approvalStatus = ($_SESSION['member']['membershipDetails']['approval']) ? 1 : 0; // Check the approval status
				
				if($approvalStatus == 0)
					@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET approval_status='0' WHERE media_id = '{$mediaID}'"); // Update the approval_status in the db
				
				
				$folderPath = "./assets/files/releases/";
				
				$cleanFilename = clean_filename($_FILES['Filedata']['name']);
				$extArray = explode(".",$cleanFilename);
				$ext = strtolower(array_pop($extArray));
				$newFilename = "pr_{$mediaID}_{$cleanFilename}";
				move_uploaded_file($_FILES['Filedata']['tmp_name'],$folderPath.$newFilename);
				
				// Delete old release
				
				// Update database
				mysqli_query($db,
					"
					UPDATE {$dbinfo[pre]}media SET 
					prop_release_form='{$newFilename}'
					WHERE media_id = '{$mediaID}'
					"
				);
				
			break;
			
			case "deleteRelease":
				
				$releaseResult = mysqli_query($db,"SELECT model_release_form,prop_release_form FROM {$dbinfo[pre]}media WHERE media_id = '{$mediaID}'");
				if($releaseRows = mysqli_num_rows($releaseResult))
				{
					
					$release = mysqli_fetch_assoc($releaseResult);
					
					if($release[$rType.'_release_form'])
					{
						// Delete release
						@unlink("./assets/files/releases/".$release[$rType.'_release_form']);
						
						// Update database
						mysqli_query($db,
							"
							UPDATE {$dbinfo[pre]}media SET 
							{$rType}_release_form=''
							WHERE media_id = '{$mediaID}'
							"
						);
					}
				}
				
			break;
			
			case "uploadModelRelease":
				/*
				* Security check
				*/
				$checkToken = md5($config['settings']['serial_number'].$_POST['securityTimestamp']);
				if($checkToken != $_POST['securityToken']) exit;
			
				$approvalStatus = ($_SESSION['member']['membershipDetails']['approval']) ? 1 : 0; // Check the approval status
				
				if($approvalStatus == 0)
					@mysqli_query($db,"UPDATE {$dbinfo[pre]}media SET approval_status='0' WHERE media_id = '{$mediaID}'"); // Update the approval_status in the db
				
				
				$folderPath = "./assets/files/releases/";
				
				$cleanFilename = clean_filename($_FILES['Filedata']['name']);
				$extArray = explode(".",$cleanFilename);
				$ext = strtolower(array_pop($extArray));
				$newFilename = "mr_{$mediaID}_{$cleanFilename}";
				move_uploaded_file($_FILES['Filedata']['tmp_name'],$folderPath.$newFilename);
				
				// Delete old release
				
				// Update database
				mysqli_query($db,
					"
					UPDATE {$dbinfo[pre]}media SET 
					model_release_form='{$newFilename}'
					WHERE media_id = '{$mediaID}'
					"
				);
				
			break;
			
			case "deleteDSP":
				// Check temp DB
				$dspTempResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_dsp_temp WHERE media_id = '{$mediaID}' AND dsp_id = '{$dspID}'");
				$dspTempRows = mysqli_num_rows($dspTempResult);
				if($dspTempRows)
				{
					$dspTemp = mysqli_fetch_array($dspTempResult);					
					@unlink("./assets/tmp/{$dspTemp[filename]}"); // Delete temp file					
					@mysqli_query($db,"DELETE FROM {$dbinfo[pre]}media_dsp_temp WHERE tmpid = '{$dspTemp[tmpid]}'"); // Delete from temp db
				}
				
				// Check library DB
				$dspResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_digital_sizes WHERE media_id = '{$mediaID}' AND ds_id = '{$dspID}'");
				$dspRows = mysqli_num_rows($dspResult);
				if($dspRows)
				{
					$dsp = mysqli_fetch_array($dspResult);
					
					/*
					$media = new mediaTools($mediaID);
					$mediaInfo = $media->getMediaInfoFromDB();
					$folderInfo = $media->getFolderStorageInfoFromDB($mediaInfo['folder_id']);
					
					if($folderInfo['encrypted'])
						$folderName = $folderInfo['enc_name'];
					else
						$folderName = $folderInfo['name'];
						
						
						
					$folderPath = "{$config[settings][library_path]}/{$folderName}/variations/";
					*/
					
					$contrFolderInfo = checkContrDirectories();
					$variationsPath = "{$config[settings][library_path]}/contr{$contrFolderInfo[contrFID]}/variations/";
					
					@unlink("{$variationsPath}{$dsp[filename]}"); // Delete file					
					@mysqli_query($db,"UPDATE {$dbinfo[pre]}media_digital_sizes SET filename='' WHERE mds_id = '{$dsp[mds_id]}'"); // Update db
				}
			break;
			
			case "cartAddNotes":				
				if($cartItemID)
					mysqli_query($db,"UPDATE {$dbinfo[pre]}invoice_items SET cart_item_notes='{$cartItemNotes}' WHERE oi_id = '{$cartItemID}'"); // Update the db
					
				echo '{"errorCode": "cartAddNotesSaved","errorMessage": ""}'; // No error messages - everything is OK
			break;
		}
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>