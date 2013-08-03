<?php
class imagePageInfo {
	public $imageTempURL = "";
	public $pageCount = "";
}

class chapterInfo {
	public $chapterURL = "";
	public $chapterTitle = ""; 
}

class Book {
	public $title = "";
	public $url_home = "";
	public $book_table = "";
	public $local_path = "";
	public $about = "";
	public $genres = array();
	public $cover = ['x' => 0, 'y' => 0, 'w' => 0, 'h' => 0];
	public $rating = 0;
	public $chapter_count = "";
	public $ongoing = 0;
}
?> 