<?php
require_once 'functions.inc.php';
require_once 'regex.inc.php';
require_once 'classes.inc.php';

ini_set('max_execution_time', 300);

$titleURL = "http://www.goodmanga.net/1586/mangaka-san_to_assistant-san_to";
$chaptersArray = analyzeTitlePage($titleURL);
// Create xml objects
$xml = createXML();
$xml_root = createXMLRoot($xml);
// Download chapters
foreach ($chaptersArray as $chapter) {
	downloadChapter($chapter, $xml, $xml_root);
}
// $pageURL = "http://www.goodmanga.net/mangaka-san_to_assistant-san_to/chapter/128";

// // Analyze page
// $pageInfo = new imagePageInfo();
// $pageInfo = analyzePage($pageURL);
// // Create image
// $combined = new Imagick();
// // Create appended image
// $bigImage = createBigImage($pageInfo->imageTempURL, 1, 
// 		$pageInfo->pageCount, $combined, $xml, $xml_root);
// // Write appended image to file
// $bigImage->writeimage("C:\\Temp\\combined.jpg");
// Write xml to file
$xml->save("c:\\Temp\\test.xml");
?>