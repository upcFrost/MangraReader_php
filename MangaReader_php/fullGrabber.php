<?php
require_once 'functions.inc.php';
require_once 'classes.inc.php';
require_once 'regex.inc.php';

ini_set('max_execution_time', 90000);

// Windows
$coverdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\cover\\';
$grabbeddirTemp = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\grabbed\\';
$tempdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\temp\\';
$coverdirTemp = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\cover\\temp\\';
// Linux
// $coverdir = '/var/www/cover/';
// $grabbeddirTemp = '/var/www/grabbed/';
// $tempdir = '/var/www/temp/';


$mainDB = createDB();
$data = getListHTML('http://www.goodmanga.net/manga-list');
// Check if cover file exists
if (file_exists($coverdir . "cover.jpg")) {
	$bigCover = new Imagick($coverdir . "cover.jpg");
} else {
	$bigCover = new Imagick();
}
if (!file_exists($coverdirTemp)) mkdir($coverdirTemp, 0777, TRUE);

$bookArray = getBookArray($data);

$idx = 0;
$dim = ['width' => 0, 'height' => 0];
foreach ($bookArray as $book) {
	echo "Book " . $book->title . "<br>";
	// Get book info
	$book = getBookInfo($book);
	// Create dirs
	echo "Creating dirs... ";
	$grabbeddir = $grabbeddirTemp . checkStringContent($book->title) . '\\';
	if (!file_exists($grabbeddir)) 
		mkdir($grabbeddir, 0777, TRUE);
	echo "done<br>";
	// Loading book SQLite DB
	$bookDB = createBookDB($grabbeddir);
	// Checking if XML exists
	echo "Checking if XML exists... ";
	if (file_exists($grabbeddir . checkStringContent($book->title) . ".xml")) {
		$xml = loadXML($grabbeddir . checkStringContent($book->title) . ".xml");
		$xml_root = $xml->documentElement;
		echo "exists... ";
	} else {
	// Create new XML
		$xml = createXML();
		$xml_root = createXMLRoot($xml);
		echo "creating... ";
	}
	echo "done<br>";
	// Get chapters array
	$chaptersArray = analyzeTitlePage($book->url_home);
	echo "Loading book " . str_replace('"', "", $book->title) . "<br>";
	// Calc chapter count
	echo "Chapter count... ";
	$book->chapter_count = count($chaptersArray);
	echo "done<br>";
	// Make temp file
	echo "Downloading cover... ";
	$tempFile = $coverdirTemp . $idx . ".jpg";
	$handle = fopen($tempFile, 'w+');
	// Construct URL
	$content = getPage($book->url_home);
	preg_match($pattern_cover, $content, $cover);
	$coverURL = $cover[1];
	// Download image
	grabImage($tempFile, $coverURL, $handle);
	// Create Imagick object from image
	$im = new Imagick("jpg:$tempFile");
	// Write image xml data
	$dim = writeCoverDims($book, $im, $dim);
	// Append object into array
	$bigCover->addimage($im);
	fclose($handle);
	echo "done<br>";
	// Write book info into main table
	echo "Populating main table... ";
	populateMainTable($book, $mainDB);
	echo "done<br>";
	// Download chapters
	echo "Downloading chapters... ";
	foreach ($chaptersArray as $chapter) {
		// Checking if chapter exists
		if (!file_exists($grabbeddir . checkStringContent($chapter->chapterTitle) . ".jpg")) {
			downloadChapter($chapter, $xml, $xml_root,
				$tempdir, $grabbeddir, $bookDB);
		}
	}
	echo "done<br>";
	// Create chapter table for this book
	echo "Creating chapter table... ";
	createChapterTable($mainDB, $book, $chaptersArray);
	echo "done<br>";
	// Create genres table and link it to the book
	echo "Populating genres table... ";
	populateGenresTable($book, $mainDB);
	echo "done<br>";
	// Write XML to disk
	echo "Writing XML to disk... ";
	$xml->save($grabbeddir . checkStringContent($book->title) . ".xml");
	echo "done<br>";
	$idx++;
	if ($idx>20) break;
} 
$bigCover->resetiterator();
try {
	$combined = $bigCover->appendimages(FALSE);
	// Write appended image to file
	$combined->writeimage($coverdir . "cover.jpg");
} catch (Exception $e) {
	
}
$mainDB->close();

echo "Main database created.<br>";
?>