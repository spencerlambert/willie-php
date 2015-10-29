<?php
	/******************************************************************
	*  Copyright 2013 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 5-13-2013
	*  Modified: 5-13-2013
	******************************************************************/

	define('BASE_PATH',dirname(__FILE__)); // Define the base path

	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/commands.php';
	require_once BASE_PATH.'/assets/includes/init.member.php';
	require_once BASE_PATH.'/assets/includes/security.inc.php';
	require_once BASE_PATH.'/assets/includes/language.inc.php';
	require_once BASE_PATH.'/assets/includes/cart.inc.php';
	require_once BASE_PATH.'/assets/includes/affiliate.inc.php';
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/mediatools.php';
	
	switch($pmode)
	{
		// Find the RM groups for the selected option
		default:
			
			if(!$optionID or !$licID) exit;
			
			$rmGroupResult = mysqli_query($db,
			"
				SELECT * FROM {$dbinfo[pre]}rm_option_grp 
				LEFT JOIN {$dbinfo[pre]}rm_ref 
				ON {$dbinfo[pre]}rm_option_grp.og_id = {$dbinfo[pre]}rm_ref.group_id 
				WHERE {$dbinfo[pre]}rm_ref.option_id = '{$optionID}' 
				AND {$dbinfo[pre]}rm_option_grp.license_id = '{$licID}'
			");
			$rmGroupRows = mysqli_num_rows($rmGroupResult);
			
			//test($rmGroupRows);
			
			if($rmGroupRows)
			{
				$rmGroup = mysqli_fetch_assoc($rmGroupResult);
				
				$rmGroupName = ($rmGroup['og_name_'.$selectedLanguage]) ? $rmGroup['og_name_'.$selectedLanguage] : $rmGroup['og_name']; // Choose the correct language
				$rmGroupName = str_replace("'",'\'',$rmGroupName);
				$rmGroupName = str_replace('"','\"',$rmGroupName);
				
				echo
					"{
						\"rmGroup\" : { 
							\"name\" : \"{$rmGroupName}\", 
							\"licenseID\" : \"{$rmGroup[license_id]}\", 
							\"id\" : \"{$rmGroup[og_id]}\"
						},
						\"rmOptions\" : [";
							
					$rmOptionsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}rm_options WHERE og_id = '{$rmGroup[og_id]}'");
					$rmOptionsRows = mysqli_num_rows($rmOptionsResult);
					$record = 1;
					while($rmOption = mysqli_fetch_assoc($rmOptionsResult))
					{	
						$rmOptionName = ($rmOption['op_name_'.$selectedLanguage]) ? $rmOption['op_name_'.$selectedLanguage] : $rmOption['op_name']; // Choose the correct language
						$rmOptionName = str_replace("'",'\'',$rmOptionName);
						$rmOptionName = str_replace('"','\"',$rmOptionName);
						
						echo 
							"{ 
								\"name\" : \"{$rmOptionName}\",
								\"id\" : \"{$rmOption[op_id]}\" ,
								\"price\" : {$rmOption[price]},
								\"credits\" : {$rmOption[credits]}, 
								\"priceMod\" : \"{$rmOption[price_mod]}\" 
							}";					
						if($record < $rmOptionsRows) echo ",";
						$record++;
					}
										
					echo " ]";
					// ,\"state\" : \"closed\"
					echo "}";
			}
			else
				echo
					"{ \"rmGroup\" : 0 }
				";
			
		break;
		
		case 'getRMTotals':
			
			$creditsEnc = k_encrypt($credits);
			$priceEnc = k_encrypt($price);
			
			//$displayPrice = $cleanCurrency->currency_display($price,1);
			
			if($_SESSION['tax']['tax_inc']) // Include tax in price
			{
				$tax = (round($price*($_SESSION['tax']['tax_a_digital']/100),2)) + (round($price*($_SESSION['tax']['tax_b_digital']/100),2)) + (round($price*($_SESSION['tax']['tax_c_digital']/100),2));
				$price+=$tax;
			}
			
			$displayPrice = $cleanCurrency->currency_display(doExchangeRate($price,'',$config['settings']['cur_decimal_places']),1);
			
			echo
				"{
					\"price\" : {\"display\" : \"{$displayPrice}\", \"priceEnc\" : \"{$priceEnc}\"}, 
					\"creditsEnc\" : \"{$creditsEnc}\", 
					\"c\" : \"c\"
				}";
			
		break;
	}

?>