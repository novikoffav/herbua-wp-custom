<?php
/**
 * Template for single Collector posts (Blocksy child)
 * Uses taxonomies for facets:
 *  - geography  (Geography of interest)
 *  - area       (Area of interest)
 *
 * ACF fields expected (URLs are full links):
 * portrait, surname, name, standard_form, alternative_names,
 * living_years, activity_years, orcid, bionomia, wikipedia, wikidata,
 * ipni, viaf, huh, zobodat, jstor, biography, notes, references,
 * label_example[1..5]
 * 
 * Author:      Andriy Novikov
 * License: GPL-3.0
 */

get_header();

while ( have_posts() ) : the_post();
  $id = get_the_ID();

  // --- Helpers --------------------------------------------------------------
  // Safe image output for ACF Image (ID | array | URL)
  if (!function_exists('col_img_from_acf')) {
    function col_img_from_acf($fieldVal, $size = 'large', $attrs = []) {
      if (empty($fieldVal)) return '';
      if (is_numeric($fieldVal)) {
        return wp_get_attachment_image((int)$fieldVal, $size, false, $attrs);
      }
      if (is_array($fieldVal)) {
        if (isset($fieldVal['ID']))   return wp_get_attachment_image((int)$fieldVal['ID'], $size, false, $attrs);
        if (isset($fieldVal['url'])) {
          $url = esc_url($fieldVal['url']);
          $alt = isset($attrs['alt']) ? esc_attr($attrs['alt']) : '';
          $class = isset($attrs['class']) ? esc_attr($attrs['class']) : '';
          return $url ? '<img src="'.$url.'" alt="'.$alt.'" class="'.$class.'">' : '';
        }
        return '';
      }
      $url = esc_url((string)$fieldVal);
      if (!$url) return '';
      $alt = isset($attrs['alt']) ? esc_attr($attrs['alt']) : '';
      $class = isset($attrs['class']) ? esc_attr($attrs['class']) : '';
      return '<img src="'.$url.'" alt="'.$alt.'" class="'.$class.'">';
    }
  }

  // URL from ACF (string URL or ACF Link array)
  if (!function_exists('col_url_from_acf')) {
    function col_url_from_acf($val) {
      if (empty($val)) return null;
      if (is_array($val)) {
        if (!empty($val['url'])) return esc_url_raw($val['url']);
        return null;
      }
      $s = trim((string)$val);
      return $s ? esc_url_raw($s) : null;
    }
  }

  // <li><a>…</a></li> builder for External resources (full URLs only)
  if (!function_exists('col_link_item')) {
    function col_link_item($label, $val) {
      $url = col_url_from_acf($val);
      if (!$url) return '';
      return '<li><a href="'.esc_url($url).'" target="_blank" rel="nofollow noopener">'.esc_html($label).'</a></li>';
    }
  }

  $safe_text = function($v){ return is_scalar($v) ? esc_html((string)$v) : ''; };
  $safe_rich = function($v){ return is_scalar($v) ? wp_kses_post(apply_filters('the_content',(string)$v)) : ''; };

  // --- Fields ---------------------------------------------------------------
  $portrait          = get_field('portrait', $id);
  $surname           = get_field('surname', $id);
  $name              = get_field('name', $id);
  $standard_form     = get_field('standard_form', $id);
  $alternative_names = get_field('alternative_names', $id);
  $living_years      = get_field('living_years', $id);
  $activity_years    = get_field('activity_years', $id);
  
  // Get values from ACF (meta keys)
