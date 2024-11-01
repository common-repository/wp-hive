<?php

class WPHive_Lead {

    // Userkey
    private $userkey = '';

    // Profile
    private $profile = array();

    // Extended Profile
    private $xprofile = array();

    // Mailinglist Module User Record
    private $emailuser = null;

    // Tags
    private $tags = array();

    // Interactions
    private $interactions = array();

    // Stats
    private $stats = array();

    public function __construct( $profile, $interactions = null ) {
        global $wphive;
        if ( is_array( $profile ) ) {
            if ( !empty( $profile['email'] ) && is_email( $profile['email'] ) ) {
                $this->userkey = md5( strtolower( $profile['email'] ) );
                $wpuser = get_user_by( 'email', $profile['email'] );
                if ( !empty( $wpuser ) ) {
                    $profile['firstname'] = $wpuser->first_name;
                    $profile['lastname']  = $wpuser->last_name;
                    if ( empty( $profile['name'] ) ) {
                        $profile['name'] = trim( sprintf( "%s %s", $profile['firstname'], $profile['lastname'] ) );
                    }
                }
                if ( empty( $profile['name'] ) ) {
                    $emailuser = $wphive->ml_getuserbyemail( $profile['email'] );
                    if ( !empty( $emailuser ) ) {
                        $profile['firstname'] = $emailuser['firstname'];
                        $profile['lastname']  = $emailuser['lastname'];
                        $profile['name']      = trim( sprintf( "%s %s", $profile['firstname'], $profile['lastname'] ) );
                        $this->emailuser      = $emailuser;
                    }
                }
                $this->tags = $wphive->tags( $profile['email'] );
                natcasesort( $this->tags );
            }
            if ( !empty( $profile['name'] ) ) {
                $profile['name'] = trim( $profile['name'] );
            }
            $this->profile = $profile;
        }
        if ( is_array( $interactions ) ) {
            $this->interactions = $interactions;
            $this->calculate_stats();
        }
        $this->load_xprofile( true, false );
    }

    // PROPERTIES

    public function __get( $name ) {
        if ( 'userkey' == $name ) {
            return $this->userkey;
        } elseif ( array_key_exists( $name, $this->profile ) ) {
            return $this->profile[$name];
        } elseif ( 'xprofile' == $name ) {
            return $this->xprofile;
        } elseif ( 'tags' == $name ) {
            return $this->tags;
        } elseif( 'subscriptions' == $name ) {
            return $this->subscriptions();
        } elseif ( 'interactions' == $name ) {
            return $this->interactions;
        } elseif ( 'stats' == $name ) {
            return $this->stats;
        } else {
            return '';
        }
    }

    public function __isset( $name ) {
        $value = $this->__get( $name );
        return !empty( $value );
    }

    public function completion( $type = 'both' ) {
        $completion = array( 0.00, '0 %' );
        if ( !empty( $this->profile['email'] ) ) {
            $completion = array( 0.33, '33 %' );
            if ( !empty( $this->profile['name'] ) ) {
                $completion = array( 0.50, '50 %' );
                if ( is_array( $this->xprofile ) && ( count( $this->xprofile ) > 0 ) ) {
                    $completion = array( 0.75, '75 %' );
                }
            }
        }
        switch ( $type ) {
            case 'numeric':
                return $completion[0];
                break;
            case 'pct_string':
                return $completion[1];
                break;
            case 'both':
            default:
                return $completion;
        }
    }

    public function emailuser() {
        global $wphive;
        if ( empty( $this->emailuser ) && isset( $this->profile['email'] ) ) {
            $emailuser = $wphive->ml_getuserbyemail( $this->profile['email'] );
            if ( !empty( $emailuser ) ) {
                $this->emailuser = $emailuser ;
            }
        }
        return $this->emailuser;
    }

    public function subscriptions() {
        global $wphive;
        $mailinglists = $wphive->mailinglists();
        $subscriptions = $wphive->subscriptions( $this->email );
        foreach ( $subscriptions as $id => $subscription ) {
            if ( array_key_exists( $subscription, $mailinglists ) ) {
                $subscriptions[$id] = $mailinglists[$subscription];
            }
        }
        natcasesort( $subscriptions );
        return $subscriptions;
    }

