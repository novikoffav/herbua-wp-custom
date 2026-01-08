<?php
/**
 * Plugin Name: Collector Overview (KPIs + Bubble Graphs)
 * Description: Shows total collectors, countries represented, latest collectors, and bubble graphs for taxonomy counts. Shortcode: [collector_overview]
 * Version:     1.0.0
 * Author:      Andriy Novikov
 * License: GPL-3.0
 */

if (!defined('ABSPATH')) exit;

add_shortcode('collector_overview', function($atts){
    $a = shortcode_atts([
        'post_type'           => 'collector', // your CPT
        'countries_taxonomy'  => 'geography', // countries taxonomy (CPT UI)
        'groups_taxonomy'     => 'area',      // plant groups taxonomy (CPT UI)
        'latest_count'        => 5,           // how many latest collectors to list
        // bubble sizing (pixels)
        'bubble_min'          => 44,
        'bubble_max'          => 140,
        // show/hide sections (yes|no)
        'show_kpis'           => 'yes',
        'show_latest'         => 'yes',
        'show_countries_bubbles' => 'yes',
        'show_groups_bubbles'    => 'yes',
        // titles
        'title_latest'        => 'New Collectors',
        'title_countries'     => 'Top Countries',
        'title_groups'        => 'Taxonomic Groups',
        'countries_limit'        => 10,
        // NEW:
        'herbaria_taxonomy'      => 'herbarium', // change to your real taxonomy slug if needed
        'herbaria_limit'         => 10,            // top N
        'title_herbaria'         => 'Top Herbaria',

    ], $atts, 'collector_overview');

    // sanitize & coerce
    $countries_limit = max(0, (int)$a['countries_limit']); // 0 = no limit
    
    $tax_h          = sanitize_key($a['herbaria_taxonomy']);
    $herbaria_limit = max(0, (int)$a['herbaria_limit']); // 0 = show all
    $title_herbaria = sanitize_text_field($a['title_herbaria']);
    
    $post_type  = sanitize_key($a['post_type']);
    $tax_c      = sanitize_key($a['countries_taxonomy']);
    $tax_g      = sanitize_key($a['groups_taxonomy']);
    $latest_n   = max(0, (int)$a['latest_count']);
    $bmin       = max(20, (int)$a['bubble_min']);
    $bmax       = max($bmin+10, (int)$a['bubble_max']);

    $show_kpis  = ($a['show_kpis'] === 'yes');
    $show_latest= ($a['show_latest'] === 'yes');
    $show_cb    = ($a['show_countries_bubbles'] === 'yes');
    $show_gb    = ($a['show_groups_bubbles'] === 'yes');

    $title_latest   = sanitize_text_field($a['title_latest']);
    $title_countries= sanitize_text_field($a['title_countries']);
    $title_groups   = sanitize_text_field($a['title_groups']);

    $uid = 'co-'.wp_generate_password(6, false, false);

    // ---------- KPIs ----------
    $total_collectors = 0;
    $countries_represented = 0;

    $cnt = wp_count_posts($post_type);
    if ($cnt && isset($cnt->publish)) {
        $total_collectors = (int)$cnt->publish;
    }

    $country_terms = get_terms([
        'taxonomy'   => $tax_c,
        'hide_empty' => true,
        'fields'     => 'ids',
    ]);
    if (!is_wp_error($country_terms)) {
        $countries_represented = count($country_terms);
    }

    // ---------- Latest collectors ----------
    $latest_html = '';
    if ($latest_n > 0) {
        $q = new WP_Query([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $latest_n,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);
        if ($q->have_posts()) {
            $items = '';
            while ($q->have_posts()) { $q->the_post();
                $items .= '<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';
            }
            wp_reset_postdata();
            $latest_html = '<ul class="co-list">'.$items.'</ul>';
        } else {
            $latest_html = '<div class="co-empty">No recent collectors.</div>';
        }
    }

    // ---------- Bubble data builders ----------
    $build_terms = function($taxonomy, $limit = 0) {
    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => $limit > 0 ? $limit : 0, // 0 => no limit
    ]);
    if (is_wp_error($terms)) return [];
    $out = [];
    foreach ($terms as $t) {
        $out[] = [
            'name'  => $t->name,
            'slug'  => $t->slug,
            'count' => (int)$t->count,
            'link'  => get_term_link($t),
        ];
    }
    return $out;
};

    $countries_data = $show_cb ? $build_terms($tax_c, $countries_limit) : [];
    $groups_data    = $show_gb ? $build_terms($tax_g) : [];

    $max_countries = 0; foreach ($countries_data as $it) { if ($it['count'] > $max_countries) $max_countries = $it['count']; }
    $max_groups    = 0; foreach ($groups_data as $it) { if ($it['count'] > $max_groups) $max_groups = $it['count']; }
    
    $herbaria_data = $build_terms($tax_h, $herbaria_limit);
    $max_herbaria  = 0; foreach ($herbaria_data as $it) { if ($it['count'] > $max_herbaria) $max_herbaria = $it['count']; }
   

    // ---------- color helper (stable "random") ----------
    $color_for_slug = function($slug){
        $h = crc32($slug) % 360; // 0..359
        $s = 65; $l = 55;
        return "hsl($h, {$s}%, {$l}%)";
    };

    // ---------- bubble HTML builders ----------
    $bubble_html = function($data, $max_count, $title) use ($bmin, $bmax, $color_for_slug) {
        if (empty($data) || !$max_count) {
            // show subtle note if no data
            $t = esc_html($title);
            return "<div class=\"co-card\"><div class=\"co-card-title\">$t</div><div class=\"co-empty\">No data yet.</div></div>";
        }
        $items = '';
        foreach ($data as $d) {
            $name  = esc_html($d['name']);
            $slug  = sanitize_title($d['slug']);
            $count = (int)$d['count'];
            $link  = is_wp_error($d['link']) ? '#' : esc_url($d['link']);

            // size map: min .. max (sqrt scaling for nicer distribution)
            $t = $count / $max_count;
            $t = max(0, min(1, $t));
            $t = sqrt($t); // perceptual
            $size = (int)round($bmin + ($bmax - $bmin) * $t);

            $bg = esc_attr($color_for_slug($slug));
            $title_attr = esc_attr("$name — $count collectors");
            $items .= "<a href=\"$link\" class=\"co-bubble\" title=\"$title_attr\" aria-label=\"$title_attr\" style=\"--co-size:{$size}px; --co-bg:$bg\"><span class=\"co-bubble-label\">$name</span><span class=\"co-bubble-count\">$count</span></a>";
        }
        $t = esc_html($title);
        return "<div class=\"co-card\"><div class=\"co-card-title\">$t</div><div class=\"co-bubbles\">$items</div></div>";
    };

    $countries_html = $show_cb ? $bubble_html($countries_data, $max_countries, $title_countries) : '';
    $groups_html    = $show_gb ? $bubble_html($groups_data, $max_groups, $title_groups)       : '';
    $herbaria_html = $bubble_html($herbaria_data, $max_herbaria, $title_herbaria);

    // ---------- styles ----------
    ob_start();
    ?>
    <style>
      #<?php echo esc_attr($uid); ?> .co-wrap { display:grid; gap:16px; }
      #<?php echo esc_attr($uid); ?> .co-kpis { display:grid; gap:12px; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); }
      #<?php echo esc_attr($uid); ?> .co-kpi {
        background:#fff; border:1px solid #eef1f3; border-radius:12px; padding:14px 16px;
        box-shadow:0 2px 8px rgba(0,0,0,.04);
      }
      #<?php echo esc_attr($uid); ?> .co-kpi .label { font-size:13px; color:#5a6a78; letter-spacing:.2px; }
      #<?php echo esc_attr($uid); ?> .co-kpi .value { font-size:28px; font-weight:700; color:#1d2a33; line-height:1.15; margin-top:6px; }

      #<?php echo esc_attr($uid); ?> .co-card {
        background:#fff; border:1px solid #eef1f3; border-radius:12px; padding:14px 16px;
        box-shadow:0 2px 8px rgba(0,0,0,.04);
      }
      #<?php echo esc_attr($uid); ?> .co-card-title { font-weight:700; margin-bottom:10px; color:#1d2a33; }
      #<?php echo esc_attr($uid); ?> .co-list { margin:0; padding-left:18px; }
      #<?php echo esc_attr($uid); ?> .co-list li { margin:6px 0; }
      #<?php echo esc_attr($uid); ?> .co-empty { color:#7a8a96; font-size:13px; }

      /* Bubbles */
      #<?php echo esc_attr($uid); ?> .co-bubbles {
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  align-items:center; /* center-align bubbles vertically */
}

      #<?php echo esc_attr($uid); ?> .co-bubble {
        --co-size: 72px;
        --co-bg: hsl(160,60%,50%);
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        width:var(--co-size); height:var(--co-size); border-radius:999px; text-decoration:none;
        color:#0d2026; background:var(--co-bg);
        border:1px solid rgba(0,0,0,.06);
        box-shadow:0 4px 10px rgba(0,0,0,.10);
        position:relative; overflow:hidden;
      }
      #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-label {
        padding:4px 6px; text-align:center; font-size:12px; line-height:1.1; font-weight:700;
        color:#052129; mix-blend-mode:multiply;
      }
      #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-count {
        font-size:12px; font-weight:600; opacity:.8; line-height:1;
      }
      #<?php echo esc_attr($uid); ?> .co-bubble:hover { transform:translateY(-2px); box-shadow:0 8px 16px rgba(0,0,0,.12); }
      @media (max-width: 520px){
        #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-label { font-size:11px; }
        #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-count { font-size:11px; }
      }

      /* Layout blocks */
      #<?php echo esc_attr($uid); ?> .co-grid-2 { display:grid; gap:16px; grid-template-columns: 1fr 1fr; }
      @media (max-width: 960px){ #<?php echo esc_attr($uid); ?> .co-grid-2 { grid-template-columns: 1fr; } }
    </style>

    <div id="<?php echo esc_attr($uid); ?>">
      <div class="co-wrap">

        <?php if ($show_kpis): ?>
        <div class="co-kpis">
          <div class="co-kpi">
            <div class="label">Total Collectors</div>
            <div class="value"><?php echo number_format_i18n($total_collectors); ?></div>
          </div>
          <div class="co-kpi">
            <div class="label">Countries Represented</div>
            <div class="value"><?php echo number_format_i18n($countries_represented); ?></div>
          </div>
        </div>
        <?php endif; ?>

        <div class="co-grid-2">
          <?php if ($show_latest): ?>
          <div class="co-card">
            <div class="co-card-title"><?php echo esc_html($title_latest); ?></div>
            <?php echo $latest_html; ?>
          </div>
          <?php endif; ?>

          <?php echo $countries_html; ?>
        </div>

        <div class="co-grid-2">
            <?php echo $groups_html; ?>
            <?php echo $herbaria_html; ?>
        </div>
        

      </div>
    </div>
    <?php
    return ob_get_clean();
});
