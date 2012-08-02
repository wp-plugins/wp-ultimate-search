=== WP Ultimate Search ===
Contributors: sekatsim, mindshare
Donate link: http://mind.sh/are/donate/
Tags: search, ajax, metadata, autocomplete, jquery
Requires at least: 3.0
Tested up to: 3.4.1
Stable tag: 0.3
 
Advanced faceted auto completing AJAX search and filter utility.

== Description ==

WP Ultimate Search: a highly customizable WordPress search alternative with the ability to autocomplete [faceted search queries](http://en.wikipedia.org/wiki/Faceted_search).

Try a [demo](http://ultimatesearch.mindsharelabs.com/).

<h4>Features</h4>

* Searches post title and body content
* Can search by multiple keywords, and by full phrases
* Highlights search terms in results
* Searches inside of shortcodes
* Option to send search queries as events to your Google Analytics account
* Facets by post category
* Can search in multiple categories (OR search)
* Category options are dynamically generated and autocompleted as you type
* Attractive and lightweight interface based on jQuery, Backbone.js, and the VisualSearch.js library
* Bypasses WordPress’ built-in search functions and conducts direct database queries for low overhead and high flexibility

Many new features coming quickly, stay tuned.

Please be advised that this is a beta release and not all features are available yet. We'll be updating the plugin heavily over the coming weeks, so check back for updates.

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

`/tags/0.2/screenshot-1.jpg`

== Changelog ==

= 0.3 =
* Added options page
* Ability to search within shortcodes
* Google Analytics integration
* Can search by multiple categories (OR)
* Option to put scripts in header or footer
* Will throw an error if search results shortcode isn't present on page
* Loading animations
* Fixed bug where widget wouldn't display on home page
* Misc. performance tweaks

= 0.2 =
* First public release

== To Do ==

* Search Advanced Custom Fields data (and other post meta)
* Permalinks for search results
* Search results tempting
* Load search results page if results area is not already loaded
* Caching of meta data
* Sortable results