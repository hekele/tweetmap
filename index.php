<?php 

if(empty($argv[1])){
  die("Please set project subfolder of folder data with config.php in it.\n");
}

$project_path = 'data/'.$argv[1];
if(!file_exists($project_path.'/config.php')){
  die("File not found ".$project_path."/config.php\n");
}

require_once('twitter-api-php/TwitterAPIExchange.php');
require_once($project_path.'/config.php');
if(!file_exists($project_path."/tweets")){
  mkdir($project_path."/tweets", 0777, TRUE);
}

$twitter_url = 'https://api.twitter.com/1.1/search/tweets.json';
$requestMethod = 'GET';

// only recent
if($since_id = @file_get_contents($project_path."/since_id.txt")){
  $twitter_getfield .= '&since_id='.$since_id;
}

// load the tweets
$twitter = new TwitterAPIExchange($twitter_settings);
$twitter_response = $twitter->setGetfield($twitter_getfield)
    ->buildOauth($twitter_url, $requestMethod)
    ->performRequest();

// json decode the tweets
$twitter_json = json_decode($twitter_response);

if(!empty($twitter_json->statuses)){

  // store geotagged tweets 
  foreach($twitter_json->statuses as $tweet){
    if(!empty($tweet->coordinates) || !empty($tweet->place)){
      file_put_contents($project_path."/tweets/".$tweet->id.".json", json_encode($tweet, JSON_PRETTY_PRINT));
    }
  }

  // store since id for next run
  file_put_contents($project_path."/since_id.txt", $tweet->id);
}

// build array of present tweet ids
$a_tweet_ids = array();
foreach(scandir($project_path."/tweets") as $file){
  if(preg_match("/json$/", $file)){
    $tweet_json = json_decode(file_get_contents($project_path."/tweets/".$file));
    $a_tweet_ids[] = $tweet_json->id;
  }
}

// fetch current tweet ids from carto
$sql = "SELECT tweet_id FROM ".$carto_api_db_name;
$carto_json = @json_decode(file_get_contents($carto_api_url.rawurlencode($sql)));
if(empty($carto_json)){
  die("Couldn't fetch data from carto: ".$sql."\n");
}
foreach($carto_json->rows as $row) {
  if(($key = array_search($row->tweet_id, $a_tweet_ids)) !== false) {
    unset($a_tweet_ids[$key]);
  }
}

// insert new tweets into carto
foreach($a_tweet_ids as $tweet_id){
  $tweet_json = json_decode(file_get_contents($project_path."/tweets/".$tweet_id.".json"));
  if(!empty($tweet_json)){

    // add geocode
    if(!empty($tweet_json->coordinates->coordinates)){
      $geocode = "ST_SetSRID(ST_Point(".$tweet_json->coordinates->coordinates[0].", ".$tweet_json->coordinates->coordinates[1]."), 4326)";
    }
    elseif(!empty($tweet_json->place)){
      $geocode = "cdb_geocode_street_point('".$tweet_json->place->name."', 'Berlin', 'Germany')";
    }
    else{
      print "No coordinates found!";
      continue;
    }

    // add media
    $tweet_media = !empty($tweet_json->entities->media)?$tweet_json->entities->media[0]->media_url:'';

    // insert into carto
    $sql = "INSERT INTO ".$carto_api_db_name." (the_geom, tweet, tweet_id, tweet_image, user_id, user_name, created_at) 
        VALUES (
        ".$geocode.",
        '".$tweet_json->text."',
        '".$tweet_id."',
        '".$tweet_media."',
        ".$tweet_json->user->id.",
        '".$tweet_json->user->screen_name."',
        '".date("Y-m-d H:i:s", strtotime($tweet_json->created_at))."'
        )";
    $carto_json = json_decode(file_get_contents($carto_api_url.rawurlencode($sql)));
    print_r($carto_json);
  }
  
}

