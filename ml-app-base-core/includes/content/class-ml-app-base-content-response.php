<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content_Response {
    public static function success( $data = array() ) {
        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $data,
        ), 200 );
    }

    public static function success_list( $data = array(), $page = 1, $per_page = 10, $total = 0 ) {
        $total_pages = $per_page > 0 ? ceil( $total / $per_page ) : 0;
        return new WP_REST_Response( array(
            'success'    => true,
            'data'       => $data,
            'pagination' => array(
                'page'        => (int) $page,
                'per_page'    => (int) $per_page,
                'total'       => (int) $total,
                'total_pages' => (int) $total_pages,
            ),
        ), 200 );
    }

    public static function error( $code, $message, $status = 400, $details = array() ) {
        return new WP_REST_Response( array(
            'success' => false,
            'error'   => array(
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ),
        ), $status );
    }
}
