<?php

namespace Bundles\Session;
use Bundles\SQL\SQLBundle;
use Exception;
use e;

class Bundle {

	private $db_bundle;
	private $dir;

	private $_cookie_url = false;
	private $_cookie_name;
	private $_orig_cookie_name;
	private $_cookie;

	private $_key;
	public $_id;
	private $_log_hit = true;
	private $_child_hit = false;
	private $_hit;
	public $_data = array(); // @todo make this private, but make sure DataAccess can manipulate this somehow
	private $data;
	private $_data_hash;
	private $_flashdata;

	private $_session;

	private $_robot = false;

	/**
	 * Wrap the public access to ->data so that you can call ->data->var = whatever; without being able to call ->data = false;
	 *
	 * @param string $var
	 * @return void
	 * @author David Boskovic
	 */
	public function __get($var) {
		if($var == 'data') return $this->data;
		if($var == 'd') return $this->data;
		return false;
	}

	/**
	 * Robots List
	 * @author Kelly Becker
	 */
	private static $_robots = array(
		"Facebook",
		"Accoona-AI-Agent",
		"AOLspider",
		"BlackBerry",
		"bot@bot.bot",
		"CazoodleBot",
		"CFNetwork",
		"ConveraCrawler",
		"Cynthia",
		"Dillo",
		"discoveryengine.com",
		"DoCoMo",
		"ee://aol/http",
		"exactseek.com",
		"fast.no",
		"FAST MetaWeb",
		"FavOrg",
		"FS-Web",
		"Gigabot",
		"GOFORITBOT",
		"gonzo",
		"Googlebot-Image",
		"holmes",
		"HTC_P4350",
		"HTML2JPG Blackbox",
		"http://www.uni-koblenz.de/~flocke/robot-info.txt",
		"iArchitect",
		"ia_archiver",
		"ICCrawler",
		"ichiro",
		"IEAutoDiscovery",
		"ilial",
		"IRLbot",
		"Keywen",
		"kkliihoihn nlkio",
		"larbin",
		"libcurl-agent",
		"libwww-perl",
		"Mediapartners-Google",
		"Metasearch Crawler",
		"Microsoft URL Control",
		"MJ12bot",
		"T-H-U-N-D-E-R-S-T-O-N-E",
		"voodoo-it",
		"www.aramamotorusearchengine.com",
		"archive.org_bot",
		"Teoma",
		"Ask Jeeves",
		"AvantGo",
		"Exabot-Images",
		"Exabot",
		"Google Keyword Tool",
		"Googlebot",
		"heritrix",
		"www.livedir.net",
		"iCab",
		"Interseek",
		"jobs.de",
		"MJ12bot",
		"pmoz.info",
		"SnapPreviewBot",
		"Slurp",
		"Danger hiptop",
		"MQBOT",
		"msnbot-media",
		"msnbot",
		"MSRBOT",
		"NetObjects Fusion",
		"nicebot",
		"nrsbot",
		"Ocelli",
		"Pagebull",
		"PEAR HTTP_Request class",
		"Pluggd/Nutch",
		"psbot",
		"Python-urllib",
		"Regiochannel",
		"SearchEngine",
		"Seekbot",
		"segelsuche.de",
		"Semager",
		"ShopWiki",
		"Snappy",
		"Speedy Spider",
		"sproose",
		"TurnitinBot",
		"Twiceler",
		"VB Project",
		"VisBot",
		"voyager",
		"VWBOT",
		"Wells Search",
		"West Wind",
		"Wget",
		"WWW-Mechanize",
		"www.show-tec.net",
		"xxyyzz",
		"YandexBot",
		"yacybot",
		"Yahoo-MMCrawler",
		"yetibot",
		"momentum-plugin",
		"NewRelicPinger"
	);

	public function __construct($dir) {
		$this->dir = $dir;
		$this->data = new DataAccess($this);
	}

