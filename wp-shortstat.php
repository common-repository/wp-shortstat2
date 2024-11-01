<?php
/* $Id: wp-shortstat.php 1375 2006-11-02 08:42:18Z mkaemmerer $
Plugin Name: WP-ShortStat
Plugin URI: http://blog.happyarts.de/wp-shortstat/
Description: Track your blog stats. Visit <a href="index.php?page=wp-shortstat.php">Dashboard|ShortStat</a>. Based on <a href="http://shortstat.shauninman.com/">Shaun Inman's ShortStat</a> and  <a href="http://jrm.cc/">Jeff Minards's</a> plugin <a href="http://dev.wp-plugins.org/wiki/wp-shortstat">wp-shortstat 1.2</a>
Version: 1.17a
Author: Markus Kaemmerer
Author URI: http://blog.happyarts.de
*/

class wp_shortstat {

	var $languages;
	var $table_stats;
	var $table_search;
	var $tz_offset;
	var $current_time;
	var $time_format;

        // Constructor -- Set things up.
	function wp_shortstat() {
		global $table_prefix;

		// tables
		$this->table_stats  = $table_prefix . "ss_stats";
		$this->table_search = $table_prefix . "ss_search";

		$this->tz_offset = get_option('gmt_offset') * 3600;
		$this->current_time = strtotime(gmdate('Y-m-d g:i:s a'))+$this->tz_offset;

		// Longest Array Line Ever...
		$this->languages = array( "af" => "Afrikaans", "sq" => "Albanian", "eu" => "Basque", "bg" => "Bulgarian", "be" => "Byelorussian", "ca" => "Catalan", "zh" => "Chinese", "zh-cn" => "Chinese/China", "zh-tw" => "Chinese/Taiwan", "zh-hk" => "Chinese/Hong Kong", "zh-sg" => "Chinese/singapore", "hr" => "Croatian", "cs" => "Czech", "da" => "Danish", "nl" => "Dutch", "nl-nl" => "Dutch/Netherlands", "nl-be" => "Dutch/Belgium", "en" => "English", "en-gb" => "English/United Kingdom", "en-us" => "English/United States", "en-au" => "English/Australian", "en-ca" => "English/Canada", "en-nz" => "English/New Zealand", "en-ie" => "English/Ireland", "en-za" => "English/South Africa", "en-jm" => "English/Jamaica", "en-bz" => "English/Belize", "en-tt" => "English/Trinidad", "et" => "Estonian", "fo" => "Faeroese", "fa" => "Farsi", "fi" => "Finnish", "fr" => "French", "fr-be" => "French/Belgium", "fr-fr" => "French/France", "fr-ch" => "French/Switzerland", "fr-ca" => "French/Canada", "fr-lu" => "French/Luxembourg", "gd" => "Gaelic", "gl" => "Galician", "de" => "German", "de-at" => "German/Austria", "de-de" => "German/Germany", "de-ch" => "German/Switzerland", "de-lu" => "German/Luxembourg", "de-li" => "German/Liechtenstein", "el" => "Greek", "he" => "Hebrew", "he-il" => "Hebrew/Israel", "hi" => "Hindi", "hu" => "Hungarian", "ie-ee" => "Internet Explorer/Easter Egg", "is" => "Icelandic", "id" => "Indonesian", "in" => "Indonesian", "ga" => "Irish", "it" => "Italian", "it-ch" => "Italian/ Switzerland", "ja" => "Japanese", "ko" => "Korean", "lv" => "Latvian", "lt" => "Lithuanian", "mk" => "Macedonian", "ms" => "Malaysian", "mt" => "Maltese", "no" => "Norwegian", "pl" => "Polish", "pt" => "Portuguese", "pt-br" => "Portuguese/Brazil", "rm" => "Rhaeto-Romanic", "ro" => "Romanian", "ro-mo" => "Romanian/Moldavia", "ru" => "Russian", "ru-mo" => "Russian /Moldavia", "gd" => "Scots Gaelic", "sr" => "Serbian", "sk" => "Slovack", "sl" => "Slovenian", "sb" => "Sorbian", "es" => "Spanish", "es-do" => "Spanish", "es-ar" => "Spanish/Argentina", "es-co" => "Spanish/Colombia", "es-mx" => "Spanish/Mexico", "es-es" => "Spanish/Spain", "es-gt" => "Spanish/Guatemala", "es-cr" => "Spanish/Costa Rica", "es-pa" => "Spanish/Panama", "es-ve" => "Spanish/Venezuela", "es-pe" => "Spanish/Peru", "es-ec" => "Spanish/Ecuador", "es-cl" => "Spanish/Chile", "es-uy" => "Spanish/Uruguay", "es-py" => "Spanish/Paraguay", "es-bo" => "Spanish/Bolivia", "es-sv" => "Spanish/El salvador", "es-hn" => "Spanish/Honduras", "es-ni" => "Spanish/Nicaragua", "es-pr" => "Spanish/Puerto Rico", "sx" => "Sutu", "sv" => "Swedish", "sv-se" => "Swedish/Sweden", "sv-fi" => "Swedish/Finland", "ts" => "Thai", "tn" => "Tswana", "tr" => "Turkish", "uk" => "Ukrainian", "ur" => "Urdu", "vi" => "Vietnamese", "xh" => "Xshosa", "ji" => "Yiddish", "zu" => "Zulu");

	}

