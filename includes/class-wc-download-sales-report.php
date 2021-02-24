<?php
/**
 * WooCommerce Download Sales Report setup
 *
 * @package WC_Download_Sales_Report
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main WC_Download_Sales_Report Class.
 *
 * @class WC_Download_Sales_Report
 */
final class WC_Download_Sales_Report {
	/**
	 * The single instance of the class.
	 *
	 * @var WC_Download_Sales_Report
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Main WC_Download_Sales_Report Instance.
	 *
	 * Ensures only one instance of WC_Download_Sales_Report is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @return WC_Download_Sales_Report - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WC_Download_Sales_Report Constructor.
	 */
	public function __construct() {
		// Check if WooCommerce is active.
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) && ! function_exists( 'WC' ) ) {
			return;
		}

		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * When WP has loaded all plugins, trigger the `wc_download_sales_report_loaded` hook.
	 *
	 * This ensures `wc_download_sales_report_loaded` is called only after all other plugins
	 * are loaded.
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		do_action( 'wc_download_sales_report_loaded' );
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		register_shutdown_function( array( $this, 'log_errors' ) );

		add_action( 'admin_notices', array( $this, 'build_dependencies_notice' ) );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), -1 );
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Output a admin notice when build dependencies not met.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function build_dependencies_notice() {
		$old_php = version_compare( phpversion(), WC_DOWNLOAD_SALES_REPORT_MIN_PHP_VERSION, '<' );
		$old_wp  = version_compare( get_bloginfo( 'version' ), WC_DOWNLOAD_SALES_REPORT_MIN_WP_VERSION, '<' );

		// Both PHP and WordPress up to date version => no notice.
		if ( ! $old_php && ! $old_wp ) {
			return;
		}

		if ( $old_php && $old_wp ) {
			$msg = sprintf(
				/* translators: 1: Minimum PHP version 2: Recommended PHP version 3: Minimum WordPress version */
				__( 'Update required: WooCommerce Download Sales Report require PHP version %1$s or newer (%2$s or higher recommended) and WordPress version %3$s or newer to work properly. Please update to required version to have best experience.', 'wc-download-sales-report' ),
				WC_DOWNLOAD_SALES_REPORT_MIN_PHP_VERSION,
				WC_DOWNLOAD_SALES_REPORT_BEST_PHP_VERSION,
				WC_DOWNLOAD_SALES_REPORT_MIN_WP_VERSION
			);
		} elseif ( $old_php ) {
			$msg = sprintf(
				/* translators: 1: Minimum PHP version 2: Recommended PHP version */
				__( 'Update required: WooCommerce Download Sales Report require PHP version %1$s or newer (%2$s or higher recommended) to work properly. Please update to required version to have best experience.', 'wc-download-sales-report' ),
				WC_DOWNLOAD_SALES_REPORT_MIN_PHP_VERSION,
				WC_DOWNLOAD_SALES_REPORT_BEST_PHP_VERSION
			);
		} elseif ( $old_wp ) {
			$msg = sprintf(
				/* translators: %s: Minimum WordPress version */
				__( 'Update required: WooCommerce Download Sales Report require WordPress version %s or newer to work properly. Please update to required version to have best experience.', 'wc-download-sales-report' ),
				WC_DOWNLOAD_SALES_REPORT_MIN_WP_VERSION
			);
		}

		echo '<div class="error"><p>' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Ensures fatal errors are logged so they can be picked up in the status report.
	 *
	 * @since 1.0.0
	 */
	public function log_errors() {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				/* translators: 1: error message 2: file name and path 3: line number */
				$error_message = sprintf( __( '%1$s in %2$s on line %3$s', 'wc-download-sales-report' ), $error['message'], $error['file'], $error['line'] ) . PHP_EOL;
				// phpcs:disable WordPress.PHP.DevelopmentFunctions
				error_log( $error_message );
				// phpcs:enable WordPress.PHP.DevelopmentFunctions
			}
		}
	}

	/**
	 * Define WC Constants.
	 */
	private function define_constants() {
		$this->define( 'WC_DOWNLOAD_SALES_REPORT_ABSPATH', dirname( WC_DOWNLOAD_SALES_REPORT_PLUGIN_FILE ) . '/' );
		$this->define( 'WC_DOWNLOAD_SALES_REPORT_PLUGIN_BASENAME', plugin_basename( WC_DOWNLOAD_SALES_REPORT_PLUGIN_FILE ) );
		$this->define( 'WC_DOWNLOAD_SALES_REPORT_VERSION', '1.0.0' );
		$this->define( 'WC_DOWNLOAD_SALES_REPORT_MIN_PHP_VERSION', '5.3' );
		$this->define( 'WC_DOWNLOAD_SALES_REPORT_BEST_PHP_VERSION', '5.6' );
		$this->define( 'WC_DOWNLOAD_SALES_REPORT_MIN_WP_VERSION', '4.0' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * What type of request is this?
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		if ( $this->is_request( 'admin' ) ) {
			include_once WC_DOWNLOAD_SALES_REPORT_ABSPATH . 'includes/admin/class-wc-download-sales-report-admin.php';
		}
	}

	/**
	 * Init WC_Download_Sales_Report when WordPress Initialises.
	 */
	public function init() {
		// Before init action.
		do_action( 'before_wc_download_sales_report_init' );

		// Set up localisation.
		$this->load_plugin_textdomain();

		// Init action.
		do_action( 'wc_download_sales_report_init' );
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/wc-download-sales-report/wc-download-sales-report-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/wc-download-sales-report-LOCALE.mo
	 */
	public function load_plugin_textdomain() {
		if ( function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
		} else {
			$locale = is_admin() ? get_user_locale() : get_locale();
		}

		$locale = apply_filters( 'plugin_locale', $locale, 'wc-download-sales-report' );

		unload_textdomain( 'wc-download-sales-report' );
		load_textdomain( 'wc-download-sales-report', WP_LANG_DIR . '/wc-download-sales-report/wc-download-sales-report-' . $locale . '.mo' );
		load_plugin_textdomain( 'wc-download-sales-report', false, plugin_basename( dirname( WC_DOWNLOAD_SALES_REPORT_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', WC_DOWNLOAD_SALES_REPORT_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( WC_DOWNLOAD_SALES_REPORT_PLUGIN_FILE ) );
	}
}