	/**
	 * Initialize SQL
	 */
	public function _on_framework_loaded() {

		// Add manager
		e::configure('manage')->activeAddKey('bundle', __NAMESPACE__, 'sessions');

		$enabled = e::$environment->requireVar('SQL.Enabled', "yes | no");

		if($enabled !== true && $enabled !== 'yes')
			return false;

		$enabled = e::$environment->requireVar('Session.Enabled', "yes | no");

		if($enabled === true || $enabled === 'yes')
			$this->db_bundle = new SQLBundle($this->dir);
		if(is_object($this->db_bundle))
			$this->db_bundle->_sql_initialize();
	}

	/**
	 * Initializes the Session
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function _on_after_framework_loaded() {
		if(!is_object($this->db_bundle))
			return;

		/**
		 * Grab the cookie name
		 */
		$this->_cookie_name = e::$environment->requireVar('Session.Cookie.Name', "Cookie Name Must be Alphanumeric + Underscores");
		if(!preg_match('/^[_a-zA-Z0-9]+$/', $this->_cookie_name))
			e::$environment->invalidVar('Session.Cookie.Name');
		$this->_orig_cookie_name = $this->_cookie_name;

		/**
		 * Let other bundles influence the cookie
		 */
		$tmp = e::$events->appendToSessionCookieName();
		foreach($tmp as $append) {
			$this->_cookie_name .= $append;
		}

		/**
		 * Grab the cookie url
		 */
		$cookie_url = e::$events->first->cookie_url();
		$cookie_url = $cookie_url ? $cookie_url : e::$environment->requireVar('Session.Cookie.URL');
		$this->_cookie_url = $cookie_url ? $cookie_url : false;

		/**
		 * Grab the cookie contents and save it to the class
		 */
		$this->_cookie = isset($_COOKIE[$this->_cookie_name]) ? $_COOKIE[$this->_cookie_name] : false;

		$session = $this->_get_session();

		if($this->_robot === true) return;

		$this->_key 		= $session->key;
		$this->_id			= $session->id;
		$this->_data		= unserialize(base64_decode($session->data));
		$this->_data_hash	= md5($session->data);
		$this->_flashdata	= isset($this->_data['flashdata']) ? $this->_data['flashdata'] : array();

		$this->_data['xpost']	= $this->_data['ypost'];
		$this->_data['xget']	= $this->_data['yget'];

		$this->_data['ypost']	= $_POST;
		$this->_data['yget']	= $_GET;

		$this->_flashdata['post']	= $_POST;
		$this->_flashdata['get']	= $_GET;

		$this->_session =& $session;

		e\trace('Session Data', null, $this->_data, 0, 3);

		/**
		 * Bind Flashdata to a LHTML Var
		 */
		e::configure('lhtml')->activeAddKey('hook', ':flash', new flash);

