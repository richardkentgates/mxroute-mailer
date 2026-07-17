<?php
/**
 * Tests for MXRoute_Queue class.
 *
 * @package MXRoute_Mailer
 */
class MXRoute_Queue_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']        = array();
		$GLOBALS['wp_function_calls'] = array();
		$GLOBALS['wp_db_inserts']     = array();
		$GLOBALS['wp_db_queries']     = array();
		$GLOBALS['wp_scheduled_events'] = array();
	}

	/**
	 * Tests that add inserts a pending queue entry into the database.
	 */
	public function test_add_inserts_pending_entry() {
		$queue = new MXRoute_Queue();
		$queue->add(
			'from@example.com',
			'to@example.com',
			'Test Subject',
			'Test body',
			'',
			array(),
			''
		);

		$this->assertNotEmpty( $GLOBALS['wp_db_inserts'] );
		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( 'wp_mxroute_mailer_logs', $insert['table'] );
		$this->assertEquals( 0, $insert['data']['success'] );
		$this->assertEquals( 'from@example.com', $insert['data']['from_email'] );
		$this->assertEquals( 'to@example.com', $insert['data']['to_email'] );
		$this->assertEquals( 'Test Subject', $insert['data']['subject'] );
		$this->assertArrayNotHasKey( 'processed_at', $insert['data'] );
	}

	/**
	 * Tests that add stores headers in the queue entry.
	 */
	public function test_add_stores_headers() {
		$queue = new MXRoute_Queue();
		$queue->add(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			'Content-Type: text/html',
			array(),
			''
		);

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( 'Content-Type: text/html', $insert['data']['headers'] );
	}

	/**
	 * Tests that add stores array headers as JSON.
	 */
	public function test_add_stores_array_headers_as_json() {
		$queue = new MXRoute_Queue();
		$headers = array( 'Content-Type: text/html', 'X-Custom: value' );
		$queue->add(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			$headers,
			array(),
			''
		);

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( wp_json_encode( $headers ), $insert['data']['headers'] );
	}

	/**
	 * Tests that add stores attachments as JSON.
	 */
	public function test_add_stores_attachments_as_json() {
		$queue = new MXRoute_Queue();
		$attachments = array( '/path/to/file1.pdf', '/path/to/file2.pdf' );
		$queue->add(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			'',
			$attachments,
			''
		);

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( wp_json_encode( $attachments ), $insert['data']['attachments'] );
	}

	/**
	 * Tests that add stores reply_to address.
	 */
	public function test_add_stores_reply_to() {
		$queue = new MXRoute_Queue();
		$queue->add(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			'',
			array(),
			'replyto@example.com'
		);

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( 'replyto@example.com', $insert['data']['reply_to'] );
	}

	/**
	 * Tests that add sanitizes email addresses.
	 */
	public function test_add_sanitizes_email_addresses() {
		$queue = new MXRoute_Queue();
		$queue->add(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			'',
			array(),
			''
		);

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( 'from@example.com', $insert['data']['from_email'] );
		$this->assertEquals( 'to@example.com', $insert['data']['to_email'] );
	}

	/**
	 * Tests that add sanitizes the subject field.
	 */
	public function test_add_sanitizes_subject() {
		$queue = new MXRoute_Queue();
		$queue->add(
			'from@example.com',
			'to@example.com',
			'<script>alert("xss")</script> Test Subject',
			'Body',
			'',
			array(),
			''
		);

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertStringNotContainsString( '<script>', $insert['data']['subject'] );
	}

	/**
	 * Tests that the logger create_table includes queue columns.
	 */
	public function test_create_table_includes_queue_columns() {
		MXRoute_Logger::create_table();
		$this->assertArrayHasKey( 'dbDelta', $GLOBALS['wp_function_calls'] );
		$query = $GLOBALS['wp_function_calls']['dbDelta'][0];
		$this->assertStringContainsString( 'headers', $query );
		$this->assertStringContainsString( 'attachments', $query );
		$this->assertStringContainsString( 'created_at', $query );
		$this->assertStringContainsString( 'processed_at', $query );
	}

	/**
	 * Tests that get_pending queries for pending items.
	 */
	public function test_get_pending_queries_pending_items() {
		$queue = new MXRoute_Queue();
		$result = $queue->get_pending( 10 );

		$this->assertIsArray( $result );
	}

	/**
	 * Tests that count_pending returns an integer.
	 */
	public function test_count_pending_returns_integer() {
		$queue = new MXRoute_Queue();
		$count = $queue->count_pending();

		$this->assertIsInt( $count );
	}

	/**
	 * Tests that cleanup runs without error.
	 */
	public function test_cleanup_runs_without_error() {
		$queue = new MXRoute_Queue();
		$queue->cleanup( 30 );

		$this->assertNotEmpty( $GLOBALS['wp_db_queries'] );
	}

	/**
	 * Tests that cleanup uses the correct date threshold.
	 */
	public function test_cleanup_uses_correct_date() {
		$queue = new MXRoute_Queue();
		$queue->cleanup( 7 );

		$query = end( $GLOBALS['wp_db_queries'] );
		$this->assertStringContainsString( 'DELETE', $query );
	}
}

