<?php

class WPHive {

    const version    = '1.0.3';
    const db_version = 2;

    // Database Storage
    private $db_tables = array();

    // Lead Scores
    private $scores = null;

    // Session Module
    private $session = null;

    // Segmentation Module
    private $segmentation = null;

    // Tracking Modules
    private $module_downloads = null;
    private $module_emails = null;

    // API Key
    private $apikey = null;

    // Notification Message
    private $message = null;

    // Countries
    private $countries = null;

    public function __construct() {
        // plugin configuration
        $this->db_tables['scores']  = $GLOBALS['table_prefix'] . 'wphive_scores';
        $this->db_tables['leadtag'] = $GLOBALS['table_prefix'] . 'wphive_leadtag';
        // plugin initialization
        add_action( 'wp_loaded', array( $this, 'init' ) );
    }

    // +++ PLUGIN MANAGEMENT +++

    public function activate() {
        global $wpdb;
        $wpdb->hide_errors();
        // Create Lead Scores DB Table
        if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $this->db_tables['scores'] . "'" ) != $this->db_tables['scores'] ) {
            $sql = "CREATE TABLE IF NOT EXISTS " . $this->db_tables['scores'] . " (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `type` varchar(20) NOT NULL,
                `uri` varchar(255) NOT NULL,
                `score` int(10) NOT NULL
              ) DEFAULT CHARSET=utf8";
            $wpdb->query( $sql );
        }
        // Create Lead Tag DB Table
        if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $this->db_tables['leadtag'] . "'" ) != $this->db_tables['leadtag'] ) {
            $sql = "CREATE TABLE IF NOT EXISTS " . $this->db_tables['leadtag'] . " (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `timestamp` int(10) unsigned NOT NULL,
                `userkey` char(32) NOT NULL,
                `tag` varchar(40) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_userkey_tag` (`userkey`, `tag`),
                KEY `idx_tag` (`tag`)
              ) DEFAULT CHARSET=utf8";
            $wpdb->query( $sql );  
        }
        // update version information
        update_option( 'wphive_version', self::version );
		add_option( 'wphive_db_version', self::db_version );
        // add custom capability: manage_wphive
        $admin_role = get_role( 'administrator' );
        $admin_role->add_cap( 'manage_wphive' );
        $editor_role = get_role( 'editor' );
        $editor_role->add_cap( 'manage_wphive' );
    }

    public function init() {
        global $wpdb;
        // DB housekeeping
        $db_errors = 0;
        if ( ( $db_version = intval( get_option( 'wphive_db_version', 0 ) ) ) < self::db_version ) {
            if ( $db_version < 2 ) {
                // downloads table
                $downloads_table = $GLOBALS['table_prefix'] . 'wphive_download';
                if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $downloads_table . "'" ) == $downloads_table ) {
                    $sql = "ALTER TABLE " . $downloads_table . "
                        ADD COLUMN `list_id` bigint(20) unsigned NOT NULL AFTER `file`,
                        ADD COLUMN `formdata` text NOT NULL AFTER `list_id`";
                    if ( false === $wpdb->query( $sql ) ) {
                        $db_errors++;
                    }
                } else {
                    $db_errors++;
                }
            }
            if ( 0 == $db_errors ) {
                update_option( 'wphive_db_version', self::db_version );
            }
        }
        // start the session
        if ( class_exists( 'WPHive_Session' ) ) {
            $this->session = new WPHive_Session();
        }
        // initialize tracking modules
        if ( class_exists( 'WPHive_Downloads' ) ) {
            $this->module_downloads = new WPHive_Downloads();
        }
        if ( is_plugin_active( 'wysija-newsletters/index.php' ) && class_exists( 'WPHive_Pro_Wysija' ) ) {
            $this->module_emails = new WPHive_Pro_Wysija();
        }
        // initialize segmentation module
        if ( class_exists( 'WPHive_Pro_Segmentation' ) ) {
            $this->segmentation = new WPHive_Pro_Segmentation();
        }
        // detect MaxButtons Pro plugin
        if ( is_plugin_active( 'maxbuttons-pro/maxbuttons-pro.php' ) ) {
            $maxbuttons_table = $GLOBALS['table_prefix'] . 'maxbuttons_buttons';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $maxbuttons_table . "'" ) == $maxbuttons_table ) {
                $this->db_tables['maxbuttons'] = $maxbuttons_table;
            }
        }
        // register tracking JS
        wp_register_script( 'wphive-pvtrack-js', plugins_url( '/js/wphive-pvtrack.js', WPHIVE_FILE ), array( 'jquery' ), self::version, true );
		// popup box
		wp_register_script( 'wphive-popup-custom-js', plugins_url( '/js/wphive-popup.js', WPHIVE_FILE ), array( 'jquery' ), self::version, true );
        // register JS and CSS for Timeline
        wp_register_style( 'wphive-timeline-css', plugins_url( '/css/jquery-vertical-timeline.css', WPHIVE_FILE ), false, '0.1.2' );
        wp_register_script( 'wphive-timeline-handlebars-js', plugins_url( '/js/timeline/libs/handlebars-1.0.rc.1.min.js', WPHIVE_FILE ), array( 'jquery' ), '0.1.2', true );
        wp_register_script( 'wphive-timeline-tabletop-js', plugins_url( '/js/timeline/libs/tabletop.master-20121204.min.js', WPHIVE_FILE ), array( 'wphive-timeline-handlebars-js' ), '0.1.2', true );
        wp_register_script( 'wphive-timeline-isotope-js', plugins_url( '/js/timeline/libs/jquery.isotope.v1.5.21.min.js', WPHIVE_FILE ), array( 'wphive-timeline-tabletop-js' ), '0.1.2', true );
        wp_register_script( 'wphive-timeline-ba-resize-js', plugins_url( '/js/timeline/libs/jquery.ba-resize.v1.1.min.js', WPHIVE_FILE ), array( 'wphive-timeline-isotope-js' ), '0.1.2', true );
        wp_register_script( 'wphive-timeline-imagesloaded-js', plugins_url( '/js/timeline/libs/jquery.imagesloaded.v2.1.0.min.js', WPHIVE_FILE ), array( 'wphive-timeline-ba-resize-js' ), '0.1.2', true );
        wp_register_script( 'wphive-timeline-js', plugins_url( '/js/timeline/jquery-vertical-timeline.min.js', WPHIVE_FILE ), array( 'wphive-timeline-imagesloaded-js' ), '0.1.2', true );
        // register CSS for World Flags Sprite
        wp_register_style( 'wphive-flags16-css', plugins_url( '/css/flags16.css', WPHIVE_FILE ), false, '20130501' );
        wp_register_style( 'wphive-flags32-css', plugins_url( '/css/flags32.css', WPHIVE_FILE ), false, '20130501' );
        // register Ajax handlers
        add_action( 'wp_ajax_wphive_admin', array( $this, 'ajax_admin' ) );
        add_action( 'wp_ajax_wphive', array( $this, 'ajax_nopriv' ) );
        add_action( 'wp_ajax_nopriv_wphive', array( $this, 'ajax_nopriv' ) );
        // enqueue scripts for the frontend
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
        // add menu entry to dashboard
        if ( current_user_can( 'manage_wphive' ) ) {
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            $this->admin_redirect();
        }
        // warn if both the free and the paid version are installed
        if ( is_plugin_active( 'wp-hive/wp-hive.php' ) && is_plugin_active( 'wp-hive-pro/wp-hive.php' ) ) {
            $this->message = "Important Notice: Both the free version and the paid version of this plugin are currently activated. Please deactivate the free version (WP HIVE), as its functionality is superseded by the paid version (WP HIVE Pro).";
        }
        // user tracking
        $this->track();
    }

    // +++ API ACCESS +++

    public function apikey() {
        if ( empty( $this->apikey ) ) {
            $this->apikey = get_option( 'wphive_apikey', 'wphive' );
        }
        return $this->apikey;
    }

    // +++ USER TRACKING +++

    public function userkey( $new_userkey = null ) {
        static $userkey = '';
        if ( is_null( $new_userkey ) ) {
            // get userkey
            if ( empty( $userkey ) && isset( $this->session ) ) {
                $userkey = $this->session->get_userkey_by_cookie();
            }
        } else {
            // set userkey
            if ( empty( $new_userkey ) ) {
                $userkey = '';
            } elseif ( preg_match( '/^[0-9a-f]{32}$/i', $new_userkey ) ) {
                $userkey = strtolower( $new_userkey );
            } else {
                $userkey = md5( strtolower( $new_userkey ) );
            }
            if ( isset( $this->session ) ) {
                $this->session->set_userkey_cookie( $userkey );
            }
        }
        return $userkey;
    }

    public function session_id() {
        if ( isset( $this->session ) ) {
            return $this->session->id();
        }
        return 0;
    }

    private function track() {
        global $wpdb;
        // set userkey (manually, for testing purposes)
        if ( isset( $_GET['wphive-user'] ) ) {
            $this->userkey( $_GET['wphive-user'] );
        }
        // get userkey
        $userkey = $this->userkey();
        // call tracking modules
        if ( isset( $this->session ) ) {
            $this->session->track( $userkey );
        }
        if ( isset( $this->module_downloads ) ) {
            $this->module_downloads->track( $userkey );
        }
        if ( isset( $this->module_emails ) ) {
            $this->module_emails->track( $userkey );
        }
    }

    public function gaq_push( $args, $script_tags = false ) {
        $js = '_gaq.push(' . json_encode( $args ) . ');';
        if ( $script_tags ) {
            return '<script type="text/javascript">' . $js . '</script>';
        } else {
            return $js;
        }
    }

    // +++ SCORING +++

    private function save_scores( $data ) {
        global $wpdb;
        $save_performed = false;
        $new_scores     = array();
        foreach ( $data as $k => $v ) {
            if ( 0 === strpos( $k, 'score-' ) ) {
                $key              = substr( $k, 6 );
                $new_scores[$key] = (int) $v;
            }
        }
        if ( 0 == count( $new_scores ) ) {
            return false;
        }
        $items = array();
        if ( in_array( $data['wphive-save-scores-type'], array( 'country', 'download', 'email', 'pageview', 'referer' ) ) ) {
            $items = $this->get_itemlist( $data['wphive-save-scores-type'] );
        }
        $scores = $this->load_scores();
        foreach ( $items as $key => $item ) {
            if ( array_key_exists( $key, $new_scores ) ) {
                if ( $item['score'] != $new_scores[$key] ) {
                    if ( array_key_exists( $key, $scores ) ) {
                        $wpdb->update(
                            $this->db_tables['scores'],
                            array( 'score' => $new_scores[$key] ),
                            array( 'id' => $scores[$key]['id'] ),
                            array( '%d' ),
                            array( '%d' )
                        );
                    } else {
                        $wpdb->insert(
                            $this->db_tables['scores'],
                            array(
                                'type'  => $item['type'],
                                'uri'   => $item['uri'],
                                'score' => $new_scores[$key]
                            ),
                            array( '%s', '%s', '%d' )
                        );
                    }
                    $save_performed = true;
                }
            }
        }
        $this->scores = $this->load_scores();
        return $save_performed;
    }

    private function load_scores() {
        global $wpdb;
        $scores  = array();
        $sql     = "SELECT id, type, uri, score FROM " . $this->db_tables['scores'];
        $entries = $wpdb->get_results( $sql, ARRAY_A );
        foreach ( $entries as $entry ) {
            $entrykey          = md5( $entry['type'] . $entry['uri'] );
            $scores[$entrykey] = array(
                'id'    => $entry['id'],
                'type'  => $entry['type'],
                'uri'   => $entry['uri'],
                'score' => $entry['score']
            );
        }
        return $scores;
    }

    public function get_score( $type, $uri, $session_id = null ) {
        if ( is_null( $this->scores ) ) {
            $this->scores = $this->load_scores();
        }
        $key = md5( $type . $uri );
        $session_modifier = 1;
        if ( isset( $this->session ) && !empty( $session_id ) ) {
            $session_modifier = $this->session->modifier( $session_id );
        }
        if ( isset( $this->scores[$key] ) && ( $this->scores[$key]['type'] == $type ) && ( $this->scores[$key]['uri'] == $uri ) ) {
            if ( empty( $session_id ) ) {
                return (int) $this->scores[$key]['score'];
            } else {
                return round( floatval( $this->scores[$key]['score'] * $session_modifier ), 2 );
            }
        } else {
            if ( in_array( $type, array( 'country', 'referer' ) ) ) {
                // default score modifier = 0%
                return 0;
            } else {
                // default score
                return $session_modifier;
            }
        }
    }

    private function get_leads( $filter = null, $include_zeroscore = true ) {
        global $wpdb;
        $leads = array();
        // retrieve user records
        if ( isset( $this->module_emails ) ) {
            $users = $this->module_emails->get_users();
            foreach ( $users as $user ) {
                $profile      = array( 'email' => $user['email'], 'name' => $user['name'] );
                $interactions = $this->get_interactions( $user['email'] );
                $lead         = new WPHive_Lead( $profile, $interactions );
                $leads[$lead->userkey] = $lead;
            }
        }
        // retrieve lead scores
        $leadscores = $this->calculate_leadscores();
        foreach ( $leadscores as $userkey => $leadscore ) {
            if ( !array_key_exists( $userkey, $leads ) ) {
                $profile      = array( 'email' => $leadscore['email'] );
                $interactions = $this->get_interactions( $leadscore['email'] );
                $lead         = new WPHive_Lead( $profile, $interactions );
                $leads[$lead->userkey] = $lead;
            }
        }
        if ( !$include_zeroscore ) {
            foreach ( $leads as $userkey => $lead ) {
                if ( 0 == $lead->stats['score'] ) {
                    unset( $leads[$userkey] );
                }
            }
        }
        if ( !empty( $filter ) ) {
            foreach ( $leads as $userkey => $lead ) {
                if ( ( false === stripos( $lead->email, $filter ) ) && ( false === stripos( $lead->name, $filter ) ) && ( false === stripos( implode( ',', $lead->tags ), $filter ) ) ) {
                    unset( $leads[$userkey] );
                }
            }
        }
        return $leads;
    }

    private function get_lead_properties( $filter = null, $include_zeroscore = true ) {
        $leads = $this->get_leads( $filter, $include_zeroscore );
        $properties = array();
        foreach ( $leads as $userkey => $lead ) {
            $properties[$userkey] = $lead->properties();
        }
        return $properties;
    }

    public function search_leads( $ruleset ) {
        $leads = $this->get_lead_properties();
        foreach ( $ruleset as $rule ) {
            foreach ( $rule as $condition_type => $condition_value ) {
                foreach ( $leads as $id => $lead ) {
                    switch ( $condition_type ) {
                        case 'firstInteractionBefore':
                            if ( empty( $lead['first'] ) || ( $lead['first'] >= $condition_value ) ) {
                                unset( $leads[$id] );
                            }
                            break;
                        case 'firstInteractionAfter':
                            if ( empty( $lead['first'] ) || ( $lead['first'] <= $condition_value ) ) {
                                unset( $leads[$id] );
                            }
                            break;
                        case 'latestInteractionBefore':
                            if ( empty( $lead['latest'] ) || ( $lead['latest'] >= $condition_value ) ) {
                                unset( $leads[$id] );
                            }
                            break;
                        case 'latestInteractionAfter':
                            if ( empty( $lead['latest'] ) || ( $lead['latest'] <= $condition_value ) ) {
                                unset( $leads[$id] );
                            }
                            break;
                        case 'leadScoreGreaterThan':
                            if ( $lead['score'] <= $condition_value ) {
                                unset( $leads[$id] );
                            }
                            break;
                        case 'leadScoreLessThan':
                            if ( $lead['score'] >= $condition_value ) {
                                unset( $leads[$id] );
                            }
                            break;
                        case 'countryIn':
                            if ( is_array( $condition_value ) && is_array( $lead['countries'] ) ) {
                                $intersect = array_intersect( $lead['countries'], $condition_value );
                                if ( 0 == count( $intersect ) ) {
                                    unset( $leads[$id] );
                                }
                            }
                            break;
                        case 'refererIn':
                            if ( is_array( $condition_value ) && is_array( $lead['referers'] ) ) {
                                $intersect = array_intersect( $lead['referers'], $condition_value );
                                if ( 0 == count( $intersect ) ) {
                                    unset( $leads[$id] );
                                }
                            }
                            break;
                        case 'tagIn':
                            $tags = explode( ',', $lead['tags'] );
                            if ( is_array( $condition_value ) && is_array( $tags ) ) {
                                $intersect = array_intersect( $tags, $condition_value );
                                if ( 0 == count( $intersect ) ) {
                                    unset( $leads[$id] );
                                }
                            }
                            break;
                        case 'listIn':
                            if ( is_array( $condition_value ) && is_array( $lead['mailinglists'] ) ) {
                                $intersect = array_intersect( $lead['mailinglists'], $condition_value );
                                if ( 0 == count( $intersect ) ) {
                                    unset( $leads[$id] );
                                }
                            }
                            break;
                        case 'downloadIn':
                            if ( is_array( $condition_value ) && is_array( $lead['downloads'] ) ) {
                                $intersect = array_intersect( $lead['downloads'], $condition_value );
                                if ( 0 == count( $intersect ) ) {
                                    unset( $leads[$id] );
                                }
                            }
                            break;
                    }
                }
            }
        }
        return $leads;
    }

    private function calculate_leadscores() {
        global $wpdb;
        $leadscores = array();
        // downloads
        if ( isset( $this->module_downloads ) ) {
            $leadscores_downloads = $this->module_downloads->calculate_leadscores();
            $leadscores = $leadscores_downloads;
        }
        // emails
        if ( isset( $this->module_emails ) ) {
            $leadscores_emails = $this->module_emails->calculate_leadscores();
            foreach ( $leadscores_emails as $userkey => $leadscore ) {
                if ( array_key_exists( $userkey, $leadscores ) ) {
                    $leadscores[$userkey]['score'] += $leadscore['score'];
                } else {
                    $leadscores[$userkey] = array(
                        'email' => $leadscore['email'],
                        'score' => $leadscore['score']
                    );
                }
            }
        }
        // sessions
        if ( isset( $this->session ) ) {
            $leadscores_sessions = $this->session->calculate_leadscores();
            foreach ( $leadscores_sessions as $userkey => $leadscore ) {
                if ( array_key_exists( $userkey, $leadscores ) ) {
                    $leadscores[$userkey]['score'] += $leadscore['score'];
                }
            }
        }
        // pageviews
        if ( isset( $this->module_pageviews ) ) {
            $leadscores_pageviews = $this->module_pageviews->calculate_leadscores();
            foreach ( $leadscores_pageviews as $userkey => $leadscore ) {
                if ( array_key_exists( $userkey, $leadscores ) ) {
                    $leadscores[$userkey]['score'] += $leadscore;
                }
            }
        }
        return $leadscores;
    }

    private function get_interactions( $email = null, $include_details = false ) {
        $interactions = $this->session->get_interactions( $email );
        // downloads
        if ( isset( $this->module_downloads ) ) {
            $interactions_downloads = $this->module_downloads->get_interactions( $email, $include_details );
            foreach ( $interactions_downloads as $ts => $interactions_ts ) {
                if ( !array_key_exists( $ts, $interactions ) ) {
                    $interactions[$ts] = array();
                }
                foreach ( $interactions_ts as $interaction ) {
                    $interactions[$ts][] = $interaction;
                }
            }
        }
        // emails
        if ( isset( $this->module_emails ) ) {
            $interactions_emails = $this->module_emails->get_interactions( $email, $include_details );
            foreach ( $interactions_emails as $ts => $interactions_ts ) {
                if ( !array_key_exists( $ts, $interactions ) ) {
                    $interactions[$ts] = array();
                }
                foreach ( $interactions_ts as $interaction ) {
                    $interactions[$ts][] = $interaction;
                }
            }
        }
        // sort
        ksort( $interactions );
        return $interactions;
    }

    // +++ SCORING ITEMS +++

    public function get_itemlist( $tracker, $filter = null ) {
        global $wpdb;
        $items = array();
        switch ( $tracker ) {
            case 'country':
            case 'pageview':
            case 'referer':
                if ( isset( $this->session ) ) {
                    $items = $this->session->get_itemlist( $tracker, $filter );
                }
                break;
            case 'download':
                if ( isset( $this->module_downloads ) ) {
                    $items = $this->module_downloads->get_itemlist( $tracker, $filter );
                }
                break;
            case 'email':
                if ( isset( $this->module_emails ) ) {
                    $items = $this->module_emails->get_itemlist( $tracker, $filter );
                }
                break;
        }
        foreach ( $items as $key => $item ) {
            $items[$key]['score'] = $this->get_score( $item['type'], $item['uri'] );
        }
        return $items;
    }

    // +++ MAILINGLIST SUBSCRIPTIONS +++

    public function mailinglists() {
        $lists = array();
        if ( isset( $this->module_emails ) ) {
            $lists = $this->module_emails->lists();
        }
        return $lists;
    }

    public function subscriptions( $email = null ) {
        $subscriptions = array();
        if ( isset( $this->module_emails ) ) {
            $subscriptions = $this->module_emails->subscriptions( $email );
        }
        return $subscriptions;
    }

    public function ml_subscribe( $email, $list, $name = null ) {
        if ( isset( $this->module_emails ) ) {
            return $this->module_emails->subscribe( $email, $list, $name );
        } else {
            return false;
        }
    }

    public function ml_unsubscribe( $email, $list ) {
        if ( isset( $this->module_emails ) ) {
            return $this->module_emails->unsubscribe( $email, $list );
        } else {
            return false;
        }
    }

    public function ml_getuserbyemail( $email ) {
        if ( isset( $this->module_emails ) ) {
            return $this->module_emails->getuserbyemail( $email );
        } else {
            return false;
        }
    }

    // +++ TAGS +++

    public function tag_userkey( $identifier ) {
        if ( preg_match( '#[0-9a-f]{32}#i', $identifier ) ) {
            return $identifier;
        } elseif ( is_email( $identifier ) ) {
            return md5( strtolower( $identifier ) );
        } else {
            return '';
        }
    }

    public function tag( $users, $tags ) {
        global $wpdb;
        if ( !isset( $this->db_tables['leadtag'] ) ) {
            return false;
        }
        if ( is_string( $users ) ) {
            $users = array( $users );
        }
        if ( is_string( $tags ) ) {
            $tags = array( $tags );
        }
        if ( !is_array( $users ) || !is_array( $tags ) ) {
            return false;
        }
        $ts = time();
        foreach ( $users as $user ) {
            foreach ( $tags as $tag ) {
                $userkey = $this->tag_userkey( $user );
                if ( empty( $userkey ) ) {
                    continue;
                }
                $sql = sprintf( "INSERT INTO `%s` (timestamp, userkey, tag) VALUES (%u, '%s', '%s') ON DUPLICATE KEY UPDATE timestamp=%u", $this->db_tables['leadtag'], $ts, $wpdb->escape( $userkey ), $wpdb->escape( trim( $tag ) ), $ts );
                $wpdb->query( $sql );
            }
        }
        return true;
    }

    public function untag( $users, $tags ) {
        global $wpdb;
        if ( !isset( $this->db_tables['leadtag'] ) ) {
            return false;
        }
        if ( is_string( $users ) ) {
            $users = array( $users );
        }
        if ( is_string( $tags ) ) {
            $tags = array( $tags );
        }
        if ( !is_array( $users ) || !is_array( $tags ) ) {
            return false;
        }
        foreach ( $users as $index => $user ) {
            $userkey = $this->tag_userkey( $user );
            if ( empty( $userkey ) ) {
                unset( $users[$index] );
                continue;
            }
            $users[$index] = "'" . $wpdb->escape( $userkey ) . "'";
        }
        if ( ( 0 == count( $users ) ) || ( 0 == count( $tags ) ) ) {
            return true;
        }
        $userkey_list = implode( ',', $users );
        foreach ( $tags as $key => $tag ) {
            $tags[$key] = "'" . $wpdb->escape( trim( $tag ) ) . "'";
        }
        $tag_list = implode( ',', $tags );
        $sql = sprintf( "DELETE FROM `%s` WHERE userkey IN (%s) AND tag IN (%s)", $this->db_tables['leadtag'], $userkey_list, $tag_list );
        if ( false === ( $result = $wpdb->query( $sql ) ) ) {
            return false;
        }
        return true;
    }

    public function tags( $users = null ) {
        global $wpdb;
        if ( !isset( $this->db_tables['leadtag'] ) ) {
            return array();
        }
        if ( is_null( $users ) ) {
            $sql = sprintf( "SELECT DISTINCT tag FROM `%s` ORDER BY tag ASC", $this->db_tables['leadtag'] );
            return $wpdb->get_col( $sql );
        } elseif ( is_string( $users ) ) {
            $userkey = $this->tag_userkey( $users );
            if ( empty( $userkey ) ) {
                return array();
            }
            $sql = sprintf( "SELECT DISTINCT tag FROM `%s` WHERE userkey='%s' ORDER BY tag ASC", $this->db_tables['leadtag'], $wpdb->escape( $userkey ) );
            return $wpdb->get_col( $sql );
        } elseif ( is_array( $users ) ) {
            $tags = array();
            foreach ( $users as $index => $user ) {
                $userkey = $this->tag_userkey( $user );
                if ( empty( $userkey ) ) {
                    continue;
                }
                $tags[$userkey] = $this->tags( $userkey );
            }
            return $tags;
        }
        return array();
    }

    public function ip2country( $ip ) {
        if ( class_exists( 'WPHive_Pro_Api' ) ) {
            return WPHive_Pro_Api::ip2country( $ip );
        } else {
            return '';
        }
    }


    // +++ COUNTRY CODES AND COUNTRY NAMES

    private function load_countries() {
        if ( empty( $this->countries ) ) {
            $data = @file_get_contents( WPHIVE_DIR . '/libs/countries.ser' );
            $countries = @unserialize( $data );
            if ( !empty( $countries ) ) {
                $this->countries = $countries;
            }
        }
        return $this->countries;
    }

    public function getcountrybycc( $cc ) {
        $this->load_countries();
        $cc = strtoupper( $cc );
        if ( is_array( $this->countries ) && array_key_exists( $cc, $this->countries ) ) {
            return $this->countries[$cc];
        }
        return $cc;
    }

    // +++ BUTTONS (MAXBUTTONS PRO PLUGIN) +++

    public function buttons() {
        global $wpdb;
        $buttons = array();
        if ( !empty( $this->db_tables['maxbuttons'] ) ) {
            $sql = sprintf( "SELECT id, name FROM `%s` ORDER BY id ASC", $this->db_tables['maxbuttons'] );
            $entries = $wpdb->get_results( $sql, ARRAY_A );
            if ( is_array( $entries ) ) {
                foreach ( $entries as $entry ) {
                    $buttons[$entry{'id'}] = $entry['name'];
                }
            }
        }
        return $buttons;
    }

    // +++ AJAX HANDLERS +++

    public function ajax_admin() {
        global $wpdb;
        $template = null;
        switch ( $_POST['task'] ) {
            case 'load_leaddata':
                $email = $_POST['email'];
                if ( is_email( $email ) ) {
                    $profile      = array( 'email' => $email );
                    $interactions = $this->get_interactions( $email );
                    $lead         = new WPHive_Lead( $profile, $interactions );
                    $template     = WPHIVE_DIR . '/templates/lead-overview.php';
                }
                break;
            default:
                echo 'Illegal Task';
        }
        if ( !empty( $template ) ) {
            if ( file_exists( $template ) ) {
                include( $template );
            } else {
                echo 'Cannot load template';
            }
        }
        die();
    }

    public function ajax_nopriv() {
        global $wpdb;
        $template = null;
        switch ( $_POST['task'] ) {
            case 'pvtrack':
                $this->session->ajaxtrack( $_POST );
                break;
            default:
                echo 'Illegal Task';
        }
        if ( !empty( $template ) ) {
            if ( file_exists( $template ) ) {
                include( $template );
            } else {
                echo 'Cannot load template';
            }
        }
        die();
    }

    // +++ FRONTEND +++

    public function frontend_enqueue_scripts() {
        $uri = ( isset( $_SERVER['REQUEST_URI'] ) ) ? $_SERVER['REQUEST_URI'] : '';
        $referer = ( isset( $_SERVER['HTTP_REFERER'] ) ) ? $_SERVER['HTTP_REFERER'] : '';
        $params = array( 'ajaxurl' => admin_url('admin-ajax.php'), 'uri' => $uri, 'referer' => $referer );
        wp_enqueue_script( 'wphive-pvtrack-js' );
        wp_localize_script( 'wphive-pvtrack-js', 'WPHive', $params );
    }

    // +++ DASHBOARD +++

    public function admin_menu() {
        // Main Menu
        add_menu_page( 'WP HIVE', 'WP HIVE', 'manage_wphive', 'wp-hive', array( $this, 'admin_page' ) );
        // Leads
        add_submenu_page( 'wp-hive', 'Leads', 'Leads', 'manage_wphive', 'wp-hive', array( $this, 'admin_page' ) );
        // Segmentation
        if ( isset( $this->segmentation ) ) {
            add_submenu_page( 'wp-hive', 'Segmentation', 'Segmentation', 'manage_wphive', 'wp-hive-segmentation', array( $this, 'admin_page' ) );
        }
        // Triggers
        if ( isset( $this->triggers ) ) {
            add_submenu_page( 'wp-hive', 'Triggers', 'Triggers', 'manage_wphive', 'wp-hive-triggers', array( $this, 'admin_page' ) );
        }
        // Scoring Setup
        add_submenu_page( 'wp-hive', 'Scoring Setup', 'Scoring', 'manage_wphive', 'wp-hive-scoring', array( $this, 'admin_page' ) );
        // Downloads Setup
        add_submenu_page( 'wp-hive', 'Downloads and Forms', 'Downloads', 'manage_wphive', 'wp-hive-downloads', array( $this, 'admin_page' ) );
        // Enqueue JS and CSS
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
    }

    public function admin_scripts( $hook ) {
		global $wp_scripts;
        if ( false === strpos( $hook, 'wp-hive' ) ) {
            return;
        }
		// jQuery UI Dialog
		$ui = $wp_scripts->query('jquery-ui-core');
		$protocol = is_ssl() ? 'https' : 'http';
		$url = $protocol . '://ajax.googleapis.com/ajax/libs/jqueryui/' . $ui->ver . '/themes/smoothness/jquery-ui.min.css';
		wp_enqueue_style( 'jquery-ui-smoothness', $url );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'wphive-popup-custom-js' );
        // Timeline
        wp_enqueue_style( 'wphive-timeline-css' );
        wp_enqueue_script( 'wphive-timeline-handlebars-js' );
        wp_enqueue_script( 'wphive-timeline-tabletop-js' );
        wp_enqueue_script( 'wphive-timeline-isotope-js' );
        wp_enqueue_script( 'wphive-timeline-ba-resize-js' );
        wp_enqueue_script( 'wphive-timeline-imagesloaded-js' );
        wp_enqueue_script( 'wphive-timeline-js' );
        // World Flags Sprite
        wp_enqueue_style( 'wphive-flags16-css' );
        wp_enqueue_style( 'wphive-flags32-css' );
    }

    public function html_select( $name, $options, $multiple = true, $size = 5, $onchange = '' ) {
        if ( !empty( $onchange ) ) {
            $onchange = ' onchange="' . esc_attr( $onchange ) . '"';
        } else {
            $onchange = '';
        }
        if ( ( $options_size = count( $options ) ) < $size ) {
            $size = $options_size;
        }
        if ( $multiple ) {
            echo '<select id="' . esc_attr( $name ) . '" multiple="multiple" name="' . esc_attr( $name ) . '[]"' . $onchange . ' size="' . esc_attr( $size ) . '">';
        } else {
            echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . $onchange . ' size="' . esc_attr( $size ) . '">';
        }
        foreach ( $options as $index => $description ) {
            echo '<option value="' . esc_attr( $index ) . '">' . esc_html( $description ) . '</option>';
        }
        echo '</select>';
    }

    public function admin_page() {
        // save scores if POST request
        $settings_updated = false;
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            if ( isset( $_POST['wphive-save-scores-type'] ) ) {
                $settings_updated = $this->save_scores( $_POST );
            }
        }
        // tabs
        $tabs = array( 'wp-hive'              => 'Leads',
                       'wp-hive-segmentation' => 'Segmentation',
                       'wp-hive-triggers'     => 'Triggers',
                       'wp-hive-scoring'      => 'Scoring Setup',
                       'wp-hive-downloads'    => 'Downloads and Forms'
        );
        if ( isset( $_REQUEST['page'] ) && array_key_exists( $_REQUEST['page'], $tabs ) ) {
            $current_tab = $_REQUEST['page'];
        } else {
            $current_tab = 'wp-hive';
        }
        echo '<div class="wrap">';
        echo '<div id="icon-themes" class="icon32"><br /></div>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $tab => $name ) {
            $class = ( $tab == $current_tab ) ? ' nav-tab-active' : '';
            if ( ( 'wp-hive-segmentation' == $tab ) && !isset( $this->segmentation ) ) {
                echo '<span class="nav-tab"><del>' . $name . '</del></span>';
            } elseif ( ( 'wp-hive-triggers' == $tab ) && !isset( $this->triggers ) ) {
                echo '<span class="nav-tab"><del>' . $name . '</del></span>';
            } else {
                echo '<a class="nav-tab' . $class . '" href="?page=' . $tab . '">' . $name . '</a>';
            }
        }
        echo '</h2>';
        echo '<h1>' . $tabs[$current_tab] . '</h1>';
        if ( $settings_updated ) {
            echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
        }
        // show debugging message
        if ( isset( $this->message ) ) {
            echo '<div id="message" class="updated fade"><p><strong>' . $this->message . '</strong></p></div>';
        }
		// popup box
		echo '<div id="wphive-popup" align="center" class="ui-widget" title="Lead Details"></div>';
        // search box filter
        $filter = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : null;
        // table
        $list_table = null;
        switch ( $current_tab ) {
            case 'wp-hive':
                $list_table = new WPHive_List_Table( 'leads', $this->get_lead_properties( $filter, true ) );
                break;
            case 'wp-hive-segmentation':
                if ( isset( $this->segmentation ) ) {
                    $this->segmentation->admin_page();
                }
                break;
            case 'wp-hive-scoring':
                // determine which table to show
                $current_table = 'download';
                $tables = array( 'download' => 'File Downloads', 'pageview' => 'Pages Visited', 'email' => 'Emails Sent', 'country' => 'Country Score Modifiers', 'referer' => 'HTTP Referer Score Modifiers' );
                if ( isset( $_REQUEST['table'] ) && array_key_exists( $_REQUEST['table'], $tables ) ) {
                    $current_table = $_REQUEST['table'];
                }
                echo '<p><b>View: ';
                foreach ( $tables as $table => $name ) {
                    if ( $current_table == $table ) {
                        echo $name . '&nbsp;&nbsp;';
                    } else {
                        echo '<a href="?page=wp-hive-scoring&amp;table=' . $table . '">' . $name . '</a>&nbsp;&nbsp;';
                    }
                }
                echo '</b></p>';
                echo '<h2>' . $tables[$current_table] . '</h2>';
                $list_table = new WPHive_List_Table( 'scoring_' . $current_table, $this->get_itemlist( $current_table, $filter ) );
                break;
            case 'wp-hive-downloads':
                if ( isset( $this->module_downloads ) ) {
                    $this->module_downloads->admin_page();
                }
                break;
        }
        // form
        if ( !is_null( $list_table ) ) {
            $post_url = admin_url( 'admin.php?page=' . $current_tab );
            if ( !empty( $current_table ) ) {
                $post_url .= '&amp;table=' . $current_table;
            }
            if ( isset( $_GET['orderby'] ) ) {
                $post_url .= '&amp;orderby=' . esc_html( $_GET['orderby'] );
            }
            if ( isset( $_GET['order'] ) ) {
                $post_url .= '&amp;order=' . esc_html( $_GET['order'] );
            }
            if ( isset( $_GET['s'] ) ) {
                $post_url .= '&amp;s=' . esc_html( $_GET['s'] );
            }
            echo '<form method="post" action="' . $post_url . '">';
            wp_nonce_field( 'wp-hive-admin' );
            $list_table->prepare_items();
            $list_table->search_box( __('Search'), 'wphive' );
            $list_table->display();
            // submit button for Scoring Setup pages
            if ( ( 'wp-hive-scoring' == $current_tab ) && isset( $current_table ) ) {
                echo '<input type="hidden" name="wphive-save-scores-type" value="' . $current_table . '" />';
                echo '<input id="wphive-save-scores" class="button" name="wphive-save-scores" type="submit" value="Save Changes" />';
            }
            // end of form
            echo '</form>';
            echo '</div>';
        }
    }

    public function admin_redirect() {
        $admin_url = parse_url( admin_url( 'admin.php?page=wp-hive' ) );
        if ( isset( $admin_url['path'], $admin_url['query'] ) ) {
            $admin_uri = $admin_url['path'] . '?' . $admin_url['query'];
            if ( false !== strpos( $_SERVER['REQUEST_URI'], $admin_uri ) ) {
                if ( !empty( $_POST['s'] ) && ( !isset( $_GET['s'] ) || ( $_GET['s'] != $_POST['s'] ) ) ) {
                    wp_redirect( add_query_arg( 's', urlencode(  $_POST['s'] ) ) );
                    exit;
                }
            }
        }
    }

}