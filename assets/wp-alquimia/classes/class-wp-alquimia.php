<?php
/**
 * @package   wp-alquimia
 * @author    Mauro Constantinescu <mauro.constantinescu@gmail.com>
 * @copyright © 2015 White, Red & Green Digital S.r.l.
 *
 * Base class of the WP Alquimia plugin. Handles custom post types and taxonomies, renaming "post" post type
 * and existing taxonomies (category and post_tag), custom post statuses, i10n, custom users groups and
 * capabilities and WP_REST_API custom endpoints. Works well with Polylang and/or Advanced Custom Fields
 * Wordpress plugins.
 */
class WP_Alquimia {
  /**
   * A list of active Wordpress plugins.
   *
   * @var array
   */
  private $plugins;

  /**
   * Plugin slug, used for loading languages files. This must be overridden and equals to the plugin
   * text domain.
   *
   * @var string
   */
  protected $name = 'wp-alquimia';

  /**
   * Plugin directory name. This must be overridden.
   *
   * @var string
   */
  protected $plugin_dir;

  /**
   * An array of custom post types, in the form:
   * ```
   * 'post_type_name' => array(
   *   // post type options, like 'labels', 'public' etc.
   *   // see http://codex.wordpress.org/Function_Reference/register_post_type
   * )
   * ```
   * You can put here 'post' too, and the options will be merged with the post's
   * default ones.
   *
   * @var array
   */
  protected $post_types;

  /**
   * An array of custom taxonomies associated to a post type, in the form:
   *
   * ```
   * 'post_type_name' => array(
   *   'taxonomy_name' => array(
   *     // taxonomy options, like 'labels', 'public' etc.
   *     // see http://codex.wordpress.org/Function_Reference/register_taxonomy
   *   )
   * )
   * ```
   *
   * You can put here 'category' and 'post_tag' too, and the options will be merged with the
   * default ones.
   *
   * @var array
   */
  protected $taxonomies;

  /**
   * A map of taxonomies associated with their post type, in the form:
   *
   * ```
   * 'post_type_name' => array( 'taxonomy_name', ... )
   * ```
   *
   * It's not required for the relationship `'post' => array( 'category', 'post_tag' )` to
   * be included, as that is natively defined into Wordpress.
   *
   * @var array
   */
  protected $terms_map;

  /**
   * An array of custom post statuses associated with their post type, in the form:
   *
   * ```
   * 'post_type_name' => array(
   *   'post-status-slug' => array(
   *      // 'label', 'public' and 'label_count' options
   *      // see https://codex.wordpress.org/Function_Reference/register_post_status
   *      // show_in_json => true|false (optional) Default: false
   *   )
   * )
   * ```
   *
   * > NOTE: you must set 'show_in_json' to true if you want the API to return posts
   * with the custom post status.
   *
   * @var array
   */
  protected $post_statuses;

  public function __construct() {
    /* $plugin_dir and $name must be overridden */
    if ( empty( $this->plugin_dir ) ) {
      _doing_it_wrong( 'WP_Alquimia::__construct', 'The plugin dir must be overridden', WP_ALQUIMIA__VERSION );
    }

    if ( empty( $this->name ) ) {
      _doing_it_wrong( 'WP_Alquimia::__construct', 'The plugin name must be overridden', WP_ALQUIMIA__VERSION );
    }

    $this->plugins = get_option( 'active_plugins' );
    $this->init();

    if ( is_admin() ) {
      add_action( 'init', array( $this, 'rename_data' ) );
      add_action( 'admin_init', array( $this, 'rename_menus' ) );
      add_action( 'admin_footer-post.php', array( $this, 'populate_post_status_dropdown' ) );
      add_action( 'admin_footer-post-new.php', array( $this, 'populate_post_status_dropdown' ) );
    }

    add_action( 'init', array( $this, 'register_data' ) );

    if ( in_array( 'json-rest-api/plugin.php', $this->plugins ) ) {
      add_action( 'wp_json_server_before_serve', array( $this, 'init_api' ) );
    }

    if ( in_array( 'oauth2-provider/wp-oauth.php', $this->plugins ) ) {
      add_filter( 'json_serve_request', array( $this, 'send_cors_headers' ) );
      add_filter( 'redirect_canonical', array( $this, 'redirect_oauth' ), 10, 2 );
      add_action( 'template_include', array( $this, 'template_redirect_oauth' ), 10, 1 );
    }
  }

