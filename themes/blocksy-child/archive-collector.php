<?php
/**
 * Archive template for Collector (Blocksy child) — TAXONOMY + YEAR FILTER VERSION
 * - Filters by taxonomies: geography, area, herbarium
 * - Search by title/content via ?s=
 * - Filters by years of life and years of activity
 * - Supports two year filter modes:
 *      overlap = interval intersects searched range
 *      within  = interval is fully inside searched range
 * - If only one year bound exists in DB, it is treated as a single year
 * - Uses custom query with pagination
 * - Shows ACF URL fields as links
 */

get_header();

// ====== Optional public CSV download (set your URL or leave empty) ======
$csv_url = 'https://URL TO YOUR CSV FILE WITH ARCHIVED DATABASE TO DOWLOAD BY USERS';
// =======================================================================

// ---------- Small helpers ----------
if (!function_exists('col_url_from_acf')) {
  function col_url_from_acf($val) {
    if (empty($val)) return null;
    if (is_array($val)) {
      if (!empty($val['url'])) return esc_url_raw($val['url']); // ACF Link field
      return null;
    }
    $s = trim((string)$val);
    return $s ? esc_url_raw($s) : null; // assumes full URL stored
  }
}

if (!function_exists('col_link_inline')) {
  function col_link_inline($label, $val) {
    $url = col_url_from_acf($val);
    if (!$url) return '';
    return '<a href="' . esc_url($url) . '" target="_blank" rel="nofollow noopener">' . esc_html($label) . '</a>';
  }
}

if (!function_exists('herbua_collector_year_filter_clauses')) {
  function herbua_collector_year_filter_clauses($clauses, $query) {
    global $wpdb;

    $life_from = (int) $query->get('herbua_life_from');
    $life_to   = (int) $query->get('herbua_life_to');
    $act_from  = (int) $query->get('herbua_act_from');
    $act_to    = (int) $query->get('herbua_act_to');

    $life_mode = $query->get('herbua_life_mode') ?: 'overlap';
    $act_mode  = $query->get('herbua_act_mode') ?: 'overlap';

    if (!$life_from && !$life_to && !$act_from && !$act_to) {
      return $clauses;
    }

    // --- Life years filter
    if ($life_from || $life_to) {
      $clauses['join'] .= "
        LEFT JOIN {$wpdb->postmeta} AS life_start_pm
          ON ({$wpdb->posts}.ID = life_start_pm.post_id AND life_start_pm.meta_key = 'life_start_year')
        LEFT JOIN {$wpdb->postmeta} AS life_end_pm
          ON ({$wpdb->posts}.ID = life_end_pm.post_id AND life_end_pm.meta_key = 'life_end_year')
      ";

      $ls = "NULLIF(life_start_pm.meta_value, '')";
      $le = "NULLIF(life_end_pm.meta_value, '')";

      // If only one bound exists, treat it as a single year
      $life_start_eff = "COALESCE(CAST($ls AS SIGNED), CAST($le AS SIGNED))";
      $life_end_eff   = "COALESCE(CAST($le AS SIGNED), CAST($ls AS SIGNED))";

      if ($life_mode === 'within') {
        $clauses['where'] .= $wpdb->prepare(
          " AND (
              $life_start_eff IS NOT NULL
              AND $life_end_eff IS NOT NULL
              AND $life_start_eff >= %d
              AND $life_end_eff <= %d
            )",
          $life_from,
          $life_to
        );
      } else {
        $clauses['where'] .= $wpdb->prepare(
          " AND (
              $life_start_eff IS NOT NULL
              AND $life_end_eff IS NOT NULL
              AND $life_start_eff <= %d
              AND $life_end_eff >= %d
            )",
          $life_to,
          $life_from
        );
      }
    }

    // --- Activity years filter
    if ($act_from || $act_to) {
      $clauses['join'] .= "
        LEFT JOIN {$wpdb->postmeta} AS act_start_pm
          ON ({$wpdb->posts}.ID = act_start_pm.post_id AND act_start_pm.meta_key = 'activity_start_year')
        LEFT JOIN {$wpdb->postmeta} AS act_end_pm
          ON ({$wpdb->posts}.ID = act_end_pm.post_id AND act_end_pm.meta_key = 'activity_end_year')
      ";

      $as = "NULLIF(act_start_pm.meta_value, '')";
      $ae = "NULLIF(act_end_pm.meta_value, '')";

      // If only one bound exists, treat it as a single year
      $act_start_eff = "COALESCE(CAST($as AS SIGNED), CAST($ae AS SIGNED))";
      $act_end_eff   = "COALESCE(CAST($ae AS SIGNED), CAST($as AS SIGNED))";

      if ($act_mode === 'within') {
        $clauses['where'] .= $wpdb->prepare(
          " AND (
              $act_start_eff IS NOT NULL
              AND $act_end_eff IS NOT NULL
              AND $act_start_eff >= %d
              AND $act_end_eff <= %d
            )",
          $act_from,
          $act_to
        );
      } else {
        $clauses['where'] .= $wpdb->prepare(
          " AND (
              $act_start_eff IS NOT NULL
              AND $act_end_eff IS NOT NULL
              AND $act_start_eff <= %d
              AND $act_end_eff >= %d
            )",
          $act_to,
          $act_from
        );
      }
    }

    $clauses['groupby'] = "{$wpdb->posts}.ID";

    return $clauses;
  }
}

