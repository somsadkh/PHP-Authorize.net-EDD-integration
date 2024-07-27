<?php

namespace EDD\Blocks\Pro\Search;

/**
 * Enqueues a small script to allow searching.
 *
 * @since 2.0.5
 * @return void
 */
function do_search() {
	if ( ! edd_is_pro() ) {
		return;
	}
	wp_enqueue_script( 'edd-blocks-search', EDD_BLOCKS_URL . 'assets/pro/search.js', array( 'jquery' ), EDD_VERSION, true );
	wp_localize_script( 'edd-blocks-search', 'EDDBlocksSearch', array( 'results' => __( 'downloads found', 'easy-digital-downloads' ) ) );
	include EDD_BLOCKS_DIR . 'views/search.php';
}
