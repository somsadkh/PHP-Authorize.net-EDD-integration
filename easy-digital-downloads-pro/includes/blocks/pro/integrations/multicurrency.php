<?php
/**
 * Handles blocks for EDD's Multi-Currency extension.
 */
namespace EDD\Blocks\Integrations\MultiCurrency;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use EDD\Blocks\Functions as Helpers;

add_action( 'init', __NAMESPACE__ . '\register' );
/**
 * Registers the currency selector block.
 *
 * @since 2.0.6
 * @return void
 */
function register() {
	$blocks = array(
		'currency-selector' => array(
			'render_callback' => __NAMESPACE__ . '\currency_selector',
		),
	);

	foreach ( $blocks as $block => $args ) {
		register_block_type( EDD_BLOCKS_DIR . 'build/pro/' . $block, $args );
	}
}

/**
 * Renders the currency selector block.
 *
 * @since 2.0.6
 * @param array $block_attributes
 * @return string
 */
function currency_selector( $block_attributes ) {
	$block_attributes = wp_parse_args(
		$block_attributes,
		array(
			'widget_type' => 'buttons',
		)
	);

	$classes = Helpers\get_block_classes(
		$block_attributes,
		array(
			'wp-block-edd-currency-selector',
			'edd-blocks__currency-selector',
		)
	);
	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', array_filter( $classes ) ) ); ?>">
		<?php
		$currency_selector = new \EDD_Multi_Currency\Widgets\CurrencySelector();
		$currency_selector->widget( array(), $block_attributes );
		?>
	</div>
	<?php

	return ob_get_clean();
}
