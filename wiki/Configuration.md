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

Go to **Tools > MXRoute Logs** to view all processed emails (sent and failed). Pending emails in the queue are shown separately under **Tools > MXRoute Queue**.

### Log Details

Each log entry includes:
- **Timestamp** - When the email was sent
- **Status** - Sent, Failed, or Pending (pending shown on queue page only)
- **From** - The MXRoute username (sender address)
- **Reply-To** - The original sender address (if different from From)
- **To** - Recipient email address
- **Subject** - Email subject line
- **Message** - Full email body
- **Headers** - Email headers passed to the API
- **Attachments** - File attachments (supports file paths and WordPress attachment IDs)
- **API Request** - JSON payload sent to MXRoute
- **API Response** - MXRoute's response

### Filtering Logs

You can filter logs by:
- **Search** - Subject, from, to, or reply-to address
- **Status** - Sent or failed
- **From email** - Filter by sender address
- **Date range** - Filter by date

### Re-queue Feature

From the logs page, you can re-queue any sent or failed email to be sent again:
- Click the **Re-queue** button on any log entry
- Use bulk actions to re-queue multiple entries at once
- Re-queued emails move to the queue and are processed by WP-Cron
- The log entry is removed from the logs view (appears on queue page)

### Clearing Logs

Click **Clear All Logs** to delete all log entries. This action cannot be undone.

## Email Queue

### Viewing the Queue

Go to **Tools > MXRoute Queue** to see emails waiting to be sent.

### Adding Emails to Queue

You can manually add emails to the queue from the queue page:
1. Fill in the From, To, Subject, and Body fields
2. Click **Add to Queue**
3. The email is processed by WP-Cron in the background

### Batch Size

The queue processes emails in batches. Configure the batch size under **Settings > MXRoute Mailer**:
- Default: 50 emails per batch
- Range: 1-500 emails per batch
- Higher values process more emails per cron run

### How the Queue Works

1. All outgoing emails are queued instead of sent immediately
2. WP-Cron triggers the queue processor in the background
3. The processor sends emails in batches via the MXRoute API
4. Each email is marked as sent or failed in the logs
5. Pending emails are hidden from the logs page (view on queue page)

## How Email is Sent

When any WordPress plugin or theme calls `wp_mail()`, MXRoute Mailer:

1. Intercepts the email via the `pre_wp_mail` filter before WordPress invokes the default mailer
2. Queues the email for background processing via WP-Cron
3. Schedules a single WP-Cron event to process the queue
4. Returns `true` to WordPress so the default mailer is not invoked

The queue processor then:
1. Fetches pending emails from the database in batches
2. Extracts the `From` header (if any), sanitizes it, and sets it as `Reply-To`
3. Uses your MXRoute username as the `From` address
4. Sanitizes the recipient (`To`) address
5. Encodes any file attachments as base64 (resolving WordPress attachment IDs to file paths automatically)
6. Sends the email through MXRoute's HTTP API
7. Logs the full request and response
8. Marks the email as sent or failed

If the MXRoute API call fails, the email is marked as failed in the logs. You can re-queue it from the logs page to try again. If MXRoute Mailer is not configured, the plugin returns `null` and lets WordPress fall back to the default mailer.

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
| timestamp | datetime | When the email was created |
| from_email | varchar(255) | Sender address (MXRoute username) |
| reply_to | varchar(255) | Original sender address |
| to_email | varchar(255) | Recipient address |
| subject | varchar(255) | Email subject |
| message | longtext | Email body |
| headers | longtext | Email headers |
| attachments | longtext | JSON array of file paths (IDs resolved before storage) |
| api_request | longtext | JSON API request |
| api_response | longtext | JSON API response |
| success | tinyint(2) | 1 = sent, -1 = failed, 0 = pending |
| created_at | datetime | When the queue entry was created |
| processed_at | datetime | When the email was sent or failed |
