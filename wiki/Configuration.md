# Configuration

## Accessing Settings

Go to **Settings > MXRoute Mailer** in your WordPress admin dashboard.

On multisite installations, only users with the `manage_network_options` capability can access the settings.

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

### Batch Size

Number of emails to process per 60-second cron cycle.

- Default: 5
- Range: 1-50
- Higher values process more emails per cycle

### Logging

When enabled, all sent emails are logged with full request and response data. View logs under **Tools > MXRoute Logs**.

### Keep Data

When checked, your logs and settings are preserved when the plugin is deleted. Uncheck to remove all data on uninstall.

## WP-CLI Configuration

You can also manage settings via WP-CLI:

```bash
# View all settings
wp mxroute option get

# View a specific setting
wp mxroute option get server
wp mxroute option get username
wp mxroute option get batch_size

# Update a setting
wp mxroute option set server chocobo.mxrouting.net
wp mxroute option set batch_size 10

# Enable/disable logging
wp mxroute option set logging_enabled 1
wp mxroute option set logging_enabled 0
```

**Note:** Passwords are stored encrypted. When you set a password via WP-CLI, it is automatically encrypted before storage.

## Test Email

Use the test email form to verify your configuration:

1. **To**: Enter the recipient email address
2. **Subject**: Defaults to "MXRoute Mailer Test"
3. **Body**: Defaults to "This is a test email from MXRoute Mailer."
4. **Options**: Check **Include file attachments** to send all three attachment types
5. Click **Send Test Email**

The response shows the queued status. The email is processed by the recurring WP-Cron event (every 60 seconds) — check the Queue page for delivery status.

When the attachment checkbox is checked, the test email includes three distinct attachment types to exercise all storage paths:

| Attachment | Type | Storage behavior |
|---|---|---|
| `test-attachment-media.txt` | Media library ID | Created via `wp_insert_attachment()`, re-resolved at send time |
| `test-attachment.txt` | Persistent file path | Referenced as-is, no copy |
| Temp file in `sys_get_temp_dir()` | Temp file (stored) | Copied to `wp-content/uploads/mxroute-mailer-attachments/` |

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
- **Transport** - How the email was sent: `api` (MXRoute HTTP API) or `smtp` (SMTP for attachments)
- **API Request** - JSON payload sent to MXRoute
- **API Response** - MXRoute's response

### WP-CLI Log Management

```bash
# List recent logs
wp mxroute logs list

# List with filters
wp mxroute logs list --status=1
wp mxroute logs list --per-page=10

# View a specific log
wp mxroute logs view 123

# Delete a log
wp mxroute logs delete 123

# Clear all processed logs
wp mxroute logs clear
```

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
- Re-queued emails move to the queue and are processed by the next cron cycle (within 60 seconds)

### Clearing Logs

Click **Clear All Logs** to delete all log entries. This action cannot be undone.

## Email Queue

### Viewing the Queue

Go to **Tools > MXRoute Queue** to see emails waiting to be sent.

### How the Queue Works

1. All outgoing emails are queued instead of sent immediately
2. A recurring WP-Cron event checks the queue every 60 seconds
3. The processor sends emails in batches via the MXRoute API or SMTP (smart-switch)
4. Each email is marked as sent or failed in the logs
5. Pending emails are hidden from the logs page (view on queue page)

### WP-CLI Queue Management

```bash
# Count pending items
wp mxroute queue count

# List pending items
wp mxroute queue list

# Clear all pending items
wp mxroute queue clear
```

## How Email is Sent

When any WordPress plugin or theme calls `wp_mail()`, MXRoute Mailer:

1. Intercepts the email via the `pre_wp_mail` filter before WordPress invokes the default mailer
2. Queues the email in the database
3. Returns `true` to WordPress so the default mailer is not invoked

A recurring WP-Cron event (every 60 seconds) then:
1. Fetches pending emails from the database in batches
2. Extracts the `From` header (if any), sanitizes it, and sets it as `Reply-To`
3. Uses your MXRoute username as the `From` address
4. Sanitizes the recipient (`To`) address
5. Applies the smart switch — chooses the best transport for each email:
   - **No attachments** → MXRoute HTTP API (lightweight, no server storage)
   - **Has attachments** → SMTP via PHPMailer (creates a copy in your MXRoute sent folder)
6. Logs the full request and response, including which transport was used
7. Marks the email as sent or failed

## Sending Emails via WP-CLI

You can send emails directly from the command line:

```bash
# Send an email directly via MXRoute API (bypasses queue)
wp mxroute send user@example.com "Subject" "Body"

# Send with custom From address
wp mxroute send user@example.com "Subject" "Body" --from=noreply@example.com

# Queue a test email (processed by cron)
wp mxroute test user@example.com
wp mxroute test user@example.com --subject="Custom Subject" --message="Custom body"
```

## Reply-To Support

When a plugin (like a contact form) sets a custom `From` header, the plugin:

- Uses your MXRoute username as the actual `From` address (for deliverability)
- Preserves the original sender address as the `Reply-To` header
- Stores the Reply-To address in the email logs

This means replies to contact form emails go to the person who submitted the form, not to your MXRoute username.

## Database

The plugin creates a database table `{prefix}_mxroute_mailer_logs` to store email logs. The table is created automatically on activation and updated automatically when new versions add columns. On multisite, each site gets its own table.

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
| attachments | longtext | JSON array of typed attachment references |
| api_request | longtext | JSON API request |
| api_response | longtext | JSON API response |
| success | tinyint(2) | 1 = sent, -1 = failed, 0 = pending |
| transport | varchar(10) | Transport method: 'api' or 'smtp' |
| created_at | datetime | When the queue entry was created |
| processed_at | datetime | When the email was sent or failed |