    public function properties() {
        $completion = $this->completion();
        $properties = array(
            'email'	 => $this->email,
            'name'	 => $this->name,
            'country'	 => $this->country,
            'referer'	 => $this->referer,
            'tags'	 => implode( ', ', $this->tags ),
            'first'      => ( !empty( $this->stats['first'] ) ) ? $this->stats['first'] : 0,
            'latest'     => ( !empty( $this->stats['latest'] ) ) ? $this->stats['latest'] : 0,
            'score'	 => ( !empty( $this->stats['score'] ) ) ? $this->stats['score'] : 0,
            'completion' => $completion[0]
        );
        return $properties;
    }

    // STATS

    public function stats( $interactions = null ) {
        if ( is_array( $interactions ) ) {
            $this->interactions = $interactions;
            $this->calculate_stats();
        }
        return $this->stats;
    }

    private function calculate_stats() {
        $stats = array( 'first' => 0, 'latest' => 0, 'count' => 0, 'score' => 0, 'ips' => array(), 'countries' => array(), 'referers' => array(), 'downloads' => array() );
        $score_ids = array();
        foreach ( $this->interactions as $ts => $ts_interactions ) {
            if ( ( 0 == $stats['first'] ) || ( $stats['first'] > $ts ) ) {
                $stats['first'] = $ts;
            }
            if ( ( 0 == $stats['latest'] ) || ( $stats['latest'] < $ts ) ) {
                $stats['latest'] = $ts;
            }
            foreach ( $ts_interactions as $interaction ) {
                $stats['count']++;
                if ( !empty( $interaction['score'] ) && !empty( $interaction['score_id'] ) ) {
                    if ( !in_array( $interaction['score_id'], $score_ids ) ) {
                        $stats['score'] += $interaction['score'];
                        $score_ids[] = $interaction['score_id'];
                    }
                }
                if ( isset( $interaction['ip'] ) && !in_array( $interaction['ip'], $stats['ips'] ) ) {
                    $stats['ips'][] = $interaction['ip'];
                }
                if ( isset( $interaction['country'] ) && !in_array( $interaction['country'], $stats['countries'] ) ) {
                    $stats['countries'][] = $interaction['country'];
                }
                if ( isset( $interaction['referer'] ) && !in_array( $interaction['referer'], $stats['referers'] ) ) {
                    $stats['referers'][] = $interaction['referer'];
                }
                if ( ( 'download' === $interaction['type'] ) && !in_array( $interaction['uri'], $stats['downloads'] ) ) {
                    $stats['downloads'][] = $interaction['uri'];
                }
            }
        }
        $stats['score'] = intval( $stats['score'] + 0.5 );
        $this->stats = $stats;
        if ( isset( $stats['countries'][0] ) ) {
            $this->profile['country_cc'] = $stats['countries'][0];
        }
        if ( isset( $stats['referers'][0] ) ) {
            $this->profile['referer'] = $stats['referers'][0];
        }
    }

    // EXTENDED PROFILE

    private function xprofile_query() {
        if ( class_exists( 'WPHive_Pro_Api' ) ) {
            return WPHive_Pro_Api::email2profile( $this->profile['email'] );
        } else {
            return null;
        }
    }

    private function country_query( $ip ) {
        global $wphive;
        if ( class_exists( 'WPHive_Pro_Api' ) ) {
            $cc = WPHive_Pro_Api::ip2country( $ip );
            return $wphive->getcountrybycc( $cc );
        } else {
            return '';
        }
    }

