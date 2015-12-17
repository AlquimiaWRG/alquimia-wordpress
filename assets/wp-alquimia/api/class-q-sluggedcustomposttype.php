<?php
/**
 * Utility class that allows to get a post ID from its slug
 */
class Q_JSON_SluggedCustomPostType extends WP_JSON_CustomPostType {
  /**
   * A list of active Wordpress plugins.
   *
   * @var array
   */
  protected $plugins;

  public function __construct( WP_JSON_ResponseHandler $server ) {
    parent::__construct( $server );
    $this->plugins = get_option( 'active_plugins' );
  }

  /**
   * Returns a post ID from its slug
   * @param  string  $slug The post slug
   * @return integer       The post ID
   */
  protected function get_post_id( $slug ) {
    global $wpdb;

    $post = $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s;", $slug ) );
    if ( empty( $post ) ) return new WP_Error( 'json_invalid_post_slug', __( 'Invalid post slug', 'alquimia' ) );

    return $post->ID;
  }
}
