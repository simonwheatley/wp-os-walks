<?php 

// ======================================================================================
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 2.1 of the License, or (at your option) any later version.
// 
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
// ======================================================================================
// @author     Simon Wheatley (http://simonwheatley.co.uk)
// @version    1.0
// @copyright  Copyright &copy; 2010 Simon Wheatley, All Rights Reserved
// @copyright  Some parts Copyright &copy; 2007 John Godley, All Rights Reserved
// ======================================================================================
// 1.0     - Initial release
// 1.01    - Added add_shortcode
// 1.10    - Added code to allow the base class to be used in a theme
// 1.2     - Truncate helper method, admin notices/errors, throw error if not provided 
//           with name in setup method call, default $pluginfile to __FILE__, bugfix around 
//           option key in delete_option method.
// 1.3     - Locale stuff
//         - Fix for get_option
// 1.31    - Attempt to cope with Win32 directory separators
// 1.32    - Add a remove_filter method
// 1.33    - Add `sil_plugins_dir` and `sil_plugins_url` filters, to allow placement 
//           outside the `wp-content/plugins/` folder, for example using `require_once` 
//           to include from the theme `functions.php`.
// 1.4     - Add the 'simple_tax_meta_box' method
//         - Adapt setup to allow us to cope with symlinked plugins
//         - Add 'remove_filter' method
//         - Make all methods public
// 1.41    - Add `add_admin_menu_separator` method
// ======================================================================================


/**
 * Wraps up several useful functions for WordPress plugins and provides a method to separate
 * display HTML from PHP code.
 *
 * <h4>Display Rendering</h4>
 * 
 * The class uses a similar technique to Ruby On Rails views, whereby the display HTML is kept
 * in a separate directory and file from the main code.  A display is 'rendered' (sent to the browser)
 * or 'captured' (returned to the calling function).
 * 
 * Template files are separated into two areas: admin and user.  Admin templates are only for display in
 * the WordPress admin interface, while user templates are typically for display on the site (although neither
 * of these are enforced).  All templates are PHP code, but are referred to without .php extension.
 * 
 * The reason for this separation is that one golden rule of plugin creation is that someone will 
 * always want to change the formatting and style of your output.  Rather than forcing them to 
 * modify the plugin (bad), or modify files within the plugin (equally bad), the class allows 
 * user templates to be overridden with files contained within the theme.
 *
 * An additional benefit is that it leads to code re-use, especially with regards to Ajax (i.e. 
 * your display code can be called from many locations)
 * 
 * @package WordPress plugin base library
 * @author Simon Wheatley
 * @copyright Copyright (C) Code for the People Ltd (except where noted)
 **/
class CFTP_OS_Map_Walk_Plugin {

	/**
	 * The name of this plugin
	 *
	 * @var string
	 **/
	public $name;

	/**
	 * The filepath to the directory containing this plugin
	 *
	 * @var string
	 **/
	public $dir;

	/**
	 * The URL for the directory containing this plugin
	 *
	 * @var string
	 **/
	public $url;

	/**
	 * The name of the directory containing this plugin, e.g. "my-plugin"
	 *
	 * @var string
	 **/
	public $folder;
	
	/**
	 * Useful for switching between debug and compressed scripts.
	 *
	 * @var string
	 **/
	public $suffix;

	/**
	 * Records the type of this class, either 'plugin' or 'theme'.
	 *
	 * @var string
	 **/
	public $type;
	
	/**
	 * Note the name of the function to call when the theme is activated.
	 *
	 * @var string
	 **/
	public $theme_activation_function;

