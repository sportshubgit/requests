<?php

/**
 * Created by PhpStorm.
 * User: tos
 * Date: 8/14/17
 * Time: 1:23 PM
 */
class plfunctions
{
	var $db;
	var $PLSALT = "p2kd0ub0ubkwxncdi";
	var $userlogin = "";
	var $userstatus = "1";
	var $userexpire = "0";
	var $userpass = "";
	var $tariff = "0";
	var $userid = "0";
	var $list = "";
	var $streamerid = "0";
	var $useridserver ="0";
	var $ip_adressuser ="";
  var $auth = 0;
  var $sportshub_mylist_movie_category_id = "999999991";
  var $sportshub_mylist_series_category_id = "999999992";
  var $sportshub_sync_db_path = __DIR__ . "/storage/sportshub_sync.db";
 
   
	/*var $mysql_pl_templ1 = "select itv.id,itv.number,itv.name,itv.cmd,itv.xmltv_id,itv.logo,tv_genre.title,itv.censored from itv left join tv_genre on tv_genre.id=itv.tv_genre_id where itv.id in (select service_id from service_in_package left join package_in_plan on service_in_package.package_id = package_in_plan.package_id where package_in_plan.plan_id = '";
	var $mysql_pl_templ2 = "' and type = 'tv') and itv.status='1' order by service_in_package.order_num;";*/
	
	var $mysql_pl_templ1 = "select itv.id,itv.number,itv.name,itv.cmd,itv.xmltv_id,itv.logo,tv_genre.title,itv.censored from itv left join tv_genre on tv_genre.id=itv.tv_genre_id inner join service_in_package on itv.id=service_in_package.service_id left join package_in_plan on service_in_package.package_id = package_in_plan.package_id where package_in_plan.plan_id ='";
	
	var $mysql_pl_templ2 = "'and type = 'tv' and itv.status=1 order by service_in_package.order_num";
  var $mysql_pl_templ2_clean = "'and type = 'tv' and itv.status=1 and itv.censored = 0 order by service_in_package.order_num";
  
	var $mac_data = "select user_apk.id,user_apk.list,user_apk.expire_date from user_apk";
	//selektovanje Vod iz Baze
	var $mysql_pl_vod1 = "select video.id,video.o_name,video.rtsp_url,video.category_id from video left join cat_genre on video.category_id=cat_genre.id group by  id";
	// retrieve sorted live categories without adult filter
	var $mysql_pl_lcsort_clean = "select id, title, number from tv_genre where censored = '0' order by number";
    // retrieve sorted live categories with adult filter
	var $mysql_pl_lcsort_dirty = "select id, title, number from tv_genre order by number";
	//retrieve best server for LB
	var $mysql_pl_lb = "select ip_adress from config_conn where enabled = 1 and maint_mode = 0 order by output ASC LIMIT 1";

    //var $mysql_epg_chan = "select distinct(xmltv_id), name from itv where xmltv_id <> '' ";
    var $mysql_epg_chan = "select distinct(name) from itv where xmltv_id <> ''";
    var $mysql_epg = 'select epg.time epg.time_to, epg.name, epg.descr, epg.category, itv.xmltv_id from epg join itv on epg.ch_id = itv.id where time between  now() - interval 3 hour and  now() + interval 2 day';
	//zavrsetak selektovanja;
	
	function __construct($db)
	{
		$this->db = $db;
	}
	//provera mac apk
	function getUserIDmac($output){
		$id = $this->db->select('user_apk', ['id','list','mac','status','time_insert', 'expire_date',], ['mac' => $output]);
		if (count($id) == 1) {
		$this->userid = $id[0]['id'];
		$this->userlogin = $id[0]['mac'];
		$this->list =$id[0]['list'];
		$this->userstatus = $id[0]['status'];
		
		$this->userexpire = strtotime($id[0]['expire_date']);
		return $id;
		}
	}

	function getStreamName($str) {	
		$strName =  $this->db->select('itv', ['id', 'cmd' ,], ['id' => $str]);
		return $strName[0]['cmd']; 
		  
	}
	//Pokupiti server 

 //	function getAllServer($id)   {
	// $id = $this->db->select('config_conn', ['id','ip_adress',], ['id' => $id]);
 //		if (count($id) == 1) {
 //		$this->serverid = $id[0]['id'];
 //		$this->ipadress = $id[0]['ip_adress'];
		
 //		return $id;
	
 //	}
	//zavrsavamo kupljenje
	function getThisMacexists($output) {
		$this->getUserIDmac($output);
		$arr = $this->db->query($this->userlogin)->fetchAll();
		return $arr;
	}
	
	function getThisMac() {
		$this->getUserIDmac($output);
		$arr = $this->db->query($this->userlogin)->fetchAll();
		return $arr;
	}
	//kraj provere

	function getUserID($login){
		$id = $this->db->select('users', ['id', 'login', 'password', 'tariff_plan_id','now_playing_streamer_id', 'status', 'expire_billing_date',], ['login' => $login]);
		if (count($id) == 1) {
		$this->userid = $id[0]['id'];
		$this->userlogin = $id[0]['login'];
		$this->userpass = $id[0]['password'];
		$this->userstatus = $id[0]['status'];
		$this->tariff = $id[0]['tariff_plan_id'];
		$this->streamerid =$id[0]['now_playing_streamer_id'];
		$this->userexpire = strtotime($id[0]['expire_billing_date']);
   			if (strtotime($id[0]['expire_billing_date']) < time()){
				$userStatusNew = "Expired";
			} else if ($this->userstatus == "1") {
				$userStatusNew = "Disabled";
			} else {
				$userStatusNew = "Active";
			}
			//$auth = "";
      
			$this->auth = 0;
			if ($userStatusNew == "Active"){
				$this->auth = 1;
			}	
		}
	}

	function getUserTarif(){
		return $this->tariff;
	}
	
/*	function getLiveStreams($login){
		$censor = $this->db->select('users', ['censor'], ['login' => $login]);
		$censor1 = $censor[0]['censor'];
		if ($censor1 == 1){   
			$arr = $this->db->select('itv',['id','number','name','cmd','xmltv_id','logo','censored','tv_genre_id']);
			} else {
				$arr = $this->db->select('itv',['id','number','name','cmd','xmltv_id','logo','censored','tv_genre_id'],['censored' => "0"]);
		}
		return $arr;
	}*/
	
  /* 	function getLiveStreamsByCat($category){
		$arr = $this->db->select('itv',['id','number','name','cmd','xmltv_id','logo','censored','tv_genre_id'],['tv_genre_id' => $category]);
		return $arr;
	} */
	
	function getLiveStreamsByCat1($category){
		$mysql_pl_lsbycat = "select itv.id, itv.number, itv.name, itv.xmltv_id, itv.logo, itv.censored, itv.tv_genre_id, itv.enable_tv_archive, itv.tv_archive_duration, itv.cat_sort, tv_genre.title from itv join tv_genre on itv.tv_genre_id = tv_genre.id where itv.tv_genre_id = ".$category." order by itv.cat_sort";
   $mysql_pl_lsbycat = "select itv.id, itv.number, itv.name, itv.xmltv_id, itv.logo, itv.censored, itv.tv_genre_id, itv.cat_sort, itv.cmd, tv_genre.title, itv.enable_tv_archive, itv.tv_archive_duration from itv join tv_genre on itv.tv_genre_id = tv_genre.id inner join service_in_package on itv.id=service_in_package.service_id left join package_in_plan on service_in_package.package_id = package_in_plan.package_id where itv.tv_genre_id = ".$category." and package_in_plan.plan_id = ".$this->tariff."  order by itv.cat_sort";
   
		$arr = $this->db->query($mysql_pl_lsbycat)->fetchAll();
		return $arr;
	} 
   