    public function load_xprofile( $query = false, $refresh = false ) {
        if ( empty( $this->userkey ) ) {
            return null;
        }
        // retrieve profile
        $profile_cached = get_option( 'wphive_lead_' . $this->userkey, null );
        if ( isset( $profile_cached['fullcontact'] ) ) {
            $profile_cached['xprofile'] = $profile_cached['fullcontact'];
            unset( $profile_cached['fullcontact'] );
        }
        // country name
        $country = null;
        if ( !empty( $profile_cached ) && !empty( $profile_cached['country'] ) ) {
            $country = $profile_cached['country'];
        } elseif ( !empty( $this->stats['ips'][0] ) ) {
            $country = $this->country_query( $this->stats['ips'][0] );
            if ( !empty( $country ) && !empty( $profile_cached ) ) {
                $profile_cached['country'] = $country;
                update_option( 'wphive_lead_' . $this->userkey, $profile_cached );
            }
        }
        // query
        if ( !empty( $profile_cached ) && isset( $profile_cached['xprofile']['status'] ) && ( '202' == $profile_cached['xprofile']['status'] ) && ( ( time() - $profile_cached['timestamp'] ) >= 300 ) ) {
            $refresh = true;
        }
        if ( $query && ( empty( $profile_cached ) || $refresh ) ) {
            $xprofile = $this->xprofile_query();
            $profile = array(
                'xprofile'  => $xprofile,
                'country'   => $country,
                'timestamp' => time()
            );
            update_option( 'wphive_lead_' . $this->userkey, $profile );
            $this->xprofile = $xprofile;
        } else {
            $this->xprofile = $profile_cached['xprofile'];
        }
        // names
        if ( isset( $this->xprofile['contactInfo'] ) ) {
            if ( !empty( $this->xprofile['contactInfo']['fullName'] ) ) {
                $this->xprofile['name'] = $this->xprofile['contactInfo']['fullName'];
                if ( empty( $this->profile['name'] ) ) {
                    $this->profile['name'] = $this->xprofile['name'];
                }
            }
            if ( !empty( $this->xprofile['contactInfo']['givenName'] ) ) {
                $this->xprofile['firstname'] = $this->xprofile['contactInfo']['givenName'];
                if ( empty( $this->profile['firstname'] ) ) {
                    $this->profile['firstname'] = $this->xprofile['firstname'];
                }
            }
            if ( !empty( $this->xprofile['contactInfo']['familyName'] ) ) {
                $this->xprofile['lastname'] = $this->xprofile['contactInfo']['familyName'];
                if ( empty( $this->profile['lastname'] ) ) {
                    $this->profile['lastname'] = $this->xprofile['lastname'];
                }
            }
        }
        // country name
        if ( !empty( $country ) ) {
            $this->profile['country'] = $country;
        } elseif ( !empty( $this->profile['country_cc'] ) ) {
            $this->profile['country'] = $this->profile['country_cc'];
        }
        // primary user photo
        if ( empty( $this->xprofile['photo'] ) ) {
            $userphoto = '';
            $photo_types = array( 'linkedin', 'google profile', 'facebook', 'twitter', 'myspace' );
            if ( is_array( $this->xprofile['photos'] ) && ( count( $this->xprofile['photos'] ) > 0 ) ) {
                $photos = array();
                foreach ( $this->xprofile['photos'] as $photo ) {
                    if ( in_array( $photo['type'], $photo_types ) && !array_key_exists( $photo['type'], $photos ) ) {
                        $photos[$photo{'type'}] = $photo['url'];
                    }
                }
                foreach ( $photo_types as $type ) {
                    if ( array_key_exists( $type, $photos ) ) {
                        $userphoto = $photos[$type];
                        break;
                    }
                }
            }
            if ( !empty( $userphoto ) ) {
                $this->xprofile['photo'] = $userphoto;
            }
        }
    }

    // TIMELINE

    public function timeline() {
        $items = array();
        foreach ( $this->interactions as $ts => $ts_interactions ) {
            foreach ( $ts_interactions as $interaction ) {
                if ( isset ( $interaction['session_id'] ) ) {
                    $box = $interaction['session_id'];
                }
                if ( 'session' === $interaction['type'] ) {
                    $items[$box] = array(
                        'title'       => $interaction['descr'],
                        'date'        => date( 'd M Y', $ts ),
                        'displaydate' => date( 'M d', $ts ),
                        'body'        => '',
                        'pageviews'   => array()
                    );
                } elseif ( 'pageview' === $interaction['type'] ) {
                    $items[$box]['pageviews'][] = $interaction['uri'];
                } else {
                    $items[] = array(
                        'title' => $interaction['title'],
                        'date' => date( 'd M Y', $ts ),
                        'displaydate' => date( 'M d', $ts ),
                        'caption' => $interaction['descr']
                    );
                }
            }
        }
        $items = array_values( $items );
        foreach ( $items as $index => $item ) {
            if ( isset( $item['pageviews'] ) && is_array( $item['pageviews'] ) ) {
                $items[$index]['body'] = implode( '<br/>', array_unique( $item['pageviews'] ) );
            }
        }
        return json_encode( $items );
    }

}