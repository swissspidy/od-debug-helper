<?php
/**
 * Plugin Name: Optimization Detective Debug Helper
 * Plugin URI: https://github.com/swissspidy/od-debug-helper
 * Description: Makes Optimization Detective data available on the front end through the admin bar.
 * Requires at least: 6.7
 * Requires PHP: 7.2
 * Requires Plugins: od-debug-helper
 * Version: 0.1.0
 * Author: Pascal Birchler
 * Author URI: https://pascalbirchler.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: od-debug-helper
 * Domain Path: /languages
 * Update URI: https://github.com/swissspidy/od-debug-helper
 * GitHub Plugin URI: https://github.com/swissspidy/od-debug-helper
 *
 * @package od-debug-helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action(
  'od_init',
  static function (): void {
	  add_filter( 'od_use_web_vitals_attribution_build', '__return_true' );
	  add_action( 'od_register_tag_visitors', 'od_debug_register_tag_visitors', PHP_INT_MAX );
	  add_filter( 'od_extension_module_urls', 'od_debug_filter_extension_module_urls' );
	  add_filter( 'od_url_metric_schema_root_additional_properties', 'od_debug_add_inp_schema_properties' );
	  add_action( 'admin_bar_menu', 'od_debug_add_admin_bar_menu_item', 100 );
	  add_action( 'wp_footer', 'od_debug_add_assets' );
  }
);

/**
 * Filters the list of Optimization Detective extension module URLs.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string[]|mixed $extension_module_urls Extension module URLs.
 * @return string[] Extension module URLs.
 */
function od_debug_filter_extension_module_urls( $extension_module_urls ): array {
	if ( ! is_array( $extension_module_urls ) ) {
		$extension_module_urls = array();
	}
	$extension_module_urls[] = plugins_url( add_query_arg( 'ver', '0.1.0', 'detect.js' ), __FILE__ );
	return $extension_module_urls;
}

/**
 * Registers tag visitors.
 *
 * @since 0.1.0
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function od_debug_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}

	if ( ! is_admin_bar_showing() ) {
		return;
	}

	require_once __DIR__ . '/class-optimization-detective-debug-helper-tag-visitor.php';

	$debug_visitor = new Optimization_Detective_Debug_Helper_Tag_Visitor();
	$registry->register( 'od-debug-helper', $debug_visitor );
}

/**
 * Filters additional properties for the element item schema for Optimization Detective.
 *
 * @since 0.1.0
 *
 * @param array<string, array{type: string}> $additional_properties Additional properties.
 * @return array<string, array{type: string}> Additional properties.
 */
function od_debug_add_inp_schema_properties( array $additional_properties ): array {
	$additional_properties['inpData'] = array(
	  'description' => __( 'INP metrics', 'optimization-detective' ),
	  'type'        => 'array',

		/*
		 * All extended properties must be optional so that URL Metrics are not all immediately invalidated once an extension is deactivated.
		 * Also, no INP data will be sent if the user never interacted with the page.
		 */
	  'required'    => false,
	  'items'       => array(
		'type'                 => 'object',
		'required'             => true,
		'properties'           => array(
		  'value'             => array(
			'type'     => 'number',
			'required' => true,
		  ),
		  'rating'            => array(
			'type'     => 'string',
			'enum'     => array( 'good', 'needs-improvement', 'poor' ),
			'required' => true,
		  ),
		  'interactionTarget' => array(
			'type'     => 'string',
			'required' => true,
		  ),
		),
		'additionalProperties' => true,
	  ),
	);
	return $additional_properties;
}

/**
 * Adds a new admin bar menu item for Optimization Detective debug mode.
 *
 * @since 0.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance, passed by reference.
 */
function od_debug_add_admin_bar_menu_item( WP_Admin_Bar &$wp_admin_bar ): void {
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}

	if ( is_admin() ) {
		return;
	}

	$wp_admin_bar->add_menu(
	  array(
		'id'     => 'optimization-detective-debug',
		'parent' => null,
		'group'  => null,
		'title'  => __( 'Optimization Detective', 'optimization-detective' ),
		'meta'   => array(
		  'onclick' => 'document.body.classList.toggle("od-debug");',
		),
	  )
	);
}

/**
 * Adds inline JS & CSS for debugging.
 */
function od_debug_add_assets(): void {
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}

	if ( ! is_admin_bar_showing() ) {
		return;
	}
	?>
	<style>
        body:not(.od-debug) .od-debug-dot,
        body:not(.od-debug) .od-debug-popover {
            display: none;
        }

        .od-debug-dot {
            height: 2em;
            width: 2em;
            background: rebeccapurple;
            border: 0;
            border-radius: 50%;
            animation: pulse 2s infinite;
            position: absolute;
            position-area: center center;
            margin: 5px 0 0 5px;
        }

        .od-debug-popover {
            position: absolute;
            position-area: top;
            bottom: anchor-size(height);
            margin: 0;
            padding: .25em .5em;
            border: none;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.8);
                opacity: 0.5;
                box-shadow: 0 0 0 0 rgba(102, 51, 153, 0.7);
            }
            70% {
                transform: scale(1);
                opacity: 1;
                box-shadow: 0 0 0 10px rgba(102, 51, 153, 0);
            }
            100% {
                transform: scale(0.8);
                opacity: 0.5;
                box-shadow: 0 0 0 0 rgba(102, 51, 153, 0);
            }
        }
	</style>
	<?php
}
