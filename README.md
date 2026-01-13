# herbUA Collectors

Custom WordPress plugins + Blocksy child theme powering HerbUA collectors experience:
- Main collectors database page and search
- Collector profile page
- General statistics page
- Interactive country SVG map page
- Collector portraits slider

## What’s in this repo

### Plugins
- `collector-overview` — General Statistics plugin; site statistics visualization page
- `collector-country-svg-map` — Interactive map plugin; interactive SVG map page (SVG embedded in plugin code)
- `collector-portraits-slider` — Portraits Slider plugin; portraits slider for homepage

### Child theme
- `blocksy-child` — Blocksy child theme with custom templates:
  - `archive-collector.php` — main database access + search page (CPT: `collector`)
  - `single-collector.php` — single collector page
  - `functions.php`, `style.css`

### Config
- `config/cpt-ui-export.txt` — CPT UI export code (CPT + taxonomies)
- `themes/blocksy-child/acf-json/` — ACF Local JSON export (field groups)

### Figs
Man_avatar.png and Woman_avatar.png are used to depict individuals when a true portrait is unavailable. They are used to exclude persons without portraits from the slider. They can also be potentially used to filter for the man/woman option.

## Requirements
- WordPress: 6.9
- PHP: 8.0+ recommended
- Theme: Blocksy (parent theme) installed separately
- Plugins (3rd-party):
   - ACF (Advanced Custom Fields)
   - CPT (Custom Post Type UI)
   - WP All Export
   - Child Theme Configurator (optional; not required for runtime)

## Installation (for a fresh WordPress site)

1) Install WordPress and log in to wp-admin  
2) Install **Blocksy** theme (parent theme), then activate it once
3) Install and activate the next plugins through WordPress (free versions are enough):
   - ACF (Advanced Custom Fields)
   - CPT (Custom Post Type UI)
   - WP All Export
   - Child Theme Configurator (optional; not required for runtime)
5) Copy this repo contents:
   - `plugins/*` → `wp-content/plugins/`
   - `themes/blocksy-child` → `wp-content/themes/blocksy-child`
6) In wp-admin:
   - Activate the 3 custom plugins (i.e., `collector-overview`, `collector-country-svg-map`, and `collector-portraits-slider`)
   - Activate the `blocksy-child` theme

## CPT setup (CPT slug: `collector`)
This project expects a Custom Post Type with slug: **collector**.

Using CPT UI:
1) Install + activate CPT UI  
2) Go to: CPT UI → Tools → Import/Export  
3) Paste the code from `config/cpt-ui-export.txt` and import.

> Tip: For maximum portability, you can later migrate CPT registration from CPT UI into a custom plugin.

## ACF (Local JSON) — Field groups
This repo stores ACF field definitions as **ACF Local JSON**.
ACF field groups are stored as JSON in:
`wp-content/themes/blocksy-child/acf-json/`

To apply them:
1) Install + activate ACF (Free)
2) Activate the `blocksy-child` theme
3) Go to Custom Fields → Field Groups
4) If you see “Sync available”, select groups and click Sync

If you do not see the groups:
- Visit Custom Fields → Tools and sync the JSON groups (if sync is available),
- Or re-save a field group once to regenerate JSON.

## WP All Export
WP All Export is used for exporting data. We do not ship real exports in this repo.
However, it is  used to generate the link for the 'Download CSV' button on the main archive page. To set up the download link, go to archive-collector.php file in a child theme (the place for link is indicated within the provided file).


## Notes on data & privacy
Do not commit:
- real database dumps
- personal information
- wp-content/uploads
- secrets / API keys

For demo purposes, use anonymized sample data only.

## Implementation examples
The repo has been developed and realized within herbUA initiative and can be accessed from https://wp.herbua.com/

## Versioning / releases
- `v0.1.0` initial public release

## License
GPL-3.0