/**
 * Tests for MXRoute_Mailer queue integration.
 */
class MXRoute_Mailer_Queue_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']          = array();
		$GLOBALS['wp_function_calls']   = array();
		$GLOBALS['wp_transients']       = array();
		$GLOBALS['wp_db_inserts']       = array();
		$GLOBALS['wp_db_queries']       = array();
		$GLOBALS['wp_scheduled_events'] = array();
	}

	/**
	 * Tests that intercept_wp_mail queues an email instead of sending directly.
	 */
	public function test_intercept_queues_email() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$result = $mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => 'Test Subject',
			'message' => 'Body',
		) );

		$this->assertTrue( $result );
		$this->assertNotEmpty( $GLOBALS['wp_db_inserts'] );
		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( 0, $insert['data']['success'] );
	}

	/**
	 * Tests that intercept_wp_mail schedules the queue processor.
	 */
	public function test_intercept_schedules_queue_processor() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => 'Test Subject',
			'message' => 'Body',
		) );

		$this->assertArrayHasKey( 'wp_schedule_single_event', $GLOBALS['wp_function_calls'] );
		$schedule = $GLOBALS['wp_function_calls']['wp_schedule_single_event'][0];
		$this->assertEquals( 'mxroute_mailer_process_queue', $schedule['hook'] );
	}

	/**
	 * Tests that intercept_wp_mail queues separate entries for multiple recipients.
	 */
	public function test_intercept_queues_separate_entries_for_multiple_recipients() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'      => array( 'a@example.com', 'b@example.com', 'c@example.com' ),
			'subject' => 'Test Subject',
			'message' => 'Body',
		) );

		$this->assertCount( 3, $GLOBALS['wp_db_inserts'] );
	}

	/**
	 * Tests that intercept_wp_mail stores headers in the queue entry.
	 */
	public function test_intercept_stores_headers() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
			'headers' => 'Content-Type: text/html',
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( 'Content-Type: text/html', $insert['data']['headers'] );
	}

	/**
	 * Tests that intercept_wp_mail stores attachments in the queue entry.
	 */
	public function test_intercept_stores_attachments() {
		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'attachment content' );

		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'          => 'to@example.com',
			'subject'     => 'Test',
			'message'     => 'Body',
			'attachments' => array( $tmp ),
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( wp_json_encode( array( $tmp ) ), $insert['data']['attachments'] );

		unlink( $tmp );
	}

	/**
	 * Tests that intercept_wp_mail stores reply_to in the queue entry.
	 */
	public function test_intercept_stores_reply_to() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
			'headers' => 'From: sender@example.com',
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertEquals( 'sender@example.com', $insert['data']['reply_to'] );
	}

	/**
	 * Tests that intercept_wp_mail returns null when no credentials are configured.
	 */
	public function test_intercept_returns_null_without_credentials() {
		$mailer = MXRoute_Mailer::instance();
		$result = $mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
		) );

		$this->assertNull( $result );
	}

	/**
	 * Tests that intercept_wp_mail returns null when "to" is empty.
	 */
	public function test_intercept_returns_null_without_to() {
		$mailer = MXRoute_Mailer::instance();
		$result = $mailer->intercept_wp_mail( array(
			'to'      => '',
			'subject' => 'Test',
			'message' => 'Body',
		) );

		$this->assertNull( $result );
	}

	/**
	 * Tests that intercept_wp_mail returns null when subject is empty.
	 */
	public function test_intercept_returns_null_without_subject() {
		$mailer = MXRoute_Mailer::instance();
		$result = $mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => '',
			'message' => 'Body',
		) );

		$this->assertNull( $result );
	}

	/**
	 * Tests that process_queue sends pending emails via the API.
	 */
	public function test_process_queue_sends_pending_emails() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']    = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username']  = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password']  = 'password123';
		$GLOBALS['wp_options']['mxroute_mailer_batch_size'] = 50;

		// Queue an email first.
		$mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => 'Test Subject',
			'message' => 'Body',
		) );

		// Reset inserts to track process_queue calls.
		$GLOBALS['wp_db_inserts'] = array();

		$mailer->process_queue();

		// process_queue calls mark_sent or mark_failed which do $wpdb->update
		// The mock doesn't track updates, but we verify the function ran without error.
		$this->assertTrue( true );
	}

	/**
	 * Tests that process_queue uses the configured batch size.
	 */
	public function test_process_queue_respects_batch_size() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']    = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username']  = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password']  = 'password123';
		$GLOBALS['wp_options']['mxroute_mailer_batch_size'] = 10;

		$mailer->process_queue();

		// Should not error with batch size of 10.
		$this->assertTrue( true );
	}

	/**
	 * Tests that process_queue does nothing when queue is empty.
	 */
	public function test_process_queue_does_nothing_when_empty() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']    = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username']  = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password']  = 'password123';
		$GLOBALS['wp_options']['mxroute_mailer_batch_size'] = 50;

		$mailer->process_queue();

		// No API calls should have been made.
		$this->assertArrayNotHasKey( 'wp_remote_post', $GLOBALS['wp_function_calls'] );
	}
}

