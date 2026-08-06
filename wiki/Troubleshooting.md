# Troubleshooting

## Common Issues

### Emails Not Sending

**Symptoms**: Contact form submissions disappear, password reset emails never arrive.

**Causes and Fixes**:

1. **MXRoute credentials not configured**
   - Go to Settings > MXRoute Mailer
   - Verify server, username, and password are entered correctly
   - Click "Send Test Email" to test

2. **Wrong server hostname**
   - Check your MXRoute dashboard for the correct server hostname
   - Common format: `chocobo.mxrouting.net` or similar
   - Do not include `https://` or trailing slashes

3. **Wrong username**
   - Username is your full MXRoute email address (e.g., `you@mxroute.com`)
   - Do not enter just the local part unless the domain matches your site URL

4. **Password incorrect**
   - Reset your MXRoute password if unsure
   - Copy-paste to avoid typos

### Test Email Fails

**Check the queue status**:

The test email is queued and processed by WP-Cron. To check status:

1. Go to **Tools > MXRoute Queue** to see if the email is pending
2. Go to **Tools > MXRoute Logs** to see if it was sent or failed
3. If failed, click **View** to see the full API request and response details

Common failure reasons:
- `"message": "Authentication failed"` - Wrong username or password
- `"message": "Invalid server"` - Wrong server hostname
- `"message": "MX record not found"` - Domain DNS issue

### Emails Send but Recipient Doesn't Receive

1. **Check spam folder** - MXRoute emails may be flagged initially
2. **Check MXRoute logs** - Go to Tools > MXRoute Logs and look for the email
3. **Check API response** - Verify `"success": true` in the log details
4. **Domain reputation** - New MXRoute accounts may need time to build reputation

### Duplicate Emails

**Symptoms**: Recipients receive two copies of each email.

**Cause**: In versions before `1.2.16`, the plugin used the `wp_mail` filter to try to stop the default mailer, which did not fully short-circuit WordPress on all setups. As a result, the MXRoute API send and the server mailer (sendmail/ssmtp) could both send the same message.

**Fix**:
- Update to MXRoute Mailer `1.2.16` or later, which uses the `pre_wp_mail` filter to stop the default mailer before it runs.
- Check if you have multiple mail plugins active
- Disable other mail plugins one at a time to identify the conflict
- MXRoute Mailer runs at priority 999 on `pre_wp_mail` so it takes precedence when configured

### Log Viewer Shows "No logs found"

1. **Check if logging is enabled** - Go to Settings > MXRoute Mailer and verify logging is on
2. **Send a test email** - This creates a log entry
3. **Check the queue** - Pending emails appear under Tools > MXRoute Queue, not in logs
4. **Check database table** - The table `{prefix}_mxroute_mailer_logs` must exist
5. **Reactivate the plugin** - This recreates the table if it was dropped

### Emails Stuck in Queue

**Symptoms**: Emails appear on the Queue page but are never sent.

**Causes and Fixes**:

1. **WP-Cron not running** - A recurring WP-Cron event checks the queue every 60 seconds. Ensure WP-Cron is triggered by an external uptime monitor or page visits. Without traffic, WP-Cron won't fire.
2. **MXRoute credentials wrong** - Check Settings > MXRoute Mailer for correct server, username, password
3. **Batch size too small** - Increase batch size under Settings > MXRoute Mailer (more emails per 60-second cycle)
4. **Re-queue failed emails** - Go to Tools > MXRoute Logs, filter by Failed status, and re-queue

### Emails with Attachments Fail

**Symptoms**: Emails without attachments send fine, but emails with file attachments fail.

**Causes and Fixes**:

1. **PHPMailer class not found** - If the error log shows `Class "PHPMailer\PHPMailer\PHPMailer" not found`, update to v1.3.8 or later. Earlier versions did not load PHPMailer explicitly, which caused a fatal error when the SMTP path was triggered.
2. **SMTP ports blocked** - Emails with file attachments are sent via SMTP (PHPMailer) instead of the MXRoute HTTP API, because the MXRoute HTTP API does not support file attachments. If your hosting environment blocks outbound SMTP ports (465, 587, 2525), these emails will fail.
3. **Check which transport was used** - Go to Tools > MXRoute Logs, open the failed email, and check the Transport field. If it says `smtp`, the email was routed via SMTP (smart switch for attachments).
4. **Check attachment status** - The log detail page shows an Attachments section with storage status for each file. If any show "Missing", the stored copy was deleted before the email could be sent. Re-queue the email to try again.
5. **Remove attachments** - If SMTP ports cannot be opened, remove file attachments from the email. Emails without attachments use the MXRoute HTTP API (port 443) and are not affected by SMTP port blocks.

### Attachment Storage Issues

**Symptoms**: Log detail shows "Missing" for stored attachments, or queue entries show red attachment badges.

**Cause**: The plugin stores volatile (temp) file copies in `wp-content/uploads/mxroute-mailer-attachments/` to prevent loss before queue processing. Media library and persistent plugin files are referenced by native path/ID without copying.

**Fixes**:

1. **Check storage directory permissions** - The `wp-content/uploads/mxroute-mailer-attachments/` directory must be writable by the web server (750)
2. **Check disk space** - Large temp files may fail to copy if disk is full
3. **Re-queue the email** - From the Logs page, click Re-queue to re-attempt delivery with fresh attachment capture

### Plugin Causes White Screen

**Symptoms**: WordPress admin shows a blank white screen.

**Causes**:
- PHP version too old (requires 7.3+)
- Another plugin conflict
- Corrupted plugin files

**Fix**:
1. Check your PHP version: `php -v`
2. Disable the plugin via FTP: rename `mxroute-mailer` to `mxroute-mailer-disabled` in `/wp-content/plugins/`
3. Check PHP error logs for the specific error
4. Re-download the plugin from GitHub if files are corrupted

### "Permission Denied" Errors on Server

**For production servers using git**:

```bash
# Fix ownership
sudo chown -R www-data:www-data /path/to/wp-content/plugins/mxroute-mailer/

# Or use git with sudo
sudo git -C /path/to/wp-content/plugins/mxroute-mailer fetch --all
sudo git -C /path/to/wp-content/plugins/mxroute-mailer checkout v1.2.4
```

### Database Migration Not Running

If you see database errors about missing `reply_to` column:

1. Visit any WordPress admin page (triggers `admin_init` hook)
2. The migration runs automatically on admin init
3. If it still fails, manually run:
   ```sql
   ALTER TABLE {prefix}_mxroute_mailer_logs ADD COLUMN reply_to varchar(255) NOT NULL DEFAULT '' AFTER from_email;
   ```

### Plugin Deactivated After an Update

**Symptoms**: After clicking "Update Now", MXRoute Mailer disappears from the active plugins list and is replaced by a folder like `mxroute-mailer-v1.2.x/`.

**Cause**: An older release zip was missing a top-level `mxroute-mailer/` folder. WordPress extracted the files into a versioned folder and deactivated the plugin.

**Fix**:

1. Go to **Plugins** and deactivate the old `mxroute-mailer-v1.2.x/` entry if it exists
2. Delete the versioned folder from `/wp-content/plugins/`
3. Install the latest release from [GitHub Releases](https://github.com/richardkentgates/mxroute-mailer/releases)
4. Activate MXRoute Mailer

This issue is fixed in `v1.2.13` and later releases.

### Multisite: Tables Not Created on New Sites

**Symptoms**: New subsites cannot send email; `wp mxroute logs list` shows errors on a subsite.

**Cause**: The `wp_initialize_site` hook may not fire if the plugin was network-activated after the site was created.

**Fix**:

1. Network-deactivate and then network-activate MXRoute Mailer
2. This triggers the `wpmu_activate_site` handler, which creates the logs and queue tables for all existing sites
3. Alternatively, use WP-CLI on the affected site:
   ```bash
   wp mxroute option get server --url=https://affected-subsite.example.com
   ```
   If this returns an error, the tables are missing. Re-activating the plugin fixes it.

### Multisite: Cannot Access Settings

**Symptoms**: The MXRoute Mailer menu does not appear under Settings on a subsite.

**Cause**: On multisite, the plugin requires `manage_network_options` capability (super admin). Per-site admins cannot configure MXRoute Mailer.

**Fix**:

- Log in as a Network Admin
- Go to **Network Admin > Settings > MXRoute Mailer** to configure credentials
- All subsites share the same MXRoute API credentials

### Multisite: Settings Not Shared Across Sites

**Symptoms**: One subsite sends email successfully but another does not.

**Cause**: MXRoute Mailer stores settings per-site. Each subsite needs its own credentials configured.

**Fix**:

1. Go to **Network Admin > Settings > MXRoute Mailer**
2. Configure credentials for each subsite that needs to send email
3. Or use WP-CLI to set credentials per-site:
   ```bash
   wp mxroute option set server chocobo.mxrouting.net --url=https://subsite.example.com
   wp mxroute option set username you@example.com --url=https://subsite.example.com
   wp mxroute option set password 'your-password' --url=https://subsite.example.com
   ```

### WP-CLI: "Cannot open" Error

**Symptoms**: `wp mxroute` returns `Error: Cannot open` or similar.

**Cause**: WP-CLI cannot load the CLI class because the plugin is not active or `WP_CLI` is not defined.

**Fix**:

1. Verify WP-CLI is installed: `wp --info`
2. Verify the plugin is active: `wp plugin status mxroute-mailer`
3. If using a custom WP-CLI bootstrap, ensure `WP_CLI` is defined before plugins load

### WP-CLI: Password Visible in Output

**Symptoms**: `wp mxroute option get password` shows encrypted string or plaintext.

**Behavior**: The CLI masks the password output as `***` for security. If you see the raw encrypted value, you are running an older version of the plugin (update to v1.4.0+).

To set the password via CLI:

```bash
wp mxroute option set password 'your-new-password'
```

## Getting Help

If your issue isn't listed here:

1. Check the [GitHub Issues](https://github.com/richardkentgates/mxroute-mailer/issues) for similar problems
2. Open a new issue with:
   - WordPress version
   - PHP version
   - Plugin version
   - Steps to reproduce
   - Error messages or logs
   - API response from the test email form

## Debugging Tips

### Enable WordPress Debug Log

Add to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check `/wp-content/debug.log` for errors.

### Enable Plugin Debug Logging

To log MXRoute API requests and responses (without passwords), add to `wp-config.php`:

```php
define( 'MXROUTE_MAILER_DEBUG', true );
```

This writes API send attempts and responses to the WordPress debug log. **Never leave this enabled in production** — it adds I/O overhead on every email send.

### Verify Password Decryption

If emails suddenly fail after a plugin update, the stored password may not be decrypting correctly. Test decryption by adding a temporary script in the WordPress root:

```php
<?php
require_once 'wp-load.php';
$pw = get_option( 'mxroute_mailer_password', '' );
$dec = MXRoute_Crypto::decrypt( $pw );
echo 'Decrypted: ' . ( $dec !== $pw ? 'YES' : 'NO (raw value returned)' ) . "\n";
echo 'Length: ' . strlen( $dec ) . "\n";
```

**Key indicators:**
- If decryption fails (returns raw value), the password is stored as plaintext or the encryption key changed
- If the decrypted password looks like a URL or garbage, the wrong value was saved
- If decryption succeeds but MXRoute rejects it, verify the password against your MXRoute dashboard

### Check MXRoute API Directly

Test your credentials with curl:

```bash
curl -X POST https://smtpapi.mxroute.com/ \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic $(echo -n 'username:password' | base64)" \
  -d '{
    "server": "your-server.mxrouting.net",
    "username": "you@mxroute.com",
    "password": "your-password",
    "from": "you@mxroute.com",
    "to": "test@example.com",
    "subject": "API Test",
    "body": "Test email from API"
  }'
```

### Check Database Table

```sql
-- Check if table exists
SHOW TABLES LIKE '{prefix}_mxroute_mailer_logs';

-- Check table structure
DESCRIBE {prefix}_mxroute_mailer_logs;

-- Check recent logs
SELECT * FROM {prefix}_mxroute_mailer_logs ORDER BY id DESC LIMIT 10;
```
