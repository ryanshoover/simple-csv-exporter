<?php // phpcs:ignoreFile
/**
 * Test the export feature
 *
 * @package simple-csv-export
 */

/**
 * Runs tests on our post types.
 */
class Export_Cest {

	protected $faker;

	/**
	 * Set up the pages before we run our tests.
	 *
	 * @param FunctionalTester $I Instance of FunctionalTester.
	 */
	public function _before( FunctionalTester $I ) {
		$this->faker = \Faker\Factory::create();

		$I->loginAsAdmin();
		$I->amOnPluginsPage();
		$I->activatePlugin( 'simple-csv-exporter' );

		$this->create_posts( $I );
	}

	protected function create_posts( FunctionalTester $I ) {
		// Remove Hello World!
		$I->dontHavePostInDatabase( [ 'ID' => '1' ] );

		for ( $num = 1; $num <= 20; $num++ ) {
			$post_title = $this->faker->words( 3, true );
			$post_name  = strtolower( str_replace( ' ', '-', $post_title ) );

			$I->havePostInDatabase(
				[
					'post_title'   => $post_title,
					'post_name'    => $post_name,
					'post_content' => $this->faker->sentences( rand( 1, 4 ), true ),
					'post_date'    => $this->faker->dateTimeBetween( '-6 months' )->format( 'Y-m-d H:i:s' ),
					'post_type'    => 'post',
					'post_status'  => 'publish',
				]
			);
		}
	}

	public function exportAllPosts( FunctionalTester $I ) {
		$I->loginAsAdmin();
		$I->amOnAdminPage( 'edit.php?post_type=post' );
		$I->click( '#export_posts' );

		$source = $I->grabPageSource();
		$I->assertEquals( 21, substr_count( $source, PHP_EOL ) );
	}

	public function exportFilteredPosts( FunctionalTester $I ) {
		$I->loginAsAdmin();
		$I->amOnAdminPage( 'edit.php?post_type=post' );

		// Filter the results.
		$first_option = $I->grabAttributeFrom( '#filter-by-date option:last-child', 'value' );
		$I->selectOption( '#filter-by-date', $first_option );
		$I->click( '#post-query-submit' );

		// How many filtered posts?
		$displaying = $I->grabTextFrom( 'span.displaying-num' );
		$filtered_total = intval( explode( ' ', $displaying )[0] ) + 1; // Add one for the header row.

		// Test download.
		$I->click( '#export_posts' );
		$source = $I->grabPageSource();
		$I->assertEquals( $filtered_total, substr_count( $source, PHP_EOL ) );
	}
}
