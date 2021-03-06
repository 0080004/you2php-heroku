<?php
ini_set("display_errors", "off");
error_reporting(E_ALL | E_STRICT);
error_reporting(0);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
// utils.php
function sig_js_decode($player_html){
	
/*	 Youtube Sep2018 Changes	 
deDE: 
		var aL={NI:function(a,b){a.splice(0,b)},jl:function(a){a.reverse()},l5:function(a,b){var c=a[0];a[0]=a[b%a.length];a[b%a.length]=c}}
		bL=function(a){a=a.split("");aL.jl(a,58);aL.NI(a,2);aL.l5(a,35);aL.NI(a,2);aL.jl(a,45);aL.l5(a,4);aL.jl(a,46);return a.join("")};
	->$L=function(a,b,c){b=void 0===b?"":b;c=void 0===c?"":c;var d=new g.cL(a);a.match(/https:\/\/yt.akamaized.net/)||d.set("alr","yes");c&&d.set(b,bL(c));return d};
*/
	
	// Pattern -> .*;.&&..set(.,XX(.*));.*;};
	$prefix ="/\W*.*;\w\&&\w\.set\(\w,";
	$funcName = "([\$a-zA-Z0-9]{2})";
	$suffix= "\(.*\).*;.*;/";
	if(preg_match($prefix.$funcName.$suffix, $player_html, $matches)){
	
		$func_name = $matches[1];		
		$func_name = preg_quote($func_name);
		
		// extract code block from that function
		// single quote in case function name contains $dollar sign
		// xm=function(a){a=a.split("");wm.zO(a,47);wm.vY(a,1);wm.z9(a,68);wm.zO(a,21);wm.z9(a,34);wm.zO(a,16);wm.z9(a,41);return a.join("")};
		if(preg_match('/'.$func_name.'=function\([a-z]+\){(.*?)}/', $player_html, $matches)){
			
			$js_code = $matches[1];
			
			// extract all relevant statements within that block
			// wm.vY(a,1);
			if(preg_match_all('/([a-z0-9]{2})\.([a-z0-9]{2})\([^,]+,(\d+)\)/i', $js_code, $matches) != false){
				
				// must be identical
				$obj_list = $matches[1];
				
				//
				$func_list = $matches[2];
				
				// extract javascript code for each one of those statement functions
				preg_match_all('/('.implode('|', $func_list).'):function(.*?)\}/m', $player_html, $matches2,  PREG_SET_ORDER);
				
				$functions = array();
				
				// translate each function according to its use
				foreach($matches2 as $m){
					
					if(strpos($m[2], 'splice') !== false){
						$functions[$m[1]] = 'splice';						
					} else if(strpos($m[2], 'a.length') !== false){
						$functions[$m[1]] = 'swap';
					} else if(strpos($m[2], 'reverse') !== false){
						$functions[$m[1]] = 'reverse';
					}
				}
				
				// FINAL STEP! convert it all to instructions set
				$instructions = array();
				
				foreach($matches[2] as $index => $name){
					$instructions[] = array($functions[$name], $matches[3][$index]);
				}
				
				return $instructions;
			}
		}
	}
	
	return false;
}
// YouTube is capitalized twice because that's how youtube itself does it:
// https://developers.google.com/youtube/v3/code_samples/php
class YouTubeDownloader {
	
	private $storage_dir;
	private $cookie_dir;
	
