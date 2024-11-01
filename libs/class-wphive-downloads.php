<?php

class WPHive_Downloads {

    private $db_tables = array();

    public function __construct() {
        global $current_user, $wpdb;
        // database
        $this->db_tables['download'] = $wpdb->prefix . "wphive_download";
        if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $this->db_tables['download'] . "'" ) != $this->db_tables['download'] ) {
            $this->create_dbtable();
        }
        // default options
        $default_options = array(
            'delivery'  => 'link',
            'shortcode' => 'form'
        );
        foreach ( $default_options as $key => $value ) {
            if ( '' == $this->get_option( $key ) ) {
                $this->set_option( $key, $value );
            }
        }
        // action handler
        add_action( 'template_redirect', array( $this, 'action_handler' ) );
        // shortcode handler
        $shortcode = $this->get_option( 'shortcode' );
        add_shortcode( $shortcode, array( $this, 'shortcode_handler' ) );
        // CSS
        add_action( 'wp_print_styles', array( $this, 'print_styles' ) );
        // recognize WordPress users
        get_currentuserinfo();
        if ( !empty( $current_user->ID ) ) {
            $_SESSION['wphive_forms_email']  = $current_user->user_email;
            $_SESSION['wphive_forms_name']   = $current_user->user_firstname . ' ' . $current_user->user_lastname;
            $_SESSION['wphive_forms_wpuser'] = 1;
        }
    }

    private function create_dbtable() {
        global $wpdb;
        $wpdb->hide_errors();
        $sql = "CREATE TABLE IF NOT EXISTS " . $this->db_tables['download'] . " (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `timestamp` int(10) unsigned NOT NULL,
                  `session_id` bigint(20) unsigned NOT NULL,
                  `email` varchar(128) NOT NULL,
                  `name` varchar(128) NOT NULL,
                  `file` varchar(255) NOT NULL,
                  `list_id` bigint(20) unsigned NOT NULL,
                  `formdata` text NOT NULL,
                  KEY `idx_session_id` (`session_id`),
                  KEY `idx_email` (`email`),
                  KEY `idx_file` (`file`)
                ) DEFAULT CHARSET=utf8;";
        if ( false === $wpdb->query( $sql ) ) {
            unset( $this->db_tables['download'] );
            return false;
        }
        return true;
    }

    public function track( $userkey ) {
        // empty
    }

    public function calculate_leadscores() {
        global $wphive, $wpdb;
        $leadscores = array();
        if ( !empty( $this->db_tables['download'] ) ) {
            $sql       = "SELECT email, file, session_id FROM " . $this->db_tables['download'] . ' GROUP by email, file ORDER by email ASC, file ASC';
            $downloads = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( $downloads as $download ) {
                $userkey = md5( strtolower( $download['email'] ) );
                if ( array_key_exists( $userkey, $leadscores ) ) {
                    $leadscores[$userkey]['score'] += $wphive->get_score( 'download', $download['file'], $download['session_id'] );
                } else {
                    $leadscores[$userkey] = array(
                        'email' => $download['email'],
                        'score' => $wphive->get_score( 'download', $download['file'], $download['session_id'] )
                    );
                }
            }
        }
        return $leadscores;
    }

    public function get_interactions( $email = null, $include_details = false ) {
        global $wphive, $wpdb;
        $mailinglists = $wphive->mailinglists();
        $interactions = array();
        if ( !empty( $this->db_tables['download'] ) ) {
            $sql = "SELECT id, timestamp, session_id, email, name, file, list_id FROM " . $this->db_tables['download'];
            if ( !empty( $email ) ) {
                $sql .= sprintf( " WHERE email='%s'", $wpdb->escape( $email ) );
            }
            $sql .= " ORDER by id ASC";
            $downloads = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( $downloads as $download ) {
                $ts = $download['timestamp'];
                $title = $description = '';
                if ( empty( $download['file'] ) ) {
                    if ( empty( $download['list_id'] ) ) {
                        $title       = 'User Registration';
                        $description = '';
                    } else {
                        $title       = 'Mailing List Subscription';
                        $description = ( array_key_exists( $download['list_id'], $mailinglists ) ) ? $mailinglists[$download{'list_id'}] : 'Mailing List #' . $download['list_id'];
                    }
                } else {
                    $title       = 'File Download';
                    $description = $download['file'];
                }
                $interaction = array(
                    'type'           => 'download',
                    'title'          => $title,
                    'descr'          => $description,
                    'uri'            => $download['file'],
                    'list_id'        => $download['list_id'],
                    'ts'             => $ts,
                    'base_score'     => $wphive->get_score( 'download', $download['file'] ),
                    'score'          => $wphive->get_score( 'download', $download['file'], $download['session_id'] ),
                    'score_id'       => md5( $download['email'] . $download['file'] ),
                    'session_id'     => $download['session_id']
                );
                if ( $include_details ) {
                    $interaction['details'] = $download;
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

    public function get_itemlist( $tracker = null, $filter = null ) {
        global $wphive, $wpdb;
        $items = array();
        if ( ( 'download' === $tracker ) && isset( $this->db_tables['download'] ) ) {
            $sql = "SELECT DISTINCT file FROM " . $this->db_tables['download'] . " ORDER BY file ASC";
            $entries = $wpdb->get_col( $sql );
            foreach ( $entries as $entry ) {
                if ( empty( $entry ) ) {
                    continue;
                }
                $key = md5( 'download' . $entry );
                $items[$key] = array(
                    'key'  => $key,
                    'type' => 'download',
                    'uri'  => $entry
                );
            }
        }
        if ( !empty( $filter ) ) {
            foreach ( $items as $key => $item ) {
                if ( false === stripos( $item['uri'], $filter ) ) {
                    unset( $items[$key] );
                }
            }
        }
        return $items;
    }

    private function track_download( $form_data ) {
        global $wphive, $wpdb;
        if ( isset ( $this->db_tables['download'] ) ) {
            $email    = ( isset( $_SESSION['wphive_forms_email'] ) ) ? strtolower( $_SESSION['wphive_forms_email'] ) : '';
            $name     = ( isset( $_SESSION['wphive_forms_name'] ) ) ? $_SESSION['wphive_forms_name'] : '';
            $file     = ( isset( $form_data['file'] ) ) ? $form_data['file'] : '';
            $list     = ( isset( $form_data['list_id'] ) ) ? intval( $form_data['list_id'] ) : 0;
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO " . $this->db_tables['download'] . " (timestamp, session_id, email, name, file, list_id, formdata) VALUES (%u, %u, %s, %s, %s, %u, %s)",
                array( time(), $wphive->session_id(), $email, $name, $file, $list, serialize( $form_data ) )
            ) );
        }
        return true;
    }

    private function redirect( $post_id = null, $form_id = null, $message = null ) {
        if ( empty( $post_id ) ) {
            wp_redirect( get_bloginfo( 'url' ) );
        } else {
            if ( empty( $form_id ) || empty( $message ) ) {
                wp_redirect( get_permalink( $post_id ) );
            } else {
                $message_token = 'message' . $form_id;
                wp_redirect( add_query_arg( array( $message_token => $message ), get_permalink( $post_id ) ) . '#wphive-form-' . $form_id );
            }
        }
        die();
    }

    private function get_filepath( $file, $format = 'dir' ) {
        if ( empty( $file ) || !is_string( $file ) ) {
            return null;
        }
        if ( '/' == substr( $file, 0, 1 ) ) {
            $file = 'http://' . $_SERVER['HTTP_HOST'] . $file;
        } elseif ( 'http://' != substr( $file, 0, 7 ) ) {
            $file = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file;
        }
        if ( 'dir' == $format ) {
            return str_replace( 'http://' . $_SERVER['HTTP_HOST'], $_SERVER['DOCUMENT_ROOT'], $file );
        } elseif ( 'url' == $format ) {
            return $file;
        }
        return null;
    }

    private function file_download( $file ) {
        global $wpdb;
        //close db connection (can cause issues if it remains open while downloading)
        mysql_close( $wpdb->dbh );
        // headers
        $mimetype = new WPHive_MimeType();
        header( 'Content-Type: ' . $mimetype->getType( $file ) );
        header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Content-Length: ' . filesize( $file ) );
        // flush output buffer
        ob_clean();
        flush();
        // deliver file
        readfile( $file );
        exit( 0 );
    }

    private function file_email( $file, $delivery, $form_data ) {
        $title = ( isset( $form_data['title'] ) ) ? $form_data['title'] : basename( $file );
        if ( 'email_attachment' == $delivery ) {
            $body = "Please find the requested file attached.";
        } else {
            $body = 'Please download your file here: <a href="' . add_query_arg( array( 'wphive-download' => $form_data['form_id'] ), get_bloginfo( 'url' ) ) . '">' . $title . '</a>';
        }
        $recipient = $_SESSION['wphive_forms_email'];
        $subject   = "Your Requested File From " . get_bloginfo( 'name' );
        $headers   = array();
        $headers[] = 'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>';
        if ( 'email_attachment' == $delivery ) {
            wp_mail( $recipient, $subject, $body, $headers, $file );
        } else {
            $headers[] = 'Content-Type: text/html';
            wp_mail( $recipient, $subject, $body, $headers );
        }
    }

    private function file_deliver( $form_id, $mode ) {
        // get form data
        $form_data = $this->get_option( $form_id, null );
        if ( !is_array( $form_data ) || empty( $form_data['file'] ) ) {
            die( 'WP Hive Download Error: File not found (#1)' );
        }
        $file = $this->get_filepath( $form_data['file'] );
        if ( empty( $file ) ) {
            die( 'WP Hive Download Error: File not found (#2)' );
        }
        if ( !file_exists( $file ) ) {
            die( 'WP Hive Download Error: File not found (#3)' );
        }
        if ( !isset( $_SESSION['wphive_forms_email'] ) ) {
            $this->redirect( $form_data['post_id'] );
        }
        // track file download
        $this->track_download( $form_data );
        if ( !isset ( $_SESSION['wphive_downloads_delivered'] ) ) {
            $_SESSION['wphive_downloads_delivered'] = array();
        }
        $_SESSION['wphive_downloads_delivered'][$form_data{'form_id'}] = array( 'ts' => time(), 'tracked' => false );
        switch ( $mode ) {
            case 'download':
                $this->file_download( $file );
                break;
            case 'email':
                $delivery = $this->get_option( 'delivery' );
                $this->file_email( $file, $delivery, $form_data );
                break;
            case 'email_a':
                $this->file_email( $file, 'email_attachment', $form_data );
                break;
            case 'email_l':
                $this->file_email( $file, 'email_link', $form_data );
                break;
        }
    }

    private function process_form_submission( $data ) {
        global $wphive;
        // check email address; store email address in session; set userkey
        $email = $name = '';
        if ( isset( $data['email'] ) && is_email( $data['email'] ) ) {
            $email = $data['email'];
            $_SESSION['wphive_forms_email'] = $email;
            $wphive->userkey( $email );
        }
        // also store name in session, if we have it
        if ( !empty( $data['name'] ) ) {
            $name = sanitize_text_field( $data['name'] );
            $_SESSION['wphive_forms_name'] = $name;
        }
        // retrieve form details from options
        $form_data = null;
        if ( isset( $data['form_id'] ) ) {
            $form_data = $this->get_option( $data['form_id'], null );
        }
        $post_id = ( isset( $form_data['post_id'] ) ) ? (int) $form_data['post_id'] : 0;
        $form_id = ( isset( $form_data['form_id'] ) ) ? $form_data['form_id'] : '';
        // track form submission in session
        if ( !empty( $form_id ) ) {
            if ( !isset ( $_SESSION['wphive_forms_submitted'] ) ) {
                $_SESSION['wphive_forms_submitted'] = array();
            }
            $_SESSION['wphive_forms_submitted'][$form_id] = array( 'ts' => time(), 'tracked' => false );
        }
        // redirect to form, if email is invalid
        if ( empty( $email ) ) {
            $this->redirect( $post_id, $form_id, '1' );
        }
        // redirect to form, if name is required and empty
        if ( isset( $form_data['type'] ) && in_array( $form_data['type'], array( 'emailandname', 'simple' ) ) ) {
            if ( empty( $name ) ) {
                $this->redirect( $post_id, $form_id, '2' );
            }
        }
        // subscribe to mailing list
        $list = ( isset( $form_data['list_id'] ) ) ? intval( $form_data['list_id'] ) : 0;
        if ( !empty( $list ) ) {
            $wphive->ml_subscribe( $email, $list, $name );
        }
        // check file and title
        $file  = ( isset( $form_data['file'] ) ) ? $form_data['file'] : '';
        $title = ( isset( $form_data['title'] ) ) ? $form_data['title'] : '';
        if ( empty( $file ) || ( 'simple' == $form_data['type'] ) ) {
            // track download with empty file
            $this->track_download( $form_data );
            // no file delivery
            $this->redirect( $post_id );
        }
        // deliver file by email, in case that's the delivery option
        $delivery = $this->get_option( 'delivery' );
        if ( ( 'email_link' == $delivery ) || ( 'email_attachment' == $delivery ) ) {
            $this->file_deliver( $form_data['form_id'], 'email' );
        }
        $this->redirect( $post_id );
    }

    public function get_option( $key ) {
        $result = get_option( 'wphive_forms_' . $key, '' );
        if ( !empty( $result ) ) {
            return $result;
        }
        $result = get_option( 'wpcd_' . $key, '' );
        if ( !empty( $result ) ) {
            $this->set_option( $key, $result );
            delete_option( 'wpcd_' . $key );
        }
        return $result;
    }

    public function set_option( $key, $value = null ) {
        if ( is_null( $value ) && isset( $_POST[$key] ) ) {
            $value = $_POST[$key];
        }
        return update_option( 'wphive_forms_' . $key, $value );
    }

    public function action_handler() {
        if ( isset( $_POST['wphive_forms_action'] ) ) {
            switch ( $_POST['wphive_forms_action'] ) {
                case 'submit_form':
                    $this->process_form_submission( $_POST );
                    break;
            }
        } elseif ( isset( $_GET['wphive-download'] ) ) {
            $this->file_deliver( $_GET['wphive-download'], 'download' );
        } elseif ( isset( $_GET['wphive-email-a'] ) ) {
            $this->file_deliver( $_GET['wphive-email-a'], 'email_a' );
        } elseif ( isset( $_GET['wphive-email-l'] ) ) {
            $this->file_deliver( $_GET['wphive-email-l'], 'email_l' );
        } elseif ( isset( $_GET['wphive-session-reset'] ) ) {
            unset( $_SESSION['wphive_forms_email'] );
            unset( $_SESSION['wphive_forms_name'] );
            unset( $_SESSION['wphive_forms_submitted'] );
            unset( $_SESSION['wphive_downloads_delivered'] );
        } elseif ( isset( $_GET['wphive-session-show'] ) ) {
            print_r( $_SESSION );
            die();
        }
    }

    public function shortcode_handler( $atts, $content = null, $code = "" ) {
        global $post, $wphive;
        // default return value
        $retval = '[ error processing WP Hive form code ]';
        // get data from shortcode
        $type = $file = $title = $button = $list = '';
        extract( shortcode_atts(
                array( 'type' => '', 'file' => '', 'title' => '', 'button' => '', 'list' => '' ),
                $atts )
        );
        // ensure valid type
        if ( empty( $type ) || !in_array( $type, array( 'email', 'emailandname', 'simple', 'user' ) ) ) {
            $type = 'email';
        }
        // ensure file name is provided unless for simple signup
        if ( empty( $file ) && ( 'simple' != $type ) ) {
            return '[ missing file name in WP Hive form code ]';
        }
        // set title to file name if empty
        if ( empty( $title ) ) {
          if ( empty( $file ) ) {
            $title = '(none)';
          } else {
            $title = basename( $file );
          }
        }
        // generate form id (unique for same set of parameters); store data in options
        static $form_counter = 0;
        $form_counter++;
        $form_id   = md5( $form_counter . md5( $post->ID . $type . $file . $title . $button . $list ) );
        $form_data = array( 'form_id' => $form_id, 'post_id' => $post->ID, 'type' => $type, 'file' => $file, 'title' => $title, 'list_id' => $list );
        $this->set_option( $form_id, $form_data );
        // Google Analytics Event Tracking Label
        $ml_name = 'List #' . $list;
        $lists = $wphive->mailinglists();
        if ( array_key_exists( $list, $lists ) ) {
            $ml_name = $lists[$list];
        }
        $ga_label = sprintf( "%s - %s - %s", $ml_name, $title, ucfirst( $type ) );
        // get delivery type
        $delivery = $this->get_option( 'delivery' );
        // either show form or deliver file
        $show_form = true;
        $email = $name = '';
        if ( !empty( $_SESSION['wphive_forms_email'] ) ) {
            $email = $_SESSION['wphive_forms_email'];
            if ( 'email' == $type ) {
                $show_form = false;
            }
            if ( !empty( $_SESSION['wphive_forms_name'] ) ) {
                $name      = $_SESSION['wphive_forms_name'];
                $show_form = false;
            }
        }
        $template = null;
        // Google Analytics Event Tracking: Form Submission; File Delivery
        if ( isset( $_SESSION['wphive_forms_submitted'][$form_id] ) && ( false == $_SESSION['wphive_forms_submitted'][$form_id]['tracked'] ) ) {
            echo $wphive->gaq_push( array( '_trackEvent', 'WP Hive Forms', 'Submitted Form', $ga_label, 1, true ), true );
            $_SESSION['wphive_forms_submitted'][$form_id]['tracked'] = true;
        }
        if ( isset( $_SESSION['wphive_downloads_delivered'][$form_id] ) && ( false == $_SESSION['wphive_downloads_delivered'][$form_id]['tracked'] ) ) {
            echo $wphive->gaq_push( array( '_trackEvent', 'WP Hive Forms', 'File Delivered', $ga_label, 1, true ), true );
            $_SESSION['wphive_downloads_delivered'][$form_id]['tracked'] = true;
        }
        if ( $show_form ) {
            // Google Analytics Event Tracking: Saw Form
            echo $wphive->gaq_push( array( '_trackEvent', 'WP Hive Forms', 'Saw Form', $ga_label, 1, false ), true );
            // select template
            switch ( $type ) {
                case 'email':
                    $template = 'emailform.php';
                    break;
                case'emailandname':
                    $template = 'emailandnameform.php';
                    break;
                case 'simple':
                    $template = 'simpleform.php';
                    break;
                case 'user':
                    $template = 'register.php';
                    break;
            }
        } else {
            // select template
            switch ( $delivery ) {
                case 'link':
                    $template = 'download.php';
                    break;
                default:
                    $template = 'emailsent.php';
                    break;
            }
        }
        // form submit button
        $button_html = '';
        if ( !empty( $button ) ) {
            // try MaxButtons shortcode
            $maxbutton_shortcode = '[maxbutton id="' . $button . '"]';
            if ( $maxbutton_shortcode != ( $maxbutton_html = do_shortcode( $maxbutton_shortcode ) ) ) {
                $button_html = str_replace( 'href=""', 'href="javascript:document.capture_' . $form_id . '.submit()"', $maxbutton_html );
            }
        }
        if ( empty( $button_html ) ) {
            $button_html = '<input type="submit" value="SUBMIT"/>';
        }
        // message
        $message = null;
        if ( isset( $_REQUEST[( 'message' . $form_id )] ) ) {
            switch ( $_REQUEST[( 'message' . $form_id )] ) {
                case '1':
                    $message = 'Please enter a valid email address';
                    break;
                case '2':
                    $message = 'Please enter your name';
                    break;
            }
        }
        // name
        $name = ( isset( $_SESSION['wphive_forms_name'] ) ) ? $_SESSION['wphive_forms_name'] : '';
        // email reset link
        $email_reset_link = '';
        if ( !isset( $_SESSION['wphive_forms_wpuser'] ) ) {
            $email_reset_link = add_query_arg( array( 'wphive-session-reset' => $form_id ), get_permalink() );
        }
        // include template
        if ( !empty( $template ) ) {
            $template_file         = null;
            $template_file_theme   = get_stylesheet_directory() . '/wp-hive/' . $template;
            $template_file_default = WPHIVE_DIR . '/templates/forms/' . $template;
            if ( file_exists( $template_file_theme ) ) {
                $template_file = $template_file_theme;
            } elseif ( file_exists( $template_file_default ) ) {
                $template_file = $template_file_default;
            }
            if ( !empty( $template_file ) ) {
                ob_start();
                include( $template_file );
                $retval = ob_get_clean();
            }
        }
        return $retval;
    }

    public function print_styles() {
        if ( file_exists( get_stylesheet_directory() . '/wp-hive/forms.css' ) ) {
            // modifications for current theme take precedence
            $css_url = get_stylesheet_directory_uri() . '/wp-hive/forms.css';
        } else {
            // otherwise serve default stylesheet
            $css_url = plugins_url( 'css/wphive-forms.css', WPHIVE_FILE );
        }
        wp_register_style( 'wphive-forms', $css_url );
        wp_enqueue_style( 'wphive-forms' );
    }

    public function admin_page() {
        global $wphive;
        // buttons and mailing lists
        $buttons      = $wphive->buttons();
        natcasesort( $buttons );
        $mailinglists = $wphive->mailinglists();
        natcasesort( $mailinglists );
        // save settings
        $message = null;
        if ( isset( $_POST['wphive-forms-save-settings'], $_POST['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_POST['_wpnonce'], 'wphive-forms-settings' ) ) {
                $this->set_option( "delivery" );
                $this->set_option( "shortcode" );
                $message = array( 'class' => 'wphive_forms_success', 'text' => 'Settings saved.' );
            }
        }
        // retrieve settings
        $delivery  = $this->get_option( "delivery" );
        $shortcode = $this->get_option( "shortcode" );
        // print admin page
        echo '
    <div id="wphive-forms-notifications">
    </div>
    <style>
        .wphive-forms-message {
            background-color: #D5E4F7;
            background-repeat: no-repeat;
            margin: .5em 0;
            padding: 6px 6px 6px 6px;
            color: #345395;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.3em;
        }
        .wphive-forms-success {
            background-color: #CFEECA;
            color: #208A1B;
        }
        .wphive-forms-error {
            background-color: #F9D6CB;
            color: #E36154;
        }
        .wphive-forms-alert {
            background-color: #FFF6CC;
            color: #CF8516;
        }
        .wphive-forms-message a {
            color: #345395;
        }
        .wphive-forms-success a {
            color: #208A1B;
        }
        .wphive-forms-error a {
            color: #E36154;
        }
        .wphive-forms-alert a {
            color: #CF8516;
        }
    </style>
';
        if ( is_array( $message ) ) {
            echo '<p class="wphive-forms-message" ' . $message['class'] . '>' . $message['text'] . '</p>';
        }
        ?>
    <h2>File Delivery and Shortcode Settings</h2>
    <form action="<?php echo admin_url( 'admin.php?page=wp-hive-downloads' ); ?>" method="post"
          enctype="multipart/form-data">
        <?php wp_nonce_field( 'wphive-forms-settings' ); ?>
        <table cellspacing="5">
            <tbody>
            <tr>
                <th align="right" valign="top"><label for="delivery">File Delivery Method:</label></th>
                <th align="left">
                    <select name="delivery">
                        <option value="link" <?php if ( $delivery == "link" ) {
                            echo 'selected="selected"';
                        } ?>>
                            Show Link to File
                        </option>
                        <option value="email_attachment" <?php if ( $delivery == "email_attachment" ) {
                            echo 'selected="selected"';
                        } ?>>
                            Send File as Email Attachment
                        </option>
                        <option value="email_link" <?php if ( $delivery == "email_link" ) {
                            echo 'selected="selected"';
                        } ?>>
                            Send Link to File by Email
                        </option>
                    </select>
                </th>
            </tr>
            <tr>
                <th align="right" valign="top">
                    <label for="shortcode">Shortcode ID:</label>
                    
                </th>
                <th align="left">
                    <input type="text" name="shortcode" value="<?php echo $shortcode; ?>"/><br/>
                    <small><em>Changing the Shortcode ID can resolve plugin conflicts</em></small>
                </th>
            <tr>
            <tr>
                <th align="right" valign="top">&nbsp;</th>
                <th align="left"><input name="wphive-forms-save-settings" type="submit" value="Save Settings"/></th>
            </tr>
            </tbody>
        </table>
    </form>

    <h2>Shortcode Generator</h2>
    <table cellspacing="5">
        <tbody>
            <tr>
                <th align="right" valign="top"><label for="wphive-form-type">Required for Downloads:</label></th>
                <th align="left">
                    <select id="wphive-form-type" name="wphive-form-type">
                        <option value="email">Properly Formatted Email Address</option>
                        <option value="emailandname">Email Address and Name</option>
                        <option value="user">Register New WordPress User</option>
                        <option value="simple">Simple Signup - No Download</option>
                    </select>
                </th>
            </tr><tr>
                <th align="right" valign="top"><label for="wphive-form-file">Download File URL:</th>
                <th align="left"><input type="text" id="wphive-form-file" name="wphive-form-file" /></th>
            </tr><tr>
                <th align="right" valign="top"><label for="wphive-form-title">Download Title:</th>
                <th align="left"><input type="text" id="wphive-form-title" name="wphive-form-title" /></th>
            </tr><tr>
                <?php if ( is_array( $buttons ) && ( count( $buttons ) > 0 ) ): ?>
                <th align="right" valign="top"><label for="wphive-form-button">Button:</th>
                <th align="left"><?php echo $wphive->html_select( 'wphive-form-button', $buttons, false, 1, null ); ?></th>
                <?php else: ?>
                <th align="right" valign="top"><label for="wphive-form-button">Button ID:</th>
                <th align="left">
                    <input type="text" id="wphive-form-button" name="wphive-form-button" /><br/>
                    <small><em>Requires the MaxButtons Pro plugin</em></small>
                </th>
                <?php endif; ?>
            </tr><tr>
                <?php if ( is_array( $mailinglists ) && ( count( $mailinglists) > 0 ) ): ?>
                <th align="right" valign="top"><label for="wphive-form-list">Mailing List:</th>
                <th align="left"><?php echo $wphive->html_select( 'wphive-form-list', $mailinglists, false, 1, null ); ?></th>
                <?php else: ?>
                <th align="right" valign="top"><label for="wphive-form-list">Mailing List ID:</th>
                <th align="left">
                    <input type="text" id="wphive-form-list" name="wphive-form-list" /><br/>
                    <small><em>Requires a suitable Mailing List plugin</em></small>
                </th>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>
    <script type="text/javascript">
        var shortcodeId = '<?php echo $shortcode; ?>';
        function wphiveShortcode() {
            jQuery('#wphive-shortcode').html('[' + shortcodeId + ' type="' + jQuery('#wphive-form-type').val() + '" file="' + jQuery('#wphive-form-file').val() + '" title="' + jQuery('#wphive-form-title').val() + '" button="' + jQuery('#wphive-form-button').val() + '" list="' + jQuery('#wphive-form-list').val() + '"]');
        }
        jQuery(document).ready( function() {
            wphiveShortcode();
            jQuery('#wphive-form-type').change( function(e) {
                wphiveShortcode();
            });
            jQuery('#wphive-form-file').change( function(e) {
                wphiveShortcode();
            });
            jQuery('#wphive-form-title').change( function(e) {
                wphiveShortcode();
            });
            jQuery('#wphive-form-button').change( function(e) {
                wphiveShortcode();
            });
            jQuery('#wphive-form-list').change( function(e) {
                wphiveShortcode();
            });
        });
    </script>
    <p>
        Use the following shortcode to embed the form:<br/><br/>
        <strong><code id="wphive-shortcode">[]</code></strong>
    </p>
    <?php
    }

}
