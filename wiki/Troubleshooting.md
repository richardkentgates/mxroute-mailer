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

**Check the API response**:

The test email form shows the full API response. Look for:

- `"success": true` - Email sent successfully
- `"success": false` with `"message": "Authentication failed"` - Wrong username or password
- `"success": false` with `"message": "Invalid server"` - Wrong server hostname
- `"success": false` with `"message": "MX record not found"` - Domain DNS issue

### Emails Send but Recipient Doesn't Receive

1. **Check spam folder** - MXRoute emails may be flagged initially
2. **Check MXRoute logs** - Go to Tools > MXRoute Logs and look for the email
3. **Check API response** - Verify `"success": true` in the log details
4. **Domain reputation** - New MXRoute accounts may need time to build reputation

### Duplicate Emails

**Symptoms**: Recipients receive two copies of each email.

**Cause**: Another plugin is also modifying `wp_mail()` at a different priority.

**Fix**:
- Check if you have multiple mail plugins active
- Disable other mail plugins one at a time to identify the conflict
- MXRoute Mailer runs at priority 999 (after all other filters)

### Log Viewer Shows "No logs found"

1. **Check if logging is enabled** - Go to Settings > MXRoute Mailer and verify logging is on
2. **Send a test email** - This creates a log entry
3. **Check database table** - The table `{prefix}_mxroute_mailer_logs` must exist
4. **Reactivate the plugin** - This recreates the table if it was dropped

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
sudo chown -R www-data:www-data /srv/www/wordpress/wp-content/plugins/mxroute-mailer/

# Or use git with sudo
sudo git -C /srv/www/wordpress/wp-content/plugins/mxroute-mailer fetch --all
sudo git -C /srv/www/wordpress/wp-content/plugins/mxroute-mailer checkout v1.2.4
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