  public function init() {
    load_plugin_textdomain( 'wp-alquimia', false, 'wp-alquimia/languages' );

    /* Eventually load the languages for the plugin that is extending this */
    if ( $this->name != 'wp-alquimia' ) {
      $path = "$this->name/languages";
      $is_loaded = load_plugin_textdomain( $this->name, false, $path );
    }

    /* Add the translations immediately if the Polylang plugin is not active, otherwise wait for it */
    if ( in_array( 'polylang/polylang.php', $this->plugins ) ) {
      add_action( 'pll_language_defined', array( $this, 'add_translations' ) );
    } else {
      $this->add_translations();
    }
  }

  /**
   * Called as soon as translations can be added. Use gettext functions here.
   * Using them before (like in `__construct` or `init`) doesn't work.
   */
  public function add_translations() {}

  /**
   * Initializes the WP_REST_API custom endpoints, taking them from $api_endpoints
   * @param  WP_JSON_Server $server see http://wp-api.org/
   */
  public function init_api( $server ) {
    if ( in_array( 'json-rest-api/plugin.php', $this->plugins ) ) {
      // Add Q_JSON_Users first
      require_once WP_ALQUIMIA__PLUGIN_DIR . 'api/class-q-json-users.php';
      $users = new Q_JSON_Users( $server );
      add_filter( 'json_endpoints', array( $users, 'register_routes' ) );

      /*
      Require but not add the WP_JSON_CustomPostType extensions,
      as they can be extended but not directly used.
       */
      require_once WP_ALQUIMIA__PLUGIN_DIR . 'api/class-q-sluggedcustomposttype.php';
      require_once WP_ALQUIMIA__PLUGIN_DIR . 'api/class-q-json-customposttype.php';

      $api_dir = $this->plugin_dir . 'api';

      if ( file_exists( $api_dir ) ) {
        $files = array_diff( scandir( $api_dir ), array( '.', '..', '.DS_Store' ) );

        foreach ( $files as $file ) {
          // class-my-json-my-post-type.php => MY_JSON_MyPostType
          $pieces = explode( '-', $file );
          $prefix = strtoupper( $pieces[1] );
          $class_name = '';

          for ( $i = 3; $i < count( $pieces ); $i++ ) {
            $class_name .= ucfirst( $pieces[$i] );
          }

          $class_name = substr( $class_name, 0, -4 );
          $class_name = implode( '_', array( $prefix, 'JSON', $class_name ) );

          require_once $this->plugin_dir . "api/$file";
          $object = new $class_name( $server );

          if ( ! empty( $this->post_statuses ) ) {
            if ( method_exists( $object, 'get_type' ) ) {
              $type = $object->get_type();

              if ( ! empty( $this->post_statuses[$type] ) ) {
                $object->allow_custom_post_statuses( true );
                $object->set_post_statuses( $this->post_statuses[$type] );
              }
            }
          }

          // If the OAuth2 server is active, disable the Wordpress default cookie authentication
          if ( in_array( 'oauth2-provider/wp-oauth.php', $this->plugins ) ) {
            global $wp_json_auth_cookie;
            $wp_json_auth_cookie = false;
          }

          add_filter( 'json_endpoints', array( $object, 'register_routes' ) );
        }
      }
    }
  }

  /**
   * Registers custom post types, taxonomies and post stauses using $post_types, $taxonomies,
   * $terms_map and $post_statuses
   */
  public function register_data() {
    /* Register post types */
    if ( ! empty( $this->post_types ) ) {
      foreach ( $this->post_types as $post_type => $options ) {
        if ( $post_type != 'post' ) {
          register_post_type( $post_type, $options );
        }
      }
    }

    /* Register taxonomies */
    if ( ! empty( $this->taxonomies ) ) {
      foreach ( $this->taxonomies as $post_type => $taxonomies ) {
        foreach ( $taxonomies as $taxonomy => $options ) {
          if ( $taxonomy != 'category' && $taxonomy != 'post_tag' ) {
            register_taxonomy( $taxonomy, $post_type, $options );
          }
        }
      }
    }

    /* Register taxonomies for post types */
    if ( ! empty( $this->terms_map ) ) {
      foreach ( $this->terms_map as $post_type => $taxonomies ) {
        foreach ( $taxonomies as $taxonomy ) {
          register_taxonomy_for_object_type( $taxonomy, $post_type );
        }
      }
    }

    /* Register post statuses */
    if ( ! empty( $this->post_statuses ) ) {
      foreach ( $this->post_statuses as $post_type => $post_statuses ) {
        foreach ( $post_statuses as $name => $post_status ) {
          register_post_status( $name, $post_status );
        }
      }
    }
  }

