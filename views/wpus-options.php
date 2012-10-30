<?php
/**
 * WPUltimateSearchOptions
 *
 */
if(!class_exists('WPUltimateSearchOptions')) :
	class WPUltimateSearchOptions extends WPUltimateSearch {

		/**
		 * @var $sections
		 */
		private $sections;
		/**
		 * @var array $checkboxes
		 */
		private $checkboxes;
		/**
		 * @var $settings
		 */
		private $settings;
		
		private $ispro = false;

		function __construct() {

			// This will keep track of the checkbox options for the validate_settings function.
			$this->checkboxes = array();
			$this->setting = array();
			$this->get_settings();

			$this->sections['general'] = __('General Settings');
			$this->sections['taxopts'] = __('Taxonomy Settings');
			$this->sections['metaopts'] = __('Post Meta Settings');
			$this->sections['reset'] = __('Reset to Defaults');
			$this->sections['usage'] = __('Usage');
			$this->sections['about'] = __('About');

			add_action('admin_menu', array($this, 'add_pages')); // adds page to menu
			add_action('admin_init', array($this, 'register_settings'));

			if(!get_option('wpus_options')) {
				$this->initialize_settings();
			}
			
			$options = get_option('wpus_options');
			
			if(!empty($options['license_key']) && !empty($options['email_address'])) {
				$this->ispro = WPUltimateSearch::get_license($options['license_key'], $options['email_address']);
			}
		}

		/**
		 *
		 * Get meta fields
		 *
		 * Queries the database for all available post_meta options (that might be useful) and
		 * stores them in an array. Optionally accepts a $count, which denotes how many instances
		 * of a particular key have to be available before we register it as valid.
		 *
		 * @param int $count
		 */
		public function get_meta_fields($count = 1) {

			$options = get_option('wpus_options');
			global $wpdb;

			$querystring = "
			SELECT pm.meta_key,COUNT(*) as count FROM {$wpdb->postmeta} pm
			WHERE pm.meta_key NOT LIKE '\_%'
			GROUP BY pm.meta_key
			ORDER BY count DESC
		";

			$allkeys = $wpdb->get_results($querystring);
			foreach($allkeys as $i => $key) {
				if($key->{'count'} > $count) {
					$options["'metafields'"][$key->{"meta_key"}]["'count'"] = $key->{'count'};
				}
			}
			update_options('wpus_options', $options);

			die();
		}

		/**
		 *
		 * Get meta field counts
		 *
		 *
		 * Returns the total number of valid instances of all eligible meta keys
		 *
		 * @return mixed
		 */
		public function get_meta_field_counts() {

			$options = get_option('wpus_options');
			global $wpdb;

			$querystring = "
			SELECT pm.meta_key,COUNT(*) as count FROM {$wpdb->postmeta} pm
			WHERE pm.meta_key NOT LIKE '\_%'
			GROUP BY pm.meta_key
			ORDER BY count DESC
		";

			$allkeys = $wpdb->get_results($querystring);
			foreach($allkeys as $i => $key) {
				if($key->{'count'} > 1) {
					$counts[$key->{"meta_key"}]['count'] = $key->{'count'};
				}
			}
			return $counts;
		}

		/**
		 * Add menu pages
		 *
		 */
		public function add_pages() {
			$admin_page = add_options_page('Ultimate Search', 'Ultimate Search', 'manage_options', 'wpus-options', array($this, 'display_page'));

			add_action('admin_print_scripts-'.$admin_page, array(&$this, 'scripts'));
			add_action('admin_print_styles-'.$admin_page, array(&$this, 'styles'));
		}

		/**
		 *
		 * Create settings field
		 *
		 *
		 * For settings fields to be registered with add_settings_field
		 *
		 * @param array $args
		 */
		public function create_setting($args = array()) {

			$defaults = array(
				'id'      => 'wpus_default',
				'title'   => 'Default Field',
				'desc'    => 'This is a default description.',
				'std'     => '',
				'type'    => 'text',
				'section' => 'general',
				'choices' => array(),
				'class'   => ''
			);

			extract(wp_parse_args($args, $defaults));

			$field_args = array(
				'type'      => $type,
				'id'        => $id,
				'desc'      => $desc,
				'std'       => $std,
				'choices'   => $choices,
				'label_for' => $id,
				'class'     => $class
			);

			if($type == 'checkbox') {
				$this->checkboxes[] = $id;
			}

			add_settings_field($id, $title, array($this, 'display_setting'), 'wpus-options', $section, $field_args);
		}

		/**
		 *
		 * Page wrappers and layout handlers
		 *
		 *
		 */
		public function display_page() {

			echo '<div class="wrap">
		<div class="icon32" id="icon-options-general"></div>
		<h2>'.__('WP Ultimate Search Options').'</h2>';
		
		if($this->ispro != 1)
		echo '<div id="upgrade"><h4>WP Ultimate Search Pro</h4><p>Supports unlimited custom taxonomies, post meta data (including data
		from Advanced Custom Fields), and more. <strong>Only $25</strong>.</p><a class="button-primary" target="_blank" href="http://mindsharelabs.com/products/wp-ultimate-search-pro/">Upgrade Now</a></div>';

			echo '<form action="options.php" method="post">';

			// WPUltimateSearch::activation_hook(); not sure why this was here, commented out for now

			settings_fields('wpus_options');
			echo '<div class="ui-tabs">
				<ul class="wpus-options ui-tabs-nav">';

			foreach($this->sections as $section_slug => $section) {
				echo '<li><a href="#'.$section_slug.'">'.$section.'</a></li>';
			}

			echo '</ul>';
			do_settings_sections($_GET['page']);

			echo '</div>
			<p class="submit"><input name="Submit" type="submit" class="button-primary" value="'.__('Save Changes').'" /></p>

		</form>';

			echo '<script type="text/javascript">
			
			jQuery(document).ready(function($) {
				
				$(function(){
				$(".tooltip").tipTip({defaultPosition: "top"});
				});
				
				$("input[type=\"checkbox\"]").change(function() {
					var title = $(this).attr("id");
				    if (this.checked) {
				        $("#" + title + "-title").addClass("checked");
				    } else {
				        $("#" + title + "-title").removeClass("checked");
				    }
				});
				$(".VS-icon-cancel").click(function() {
					var title = $(this).parent().attr("id");
					title = title.replace("-title", "");
					$("#" + title).attr("checked", false);
					$(this).parent().removeClass("checked");
				})
				
				var sections = [];';

			foreach($this->sections as $section_slug => $section) {
				echo "sections['$section'] = '$section_slug';";
			}

			echo 'var wrapped = $(".wrap h3").wrap("<div class=\"ui-tabs-panel\">");
				wrapped.each(function() {
					$(this).parent().append($(this).parent().nextUntil("div.ui-tabs-panel"));
				});
				$(".ui-tabs-panel").each(function(index) {
					$(this).attr("id", sections[$(this).children("h3").text()]);
					if (index > 0)
						$(this).addClass("ui-tabs-hide");
				});
				$(".ui-tabs").tabs({
					fx: { opacity: "toggle", duration: "fast" }
				});

				$("input[type=text], textarea").each(function() {
					if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "")
						$(this).css("color", "#999");
				});

				$("input[type=text], textarea").focus(function() {
					if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "") {
						$(this).val("");
						$(this).css("color", "#000");
					}
				}).blur(function() {
					if ($(this).val() == "" || $(this).val() == $(this).attr("placeholder")) {
						$(this).val($(this).attr("placeholder"));
						$(this).css("color", "#999");
					}
				});

				$(".wrap h3, .wrap table").show();

				// This will make the "warning" checkbox class really stand out when checked.
				// I use it here for the Reset checkbox.
				$(".warning").change(function() {
					if ($(this).is(":checked"))
						$(this).parent().css("background", "#c00").css("color", "#fff").css("fontWeight", "bold");
					else
						$(this).parent().css("background", "none").css("color", "inherit").css("fontWeight", "normal");
				});

				// Browser compatibility
				if ($.browser.mozilla) 
				         $("form").attr("autocomplete", "off");
			});
		</script>
		</div>';
		}

		/**
		 *
		 * Generic display section
		 *
		 *
		 */
		public function display_section() {
			// code
		}

		/**
		 *
		 * Taxonomy options section
		 *
		 *
		 */
		public function display_taxopts_section() {
			$options = get_option('wpus_options');
			if($this->ispro != 1) echo '<h4 class="notice">Enable these options by upgrading to Ultimate Search Pro</h4>';
			?>
        <table class="taxonomies-table <?php if($this->ispro != 1) echo 'disabled'; ?>">
            <tbody>
            <tr>
                <th class="nobg">Taxonomy
                    <div class="tooltip" title="Taxonomy label field, as it's stored in the database."></div>
                </th>
                <th>Enabled
                    <div class="tooltip" title="Whether or not to include this term as a search facet."></div>
                </th>
                <th>Label override
                    <div class="tooltip" title="You can specify a label which will be autocompleted in the search box. This will override the taxonomy's default label."></div>
                </th>
                <th>Terms found
                    <div class="tooltip" title="Number of terms in the taxonomy. Hover over the number for a listing."></div>
                </th>
                <th>Max terms
                    <div class="tooltip" title="Set a maximum number of terms to load in the autocomplete dropdown. Use '0' for unlimited."></div>
                </th>
                <th>Exclude
                    <div class="tooltip" title="Comma-separated list of term names to exclude from autocomplete. If the term contains spaces, wrap it in quotation marks."></div>
                </th>
            </tr>
				<?php
				$altclass = '';

				$taxonomies = get_taxonomies(array('public'   => TRUE), 'objects');
				foreach($taxonomies as $taxonomy) {
					$tax = $taxonomy->name;

					// If there aren't default settings yet for the given taxonomy, create them
					if(!isset($options["'taxonomies'"][$tax])) {
						$options["'taxonomies'"][$tax] = array(
							"'enabled'"    => 0,
							"'label'"      => $tax,
							"'max'"        => 0,
							"'exclude'"    => ''
						);
					}

					// If the taxonomy is active, set the 'checked' class
					if(!empty($options["'taxonomies'"][$tax]["'enabled'"])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$options["'taxonomies'"][$tax]["'enabled'"] = 0;
					}

					// Generate the list of terms for the "Count" tooltip
					$terms = get_terms($tax);
					$termcount = count($terms);
					$termstring = '';
					foreach($terms as $term) {
						$termstring .= $term->name.', ';
					}
					$disabledtext = "";
					if($this->ispro != 1) $disabledtext = 'disabled="disabled"';
					?>
                <tr>
                    <th scope="row" class="tax <?php echo $altclass ?>"><span id="<?php echo $tax.'-title' ?>" class="<?php echo $checked ?>"><?php echo $taxonomy->label ?>:<div class="VS-icon-cancel"></div></span>
                    </th>
                    <td class="<?php echo $altclass ?>">
                        <input class="checkbox" <?php echo $disabledtext ?> type="checkbox" id="<?php echo $tax ?>" name="wpus_options['taxonomies'][<?php echo $tax ?>]['enabled']" value="1" <?php echo checked($options["'taxonomies'"][$tax]["'enabled'"], 1, FALSE) ?> />
                    </td>
                    <td class="<?php echo $altclass ?>">
                        <input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options['taxonomies'][<?php echo $tax ?>]['label']" size="20" placeholder="<?php echo $taxonomy->name ?>" value="<?php echo esc_attr($options["'taxonomies'"][$tax]["'label'"]) ?>" />
                    </td>
                    <td class="<?php echo $altclass ?>"><?php echo $termcount ?>
                        <div class="tooltip" title="<?php echo $termstring ?>"></div>
                    </td>
                    <td class="<?php echo $altclass ?>">
                        <input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options['taxonomies'][<?php echo $tax ?>]['max']" size="3" placeholder="0" value="<?php echo esc_attr($options["'taxonomies'"][$tax]["'max'"]) ?>" />
                    </td>
                    <td class="<?php echo $altclass ?>">
                        <input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options['taxonomies'][<?php echo $tax ?>]['exclude']" size="30" placeholder="" value="<?php echo esc_attr($options["'taxonomies'"][$tax]["'exclude'"]) ?>" />
                    </td>
                </tr>
					<?php
					// Set alternating classes on the table rows
					if($altclass == 'alt') {
						$altclass = '';
					} else {
						$altclass = 'alt';
					}?>
					<?php } ?>
            </tbody>
        </table>
		<?php
		}

		/**
		 *
		 * Meta field options section
		 *
		 *
		 */
		public function display_metaopts_section() {
			$options = get_option('wpus_options');
			if($this->ispro != 1) echo '<h4 class="notice">Enable these options by upgrading to Ultimate Search Pro</h4>';
			?>
        <table class="taxonomies-table <?php if($this->ispro != 1) echo 'disabled'; ?>">
            <tbody>
            <tr>
                <th class="nobg">Meta Key
                    <div class="tooltip" title="Meta key field, as it's stored in the database."></div>
                </th>
                <th>Enabled
                    <div class="tooltip" title="Whether or not to include this term as a search facet."></div>
                </th>
                <th>Label override
                    <div class="tooltip" title="You can specify a label which will be autocompleted in the search box. This will override the field's default label."></div>
                </th>
                <th>Instances
                    <div class="tooltip" title="Number of times a particular meta field was found in the database."></div>
                </th>
                <th>Type
                    <div class="tooltip" title="Set the format of the data."></div>
                </th>
                <th>Autocomplete
                    <div class="tooltip" title="Whether or not to autocomplete search terms in the search bar. Only select this if the meta field has a small number of possible options."></div>
                </th>
            </tr>
				<?php
				$altclass = '';

				$counts = $this->get_meta_field_counts();

				foreach($options["'metafields'"] as $metafield => $value) {

					// If the taxonomy is active, set the 'checked' class
					if(!empty($value["'enabled'"])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$options["'metafields'"][$metafield]["'enabled'"] = 0;
					}

					if(empty($value["'autocomplete'"])) {
						$options["'metafields'"][$metafield]["'autocomplete'"] = 0;
					}

					if(empty($value["'count'"])) {
						$value["'count'"] = $counts[$metafield]['count'];
					}


					// Generate the list of terms for the "Count" tooltip
					/* $terms = get_terms($tax);
									$termcount = count($terms);
									$termstring = '';
									foreach ( $terms as $term ) {
										$termstring .= $term->name . ', ';
									} */
					if($this->ispro != 1) $disabledtext = 'disabled="disabled"';
					?>
                <tr>
                    <th scope="row" class="tax <?php echo $altclass ?>"><span id="<?php echo $metafield.'-title' ?>" class="<?php echo $checked ?>"><?php echo $metafield ?>:<div class="VS-icon-cancel"></div></span>
                    </th>
                    <td class="<?php echo $altclass ?>">
                        <input class="checkbox" <?php echo $disabledtext ?> type="checkbox" id="<?php echo $metafield ?>" name="wpus_options['metafields'][<?php echo $metafield ?>]['enabled']" value="1" <?php echo checked($options["'metafields'"][$metafield]["'enabled'"], 1, FALSE) ?> />
                    </td>
                    <td class="<?php echo $altclass ?>">
                        <input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $metafield ?>" name="wpus_options['metafields'][<?php echo $metafield ?>]['label']" size="20" placeholder="<?php echo $metafield ?>" value="<?php echo esc_attr($options["'metafields'"][$metafield]["'label'"]) ?>" />
                    </td>
                    <td class="<?php echo $altclass ?>"><?php echo $value["'count'"] ?></td>
                    <td class="<?php echo $altclass ?>"><select class="" id="<?php echo $metafield ?>" name="wpus_options['metafields'][<?php echo $metafield ?>]['type']" />
                        <option value="string" <?php echo selected($options["'metafields'"][$metafield]["'type'"], "string", FALSE) ?> >String</option>
                        <option value="number" <?php echo selected($options["'metafields'"][$metafield]["'type'"], "number", FALSE) ?> >Number</option>
                        <option value="date" <?php echo selected($options["'metafields'"][$metafield]["'type'"], "date", FALSE) ?> >Date</option>
                        </select></td>
                    <td class="<?php echo $altclass ?>">
                        <input class="checkbox" <?php echo $disabledtext ?> type="checkbox" name="wpus_options['metafields'][<?php echo $metafield ?>]['autocomplete']" value="1" <?php echo checked($options["'metafields'"][$metafield]["'autocomplete'"], 1, FALSE) ?> />
                    </td>
                </tr>
					<?php
					// Set alternating classes on the table rows
					if($altclass == 'alt') {
						$altclass = '';
					} else {
						$altclass = 'alt';
					}?>
					<?php } ?>
            </tbody>
        </table>
		<?php
		}

		/**
		 *
		 * Usage section
		 *
		 *
		 */
		public function display_usage_section() {
			?>

        <strong>Shortcode</strong><br />
        <p>During this development period, the shortcode is used in two parts: the search bar, and the search results. Put [<?=WPUS_PLUGIN_SLUG?>-bar] where you’d like the search bar, and
            [<?=WPUS_PLUGIN_SLUG?>-results] where you’d like the results to appear. No options (…yet).</p>

        <strong>Template tag</strong><br />
        <p>Call the search bar with wp_ultimate_search_bar()<br />
            Render the search results area with wp_ultimate_search_results()</p>
		<?php
		}

		/**
		 *
		 * About section
		 *
		 *
		 */
		public function display_about_section() {
			?>

        <p>This happened in 2012.<br /><a href="http://www.brycecorkins.com/">Bryce</a> and <a href="http://www.damiantaggart.com/">Damian</a> were involved.
            <br />They work at <a href="http://mind.sh/are/">Mindshare Studios</a>. </p>
        <p><br />If you like what we do and want to show your support, consider <a href="http://mind.sh/are/donate/">making a donation</a>.</p>
        <br />
        <p>This plugin <a href="http://wordpress.org/extend/plugins/<?=WPUS_PLUGIN_SLUG?>/">on WordPress.org</a>.</p>
        <p>You can also <a href="http://wordpress.org/support/plugin/<?=WPUS_PLUGIN_SLUG?>/">get support</a>.</p>

		<?php
		}

		/**
		 *
		 * Display HTML fields for individual settings
		 *
		 *
		 * This outputs the actual HTML for the settings fields, where we can receive input and display
		 * labels and descriptions.
		 *
		 * @param array $args
		 */
		public function display_setting($args = array()) {

			extract($args);

			$options = get_option('wpus_options');

			if(!isset($options[$id]) && $type != 'checkbox') {
				$options[$id] = $std;
			} elseif(!isset($options[$id])) {
				$options[$id] = 0;
			}

			$field_class = '';
			if($class != '') {
				$field_class = ' '.$class;
			}

			switch($type) {

				case 'heading':
					echo '</td></tr><tr valign="top"><td colspan="2"><h4>'.$desc.'</h4>';
					break;

				case 'checkbox':

					echo '<input class="checkbox'.$field_class.'" type="checkbox" id="'.$id.'" name="wpus_options['.$id.']" value="1" '.checked($options[$id], 1, FALSE).' /> <label for="'.$id.'">'.$desc.'</label>';

					break;

				case 'select':
					echo '<select class="select'.$field_class.'" name="wpus_options['.$id.']">';

					foreach($choices as $value => $label) {
						echo '<option value="'.esc_attr($value).'"'.selected($options[$id], $value, FALSE).'>'.$label.'</option>';
					}

					echo '</select>';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'radio':
					$i = 0;
					foreach($choices as $value => $label) {
						echo '<input class="radio'.$field_class.'" type="radio" name="wpus_options['.$id.']" id="'.$id.$i.'" value="'.esc_attr($value).'" '.checked($options[$id], $value, FALSE).'> <label for="'.$id.$i.'">'.$label.'</label>';
						if($i < count($options) - 1) {
							echo '<br />';
						}
						$i++;
					}

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'textarea':
					echo '<textarea class="'.$field_class.'" id="'.$id.'" name="wpus_options['.$id.']" placeholder="'.$std.'" rows="5" cols="30">'.wp_htmledit_pre($options[$id]).'</textarea>';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'password':
					echo '<input class="regular-text'.$field_class.'" type="password" id="'.$id.'" name="wpus_options['.$id.']" value="'.esc_attr($options[$id]).'" />';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'text':
				default:
					$disabledtxt = ' ';
					if($field_class == " disabled") {
						$disabledtxt = ' disabled="disabled" ';
					}

					echo '<input class="regular-text'.$field_class.'"'.$disabledtxt.'type="text" id="'.$id.'" name="wpus_options['.$id.']" placeholder="'.$std.'" value="'.esc_attr($options[$id]).'" />';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;
			}
		}

		/**
		 *
		 * Standard settings
		 *
		 *
		 * All settings in the $this->settings object wil be registered with add_settings_field. You can
		 * specify a settings section and default value.
		 *
		 */
		public function get_settings() {

			/* General Settings
		 ===========================================*/
			
			$options = get_option('wpus_options');
			
			$this->settings['email_address'] = array(
				'title'   => __('Email Address'),
				'desc'    => __(''),
				'std'     => "",
				'type'    => 'text',
				'section' => 'general'
			);

			if($this->ispro == '1') {
				$this->settings['license_key'] = array(
					'title'   => __('License Key'),
					'desc'    => __('Thanks for registering!'),
					'std'     => "",
					'type'    => 'text',
					'section' => 'general',
					'class'	  => 'valid'
				);
			} elseif(!empty($options['license_key']) && $this->ispro != 1) {
				$this->settings['license_key'] = array(
					'title'   => __('License Key'),
					'desc'    => __('We were unable to validate your license key. Please try again, or contact support.'),
					'std'     => "",
					'type'    => 'text',
					'section' => 'general',
					'class'	  => 'invalid'
				);
			} else {
				$this->settings['license_key'] = array(
					'title'   => __('License Key'),
					'desc'    => __('Enter your license key to unlock the premium features.'),
					'std'     => "",
					'type'    => 'text',
					'section' => 'general'
				);
			}

			$this->settings['scripts_in_footer'] = array(
				'section' => 'general',
				'title'   => __('Load Scripts in Footer'),
				'desc'    => __('If you encounter problems, try turning this off to load the scripts into the header instead.'),
				'type'    => 'checkbox',
				'std'     => 1 // Set to 1 to be checked by default, 0 to be unchecked by default.
			);

//			$this->settings['search_shortcodes'] = array(
//				'section' => 'general',
//				'title'   => __('Search Shortcode Content'),
//				'desc'    => __('Enable this option to evaluate shortcodes before searching. Will slow down searches slightly.'),
//				'type'    => 'checkbox',
//				'std'     => 0 // Set to 1 to be checked by default, 0 to be unchecked by default.
//			);
			$this->settings['loading_animation'] = array(
				'section' => 'general',
				'title'   => __('Loading Animation'),
				'desc'    => __('Style of loading animation to use for results pane.'),
				'type'    => 'radio',
				'std'     => '2',
				'choices' => array(
					'css3'      => 'Cool CSS3 animation',
					'spinner'   => 'Standard .gif spinner',
					'none'      => 'No loading animation (default)'
				)
			);
			$this->settings['results_page'] = array(
				'title'   => __('Search Results Page'),
				'desc'    => __('Specify the URL to the page with the ['.WPUS_PLUGIN_SLUG.'-results] shortcode'),
				'std'     => "/search/",
				'type'    => 'text',
				'section' => 'general'
			);
			$this->settings['no_results_msg'] = array(
				'title'   => __('"No results" message'),
				'desc'    => __('Customize the message displayed when no results are found'),
				'std'     => "Sorry, no results found.",
				'type'    => 'text',
				'section' => 'general'
			);

			$this->settings['analytics_heading'] = array(
				'section' => 'general',
				'title'   => '', // not used
				'desc'    => 'Google Analytics',
				'type'    => 'heading'
			);

			$this->settings['track_events'] = array(
				'section' => 'general',
				'title'   => __('Track Events'),
				'desc'    => __('Enabling this option will cause searches to appear as events in your Google Analytics reports<br /> (requires an Analytics tracking code to be already installed.)'),
				'type'    => 'checkbox',
				'std'     => 0 // Set to 1 to be checked by default, 0 to be unchecked by default.
			);

			$this->settings['event_category'] = array(
				'title'   => __('Event Category'),
				'desc'    => __('Set the category your events will appear under in reports.'),
				'std'     => 'Search',
				'type'    => 'text',
				'section' => 'general'
			);

			/*
							$this->settings['example_textarea'] = array(
								'title'   => __( 'Example Textarea Input' ),
								'desc'    => __( 'This is a description for the textarea input.' ),
								'std'     => 'Default value',
								'type'    => 'textarea',
								'section' => 'general'
							);

							$this->settings['example_select'] = array(
								'section' => 'general',
								'title'   => __( 'Example Select' ),
								'desc'    => __( 'This is a description for the drop-down.' ),
								'type'    => 'select',
								'std'     => '',
								'choices' => array(
									'choice1' => 'Other Choice 1',
									'choice2' => 'Other Choice 2',
									'choice3' => 'Other Choice 3'
								)
							); */

			/* Reset
		 ===========================================*/

			$this->settings['reset_theme'] = array(
				'section' => 'reset',
				'title'   => __('Reset options'),
				'type'    => 'checkbox',
				'std'     => 0,
				'class'   => 'warning', // Custom class for CSS
				'desc'    => __('Check this box and click "Save Changes" below to reset all options to their defaults.')
			);
		}

		/**
		 *
		 * Initialize default settings
		 *
		 *
		 * If no options array is found, initialize everything to their default settings
		 *
		 *
		 */
		public function initialize_settings() {

			$default_settings = array();
			foreach($this->settings as $id => $setting) {
				if($setting['type'] != 'heading') {
					$default_settings[$id] = $setting['std'];
				}
			}

			// Set default taxonomy parameters. Because this is called from the constructor, custom taxonomies
			// aren't available yet, so we'll set all of the built-in taxonomies to enabled by default.
			$taxonomies = get_taxonomies(array('public'   => TRUE));
			foreach($taxonomies as $taxonomy) {
				$default_settings["'taxonomies'"][$taxonomy] = array(
					"'enabled'"    => 1,
					"'label'"      => $taxonomy,
					"'max'"        => 0,
					"'exclude'"    => ''
				);
			}

			// Set default meta field parameters.
			global $wpdb;
			$querystring = "
			SELECT pm.meta_key,COUNT(*) as count FROM {$wpdb->postmeta} pm
			WHERE pm.meta_key NOT LIKE '\_%'
			GROUP BY pm.meta_key
			ORDER BY count DESC
		";

			$allkeys = $wpdb->get_results($querystring);
			foreach($allkeys as $i => $key) {
				if($key->{'count'} > 1) {
					$default_settings["'metafields'"][$key->{"meta_key"}] = array(
						"'enabled'"      => 0,
						"'label'"        => $key->{"meta_key"},
						"'count'"        => $key->{'count'},
						"'type'"         => 'string',
						"'autocomplete'" => 0
					);
				}
			}

			update_option('wpus_options', $default_settings);
		}

		/**
		 *
		 * Register settings
		 *
		 *
		 * Set up the wpus_options object, register the different settings sections / pages, and register
		 * each of the individual settings.
		 *
		 */
		public function register_settings() {

			register_setting('wpus_options', 'wpus_options', array(&$this, 'validate_settings'));

			foreach($this->sections as $slug => $title) {
				if($slug == 'about') {
					add_settings_section($slug, $title, array(&$this, 'display_about_section'), 'wpus-options');
				} else {
					if($slug == 'usage') {
						add_settings_section($slug, $title, array(&$this, 'display_usage_section'), 'wpus-options');
					} else {
						if($slug == 'taxopts') {
							add_settings_section($slug, $title, array(&$this, 'display_taxopts_section'), 'wpus-options');
						} else {
							if($slug == 'metaopts') {
								add_settings_section($slug, $title, array(&$this, 'display_metaopts_section'), 'wpus-options');
							} else {
								add_settings_section($slug, $title, array(&$this, 'display_section'), 'wpus-options');
							}
						}
					}
				}
			}

			$this->get_settings();

			foreach($this->settings as $id => $setting) {
				$setting['id'] = $id;
				$this->create_setting($setting);
			}
		}

		/**
		 *
		 * Validate settings
		 *
		 *
		 * By default, _POST ignores checkboxes with no value set. We need to set this to 0 in wpus_options,
		 * so this function compares the POST data with the local $options array and sets the checkboxes to
		 * 0 where needed. Then merges $input with $options so the options *not* registered with add_settings_field
		 * still get passed through into the database.
		 *
		 * @param $input
		 *
		 * @return array|bool
		 */
		public function validate_settings($input) {

			if(!isset($input['reset_theme'])) {
				$options = get_option('wpus_options');

				foreach($this->checkboxes as $id) {
					if(!isset($input[$id]) || $input[$id] != '1') {
						$input[$id] = 0;
					} else {
						$input[$id] = 1;
					}
				}
				$result = array_merge($options, $input);
				return $result;
			}
			return FALSE;
		}

		/**
		 *
		 * Enqueue and print scripts
		 *
		 *
		 */
		public function scripts() {

			wp_enqueue_script('tiptip', WPUS_BASE.'js/jquery.tipTip.minified.js', array('jquery'));
			wp_print_scripts('jquery-ui-tabs');
		}

		/**
		 *
		 * Enqueue and print styles
		 *
		 *
		 */
		public function styles() {

			wp_register_style('wpus-admin', WPUS_BASE.'css/wpus-options.css');
			wp_enqueue_style('wpus-admin');
		}
	} // END CLASS
endif;

$wp_ultimate_search_options = new WPUltimateSearchOptions();
?>
