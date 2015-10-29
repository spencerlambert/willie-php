<?php
	/******************************************************************
	*  Copyright 2011 Ktools.net LLC - All Rights Reserved
	*  http://www.ktools.net
	*  Created: 4-21-2011
	*  Modified: 4-21-2011
	******************************************************************/
	
	define('BASE_PATH',dirname(__FILE__)); // Define the base path
	define('PAGE_ID','search'); // Page ID
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

	define('META_TITLE',''); // Override page title, description, keywords and page encoding here
	define('META_DESCRIPTION','');
	define('META_KEYWORDS','');
	define('PAGE_ENCODING','');
	
	require_once BASE_PATH.'/assets/includes/header.inc.php';
	require_once BASE_PATH.'/assets/includes/errors.php';
	require_once BASE_PATH.'/assets/classes/paging.php';
	
	// Clean search keywords function
	function searchKeywordClean($keyword)
	{
		$keyword = trim($keyword);
		
		$nonSearchableKeywords = array('and','the'); // Keywords that should not be searchable
		
		if($_SESSION['searchForm']['keywords'])
		{
			foreach($_SESSION['searchForm']['keywords'] as $key => $keywordsArray)
			{
				if(in_array($keyword,$keywordsArray['words']))
				{
					$keywordExists = true;
					break;
				}
				else
					$keywordExists = false;
			}
		}
		
		if(strlen($keyword) > 2 and !in_array($keyword,$nonSearchableKeywords) and !$keywordExists)
		{
			$cleanKeyword = $keyword;
			return $cleanKeyword;
		}
		else
			return false;
	}
	
	// Pluralize keywords function - singularize and pluralize
	function pluralizeKeywords($keyword)
	{
		
	}
	
	// XXXXXXXX Search fields
	// Title
	// Description
	
	//echo 'psf: '.$postSearchForm.'-';
	
	/*
	* Paging
	*/
	$mediaPerPage = $config['settings']['media_perpage']; // Set the default media per page amount
	$mediaPages = new paging('search');
	$mediaPages->setPerPage($mediaPerPage);
	$mediaPages->setPageName('search.php?');
	$mediaPages->setPageVar();
	
	// No get or post data sent or a clearSearch request - resetting search
	if((!$_GET and !$_POST) or $_REQUEST['clearSearch'])
	{
		$mediaPages->setCurrentPage(1);
		unset($_SESSION['searchForm']);
		
		$_SESSION['searchForm']['allFields'] = true;
	}
	
	//echo $_SESSION['searchForm']['firstLoad'];
	
	if($postSearchForm)
	{
		$mediaPages->setCurrentPage(1);
		unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
	}

	if($_GET['clear'])
	{
		// Clear individual data
		switch($_GET['clear'])
		{
			case "mediaTypes":
				unset($_SESSION['searchForm']['mediaTypes']);
				unset($_SESSION['searchForm']['seachForIDs']['mediaTypes']);
			break;
			case "orientations":
				unset($_SESSION['searchForm']['orientations']);
				unset($_SESSION['searchForm']['seachForIDs']['orientations']);
			break;
			case "licenses":
				unset($_SESSION['searchForm']['licenses']);
				unset($_SESSION['searchForm']['seachForIDs']['licenses']);
			break;
			case "galleries":
				unset($_SESSION['searchForm']['galleries']);
				unset($_SESSION['searchForm']['seachForIDs']['galleries']);
			break;
			case "colors":
				unset($_SESSION['searchForm']['hex']);
				unset($_SESSION['searchForm']['red']);
				unset($_SESSION['searchForm']['green']);
				unset($_SESSION['searchForm']['blue']);
				unset($_SESSION['searchForm']['seachForIDs']['colors']);
				unset($_SESSION['searchForm']['searchSortBy']);
			break;
			case "searchPhrase":
				unset($_SESSION['searchForm']['keywords'][$batch]);
				unset($_SESSION['searchForm']['seachForIDs']['keywords']);
			break;
			case "dates":
				unset($_SESSION['searchForm']['searchDate']);
			break;
			case "fields":
				unset($_SESSION['searchForm']['fields']);
				$_SESSION['searchForm']['allFields'] = true;
			break;
		}
		$mediaPages->setCurrentPage(1);
		
		unset($_SESSION['searchForm']['resultsArray']); // Clear previous resultsArray
	}
	
	if($page)
		$mediaPages->setCurrentPage($page); // Set new search page	
	else
		$mediaPages->setCurrentPage($_SESSION['searchCurrentPage']); // Use session current page
		
	$mediaStartRecord = $mediaPages->getStartRecord(); // Get the record the db should start at
	
	//echo "{$mediaStartRecord},{$mediaPerPage} - ".$_SESSION['mediaCurrentPage'];
	//$mediaStartRecord = 0;
	
	if($_REQUEST['searchSortBy'])
		$_SESSION['searchForm']['searchSortBy'] = $_REQUEST['searchSortBy'];
		
	if($_REQUEST['searchSortType'])
		$_SESSION['searchForm']['searchSortType'] = $_REQUEST['searchSortType'];
	
	if(!$_SESSION['searchForm']['searchSortBy'])
		$_SESSION['searchForm']['searchSortBy'] = 'date_added';
		
	if(!$_SESSION['searchForm']['searchSortType'])
	{
		switch($_SESSION['searchForm']['searchSortType'])
		{
			default:
			case 'date_added':
			case 'media_id':
			case 'filesize':
			case 'width':
			case 'height':
			case 'views':
				$_SESSION['searchForm']['searchSortType'] = 'desc';
			break;
			case 'title':
				$_SESSION['searchForm']['searchSortType'] = 'asc';
			break;
		}
	}
	
	// Parse the search phrase
	if($_REQUEST['searchPhrase'])
	{	
		//unset($_SESSION['searchForm']['keywords']); // Clear keywords first
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		
		if(!$_SESSION['searchForm']['fields'])
			$_SESSION['searchForm']['keywordsSearch'] = true;
		
		
		if($_REQUEST['searchPhrase'] != $lang['enterKeywords']) // Make sure the phrase 'Enter Keywords' is not included in the search
		{
			$searchPhrase = strtolower($_REQUEST['searchPhrase']);
			$keywords = array_filter(explode(" ",$searchPhrase),"searchKeywordClean");
			
			if(count($keywords) > 0)
			{
				$_SESSION['searchForm']['keywordBatch']++; // Set a new batch on the keywords
				$keywordBatch = $_SESSION['searchForm']['keywordBatch']; // Set local
				
				$hiddenKeywords = array_filter($keywords,'pluralizeKeywords'); // Make a hidden array of the keywords pluralized	
				foreach($hiddenKeywords as $key => $keyword)
					$_SESSION['searchForm']['keywords'][$keywordBatch]['hiddenWords'][] = $keyword;
				
				foreach($keywords as $key => $keyword)
					$_SESSION['searchForm']['keywords'][$keywordBatch]['words'][] = $keyword;
				
				if($keywordBatch > 1)
					$_SESSION['searchForm']['keywords'][$keywordBatch]['connector'] = $searchConnector; // Search connector
			}
					
			//echo $keywordBatch.'<br>';
			//print_r($_SESSION['searchForm']['keywords']);
		}
		$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Fields to search
	if($_REQUEST['fields'])
	{
		unset($_SESSION['searchForm']['fields']); // Clear licenses first
		
		$_SESSION['searchForm']['allFields'] = false;
		
		foreach($_REQUEST['fields'] as $key => $value)
			$_SESSION['searchForm']['fields'][$key] = $value;
		
		
		//print_r($_SESSION['searchForm']['fields']);
		
		/*
		
		$_SESSION['searchForm']['keywordsSearch'] = false;
		
		
		foreach($_REQUEST['fields'] as $key => $value)
			$_SESSION['searchForm']['fields'][$key] = $value;
		//print_r($_SESSION['searchForm']['fields']);
		
		if(!in_array('keywords',array_keys($_REQUEST['fields'])))
			$_SESSION['searchForm']['keywordsSearch'] = true;
		
		if(!in_array('keywords',array_keys($_REQUEST['fields'])))
		{
			unset($_SESSION['searchForm']['seachForIDs']['keywords']);
			//unset($_SESSION['searchForm']['keywords']);
		}
		*/
		
		$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Color search passed
	if($_REQUEST['hex'])
	{	
		//if(!$_SESSION['searchForm']['keywords']) $_SESSION['searchForm']['allFields'] = false;
		
		$_SESSION['searchForm']['searchSortBy'] = 'color';
		
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		$_SESSION['searchForm']['hex'] = $_REQUEST['hex'];
		
		if($_REQUEST['red']) // Only hex is being passed - find rgb
		{
			$_SESSION['searchForm']['red'] = $_REQUEST['red'];
			$_SESSION['searchForm']['green'] = $_REQUEST['green'];
			$_SESSION['searchForm']['blue'] = $_REQUEST['blue'];
		}
		else
		{
			$rgbColor = html2rgb($_REQUEST['hex']); // Convert hex to rgb
			$_SESSION['searchForm']['red'] = $rgbColor['red'];
			$_SESSION['searchForm']['green'] = $rgbColor['green'];
			$_SESSION['searchForm']['blue'] = $rgbColor['blue'];
		}
		$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Orientation search passed
	if($_REQUEST['orientations'])
	{
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		unset($_SESSION['searchForm']['orientations']); // Clear ori first
		foreach($_REQUEST['orientations'] as $key => $value)
			$_SESSION['searchForm']['orientations'][$key] = $value;
		//print_r($_SESSION['searchForm']['orientations']);
		$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Date ranges
	//echo $_REQUEST['searchDate']['dateRangeSearch'];
	if(isset($_REQUEST['searchDate']['dateRangeSearch']))
	{
		unset($_SESSION['searchForm']['searchDate']); // Clear searchDate first
		if($_REQUEST['searchDate']['dateRangeSearch'] == 'on')
		{
			$_SESSION['searchForm']['searchDate'] = $_REQUEST['searchDate']; // Set to a session
			$_SESSION['searchForm']['inSearch'] = true;
		}
	}	
	if(!$_SESSION['searchForm']['searchDate'])
	{
		$_SESSION['searchForm']['searchDate']['toYear'] = date("Y");
		$_SESSION['searchForm']['searchDate']['toMonth'] = date("m");
		$_SESSION['searchForm']['searchDate']['toDay'] = date("d");
	}
		
	// License search passed
	if($_REQUEST['licenses'])
	{
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		unset($_SESSION['searchForm']['licenses']); // Clear licenses first
		foreach($_REQUEST['licenses'] as $key => $value)
			$_SESSION['searchForm']['licenses'][$key] = $value;
		//print_r($_SESSION['searchForm']['licenses']);
		$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Media type passed
	if($_REQUEST['mediaTypes'])
	{
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		unset($_SESSION['searchForm']['mediaTypes']); // Clear mediaTypes first
		foreach($_REQUEST['mediaTypes'] as $key => $mediaTypeID)
			$_SESSION['searchForm']['mediaTypes'][$key] = $mediaTypeID;
		//print_r($_SESSION['searchForm']['mediaTypes']);
		$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Galleries passed
	if($_REQUEST['galleries'])
	{
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		unset($_SESSION['searchForm']['galleries']); // Clear galleries first
		if(is_array($_REQUEST['galleries'])) // Check to see if an array or single gallery was passed
		{
			foreach($_REQUEST['galleries'] as $key => $galleryID)
				$_SESSION['searchForm']['galleries'][$key] = $galleryID;
		}
		else
			$_SESSION['searchForm']['galleries'][$_REQUEST['galleries']] = $_REQUEST['galleries']; // Single gallery passed
		//print_r($_SESSION['searchForm']['galleries']);
		$_SESSION['searchForm']['inSearch'] = true;
	}
	//else
	//	unset($_SESSION['searchForm']['galleries']); // Clear galleries first
	
	if($postForm)
	{
		$_SESSION['searchForm']['inSearch'] = true;
		unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
	}
	
	try
	{	
		if($_SESSION['searchForm']['inSearch']) // Make sure we are in a search before searching
		{	
			if(!$_SESSION['searchForm']['resultsArray']) // No results array exists yet - 
			{				
				//echo "no results session";
				
				$resultsArray = array(); // Start the results array blank
				$totalRowsFound = 1;
				
				function wrapInQuotes($item)
				{
					return "'".$item."'";
				}
				
				// Keywords
				if($_SESSION['searchForm']['fields']['keywords'] or $_SESSION['searchForm']['allFields']) // $_SESSION['searchForm']['fields'] or $_SESSION['searchForm']['fields']['keywords']
				{	
					$_SESSION['searchForm']['seachForIDs']['keywords'] = true;
					
					foreach($_SESSION['searchForm']['keywords'] as $key => $batch)
					{
						$flatKeywords = implode(',',array_map('wrapInQuotes',$batch['words']));
						
						$wordCounter = 1;
						foreach($batch['words'] as $keyword)
						{
							if(strpos($keyword,'"') !== false)
								$searchSubSQL = "= '".str_replace('"','',$keyword)."'";
							else
								$searchSubSQL = "LIKE '%{$keyword}%'";
							
							$searchSQL.= "keyword {$searchSubSQL}"; 
							if($wordCounter < count($batch['words']))
								$searchSQL.= ' OR ';
							$wordCounter++;
						}
						$wordCounter = 1;
						
						//echo $searchSQL; exit;
						
						$keywordsResult = mysqli_query($db,
						"
							SELECT * FROM {$dbinfo[pre]}keywords 
							WHERE {$searchSQL}
						");
						/*
						$keywordsResult = mysqli_query($db,
						"
							SELECT * FROM {$dbinfo[pre]}keywords 
							WHERE MATCH (keyword) 
							AGAINST ('{$flatKeywords}' IN BOOLEAN MODE)
						");
						*/
						$keywordsRows = mysqli_num_rows($keywordsResult);
						
						//echo 'keyrows'. $keywordsRows;
						
						if($keywordsRows)
						{
							while($keyword = mysqli_fetch_assoc($keywordsResult))
							{								
								/*
								switch($batch['connector'])
								{
									default:
									case 'OR':
										$orKeywords[$keyword['media_id']] = $keyword['media_id'];
									break;
									case 'AND':
										$andKeywords[$keyword['media_id']] = $keyword['media_id'];
									break;
									case 'NOT':
										$notKeywords[$keyword['media_id']] = $keyword['media_id'];
									break;
									//array_diff
								}
								*/
								$runningKeywords[$keyword['media_id']] = $keyword['media_id'];
									
								//echo "<br>Return Key Search: {$keyword[media_id]} - {$keyword[keyword]}<br>";
							}
							
							//echo "<br>Rows: {$keywordsRows} - {$batch[connector]}<br>";
						}
						
						$searchSQL='';
					}
					
					if(count($runningKeywords) > 0) $runningKeywords = array_unique($runningKeywords);
					
					/*
					if($runningKeywords)
					{
						foreach($runningKeywords as $key => $mid)
						{
							if(@in_array($key,$notKeywords))
								unset($runningKeywords[$keyword['media_id']]);
								
							//if(in_array($key,$notKeywords))
							//	unset($runningKeywords[$keyword['media_id']]);
						}
					}
					*/
					
					//$resultsArray = $runningKeywords;
					
					$_SESSION['searchForm']['keywordsResultsArray'] = $runningKeywords;
					
					$totalRowsFound = count($runningKeywords);
					
					//@$keywordMediaResults = array_unique($keywordMediaResults); // Only unique keyword media ids
					//echo "<br>keywordMediaResults: ";		
					//print_r($resultsArray);
					//echo "<br><br>";
				}
				
				/*
				if($_SESSION['searchForm']['searchDate']['dateRangeSearch'] and $totalRowsFound > 0)
				{
					$sqlFromDate = "{$_SESSION[searchForm][searchDate][fromYear]}-{$_SESSION[searchForm][searchDate][fromMonth]}-{$_SESSION[searchForm][searchDate][fromDay]} 00:00:00";
					$sqlToDate = "{$_SESSION[searchForm][searchDate][toYear]}-{$_SESSION[searchForm][searchDate][toMonth]}-{$_SESSION[searchForm][searchDate][toDay]} 00:00:00";
					
					//echo $sqlToDate; exit;
					
					
					$dateRangeCount = mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM {$dbinfo[pre]}media WHERE date_added > '{$sqlFromDate}' AND date_added < '{$sqlToDate}' LIMIT 20"));
				}
				*/
				
				// Color
				/*
				if($_SESSION['searchForm']['hex'] and $totalRowsFound > 0)
				{
					$_SESSION['searchForm']['seachForIDs']['colors'] = true;
					
					$red = $_SESSION['searchForm']['red']; // Convert to local
					$green = $_SESSION['searchForm']['green'];
					$blue = $_SESSION['searchForm']['blue'];
					
					if(!$red) $red = 0; // Make sure values are set
					if(!$green) $green = 0;
					if(!$blue) $blue = 0;
					
					//echo "{$red}-{$green}-{$blue}<br>"; // Output red, green, blue for testing
					
					$redMin = $red - $config['colorSearchVariance'];
					$redMax = $red + $config['colorSearchVariance'];	
					$greenMin = $green - $config['colorSearchVariance'];
					$greenMax = $green + $config['colorSearchVariance'];	
					$blueMin = $blue - $config['colorSearchVariance'];
					$blueMax = $blue + $config['colorSearchVariance'];
					
					$colorSearchSQL =
					"
						SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}color_palettes 
						WHERE (red BETWEEN {$redMin} AND {$redMax}) 
						AND (green BETWEEN {$greenMin} AND {$greenMax}) 
						AND (blue BETWEEN {$blueMin} AND {$blueMax}) 
						AND percentage > {$config[colorSearchMinimum]}
					";
					if(count($resultsArray) > 0)
					{
						$resultsFlat = implode(',',$resultsArray); // Flatten the results array
						$colorSearchSQL.=" AND media_id IN ({$resultsFlat})"; // If results have already been found then add those to the query
					}
					$colorSearchSQL.=" ORDER BY percentage DESC";
					$colorSearchResult = mysqli_query($db,$colorSearchSQL);
					//$colorSearchRows = mysqli_num_rows($colorSearchResult);
					$colorSearchRows = getRows();
					if($colorSearchRows > 0)
					{
						$totalRowsFound = $colorSearchRows; // Set total rows found to rows from this area search
						while($color = mysqli_fetch_assoc($colorSearchResult))
						{
							if($resultsArray[$color['media_id']]) // Already exists
							{	
								if($resultsArray[$color['media_id']] < $color['percentage'])
									$resultsArray[$color['media_id']] = $color['percentage']; // set a new weight
							}
							else
								$resultsArray[$color['media_id']] = $color['percentage']; // set a weight
								
							//echo "<p style='background-color: #{$color[hex]}'>{$color[hex]} - {$color[media_id]}</p><br>";
						}
						//echo "<strong>Color:</strong> Results (pre search): {$resultsFlat} | Count: {$colorSearchRows}<br><br>";
					}
				}
				
				echo "Media IDs - ";
				print_r($resultsArray);
				echo "<br><br>";
				*/
				
				// Orientation
				/*
				if(count($_SESSION['searchForm']['orientations']) > 0 and $totalRowsFound > 0)
				{
					$_SESSION['searchForm']['seachForIDs']['orientations'] = true;
					
					$orientationsSQL = "SELECT width,height,media_id FROM {$dbinfo[pre]}media WHERE active = 1 AND width > 0 AND height > 0";
					$connector = 'AND (';
					if($_SESSION['searchForm']['orientations']['portrait'])
					{
						$orientationsSQL.= " {$connector} height > width";
						$connector = 'OR';
					}
					if($_SESSION['searchForm']['orientations']['landscape'])
					{
						$orientationsSQL.= " {$connector} width > height";
						$connector = 'OR';
					}	
					if($_SESSION['searchForm']['orientations']['square'])
						$orientationsSQL.= " {$connector} height = width";

					if(count($resultsArray) > 0)
					{
						$resultsFlat = implode(',',array_keys($resultsArray)); // Flatten the results array
						$orientationsSQL.=" AND media_id IN ({$resultsFlat})"; // If results have already been found then add those to the query
					}
					
					$orientationsSQL.=")";
					//echo $orientationsSQL;
					$orientationsResult = mysqli_query($db,$orientationsSQL);
					//$orientationsCount = mysqli_num_rows($orientationsResult);
					$orientationsCount = getRows();
					if($totalRowsFound > 0 and $orientationsCount > 0) // Only do this if previous rows have been found
					{
						$totalRowsFound = $orientationsCount; // Set total rows found to rows from this area search
						while($orientation = mysqli_fetch_assoc($orientationsResult))
							$orientationResultsArray[$orientation['media_id']] = '0'; // Assign the media ids to the results array - set it to a value of 0
						$resultsArray = $orientationResultsArray; // Update the results array
						//echo "<strong>Orientations:</strong> Results (pre search): {$resultsFlat} | Count: {$orientationsCount}<br><br>";
					}
					else
					{
						unset($resultsArray);
						$totalRowsFound = 0; // Nothing found here - set total rows found to 0
					}
				}
				*/
				/*
				echo "Media IDs - ";
				print_r($resultsArray);
				echo "<br><br>";
				*/
				
				// License
				if(count($_SESSION['searchForm']['licenses']) > 0 and $totalRowsFound > 0)
				{
					$_SESSION['searchForm']['seachForIDs']['licenses'] = true;
					
					$licensesSQL = "SELECT SQL_CALC_FOUND_ROWS license,media_id FROM {$dbinfo[pre]}media WHERE active = 1";
					$connector = 'AND (';
					if($_SESSION['searchForm']['licenses']['royaltyFree'])
					{
						$licensesSQL.= " {$connector} license = 'rf'";
						$connector = 'OR';
					}
					if($_SESSION['searchForm']['licenses']['rightsManaged'])
					{
						$licensesSQL.= " {$connector} license = 'rm'";
						$connector = 'OR';
					}
					if($_SESSION['searchForm']['licenses']['free'])
					{
						$licensesSQL.= " {$connector} license = 'fr'";
						$connector = 'OR';
					}
					if($_SESSION['searchForm']['licenses']['contactUs'])
						$licensesSQL.= " {$connector} license = 'cu'";
					$licensesSQL.=")";
					
					if(count($resultsArray) > 0)
					{
						$resultsFlat = implode(',',array_keys($resultsArray)); // Flatten the results array
						$licensesSQL.=" AND media_id IN ({$resultsFlat})"; // If results have already been found then add those to the query
					}
					//echo $licensesSQL;
					$licensesResult = mysqli_query($db,$licensesSQL);
					$licensesCount = getRows(); //mysqli_num_rows($licensesResult);
					if($totalRowsFound > 0 and $licensesCount > 0) // Only do this if previous rows have been found
					{
						$totalRowsFound = $licensesCount; // Set total rows found to rows from this area search
						while($license = mysqli_fetch_assoc($licensesResult))
							$licenseResultsArray[$license['media_id']] = '0'; // Assign the media ids to the results array
						$resultsArray = $licenseResultsArray; // Update the results array	
						//echo "<strong>Licenses:</strong> Results (pre search): {$resultsFlat} | Count: {$licensesCount}<br><br>";
					}
					else
					{
						unset($resultsArray);
						$totalRowsFound = 0; // Nothing found here - set total rows found to 0
					}
				}
				
				/*
				echo "Media IDs - ";
				print_r($resultsArray);
				echo "<br><br>";
				*/
				
				// Media Type
				if(count($_SESSION['searchForm']['mediaTypes']) > 0 and $totalRowsFound > 0)
				{
					$_SESSION['searchForm']['seachForIDs']['mediaTypes'] = true;
					
					$mediaTypesFlat = implode(',',$_SESSION['searchForm']['mediaTypes']); // Flatten the mediaTypes array to use it in the search
					$mediaTypesSQL = "SELECT * FROM {$dbinfo[pre]}media_types_ref WHERE mt_id IN ({$mediaTypesFlat})"; // Build the query string
					if(count($resultsArray) > 0)
					{
						$resultsFlat = implode(',',array_keys($resultsArray)); // Flatten the results array
						$mediaTypesSQL.=" AND media_id IN ({$resultsFlat})"; // If results have already been found then add those to the query
					}
					$mediaTypesResult = mysqli_query($db,$mediaTypesSQL);
					$mediaTypesCount = getRows(); //mysqli_num_rows($mediaTypesResult);
					if($totalRowsFound > 0 and $mediaTypesCount > 0) // Only do this if previous rows have been found
					{
						$totalRowsFound = $mediaTypesCount; // Set total rows found to rows from this area search
						while($mediaType = mysqli_fetch_assoc($mediaTypesResult))
							$mediaTypeResultsArray[$mediaType['media_id']] = '0'; // Assign the media ids to the results array
						$resultsArray = $mediaTypeResultsArray; // Update the results array	
						//echo "<strong>Media Types:</strong> Results (pre search): {$resultsFlat} | mediaTypes Flat: {$galleriesFlat} | Count: {$mediaTypesCount}<br><br>";
					}
					else
					{
						unset($resultsArray);
						$totalRowsFound = 0; // Nothing found here - set total rows found to 0
					}
				}
				
				/*
				echo "Media IDs - ";
				print_r($resultsArray);
				echo "<br><br>";
				*/
				
				// Galleries
				if(count($_SESSION['searchForm']['galleries']) > 0 and $totalRowsFound > 0)
				{
					$_SESSION['searchForm']['seachForIDs']['galleries'] = true;
					
					$galleriesFlat = implode(',',$_SESSION['searchForm']['galleries']); // Flatten the galleries array to use it in the search
					$galleriesSQL = "SELECT SQL_CALC_FOUND_ROWS * FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$galleriesFlat})"; // Build the query string
					if(count($resultsArray) > 0)
					{
						$resultsFlat = implode(',',array_keys($resultsArray)); // Flatten the results array
						$galleriesSQL.=" AND gmedia_id IN ({$resultsFlat})"; // If results have already been found then add those to the query
					}
					$galleriesResult = mysqli_query($db,$galleriesSQL);
					$galleriesCount = getRows(); //mysqli_num_rows($galleriesResult);
					if($totalRowsFound > 0 and $galleriesCount > 0) // Only do this if previous rows have been found
					{
						$totalRowsFound = $galleriesCount; // Set total rows found to rows from this area search
						while($gallery = mysqli_fetch_assoc($galleriesResult))
							$galleryResultsArray[$gallery['gmedia_id']] = '0'; // Assign the media ids to the results array
						$resultsArray = $galleryResultsArray; // Update the results array	
						//echo "<strong>Galleries:</strong> Results (pre search): {$resultsFlat} | Galleries Flat: {$galleriesFlat} | Count: {$galleriesCount}<br><br>";
					}
					else
					{
						unset($resultsArray);
						$totalRowsFound = 0; // Nothing found here - set total rows found to 0
					}
				}
				
				/*
				echo "Media IDs - ";
				print_r($resultsArray);
				echo "<br><br>";
				*/
				
				$_SESSION['searchForm']['resultsArray'] = $resultsArray; // Grab media ids
				//@arsort($_SESSION['searchForm']['resultsArray']); // Sort the array by its weight
			}
			
			//echo "Unique IDs = ";
			//print_r($_SESSION['searchForm']['resultsArray']);
			//echo "<br><br>";
			
			/*
			if($_SESSION['searchForm']['searchDate']['dateRangeSearch'])
			{
				$_SESSION['searchForm']['resultsArray'][] = 0;	
			}
			*/
			
			/*
			* Get all the media information and pass it to smarty
			*/
			//if(count($_SESSION['searchForm']['resultsArray']) > 0)
			//{
				if(count($_SESSION['searchForm']['resultsArray']) > 0)
					$mediaIDsFlat = implode(',',array_keys($_SESSION['searchForm']['resultsArray']));
				else
					$mediaIDsFlat = '0';
				
				if(count($_SESSION['searchForm']['keywordsResultsArray']) > 0)
					$keywordIDsFlat = implode(',',array_keys($_SESSION['searchForm']['keywordsResultsArray']));
				else
					$keywordIDsFlat = '0';
				
				//$sql = "SELECT * FROM {$dbinfo[pre]}media WHERE media_id IN ({$mediaIDsFlat}) LIMIT 10";
				
				if($_SESSION['searchForm']['hex']) // Color search
				{
					$red = $_SESSION['searchForm']['red']; // Convert to local
					$green = $_SESSION['searchForm']['green'];
					$blue = $_SESSION['searchForm']['blue'];
					
					if(!$red) $red = 0; // Make sure values are set
					if(!$green) $green = 0;
					if(!$blue) $blue = 0;
					
					//echo "{$red}-{$green}-{$blue}<br>"; // Output red, green, blue for testing
					
					$redMin = $red - $config['colorSearchVariance'];
					$redMax = $red + $config['colorSearchVariance'];	
					$greenMin = $green - $config['colorSearchVariance'];
					$greenMax = $green + $config['colorSearchVariance'];	
					$blueMin = $blue - $config['colorSearchVariance'];
					$blueMax = $blue + $config['colorSearchVariance'];
				
				}
				
				/*
					//$resultsArraySQL = (count($_SESSION['searchForm']['resultsArray']) > 0) ? "AND {$dbinfo[pre]}media.media_id IN ({$mediaIDsFlat}) " : ''; 
					
					$sql = 
					"
						SELECT SQL_CALC_FOUND_ROWS *
						FROM {$dbinfo[pre]}media
						LEFT JOIN {$dbinfo[pre]}media_galleries 
						ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id 
						LEFT JOIN {$dbinfo[pre]}color_palettes 
						ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}color_palettes.media_id 
						WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB}) 
					";
					if($_SESSION['searchForm']['seachForIDs']) $sql.= "AND {$dbinfo[pre]}media.media_id IN ({$mediaIDsFlat}) ";
					$sql.=
					"
						AND ({$dbinfo[pre]}color_palettes.red BETWEEN {$redMin} AND {$redMax}) 
						AND ({$dbinfo[pre]}color_palettes.green BETWEEN {$greenMin} AND {$greenMax}) 
						AND ({$dbinfo[pre]}color_palettes.blue BETWEEN {$blueMin} AND {$blueMax}) 
						AND {$dbinfo[pre]}color_palettes.percentage > {$config[colorSearchMinimum]} 
						AND {$dbinfo[pre]}media.active = 1 
						GROUP BY {$dbinfo[pre]}media.media_id 
						ORDER BY {$dbinfo[pre]}color_palettes.percentage DESC 
						LIMIT {$mediaStartRecord},{$mediaPerPage}
					";
					//$mediaCount = mysqli_num_rows(mysqli_query($db,$sql)); // Get the total number of items
					//$mediaPages->setTotalResults($mediaCount); // Pass the total number of results to the $pages object
					//$sql.= " LIMIT {$mediaStartRecord},{$mediaPerPage}";
				}
				else // Non color search
				{
				*/
					$keywords = array();
					if($_SESSION['searchForm']['keywords'])
					{
						foreach($_SESSION['searchForm']['keywords'] as $key => $batch)
							array_push($keywords,implode(',',$batch['words']));
					}
					
					if($_SESSION['searchForm']['fields']['title'] or $_SESSION['searchForm']['allFields'])
					{
						$wordCounter=1;
						//$titleSQL = ' (';
						foreach($keywords as $keyword)
						{
							if(strpos($keyword,'"') !== false)
								$titleSubSQL = "= '".str_replace('"','',$keyword)."'";
							else
								$titleSubSQL = "LIKE '%{$keyword}%'";
							
							$titleSQL.= "{$dbinfo[pre]}media.title {$titleSubSQL}";
							
							if($wordCounter < count($keywords)) $titleSQL.= ' OR ';							
							
							$wordCounter++;
						}
						//$titleSQL.= ') ';
					}
					
					//echo $titleSQL;
					
					if($_SESSION['searchForm']['fields']['description'] or $_SESSION['searchForm']['allFields'])
					{
						$wordCounter=1;
						//$descriptionSQL = ' (';
						foreach($keywords as $keyword)
						{
							if(strpos($keyword,'"') !== false)
								$descriptionSubSQL = "= '".str_replace('"','',$keyword)."'";
							else
								$descriptionSubSQL = "LIKE '%{$keyword}%'";
							
							$descriptionSQL.= "{$dbinfo[pre]}media.description {$titleSubSQL}";
							
							if($wordCounter < count($keywords)) $descriptionSQL.= ' OR ';							
							
							$wordCounter++;
						}
						//$descriptionSQL.= ') ';
					}
					
					if($_SESSION['searchForm']['fields']['filename'] or $_SESSION['searchForm']['allFields'])
					{
						$wordCounter=1;
						//$filenameSQL = ' (';
						foreach($keywords as $keyword)
						{
							if(strpos($keyword,'"') !== false)
								$filenameSubSQL = "= '".str_replace('"','',$keyword)."'";
							else
								$filenameSubSQL = "LIKE '%{$keyword}%'";
							
							$filenameSQL.= "{$dbinfo[pre]}media.filename {$filenameSubSQL}";
							
							if($wordCounter < count($keywords)) $filenameSQL.= ' OR ';							
							
							$wordCounter++;
						}
						//$filenameSQL.= ') ';
					}
				
					if(
					   $_SESSION['searchForm']['seachForIDs']['licenses'] or
					   $_SESSION['searchForm']['seachForIDs']['mediaTypes'] or
					   $_SESSION['searchForm']['seachForIDs']['galleries']
					   ) $searchForIDs = true; // $_SESSION['searchForm']['seachForIDs']['colors'] OR $_SESSION['searchForm']['seachForIDs']['keywords'] OR $_SESSION['searchForm']['seachForIDs']['orientations'] or
					
					$sql = 
					"
						SELECT SQL_CALC_FOUND_ROWS *
						FROM {$dbinfo[pre]}media
						LEFT JOIN {$dbinfo[pre]}media_galleries
						ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id 
					";
					
					if($_SESSION['searchForm']['hex'])
						$sql.=
						"
							LEFT JOIN {$dbinfo[pre]}color_palettes 
							ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}color_palettes.media_id 
						";
					
					$sql.= "WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$memberPermGalleriesForDB}) ";
					
					if($_SESSION['searchForm']['hex'])
						$sql.=
						"
							AND ({$dbinfo[pre]}color_palettes.red BETWEEN {$redMin} AND {$redMax}) 
							AND ({$dbinfo[pre]}color_palettes.green BETWEEN {$greenMin} AND {$greenMax}) 
							AND ({$dbinfo[pre]}color_palettes.blue BETWEEN {$blueMin} AND {$blueMax}) 
							AND {$dbinfo[pre]}color_palettes.percentage > {$config[colorSearchMinimum]} 
						";
					
					if(
						$_SESSION['searchForm']['fields']['title'] or 
						$_SESSION['searchForm']['fields']['description'] or 
						$_SESSION['searchForm']['fields']['filename'] or 
						$_SESSION['searchForm']['allFields']
						)
					{	
						if($_SESSION['searchForm']['fields']['keywords'] or $_SESSION['searchForm']['allFields'])
							$sql.= "AND ({$dbinfo[pre]}media.media_id IN ({$keywordIDsFlat}) OR ";
						else
							$sql.= "AND (";
																						
						if($_SESSION['searchForm']['fields']['title'] or $_SESSION['searchForm']['allFields']) $sql.= $titleSQL;
					
						if($_SESSION['searchForm']['fields']['description'] or $_SESSION['searchForm']['allFields'])
						{
							if($_SESSION['searchForm']['fields']['title'] or $_SESSION['searchForm']['allFields']) $sql.=" OR ";
							$sql.= $descriptionSQL;
						}
						
						if($_SESSION['searchForm']['fields']['filename'] or $_SESSION['searchForm']['allFields'])
						{
							if($_SESSION['searchForm']['fields']['description'] or $_SESSION['searchForm']['allFields']) $sql.=" OR ";
							$sql.= $filenameSQL;
						}
						
						$sql.=")";
					}
					else
					{
						if($_SESSION['searchForm']['fields']['keywords'] or $_SESSION['searchForm']['allFields']) $sql.= "AND {$dbinfo[pre]}media.media_id IN ({$keywordIDsFlat}) ";
					}
					
					// Orientation
					if(count($_SESSION['searchForm']['orientations']) > 0)
					{
						$orientationsSQL = ''; //"SELECT width,height,media_id FROM {$dbinfo[pre]}media WHERE active = 1 AND width > 0 AND height > 0";
						$connector = 'AND (';
						if($_SESSION['searchForm']['orientations']['portrait'])
						{
							$orientationsSQL.= " {$connector} height > width";
							$connector = 'OR';
						}
						if($_SESSION['searchForm']['orientations']['landscape'])
						{
							$orientationsSQL.= " {$connector} width > height";
							$connector = 'OR';
						}	
						if($_SESSION['searchForm']['orientations']['square'])
							$orientationsSQL.= " {$connector} height = width";
						$orientationsSQL.= ") ";	
						
						$sql.= $orientationsSQL;
						
						//echo $orientationsSQL; exit;
					}
					//echo 'sfi'.$searchForIDs.'-<br>';

					if($searchForIDs) $sql.= "AND {$dbinfo[pre]}media.media_id IN ({$mediaIDsFlat}) ";
					
					if($_SESSION['searchForm']['searchDate']['dateRangeSearch'])
					{
						$sqlFromDate = "{$_SESSION[searchForm][searchDate][fromYear]}-{$_SESSION[searchForm][searchDate][fromMonth]}-{$_SESSION[searchForm][searchDate][fromDay]} 00:00:00";
						$sqlToDate = "{$_SESSION[searchForm][searchDate][toYear]}-{$_SESSION[searchForm][searchDate][toMonth]}-{$_SESSION[searchForm][searchDate][toDay]} 00:00:00";
						$sql.= "AND {$dbinfo[pre]}media.date_added > '{$sqlFromDate}' AND {$dbinfo[pre]}media.date_added < '{$sqlToDate}' ";
					}
					
					$sql.= 
					"	
						AND {$dbinfo[pre]}media.active = 1 
						GROUP BY {$dbinfo[pre]}media.media_id 
					";
					
					if($_SESSION['searchForm']['hex'])
						$sql.= "ORDER BY {$dbinfo[pre]}color_palettes.percentage DESC ";
					else
						$sql.= "ORDER BY {$dbinfo[pre]}media.{$_SESSION[searchForm][searchSortBy]} {$_SESSION[searchForm][searchSortType]}";
					
					$sql.=
					"
						LIMIT {$mediaStartRecord},{$mediaPerPage}
					";
					
					//echo $sql; exit;
					
					//$mediaCount = mysqli_num_rows(mysqli_query($db,$sql)); // Get the total number of items
					//$mediaPages->setTotalResults($mediaCount); // Pass the total number of results to the $pages object
					//$sql.= " LIMIT {$mediaStartRecord},{$mediaPerPage}";
				//}
				
				$media = new mediaList($sql); // Create a new mediaList object
				if($returnRows = $media->getRows()) // Continue only if results are found
				{
					
					if($returnRows > ($mediaPerPage * $config['searchResultLimit'])) $returnRows = $mediaPerPage * $config['searchResultLimit']; // Limit the results to X pages	
					
					$mediaPages->setTotalResults($returnRows); // Pass the total number of results to the $pages object
					
					$media->getMediaDetails(); // Run the getMediaDetails function to grab all the media file details
					$mediaArray = $media->getMediaArray(); // Get the array of media
					
					$thumbMediaDetailsArray = $media->getDetailsFields('thumb'); // Get thumb details
					
					$smarty->assign('thumbMediaDetails',$thumbMediaDetailsArray);
					$smarty->assign('mediaRows',$returnRows);
					$smarty->assign('mediaArray',$mediaArray);
				}
				
				/*
				* Get paging info and pass it to smarty
				*/
				//$mediaPages->setTotalResults($returnRows); // Pass the total number of results to the $pages object
				$mediaPagingArray = $mediaPages->getPagingArray();
				$mediaPagingArray['pageNumbers'] = range(0,$mediaPagingArray['totalPages']);				
				unset($mediaPagingArray['pageNumbers'][0]); // Remove the 0 element from the beginning of the array
				$smarty->assign('mediaPaging',$mediaPagingArray);
				
				$smarty->assign('resultsArray',$_SESSION['searchForm']['resultsArray']); // xxxxxx for testing
				
			//}
		}
		
		if(!$_SESSION['searchForm']['inSearch'] or !$returnRows)
		{
			$tagCloudResult = mysqli_query($db,"SELECT *,COUNT(key_id) AS keycounter FROM {$dbinfo[pre]}keywords GROUP BY keyword ORDER BY keycounter DESC LIMIT 300"); // xxxxxxxxx Language
			$tagCloudCount = mysqli_num_rows($tagCloudResult);
			while($tag = mysqli_fetch_assoc($tagCloudResult))
			{
				$tagsArray[$tag['key_id']] = $tag;
				$tagsArray[$tag['key_id']]['count'] = $tag['keycounter'];
			}
			$smarty->assign('tags',$tagsArray);
		}
		
		/*
		* Get the active media types
		*/
		$mediaTypesResult = mysqli_query($db,
			"
			SELECT *
			FROM {$dbinfo[pre]}media_types 
			WHERE active = 1
			
			"
		); 
		if($returnRows = mysqli_num_rows($mediaTypesResult))
		{
			while($mediaTypes = mysqli_fetch_assoc($mediaTypesResult))
			{
				$mediaTypesArray[$mediaTypes['mt_id']] = $mediaTypes; // xxxx Language
				
				if(@in_array($mediaTypes['mt_id'],$_SESSION['searchForm']['mediaTypes']))
					$mediaTypesArray[$mediaTypes['mt_id']]['selected'] = true;
				
			}
			$smarty->assign('mediaTypesRows',$returnRows);
			$smarty->assign('mediaTypes',$mediaTypesArray);
		}
		
		/*
		* Create the orientations
		*/
		$orientationsArray['portrait']['id'] = 'portrait';
		$orientationsArray['portrait']['name'] = 'Portrait';
		$orientationsArray['portrait']['selected'] = false;
		
		/*
		* Create main level galleries
		*/
		if($_SESSION['galleriesData'])
		{
			foreach($_SESSION['galleriesData'] as $key => $gallery)
			{
				if($gallery['parent_gal'] == 0 and $gallery['gallery_id'] != 0)
				{
					$galleriesArray[$key] = $gallery;
					if(@in_array($key,$_SESSION['searchForm']['galleries']))
						$galleriesArray[$key]['selected'] = true;
				}
			}
		
			$smarty->assign('galleryRows',count($galleriesArray));
			$smarty->assign('galleries',$galleriesArray);
		}
		
		// Date range to & from
		foreach(range(2000,date("Y")) as $year)
		{
			$fromYear[$year] = $year;
			$toYearTmp[$year] = $year;
			$toYear = array_reverse($toYearTmp,true);
		}			
		foreach(range(1,12) as $month)
		{
			$month = zerofill($month);
			$fromMonth[$month] = $month;
			$toMonth[$month] = $month;
		}		
		foreach(range(1,31) as $day)
		{
			$day = zerofill($day);
			$fromDay[$day] = $day;
			$toDay[$day] = $day;
		}		
		if($_SESSION['searchForm']['searchDate']['dateRangeSearch'])
		{
			$searchDateObj = new kdate; // Setup a new kdate object to use specifically on media details
			$searchDateObj->setMemberSpecificDateInfo();
			$searchDateObj->distime = 0;	
			$searchDateObj->adjust_date = 0;
			$fromDateText = $searchDateObj->showdate("{$_SESSION[searchForm][searchDate][fromYear]}-{$_SESSION[searchForm][searchDate][fromMonth]}-{$_SESSION[searchForm][searchDate][fromDay]} 00:00:00");
			$toDateText = $searchDateObj->showdate("{$_SESSION[searchForm][searchDate][toYear]}-{$_SESSION[searchForm][searchDate][toMonth]}-{$_SESSION[searchForm][searchDate][toDay]} 00:00:00");
			$smarty->assign('fromDateText',$fromDateText);
			$smarty->assign('toDateText',$toDateText);
		}
		
		// Default is sortnumber, id desc
		if($_SESSION['searchForm']['hex']) $searchSortByOptions['color'] = 'Color'; // [t]
		$searchSortByOptions['date_added'] = 'Date Added';
		$searchSortByOptions['media_id'] = 'ID';
		$searchSortByOptions['title'] = 'Title';
		$searchSortByOptions['filesize'] = 'Filesize';
		$searchSortByOptions['width'] = 'Width';
		$searchSortByOptions['height'] = 'Height';
		$searchSortByOptions['views'] = 'Views';
		
		$searchSortByTypeOptions['asc'] = 'Ascending';
		$searchSortByTypeOptions['desc'] = 'Descending';
		
		$smarty->assign('searchSortByOptions',$searchSortByOptions);
		$smarty->assign('searchSortByTypeOptions',$searchSortByTypeOptions);
		$smarty->assign('fromYear',$fromYear);
		$smarty->assign('fromMonth',$fromMonth);
		$smarty->assign('fromDay',$fromDay);
		$smarty->assign('toYear',$toYear);
		$smarty->assign('toMonth',$toDay);
		$smarty->assign('toDay',$toDay);
		$smarty->assign('sql',$sql);
		$smarty->assign('searchForm',$_SESSION['searchForm']);
		$smarty->display('search.tpl');
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	include BASE_PATH.'/assets/includes/debug.php';
	if($db) mysqli_close($db); // Close any database connections
?>