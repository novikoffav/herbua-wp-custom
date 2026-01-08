<?php
/**
 * Archive template for Collector (Blocksy child) — TAXONOMY VERSION
 * - Filters by taxonomies: geography, area (via ?geo=<slug>&area=<slug>)
 * - Search by title/content via ?s=
 * - Uses custom query with pagination
 * - Shows ACF URL fields as links (ORCID, Bionomia, Wikipedia, etc.)
 * Author:      Andriy Novikov
 * License: GPL-3.0
 */

get_header();

// ====== Optional public CSV download (set your URL or leave empty) ======
$csv_url = 'https://wp.herbua.com/wp-load.php?security_token=0f4d868f2cd8f47b&export_id=3&action=get_data'; // Please replace with URL to your database archive in .csv format generated through the WP All Export plugin or in any other way. It will be used under the button "Download CSV" on the main page of the database
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
    return '<a href="'.esc_url($url).'" target="_blank" rel="nofollow noopener">'.esc_html($label).'</a>';
  }
}

// ---------- Inputs ----------
$search = isset($_GET['s'])    ? sanitize_text_field($_GET['s'])    : '';
$geo    = isset($_GET['geo'])  ? sanitize_text_field($_GET['geo'])  : ''; // term slug
$area   = isset($_GET['area']) ? sanitize_text_field($_GET['area']) : ''; // term slug
$herbarium   = isset($_GET['herbarium']) ? sanitize_text_field($_GET['herbarium']) : ''; // term slug

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
if (count($tax_query) === 1) $tax_query = []; // no actual filters

// ---------- Pagination ----------
$paged = max(1, get_query_var('paged') ?: get_query_var('page') ?: 1);
$ppp   = (int) get_option('posts_per_page');

// ---------- Query ----------
$args = [
  'post_type'      => 'collector',
  'post_status'    => 'publish',
  'posts_per_page' => $ppp,
  'paged'          => $paged,
  'orderby'        => 'title',
  'order'          => 'ASC',
  's'              => $search,
];
if (!empty($tax_query)) $args['tax_query'] = $tax_query;

$q = new WP_Query($args);

// ---------- Dropdown terms ----------
$geo_terms  = get_terms(['taxonomy'=>'geography', 'hide_empty'=>false, 'orderby'=>'name', 'order'=>'ASC']);
$area_terms = get_terms(['taxonomy'=>'area',      'hide_empty'=>false, 'orderby'=>'name', 'order'=>'ASC']);
$herbarium_terms = get_terms(['taxonomy'=>'herbarium',      'hide_empty'=>false, 'orderby'=>'name', 'order'=>'ASC']);
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

  <!-- Filter Bar -->
  <form method="get" class="collector-filter-bar" action="">
    <div class="filter-group">
      <label for="s">Search</label>
      <input type="text" name="s" id="s"
             value="<?php echo esc_attr($search); ?>"
             placeholder="Search by name or standard form…">
    </div>

    <div class="filter-group">
      <label for="geo">Geography of interest</label>
      <select name="geo" id="geo">
        <option value="">All</option>
        <?php if (!is_wp_error($geo_terms)) :
          foreach ($geo_terms as $t) :
            $sel = ($geo !== '' && $geo === $t->slug) ? 'selected' : '';
            echo '<option '.$sel.' value="'.esc_attr($t->slug).'">'.esc_html($t->name).'</option>';
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
            echo '<option '.$sel.' value="'.esc_attr($t->slug).'">'.esc_html($t->name).'</option>';
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
            echo '<option '.$sel.' value="'.esc_attr($t->slug).'">'.esc_html($t->name).'</option>';
          endforeach;
        endif; ?>
      </select>
    </div>

    <button type="submit" class="button">Filter</button>
  </form>

  <?php if ( $q->have_posts() ) : ?>

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
          while ( $q->have_posts() ) : $q->the_post();
            $id       = get_the_ID();
            $surname  = trim((string)get_field('surname', $id));
            $name     = trim((string)get_field('name', $id));
            $display  = $surname . ( $surname && $name ? ', ' : '' ) . $name;
            if ($display === '') $display = get_the_title();

            $standard = get_field('standard_form', $id);
            $life     = get_field('living_years', $id);
            $active   = get_field('activity_years', $id);

            // taxonomy lists
            $geo_list  = wp_get_post_terms($id, 'geography', ['fields'=>'names']);
            $area_list = wp_get_post_terms($id, 'area',      ['fields'=>'names']);
            $herbarium_list = wp_get_post_terms($id, 'herbarium',      ['fields'=>'names']);

            // external links from ACF full URLs
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
          'total'     => max(1, (int)$q->max_num_pages),
          'current'   => $paged,
          'mid_size'  => 2,
          'prev_text' => '← Previous',
          'next_text' => 'Next →',
        ]);
      ?>
    </nav>

  <?php else : ?>

    <p>No collectors found. Try adjusting your filters.</p>

  <?php endif; ?>

</main>

<style>
  .collector-archive__header {
    display:flex; align-items:flex-end; justify-content:space-between; gap:1rem;
    margin:.5rem 0 1rem;
  }
  .collector-archive__actions .collector-download {
    display:inline-block; padding:.6rem 1rem; border-radius:10px;
  }

  .collector-filter-bar {
    display:flex; flex-wrap:wrap; gap:1rem; margin:.5rem 0 1.25rem;
    align-items:flex-end;
  }
  .collector-filter-bar .filter-group { display:flex; flex-direction:column; }
  .collector-filter-bar input[type="text"], .collector-filter-bar select {
    padding:.55rem .75rem; border:1px solid #ccc; border-radius:6px;
  }

  .collector-table-wrapper { overflow:auto; }
  .collector-table { width:100%; border-collapse: collapse; }
  .collector-table th, .collector-table td {
    padding:.6rem .7rem; border-bottom:1px solid var(--ct-border-color,#eee);
    vertical-align: top; text-align:left;
  }
  .collector-table th { font-weight:600; color: var(--ct-color-text,#333); }
  .collector-table .muted { color:#888; }
  .col-name a { font-weight:600; text-decoration: none; }
  .col-links a { text-decoration: underline; }
  .pagination-wrap { margin:1.2rem 0; }

  @media (max-width: 900px) {
    .collector-archive__header { flex-direction:column; align-items:flex-start; }
    .collector-filter-bar { flex-direction:column; align-items:stretch; }
    .collector-filter-bar button { width:100%; }
  }
</style>

<?php
get_footer();