$lsid = get_field('herbua_lsid');            // e.g. urn:lsid:herbua.com:collectors:000347-1
$obj  = get_field('herbua_object_id');       // e.g. 000347
$ver  = (int) get_field('herbua_version');   // e.g. 1 (optional)

  // External links (ACF: full URLs)
  $orcid     = get_field('orcid', $id);
  $bionomia  = get_field('bionomia', $id);
  $wikipedia = get_field('wikipedia', $id);
  $wikidata  = get_field('wikidata', $id);
  $ipni      = get_field('ipni', $id);
  $viaf      = get_field('viaf', $id);
  $huh       = get_field('huh', $id);
  $zobodat   = get_field('zobodat', $id);
  $jstor     = get_field('jstor', $id);

  $biography  = get_field('biography', $id);
  $notes      = get_field('notes', $id);
  $references = get_field('references', $id);

  $label_example   = get_field('label_example', $id);
  $label_example_2 = get_field('label_example_2', $id);
  $label_example_3 = get_field('label_example_3', $id);
  $label_example_4 = get_field('label_example_4', $id);
  $label_example_5 = get_field('label_example_5', $id);

  // --- Taxonomies (replace ACF text fields) --------------------------------
  // Geography of interest → taxonomy 'geography'
  $geo_terms  = wp_get_post_terms($id, 'geography'); // full term objects
  // Area of interest → taxonomy 'area'
  $area_terms = wp_get_post_terms($id, 'area');
  // Herbarium → taxonomy 'herbarium'
  $herbarium_terms = wp_get_post_terms($id, 'herbarium');

  // Title: prefer "Surname, Name"; fallback to WP title
  $display_title = trim((string)$surname . ( $surname && $name ? ', ' : '' ) . (string)$name);
  if ($display_title === '') $display_title = get_the_title();

?>
  <main id="primary" class="site-main">
    <article <?php post_class('collector-entry'); ?>>
      <header class="entry-header">
        <h1 class="entry-title" style="margin-left: 1rem;"><?php echo esc_html($display_title); ?></h1>
        <?php if (!empty($standard_form)) : ?>
          <p class="standard-form" style="margin-left: 1rem;"><strong>IPNI standard form:</strong> <?php echo $safe_text($standard_form); ?></p>
        <?php endif; ?>
      </header>

      <div class="entry-content collector-layout">
        <div class="collector-grid">
          <!-- LEFT COLUMN: portrait + quick facts -->
          <div class="collector-left">
            <?php if (!empty($portrait)) : ?>
              <div class="collector-portrait">
                <?php echo col_img_from_acf($portrait, 'large', ['class'=>'collector-photo', 'alt'=>$display_title]); ?>
              </div>
            <?php endif; ?>

<?php
    $lsid = get_post_meta(get_the_ID(), 'herbua_lsid', true);
        if ($lsid) {
         echo '<p style="text-align: left; padding-left: 1rem;">
          <strong>HerbUA LSID:</strong> 
          <code>' . esc_html($lsid) . '</code>
        </p>';
        }
?>

            <table class="collector-meta">
              <tbody>
                <?php if (!empty($alternative_names)) : ?>
                  <tr><th style="text-align: left; padding-left: 1rem;">Alternative names</th><td><?php echo $safe_text($alternative_names); ?></td></tr>
                <?php endif; ?>
    
                <?php if (!empty($living_years)) : ?>
                  <tr><th style="text-align: left; padding-left: 1rem;">Years of life</th><td><?php echo $safe_text($living_years); ?></td></tr>
                <?php endif; ?>

                <?php if (!empty($activity_years)) : ?>
                  <tr><th style="text-align: left; padding-left: 1rem;">Years of activity</th><td><?php echo $safe_text($activity_years); ?></td></tr>
                <?php endif; ?>

                <?php if (!empty($geo_terms) && !is_wp_error($geo_terms)) : ?>
                  <tr>
                    <th style="text-align: left; padding-left: 1rem;">Geography of interest</th>
                    <td>
                      <?php
                        $links = [];
                        foreach ($geo_terms as $t) {
                          $links[] = '<a href="'.esc_url(get_term_link($t)).'">'.esc_html($t->name).'</a>';
                        }
                        echo implode('; ', $links);
                      ?>
                    </td>
                  </tr>
                <?php endif; ?>

                <?php if (!empty($area_terms) && !is_wp_error($area_terms)) : ?>
                  <tr>
                    <th style="text-align: left; padding-left: 1rem;">Area of interest</th>
                    <td>
                      <?php
                        $links = [];
                        foreach ($area_terms as $t) {
                          $links[] = '<a href="'.esc_url(get_term_link($t)).'">'.esc_html($t->name).'</a>';
                        }
                        echo implode('; ', $links);
                      ?>
                    </td>
                  </tr>
                <?php endif; ?>
                
                <?php if (!empty($herbarium_terms) && !is_wp_error($herbarium_terms)) : ?>
                  <tr>
                    <th style="text-align: left; padding-left: 1rem;">Hosting herbaria</th>
                    <td>
                      <?php
                        $links = [];
                        foreach ($herbarium_terms as $t) {
                          $links[] = '<a href="'.esc_url(get_term_link($t)).'">'.esc_html($t->name).'</a>';
                        }
                        echo implode('; ', $links);
                      ?>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div><!-- /.collector-left -->

          <!-- RIGHT COLUMN: sections -->
          <div class="collector-right">

                        <?php if (!empty($biography)) : ?>
              <section class="collector-section">
                <h2>Biography</h2>
                <div class="section-body"><?php echo $safe_rich($biography); ?></div>
              </section>
            <?php endif; ?>

            <?php if (!empty($notes)) : ?>
              <section class="collector-section">
                <h2>Notes</h2>
                <div class="section-body"><?php echo $safe_rich($notes); ?></div>
              </section>
            <?php endif; ?>

            <?php if (!empty($references)) : ?>
              <section class="collector-section">
                <h2>References</h2>
                <div class="section-body"><?php echo $safe_rich($references); ?></div>
              </section>
            <?php endif; ?>

            <?php
              $labels = array_filter([$label_example, $label_example_2, $label_example_3, $label_example_4, $label_example_5]);
              if (!empty($labels)) : ?>
              <section class="collector-section">
                <h2>Label examples</h2>
                <div class="collector-labels">
                  <?php foreach ($labels as $lb) echo '<figure class="label-item">'.col_img_from_acf($lb, 'large', ['class'=>'label-img', 'alt'=>'Label example']).'</figure>'; ?>
                </div>
              </section>
            <?php endif; ?>

