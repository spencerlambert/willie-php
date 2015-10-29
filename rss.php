<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 2-7-2012
	*  Modified: 2-7-2012
	******************************************************************/
	header("Content-type: text/xml");
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	/*
	*
	*/
	
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
	$mediaDate = new kdate;

	//GET ID PASSED TO THIS FILE
	if($_GET['id']){
		if($config['EncryptIDs'] == 1){
			$id = k_decrypt($_GET['id']);
		} else {
			$id = $_GET['id'];
		}
	}
	
	switch($mode)
	{
		
		/*
			function getXML($table,$query,$limit,$sortBy,$sortOrder,$title,$mode)
			$table = table without prefix, 
			$query = additional queries or conditions needed,
			$limit = limit of query how many results,
			$sortyBy = sort by table field, 
			$sortOrder = sort order of field, 
			$title = title of xml, 
			$mode = 1 = gallery of some type, 2 = news or articles (non-photos)
			$case = actual case called below
			$size = size of the photos shown on the feed
		*/
			
			
		case 'newestMedia':
			try
			{
				echo getXML('media','LEFT JOIN '.$dbinfo[pre].'media_galleries ON '.$dbinfo[pre].'media.media_id = '.$dbinfo[pre].'media_galleries.gmedia_id WHERE '.$dbinfo[pre].'media_galleries.gallery_id IN ('.$memberPermGalleriesForDB.') AND '.$dbinfo[pre].'media.active = 1 AND '.$dbinfo[pre].'media.approval_status = 1 GROUP BY '.$dbinfo[pre].'media.media_id','50',''.$dbinfo[pre].'media.date_added','desc','Newest Media',1,'newestMedia',400);
			} 
			catch(Exception $e)
			{
    		echo $e->getMessage();
  		}
		break;
		case 'popularMedia':
			try
			{
				echo getXML('media','LEFT JOIN '.$dbinfo[pre].'media_galleries ON '.$dbinfo[pre].'media.media_id = '.$dbinfo[pre].'media_galleries.gmedia_id WHERE '.$dbinfo[pre].'media_galleries.gallery_id IN ('.$memberPermGalleriesForDB.') AND '.$dbinfo[pre].'media.active = 1 AND '.$dbinfo[pre].'media.approval_status = 1 GROUP BY '.$dbinfo[pre].'media.media_id','50',''.$dbinfo[pre].'media.views','desc','Popular Media',1,'popularMedia',400);
			} 
			catch(Exception $e)
			{
    		echo $e->getMessage();
  		}
		break;
		case 'featuredMedia':
			try
			{
				echo getXML('media','LEFT JOIN '.$dbinfo[pre].'media_galleries ON '.$dbinfo[pre].'media.media_id = '.$dbinfo[pre].'media_galleries.gmedia_id WHERE '.$dbinfo[pre].'media_galleries.gallery_id IN ('.$memberPermGalleriesForDB.') AND '.$dbinfo[pre].'media.active = 1 AND '.$dbinfo[pre].'media.featured = 1 AND '.$dbinfo[pre].'media.approval_status = 1 GROUP BY '.$dbinfo[pre].'media.media_id','50',''.$dbinfo[pre].'media.date_added','desc','Featured Media',1,'featuredMedia',400);
			} 
			catch(Exception $e)
			{
    		echo $e->getMessage();
  		}
		break;
		case 'gallery':
			try
			{
				echo getXML('media','LEFT JOIN '.$dbinfo[pre].'media_galleries ON '.$dbinfo[pre].'media.media_id = '.$dbinfo[pre].'media_galleries.gmedia_id WHERE '.$dbinfo[pre].'media_galleries.gallery_id = '.$id.' AND '.$dbinfo[pre].'media.active = 1 AND '.$dbinfo[pre].'media.approval_status = 1 GROUP BY '.$dbinfo[pre].'media.media_id','50',''.$dbinfo[pre].'media.date_added','desc','Gallery Media',1,'gallery',400);
			} 
			catch(Exception $e)
			{
    		echo $e->getMessage();
  		}
		break;
		case 'search':
		
		break;
		case 'news':
			echo getXML('news','WHERE active = 1 AND add_date < \''.$nowGMT.'\' AND (expire_type = 0 OR expire_date > \''.$nowGMT.'\')','25','add_date','desc','News',0,'news','');
		break;
	}	
	
?>