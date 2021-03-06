<?php
include 'functions.inc.php';
require_once 'classes.inc.php';
require_once 'regex.inc.php';

ini_set('max_execution_time', 900);

$OS = 'Windows';
// $OS = 'Linux';

if ($OS == 'Windows') {
	$coverdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\cover\\';
	$tempdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\temp\\';
} else {
	$coverdir = '/var/www/cover/';
	$tempdir = '/var/www/temp/';
}


$db = createDB();
$data = getListHTML('http://www.goodmanga.net/manga-list');
$bigCover = new Imagick();

$bookArray = getBookArray($data);

$i = 0;
$dim = ['width' => 0, 'height' => 0];
foreach ($bookArray as $book) {
	$book = getBookInfo($book);
	// Get chapters array
	$chaptersArray = analyzeTitlePage($book->url_home);
	echo "Loading book " . str_replace('"', "", $book->title) . "<br>";
	// Calc chapter count
	echo "Chapter count... ";
	$book->chapter_count = count($chaptersArray);
	echo "done<br>";
	// Make temp file
	echo "Loading cover... ";
	$tempFile = $tempdir . $i . ".jpg";
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
	// Create chapter table for this book
	echo "Creating chapter table... ";
	createChapterTable($db, $book, $chaptersArray);
	echo "done<br>";
	// Create genres table and link it to the book
	echo "Populating genres table... ";
	populateGenresTable($book, $db);
	echo "done<br>";
	$i++;
	if ($i>30) break;
} 
$bigCover->resetiterator();
$combined = $bigCover->appendimages(FALSE);
// Write appended image to file
$combined->writeimage($coverdir . "cover.jpg");
$db->close();

echo "Main database created.<br>";
?>