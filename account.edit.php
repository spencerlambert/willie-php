<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','accountEdit'); // Page ID
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

	// Set the member uploader
	if(!$_SESSION['member']['uploader'])
		$_SESSION['member']['uploader'] = $config['settings']['pubuploader'];

	try
	{
		$smarty->assign('mode',$mode);
		switch($mode)
		{
			case "personalInfo":			
			break;
			case "avatar":
				$maxAvatarFileSize = $config['settings']['avatar_filesize']*1024;
				$smarty->assign('maxAvatarFileSize',$maxAvatarFileSize);
				
				$fileExtArray = explode(",",$config['settings']['avatar_filetypes']);
				
				foreach($fileExtArray as $value)
					$fileExtUpdated[] = "*.{$value};";
				
				//$maxAvatarFileSize = $config['settings']['avatar_filesize']*1024;
				$smarty->assign('fileExt',implode("",$fileExtUpdated));
			break;
			case "address":
				$selectedCountry = $_SESSION['member']['primaryAddress']['countryID'];
				$smarty->assign('countries',getCountryList($selectedLanguage));
				$smarty->assign('selectedCountry',$selectedCountry);
				
				$selectedState = $_SESSION['member']['primaryAddress']['stateID'];
				
				$stateResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}states WHERE ((active = 1 AND deleted = 0) OR state_id = '{$selectedState}') AND country_id = '{$selectedCountry}'"); // Select states
				while($state = mysqli_fetch_assoc($stateResult))
				{
					$states[$state['state_id']] = $state['name']; // xxxxx languages	
				}
				$smarty->assign('states',$states);
				$smarty->assign('selectedState',$selectedState);
			break;
			case "bio":
				$memberBioResult = mysqli_query($db,
					"
					SELECT bio_content 
					FROM {$dbinfo[pre]}members
					WHERE mem_id = '{$_SESSION[member][mem_id]}'
					"
				);
				$memberBio = mysqli_fetch_array($memberBioResult);				
				$smarty->assign('bio',$memberBio['bio_content']);
			break;
			case "dateTime":
				$dateFormat['US'] = 'mm/dd/yyyy';
				$dateFormat['EURO'] = 'dd/mm/yyyy';
				$dateFormat['INT'] = 'yyyy/mm/dd';
				
				$dateDisplay['long'] = $lang['longDate'];
				$dateDisplay['short'] = $lang['shortDate'];
				$dateDisplay['numb'] = $lang['numbDate'];
				
				$clockFormat['12'] = '12 '.$lang['hour'];
				$clockFormat['24'] = '24 '.$lang['hour'];
				
				$numberDateSep['slash'] = $lang['slash'].' ( / )';
				$numberDateSep['period'] = $lang['period'].' ( . )';
				$numberDateSep['dash'] = $lang['dash'].' ( - )';
				
				$timeZone['-12']	= "{$lang[gmt]} -12:00";
				$timeZone['-11'] 	= "{$lang[gmt]} -11:00";
				$timeZone['-10'] 	= "{$lang[gmt]} -10:00";
				$timeZone['-9'] 	= "{$lang[gmt]} -09:00";
				$timeZone['-8'] 	= "{$lang[gmt]} -08:00";
				$timeZone['-7'] 	= "{$lang[gmt]} -07:00";
				$timeZone['-6'] 	= "{$lang[gmt]} -06:00";
				$timeZone['-5'] 	= "{$lang[gmt]} -05:00";
				$timeZone['-4'] 	= "{$lang[gmt]} -04:00";
				$timeZone['-3.5'] 	= "{$lang[gmt]} -03:30";
				$timeZone['-3'] 	= "{$lang[gmt]} -03:00";
				$timeZone['-2'] 	= "{$lang[gmt]} -02:00";
				$timeZone['-1'] 	= "{$lang[gmt]} -01:00";
				$timeZone['-0.0'] 	= "{$lang[gmt]} -00:00";
				$timeZone['1'] 		= "{$lang[gmt]} 01:00";
				$timeZone['2'] 		= "{$lang[gmt]} 02:00";
				$timeZone['3'] 		= "{$lang[gmt]} 03:00";
				$timeZone['3.5'] 	= "{$lang[gmt]} 03:30";
				$timeZone['4'] 		= "{$lang[gmt]} 04:00";
				$timeZone['4.5'] 	= "{$lang[gmt]} 04:30";
				$timeZone['5'] 		= "{$lang[gmt]} 05:00";
				$timeZone['5.5'] 	= "{$lang[gmt]} 05:30";
				$timeZone['6'] 		= "{$lang[gmt]} 06:00";
				$timeZone['7'] 		= "{$lang[gmt]} 07:00";
				$timeZone['8'] 		= "{$lang[gmt]} 08:00";
				$timeZone['9'] 		= "{$lang[gmt]} 09:00";
				$timeZone['9.5'] 	= "{$lang[gmt]} 09:30";
				$timeZone['10'] 	= "{$lang[gmt]} 10:00";
				$timeZone['11'] 	= "{$lang[gmt]} 11:00";
				$timeZone['12'] 	= "{$lang[gmt]} 12:00";
				
				$smarty->assign('timeZone',$timeZone);
				$smarty->assign('dateFormat',$dateFormat);
				$smarty->assign('dateDisplay',$dateDisplay);
				$smarty->assign('clockFormat',$clockFormat);
				$smarty->assign('numberDateSep',$numberDateSep);
			break;
			case "membership":
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
					"
				);
				while($membershipDB = mysqli_fetch_array($membershipDBResult))
				{
					if($membershipDB['active'] == 1) // Only show those that are set to display or if one is passed in the URL
					{						
						if($msID == $membershipDB['ums_id']) $msIDActive = 1; // See if the msID that was passed is actually active
						$memberships[$membershipDB['ms_id']] = membershipsList($membershipDB);
					}
					
					if($_SESSION['member']['membership'] == $membershipDB['ms_id'])
						$selectedMembership = $membershipDB['ums_id'];
				}
				
				$smarty->assign('selectedMembership',$selectedMembership);
				$smarty->assign('memberships',$memberships);
				
			break;
			case "commission":
				$compays = explode(",",$config['settings']['compay']);
				
				if(in_array(1,$compays)) $commissionTypes['paypal'] = true;
				if(in_array(2,$compays)) $commissionTypes['check'] = true;
				if(in_array(3,$compays))
				{
					$commissionTypes['other'] = true;
					$commissionTypes['otherName'] = $config['settings']['compay_other']; // xxxxx Language
				}
				$smarty->assign('commissionTypes',$commissionTypes);
			break;
		}
		$smarty->display('account.edit.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	if($db) mysqli_close($db); // Close any database connections
?>