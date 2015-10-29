<?php
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','treeData'); // Page ID
	define('ACCESS','public'); // Page access type - public|private
	define('INIT_SMARTY',true); // Use Smarty
	require_once BASE_PATH.'/assets/includes/session.php';
	require_once BASE_PATH.'/assets/includes/initialize.php';
	require_once BASE_PATH.'/assets/includes/shared.functions.php';
	
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past	

	if($_REQUEST['mode']) // Get the mode for the tree data
		$mode = $_REQUEST['mode'];
	else
		$mode = 'regular';
		
	//print_r($_SESSION['galleriesData']); exit; // testing
		
	function getTree($parentID,$level)
	{	
		global $config;
		
		$childCount = 0;
		$cCounter = 1;
		
		if($children = findChildren($parentID))
		{
			$childCount = count($children);
			$cCounter = 1;
			
			foreach($children as $value)
			{
				for($x=0;$x<$level;$x++)
				{
					//echo "&nbsp;";	
				}
				
				$galleryNameText = $_SESSION['galleriesData'][$value]['name'];
				//$galleryNameText = str_replace("'",'\'',$galleryNameText);
				//$galleryNameText = str_replace('"','&quot;',$galleryNameText);
				$galleryNameText = str_replace('\\','',$galleryNameText);
				
				$galleryNameText = htmlspecialchars($galleryNameText); // New in 4.4.7
				
				/*
				$galleryNameText = str_replace('(','',$galleryNameText);
				$galleryNameText = str_replace(')','',$galleryNameText);
				$galleryNameText = str_replace('/','',$galleryNameText);
				$galleryNameText = str_replace(')','',$galleryNameText);
				*/
				
				//$galleryNameText = cleanString($galleryNameText);
				if($config['settings']['gallery_count'] and $_SESSION['galleriesData'][$value]['gallery_count']) $galleryNameText .= " (".$_SESSION['galleriesData'][$value]['gallery_count'].")";
				if($_SESSION['galleriesData'][$value]['password']) $galleryNameText .= " <span class='treeLock'>&nbsp;&nbsp;&nbsp;</span>";
				$linkto =  $_SESSION['galleriesData'][$value]['linkto'];
				
				echo
				"{ 
					\"attr\" : { \"id\" : \"galleryTree{$value}\" }, 
					\"data\" : { 
						\"title\" : \"{$galleryNameText}\", 
						\"level\" : \"{$level}\", 
						\"attr\" : { \"href\" : \"{$linkto}\" }, 
						\"icon\" : \"\" 
					},
					\"children\" : [ ";
						
						if($value)
							getTree($value,$level+1); 
									
				echo " ]";
				// ,\"state\" : \"closed\"
				echo "}";
				
				//echo "<br /><br />"; // For testing
				
				if($cCounter < $childCount)
					echo ",";
					
				$cCounter++;
			}
		}
	}
	
	switch($mode)
	{
		default:
			function findChildren($parentID)
			{	
				foreach($_SESSION['galleriesData'] as $key => $gallery)
				{
					if($gallery['album'] == 0)
					{
						if($gallery['parent_gal'] == $parentID and $gallery['gallery_id']) // avoid loading gallery called "gallery"
							$children[] = $key;
					}
				}
				
				if(count($children) > 0)
					return $children;
				else
					return false;
			}
		break;
		case "contr":
			function findChildren($parentID)
			{	
				foreach($_SESSION['galleriesData'] as $key => $gallery)
				{	
					if($gallery['album'] == 0 and $gallery['allow_uploads'] == 1) //Only non albums and only those that allow uploading
					{
						if($gallery['parent_gal'] == $parentID and $gallery['gallery_id']) // avoid loading gallery called "gallery"
							$children[] = $key;
					}
				}
				
				if(count($children) > 0)
					return $children;
				else
					return false;
			}
		break;
	}
	
	
	
	echo "[";
	
	getTree(0,0);
	
	echo "]";
?>