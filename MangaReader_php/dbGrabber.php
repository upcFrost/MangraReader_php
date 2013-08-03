<?php
include 'functions.inc.php';
require_once 'classes.inc.php';
require_once 'regex.inc.php';

$OS = 'Windows';
// $OS = 'Linux';

if ($OS == 'Windows') {
	$savedir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\grabbed\\';
	$tempdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\temp\\';
} else {
	$savedir = '/var/www/grabbed/';
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
	// Calc chapter count
	$book->chapter_count = count($chaptersArray);
	// Make temp file
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
	// Write book info into main table
	populateMainTable($book, $db);
	createChapterTable($db, $book, $chaptersArray);
	populateGenresTable($book, $db);
	
	$i++;
	if ($i>2) break;
} 
$bigCover->resetiterator();
$combined = $bigCover->appendimages(FALSE);
// Write appended image to file
$combined->writeimage($savedir . "cover.jpg");
@$db->close();
?>