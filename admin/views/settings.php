<?php
/**
 * MXRoute Mailer settings page view.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap mxroute-settings-wrap">
	<h1><?php esc_html_e( 'MXRoute Mailer Settings', 'mxroute-mailer' ); ?></h1>

	<?php if ( isset( $_GET['test_sent'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Test email sent. Check the result below.', 'mxroute-mailer' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	$test_result = get_transient( 'mxroute_test_email_result' );
	if ( $test_result ) :
		?>
		<div class="notice <?php echo esc_attr( $test_result['success'] ? 'notice-success' : 'notice-error' ); ?> is-dismissible">
			<p><strong><?php echo esc_html( $test_result['success'] ? __( 'Sent', 'mxroute-mailer' ) : __( 'Failed', 'mxroute-mailer' ) ); ?>:</strong> <?php echo esc_html( $test_result['message'] ); ?></p>
			<?php if ( ! empty( $test_result['response'] ) ) : ?>
				<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $test_result['response'], JSON_PRETTY_PRINT ) ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
		delete_transient( 'mxroute_test_email_result' );
	endif;
	?>

	<form method="post" action="options.php" class="mxroute-settings-form">
		<?php settings_fields( 'mxroute_mailer_settings' ); ?>

		<h2><?php esc_html_e( 'MXRoute Credentials', 'mxroute-mailer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Enter your MXRoute SMTP API credentials. Found in your MXRoute control panel under Email Clients.', 'mxroute-mailer' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="mxroute_mailer_server"><?php esc_html_e( 'Server Hostname', 'mxroute-mailer' ); ?></label></th>
				<td>
					<input type="text" id="mxroute_mailer_server" name="mxroute_mailer_server"
							value="<?php echo esc_attr( get_option( 'mxroute_mailer_server', '' ) ); ?>"
							class="regular-text" placeholder="<?php esc_attr_e( 'e.g. tuesday.mxrouting.net', 'mxroute-mailer' ); ?>" />
					<p class="description"><?php esc_html_e( 'Your MXRoute server hostname (from control panel > Email Clients).', 'mxroute-mailer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mxroute_mailer_username"><?php esc_html_e( 'Username', 'mxroute-mailer' ); ?></label></th>
				<td>
					<input type="text" id="mxroute_mailer_username" name="mxroute_mailer_username"
							value="<?php echo esc_attr( get_option( 'mxroute_mailer_username', '' ) ); ?>"
							class="regular-text" placeholder="<?php esc_attr_e( 'e.g. user@yourdomain.com', 'mxroute-mailer' ); ?>" />
					<p class="description"><?php esc_html_e( 'Your full email address or SMTP username.', 'mxroute-mailer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mxroute_mailer_password"><?php esc_html_e( 'Password', 'mxroute-mailer' ); ?></label></th>
				<td>
					<?php
					$password_set = '' !== get_option( 'mxroute_mailer_password', '' );
					$placeholder  = $password_set ? __( 'Password is set. Leave blank to keep current.', 'mxroute-mailer' ) : '';
					?>
					<input type="password" id="mxroute_mailer_password" name="mxroute_mailer_password"
							value="" class="regular-text"
							placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mxroute_mailer_default_from"><?php esc_html_e( 'Default From Email', 'mxroute-mailer' ); ?></label></th>
				<td>
					<input type="email" id="mxroute_mailer_default_from" name="mxroute_mailer_default_from"
							value="<?php echo esc_attr( get_option( 'mxroute_mailer_default_from', get_option( 'admin_email' ) ) ); ?>"
							class="regular-text" />
					<p class="description"><?php esc_html_e( 'Used when no From header is specified in wp_mail() calls.', 'mxroute-mailer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Logging', 'mxroute-mailer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mxroute_mailer_logging_enabled" value="1"
								<?php checked( get_option( 'mxroute_mailer_logging_enabled', 1 ), 1 ); ?> />
						<?php esc_html_e( 'Log all sent emails (request and response data)', 'mxroute-mailer' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'mxroute-mailer' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mxroute_mailer_keep_data" value="1"
								<?php checked( get_option( 'mxroute_mailer_keep_data', 0 ), 1 ); ?> />
						<?php esc_html_e( 'Keep logs and settings when plugin is deleted', 'mxroute-mailer' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'mxroute-mailer' ) ); ?>

		<p class="submit" style="margin-left: 10px; display: inline-block;">
			<button type="button" id="mxroute-test-connection" class="button"><?php esc_html_e( 'Test Connection', 'mxroute-mailer' ); ?></button>
			<span id="mxroute-test-result" style="margin-left: 10px;"></span>
		</p>
	</form>

	<hr />

	<h2><?php esc_html_e( 'Send Test Email', 'mxroute-mailer' ); ?></h2>
	<form method="post" class="mxroute-test-form">
		<?php wp_nonce_field( 'mxroute_test_email', 'mxroute_test_email_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="mxroute_test_to"><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></label></th>
				<td>
					<input type="email" id="mxroute_test_to" name="mxroute_test_to" required
							class="regular-text" placeholder="<?php esc_attr_e( 'recipient@example.com', 'mxroute-mailer' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mxroute_test_from"><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></label></th>
				<td>
					<input type="email" id="mxroute_test_from" name="mxroute_test_from" required
							class="regular-text" placeholder="<?php esc_attr_e( 'sender@yourdomain.com', 'mxroute-mailer' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mxroute_test_subject"><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></label></th>
				<td>
					<input type="text" id="mxroute_test_subject" name="mxroute_test_subject"
							class="regular-text" value="<?php echo esc_attr( __( 'MXRoute Mailer Test', 'mxroute-mailer' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mxroute_test_body"><?php esc_html_e( 'Body', 'mxroute-mailer' ); ?></label></th>
				<td>
					<textarea id="mxroute_test_body" name="mxroute_test_body" rows="4" class="large-text"><?php echo esc_textarea( __( 'This is a test email from MXRoute Mailer.', 'mxroute-mailer' ) ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Send Test Email', 'mxroute-mailer' ) ); ?>
	</form>
</div>
