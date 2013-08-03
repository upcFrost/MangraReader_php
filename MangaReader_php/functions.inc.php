<?php
require_once 'classes.inc.php';
require_once 'regex.inc.php';

/** DB Grabber functions **/

/**
 * Creates MangaBase DB
 * 
 * @return SQLite3
 */
function createDB() {
	if ($db = new SQLite3('MangaBase')) {
		// Main table
		$q = @$db->query('CREATE TABLE IF NOT EXISTS MangaReader_Main(`_id` INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, url_home TEXT NOT NULL, book_table TEXT, local_path TEXT, about TEXT, rating INTEGER, chapter_count INTEGER, ongoing INTEGER, coverX INTEGER, coverY INTEGER, coverW INTEGER, coverH INTEGER, favorite INTEGER DEFAULT 0, version INTEGER NOT NULL, UNIQUE (title, url_home) ON CONFLICT FAIL);');	
		$q = @$db->query("CREATE TABLE IF NOT EXISTS `MangaReader_Genres` (`_id` INTEGER NOT NULL primary key autoincrement, `genre` varchar(255) NOT NULL, `version` INTEGER NOT NULL);");
		$q = @$db->query("CREATE TABLE IF NOT EXISTS `MangaReader_Genres_Link` (`genre_id` INTEGER   NULL, `manga_id` INTEGER   NULL, `version` INTEGER NOT NULL);");
		$q = @$db->query("CREATE TABLE IF NOT EXISTS `MangaReader_Downloads` (`_id` INTEGER NOT NULL primary key autoincrement, `manga_id` INTEGER NULL, `chapter_id` INTEGER NULL,	`chapter_name` TEXT NOT NULL, `chapter_state` INTEGER '0', `chapter_download_percent` INTEGER '0', `last_page` INTEGER '1');");
	} else {
		die($err);
	}
	return $db;
}

function getListHTML($url) {
	$data = getPage($url);
	return $data; 
}

function getBookArray($data) {
	global $pattern_book;
	
	$bookArray = array();
	
	if (preg_match_all($pattern_book, $data, $bookMatches)) {
		$length = count($bookMatches[1]);
		for ($i = 0; $i < $length; $i++) {
			$book = new Book();
			$book->title = $bookMatches[2][$i];
			$book->url_home = $bookMatches[1][$i];
			$bookArray[] = $book;
		}
	} 
	
	return $bookArray;
}

/**
 * Get book info
 * 
 * @param Book $book <br>
 * Book object with title and url_home fields set
 * 
 * @return Book
 */
function getBookInfo(Book $book) {
	global $pattern_details;
	global $pattern_genres;
	
	$data = getPage($book->url_home);
	
	// Match details pattern: [2] - about, [3] - ongoing, [5] - rating (numeric) 
	if (preg_match($pattern_details, $data, $details)) {
		$book->about = $details[2];
		$book->ongoing = $details[3] == 'Completed' ? 0 : 1;
		$book->rating = $details[5];
		if (preg_match_all($pattern_genres, $data, $genres)) {
			foreach ($genres[1] as $genre) {
				$book->genres[] = $genre;
			}
		}
	}
	
	return $book;
}

/**
 * Calculates current book's dimensions
 * 
 * @param Imagick $im <br>
 * Imagick object with current image
 * 
 * @param Book $book <br>
 * Current book

 * @param Array['width','height'] $prevDim <br>
 * Imagick dimensions array for current big image
 *
 * @return Array['width','height']
 */
function writeCoverDims(Book $book, Imagick $im, $prevDim) {
	// Grab geometry and offset (from prev geometry + prev offset)
	$dim = $im->getImageGeometry();
	$width = $dim['width'];
	$height = $dim['height'];
	$offset = [
	"x" => $prevDim["width"],
	"y" => $prevDim["height"]
	];
	// Writing cover dims to Book object
	$book->cover["x"] = $offset["x"];
	$book->cover["y"] = $offset["y"];
	$book->cover["w"] = $width;
	$book->cover["h"] = $height;
	// Now calculate next offset
	$nextOffset = [
	'width' => $dim["width"]+$offset["x"],
	'height' => $dim["height"]+$offset["y"]
	];

	return $nextOffset;
}

/**
 * Add current book into main table
 * 
 * @param Book $book <br>
 * Current book
 * 
 * @param SQLite3 $db <br>
 * SQLite3 opened database
 * 
 */
