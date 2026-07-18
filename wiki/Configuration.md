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
4. **Options**: Check **Include file attachments** to send all three attachment types
5. Click **Send Test Email**

The response shows the queued status. The email is processed in the background by WP-Cron — check the Queue page for delivery status.

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
- **Transport** - How the email was sent: `api` (MXRoute HTTP API for simple emails) or `smtp` (SMTP for emails with attachments, creates a copy in the sent folder)
- **API Request** - JSON payload sent to MXRoute
- **API Response** - MXRoute's response

### Attachment Details

The log detail page shows an Attachments section when attachments are present. Each attachment displays:

| Field | Description |
|-------|-------------|
| Type | `Media ID 123` (WordPress media library), `Temp file (stored)` (volatile file copied to storage), or `File` (persistent file path) |
| Original Path | The source file path or media library resolution |
| Stored | `OK` (copy exists in persistent storage), `Missing` (copy was deleted), or `N/A` (no copy needed — native reference) |

**How attachment storage works:**

The plugin handles three types of attachments intelligently to avoid unnecessary file duplication:

1. **Media library IDs** — Referenced by ID only, re-resolved from WordPress at send time. No copy created — media files are already persistent in `wp-content/uploads/`.

2. **File paths in temp directories** (e.g., `/tmp/`) — Copied to persistent storage at `wp-content/uploads/mxroute-mailer-attachments/`. These files are volatile and may be deleted before the queue processes, so a copy ensures reliable delivery.

3. **File paths in persistent locations** (e.g., plugin upload directories) — Referenced by path only. No copy created — the file is already in a stable location.

The queue page also shows attachment status for each pending entry, with a count and storage indicator (green badge for all stored, red badge if any are missing).

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

### Batch Size

The queue processes emails in batches. Configure the batch size under **Settings > MXRoute Mailer**:
- Default: 5 emails per batch
- Range: 1-50 emails per batch
- Higher values process more emails per cron run

### How the Queue Works

1. All outgoing emails are queued instead of sent immediately
2. WP-Cron triggers the queue processor in the background
3. The processor sends emails in batches via the MXRoute API or SMTP (smart-switch)
4. Each email is marked as sent or failed in the logs
5. Pending emails are hidden from the logs page (view on queue page)

## How Email is Sent

When any WordPress plugin or theme calls `wp_mail()`, MXRoute Mailer:

1. Intercepts the email via the `pre_wp_mail` filter before WordPress invokes the default mailer
2. Queues the email for background processing via WP-Cron
3. Schedules a single WP-Cron event to process the queue
4. Returns `false` to WordPress so the default mailer is not invoked

The queue processor then:
1. Fetches pending emails from the database in batches
2. Extracts the `From` header (if any), sanitizes it, and sets it as `Reply-To`
3. Uses your MXRoute username as the `From` address
4. Sanitizes the recipient (`To`) address
5. Applies the smart switch — chooses the best transport for each email:
   - **No attachments** → MXRoute HTTP API (lightweight, no server storage)
   - **Has attachments** → SMTP via PHPMailer (creates a copy in your MXRoute sent folder for legal records and documentation)
6. Logs the full request and response, including which transport was used
7. Marks the email as sent or failed

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
| attachments | longtext | JSON array of typed attachment references (id, path, or stored) |
| api_request | longtext | JSON API request |
| api_response | longtext | JSON API response |
| success | tinyint(2) | 1 = sent, -1 = failed, 0 = pending |
| transport | varchar(10) | Transport method: 'api' or 'smtp' |
| created_at | datetime | When the queue entry was created |
| processed_at | datetime | When the email was sent or failed |
