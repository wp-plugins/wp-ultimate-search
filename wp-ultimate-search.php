<?php
/*
Plugin Name: WP Ultimate Search
Plugin URI: http://ultimatesearch.mindsharelabs.com
Description: Advanced faceted AJAX search and filter utility.
Version: 0.7
Author: Bryce Corkins / Mindshare Studios
Author URI: http://mindsharelabs.com/
*/

/**
 * @copyright Copyright (c) 2012. All rights reserved.
 * @author    Mindshare Studios, Inc.
 *
 * @license   Released under the GPL license http://www.opensource.org/licenses/gpl-license.php
 * @see       This is an add-on for WordPress http://wordpress.org
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 *
 * @todo add protected var options, reduce calls to get_option
 * @todo move includes into class, admin_init and init
 * @todo figure out why update mechanism isn't returning the correct results, http://wp.tutsplus.com/tutorials/plugins/a-guide-to-the-wordpress-http-api-automatic-plugin-updates/
 */

/* CONSTANTS */
if(!defined('WPUS_MIN_WP_VERSION')) {
	define('WPUS_MIN_WP_VERSION', '3.1');
} //@todo yo Bryce: what version of WP is needed?

if(!defined('WPUS_PLUGIN_NAME')) {
	define('WPUS_PLUGIN_NAME', 'WP Ultimate Search');
}

if(!defined('WPUS_PLUGIN_SLUG')) {
	define('WPUS_PLUGIN_SLUG', 'wp-ultimate-search');
}

if(!defined('WPUS_DIR_PATH')) {
	define('WPUS_DIR_PATH', plugin_dir_path(__FILE__));
}

if(!defined('WPUS_BASE')) {
	define('WPUS_BASE', plugin_dir_url(__FILE__));
}

if ( ! defined( 'WP_PLUGIN_DIR' ) )
       define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

// check WordPress version
global $wp_version;
if(version_compare($wp_version, WPUS_MIN_WP_VERSION, "<")) {
	exit(WPUS_PLUGIN_NAME.' requires WordPress '.WPUS_MIN_WP_VERSION.' or newer.');
}

