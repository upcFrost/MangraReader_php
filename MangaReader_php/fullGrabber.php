<?php
require_once 'functions.inc.php';
require_once 'classes.inc.php';
require_once 'regex.inc.php';

ini_set('max_execution_time', 900);

// Windows
$coverdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\cover\\';
$grabbeddirTemp = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\grabbed\\';
$tempdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\temp\\';
$coverdirTemp = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\cover\\temp\\';
// Linux
// $coverdir = '/var/www/cover/';
// $grabbeddirTemp = '/var/www/grabbed/';
// $tempdir = '/var/www/temp/';


$db = createDB();
$data = getListHTML('http://www.goodmanga.net/manga-list');
$bigCover = new Imagick();
if (!file_exists($coverdirTemp)) mkdir($coverdirTemp, 0777, TRUE);

$bookArray = getBookArray($data);

$idx = 0;
$dim = ['width' => 0, 'height' => 0];
foreach ($bookArray as $book) {
	echo "Book " . $book->title . "<br>";
	// Create new XML
	$xml = createXML();
	$xml_root = createXMLRoot($xml);
	// Get book info
	$book = getBookInfo($book);
	// Create dirs
	echo "Creating dirs... ";
	$grabbeddir = $grabbeddirTemp . $book->title . '\\';
	if (!file_exists($grabbeddir)) mkdir($grabbeddir, 0777, TRUE);
	echo "done<br>";
	// Get chapters array
	$chaptersArray = analyzeTitlePage($book->url_home);
	echo "Loading book " . str_replace('"', "", $book->title) . "<br>";
	// Calc chapter count
	echo "Chapter count... ";
	$book->chapter_count = count($chaptersArray);
	echo "done<br>";
	// Make temp file
	echo "Loading cover... ";
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
	populateMainTable($book, $db);
	echo "done<br>";
	// Download chapters
	echo "Downloading chapters... ";
	foreach ($chaptersArray as $chapter) {
		downloadChapter($chapter, $xml, $xml_root,
		$tempdir, $grabbeddir);
	}
	echo "done<br>";
	// Create chapter table for this book
	echo "Creating chapter table... ";
	createChapterTable($db, $book, $chaptersArray);
	echo "done<br>";
	// Create genres table and link it to the book
	echo "Populating genres table... ";
	populateGenresTable($book, $db);
	echo "done<br>";
	// Write XML to disk
	echo "Writing XML to disk... ";
	$xml->save($grabbeddir . $book->title . ".xml");
	echo "done<br>";
	$idx++;
	if ($idx>2) break;
} 
$bigCover->resetiterator();
$combined = $bigCover->appendimages(FALSE);
// Write appended image to file
$combined->writeimage($coverdir . "cover.jpg");
$db->close();

echo "Main database created.<br>";
?>