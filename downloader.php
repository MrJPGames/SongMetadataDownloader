<?php
	error_reporting(E_ERROR);
	require 'PHP-ID3/PhpId3/BinaryFileReader.php';
	require 'PHP-ID3/PhpId3/Id3Tags.php';
	require 'PHP-ID3/PhpId3/Id3TagsReader.php';
	use PhpId3\Id3TagsReader;

	//unset($argv[0]);
	//$query = implode($argv, "+");

	$filename = $argv[1];
	if (file_exists($filename)){
        $id3 = new Id3TagsReader(fopen(__DIR__ . "/". $filename, "rb"));
        $id3->readAllTags();

        foreach($id3->getId3Array() as $key => $value) {
			if( $key !== "APIC" ) { //Skip Image data
				echo $value["fullTagName"] . ": " . $value["body"] . PHP_EOL; 
		    }else{
		    	echo "Image";
		    }
		}

		$id3Data = $id3->getId3Array();

		$query = str_replace(" ", "+", $id3Data["TIT2"]["body"] . " " . $id3Data["TPE1"]["body"]);


		$dURL = "https://genius.com/api/search/song?per_page=5&q=" . $query;

		$ch = curl_init($dURL);

	    //return the transfer as a string 
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

	    $result = curl_exec($ch);  

		$searchData = json_decode($result, true);
		
		if ($searchData["response"]["sections"][0]["hits"][0]["result"] != NULL){
			$songData = $searchData["response"]["sections"][0]["hits"][0]["result"];

			echo "Title: ", $songData["title"], PHP_EOL;
			echo "Title with featured: ", $songData["title_with_featured"], PHP_EOL; 
			echo "Artist: ", $songData["primary_artist"]["name"], PHP_EOL;
			echo "Album art image URL: ", $songData["header_image_url"], PHP_EOL;

			$dURL = "https://genius.com/api" . $songData["api_path"];

			echo "API URL: ", $songData["api_path"], PHP_EOL;

			curl_setopt($ch, CURLOPT_URL, $dURL);

		    $result = curl_exec($ch); 

			$jsonData = json_decode($result, true);

			$songData = $jsonData["response"]["song"];
			$albumData = $jsonData["response"]["song"]["album"];

			echo "Full Title: ",  $songData["full_title"], PHP_EOL;
			echo "Release date: ", $songData["release_date"], PHP_EOL;
			echo "Recording Location: ", $songData["recording_location"], PHP_EOL;
			echo "Album name: ", $albumData["name"], PHP_EOL;
			echo "Lyrics page URL: ", $songData["path"];

			$dURL = "https://genius.com".$songData["path"];

			curl_setopt($ch, CURLOPT_URL, $dURL);

			$result = curl_exec($ch);

			$breaks = array("<br />","<br>","<br/>", "            ");  
			$result = str_ireplace($breaks, "", $result);

			$HTMLdoc = new DOMDocument();
			$HTMLdoc->loadHTML($result);

			$xpath = new DomXpath($HTMLdoc);

			$rowNode = $xpath->query('//div[@class="lyrics"]')->item(0);
			if ($rowNode instanceof DomElement) {
			    echo $rowNode->nodeValue;
			}
		}else{
			echo "[!] Did not find any song for " . $query, PHP_EOL;
		}
	}else{
		echo "File not found!";
	}
    curl_close($ch); 
?>