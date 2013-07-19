<?php
require_once 'functions.inc.php';
require_once 'regex.inc.php';
require_once 'classes.inc.php';

$pageURL = "http://www.goodmanga.net/mangaka-san_to_assistant-san_to/chapter/128";

// Analyze page
$pageInfo = new imagePageInfo();
$pageInfo = analyzePage($pageURL);
// Create image and xml objects
$combined = new Imagick();
$xml = createXML();
$xml_root = createXMLRoot($xml);
// Create appended image
$bigImage = createBigImage($pageInfo->imageTempURL, 1, 
		$pageInfo->pageCount, $combined, $xml, $xml_root);
// Write appended image to file
$bigImage->writeimage("C:\\Temp\\combined.jpg");
// Write xml to file
$xml->save("c:\\Temp\\test.xml");
?>