	function getUserinfo($login, $password = ""){
		$info = $this->db->select('users', ['id', 'login', 'password', 'tariff_plan_id','now_playing_streamer_id', 'status', 'expire_billing_date','connections_number','created'], ['login' => $login]);
		if (count($info) == 1) {
			$this->userid = $info[0]['id'];
			$this->userlogin = $info[0]['login'];
			$this->userpass = $info[0]['password'];
			$this->userstatus = $info[0]['status'];
			$this->tariff = $info[0]['tariff_plan_id'];
			$this->streamerid =$info[0]['now_playing_streamer_id'];
			$this->userexpire = strtotime($info[0]['expire_billing_date']);
			$this->maxconns =$info[0]['connections_number'];
			$this->created = strtotime($info[0]['created']);
			$token = md5($this->userlogin . $this->PLSALT . $this->userpass);;
			if (strtotime($info[0]['expire_billing_date']) < time()){
				$userStatusNew = "Expired";
			} else if ($this->userstatus == "1") {
				$userStatusNew = "Disabled";
			} else {
				$userStatusNew = "Active";
			}
			//$auth = "";
      $activeConns = $this->getActiveConns($login);
			$auth = 0;
			if ($this->userpass == $password && $userStatusNew == "Active"){
				$auth = 1;
			}
        if (strtotime($info[0]['expire_billing_date']) == 0){
				$userStatusNew = "Active";
            $auth = 1;
            $this->userexpire = '';
			}
			$output = array();
			$output["server_info"] = array("url" => $_SERVER['SERVER_NAME'], "port" => $_SERVER['SERVER_PORT'], "server_protocol" => "https", "timezone" => date_default_timezone_get(), "timestamp_now" => time(), "time_now" => date("Y-m-d H:i:s"), "rtmp_port" => "", "https_port" => "443");
        $output["user_info"]["username"] = $this->userlogin;
			//if ($auth == 1) {
				
				$output["user_info"]["password"] = $this->userpass;
            
		//	}
			$output["user_info"]["auth"] = $auth;
        $output["user_info"]["status"] = $userStatusNew;
         $output["user_info"]["exp_date"] = $this->userexpire;
			if ($auth == 1) {
				$output["user_info"]["message"] = "";
				
				
				$output["user_info"]["is_trial"] = 0;
				$output["user_info"]["active_cons"] = $activeConns;
				$output["user_info"]["created_at"] = $this->created;
				$output["user_info"]["max_connections"] = $this->maxconns;
				$output["user_info"]["allowed_output_formats"] = array("ts","m3u8");
			}
			return $output;
		}
	}
	
	function getUserinfoPanel($login){
		$info = $this->db->select('users', ['id', 'login', 'password', 'tariff_plan_id','now_playing_streamer_id', 'status', 'expire_billing_date','connections_number','created','censor'], ['login' => $login]);
		if (count($info) == 1) {
			$this->userid = $info[0]['id'];
			$this->userlogin = $info[0]['login'];
			$this->userpass = $info[0]['password'];
			$this->userstatus = $info[0]['status'];
			$this->tariff = $info[0]['tariff_plan_id'];
			$this->streamerid =$info[0]['now_playing_streamer_id'];
			$this->userexpire = strtotime($info[0]['expire_billing_date']);
			$this->maxconns =$info[0]['connections_number'];
			$this->created = strtotime($info[0]['created']);
			$this->censor = $info[0]['censor'];
			$token = md5($this->userlogin . $this->PLSALT . $this->userpass);;
			if (strtotime($info[0]['expire_billing_date']) < time()){
				$userStatusNew = "Expired";
			} else if ($this->userstatus == "1") {
				$userStatusNew = "Disabled";
			} else {
				$userStatusNew = "Active";
			}
			$auth = "";
			if ($userStatusNew == "Active"){
				$auth = 1;
			}
        if (strtotime($info[0]['expire_billing_date']) == 0){
				$userStatusNew = "Active";
            $auth = 1;
            $this->userexpire = '';
			}
			$af = array("ts","m3u8");
      $activeConns = $this->getActiveConns($login);
			$output = array();
			$output["server_info"] = array("url" => $_SERVER['SERVER_NAME'], "port" => $_SERVER['SERVER_PORT']);
			$output["user_info"]["username"] = $this->userlogin;
			$output["user_info"]["password"] = $this->userpass;
			$output["user_info"]["auth"] = $auth;
			$output["user_info"]["message"] = "";
			$output["user_info"]["status"] = $userStatusNew;
			$output["user_info"]["exp_date"] = $this->userexpire;
			$output["user_info"]["is_trial"] = 0;
			$output["user_info"]["active_cons"] = $activeConns;
			$output["user_info"]["created_at"] = $this->created;
			$output["user_info"]["max_connections"] = $this->maxconns;
			$output["user_info"]["allowed_output_formats"] = array("ts", "m3u8");
		
			$cats = $this->getLiveCategories($this->userlogin);
			$live_num = 0;
			$output["available_channels"] = array();
			foreach ($cats as $category){
				$catid = $category['category_id'];
				$streams = $this->getLiveStreamsByCat1($catid);
				foreach ($streams as $stream) {
					$live_num++;

					$imgID = $stream['name'];
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
       if (strpos($stream['title'],"XXX")> 0){
            $imgID = "XXX";
        }   
        
   //    if (strpos($stream['title'],"iFollow") === 0 && substr($imgID, 0, 3) == "EFL"){
        //    $imgID = "iFollow";
    //    }
					$stream_icon = "http://misc.allthehubbs.co.uk/SHUB/images/channels/" . $imgID . ".png";
					$output["available_channels"][$stream["id"]] = array("num" => $live_num, "name" => $stream["name"], "stream_type" => "live", "type_name" => "live", "stream_id" => $stream["id"], "stream_icon" => $stream_icon, "epg_channel_id" => $stream["name"], "added" => time(), "category_id" => $stream["tv_genre_id"], "category_name" => $stream["title"], "category_parent_id" => "", "series_no" => "", "direct_source" => 0, "direct_source_url" => "", "live" => "1", "container_extension" => "ts", "custom_sid" => $stream["number"]);
				}
			}
	 
   		return $output;	
			
		}
   
	}
	
	function checkVreme($output,$sat) {
		$this->getUserIDmac($output);
		if ($this->userexpire < $sat)
			return true;  
			   else return false;
	}

//provera mac u bazi za apk i status true
	function checkUserMac($output) {
		$this->getUserIDmac($output);
		if ($this->userstatus == "1")
			return true;  
			   else return false;
	}

  

	 //get server for user
	function getUserServ($strim) {
	   $arrs =  $this->db->select('config_conn', ['id', 'ip_adress' ,], ['id' => $strim]);
		if (count($arrs) == 1) {
			$this->useridserver = $arrs[0]['id'];
			$this->ip_adressuser = $arrs[0]['ip_adress'];
		}
	}
	
	function getUserServerid() {
		return $this->ip_adressuser;
	}
	//zavrava proveru userServer
	function checkUserPass($login, $pass) {
		$this->getUserID($login);
		if (($pass == $this->userpass) && ($this->userstatus == "0")) return true;
		else return false;
	}

	function checkUserServer($login, $pass) {
		$this->getUserID($login);
		if (($pass == $this->userpass) && ($this->streamerid == "0") ) return true;
		else return false;
	}
	
	function getChannelsArray($user){

    $censor = $this->getCensor($user);
    if ($censor){
		$arr = $this->db->query($this->mysql_pl_templ1 . $this->tariff . $this->mysql_pl_templ2)->fetchAll();
    } else {
   $arr = $this->db->query($this->mysql_pl_templ1 . $this->tariff . $this->mysql_pl_templ2_clean)->fetchAll();
    }
		return $arr;

	}

//array fetch VOD
	function getVodArray(){
		$arr = $this->db->query($this->mysql_pl_vod1)->fetchAll();
		return $arr;
	}
	//zavretak VOD

	function getToken() {
		$usertoken = md5($this->userlogin . $this->PLSALT . $this->userpass);
		//$token = base64_encode(":::".md5($this->userlogin . $this->login  ) . ":::" . $usertoken);
		//$token = base64_encode(":::".$this->userlogin  . ":::" . $this->userpass);
		return $usertoken;
	}
	
	function getStreamToken($login, $pass) {
		$usertoken = md5($login . $this->PLSALT . $pass);
		//$token = base64_encode(":::".md5($this->userlogin . $this->login  ) . ":::" . $usertoken);
		//$token = base64_encode(":::".$this->userlogin  . ":::" . $this->userpass);
		return $usertoken;
	}

	 function playlistRow($f) {
		$imgID = $f['name'];
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
	    //      if ($f['title'] == "24|7"){
       //     $imgID = "24-7";
    //    }
     if (strpos($f['name'],"24/7") === 0){
            $imgID = "24-7";
        }  
            if (strpos($f['name'],"Formula 1") === 0){
            $imgID = "F1 TV";
        }    
           if (strpos($f['name'],"XXX")> 0){
            $imgID = "XXX";
        }   
     
       //if (strpos($f['name'],"iFollow") === 0 && substr($imgID, 0, 3) == "EFL"){
        //    $imgID = "iFollow";
      //  }
  	return "#EXTINF:-1 tvg-id=\"". $f['name']."\"". " tvg-name=\"". $f['name']."\"". " group-title=\"". $f['title']."\""." tvg-logo=\"http://misc.allthehubbs.co.uk/SHUB/images/channels/". $imgID . ".png\",".$f['name'] . PHP_EOL;
	}