function populateMainTable(Book $book, SQLite3 $db) {
	$mangaID = @$db->query('SELECT _id FROM MangaReader_Main WHERE title = "' . $book->title . '";')->fetchArray();
	if ($mangaID == FALSE) {
	$stmt = $db->prepare('INSERT INTO MangaReader_Main (title, url_home, book_table, about, coverX, coverY, coverW, coverH, rating, chapter_count, ongoing, version) 
			VALUES (:title, :url_home, :book_table, :about, :coverX, :coverY, :coverW, :coverH, :rating, :chapter_count, :ongoing, 1);');
		$stmt->bindValue(":title", $book->title, SQLITE3_TEXT);
		$stmt->bindValue(":url_home", $book->url_home, SQLITE3_TEXT);
		$stmt->bindValue(":book_table", "[" . $book->title . "]", SQLITE3_TEXT);
		$stmt->bindValue(":about", $book->about, SQLITE3_TEXT);
		$stmt->bindValue(":rating", $book->rating, SQLITE3_INTEGER);
		$stmt->bindValue(":chapter_count", $book->chapter_count, SQLITE3_INTEGER);
		$stmt->bindValue(":ongoing", $book->ongoing, SQLITE3_INTEGER);
		$stmt->bindValue(":coverX", $book->cover["x"]);
		$stmt->bindValue(":coverY", $book->cover["y"]);
		$stmt->bindValue(":coverW", $book->cover["w"]);
		$stmt->bindValue(":coverH", $book->cover["h"]);
	
		$stmt->execute();
	}
}

function createChapterTable(SQLite3 $db, Book $book, $chapterArray) {
	global $pattern_chapterNum;
	$tableName = "[" . $book->title . "]";
	
	$q = @$db->query("CREATE TABLE IF NOT EXISTS `" . $tableName . "`(`_id` INTEGER NOT NULL primary key autoincrement, `chapter_name` varchar(255) NOT NULL, `chapter_url` text NOT NULL, `chapter_num` float   NULL, `chapter_state` INTEGER   '0', `chapter_page_num` INTEGER '0', `chapter_download_percent` INTEGER '0', `version` INTEGER NOT NULL, UNIQUE (chapter_name, chapter_url) ON CONFLICT FAIL);");
	
	foreach ($chapterArray as $chapter) {
		
		preg_match($pattern_chapterNum, $chapter->chapterURL, $chapterNum);
		
		$q = @$db->query("INSERT INTO '" . $tableName . "'(chapter_name, chapter_url, chapter_num, version) VALUES ('" . $chapter->chapterTitle . "', '" . $chapter->chapterURL . "', " . $chapterNum[1] . ", 1);");
	}
}

/**
 * Populate Genres and Genres_Link tables with book's genres
 * 
 * @param Book $book <br>
 * Current book
 * 
 * @param SQLite3 $db <br>
 * SQLite3 opened database
 * 
 */
function populateGenresTable(Book $book, SQLite3 $db) {
	foreach ($book->genres as $genre) {
		// Check if we have all genres in DB
		$genreID = @$db->query('SELECT `_id` FROM MangaReader_Genres WHERE genre = "' . $genre . '";')->fetchArray();
		if ($genreID == FALSE) {
			$db->query('INSERT INTO MangaReader_Genres (genre, version) VALUES ("' . $genre . '", 1);');
			$genreID = @$db->query('SELECT _id FROM MangaReader_Genres WHERE genre = "' . $genre . '";')->fetchArray();
		}
		// Check if we have links in DB
		$mangaIDstmt = $db->prepare('SELECT `_id` FROM MangaReader_Main WHERE title = :title;');
		$mangaIDstmt->bindValue(":title", $book->title, SQLITE3_TEXT);
		$mangaID = $mangaIDstmt->execute()->fetchArray();
		
		$linkIDstmt = $db->prepare('SELECT * FROM MangaReader_Genres_Link WHERE genre_id = :genreID AND manga_id = :mangaID;');
		$linkIDstmt->bindValue(":genreID", $genreID[0], SQLITE3_INTEGER);
		$linkIDstmt->bindValue(":mangaID", $mangaID[0], SQLITE3_INTEGER);
		$linkID = $linkIDstmt->execute()->fetchArray();
		
		if ($linkID == FALSE) {
			$stmt = @$db->prepare('INSERT INTO MangaReader_Genres_Link (genre_id, manga_id, version) VALUES (:genreID,:mangaID,1);');
			$stmt->bindValue(":genreID", $genreID[0], SQLITE3_INTEGER);
			$stmt->bindValue(":mangaID", $mangaID[0], SQLITE3_INTEGER);
			$stmt->execute();
		}
	}
}

/** Global functions **/
 
function downloadChapter($chapter, $xml, $xml_root, $tempdir, $savedir) {
	// Analyze page
	$pageInfo = new imagePageInfo();
	$pageInfo = analyzePage($chapter->chapterURL);
	// Create image and xml objects
	$combined = new Imagick();
	// Create appended image
	$bigImage = createBigImage($pageInfo->imageTempURL, 1,
			$pageInfo->pageCount, $combined, $xml, $xml_root,
			$chapter->chapterTitle, $tempdir);
	// Write appended image to file
	$bigImage->writeimage($savedir . $chapter->chapterTitle . ".jpg");
}


/** Network functions **/

/**
 * Download web object using cURL
 * 
 * @param String $url <br>
 * Page URL
 * 
 * @return mixed
 */
function getPage($url) {
	$ch = curl_init($url);
	$proxy = '10.100.120.37:3128';
	// 	$proxyauth = 'user:pass';
	
	curl_setopt($ch, CURLOPT_PROXY, $proxy);
	// 	curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	// 	curl_setopt($ch, CURLOPT_FILE, $handler);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	$data = curl_exec($ch);
	curl_close($ch);
	
	return $data;
}

/** Page analysis functions **/

function analyzeTitlePage($titleURL) {
	// Use patterns from regex.inc
	global $pattern_chapter;
	global $pattern_next;
	// Using array as a return var
	$chaptersArray = array();
	$content = getPage($titleURL);
	preg_match_all($pattern_next, $content, $hasNext);
	$pageNum = 1;
	$next = TRUE;
	// Repeat when at least one chapter is found on page
	while ($next || $pageNum == 1) {
		preg_match_all($pattern_chapter, $content, $matches);
		if (count($hasNext[0]) == 0)
			$next = FALSE;
		
		$length = count($matches[1]);
		for ($i = 0; $i < $length; $i++) {
			$tempChapter = new chapterInfo();
			$tempChapter->chapterURL = $matches[1][$i];
			$tempChapter->chapterTitle = $matches[2][$i];
			$chaptersArray[] = $tempChapter;
		}
		
		$pageNum = $pageNum+1;
		$content = getPage($titleURL . "?page=" . $pageNum);
		preg_match_all($pattern_next, $content, $hasNext);
	}
	
	return $chaptersArray;
}

function analyzePage($pageURL) {
	// Use patterns from regex.inc
	global $pattern_page;
	global $pattern_page_count;
	// Getting page HTML source
	$content = getPage($pageURL);
	if (preg_match($pattern_page, $content, $imageTempURL)
			&& preg_match($pattern_page_count, $content, $pageCount)) {
		// Create a imagePageInfo object to contain matched values
		$pageInfo = new imagePageInfo();
		$pageInfo->imageTempURL = $imageTempURL[1];
		$pageInfo->pageCount = $pageCount[1];
		return $pageInfo;
	}
	return FALSE;
}

/** XML functions **/

function readXML($file) {
	$fp = fopen($file, "rb") or die("cannot open file");
	$str = fread($fp, filesize($file));
	
	$xml = createXML();
	$xml->loadXML($str) or die("Error loading XML");
	
	return $xml;
}

function readXMLRoot($xml) {
	return $xml->documentElement;
}

/**
 * Write each image part dimensions into XML
 * 
 * @param DOMDocument $xml <br>
 * Main XML object
 * 
 * @param Imagick $im <br>
 * Imagick object with current image
 * 
 * @param Integer $page <br>
 * Page number
 * 
 * @param Array['width','height'] $prevDim <br>
 * Imagick dimensions array for current big image
 * 
 * @param DOMElement $xml_chapter <br>
 * XML chapter node
 * 
 * @return Array['width','height']
 */
function writeDims($xml, $im, $page, $prevDim, $xml_chapter) {
	// Grab geometry and offset (from prev geometry + prev offset)
	$dim = $im->getImageGeometry();
	$width = $dim['width'];
	$height = $dim['height'];
	$offset = [
		"x" => $prevDim["width"],
		"y" => $prevDim["height"]
	];
	// Create xml nodes
	$xml_page = $xml->createElement("page");
	$xml_page_id = $xml->createElement("page_id", $page);
	$xml_offsetX = $xml->createElement("offset_x", $offset["x"]);
	$xml_offsetY = $xml->createElement("offset_y", $offset["y"]);
	$xml_width = $xml->createElement("width", $width);
	$xml_height = $xml->createElement("height", $height);
	// Append all page dims and page_id to page node
	$xml_page->appendChild($xml_page_id);
	$xml_page->appendChild($xml_offsetX);
	$xml_page->appendChild($xml_offsetY);
	$xml_page->appendChild($xml_width);
	$xml_page->appendChild($xml_height);
	// Finally append all children to the main file
	$xml_chapter->appendChild($xml_page);
	// Now calculate next offset
	$nextOffset = [
		'width' => $dim["width"]+$offset["x"],
		'height' => $dim["height"]+$offset["y"]
	];
	
	return $nextOffset;
}

/** 
 * Creates chapter node in xml object
 * 
 * @param DOMDocument $xml <br>
 * Main xml object
 * 
 * @param DOMElement $xml_root <br>
 * Xml root node
 * 
 * @param String $chapterName <br>
 * Chapter title
 * 
 * @return DOMElement
 */
function createChapter($xml, $xml_root, $chapterName) {
	// Add chapter and chapterId nodes
	$xml_chapter = $xml->createElement("chapter");
	$xml_chapter_id = $xml->createElement("chapter_id", $chapterName);
	$xml_chapter->appendChild($xml_chapter_id);
	$xml_root->appendChild($xml_chapter);
	
	return $xml_chapter;
}

/** Creates basic xml object
 * 
 *  @return DOMDocument
 */
function createXML() {
	$xml = new DOMDocument('1.0');
	$xml->formatOutput = TRUE;
	$xml->preserveWhiteSpace = FALSE;
	
	return $xml;
}

/**
 * Creates root node in XML object
 * 
 * @param DOMDocument $xml
 * 
 * @return DOMElement
 */
function createXMLRoot($xml) {
	// Create root xml node
	$xml_root = $xml->createElement("root");
	// Append it
	$xml->appendChild($xml_root);
	
	return $xml_root;
}

/** ZIP functions **/
function loadZip($path, $file) {
	$zip = new ZipArchive;
	$res = $zip->open($file);
	if ($res != TRUE) {
		return FALSE;
	}
	
	echo "Extracting " . $file . " to " . $path . "\n";
	
	$zip->extractTo($path);
	$zip->close();
	// Read file list
	$handle = opendir($path);
	// File array to sort them
	$item = array();
	// Reading dir
	while (false !== ($entry = readdir($handle))) {
		if ($entry != '.' && $entry != '..' && is_image($path . $entry)) {
			if (!is_dir($entry)) {
				$item[] = $entry;
				echo $entry . "<br>";
			}
		}
	}
	closedir($handle);
	
	return $item;
}

/** Image functions **/

function grabImage($tempFile, $url, $handler) {
	$data = getPage($url);
	file_put_contents($tempFile, $data);
}

function is_image($path)
{
	$a = getimagesize($path);
	$image_type = $a[2];
	 
	if(in_array($image_type , array(IMAGETYPE_GIF , IMAGETYPE_JPEG ,IMAGETYPE_PNG , IMAGETYPE_BMP)))
	{
		return true;
	}
	return false;
}

/**
 * Downloads images with given URL template, adds them into XML document
 * and creates a big combined image
 * 
 * @param String $urlTemp <br>
 * Image URL template 
 * 
 * @param Integer $minIdx <br>
 * First index
 * 
 * @param Integer $maxIdx <br>
 * Last index
 * 
 * @param Imagick $combined <br>
 * Combined image object to return
 * 
 * @param DOMDocument $xml <br>
 * Main XML document
 * 
 * @param DOMElement $xml_root <br>
 * Root XML node
 * 
 * @param String $chapterName <br>
 * Chapter name to use in XML
 * 
 * @return Imagick
 */
function createBigImage($urlTemp, $minIdx, $maxIdx,
		$combined, $xml, $xml_root, $chapterName,
		$tempdir) {
	$all = new Imagick();
	$xml_chapter = createChapter($xml, $xml_root, $chapterName);
	$dim = ['width' => 0, 'height' => 0];
	for ($i = $minIdx; $i < $maxIdx+1; $i++) {
		// Make temp file
		$tempFile = $tempdir . $i . ".jpg";
		$handle = fopen($tempFile, 'w+');
		// Construct URL
		$url = $urlTemp . '/' . $i . ".jpg";
		// Download image
		grabImage($tempFile, $url, $handle);
		// Create Imagick object from image
		$im = new Imagick("jpg:$tempFile");
		// Write image xml data
		$dim = writeDims($xml, $im, $i, $dim, $xml_chapter);
		// Append object into array
		$all->addimage($im);
		fclose($handle);
	}
	$all->resetiterator();
	$combined = $all->appendimages(FALSE);
	
	return $combined;
}
?>