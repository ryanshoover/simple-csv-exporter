<?php
/**
 * Handle exporting to a CSV
 *
 * @package simple-csv-exporter
 */

namespace SimpleCSVExporter;

/**
 * Exporter class
 */
class Exporter {

	/**
	 * Active WP_Query to process.
	 *
	 * @var \WP_Query
	 */
	protected $query;

	/**
	 * Name of the post type being exported.
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * File pointer for the CSV output
	 *
	 * @var resource
	 */
	protected $file;

	/**
	 * Cached user names.
	 *
	 * @var array
	 */
	protected $users = [];

	/**
	 * Full list of meta keys for the post type.
	 *
	 * @var array
	 */
	protected $meta_keys = [];

	/**
	 * Taxonomies attached to the post type.
	 *
	 * @var array
	 */
	protected $tax_names = [];

	/**
	 * Build the class.
	 *
	 * @param \WP_Query $wp_query Query that has the posts to export.
	 */
	public function __construct( $wp_query ) {
		$this->query     = $wp_query;
		$this->post_type = $wp_query->query_vars['post_type'];
		$this->file      = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$this->meta_keys = $this->get_meta_keys();
		$this->tax_names = $this->get_tax_names();
	}

	/**
	 * Primary controller function.
	 */
	public function run() {
		if ( ! $this->query->have_posts() ) {
			return;
		}

		$this->headers();

		$this->row_header();

		$this->loop_through_pages();

		exit;
	}

	/**
	 * Echo the headers necessary for the browser
	 * to download the CSV.
	 */
	protected function headers() {
		$filename = 'simple-export-' . $this->post_type . '-' . date( 'Y-m-d' ) . '.csv';

		header( 'Content-type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}

	/**
	 * Add our header row to the CSV.
	 */
	protected function row_header() {
		$core_fields = [
			'ID',
			'Title',
			'Slug',
			'Author',
			'Type',
			'Publish Date',
			'Modified Date',
			'Content',
			'Excerpt',
			'Status',
			'Parent ID',
			'Featured Image',
		];

		$tax_labels = array_map(
			function( $tax_name ) {
				$tax = get_taxonomy( $tax_name );
				return $tax->labels->name;
			},
			$this->tax_names
		);

		$fields = array_merge(
			$core_fields,
			$this->meta_keys,
			$tax_labels
		);

		fputcsv(
			$this->file,
			$fields
		);
	}

	/**
	 * Get the total list of keys for the post type.
	 *
	 * @return array List of meta keys for the post type.
	 */
	protected function get_meta_keys() {
		global $wpdb;

		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT m.meta_key FROM $wpdb->posts p JOIN $wpdb->postmeta m ON p.ID = m.post_id WHERE p.post_type = %s ORDER BY m.meta_key ASC ",
				$this->post_type
			)
		);

		return $keys;
	}

	/**
	 * Get the taxonomies attached to the post type.
	 *
	 * @return array List of taxonomy names.
	 */
	protected function get_tax_names() {
		$tax_names = get_object_taxonomies(
			$this->post_type,
			'names'
		);

		sort( $tax_names );

		return $tax_names;
	}

	/**
	 * Loop through all the possible pages
	 * for the query.
	 */
	protected function loop_through_pages() {
		$max_pages = intval( $this->query->max_num_pages );

		while ( $this->query->query_vars['paged'] <= $max_pages ) {
			if ( ! $this->query->have_posts() ) {
				break;
			}

			$this->process_wp_query();

			// Set up next WP_Query.
			$query_vars          = array_filter( $this->query->query_vars );
			$query_vars['paged'] = $this->query->is_paged ? $query_vars['paged'] + 1 : 2;

			$this->query = new \WP_Query( $query_vars );
		}
	}

	/**
	 * Process a single query.
	 *
	 * Loops through all posts in the query and adds them
	 * to the CSV.
	 */
	protected function process_wp_query() {
		while ( $this->query->have_posts() ) {
			$this->query->the_post();
			$this->row_post();
		}
	}

	/**
	 * Add a single post to the CSV.
	 */
	protected function row_post() {
		$post = get_post();

		$core_values = [
			$post->ID,
			$post->post_title,
			$post->post_name,
			$this->get_user_name( $post->post_author ),
			$post->post_type,
			$post->post_date,
			$post->post_modified,
			$post->post_content,
			$post->post_excerpt,
			$post->post_status,
			$post->post_parent,
			get_the_post_thumbnail_url( get_the_ID(), 'full' ),
		];

		$meta_values = $this->get_post_meta_values();

		$term_names = $this->get_post_term_names();

		$values = array_merge(
			$core_values,
			$meta_values,
			$term_names
		);

		fputcsv(
			$this->file,
			$values
		);
	}

	/**
	 * Gets a user's display name.
	 *
	 * Gets it either from a new query or our cached record
	 * of user ids and display names.
	 *
	 * @param int $user_id User's ID.
	 * @return string      User's display name.
	 */
	protected function get_user_name( $user_id ) {
		// Get the user info if we don't have it yet.
		if ( empty( $this->users[ $user_id ] ) ) {
			$user = get_user_by( 'ID', $user_id );

			$this->users[ $user_id ] = ! empty( $user ) ? $user->display_name : 'System';
		}

		return $this->users[ $user_id ];
	}

	/**
	 * Get the processed meta values of the post,
	 * ordered by the meta_key.
	 *
	 * @return array Post meta values.
	 */
	protected function get_post_meta_values() {
		$raw_meta = get_post_meta( get_the_ID() );
		$meta     = [];

		foreach ( $this->meta_keys as $key ) {
			if ( empty( $raw_meta[ $key ] ) ) {
				$meta[ $key ] = null;
				continue;
			} elseif ( 1 === count( $raw_meta[ $key ] ) ) {
				$meta[ $key ] = wp_json_encode( $raw_meta[ $key ][0] );
			} else {
				$meta[ $key ] = wp_json_encode( $raw_meta[ $key ] );
			}
		}

		ksort( $meta );

		return array_values( $meta );
	}

	/**
	 * Get an array of all terms associated with a post.
	 *
	 * - Array values are a comma-separated list of term names.
	 *
	 * @return array Term names tied to a post.
	 */
	protected function get_post_term_names() {
		$all_term_names = [];

		foreach ( $this->tax_names as $tax_name ) {
			$terms = get_the_terms( get_the_ID(), $tax_name );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				$all_term_names[ $tax_name ] = '';
				continue;
			}

			$term_names = array_map(
				function( $term ) {
					return $term->name;
				},
				$terms
			);

			$all_term_names[ $tax_name ] = implode( ',', $term_names );
		}

		ksort( $all_term_names );

		return array_values( $all_term_names );
	}
}
