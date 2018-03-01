# SongMetadataDownloader
Downloads metadata from Genius for all mp3's in a directory

Usage:
---
```php downloader.php -d directory_name```

Add -r for recursive folder searching

Directory names can be relative to current directory or absolute paths

Warning:
---
This script will modify your MP3's ID3 data. It also removes some data as it can cause issues with getID3 the PHP libary used to modify the metadata. The info received from Genius could also be wrong, either because the song it found was not the actual song of the MP3 or because Genius contained incorrect data (as it is user generated this can happen). Therefor we recommend you make a copy of your music first then use the script on the copy and check manually if everything is okey before removing/overwriting the original file.