	function getPlaylist($type,$output){
 
		$user = $this->userlogin;
    $cats = $this->getLiveCategories($user);
    $i=0;
    foreach ($cats as $category){
            $catid = $category['category_id'];
            $streams = $this->getLiveStreamsByCat1($catid);
              foreach ($streams as $stream) {
                  $i ++;

                  $stream_icon = "http://misc.allthehubbs.co.uk/SHUB/images/channels/" .$stream["name"] . ".png";
                  $arr[] = Array("num" => $i, "name" => $stream["name"], "stream_type" => "live", "id" => $stream["id"], "stream_icon" => $stream_icon, "xmltv_id" => $stream["name"], "added" => time(), "title" => $stream["title"], "tv_archive" => 0, "direct_source" => "", "tv_archive_duration" => 0, "cmd" => $stream['cmd']);
                  }
                  }
                  
		//
    
    $user = $this->userlogin;
    //$arr = $this->getChannelsArray($user);
		$arrVod = $this->getVodArray();
    //$shvod = $this->getVODStreams();    
		if($type == 'm3u' || $type == 'm3u_plus'){
			
			$res = "#EXTM3U\n";
			//m3u_plus&output=ts
			if($output=='ts' || $output=='mpegts'){
				
				$stream_format = '/mpegts';
				
				foreach ($arr as $f) {
					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					array_pop($tokens); // used in a void context to throw the end away
					$stream = implode('/', $tokens);
					$res .= $this->playlistRow($f);
					//$res .= $stream . $stream_format  . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
               $res .= "http://sh.brb.ac/live/".$this->userlogin . "/" . $this->userpass ."/".$f['id']. PHP_EOL;
				}
		/*	foreach ($shvod as $vod){
					$res .= "#EXTINF:-1 tvg-id=\"\"". " tvg-name=\"". $vod['mov_name']."\"". " group-title=\"Movie: A-C\""." tvg-logo=\"".$vod['poster'] . "\",".$vod['mov_name']  . PHP_EOL;
					$res .= "http://someurl" . PHP_EOL;
					
				}*/
			}

//pocetak vod lista
			elseif($output=='Vod' || $output=='sss') {

					$stream_format = '/index.m3u8';
				
				
				foreach ($arrVod as $f) {
					
					$stream = $f['rtsp_url'];
					$tokens = explode('/', $stream);
					array_pop($tokens); // used in a void context to throw the end away
					$stream = implode('/', $tokens);
					
					$res .= $this->playlistRow($f);
					$res .= $stream . $stream_format  . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
				}
			
			}

			//kraj vod lista

			elseif($output == 'm3u8' || $output =='hls'){
				
				$stream_format = '/index.m3u8';
				
				foreach ($arr as $f) {
					
					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					array_pop($tokens); // used in a void context to throw the end away
					$stream = implode('/', $tokens);
					
					$res .= $this->playlistRow($f);
					//$res .= $stream . $stream_format . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
                 $res .= "http://sh.brb.ac/live/".$this->userlogin . "/" . $this->userpass ."/".$f['id']. PHP_EOL;
				}
				/*foreach ($shvod as $vod){
					$res .= "#EXTINF:-1 tvg-id=\"\"". " tvg-name=\"". $vod['mov_name']."\"". " group-title=\"Movie: A-C\""." tvg-logo=\"".$vod['poster'] . "\",".$vod['mov_name']  . PHP_EOL;
					$res .= "http://someurl" . PHP_EOL;
					
				}*/
			}else {
				
				die("Error : Unknown Stream output");
				
			}
			
		}elseif($type == 'enigma16'){
			
			$res = "#NAME My_Bouquet_Name\n";
			
			if($output=='ts'){
				$stream_format = '/mpegts';
				foreach ($arr as $f) {
					$selektserver = $this->getUserServ($this->streamerid);
					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					$treba = $tokens[3];
					$stream = implode('%', $tokens);
					
					$res .= "#SERVICE 4097:0:1:0:0:0:0:0:0:0:". $this->ip_adressuser."/". $treba . $stream_format . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['name'] . PHP_EOL; //$f['number']." ". $f['name]." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}
//Vod za Enigma 
	
		else if($output=='Vod'){
				
				foreach ($arrVod as $f) {
					
					
					$res .= "#SERVICE 4097:0:1:0:0:0:0:0:0:0:". $f['rtsp_url'] . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['o_name'] . PHP_EOL; //$f['number']." ". $f['name']." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}

			// kraj Vod za Enigma


			else {
				
				die("Error : Unknown Stream output");
				
			}
			
			
			
			
		}elseif($type == 'dreambox'){
			
			$res = "#NAME My_Bouquet_Name\n";
			
			if($output=='ts'){
				
				foreach ($arr as $f) {
					
					
					$stream1 = $f['cmd'];
					$stream1 = str_replace(":", "%3A", $stream1);
					
					$res .= "#SERVICE 1:0:1:0:0:0:0:0:0:0:". $stream1 . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['name'] . PHP_EOL; //$f['number']." ". $f['name']." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}
			// Pocetak Vod za Drembox lista
			else if($output=='Vod'){
				//$stream_format = '/index.m3u8';
				
				foreach ($arrVod as $f) {
					//$selektserver = $this->getUserServ($this->streamerid);
					$stream = $f['rtsp_url'];
					// $tokens = explode('/', $stream);
					// $treba = $tokens[3];
					
					//$stream1 = $this->ip_adressuser;
					$stream = str_replace(":", "%3A", $stream);
					
					$res .= "#SERVICE 1:0:1:0:0:0:0:0:0:0:". $stream . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['o_name'] . PHP_EOL; //$f['number']." ". $f['name']." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}


			//kraj liste Drembox Vod



			else {
				
				die("Error : Unknown Stream output");
				
			}
			
			
			
			
		}elseif ($userpass = $_GET['password']){
			$res = "#EXTM3U\n";
			$stream_format = '/mpegts';
			foreach ($arr as $f) {
					
					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					array_pop($tokens); // used in a void context to throw the end away
					$stream = implode('/', $tokens);
					
					$res .= $this->playlistRow($f);
					//$res .= $stream . $stream_format  . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
               $res .= "http://sh.brb.ac/live/".$this->userlogin . "/" . $this->userpass ."/".$f['id']. PHP_EOL;
				}




		}else {
				
				die("Error : Unknown List Type");
				
			}
		
		return $res;
	}
   

  
// pocinje GetPlayList za Balance server

	function getPlaylistBalance($type,$output){
//		$user = $this->userlogin;
		//$arr = $this->getChannelsArray($user);
//		$arrVod = $this->getVodArray();
	   
			$user = $this->userlogin;
    $cats = $this->getLiveCategories($user);
    $i=0;
    foreach ($cats as $category){
            $catid = $category['category_id'];
            $streams = $this->getLiveStreamsByCat1($catid);
              foreach ($streams as $stream) {
                  $i ++;
        if (strpos($stream['name'],"24/7") === 0){
            $imgID = "24-7";
        }        
        else {
        $imgID = $stream['name'];
        }
                  $stream_icon = "http://misc.allthehubbs.co.uk/SHUB/images/channels/" .$imgID . ".png";
                  $arr[] = Array("num" => $i, "name" => $stream["name"], "stream_type" => "live", "id" => $stream["id"], "stream_icon" => $stream_icon, "xmltv_id" => $stream["name"], "added" => time(), "title" => $stream["title"], "tv_archive" => 0, "direct_source" => "", "tv_archive_duration" => 0, "cmd" => $stream['cmd']);
                  }
                  }
                  
		//
    
    $user = $this->userlogin;
    //$arr = $this->getChannelsArray($user);
		$arrVod = $this->getVodArray();
	  //$shvod = $this->getVODStreams();  
		if($type == 'm3u'){
			
			$res = "#EXTM3U\n";
			if($output=='ts' || $output=='mpegts'){
				
					$stream_format = '/mpegts';
				 
				//$stream= $this->ip_adressuser;
					
				//$stream = 'ova_adresa/mpgs';//

				foreach ($arr as $f) {
					$selektserver = $this->getUserServ($this->streamerid);
						$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					$treba = $tokens[3];
					//array_shift($tokens[1]); // used in a void context to throw the end away
					//$stream = implode($tokens[4],$tokens);
					//$zamena = $this->ip_adressuser;
					 //$f['cmd'];
					// $tokens = explode('/', $stream);
					// array_pop($tokens); // used in a void context to throw the end away
					// $stream = implode('/', $tokens);
					// $menjam = str_replace("http://", $zamena, $stream);
					$res .= $this->playlistRow($f);
					//$res .= $this->ip_adressuser."/".$treba . $stream_format  . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
                 $res .= "http://sh.brb.ac/live/".$this->userlogin . "/" . $this->userpass ."/".$f['id']. PHP_EOL;
				}
					
			
			}

//pocetak vod lista
			elseif($output=='Vod' || $output=='sss') {

					$stream_format = '/index.m3u8';
				
				
				foreach ($arrVod as $f) {
					
					$stream = $f['rtsp_url'];
					$tokens = explode('/', $stream);
					array_pop($tokens); // used in a void context to throw the end away
					$stream = implode('/', $tokens);
					
					$res .= $this->playlistRow($f);
					$res .= $stream . $stream_format  . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
				}
			
			}

			//kraj vod lista

			elseif($output == 'm3u8' || $output =='hls'){
				
				$stream_format = '/index.m3u8';
				
				foreach ($arr as $f) {
					$selektserver = $this->getUserServ($this->streamerid);
					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					$treba = $tokens[3];
					// $stream = $f['cmd'];
					// $tokens = explode('/', $stream);
					// array_pop($tokens); // used in a void context to throw the end away
					// $stream = implode('/', $tokens);
					
					$res .= $this->playlistRow($f);
					//$res .= $this->ip_adressuser ."/".$treba . $stream_format . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
                 $res .= "http://sh.brb.ac/live/".$this->userlogin . "/" . $this->userpass ."/".$f['id']. PHP_EOL;
				}
		/*	  foreach ($shvod as $vod){
					$res .= "#EXTINF:-1 tvg-id=\"". $vod['mov_id']."\"". " tvg-name=\"". $vod['mov_name']."\"". " group-title=\"". $vod['category']."\""." tvg-logo=\"".$vod['poster'] . "\"," . PHP_EOL;
					$res .= "http://someurl" . PHP_EOL;
					
				}*/
			}else {
				
				die("Error : Unknown Stream output");
				
			}
			
		}elseif($type == 'enigma16'){
			
			$res = "#NAME INTERNET_TV_NTVOTT\n";
			
			if($output=='ts'){
				$stream_format = '/mpegts';
				foreach ($arr as $f) {
					$selektserver = $this->getUserServ($this->streamerid);
					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					$treba = $tokens[3];
					
					$res .= "#SERVICE 4097:0:1:0:0:0:0:0:0:0:". $this->ip_adressuser."/". $treba . $stream_format . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['name'] . PHP_EOL; //$f['number']." ". $f['name']." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}
//Vod za Enigma 
	
		else if($output=='Vod'){
				
				foreach ($arrVod as $f) {
					
					
					$res .= "#SERVICE 4097:0:1:0:0:0:0:0:0:0:". $f['rtsp_url'] . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['o_name'] . PHP_EOL; //$f['number']." ". $f['name']." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}

			// kraj Vod za Enigma
			else {
				
				die("Error : Unknown Stream output");
				
			}
			
			
			
			
		}elseif($type == 'dreambox'){
			
			$res = "#NAME INTERNET_TV_NTVOTT\n";
			
			if($output=='ts'){
				$stream_format = '/mpegts';
				
				foreach ($arr as $f) {
					$selektserver = $this->getUserServ($this->streamerid);
					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					$treba = $tokens[3];
					
					$stream1 = $this->ip_adressuser;
					$stream1 = str_replace(":", "%3A", $stream1);
					
					$res .= "#SERVICE 1:0:1:0:0:0:0:0:0:0:". $stream1 . "/". $treba . $stream_format . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['name'] . PHP_EOL; //$f['number']." ". $f['name]." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}
// Pocetak Vod za Drembox lista
			else if($output=='Vod'){
				//$stream_format = '/index.m3u8';
				
				foreach ($arrVod as $f) {
					//$selektserver = $this->getUserServ($this->streamerid);
					$stream = $f['rtsp_url'];
					// $tokens = explode('/', $stream);
					// $treba = $tokens[3];
					
					//$stream1 = $this->ip_adressuser;
					$stream = str_replace(":", "%3A", $stream);
					
					$res .= "#SERVICE 1:0:1:0:0:0:0:0:0:0:". $stream . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
					$res .= "#DESCRIPTION ". $f['o_name'] . PHP_EOL; //$f['number']." ". $f['name']." ". $f['title']." ". $f['logo'].",".$f['name'] . PHP_EOL;
					
				}
				
				
			}


			//kraj liste Drembox Vod
			else {
				
				die("Error : Unknown Stream output");
				
			}
			
			
			
			
		}elseif ($userpass = $_GET['password']){
			$res = "#EXTM3U\n";
			$stream_format = '/mpegts';
			foreach ($arr as $f) {
					$selektserver = $this->getUserServ($this->streamerid);

					$stream = $f['cmd'];
					$tokens = explode('/', $stream);
					$treba = $tokens[3];
					// $stream = $f['cmd'];
					// $tokens = explode('/', $stream);
					// array_pop($tokens); // used in a void context to throw the end away
					// $stream = implode('/', $tokens);
					
					$res .= $this->playlistRow($f);
					//$res .= $this->ip_adressuser . "/". $treba . $stream_format  . "?username=" .$this->userlogin . "&password=" . $this->userpass ."&token=".$this->getToken()."&ch_id=".$f['id']. PHP_EOL;
                 $res .= "http://sh.brb.ac/live/".$this->userlogin . "/" . $this->userpass ."/".$f['id']. PHP_EOL;
				}




		}else {
				
				die("Error : Unknown List Type");
				
			}
		
		return $res;
	}
   
	function getLiveCategories($user){
		$censor = $this->db->select('users', ['censor'], ['login' => $user]);
		$censor1 = $censor[0]['censor'];
		if ($censor1 == 1){   
			$cats = $this->db->query($this->mysql_pl_lcsort_dirty)->fetchAll();
		} else {
			$cats = $this->db->query($this->mysql_pl_lcsort_clean)->fetchAll();
		}
	 	foreach ($cats as $category) {
			$output[] = Array("category_id" => $category["id"], "category_name" => $category["title"], "parent_id" => 0);
		}
		return $output;
	}
	
	function getCensor($user){
		$censor = $this->db->select('users', ['censor'], ['login' => $user]);
		return $censor;
	}
	
	function getWebPlayerinfo($user,$pass,$stream){
		$token = $this->getStreamToken($user,$pass);
		$strName = $this->getStreamName($stream);
		$strNameEx = explode("/",$strName);
		$output = Array("token" => $token, "stream" => $strNameEx[3]);
		return $output;
	}
  

  function getActiveConns($user){
    $servers = $this->db->select('config_conn', ['ip_adress', 'user', 'pass'],['enabled' => 1]);
    $count = 0;
    foreach ($servers as $server1){
    $panel_url = 'http://'.$server1['user'].':'.$server1['pass'].'@'.str_replace('http://', '', $server1['ip_adress']).'/flussonic/api/sessions';
    $opts = array( 'http' => array(
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded' ) );
    $context = stream_context_create( $opts );
    $data = file_get_contents( $panel_url, false, $context );
    $json = json_decode($data, true);
    $online = $json[ 'sessions' ];
      foreach ($online as $live) {
        if ( isset($live[ 'user_agent' ]) & isset($live[ 'user_id' ])){
          if ($live['user_id'] == $user){ 
            $count++;
          }
        }
    }
}
	  return $count;
  } 
  
  
  function GetEPGStream($stream_id){

			$epgdata = $this->db->select('epg', ['ch_id','time','time_to','name','descr' ],['ch_id' => $stream_id]);
	
		return $epgdata;
  }
  
  function getSubInfo($user){
    $subinfo = $this->db->select('users', ['login', 'reseller_id', 'censor', 'status', 'expire_billing_date','connections_number', 'refer_trial', 'referrer'], ['login' => $user]);
    if (count($subinfo) == 1){
      $activeConns = $this->getActiveConns($user);
  	  $output = array();
		  $output["result"] = 1;
		  $output['user_info']['member_id'] = $subinfo[0]['reseller_id'];
      $output['user_info']['censor'] = $subinfo[0]['censor'];
      $output['user_info']['max_connections'] = $subinfo[0]['connections_number'];
      $output['user_info']['active_cons'] = $activeConns;
      $output['user_info']['exp_date'] = strtotime($subinfo[0]['expire_billing_date']);
      $output['user_info']['refer_trial'] = $subinfo[0]['refer_trial'];
      $output['user_info']['referrer'] = $subinfo[0]['referrer'];
    } else {
        $output["result"] = 0;
      }
      return $output;
  }
  
  function adultContent($user,$enable){
  if ($enable){
    $response = $this->db->update('users', ['censor' => 1], ['login' => $user]);
    return $response;
  } else {
    $response = $this->db->update('users', ['censor' => 0], ['login' => $user]);
    return $response;
  }
  }
  
  function getResellerCredits(){
    $resellers = $this->db->select('administrators', ['id', 'login', 'credit']);
    $output = array();
    foreach($resellers as $reseller){
      $output[] = Array("username" => $reseller['login'], "credits" => $reseller['credit']);
    }
     return $output;
  
  }
  
  function setMaxConns($user,$maxconns){
    $this->db->update('users', ['connections_number' => $maxconns], ['login' => $user]);
    $response = $this->db->select('users', ['connections_number'], ['login' => $user]);
    if ($response[0]['connections_number'] == $maxconns){
     $result = 1;
    } else { 
      $result = 0;
    }
    return $result;
  }
  
  function setExpDate($user,$exp_date){
    $expdate1 = date("Y-m-d H:i:s", $exp_date);
    $this->db->update('users', ['expire_billing_date' => $expdate1, 'status' => "0"], ['login' => $user]);
    $response = $this->db->select('users', ['expire_billing_date'], ['login' => $user]);
    if ($response[0]['expire_billing_date'] == $expdate1){
      $result = 1;
    } else { 
      $result = 0;
    }
    return $result;
  }
  
  function adjustCredits($id,$amount){
    $reseller = $this->db->select('administrators', ['id','login', 'credit'], ['id' => $id]);
    $credits = $reseller[0]['credit'];
    $newcredit = $credits + $amount;
    $this->db->update('administrators', ['credit' => $newcredit], ['id' => $id]);
    $verify = $this->db->select('administrators', ['id', 'credit'], ['id' => $id]);
    if ($verify[0]['credit'] == $newcredit){
      $result = 1;
    } else {
      $result = 0;
    }
    
     if ($amount > 0){
       $this->db->insert('log_credit', ['origin' => 'SH Portal', 'reseller' => $reseller[0]['login'], 'admin' =>  'Portal API', 'credits'=> $amount]);
       }
    return $result;
  }
	
	function GetEPGStreamPlayer($stream_id, $limit = 4)
{
    
    $chQuery = $this->db->select('itv', ['id','tv_archive_duration'], ['cmd' => $stream_id]);
    if (0 < count($chQuery)) {
        $arcDur = 86400 * $chQuery[0]['tv_archive_duration'];
        $arcdur = time() - $arcDur;
        $data = $chQuery[0];
date_default_timezone_set('Europe/London');
$tn = date("Y-m-d H:i:s.0", time());
        
                $query = 'select id, ch_id, time, time_to, duration, name, descr from epg where ch_id = '.$stream_id.' and unix_timestamp(time) >= '.$arcdur.' and unix_timestamp(time) <= '.time().' ORDER by time ASC LIMIT '.$limit;
                
                $dataload = $this->db->query($query)->fetchAll();
                return $dataload;

        
    }
    //return array();
}

	function updateServerOutput(){
     $mm = $this->db->select('server_config', ['value'], ['name' => 'maint_mode']);
     $maintmode = $mm[0]['value'];
    $servers = $this->db->select('config_conn', ['ip_adress', 'user', 'pass','output_multiplier','out_count','maint_mode']);

		foreach ($servers as $server1){
        $panel_url = 'http://'.$server1['user'].':'.$server1['pass'].'@'.str_replace('http://','', $server1['ip_adress']).'/flussonic/api/server';
        $opts = array('http' => array('method' =>'POST','header' => 'Content-type: application/x-www-form-urlencoded'));
        $context = stream_context_create($opts );
        $data = file_get_contents($panel_url,false, $context);
        $json = json_decode($data,true);
        $output = $json['output_kbit'];
        $output = $output * $server1['output_multiplier'];
        
        $out_count = $server1['out_count'];
        $handle = curl_init($panel_url);
        curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);  
        $response = curl_exec($handle);

      
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if($httpCode == 200) {
            if ($maintmode == 0 && $server['maint_ mode'] == 0){
               $this->db->update('config_conn',['output' => $output,'out_count' => 0, 'last_http_resp' => $httpCode,'enabled' => 1 ], ['ip_adress' => $server1['ip_adress']]);
               } else {
               $this->db->update('config_conn',['output' => $output,'out_count' => 0, 'last_http_resp' => $httpCode ], ['ip_adress' => $server1['ip_adress']]);
               }
        } else {
          if ($out_count >= 3){
           $this->db->update('config_conn',['enabled' => 0, 'output' => 0, 'last_http_resp' => $httpCode, 'out_count' => $out_count], ['ip_adress' => $server1['ip_adress']]);
           } else {
           $out_count++;
           $this->db->update('config_conn',['out_count' => $out_count, 'last_http_resp' => $httpCode], ['ip_adress' => $server1['ip_adress']]);
           }
        }

/*
        if (!empty($output)){
            $this->db->update('config_conn',['output' => $output], ['ip_adress' => $server1['ip_adress']]);
        }
        */
}
} 
	function selectServerLB(){
		$server = $this->db->query($this->mysql_pl_lb)->fetchAll();
		return $server[0]['ip_adress'];
	}

    function getEPGChannels(){
		$epgc = $this->db->query($this->mysql_epg_chan)->fetchAll();
		return $epgc;
	}

    function getEPG(){
    $tf = time()  - 10800;
        $tt = time() + 172800;
        $sql_epg = 'select itv.name as name1, epg.id, epg.time, epg.time_to, epg.name, epg.descr, epg.category from itv join epg on itv.id = epg.ch_id where unix_timestamp(time) >='.$tf.' and unix_timestamp(time) <= '.$tt;
		$epg = $this->db->query($sql_epg)->fetchAll();
//$epg = $this->db->select("epg", ["[>]itv" => ["ch_id" => "id"]], ["epg.id","epg.time","epg.time_to","epg.name","epg.descr","epc.category","itv.xmltv_id"], ["epg.time[<>]" => ['.$tf.','.$tt.']]);
		return $epg;
	}
	
	function getVODCategories(){
 $sql = 'select distinct(vod_mov.category), media_category.category_name, media_category.num, media_category.id from vod_mov join media_category on vod_mov.category = media_category.id';
		//$cats = $this->db->select('media_category', ['id', 'category_name', 'num']);
   $cats = $this->db->query($sql)->fetchAll();
		return $cats;
	}
 
 	function getVODCategoriesSH(){

 $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
   $filtersql = "select * from category  where type = 'movies' order by num ASC";
   
   $cats = mysqli_query($shdb,$filtersql);
   
		return $cats;
	}
 
  function getPVODCategoriesSH(){

 $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
   $filtersql = "select id , category_name from category where type = 'movie' order by category_name ASC";
   
   $cats = mysqli_query($shdb,$filtersql);
   
		return $cats;
	}

	function getSeriesCategories(){
     $sql = 'select distinct(vod_tvseries.category), media_category.category_name, media_category.num, media_category.id from vod_tvseries join media_category on vod_tvseries.category = media_category.id';
		//$cats = $this->db->select('media_category', ['id', 'category_name', 'num']);
    $cats = $this->db->query($sql)->fetchAll();
		return $cats;
	}
	
	function getSeriesCategoriesSH(){
		$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
     $filtersql = 'select distinct(category), genre from series order by genre asc';
   
     $streams = mysqli_query($shdb,$filtersql);
     
		return $streams;
	}
 
  function getPSeriesCategoriesSH(){
		$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
     $filtersql = "select id as category, category_name as genre from category where type = 'tv' order by category_name asc";
   
     $streams = mysqli_query($shdb,$filtersql);
     
		return $streams;
	}
	
	function getVODStreams(){
		//$streams = $this->db->select('video', ['id','name','description','rate','category_id','added','pic','director','actors']);
		$streams = $this->db->select('vod_mov', ['mov_id','mov_name','category','poster']);
		return $streams;
	}

	function getVODStreamsSH(){
     $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
     $filtersql = 'select * from movies';
   
     $streams = mysqli_query($shdb,$filtersql);
     
		return $streams;
	}

	function normalizeVODText($value){
		$value = trim((string)$value);
		if ($value === ''){
			return '';
		}
		$value = strtolower($value);
		$value = preg_replace('/[^a-z0-9]+/', ' ', $value);
		return trim(preg_replace('/\s+/', ' ', $value));
	}

	function getVODSourcePriority($stream){
		if (!isset($stream['p_server'])){
			return 0;
		}
		$server = strtolower((string)$stream['p_server']);
		if (strpos($server, 'combined') !== false){
			return 3;
		}
		if (strpos($server, 'cinema') !== false){
			return 1;
		}
		return 2;
	}

	function getVODIdentityKey($stream){
		if (isset($stream['imdb_id']) && trim($stream['imdb_id']) !== ''){
			return 'imdb:' . strtolower(trim($stream['imdb_id']));
		}
		if (isset($stream['tmdbid']) && trim($stream['tmdbid']) !== ''){
			return 'tmdb:' . trim($stream['tmdbid']);
		}
		if (isset($stream['tvdb_id']) && trim($stream['tvdb_id']) !== ''){
			return 'tvdb:' . trim($stream['tvdb_id']);
		}
		$title = '';
		if (isset($stream['mov_name'])){
			$title = $this->normalizeVODText($stream['mov_name']);
		}
		$year = '';
		if (isset($stream['year'])){
			$year = preg_replace('/[^0-9]/', '', (string)$stream['year']);
		}
		if ($title !== ''){
			return 'title:' . $title . ':' . $year;
		}
		if (isset($stream['mov_id']) && trim($stream['mov_id']) !== ''){
			return 'mov:' . trim($stream['mov_id']);
		}
		return '';
	}

	function shouldReplaceVODStream($existing, $candidate, $requestedCategory = ''){
		$existingSource = $this->getVODSourcePriority($existing);
		$candidateSource = $this->getVODSourcePriority($candidate);
		if ($candidateSource !== $existingSource){
			return $candidateSource > $existingSource;
		}

		if ($requestedCategory !== ''){
			$requestedCategory = (string)$requestedCategory;
			$existingCat = isset($existing['category']) ? (string)$existing['category'] : '';
			$candidateCat = isset($candidate['category']) ? (string)$candidate['category'] : '';
			$existingMatchesRequest = ($existingCat === $requestedCategory);
			$candidateMatchesRequest = ($candidateCat === $requestedCategory);
			if ($existingMatchesRequest !== $candidateMatchesRequest){
				return $candidateMatchesRequest;
			}
		}

		$existingAdded = isset($existing['added']) ? strtotime($existing['added']) : 0;
		$candidateAdded = isset($candidate['added']) ? strtotime($candidate['added']) : 0;
		if ($candidateAdded !== $existingAdded){
			return $candidateAdded > $existingAdded;
		}

		return false;
	}

	function dedupeVODStreams($streams, $requestedCategory = ''){
		$bestByIdentity = array();
		foreach ($streams as $stream){
			$key = $this->getVODIdentityKey($stream);
			$categoryBucket = isset($stream['category']) ? (string)$stream['category'] : '';
			if ($categoryBucket !== ''){
				$key .= '|cat:' . $categoryBucket;
			}
			if ($key === ''){
				$key = 'fallback:' . md5(json_encode($stream));
			}
			if (!isset($bestByIdentity[$key]) || $this->shouldReplaceVODStream($bestByIdentity[$key], $stream, $requestedCategory)){
				$bestByIdentity[$key] = $stream;
			}
		}
		return array_values($bestByIdentity);
	}

	function getSeriesIdentityKey($stream){
		if (isset($stream['imdbid']) && trim($stream['imdbid']) !== ''){
			return 'imdb:' . strtolower(trim($stream['imdbid']));
		}
		if (isset($stream['tmdbid']) && trim($stream['tmdbid']) !== ''){
			return 'tmdb:' . trim($stream['tmdbid']);
		}
		if (isset($stream['tvdbid']) && trim($stream['tvdbid']) !== ''){
			return 'tvdb:' . trim($stream['tvdbid']);
		}
		$title = '';
		if (isset($stream['ser_name'])){
			$title = $this->normalizeVODText($stream['ser_name']);
		}
		$year = '';
		if (isset($stream['releasedate'])){
			preg_match('/([0-9]{4})/', (string)$stream['releasedate'], $matches);
			if (isset($matches[1])){
				$year = $matches[1];
			}
		}
		if ($title !== ''){
			return 'title:' . $title . ':' . $year;
		}
		if (isset($stream['ser_id']) && trim($stream['ser_id']) !== ''){
			return 'ser:' . trim($stream['ser_id']);
		}
		return '';
	}

	function shouldReplaceSeriesStream($existing, $candidate, $requestedCategory = ''){
		$existingSource = $this->getVODSourcePriority($existing);
		$candidateSource = $this->getVODSourcePriority($candidate);
		if ($candidateSource !== $existingSource){
			return $candidateSource > $existingSource;
		}

		if ($requestedCategory !== ''){
			$requestedCategory = (string)$requestedCategory;
			$existingCat = isset($existing['category']) ? (string)$existing['category'] : '';
			$candidateCat = isset($candidate['category']) ? (string)$candidate['category'] : '';
			$existingMatchesRequest = ($existingCat === $requestedCategory);
			$candidateMatchesRequest = ($candidateCat === $requestedCategory);
			if ($existingMatchesRequest !== $candidateMatchesRequest){
				return $candidateMatchesRequest;
			}
		}

		$existingAdded = isset($existing['added']) ? strtotime($existing['added']) : 0;
		$candidateAdded = isset($candidate['added']) ? strtotime($candidate['added']) : 0;
		if ($candidateAdded !== $existingAdded){
			return $candidateAdded > $existingAdded;
		}

		return false;
	}

	function dedupeSeriesStreams($streams, $requestedCategory = ''){
		$bestByIdentity = array();
		foreach ($streams as $stream){
			$key = $this->getSeriesIdentityKey($stream);
			$categoryBucket = isset($stream['category']) ? (string)$stream['category'] : '';
			if ($categoryBucket !== ''){
				$key .= '|cat:' . $categoryBucket;
			}
			if ($key === ''){
				$key = 'fallback:' . md5(json_encode($stream));
			}
			if (!isset($bestByIdentity[$key]) || $this->shouldReplaceSeriesStream($bestByIdentity[$key], $stream, $requestedCategory)){
				$bestByIdentity[$key] = $stream;
			}
		}
		return array_values($bestByIdentity);
	}
  
  function getPVODStreamsSH($catid){
     $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
     if($catid <> ''){
       $catid = (string)$catid;
       if ($catid === '999999993'){
         $filtersql = "SELECT * FROM (
                         select mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies`
                         UNION
                         SELECT mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,'999999993' as category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies` where nf_topX = 1
                         UNION
                         SELECT mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,'999999994' as category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies` where mwtw = 1
                       ) all_movies WHERE category = '999999993'";
       } else if ($catid === '999999994'){
         $filtersql = "SELECT * FROM (
                         select mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies`
                         UNION
                         SELECT mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,'999999993' as category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies` where nf_topX = 1
                         UNION
                         SELECT mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,'999999994' as category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies` where mwtw = 1
                       ) all_movies WHERE category = '999999994'";
       } else {
         $filtersql = "select * from movies where category = $catid";
       }
     } else {
     // 1st Query = All, 3rd = Top Trending, 3rd = Most watched this week
       $filtersql = "select mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies`
                     UNION
                     SELECT mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,'999999993' as category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies` where nf_topX = 1
                     UNION
                     SELECT mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,'999999994' as category,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM `movies` where mwtw = 1";
     }
   
     $streams = mysqli_query($shdb,$filtersql);
     
		return $streams;
	}
  	
	function getSeries(){
		//$streams = $this->db->select('video', ['id','name','description','rate','category_id','added','pic','director','actors']);
		$streams = $this->db->select('vod_tvseries', ['seriesid','seriesname','category','poster']);
		return $streams;
	}
 
 	function getSeriesSH(){
		$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
     $filtersql = 'select * from series';
  
     $streams = mysqli_query($shdb,$filtersql);
     
		return $streams;
	}
 
  function getPSeriesSH($catid){
		$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");

     if($catid <> ''){
       $catid = (string)$catid;
       if ($catid === '999999993'){
         $filtersql = "SELECT * FROM (
                         select ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series
                         UNION
                         SELECT ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,999999993 as category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series where nf_topX = 1
                         UNION
                         SELECT ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,999999994 as category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series where mwtw = 1
                       ) all_series WHERE category = '999999993'";
       } else if ($catid === '999999994'){
         $filtersql = "SELECT * FROM (
                         select ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series
                         UNION
                         SELECT ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,999999993 as category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series where nf_topX = 1
                         UNION
                         SELECT ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,999999994 as category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series where mwtw = 1
                       ) all_series WHERE category = '999999994'";
       } else {
         $filtersql = "select * from series where category = $catid";
       }
     } else {
       // 1st Query = All, 3rd = Top Trending, 3rd = Most watched this week
       $filtersql = "select ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series
                     UNION
                     SELECT ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,999999993 as category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series where nf_topX = 1
                     UNION
                     SELECT ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,999999994 as category,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer from series where mwtw = 1";
       ;
     }
     $streams = mysqli_query($shdb,$filtersql);
     
		return $streams;
	}

	function getSeriesInfo($sid){
   if ($sid != ''){
		$streams = $this->db->select('vod_tveps', ['ep_id', 'seriesid', 'season', 'relative_path', 'ep_number'],['seriesid' => $sid]);
   } else {
   $streams = $this->db->select('vod_tveps', ['ep_id', 'seriesid', 'season', 'relative_path', 'ep_number']);
   }
		return $streams;
	}
 
 	function getSeriesInfoSH($sid){
  $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
   if ($sid != ''){
   $filtersql = 'select * from episodes where ser_id = '.$sid.' order by season, episode';
   $streams = mysqli_query($shdb,$filtersql);
   } else {
  $filtersql = 'select * from episodes';
   $streams = mysqli_query($shdb,$filtersql);
   }
		return $streams;
	}
 
  function getPSeriesInfoSH($sid){
  $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
   if ($sid != ''){
   $filtersql = 'select * from episodes where ser_id = '.$sid.' order by season, episode';
   $streams = mysqli_query($shdb,$filtersql);
   } else {
  $filtersql = 'select * from episodes';
   $streams = mysqli_query($shdb,$filtersql);
   }
		return $streams;
	}
	
	function getSeriesData($sid){
    if ($sid != ''){
		$sql = 'SELECT vod_tveps.season, vod_tvseries.category, vod_tvseries.seriesname, vod_tvseries.poster, vod_tveps.seriesid, count(vod_tveps.ep_number) as epcount FROM vod_tveps join vod_tvseries on vod_tveps.seriesid = vod_tvseries.seriesid where vod_tvseries.seriesid = '.$sid.' group by vod_tveps.season';
   } else {
     $sql = 'SELECT vod_tveps.season, vod_tvseries.category, vod_tvseries.seriesname, vod_tvseries.poster, vod_tveps.seriesid, count(vod_tveps.ep_number) as epcount FROM vod_tveps join vod_tvseries on vod_tveps.seriesid = vod_tvseries.seriesid  group by vod_tveps.season';
   }
		$voddata = $this->db->query($sql)->fetchAll();
		return $voddata;
	}

  	function getSeriesDataSH($sid){
   $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
    if ($sid != ''){
     $filtersql = 'SELECT DISTINCT(episodes.season), episodes.name as ser_name, series.rating, series.yt_trailer, episodes.overview, series.releasedate, series.category, series.genre, series.backdrop, series.poster, min(episodes.airdate) as airdate, series.description, series.ser_id, episodes.cover, episodes.coverbig, count(episode) as epcount from episodes inner join series on episodes.ser_id = series.ser_id where episodes.ser_id ='.$sid.' group by episodes.season';
		
   } else {
     $filtersql = 'SELECT DISTINCT(episodes.season), episodes.name as ser_name, series.rating, seties.yt_trailer, episodes.overview, series.releasedate, series.category, series.genre, series.backdrop, series.poster, min(episodes.airdate) as airdate,  series.description, series.ser_id, episodes.cover, episodes.coverbig, count(episode) as epcount from episodes inner join series on episodes.ser_id = series.ser_id group by episodes.season';
   }
		$voddata = mysqli_query($shdb,$filtersql);
		return $voddata;
	}
 
  function getPSeriesDataSH($sid){
   $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
    if ($sid != ''){
     $filtersql = 'SELECT DISTINCT(episodes.season), episodes.name as ser_name, series.rating, series.yt_trailer, episodes.overview, series.releasedate, series.category, series.genre, series.backdrop, series.poster, min(episodes.airdate) as airdate, series.description, series.ser_id, episodes.cover, episodes.coverbig, count(episode) as epcount from episodes inner join series on episodes.ser_id = series.ser_id where episodes.ser_id ='.$sid.' group by episodes.season';
		
   } else {
     $filtersql = 'SELECT DISTINCT(episodes.season), episodes.name as ser_name, series.rating, seties.yt_trailer, episodes.overview, series.releasedate, series.category, series.genre, series.backdrop, series.poster, min(episodes.airdate) as airdate,  series.description, series.ser_id, episodes.cover, episodes.coverbig, count(episode) as epcount from episodes inner join series on episodes.ser_id = series.ser_id group by episodes.season';
   }
		$voddata = mysqli_query($shdb,$filtersql);
		return $voddata;
	}
 
	function getVODURL($id){
     
		$url = $this->db->select('vod_mov', ['path'], ['mov_id' => $id]);
     return $url[0]['path'];
   
		
	}
 
 	function getVODURLSH($id){
     
	$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
     $filtersql = 'select url from movies where mov_id = '. $id;
   
     $streams = mysqli_query($shdb,$filtersql);
     $row = mysqli_fetch_assoc($streams);
     return $row['url'];
   
		
	}
 
  function getPVODURLSH($id){
     
	$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
     $filtersql = 'select url from movies where mov_id = '. $id;
   
     $streams = mysqli_query($shdb,$filtersql);
     $row = mysqli_fetch_assoc($streams);
     return $row['url'];
   
		
	}
	
	function getVODTVURL($id){
		$url = $this->db->select('vod_tveps', ['relative_path'], ['ep_id' => $id]);
		return $url[0]['relative_path'];
	}
 
 	function getVODTVURLSH($id){
		$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
     $filtersql = 'select url from episodes where id = '. $id;
   
     $streams = mysqli_query($shdb,$filtersql);
     $row = mysqli_fetch_assoc($streams);
     return $row['url'];
	}
 
  function getPVODTVURLSH($id){
		$shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
     $filtersql = 'select url from episodes where id = '. $id;
   
     $streams = mysqli_query($shdb,$filtersql);
     $row = mysqli_fetch_assoc($streams);
     return $row['url'];
	}
	
	function getMovieDetails($id){
		//$details = $this->db->select('video', ['id','name','description','rate','category_id','added','pic','director','actors','time','genre_id','year','o_name'],['id' => $id]);
		$details = $this->db->select('vod_mov', ['mov_id','mov_name','category','poster'],['mov_id' => $id]);
		
		return $details;
	}
 
	function getMovieDetailsSH($id){
		 $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "vod");
     $filtersql = 'select * from movies where mov_id = '. $id;
   
     $details = mysqli_query($shdb,$filtersql);

		return $details;
	}	
 
  function getPMovieDetailsSH($id){
		 $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
     $filtersql = 'select * from movies where mov_id = '. $id;
   
     $details = mysqli_query($shdb,$filtersql);

		return $details;
	}
 
	function getVODSwitch($user){
		$censor = $this->db->select('users', ['vod_switch'], ['login' => $user]);
		return $censor[0]['vod_switch'];
	}
 
 	function checkGeolock($login){
	  $geo = $this->db->select('users', ['geolock'], ['login' => $login]);
		return $geo[0]['geolock'];
	}
 
	function checkGeolockKill(){
	  $glk = $this->db->select('server_config', ['value'], ['name' => "geolock_kill"]);
		return $glk[0]['value'];
	}

  function getSportsHubMyListMovieCategoryId(){
    return $this->sportshub_mylist_movie_category_id;
  }

  function getSportsHubMyListSeriesCategoryId(){
    return $this->sportshub_mylist_series_category_id;
  }

  function getSportsHubSyncDbPath(){
    return $this->sportshub_sync_db_path;
  }

  function openSportsHubSyncDb(){
    if (!class_exists('SQLite3')) {
      return null;
    }

    $dbPath = $this->getSportsHubSyncDbPath();
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }

    $sqlite = new SQLite3($dbPath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('PRAGMA journal_mode = WAL;');
    $sqlite->exec('PRAGMA synchronous = NORMAL;');
    return $sqlite;
  }

  function ensureSportsHubSyncSchema($sqlite){
    if (!$sqlite) {
      return false;
    }
    $sqlite->exec('
      CREATE TABLE IF NOT EXISTS user_mylist (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        username TEXT NOT NULL,
        media_type TEXT NOT NULL,
        internal_id INTEGER NOT NULL,
        title TEXT,
        category TEXT NOT NULL DEFAULT "tracked",
        watched INTEGER NOT NULL DEFAULT 0,
        tmdb_id TEXT,
        imdb_id TEXT,
        tvdb_id TEXT,
        source_event TEXT,
        metadata_json TEXT,
        added_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL,
        removed_at INTEGER
      );
    ');
    $sqlite->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_user_mylist_unique ON user_mylist (user_id, media_type, internal_id, category);');
    $sqlite->exec('CREATE INDEX IF NOT EXISTS idx_user_mylist_lookup ON user_mylist (user_id, media_type, removed_at, updated_at);');
    return true;
  }

  function getUserRecordByLogin($login){
    if ($login === null || $login === ''){
      return null;
    }
    $rows = $this->db->select('users', ['id', 'login', 'status', 'expire_billing_date'], ['login' => $login]);
    if (!is_array($rows) || count($rows) !== 1){
      return null;
    }
    return $rows[0];
  }

  function isUserActiveAndNotExpired($userRow){
    if (!$userRow || !isset($userRow['status'])){
      return false;
    }

    if ((string)$userRow['status'] === '1'){
      return false;
    }

    $expDate = isset($userRow['expire_billing_date']) ? $userRow['expire_billing_date'] : '';
    if ($expDate === null || $expDate === '' || $expDate === '0' || $expDate === '0000-00-00 00:00:00'){
      return true;
    }

    $expTs = strtotime($expDate);
    if (!$expTs){
      return true;
    }

    return $expTs >= time();
  }

  function getUserMyListRows($userId, $mediaType, $category){
    $sqlite = $this->openSportsHubSyncDb();
    if (!$sqlite) {
      return array();
    }
    $this->ensureSportsHubSyncSchema($sqlite);

    $query = 'SELECT internal_id, watched, updated_at FROM user_mylist WHERE user_id = :user_id AND media_type = :media_type AND removed_at IS NULL';
    if ($category !== null && $category !== ''){
      $query .= ' AND category = :category';
    }
    $query .= ' ORDER BY updated_at DESC';

    $stmt = $sqlite->prepare($query);
    if (!$stmt) {
      $sqlite->close();
      return array();
    }
    $stmt->bindValue(':user_id', (int)$userId, SQLITE3_INTEGER);
    $stmt->bindValue(':media_type', (string)$mediaType, SQLITE3_TEXT);
    if ($category !== null && $category !== ''){
      $stmt->bindValue(':category', (string)$category, SQLITE3_TEXT);
    }

    $result = $stmt->execute();
    $rows = array();
    if ($result) {
      while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
      }
      $result->finalize();
    }

    $stmt->close();
    $sqlite->close();
    return $rows;
  }

  function getPVODMyListMovieStreams($username, $category = 'tracked'){
    $userRow = $this->getUserRecordByLogin($username);
    if (!$userRow || !$this->isUserActiveAndNotExpired($userRow)) {
      return array();
    }

    $myListRows = $this->getUserMyListRows((int)$userRow['id'], 'movie', $category);
    if (count($myListRows) === 0) {
      return array();
    }

    $idOrder = array();
    foreach ($myListRows as $row) {
      $idOrder[] = (int)$row['internal_id'];
    }

    $idOrder = array_values(array_unique($idOrder));
    $placeholders = implode(',', array_fill(0, count($idOrder), '?'));
    $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
    if (!$shdb) {
      return array();
    }

    $sql = "SELECT mov_id,p_server,p_id,mov_name,year,tmdbid,imdb_id,tvdb_id,bitrate,poster,backdrop,added,genre,container,releasedate,rating,age,description,plot,url,urlkey,duration_secs,meta_check,yt_trailer FROM movies WHERE mov_id IN ($placeholders)";
    $stmt = mysqli_prepare($shdb, $sql);
    if (!$stmt) {
      mysqli_close($shdb);
      return array();
    }

    $types = str_repeat('i', count($idOrder));
    mysqli_stmt_bind_param($stmt, $types, ...$idOrder);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $byId = array();
    while ($row = mysqli_fetch_assoc($res)) {
      $row['category'] = $this->getSportsHubMyListMovieCategoryId();
      $byId[(int)$row['mov_id']] = $row;
    }

    mysqli_stmt_close($stmt);
    mysqli_close($shdb);

    $ordered = array();
    foreach ($idOrder as $id) {
      if (isset($byId[(int)$id])) {
        $ordered[] = $byId[(int)$id];
      }
    }

    return $ordered;
  }

  function getPVODMyListSeriesStreams($username, $category = 'tracked'){
    $userRow = $this->getUserRecordByLogin($username);
    if (!$userRow || !$this->isUserActiveAndNotExpired($userRow)) {
      return array();
    }

    $myListRows = $this->getUserMyListRows((int)$userRow['id'], 'series', $category);
    if (count($myListRows) === 0) {
      return array();
    }

    $idOrder = array();
    foreach ($myListRows as $row) {
      $idOrder[] = (int)$row['internal_id'];
    }

    $idOrder = array_values(array_unique($idOrder));
    $placeholders = implode(',', array_fill(0, count($idOrder), '?'));
    $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
    if (!$shdb) {
      return array();
    }

    $sql = "SELECT ser_id,p_server,p_id,ser_name,tmdbid,tvdbid,imdbid,poster,backdrop,added,genre,releasedate,rating,description,plot,ep_runtime,meta_check,yt_trailer FROM series WHERE ser_id IN ($placeholders)";
    $stmt = mysqli_prepare($shdb, $sql);
    if (!$stmt) {
      mysqli_close($shdb);
      return array();
    }

    $types = str_repeat('i', count($idOrder));
    mysqli_stmt_bind_param($stmt, $types, ...$idOrder);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $byId = array();
    while ($row = mysqli_fetch_assoc($res)) {
      $row['category'] = $this->getSportsHubMyListSeriesCategoryId();
      $byId[(int)$row['ser_id']] = $row;
    }

    mysqli_stmt_close($stmt);
    mysqli_close($shdb);

    $ordered = array();
    foreach ($idOrder as $id) {
      if (isset($byId[(int)$id])) {
        $ordered[] = $byId[(int)$id];
      }
    }

    return $ordered;
  }

  function resolvePVODMovieIdByExternalIds($imdbId, $tmdbId, $tvdbId){
    $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
    if (!$shdb) {
      return null;
    }

    $checks = array(
      array('field' => 'imdb_id', 'value' => $imdbId),
      array('field' => 'tmdbid', 'value' => $tmdbId),
      array('field' => 'tvdb_id', 'value' => $tvdbId),
    );

    foreach ($checks as $check) {
      if ($check['value'] === null || $check['value'] === '') {
        continue;
      }
      $sql = "SELECT mov_id FROM movies WHERE {$check['field']} = ? LIMIT 1";
      $stmt = mysqli_prepare($shdb, $sql);
      if (!$stmt) {
        continue;
      }
      $value = (string)$check['value'];
      mysqli_stmt_bind_param($stmt, 's', $value);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      mysqli_stmt_close($stmt);
      if ($row && isset($row['mov_id'])) {
        mysqli_close($shdb);
        return (int)$row['mov_id'];
      }
    }

    mysqli_close($shdb);
    return null;
  }

  function resolvePVODSeriesIdByExternalIds($imdbId, $tmdbId, $tvdbId){
    $shdb = mysqli_connect("144.76.182.242", "sportshub", "XXS1S0SsxCQyKwFK", "pvod");
    if (!$shdb) {
      return null;
    }

    $checks = array(
      array('field' => 'imdbid', 'value' => $imdbId),
      array('field' => 'tmdbid', 'value' => $tmdbId),
      array('field' => 'tvdbid', 'value' => $tvdbId),
    );

    foreach ($checks as $check) {
      if ($check['value'] === null || $check['value'] === '') {
        continue;
      }
      $sql = "SELECT ser_id FROM series WHERE {$check['field']} = ? LIMIT 1";
      $stmt = mysqli_prepare($shdb, $sql);
      if (!$stmt) {
        continue;
      }
      $value = (string)$check['value'];
      mysqli_stmt_bind_param($stmt, 's', $value);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      mysqli_stmt_close($stmt);
      if ($row && isset($row['ser_id'])) {
        mysqli_close($shdb);
        return (int)$row['ser_id'];
      }
    }

    mysqli_close($shdb);
    return null;
  }

}