		/**
		 * Bind session data to LHTML hook
		 * @author Nate Ferrero
		 */
		e::configure('lhtml')->activeAddKey('hook', ':session', array('--reference' => &$this->_data));
	}

	/**
	 * Gets the session from the DB
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function _get_session() {
		try {
			if(isset($_POST['override_session'])) $this->_cookie = $_POST['override_session'];

			if(strlen($this->_cookie) == 32) return $this->_get();
			else return $this->_create();
		} catch(Exception $e) {
			$url = $_SERVER['REQUEST_URI'];
			if($url[1] !== '@')
				throw $e;
		}
	}

	/**
	 * Gets the Existing Session
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function _get() {
		$session = e::$sql->select('session.list', array('key' => $this->_cookie))->row();
		if(!$session) return $this->_create();
		return $this->db_bundle->getSession($session);
	}

	/**
	 * Creates a new session
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function _create() {
		if($this->_is_robot()) { $this->_robot = true; return; }

		$key = $this->_token(32);

		$session = $this->db_bundle->newSession();
		$session->key = $key;
		$session->extra_info = base64_encode(serialize($_SERVER));
		$session->data = base64_encode(serialize(array()));
		$session->ip = $_SERVER['REMOTE_ADDR'];
		$session->save();
		$set = setcookie($this->_cookie_name, $key, 0, '/', ($this->_cookie_url ? $this->_cookie_url : false), false);

		if(!$set)
			throw new Exception("Session cookie `$this->_cookie_name` could not be set due to prior output from PHP");

		return $session;
	}

	/**
	 * Copy session cookie to another domain
	 * @author Nate Ferrero
	 */
	public function addDomain($domain, $append = '') {
		$name = $this->_orig_cookie_name . $append;
		$key = $this->_session->key;
		$set = setcookie($name, $key, 0, '/', $domain, false);
		if(!$set)
			throw new Exception("Session cookie `$this->_cookie_name$append` could not be set due to prior output from PHP");
	}


	/**
	 * Add a flashdata variable
	 *
	 * @param string $key
	 * @param string $subkey
	 * @param string $value
	 * @return void
	 * @author David Boskovic
	 */
	public function flashdata_push($key, $subkey, $value) {
		$this->_data['flashdata'][$key][$subkey][] = $value;
	}

	/**
	 * Add a message to the flashdata. This is weird too.
	 *
	 * @param string $type
	 * @param string $message
	 * @return void
	 * @author David Boskovic
	 */
	public function message($type, $message) {
		return $this->flashdata_push('result_data', 'messages', array('type' => $type, 'message' => $message));
	}

	/**
	 * Adds and returns flashdata
	 *
	 * @param string $key
	 * @param string $value
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function flashdata($key, $value = false) {

		if($value !== false) {
			if(isset($value['messages']) && is_array($value['messages']) && $key == 'result_data') foreach($value['messages'] as $msg) {
				$this->flashdata_push($key, 'messages', $msg);
			}

			else if(isset($this->_data['flashdata'][$key])) {
				$this->flashdata_push($key, 'messages', $msg);
			}

			else $this->_data['flashdata'][$key] = $value;

			$this->save();

			return true;
		}

		else {
			if(isset($this->_data['flashdata'][$key]))
				unset($this->_data['flashdata'][$key]);

			return $this->_flashdata[$key];
		}

	}

	/**
	 * Saves the updated session
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function save() {
		if(!($this->_session instanceof \Bundles\SQL\Model) || $this->_robot)
			return false;

		$serialize = base64_encode(serialize($this->_data));
		$session =& $this->_session;

		if(md5($serialize) !== $this->_data_hash)
			$session->data = $serialize;

		$session->save();
	}

	/**
	 * Get session Model
	 */
	public function _session() {
		return $this->_session;
	}

	public function _on_complete() {
		if(!is_object($this->db_bundle) || $this->_robot)
			return;
		if($this->_log_hit || $this->_child_hit) {
			$this->_hit = $this->db_bundle->newHit();
			$this->_hit->url = $_SERVER['REQUEST_URI'];
			if(isset($_SERVER['HTTP_REFERER']))
				$this->_hit->referer = $_SERVER['HTTP_REFERER'];
			$this->_hit->save();
			$this->_hit->linkSession($this->_session);

			if($this->_log_hit) {
				if($this->_session instanceof \Bundles\SQL\Model) {
					$this->_session->last_hit = $this->_hit->id;
					$this->_session->save();
				}
			} else if($this->_child_hit) {
				$this->_hit->parent = $this->_session->last_hit;
				$this->_hit->save();
			}
		}
		if($this->_session instanceof \Bundles\SQL\Model) {
			$this->_session->hits++;
			$this->save();
		}
		if(isset($this->_data['flashdata']))
			e\trace('Flash Data', null, $this->_data['flashdata']['result_data']);
	}

	/**
	 * Saving/retrieving data
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function data($method, $var, $val = false) {
		switch($method) {
			case 'get':
				return isset($this->_data[$var]) ? $this->_data[$var] : null;
			break;
			case 'set':
				$this->_data[$var] = $val;
				$this->save();
				return true;
			break;
			case 'unset':
				if(isset($this->_data[$var])) unset($this->_data[$var]);
				$this->save();
				return true;
			break;
			default:
				return false;
			break;
		}
	}

	/**
	 * Generate a random session ID.
	 *
	 * @param string $len
	 * @param string $md5
	 * @return void
	 * @author Andrew Johnson
	 * @website http://www.itnewb.com/v/Generating-Session-IDs-and-Random-Passwords-with-PHP
	 */
	private function _token( $len = 32, $md5 = true ) {

	    # Seed random number generator
	    # Only needed for PHP versions prior to 4.2
	    mt_srand( (double)microtime()*1000000 );

	    # Array of characters, adjust as desired
	    $chars = array(
	        'Q', '@', '8', 'y', '%', '^', '5', 'Z', '(', 'G', '_', 'O', '`',
	        'S', '-', 'N', '<', 'D', '{', '}', '[', ']', 'h', ';', 'W', '.',
	        '/', '|', ':', '1', 'E', 'L', '4', '&', '6', '7', '#', '9', 'a',
	        'A', 'b', 'B', '~', 'C', 'd', '>', 'e', '2', 'f', 'P', 'g', ')',
	        '?', 'H', 'i', 'X', 'U', 'J', 'k', 'r', 'l', '3', 't', 'M', 'n',
	        '=', 'o', '+', 'p', 'F', 'q', '!', 'K', 'R', 's', 'c', 'm', 'T',
	        'v', 'j', 'u', 'V', 'w', ',', 'x', 'I', '$', 'Y', 'z', '*'
	    );

	    # Array indice friendly number of chars; empty token string
	    $numChars = count($chars) - 1; $token = '';

	    # Create random token at the specified length
	    for ( $i=0; $i<$len; $i++ )
	        $token .= $chars[ mt_rand(0, $numChars) ];

	    # Should token be run through md5?
	    if ( $md5 ) {

	        # Number of 32 char chunks
	        $chunks = ceil( strlen($token) / 32 ); $md5token = '';

	        # Run each chunk through md5
	        for ( $i=1; $i<=$chunks; $i++ )
	            $md5token .= md5( substr($token, $i * 32 - 32, 32) );

	        # Trim the token
	        $token = substr($md5token, 0, $len);

	    } return $token;
	}

	public function __call($func, $args) {
		if($this->_robot) return;

		if(!is_object($this->_session))
			throw new Exception("Trying to load a session instance before initialized");

		return call_user_func_array(array($this->_session, $func), $args);
	}

	/**
	 * Show session data
	 */
	public function route() {
		$session = array(
			'member' => e::$members->currentMember(),
			'data' => $this->_data
		);
		dump($session);
	}

	/**
	 * Disable Page Load Hit - Useful for static files
	 */
	public function disable_hit($type = 'none') {
		if($type = 'child') $this->_child_hit = true;
		return $this->_log_hit = false;
	}

	/**
	 * Add a Hit log to the session
	 */
	public function add_hit($url = '', $referer = '', $time = 0) {
		$hit = $this->db_bundle->newHit();
		$hit->url = $url;
		$hit->referer = $referer;
		$hit->exec_time_ms = $time;
		$hit->parent = $this->_session->last_hit;
		$hit->save();
		$hit->linkSession($this->_session);
		return $hit;
	}

	/**
	 * Add total time to page hit - Used in e\complete() only!
	 */
	public function complete_hit($time) {
		if(method_exists($this->_hit, 'save')) {
			$this->_hit->exec_time_ms = abs($time);
			$this->_hit->save();
		}
	}

	public function robot() {
		return $this->_robot;
	}

	/**
	 * Checks to see if we are handling a robot
	 */
	private function _is_robot() {
		$robot = false;
		foreach(self::$_robots as $bot) {
			if(strpos($_SERVER['HTTP_USER_AGENT'], $bot) !== false) {
				$robot = true;
				break;
			}
		}

		return $robot;
	}
}

/**
 * Use this for data access e::$session->data->varname= whatever;
 *
 * @package default
 * @author David Boskovic
 */
class DataAccess {

	public function __set($var, $val) {
		return e::$session->data('set', $var, $val);
	}

	public function __get($var) {
		return e::$session->data('get', $var);
	}

	public function __unset($var) {
		return e::$session->data('unset', $var);
	}

}

class flash {

	public function __call($function, $args) {
		return e::$session->flashdata($function);
	}

}