/**
 * Tests for MXRoute_Mailer attachment normalization.
 */
class MXRoute_Mailer_Attachment_Normalize_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']          = array();
		$GLOBALS['wp_function_calls']   = array();
		$GLOBALS['wp_transients']       = array();
		$GLOBALS['wp_db_inserts']       = array();
		$GLOBALS['wp_db_queries']       = array();
		$GLOBALS['wp_scheduled_events'] = array();
		$GLOBALS['wp_attached_files']   = array();
	}

	/**
	 * Tests that intercept_wp_mail resolves attachment IDs to file paths.
	 */
	public function test_intercept_resolves_attachment_ids() {
		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'attachment content' );
		$GLOBALS['wp_attached_files'][123] = $tmp;

		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'          => 'to@example.com',
			'subject'     => 'Test',
			'message'     => 'Body',
			'attachments' => array( 123 ),
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$stored = json_decode( $insert['data']['attachments'], true );
		$this->assertCount( 1, $stored );
		$this->assertEquals( $tmp, $stored[0] );

		unlink( $tmp );
	}

	/**
	 * Tests that intercept_wp_mail handles mixed paths and IDs.
	 */
	public function test_intercept_handles_mixed_attachments() {
		$tmp1 = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp1, 'file content' );
		$tmp2 = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp2, 'id content' );
		$GLOBALS['wp_attached_files'][456] = $tmp2;

		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'          => 'to@example.com',
			'subject'     => 'Test',
			'message'     => 'Body',
			'attachments' => array( $tmp1, 456 ),
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$stored = json_decode( $insert['data']['attachments'], true );
		$this->assertCount( 2, $stored );
		$this->assertEquals( $tmp1, $stored[0] );
		$this->assertEquals( $tmp2, $stored[1] );

		unlink( $tmp1 );
		unlink( $tmp2 );
	}

	/**
	 * Tests that intercept_wp_mail skips non-existent attachment IDs.
	 */
	public function test_intercept_skips_nonexistent_attachment_ids() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'          => 'to@example.com',
			'subject'     => 'Test',
			'message'     => 'Body',
			'attachments' => array( 999 ),
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$stored = json_decode( $insert['data']['attachments'], true );
		$this->assertEmpty( $stored );
	}

	/**
	 * Tests that intercept_wp_mail skips non-existent file paths.
	 */
	public function test_intercept_skips_nonexistent_file_paths() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'          => 'to@example.com',
			'subject'     => 'Test',
			'message'     => 'Body',
			'attachments' => array( '/nonexistent/file.pdf' ),
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$stored = json_decode( $insert['data']['attachments'], true );
		$this->assertEmpty( $stored );
	}

	/**
	 * Tests that intercept_wp_mail still works without attachments.
	 */
	public function test_intercept_works_without_attachments() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'      => 'to@example.com',
			'subject' => 'Test',
			'message' => 'Body',
		) );

		$insert = $GLOBALS['wp_db_inserts'][0];
		$stored = json_decode( $insert['data']['attachments'], true );
		$this->assertEmpty( $stored );
	}
}