// ---------- Inputs ----------
$search    = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$geo       = isset($_GET['geo']) ? sanitize_text_field($_GET['geo']) : '';
$area      = isset($_GET['area']) ? sanitize_text_field($_GET['area']) : '';
$herbarium = isset($_GET['herbarium']) ? sanitize_text_field($_GET['herbarium']) : '';

$life_from = isset($_GET['life_from']) ? (int) $_GET['life_from'] : 0;
$life_to   = isset($_GET['life_to']) ? (int) $_GET['life_to'] : 0;
$act_from  = isset($_GET['act_from']) ? (int) $_GET['act_from'] : 0;
$act_to    = isset($_GET['act_to']) ? (int) $_GET['act_to'] : 0;

$life_mode = isset($_GET['life_mode']) ? sanitize_text_field($_GET['life_mode']) : 'overlap';
$act_mode  = isset($_GET['act_mode']) ? sanitize_text_field($_GET['act_mode']) : 'overlap';

if (!in_array($life_mode, ['overlap', 'within'], true)) $life_mode = 'overlap';
if (!in_array($act_mode, ['overlap', 'within'], true)) $act_mode = 'overlap';

// normalize ranges
if ($life_from && $life_to && $life_from > $life_to) {
  [$life_from, $life_to] = [$life_to, $life_from];
}
if ($act_from && $act_to && $act_from > $act_to) {
  [$act_from, $act_to] = [$act_to, $act_from];
}

// open-ended searched ranges
if ($life_from && !$life_to) $life_to = 9999;
if (!$life_from && $life_to) $life_from = 0;

if ($act_from && !$act_to) $act_to = 9999;
if (!$act_from && $act_to) $act_from = 0;

// ---------- Build tax_query ----------
$tax_query = ['relation' => 'AND'];

if ($geo !== '') {
  $tax_query[] = [
    'taxonomy' => 'geography',
    'field'    => 'slug',
    'terms'    => $geo,
  ];
}

if ($area !== '') {
  $tax_query[] = [
    'taxonomy' => 'area',
    'field'    => 'slug',
    'terms'    => $area,
  ];
}

if ($herbarium !== '') {
  $tax_query[] = [
    'taxonomy' => 'herbarium',
    'field'    => 'slug',
    'terms'    => $herbarium,
  ];
}

if (count($tax_query) === 1) {
  $tax_query = [];
}

// ---------- Pagination ----------
$paged = max(1, get_query_var('paged') ?: get_query_var('page') ?: 1);
$ppp   = (int) get_option('posts_per_page');

// ---------- Query ----------
$args = [
  'post_type'         => 'collector',
  'post_status'       => 'publish',
  'posts_per_page'    => $ppp,
  'paged'             => $paged,
  'orderby'           => 'title',
  'order'             => 'ASC',
  's'                 => $search,
  'herbua_life_from'  => $life_from,
  'herbua_life_to'    => $life_to,
  'herbua_act_from'   => $act_from,
  'herbua_act_to'     => $act_to,
  'herbua_life_mode'  => $life_mode,
  'herbua_act_mode'   => $act_mode,
];

if (!empty($tax_query)) {
  $args['tax_query'] = $tax_query;
}

add_filter('posts_clauses', 'herbua_collector_year_filter_clauses', 10, 2);
$q = new WP_Query($args);
remove_filter('posts_clauses', 'herbua_collector_year_filter_clauses', 10);

