<?php
/**
 * Add admin settings & buttons.
 *
 * @package simple-csv-exporter
 */

namespace SimpleCSVExporter;

/**
 * Class to add admin features.
 */
class Admin {

	/**
	 * Hook into WordPress.
	 */
	public static function hooks() {
		add_action( 'manage_posts_extra_tablenav', [ __CLASS__, 'add_export_button' ] );

		add_action( 'wp', [ __CLASS__, 'maybe_trigger_export' ] );
	}

	/**
	 * Add the Export Posts button to the Edit screen
	 * on all post types.
	 *
	 * @param string $which Location of the bar, top or bottom.
	 */
	public static function add_export_button( $which ) {
		// Only add the export button to the top tablenav.
		if ( 'top' !== $which ) {
			return;
		}

		$screen    = get_current_screen();
		$post_type = get_post_type_object( $screen->post_type );

		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			parse_str( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ), $params );
		} else {
			parse_str( wp_parse_url( $screen->parent_file, PHP_URL_QUERY ), $params );
		}

		$params['export']   = 'all';
		$params['_wpnonce'] = wp_create_nonce( 'export_posts' );

		$path = wp_parse_url( $screen->parent_file, PHP_URL_PATH );

		$url = admin_url( $path . '?' . http_build_query( $params ) );

		?>
		<div class="alignleft actions">
			<a
				href="<?php echo esc_url( $url ); ?>"
				id="export_posts"
				class="button"
				aria-label="<?php esc_html_e( 'Download CSV of filtered posts', 'simple-csv-exporter' ); ?>"
				title="<?php esc_html_e( 'Download CSV of filtered posts', 'simple-csv-exporter' ); ?>"
			>
				<span class="dashicons dashicons-download" style="line-height: 1.4em"></span>&nbsp;<?php esc_html_e( 'Download' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Trigger the export if we've got the right URL parameters.
	 */
	public static function maybe_trigger_export() {
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'export_posts' ) ) {
			return;
		}

		if ( empty( $_GET['export'] ) || 'all' !== sanitize_text_field( $_GET['export'] ) ) {
			return;
		}

		global $wp_query;

		if ( ! $wp_query->is_main_query() ) {
			return;
		}

		$exporter = new Exporter( $wp_query );

		$exporter->run();
	}
}