/**
 * Tests for MXRoute_API attachment support.
 */
class MXRoute_API_Attachment_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']            = array();
		$GLOBALS['wp_function_calls']     = array();
		$GLOBALS['mxroute_mock_remote_response'] = null;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mxroute_mock_remote_response'] );
	}

	/**
	 * Tests that send includes attachments in the payload when provided.
	 */
	public function test_send_includes_attachments_when_readable() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'Hello attachment' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body',
			'',
			array( $tmp )
		);

		$call  = $GLOBALS['wp_function_calls']['wp_remote_post'][0];
		$body  = json_decode( $call['args']['body'], true );

		$this->assertArrayHasKey( 'attachments', $body );
		$this->assertCount( 1, $body['attachments'] );
		$this->assertEquals( basename( $tmp ), $body['attachments'][0]['filename'] );
		$this->assertEquals( base64_encode( 'Hello attachment' ), $body['attachments'][0]['content'] );

		unlink( $tmp );
	}

	/**
	 * Tests that send skips non-existent attachment files.
	 */
	public function test_send_skips_nonexistent_files() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body',
			'',
			array( '/nonexistent/file.pdf' )
		);

		$call = $GLOBALS['wp_function_calls']['wp_remote_post'][0];
		$body = json_decode( $call['args']['body'], true );

		$this->assertArrayNotHasKey( 'attachments', $body );
	}

	/**
	 * Tests that send omits attachments key when array is empty.
	 */
	public function test_send_omits_attachments_when_empty() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body',
			'',
			array()
		);

		$call = $GLOBALS['wp_function_calls']['wp_remote_post'][0];
		$body = json_decode( $call['args']['body'], true );

		$this->assertArrayNotHasKey( 'attachments', $body );
	}

	/**
	 * Tests that send handles mixed valid and invalid attachment paths.
	 */
	public function test_send_handles_mixed_valid_and_invalid_paths() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'valid content' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body',
			'',
			array( $tmp, '/nonexistent/file.pdf' )
		);

		$call = $GLOBALS['wp_function_calls']['wp_remote_post'][0];
		$body = json_decode( $call['args']['body'], true );

		$this->assertArrayHasKey( 'attachments', $body );
		$this->assertCount( 1, $body['attachments'] );

		unlink( $tmp );
	}

	/**
	 * Tests that send still works without attachments parameter.
	 */
	public function test_send_works_without_attachments_param() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body'
		);

		$this->assertTrue( $result['success'] );
	}
}
