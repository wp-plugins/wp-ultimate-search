<?php
/**
 * WPUltimateSearchOptions
 *
 */
if(!class_exists('WPUltimateSearchOptions')) :
	class WPUltimateSearchOptions extends WPUltimateSearch {

		private $sections, $checkboxes, $settings;
		public $options, $is_active, $updater;

		function __construct() {

			// This will keep track of the checkbox options for the validate_settings function.
			$this->checkboxes = array();
			$this->setting = array();
			$this->get_settings();

			if(!$this->options = get_option('wpus_options')) {
				$this->initialize_settings();
			} else {
				// Check if there are any new meta / taxonomy fields. Set them up w/ default values if necessary
				$this->update_meta_fields();
				$this->update_taxonomies();
			}
			
			require_once(WPUS_DIR_PATH.'lib/mindshare-auto-update/mindshare-license-check.php');
			$this->updater = new mindshare_license_check(trailingslashit(WPUS_PRO_SLUG).WPUS_PRO_FILE, WPUS_PRO_PATH);

			// @todo this is a mess and doesn't make sense.. I blame @bryce!
			if(!empty($this->options['license_key']) && !empty($this->options['email_address'])) {
				$this->is_active = $this->updater->get_remote_license($this->options['license_key'], $this->options['email_address']);
				$this->hash = $this->updater->hash;
			} else {
				$this->is_active = FALSE;
				$this->hash = NULL;
			}
			
			$this->sections['general'] = __('General Settings');
			// Only show taxonomy / metafield options for registered users (no more teasing)
			if($this->is_active === $this->hash) {
				$this->sections['taxopts'] = __('Taxonomy Settings');
				$this->sections['metaopts'] = __('Post Meta Settings');
			}
			$this->sections['reset'] = __('Reset to Defaults');
			$this->sections['about'] = __('About');
			
			add_action('wp_ajax_wpus_validate', array($this, 'ajax_validate'));
		}

		/**
		 *
		 * Update meta fields
		 *
		 * Queries the database for all available post_meta options (that might be useful) and
		 * stores them in an array. Optionally accepts a $count, which denotes how many instances
		 * of a particular key have to be available before we register it as valid.
		 *
		 * @param int $count
		 */
		private function update_meta_fields($count = 1) {
			global $wpdb;
			$querystring = "
			SELECT pm.meta_key,COUNT(*) as count FROM {$wpdb->postmeta} pm
			WHERE pm.meta_key NOT LIKE '\_%'
			GROUP BY pm.meta_key
			ORDER BY count DESC
		";

			$allkeys = $wpdb->get_results($querystring);

			// set default values for all meta keys without stored settings

			foreach($allkeys as $i => $key) {
				if($key->{'count'} > $count && !isset($this->options["metafields"][$key->{"meta_key"}])) {
					$this->options["metafields"][$key->{"meta_key"}] = array(
						"enabled"      => 0,
						"label"        => $key->{"meta_key"},
						"count"        => $key->{'count'},
						"type"         => 'string',
						"autocomplete" => 0
					);
				}
				// count the instances of each key, overwrite whatever it was before
				if($key->{'count'} > $count) {
					$this->options["metafields"][$key->{"meta_key"}]["count"] = $key->{'count'};
				}
			}
		}

		/**
		 *  Set default taxonomy parameters
		 *
		 */
		private function update_taxonomies() {

			$taxonomies = get_taxonomies(array('public' => TRUE));
			foreach($taxonomies as $taxonomy) {
				if(!isset($this->options['taxonomies'][$taxonomy])) {
					if($taxonomy == 'post_tag') {
						$this->options['taxonomies'][$taxonomy] = array(
							"enabled" => 1,
							"label"   => 'tag',
							"max"     => 0,
							"exclude" => ''
						);
					} elseif($taxonomy == 'category') {
						$this->options['taxonomies'][$taxonomy] = array(
							"enabled" => 1,
							"label"   => $taxonomy,
							"max"     => 0,
							"exclude" => ''
						);
					} else {
						$this->options['taxonomies'][$taxonomy] = array(
							"enabled" => 0,
							"label"   => $taxonomy,
							"max"     => 0,
							"exclude" => ''
						);
					}
				}
			}
		}

		/**
		 * Add menu pages
		 *
		 */
		public function add_pages() {
			$admin_page = add_options_page('Ultimate Search', 'Ultimate Search', 'manage_options', 'wpus-options', array($this, 'display_page'));
			add_action('admin_print_scripts-'.$admin_page, array($this, 'scripts'));
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

			/** @noinspection PhpUndefinedVariableInspection */
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

			/** @noinspection PhpUndefinedVariableInspection */
			add_settings_field($id, $title, array($this, 'display_setting'), 'wpus-options', $section, $field_args);
		}

		/**
		 *
		 * Page wrappers and layout handlers
		 *
		 *
		 */
		public function display_page() {
			?>

			<div class="wrap">
			<div class="icon32" id="icon-options-general"></div>
			<h2><?php echo __('WP Ultimate Search Options') ?> </h2>
			<?php if($this->is_active !== $this->hash) { ?>
				<div class="postbox-container">
					<div id="submitdiv" class="postbox">
						<h3>WP Ultimate Search Pro</h3>

						<div class="inside">
							<p>The free version of <strong>WP Ultimate Search</strong> contains all of the power of the pro version, but supports faceting only by "tag" and "category".</p>
							<p>Upgrading to <strong>WP Ultimate Search Pro</strong> adds support for faceting by unlimited custom taxonomies, post meta data (including data from Advanced Custom Fields), and provides additional settings for how these facets are displayed.</p>
							<p><strong>Only $25 for an unlimited license</strong>.</p>
							<a class="button-primary" target="_blank" href="https://mindsharelabs.com/products/wp-ultimate-search-pro/?utm_source=wpus_basic&utm_medium=upgradebutton&utm_campaign=upgrade">Learn More</a>
						</div>
					</div>
				</div>
			<?php } ?>

			<form id="wpus-options" action="options.php" method="post">
				<?php settings_fields('wpus_options'); ?>
				<div class="ui-tabs">
					<ul class="wpus-options ui-tabs-nav">
						<?php foreach($this->sections as $section_slug => $section) { ?>
							<li><a href="#<?php echo $section_slug ?>"><?php echo $section ?></a></li>
						<?php } ?>
					</ul>
					<?php do_settings_sections($_GET['page']); ?>
				</div>
				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes" /></p>
			</form>

		<?php
		}

		/**
		 *
		 * First pane, general options
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
		public function display_taxopts_section() { ?>
			<table class="widefat <?php if($this->is_active !== $this->hash) : echo 'disabled'; endif; ?>">
				<thead>
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
				</thead>
				<tfoot>
				<tr>
					<th class="nobg">Taxonomy</th>
					<th>Enabled</th>
					<th>Label override</th>
					<th>Terms found</th>
					<th>Max terms</th>
					<th>Exclude</th>
				</tr>
				</tfoot>
				<tbody>
				<?php
				$altclass = '';

				$taxonomies = get_taxonomies(array('public' => TRUE), 'objects');
				foreach($taxonomies as $taxonomy) {
					$tax = $taxonomy->name;

					// If the taxonomy is active, set the 'checked' class
					if(!empty($this->options['taxonomies'][$tax]['enabled'])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$this->options['taxonomies'][$tax]['enabled'] = 0;
					}

					// Generate the list of terms for the "Count" tooltip
					$terms = get_terms($tax);
					$termcount = count($terms);
					$termstring = '';
					foreach($terms as $term) {
						$termstring .= $term->name.', ';
					}
					$disabledtext = "";
					if($this->is_active !== $this->hash) {
						$disabledtext = 'disabled="disabled"';
					}
					?>
					<tr>
						<th scope="row" class="tax <?php echo $altclass ?>"><span id="<?php echo $tax.'-title' ?>" class="<?php echo $checked ?>"><?php echo $taxonomy->label ?>:<div class="VS-icon-cancel"></div></span>
						</th>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" <?php echo $disabledtext ?> type="checkbox" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][enabled]" value="1" <?php echo checked($this->options['taxonomies'][$tax]['enabled'], 1, FALSE) ?> />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][label]" size="20" placeholder="<?php echo $taxonomy->name ?>" value="<?php echo esc_attr($this->options['taxonomies'][$tax]['label']) ?>" />
						</td>
						<td class="<?php echo $altclass ?>"><?php echo $termcount ?>
							<div class="tooltip" title="<?php echo $termstring ?>"></div>
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][max]" size="3" placeholder="0" value="<?php echo esc_attr($this->options['taxonomies'][$tax]['max']) ?>" />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $tax ?>" name="wpus_options[taxonomies][<?php echo $tax ?>][exclude]" size="30" placeholder="" value="<?php echo esc_attr($this->options['taxonomies'][$tax]['exclude']) ?>" />
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
		public function display_metaopts_section() { ?>
			<table class="widefat <?php if($this->is_active !== $this->hash) : echo 'disabled'; endif; ?>">
				<thead>
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
					<?php /* commenting these fields out for now as they haven't been implemented yet
					<th>Type
						<div class="tooltip" title="Set the format of the data."></div>
					</th>
					<th>Autocomplete
						<div class="tooltip" title="Whether or not to autocomplete search terms in the search bar. Only select this if the meta field has a small number of possible options."></div>
					</th>
					*/ ?>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th class="nobg">Meta Key</th>
					<th>Enabled</th>
					<th>Label override</th>
					<th>Instances</th>
					<!-- <th>Type</th>
					<th>Autocomplete</th> -->
				</tr>
				</tfoot>
				<tbody>
				<?php
				$altclass = '';

				//$counts = $this->get_meta_field_counts();

				if(!isset($this->options["metafields"])) {
					$this->options["metafields"] = array(); ?>
					<tr>
						<td colspan=4>No eligible meta fields found</td>
					</tr>
				<?php
				}

				foreach($this->options["metafields"] as $metafield => $value) {

					// If the taxonomy is active, set the 'checked' class
					if(!empty($value["enabled"])) {
						$checked = 'checked';
					} else {
						$checked = '';
						$this->options["metafields"][$metafield]["enabled"] = 0;
					}

					if(empty($value["autocomplete"])) {
						$this->options["metafields"][$metafield]["autocomplete"] = 0;
					}

					// Generate the list of terms for the "Count" tooltip
					/* $terms = get_terms($tax);
									$termcount = count($terms);
									$termstring = '';
									foreach ( $terms as $term ) {
										$termstring .= $term->name . ', ';
									} */
					if($this->is_active !== $this->hash) {
						$disabledtext = 'disabled="disabled"';
					}
					?>
					<tr>
						<th scope="row" class="tax <?php echo $altclass ?>"><span id="<?php echo $metafield.'-title' ?>" class="<?php echo $checked ?>"><?php echo $metafield ?>:<div class="VS-icon-cancel"></div></span>
						</th>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" <?php echo $disabledtext ?> type="checkbox" id="<?php echo $metafield ?>" name="wpus_options[metafields][<?php echo $metafield ?>][enabled]" value="1" <?php echo checked($this->options["metafields"][$metafield]["enabled"], 1, FALSE) ?> />
						</td>
						<td class="<?php echo $altclass ?>">
							<input class="" <?php echo $disabledtext ?> type="text" id="<?php echo $metafield ?>" name="wpus_options[metafields][<?php echo $metafield ?>][label]" size="20" placeholder="<?php echo $metafield ?>" value="<?php echo esc_attr($this->options["metafields"][$metafield]["label"]) ?>" />
						</td>
						<td class="<?php echo $altclass ?>"><?php echo $value["count"] ?></td>

						<?php /* commenting these fields out for now as they haven't been implemented yet
						
						<td class="<?php echo $altclass ?>"><select class="" id="<?php echo $metafield ?>" name="wpus_options['metafields'][<?php echo $metafield ?>][type']" />
							<option value="string" <?php echo selected($this->options["'metafields'"][$metafield]["'type'"], "string", FALSE) ?> >String</option>
							<option value="number" <?php echo selected($this->options["'metafields'"][$metafield]["'type'"], "number", FALSE) ?> >Number</option>
							<option value="date" <?php echo selected($this->options["'metafields'"][$metafield]["'type'"], "date", FALSE) ?> >Date</option>
							</select></td>
						<td class="<?php echo $altclass ?>">
							<input class="checkbox" <?php echo $disabledtext ?> type="checkbox" name="wpus_options['metafields'][<?php echo $metafield ?>][autocomplete']" value="1" <?php echo checked($this->options["'metafields'"][$metafield]["'autocomplete'"], 1, FALSE) ?> />
						</td>
						*/ ?>
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
		 * About section
		 *
		 *
		 */
		public function display_about_section() {
			?>
			<p>Developed by <a href="http://mind.sh/are/?ref=wpus">Mindshare Studios, Inc</a>. </p>
			<p>If you like what we do and want to show your support, consider <a href="http://mind.sh/are/donate/">making a donation</a>.</p>
			<p>Plugin page on <a href="http://wordpress.org/extend/plugins/<?= WPUS_PLUGIN_SLUG ?>/">WordPress.org</a></p>
			<p>Mindshare <a href="https://mindsharelabs.com/support/">Support Forum</a></p>

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

			if(!isset($this->options[$id]) && $type != 'checkbox') {
				$this->options[$id] = $std;
			} elseif(!isset($this->options[$id])) {
				$this->options[$id] = 0;
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

					echo '<input class="checkbox'.$field_class.'" type="checkbox" id="'.$id.'" name="wpus_options['.$id.']" value="1" '.checked($this->options[$id], 1, FALSE).' /> <label for="'.$id.'">'.$desc.'</label>';

					break;

				case 'select':
					echo '<select class="select'.$field_class.'" name="wpus_options['.$id.']">';

					foreach($choices as $value => $label) {
						echo '<option value="'.esc_attr($value).'"'.selected($this->options[$id], $value, FALSE).'>'.$label.'</option>';
					}

					echo '</select>';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'radio':
					$i = 0;
					foreach($choices as $value => $label) {
						echo '<input class="radio'.$field_class.'" type="radio" name="wpus_options['.$id.']" id="'.$id.$i.'" value="'.esc_attr($value).'" '.checked($this->options[$id], $value, FALSE).'> <label for="'.$id.$i.'">'.$label.'</label>';
						if($i < count($this->options) - 1) {
							echo '<br />';
						}
						$i++;
					}

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'textarea':
					echo '<textarea class="'.$field_class.'" id="'.$id.'" name="wpus_options['.$id.']" placeholder="'.$std.'" rows="5" cols="30">'.wp_htmledit_pre($this->options[$id]).'</textarea>';

					if($desc != '') {
						echo '<br /><span class="description">'.$desc.'</span>';
					}

					break;

				case 'password':
					echo '<input class="regular-text'.$field_class.'" type="password" id="'.$id.'" name="wpus_options['.$id.']" value="'.esc_attr($this->options[$id]).'" />';

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

					echo '<input class="regular-text'.$field_class.'"'.$disabledtxt.'type="text" id="'.$id.'" name="wpus_options['.$id.']" placeholder="'.$std.'" value="'.esc_attr($this->options[$id]).'" />';

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
		 * All settings in the $this->settings object wil be registered with add_settings_field. You can
		 * specify a settings section and default value.
		 *
		 */
		public function get_settings() {

			/* General Settings	 */

			$this->settings['email_address'] = array(
				'title'   => __('Email Address'),
				'desc'    => __(''),
				'std'     => "",
				'type'    => 'text',
				'section' => 'general'
			);

			if(!empty($this->options['license_key']) && !empty($this->options['email_address'])) {
				if($this->is_active === $this->hash && file_exists(WPUS_PRO_PATH.WPUS_PRO_FILE)) {
					$this->settings['license_key'] = array(
						'title'   => __('License Key'),
						'desc'    => __('Thanks for registering!'),
						'std'     => '',
						'type'    => 'password',
						'section' => 'general',
						'class'   => 'valid'
					);
				} elseif($this->is_active !== $this->hash) {
					$this->settings['license_key'] = array(
						'title'   => __('License Key'),
						'desc'    => __('<div id="message" class="error"><p>We were unable to validate your license key. Please try again, or contact support.</p></div>'),
						'std'     => "",
						'type'    => 'text',
						'section' => 'general',
						'class'   => 'invalid'
					);
				} elseif($this->is_active === $this->hash && !file_exists(WPUS_PRO_PATH.WPUS_PRO_FILE)) {
					$this->settings['license_key'] = array(
						'title'   => __('License Key'),
						'desc'    => __('<div id="message" class="error"><p>Your license key is valid but WP Ultimate Search Pro is not installed.<br />Re-enter your credentials and hit Validate to install.</p></div>'),
						'std'     => "",
						'type'    => 'text',
						'section' => 'general',
						'class'   => 'invalid'
					);
				}
			} else {
				$this->settings['license_key'] = array(
					'title'   => __('License Key'),
					'desc'    => __('Enter your license key to unlock the premium features.'),
					'std'     => "",
					'type'    => 'text',
					'section' => 'general'
				);
			}
			$this->settings['override_default'] = array(
				'section' => 'general',
				'title'   => __('Override default search box'),
				'desc'    => __('Select this to replace the default WordPress search for with an instance of WP Ultimate Search.<br /> Results will be shown at the page below.'),
				'type'    => 'checkbox',
				'std'     => 0
			);
			$this->settings['results_page'] = array(
				'title'   => __('Search Results Page'),
				'desc'    => __('Specify the URL to the page with the ['.WPUS_PLUGIN_SLUG.'-results] shortcode (requires permalinks)'),
				'std'     => "/search",
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
		 * If no options array is found, initialize everything to their default settings
		 *
		 */
		public function initialize_settings() {

			$this->options = array();
			foreach($this->settings as $id => $setting) {
				if(@$setting['type'] != 'heading') {
					@$this->options[$id] = @$setting['std'];
				}
			}

			// Set default meta field parameters.
			$this->update_meta_fields();

			// Set default taxonomy parameters
			$this->update_taxonomies();

			update_option('wpus_options', $this->options);
		}

		/**
		 *
		 * Register settings
		 *
		 * Set up the wpus_options object, register the different settings sections / pages, and register
		 * each of the individual settings.
		 *
		 */
		public function register_settings() {

			register_setting('wpus_options', 'wpus_options', array($this, 'validate_settings'));

			foreach($this->sections as $slug => $title) {
				if($slug == 'about') {
					add_settings_section($slug, $title, array(&$this, 'display_about_section'), 'wpus-options');
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
		 * so this function compares the POST data with the local $this->options array and sets the checkboxes to
		 * 0 where needed. Then merges $input with $this->options so the options *not* registered with add_settings_field
		 * still get passed through into the database.
		 *
		 * @param $input
		 *
		 * @return array|bool
		 */
		public function validate_settings($input) {

			if(!isset($input['reset_theme'])) {

				foreach($this->checkboxes as $id) {
					if(!isset($input[$id]) || $input[$id] != '1') {
						$input[$id] = 0;
					} else {
						$input[$id] = 1;
					}
				}
				$result = array_merge($this->options, $input);
				return $result;
			} else {
				return FALSE;
			}
		}

		/**
		 *
		 * Enqueue and print scripts
		 *
		 */
		public function scripts() {

			wp_enqueue_script('tiptip', WPUS_DIR_URL.'js/jquery.tipTip.minified.js', array('jquery'));
			wp_enqueue_script('main', WPUS_DIR_URL.'js/main-admin.js', array('jquery'));
			wp_localize_script('main', 'main', json_encode($this->sections));
			wp_enqueue_script('jquery-ui-tabs');

			wp_enqueue_style('wpus-admin', WPUS_DIR_URL.'css/wpus-options.css');
		}

		/**
		 *
		 * AJAX registration validation
		 *
		 */

		public function ajax_validate() {

			$updater = $this->updater;

			// @bryce these values weren't getting stored and we *need* them for pro's auto update to work right
			// if you want to handle this differently let me know
			update_option('wpus_k', $_POST['email']);
			update_option('wpus_e', $_POST['key']);

			// validate the license before proceeding.
			$result = $updater->get_remote_license($_POST['key'], $_POST['email']);

			if($result == $updater->hash) {

				// get the url to the purchased file
				$http_request_url = $updater->get_remote_url($_POST['key'], $_POST['email']);

				// install the remote file
				$result = $updater->do_remote_install($http_request_url, WP_PLUGIN_DIR);

				if($result == TRUE) {

					// if installed successfully, activate. set $result to either "true" or error message
					$result = $updater->maybe_activate_plugin();
				}
			}

			die($result); // return either success or error message to the script
		}
	} // END CLASS
endif;
