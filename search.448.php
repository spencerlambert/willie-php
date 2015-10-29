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
	
	$_SESSION['currentMode'] = 'search'; // Change the currentMode to search
	
	$_SESSION['backButtonSession']['linkto'] = pageLink(); // Update the back button link session
	
	// Update crumbs links
	unset($_SESSION['crumbsSession']);
	$crumbs[0]['linkto'] = $_SESSION['backButtonSession']['linkto']; // Check for SEO
	$crumbs[0]['name'] = $lang['searchResults']; //				
	$_SESSION['crumbsSession'] = $crumbs; // Assign these to a session to be used elsewhere
	
	// Clean search keywords function
	function searchKeywordClean($keyword)
	{
		global $config;
		
		$keyword = strip_tags(trim($keyword));
		
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
		
		if((strlen($keyword) >= $config['minSearchWordLength'] or is_numeric($keyword)) and !in_array($keyword,$nonSearchableKeywords) and !$keywordExists)
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
		
		unset($_SESSION['prevNextArraySess']); // Unset and prev next array
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
		
		unset($_SESSION['prevNextArraySess']); // Unset and prev next array
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
		$_SESSION['searchForm']['searchSortBy'] = $searchSortBy;
		
	if($_REQUEST['searchSortType'])
		$_SESSION['searchForm']['searchSortType'] = $searchSortType;
	
	if(!$_SESSION['searchForm']['searchSortBy'])
		$_SESSION['searchForm']['searchSortBy'] = 'relevancy';
		
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
			$searchPhrase = strtolower($searchPhrase);
			
			//echo $searchPhrase; exit; 
			
			if($_REQUEST['exactMatch'] or ($config['exactNumericSearch'] and is_numeric($searchPhrase))) // or is_numeric($searchPhrase)
				$searchPhrase = '\"'.$searchPhrase.'\"';
			
			if(strpos($searchPhrase,'"') !== false)
				$keywords = array_filter(array($searchPhrase),"searchKeywordClean");
			else
				$keywords = array_filter(explode(" ",$searchPhrase),"searchKeywordClean");
			
			//print_r($keywords); exit;
			
			if(count($keywords) > 0)
			{
				$_SESSION['searchForm']['keywordBatch']++; // Set a new batch on the keywords
				$keywordBatch = $_SESSION['searchForm']['keywordBatch']; // Set local
				
				$hiddenKeywords = array_filter($keywords,'pluralizeKeywords'); // Make a hidden array of the keywords pluralized	
				foreach($hiddenKeywords as $key => $keyword)
					$_SESSION['searchForm']['keywords'][$keywordBatch]['hiddenWords'][] = $keyword;
				
				foreach($keywords as $key => $keyword)
				{
					$_SESSION['searchForm']['keywords'][$keywordBatch]['words'][] = $keyword;
					$_SESSION['searchForm']['keywords'][$keywordBatch]['displayWords'][] = strip_tags(stripslashes($keyword));
				}
				
				if($keywordBatch > 1)
					$_SESSION['searchForm']['keywords'][$keywordBatch]['connector'] = $searchConnector; // Search connector
			
				$_SESSION['searchForm']['inSearch'] = true;
			
			}
					
			//echo $keywordBatch.'<br>';
			//print_r($_SESSION['searchForm']['keywords']);
		}
		
		//$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Fields to search
	if($_REQUEST['fields'])
	{
		unset($_SESSION['searchForm']['fields']); // Clear licenses first
		
		$_SESSION['searchForm']['allFields'] = false;
		
		foreach($fields as $key => $value)
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
		
		if(preg_match("/[^A-Za-z0-9_-]/",$hex))
		{
			header("location: error.php?eType=invalidQuery");
			exit;
		}
				
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		$_SESSION['searchForm']['hex'] = $hex;
		
		if($_REQUEST['red']) // Only hex is being passed - find rgb
		{
			
			if(preg_match("/[^A-Za-z0-9_-]/",$red) or preg_match("/[^A-Za-z0-9_-]/",$green) or preg_match("/[^A-Za-z0-9_-]/",$blue))
			{
				header("location: error.php?eType=invalidQuery");
				exit;
			}
			
			$_SESSION['searchForm']['red'] = $red;
			$_SESSION['searchForm']['green'] = $green;
			$_SESSION['searchForm']['blue'] = $blue;
		}
		else
		{
			$rgbColor = html2rgb($hex); // Convert hex to rgb
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
			$_SESSION['searchForm']['searchDate'] = $searchDate; // Set to a session
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
		foreach($licenses as $key => $value)
			$_SESSION['searchForm']['licenses'][$key] = $value;
		//print_r($_SESSION['searchForm']['licenses']);
		$_SESSION['searchForm']['inSearch'] = true;
	}
	
	// Media type passed
	if($_REQUEST['mediaTypes'])
	{
		//unset($_SESSION['searchForm']['resultsArray']); // Clear any previous results array
		unset($_SESSION['searchForm']['mediaTypes']); // Clear mediaTypes first
		foreach($mediaTypes as $key => $mediaTypeID)
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
			foreach($galleries as $key => $galleryID)
			{
				if(!is_numeric($galleryID))
				{
					header("location: error.php?eType=invalidQuery");
					exit;	
				}
				
				$_SESSION['searchForm']['galleries'][$key] = $galleryID;
			}
		}
		else
		{
			if(!is_numeric($_REQUEST['galleries']))
			{
				header("location: error.php?eType=invalidQuery");
				exit;	
			}			
			
			$_SESSION['searchForm']['galleries'][$_REQUEST['galleries']] = $_REQUEST['galleries']; // Single gallery passed
		}
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
				if(($_SESSION['searchForm']['fields']['keywords'] or $_SESSION['searchForm']['allFields']) and $_SESSION['searchForm']['keywords']) // $_SESSION['searchForm']['fields'] or $_SESSION['searchForm']['fields']['keywords']
				{	
					$_SESSION['searchForm']['seachForIDs']['keywords'] = true;
					
					$runningKeywords = array();
					
					if(!$config['searchRelevance']) $config['searchRelevance'] = 70; // Make sure this is set
					
					$wordCounter = 0;
					
					//print_r($_SESSION['searchForm']['keywords']); exit;
					
					foreach($_SESSION['searchForm']['keywords'] as $key => $batch)
					{
						$flatKeywords = implode(',',array_map('wrapInQuotes',$batch['words']));
						@$flatKeywordIDs = implode(',',$runningKeywords);
						
						if($key != 1)
							unset($runningKeywords);
						
						foreach($batch['words'] as $keyword)
						{							
							if(strpos($keyword,'"') !== false)
								$searchSubSQL = "= '".str_replace('\"','',$keyword)."'";
							else
								$searchSubSQL = "LIKE '%{$keyword}%'";
							
							$searchSQL.= "keyword {$searchSubSQL}"; 
							
							$keySQL = "
								SELECT * FROM {$dbinfo[pre]}keywords 
								WHERE ({$searchSQL})
							";
							
							if($key != 1)
								$keySQL.= " AND media_id IN ({$flatKeywordIDs})";
								
							//$keySQL.= " GROUP BY media_id"; // If the single keyword is found in multiple entries for the same photo only report it once to keep the count from being off 
							// Removed in 4.4.4 to prevent mutiple occurances of the keyword for the same media from only returning 1 result - for example men may only return women instead of both keywords men and women
							
							$keywordsResult = mysqli_query($db,$keySQL);
							$keywordsFound = mysqli_num_rows($keywordsResult);
							//echo '-'.$keywordsFound.'<br>';
							while($keywordDB = mysqli_fetch_assoc($keywordsResult))
							{
								$keywordParts = explode(" ",$keywordDB['keyword']); // Break multiple word keywords in to single words
								
								foreach($keywordParts as $parts)
								{
									@$thisPartMatch = round(strlen($keyword)/strlen($parts)*100);									
									if($thisPartMatch > $thisKeywordMatch) $thisKeywordMatch = $thisPartMatch;
									
									//echo $thisKeywordMatch." - ".$keywordDB['media_id']." - ".$parts." <br> ";

								}
								
								//echo $keywordDB['media_id'].'<br>';
								
								//echo "{$thisKeywordMatch}<br>\n";
								
								if($thisKeywordMatch >= $config['searchRelevance'])
								{
									$runningKeywords[$keywordDB['media_id']] = $keywordDB['media_id'];
									$keywordWeight[$keywordDB['media_id']]++;
								
									if($keywordMatch[$keywordDB['media_id']]) // Already found - add last match value (New in 4.1.7)
										$thisKeywordMatch+=$keywordMatch[$keywordDB['media_id']];
									
									if($keywordMatch[$keywordDB['media_id']] < $thisKeywordMatch) // Already found - see if this match is better								
										$keywordMatch[$keywordDB['media_id']] = $thisKeywordMatch;
								}
								$thisKeywordMatch = 0;
							}
							//exit;
							$wordCounter++;
							$searchSQL='';
							$searchSubSQL='';
						}
						//print_r($runningKeywords);
						//print_r($keywordWeight);
						
						$searchSQL='';					
						
					}
					
					//echo $wordCounter;
					
					if($runningKeywords)
					{
						foreach($runningKeywords as $key => $value)
						{							
							if($keywordWeight[$key] < $wordCounter) // or $keywordMatch[$key] < $config['searchRelevance']
							{
								unset($runningKeywords[$key]);
								unset($keywordMatch[$key]);
								unset($keywordWeight[$key]);
							}
						}
					}
					
					//print_r($runningKeywords);
					
					//echo "<br><br>order<br>";					
					//arsort($keywordMatch);
					
					/*
					array_multisort($keywordMatch,$runningKeywords);
					
					foreach($runningKeywords as $key => $value)
					{							
						echo $value." - {$keywordMatch[$key]}<br>";
					}
					*/
					
					//@arsort($keywordMatch); // Sort the matching in reverse order					
					@asort($keywordMatch); // Sort the matching in low to high - I will reverse it in the query
					
					//print_r($keywordMatch);
					
					// print_r($runningKeywords); // Testing
					
					if(count($runningKeywords) > 0) $runningKeywords = array_unique($runningKeywords);
					
					$_SESSION['searchForm']['keywordsResultsArray'] = $runningKeywords;
					
					$totalRowsFound = count($runningKeywords);					
				}
				
				//echo $totalRowsFound; exit;
				
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
					
					$licListFlat = implode(',',$_SESSION['searchForm']['licenses']);
					
					$mediaFromDSPLicenseCount = 0;
					
					//$licensesSQL = "SELECT SQL_CALC_FOUND_ROWS license,media_id FROM {$dbinfo[pre]}media WHERE active = 1";
					//$connector = 'AND (';
					
					// Get digital profiles that use this license
					if($totalRowsFound > 0) // Only do this if previous rows have been found
					{
						$dspLicensesResult = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS license,ds_id FROM {$dbinfo[pre]}digital_sizes WHERE active = 1 AND deleted = 0 AND license IN ({$licListFlat})");
						if($dspLicensesCount = getRows())
						{
							while($dspLicense = mysqli_fetch_assoc($dspLicensesResult))
								$validDigitalSizes[] = $dspLicense['ds_id'];
								
							@$validDigitalSizesFlat = implode(',',$validDigitalSizes);
							
							$mediaFromDSPLicenseResult = mysqli_query($db,"SELECT SQL_CALC_FOUND_ROWS media_id FROM {$dbinfo[pre]}media_digital_sizes WHERE license IN ({$licListFlat}) OR ds_id IN ($validDigitalSizesFlat)");
							$mediaFromDSPLicenseCount = getRows();
							if($mediaFromDSPLicenseCount > 0)
							{
								$totalRowsFound = $mediaFromDSPLicenseCount;
								
								while($mediaFromDSPLicense = mysqli_fetch_assoc($mediaFromDSPLicenseResult))
									$mediaFromDSPLicenseArray[$mediaFromDSPLicense['media_id']] = '0';
									
								$resultsArray = $mediaFromDSPLicenseArray;
							}
							else
							{
								unset($resultsArray);
							}
						}
					}
					
					//print_r($_SESSION['searchForm']['licenses']);					
					$licensesSQL = "SELECT SQL_CALC_FOUND_ROWS license,media_id FROM {$dbinfo[pre]}media WHERE active = 1 AND license IN ({$licListFlat})";					
					//echo $licensesSQL; exit;
					
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
						$totalRowsFound = $licensesCount + $mediaFromDSPLicenseCount; // Set total rows found to rows from this area search
						while($license = mysqli_fetch_assoc($licensesResult))
							$licenseResultsArray[$license['media_id']] = '0'; // Assign the media ids to the results array
						
						if($mediaFromDSPLicenseArray) // Merge with the previous array
							$resultsArray = array_merge($licenseResultsArray,$mediaFromDSPLicenseArray);
						else
							$resultsArray = $licenseResultsArray; // Update the results array	
						//echo "<strong>Licenses:</strong> Results (pre search): {$resultsFlat} | Count: {$licensesCount}<br><br>";
					}
					else
					{
						if(!$mediaFromDSPLicenseCount)
						{
							unset($resultsArray);
							$totalRowsFound = 0; // Nothing found here - set total rows found to 0
						}
					}
				}
				
				//print_r($resultsArray); exit;
				
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
				
				//echo $totalRowsFound; exit;
				
				// Galleries
				if(count($_SESSION['searchForm']['galleries']) > 0 and $totalRowsFound > 0)
				{
					$_SESSION['searchForm']['seachForIDs']['galleries'] = true;
					
					/*
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
					*/
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
					
				if($_SESSION['searchForm']['seachForIDs']['galleries'])
				{
					@$similarGalleryIDs = array_intersect($_SESSION['searchForm']['galleries'],$_SESSION['member']['memberPermGalleries']);	 // Added in 4.4.4				
					
					if(count($similarGalleryIDs) > 0) 
						$inGalleries = implode(',',$similarGalleryIDs);
					else
						$inGalleries = 0;
				}
				else
					$inGalleries = $memberPermGalleriesForDB;
				
				//echo $inGalleries; exit;
					
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
					unset($keywords);
					$keywords = array();
					
					if($_SESSION['searchForm']['keywords'])
					{
						foreach(@$_SESSION['searchForm']['keywords'] as $key => $batch)
						{
							foreach(@$batch['words'] as $words)
								array_push($keywords,$words);
						}
						
						
						//echo "- "; print_r($keywords); exit;

						
						if($_SESSION['searchForm']['fields']['mediaID'] or $_SESSION['searchForm']['allFields'])
						{
							$wordCounter=1;
							$mediaIDSQL = ' (';
							foreach($keywords as $keyword)
							{
								if(strpos($keyword,'"') !== false)
									$mediaIDSubSQL = "= '".str_replace('\"','',$keyword)."'";
								else
									$mediaIDSubSQL = "= '{$keyword}'";
								
								$mediaIDSQL.= "{$dbinfo[pre]}media.media_id {$mediaIDSubSQL}";
								
								if($wordCounter < count($keywords)) $mediaIDSQL.= ' AND ';							
								
								$wordCounter++;
							}
							$mediaIDSQL.= ') ';
							
							//echo $mediaIDSQL; exit; // Testing
						}
						
						if($_SESSION['searchForm']['fields']['title'] or $_SESSION['searchForm']['allFields'])
						{							
							$wordCounter=1;
							$titleSQL = ' (';
							foreach($keywords as $keyword)
							{
								//echo "{$keyword}<br>";
								
								if(strpos($keyword,'"') !== false)
									$titleSubSQL = "= '".str_replace('\"','',$keyword)."'";
								else
									$titleSubSQL = "LIKE '%{$keyword}%'";
								
								$titleSQL.= "{$dbinfo[pre]}media.title {$titleSubSQL}";
								
								if($wordCounter < count($keywords)) $titleSQL.= ' AND ';							
								
								$wordCounter++;
							}
							$titleSQL.= ') ';
						}
					
						//echo $titleSQL;
						
						if($_SESSION['searchForm']['fields']['description'] or $_SESSION['searchForm']['allFields'])
						{
							$wordCounter=1;
							$descriptionSQL = ' (';
							foreach($keywords as $key => $keyword)
							{
								if(strpos($keyword,'"') !== false)
									$descriptionSubSQL = "= '".str_replace('\"','',$keyword)."'";
								else
									$descriptionSubSQL = "LIKE '%{$keyword}%'";
								
								$descriptionSQL.= "{$dbinfo[pre]}media.description {$descriptionSubSQL}";
								
								if($wordCounter < count($keywords)) $descriptionSQL.= ' AND ';							
								
								$wordCounter++;
							}
							$descriptionSQL.= ') ';
						}
					
						if($_SESSION['searchForm']['fields']['filename'] or $_SESSION['searchForm']['allFields'])
						{
							$wordCounter=1;
							$filenameSQL = ' (';
							foreach($keywords as $keyword)
							{
								if(strpos($keyword,'"') !== false)
									$filenameSubSQL = "= '".str_replace('\"','',$keyword)."'";
								else
									$filenameSubSQL = "LIKE '%{$keyword}%'";
								
								$filenameSQL.= "{$dbinfo[pre]}media.filename {$filenameSubSQL}";
								
								if($wordCounter < count($keywords)) $filenameSQL.= ' AND ';							
								
								$wordCounter++;
							}
							$filenameSQL.= ') ';
						}
					}
				
					if($_SESSION['searchForm']['seachForIDs']['licenses'] or $_SESSION['searchForm']['seachForIDs']['mediaTypes']) $searchForIDs = true; // $_SESSION['searchForm']['seachForIDs']['colors'] OR $_SESSION['searchForm']['seachForIDs']['keywords'] OR $_SESSION['searchForm']['seachForIDs']['orientations'] or $_SESSION['searchForm']['seachForIDs']['galleries']
					/*
					$sql = 
					"
						SELECT SQL_CALC_FOUND_ROWS *
						FROM {$dbinfo[pre]}media
						LEFT JOIN {$dbinfo[pre]}media_galleries
						ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}media_galleries.gmedia_id 
					";
					*/
					$sql = 
					"
						SELECT SQL_CALC_FOUND_ROWS *
						FROM {$dbinfo[pre]}media 
					";
					
					
					if($_SESSION['searchForm']['hex'])
						$sql.=
						"
							LEFT JOIN {$dbinfo[pre]}color_palettes 
							ON {$dbinfo[pre]}media.media_id = {$dbinfo[pre]}color_palettes.media_id 
						";
					
					//$sql.= "WHERE {$dbinfo[pre]}media_galleries.gallery_id IN ({$inGalleries}) ";					
					$sql.= "WHERE {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(gmedia_id) FROM {$dbinfo[pre]}media_galleries WHERE gallery_id IN ({$inGalleries})) "; // New 4.3.2
					
					
					if($_SESSION['searchForm']['hex'])
						$sql.=
						"
							AND ({$dbinfo[pre]}color_palettes.red BETWEEN {$redMin} AND {$redMax}) 
							AND ({$dbinfo[pre]}color_palettes.green BETWEEN {$greenMin} AND {$greenMax}) 
							AND ({$dbinfo[pre]}color_palettes.blue BETWEEN {$blueMin} AND {$blueMax}) 
							AND {$dbinfo[pre]}color_palettes.percentage > {$config[colorSearchMinimum]} 
						";
					
					/*
					if($_SESSION['searchForm']['hex'])
						$sql.=
						"
							AND {$dbinfo[pre]}media.media_id IN (SELECT DISTINCT(media_id) FROM {$dbinfo[pre]}color_palettes WHERE ({$dbinfo[pre]}color_palettes.red BETWEEN {$redMin} AND {$redMax}) 
							AND ({$dbinfo[pre]}color_palettes.green BETWEEN {$greenMin} AND {$greenMax}) 
							AND ({$dbinfo[pre]}color_palettes.blue BETWEEN {$blueMin} AND {$blueMax}) 
							AND {$dbinfo[pre]}color_palettes.percentage > {$config[colorSearchMinimum]}) 
						"; // New 4.3.2
					*/
					
					if(
						($_SESSION['searchForm']['fields']['title'] or 
						$_SESSION['searchForm']['fields']['description'] or 
						$_SESSION['searchForm']['fields']['mediaID'] or 
						$_SESSION['searchForm']['fields']['filename'] or 
						$_SESSION['searchForm']['allFields']) and
						$_SESSION['searchForm']['keywords']
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
						
						if($_SESSION['searchForm']['fields']['mediaID'] or $_SESSION['searchForm']['allFields'])
						{
							if($_SESSION['searchForm']['fields']['filename'] or $_SESSION['searchForm']['allFields']) $sql.=" OR ";
							$sql.= $mediaIDSQL;
						}
						
						$sql.=")";
						
						//echo $sql; exit; // Testing
					}
					else
					{
						if(($_SESSION['searchForm']['fields']['keywords'] or $_SESSION['searchForm']['allFields']) and $_SESSION['searchForm']['keywords']) $sql.= "AND {$dbinfo[pre]}media.media_id IN ({$keywordIDsFlat}) ";
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
						$sql.= "AND {$dbinfo[pre]}media.{$config[dateSearchField]} > '{$sqlFromDate}' AND {$dbinfo[pre]}media.{$config[dateSearchField]} < '{$sqlToDate}' ";
					}
					
					$sql.= 
					"	
						AND {$dbinfo[pre]}media.active = 1 
						AND {$dbinfo[pre]}media.approval_status = 1 
						GROUP BY {$dbinfo[pre]}media.media_id 
					";
					
					if($_SESSION['searchForm']['hex'])
						$sql.= "ORDER BY {$dbinfo[pre]}color_palettes.percentage DESC ";
					else
					{
						if($_SESSION['searchForm']['searchSortBy'] == 'relevancy')
						{
							if(count($keywordMatch) > 0)
								@$keywordRelevancyIDs = implode(',',array_keys($keywordMatch));
							else
								$keywordRelevancyIDs = '0';
								
							$sql.= "ORDER BY FIELD({$dbinfo[pre]}media.media_id, $keywordRelevancyIDs) DESC, {$dbinfo[pre]}media.media_id DESC";
						}
						else
							$sql.= "ORDER BY {$dbinfo[pre]}media.{$_SESSION[searchForm][searchSortBy]} {$_SESSION[searchForm][searchSortType]}";
					}
					
					//echo $sql; exit;
					
					//$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM ps4_media WHERE ps4_media.media_id IN (SELECT DISTINCT(gmedia_id) FROM ps4_media_galleries WHERE gallery_id IN ('2','8','9','10','1','3','4','5','7'))  OR (ps4_media.title LIKE '%fasan%') OR (ps4_media.description LIKE '%fasan%') OR (ps4_media.filename LIKE '%fasan%') OR (ps4_media.media_id = 'fasan') )	 AND ps4_media.active = 1 AND ps4_media.approval_status = 1 GROUP BY ps4_media.media_id ORDER BY FIELD(ps4_media.media_id, 3932,4460,4504,4917,3931,3361,1241,2908,3360,993) DESC, ps4_media.media_id DESC";
					
					/*
					* Previous and next button array
					*/
					if(!$_SESSION['prevNextArraySess']) // Only do this if it doesn't already exist
					{
						$prevNextResult = mysqli_query($db,str_replace('*',"{$dbinfo[pre]}media.media_id",$sql));
						while($prevNext = mysqli_fetch_assoc($prevNextResult))
							$prevNextArray[] = $prevNext['media_id'];					
						$_SESSION['prevNextArraySess'] = $prevNextArray;
					}
					
					$sql.=
					"
						LIMIT {$mediaStartRecord},{$mediaPerPage}
					";
					
					//echo $sql; exit;
					
					//$mediaCount = mysqli_num_rows(mysqli_query($db,$sql)); // Get the total number of items
					//$mediaPages->setTotalResults($mediaCount); // Pass the total number of results to the $pages object
					//$sql.= " LIMIT {$mediaStartRecord},{$mediaPerPage}";
				//}
				
				//mysqli_free_result();
				
				//echo $sql; // testing
				
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
				
				//$smarty->assign('resultsArray',$_SESSION['searchForm']['resultsArray']); // for testing
				
			//}
		}
		
		if(!$_SESSION['searchForm']['inSearch'] or !$returnRows)
		{
			if($config['settings']['tagCloudOn'] == 1)
				$smarty->assign('tags',tagCloud(false,$config['settings']['tagCloudSort']));
			else
				$smarty->assign('tags'," ");
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
		if(@$returnRows = mysqli_num_rows($mediaTypesResult))
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
		if($_SESSION['searchForm']['hex']) $searchSortByOptions['color'] = $lang['galSortColor'];
		$searchSortByOptions['relevancy'] = $lang['keywordRelevancy'];
		$searchSortByOptions['date_added'] = $lang['galSortDate'];
		$searchSortByOptions['media_id'] = $lang['galSortID'];
		$searchSortByOptions['title'] = $lang['galSortTitle'];
		$searchSortByOptions['filename'] = $lang['galSortFilename'];
		$searchSortByOptions['filesize'] = $lang['galSortFilesize'];
		$searchSortByOptions['width'] = $lang['galSortWidth'];
		$searchSortByOptions['height'] = $lang['galSortHeight'];
		$searchSortByOptions['views'] = $lang['galSortViews'];
		
		$searchSortByTypeOptions['asc'] = $lang['galSortAsc'];
		$searchSortByTypeOptions['desc'] = $lang['galSortDesc'];
		
		$licenseResult = mysqli_query($db,"SELECT * FROM {$dbinfo[pre]}licenses");
		while($licenseDB = mysqli_fetch_assoc($licenseResult))
		{
			$licenseDB['licenseLang'] = ($licenseDB['lic_name_'.$selectedLanguage]) ? $licenseDB['lic_name_'.$selectedLanguage] : $licenseDB['lic_name'];
			if(@in_array($licenseDB['license_id'],$_SESSION['searchForm']['licenses']))
				$licenseDB['selected'] = 1;
			else
				$licenseDB['selected'] = 0;
			$licensesList[$licenseDB['license_id']] = $licenseDB;
		}
		
		$smarty->assign('licensesList',$licensesList);
		$smarty->assign('searchSortByOptions',$searchSortByOptions);
		$smarty->assign('searchSortByTypeOptions',$searchSortByTypeOptions);
		$smarty->assign('fromYear',$fromYear);
		$smarty->assign('fromMonth',$fromMonth);
		$smarty->assign('fromDay',$fromDay);
		$smarty->assign('toYear',$toYear);
		$smarty->assign('toMonth',$toMonth);
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