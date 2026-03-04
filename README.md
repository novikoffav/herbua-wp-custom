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
- `collector-country-svg-map` — Interactive Map plugin; interactive SVG map page (SVG embedded in plugin code)
- `collector-portraits-slider` — Portraits Slider plugin; portraits slider for homepage
  
These custom plugins can be applied using shortcodes. Examples of the shortcodes (e.g., [collector_portraits_slider width="150" ratio="3/4" radius="12" autoplay="yes" speed="13000" pause="yes" reverse="no" count="200"]) are provided in the respective README.md files.

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
   - Activate the `blocksy-child` theme
   - Activate the 3 custom plugins (i.e., `collector-overview`, `collector-country-svg-map`, and `collector-portraits-slider`)
   - Configure CPT and ACF plugins (see instructions below)

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

Currently applied ACF fields and their types
Nr	Label	Name	Type
	Collector Portfolio		
1	Portrait	portrait	Image
2	Portrait rights	portrait_rights	Group
a	Rights type *	rights_type	Select
b	Credit	credit	Text
c	Source URL	source_url	URL
d	CC license **	cc_license	Select
e	License URL	license_url	URL
3	Surname	surname	Text
4	Name	name	Text
5	Standard form	standard_form	Text
6	Alternative names	alternative_names	Text
7	Living years	living_years	Text
8	Activity years	activity_years	Text
9	ORCID	orcid	Link
10	Bionomia	bionomia	Link
11	Wikipedia	wikipedia	Link
12	Wikidata	wikidata	Link
13	IPNI	ipni	Link
14	VIAF	viaf	Link
15	HUH	huh	Link
16	Zobodat	zobodat	Link
17	JSTOR Global Plants	jstor	Link
18	IndExs Records	indexs_group	Group
a	IndExs_1	indexs_1	Link
b	IndExs_2	indexs_2	Link
c	IndExs_3	indexs_3	Link
d	IndExs_4	indexs_4	Link
e	IndExs_5	indexs_5	Link
19	Biography	biography	Text Area
20	Notes	notes	Text
21	References	references	Text
22	Label example	label_example	Image
23	Label example 2	label_example_2	Image
24	Label example 3	label_example_3	Image
25	Label example 4	label_example_4	Image
26	Label example 5	label_example_5	Image
27	Label Rights	label_rights	Group
a	License ***	license	Select
b	Attribution	attribution	Text
c	Source URL	source_url	URL
	Collector Identifiers (LSID)	
1	HerbUA Object ID	herbua_object_id	Text
2	HerbUA Version	herbua_version	Number
3	HerbUA LSID	herbua_lsid	Text

Choices for selective fields: * – cc : Creative Commons; public_domain : Public domain; permission : Used with permission; copyrighted : Copyrighted / All rights reserved; unknown : Unknown. ** – CC_BY : CC BY; CC_BY_SA : CC BY-SA; CC_BY_NC : CC BY-NC; CC_BY_ND : CC BY-ND CC0. *** – CC_BY : CC BY; CC_BY_NC : CC BY-NC; CC_BY_SA : CC BY-SA; CC_BY_ND : CC BY-ND; CC0 Public_Domain : Public Domain; All_Rights_Reserved : All rights reserved.


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
- `v0.1.2` initial public release

## License
GPL-3.0