        // get the DB ready if it isn't already.
	function setup() {

	// include upgrade-functions for maybe_create_table;
  	if (! function_exists('maybe_create_table')) {
           require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  	}

		$table_stats_query = "CREATE TABLE $this->table_stats (
							  id int(11) unsigned NOT NULL auto_increment,
							  remote_ip varchar(15) NOT NULL default '',
							  country varchar(50) NOT NULL default '',
							  language VARCHAR(5) NOT NULL default '',
							  domain varchar(255) NOT NULL default '',
							  referer varchar(255) NOT NULL default '',
							  resource varchar(255) NOT NULL default '',
							  platform varchar(50) NOT NULL default '',
							  browser varchar(50) NOT NULL default '',
							  version varchar(15) NOT NULL default '',
							  dt int(10) unsigned NOT NULL default '0',
							  UNIQUE KEY id (id)
							  ) TYPE=MyISAM";

		$table_search_query = "CREATE TABLE $this->table_search (
							  id int(11) unsigned NOT NULL auto_increment,
							  searchterms varchar(255) NOT NULL default '',
							  count int(10) unsigned NOT NULL default '0',
							  PRIMARY KEY  (id)
							  ) TYPE=MyISAM;";

		maybe_create_table($this->table_stats, $table_stats_query);
		maybe_create_table($this->table_search, $table_search_query);
	}

        // Only public function
	function track() {
		global $wpdb, $cookiehash;

	// disable error logging
	error_reporting(E_ERROR);
 	ini_set('display_errors', 'off');

 	if(is_admin()
		|| is_404()
		|| is_preview()
		|| isset($_COOKIE['wordpressuser_'.$cookiehash])
                || strstr($_SERVER['PHP_SELF'], 'wp-login.php')
		  )return; // let's not track the admin pages -- no one cares.

                // test if user is admin, only get userinfo, when user is logged in
                if (is_user_logged_in())
                {
                  global $user_level;
   		  get_currentuserinfo();
                  if ($user_level > 8)
                     return;
                }

		$ip	= $_SERVER['REMOTE_ADDR'];

		$cntry  = "";                  	//$cntry	= $this->determineCountry($ip);
		$lang	= $this->determineLanguage();
		$ref	= $_SERVER['HTTP_REFERER'];

      // add "http://", if missing (e.g. with www.live.com)
      if (($ref != '') && (!stristr($ref, 'http://')))
      {
        $ref = 'http://'.$ref;
      }
      
        $url = @parse_url(urldecode($ref));

     if(function_exists('error_get_last'))
     {
       if($error = error_get_last())
       {
         if($error['type'] == E_WARNING)
         { $url = ''; }
       }
     }

      // filter phpsessionid from ref-string
      $matchstr = '/(.*)(PHPSESSID=.*[\&,$])(.*)/';
      if (preg_match($matchstr, $ref))
      {
        $ref = preg_replace($matchstr, '$1$3', $ref);
      }
		$domain	= eregi_replace('^www\.','',$url['host']);
		$res	= $_SERVER['REQUEST_URI'];
		$br	= $this->parseUserAgent($_SERVER['HTTP_USER_AGENT']);
		$dt	= $this->current_time;

		$this->sniffKeywords($url);

		$query = $wpdb->prepare("INSERT INTO $this->table_stats 
				(remote_ip,country,language,domain,referer,resource,platform,browser,version,dt)
				  VALUES 
		(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
		$ip,$cntry,$lang,$domain,$ref,$res,$br['platform'],$br['browser'],$br['version'],$dt);
		$wpdb->query($query);
}

    function query2utf8( $query )
    {
        if( preg_match('/^.{1}/us',$query) == 1 )
        {
            return $query;
        } else {
            return @mb_convert_encoding($query, 'utf-8', 'windows-1251');
        }
    }

	function sniffKeyword($url)  // $url should be an array created by parse_url($ref)
    {
    	$searchterms = '';
		$q = '';

		// Check for google first
		if (preg_match('/google\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// Googles search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/yahoo\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// Yahoo search terms are in "p"
			$searchterms = $q['p'];
			}
		else if (preg_match('/search\.msn\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// MSN search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/search\.aol\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// AOL search terms are in "query"
			$searchterms = $q['query'];
			}
		else if (preg_match('/web\.ask\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// Ask Jeeves search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/search\.looksmart\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// LookSmart search terms are in "p"
			$searchterms = $q['p'];
			}
		else if (preg_match('/alltheweb\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// All the Web search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/a9\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// A9 search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/gigablast\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// Gigablast search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/s\.teoma\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// Teoma search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/bing\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// Bings search terms are in "q"
			$searchterms = $q['q'];
			}			
		else if (preg_match('/search\.icq\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// ICQ search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/123people\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// 123peoples search terms are in "search_term"
			$searchterms = $q['search_term'];
			}
		else if (preg_match('/s\.blogger\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// blogger.com search terms are in "q"
			$searchterms = $q['q'];
			}
		else if (preg_match('/clusty\./i', $url['host'])) {
			parse_str($url['query'],$q);
			// Clusty search terms are in "query"
			$searchterms = $q['query'];
			}
                else if (preg_match('/yandex\./i', $url['host'])) {
                         parse_str($url['query'],$q);
            $searchterms = $this->query2utf8( $q['text'] );
                }
                else if (preg_match('/rambler\./i', $url['host'])) {
                     parse_str($url['query'],$q);
                     $searchterms = $q['words'];
                }
        else if (preg_match('/mail\./i', $url['host'])) {
            parse_str($url['query'],$q);
            $searchterms = $this->query2utf8( $q['q'] );
            }
                else if (preg_match('/aport\./i', $url['host'])) {
                     parse_str($url['query'],$q);
                     $searchterms = $q['r'];
                }
		else if (preg_match('/search\.naver\./i', $url['host'])) {
		    parse_str($url['query'],$q);
		    // Naver search terms are in 'query'
		    $searchterms = $q['query'];
    }
    
    if ( function_exists('mb_strtolower') )
        { $searchterms = mb_strtolower($searchterms,'utf-8'); }
    else
        { $searchterms = strtolower($searchterms); }
    $searchterms = preg_replace('/\\\"/', '"', $searchterms);

    return $searchterms;
  }


	function sniffKeywords($url) { // $url should be an array created by parse_url($ref)
		global $wpdb;

    $searchterms = $this->sniffKeyword($url);

		if (isset($searchterms) && !empty($searchterms))
    {
      $exists_query = $wpdb->prepare("SELECT id FROM $this->table_search WHERE searchterms = %s LIMIT 1",$searchterms);
			$search_term_id = $wpdb->get_var($exists_query);

			if( $search_term_id ) {
				$query = "UPDATE $this->table_search SET count = (count+1) WHERE id = $search_term_id";
			} else {
				$query = $wpdb->prepare("INSERT INTO $this->table_search (searchterms,count) VALUES 
					(%s,1)",$searchterms);
			}
			
			$wpdb->query($query);
		}
	}
	
	function parseUserAgent($ua) {
		$browser['platform']	= "";
		$browser['browser']		= "";
		$browser['version']		= "";
		$browser['majorver']	= "";
		$browser['minorver']	= "";

		// Test for platform
		if (eregi('Win95',$ua)) {
			$browser['platform'] = "Windows 95";
			}
		else if (eregi('Win98',$ua)) {
			$browser['platform'] = "Windows 98";
			}
		else if (eregi('Win 9x 4.90',$ua)) {
			$browser['platform'] = "Windows ME";
			}
		else if (eregi('Windows NT 5.0',$ua)) {
			$browser['platform'] = "Windows 2000";
			}
		else if (eregi('Windows NT 5.1',$ua)) {
			$browser['platform'] = "Windows XP";
			}
		else if (eregi('Windows NT 5.2',$ua)) {
			$browser['platform'] = "Windows Server 2003";
			}
		else if (eregi('Windows NT 6.0',$ua)) {
			$browser['platform'] = "Windows Vista";
			}
		else if (eregi('Windows NT 6.1',$ua)) {
			$browser['platform'] = "Windows Server 2008";
			}
		else if (eregi('Windows NT 7.0',$ua)) {
			$browser['platform'] = "Windows 7";
			}
		else if (eregi('Ubuntu',$ua)) {
			$browser['platform'] = "Ubuntu";
			}
		else if (eregi('Kubuntu',$ua)) {
			$browser['platform'] = "Kubuntu";
			}
		else if (eregi('Windows',$ua)) {
			$browser['platform'] = "Windows";
			}
		else if (eregi('Mac OS X',$ua)) {
			$browser['platform'] = "Mac OS X";
			}
		else if (eregi('Macintosh',$ua)) {
			$browser['platform'] = "Mac OS Classic";
			}
		else if (eregi('Linux',$ua)) {
			$browser['platform'] = "Linux";
			}
		else if (eregi('BSD',$ua) || eregi('FreeBSD',$ua) || eregi('NetBSD',$ua)) {
		$browser['platform'] = "BSD";
			}
		else if (eregi('SunOS',$ua)) {
			$browser['platform'] = "Solaris";
			}

		$b = "";
		
		// Test for browser type
		if (eregi('Mozilla/4',$ua) && !eregi('compatible',$ua)) {
			$browser['browser'] = "Netscape";
			eregi('Mozilla/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Mozilla/5',$ua) || eregi('Gecko',$ua)) {
			$browser['browser'] = "Mozilla";
			eregi('rv(:| )([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[2];
			}
                if (eregi('Safari',$ua)) {
                   $browser['browser'] = "Safari";
                   $browser['platform'] = "Mac OS X";
              	   eregi('Safari/([[:digit:]\.]+)',$ua,$b);
              	   $browser['version'] = $b[1];

              	if (eregi('417',$browser['version'])) { // v2.0.3
              		$browser['version'] 	= 2.0;
              		$browser['majorver']	= 3;
              		$browser['minorver']	= 0;
              		}
              	else if (eregi('416',$browser['version'])) { // v2.0.2
              		$browser['version'] 	= 2.0;
              		$browser['majorver']	= 2;
              		$browser['minorver']	= 0;
              		}
              	else if (eregi('412',$browser['version'])) { // v2.0 - 2.0.1
              		$browser['version'] 	= 2.0;
              		$browser['majorver']	= 1;
              		$browser['minorver']	= 0;
              		}
              	else if (eregi('312',$browser['version'])) { // v1.3 - 1.3.2
              		$browser['version'] 	= 1.3;
              		$browser['majorver']	= 1;
              		$browser['minorver']	= 3;
              		}
              	else if (eregi('125',$browser['version'])) { // v1.2 - 1.2.4
              		$browser['version'] 	= 1.2;
              		$browser['majorver']	= 1;
              		$browser['minorver']	= 2;
              		}
              	else if (eregi('100',$browser['version'])) { // v1.1 - 1.1.1
              		$browser['version'] 	= 1.1;
              		$browser['majorver']	= 1;
              		$browser['minorver']	= 1;
              		}
              	else if (eregi('85',$browser['version'])) { // v1.0 - 1.0.3 
              		$browser['version'] 	= 1.0;
              		$browser['majorver']	= 1;
              		$browser['minorver']	= 0;
              		}
              	else if ($browser['version'] < 85) { // v1.0b or earlier
              		$browser['version'] 	= "1.0b";
              		}
              	}
        	if (eregi('iCab',$ua)) {
			$browser['browser'] = "iCab";
			eregi('iCab ([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
                if (eregi('Flock',$ua)) {
                	$browser['browser'] = "Flock";
                	eregi('Flock/([[:digit:]\.]+)',$ua,$b);
                	$browser['version'] = $b[1];
                	}
       		if (eregi('Firefox',$ua)) {
			$browser['browser'] = "Firefox";
			eregi('Firefox/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
                if (eregi('Flock',$ua)) {
                	$browser['browser'] = "Flock";
                	eregi('Flock/([[:digit:]\.]+)',$ua,$b);
                	$browser['version'] = $b[1];
                	}
        	if (eregi('Firebird',$ua)) {
                        $browser['browser'] = "Firebird";
			eregi('Firebird/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Phoenix',$ua)) {
			$browser['browser'] = "Phoenix";
			eregi('Phoenix/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Camino',$ua)) {
			$browser['browser'] = "Camino";
			eregi('Camino/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Chimera',$ua)) {
			$browser['browser'] = "Chimera";
			eregi('Chimera/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
                if (eregi('SeaMonkey',$ua)) {
                	$browser['browser'] = "SeaMonkey";
                	eregi('SeaMonkey/([[:digit:]\.]+)',$ua,$b);
                	$browser['version'] = $b[1];
                	}
		if (eregi('Netscape',$ua)) {
			$browser['browser'] = "Netscape";
			eregi('Netscape[0-9]?/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('MSIE',$ua)) {
			$browser['browser'] = "Internet Explorer";
			eregi('MSIE ([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('MSN Explorer',$ua)) {
			$browser['browser'] = "MSN Explorer";
			eregi('MSN Explorer ([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('AOL',$ua)) {
			$browser['browser'] = "AOL";
			eregi('AOL ([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('America Online Browser',$ua)) {
			$browser['browser'] = "AOL Browser";
			eregi('America Online Browser ([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('K-Meleon',$ua)) {
			$browser['browser'] = "K-Meleon";
			eregi('K-Meleon/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Mediapartners-Google',$ua)) {
			$browser['browser'] = "Mediapartners-Google";
			eregi('Mediapartners-Google/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Feedfetcher-Google',$ua)) {
			$browser['browser'] = "Feedfetcher-Google";
			eregi('Feedfetcher-Google',$ua,$b);
			$browser['version'] = 0;
			}
		if (eregi('Beonex',$ua)) {
			$browser['browser'] = "Beonex";
			eregi('Beonex/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Opera',$ua)) {
			$browser['browser'] = "Opera";
			eregi('Opera( |/)([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[2];
			}
		if (eregi('OmniWeb',$ua)) {
			$browser['browser'] = "OmniWeb";
			eregi('OmniWeb/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];

			if (eregi('563',$browser['version'])) {
				$browser['version'] 	= 5.1;
				$browser['majorver']	= 5;
				$browser['minorver']	= 1;
				}
			else if (eregi('558',$browser['version'])) {
				$browser['version'] 	= 5.0;
				$browser['majorver']	= 5;
				$browser['minorver']	= 0;
				}
			else if (eregi('496',$browser['version'])) {
				$browser['version'] 	= 4.5;
				$browser['majorver']	= 4;
				$browser['minorver']	= 5;
				}
			}
		if (eregi('Konqueror',$ua)) {
			$browser['platform'] = "Linux";
	
			$browser['browser'] = "Konqueror";
			eregi('Konqueror/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Galeon',$ua)) {
			$browser['browser'] = "Galeon";
			eregi('Galeon/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Epiphany',$ua)) {
			$browser['browser'] = "Epiphany";
			eregi('Epiphany/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Kazehakase',$ua)) {
			$browser['browser'] = "Kazehakase";
			eregi('Kazehakase/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('amaya',$ua)) {
			$browser['browser'] = "Amaya";
			eregi('amaya/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Crawl',$ua) || eregi('bot',$ua) || eregi('slurp',$ua) || eregi('spider',$ua)) {
			$browser['browser'] = "Crawler/Search Engine";
			}
    if (eregi('msnbot',$ua)) {
        $browser['browser'] = "msnbot";
        eregi('msnbot/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }
    if (eregi('Googlebot',$ua)) {
        $browser['browser'] = "Googlebot";
        eregi('Googlebot/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }
    if (eregi('Yahoo-Blogs',$ua)) {
        $browser['browser'] = "Yahoo-Blogs";
        eregi('Yahoo-Blogs/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }
    if (eregi('Gigabot',$ua)) {
        $browser['browser'] = "Gigabot";
        eregi('Gigabot/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
      }if (eregi('Yahoo! Slurp',$ua)) {
        $browser['browser'] = "Yahoo! Slurp";
        $browser['version'] = "";
      }
 		if (eregi('Lynx',$ua)) {
			$browser['browser'] = "Lynx";
			eregi('Lynx/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('Links',$ua)) {
			$browser['browser'] = "Links";
			eregi('\(([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
		if (eregi('ELinks',$ua)) {
			$browser['browser'] = "ELinks";
			eregi('ELinks/([[:digit:]\.]+)',$ua,$b);
			$browser['version'] = $b[1];
			}
    if (eregi('ZyBorg',$ua)) {
        $browser['browser'] = "ZyBorg";
        eregi('ZyBorg/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }
    if (eregi('ichiro',$ua)) {
        $browser['browser'] = "ichiro";
        eregi('ichiro/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }
    if (eregi('NutchCVS',$ua)) {
        $browser['browser'] = "NutchCVS";
        eregi('NutchCVS/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }
    if (eregi('Technoratibot',$ua)) {
        $browser['browser'] = "Technoratibot";
        eregi('Technoratibot/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }
    if (eregi('Ask Jeeves/Teoma',$ua)) {
        $browser['browser'] = "Ask Jeeves/Teoma";
        $browser['version'] = "";
        }
    if (eregi('heritrix',$ua)) {
        $browser['browser'] = "Heritrix";
        eregi('heritrix/([[:digit:]\.]+)',$ua,$b);
        $browser['version'] = $b[1];
        }

		$v = "";
		// Determine browser versions
		if (($browser['browser']!='AppleWebKit' || $browser['browser']!='OmniWeb') && $browser['browser'] != "" && $browser['browser'] != "Crawler/Search Engine" && $browser['version'] != "") {
			// Make sure we have at least .0 for a minor version for Safari and OmniWeb
			$browser['version'] = (!eregi('\.',$browser['version']))?$browser['version'].".0":$browser['version'];
			
			eregi('^([0-9]*).(.*)$',$browser['version'],$v);
			$browser['majorver'] = $v[1];
			$browser['minorver'] = $v[2];
			}
		if (empty($browser['version']) || $browser['version']=='.0') {
			$browser['version']		= "";
			$browser['majorver']	= "";
			$browser['minorver']	= "";
			}
		
		return $browser;
	}
	
	function determineLanguage() {
		$lang_choice = "empty";
		$langs = array();
		if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
			// Capture up to the first delimiter (, found in Safari)
			preg_match('/([^,;]*)/',$_SERVER["HTTP_ACCEPT_LANGUAGE"],$langs);
			$lang_choice = $langs[0];
		}
		return $lang_choice;
	}

	// DISPLAY
	
	function getKeywords() {
		global $wpdb;
		$query = "SELECT searchterms, count
				  FROM $this->table_search
				  ORDER BY count DESC
				  LIMIT 0,36";

		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			
			foreach( $results as $r ) {
				$ul .= "<tr><td>".htmlspecialchars($r->searchterms)."</td><td class=\"last\">$r->count</td></tr>\n";
			}

			$ul .= "</table>";
		}
		return $ul;
	}

	function getReferers() {
		global $wpdb;

		$query = "SELECT referer, resource, dt
				  FROM $this->table_stats
				  WHERE referer NOT LIKE '%".$this->trimReferer($_SERVER['SERVER_NAME'])."%' AND
						referer!=''
				  ORDER BY dt DESC
				  LIMIT 0,36";

		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			foreach( $results as $r ) {
				$url = parse_url($r->referer);

				$when = ($r->dt >= strtotime(date("j F Y", $this->current_time)))?strftime("%H:%M",$r->dt):strftime("%e %b",$r->dt);

				$ul .= "<tr><td><a href=\"$r->referer\" title=\"$r->resource\" rel=\"nofollow\">".$this->trimReferer($url['host'])."</a></td><td class=\"last\">$when</td></tr>\n";
			}
			$ul .= "</table>";
		}
    if ( function_exists('mb_convert_encoding') )
      { $ul = @mb_convert_encoding($ul, 'HTML-ENTITIES', 'auto'); }
		return $ul;
	}

  	function getLastKeywords() {
		global $wpdb;

		$query = "SELECT referer, resource, dt
				  FROM $this->table_stats
				  WHERE referer NOT LIKE '%".$this->trimReferer($_SERVER['SERVER_NAME'])."%' AND
						referer!=''
				  ORDER BY dt DESC
				  LIMIT 0,100";

		if ($results = $wpdb->get_results($query)) {
			$ul = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			foreach( $results as $r )
      {
				$keyword = $this->sniffKeyword(parse_url($r->referer));

	    $lastline = '';
        if (!empty($keyword))
        {
  				$when = ($r->dt >= strtotime(date("j F Y", $this->current_time)))?strftime("%H:%M",$r->dt):strftime("%e %b",$r->dt);
  				$newline = "<tr><td><a href=\"$r->referer\" title=\"$r->resource\" rel=\"nofollow\">".htmlspecialchars(wordwrap($keyword,30,' ',true))."</a></td><td class=\"last\">$when</td></tr>\n";
          if ($lastline <> $newline)
          {
  	  			$ul .= $newline;
  	  			$lastline = $newline;
			    }
        }
			}
			$ul .= "</table>";
		}
		return $ul;
	}

	function getDomains() {
		global $wpdb;

		$query = "SELECT domain, referer, resource, COUNT(domain) AS 'total'
				  FROM $this->table_stats
				  WHERE domain !='".$this->trimReferer($_SERVER['SERVER_NAME'])."' AND
						domain!=''
				  GROUP BY domain
				  ORDER BY total DESC, dt DESC";

		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			$i=0;
			foreach( $results as $r ) {
				if ($i++ < 36) {
					$ul .= "\t<tr><td><a href=\"$r->referer\" title=\"$r->resource\" rel=\"nofollow\">$r->domain</a></td><td class=\"last\">$r->total</td></tr>\n";
				} else {
					break;
				}
			}
			$ul .= "</table>";
		}
		return $ul;
	}
	
	function getCountries() {
		global $wpdb;

		$query = "SELECT COUNT(*) AS 'total' FROM $this->table_stats WHERE country != ''";
		$th = $wpdb->get_var($query);

		$query = "SELECT country, COUNT(country) AS 'total'
				  FROM $this->table_stats
				  WHERE country!=''
				  GROUP BY country
				  ORDER BY total DESC";

		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			$i=0;
			foreach( $results as $r ) {
				if ($i++ < 36) {
					$ul .= "\t<tr><td>$r->country</td><td class=\"last\">$r->total (".number_format(($r->total/$th)*100)."%)</td></tr>\n";
				} else {
					break;
				}
			}
			$ul .= "</table>";
		}
		return $ul;
	}
	
	function getResources() {
		global $wpdb;

		$query = "SELECT resource, COUNT(resource) AS 'requests'
				  FROM $this->table_stats
				  WHERE
				  resource NOT LIKE '%/inc/%'
				  GROUP BY resource
				  ORDER BY requests DESC
				  LIMIT 0,36";

		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			foreach( $results as $r ) {
				$resource = $this->truncate($r->resource,34);
				$ul .= "\t<tr><td><a href=\"http://".$this->trimReferer($_SERVER['SERVER_NAME'])."$r->resource\">".$resource."</a></td><td class=\"last\">$r->requests</td></tr>\n";
			}
			$ul .= "</table>";
		}
		return $ul;
	}

	function getLastResources() {
		global $wpdb;

		$query = "SELECT resource, dt
				  FROM $this->table_stats
				  WHERE referer NOT LIKE '%".$this->trimReferer($_SERVER['SERVER_NAME'])."%' AND
				  referer!=''
				  ORDER BY dt DESC
				  LIMIT 0,36";

		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			foreach( $results as $r ) {
				$resource = $this->truncate($r->resource,34);
				$when = ($r->dt >= strtotime(date("j F Y", $this->current_time)))?strftime("%H:%M",$r->dt):strftime("%e %b",$r->dt);
				$ul .= "\t<tr><td><a href=\"http://".$this->trimReferer($_SERVER['SERVER_NAME'])."$r->resource\" title=\"$r->referer\">".
				  $resource."</a></td><td class=\"last\">$when</td></tr>\n";
			}
			$ul .= "</table>";
		}
    if ( function_exists('mb_convert_encoding') )
      { $ul = @mb_convert_encoding($ul, 'HTML-ENTITIES', 'auto'); }
		return $ul;
	}

	function getPlatforms() {
		global $wpdb;

		$query = "SELECT COUNT(*) AS 'total' FROM $this->table_stats WHERE platform != ''";
		$th = $wpdb->get_var($query);

		$query = "SELECT platform, COUNT(platform) AS 'total'
				  FROM $this->table_stats
				  WHERE platform != ''
				  GROUP BY platform
				  ORDER BY total DESC";
		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			foreach( $results as $r ) {
				$ul .= "\t<tr><td>$r->platform</td><td class=\"last\">".number_format(($r->total/$th)*100)."% </td></tr>\n";
			}
			$ul .= "</table>";
		}
		return $ul;
	}

	function getBrowsers() {
		global $wpdb;

		$query = "SELECT COUNT(*) AS 'total' FROM $this->table_stats WHERE browser != ''";
		$th = $wpdb->get_var($query);

		$query = "SELECT browser, version, COUNT(*) AS 'total'
				  FROM $this->table_stats
				  WHERE browser != ''
				  GROUP BY browser, version
				  ORDER BY total DESC";
		if ($results = $wpdb->get_results($query)) {
			$ul  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			foreach( $results as $r ) {
				$p = number_format(($r->total/$th)*100);
				if ($r->version == '') { $r->version = 'Unknown'; }
				if ($p>=1) {
					$ul .= "\t<tr><td>$r->browser</td><td>$r->version</td><td class=\"last\">$p%</td></tr>\n";
				}
			}
			$ul .= "</table>";
		}
		return $ul;
	}

	function getTotalHits() {
		global $wpdb;
		$query = "SELECT COUNT(*) AS 'total' FROM $this->table_stats";
		return $wpdb->get_var($query);
	}
	function getFirstHit() {
		global $wpdb;
		$query = "SELECT dt FROM $this->table_stats ORDER BY id LIMIT 0,1";
		return $wpdb->get_var($query);
	}
	function getUniqueHits() {
		global $wpdb;
		$query = "SELECT COUNT(DISTINCT remote_ip) AS 'total' FROM $this->table_stats";
		return $wpdb->get_var($query);
	}

    function getWeeksHits() {
      global $wpdb;

      $offset=24*60*60;
      $of = get_settings('gmt_offset');
      $ct = mktime(gmdate("G")+$of, gmdate("i")+($of - ((int) $of)) * 60, gmdate("s"), gmdate("m"), gmdate("d"), gmdate("Y"));

      $tmp  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
      $tmp .= "\t<tr><td class=\"accent\">".__('Day', 'wp-shortstat')."</td><td class=\"accent\">".__('Unique', 'wp-shortstat')."</td><td class=\"accent last\">".__('Hits+RSS', 'wp-shortstat')."</td></tr>\n";

      for ($i=0; $i<8; $i++) {
        // start with 00:00 on currente date
        $dt_start = mktime(0, 0, 0, date("m", $ct), date("d", $ct)-$i, date("Y", $ct));

        $day = ($i > 0) ? strftime("%a, %d %b %Y",$dt_start) : __('Today, ', 'wp-shortstat').strftime("%d %b %Y",$dt_start);

        $query = "SELECT COUNT(*) AS 'total', COUNT(DISTINCT remote_ip) as 'unique'  FROM $this->table_stats WHERE dt >= $dt_start AND dt <($dt_start+$offset) AND resource LIKE '%/feed/%'";
        if ($results = $wpdb->get_results($query)) {
          foreach ($results as $result) {
           $feedhits = $result->unique;
           $feedtotal = $result->total;
          }
        }

        $query = "SELECT COUNT(*) AS 'total', COUNT(DISTINCT remote_ip) as 'unique' FROM $this->table_stats WHERE dt >= $dt_start AND dt <($dt_start+$offset) AND resource NOT LIKE '%/feed/%'";
        if ($results = $wpdb->get_results($query)) {
          foreach ($results as $result) {
            $total = $result->unique+$feedhits;
           	$tmp .= "\t<tr><td>$day</td><td>$total</td><td class=\"last\">$result->total+$feedtotal</td></tr>\n";
            }
          }
        }

        $tmp .= "\t<tr><td class=\"accent\">".__('Since', 'wp-shortstat')."</td><td class=\"accent\">".__('Unique', 'wp-shortstat')."</td><td class=\"accent last\">".__('Hits', 'wp-shortstat')."</td></tr>\n";

        $tmp .= "\t<tr><td>".strftime(__('%e %b %Y, %H:%M', 'wp-shortstat'), $this->getFirstHit());

        $unique = $this->getUniqueHits();
        $total = $this->getTotalHits();
        $tmp .= "</td><td>$unique</td><td class=\"last\">$total</td></tr>\n</table>";

         if ( function_exists('mb_convert_encoding') )
          { $tmp = @mb_convert_encoding($tmp, 'HTML-ENTITIES', 'auto'); }
      	return $tmp;
  }

	function getLanguage() {
		global $wpdb;

		$query = "SELECT COUNT(*) AS 'total' FROM $this->table_stats WHERE language != '' AND language != 'empty'";
		$th = $wpdb->get_var($query);

		$query = "SELECT language, COUNT(language) AS 'total'
				  FROM $this->table_stats
				  WHERE language != '' AND
				  language != 'empty'
				  GROUP BY language
				  ORDER BY total DESC";

		if ($results = $wpdb->get_results($query)) {
			$html  = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
			foreach( $results as $r ) {
				$l = $r->language;
				$lang = (isset($this->languages[$l]))?$this->languages[$l]:$l;
				$per = number_format(($r->total/$th)*100);
				$per = ($per)?$per:'<1';
				$html .= "\t<tr><td>$lang</td><td class=\"last\">$per%</td></tr>\n";
				}
			$html .= "</table>";
			}
		return $html;
		}

	function truncate($var, $len = 120) {
		if (empty ($var)) return "";
		if (strlen ($var) < $len) return $var;
		$match = '';
		if (preg_match ('/(.{1,$len})\s./ms', $var, $match)) {
			return $match [1] . "...";
		} else {
			return substr ($var, 0, $len) . "...";
		}
	}

	function trimReferer($r) {
		$r = eregi_replace("http://","",$r);
		$r = eregi_replace("^www.","",$r);
		$r = $this->truncate($r,36);
		return $r;
	}
}

// Always want that instance
$wpss = new wp_shortstat();

// Installation/Initialization Routine
add_action('activate_'.plugin_basename(__FILE__), array(&$wpss, 'setup'));
// Tracking hook
add_action('shutdown', array(&$wpss, 'track'));


// For ze admin page
if (!function_exists('wp_shortstat_display_stats')) {
    function wp_shortstat_display_stats()
    {
    	global $wpss;
    	setlocale (LC_TIME, WPLANG);
    	load_plugin_textdomain('wp-shortstat');
    	?>
     <div class="wrap">
      <h2>ShortStat</h2>
      <div id="wp_shortstat">
        <div class="module waccents">
          <h3><?php echo __('Last week', 'wp-shortstat') ?></h3>
          <div><?php echo $wpss->getWeeksHits(); ?></div>
       	</div>
         <div class="module">
          <h3><?php echo __('Last Resources', 'wp-shortstat') ?> <span><?php echo __('When', 'wp-shortstat') ?></span></h3>
          <div><?php echo $wpss->getLastResources(); ?></div>
        </div>
         <div class="module">
          <h3><?php echo __('Last Keywords', 'wp-shortstat') ?> <span><?php echo __('When', 'wp-shortstat') ?></span></h3>
          <div><?php echo $wpss->getLastKeywords(); ?></div>
        </div>
         <div class="module">
          <h3><?php echo __('Last Referers', 'wp-shortstat') ?> <span><?php echo __('When', 'wp-shortstat') ?></span></h3>
          <div><?php echo $wpss->getReferers(); ?></div>
        </div>
         <div class="module">
          <h3><?php echo __('Keywords', 'wp-shortstat') ?> <span><?php echo __('Total', 'wp-shortstat') ?></span></h3>
          <div><?php echo $wpss->getKeywords(); ?></div>
        </div>
         <div class="module">
          <h3><?php echo __('Resources', 'wp-shortstat') ?> <span><?php echo __('Hits', 'wp-shortstat') ?></span></h3>
          <div><?php echo $wpss->getResources(); ?></div>
        </div>
         <div class="module">
          <h3><?php echo __('Domains', 'wp-shortstat') ?> <span><?php echo __('Hits', 'wp-shortstat') ?></span></h3>
          <div><?php echo $wpss->getDomains(); ?></div>
        </div>
         <div class="module">
          <h3><?php echo __('Browsers', 'wp-shortstat') ?> <span>%</span></h3>
          <div><?php echo $wpss->getBrowsers(); ?></div>
        </div>
          <div class="module waccents">
             <h3><?php echo __('Platforms', 'wp-shortstat') ?> <span>%</span></h3>
             <div><?php echo $wpss->getPlatforms(); ?></div>
           </div>
         <div class="module">
          <h3><?php echo __('Languages', 'wp-shortstat') ?> <span>%</span></h3>
          <div><?php echo $wpss->getLanguage(); ?></div>
        </div>
         <div id="donotremove"><?php echo __('&copy; 2004, 2009', 'wp-shortstat') ?> <a href="http://www.shauninman.com/">Shaun Inman</a>, <a href="http://blog.happyarts.de/">Markus Kaemmerer/Happy Arts</a></div>
       </div>
    </div>
    <?php
    }
} else {
    var_dump(debug_backtrace());
}

function wp_shortstat_add_pages($s) {
	add_submenu_page('index.php', 'ShortStat', 'ShortStat', 1, __FILE__, 'wp_shortstat_display_stats');
	return $s;
}
add_action('admin_menu', 'wp_shortstat_add_pages');


function wp_shortstat_css() {
	?> 
<style type="text/css">
/* BASIC STYLES
------------------------------------------------------------------------------*/
#wp_shortstat, #wp_shortstat table, #wp_shortstat td, #wp_shortstat th { font: 10px/14px Verdana, sans-serif; color: #313131; }
#wp_shortstat a { color: #0052CC; text-decoration: none; border: 0;}
#wp_shortstat a:visited { /*color: #00659C;*/ }
#wp_shortstat a:hover { color: #E60000; text-decoration: none; }

/* MODULE STYLES
------------------------------------------------------------------------------*/
#wp_shortstat .module {
	float: left;
	width: 312px;
	margin: 0 3px 3px 0;
	border-top: 1px solid #333;
	border-bottom: 1px solid #0D324F;
	padding-bottom: 1px;
	font: 10px/14px Verdana, sans-serif;
	}
#wp_shortstat .module h3 {
	position: relative;
	margin: 0 0 1px;
	padding: 8px 5px 1px 4px;
	text-align: left;
	font-size: 10px;
	font-weight: normal;
	color: #FFF;
        background-color: #14568A;
 	border-bottom: 1px solid #0D324F;
        }
#wp_shortstat .module h3 span {
	position: absolute;
	right: 19px;
	}
#wp_shortstat .module div {
	width: 315px;
	height: 253px;
	overflow: auto;
	}
#wp_shortstat .module div table {
	width: 296px;
 	border-bottom: 1px dotted #0D324F;
	}
#wp_shortstat .module div table th {
	display: none;
	}
#wp_shortstat .module div table td {
	padding: 3px 16px 3px 3px;
	vertical-align: top;
 	border-top: 1px dotted #0D324F;
 	}
#wp_shortstat .module div table td.last {
	text-align: right;
	white-space:nowrap;
	padding-right: 2px;
	}

#wp_shortstat .waccents h3 {
	margin-bottom: 0;
	}
#wp_shortstat .waccents div table {
	width: 312px;
 	border-bottom-width: 0;
	}
#wp_shortstat .waccents div table td {
	border-top-width: 0;
	border-bottom: 1px dotted #0D324F;
	}


#wp_shortstat .module div table td.accent {
	font-size: 9px;
	color: #000;
	background-color: #6DA6D1;
	border-top: 1px solid #FFF;
	border-bottom: 1px solid #BBB;
	/* text-shadow: 2px 2px #DFDFDF; */
	margin-bottom: 1px;
	}

#wp_shortstat .column {
	font: 10px/14px Verdana, sans-serif;
	width: 312px;
	height: 606px;
	float: left;
	margin: 0 3px 3px 0;
	}
#wp_shortstat .column .module {
	margin: 0 0 12px 0;
	float: none;
	} 
#wp_shortstat .column .module h3 span {
	right: 5px;
	}
#wp_shortstat .column .module div {
	width: auto;
	height: auto;
	}
#wp_shortstat .column .module div table {
	width: 312px;
	}


/* H STYLES
------------------------------------------------------------------------------*/
#wp_shortstat h1 {
	font: normal 18px/18px Helvetica, Arial;
	color: #999;
	clear: both;
	margin: 0;
	}
#wp_shortstat h1 em {
	color: #710101;
	font-style: normal;
	}
#wp_shortstat h2 {
	font: normal 10px/14px Geneva;
	color: #999;
	clear: both;
	margin: 0 0 16px;
	padding: 0 0 0 2px;
	}

/* COPYRIGHT STYLES
------------------------------------------------------------------------------*/
#wp_shortstat #donotremove { display: block; clear: both; }
	</style> 
<?php
}


add_action('admin_head', 'wp_shortstat_css');

?>