<?php
            // External identifiers / links block (full URLs from ACF)
            $links_html = array_filter([
              col_link_item('ORCID',     $orcid),
              col_link_item('Bionomia',  $bionomia),
              col_link_item('Wikipedia', $wikipedia),
              col_link_item('Wikidata',  $wikidata),
              col_link_item('IPNI',      $ipni),
              col_link_item('VIAF',      $viaf),
              col_link_item('HUH',       $huh),
              col_link_item('ZOBODAT',   $zobodat),
              col_link_item('JSTOR',     $jstor),
            ]);
            if (!empty($links_html)) : ?>
              <section class="collector-section collector-links">
                <h2>External resources</h2>
                <div class="section-body">
                  <ul class="collector-links-list">
                    <?php echo implode('', $links_html); ?>
                  </ul>
                </div>
              </section>
            <?php endif; ?>

            <?php the_content(); ?>
          </div><!-- /.collector-right -->
        </div><!-- /.collector-grid -->
      </div><!-- /.entry-content -->
      
      <?php
$oid  = get_post_meta(get_the_ID(), 'herbua_object_id', true);
$lsid = get_post_meta(get_the_ID(), 'herbua_lsid', true);
$pid  = $oid ? home_url("/id/collectors/{$oid}") : get_permalink();
?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "@id": "<?= esc_url($pid); ?>",
  "name": "<?= esc_html(get_the_title()); ?>",
  "identifier": [
    {"@type": "PropertyValue", "propertyID": "LSID", "value": "<?= esc_html($lsid); ?>"}
  ]
}
</script>

    </article>
  </main>

  <style>
    .collector-layout { margin-top: 1rem; }
    .collector-grid { display:grid; grid-template-columns:320px 1fr; gap:2rem; }
    @media (max-width:900px){ .collector-grid{ grid-template-columns:1fr; } }
    .collector-portrait { margin-bottom:1rem; text-align:center; }
    .collector-photo { border-radius:12px; max-width:100%; height:auto; }

    .collector-meta { width:100%; border-collapse:collapse; }
    .collector-meta th, .collector-meta td {
      padding:.55rem .75rem; border-bottom:1px solid var(--ct-border-color,#eee); vertical-align:top;
    }
    .collector-meta th { width:210px; font-weight:600; color:var(--ct-color-text,#333); }

    .collector-section { margin:1.25rem 0 1.75rem; }
    .collector-section h2 { margin-bottom:.5rem; }
    .collector-links-list { list-style:none; margin:0; padding:0; }
    .collector-links-list li { margin:.3rem 0; }
    .collector-links-list a { text-decoration:underline; }

    .collector-labels { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:1rem; }
    .label-item {
  aspect-ratio: 4 / 2; /* makes all boxes square — optional */
  box-shadow: 0 2px 6px rgba(0,0,0,0.9);
  overflow: hidden;
  border-radius: 10px;
}
    .label-img { width:100%; height:auto; border-radius:10px; }
  </style>

<?php
endwhile;

get_footer();
