=== Ingeni Store Locator ===

Contributors: Bruce McKinnon
Tags: content, receipties
Requires at least: 5.0
Tested up to: 5.7
Stable tag: 2021.03

A Store locator with map and freeform location lookup (for finding stores closest to a location), and bulk import.

All based on OpenStreetMap, Leaflet and Nominatim. Plus support for Mapbox and MapQuest.



== Description ==

* - Create new locations and have them identified on a map.

* - Integrates with Nominatim, Mapbox and MapQuest for geolocating via OSM.



== Installation ==

1. Upload 'ingeni-store-locator' folder to the '/wp-content/plugins/' directory.

2. Activate the plugin through the 'Plugins' menu in WordPress.

3. Add new Ingeni Stores, or perform a bulk import (via the Tools menu).

4. Display the map and search box using shortcodes or directly with custom templates




== Frequently Asked Questions ==



= How do I use the map shortcode? =

The map is displayed using the [ingeni-store-locator] shortcode.

The following parameters are available:

class = A wrapper class. Defaults is 'store_map'.

lat = Map starting lat. Defaults to -27.7 - centre of Australia.

lng = Map starting lng. Defaults to 133.7751 - centre of Australia.

title = Map title. Defaults to 'Stockists'.

pin_icon = Default map pin icon. Defaults to 'map-pin.svg'.

pin_color = Defaults to #000000 - only used if pin_icon is specified. If using the standard pin, use CSS to colour the marker.

zoom = Default map zoom. Defaults to 4.

minheight = Minimum map height. Details to 250px.

minwidth = Minimum map width. Details to 100%.

layerprovider = Custom OSM tile provider. Defaults to 'CartoDB.Positron'.

clustered = Switch on cluster maps. Default = 1 (clustering on).

pin_width = Width of a map marker loaded with the pin_icon parameter. Default = 30 (pixels).

pin_height = Height of a map marker loaded with the pin_icon parameter. Default = 30 (pixels).

category = Category name to display. If blank or 'all', then all categories are included. May also be a single, or comma separated list of categories. Default is blank.

tags = Tags to display. If blank or 'all', then all tags are included. May also be a single, or comma separated list of tags. Default is blank.



For example:

[ingeni-store-locator class="store_map"]

[ingeni-store-locator class="coles_store_map" pin_color="#E01A22" pin_height="20" pin_width="15" category="Coles" tags="catmate"]




= How do I use the search shortcode? =

The search box is displayed using the [ingeni-store-locator-nearest] shortcode.


max_results = Max. number of results to display. Default = 5 results.

category = Category name to display. If blank or 'all', then all categories are included. May also be a single, or comma separated list of categories. Default is blank.

tags = Tags to display. If blank or 'all', then all tags are included. May also be a single, or comma separated list of tags. Default is blank.

show_cats_chkbox - Display the available categories as checkboxes. Allows the user to specific which categories to display nearest to that location. Default is 0 = all categories.

show_tags_chkbox - Display the available tags as checkboxes. Allows the user to specific which tags to display nearest to that location. Default is 0 = all categories.

cats_title = Title to display next to the categories checkboxes. Default is 'Categories'.

tags_title = Title to display next to the tags checkboxes. Default is 'Tags'.



For example:

[ingeni-store-locator-nearest]

[ingeni-store-locator-nearest category="Coles" tags="catmate"]

[ingeni-store-locator-nearest tags="all" show_tags_chkbox="1" tags_title="Stocks"]






== Changelog ==

2021.01 - 27 April 2021 - Initial version


2021.02 - ingeni_store_locator_shortcode() - Don't include locations that have no lat/lng specified
				- Now supports Mapbox and MapQuest (API keys required) in addition to Nominitim
				- Admin screens now include a Get Lat/Lng button

2021.03 - Added support for categories and tags, both via the import and the shortcode.
				- ingeni_store_locator_shortcode() now uses WP_Query rather than direct SQL call.
				- implemented default inline svg map markers
				- Added optional checkboxes for Categories and Tags to the Nearest Search box.
				- Additional category and tags params for both shortcodes.