// deny direct access
if(!function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

add_action('init', 'mindshare_auto_update');
function mindshare_auto_update() {
	
}

/**
 *  WPUltimateSearch CONTAINER CLASS
 */
if(!class_exists("WPUltimateSearch")) :
	class WPUltimateSearch {

		/**
		 * @var $metadata_url (string) used for update service
		 */
		private $metadata_url;
		public static $is_pro = true;

		function __construct() {

			// REGISTER AJAX FUNCTIONS WITH ADMIN-AJAX
			add_action('wp_ajax_usearch_search', array($this, 'get_results'));
			add_action('wp_ajax_nopriv_usearch_search', array($this, 'get_results')); // need this to serve non logged in users
			add_action('wp_ajax_usearch_getvalues', array($this, 'get_values'));
			add_action('wp_ajax_nopriv_usearch_getvalues', array($this, 'get_values')); // need this to serve non logged in users

			// REGISTER SHORTCODES
			add_shortcode(WPUS_PLUGIN_SLUG."-bar", array($this, 'search_form'));
			add_shortcode(WPUS_PLUGIN_SLUG."-results", array($this, 'search_results'));

			// REGISTER WIDGET
			add_action('widgets_init', create_function('', 'register_widget( "wpultimatesearchwidget" );'));

			register_activation_hook(__FILE__, array($this, 'activation_hook')); // on plugin activation, create search results page
		}
		
		/**
		 *
		 * Create search results page
		 *
		 *
		 * When the plugin is first activated, create a /search/ page with the results shortcode.
		 *
		 *
		 */
		public function activation_hook() {
			$pages = get_pages();
			foreach($pages as $page) {
				if($page->post_name == "search") {
					return;
				}
			} // if search page already exists, exit
			$results_page = array(
				'post_title'   => 'Search',
				'post_content' => '['.WPUS_PLUGIN_SLUG.'-bar]<br />['.WPUS_PLUGIN_SLUG.'-results]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_name'    => 'search',
				'comment_status' => 'closed'
			);
			wp_insert_post($results_page);
		}

		/**
		 *  PRIVATE FUNCTIONS
		 */

		// values entered by licensed user, probably set via options page
		/*$key = '8b8c96bff3e618ddd4adb86772739b5d2bb85e24';
		$email = 'damiantaggart@gmail.com';
		$v = get_license($key, $email) == md5('MQ==') ? '1' : 'invalid';*/

		/**
		 * get_license
		 *
		 * @param $key
		 * @param $email
		 *
		 * @return float|string
		 */
		protected function get_license($key, $email) {
			$hash = 'cdd96d3cc73d1dbdaffa03cc6cd7339b';
			$args = array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => TRUE,
				'headers'     => array(),
				'body'        => array('project' => 'wp-ultimate-search', 'action' => 'license', 'k' => $key, 'u' => $email),
				'cookies'     => array()
			);
			$response = wp_remote_post('http://mindsharelabs.com/update/', $args);
			if( is_wp_error( $response ) ) {
			   echo 'Unable to reach the server. Please try again in a minute.';
			} else {
				return $response['body'];
			}
		}

		/**
		 *
		 * Highlight search terms
		 *
		 *
		 * Takes a block of text and an array of keywords, returns the text with
		 * keywords wrapped in a "highlight" class.
		 *
		 * @param $text
		 * @param $keywords
		 *
		 * @return mixed
		 */
		private function highlightsearchterms($text, $keywords) {
			return preg_replace('/('.implode('|', $keywords).')/i', '<strong class="usearch-highlight">$0</strong>', $text);
		}

		/**
		 *
		 * Render categories
		 *
		 *
		 * Takes a post object returned from wpdb->get_results() and returns a string
		 * with all categories assigned to the post.
		 *
		 * @param $array
		 *
		 * @return string
		 */
		private function render_categories($array) {
			if(!($categories = get_the_category($array->ID))) {
				return;
			}

			if(count($categories) > 1) {
				$catstring = 'in categories: ';
			} elseif(count($categories) == 1) {
				$catstring = 'in category: ';
			}
			foreach($categories as $category) {
				$catstring .= '<a href="'.get_category_link($category->term_id).'" title="'.esc_attr(sprintf(__("View all posts in %s"), $category->name)).'">'.$category->cat_name.'</a>'.', ';
			}

			return trim($catstring, ', ');
		}

		/**
		 *
		 * Convert a string to an array of keywords
		 *
		 *
		 * Separate a comma-separated string of keywords into an array, preserving quotation marks
		 *
		 * @param $search
		 *
		 * @return mixed
		 */
		protected function string_to_keywords($search) {
			preg_match_all('/(?<!")\b\w+\b|(?<=")\b[^"]+/', $search, $keywords);
			for($i = 0; $i < count($keywords[0]); $i++) {
				$keywords[0][$i] = stripslashes($keywords[0][$i]);
			}
			return $keywords[0];
		}

		/**
		 *
		 * Modified version of wp_strip_all_tags
		 *
		 *
		 * Strips all HTML etc. tags from a given inpus, converts line breaks to spaces, and
		 * removes any trailing tags that got clipped by the excerpt process
		 *
		 * @param      $string
		 * @param bool $remove_breaks
		 *
		 * @return string
		 */
		private function wpus_strip_tags($string, $remove_breaks = FALSE) {
			$string = preg_replace('/[\r\n\t ]+/', ' ', $string);

			$string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);

			$string = preg_replace('@ *</?\s*(P|UL|OL|DL|BLOCKQUOTE)\b[^>]*?> *@si', "\n\n", $string);
			$string = preg_replace('@ *<(BR|DIV|LI|DT|DD|TR|TD|H\d)\b[^>]*?> *@si', "\n", $string);
			$string = preg_replace("@\n\n\n+@si", "\n\n", $string);

			$string = strip_tags($string);

			if($remove_breaks) {
				$string = preg_replace('/[\r\n\t ]+/', ' ', $string);
			}

			// ...since we're pulling excerpts from the DB, some of the excerpts contain truncated HTML tags
			// that won't be picked up by strip_tags(). This removes any trailing HTML from the beginning
			// and end of the excerpt:
			$string = preg_replace('/.*>|<.*/', ' ', $string);

			return trim($string);
		}

		/**
		 *
		 * Ajax response
		 *
		 *
		 * Similar to wp_localize_script, but wp_localize_script can only be called on plugin load / on
		 * page load. This function can be called during execution of the AJAX call & response process
		 * to update the main.js file with new variables.
		 *
		 * @param $parameter
		 * @param $response
		 */
		private function ajax_response($parameter, $response) {
			echo '
				<script type="text/javascript">
				    /* <![CDATA[ */
				    var usearch_response = {
				            "'.$parameter.'":"'.$response.'"
				    };
				    /* ]]> */
				    </script>';
		}

		/**
		 *
		 * Print results
		 *
		 *
		 * If there are results, load the appropriate results template and output
		 * the search results. Send Analytics tracking beacon if enabled.
		 *
		 * @param $resultsarray
		 * @param $keywords
		 */
		protected function print_results($resultsarray, $keywords) {
			if($resultsarray) { // if results were found, continue
				if(file_exists(TEMPLATEPATH.'/wpus-results-template.php')) {
					require(TEMPLATEPATH.'/wpus-results-template.php');
				} else {
					require('views/wpus-results-template.php');
				}
				if(wpus_option('track_events')) // if we're tracking searches as analytics events, pass the number of search results back to main.js
				{
					$this->ajax_response('numresults', count($resultsarray));
				}
			} else { // if no results were found, let 'em know
				echo wpus_option('no_results_msg');
			}
		}

		/**
		 *
		 * Get Enabled Taxonomies
		 *
		 *
		 * Return an array of all taxonomies which are currently selected in the options window
		 *
		 * @return array
		 */
		private function get_enabled_facets() {
			$options = get_option('wpus_options');

			foreach($options["'taxonomies'"] as $taxonomy) {
				if(!isset($taxonomy["'enabled'"])) {
					break;
				}
				if(!class_exists("WPUltimateSearchPro") && $taxonomy["'label'"] == 'post_tag') {
					$enabled_facets[] = 'tag';
				} else {
					$enabled_facets[] = $taxonomy["'label'"];
				}
			}
			foreach($options["'metafields'"] as $metafield) {
				if(!isset($metafield["'enabled'"])) {
					break;
				}
				$enabled_facets[] = $metafield["'label'"];
			}
			return $enabled_facets;
		}

		/**
		 *
		 * Get Taxonomy Name
		 *
		 *
		 * Matches a user-specified label from the options screen to it's corresponding term_name in the db
		 *
		 * @param $label
		 *
		 * @return int|string
		 */
		protected function get_taxonomy_name($label) {
			$options = get_option('wpus_options');

			foreach($options["'taxonomies'"] as $taxonomy => $value) {
				if($value["'label'"] == $label) {
					return $taxonomy;
				}
			}
		}

		/**
		 *
		 * Get Metafield Name
		 *
		 *
		 * Matches a user-specified label from the options screen to it's corresponding meta_key in the db
		 *
		 * @param $label
		 *
		 * @return int|string
		 */
		protected function get_metafield_name($label) {
			$options = get_option('wpus_options');

			foreach($options["'metafields'"] as $metafield => $value) {
				if($value["'label'"] == $label) {
					return $metafield;
				}
			}
		}

		/**
		 *
		 * Determine facet type
		 *
		 *
		 * Given a facet label in string form, determines whether it's a taxonomy or post meta
		 *
		 * @param $facet
		 *
		 * @return string
		 */
		protected function determine_facet_type($facet) {
			$options = get_option('wpus_options');

			if($facet == "text") {
				return "text";
			}

			foreach($options["'taxonomies'"] as $taxonomy => $value) {
				if($value["'label'"] == $facet) {
					return "taxonomy";
				}
			}
			foreach($options["'metafields'"] as $metafield => $value) {
				if($value["'label'"] == $facet) {
					return "metafield";
				}
			}
		}

		/**
		 *  PUBLIC FUNCTIONS
		 */

		/**
		 * register_scripts
		 *
		 */
		public function register_scripts() {

			// ENQUEUE VISUALSEARCH SCRIPTS
			wp_enqueue_script('underscore', WPUS_BASE.'js/underscore-min.js');
			wp_enqueue_script('backbone', WPUS_BASE.'js/backbone-min.js', array('underscore'));
			wp_enqueue_script('visualsearch', WPUS_BASE.'js/visualsearch.js', array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position', 'jquery-ui-autocomplete', 'backbone'));

			// ENQUEUE AND LOCALIZE MAIN JS FILE
			wp_enqueue_script('usearch-script', WPUS_BASE.'js/main.js', array('visualsearch'), '', wpus_option('scripts_in_footer'));

			$options = get_option('wpus_options');

			$params = array(
				'ajaxurl'          => admin_url('admin-ajax.php'),
				'searchNonce'      => wp_create_nonce('search-nonce'),
				'trackevents'      => $options['track_events'],
				'eventtitle'       => $options['event_category'],
				'enabledfacets'    => json_encode($this->get_enabled_facets()),
				'loadinganimation' => $options['loading_animation'],
				'resultspage'      => $options['results_page']
			);

			wp_localize_script('usearch-script', 'usearch_script', $params);

			// ENQUEUE STYLES
			wp_enqueue_style('usearch-bar', WPUS_BASE.'css/visualsearch.css');
		}

		/**
		 * search_form
		 *
		 * @return string
		 */
		public function search_form() {
			$this->register_scripts();
			// RENDER SEARCH FORM
			ob_start(); //start output buffer: everything echoed will be sent to the ob instead of the browser.
			echo '<div id="search_box_container"></div>';
			$the_form = ob_get_clean(); // flush output buffer into variable
			return $the_form;
		}

		/**
		 * search_form_template_tag
		 *
		 */
		public function search_form_template_tag() {
			$this->register_scripts();
			echo '<div id="search_box_container"></div>';
		}

		/**
		 * search_results
		 *
		 * @return string
		 */
		public function search_results() {
			// RENDER SEARCH RESULTS AREA
			$options = get_option('wpus_options');

			$the_results = '<div class="usearch_results">';
			if($options['loading_animation'] == 'css3') {
				$the_results .= '<div id="usearch_loading"></div>
									<div id="usearch_loading1"></div>';
			}

			$the_results .= '<div id="usearch_response">&nbsp;</div>
			</div>';
			return $the_results;
		}

		/**
		 * search_results_template_tag
		 *
		 */
		public function search_results_template_tag() {
			echo '<div id="usearch_loading"></div>
				<div id="usearch_loading1"></div>
			<div id="usearch_response">&nbsp;</div>';
		}

		/**
		 *
		 * Get values
		 *
		 *
		 * This is called by main.js whenever an eligible facet is entered in the search
		 * bar. Returns a comma-separated list of available terms for the facet.
		 *
		 */
		public function get_values() {
			$facet = $_GET['facet'];
			if(!isset($facet)) {
				exit;
			} // if nothing's been set, we can exit
			$type = $this->determine_facet_type($facet); // determine if we're dealing with a taxonomy or a metafield

			$options = get_option('wpus_options');

			switch($type) {
				case "taxonomy" :
					$facet = $this->get_taxonomy_name($facet); // get the database taxonomy name from the current facet

					if(isset($options["'taxonomies'"][$facet]["'max'"])) {
						$number = $options["'taxonomies'"][$facet]["'max'"];
					} else {
						$number = 50; // set a max of 50 terms, so we don't break anything
					}
					$excludetermids = array();
					if(!empty($options["'taxonomies'"][$facet]["'exclude'"])) {
						$excludeterms = $this->string_to_keywords($options["'taxonomies'"][$facet]["'exclude'"]);
						foreach($excludeterms as $term) {
							$term = get_term_by('name', $term, $facet);
							$excludetermids[] = $term->term_id;
						}
					}
					$args = array( // parameters for the term query
						'orderby' => 'name',
						'order'   => 'ASC',
						'number'  => $number,
						'exclude' => $excludetermids
					);

					$terms = get_terms($facet, $args);
					foreach($terms as $term) {
						$values[] = $term->name;
					}

					echo json_encode($values); // json encode the results array and pass it back to the UI
					die();

				case "metafield" :
					global $wpdb;

					$querystring = "
						SELECT pm.meta_value as value FROM {$wpdb->postmeta} pm
						WHERE pm.meta_key LIKE '{$facet}'
						ORDER BY value DESC"; // get the values from post_meta where the meta key matches the search facet...
					// this will be cached, eventually
					$results = $wpdb->get_results($querystring);

					foreach($results as $key) {
						if(!empty($key->value)) { // for some reason, $results sometimes returns zero-length strings as keys, so this filters them out
							$values[strtolower($key->value)] = $key->value;
						}
					}
					echo json_encode($values);
					die();
			}
		}

		/**
		 *
		 * Get results
		 *
		 *
		 * This is called by main.js when the usearch_search action is triggered. Gets
		 * the query from the UI, reconstructs it into an array, builds and executes the
		 * database query, and calls the function to output the results.
		 *
		 */
		public function get_results() {

			if(!isset($_GET['usearchquery'])) {
				die(); // if no data has been entered, quit
			} else {
				$searcharray = $_GET['usearchquery'];
			}

			$nonce = $_GET['searchNonce'];
			if(!wp_verify_nonce($nonce, 'search-nonce')) // make sure the search nonce matches the nonce generated earlier
			{
				die ('Busted!');
			}
			
			if(class_exists("WPUltimateSearchPro")) {
				WPUltimateSearchPro::execute_query_pro($searcharray);
			} else {
				$this->execute_query_basic($searcharray);
			}
		}

		public function execute_query_basic($searcharray) {

			global $wpdb; // load the database wrapper

			foreach($searcharray as $index) { // iterate through the search query array and separate the taxonomies into their own array
				foreach($index as $facet => $data) {
					$facet = $wpdb->escape($facet);
			//		$data = $wpdb->escape($data);			//	@todo find an escape method that doesn't break strings encased in quotes. not a huge deal since we're breaking all
															//	strings apart anyway (so sql injection is impossible)
					$type = $this->determine_facet_type($facet); // determine if we're dealing with a taxonomy or a metafield

					switch($type) {
						case "text" :
							$keywords = $this->string_to_keywords($data);
							break;
						case "taxonomy" :
							$data = preg_replace('/_/', " ", $data); // in case there are underscores in the value (from a permalink), remove them
							if($facet == "tag") $facet = "post_tag";
							if(!isset($taxonomies[$facet])) {
								$taxonomies[$facet] = "'".$data."'"; // if it's the first parameter, don't prefix with a comma
							} else {
								$taxonomies[$facet] .= ", '".$data."'"; // prefix subsequent parameters with ", "
							}
							break;
						case "metafield" :
							echo "WP Ultimate Search Pro is not installed or configured correctly.";
							die();
					}
				}
			}
			// @todo would be nice if we could somehow iterate through to find the first matching keyword instead of just checking $keywords[0]
			$querystring = "
			SELECT *,
			substring(post_content, ";
			if(isset($keywords)) { // if there are keywords, locate them and return a 200 character excerpt beginning 80 characters before the keyword
				$keywords = $wpdb->escape($keywords); // Sanitize the keywords parameters to prevent sql injection attacks
				$querystring .= "
					case 
						 when locate('$keywords[0]', lower(post_content)) <= 80 then 1
			             else locate('$keywords[0]', lower(post_content)) - 80
			        end,";
			} else { // if there aren't any keywords, just return the first 200 characters of the post
				$querystring .= "1,";
			}
			$querystring .= "200)
			AS excerpt
			FROM $wpdb->posts ";
			if(isset($taxonomies)) {
				for($i = 0; $i < count($taxonomies); $i++) { // for each taxonomy (categories, tags, etc.) do some joins so we can check each post against taxonomy[i] and term[i]
					$querystring .= "
					LEFT JOIN $wpdb->term_relationships AS rel".$i." ON($wpdb->posts.ID = rel".$i.".object_id)
					LEFT JOIN $wpdb->term_taxonomy AS tax".$i." ON(rel".$i.".term_taxonomy_id = tax".$i.".term_taxonomy_id)
					LEFT JOIN $wpdb->terms AS term".$i." ON(tax".$i.".term_id = term".$i.".term_id) ";
				}
			}
			$querystring .= "WHERE "; // the SELECT part of the query told us *what* to grab, the WHERE part tells us which posts to grab it from
			if(isset($keywords)) // if there are keywords, select posts where any of the keywords appear in either the title or post body
			{
				for($i = 0; $i < count($keywords); $i++) {
					$querystring .= "(lower(post_content) LIKE '%{$keywords[$i]}%' ";
					$querystring .= "OR lower(post_title) LIKE '%{$keywords[$i]}%') ";
					if($i < count($keywords) - 1) {
						$querystring .= "AND ";
					}
				}
			}
			if(isset($keywords) && isset($taxonomies)) {
				$querystring .= "AND ";
			} // if there were keywords, and there are taxonomies, insert an AND between the two sections
			$i = 0;
			if(isset($taxonomies)) {
				foreach($taxonomies as $taxonomy => $taxstring) { // for each taxonomy, check to see if there are any matches from within the comma-separated list of terms
					if($i > 0) {
						$querystring .= "AND ";
					}
					$querystring .= "(term".$i.".name IN (".$taxstring.") ";
					$querystring .= "AND tax".$i.".taxonomy = '".$taxonomy."') ";
					$i++;
				}
			}
			if((isset($keywords) || isset($taxonomies)) && isset($metafields)) {
				$querystring .= "AND ";
			}
			$querystring .= "
			AND $wpdb->posts.post_status = 'publish'"; // exclude drafts, scheduled posts, etc

			//			echo $querystring; $wpdb->show_errors(); 		// for debugging, you can echo the completed query string and enable error reporting before it's executed

			if(!isset($keywords)) {
				$keywords = NULL;
			}

			$this->print_results($wpdb->get_results($querystring), $keywords); // format and output the search results

			die(); // wordpress may print out a spurious zero without this - can be particularly bad if using json
		}
	} // END WPUltimateSearch CLASS
endif; // END if(!class_exists("WPUltimateSearch"))

/**
 *  GLOBAL FUNCTIONS AND TEMPLATE TAGS
 */

if(class_exists("WPUltimateSearch")) {
	$wp_ultimate_search = new WPUltimateSearch();

	/**
	 * wp_ultimate_search_results
	 *
	 */
	function wp_ultimate_search_results() {
		global $wp_ultimate_search;
		$wp_ultimate_search->search_results_template_tag();
	}

	/**
	 * wp_ultimate_search_bar
	 *
	 */
	function wp_ultimate_search_bar() {
		global $wp_ultimate_search;
		$wp_ultimate_search->search_form_template_tag();
	}

	/* INCLUDES */
	require_once('wpus-widget.php'); // include widget file

	if(is_admin()) {
		require_once('views/wpus-options.php'); // include options file
	}

	if(file_exists( WP_PLUGIN_DIR . '/wp-ultimate-search-pro/wp-ultimate-search-pro.php')) {
		require_once(WP_PLUGIN_DIR . '/wp-ultimate-search-pro/wp-ultimate-search-pro.php');
		$wp_ultimate_search_pro = new WPUltimateSearchPro();
	}

	/**
	 * make options public
	 *
	 * @param $option
	 *
	 * @return bool
	 */
	function wpus_option($option) {
		$options = get_option('wpus_options');
		if(isset($options[$option])) {
			return $options[$option];
		} else {
			return FALSE;
		}
	}
}

?>