	private $itag_info = array(
		5 => "FLV 400x240",
		6 => "FLV 450x240",
		13 => "3GP Mobile",
		17 => "3GP 144p",
		18 => "MP4 360p",
		22 => "MP4 720p (HD)",
		34 => "FLV 360p",
		35 => "FLV 480p",
		36 => "3GP 240p",
		37 => "MP4 1080",
		38 => "MP4 3072p",
		43 => "WebM 360p",
		44 => "WebM 480p",
		45 => "WebM 720p",
		46 => "WebM 1080p",
		59 => "MP4 480p",
		78 => "MP4 480p",
		82 => "MP4 360p 3D",
		83 => "MP4 480p 3D",
		84 => "MP4 720p 3D",
		85 => "MP4 1080p 3D",
		91 => "MP4 144p",
		92 => "MP4 240p HLS",
		93 => "MP4 360p HLS",
		94 => "MP4 480p HLS",
		95 => "MP4 720p HLS",
		96 => "MP4 1080p HLS",
		100 => "WebM 360p 3D",
		101 => "WebM 480p 3D",
		102 => "WebM 720p 3D",
		120 => "WebM 720p 3D",
		127 => "TS Dash Audio 96kbps",
		128 => "TS Dash Audio 128kbps"
	);
	
	
	function __construct(){
		$this->storage_dir = sys_get_temp_dir();
		$this->cookie_dir = sys_get_temp_dir();
	}
	
	function setStorageDir($dir){
		$this->storage_dir = $dir;
	}
	
	// what identifies each request? user agent, cookies...
	public function curl($url){
	
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
		//curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
		
		//curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		
		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;
	}
	
	public static function head($url){
		
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		$result = curl_exec($ch);
		curl_close($ch);
		
		return http_parse_headers($result);
	}
	
	// html code of watch?v=aaa
	private function getInstructions($html){
		
		// <script src="//s.ytimg.com/yts/jsbin/player-fr_FR-vflHVjlC5/base.js" name="player/base"></script>
		
		// check what player version that video is using
		if(preg_match('@<script\s*src="([^"]+player[^"]+js)@', $html, $matches)){
			
			$player_url = $matches[1];
			
			// relative protocol?
			if(strpos($player_url, '//') === 0){
				$player_url = 'http://'.substr($player_url, 2);
			} else if(strpos($player_url, '/') === 0){
				// relative path?
				$player_url = 'http://www.youtube.com'.$player_url;
			}
			
			// try to find instructions list already cached from previous requests...
			$file_path = $this->storage_dir.'/'.md5($player_url);
			
			if(file_exists($file_path)){
				
				// unserialize could fail on empty file
				$str = file_get_contents($file_path);
				return unserialize($str);
				
			} else {
				
				$js_code = $this->curl($player_url);
				$instructions = sig_js_decode($js_code);
				
				if($instructions){
					file_put_contents($file_path, serialize($instructions));
					return $instructions;
				}
			}
		}
		
		return false;
	}
	
	// this is in beta mode!!
	public function stream($id){
		
		$links = $this->getDownloadLinks($id, "mp4");
		
		if(count($links) == 0){
			die("no url found!");
		}
		
		// grab first available MP4 link
		$url = $links[0]['url'];
		
		// request headers
		$headers = array(
			'User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0'
		);
		
		if(isset($_SERVER['HTTP_RANGE'])){
			$headers[] = 'Range: '.$_SERVER['HTTP_RANGE'];
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		
		// we deal with this ourselves
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		
		// whether request to video success
		$headers = '';
		$headers_sent = false;
		$success = false;
		
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $data) use (&$headers, &$headers_sent){
			
			$headers .= $data;
			
			// this should be first line
			if(preg_match('@HTTP\/\d\.\d\s(\d+)@', $data, $matches)){
				$status_code = $matches[1];
				
				// status=ok or partial content
				if($status_code == 200 || $status_code == 206){
					$headers_sent = true;
					header(rtrim($data));
				}
				
			} else {
				
				// only headers we wish to forward back to the client
				$forward = array('content-type', 'content-length', 'accept-ranges', 'content-range');
				
				$parts = explode(':', $data, 2);
				
				if($headers_sent && count($parts) == 2 && in_array(trim(strtolower($parts[0])), $forward)){
					header(rtrim($data));
				}
			}
			
			return strlen($data);
		});
		
