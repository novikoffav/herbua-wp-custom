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
        wp_enqueue_style(
            'chld_thm_cfg_child',
            trailingslashit( get_stylesheet_directory_uri() ) . 'style.css',
            array( 'ct-main-styles','ct-admin-frontend-styles','ct-page-title-styles' )
        );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION


/**
 * 1) Auto-generate HerbUA object id + LSID for collector CPT
 */
function herbua_autogen_lsid($post_id, $post, $update) {
  if (!($post instanceof WP_Post)) return;
  if ($post->post_type !== 'collector' || $post->post_status === 'auto-draft') return;

  $obj_id = get_post_meta($post_id, 'herbua_object_id', true);

  // Only generate if missing
  if (!$obj_id) {
    $obj_id = str_pad((string)$post_id, 6, '0', STR_PAD_LEFT);
    update_post_meta($post_id, 'herbua_object_id', $obj_id);

    // Versioning: start at 1
    update_post_meta($post_id, 'herbua_version', 1);

    // LSID: urn:lsid:herbua.com:collectors:000257-1
    $lsid = "urn:lsid:herbua.com:collectors:{$obj_id}-1";
    update_post_meta($post_id, 'herbua_lsid', $lsid);
  }
}
add_action('save_post', 'herbua_autogen_lsid', 10, 3);


/**
 * 2) Rewrite rules
 *    - /id/collectors/000277      -> herbua_obj_id=000277 (HTML resolver redirect)
 *    - /id/collectors/000277.json -> herbua_obj_id=000277 & herbua_format=json (JSON response)
 *    - /lsid/collectors/000257-1  -> herbua_lsid_ns=collectors & herbua_lsid_obj=000257-1 (LSID resolver)
 */
add_action('init', function () {
  // JSON form first (more specific)
  add_rewrite_rule(
    '^id/collectors/([A-Za-z0-9]+)/\.json/?$',
    'index.php?herbua_obj_id=$matches[1]&herbua_format=json',
    'top'
  );

  // HTML resolver
  add_rewrite_rule(
    '^id/collectors/([A-Za-z0-9]+)/?$',
    'index.php?herbua_obj_id=$matches[1]',
    'top'
  );

  // LSID resolver endpoint:
  // /lsid/collectors/000257-1
  add_rewrite_rule(
    '^lsid/([A-Za-z0-9_-]+)/([A-Za-z0-9_-]+)/?$',
    'index.php?herbua_lsid_ns=$matches[1]&herbua_lsid_obj=$matches[2]',
    'top'
  );
});


/**
 * 2.2 Allow query vars
 */
add_filter('query_vars', function ($vars) {
  $vars[] = 'herbua_obj_id';
  $vars[] = 'herbua_format';
  $vars[] = 'herbua_lsid_ns';
  $vars[] = 'herbua_lsid_obj';
  return $vars;
});


/**
 * Helper: find collector post ID by herbua_object_id
 */
function herbua_find_collector_id_by_obj_id($obj_id) {
  $q = new WP_Query([
    'post_type'      => 'collector',
    'meta_key'       => 'herbua_object_id',
    'meta_value'     => $obj_id,
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
  ]);
  return !empty($q->posts) ? (int)$q->posts[0] : 0;
}


/**
 * Helper: output JSON and exit
 */
function herbua_send_json($data, $status = 200) {
  status_header($status);
  nocache_headers();
  header('Content-Type: application/json; charset=utf-8');
  echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}


/**
 * 2.3 Resolver logic:
 *   A) /lsid/collectors/000257-1  -> 303 -> /id/collectors/000257
 *   B) /id/collectors/000257.json -> JSON record (no redirect)
 *   C) /id/collectors/000257      -> 301 -> canonical collector page
 */
add_action('template_redirect', function () {

  /**
   * A) LSID resolution
   */
  $lsid_ns  = get_query_var('herbua_lsid_ns');
  $lsid_obj = get_query_var('herbua_lsid_obj');

  if ($lsid_ns && $lsid_obj) {
    // For now only collectors namespace
    if ($lsid_ns !== 'collectors') {
      status_header(404); nocache_headers();
      echo 'Unknown LSID namespace';
      exit;
    }

    // LSID object can be like 000257-1; strip revision suffix "-1"
    $base_id = preg_replace('/-\d+$/', '', $lsid_obj);
    $base_id = trim((string)$base_id);

    if ($base_id === '') {
      status_header(404); nocache_headers();
      echo 'Invalid LSID object id';
      exit;
    }

    // 303 See Other -> canonical ID URL
    $target = home_url('/id/collectors/' . rawurlencode($base_id));
    wp_redirect($target, 303);
    exit;
  }


  /**
   * B/C) /id/collectors/{id}[.json]
   */
  $obj_id = get_query_var('herbua_obj_id');
  if (!$obj_id) return;

  $obj_id = trim((string)$obj_id);
  $format = get_query_var('herbua_format');

  // Optional alternative: /id/collectors/000257?format=json
  if (isset($_GET['format']) && $_GET['format'] === 'json') {
    $format = 'json';
  }

  $post_id = herbua_find_collector_id_by_obj_id($obj_id);

  if (!$post_id) {
    // Unknown ID
    if ($format === 'json') {
      herbua_send_json(['error' => 'Unknown HerbUA object id', 'id' => $obj_id], 404);
    }

    status_header(404); nocache_headers();
    echo 'Unknown HerbUA object id';
    exit;
  }

  // If JSON requested, return metadata instead of redirect
  if ($format === 'json') {
    $lsid    = get_post_meta($post_id, 'herbua_lsid', true);
    $version = (int) get_post_meta($post_id, 'herbua_version', true);

    // Minimal but useful JSON record (extend anytime)
    $data = [
      'type'        => 'collector',
      'title'       => get_the_title($post_id),
      'wp_id'       => $post_id,
      'object_id'   => $obj_id,
      'version'     => $version ?: 1,
      'lsid'        => $lsid ?: null,
      'stable_id'   => home_url('/id/collectors/' . rawurlencode($obj_id)),
      'canonical'   => get_permalink($post_id),
      'modified'    => get_post_modified_time('c', true, $post_id),
      'external'    => [
        // If you later want: load ACF fields here.
        // 'orcid' => get_field('orcid', $post_id),
      ],
    ];

    herbua_send_json($data, 200);
  }

  // Otherwise redirect to canonical collector page (HTML)
  $permalink = get_permalink($post_id);

  // Optional: version handling (?v=2 -> anchor)
  if (isset($_GET['v'])) {
    $permalink = trailingslashit($permalink) . '#version-' . intval($_GET['v']);
  }

  wp_redirect($permalink, 301);
  exit;
});


/**
 * 3) ACF Local JSON in child theme
 */
add_filter('acf/settings/save_json', function ($path) {
  return get_stylesheet_directory() . '/acf-json';
});

add_filter('acf/settings/load_json', function ($paths) {
  $paths[] = get_stylesheet_directory() . '/acf-json';
  return $paths;
});


/**
 * 4) Biography cleanup (flatten line breaks)
 * NOTE: This only affects saves going forward; existing content stays as-is unless re-saved.
 */
add_filter('acf/update_value/name=biography', function ($value, $post_id, $field) {
  if (!is_string($value)) return $value;

  $value = preg_replace("/\r\n|\r|\n/", " ", $value);
  $value = preg_replace("/\s+/", " ", $value);

  return trim($value);
}, 10, 3);