  /**
   * If needed, renames native Wordpress entities' labels (post, category and post_tag)
   */
  public function rename_data() {
    if ( ! empty( $this->post_types ) && ! empty( $this->post_types['post'] ) ) {
      global $wp_post_types;

      $args = get_post_type_object( 'post' );
      unset( $wp_post_types['post'] );

      $args_array = get_object_vars( $args );
      $args = array_merge( $args_array, $this->post_types['post'] );

      register_post_type( 'post', $args );
    }

    $edited_category = ! empty( $this->taxonomies ) &&
                       ! empty( $this->taxonomies['post'] ) &&
                       ! empty( $this->taxonomies['post']['category'] );
    $edited_post_tag = ! empty( $this->taxonomies ) &&
                       ! empty( $this->taxonomies['post'] ) &&
                       ! empty( $this->taxonomies['post']['post_tag'] );

    if ( $edited_category || $edited_post_tag ) {
      global $wp_taxonomies;

      if ( $edited_category ) {
        $category = $wp_taxonomies['category'];
        unset( $wp_taxonomies['category'] );

        $args = array_merge( get_object_vars( $category ), $this->taxonomies['post']['category'] );
        register_taxonomy( 'category', 'post', $args );
      }

      if ( $edited_post_tag ) {
        $post_tag = $wp_taxonomies['post_tag'];
        unset( $wp_taxonomies['post_tag'] );

        $args = array_merge( get_object_vars( $post_tag ), $this->taxonomies['post']['post_tag'] );
        register_taxonomy( 'post_tag', 'post', $args );
      }
    }
  }

  /**
   * If needed, renames post admin menu entries
   */
  public function rename_menus() {
    if ( ! empty( $this->post_types ) && ! empty( $this->post_types['post'] ) ) {
      global $menu;
      global $submenu;

      $post = get_post_type_object( 'post' );

      $menu_name = $post->labels->menu_name;
      $all_items = $post->labels->all_items;
      $add_new_item = $post->labels->add_new_item;

      $menu[5][0] = $menu_name;
      $submenu['edit.php'][5][0] = $all_items;
      $submenu['edit.php'][10][0] = $add_new_item;
    }
  }

  /**
   * Prints a script that populates the post status dropdown into the "Create post" and "Edit post" admin
   * pages, placing the custom post statuses.
   */
  public function populate_post_status_dropdown() {
    global $post;
    if ( ! empty( $this->post_statuses ) && in_array( $post->post_type, array_keys( $this->post_statuses ) ) ) {
      ?>
      <script>
        jQuery( function( $ ) {

          /* Kindly printed by PHP */
          var statuses = {
            <?php foreach ( $this->post_statuses[$post->post_type] as $name => $post_status ): ?>
            '<?php echo $name; ?>': '<?php echo $post_status["label"]; ?>',
            <?php endforeach; ?>
          };

          var $dropdown = $( '#post_status' );
          var currentStatus = '<?php echo $post->post_status; ?>';

          /*
          Show published posts, so if a post was published for some reason,
          we can bring it back to another status
           */
          if ( currentStatus == 'auto-draft' || currentStatus in statuses ) {
            /* Hide default publish button */
            $( '#publish' ).hide();

            /*
            Change the label of the "Save draft" button to "Update". Some Wordpress script keep changing it
            back to "Save draft" every time the status is changed by the user, so we remove the id attribute
            from it making it unreachable. This cause the button to float to the right because Wordpress uses
            its id into the CSS, so we make it "float: left".
             */
            $( '#save-post' )
              .css( 'float', 'left' )
              .prop( 'id', '' )
              .attr( 'value', '<?php _e( "Update" ); ?>' );
          }

          /* Create dropdown menu options */
          for ( var i in statuses ) {
            var $option = $( document.createElement( 'option' ) )
              .prop( 'value', i ).html( statuses[i] )
              .prop( 'selected', i == currentStatus );
            $dropdown.append( $option );
          }

          /* Change the label of the current status to the right custom one */
          $( '#post-status-display' ).html( statuses[currentStatus] );
        } );
      </script>
      <?php
    }
  }

  /**
   * Allows the OAuth clients to authenticate through CORS (the WP REST API plugin doesn't allow
   * the Authorization header to be sent).
   */
  public function send_cors_headers() {
    $origin = get_http_origin();

    if ( $origin ) {
      header( 'Access-Control-Allow-Headers: Authorization' );
    }
  }

