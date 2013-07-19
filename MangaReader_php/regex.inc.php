<?php
// Details
$pattern_book = '<td><a href="(http://www.goodmanga.net/\d*/.*)">(.*)</a></td>';
$pattern_cover = '<img src="(.*goodmanga.net/images/series/large/\d*.jpg)" id="series_image"';
$pattern_details_open = '<div id="series_details">';
$pattern_details_close = '<div id="series_social">';
$pattern_details = '<div id="series_details">.*<span>Authors:</span>(.*?)</div>.*?<span>Description:</span>.*?<div>(.*?)</div>.*<span>Status:</span>(.*?)</div>.*<span>Released:</span>(.*?)</div>.*<span id="rating_num">(.*?)</span>';
$pattern_big_about = '<span id="full_notes">(.*?)<a href="#';
$pattern_ongoing = 'ongoing';
$pattern_genres = '<a href="http://www.goodmanga.net/manga-genre/.*?">(.*?)</a>';
$pattern_chapters = '<div id="chapters">.*?<h2>.*</h2>.*?<ul>.*?<li>.*?<a href="(.*?)">(.*?)</a>.*?</li>.*?</ul>';
$pattern_next = '<li><a href="(.*?page=\d+)">Next</a></li>';

// Pages
$pattern_page = '#<div id="manga_viewer">.*?<img src="(.*?/)\d\.jpg#s'; 
$pattern_page_count = '#<select name="page_select" class="page_select">.*?<span>of (\d*?)</span>#s';
?>