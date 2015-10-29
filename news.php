<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','blankPage'); // Page ID
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

	if($id)
	{
		$newsResult = mysqli_query($db,"SELECT *	FROM {$dbinfo[pre]}news	WHERE news_id = '$id'");
		$news = mysqli_fetch_assoc($newsResult);
		$news['title'] = ($news['title_'.$selectedLanguage]) ? $news['title_'.$selectedLanguage] : $news['title']; // Choose the correct language		
		define('META_TITLE',$news['title']); // Override page title, description, keywords and page encoding here
	}
	//define('META_DESCRIPTION','');
	//define('META_KEYWORDS','');
	//define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';

	try
	{		
		$customNewsDate = new kdate;
		$customNewsDate->setMemberSpecificDateInfo();
		$customNewsDate->distime = 0;
		
		if($id)
		{
			/*
			* Grab selected news article
			*/			
			
			
			$news['article'] = ($news['article_'.$selectedLanguage]) ? $news['article_'.$selectedLanguage] : $news['article']; // Choose the correct language
			$news['display_date'] = $customNewsDate->showdate($news['add_date']); // Create a local time and date
			$smarty->assign('newsArticle',$news);
			
			if($news and $news['active'] == 1 and $news['add_date'] < $nowGMT and ($news['expire_type'] == 0 or $news['expire_date'] > $nowGMT))
				$smarty->display('news.article.tpl'); // Smarty template
			else
				$smarty->display('noaccess.tpl'); // Smarty template
				
		}
		else
		{
			/*
			* Grab a list of active news articles
			*/
			$newsResult = mysqli_query($db,
				"
				SELECT *
				FROM {$dbinfo[pre]}news
				WHERE active = 1
				AND add_date < '{$nowGMT}'
				AND (expire_type = 0 OR expire_date > '{$nowGMT}') 
				ORDER BY sortorder,add_date DESC
				"
			);
			if($returnRows = mysqli_num_rows($newsResult))
			{
				while($news = mysqli_fetch_assoc($newsResult))
				{
					$news['title'] = ($news['title_'.$selectedLanguage]) ? $news['title_'.$selectedLanguage] : $news['title']; // Choose the correct language
					$news['short'] = ($news['short_'.$selectedLanguage]) ? $news['short_'.$selectedLanguage] : $news['short']; // Choose the correct language
					$news['seoTitle'] = cleanForSEO($news['title']); // Name cleaned for SEO usage
					
					$parms['page'] = "news.php?id={$news[news_id]}"; // Link to page
					if($modRewrite) $parms['page'].="&seoTitle={$news[seoTitle]}"; // Link to page with seoName added
					$news['linkto'] = linkto($parms); // Create the link using SEO if needed
					
					$news['display_date'] = $customNewsDate->showdate($news['add_date']); // Create a local time and date
					$newsArray[] = $news;
				}
				$smarty->assign('newsRows',$returnRows);
				$smarty->assign('news',$newsArray);
			}
			
			$smarty->display('news.list.tpl'); // Smarty template
		}
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>