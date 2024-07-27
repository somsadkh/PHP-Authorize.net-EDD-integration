<?php
/**
 * Plugin Name: Easy Digital Downloads - Authorize.net Gateway
 * Plugin URI: https://easydigitaldownloads.com/downloads/authorize-net-gateway/
 * Description: Adds a payment gateway for Authorize.net.
 * Version: 2.0.5
 * Requires PHP: 7.2
 * Requires at least: 4.9
 * Author: Easy Digital Downloads
 * Author URI: https://easydigitaldownloads.com
 * Textdomain: edda
 * Contributors: mordauk, easydigitaldownloads
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The main plugin requirements checker
 *
 * @since 1.0
 */
final class EDD_Authorize_Net_Requirements_Check {

	/**
	 * Plugin file
	 *
	 * @since 1.0
	 * @var string
	 */
	private $file = '';

	/**
	 * Plugin basename
	 *
	 * @since 1.0
	 * @var string
	 */
	private $base = '';

	/**
	 * Requirements array
	 *
	 * @deprecated 2.0.4
	 * @var array
	 * @since 1.0
	 */
	private $requirements = array();

	/**
	 * Setup plugin requirements
	 *
	 * @since 1.0
	 */
	public function __construct() {

		// Setup file & base
		$this->setup_constants();
		$this->file = __FILE__;
		$this->base = plugin_basename( $this->file );

		// Always load translations
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Load or quit
		$this->load();
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 * @since 1.0.0
	 * @return void
	 */
	private function setup_constants() {

		// Plugin version.
		if ( ! defined( 'EDDA_VERSION' ) ) {
			define( 'EDDA_VERSION', '2.0.5' );
		}

		// Plugin Root File.
		if ( ! defined( 'EDDA_PLUGIN_FILE' ) ) {
			define( 'EDDA_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name.
		if ( ! defined( 'EDDA_PLUGIN_BASE' ) ) {
			define( 'EDDA_PLUGIN_BASE', plugin_basename( EDDA_PLUGIN_FILE ) );
		}

		// Plugin Folder Path.
		if ( ! defined( 'EDDA_PLUGIN_DIR' ) ) {
			define( 'EDDA_PLUGIN_DIR', plugin_dir_path( EDDA_PLUGIN_FILE ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'EDDA_PLUGIN_URL' ) ) {
			define( 'EDDA_PLUGIN_URL', plugin_dir_url( EDDA_PLUGIN_FILE ) );
		}
	}

	/** Specific Methods ******************************************************/

	/**
	 * Load normally
	 *
	 * @since 1.0
	 */
	private function load() {

		// Maybe include the bundled bootstrapper
		if ( ! class_exists( 'Easy_Digital_Downloads_Authorize_Net' ) ) {
			require_once dirname( $this->file ) . '/init.php';
		}

		// Maybe hook-in the bootstrapper
		if ( class_exists( 'Easy_Digital_Downloads_Authorize_Net' ) ) {

			// Bootstrap to plugins_loaded before priority 101 because Recurring Payments waits until 100
			add_action( 'plugins_loaded', array( $this, 'bootstrap' ), 101 );

			// Register the activation hook
			register_activation_hook( $this->file, array( $this, 'install' ) );
		}
	}

	/**
	 * Install, usually on an activation hook.
	 *
	 * @since 1.0
	 */
	public function install() {

		// Bootstrap to include all of the necessary files
		$this->bootstrap();

		// Network wide?
		$network_wide = ! empty( $_GET['networkwide'] )
			? (bool) $_GET['networkwide']
			: false;

		// Call the installer directly during the activation hook
		edd_install( $network_wide );
	}

	/**
	 * Bootstrap everything.
	 *
	 * @since 1.0
	 */
	public function bootstrap() {
		Easy_Digital_Downloads_Authorize_Net::instance( $this->file );
	}

	/** Translations **********************************************************/

	/**
	 * Plugin specific text-domain loader.
	 *
	 * @since 1.4
	 * @return void
	 */
	public function load_textdomain() {

		/*
		 * Due to the introduction of language packs through translate.wordpress.org,
		 * loading our textdomain is complex.
		 *
		 *
		 * We must look for translation files in several places and under several names.
		 *
		 * - wp-content/languages/plugins/edd-authorize-net (introduced with language packs)
		 * - wp-content/plugins/edd-authorize-net/languages/
		 *
		 * In wp-content/languages/edd-authorize-net/ we look for:
		 * - "edd-authorize-net-{lang}_{country}.mo"
		 *
		 * In wp-content/languages/plugins/edd-authorize-net/ we look for:
		 * - "edd-authorize-net-{lang}_{country}.mo"
		 *
		 */

		// Set filter for plugin's languages directory.
		$edd_lang_dir = dirname( $this->base ) . '/languages/';
		$get_locale   = function_exists( 'get_user_locale' )
			? get_user_locale()
			: get_locale();

		/**
		 * Defines the plugin language locale used in Easy Digital Downloads - Authorize.net Checkout.
		 *
		 * @var $get_locale The locale to use. Uses get_user_locale()` in WordPress 4.7 or greater,
		 *                  otherwise uses `get_locale()`.
		 */
		$locale = apply_filters( 'plugin_locale', $get_locale, 'edd-authorize-net' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'edd-authorize-net', $locale );

		// Look in wp-content/languages/plugins/edd-authorize-net
		$mofile_global1 = WP_LANG_DIR . "/plugins/edd-authorize-net/{$mofile}";

		// Look in wp-content/languages/edd-authorize-net
		$mofile_global2 = WP_LANG_DIR . "/edd-authorize-net/{$mofile}";

		// Try to load from first global location
		if ( file_exists( $mofile_global1 ) ) {
			load_textdomain( 'edda', $mofile_global1 );

			// Try to load from next global location
		} elseif ( file_exists( $mofile_global2 ) ) {
			load_textdomain( 'edda', $mofile_global2 );

			// Load the default language files
		} else {
			load_plugin_textdomain( 'edda', false, $edd_lang_dir );
		}
	}

		/**
	 * Plugin specific URL for an external requirements page.
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @return string
	 */
	private function unmet_requirements_url() {
		return '';
	}

	/**
	 * Plugin specific text to quickly explain what's wrong.
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @return string
	 */
	private function unmet_requirements_text() {
		esc_html_e( 'This plugin is not fully active.', 'edda' );
	}

	/**
	 * Plugin specific text to describe a single unmet requirement.
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @return string
	 */
	private function unmet_requirements_description_text() {
		return '';
	}

	/**
	 * Plugin specific text to describe a single missing requirement.
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @return string
	 */
	private function unmet_requirements_missing_text() {
		return '';
	}

	/**
	 * Plugin specific text used to link to an external requirements page.
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @return string
	 */
	private function unmet_requirements_link() {
		return esc_html__( 'Requirements', 'edda' );
	}

	/**
	 * Plugin specific aria label text to describe the requirements link.
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @return string
	 */
	private function unmet_requirements_label() {
		return esc_html__( 'Easy Digital Downloads Authorize.net Checkout Requirements', 'edda' );
	}

	/**
	 * Plugin specific text used in CSS to identify attribute IDs and classes.
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @return string
	 */
	private function unmet_requirements_name() {
		return 'eddppc-requirements';
	}

	/** Agnostic Methods ******************************************************/

	/**
	 * Plugin agnostic method to output the additional plugin row
	 *
	 * @deprecated 2.0.4
	 * @since 1.0
	 */
	public function plugin_row_notice() {}

	/**
	 * Plugin agnostic method used to output all unmet requirement information
	 *
	 * @deprecated 2.0.4
	 * @since 1.0
	 */
	private function unmet_requirements_description() {}

	/**
	 * Plugin agnostic method to output specific unmet requirement information
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 * @param array $requirement
	 */
	private function unmet_requirement_description( $requirement = array() ) {}

	/**
	 * Plugin agnostic method to output unmet requirements styling
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 */
	public function admin_head() {}

	/** Checkers **************************************************************/

	/**
	 * Plugin specific requirements checker
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 */
	private function check() {}

	/**
	 * Have all requirements been met?
	 *
	 * @since 1.0
	 * @deprecated 2.0.4
	 *
	 * @return boolean
	 */
	public function met() {}

	/**
	 * Quit without loading
	 *
	 * @deprecated 2.0.4
	 * @since 1.0
	 */
	private function quit() {}
}

$requirements = array(
	'php'                    => '7.2',
	'easy-digital-downloads' => '2.11',
	'wp'                     => '4.9',
);

if (
	defined( 'EDD_RECURRING_VERSION' ) &&
	defined( '\\EDD\\ExtensionUtils\\v1\\ExtensionLoader::VERSION' ) &&
	version_compare( \EDD\ExtensionUtils\v1\ExtensionLoader::VERSION, '1.0.1', '>=' )
) {
	$requirements['recurring'] = array(
		'minimum' => '2.9.6',
		'name'    => 'Easy Digital Downloads - Recurring Payments',
		'exists'  => static function () {
			return function_exists( 'edd_recurring' );
		},
		'current' => static function () {
			return EDD_RECURRING_VERSION;
		},
	);
}

require_once __DIR__ . '/vendor/autoload.php';
\EDD\ExtensionUtils\v1\ExtensionLoader::loadOrQuit(
	__FILE__,
	'edd_authorizenet_load_requirements_check',
	$requirements
);

/**
 * Load the requirements checker.
 *
 * @since 2.0.1
 * @return void
 */
function edd_authorizenet_load_requirements_check() {
	new EDD_Authorize_Net_Requirements_Check();
}
