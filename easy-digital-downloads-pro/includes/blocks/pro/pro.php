<?php
/**
 * Pro features for Blocks.
 */

namespace EDD\Blocks\Pro;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Initialize the Pro features.
 *
 * @since 2.0.6
 * @return void
 */
function init() {
	$pro_files = array(
		'search',
	);

	$files_to_require = array();
	foreach ( $pro_files as $file ) {
		$files_to_require[] = trailingslashit( EDD_BLOCKS_DIR . 'pro' ) . $file . '.php';
	}

	$integrations = array(
		'eddMultiCurrency' => 'multicurrency',
	);
	foreach ( $integrations as $function_exists => $file ) {
		if ( function_exists( $function_exists ) ) {
			$files_to_require[] = trailingslashit( EDD_BLOCKS_DIR . 'pro/integrations' ) . $file . '.php';
		}
	}

	foreach ( $files_to_require as $file ) {
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

/**
 * Register the Pro blocks.
 *
 * @since 3.2.10
 * @param array $edd_blocks The registered blocks.
 * @return array
 */
function register_pro_blocks( $edd_blocks ) {
	return array_merge( $edd_blocks, get_pro_blocks() );
}
add_filter( 'edd_registered_blocks', __NAMESPACE__ . '\register_pro_blocks' );

/**
 * Gets the Pro blocks.
 *
 * @since 3.2.10
 * @return array
 */
function get_pro_blocks() {
	if ( ! is_dir( EDD_BLOCKS_DIR . 'build/pro' ) ) {
		return array();
	}
	$cached_blocks = wp_cache_get( 'edd-registered-pro-blocks', 'edd-blocks' );
	if ( false !== $cached_blocks ) {
		return $cached_blocks;
	}
	$edd_blocks = array();
	$pro_blocks = glob( EDD_BLOCKS_DIR . 'build/pro/**', GLOB_ONLYDIR | GLOB_NOSORT );
	foreach ( $pro_blocks as $block_path ) {
		$block_name   = explode( '/', $block_path );
		$block_name   = end( $block_name );
		$edd_blocks[] = "edd/{$block_name}";
	}
	wp_cache_set( 'edd-registered-pro-blocks', $edd_blocks, 'edd-blocks', 5 * MINUTE_IN_SECONDS );

	return $edd_blocks;
}

/**
 * Add styles for Pro blocks.
 *
 * @since 3.2.10
 * @param string $url        The URL for the stylesheet.
 * @param string $block_name The block name.
 * @return string
 */
function add_styles( $url, $block_name ) {
	$pro_blocks = get_pro_blocks();
	if ( ! in_array( "edd/$block_name", $pro_blocks, true ) ) {
		return $url;
	}

	return EDD_BLOCKS_URL . "build/pro/{$block_name}/style-index.css";
}
add_filter( 'edd_block_style_uri', __NAMESPACE__ . '\add_styles', 10, 2 );