		// if response is empty - this never gets called
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$headers_sent){
			
			if($headers_sent){
				echo $data;
				flush();
			}
			
			return strlen($data);
		});
		
		$ret = @curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);
		
		// if we are still here by now, return status_code
		return true;
	}
	
	// extract youtube video_id from any piece of text
	public function extractId($str){
		
		if(preg_match('/[a-z0-9_-]{11}/i', $str, $matches)){
			return $matches[0];
		}
		
		return false;
	}
	
	// selector by format: mp4 360, 
	private function selectFirst($links, $selector){
		
		$result = array();
		$formats = preg_split('/\s*,\s*/', $selector);
		
		// has to be in this order
		foreach($formats as $f){
			
			foreach($links as $l){
				
				if(stripos($l['format'], $f) !== false || $f == 'any'){
					$result[] = $l;
				}
			}
		}
		
		return $result;
	}
	
	// options | deep_links | append_redirector
	public function getDownloadLinks($id, $selector = false){
		
		$result = array();
		$instructions = array();
		
		// you can input HTML of /watch? page directory instead of id
		if(strpos($id, '<div id="player') !== false){
			$html = $id;
		} else {
			$video_id = $this->extractId($id);
			
			if(!$video_id){
				return false;
			}
			
			$html = $this->curl("https://www.youtube.com/watch?v={$video_id}");
		}
		
		$gvi = $this->curl("https://www.youtube.com/get_video_info?el=embedded&eurl=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3D".urlencode($video_id)."&video_id={$video_id}");
		// http://stackoverflow.com/questions/35608686/how-can-i-get-the-actual-video-url-of-a-youtube-live-stream
		if(preg_match('@url_encoded_fmt_stream_map["\']:\s*["\']([^"\'\s]*)@', $html, $matches)){
			$uefsm = $matches[1];
		} elseif (preg_match('@url_encoded_fmt_stream_map=([^\&\s]+)@', $gvi, $matches_gvi)){
			$uefsm = urldecode($matches_gvi[1]);
		} elseif (strpos($html, 'player-age-gate-content') !== false) { // age-gate
			// nothing you can do folks...
			return false;
		} else {
			// youtube must have changed something
			return false;
		}
		$parts = explode(",", $uefsm);
			
		foreach($parts as $p){
			$query = str_replace('\u0026', '&', $p);
			parse_str($query, $arr);
			$url = $arr['url'];
			if(isset($arr['sig'])){
				$url = $url.'&signature='.$arr['sig'];
			} else if(isset($arr['signature'])){
				$url = $url.'&signature='.$arr['signature'];
			} else if(isset($arr['s'])){
				// this is probably a VEVO/ads video... signature must be decrypted first! We need instructions for doing that
				if(count($instructions) == 0){
					$instructions = (array)$this->getInstructions($html);
				}
				$dec = $this->sig_decipher($arr['s'], $instructions);
				$url = $url.'&signature='.$dec;
			}
			// redirector.googlevideo.com
			//$url = preg_replace('@(\/\/)[^\.]+(\.googlevideo\.com)@', '$1redirector$2', $url);
			$itag = $arr['itag'];
			$format = isset($this->itag_info[$itag]) ? $this->itag_info[$itag] : 'Unknown';
			$result[$itag] = array(
				'url' => $url,
				'format' => $format
			);
		}
		
		// do we want all links or just select few?
		if($selector){
			return $this->selectFirst($result, $selector);
		}
		
		return $result;
	}
	
	private function sig_decipher($signature, $instructions){
		
		foreach($instructions as $opt){
			
			$command = $opt[0];
			$value = $opt[1];
			
			if($command == 'swap'){
				
				$temp = $signature[0];
				$signature[0] = $signature[$value % strlen($signature)];
				$signature[$value] = $temp;
				
			} else if($command == 'splice'){
				$signature = substr($signature, $value);
			} else if($command == 'reverse'){
				$signature = strrev($signature);
			}
		}
		
		return trim($signature);
	}
}
?>
