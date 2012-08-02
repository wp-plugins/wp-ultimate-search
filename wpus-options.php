<?php

class WPUltimateSearchOptions extends WPUltimateSearch {
	
	private $sections;
	private $checkboxes;
	private $settings;
	
	function __construct() {

		// This will keep track of the checkbox options for the validate_settings function.
		$this->checkboxes = array();
		$this->setting = array();
		$this->get_settings();
		
		$this->sections['general'] = __( 'General Settings' );
		$this->sections['reset']        = __( 'Reset to Defaults' );
		$this->sections['about']        = __( 'About' );

		add_action('admin_menu', array($this, 'add_pages')); // adds page to menu
		add_action('admin_init', array($this, 'register_settings'));
		
		if ( ! get_option( 'wpus_options' ) )
					$this->initialize_settings();
		
	}
	
	/* Add page(s) to the admin menu */
	public function add_pages() {
		$admin_page = add_options_page('Ultimate Search', 'Ultimate Search', 'manage_options', 'wpus-options', array($this, 'display_page'));
	
		add_action( 'admin_print_scripts-' . $admin_page, array( &$this, 'scripts' ) );
		add_action( 'admin_print_styles-' . $admin_page, array( &$this, 'styles' ) );
	}

	/* Create settings field */
	public function create_setting( $args = array() ) {

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

		extract( wp_parse_args( $args, $defaults ) );

		$field_args = array(
			'type'      => $type,
			'id'        => $id,
			'desc'      => $desc,
			'std'       => $std,
			'choices'   => $choices,
			'label_for' => $id,
			'class'     => $class
		);

		if ( $type == 'checkbox' )
			$this->checkboxes[] = $id;

		add_settings_field( $id, $title, array( $this, 'display_setting' ), 'wpus-options', $section, $field_args );

	}

