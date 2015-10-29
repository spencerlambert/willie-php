<?php
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','upgrade'); // Page ID
	
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	
	if(!function_exists('mysqli_query'))
		die("<span style='color: #FF0000; font-weight: bold;'>Your server does not support MySQLi. PhotoStore 4.5 and higher requires MySQLi to function. Please contact your host and have them add MySQLi support to PHP or downgrade to 4.4.8</span>");
	
	if($_POST['doUpgrade'])
	{
		
		/*
		* Clear the smarty cache
		*/
		clearSmartyCache();
		
		/*
		* Upgrade database version number
		*/
		$sql[] = 
		"
			UPDATE {$dbinfo[pre]}settings SET db_version='4.7.5' WHERE settings_id = '1'
		";
		
		// 08909AC3B2F181653766BC5B1D9D6E56 - default membership ums_id
		
		// Active languages
		$languagesList = $config['settings']['lang_file_mgr'].','.$config['settings']['lang_file_pub'];
		$languages = array_unique(explode(',',$languagesList));
		
		$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `default_price` `default_price` DECIMAL(10,4) NOT NULL"; // Alter the default price field type
		$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `min_total` `min_total` DECIMAL(10,4) NOT NULL"; // Alter the min_total field
		
		// Just to make sure since some still had databases that weren't updated with the new license info
		$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '1' WHERE license = 'rf'";
		$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '3' WHERE license = 'cu'";
		$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '4' WHERE license = 'fr'";
		$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '5' WHERE license = 'eu'";
		$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '6' WHERE license = 'ex'";
				
		$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '1' WHERE license = 'rf'";
		$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '3' WHERE license = 'cu'";
		$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '4' WHERE license = 'fr'";
		$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '5' WHERE license = 'eu'";
		$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '6' WHERE license = 'ex'";		 
		
		
		
		
		/*
		* 4.7.3
		*/
		if($config['settings']['db_version'] < '4.7.3')
		{
			$sql[] = "ALTER TABLE  `{$dbinfo[pre]}media` ADD  `external_link` TEXT NOT NULL AFTER  `ofilename`";
			$sql[] = "ALTER TABLE  `{$dbinfo[pre]}media_digital_sizes` ADD  `external_link` TEXT NOT NULL AFTER  `customized`";
		}
		
		/*
		* 4.7
		*/
		if($config['settings']['db_version'] < '4.7')
		{
			$sql[] = "ALTER TABLE  `{$dbinfo[pre]}settings2` ADD  `fotomoto` VARCHAR( 250 ) NOT NULL";
			$sql[] = "ALTER TABLE  `{$dbinfo[pre]}galleries` ADD  `feature` TINYINT( 1 ) NOT NULL";		
		}		
		
		/*
		* 4.6
		*/
		if($config['settings']['db_version'] < '4.6')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `facebook_link` VARCHAR(250) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `twitter_link` VARCHAR(250) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` CHANGE `fps` `fps` DECIMAL(10,4) NOT NULL";			
		}
		
		/*
		* 4.4.6
		*/
		if($config['settings']['db_version'] < '4.4.6')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}members` ADD `uploader` INT(1) NOT NULL DEFAULT '0'";
		}
		
		
		/*
		* 4.4.5
		*/
		if($config['settings']['db_version'] < '4.4.5')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `pubuploader` TINYINT (1) NOT NULL DEFAULT '1'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}licenses` ADD `attachlicense` INT(6) NOT NULL DEFAULT '0'";			
			// Expand taxes to 3 decimal places
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `tax_a_default` `tax_a_default` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `tax_b_default` `tax_b_default` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `tax_c_default` `tax_c_default` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `tax_a_digital` `tax_a_digital` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `tax_b_digital` `tax_b_digital` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `tax_c_digital` `tax_c_digital` DECIMAL(6,3) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` CHANGE `tax_ratea` `tax_ratea` DECIMAL(3,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` CHANGE `tax_rateb` `tax_rateb` DECIMAL(3,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` CHANGE `tax_ratec` `tax_ratec` DECIMAL(3,3) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` ADD `tax_digratea` DECIMAL(3,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` ADD `tax_digrateb` DECIMAL(3,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` ADD `tax_digratec` DECIMAL(3,3) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}taxes` CHANGE `tax_a` `tax_a` DECIMAL(6,3) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}taxes` CHANGE `tax_b` `tax_b` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}taxes` CHANGE `tax_c` `tax_c` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}taxes` CHANGE `tax_a_digital` `tax_a_digital` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}taxes` CHANGE `tax_b_digital` `tax_b_digital` DECIMAL(6,3) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}taxes` CHANGE `tax_c_digital` `tax_c_digital` DECIMAL(6,3) NOT NULL";
		}
		
		/*
		* 4.4.3
		*/
		if($config['settings']['db_version'] < '4.4.3')
		{			
			if(!is_dir("../assets/files/releases"))
			{
				@mkdir("../assets/files/releases",0777,true);
			}
						
			$sql[] = "UPDATE {$dbinfo[pre]}settings2 SET gallerySortBy = 'sort_number'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}content` ADD `linked` TEXT NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` ADD `prop_release_status` TINYINT (1) NOT NULL DEFAULT '0' AFTER `model_release_form`, ADD `prop_release_form` VARCHAR (100) NOT NULL AFTER `prop_release_status`";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `gallerySortBy` TEXT NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `gallerySortOrder` TEXT NOT NULL";
		}
		
		/*
		* 4.4.2
		*/
		if($config['settings']['db_version'] < '4.4.2')
		{			
			$sql[] = "UPDATE {$dbinfo[pre]}settings2 SET tagCloudSort = 'default'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `tagCloudOn` TINYINT (1) NOT NULL DEFAULT '1'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `tagCloudSort` TEXT NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `dl_sub_id` INT(6) NOT NULL DEFAULT '0', ADD `dl_mem_id` INT(6) NOT NULL DEFAULT '0'";	
		}
		
		/*
		* 4.4
		*/
		if($config['settings']['db_version'] < '4.4')
		{			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings2` ADD `minicart` TINYINT(1) NOT NULL DEFAULT '1'";			
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$dbinfo[pre]}rm_options` (  `op_id` int(6) NOT NULL AUTO_INCREMENT,  `og_id` int(4) NOT NULL,  `license_id` int(4) NOT NULL,  `op_name` varchar(250) NOT NULL,  `price` decimal(10,4) NOT NULL,  `credits` int(6) NOT NULL DEFAULT '0',  `price_mod` varchar(4) NOT NULL,  `op_name_german` varchar(250) NOT NULL,  `op_name_english` varchar(250) NOT NULL,  PRIMARY KEY (`op_id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$dbinfo[pre]}rm_option_grp` (  `og_id` int(6) NOT NULL AUTO_INCREMENT,  `license_id` int(4) NOT NULL,  `og_name` varchar(250) NOT NULL,  `og_name_german` varchar(250) NOT NULL,  `og_name_english` varchar(250) NOT NULL,  PRIMARY KEY (`og_id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$dbinfo[pre]}rm_ref` (  `ref_id` int(4) NOT NULL AUTO_INCREMENT,  `group_id` int(4) NOT NULL,  `option_id` int(4) NOT NULL,  PRIMARY KEY (`ref_id`)) ENGINE=MyISAM  DEFAULT CHARSET=utf8";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}licenses` ADD `rm_base_type` VARCHAR(2) NOT NULL DEFAULT 'mp'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}licenses` ADD `rm_base_price` DECIMAL(10,4) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}licenses` ADD `rm_base_credits` INT(7) NOT NULL DEFAULT '0'";			
			$sql[] = "INSERT INTO `{$dbinfo[pre]}settings2` VALUES(1,'','1','0','1')";
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$dbinfo[pre]}settings2` (`settings_id` INT(6) NOT NULL AUTO_INCREMENT, `save2_id` INT(6) NOT NULL, `contrib_link` TINYINT(1) NOT NULL DEFAULT '1', `thumbDetailsDownloads` TINYINT(1) NOT NULL DEFAULT '1', `share` TINYINT(1) NOT NULL DEFAULT '1', PRIMARY KEY (`settings_id`))";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `save_id` INT(6) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoice_items` ADD `rm_selections` TEXT NOT NULL";
		}
		
		/*
		* 4.3.2
		*/
		if($config['settings']['db_version'] < '4.3.2')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}news` ADD INDEX (`homepage`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` ADD INDEX (`featured`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` ADD INDEX (`approval_status`) ";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` ADD INDEX (`active`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}galleries` ADD INDEX (`active`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}galleries` ADD INDEX (`deleted`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}prints` ADD INDEX (`active` ,`homepage` ,`featured` ,`deleted` ,`everyone`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}products` ADD INDEX (`active` ,`homepage` ,`featured` ,`deleted` ,`everyone` ,`quantity`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}packages` ADD INDEX (`active` ,`homepage` ,`featured` ,`deleted` ,`everyone` ,`quantity`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}collections` ADD INDEX (`quantity` ,`active` ,`deleted` ,`everyone` ,`featured`)";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}promotions` ADD INDEX (`active` ,`everyone` ,`quantity` ,`homepage` ,`deleted`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}subscriptions` ADD INDEX (`active` ,`homepage` ,`featured` ,`deleted` ,`everyone`)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}credits` ADD INDEX (`active` ,`featured` ,`homepage` ,`everyone` ,`deleted`)";
		}
		
		/*
		* 4.3.1
		*/
		if($config['settings']['db_version'] < '4.3.1')
		{
			// Add license type connections to media
			/*
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '1' WHERE license = 'rf'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '3' WHERE license = 'cu'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '4' WHERE license = 'fr'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '5' WHERE license = 'eu'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '6' WHERE license = 'ex'";
			*/
			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `lang_file_pub` `lang_file_pub` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `lang_file_mgr` `lang_file_mgr` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `site_title` `site_title` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `site_url` `site_url` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `taxa_name` `taxa_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `taxb_name` `taxb_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `taxc_name` `taxc_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `incoming_path` `incoming_path` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `incoming_path` `incoming_path` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `smtp_host` `smtp_host` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `vidpreview_wm` `vidpreview_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `vidrollover_wm` `vidrollover_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `preview_image` `preview_image` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `preview_wm` `preview_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `rollover_wm` `rollover_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `thumb_wm` `thumb_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `support_email` `support_email` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `sales_email` `sales_email` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `billings_filters` `billings_filters` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `billings_headers` `billings_headers` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `commission_filters` `commission_filters` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `odr_filters` `odr_filters` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `business_address2` `business_address2` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `forum_link` `forum_link` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `print_orders_email` `print_orders_email` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
		}
		
		/*
		* 4.3
		*/
		if($config['settings']['db_version'] < '4.3')
		{
			// Additional language fields for license types
			foreach($languages as $value)
			{
				$sql[] = "ALTER TABLE {$dbinfo[pre]}licenses ADD `lic_name_{$value}` VARCHAR(200) NOT NULL";
				$sql[] = "ALTER TABLE {$dbinfo[pre]}licenses ADD `lic_description_{$value}` VARCHAR(200) NOT NULL";
			}
			
			// Add license type connections to digital profiles
			$sql[] = "UPDATE {$dbinfo[pre]}digital_sizes SET license = '1' WHERE license = 'rf'";
			$sql[] = "UPDATE {$dbinfo[pre]}digital_sizes SET license = '3' WHERE license = 'cu'";
			$sql[] = "UPDATE {$dbinfo[pre]}digital_sizes SET license = '4' WHERE license = 'fr'";
			$sql[] = "UPDATE {$dbinfo[pre]}digital_sizes SET license = '5' WHERE license = 'eu'";
			$sql[] = "UPDATE {$dbinfo[pre]}digital_sizes SET license = '6' WHERE license = 'ex'";	
			
			// Add license type connections to media
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '1' WHERE license = 'rf'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '3' WHERE license = 'cu'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '4' WHERE license = 'fr'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '5' WHERE license = 'eu'";
			$sql[] = "UPDATE {$dbinfo[pre]}media SET license = '6' WHERE license = 'ex'";			
			
			// Add license type connections to customized digial profiles
			$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '1' WHERE license = 'rf'";
			$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '3' WHERE license = 'cu'";
			$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '4' WHERE license = 'fr'";
			$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '5' WHERE license = 'eu'";
			$sql[] = "UPDATE {$dbinfo[pre]}media_digital_sizes SET license = '6' WHERE license = 'ex'";
			
			// New license types
			$sql[] = "INSERT INTO `{$dbinfo[pre]}licenses` VALUES(1, 'Royalty Free', 'rf', '', 1)";
			$sql[] = "INSERT INTO `{$dbinfo[pre]}licenses` VALUES(3, 'Contact Us', 'cu', '', 1)";
			$sql[] = "INSERT INTO `{$dbinfo[pre]}licenses` VALUES(4, 'Free', 'fr', '', 1)";
			$sql[] = "INSERT INTO `{$dbinfo[pre]}licenses` VALUES(5, 'Editorial Use', 'rf', '', 1)";
			$sql[] = "INSERT INTO `{$dbinfo[pre]}licenses` VALUES(6, 'Extended', 'rf', '', 1)";
			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}members` ADD `taxid` VARCHAR (100) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` ADD `taxid` VARCHAR (100) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `customer_taxid` TINYINT (1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `display_license` TINYINT (1) NOT NULL DEFAULT '1'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `cart_notes` TINYINT (1) NOT NULL DEFAULT '0'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoices` ADD `cart_notes` TEXT NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoice_items` ADD `cart_item_notes` TEXT NOT NULL";
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$dbinfo[pre]}licenses` (`license_id` INT(6) NOT NULL AUTO_INCREMENT,`lic_name` VARCHAR(250) NOT NULL,`lic_purchase_type` VARCHAR(10) NOT NULL,`lic_description` TEXT NOT NULL, `lic_locked` TINYINT(1) NOT NULL DEFAULT '0', PRIMARY KEY (`license_id`))";
		
			$sql[] = "
				INSERT INTO `{$dbinfo[pre]}content` (
				`content_id`,
				`content_code`,
				`ca_id`,
				`name`,
				`description`,
				`content`,
				`locked`,
				`active` 
				)
				VALUES (
				NULL,
				'newMemberTicketResponse',
				'4',
				'Ticket Response From {\$config.settings.business_name}',
				'Email sent to the member when a reply was posted to a ticket.',
				'We have responded to a ticket that you have submitted to our site.<br />\r\nPlease log into your account to view the ticket response at <a href=\'{\$config.settings.site_url}\'>{\$config.settings.site_url}</a><br />\r\n<br />\r\nThanks<br />\r\n{\$config.settings.business_name}',
				'1',
				'1'
				);
				";
			$sql[] = "
				INSERT INTO `{$dbinfo[pre]}content` (
				`content_id`,
				`content_code`,
				`ca_id`,
				`name`,
				`description`,
				`content`,
				`locked`,
				`active` 
				)
				VALUES (
				NULL,
				'newAdminTicketResponse',
				'6',
				'New Ticket or Ticket Response From {\$config.settings.business_name}',
				'Email sent to the store owner when a reply was posted to a ticket.',
				'You either got a new ticket submitted, or a response to a previous ticket from a member of your store.<br>Please log into your store manager to view the new ticket response.',
				'1',
				'1'
				);
				";
				
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `lang_file_pub` `lang_file_pub` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `lang_file_mgr` `lang_file_mgr` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `site_title` `site_title` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `site_url` `site_url` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `taxa_name` `taxa_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `taxb_name` `taxb_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `taxc_name` `taxc_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `incoming_path` `incoming_path` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `incoming_path` `incoming_path` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `smtp_host` `smtp_host` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `vidpreview_wm` `vidpreview_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `vidrollover_wm` `vidrollover_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `preview_image` `preview_image` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `preview_wm` `preview_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `rollover_wm` `rollover_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `thumb_wm` `thumb_wm` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `support_email` `support_email` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `sales_email` `sales_email` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `billings_filters` `billings_filters` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `billings_headers` `billings_headers` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `commission_filters` `commission_filters` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `odr_filters` `odr_filters` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `business_address2` `business_address2` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `forum_link` `forum_link` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` CHANGE `print_orders_email` `print_orders_email` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT''";
		}
		
		/*
		* 4.2.2
		*/
		if($config['settings']['db_version'] < '4.2.2')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `tax_a_digital` DECIMAL (6,2) NOT NULL AFTER `tax_c_default`, ADD `tax_b_digital` DECIMAL (6,2) NOT NULL AFTER `tax_a_digital` , ADD `tax_c_digital` DECIMAL (6,2) NOT NULL AFTER `tax_b_digital`";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}taxes` ADD `tax_a_digital` DECIMAL (6,2) NOT NULL, ADD `tax_b_digital` DECIMAL (6,2) NOT NULL AFTER `tax_a_digital` , ADD `tax_c_digital` DECIMAL (6,2) NOT NULL AFTER `tax_b_digital`";
		}
		
		/*
		* 4.2.1
		*/
		if($config['settings']['db_version'] < '4.2.1')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `contactCaptcha` TINYINT (1) NOT NULL DEFAULT '1'";
		}
		
		/*
		* 4.1.9
		*/
		if($config['settings']['db_version'] < '4.1.9')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}keywords` ADD `member_id` INT(6) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}keywords` ADD `posted` DATETIME NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}keywords` ADD `status` TINYINT(1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}keywords` ADD `memtag` TINYINT(1) NOT NULL DEFAULT '0'"; 
		}
		
		/*
		* 4.1.6
		*/
		if($config['settings']['db_version'] < '4.1.6')
		{
			//$sql[] = "UPDATE {$dbinfo[pre]}content SET content='xxx' WHERE content_code = 'newContrUploadEmailAdmin'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}subscriptions` ADD `tdownloads` INT(6) NOT NULL AFTER `downloads`"; 
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}memsubs` ADD `total_downloads` VARCHAR(10) NOT NULL AFTER `perday`";  
		}
		
		/*
		* 4.1.3
		*/
		if($config['settings']['db_version'] < '4.1.3')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `featured_wm` TINYINT (1) NOT NULL DEFAULT '1'";
			
			$sql[] = "
			INSERT INTO `{$dbinfo[pre]}content` (
			`content_id`,
			`content_code`,
			`ca_id`,
			`name`,
			`description`,
			`content`,
			`locked`,
			`active` 
			)
			VALUES (
			NULL,
			'newContrUploadEmailAdmin',
			'6',
			'New Contributor Upload',
			'Email that the support email address receives when a member uploads new media.',
			'{\$member.f_name} {\$member.l_name} has uploaded new media to their account.<br /><br />{if \$approvalStatus == 0}</div><div>Please log in to your management area to approve these new uploads.{/if}',
			'1',
			'1'
			);
			";
		}
		
		/*
		* 4.1.2
		*/
		if($config['settings']['db_version'] < '4.1.2')
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}lightboxes` ADD `guest` TINYINT (1) NOT NULL DEFAULT '0'";
		}
		
		/*
		* Beta 1 Fix for clean db missing fields
		*/
		if(!$config['settings']['zoomonoff'])
		{
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoomonoff` TINYINT(1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoombordersize` int(3) NOT NULL DEFAULT '2'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoomlenssize` int(4) NOT NULL DEFAULT '300'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoombordercolor` VARCHAR(20) NOT NULL DEFAULT '888888'";
		}
		
		/*
		* 4.1
		*/
		if($config['settings']['db_version'] < '4.1')
		{
			// Create content files table
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$dbinfo[pre]}content_files` (`id` int(10) NOT NULL AUTO_INCREMENT,`content_id` varchar(250) NOT NULL,`file_name` text NOT NULL,`type` varchar(250) NOT NULL, PRIMARY KEY (`id`))";
			$sql[] = "UPDATE {$dbinfo[pre]}content SET locked = 1 WHERE content_id = '64'";
			$sql[] = "UPDATE {$dbinfo[pre]}content SET locked = 1 WHERE content_id = '65'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}digital_sizes` ADD `watermark` TEXT NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `measurement` VARCHAR(250) NOT NULL DEFAULT 'i'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `gpsonoff` TINYINT(1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `gpszoom` int(3) NOT NULL DEFAULT '10'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `gpscolor` VARCHAR(20) NOT NULL DEFAULT 'black'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `gpswidth` int(4) NOT NULL DEFAULT '280'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `gpsheight` int(4) NOT NULL DEFAULT '155'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `gpsmaptype` VARCHAR(20) NOT NULL DEFAULT 'terrain'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}digital_sizes` ADD `delivery_method` TINYINT (1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}digital_sizes` ADD `contr_sell` TINYINT (1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoomonoff` TINYINT(1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoombordersize` int(3) NOT NULL DEFAULT '2'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoomlenssize` int(4) NOT NULL DEFAULT '300'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `zoombordercolor` VARCHAR(20) NOT NULL DEFAULT '888888'";
			$sql[] = "INSERT INTO `{$dbinfo[pre]}content` (content_code,ca_id,name,description,content,locked,active) VALUES ('emailForgottenPassword',4,'Your password for {\$config.settings.business_name}','','<p>{\$form.memberName},</p>\r\n<p>Here is your password:<br />{\$form.password}</p>\r\n<p>You can log into our site at {\$config.settings.site_url}.</p>\r\n<p>Thanks<br />{\$config.settings.business_name}</p>',1,1)";
			$sql[] = "INSERT INTO `{$dbinfo[pre]}content` (content_code,ca_id,name,description,content,locked,active) VALUES ('orderApprovalMessage',4,'Your {\$config.settings.business_name} order was approved','','<p>Your order {\$order.order_number} has been approved. If you have made payment using a check or money order this means that your payment has cleared. You can always log into your account at our site {\$config.settings.site_url} to view the status of your order. If you have any questions please contact us.</p><p>Order Number: {\$order.order_number} <br>Order Details: <a href=\"{\$order.orderLink}\">{\$order.orderLink}</a></p><p>Thanks {\$config.settings.business_name}</p>',1,1)";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}galleries` ADD `album` TINYINT (1) NOT NULL DEFAULT '0'"; 
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}folders` ADD `owner` INT (6) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}memberships CHANGE description_dutch description_dutch TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}memberships CHANGE description_french description_french TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}memberships CHANGE description_german description_german TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}memberships CHANGE description_spanish description_spanish TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}memberships CHANGE description_english description_english TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}shipping CHANGE description_dutch description_dutch TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}shipping CHANGE description_french description_french TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}shipping CHANGE description_german description_german TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}shipping CHANGE description_spanish description_spanish TEXT NOT NULL";
			$sql[] = "ALTER TABLE {$dbinfo[pre]}shipping CHANGE description_english description_english TEXT NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}members` ADD `profile_views` INT (8) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` ADD `approval_status` TINYINT (1) NOT NULL DEFAULT '1'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` ADD `approval_message` TEXT NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}media` ADD `approval_date` DATETIME NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}memberships` ADD `contr_col` TINYINT (1) NOT NULL DEFAULT '0'"; 			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `dlitem_id` INT (6) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `comtype` VARCHAR (10) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `item_qty` INT (4) NOT NULL DEFAULT '1'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `com_credits` INT (10) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `per_credit_value` DECIMAL (10,4) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `omedia_id` INT (6) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `odsp_id` INT (6) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}commission` ADD `order_date` DATETIME NOT NULL, ADD `pay_date` DATETIME NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `sub_com` DECIMAL (10,4) NOT NULL";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `commission_filters` VARCHAR (50) NOT NULL";
			
			//$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoice_items` ADD `commission` DECIMAL (10, 4) NOT NULL AFTER `contributor_id`";
			//$sql[] = "ALTER TABLE `{$dbinfo[pre]}invoice_items` ADD `compay_status` TINYINT (1) NOT NULL DEFAULT '0' AFTER `contributor_id` ";
		
			@rename(BASE_PATH.'/assets/ticket_files',BASE_PATH.'/assets/files');
			
			if(!is_dir(BASE_PATH.'/assets/files')) // Make sure new folder exists
				die(BASE_PATH.'/assets/files does not exist!');
				
			if(!is_writable(BASE_PATH.'/assets/files')) // Make sure new folder is writable
				die(BASE_PATH.'/assets/files must be writable! Please make this directory writable and refresh this page to continue.');
		}
		
		/*
		* 4.0.9 fix
		*/
		if($config['settings']['db_version'] == '4.0.9')
		{
			$sql[] = "UPDATE {$dbinfo[pre]}settings SET hpf_width='694' WHERE settings_id = '1'";
			$sql[] = "UPDATE {$dbinfo[pre]}settings SET hpf_crop_to='340' WHERE settings_id = '1'";
			$sql[] = "UPDATE {$dbinfo[pre]}settings SET hpf_fade_speed='1000' WHERE settings_id = '1'";
			$sql[] = "UPDATE {$dbinfo[pre]}settings SET hpf_inverval='10000' WHERE settings_id = '1'";
			$sql[] = "UPDATE {$dbinfo[pre]}settings SET hpf_details_delay='1000' WHERE settings_id = '1'";
			$sql[] = "UPDATE {$dbinfo[pre]}settings SET hpf_details_distime='4000' WHERE settings_id = '1'";
		}
		
		/*
		* 4.0.9
		*/
		if($config['settings']['db_version'] < '4.0.9')
		{			
			 // Add display name for members
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}members` ADD `display_name` VARCHAR(100) NOT NULL";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}members` ADD `showcase` TINYINT (1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}galleries` ADD `publicgal` TINYINT (1) NOT NULL DEFAULT '0'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}galleries` ADD `deleted` TINYINT (1) NOT NULL DEFAULT '0'";			
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `hpf_width` INT (8) NOT NULL DEFAULT '694'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `hpf_crop_to` INT (8) NOT NULL DEFAULT '340'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `hpf_fade_speed` INT (8) NOT NULL DEFAULT '1000'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `hpf_inverval` INT (8) NOT NULL DEFAULT '10000'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `hpf_details_delay` INT (8) NOT NULL DEFAULT '1000'";
			$sql[] = "ALTER TABLE `{$dbinfo[pre]}settings` ADD `hpf_details_distime` INT (8) NOT NULL DEFAULT '4000'";
		}
		 
		/*
		* 4.0.8
		*/
		if($config['settings']['db_version'] < '4.0.8')
		{			
			 // Add new state
			$sql[] = 
			"
				INSERT INTO {$dbinfo[pre]}states 
				(
					ustate_id,
					name,
					scode,
					active,
					deleted,
					country_id,
					all_ship_methods,
					ship_percentage
				) 
				VALUES 
				(
					'72EF3C6A6C47358FA757A475FF436227',
					'New South Wales',
					'NSW',
					'1',
					'0',
					'15',
					'1',
					'100'
				)
			";
		}

		
		/*
		* 4.0.7
		*/
		if($config['settings']['db_version'] < '4.0.7') // Add custom content blocks
		{			
			$sql[] = 
			"
				ALTER TABLE `{$dbinfo[pre]}settings`
				ADD `iptc_utf8` TINYINT(1) NOT NULL DEFAULT '1' 
			";
		}
		
		/*
		* 4.0.6
		*/
		if($config['settings']['db_version'] < '4.0.6') // Add custom content blocks
		{		
			$sql[] = 
			"
				ALTER TABLE `{$dbinfo[pre]}settings`
				ADD `pppage` TINYINT(1) NOT NULL DEFAULT '1' 
			";
			
			$sql[] = 
			"
				ALTER TABLE `{$dbinfo[pre]}settings`
				ADD `papage` TINYINT(1) NOT NULL DEFAULT '1' 
			";
			
			$sql[] = 
			"
				ALTER TABLE `{$dbinfo[pre]}settings`
				ADD `tospage` TINYINT(1) NOT NULL DEFAULT '1' 
			";
		}
		
		/*
		* 4.0.5
		*/
		if($config['settings']['db_version'] < '4.0.5') // Add custom content blocks
		{		
			$sql[] = 
			"
				ALTER TABLE `{$dbinfo[pre]}galleries`
				ADD `sort_number` INT(7) NOT NULL DEFAULT '0' 
			";
			
			$sql[] = 
			"
				ALTER TABLE `{$dbinfo[pre]}settings`
				ADD `display_login` TINYINT(1) NOT NULL DEFAULT '1' 
			";
			
			$sql[] = 
			"
				ALTER TABLE `{$dbinfo[pre]}content`
				ADD `active` TINYINT(1) NOT NULL DEFAULT '1' 
			";		
			
			$checkForCB1 = mysqli_query($db,"SELECT content_id FROM {$dbinfo[pre]}content WHERE content_code = 'customBlock1'");
			if(!mysqli_num_rows($checkForCB1))
				$sql[] = "INSERT INTO {$dbinfo[pre]}content (content_code,ca_id,name,description,content,locked,active) VALUES ('customBlock1',2,'Custom Block 1','','',0,0)";
				
			$checkForCB2 = mysqli_query($db,"SELECT content_id FROM {$dbinfo[pre]}content WHERE content_code = 'customBlock2'");
			if(!mysqli_num_rows($checkForCB2))
				$sql[] = "INSERT INTO {$dbinfo[pre]}content (content_code,ca_id,name,description,content,locked,active) VALUES ('customBlock2',2,'Custom Block 2','','',0,0)";
				
			$checkForCB3 = mysqli_query($db,"SELECT content_id FROM {$dbinfo[pre]}content WHERE content_code = 'customBlock3'");
			if(!mysqli_num_rows($checkForCB3))
				$sql[] = "INSERT INTO {$dbinfo[pre]}content (content_code,ca_id,name,description,content,locked,active) VALUES ('customBlock3',2,'Custom Block 3','','',0,0)";
				
			$checkForCBF = mysqli_query($db,"SELECT content_id FROM {$dbinfo[pre]}content WHERE content_code = 'customBlockFooter'");
			if(!mysqli_num_rows($checkForCBF))
				$sql[] = "INSERT INTO {$dbinfo[pre]}content (content_code,ca_id,name,description,content,locked,active) VALUES ('customBlockFooter',2,'Custom Footer Content Block','','',0,0)";
		}
		
		
		/*
		* 4.0.4
		*/
		if($config['settings']['db_version'] < '4.0.4') // Add custom content blocks
		{
			$checkForCMOResult = mysqli_query($db,"SELECT content_id FROM {$dbinfo[pre]}content WHERE content_code = 'checkConfirmPage'");
			if(!$checkCMORows = mysqli_num_rows($checkForCMOResult))
			{
				$cmoContent = 'Thank you. We have received your order. Please mail your check or money order for the following amount to the address listed. Once payment is received we will approve your order. <div><br></div><div>Total: <span class="price">{$cartTotals.cartGrandTotalLocal.display}</span></div><div><br></div><div>*Please reference the following number with your payment: <span style="font-weight: bold; ">{$cartInfo.orderNumber}</span></div><div><span style="font-weight: bold;"><br></span></div><div>Mail Payment To:</div><div><p><strong>{$config.settings.business_name}</strong><br>{$config.settings.business_address}<br>{if $config.settings.business_address2}{$config.settings.business_address2}<br>{/if} {$config.settings.business_city}, {$config.settings.business_state} {$config.settings.business_zip}<br>{$config.settings.business_country}<br></p></div>';
				$sql[] = "INSERT INTO {$dbinfo[pre]}content (content_code,ca_id,name,description,content,locked) VALUES ('checkConfirmPage',1,'Check/Money Order Confirmation Page','','{$cmoContent}',1)";
			}
		}
		
		/*
		* PS 4.0.1
		*/
		$checkForMsResult = mysqli_query($db,"SELECT ms_id FROM {$dbinfo[pre]}memberships WHERE ms_id = 1");
		if(!$checkMsRows = mysqli_num_rows($checkForMsResult))
		{
			$sql[] = 
			"
				INSERT INTO `{$dbinfo[pre]}memberships` VALUES(1, 0, 0, 9999, 0, 0, 0, 0, 0, 0, 0, 100, 0, 10000, '', 0, 0, '', 'Basic Membership', '', '', 'free', 0, 0, 'days', '0.00', '0.00', 0, 'weekly', 0, 'icon.none.gif', 1, 1, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', '', '', '', '', '', '', '') 
			";
		}
		
		$sql[] = "DELETE FROM `{$dbinfo[pre]}settings` WHERE settings_id != '1'"; // Delete old settings to limit db size
		
		/*
		* Beta 2
		*/
		/*
		$sql[] = 
		"
			ALTER TABLE `{$dbinfo[pre]}settings`
			ADD `gal_version` INT(11) NOT NULL DEFAULT '0' 
		";
		*/
		
		/*
		* Process all queries
		*/
		$sqlr = array_reverse($sql); // Run oldest to newest		
		foreach($sqlr as $query)
		{
			if(!mysqli_query($db,$query))
			{
				if(strpos(mysqli_error($db),"Duplicate") === false) // Only show the erorr if it is not a duplicate
					$error[] = mysqli_error($db).": {$query}";
			}
		}
		
		// Move the tags to the keyword table
		if($config['settings']['db_version'] < '4.1.9')
		{
			$tagsResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}media_tags");
			while($tag = mysqli_fetch_assoc($tagsResult))
			{
				$keySQL = "INSERT INTO {$dbinfo[pre]}keywords (keyword,media_id,language,member_id,posted,status,memtag) VALUES ('{$tag[tag]}','{$tag[media_id]}','{$tag[language]}','{$tag[member_id]}','{$tag[posted]}','{$tag[status]}','1')";
				$result = mysqli_query($db,$keySQL);
			}
		}
		
		/*
		* Errors
		*/
		if($error)
		{
			foreach($error as $message) // Output all errors/messages
				echo "{$message}<br><br>";	
		}
		else
			echo "Upgrade Complete!<br><br>Go to <a href='index.php'>homepage</a>.";
	}
	else
	{
		if($config['settings']['db_version'] < $config['productVersion'])
			echo "<span style='color: #FF0000; font-weight: bold;'>Your PhotoStore requires an upgrade.</span><br><br>";
			
		echo "<form action='upgrade.php' method='post'>";
		echo "<input type='hidden' name='doUpgrade' value='1'>";
		echo "To upgrade your PhotoStore database to the newest version please click the <strong>Upgrade Now</strong> button. <input type='submit' value='Upgrade Now'>";
		echo "</form>";
	}
?>