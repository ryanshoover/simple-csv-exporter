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

		$params = [
			'export'   => 'all',
			'_wpnonce' => wp_create_nonce( 'export_posts' ),
		];

		$junction = stristr( $screen->parent_file, '?' ) ? '&' : '?';

		$url = '/wp-admin/' . $screen->parent_file . $junction . http_build_query( $params );

		?>
		<div class="alignleft actions">
			<a
				href="<?php echo esc_url( $url ); ?>"
				id="export_posts"
				class="button"
			>
				<?php
				printf(
					// translators: The placeholder is the plural name of the post type.
					esc_html__( 'Export %s', 'simple-csv-exporter' ),
					esc_html( $post_type->labels->name )
				);
				?>
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

		$exporter = new Exporter( $wp_query );

		$exporter->run();
	}
}
