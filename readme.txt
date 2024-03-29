=== Ingeni Store Locator ===

Contributors: Bruce McKinnon
Tags: content, receipties
Requires at least: 5.0
Tested up to: 5.7
Stable tag: 2022.03

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





== How do I show a list of all of the Stores? ==

Use the [ingeni-store-list] shortcode.

Parameters:

parent_cat = Category to display. Leave blank to display all categories.

orderby = Which post field to order by. Standard wp_query(). Defaults to 'name'.

order = Which ordering to use. Standard wp_query(). Defaults to 'asc'.

class = Specify a wrapper class for the list. Defaults to 'stores_list'.

store_class = Specify a class for each store listing. Defaults to 'store_listing'.


For example: [ingeni-store-list parent_cat="suppliers"]






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

2021.04 - Added the Export to CSV option
		- Import now supports an ID field; removes reliance on matching the store name
		- Import now provides for adding the town name as part of the store name, if there are duplicated store names.

2022.01 - isl_settings_support() - Improved checking of non-existant values when using the Wordpress checked() function 
	- isl_map_support() - Removed logging of redundant json values.
	- isl_content_save() - Fixed bug to save Addr2 correctly.
	- Updated the Nearest Search box to return a store web URL (if available).

2022.02 - IngeniStoreLocator() - Create and save an options key if ones does not exist (e.g., first-time install).
	- Added support for Contact Name
	- Fixed an unclosed row error in isl_add_meta_boxes();
	- Added [ingeni-store-list] for displaying an ajax enabled list of stores

2022.03 - IngeniStoreCsvImport->isl_upload_to_server() - Fixed optional parameter defaults for PHP 8



