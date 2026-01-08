<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'ct-main-styles','ct-admin-frontend-styles','ct-page-title-styles' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

function herbua_autogen_lsid($post_id, $post, $update) {
  if ($post->post_type !== 'collector' || $post->post_status === 'auto-draft') return;

  $obj_id = get_post_meta($post_id, 'herbua_object_id', true);
  if (!$obj_id) {
    $obj_id = str_pad($post_id, 6, '0', STR_PAD_LEFT);
    update_post_meta($post_id, 'herbua_object_id', $obj_id);
    update_post_meta($post_id, 'herbua_version', 1);
    $lsid = "urn:lsid:herbua.com:collectors:{$obj_id}-1";
    update_post_meta($post_id, 'herbua_lsid', $lsid);
  }
}
add_action('save_post', 'herbua_autogen_lsid', 10, 3);

// 2.1 Add rewrite rule: /id/collectors/000277 -> query var herbua_obj_id=000277
add_action('init', function () {
  add_rewrite_rule('^id/collectors/([A-Za-z0-9]+)/?$', 'index.php?herbua_obj_id=$matches[1]', 'top');
});

// 2.2 Allow the query var
add_filter('query_vars', function ($vars) {
  $vars[] = 'herbua_obj_id';
  return $vars;
});

// 2.3 Resolve to the canonical collector page
add_action('template_redirect', function () {
  $obj_id = get_query_var('herbua_obj_id');
  if (!$obj_id) return;

  // Look up the collector by meta herbua_object_id
  $q = new WP_Query([
    'post_type'      => 'collector',
    'meta_key'       => 'herbua_object_id',
    'meta_value'     => $obj_id,
    'posts_per_page' => 1,
    'fields'         => 'ids'
  ]);

  if (!empty($q->posts)) {
    $permalink = get_permalink($q->posts[0]);

    // Optional: version handling (?v=2 → just append an anchor or show a changelog)
    if (isset($_GET['v'])) {
      // e.g., #version-2 anchor if you have a changelog on the page
      $permalink = trailingslashit($permalink) . '#version-' . intval($_GET['v']);
    }

    // 301 = permanent resolver (good for SEO)
    wp_redirect($permalink, 301);
    exit;
  }

  // If unknown ID, return 404
  status_header(404); nocache_headers();
  echo 'Unknown HerbUA object id';
  exit;
});

// ACF Local JSON in child theme
add_filter('acf/settings/save_json', function ($path) {
    return get_stylesheet_directory() . '/acf-json';
});

add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});

