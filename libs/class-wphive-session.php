<?php

class WPHive_Session {

    // Database Tables
    private $db_tables = array();

    // Session Information
    private $info = null;

    // Current Time
    private $timestamp = null;

    // This Site's Top Level Domain (TLD)
    private $mytld = null;

    // Max Duration of Cookieless Sessions (86400s == 24h)
    private $maxduration = 86400;

    // Session Scoring Modifiers
    private $modifiers = null;


    public function __construct() {
        global $wpdb, $wphive;
        // check if DB tables exist, create missing one(s)
        $db_tables = array( 'pageview', 'session' );
        foreach ( $db_tables as $db_table ) {
            $this->db_tables[$db_table] = $wpdb->prefix . 'wphive_' . $db_table;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $this->db_tables[$db_table] . "'" ) != $this->db_tables[$db_table] ) {
                $this->create_dbtable( $db_table );
            }
        }
        // initialize state
        $this->timestamp = time();
        $this->mytld = $this->url2tld( get_site_url() );
        // start PHP session
        if ( '' == session_id() ) {
            session_start();
        }
        if ( !isset( $_SESSION['wphive_session_init'] ) ) {
            session_regenerate_id();
            $_SESSION['wphive_session_init'] = true;
        }
        // load session information
        $this->info = $this->get_sessioninfo();
    }

    private function create_dbtable( $table ) {
        global $wpdb;
        $wpdb->hide_errors();
        switch ( $table ) {
            case 'pageview':
                $sql = "CREATE TABLE IF NOT EXISTS " . $this->db_tables['pageview'] . " (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `timestamp` int(10) unsigned NOT NULL,
                        `session_id` bigint(20) unsigned NOT NULL,
                        `url` text NOT NULL,
                        `page` varchar(255) NOT NULL,
                        `referer` text NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_session_id` (`session_id`),
                        KEY `idx_page` (`page`)
                      ) DEFAULT CHARSET=utf8";
                break;
            case 'session':
                $sql = "CREATE TABLE IF NOT EXISTS " . $this->db_tables['session'] . " (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `timestamp` int(10) unsigned NOT NULL,
                        `sesskey` char(32) NOT NULL,
                        `userkey` char(32) NOT NULL,
                        `ip` varchar(40) NOT NULL,
                        `hostname` varchar(255) NOT NULL,
                        `useragent` varchar(255) NOT NULL,
                        `referer` varchar(255) NOT NULL,
                        `country` char(2) NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_sesskey` (`sesskey`),
                        KEY `idx_userkey` (`userkey`),
                        KEY `idx_referer` (`referer`),
                        KEY `idx_country` (`country`)
                      ) DEFAULT CHARSET=utf8;";
            break;
        }
        if ( false === $wpdb->query( $sql ) ) {
            unset( $this->db_tables[$table] );
            return false;
        }
        return true;
    }

    public function id() {
        $session_id = ( isset( $this->info['id'] ) ) ? (int) $this->info['id'] : 0;
        return $session_id;
    }

    private function load_modifiers() {
        global $wpdb, $wphive;
        $modifiers = array();
        if ( isset( $this->db_tables['session'] ) ) {
            $sql = sprintf( "SELECT id, country, referer FROM `%s` ORDER BY id ASC", $this->db_tables['session'] );
            $entries = $wpdb->get_results( $sql, ARRAY_A );
            if ( is_array( $entries ) ) {
                foreach ( $entries as $entry ) {
                    $factor_c = 1 + ( $wphive->get_score( 'country', $entry['country'] ) / 100 );
                    $factor_r = 1 + ( $wphive->get_score( 'referer', $entry['referer'] ) / 100 );
                    $modifier = round( $factor_c * $factor_r, 2 );
                    $modifiers[$entry{'id'}] = $modifier;
                }
            }
        }
        return $modifiers;
    }

    public function modifier( $session_id ) {
        if ( is_null( $this->modifiers ) ) {
            $this->modifiers = $this->load_modifiers();
        }
        if ( is_array( $this->modifiers ) && array_key_exists( $session_id, $this->modifiers ) ) {
            return $this->modifiers[$session_id];
        }
        // default modifier factor = 1
        return 1;
    }

    public function url2tld( $url ) {
        static $ccsld = array( 'au', 'br', 'uk' );
        if ( empty( $url ) ) {
            return '';
        }
        $urlParts = @parse_url( $url );
        if ( empty( $urlParts['host'] ) ) {
            return '';
        }
        $hostParts = explode( '.', $urlParts['host'] );
        if ( !isset( $hostParts[0], $hostParts[1] ) ) {
            return '';
        }
        $hostParts = array_reverse( $hostParts );
        if ( in_array( $hostParts[0], $ccsld ) ) {
            if ( isset( $hostParts[2] ) ) {
                return $hostParts[2] . '.' . $hostParts[1] . '.' . $hostParts[0];
            }
        }
        return $hostParts[1] . '.' . $hostParts[0];
    }

    public function sessionkey( $offset = 0 ) {
        $sessiondata = floor( $this->timestamp / $this->maxduration ) - ( $offset * $this->maxduration );
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $sessiondata .= $_SERVER['REMOTE_ADDR'];
        }
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $sessiondata .= $_SERVER['HTTP_USER_AGENT'];
        }
        if ( defined( 'AUTH_KEY' ) ) {
            $sessiondata .= AUTH_KEY;
        }
        return md5( $sessiondata );
    }

    public function get_sessioninfo() {
        global $wpdb;
        if ( !empty( $this->info ) || !isset( $this->db_tables['session'] ) ) {
            return $this->info;
        }
        if ( isset( $_SESSION['wphive_session_id'] ) ) {
            $sql = sprintf( "SELECT * FROM `%s` WHERE id=%u", $this->db_tables['session'], (int) $_SESSION['wphive_session_id'] );
        } else {
            $sql = sprintf( "SELECT * FROM `%s` WHERE sesskey IN ('%s', '%s') AND timestamp>=%u ORDER BY id DESC LIMIT 1", $this->db_tables['session'], $wpdb->escape( $this->sessionkey( 0 ) ), $wpdb->escape( $this->sessionkey( 1 ) ), ( $this->timestamp - $this->maxduration ) );
        }
        $info = $wpdb->get_row( $sql, ARRAY_A );
        if ( !empty( $info ) ) {
            $this->info = $info;
            return $info;
        }
        return null;
    }

    public function url2page( $url ) {
        // host part -> site URL
        $site_url = get_site_url();
        if ( false === stripos( $url, $site_url ) ) {
            $urlParts = @parse_url( $url );
            if ( isset( $urlParts['scheme'], $urlParts['host'] ) ) {
                $url = str_ireplace( $urlParts['scheme'] . '://' . $urlParts['host'], $site_url, $url );
            }
        }
        // exclude admin URLs
        if ( preg_match( '#(wp-(admin|content|login)|\[)#i', $url ) ) {
            return null;
        }
        $page = $url;
        // strip additional query parameters
        if ( false !== ( $pos = strpos( $page, '&' ) ) ) {
            $page = substr( $page, 0, $pos );
        }
        // only keep WordPress category/page/post parameters
        if ( false !== ( $pos = strpos( $page, '?' ) ) ) {
            if ( !preg_match( '#\?(cat|feed|m|p|page_id|tag)\=#i', $page ) ) {
                $page = substr( $page, 0, $pos );
            }
        }
        // return canonical form of the page URL
        return $page;
    }

    private function store_pageview( $url, $referer ) {
        global $wpdb;
        if ( !isset( $this->db_tables['pageview'] ) ) {
            return false;
        }
        $page = $this->url2page( $url );
        if ( empty( $page ) ) {
            return true;
        }
        $result = $wpdb->insert(
            $this->db_tables['pageview'],
            array(
                'timestamp'		=> $this->timestamp,
                'session_id'		=> $this->id(),
                'url'			=> $url,
                'page'			=> $page,
                'referer'		=> $referer
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        if ( ( false !== $result ) && isset( $wpdb->insert_id ) ) {
            return true;
        }
        return false;
    }

    private function store_sessioninfo( $referer = null ) {
        global $wpdb, $wphive;
        if ( !isset( $this->db_tables['session'] ) || isset( $this->info['id'] ) ) {
            return false;
        }
        // insert session record into database
        $ipaddress = '';
        if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        }
        $country = $wphive->ip2country( $ipaddress );
        $hostname = gethostbyaddr( $ipaddress );
        if ( is_null( $referer ) ) {
            $referer = ( isset( $_SERVER['HTTP_REFERER'] ) ) ? $_SERVER['HTTP_REFERER'] : '';
        }
        $referer = $this->url2tld( $referer );
        if ( $referer == $this->mytld ) {
            $referer = '';
        }
        $useragent = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $result = $wpdb->insert(
            $this->db_tables['session'],
            array(
                'timestamp'		=> $this->timestamp,
                'sesskey'		=> $this->sessionkey(),
                'userkey'		=> $wphive->userkey(),
                'ip'			=> $ipaddress,
                'hostname'		=> $hostname,
                'useragent'		=> $useragent,
                'referer'		=> $referer,
                'country'		=> $country
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( ( false !== $result ) && isset( $wpdb->insert_id ) ) {
            $_SESSION['wphive_session_id'] = $wpdb->insert_id;
            $this->info = $this->get_sessioninfo();
            return true;
        }
        return false;
    }

    private function update_sessioninfo() {
        global $wpdb, $wphive;
        if ( !isset( $this->info['id'] ) ) {
            return false;
        }
        $updates = array();
        $userkey = $wphive->userkey();
        if ( empty( $this->info['userkey'] ) && !empty( $userkey ) ) {
            $updates['userkey'] = $userkey;
        }
        if ( count( $updates ) > 0 ) {
            $update_fields = array();
            foreach ( $updates as $key => $value ) {
                $update_fields[] = "`" . $key . "`='" . $wpdb->escape( $value ) . "'";
            }
            $sql = sprintf( "UPDATE `%s` SET %s WHERE id=%u", $this->db_tables['session'], implode( ',', $update_fields ), (int) $this->id() );
            if ( 1 != $wpdb->query( $sql ) ) {
                return false;
            }
        }
        return true;
    }

    public function track( $userkey = '' ) {
        // Ajax Tracking is used instead
        return true;
        /**
        if ( empty( $this->info ) ) {
            // create new session record
            $result = $this->store_sessioninfo();
        } else {
            // update existing session record
            $result = $this->update_sessioninfo();
        }
        if ( false !== $result ) {
            return $this->store_pageview();
        }
        return false;
        **/
    }

    public function ajaxtrack( $data ) {
        $url     = ( isset( $data['url'] ) ) ? $data['url'] : '';
        $referer = ( isset( $data['referer'] ) ) ? $data['referer'] : '';
        if ( empty( $this->info ) ) {
            // create new session record
            $result = $this->store_sessioninfo( $referer );
        } else {
            // update existing session record
            $result = $this->update_sessioninfo();
        }
        if ( ( false !== $result ) && !empty( $url ) ) {
            $this->store_pageview( $url, $referer );
        }
    }

    public function set_userkey_cookie( $userkey ) {
        $expiration   = $this->timestamp + 365 * 24 * 3600; // 1y
        $cookiedomain = $this->mytld;
        if ( empty( $userkey ) ) {
            $cookievalue = false;
        } else {
            $cookievalue = sprintf( "%s|%u|%s", $userkey, $this->timestamp, md5( $userkey . $this->timestamp ) );
        }
        setcookie( 'wphive', $cookievalue, $expiration, '/', $cookiedomain );
        setcookie( 'wpcollider', '', time() - 3600, '/', $cookiedomain );
    }

    public function get_userkey_by_cookie() {
        if ( isset( $_COOKIE['wphive'] ) ) {
            $cookiedata = explode( '|', $_COOKIE['wphive'] );
            if ( isset( $cookiedata[0], $cookiedata[1], $cookiedata[2] ) && ( $cookiedata[2] == md5( $cookiedata[0] . $cookiedata[1] ) ) ) {
                return $cookiedata[0];
            }
        }
        if ( isset( $_COOKIE['wpcollider'] ) ) {
            $cookiedata = explode( '|', $_COOKIE['wpcollider'] );
            if ( isset( $cookiedata[0], $cookiedata[1], $cookiedata[2] ) && ( $cookiedata[2] == md5( $cookiedata[0] . $cookiedata[1] ) ) ) {
                $this->set_userkey_cookie( $cookiedata[0] );
                return $cookiedata[0];
            }
        }
        // default: no (valid) user
        return '';
    }

    public function calculate_leadscores() {
        global $wpdb, $wphive;
        $leadscores = array();
        if ( isset( $this->db_tables['pageview'], $this->db_tables['session'] ) ) {
            $sql = "SELECT s.id AS session_id, s.userkey AS userkey, p.page AS page FROM " . $this->db_tables['session'] . " s, " . $this->db_tables['pageview'] . " p WHERE userkey<>'' AND s.id = p.session_id GROUP BY userkey, page ORDER BY p.timestamp, page ASC";
            $entries = $wpdb->get_results( $sql, ARRAY_A );
            if ( is_array( $entries ) && ( count( $entries ) > 0 ) ) {
                foreach ( $entries as $entry ) {
                    $score = $wphive->get_score( 'pageview', $entry['page'], $entry['session_id'] );
                    if ( array_key_exists( $entry['userkey'], $leadscores ) ) {
                        $leadscores[$entry{'userkey'}]['score'] += $score;
                    } else {
                        $leadscores[$entry{'userkey'}] = array( 'email' => '', 'score' => $score );
                    }
                }
            }
        }
        return $leadscores;
    }

    public function get_interactions( $email = null, $include_details = false ) {
        global $wpdb, $wphive;
        $interactions = array();
        // sessions
        if ( isset( $this->db_tables['session'] ) ) {
            $sql = "SELECT id, timestamp, userkey, ip, referer, country FROM " . $this->db_tables['session'];
            if ( !empty( $email ) ) {
                $userkey = md5( strtolower( $email ) );
                $sql .= sprintf( " WHERE userkey='%s'", $wpdb->escape( $userkey ) );
            } else {
                $sql .= " WHERE userkey<>''";
            }
            $sql .= " ORDER BY userkey ASC, id ASC";
            $entries = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( $entries as $entry ) {
                $ts = $entry['timestamp'];
                $interaction = array();
                $interaction['type']     = 'session';
                $interaction['title']    = 'Session';
                if ( !empty( $entry['country'] ) ) {
                    $interaction['descr'] = 'Visit from ' . $entry['country'] . '; ';
                } else {
                    $interaction['descr'] = 'Visit from unknown; ';
                }
                if ( empty( $entry['referer'] ) ) {
                    $interaction['descr'] .= 'Direct Hit';
                } else {
                    $interaction['descr'] .= 'HTTP Referer: ' . $entry['referer'];
                }
                //$interaction['descr']   .= '; Scoring x ' . $this->modifier( $entry['id'] );
                $interaction['ts']         = $ts;
                $interaction['score']      = 0;
                $interaction['score_id']   = md5( $entry['userkey'] . $entry['id'] );
                $interaction['ip']         = $entry['ip'];
                $interaction['country']    = $entry['country'];
                $interaction['referer']    = $entry['referer'];
                $interaction['session_id'] = $entry['id'];
                if ( $include_details ) {
                    $interaction['details'] = $entry;
                } else {
                    $interaction['details'] = null;
                }
                if ( !array_key_exists( $ts, $interactions ) ) {
                    $interactions[$ts] = array();
                }
                $interactions[$ts][] = $interaction;
            }
        }
        // pageviews
        if ( isset( $this->db_tables['pageview'], $this->db_tables['session'] ) ) {
            $sql = "SELECT s.id AS session_id, s.userkey AS userkey, p.timestamp as timestamp, p.page AS page FROM " . $this->db_tables['session'] . " s, " . $this->db_tables['pageview'] . " p";
            if ( !empty( $email ) ) {
                $userkey = md5( strtolower( $email ) );
                $sql .= sprintf( " WHERE userkey='%s'", $wpdb->escape( $userkey ) );
            } else {
                $sql .= " WHERE userkey<>''";
            }
            $sql .= " AND s.id = p.session_id ORDER BY userkey ASC, timestamp ASC";
            $entries = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( $entries as $entry ) {
                $ts = $entry['timestamp'];
                $uri = str_replace( get_site_url(), '', $entry['page'] );
                $urlParts = @parse_url( $entry['page'] );
                $page = ( isset( $urlParts['path'] ) ) ? $urlParts['path'] : '';
                $interaction = array();
                $interaction['type']       = 'pageview';
                $interaction['title']      = 'Pageview';
                $interaction['descr']      = $entry['page'];
                $interaction['uri']        = $uri;
                $interaction['ts']         = $ts;
                $interaction['base_score'] = $wphive->get_score( 'pageview', $entry['page'] );
                $interaction['score']      = $wphive->get_score( 'pageview', $entry['page'], $entry['session_id'] );
                $interaction['score_id']   = md5( $entry['userkey'] . $entry['page'] );
                $interaction['session_id'] = $entry['session_id'];
                if ( $include_details ) {
                    $interaction['details'] = $entry;
                } else {
                    $interaction['details'] = null;
                }
                if ( !array_key_exists( $ts, $interactions ) ) {
                    $interactions[$ts] = array();
                }
                $interactions[$ts][] = $interaction;
            }
        }
        return $interactions;
    }

    private function fix_pages() {
        global $wpdb, $wphive;
        // pageviews
        if ( isset( $this->db_tables['pageview'], $this->db_tables['session'] ) ) {
            $sql = "SELECT id, url, page FROM " . $this->db_tables['pageview'] . " ORDER BY id ASC";
            $entries = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( $entries as $entry ) {
                $page = $this->url2page( $entry['url'] );
                if ( empty( $page ) || ( $page == $entry['page'] ) ) {
                    continue;
                }
                $sql = sprintf( "UPDATE `%s` SET page='%s' WHERE id=%u", $this->db_tables['pageview'], $wpdb->escape( $page), $entry['id'] );
                print $sql . "<br />";
                $wpdb->query( $sql );
            }
        }
    }

    public function get_itemlist( $tracker = null, $filter = null ) {
        global $wpdb, $wphive;
        $items = array();
        if ( isset( $this->db_tables['session'] ) ) {
            $sql = null;
            switch( $tracker ) {
                case 'country':
                    $sql = "SELECT DISTINCT country FROM " . $this->db_tables['session'] . " ORDER BY country ASC";
                    break;
                case 'pageview':
                    //$this->fix_pages();
                    $sql = "SELECT DISTINCT page FROM " . $this->db_tables['pageview'] . " ORDER BY page ASC";
                    break;
                case 'referer':
                    $sql = "SELECT DISTINCT referer FROM " . $this->db_tables['session'] . " ORDER BY referer ASC";
                    $items['c66c00ae9f18fc0c67d8973bd07dc4cd'] = array( 'key' => 'c66c00ae9f18fc0c67d8973bd07dc4cd', 'type' => 'referer', 'domain' => '[Direct Hits]', 'uri' => '' );
                    break;
                default:
                    return array();
            }
            if ( !empty( $sql ) ) {
                $entries = $wpdb->get_col( $sql );
                if ( is_array( $entries ) ) {
                    foreach ( $entries as $entry ) {
                        if ( empty( $entry ) ) {
                            continue;
                        }
                        $key = md5( $tracker . $entry );
                        $items[$key] = array(
                            'key'        => $key,
                            'type'       => $tracker,
                            'uri'        => $entry,
                            'filtertext' => $entry
                        );
                        if ( 'country' == $tracker ) {
                            $items[$key]['country']      = $entry;
                            $items[$key]['country_cc']   = $entry;
                            $items[$key]['country_name'] = $wphive->getcountrybycc( $entry );
                            $items[$key]['filtertext']   = $items[$key]['country_cc'] . ':' . $items[$key]['country_name'];
                        } elseif ( 'referer' == $tracker ) {
                            $items[$key]['domain'] = $entry;
                        }
                    }
                }
            }
        }
        if ( !empty( $filter ) ) {
            foreach ( $items as $key => $item ) {
                if ( false === stripos( $item['filtertext'], $filter ) ) {
                    unset( $items[$key] );
                }
            }
        }
        return $items;
    }

}