	/**
	 * Initiate!
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function setup( $name = '', $type = null ) {
		if ( ! $name )
			throw new exception( "Please pass the name parameter into the setup method." );
		$this->name = $name;

		// Attempt to handle a Windows
		$ds = ( defined( 'DIRECTORY_SEPARATOR' ) ) ? DIRECTORY_SEPARATOR : '\\';
		$file = str_replace( $ds, '/', __FILE__ );
		$plugins_dir = str_replace( $ds, '/', WP_PLUGIN_DIR );
		// Setup the dir and url for this plugin/theme
		if ( 'theme' == $type ) {
			// This is a theme
			$this->type = 'theme';
			$this->dir = get_stylesheet_directory();
			$this->url = get_stylesheet_directory_uri();
		} elseif ( stripos( $file, $plugins_dir ) !== false || 'plugin' == $type ) {
			// This is a plugin
			$this->folder = trim( basename( dirname( $file ) ), '/' );
			$this->type = 'plugin';
			// Allow someone to override the assumptions we're making here about where 
			// the plugin is held. For example, if this plugin is included as part of 
			// the files for a theme, in wp-content/themes/[your theme]/plugins/ then
			// you could hook `sil_plugins_dir` and `sil_plugins_url` to correct
			// our assumptions.
			// N.B. Because this code is running when the file is required, other plugins
			// may not be loaded and able to hook these filters!
			$plugins_dir = apply_filters( 'sil_plugins_dir', $plugins_dir, $this->name );
			$plugins_url = apply_filters( 'sil_plugins_url', plugins_url(), $this->name );
			$this->dir = trailingslashit( $plugins_dir ) . $this->folder . '/';
			$this->url = trailingslashit( $plugins_url ) . $this->folder . '/';
		} else {
			// WTF?
			error_log( 'PLUGIN/THEME ERROR: Cannot find ' . $plugin_dir . ' or "themes" in ' . $file );
		}

		// Suffix for enqueuing
		$this->suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		
		if ( is_admin() ) {
			// Admin notices
			$this->add_action( 'admin_notices', '_admin_notices' );
		}

		$this->add_action( 'init', 'load_locale' );
	}

	/**
	 * Hook called to change the locale directory.
	 * 
	 * @return void
	 * @author © John Godley
	 **/
	public function load_locale() {
		// Here we manually fudge the plugin locale as WP doesnt allow many options
		$locale = get_locale();
		if( empty( $locale ) )
			$locale = 'en_US';

		$mofile = $this->dir( "/locale/$locale.mo" );
		load_textdomain( $this->name, $mofile );
	}
	
	/**
	 * Register a WordPress action and map it back to the calling object
	 *
	 * @param string $action Name of the action
	 * @param string $function Function name (optional)
	 * @param int $priority WordPress priority (optional)
	 * @param int $accepted_args Number of arguments the function accepts (optional)
	 * @return void
	 * @author © John Godley
	 **/
	public function add_action ($action, $function = '', $priority = 10, $accepted_args = 1) {
		if ( $priority === null )
			$priority = 10;
		add_action ($action, array (&$this, $function == '' ? $action : $function), $priority, $accepted_args);
	}


	/**
	 * Register a WordPress filter and map it back to the calling object
	 *
	 * @param string $action Name of the action
	 * @param string $function Function name (optional)
	 * @param int $priority WordPress priority (optional)
	 * @param int $accepted_args Number of arguments the function accepts (optional)
	 * @return void
	 * @author © John Godley
	 **/
	public function add_filter ($filter, $function = '', $priority = 10, $accepted_args = 1) {
		add_filter ($filter, array (&$this, $function == '' ? $filter : $function), $priority, $accepted_args);
	}


	/**
	 * De-register a WordPress filter and map it back to the calling object
	 *
	 * @param string $action Name of the action
	 * @param string $function Function name (optional)
	 * @param int $priority WordPress priority (optional)
	 * @param int $accepted_args Number of arguments the function accepts (optional)
	 * @return void
	 * @author © John Godley
	 **/
	public function remove_filter ($filter, $function = '', $priority = 10, $accepted_args = 1) {
		remove_filter ($filter, array (&$this, $function == '' ? $filter : $function), $priority, $accepted_args);
	}


	/**
	 * Special activation function that takes into account the plugin directory
	 *
	 * @param string $pluginfile The plugin file location (i.e. __FILE__)
	 * @param string $function Optional function name, or default to 'activate'
	 * @return void
	 * @author © John Godley
	 **/
	public function register_activation ( $pluginfile = __FILE__, $function = '' ) {
		if ( $this->type == 'plugin' ) {
			add_action ('activate_'.basename (dirname ($pluginfile)).'/'.basename ($pluginfile), array (&$this, $function == '' ? 'activate' : $function));
		} elseif ( $this->type == 'theme' ) {
			$this->theme_activation_function = ( $function ) ? $function : 'activate';
			add_action ('load-themes.php', array ( & $this, 'theme_activation' ) );
		}
	}
	
