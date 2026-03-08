<?php
/**
 * Plugin Name: Collector Overview (KPIs + Bubble Graphs + Temporal Charts)
 * Description: Shows collector KPIs, latest collectors, bubble graphs, and temporal charts. Shortcode: [collector_overview]
 * Version:     1.1.0
 * Author:      You
 */

if (!defined('ABSPATH')) exit;

add_shortcode('collector_overview', function($atts){

    $a = shortcode_atts([
        'post_type'                => 'collector',
        'countries_taxonomy'       => 'geography',
        'groups_taxonomy'          => 'area',
        'herbaria_taxonomy'        => 'herbarium',

        'latest_count'             => 5,

        'bubble_min'               => 44,
        'bubble_max'               => 140,

        'countries_limit'          => 10,
        'groups_limit'             => 10,
        'herbaria_limit'           => 10,

        'show_kpis'                => 'yes',
        'show_latest'              => 'yes',
        'show_countries_bubbles'   => 'yes',
        'show_groups_bubbles'      => 'yes',
        'show_herbaria_bubbles'    => 'yes',
        'show_temporal_charts'     => 'yes',

        'title_latest'             => 'New Collectors',
        'title_countries'          => 'Top Countries',
        'title_groups'             => 'Taxonomic Groups',
        'title_herbaria'           => 'Top Herbaria',
        'title_life_chart'         => 'Collectors Alive by Year',
        'title_activity_chart'     => 'Collectors Active by Decade',
        'title_filters'            => 'Filter Statistics',
    ], $atts, 'collector_overview');

    $post_type        = sanitize_key($a['post_type']);
    $tax_c            = sanitize_key($a['countries_taxonomy']);
    $tax_g            = sanitize_key($a['groups_taxonomy']);
    $tax_h            = sanitize_key($a['herbaria_taxonomy']);

    $latest_n         = max(0, (int) $a['latest_count']);

    $bmin             = max(20, (int) $a['bubble_min']);
    $bmax             = max($bmin + 10, (int) $a['bubble_max']);

    $countries_limit  = max(0, (int) $a['countries_limit']);
    $groups_limit     = max(0, (int) $a['groups_limit']);
    $herbaria_limit   = max(0, (int) $a['herbaria_limit']);

    $show_kpis        = ($a['show_kpis'] === 'yes');
    $show_latest      = ($a['show_latest'] === 'yes');
    $show_cb          = ($a['show_countries_bubbles'] === 'yes');
    $show_gb          = ($a['show_groups_bubbles'] === 'yes');
    $show_hb          = ($a['show_herbaria_bubbles'] === 'yes');
    $show_tc          = ($a['show_temporal_charts'] === 'yes');

    $title_latest     = sanitize_text_field($a['title_latest']);
    $title_countries  = sanitize_text_field($a['title_countries']);
    $title_groups     = sanitize_text_field($a['title_groups']);
    $title_herbaria   = sanitize_text_field($a['title_herbaria']);
    $title_life_chart = sanitize_text_field($a['title_life_chart']);
    $title_act_chart  = sanitize_text_field($a['title_activity_chart']);
    $title_filters    = sanitize_text_field($a['title_filters']);

    $uid = 'co-' . wp_generate_password(6, false, false);

    // Load Chart.js once
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
        [],
        '4.4.1',
        true
    );

    // ---------- Filters from URL ----------
    $filter_geo       = isset($_GET['co_geo']) ? sanitize_text_field($_GET['co_geo']) : '';
    $filter_group     = isset($_GET['co_group']) ? sanitize_text_field($_GET['co_group']) : '';
    $filter_herbarium = isset($_GET['co_herbarium']) ? sanitize_text_field($_GET['co_herbarium']) : '';

    // ---------- Helpers ----------
    $color_for_slug = function($slug){
        $h = crc32($slug) % 360;
        return "hsl($h, 65%, 55%)";
    };

    $normalize_interval = function($start_raw, $end_raw){
        $start = (int) $start_raw;
        $end   = (int) $end_raw;

        if (!$start && !$end) return [null, null];
        if ($start && !$end) $end = $start;
        if (!$start && $end) $start = $end;

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    };

    $build_tax_query = function() use ($tax_c, $tax_g, $tax_h, $filter_geo, $filter_group, $filter_herbarium) {
        $tax_query = ['relation' => 'AND'];

        if ($filter_geo !== '') {
            $tax_query[] = [
                'taxonomy' => $tax_c,
                'field'    => 'slug',
                'terms'    => $filter_geo,
            ];
        }

        if ($filter_group !== '') {
            $tax_query[] = [
                'taxonomy' => $tax_g,
                'field'    => 'slug',
                'terms'    => $filter_group,
            ];
        }

        if ($filter_herbarium !== '') {
            $tax_query[] = [
                'taxonomy' => $tax_h,
                'field'    => 'slug',
                'terms'    => $filter_herbarium,
            ];
        }

        return count($tax_query) > 1 ? $tax_query : [];
    };

    $build_filtered_ids = function() use ($post_type, $build_tax_query) {
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $tax_query = $build_tax_query();
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        return get_posts($args);
    };

    $build_term_counts_from_posts = function($post_ids, $taxonomy, $limit = 0) {
        $counts = [];

        foreach ($post_ids as $pid) {
            $terms = wp_get_post_terms($pid, $taxonomy);
            if (is_wp_error($terms) || empty($terms)) continue;

            foreach ($terms as $t) {
                if (!isset($counts[$t->term_id])) {
                    $counts[$t->term_id] = [
                        'name'  => $t->name,
                        'slug'  => $t->slug,
                        'count' => 0,
                        'link'  => get_term_link($t),
                    ];
                }
                $counts[$t->term_id]['count']++;
            }
        }

        uasort($counts, function($a, $b){
            if ($a['count'] === $b['count']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $b['count'] <=> $a['count'];
        });

        $out = array_values($counts);

        if ($limit > 0) {
            $out = array_slice($out, 0, $limit);
        }

        return $out;
    };

    $build_life_density = function($post_ids) use ($normalize_interval) {
        $spans = [];
        $min_year = null;
        $max_year = null;

        foreach ($post_ids as $pid) {
            $start_raw = get_field('life_start_year', $pid);
            $end_raw   = get_field('life_end_year', $pid);
            [$start, $end] = $normalize_interval($start_raw, $end_raw);

            if ($start === null || $end === null) continue;

            $spans[] = [$start, $end];
            if ($min_year === null || $start < $min_year) $min_year = $start;
            if ($max_year === null || $end > $max_year) $max_year = $end;
        }

        if (empty($spans) || $min_year === null || $max_year === null) {
            return ['labels' => [], 'values' => []];
        }

        $labels = [];
        $values = [];

        for ($year = $min_year; $year <= $max_year; $year++) {
            $count = 0;
            foreach ($spans as [$start, $end]) {
                if ($year >= $start && $year <= $end) {
                    $count++;
                }
            }
            $labels[] = (string) $year;
            $values[] = $count;
        }

        return ['labels' => $labels, 'values' => $values];
    };

    $build_activity_density_by_decade = function($post_ids) use ($normalize_interval) {
        $counts = [];
        $min_decade = null;
        $max_decade = null;

        foreach ($post_ids as $pid) {
            $start_raw = get_field('activity_start_year', $pid);
            $end_raw   = get_field('activity_end_year', $pid);
            [$start, $end] = $normalize_interval($start_raw, $end_raw);

            if ($start === null || $end === null) continue;

            $decade_start = (int) floor($start / 10) * 10;
            $decade_end   = (int) floor($end / 10) * 10;

            if ($min_decade === null || $decade_start < $min_decade) $min_decade = $decade_start;
            if ($max_decade === null || $decade_end > $max_decade) $max_decade = $decade_end;

            for ($d = $decade_start; $d <= $decade_end; $d += 10) {
                if (!isset($counts[$d])) $counts[$d] = 0;
                $counts[$d]++;
            }
        }

        if ($min_decade === null || $max_decade === null) {
            return ['labels' => [], 'values' => []];
        }

        $labels = [];
        $values = [];

        for ($d = $min_decade; $d <= $max_decade; $d += 10) {
            $labels[] = $d . 's';
            $values[] = isset($counts[$d]) ? $counts[$d] : 0;
        }

        return ['labels' => $labels, 'values' => $values];
    };

    $bubble_html = function($data, $max_count, $title) use ($bmin, $bmax, $color_for_slug) {
        if (empty($data) || !$max_count) {
            $t = esc_html($title);
            return "<div class=\"co-card\"><div class=\"co-card-title\">$t</div><div class=\"co-empty\">No data yet.</div></div>";
        }

        $items = '';
        foreach ($data as $d) {
            $name  = esc_html($d['name']);
            $slug  = sanitize_title($d['slug']);
            $count = (int) $d['count'];
            $link  = is_wp_error($d['link']) ? '#' : esc_url($d['link']);

            $ratio = $count / $max_count;
            $ratio = max(0, min(1, $ratio));
            $ratio = sqrt($ratio);
            $size  = (int) round($bmin + ($bmax - $bmin) * $ratio);

            $bg = esc_attr($color_for_slug($slug));
            $title_attr = esc_attr("$name — $count collectors");

            $items .= "<a href=\"$link\" class=\"co-bubble\" title=\"$title_attr\" aria-label=\"$title_attr\" style=\"--co-size:{$size}px; --co-bg:$bg\"><span class=\"co-bubble-label\">$name</span><span class=\"co-bubble-count\">$count</span></a>";
        }

        $t = esc_html($title);
        return "<div class=\"co-card\"><div class=\"co-card-title\">$t</div><div class=\"co-bubbles\">$items</div></div>";
    };

    // ---------- Filtered post ids ----------
    $filtered_ids = $build_filtered_ids();

    // ---------- KPIs ----------
    $total_collectors = count($filtered_ids);

    $countries_represented = 0;
    if (!empty($filtered_ids)) {
        $tmp = $build_term_counts_from_posts($filtered_ids, $tax_c, 0);
        $countries_represented = count($tmp);
    }

    // ---------- Latest collectors ----------
    $latest_html = '';
    if ($latest_n > 0) {
        $latest_args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $latest_n,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $tax_query = $build_tax_query();
        if (!empty($tax_query)) {
            $latest_args['tax_query'] = $tax_query;
        }

        $q = new WP_Query($latest_args);

        if ($q->have_posts()) {
            $items = '';
            while ($q->have_posts()) {
                $q->the_post();
                $items .= '<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';
            }
            wp_reset_postdata();
            $latest_html = '<ul class="co-list">'.$items.'</ul>';
        } else {
            $latest_html = '<div class="co-empty">No recent collectors.</div>';
        }
    }

    // ---------- Bubble data ----------
    $countries_data = $show_cb ? $build_term_counts_from_posts($filtered_ids, $tax_c, $countries_limit) : [];
    $groups_data    = $show_gb ? $build_term_counts_from_posts($filtered_ids, $tax_g, $groups_limit) : [];
    $herbaria_data  = $show_hb ? $build_term_counts_from_posts($filtered_ids, $tax_h, $herbaria_limit) : [];

    $max_countries = 0; foreach ($countries_data as $it) { if ($it['count'] > $max_countries) $max_countries = $it['count']; }
    $max_groups    = 0; foreach ($groups_data as $it)    { if ($it['count'] > $max_groups)    $max_groups = $it['count']; }
    $max_herbaria  = 0; foreach ($herbaria_data as $it)  { if ($it['count'] > $max_herbaria)  $max_herbaria = $it['count']; }

    $countries_html = $show_cb ? $bubble_html($countries_data, $max_countries, $title_countries) : '';
    $groups_html    = $show_gb ? $bubble_html($groups_data, $max_groups, $title_groups) : '';
    $herbaria_html  = $show_hb ? $bubble_html($herbaria_data, $max_herbaria, $title_herbaria) : '';

    // ---------- Temporal charts ----------
    $life_chart     = $show_tc ? $build_life_density($filtered_ids) : ['labels' => [], 'values' => []];
    $activity_chart = $show_tc ? $build_activity_density_by_decade($filtered_ids) : ['labels' => [], 'values' => []];

    // ---------- Filter dropdown terms ----------
    $geo_terms       = get_terms(['taxonomy' => $tax_c, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
    $group_terms     = get_terms(['taxonomy' => $tax_g, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
    $herbarium_terms = get_terms(['taxonomy' => $tax_h, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);

    ob_start();
    ?>
    <style>
      #<?php echo esc_attr($uid); ?> .co-wrap { display:grid; gap:16px; }

      #<?php echo esc_attr($uid); ?> .co-filters {
        background:#fff; border:1px solid #eef1f3; border-radius:12px; padding:14px 16px;
        box-shadow:0 2px 8px rgba(0,0,0,.04);
      }
      #<?php echo esc_attr($uid); ?> .co-filters-title {
        font-weight:700; margin-bottom:12px; color:#1d2a33;
      }
      #<?php echo esc_attr($uid); ?> .co-filter-form {
        display:flex; flex-wrap:wrap; gap:12px; align-items:end;
      }
      #<?php echo esc_attr($uid); ?> .co-filter-group {
        display:flex; flex-direction:column; min-width:220px;
      }
      #<?php echo esc_attr($uid); ?> .co-filter-group label {
        font-size:13px; color:#5a6a78; margin-bottom:4px;
      }
      #<?php echo esc_attr($uid); ?> .co-filter-group select {
        padding:.55rem .75rem; border:1px solid #ccc; border-radius:6px; background:#fff;
      }
      #<?php echo esc_attr($uid); ?> .co-filter-actions {
        display:flex; gap:8px; align-items:center;
      }
      #<?php echo esc_attr($uid); ?> .co-filter-actions .button {
        padding:.6rem 1rem;
      }

      #<?php echo esc_attr($uid); ?> .co-kpis {
        display:grid; gap:12px; grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
      }
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

      #<?php echo esc_attr($uid); ?> .co-bubbles {
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        align-items:center;
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
        transition:transform .15s ease, box-shadow .15s ease;
      }
      #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-label {
        padding:4px 6px; text-align:center; font-size:12px; line-height:1.1; font-weight:700;
        color:#052129; mix-blend-mode:multiply;
      }
      #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-count {
        font-size:12px; font-weight:600; opacity:.8; line-height:1;
      }
      #<?php echo esc_attr($uid); ?> .co-bubble:hover {
        transform:translateY(-2px);
        box-shadow:0 8px 16px rgba(0,0,0,.12);
      }

      #<?php echo esc_attr($uid); ?> .co-grid-2 {
        display:grid; gap:16px; grid-template-columns:1fr 1fr;
      }

      #<?php echo esc_attr($uid); ?> .co-chart-wrap {
        position:relative;
        min-height:340px;
      }

      @media (max-width: 960px){
        #<?php echo esc_attr($uid); ?> .co-grid-2 { grid-template-columns:1fr; }
      }
      @media (max-width: 520px){
        #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-label { font-size:11px; }
        #<?php echo esc_attr($uid); ?> .co-bubble .co-bubble-count { font-size:11px; }
        #<?php echo esc_attr($uid); ?> .co-filter-group { min-width:100%; }
      }
    </style>

    <div id="<?php echo esc_attr($uid); ?>">
      <div class="co-wrap">

        <div class="co-filters">
          <div class="co-filters-title"><?php echo esc_html($title_filters); ?></div>
          <form method="get" class="co-filter-form">
            <div class="co-filter-group">
              <label for="<?php echo esc_attr($uid); ?>-geo">Country</label>
              <select name="co_geo" id="<?php echo esc_attr($uid); ?>-geo">
                <option value="">All</option>
                <?php if (!is_wp_error($geo_terms)) :
                  foreach ($geo_terms as $t) :
                    printf(
                      '<option value="%s" %s>%s</option>',
                      esc_attr($t->slug),
                      selected($filter_geo, $t->slug, false),
                      esc_html($t->name)
                    );
                  endforeach;
                endif; ?>
              </select>
            </div>

            <div class="co-filter-group">
              <label for="<?php echo esc_attr($uid); ?>-group">Plant taxonomic group</label>
              <select name="co_group" id="<?php echo esc_attr($uid); ?>-group">
                <option value="">All</option>
                <?php if (!is_wp_error($group_terms)) :
                  foreach ($group_terms as $t) :
                    printf(
                      '<option value="%s" %s>%s</option>',
                      esc_attr($t->slug),
                      selected($filter_group, $t->slug, false),
                      esc_html($t->name)
                    );
                  endforeach;
                endif; ?>
              </select>
            </div>

            <div class="co-filter-group">
              <label for="<?php echo esc_attr($uid); ?>-herbarium">Hosting herbarium</label>
              <select name="co_herbarium" id="<?php echo esc_attr($uid); ?>-herbarium">
                <option value="">All</option>
                <?php if (!is_wp_error($herbarium_terms)) :
                  foreach ($herbarium_terms as $t) :
                    printf(
                      '<option value="%s" %s>%s</option>',
                      esc_attr($t->slug),
                      selected($filter_herbarium, $t->slug, false),
                      esc_html($t->name)
                    );
                  endforeach;
                endif; ?>
              </select>
            </div>

            <div class="co-filter-actions">
              <button type="submit" class="button">Apply filters</button>
              <a class="button" href="<?php echo esc_url(get_permalink()); ?>">Reset</a>
            </div>
          </form>
        </div>

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

        <?php if ($show_tc): ?>
        <div class="co-grid-2">
          <div class="co-card">
            <div class="co-card-title"><?php echo esc_html($title_life_chart); ?></div>
            <?php if (!empty($life_chart['labels'])) : ?>
              <div class="co-chart-wrap">
                <canvas id="<?php echo esc_attr($uid); ?>-life-chart"></canvas>
              </div>
            <?php else : ?>
              <div class="co-empty">No life-year data available for the current filter.</div>
            <?php endif; ?>
          </div>

          <div class="co-card">
            <div class="co-card-title"><?php echo esc_html($title_act_chart); ?></div>
            <?php if (!empty($activity_chart['labels'])) : ?>
              <div class="co-chart-wrap">
                <canvas id="<?php echo esc_attr($uid); ?>-activity-chart"></canvas>
              </div>
            <?php else : ?>
              <div class="co-empty">No activity-year data available for the current filter.</div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <?php if ($show_tc && (!empty($life_chart['labels']) || !empty($activity_chart['labels']))) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      const lifeCanvas = document.getElementById('<?php echo esc_js($uid); ?>-life-chart');
      if (lifeCanvas) {
        new Chart(lifeCanvas, {
          type: 'line',
          data: {
            labels: <?php echo wp_json_encode($life_chart['labels']); ?>,
            datasets: [{
              label: 'Collectors alive',
              data: <?php echo wp_json_encode($life_chart['values']); ?>,
              fill: true,
              tension: 0.25,
              borderWidth: 2,
              borderColor: 'rgba(54, 162, 235, 1)',
              backgroundColor: 'rgba(54, 162, 235, 0.22)',
              pointRadius: 0,
              pointHoverRadius: 3
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
              mode: 'index',
              intersect: false
            },
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                callbacks: {
                  label: function(ctx) {
                    return ' ' + ctx.parsed.y + ' collectors';
                  }
                }
              }
            },
            scales: {
              x: {
                ticks: {
                  maxTicksLimit: 12,
                  autoSkip: true
                },
                title: {
                  display: true,
                  text: 'Year'
                }
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0
                },
                title: {
                  display: true,
                  text: 'Collectors'
                }
              }
            }
          }
        });
      }

      const activityCanvas = document.getElementById('<?php echo esc_js($uid); ?>-activity-chart');
      if (activityCanvas) {
        new Chart(activityCanvas, {
  type: 'line',
  data: {
    labels: <?php echo wp_json_encode($activity_chart['labels']); ?>,
    datasets: [{
      label: 'Collectors active',
      data: <?php echo wp_json_encode($activity_chart['values']); ?>,
      fill: true,
      tension: 0.05,
      borderWidth: 2,
      borderColor: 'rgba(255, 159, 64, 1)',
      backgroundColor: 'rgba(255, 159, 64, 0.22)',
      pointRadius: 0,
      pointHoverRadius: 3
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index',
      intersect: false
    },
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        callbacks: {
          label: function(ctx) {
            return ' ' + ctx.parsed.y + ' collectors';
          }
        }
      }
    },
    scales: {
      x: {
        ticks: {
          maxTicksLimit: 14,
          autoSkip: true
        },
        title: {
          display: true,
          text: 'Decade'
        }
      },
      y: {
        beginAtZero: true,
        ticks: {
          precision: 0
        },
        title: {
          display: true,
          text: 'Collectors'
        }
      }
    }
  }
});
      }
    });
    </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
});