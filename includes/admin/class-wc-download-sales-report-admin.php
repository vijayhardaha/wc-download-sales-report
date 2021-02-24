<?php
/**
 * WooCommerce Download Sales Report Admin
 *
 * @class WC_Download_Sales_Report_Admin
 * @package WC_Download_Sales_Report
 * @subpackage WC_Download_Sales_Report/Admin
 * @version 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Download_Sales_Report_Admin' ) ) {
	return new WC_Download_Sales_Report_Admin();
}

/**
 * WC_Download_Sales_Report_Admin class.
 */
class WC_Download_Sales_Report_Admin {
	/**
	 * Slug of the admin page.
	 *
	 * @var string
	 */
	private $screen_id = 'wc-download-sales-report';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add menus.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Hide all notices.
		add_action( 'admin_head', array( $this, 'hide_all_notices' ) );

		// Enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'wp_ajax_wc_download_sales_report', array( $this, 'ajax_callback' ) );
		add_action( 'admin_init', array( $this, 'download_csv_file' ) );
	}

	/**
	 * Add menu items.
	 */
	public function admin_menu() {
		add_submenu_page( 'woocommerce', __( 'Download Sales Report', 'wc-download-sales-report' ), __( 'Download Sales Report', 'wc-download-sales-report' ), 'view_woocommerce_reports', $this->screen_id, array( $this, 'display_admin_page' ) );
	}

	/**
	 * Valid screen ids for plugin scripts & styles
	 *
	 * @return  bool
	 */
	public function is_valid_screen() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		$valid_screen_ids = apply_filters(
			'wc_download_sales_report_valid_admin_screen_ids',
			array(
				$this->screen_id,
			)
		);

		if ( empty( $valid_screen_ids ) ) {
			return false;
		}

		foreach ( $valid_screen_ids as $admin_screen_id ) {
			$matcher = '/' . $admin_screen_id . '/';
			if ( preg_match( $matcher, $screen_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue styles.
	 */
	public function admin_styles() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register admin styles.
		wp_register_style( 'wc-download-sales-report-admin-styles', wc_download_sales_report()->plugin_url() . '/assets/css/admin' . $suffix . '.css', array(), WC_DOWNLOAD_SALES_REPORT_VERSION );

		// Admin styles for wc_download_sales_report pages only.
		if ( $this->is_valid_screen() ) {
			wp_enqueue_style( 'wc-download-sales-report-admin-styles' );
		}
	}

	/**
	 * Enqueue scripts.
	 */
	public function admin_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		// Register scripts.
		wp_register_script( 'wc-download-sales-report-admin', wc_download_sales_report()->plugin_url() . '/assets/js/admin' . $suffix . '.js', array( 'jquery', 'jquery-ui-datepicker' ), WC_DOWNLOAD_SALES_REPORT_VERSION, true );

		// Admin scripts for wc_download_sales_report pages only.
		if ( $this->is_valid_screen() ) {
			wp_enqueue_script( 'wc-download-sales-report-admin' );
			$params = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			);
			wp_localize_script( 'wc-download-sales-report-admin', 'wc_download_sales_report_admin_params', $params );
		}
	}

	/**
	 * Hides all admin notice for new admin page
	 */
	public function hide_all_notices() {
		if ( $this->is_valid_screen() ) {
			remove_all_actions( 'admin_notices' );
		}
	}

	/**
	 * Returns sales report settings
	 *
	 * @return array
	 */
	private function get_settings() {
		$default = array(
			'report_time'  => '30d',
			'report_start' => gmdate( 'Y-m-d', strtotime( date_i18n( 'Y-m-d' ) ) - ( 86400 * 31 ) ),
			'report_end'   => gmdate( 'Y-m-d', strtotime( date_i18n( 'Y-m-d' ) ) - 86400 ),
			'order_status' => array( 'wc-processing', 'wc-on-hold', 'wc-completed' ),
			'products'     => 'all',
			'product_cats' => array(),
			'product_ids'  => '',
			'orderby'      => 'quantity',
			'order'        => 'DESC',
			'fields'       => array( 'product_id', 'product_sku', 'product_name', 'quantity_sold', 'gross_sales' ),
			'exclude_free' => 'no',
		);

		$settings = get_option( 'wc_download_sales_report_settings' );
		$settings = $settings ? $settings : array();
		$settings = wp_parse_args( $settings, $default );

		return $settings;
	}

	/**
	 * Retuns download url
	 *
	 * @param array $args download arguments.
	 * @return string
	 */
	public function get_download_url( $args = array() ) {
		$dowload_query = build_query( $args );
		$download_link = wp_nonce_url( admin_url( 'admin.php?' . $dowload_query ), 'wc_download_sales_report_nonce' );

		return str_replace( '&amp;', '&', $download_link );
	}

	/**
	 * Display admin page
	 */
	public function display_admin_page() {
		$report_fields = array(
			'product_id'           => __( 'Product ID', 'wc-download-sales-report' ),
			'product_sku'          => __( 'Product SKU', 'wc-download-sales-report' ),
			'product_name'         => __( 'Product Name', 'wc-download-sales-report' ),
			'product_categories'   => __( 'Product Categories', 'wc-download-sales-report' ),
			'quantity_sold'        => __( 'Quantity Sold', 'wc-download-sales-report' ),
			'gross_sales'          => __( 'Gross Sales', 'wc-download-sales-report' ),
			'gross_after_discount' => __( 'Gross Sales (After Discounts)', 'wc-download-sales-report' ),
		);

		$report_times = array(
			'0d'     => __( 'Today', 'wc-download-sales-report' ),
			'1d'     => __( 'Yesterday', 'wc-download-sales-report' ),
			'7d'     => __( 'Previous 7 days (excluding today)', 'wc-download-sales-report' ),
			'30d'    => __( 'Previous 30 days (excluding today)', 'wc-download-sales-report' ),
			'0cm'    => __( 'Current calendar month', 'wc-download-sales-report' ),
			'1cm'    => __( 'Previous calendar month', 'wc-download-sales-report' ),
			'+7d'    => __( 'Next 7 days (future dated orders)', 'wc-download-sales-report' ),
			'+30d'   => __( 'Next 30 days (future dated orders)', 'wc-download-sales-report' ),
			'+1cm'   => __( 'Next calendar month (future dated orders)', 'wc-download-sales-report' ),
			'all'    => __( 'All time', 'wc-download-sales-report' ),
			'custom' => __( 'Custom date range', 'wc-download-sales-report' ),
		);

		$orderby_fields = array(
			'product_id'           => __( 'Product ID', 'wc-download-sales-report' ),
			'quantity'             => __( 'Quantity Sold', 'wc-download-sales-report' ),
			'gross'                => __( 'Gross Sales', 'wc-download-sales-report' ),
			'gross_after_discount' => __( 'Gross Sales (After Discounts)', 'wc-download-sales-report' ),
		);

		$order_fields = array(
			'ASC'  => __( 'Ascending', 'wc-download-sales-report' ),
			'DESC' => __( 'Descending', 'wc-download-sales-report' ),
		);

		$product_cats = get_terms(
			array(
				'taxonomy'     => 'product_cat',
				'orderby'      => 'name',
				'order'        => 'ASC',
				'hierarchical' => false,
			)
		);

		$settings = $this->get_settings();
		?>

		<div class="wrap wc-download-sales-report-container" id="wc-download-sales-report-container">
			<div class="wrapper">
				<div class="page-title">
					<h2>
						<span class="dashicons dashicons-cart"></span>
						<span class="link-shadow"><?php esc_html_e( 'Product Sales Report', 'wc-download-sales-report' ); ?></span>
					</h2>
				</div>

				<div class="page-content">
					<form method="post" action="" class="download-sales-report-form">
						<input type="hidden" name="action" value="wc_download_sales_report" />
						<input type="hidden" name="download" value="0" />

						<?php wp_nonce_field( 'wc_download_sales_report_nonce' ); ?>

						<div id="setting-row-report-time" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-report-time"><?php esc_html_e( 'Report Period', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<select id="setting-report-time" name="report_time">
									<?php
									foreach ( $report_times as $key => $value ) {
										printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $key ), esc_html( $value ), selected( $key, $settings['report_time'], false ) );
									}
									?>
								</select>
								<p class="desc"><?php esc_html_e( 'Choose the report orders period.', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<div id="setting-row-custom-date-range" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-custom-date-range"><?php esc_html_e( 'Custom Date Range', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<div class="input-group">
									<span for="setting-report-start" class="label"><?php esc_html_e( 'Start Date', 'wc-download-sales-report' ); ?></span>
									<input id="setting-report-start" type="text" class="datepicker" name="report_start" value="<?php echo esc_attr( $settings['report_start'] ); ?>" placeholder="YYYY-MM-DD" readonly />
								</div>
								<div class="input-group">
									<span for="setting-report-end" class="label"><?php esc_html_e( 'End Date', 'wc-download-sales-report' ); ?></span>
									<input id="setting-report-end" type="text" class="datepicker" name="report_end" value="<?php echo esc_attr( $settings['report_end'] ); ?>" placeholder="YYYY-MM-DD" readonly />
								</div>
								<p class="desc"><?php esc_html_e( 'Choose the custom date range for report orders period.', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<div id="setting-row-order-status" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-order-status"><?php esc_html_e( 'Order Status', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<?php
								foreach ( wc_get_order_statuses() as $key => $value ) {
									$checked = ! empty( $settings['order_status'] ) && in_array( $key, $settings['order_status'], true ) ? 1 : 0;
									printf(
										'<div class="checkbox-group inline"><input id="setting-order-status-%1$s" name="order_status[]" type="checkbox" value="%1$s" %3$s /><label for="setting-order-status-%1$s">%2$s</label></div>',
										esc_attr( $key ),
										esc_attr( $value ),
										checked( 1, $checked, false )
									);
								}
								?>
								<p class="desc"><?php esc_html_e( 'Choose the order statuses to be included in report.', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<div id="setting-row-include-products" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-include-products"><?php esc_html_e( 'Include Products', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<div class="checkbox-group">
									<input id="setting-include-products-all" name="products" type="radio" value="all" <?php checked( 'all', $settings['products'] ); ?>/>
									<label for="setting-include-products-all"><?php esc_html_e( 'All Products', 'wc-download-sales-report' ); ?></label>
								</div>
								<div class="checkbox-group">
									<input id="setting-include-products-categories" name="products" type="radio" value="categories" <?php checked( 'categories', $settings['products'] ); ?>/>
									<label for="setting-include-products-categories"><?php esc_html_e( 'Products in Categories', 'wc-download-sales-report' ); ?></label>
									<div class="inner-group" id="product-cats-lists">
										<?php
										foreach ( $product_cats as $term ) {
											$checked = ! empty( $settings['product_cats'] ) && in_array( $term->term_id, $settings['product_cats'], true ) ? 1 : 0;
											printf(
												'<div class="checkbox-group inline"><input id="product-cats-%1$s" name="product_cats[]" type="checkbox" value="%1$s" %3$s /><label for="product-cats-%1$s">%2$s</label></div>',
												esc_attr( $term->term_id ),
												esc_attr( $term->name ),
												checked( 1, $checked, false )
											);
										}
										?>
									</div>
								</div>
								<div class="checkbox-group">
									<input id="setting-include-products-ids" name="products" type="radio" value="ids" <?php checked( 'ids', $settings['products'] ); ?>s />
									<label for="setting-include-products-ids"><?php esc_html_e( 'Product IDs', 'wc-download-sales-report' ); ?></label>
									<div class="inner-group" id="product-ids-field">
										<input id="setting-product-ids" type="text" name="product_ids" value="<?php echo esc_attr( $settings['product_ids'] ); ?>" placeholder="<?php esc_attr_e( 'Use commas to separate multiple product IDs', 'wc-download-sales-report' ); ?>"  />
									</div>
								</div>
								<p class="desc"><?php esc_html_e( 'Choose the products to be included in report..', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<div id="setting-row-orderby" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-orderby"><?php esc_html_e( 'Order By', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<select id="setting-orderby" name="orderby">
									<?php
									foreach ( $orderby_fields as $key => $value ) {
										printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $key ), esc_html( $value ), selected( $key, $settings['orderby'], false ) );
									}
									?>
								</select>
								<p class="desc"><?php esc_html_e( 'Choose the report order field..', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<div id="setting-row-order" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-order"><?php esc_html_e( 'Order', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<select id="setting-order" name="order">
									<?php
									foreach ( $order_fields as $key => $value ) {
										printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $key ), esc_html( $value ), selected( $key, $settings['order'], false ) );
									}
									?>
								</select>
								<p class="desc"><?php esc_html_e( 'Choose the report order type.', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<div id="setting-row-report-fields" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-report-ields"><?php esc_html_e( 'Report Fields', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<?php
								foreach ( $report_fields as $key => $value ) {
									$checked = ! empty( $settings['fields'] ) && in_array( $key, $settings['fields'], true ) ? 1 : 0;
									printf(
										'<div class="checkbox-group"><input id="setting-report-fields-%1$s" name="fields[]" type="checkbox" value="%1$s" %3$s /><label for="setting-report-fields-%1$s">%2$s</label></div>',
										esc_attr( $key ),
										esc_attr( $value ),
										checked( 1, $checked, false )
									);
								}
								?>
								<p class="desc"><?php esc_html_e( 'Choose the fields to be included in report.', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<div id="setting-row-exclude-free" class="setting-row clear">
							<div class="setting-label">
								<label for="setting-exclude-free"><?php esc_html_e( 'Exclude free products', 'wc-download-sales-report' ); ?></label>
							</div>
							<div class="setting-field">
								<label class="radio-field" for="setting-exclude-free-yes"><input id="setting-exclude-free-yes" type="radio" name="exclude_free" value="yes" <?php checked( 'yes', $settings['exclude_free'] ); ?> /><?php esc_html_e( 'Yes', 'wc-download-sales-report' ); ?></label>
								<label  class="radio-field" for="setting-exclude-free-no"><input id="setting-exclude-free-no" type="radio"  name="exclude_free" value="no" <?php checked( 'no', $settings['exclude_free'] ); ?> /><?php esc_html_e( 'No', 'wc-download-sales-report' ); ?></label>
								<p class="desc"><?php esc_html_e( 'Choose if free products should be include or exclude.', 'wc-download-sales-report' ); ?></p>
							</div>
						</div>

						<p class="setting-submit">
							<button class="btn view-report" type="button"><?php esc_html_e( 'View Report', 'wc-download-sales-report' ); ?></button>
							<button class="btn download-report" type="button"><?php esc_html_e( 'Download Report', 'wc-download-sales-report' ); ?></button>
							<a class="btn download-url hidden" href="#"><?php esc_html_e( 'Download Report', 'wc-download-sales-report' ); ?></a>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Ajax callback function
	 */
	public function ajax_callback() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wc_download_sales_report_nonce' ) ) {

			$settings = array(
				'report_time'  => isset( $_POST['report_time'] ) ? sanitize_text_field( wp_unslash( $_POST['report_time'] ) ) : '',
				'order_status' => isset( $_POST['order_status'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['order_status'] ) ) : array(),
				'products'     => isset( $_POST['products'] ) ? sanitize_text_field( wp_unslash( $_POST['products'] ) ) : '',
				'product_cats' => isset( $_POST['product_cats'] ) ? array_map( 'absint', wp_unslash( $_POST['product_cats'] ) ) : array(),
				'product_ids'  => isset( $_POST['product_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['product_ids'] ) ) : '',
				'orderby'      => isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : '',
				'order'        => isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : '',
				'fields'       => isset( $_POST['fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) ) : array(),
				'exclude_free' => isset( $_POST['exclude_free'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_free'] ) ) : '',
			);

			update_option( 'wc_download_sales_report_settings', $settings );

			if ( isset( $_POST['download'] ) && absint( wp_unslash( $_POST['download'] ) ) === 0 ) {
				wp_send_json_success( array( 'html' => $this->generate_report_content() ) );
			} else {
				$download_url = $this->get_download_url(
					array(
						'page'                     => $this->screen_id,
						'wc-download-sales-report' => 1,
					)
				);
				wp_send_json_success( array( 'redirect' => $download_url ) );
			}
		}

		wp_send_json_error();
		exit;
	}

	/**
	 * Return sales report content
	 *
	 * @return string
	 */
	private function generate_report_content() {
		$settings     = $this->get_settings();
		$rows         = $this->export_body( null, true );
		$headers      = $this->export_header( null, true );
		$fields_count = count( $settings['fields'] );
		ob_start();
		?>
		<div class="side-panel-wrapper">
			<div class="side-panel-content">
				<div class="side-panel-section">
					<table class="widefat fixed striped">
					<thead>
						<tr>
							<?php
							foreach ( $headers as $row ) {
								printf( '<th>%s</th>', esc_html( $row ) );

							}
							?>
						</tr>
					</thead>
					<tbody>
							<?php
							if ( ! empty( $rows ) ) {
								foreach ( $rows as $key => $row ) {
									$items = '';
									foreach ( $row as $item ) {
										$items .= sprintf( '<td>%s</td>', esc_html( $item ) );
									}
									printf( '<tr>%s</tr>', wp_kses_post( $items ) );
								}
							} else {
								?>
								<tr><td colspan="<?php echo esc_attr( $fields_count ); ?>"><?php esc_html_e( 'No Records found', 'wc-download-sales-report' ); ?></td></tr>
								<?php
							}
							?>
					</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
		$content = ob_get_clean();
		return $content;
	}

	/**
	 * Download csv sales report
	 */
	public function download_csv_file() {
		global $pagenow;

		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && $_GET['page'] === $this->screen_id && isset( $_GET['wc-download-sales-report'] ) && 1 === absint( wp_unslash( $_GET['wc-download-sales-report'] ) ) ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wc_download_sales_report_nonce' ) ) {
				$settings = $this->get_settings();
				// Check if no fields are selected or if not downloading.
				if ( empty( $settings['fields'] ) ) {
					return;
				}

				// Assemble the filename for the report download.
				$filename = 'Product Sales - ' . gmdate( 'Y-m-d', strtotime( date_i18n( 'Y-m-d' ) ) ) . '-' . strtotime( date_i18n( 'Y-m-d' ) ) . '.csv';

				// Send headers.
				header( 'Content-Type: text/csv' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

				// Output the report header row (if applicable) and body.
				$stdout = fopen( 'php://output', 'w' );

				$this->export_header( $stdout );
				$this->export_body( $stdout );

				exit;
			}
		}
	}

	/**
	 * Returns valid order statues
	 *
	 * @return array
	 */
	public function report_order_statuses() {
		$settings          = $this->get_settings();
		$wc_order_statuses = wc_get_order_statuses();
		$order_statuses    = $settings['order_status'];
		$statuses          = array();
		if ( ! empty( $order_statuses ) ) {
			foreach ( $order_statuses as $order_status ) {
				if ( isset( $wc_order_statuses[ $order_status ] ) ) {
					$statuses[] = substr( $order_status, 3 );
				}
			}
		}

		return $statuses;
	}

	/**
	 * Outputs the report header row
	 *
	 * @param string  $dest destination path.
	 * @param boolean $return if true then return.
	 */
	private function export_header( $dest, $return = false ) {
		$header   = array();
		$settings = $this->get_settings();
		foreach ( $settings['fields'] as $field ) {
			switch ( $field ) {
				case 'product_id':
					$header[] = __( 'Product ID', 'wc-download-sales-report' );
					break;
				case 'variation_id':
					$header[] = __( 'Variation ID', 'wc-download-sales-report' );
					break;
				case 'product_sku':
					$header[] = __( 'Product SKU', 'wc-download-sales-report' );
					break;
				case 'product_name':
					$header[] = __( 'Product Name', 'wc-download-sales-report' );
					break;
				case 'variation_attributes':
					$header[] = __( 'Variation Attributes', 'wc-download-sales-report' );
					break;
				case 'quantity_sold':
					$header[] = __( 'Quantity Sold', 'wc-download-sales-report' );
					break;
				case 'gross_sales':
					$header[] = __( 'Gross Sales', 'wc-download-sales-report' );
					break;
				case 'gross_after_discount':
					$header[] = __( 'Gross Sales (After Discounts)', 'wc-download-sales-report' );
					break;
				case 'product_categories':
					$header[] = __( 'Product Categories', 'wc-download-sales-report' );
					break;
			}
		}

		if ( $return ) {
			return $header;
		}

		fputcsv( $dest, $header );
	}

	/**
	 * Generates and outputs the report body rows
	 *
	 * @param string  $dest destination path.
	 * @param boolean $return if true then return.
	 */
	private function export_body( $dest, $return = false ) {
		global $wpdb;

		$settings = $this->get_settings();

		$product_ids = array();
		if ( 'cats' === $settings['products'] ) {
			$cats = array();
			foreach ( $settings['product_cats'] as $cat ) {
				if ( is_numeric( $cat ) ) {
					$cats[] = $cat;
				}
			}
			$product_ids = get_objects_in_term( $cats, 'product_cat' );
		} elseif ( 'ids' === $settings['products'] ) {
			foreach ( explode( ',', $settings['product_ids'] ) as $product_id ) {
				$product_id = trim( $product_id );
				if ( is_numeric( $product_id ) ) {
					$product_ids[] = $product_id;
				}
			}
		}

		// Calculate report start and end dates (timestamps).
		switch ( $settings['report_time'] ) {
			case '0d':
				$end_date   = strtotime( 'midnight', strtotime( date_i18n( 'Y-m-d' ) ) );
				$start_date = $end_date;
				break;
			case '1d':
				$end_date   = strtotime( 'midnight', strtotime( date_i18n( 'Y-m-d' ) ) ) - 86400;
				$start_date = $end_date;
				break;
			case '7d':
				$end_date   = strtotime( 'midnight', strtotime( date_i18n( 'Y-m-d' ) ) ) - 86400;
				$start_date = $end_date - ( 86400 * 6 );
				break;
			case '1cm':
				$start_date = strtotime( gmdate( 'Y-m', strtotime( date_i18n( 'Y-m-d' ) ) ) . '-01 midnight -1month' );
				$end_date   = strtotime( '+1month', $start_date ) - 86400;
				break;
			case '0cm':
				$start_date = strtotime( gmdate( 'Y-m', strtotime( date_i18n( 'Y-m-d' ) ) ) . '-01 midnight' );
				$end_date   = strtotime( '+1month', $start_date ) - 86400;
				break;
			case '+1cm':
				$start_date = strtotime( gmdate( 'Y-m', strtotime( date_i18n( 'Y-m-d' ) ) ) . '-01 midnight +1month' );
				$end_date   = strtotime( '+1month', $start_date ) - 86400;
				break;
			case '+7d':
				$start_date = strtotime( 'midnight', strtotime( date_i18n( 'Y-m-d' ) ) ) + 86400;
				$end_date   = $start_date + ( 86400 * 6 );
				break;
			case '+30d':
				$start_date = strtotime( 'midnight', strtotime( date_i18n( 'Y-m-d' ) ) ) + 86400;
				$end_date   = $start_date + ( 86400 * 29 );
				break;
			case 'custom':
				$end_date   = strtotime( 'midnight', strtotime( $settings['report_end'] ) );
				$start_date = strtotime( 'midnight', strtotime( $settings['report_start'] ) );
				break;
			default: // 30 days is the default.
				$end_date   = strtotime( 'midnight', strtotime( date_i18n( 'Y-m-d' ) ) ) - 86400;
				$start_date = $end_date - ( 86400 * 29 );
		}

		// Assemble order by string.
		$orderby  = in_array( $settings['orderby'], array( 'product_id', 'gross', 'gross_after_discount' ), true ) ? $settings['orderby'] : 'quantity';
		$orderby .= ' ' . $settings['order'];

		// Create a new WC_Admin_Report object.
		include_once WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php';

		$wc_report             = new WC_Admin_Report();
		$wc_report->start_date = $start_date;
		$wc_report->end_date   = $end_date;

		$where_meta = array();

		if ( 'all' !== $settings['products'] ) {
			$where_meta[] = array(
				'meta_key'   => '_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $product_ids, // phpcs:ignore WordPress.DB.SlowDBQuery
				'operator'   => 'in',
				'type'       => 'order_item_meta',
			);
		}

		if ( ! empty( $settings['exclude_free'] ) ) {
			$where_meta[] = array(
				'meta_key'   => '_line_total', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => 0, // phpcs:ignore WordPress.DB.SlowDBQuery
				'operator'   => '!=',
				'type'       => 'order_item_meta',
			);
		}

		// Avoid max join size error.
		$wpdb->query( 'SET SQL_BIG_SELECTS=1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Prevent plugins from overriding the order status filter.
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'report_order_statuses' ), 9999 );

		// Based on woocoommerce/includes/admin/reports/class-wc-report-sales-by-product.php.
		$sold_products = $wc_report->get_order_report_data(
			array(
				'data'         => array(
					'_product_id'    => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => '',
						'name'            => 'product_id',
					),
					'_qty'           => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => 'SUM',
						'name'            => 'quantity',
					),
					'_line_subtotal' => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => 'SUM',
						'name'            => 'gross',
					),
					'_line_total'    => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => 'SUM',
						'name'            => 'gross_after_discount',
					),
				),
				'query_type'   => 'get_results',
				'group_by'     => 'product_id',
				'where_meta'   => $where_meta,
				'order_by'     => $orderby,
				'limit'        => '',
				'filter_range' => 'all' !== $settings['report_time'],
				'order_types'  => wc_get_order_types( 'order_count' ),
				'order_status' => $this->report_order_statuses(),
			)
		);

		// Remove report order statuses filter.
		remove_filter( 'woocommerce_reports_order_statuses', array( $this, 'report_order_statuses' ), 9999 );

		if ( $return ) {
			$rows = array();
		}

		// Output report rows.
		foreach ( $sold_products as $product ) {
			$row = array();

			foreach ( $settings['fields'] as $field ) {
				switch ( $field ) {
					case 'product_id':
						$row[] = $product->product_id;
						break;
					case 'variation_id':
						$row[] = empty( $product->variation_id ) ? '' : $product->variation_id;
						break;
					case 'product_sku':
						$row[] = get_post_meta( $product->product_id, '_sku', true );
						break;
					case 'product_name':
						$row[] = html_entity_decode( get_the_title( $product->product_id ) );
						break;
					case 'quantity_sold':
						$row[] = $product->quantity;
						break;
					case 'gross_sales':
						$row[] = number_format( $product->gross, 2 );
						break;
					case 'gross_after_discount':
						$row[] = number_format( $product->gross_after_discount, 2 );
						break;
					case 'product_categories':
						$terms = get_the_terms( $product->product_id, 'product_cat' );
						if ( empty( $terms ) ) {
							$row[] = '';
						} else {
							$categories = array();
							foreach ( $terms as $term ) {
								$categories[] = ucwords( strtolower( $term->name ) );
							}
							$row[] = implode( ', ', $categories );
						}
						break;
				}
			}

			if ( $return ) {
				$rows[] = $row;
			} else {
				fputcsv( $dest, $row );
			}
		}

		if ( $return ) {
			return $rows;
		}
	}
}

return new WC_Download_Sales_Report_Admin();
