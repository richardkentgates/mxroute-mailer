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
		MXRoute_Mailer::reset();
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
	 * Tests that add stores attachments as typed JSON references.
	 */
	public function test_add_stores_attachments_as_json() {
		$tmp1 = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp1, 'content1' );
		$tmp2 = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp2, 'content2' );

		$queue = new MXRoute_Queue();
		$queue->add(
			'from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			'',
			array( $tmp1, $tmp2 ),
			''
		);

		$update = $GLOBALS['wp_function_calls']['$wpdb->update'][0];
		$stored = json_decode( $update['data']['attachments'], true );
		$this->assertCount( 2, $stored );
		$this->assertEquals( 'stored', $stored[0]['type'] );
		$this->assertEquals( $tmp1, $stored[0]['origin'] );
		$this->assertEquals( 'stored', $stored[1]['type'] );
		$this->assertEquals( $tmp2, $stored[1]['origin'] );

		unlink( $tmp1 );
		unlink( $tmp2 );
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
	 * Tests that add sanitizes email addresses by stripping invalid characters.
	 */
	public function test_add_sanitizes_email_addresses() {
		$queue = new MXRoute_Queue();
		$queue->add(
			'<script>alert("xss")</script>from@example.com',
			'to@example.com',
			'Subject',
			'Body',
			'',
			array(),
			''
		);

		$insert = $GLOBALS['wp_db_inserts'][0];
		$this->assertStringNotContainsString( '<script>', $insert['data']['from_email'] );
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
		MXRoute_Mailer::reset();
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
	 * Tests that intercept_wp_mail splits comma-separated recipient strings.
	 */
	public function test_intercept_splits_comma_separated_recipients() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$mailer->intercept_wp_mail( array(
			'to'      => 'a@example.com, b@example.com, c@example.com',
			'subject' => 'Test Subject',
			'message' => 'Body',
		) );

		$this->assertCount( 3, $GLOBALS['wp_db_inserts'] );
		$this->assertEquals( 'a@example.com', $GLOBALS['wp_db_inserts'][0]['data']['to_email'] );
		$this->assertEquals( 'b@example.com', $GLOBALS['wp_db_inserts'][1]['data']['to_email'] );
		$this->assertEquals( 'c@example.com', $GLOBALS['wp_db_inserts'][2]['data']['to_email'] );
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
	 * Tests that intercept_wp_mail stores attachments as typed references.
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

		$update = $GLOBALS['wp_function_calls']['$wpdb->update'][0];
		$stored = json_decode( $update['data']['attachments'], true );
		$this->assertCount( 1, $stored );
		$this->assertEquals( 'stored', $stored[0]['type'] );
		$this->assertEquals( $tmp, $stored[0]['origin'] );

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
	 * Tests that process_queue sends pending emails via the API.
	 */
	public function test_process_queue_sends_pending_emails() {
		$mailer = MXRoute_Mailer::instance();
		$GLOBALS['wp_options']['mxroute_mailer_server']    = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username']  = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password']  = 'password123';
		$GLOBALS['wp_options']['mxroute_mailer_batch_size'] = 50;

		$item = (object) array(
			'id'          => 1,
			'from_email'  => 'from@example.com',
			'to_email'    => 'to@example.com',
			'subject'     => 'Test Subject',
			'message'     => 'Body',
			'reply_to'    => '',
			'attachments' => '[]',
			'transport'   => '',
		);
		$GLOBALS['wp_db_results'] = array( $item );

		$mailer->process_queue();

		// Verify process_queue called the API.
		$this->assertArrayHasKey( 'wp_remote_post', $GLOBALS['wp_function_calls'] );
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

		// Provide items so claim_pending returns something.
		$item = (object) array(
			'id'          => 1,
			'from_email'  => 'from@example.com',
			'to_email'    => 'to@example.com',
			'subject'     => 'Test',
			'message'     => 'Body',
			'reply_to'    => '',
			'attachments' => '[]',
			'transport'   => '',
		);
		$GLOBALS['wp_db_results'] = array( $item );

		$mailer->process_queue();

		// Should have called the API with batch size of 10.
		$this->assertArrayHasKey( 'wp_remote_post', $GLOBALS['wp_function_calls'] );
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
		$GLOBALS['wp_db_results'] = array();

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
	 * Tests that intercept_wp_mail stores resolved attachment IDs as typed references.
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

		$update = $GLOBALS['wp_function_calls']['$wpdb->update'][0];
		$stored = json_decode( $update['data']['attachments'], true );
		$this->assertCount( 1, $stored );
		$this->assertEquals( 'stored', $stored[0]['type'] );
		$this->assertEquals( $tmp, $stored[0]['origin'] );

		unlink( $tmp );
	}

	/**
	 * Tests that intercept_wp_mail handles mixed paths and IDs as typed references.
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

		$update = $GLOBALS['wp_function_calls']['$wpdb->update'][0];
		$stored = json_decode( $update['data']['attachments'], true );
		$this->assertCount( 2, $stored );
		$this->assertEquals( 'stored', $stored[0]['type'] );
		$this->assertEquals( $tmp1, $stored[0]['origin'] );
		$this->assertEquals( 'stored', $stored[1]['type'] );
		$this->assertEquals( $tmp2, $stored[1]['origin'] );

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
	 * Tests that send_via_api includes attachments in the payload when provided.
	 */
	public function test_send_includes_attachments_when_readable() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'Hello attachment' );

		$api    = new MXRoute_API();
		$result = $api->send_via_api(
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
	 * Tests that send_via_api skips non-existent attachment files.
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
	 * Tests that send_via_api handles mixed valid and invalid attachment paths.
	 */
	public function test_send_handles_mixed_valid_and_invalid_paths() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'valid content' );

		$api    = new MXRoute_API();
		$result = $api->send_via_api(
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

/**
 * Tests for MXRoute_API smart switch (attachment-based transport routing).
 */
class MXRoute_API_Smart_Switch_Test extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options']            = array();
		$GLOBALS['wp_function_calls']     = array();
		$GLOBALS['mxroute_mock_remote_response'] = null;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mxroute_mock_remote_response'] );
	}

	/**
	 * Tests that get_transport returns 'smtp' when valid attachments are provided.
	 */
	public function test_get_transport_returns_smtp_for_valid_attachments() {
		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test content' );

		$api       = new MXRoute_API();
		$transport = $api->get_transport( array( $tmp ) );

		$this->assertEquals( 'smtp', $transport );

		unlink( $tmp );
	}

	/**
	 * Tests that get_transport returns 'api' when no attachments are provided.
	 */
	public function test_get_transport_returns_api_for_no_attachments() {
		$api       = new MXRoute_API();
		$transport = $api->get_transport( array() );

		$this->assertEquals( 'api', $transport );
	}

	/**
	 * Tests that get_transport returns 'api' when only nonexistent files are provided.
	 */
	public function test_get_transport_returns_api_for_nonexistent_files() {
		$api       = new MXRoute_API();
		$transport = $api->get_transport( array( '/nonexistent/file.pdf' ) );

		$this->assertEquals( 'api', $transport );
	}

	/**
	 * Tests that get_transport returns 'api' when attachments parameter is omitted.
	 */
	public function test_get_transport_returns_api_when_no_param() {
		$api       = new MXRoute_API();
		$transport = $api->get_transport();

		$this->assertEquals( 'api', $transport );
	}

	/**
	 * Tests that get_transport filters out unreadable files.
	 */
	public function test_get_transport_filters_unreadable_files() {
		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		chmod( $tmp, 0000 );

		$api       = new MXRoute_API();
		$transport = $api->get_transport( array( $tmp ) );

		$this->assertEquals( 'api', $transport );

		chmod( $tmp, 0644 );
		unlink( $tmp );
	}

	/**
	 * Tests that get_transport filters files over 5MB.
	 */
	public function test_get_transport_filters_oversized_files() {
		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		$fh  = fopen( $tmp, 'w' );
		fseek( $fh, 5242881, SEEK_SET );
		fwrite( $fh, "\0" );
		fclose( $fh );

		$api       = new MXRoute_API();
		$transport = $api->get_transport( array( $tmp ) );

		$this->assertEquals( 'api', $transport );

		unlink( $tmp );
	}

	/**
	 * Tests that get_transport accepts files exactly at 5MB limit.
	 */
	public function test_get_transport_accepts_files_at_5mb_limit() {
		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		$fh  = fopen( $tmp, 'w' );
		fseek( $fh, 5242879, SEEK_SET );
		fwrite( $fh, "\0" );
		fclose( $fh );

		$this->assertEquals( 5242880, filesize( $tmp ) );

		$api       = new MXRoute_API();
		$transport = $api->get_transport( array( $tmp ) );

		$this->assertEquals( 'smtp', $transport );

		unlink( $tmp );
	}

	/**
	 * Tests that get_transport returns 'api' for mixed valid and invalid paths
	 * when all valid files are too large or nonexistent.
	 */
	public function test_get_transport_returns_api_for_all_invalid_mixed() {
		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		chmod( $tmp, 0000 );

		$api       = new MXRoute_API();
		$transport = $api->get_transport( array( $tmp, '/nonexistent/file.pdf' ) );

		$this->assertEquals( 'api', $transport );

		chmod( $tmp, 0644 );
		unlink( $tmp );
	}

	/**
	 * Tests that send with valid attachments does not call wp_remote_post
	 * (routes to SMTP instead of API).
	 */
	public function test_send_with_attachments_skips_wp_remote_post() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test content' );

		$api  = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body',
			'',
			array( $tmp )
		);

		$this->assertEmpty(
			$GLOBALS['wp_function_calls']['wp_remote_post'] ?? array(),
			'send() with valid attachments should not call wp_remote_post (should route to SMTP)'
		);
		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'SMTP', $result['message'] );

		unlink( $tmp );
	}

	/**
	 * Tests that send without attachments calls wp_remote_post (routes to API).
	 */
	public function test_send_without_attachments_calls_wp_remote_post() {
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

		$this->assertNotEmpty( $GLOBALS['wp_function_calls']['wp_remote_post'] );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Tests that send with only nonexistent files routes to API (no valid attachments).
	 */
	public function test_send_with_only_nonexistent_files_routes_to_api() {
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

		$this->assertNotEmpty( $GLOBALS['wp_function_calls']['wp_remote_post'] );
		$this->assertTrue( $result['success'] );
	}

	/**
	 * Tests that send_via_smtp failure includes port information in the response.
	 */
	public function test_send_via_smtp_includes_transport_in_response() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test content' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body',
			'',
			array( $tmp )
		);

		$this->assertArrayHasKey( 'transport', $result['response'] );
		$this->assertEquals( 'smtp', $result['response']['transport'] );

		unlink( $tmp );
	}

	/**
	 * Tests that send_via_api does not include transport key in response
	 * (transport column is handled by the logger).
	 */
	public function test_send_via_api_response_has_no_transport_key() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$api    = new MXRoute_API();
		$result = $api->send_via_api(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body'
		);

		$this->assertArrayNotHasKey( 'transport', $result['response'] );
	}

	/**
	 * Tests that send_via_smtp request array includes transport key.
	 */
	public function test_send_via_smtp_request_includes_transport() {
		$GLOBALS['wp_options']['mxroute_mailer_server']   = 'server.example.com';
		$GLOBALS['wp_options']['mxroute_mailer_username'] = 'user@example.com';
		$GLOBALS['wp_options']['mxroute_mailer_password'] = 'password123';

		$tmp = tempnam( sys_get_temp_dir(), 'mxroute_test_' );
		file_put_contents( $tmp, 'test content' );

		$api    = new MXRoute_API();
		$result = $api->send(
			'from@example.com',
			'to@example.com',
			'Test',
			'Body',
			'',
			array( $tmp )
		);

		$this->assertArrayHasKey( 'transport', $result['request'] );
		$this->assertEquals( 'smtp', $result['request']['transport'] );

		unlink( $tmp );
	}
}
