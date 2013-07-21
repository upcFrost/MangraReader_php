<?php
require_once 'classes.inc.php';

/** Global functions **/

function downloadChapter($chapter, $xml, $xml_root) {
	// Analyze page
	$pageInfo = new imagePageInfo();
	$pageInfo = analyzePage($chapter->chapterURL);
	// Create image and xml objects
	$combined = new Imagick();
	// Create appended image
	$bigImage = createBigImage($pageInfo->imageTempURL, 1,
			$pageInfo->pageCount, $combined, $xml, $xml_root,
			$chapter->chapterTitle);
	// Write appended image to file
	$bigImage->writeimage("C:\\Temp\\Done\\" . $chapter->chapterTitle . ".jpg");
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
	curl_setopt($ch, CURLOPT_HEADER, 0);
	$data = curl_exec($ch);
	curl_close($ch);
	
	return $data;
}

/** Page analysis functions **/

function analyzeTitlePage($titleURL) {
	// Use patterns from regex.inc
	global $pattern_chapter;
	// Using array as a return var
	$chaptersArray = array();
	$content = getPage($titleURL);
	if (preg_match_all($pattern_chapter, $content, $matches)) {
		$length = count($matches[1]);
		for ($i = 0; $i < $length; $i++) {
			$chaptersArray[$i] = new chapterInfo();
			$chaptersArray[$i]->chapterURL = $matches[1][$i];
			$chaptersArray[$i]->chapterTitle = $matches[2][$i];
		}
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
 *  */
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

/** Image grabber **/

function grabImage($tempFile, $url, $handler) {
	$data = getPage($url);
	file_put_contents($tempFile, $data);
}

function createBigImage($urlTemp, $minIdx, $maxIdx,
		$combined, $xml, $xml_root, $chapterName) {
	$all = new Imagick();
	$xml_chapter = createChapter($xml, $xml_root, $chapterName);
	$dim = ['width' => 0, 'height' => 0];
	for ($i = $minIdx; $i < $maxIdx+1; $i++) {
		// Make temp file
		$tempFile = "c:\\Temp\\" . $i . ".jpg";
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