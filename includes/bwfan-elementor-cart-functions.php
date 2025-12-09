<?php
/**
 * Elementor Cart Integration Helper Functions
 *
 * @package BWFAN_Elementor_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if WooCommerce is active
 *
 * @return bool
 */
if ( ! function_exists( 'bwfan_is_woocommerce_active' ) ) {
	function bwfan_is_woocommerce_active() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins ) || class_exists( 'WooCommerce' );
	}
}

/**
 * Check if FunnelKit Automations (base) is active
 *
 * @return bool
 */
if ( ! function_exists( 'bwfan_is_automations_active' ) ) {
	function bwfan_is_automations_active() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'wp-marketing-automations/wp-marketing-automations.php', $active_plugins, true ) || array_key_exists( 'wp-marketing-automations/wp-marketing-automations.php', $active_plugins ) || class_exists( 'BWFAN_Core' );
	}
}

/**
 * Check if FunnelKit Automations Pro is active
 *
 * @return bool
 */
if ( ! function_exists( 'bwfan_is_automations_pro_active' ) ) {
	function bwfan_is_automations_pro_active() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'wp-marketing-automations-pro/wp-marketing-automations-pro.php', $active_plugins, true ) || array_key_exists( 'wp-marketing-automations-pro/wp-marketing-automations-pro.php', $active_plugins ) || class_exists( 'BWFAN_Pro' );
	}
}

/**
 * Check if Elementor Pro is active
 *
 * @return bool
 */
if ( ! function_exists( 'bwfan_is_elementor_pro_active' ) ) {
	function bwfan_is_elementor_pro_active() {
		// Check multiple ways Elementor Pro might be detected
		if ( did_action( 'elementor_pro/loaded' ) ) {
			return true;
		}

		if ( class_exists( '\ElementorPro\Plugin' ) ) {
			return true;
		}

		// Check if Elementor Pro plugin is active
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		$elementor_pro_files = array(
			'elementor-pro/elementor-pro.php',
			'elementor-pro-pro/elementor-pro.php',
		);

		foreach ( $elementor_pro_files as $file ) {
			if ( in_array( $file, $active_plugins, true ) || array_key_exists( $file, $active_plugins ) ) {
				return true;
			}
		}

		// Check if Elementor Pro constants are defined
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return true;
		}

		return false;
	}
}

