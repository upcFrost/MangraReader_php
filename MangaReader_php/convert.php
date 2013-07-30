<?php
require_once 'functions.inc.php';
require_once 'regex.inc.php';
require_once 'classes.inc.php';

ini_set('max_execution_time', 900);

$OS = 'Windows';
// $OS = 'Linux';

if ($OS == 'Windows') {
	$uploaddir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\upload\\';
	$convertdir = 'C:\\Users\\PBelyaev\\git\\MangaReader_php\\MangaReader_php\\convert\\';
} else {
	$uploaddir = '/var/www/uploads/';
	$convertdir = '/var/www/convert/';
}

$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);
echo $_FILES['userfile']['name'] . "<br>";

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
	echo "File upload success.<br>";
} else {
	echo "Possible attack through file upload!<br>";
}

// Create xml file
$file = $uploadfile;
if (!file_exists($convertdir . "test.xml")) {
	// Create xml objects
	$xml = createXML();
	$xml_root = createXMLRoot($xml);
} else {
	// Load xml objects
	$xml = readXML($convertdir . "test.xml");
	$xml_root = readXMLRoot($xml);
}

// Create new chapter
$all = new Imagick();
$xml_chapter = createChapter($xml, $xml_root, '123');
$dim = ['width' => 0, 'height' => 0];

echo $_FILES['userfile']['type'] . "<br>";

// Check archive type
if ($_FILES['userfile']['type'] == 'application/x-zip-compressed') {
	// Unzip file
	$item = loadZip($uploaddir, $file);
}
// TODO more archive types

if ($item != FALSE) {
	// Sorting file array
	sort($item, SORT_NUMERIC);
	
	// Append files to IMagick object
	$i = 1;
	foreach ($item as $entry) {
		// Create Imagick object from file
		$im = new Imagick($uploaddir . $entry);
		
		// Write image xml data
		$dim = writeDims($xml, $im, $i, $dim, $xml_chapter);
		
		// Append object into array
		$all->addimage($im);
		$i++;
	}
	
	// Combine images
	$all->resetiterator();
	$combined = $all->appendimages(FALSE);
	
	// Write appended image to file
	$combined->writeimage($convertdir . "converted.jpg");
	$xml->save($convertdir . "test.xml");
	
	echo "Convertion done!<br>";
	echo "<img src='convert/converted.jpg'>";
} else {
	echo "Error reading ZIP!";
}
?>