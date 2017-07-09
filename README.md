# tweetmap
Displays geotagged tweets

0. first you'll need an Carto Engine Account
1. git clone recursive: git clone --recursive git@github.com:hekele/tweetmap.git
2. create subfolder in folder data (e.g. data/radfreude)
3. copy default.config.php into new folder and rename to config (e.g. data/radfreude/config.php)
4. edit config.php and set carto params as well as twitter params
5. copy this carto db to your carto account: https://bosh.carto.com/tables/tweets/public

Then try to call the index.php from command line: index.php radfreude
