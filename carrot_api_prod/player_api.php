<?php
	/** 
		* Created by PhpStorm.
		* User: tos
		* Date: 8/13/17
		* Time: 12:50 PM
	*/
	require 'Medoo.php';
	require 'config.php';

	function log_player_api_request() {
		$log_path = __DIR__ . '/player_api_request_debug.log';
		$request = $_REQUEST;
		if (isset($request['password'])) {
			$request['password'] = '***';
		}
		$entry = array(
			'ts' => date('c'),
			'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
			'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'query' => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
			'request' => $request
		);
		$result = file_put_contents($log_path, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
		if ($result === false) {
			error_log('player_api debug log write failed: ' . $log_path);
		}
	}

  function append_virtual_category(&$output, $categoryId, $categoryName) {
    if (!is_array($output)) {
      $output = array();
    }
    $output[] = array(
      "category_id" => (string)$categoryId,
      "category_name" => $categoryName,
      "parent_id" => 0
    );
  }
	
	$valid_actions = array("get_live_categories", "get_vod_categories", "get_live_streams", "get_short_epg", "get_simple_data_table", "get_vod_streams", "get_vod_info");
	$login = $_REQUEST['username'];
	$userpass = $_REQUEST['password'];
	$action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : "");
  $catid = (isset($_REQUEST['category_id']) ? $_REQUEST['category_id'] : "");
	log_player_api_request();
	
	
	$db = new \Medoo\Medoo([
    'database_type' => 'mysql',
    'database_name' => $stalker_db,
    'server' => $stalker_host,
    'username' => $stalker_dbuser,
    'password' => $stalker_dbpass,
    'charset' => 'utf8',
	]);
	
	$plf = new plfunctions($db);
	
	$passcheck = $plf->checkUserPass($login, $userpass);
	if (!$passcheck){
        header('Content-type: application/json');
		// $output = $plf->getUserinfo($login,$userpass);
        if(!$output){
            $output = ["user_info"=>["auth"=> 0, "status" => "Unknown"]];
		}
        echo json_encode($output);
        exit();
	}	
	
	$output1 = $plf->getUserinfoPanel($login);
	if( $output1["user_info"]["status"] == 'Expired'){
		$output = $plf->getUserinfo($login,$userpass);
		echo json_encode($output);
		exit();
	}
	
	if($plf->checkUserServer($login,$userpass)){
		$VODUser = $plf->getVODSwitch($login);
		$output1 = json_decode($plf->getUserinfo($login,$userpass),true);
		if ($output1['status'] == 'Active')
		{
			$active = true;
		}
		switch ($action) {
			
			case "get_live_categories":
			
			
            $output = $plf->getLiveCategories($login);
			
			
			break;
			
			case "test":
			$str = "300014";
			$cats = $plf->getEpgStream($str);
			print_r($cats);
			
			
			break;
			case "get_live_streams":
			
			$output = [];
			
            $cats = $plf->getLiveCategories($login);
			$i = 0;
			foreach ($cats as $category){
				$catid = $category['category_id'];
				if ((isset($_REQUEST["category_id"])) && ($_REQUEST["category_id"] === $catid) or (!isset($_REQUEST["category_id"]))){
					$streams = $plf->getLiveStreamsByCat1($catid);
					foreach ($streams as $stream) {
						$i ++;
						
						$imgID = $stream['name'];
						if(substr($imgID, 0, 11) ==  "Live Events" || substr($imgID, 0, 3) ==  "PPV" || substr($imgID, 0, 11) ==  "Live events"){
							$imgID = "Live Events";
						}
						$imgID = str_replace(' +1', '', $imgID);
						$imgID = str_replace('+1', '', $imgID);
						$imgID = str_replace('(FHD)', '', $imgID);
						$imgID = str_replace('(HD)', '', $imgID);
						$imgID = str_replace('(SD)', '', $imgID);
						$imgID = str_replace('FHD', '', $imgID);
						$imgID = str_replace('HD', '', $imgID);
						$imgID = str_replace('SD', '', $imgID);
						if (substr($imgID,0,8) == "iFollow "){
							$imgID = substr($imgID, 0, 10);
						} 
						if (substr($imgID,0,4) == "EFL "){
							$imgID = substr($imgID, 0, 6);
						} 
						$imgID = str_replace(':', '-', $imgID);
						$imgID = str_replace('/', '-', $imgID);
						$imgID = str_replace('*', '', $imgID);
						$imgID = trim($imgID);
						$imgID = str_replace(' ', '%20', $imgID);
						if (strpos($stream['title'],"24/7") === 0){
							$imgID = "24-7";
						}
						if (strpos($stream['title'],"Formula 1") === 0){
							$imgID = "F1 TV";
						}
						if (strpos($stream['title'],"XXX") > 0){
							$imgID = "XXX";
						}
						//  if (strpos($stream['title'],"iFollow") === 0 && substr($imgID, 0, 3) == "EFL"){
						//  $imgID = "iFollow";
						//   }
						
						
						$stream_icon = "http://misc.allthehubbs.co.uk/SHUB/images/channels/" . $imgID . ".png";
						$output[] = Array("num" => $i, "name" => $stream["name"], "stream_type" => "live", "stream_id" => $stream["cmd"], "stream_icon" => $stream_icon, "epg_channel_id" => $stream["name"], "added" => time(), "category_id" => $stream["tv_genre_id"], "tv_archive" => $stream["enable_tv_archive"], "direct_source" => "", "tv_archive_duration" => $stream["tv_archive_duration"], "custom_sid" => $i);
					}  
				}
			}
			
			break;
			
			case "get_vod_categories":
			/*if ($VODUser){
				if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer"){
				$categories = $plf->getPVODCategoriesSH();
				} else {
				$categories = $plf->getVODCategoriesSH();
				}
				foreach ($categories as $category) {
                $output[] = Array("category_id" => $category["id"], "category_name" => $category["category_name"], "parent_id" => 0);
				
				}
				} else {
			*/
            if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer" || $login === "johnfessey"|| $login === "charliewhelan"){
				$categories = $plf->getPVODCategoriesSH();
				} else {
				//$categories = $plf->getVODCategoriesSH();
        $categories = $plf->getPVODCategoriesSH();
			}
			
            foreach ($categories as $category) {
                $output[] = Array("category_id" => $category["id"], "category_name" => utf8_encode($category["category_name"]), "parent_id" => 0);
				
			}
            append_virtual_category($output, $plf->getSportsHubMyListMovieCategoryId(), " My SH Watchlist");
      

            //$output = [];
			// }
            break;
			
			case "get_series_categories":
			/*  if ($VODUser){
				if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer"){
				$categories = $plf->getPSeriesCategoriesSH();
				} else {
				$categories = $plf->getSeriesCategoriesSH();
				}
				
				foreach ($categories as $category) {
                //$output[] = Array("category_id" => $category["id"], "category_name" => $category["category_name"], "parent_id" => 0);
                $output[] = Array("category_id" => $category["category"], 
                "category_name" => $category["genre"], 
                "parent_id" => 0);
				
				}
			} else {*/
            if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer" || $login === "johnfessey"|| $login === "charliewhelan"){
				$categories = $plf->getPSeriesCategoriesSH();
				} else {
				//$categories = $plf->getSeriesCategoriesSH();
        $categories = $plf->getPSeriesCategoriesSH();
			}
			
            foreach ($categories as $category) {
                $output[] = Array("category_id" => $category["category"], 
                "category_name" => utf8_encode($category["genre"]), 
                "parent_id" => 0);
				
			}
            append_virtual_category($output, $plf->getSportsHubMyListSeriesCategoryId(), " My SH Watchlist");

            //$output = [];
			//  }
			
            break;
			
			case "get_series":
			/*if ($VODUser){
				if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer"){
				$streams = $plf->getPSeriesSH();
				} else {
				$streams = $plf->getSeriesSH();
				}
				
				$i = 0;
				foreach ($streams as $stream) {
                $i ++;
				if (isset($stream["rating"])) {
				$rating = floatval($stream["rating"]);
				$rating_5 = ceil($rating / 2.0);
				} else {
				$rating = "9";
				$rating_5 = 4.5;
				}
				$serid = $stream['ser_id'];
				$output[] = Array("num" => $i,
				"name" => utf8_encode($stream["ser_name"]),
				
				//"name" => $stream['ser_name'], 
				//"stream_type" => "tvseries", 
				"series_id" => (int)$serid, 
				"cover" => $stream['poster'],
				"plot" => utf8_encode($stream['description']),
				"cast" => "Cast info",
				"director" => "",
				"genre" => $stream['genre'],
				//"stream_icon" => $stream["poster"], 
				"added" => strtotime($stream['added']), 
				"releaseDate" => $stream['releasedate'], 
				"last_modified" => strtotime($stream['added']), 
				"rating" => $rating, 
				"rating_5based" => (int)$rating_5, 
				//"rating" => "7", 
				//"rating_5based" => 3.5, 
				"backdrop_path" => array( "0" => $stream['backdrop']),
				
				//"direct_source" => "", 
				
				//"custom_sid" => "", 
				"youtube_trailer" => $stream['yt_trailer'],
				"episode_run_time" => $stream['ep_runtime'],
				//"container_extension" => "mkv");
				"category_id" => $stream['category']
				);
				//$containers[$stream["target_container_id"]]
				
				}
				
			} else {*/
            $output = [];
            
            $catidString = (string)$catid;
            if ($catidString === (string)$plf->getSportsHubMyListSeriesCategoryId()) {
              $streams = $plf->getPVODMyListSeriesStreams($login, '');
            } else {
              if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer" || $login === "johnfessey"|| $login === "charliewhelan"){
                $streams = $plf->getPSeriesSH($catid);
                } else {
                //$streams = $plf->getSeriesSH();
                $streams = $plf->getPSeriesSH($catid);
              }
              if ($catid === ""){
                $streams = $plf->dedupeSeriesStreams($streams, $catid);
              }
            }
			
            $i = 0;
            foreach ($streams as $stream) {
                $i ++;
				if (isset($stream["rating"])) {
					$rating = floatval($stream["rating"]);
					$rating_5 = ceil($rating / 2.0);
                    } else {
					$rating = "9";
					$rating_5 = 4.5;
				}
				$serid = $stream['ser_id'];
				$output[] = Array("num" => $i,
				"name" => utf8_encode($stream["ser_name"]),
				
				//"name" => $stream['ser_name'], 
				//"stream_type" => "tvseries", 
				"series_id" => (int)$serid, 
				"cover" => $stream['poster'],
				"plot" => utf8_encode($stream['description']),
				"cast" => "Cast info",
				"director" => "",
				"genre" => $stream['genre'],
				//"stream_icon" => $stream["poster"], 
				"added" => strtotime($stream['added']), 
				"releaseDate" => $stream['releasedate'], 
				"last_modified" => strtotime($stream['added']), 
				"rating" => $rating, 
				"rating_5based" => (int)$rating_5, 
				//"rating" => "7", 
				//"rating_5based" => 3.5, 
				"backdrop_path" => array( "0" => $stream['backdrop']),
				
				//"direct_source" => "", 
				
				//"custom_sid" => "", 
				"youtube_trailer" => $stream['yt_trailer'],
				"episode_run_time" => $stream['ep_runtime'],
				//"container_extension" => "mkv");
				"category_id" => $stream['category']
				);
				//$containers[$stream["target_container_id"]]
				
			}
			//echo json_encode($output);
			// }

            break;	
			
			case "get_series_info":
			/*	if ($VODUser){
				$sid = (isset($_REQUEST['series_id']) ? $_REQUEST['series_id'] : "");
				if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer"){
				$voddata = $plf->getPSeriesDataSH($sid);
				} else {
				$voddata = $plf->getSeriesDataSH($sid);
				}
				
				// get seasons of series
				$i = 0;
				foreach ($voddata as $season) {
				if (isset($season["rating"])) {
				$rating = floatval($season["rating"]);
				$rating_5 = ceil($rating / 2.0);
				} else {
				$rating = "";
				$rating_5 = 0;
				}
				$output['seasons'][$i] = Array("air_Date" => $season['airdate'],
				"episode_count" => $season['epcount'],
				"id" => $season['ser_id'],
				"name" => utf8_encode("Season ".$season['season']),
				"overview" => utf8_encode($season['description']),
				"season_number" => $season['season'],
				"cover" => $season['cover'],
				"cover_big" => $season['coverbig']);
				//series info
				$output['info'] = Array("name" => utf8_encode($season['ser_name']),
				"cover" => $season['poster'],
				//"plot" => utf8_encode($season['overview']),
				"plot" => utf8_encode($season['description']),
				"cast" => "",
				"director" => "",
				"genre" => $season['genre'],
				"releaseDate" => $season['releasedate'],
				"last_modified" => "1581545299",
				"rating" => $rating,
				"rating_5based" => $rating_5,
				"backdrop_path" => array(0 => $season['backdrop']),
				"youtube_trailer" => $season['yt_trailer'],
				"episode_run_time" > "",
				"category_id" => $season['category']);	
				$i++;
				}
				
				$audio = Array("index" => 0); 
				$video = Array("index" => 0); 
				$bdpath = Array("bdpath" => "");
				if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer"){
				$streams = $plf->getPSeriesInfoSH($sid);
				} else {
				$streams = $plf->getSeriesInfoSH($sid);
				}
				$i = 0;
				foreach ($streams as $stream) {
                $i ++;
                
				//$movie_properties = json_decode($stream["movie_propeties"], True);
				if (isset($stream["rating"])) {
				$rating = floatval($stream["rating"]);
				$rating_5 = ceil($rating / 2.0);
				} else {
				$rating = "";
				$rating_5 = 0;
				}
				
				$info = Array("tmdb_id" => $stream['imdbid'],
				"movie_image" => $stream['epi_pic'],
				"plot" => utf8_encode($stream['overview']),
				"releaseDate" => "2020-01-01",
				//"name" => "",
				"genre" => "genre",
				"duration_secs" => 0,
				"duration" => "00:00:00",
				"audio" => $audio, 
				"video" => $video,
				"season" => $stream['season'],
				"bitrate" => 0,
				"rating" => $rating);
				// episodes
				
				$episodes = Array("id" => $stream['id'],
				"episode_num" => $stream['episode'],
				"title" => utf8_encode($stream['title']),
				"container_extension" => $stream['container'],
				"info" => $info,
				"custom_sid" => "",
				"added" => "1581545299",
				"season" => $stream['season'],
				"direct_source" => "");
				$output['episodes'][$stream['season']][] = $episodes;
				
				
				
				//$output = [];
				}
			} else {*/
			$sid = (isset($_REQUEST['series_id']) ? $_REQUEST['series_id'] : "");
			if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer" || $login === "johnfessey"|| $login === "charliewhelan"){
				$voddata = $plf->getPSeriesDataSH($sid);
				} else {
				//$voddata = $plf->getSeriesDataSH($sid);
        $voddata = $plf->getPSeriesDataSH($sid);
			}
			
			// get seasons of series
			$i = 0;
            foreach ($voddata as $season) {
				if (isset($season["rating"])) {
					$rating = floatval($season["rating"]);
					$rating_5 = ceil($rating / 2.0);
                    } else {
					$rating = "";
					$rating_5 = 0;
				}
				$output['seasons'][$i] = Array("air_Date" => $season['airdate'],
				"episode_count" => $season['epcount'],
				"id" => $season['ser_id'],
				"name" => utf8_encode("Season ".$season['season']),
				"overview" => utf8_encode($season['description']),
				"season_number" => $season['season'],
				"cover" => $season['cover'],
				"cover_big" => $season['coverbig']);
				//series info
				$output['info'] = Array("name" => utf8_encode($season['ser_name']),
				"cover" => $season['poster'],
				// "plot" => utf8_encode($season['overview']),
				"plot" => utf8_encode($season['description']),
				"cast" => "",
				"director" => "",
				"genre" => $season['genre'],
				"releaseDate" => $season['releasedate'],
				"last_modified" => "1581545299",
				"rating" => $rating,
				"rating_5based" => $rating_5,
				"backdrop_path" => array(0 => $season['backdrop']),
				"youtube_trailer" => $season['yt_trailer'],
				"episode_run_time" => "",
				"category_id" => $season['category']);	
				$i++;
			}
			
			$audio = Array("index" => 0); 
			$video = Array("index" => 0); 
			$bdpath = Array("bdpath" => "");
			if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer" || $login === "johnfessey"|| $login === "charliewhelan"){
				$streams = $plf->getPSeriesInfoSH($sid);
				} else {
				//$streams = $plf->getSeriesInfoSH($sid);
        $streams = $plf->getPSeriesInfoSH($sid);
			}
			
            $i = 0;
            foreach ($streams as $stream) {
                $i ++;
                
				//$movie_properties = json_decode($stream["movie_propeties"], True);
				if (isset($stream["rating"])) {
					$rating = floatval($stream["rating"]);
					$rating_5 = ceil($rating / 2.0);
                    } else {
					$rating = "";
					$rating_5 = 0;
				}
				
				$info = Array("tmdb_id" => $stream['imdbid'],
				"movie_image" => $stream['epi_pic'],
				"plot" => utf8_encode($stream['overview']),
				"releaseDate" => "2020-01-01",
				//"name" => "",
				"genre" => "genre",
				"duration_secs" => 0,
				"duration" => "00:00:00",
				"audio" => $audio, 
				"video" => $video,
				"season" => $stream['season'],
				"bitrate" => 0,
				"rating" => $rating);
				// episodes
				
				$episodes = Array("id" => $stream['id'],
				"episode_num" => $stream['episode'],
				"title" => utf8_encode($stream['title']),
				"container_extension" => $stream['container'],
				"info" => $info,
				"custom_sid" => "",
				"added" => "1581545299",
				"season" => $stream['season'],
				"direct_source" => "");
				$output['episodes'][$stream['season']][] = $episodes;
				

				
				//$output = [];
			}
			//	}
            break;
			
			case "get_vod_streams":
			/*		if ($VODUser){
				//$containers = Array();
				//foreach ( as $container) {
				//    $containers[$container["container_id"]] = $container["container_extension"];
				//}
				if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer"){
				$streams = $plf->getVODStreamsSH();
				} else {
				$streams = $plf->getVODStreamsSH();
				}
				$i = 0;
				foreach ($streams as $stream) {
                $i ++;
                
				//$movie_properties = json_decode($stream["movie_propeties"], True);
				if (isset($stream["rating"])) {
				$rating = floatval($stream["rating"]);
				$rating_5 = ceil($rating / 2.0);
				} else {
				$rating = null;
				$rating_5 = 0;
				}
				$output[] = Array("num" => $i, 
				"name" => utf8_encode($stream["mov_name"]), 
				"stream_type" => "movie", 
				"stream_id" => $stream["mov_id"], 
				"stream_icon" => $stream["poster"], 
				"added" => strtotime($stream["added"]), 
				"is_adult" => "0", 
				"category_id" => $stream["category"], 
				"direct_source" => "", 
				"rating" => $rating, 
				"rating_5based" => $rating_5, 
				"custom_sid" => "", 
				"container_extension" => $stream["container"]);
				}
			} else {*/
			
      $catidString = (string)$catid;
      if ($catidString === (string)$plf->getSportsHubMyListMovieCategoryId()) {
        $streams = $plf->getPVODMyListMovieStreams($login, '');
      } else {
        if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer" || $login === "johnfessey"|| $login === "charliewhelan"){
          $streams = $plf->getPVODStreamsSH($catid);
          } else {
          //$streams = $plf->getVODStreamsSH();
          $streams = $plf->getPVODStreamsSH($catid);
        }
        if ($catid === ""){
          $streams = $plf->dedupeVODStreams($streams, $catid);
        }
      }
			$output = array();
            $i = 0;
            foreach ($streams as $stream) {
                $i ++;
                
				//$movie_properties = json_decode($stream["movie_propeties"], True);
				if (isset($stream["rating"])) {
					$rating = floatval($stream["rating"]);
					$rating_5 = ceil($rating / 2.0);
                    } else {
					$rating = null;
					$rating_5 = 0;
				}
				$output[] = Array("num" => $i, 
				"name" => utf8_encode($stream["mov_name"]), 
				"stream_type" => "movie", 
				"stream_id" => $stream["mov_id"], 
				"stream_icon" => $stream["poster"], 
				"added" => strtotime($stream["added"]), 
				"is_adult" => "0", 
				"category_id" => $stream["category"], 
				"direct_source" => "", 
				"rating" => $rating, 
				"rating_5based" => $rating_5, 
				"custom_sid" => "", 
				"container_extension" => $stream["container"]);
			}

			// $output = [];
			//   }
			
            break;	
			case "get_vod_info":
			/*       if ($VODUser){
				if (isset($_REQUEST["vod_id"])) {
				$vodid = $_REQUEST["vod_id"];
				if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer"){
				$movie_properties = $plf->getPMovieDetailsSH($vodid);
				} else {
				$movie_properties = $plf->getMovieDetailsSH($vodid);
				}
                
				$movie = $movie_properties->fetch_assoc();
                
                if (isset($movie["rating"])) {
				$rating = floatval($movie["rating"]);
                } else {
				$rating = "";
                }
                $stream_info = $movie["mov_name"];
				$audio = Array("index" => 0); 
				$video = Array("index" => 0);
                $output["info"] = Array("imdb_id" => $movie["imdb_id"], 
                "movie_image" => $movie["poster"], 
                "genre" => $movie["genre"], 
                "plot" => $movie["description"], 
                "cast" => "", "director" => "", 
                "rating" => $rating, 
                "releasedate" => $movie["releasedate"], 
                "duration_secs" => $movie["duration_secs"], 
                "duration" => "", 
                "bitrate" => (int)$movie["bitrate"], 
                "kinopoisk_url" => "", 
                "episode_run_time" => "", 
                "youtube_trailer" => $movie['yt_trailer'], 
                "actors" => "", "name" => $movie["mov_name"], 
                "name_o" => $movie["mov_name"], 
                "cover_big" => $movie["backdrop"], 
                "description" => utf8_encode($movie["description"]), 
                "age" => $movie["age"], 
                "rating_mpaa" => "", 
                "rating_count_kinopoisk" => 0, 
                "country" => "", 
                "backdrop_path" => ["0" => $movie["backdrop"]], 
                "audio" => $audio, 
                "video" => $video);
                
                $output["movie_data"] = Array("stream_id" => $movie["mov_id"], "name" => $movie["mov_name"], "added" => strtotime($movie["added"]), "category_id" => $movie["category"], "container_extension" => $movie["container"], "custom_sid" => "", "direct_source" => "");
				}
				
			} else {*/
            if (isset($_REQUEST["vod_id"])) {
				$vodid = $_REQUEST["vod_id"];
                if($login === "alexcruickshank" || $login === "jamieo" || $login === "michaelbuble" || $login === "ryanmortimer" || $login === "johnfessey"|| $login === "charliewhelan"){
					$movie_properties = $plf->getPMovieDetailsSH($vodid);
					} else {
					//$movie_properties = $plf->getMovieDetailsSH($vodid);
          $movie_properties = $plf->getPMovieDetailsSH($vodid);
				}
				$movie = $movie_properties->fetch_assoc();
                
                if (isset($movie["rating"])) {
                    $rating = floatval($movie["rating"]);
					} else {
                    $rating = "";
				}
                $stream_info = $movie["mov_name"];
				$audio = Array("index" => 0); 
				$video = Array("index" => 0);
                $output["info"] = Array("imdb_id" => $movie["imdb_id"], 
                "movie_image" => $movie["poster"], 
                "genre" => $movie["genre"], 
                "plot" => utf8_encode($movie["description"]), 
                "cast" => "", "director" => "", 
                "rating" => $rating, 
                "releasedate" => $movie["releasedate"], 
                "duration_secs" => $movie["duration_secs"], 
                "duration" => "", 
                "bitrate" => (int)$movie["bitrate"], 
                "kinopoisk_url" => "", 
                "episode_run_time" => "", 
                "youtube_trailer" => $movie['yt_trailer'], 
                "actors" => "", "name" => $movie["mov_name"], 
                "name_o" => $movie["mov_name"], 
                "cover_big" => $movie["backdrop"], 
                "description" => utf8_encode($movie["description"]), 
                "age" => $movie["age"], 
                "rating_mpaa" => "", 
                "rating_count_kinopoisk" => 0, 
                "country" => "", 
                "backdrop_path" => ["0" => $movie["backdrop"]], 
                "audio" => $audio, 
                "video" => $video);
                
                $output["movie_data"] = Array("stream_id" => $movie["mov_id"], "name" => $movie["mov_name"], "added" => strtotime($movie["added"]), "category_id" => $movie["category"], "container_extension" => $movie["container"], "custom_sid" => "", "direct_source" => "");
			}
            //$output = [];

			//  }
            break;	
			
			case "get_short_epg" || "get_simple_data_table":
            if (isset($_REQUEST["stream_id"])) {
                $stream_id = intval($_REQUEST["stream_id"]);
                if ($action == "get_simple_data_table") {
                    $limit = 1000;
					} else {
                    if (isset($_REQUEST["limit"])) {
                        $limit = $_REQUEST["limit"];
						} else {
                        $limit = 4;
					}
				}
				
				
                $EPGs = $plf->GetEPGStreamPlayer($stream_id, $limit);
                $return = Array();
                $i = 0;
                foreach ($EPGs as $EPG) {
                    $i ++;
                    if ($action == "get_simple_data_table") {
                        if ($i == 1) {
                            $EPG["now_playing"] = 1;
							} else {
                            $EPG["now_playing"] = 0;
						}
                        $EPG["has_archive"] = 1;
					}
                    $EPG["title"] = base64_encode($EPG["name"]);
                    $EPG["description"] = base64_encode($EPG["descr"]);
                    $EPG["start_timestamp"] = strtotime($EPG["time"]);
                    $EPG["stop_timestamp"] = strtotime($EPG["time_to"]);
                    $EPG["start"] = date("Y-m-d H:i:s", strtotime($EPG["time"]));
                    $EPG["end"] = date("Y-m-d H:i:s", strtotime($EPG["time_to"]));
                    $return[] = $EPG;
				}
                
                echo json_encode(Array("epg_listings" => $return));
                exit();
			}
            else {
                echo json_encode(array());
                exit();
			}
            break;
			
			//	case "get_vod_categories" || "get_series_categories" || "get_vod_streams" || "get_series":
			
			//$output[] = Array("category_id" => "1", "category_name" => "all", "parent_id" => 0);
			//		$output = [];
            
			//      break;
			
			
			default:
			
			$output = $plf->getUserinfo($login,$userpass);
			
			
		}
		header('Content-type: application/json');
		echo json_encode($output);
		/*		}  else {
            header('Content-type: application/json');
			$userinfo = ["user_info"=>["auth"=> 0]];
			echo json_encode($userinfo);
		} */
	}
?>
