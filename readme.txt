=== Ingeni Store Locator ===

Contributors: Bruce McKinnon
Tags: content, receipties
Requires at least: 5.0
Tested up to: 5.7
Stable tag: 2021.02

A Store locator with map and freeform location lookup (for finding stores closest to a location), and bulk import.

All based on OpenStreetMap, Leaflet and Nominatim.



== Description ==

* - Create new locations and have them identified on a map.

* - Integrates with Nominatim for geolocating via OSM.



== Installation ==

1. Upload 'ingeni-store-locator' folder to the '/wp-content/plugins/' directory.

2. Activate the plugin through the 'Plugins' menu in WordPress.

3. Add new Ingeni Stores, or perform a bulk import (via the Tools menu).

4. Display the map and search box using shortcodes or directly with custom templates




== Frequently Asked Questions ==



= How do I use the map shortcode? =

The map is displayed using the [ingeni-store-locator] shortcode.

The following parameters are available:

lat = Map starting lat. Defaults to -27.7 - centre of Australia.

lng = Map starting lng. Defaults to 133.7751 - centre of Australia.

zoom = Default map zoom. Defaults to 4.

pin_icon = Default map pin icon. Defaults to 'map-pin.svg'.

pin_color = Defaults to #000000

title = Map title. Defaults to 'Stockists'.

minheight = Minimum map height. Details to 250px.

minwidth = Minimum map width. Details to 100%.

class = A wrapper class. Defaults is 'store_map'.

layerprovider = Custom OSM tile provider. Defaults to 'CartoDB.Positron'.



For example:

[ingeni-store-locator class="store_map" pin_color="#426439"]





= How do I use the search shortcode? =

The search box is displayed using the [ingeni-store-locator-nearest] shortcode.

For example:

[ingeni-store-locator-nearest]




== Changelog ==

2021.01 - 27 April 2021 - Initial version


2021.02 - ingeni_store_locator_shortcode() - Don't include locations that have no lat/lng specified
				- Now supports Mapbox (API key required) in addtion to Nominitim
				- Admin screens now include a Get Lat/Lng button

