<?php
	error_reporting(E_ERROR);
	$options = getopt("d:r");
	echo "Statring metadata downloader", PHP_EOL;

	if (!isset($options["d"])){
		echo "No directory set!";
		exit;
	}
	$directory = $options["d"];

	$recursive=false;
	if (isset($options["r"])){
		$recursive=true;
	}

	$TaggingFormat = 'UTF-8';

	require_once('getID3/getid3/getid3.php');

	//unset($argv[0]);
	//$query = implode($argv, "+");
	scanDirectory($directory, $recursive);

	function scanDirectory($dir, $recursive){
		$getID3 = new getID3;
		$getID3->setOption(array('encoding'=>$TaggingFormat));
		getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);
		echo "Now scanning directory: ", $dir, PHP_EOL;
		$dirContent = scandir($dir);
			//Skip folders . and .. (would case issues in recursive otherwise)
			if ($file == "." || $file == "..")
				continue;
			if (is_dir($file)){
				if ($recursive)
					scanDirectory($dir . "/" . $file);
				continue;
			}
			if (file_exists(__DIR__ . "/" . $dir . "/" . $file) && pathinfo($file, PATHINFO_EXTENSION) == 'mp3'){

				$ThisFileInfo = $getID3->analyze(__DIR__ . "/" . $dir . "/" . $file);

				getid3_lib::CopyTagsToComments($ThisFileInfo);

				$metadata = $ThisFileInfo["tags"]["id3v2"];
				//var_export($metadata);
				

				$query = str_replace(" ", "+", $metadata["title"][0] . " " . $metadata["artist"][0]);

				echo "Searching Genius for metadata for: ", $metadata["title"][0], " ", $metadata["artist"][0], PHP_EOL;

				//Start searching based on song name + artist
				$dURL = "https://genius.com/api/search/song?per_page=5&q=" . $query;

				$ch = curl_init($dURL);

			    //return the transfer as a string 
			    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

			    $result = curl_exec($ch);  

				$searchData = json_decode($result, true);
				
				//Check if Genius returns any search results
				if ($searchData["response"]["sections"][0]["hits"][0]["result"] != NULL){
					$songData = $searchData["response"]["sections"][0]["hits"][0]["result"];

					//Song Title
					$metadata["title"][0] = $songData["title"];
					//Song Artist(s)
					if ($songData["featured_artists"][0] == NULL){
						$metadata["band"][0] = $songData["primary_artist"]["name"];
						$metadata["artist"][0] = $songData["primary_artist"]["name"];
					}else{
						//Correct handling of featured artist data
						$i=0;
						foreach($songData["featured_artists"] as $feature){
							$features[$i] = $feature["name"];
							$i++;
						}
						$metadata["band"][0] = $songData["artist"] . " (Ft. " . implode(" ", $features) . ")";
						$metadata["original_artist"][0] = $songData["primary_artist"]["name"] . " (Ft. " . implode(" ", $features) . ")";
					}
					$songArtURL = $songData["header_image_url"];

					if ($songArtURL != NULL){
						$dURL = $songArtURL;
						curl_setopt($ch, CURLOPT_URL, $dURL);

					    $songArtData = curl_exec($ch);

					    $metadata["attached_picture"][0]["data"] = $songArtData;
					    $metadata["attached_picture"][0]["picturetypeid"] = 0x03; //Cover (front) art
					    $metadata["attached_picture"][0]["description"] = "Cover added by SongMetadataDownloader";
					    $metadata["attached_picture"][0]["mime"] = "image/jpeg";
					    unset($songArtData);
					}

					$dURL = "https://genius.com/api" . $songData["api_path"];

					curl_setopt($ch, CURLOPT_URL, $dURL);

				    $result = curl_exec($ch); 

					$jsonData = json_decode($result, true);

					$songData = $jsonData["response"]["song"];
					$albumData = $jsonData["response"]["song"]["album"];

					//Release date
					$metadata["date"][0] = $songData["release_date"];
					//Album name
					if ($albumData["name"] != "")
						$metadata["album"][0] = $albumData["name"];
					//Year
					$metadata["year"][0] = $songData["release_date_components"]["year"];

					//Does Genius know who the writers are?
					if ($songData["writer_artists"][0] != NULL){
						$i=0;
						foreach($songData["writer_artists"] as $writer){
							$writers[$i] = $writer["name"];
							$i++;
						}
						$metadata["lyricist"][0] = implode(", ", $writers);
					}

					//Get lyrics (From HTML data)
					$dURL = "https://genius.com" . $songData["path"];

					curl_setopt($ch, CURLOPT_URL, $dURL);

					$result = curl_exec($ch);

					$breaks = array("<br />","<br>","<br/>", "            ");  
					$result = str_ireplace($breaks, "", $result);

					$HTMLdoc = new DOMDocument();
					$HTMLdoc->loadHTML($result);

					$xpath = new DomXpath($HTMLdoc);

					$rowNode = $xpath->query('//div[@class="lyrics"]')->item(0);
					if ($rowNode instanceof DomElement) {
					    //Lyrics found
					    $metadata["unsynchronised_lyric"][0] = $rowNode->nodeValue;
					}

					echo "Metadata was found! Songtitle: ", $songData["title"], " Artist: ", $songData["primary_artist"]["name"], " Album: ", $albumData["name"], PHP_EOL;


					//Start writing tags
					$tagwriter = new getid3_writetags;
					$tagwriter->filename       = __DIR__ . "/" . $dir . "/" . $file;
					$tagwriter->tagformats     = ['id3v2.3'];
					$tagwriter->overwrite_tags = true;
					$tagwriter->tag_encoding   = $TaggingFormat;
					$tagwriter->tag_data	   = $metadata;
					if ($tagwriter->WriteTags()) {
						echo "Wrote Metadata!", PHP_EOL;
					}else{
						echo "Failed to write metadata to file!", PHP_EOL, "Error: ", html_entity_decode(strip_tags($tagwriter->errors[0])), PHP_EOL;
					}


				}else{
					echo "[!] Did not find any song for " . $query, PHP_EOL;
				}
			}else{
				echo "Skipped ", $file, PHP_EOL;
			}
		}
	}
    curl_close($ch); 
?>