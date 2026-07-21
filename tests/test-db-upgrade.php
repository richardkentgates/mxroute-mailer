<?php
/**
 * Tests for mxroute_mailer_db_upgrade() function.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_DB_Upgrade_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['wp_db_queries']     = array();
		$GLOBALS['wp_db_col']         = array();
		$GLOBALS['wp_db_var']         = null;
	}

	/**
	 * Tests that db_upgrade runs when version mismatch.
	 */
	public function test_db_upgrade_runs_on_version_mismatch() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$this->assertNotEmpty( $GLOBALS['wp_db_queries'] );
	}

	/**
	 * Tests that db_upgrade skips when version matches.
	 */
	public function test_db_upgrade_skips_when_version_matches() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = MXROUTE_MAILER_VERSION;

		mxroute_mailer_db_upgrade();

		$this->assertEmpty( $GLOBALS['wp_db_queries'] );
	}

	/**
	 * Tests that db_upgrade adds reply_to column when missing.
	 */
	public function test_db_upgrade_adds_reply_to_column() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$found = false;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'reply_to' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected reply_to ALTER TABLE query' );
	}

	/**
	 * Tests that db_upgrade adds headers column when missing.
	 */
	public function test_db_upgrade_adds_headers_column() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$found = false;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'headers' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected headers ALTER TABLE query' );
	}

	/**
	 * Tests that db_upgrade adds attachments column when missing.
	 */
	public function test_db_upgrade_adds_attachments_column() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$found = false;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'attachments' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected attachments ALTER TABLE query' );
	}

	/**
	 * Tests that db_upgrade adds created_at column when missing.
	 */
	public function test_db_upgrade_adds_created_at_column() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$found = false;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'created_at' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected created_at ALTER TABLE query' );
	}

	/**
	 * Tests that db_upgrade adds processed_at column when missing.
	 */
	public function test_db_upgrade_adds_processed_at_column() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$found = false;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'processed_at' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected processed_at ALTER TABLE query' );
	}

	/**
	 * Tests that db_upgrade adds transport column when missing.
	 */
	public function test_db_upgrade_adds_transport_column() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$found = false;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'transport' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected transport ALTER TABLE query' );
	}

	/**
	 * Tests that db_upgrade migrates old failed entries (success=0 to success=-1).
	 */
	public function test_db_upgrade_migrates_old_failed_entries() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$found = false;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'success' ) && false !== strpos( $query, '-1' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected UPDATE query migrating success=0 to success=-1' );
	}

	/**
	 * Tests that db_upgrade updates the db_version option.
	 */
	public function test_db_upgrade_updates_version_option() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array( 'id', 'timestamp', 'from_email', 'to_email', 'subject', 'message', 'success' );

		mxroute_mailer_db_upgrade();

		$this->assertEquals( MXROUTE_MAILER_VERSION, $GLOBALS['wp_options']['mxroute_mailer_db_version'] );
	}

	/**
	 * Tests that db_upgrade does not run ALTER queries when columns already exist.
	 */
	public function test_db_upgrade_skips_when_columns_exist() {
		$GLOBALS['wp_options']['mxroute_mailer_db_version'] = '0.0.0';
		$GLOBALS['wp_db_col'] = array(
			'id', 'timestamp', 'from_email', 'reply_to', 'to_email',
			'subject', 'message', 'headers', 'attachments', 'success',
			'transport', 'created_at', 'processed_at',
		);

		mxroute_mailer_db_upgrade();

		$alter_count = 0;
		foreach ( $GLOBALS['wp_db_queries'] as $query ) {
			if ( false !== strpos( $query, 'ALTER' ) ) {
				++$alter_count;
			}
		}
		$this->assertEquals( 0, $alter_count, 'No ALTER queries expected when all columns exist' );
	}
}
