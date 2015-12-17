<?php
/**
 * Extends the get_current_user method from WP_JSON_Users. The original method
 * redirects to /users/<current user ID>, but redirects are not allowed by CORS
 * requests.
 */
class Q_JSON_Users extends WP_JSON_Users {
  /**
   * Mimics the original get_current_user method, but returns the response directly
   * instead of making a redirect (HTTP 302). This way, CORS requests will not bail.
   * @param  string $context
   * @return response
   */
  public function get_current_user( $context = 'view' ) {
    $current_user_id = get_current_user_id();

    if ( empty( $current_user_id ) ) {
      return new WP_Error( 'json_not_logged_in', __( 'You are not currently logged in.' ), array( 'status' => 401 ) );
    }

    $response = $this->get_user( $current_user_id, $context );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    if ( ! ( $response instanceof WP_JSON_ResponseInterface ) ) {
      $response = new WP_JSON_Response( $response );
    }

    return $response;
  }
}