// ---------- Dropdown terms ----------
$geo_terms       = get_terms(['taxonomy' => 'geography', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
$area_terms      = get_terms(['taxonomy' => 'area', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
$herbarium_terms = get_terms(['taxonomy' => 'herbarium', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
?>

<main id="primary" class="site-main collector-archive">

  <header class="page-header collector-archive__header">
    <div>
      <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
      <p class="page-subtitle">Browse the index of botanical collectors. Use search and filters.</p>
    </div>

    <?php if (!empty($csv_url)) : ?>
      <p class="collector-archive__actions">
        <a class="button button-primary collector-download" href="<?php echo esc_url($csv_url); ?>">📥 Download CSV</a>
      </p>
    <?php endif; ?>
  </header>

  <form method="get" class="collector-filter-bar" action="">
    <div class="filter-group">
      <label for="s">Search</label>
      <input
        type="text"
        name="s"
        id="s"
        value="<?php echo esc_attr($search); ?>"
        placeholder="Search by name or standard form…"
      >
    </div>

    <div class="filter-group">
      <label for="geo">Geography of interest</label>
      <select name="geo" id="geo">
        <option value="">All</option>
        <?php if (!is_wp_error($geo_terms)) :
          foreach ($geo_terms as $t) :
            $sel = ($geo !== '' && $geo === $t->slug) ? 'selected' : '';
            echo '<option ' . $sel . ' value="' . esc_attr($t->slug) . '">' . esc_html($t->name) . '</option>';
          endforeach;
        endif; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="area">Area of interest</label>
      <select name="area" id="area">
        <option value="">All</option>
        <?php if (!is_wp_error($area_terms)) :
          foreach ($area_terms as $t) :
            $sel = ($area !== '' && $area === $t->slug) ? 'selected' : '';
            echo '<option ' . $sel . ' value="' . esc_attr($t->slug) . '">' . esc_html($t->name) . '</option>';
          endforeach;
        endif; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="herbarium">Hosting herbaria</label>
      <select name="herbarium" id="herbarium">
        <option value="">All</option>
        <?php if (!is_wp_error($herbarium_terms)) :
          foreach ($herbarium_terms as $t) :
            $sel = ($herbarium !== '' && $herbarium === $t->slug) ? 'selected' : '';
            echo '<option ' . $sel . ' value="' . esc_attr($t->slug) . '">' . esc_html($t->name) . '</option>';
          endforeach;
        endif; ?>
      </select>
    </div>

    <div class="filter-group">
      <label>Life interval</label>
      <div class="year-range">
        <input
          type="number"
          name="life_from"
          value="<?php echo esc_attr($life_from ?: ''); ?>"
          placeholder="from"
        >
        <input
          type="number"
          name="life_to"
          value="<?php echo esc_attr(($life_to && $life_to !== 9999) ? $life_to : ''); ?>"
          placeholder="to"
        >
      </div>
    </div>

    <div class="filter-group">
      <label for="life_mode">Life filter mode</label>
      <select name="life_mode" id="life_mode">
        <option value="overlap" <?php selected($life_mode, 'overlap'); ?>>Overlaps interval</option>
        <option value="within" <?php selected($life_mode, 'within'); ?>>Inside interval only</option>
      </select>
    </div>

    <div class="filter-group">
      <label>Activity interval</label>
      <div class="year-range">
        <input
          type="number"
          name="act_from"
          value="<?php echo esc_attr($act_from ?: ''); ?>"
          placeholder="from"
        >
        <input
          type="number"
          name="act_to"
          value="<?php echo esc_attr(($act_to && $act_to !== 9999) ? $act_to : ''); ?>"
          placeholder="to"
        >
      </div>
    </div>

    <div class="filter-group">
      <label for="act_mode">Activity filter mode</label>
      <select name="act_mode" id="act_mode">
        <option value="overlap" <?php selected($act_mode, 'overlap'); ?>>Overlaps interval</option>
        <option value="within" <?php selected($act_mode, 'within'); ?>>Inside interval only</option>
      </select>
    </div>

    <button type="submit" class="button">Filter</button>
  </form>

  <?php if ($q->have_posts()) : ?>

    <div class="collector-table-wrapper">
      <table class="collector-table">
        <thead>
          <tr>
            <th style="min-width:220px;">Name</th>
            <th>Standard form</th>
            <th>Years (life)</th>
            <th>Years (activity)</th>
            <th>Geography of interest</th>
            <th>Area of interest</th>
            <th>Hosting herbaria</th>
            <th style="min-width:200px;">Links</th>
          </tr>
        </thead>
        <tbody>
          <?php
          while ($q->have_posts()) : $q->the_post();
            $id      = get_the_ID();
            $surname = trim((string)get_field('surname', $id));
            $name    = trim((string)get_field('name', $id));
            $display = $surname . ($surname && $name ? ', ' : '') . $name;
            if ($display === '') $display = get_the_title();

            $standard = get_field('standard_form', $id);
            $life     = get_field('living_years', $id);
            $active   = get_field('activity_years', $id);

            $geo_list       = wp_get_post_terms($id, 'geography', ['fields' => 'names']);
            $area_list      = wp_get_post_terms($id, 'area', ['fields' => 'names']);
            $herbarium_list = wp_get_post_terms($id, 'herbarium', ['fields' => 'names']);

            $link_bits = array_filter([
              col_link_inline('ORCID',     get_field('orcid', $id)),
              col_link_inline('Bionomia',  get_field('bionomia', $id)),
              col_link_inline('Wikipedia', get_field('wikipedia', $id)),
              col_link_inline('Wikidata',  get_field('wikidata', $id)),
              col_link_inline('IPNI',      get_field('ipni', $id)),
              col_link_inline('VIAF',      get_field('viaf', $id)),
              col_link_inline('HUH',       get_field('huh', $id)),
              col_link_inline('ZOBODAT',   get_field('zobodat', $id)),
              col_link_inline('JSTOR',     get_field('jstor', $id)),
            ]);
          ?>
            <tr>
              <td class="col-name"><a href="<?php the_permalink(); ?>"><?php echo esc_html($display); ?></a></td>
              <td><?php echo esc_html((string)$standard); ?></td>
              <td><?php echo esc_html((string)$life); ?></td>
              <td><?php echo esc_html((string)$active); ?></td>
              <td><?php echo esc_html(implode('; ', (array)$geo_list)); ?></td>
              <td><?php echo esc_html(implode('; ', (array)$area_list)); ?></td>
              <td><?php echo esc_html(implode('; ', (array)$herbarium_list)); ?></td>
              <td class="col-links"><?php echo $link_bits ? implode(' · ', $link_bits) : '<span class="muted">—</span>'; ?></td>
            </tr>
          <?php endwhile; wp_reset_postdata(); ?>
        </tbody>
      </table>
    </div>

    <nav class="pagination-wrap">
      <?php
      echo paginate_links([
        'total'     => max(1, (int) $q->max_num_pages),
        'current'   => $paged,
        'mid_size'  => 2,
        'prev_text' => '← Previous',
        'next_text' => 'Next →',
        'add_args'  => array_filter([
          's'         => $search,
          'geo'       => $geo,
          'area'      => $area,
          'herbarium' => $herbarium,
          'life_from' => $life_from ?: null,
          'life_to'   => ($life_to && $life_to !== 9999) ? $life_to : null,
          'life_mode' => $life_mode,
          'act_from'  => $act_from ?: null,
          'act_to'    => ($act_to && $act_to !== 9999) ? $act_to : null,
          'act_mode'  => $act_mode,
        ]),
      ]);
      ?>
    </nav>

  <?php else : ?>

    <p>No collectors found. Try adjusting your filters.</p>

  <?php endif; ?>

</main>

<style>
  .collector-archive__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin: .5rem 0 1rem;
  }

  .collector-archive__actions .collector-download {
    display: inline-block;
    padding: .6rem 1rem;
    border-radius: 10px;
  }

  .collector-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin: .5rem 0 1.25rem;
    align-items: flex-end;
  }

  .collector-filter-bar .filter-group {
    display: flex;
    flex-direction: column;
  }

  .collector-filter-bar input[type="text"],
  .collector-filter-bar input[type="number"],
  .collector-filter-bar select {
    padding: .55rem .75rem;
    border: 1px solid #ccc;
    border-radius: 6px;
  }

  .year-range {
    display: flex;
    gap: .4rem;
  }

  .year-range input[type="number"] {
    width: 110px;
  }

  .collector-table-wrapper {
    overflow: auto;
  }

  .collector-table {
    width: 100%;
    border-collapse: collapse;
  }

  .collector-table th,
  .collector-table td {
    padding: .6rem .7rem;
    border-bottom: 1px solid var(--ct-border-color, #eee);
    vertical-align: top;
    text-align: left;
  }

  .collector-table th {
    font-weight: 600;
    color: var(--ct-color-text, #333);
  }

  .collector-table .muted {
    color: #888;
  }

  .col-name a {
    font-weight: 600;
    text-decoration: none;
  }

  .col-links a {
    text-decoration: underline;
  }

  .pagination-wrap {
    margin: 1.2rem 0;
  }

  @media (max-width: 900px) {
    .collector-archive__header {
      flex-direction: column;
      align-items: flex-start;
    }

    .collector-filter-bar {
      flex-direction: column;
      align-items: stretch;
    }

    .collector-filter-bar button {
      width: 100%;
    }

    .year-range input[type="number"] {
      width: 100%;
    }
  }
</style>

<?php
get_footer();
?>
