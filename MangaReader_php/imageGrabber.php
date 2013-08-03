<?php
require_once 'functions.inc.php';
require_once 'regex.inc.php';
require_once 'classes.inc.php';

ini_set('max_execution_time', 900);

$OS = 'Windows';
// $OS = 'Linux';

if ($OS == 'Windows') {
	$grabbeddir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\grabbed\\';
	$tempdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\temp\\';
} else {
	$grabbeddir = '/var/www/grabbed/';
	$tempdir = '/var/www/temp/';
}
 
// Get text from post
$text = trim($_POST['txtarea']);
$textAr = explode("\n", $text);
$textAr = array_filter($textAr, 'trim'); // remove any extra \r characters left behind

foreach ($textAr as $line) {
	$chaptersArray = analyzeTitlePage($line);
	
	// Create xml objects
	$xml = createXML();
	$xml_root = createXMLRoot($xml);
	
	// Download chapters
	foreach ($chaptersArray as $chapter) {
		downloadChapter($chapter, $xml, $xml_root, 
			$tempdir, $grabbeddir);
	}
	
	$xml->save($grabbeddir . "test.xml");
}

echo "Done!"
?>