  /**
   * Taps into the Wordpress canonical redirect filter and redirects to the OAuth Server inverting
   * the Oauth2 Server rewrite rules. For now, this is the only way to access the OAuth Server when
   * the home_url is outside the Wordpress directory.
   * @param  string $redirect_url  The current URL to be redirected to.
   * @param  string $requested_url The original URL.
   * @return string|false          The new redirect URL. In this specific case, this is
   *                               the result of inverting the OAuth2 Server default
   *                               rewrite rule (/oauth/... => /index.php?oauth=...).
   *                               Wordpress applies this filter a second time passing the
   *                               already transformed redirect URL. In this case, we skip
   *                               redirection returning the original URL.
   *
   * @todo  The OAuth plugin contains a third rule:
   * $newRule += array('.well-known/(.+)' => 'index.php?well-known=' . $wp_rewrite->preg_index( 1 ) );
   */
  public function redirect_oauth( $redirect_url, $requested_url ) {
    // First time: the URL is something like "http://www.example.com/admin/oauth/keyword?query"
    // or something like "http://www.example.com/admin/wpoauthincludes/something?query"
    // $query is "keyword?query" or "something?query"
    if ( false !== ( $pos = strpos( $requested_url, 'oauth/' ) ) ) {
      // http://www.example.com/admin/index.php?oauth=keyword&query
      $query = str_replace('?', '&', substr( $requested_url, $pos + strlen( 'oauth/' ) ) );
      $url = site_url( "index.php?oauth=$query" );
      return $url;
    } elseif ( false !== ( $pos = strpos( $requested_url, 'wpoauthincludes/' ) ) ) {
      // http://www.example.com/admin/index.php?wpoauthincludes=something&query
      $query = str_replace('?', '&', substr( $requested_url, $pos + strlen( 'wpoauthincludes/' ) ) );
      $url = site_url( "index.php?wpoauthincludes=$query" );
      return $url;
    }

    /*
    Second time, the url is "http://www.example.com/admin/index.php?oauth=keyword&query" or
    "http://www.example.com/admin/index.php?wpoauthincludes=something&query": do nothing
     */
    if ( $this->str_contains( 'oauth', $requested_url ) ||
      $this->str_contains( 'wpoauthincludes', $requested_url ) ) {
      return $requested_url;
    }

    return $redirect_url;
  }

  /**
   * Same as redirect_oauth, but called when the home_url server is the same as the admin_url one.
   * Triggered by the 'template_redirect' action.
   * @param  string $template The current loaded template.
   *
   * @todo  The OAuth plugin contains a third rule:
   * $newRule += array('.well-known/(.+)' => 'index.php?well-known=' . $wp_rewrite->preg_index( 1 ) );
   */
  public function template_redirect_oauth( $template ) {
    if ( isset( $_SERVER['HTTP_HOST'] ) ) {
      // Since the string "wpoauthincludes" contains the "oauth" one, check "wpoauthincludes" first
      if ( $this->str_contains( 'wpoauthincludes', $_SERVER['REQUEST_URI'] ) ||
        $this->str_contains( 'oauth', $_SERVER['REQUEST_URI'] ) ) {

        // build the URL in the address bar
        $requested_url  = is_ssl() ? 'https://' : 'http://';
        $requested_url .= $_SERVER['HTTP_HOST'];
        $requested_url .= $_SERVER['REQUEST_URI'];

        $path = '';

        if ( false !== strpos( $requested_url, '?' ) ) {
          $path = substr( $requested_url, strpos( $requested_url, '?' ), strlen( $requested_url ) );
        }

        /*
        Check that the query string does NOT contain the 'wpoauthincludes' and 'oauth' parameters,
        to avoid redirect loops
         */
        if ( ! $this->str_contains( 'wpoauthincludes', $path ) && ! $this->str_contains( 'oauth', $path ) ) {
          $url = $this->redirect_oauth( $requested_url, $requested_url );
          wp_redirect( $url, 301 );
        }
      }
    }
  }

  /**
   * Returns true if $haystack contains $needle, false otherwise.
   * @param  string $needle   The string to seach for.
   * @param  string $haystack The string to be tested.
   * @return boolean          true if $haystack contains $needle, false otherwise.
   */
  private function str_contains( $needle, $haystack ) {
    return false !== strpos( $haystack, $needle );
  }
}
