<?php
// Details
$pattern_book = '#<td><a href="(http://www.goodmanga.net/\d*/.*)">(.*)</a></td>#';
$pattern_cover = '#<img src="(.*goodmanga.net/images/series/large/\d*.jpg)" id="series_image"#';
$pattern_details = '#<div id="series_details">.*<span>Authors:</span>(.*?)</div>.*?<span>Description:</span>.*?<div>(.*?)</div>.*<span>Status:</span>(.*?)</div>.*<span>Released:</span>(.*?)</div>.*<span id="rating_num">(.*?)</span>#s';
$pattern_big_about = '/<span id="full_notes">(.*?)<a href="#/s';
$pattern_ongoing = 'ongoing';
$pattern_genres = '#<a href="http://www.goodmanga.net/manga-genre/.*?">(.*?)</a>#';
$pattern_next = '#<li><a.*?page=\d+">Next</a>#i';
//$pattern_chapter = '<div id="chapters">.*?<h2>.*</h2>.*?<ul>.*?<li>.*?<a href="(.*?)">(.*?)</a>.*?</li>.*?</ul>';
$pattern_chapter = '#<a href="(.*?chapter.*?)">.*?\n[ ]*?([^ ].*?)[ ]*?</a>#';
$pattern_chapterNum = '#chapter/(.*)#';

// Pages
$pattern_page = '#<div id="manga_viewer">.*?<img src="(.*?/)\d\.jpg#s'; 
$pattern_page_count = '#<select name="page_select" class="page_select">.*?<span>of (\d*?)</span>#s';
?> 