	/**
	 * Hack to catch theme activation. We hook the load-themes.php action, look for the
	 * "activated" GET param and make a big fat assumption if we find it.
	 * 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function theme_activation() {
		$activated = (bool) @ $_GET[ 'activated' ];
		if ( ! $activated )
			return;
		if ( ! $this->theme_activation_function )
			return;
		// Looks like the theme might just have been activated, call the registered function
		$this->{$this->theme_activation_function}();
	}
	
	/**
	 * Special deactivation function that takes into account the plugin directory
	 *
	 * @param string $pluginfile The plugin file location (i.e. __FILE__)
	 * @param string $function Optional function name, or default to 'deactivate'
	 * @return void
	 * @author © John Godley
	 **/
	public function register_deactivation ($pluginfile, $function = '') {
		add_action ('deactivate_'.basename (dirname ($pluginfile)).'/'.basename ($pluginfile), array(&$this, $function == '' ? 'deactivate' : $function));
	}

	/**
	 * Renders a template, looking first for the template file in the theme directory
	 * and afterwards in this plugin's /theme/ directory.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function render( $template_file, $vars = null ) {
		// Maybe override the template with our own file
		$template_file = $this->locate_template( $template_file );
		// Ensure we have the same vars as regular WP templates
		global $posts, $post, $wp_did_header, $wp_did_template_redirect, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID;

		if ( is_array($wp_query->query_vars) )
			extract($wp_query->query_vars, EXTR_SKIP);

		// Plus our specific template vars
		if ( is_array( $vars ) )
			extract( $vars );
		
		require( $template_file );
	}

	/**
	 * Renders an admin template from this plugin's /templates-admin/ directory.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function render_admin( $template_file, $vars = null, $references = false ) {
		// Plus our specific template vars
		if ( is_array( $vars ) ) {
			if ( $references )
				extract( $vars, EXTR_REFS );
			else
				extract( $vars );
		}

		// Try to render
		if ( file_exists( $this->dir( "templates-admin/$template_file" ) ) ) {
			require( $this->dir( "templates-admin/$template_file" ) );
		} else {
			$msg = sprintf( __( "This plugin admin template could not be found: %s" ), $this->dir( "templates-admin/$template_file" ) );
			error_log( "Plugin template error: $msg" );
			echo "<p style='background-color: #ffa; border: 1px solid red; color: #300; padding: 10px;'>$msg</p>";
		}
	}
	
	/**
	 * Returns a section of user display code, returning the rendered markup.
	 *
	 * @param string $ug_name Name of the admin file (without extension)
	 * @param string $array Array of variable name=>value that is available to the display code (optional)
	 * @return void
	 * @author © John Godley
	 **/
	public function capture( $template_file, $vars = null ) {
		ob_start();
		$this->render( $template_file, $vars );
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	/**
	 * Returns a section of user display code, returning the rendered markup.
	 *
	 * @param string $ug_name Name of the admin file (without extension)
	 * @param string $array Array of variable name=>value that is available to the display code (optional)
	 * @return void
	 * @author © John Godley
	 **/
	public function capture_admin( $template_file, $vars = null ) {
		ob_start();
		$this->render_admin( $template_file, $vars );
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	/**
	 * Hooks the WP admin_notices action to render any notices
	 * that have been set with the set_admin_notice method.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function _admin_notices() {
		$notices = $this->get_option( 'admin_notices' );
		$errors = $this->get_option( 'admin_errors' );
		if ( $errors )
			foreach ( $errors as $error )
				$this->render_admin_error( $error );
		$this->delete_option( 'admin_errors' );
		if ( $notices )
			foreach ( $notices as $notice )
				$this->render_admin_notice( $notice );
		$this->delete_option( 'admin_notices' );
	}
	
	/**
	 * Echoes some HTML for an admin notice.
	 *
	 * @param string $notice The notice 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function render_admin_notice( $notice ) {
		echo "<div class='updated'><p>$notice</p></div>";
	}
	
	/**
	 * Echoes some HTML for an admin error.
	 *
	 * @param string $error The error 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function render_admin_error( $error ) {
		echo "<div class='error'><p>$error</p></div>";
	}
	
	/**
	 * Sets a string as an admin notice.
	 *
	 * @param string $msg A *localised* admin notice message 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function set_admin_notice( $msg ) {
		$notices = (array) $this->get_option( 'admin_notices' );
		$notices[] = $msg;
		$this->update_option( 'admin_notices', $notices );
	}
	
	/**
	 * Sets a string as an admin error.
	 *
	 * @param string $msg A *localised* admin error message 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function set_admin_error( $msg ) {
		$errors = (array) $this->get_option( 'admin_errors' );
		$errors[] = $msg;
		$this->update_option( 'admin_errors', $errors );
	}
	
	/**
	 * Takes a filename and attempts to find that in the designated plugin templates
	 * folder in the theme (defaults to main theme directory, but uses a custom filter
	 * to allow theme devs to specify a sub-folder for all plugin template files using
	 * this system).
	 * 
	 * Searches in the STYLESHEETPATH before TEMPLATEPATH to cope with themes which
	 * inherit from a parent theme by just overloading one file.
	 *
	 * @param string $template_file A template filename to search for 
	 * @return string The path to the template file to use
	 * @author Simon Wheatley
	 **/
	public function locate_template( $template_file ) {
		$located = '';
		$sub_dir = apply_filters( 'sw_plugin_tpl_dir', '' );
		if ( $sub_dir )
			$sub_dir = trailingslashit( $sub_dir );
		// If there's a tpl in a (child theme or theme with no child)
		if ( file_exists( STYLESHEETPATH . "/$sub_dir" . $template_file ) )
			return STYLESHEETPATH . "/$sub_dir" . $template_file;
		// If there's a tpl in the parent of the current child theme
		else if ( file_exists( TEMPLATEPATH . "/$sub_dir" . $template_file ) )
			return TEMPLATEPATH . "/$sub_dir" . $template_file;
		// Fall back on the bundled plugin template (N.B. no filtered subfolder involved)
		else if ( file_exists( $this->dir( "templates/$template_file" ) ) )
			return $this->dir( "templates/$template_file" );
		// Oh dear. We can't find the template.
		$msg = sprintf( __( "This plugin template could not be found, perhaps you need to hook `sil_plugins_dir` and `sil_plugins_url`: %s" ), $this->dir( "templates/$template_file" ) );
		error_log( "Template error: $msg" );
		echo "<p style='background-color: #ffa; border: 1px solid red; color: #300; padding: 10px;'>$msg</p>";
	}
	
	/**
	 * Register a WordPress meta box
	 *
	 * @param string $id ID for the box, also used as a function name if none is given
	 * @param string $title Title for the box
	 * @param int $page The type of edit page on which to show the box (post, page, link).
	 * @param string $function Function name (optional)
	 * @param string $context e.g. 'advanced' or 'core' (optional)
	 * @param int $priority Priority, rough effect on the ordering (optional)
	 * @param mixed $args Some arguments to pass to the callback function as part of a larger object (optional)
	 * @return void
	 * @author © John Godley
	 **/
	public function add_meta_box( $id, $title, $function = '', $page, $context = 'advanced', $priority = 'default', $args = null )
	{
		require_once( ABSPATH . 'wp-admin/includes/template.php' );
		add_meta_box( $id, $title, array( &$this, $function == '' ? $id : $function ), $page, $context, $priority, $args );
	}

	/**
	 * Add hook for shortcode tag.
	 *
	 * There can only be one hook for each shortcode. Which means that if another
	 * plugin has a similar shortcode, it will override yours or yours will override
	 * theirs depending on which order the plugins are included and/or ran.
	 *
	 * @param string $tag Shortcode tag to be searched in post content.
	 * @param callable $func Hook to run when shortcode is found.
	 */
	public function add_shortcode( $tag, $function = null ) {
		add_shortcode( $tag, array( &$this, $function == '' ? $tag : $function ) );
	}

	/**
	 * Adds in a menu separator.
	 *
	 * @param string $position The position you want the menu separator added at
	 * @return void
	 * @author Mike Schinkel: http://wordpress.stackexchange.com/a/2674
	 */
	public function add_admin_menu_separator( $position ) {
		global $menu;
		$index = 0;
		foreach( $menu as $offset => $section ) {
			if ( substr( $section[2], 0, 9 ) == 'separator' )
				$index++;
			if ( $offset >= $position ) {
				$menu[ $position ] = array( '', 'read', "separator{$index}", '', 'wp-menu-separator' );
				break;
			}
		}
		ksort( $menu );
	}

	/**
	 * Returns the filesystem path for a file/dir within this plugin.
	 *
	 * @param $path string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Filesystem path
	 * @author Simon Wheatley
	 **/
	public function dir( $path ) {
		return trailingslashit( $this->dir ) . trim( $path, '/' );
	}

	/**
	 * Returns the URL for for a file/dir within this plugin.
	 *
	 * @param $path string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string URL
	 * @author Simon Wheatley
	 **/
	public function url( $path = '' ) {
		return esc_url( trailingslashit( $this->url ) . trim( $path, '/' ) );
	}
	
	/**
	 * Gets the value of an option named as per this plugin.
	 *
	 * @return mixed Whatever 
	 * @author Simon Wheatley
	 **/
	public function get_all_options() {
		return get_option( $this->name );
	}
	
	/**
	 * Sets the value of an option named as per this plugin.
	 *
	 * @return mixed Whatever 
	 * @author Simon Wheatley
	 **/
	public function update_all_options( $value ) {
		return update_option( $this->name, $value );
	}
	
	/**
	 * Gets the value from an array index on an option named as per this plugin.
	 *
	 * @param string $key A string 
	 * @return mixed Whatever 
	 * @author Simon Wheatley
	 **/
	public function get_option( $key, $value = null ) {
		$option = get_option( $this->name );
		if ( ! is_array( $option ) || ! isset( $option[ $key ] ) )
			return $value;
		return $option[ $key ];
	}
	
	/**
	 * Sets the value on an array index on an option named as per this plugin.
	 *
	 * @param string $key A string 
	 * @param mixed $value Whatever
	 * @return bool False if option was not updated and true if option was updated.
	 * @author Simon Wheatley
	 **/
	public function update_option( $key, $value ) {
		$option = get_option( $this->name );
		$option[ $key ] = $value;
		return update_option( $this->name, $option );
	}
	
	/**
	 * Deletes the array index on an option named as per this plugin.
	 *
	 * @param string $key A string 
	 * @return bool False if option was not updated and true if option was updated.
	 * @author Simon Wheatley
	 **/
	public function delete_option( $key ) {
		$option = get_option( $this->name );
		if ( isset( $option[ $key ] ) )
			unset( $option[ $key ] );
		return update_option( $this->name, $option );
	}
	
	/**
	 * Echoes out some JSON indicating that stuff has gone wrong.
	 *
	 * @param string $msg The error message 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function ajax_die( $msg ) {
		$data = array( 'msg' => $msg, 'success' => false );
		echo json_encode( $data );
		// N.B. No 500 header
		exit;
	}
	
	/**
	 * Truncates a string in a human friendly way.
	 *
	 * @param string $str The string to truncate 
	 * @param int $num_words The number of words to truncate to
	 * @return string The truncated string
	 * @author Simon Wheatley
	 **/
	public function truncate( $str, $num_words )
	{
		$str = strip_tags( $str );
		$words = explode(' ', $str );
		if ( count( $words ) > $num_words) {
			$k = $num_words;
			$use_dotdotdot = 1;
		} else {
			$k = count( $words );
			$use_dotdotdot = 0;
		}
		$words  = array_slice( $words, 0, $k );
		$excerpt = trim( join( ' ', $words ) );
		$excerpt .= ($use_dotdotdot) ? '…' : '';
		return $excerpt;
	}
	
	/**
	 * Echoes the HTML for a simple taxonomy metabox.
	 *
	 * @param object $post The WP Post object
	 * @param array $box The WP Metabox information
	 * @param string $taxonomy The taxonomy name
	 * @param string $type Either 'checkboxes' or 'radios'
	 * @return void
	 **/
	public function simple_tax_meta_box( $post, $box, $taxonomy, $type = 'checkboxes' ) {
		echo $this->get_simple_tax_ui( $post, $box, $taxonomy, $type );
	}
	
	/**
	 * Returns the HTML for a simple taxonomy metabox.
	 *
	 * @param object $post The WP Post object
	 * @param array $box The WP Metabox information
	 * @param string $taxonomy The taxonomy name
	 * @param string $type Either 'checkboxes', 'radios', 'select' or 'multiselect
	 * @param bool $for_attachments Structures the field names like "tax_input[post_id][taxonomy]" if true, otherwise like "tax_input[taxonomy]" if false
	 * @return void
	 **/
	public function get_simple_tax_ui( $post, $box, $taxonomy, $type = 'checkboxes', $for_attachments = false ) {
		$tax_obj = get_taxonomy( $taxonomy );
		$terms = get_terms( $taxonomy, array( 'hide_empty' => false ) );
		$current_terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current_terms ) )
			throw new exception( $current_terms->get_error_message() );
		$current_terms = array_map( 'absint', $current_terms );

		$name = $for_attachments ? 'name="attachments[' . $post->ID . '][tax_input][' . esc_attr( $taxonomy ) . '][]"' : 'name="tax_input[' . esc_attr( $taxonomy ) . '][]"';

		$html  = '';
		$html .= '<div class="cftp-' . esc_attr( $taxonomy ) . '-select">';
		$html .= '<input type="hidden" name="cftp_simple_tax_ui[]" value="' . esc_attr( $taxonomy ) . '" id="cftp_simple_taxonomy[]">';
		$html .= '<input type="hidden" ' . $name . ' value="0" />';
		if ( 'checkboxes' == $type || 'radios' == $type ) {
			$input_type = ( 'checkboxes' == $type ) ? 'checkbox' : 'radio';
			foreach ( $terms as $term ) {
				$checked = in_array( $term->term_id, $current_terms ) ? ' checked="checked" ' : '';
				$value = $tax_obj->hierarchical ? esc_attr( $term->term_id ): esc_attr( $term->name );
				if ( 'radio' == $type && ! $current_terms )
					$checked = ( $terms[ 0 ]->term_id == $term->term_id );
				$html .= '<input type="' . esc_attr( $input_type ) . '" ' . $name . ' class="' . esc_attr( $taxonomy ) . '" id="' . esc_attr( $taxonomy ) . '-' . esc_attr( $term->slug ) . '" value="' . $value . '" ' . $checked . ' /> ';
				$html .= '<label for="' . esc_attr( $taxonomy ) . '-' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</label><br />';
			}
		} else if ( 'select' == $type || 'multiselect' == $type ) {
			// $id = attachments[1030][releases]
			$id = $for_attachments ? 'attachments[' . esc_attr( $post->ID ) . '][' .  esc_attr( $taxonomy ). ']' : 'tax_input_' . esc_attr( $taxonomy ) . '_' . esc_attr( $post->ID );
			$multiple = ( 'multiselect' == $type ) ? ' multiple="multiple" ' : '';
			$select_type = ( 'multiselect' == $type ) ? 'multi' : 'single';
			$html .= '<select ' . $name . ' id="' . $id . '" ' . $multiple . ' class="cftp-select">';
			foreach ( $terms as $term ) {
				$value = $tax_obj->hierarchical ? esc_attr( $term->term_id ): esc_attr( $term->name );
				$selected = in_array( $term->term_id, $current_terms ) ? ' selected="selected" ' : '';
				// @TODO Complete this SELECT/MULTISELECT option!
				$html .= '<option value="' . $value . '" ' . $selected . '>';
				$html .= esc_html( $term->name );
				$html .= '</option>';
			}
			$html .= '</select>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Get a remote image, load it into WordPress as an attachment 
	 * attached to the specified Post ID.
	 *
	 * @param string $file The URL of the thumbnail image to retrieve
	 * @param int $post_id The ID of the post to attach the image to 
	 * @param string $desc The description to attach to the retrieved image
	 * @return int The ID of the WP attachment Post object we've just loaded
	 **/
	public function sideload_image( $file, $post_id, $desc ) {
		// START Direct copy/paste from media_sideload_image()
		// Download file to temp location
		$tmp = download_url( $file );

		// Set variables for storage
		// fix file filename for query strings
		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $file, $matches);
		$file_array['name'] = basename($matches[0]);
		$file_array['tmp_name'] = $tmp;

		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
		}

		// do the validation, attachment and storage stuff
		$id = media_handle_sideload( $file_array, $post_id, $desc );
		// If error storing permanently, unlink
		if ( is_wp_error($id) ) {
			@unlink($file_array['tmp_name']);
			return $id;
		}
		// END Direct copy/paste from media_sideload_image()
		return $id;
	}

} // END CFTP_OS_Map_Walk_Plugin class 

