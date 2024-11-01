<?php

class WPHive_List_Table extends WP_List_Table {

    private $type = '';
    private $data = array();

    function __construct( $type = null, $data = null ) {
        parent::__construct( array(
            'singular' => 'item',
            'plural'   => 'items',
            'ajax'     => false
        ) );
        $this->type = $type;
        $this->data = $data;
    }

    function column_default( $item, $column_name ) {
        if ( array_key_exists( $column_name, $item ) ) {
            if ( 'completion' == $column_name ) {
                return sprintf( "%u %%", $item['completion'] * 100 );
            } elseif ( 'country' == $column_name ) {
                return '<div class="f16"><span class="flag ' . strtolower( $item['country_cc'] ) . '"></span>&nbsp;' . $item['country_name'] . '</div>';
            } elseif ( 'email' == $column_name ) {
                return '<a class="wphive-popup-link" href="#wphive-popup" onclick="javascript:wphive_load_leaddata(\'' . $item['email'] . '\');">' . $item['email'] . '</a>';
            } elseif ( 'latest' == $column_name ) {
                return ( 0 == $item['latest'] ) ? '<em>never</em>' : date( 'Y-m-d', $item['latest'] );
            } elseif ( 'uri' == $column_name ) {
                return '<a href="' . $item['uri'] . '" target="_blank">' . $item['uri'] . '</a>';
            } elseif ( ( 'score' == $column_name ) && ( isset( $item['key'] ) ) ) {
                return '<input name="score-' . $item['key'] . '" type="text" value="' . $item['score'] . '" />';
            } elseif ( 'type' == $column_name ) {
                return ucfirst( $item[$column_name] );
            } else {
                return $item[$column_name];
            }
        } else {
            return '?';
        }
    }

    function get_columns() {
        switch ( $this->type ) {
            case 'leads':
                $columns = array(
                    'email'      => 'Email',
                    'name'       => 'Name',
                    'latest'     => 'Last Interaction',
                    'completion' => 'Profile Completion',
                    'score'      => 'Lead Score',
                    'tags'       => 'Tags'
                );
                break;
            case 'scoring_download':
                $columns = array(
                    'uri'   => 'File URL',
                    'score' => 'Score Points'
                );
                break;
            case 'scoring_pageview':
                $columns = array(
                    'uri'   => 'Page URL',
                    'score' => 'Score Points'
                );
                break;
            case 'scoring_email':
                $columns = array(
                    'title' => 'Email Subject',
                    'type'  => 'Email Type',
                    'score' => 'Score Points'
                );
                break;
            case 'scoring_country':
                $columns = array(
                    'country' => 'Country',
                    'score'   => 'Score Modifier (%)'
                );
                break;
            case 'scoring_referer':
                $columns = array(
                    'domain' => 'HTTP Referer TLD',
                    'score'  => 'Score Modifier (%)'
                );
                break;
        }
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array();
        $columns          = array_keys( $this->get_columns() );
        foreach ( $columns as $column ) {
            if ( in_array( $column, array( 'completion', 'country', 'domain', 'email', 'name', 'title', 'type', 'score', 'uri' ) ) ) {
                $sortable_columns[$column] = array( $column, false );
            }
        }
        return $sortable_columns;
    }

    function prepare_items() {
        // max # of table items per page
        $per_page = 20;
        // columns setup
        $columns               = $this->get_columns();
        $hidden                = array();
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        // sort items
        $data = $this->data;
        if ( !empty( $_REQUEST['orderby'] ) && !empty( $_REQUEST['order'] ) && array_key_exists( $_REQUEST['orderby'], $sortable ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) {
            $orderby = $_REQUEST['orderby'];
            $order   = $_REQUEST['order'];
        } else {
            switch ( $this->type ) {
                case 'leads':
                    $orderby = 'score';
                    $order   = 'desc';
                    break;
                case 'scoring_email':
                    $orderby = 'type';
                    $order   = 'asc';
                    break;
                case 'scoring_country':
                    $orderby = 'country';
                    $order   = 'asc';
                    break;
                case 'scoring_referer':
                    $orderby = 'domain';
                    $order   = 'asc';
                    break;
                default:
                    $orderby = 'uri';
                    $order   = 'asc';
            }
        }
        if ( 'country' == $orderby ) {
            $orderby = 'country_name';
        }
        $sorter = create_function( '$a,$b', '
            if ( "score" == "' . $orderby . '" ) {
                $result = $a["score"] - $b["score"];
            } else {
                $result = strcasecmp( $a["' . $orderby . '"], $b["' . $orderby . '"] );
            }
            return ( "asc" === "' . $order . '" ) ? $result : -$result;'
        );
        usort( $data, $sorter );
        // pagination
        $total_items = count( $data );
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );
        $page = ( isset( $_REQUEST['paged'] ) ) ? (int) $_REQUEST['paged'] : 1;
        if ( $total_items > $per_page ) {
            $this->items = array_slice( $data, ( ( $page - 1 ) * $per_page ), $per_page );
        } else {
            $this->items = $data;
        }
    }

}
