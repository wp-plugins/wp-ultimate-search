=== WP Ultimate Search ===
Contributors: sekatsim, mindshare
Donate link: http://mind.sh/are/donate/
Tags: search, ajax, metadata, autocomplete, jquery
Requires at least: 3.0
Tested up to: 3.4.1
Stable tag: 0.2
 
Advanced faceted auto completing AJAX search and filter utility.

== Description ==

WP Ultimate Search: a highly customizable WordPress search alternative with the ability to autocomplete faceted search queries.

Try a [demo](http://ultimatesearch.mindsharelabs.com/).

<h4>Features</h4>

* Searches post title and body content
* Can search by multiple keywords, and by full phrases
* Highlights search terms in results
* Currently can only facet by post Category, but we will be expanding the functionality to auto-detect and suggest indefinite facets
* Attractive and lightweight interface based on jQuery, Backbone.js, and the VisualSearch.js library
* Bypasses WordPress’ built-in search functions and conducts direct database queries for low overhead and high flexibility

Many new features coming quickly, stay tuned.


Please be advised that this is a beta release and not all features are available yet. Please install it and try it out, but we wouldn't recommend using this plugin on a live site. We'll be updating the plugin heavily over the coming weeks, so check back for updates.

== Installation ==

1. Upload the `wp-ultimate-search` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add a shortcode to a post, use the template tag in your theme, or use the sidebar widget.

<h4>Shortcode</h4>
During this development period, the shortcode is used in two parts: the search bar, and the search results. Put `[wp-ultimate-search-bar]` where you’d like the search bar, and `[wp-ultimate-search-results]` where you’d like the results to appear. No options (…yet).

<h4>Template tag</h4>
Call the search bar with `wp_ultimate_search_bar()`
Render the search results area with `wp_ultimate_search_results()`

No parameters yet.

== Frequently Asked Questions ==

Post your questions in the support forum.


== Screenshots ==

1. Search bar with results.

== Changelog ==

= 0.2 =
* First public release

== To Do ==

* Options page
* Search Advanced Custom Fields data (and other post meta)
* Permalinks for search results
* Search results tempting
* Load search results page if results area is not already loaded
* Caching of meta data
* Loading graphics
* Sortable results