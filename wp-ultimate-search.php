<?php
/*
Plugin Name: WP Ultimate Search
Plugin URI: http://ultimatesearch.mindsharelabs.com
Description: Advanced faceted AJAX search and filter utility
Version: 0.3
Author: Bryce Corkins / Mindshare Studios
Author URI: http://mind.sh/are/
*/

/**
 * Copyright (c) 2012 Mindshare Studios Inc., all rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
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
 */

/* CONSTANTS */
define('WPUS_BASE', plugin_dir_url( __FILE__ ));

/**
*  WP ULTIMATE SEARCH CONTAINER CLASS
*/
if (!class_exists("WPUltimateSearch")) {
	class WPUltimateSearch
	{
		function __construct() {
		
			// REGISTER AJAX FUNCTIONS WITH ADMIN-AJAX
			add_action( 'wp_ajax_usearch_search', array($this, 'get_results') );
			add_action( 'wp_ajax_nopriv_usearch_search', array($this, 'get_results') ); // need this to serve non logged in users
			add_action( 'wp_ajax_usearch_getvalues', array($this, 'get_values') );
			add_action( 'wp_ajax_nopriv_usearch_getvalues', array($this, 'get_values') ); // need this to serve non logged in users

			// REGISTER SHORTCODES
			add_shortcode("wp-ultimate-search-bar", array($this,'search_form'));
			add_shortcode("wp-ultimate-search-results", array($this,'search_results'));
		
			// REGISTER WIDGET
			add_action( 'widgets_init', create_function( '', 'register_widget( "wpultimatesearchwidget" );' ) );
		}
		
		/**
		*  PRIVATE FUNCTIONS
		*/

		private function highlightsearchterms($text,$keywords){
			foreach($keywords as $keyword) {
				$text = preg_replace('#' . $keyword . '#iu', '<strong class="usearch-highlight">$0</strong>', $text);
	    	}
			return $text;
		}
		private function render_categories($array){
			$seperator = ', ';
			$categories = get_the_category($array->ID);

			if( count($categories) > 1 ) {
				$catstring = 'in categories: ';
			} elseif (count($categories) == 1 ) {
				$catstring = 'in category: ';
			}
			foreach( $categories as $category) {
					$catstring .= '<a href="' . get_category_link( $category->term_id ).'" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $category->name ) ) . '">' . $category->cat_name . '</a>' . $seperator;
			}
		
			return trim($catstring, $seperator);
		}
		private function string_to_keywords($search){
			// TAKES A STRING AND SEPARATES IT INTO AN ARRAY. PRESERVES QUOTATION MARKS.
			preg_match_all('/(?<!")\b\w+\b|(?<=")\b[^"]+/', $search, $keywords);
			for ($i = 0; $i < count($keywords[0]); $i++) {
				$keywords[0][$i] = stripslashes($keywords[0][$i]);
			}
			return $keywords[0];
		}
		private function wpus_strip_tags($string, $remove_breaks = false) {
			// MODIFIED VERSION OF wp_strip_all_tags. Converts line breaks to spaces, plus one added preg_replace...
			$string = preg_replace('/[\r\n\t ]+/', ' ', $string);

			$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );

			$string = preg_replace( '@ *</?\s*(P|UL|OL|DL|BLOCKQUOTE)\b[^>]*?> *@si', "\n\n", $string );
			$string = preg_replace( '@ *<(BR|DIV|LI|DT|DD|TR|TD|H\d)\b[^>]*?> *@si', "\n", $string );
			$string = preg_replace( "@\n\n\n+@si", "\n\n", $string );

			$string = strip_tags( $string );

			if ( $remove_breaks )
				$string = preg_replace('/[\r\n\t ]+/', ' ', $string);

			// ...since we're pulling excerpts from the DB, some of the excerpts contain truncated HTML tags
			// that won't be picked up by strip_tags(). This removes any trailing HTML from the beginning
			// and end of the excerpt:
			$string = preg_replace( '/.*>|<.*/', ' ', $string);

			return trim( $string );
		}
		private function ajax_response($parameter, $response) {
			// similar to localize_script, but can be called after an action is completed
			echo '
				<script type="text/javascript">
				    /* <![CDATA[ */
				    var usearch_response = {
				            "' . $parameter . '":"'. $response .'"
				    };
				    /* ]]> */
				    </script>';
		}

		/**
		*  PUBLIC FUNCTIONS
		*/
	
		public function register_scripts() {
		
			// ENQUEUE VISUALSEARCH SCRIPTS
			wp_enqueue_script( 'jquery-ui-autocomplete', WPUS_BASE. 'js/jquery-ui-autocomplete.js', array('jquery') );
			wp_enqueue_script( 'jquery-ui-position', WPUS_BASE. 'js/jquery-ui-position.js', array('jquery') );
			wp_enqueue_script( 'underscore', WPUS_BASE. 'js/underscore-1.1.5.js');						
			wp_enqueue_script( 'backbone', WPUS_BASE. 'js/backbone-0.5.0.js', array('underscore') );	
			wp_enqueue_script( 'visualsearch', WPUS_BASE. 'js/visualsearch.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position', 'jquery-ui-autocomplete', 'backbone' ) );
		
			// ENQUEUE AND LOCALIZE MAIN JS FILE
			wp_enqueue_script( 'usearch-script', WPUS_BASE. 'js/main.js', array( 'visualsearch' ),'', wpus_option('scripts_in_footer'));
			wp_localize_script( 'usearch-script', 'usearch_script', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'searchNonce' => wp_create_nonce('search-nonce'), 'trackevents' => wpus_option('track_events'), 'eventtitle' => wpus_option('event_category') ) );
		
			// ENQUEUE STYLES
			wp_enqueue_style('usearch-bar', WPUS_BASE.'css/visualsearch.css');
			wp_enqueue_style('visualsearchcss', WPUS_BASE.'css/visualsearch-datauri.css');
		}
	
		public function search_form(){
			$this->register_scripts();
			// RENDER SEARCH FORM
			ob_start(); //start output buffer: everything echoed will be sent to the ob instead of the browser.
			echo '<div id="search_box_container"></div>';
			$the_form = ob_get_clean(); // flush output buffer into variable
			return $the_form;
		}
		public function search_form_template_tag() {
			$this->register_scripts();
			echo '<div id="search_box_container"></div>';
		}
		public function search_results() {
			// RENDER SEARCH RESULTS AREA
			$the_results = '
			<div id="search_query"></div>
			<div id="usearch_response">&nbsp;</div>';
			return $the_results;
		}
		public function search_results_template_tag(){
			echo '<div id="search_query"></div>
			<div id="usearch_response">&nbsp;</div>';
		}
		public function get_values() {
			// GET CURRENT CATEGORIES
			$args=array(
			  'orderby' => 'name',
			  'order' => 'ASC'
			  );
			$catstring = "";
			$categories=get_categories($args);
			foreach($categories as $category) {
			 	if($category === end($categories)){ // we don't want to append "," to the last array item, or the "split" function will insert a blank value
					$catstring .= $category->name;
				}
				else {
			  		$catstring .= $category->name .',';
				}
			}
			echo $catstring;
			exit;
		}
		public function get_results(){
			// THIS FUNCTION IS CALLED WHEN THE usearch_search ACTION IS TRIGGERED
			$nonce = $_GET['searchNonce'];
			// check to see if the submitted nonce matches with the
			// generated nonce we created earlier
			if ( ! wp_verify_nonce( $nonce, 'search-nonce' ) )
				die ( 'Busted!');
		
			$searcharray = $_GET['usearchquery'];
			global $wpdb;
		
			foreach($searcharray as $i => $row){
				if($row['category']){
					$categories[$i] = get_cat_ID($row['category']);
				} else if ($row['text']){
					$keywords = $this->string_to_keywords($row['text']);
				}
			}

			// TODO: would be nice if we could somehow iterate through to find the first matching keyword instead of just checking $keywords[0]

			$querystring = "
			SELECT *,
			substring(post_content,
					case
						 when locate('$keywords[0]', lower(post_content)) <= 80 then 1
			             else locate('$keywords[0]', lower(post_content)) - 80
			        end, 200)
			AS excerpt
			FROM $wpdb->posts ";
		
			if($categories) {
				$querystring .= "
				LEFT JOIN $wpdb->term_relationships ON($wpdb->posts.ID = $wpdb->term_relationships.object_id)
				LEFT JOIN $wpdb->term_taxonomy ON($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
			}
			$querystring .= "WHERE ";
			for ($i = 0; $i < count($keywords); $i++) {
			 	$querystring .= "(lower(post_content) LIKE '%{$keywords[$i]}%' ";
				$querystring .= "OR lower(post_title) LIKE '%{$keywords[$i]}%') ";
				if($i < count($keywords)-1) $querystring .= "AND ";
			}
			if($categories) {
				if($keywords) $querystring .= "AND ";
				$querystring .= "(";
				for ($i = 0; $i < count($categories); $i++) {
					$querystring .="$wpdb->term_taxonomy.term_id IN ('$categories[$i]') ";
					if($i < count($categories)-1) $querystring .= "OR ";
				}
				$querystring .= ") ";
				$querystring .= "AND $wpdb->term_taxonomy.taxonomy = 'category' ";
			}
			$querystring .="
			AND $wpdb->posts.post_status = 'publish'";
		
			if( $searcharray && $array = $wpdb->get_results($querystring)){
				foreach ($array as $item){
					
						if(wpus_option('search_shortcodes')) {
							$excerpt = $this->wpus_strip_tags(apply_filters('the_content', $item->excerpt));
						} else {
							$excerpt = $this->wpus_strip_tags(apply_filters('strip_shortcodes', $item->excerpt));
						}
						
						if($keywords)
							$excerpt = $this->highlightsearchterms($excerpt, $keywords);
							
						$catstring = $this->render_categories($item);
						echo '<div class="usearch-result"><div class="usearch-meta"><a href="'. get_permalink($item->ID) .'">'. $item->post_title . '</a> ' . $catstring . '</div><div class="usearch-excerpt">' . $excerpt . '</div></div>';
				}
				
				if(wpus_option('track_events')) // if we're tracking searches as analytics events, pass the number of search results back to main.js
					$this->ajax_response('numresults', count($array));
					
			} else if (count($searcharray) > 0) {
				echo 'Sorry, no results found.';
			}
				
			die();// wordpress may print out a spurious zero without this - can be particularly bad if using json
		}
	} // END WPUltimateSearch CLASS
} // END if(!class_exists("WPUltimateSearch"))

/**
*  PUBLIC FUNCTIONS AND TEMPLATE TAGS
*/

if (class_exists("WPUltimateSearch")) {
	$wp_ultimate_search = new WPUltimateSearch();
	
	function wp_ultimate_search_results() {  
		$wp_ultimate_search = new WPUltimateSearch();
	    $wp_ultimate_search->search_results_template_tag();
	}
	function wp_ultimate_search_bar() {  
		$wp_ultimate_search = new WPUltimateSearch();	
	    $wp_ultimate_search->search_form_template_tag();
	}

	/* INCLUDES */
	require_once( 'wpus-widget.php'); // include widget file
	if (is_admin())
	require_once( 'wpus-options.php'); // include options file

	// make options public
	function wpus_option( $option ) {
		$options = get_option( 'wpus_options' );
		if ( isset( $options[$option] ) )
			return $options[$option];
		else
			return false;
	}

}

?>