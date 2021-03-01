<?php
/**
 * Plugin Name: WooCommerce Download Sales Report
 * Plugin URI: https://twitter.com/vijayhardaha/
 * Description: This plugin simply adds a report menu to display & download WooCommerce sales report by different filters.
 * Version: 1.0.2
 * Author: Vijay Hardaha
 * Author URI: https://twitter.com/vijayhardaha/
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: wc-download-sales-report
 * Domain Path: /languages/
 *
 * @package WC_Download_Sales_Report
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WC_DOWNLOAD_SALES_REPORT_PLUGIN_FILE' ) ) {
	define( 'WC_DOWNLOAD_SALES_REPORT_PLUGIN_FILE', __FILE__ );
}

// Include the main WC_Download_Sales_Report class.
if ( ! class_exists( 'WC_Download_Sales_Report', false ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-wc-download-sales-report.php';
}

/**
 * Returns the main instance of WC_Download_Sales_Report.
 *
 * @since  1.0.0
 * @return WC_Download_Sales_Report
 */
function wc_download_sales_report() {
	return WC_Download_Sales_Report::instance();
}

// Global for backwards compatibility.
$GLOBALS['wc_download_sales_report'] = wc_download_sales_report();
