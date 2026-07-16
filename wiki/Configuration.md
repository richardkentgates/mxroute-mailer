# Configuration

## Accessing Settings

Go to **Settings > MXRoute Mailer** in your WordPress admin dashboard.

## Server Settings

### Server

Your MXRoute server hostname. This is provided by MXRoute when you create your account.

Example: `chocobo.mxrouting.net`

### Username

Your MXRoute email address. This is also used as the **From** address for all emails sent through the plugin.

Example: `you@mxroute.com`

The username field displays your full email address (e.g., `you@example.com`) with the domain derived from your WordPress site URL. If you enter just the local part (e.g., `you`), the domain is appended automatically.

### Password

Your MXRoute account password. It is encrypted with AES-256-GCM using your site's WordPress auth salt before being stored in the WordPress database. The password is only decrypted when the plugin sends an email.

## Test Email

Use the test email form to verify your configuration:

1. **To**: Enter the recipient email address
2. **Subject**: Defaults to "MXRoute Mailer Test"
3. **Body**: Defaults to "This is a test email from MXRoute Mailer."
4. Click **Send Test Email**

The response shows the full API request and response, which is useful for debugging.

## Email Logging

### Viewing Logs

Go to **Tools > MXRoute Logs** to view all emails sent through the plugin.

### Log Details

Each log entry includes:
- **Timestamp** - When the email was sent
- **Status** - Success or failure
- **From** - The MXRoute username (sender address)
- **Reply-To** - The original sender address (if different from From)
- **To** - Recipient email address
- **Subject** - Email subject line
- **Message** - Full email body
- **API Request** - JSON payload sent to MXRoute
- **API Response** - MXRoute's response

### Filtering Logs

You can filter logs by:
- **Search** - Subject, from, to, or reply-to address
- **Status** - Success or failed
- **From email** - Filter by sender address
- **Date range** - Filter by date

### Clearing Logs

Click **Clear All Logs** to delete all log entries. This action cannot be undone.

## How Email is Sent

When any WordPress plugin or theme calls `wp_mail()`, MXRoute Mailer:

1. Intercepts the email via the `pre_wp_mail` filter before WordPress invokes the default mailer
2. Extracts the `From` header (if any), sanitizes it, and sets it as `Reply-To`
3. Uses your MXRoute username as the `From` address
4. Sanitizes the recipient (`To`) address before building the API request
5. Sends the email through MXRoute's HTTP API
6. Logs the full request and response
7. Returns `true` to WordPress so the default mailer (sendmail/ssmtp/PHPMailer) is not also invoked

If the MXRoute API call fails, the plugin returns `false` and fires the `wp_mail_failed` action. If MXRoute Mailer is not configured, the plugin returns `null` and lets WordPress fall back to the default mailer.

## Reply-To Support

When a plugin (like a contact form) sets a custom `From` header, the plugin:

- Uses your MXRoute username as the actual `From` address (for deliverability)
- Preserves the original sender address as the `Reply-To` header
- Stores the Reply-To address in the email logs

This means replies to contact form emails go to the person who submitted the form, not to your MXRoute username.

## Database

The plugin creates a database table `{prefix}_mxroute_mailer_logs` to store email logs. The table is created automatically on activation and updated automatically when new versions add columns.

### Table Schema

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Auto-increment primary key |
| timestamp | datetime | When the email was sent |
| from_email | varchar(255) | Sender address (MXRoute username) |
| reply_to | varchar(255) | Original sender address |
| to_email | varchar(255) | Recipient address |
| subject | varchar(255) | Email subject |
| message | longtext | Email body |
| api_request | longtext | JSON API request |
| api_response | longtext | JSON API response |
| success | tinyint(1) | 1 = success, 0 = failure |