		/**
		 * Display options page
		 *
		 * @since 0.3
		 */
		public function display_page() {

			echo '<div class="wrap">
		<div class="icon32" id="icon-options-general"></div>
		<h2>' . __( 'WP Ultimate Search Options' ) . '</h2>';

			echo '<form action="options.php" method="post">';

			settings_fields( 'wpus_options' );
			echo '<div class="ui-tabs">
				<ul class="ui-tabs-nav">';

			foreach ( $this->sections as $section_slug => $section )
				echo '<li><a href="#' . $section_slug . '">' . $section . '</a></li>';

			echo '</ul>';
			do_settings_sections( $_GET['page'] );

			echo '</div>
			<p class="submit"><input name="Submit" type="submit" class="button-primary" value="' . __( 'Save Changes' ) . '" /></p>

		</form>';

		echo '<script type="text/javascript">
			jQuery(document).ready(function($) {
				var sections = [];';

				foreach ( $this->sections as $section_slug => $section )
					echo "sections['$section'] = '$section_slug';";

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
	 * Description for section
	 *
	 * @since 1.0
	 */
	public function display_section() {
		// code
	}

	/**
	 * Description for About section
	 *
	 * @since 1.0
	 */
	public function display_about_section() {

		// This displays on the "About" tab. Echo regular HTML here, like so:
		echo '<p>This happened in 2012.<br /><a href="http://www.brycecorkins.com/">Bryce</a> and <a href="http://www.damiantaggart.com/">Damian</a> were involved.
		<br />They work at <a href="http://mind.sh/are/">Mindshare Studios</a>. </p>
		<p><br />If you like what we do and want to show your support, consider <a href="http://mind.sh/are/donate/">making a donation</a>.</p>
		<br />
		<p>This plugin <a href="http://wordpress.org/extend/plugins/wp-ultimate-search/">on WordPress.org</a>.</p>
		<p>You can also <a href="http://wordpress.org/support/plugin/wp-ultimate-search/">get support</a>.</p>';

	}
		/**
		 * HTML output for text field
		 *
		 * @since 1.0
		 */
		public function display_setting( $args = array() ) {

			extract( $args );

			$options = get_option( 'wpus_options' );

			if ( ! isset( $options[$id] ) && $type != 'checkbox' )
				$options[$id] = $std;
			elseif ( ! isset( $options[$id] ) )
				$options[$id] = 0;

			$field_class = '';
			if ( $class != '' )
				$field_class = ' ' . $class;

			switch ( $type ) {

				case 'heading':
					echo '</td></tr><tr valign="top"><td colspan="2"><h4>' . $desc . '</h4>';
					break;

				case 'checkbox':

					echo '<input class="checkbox' . $field_class . '" type="checkbox" id="' . $id . '" name="wpus_options[' . $id . ']" value="1" ' . checked( $options[$id], 1, false ) . ' /> <label for="' . $id . '">' . $desc . '</label>';

					break;

				case 'select':
					echo '<select class="select' . $field_class . '" name="wpus_options[' . $id . ']">';

					foreach ( $choices as $value => $label )
						echo '<option value="' . esc_attr( $value ) . '"' . selected( $options[$id], $value, false ) . '>' . $label . '</option>';

					echo '</select>';

					if ( $desc != '' )
						echo '<br /><span class="description">' . $desc . '</span>';

					break;

				case 'radio':
					$i = 0;
					foreach ( $choices as $value => $label ) {
						echo '<input class="radio' . $field_class . '" type="radio" name="wpus_options[' . $id . ']" id="' . $id . $i . '" value="' . esc_attr( $value ) . '" ' . checked( $options[$id], $value, false ) . '> <label for="' . $id . $i . '">' . $label . '</label>';
						if ( $i < count( $options ) - 1 )
							echo '<br />';
						$i++;
					}

					if ( $desc != '' )
						echo '<br /><span class="description">' . $desc . '</span>';

					break;

				case 'textarea':
					echo '<textarea class="' . $field_class . '" id="' . $id . '" name="wpus_options[' . $id . ']" placeholder="' . $std . '" rows="5" cols="30">' . wp_htmledit_pre( $options[$id] ) . '</textarea>';

					if ( $desc != '' )
						echo '<br /><span class="description">' . $desc . '</span>';

					break;

				case 'password':
					echo '<input class="regular-text' . $field_class . '" type="password" id="' . $id . '" name="wpus_options[' . $id . ']" value="' . esc_attr( $options[$id] ) . '" />';

					if ( $desc != '' )
						echo '<br /><span class="description">' . $desc . '</span>';

					break;

				case 'text':
				default:
			 		echo '<input class="regular-text' . $field_class . '" type="text" id="' . $id . '" name="wpus_options[' . $id . ']" placeholder="' . $std . '" value="' . esc_attr( $options[$id] ) . '" />';

			 		if ( $desc != '' )
			 			echo '<br /><span class="description">' . $desc . '</span>';

			 		break;

			}

		}

		/**
		 * Settings and defaults
		 * 
		 * @since 1.0
		 */
		public function get_settings() {

			/* General Settings
			===========================================*/

			$this->settings['scripts_in_footer'] = array(
				'section' => 'general',
				'title'   => __( 'Load Scripts in Footer' ),
				'desc'    => __( 'If you encounter problems, try turning this off to load the scripts into the header instead.' ),
				'type'    => 'checkbox',
				'std'     => 1 // Set to 1 to be checked by default, 0 to be unchecked by default.
			);
			
			$this->settings['search_shortcodes'] = array(
				'section' => 'general',
				'title'   => __( 'Search Shortcode Content' ),
				'desc'    => __( 'Enable this option to evaluate shortcodes before searching. Will slow down searches slightly.' ),
				'type'    => 'checkbox',
				'std'     => 0 // Set to 1 to be checked by default, 0 to be unchecked by default.
			);

			$this->settings['analytics_heading'] = array(
				'section' => 'general',
				'title'   => '', // Not used for headings.
				'desc'    => 'Google Analytics',
				'type'    => 'heading'
			);
			
			$this->settings['track_events'] = array(
				'section' => 'general',
				'title'   => __( 'Track Events' ),
				'desc'    => __( 'Enabling this option will cause searches to appear as events in your Google Analytics reports<br /> (requires an Analytics tracking code to be already installed.)' ),
				'type'    => 'checkbox',
				'std'     => 0 // Set to 1 to be checked by default, 0 to be unchecked by default.
			);
			
			$this->settings['event_category'] = array(
				'title'   => __( 'Event Category' ),
				'desc'    => __( 'Set the category your events will appear under in reports.' ),
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

			$this->settings['example_radio'] = array(
				'section' => 'general',
				'title'   => __( 'Example Radio' ),
				'desc'    => __( 'This is a description for the radio buttons.' ),
				'type'    => 'radio',
				'std'     => '',
				'choices' => array(
					'choice1' => 'Choice 1',
					'choice2' => 'Choice 2',
					'choice3' => 'Choice 3'
				)
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
				'title'   => __( 'Reset theme' ),
				'type'    => 'checkbox',
				'std'     => 0,
				'class'   => 'warning', // Custom class for CSS
				'desc'    => __( 'Check this box and click "Save Changes" below to reset all options to their defaults.' )
			);

		}

		/**
		 * Initialize settings to their default values
		 * 
		 * @since 1.0
		 */
		public function initialize_settings() {

			$default_settings = array();
			foreach ( $this->settings as $id => $setting ) {
				if ( $setting['type'] != 'heading' )
					$default_settings[$id] = $setting['std'];
			}

			update_option( 'wpus_options', $default_settings );

		}

		/**
		* Register settings
		*
		* @since 1.0
		*/
		public function register_settings() {

			register_setting( 'wpus_options', 'wpus_options', array ( &$this, 'validate_settings' ) );

			foreach ( $this->sections as $slug => $title ) {
				if ( $slug == 'about' )
					add_settings_section( $slug, $title, array( &$this, 'display_about_section' ), 'wpus-options' );
				else
					add_settings_section( $slug, $title, array( &$this, 'display_section' ), 'wpus-options' );
			}

			$this->get_settings();

			foreach ( $this->settings as $id => $setting ) {
				$setting['id'] = $id;
				$this->create_setting( $setting );
			}

		}

		/**
		* jQuery Tabs
		*
		* @since 1.0
		*/
		public function scripts() {

			wp_print_scripts( 'jquery-ui-tabs' );

		}

		/**
		* Styling for the theme options page
		*
		* @since 1.0
		*/
		public function styles() {

			wp_register_style( 'wpus-admin', plugins_url('/wpus-options.css', __FILE__) );
			wp_enqueue_style( 'wpus-admin' );

		}

		/**
		* Validate settings
		*
		* @since 1.0
		*/
		public function validate_settings( $input ) {

			if ( ! isset( $input['reset_theme'] ) ) {
				$options = get_option( 'wpus_options' );

				foreach ( $this->checkboxes as $id ) {
					if ( isset( $options[$id] ) && ! isset( $input[$id] ) )
						unset( $options[$id] );
				}

				return $input;
			}
			return false;

		}

	} // END CLASS
$wp_ultimate_search_options = new WPUltimateSearchOptions();
?>
