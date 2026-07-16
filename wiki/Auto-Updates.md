# Auto-Updates

MXRoute Mailer includes a GitHub-based auto-updater that checks for new releases and allows you to update directly from your WordPress dashboard.

## How It Works

The plugin uses WordPress's built-in plugin update system with a custom updater that:

1. Checks the GitHub API for new releases periodically
2. Compares the latest release version with your installed version
3. Shows an update notification in your WordPress dashboard when a new version is available
4. Downloads and installs the update when you click "Update Now"

## Checking for Updates

WordPress checks for plugin updates automatically (typically every 12 hours). To check manually:

1. Go to **Dashboard > Updates**
2. Scroll down to the Plugins section
3. MXRoute Mailer will appear if an update is available

Alternatively, go to **Plugins** and click **Check for updates**.

## Installing Updates

### Via WordPress Dashboard

1. When an update is available, you'll see a notification on the Plugins page
2. Click **Update Now** next to MXRoute Mailer
3. The update downloads from GitHub and installs automatically

### Via WP-CLI

```bash
wp plugin update mxroute-mailer
```

### Manual Update

If automatic updates don't work:

1. Download the latest release from [GitHub Releases](https://github.com/richardkentgates/mxroute-mailer/releases)
2. Extract the zip file
3. Replace the `mxroute-mailer` folder in `/wp-content/plugins/` with the new version
4. The update is complete - no database migration needed (it runs automatically)

## Release Zip Layout

Starting with `v1.2.13`, release zips contain a single top-level folder named `mxroute-mailer/`. This ensures WordPress extracts the update into the existing plugin directory.

If you previously installed an update that created a folder like `mxroute-mailer-v1.2.7/`, the plugin was deactivated because WordPress could not find it in the expected location. To fix this:

1. Deactivate and delete the `mxroute-mailer-v1.2.x/` folder from `/wp-content/plugins/`
2. Install the latest release zip normally
3. Activate MXRoute Mailer

## Version Comparison

The updater uses WordPress's standard version comparison, which follows [PHP's version_compare()](https://www.php.net/manual/en/function.version-compare.php) rules:

- `1.2.4` is newer than `1.2.3`
- `1.3.0` is newer than `1.2.4`
- `2.0.0` is newer than `1.9.9`

## Release Tags

Releases use semantic versioning with a `v` prefix:

- `v1.2.4` - Version 1.2.4
- `v1.3.0` - Version 1.3.0 (new features)
- `v2.0.0` - Version 2.0.0 (major release)

## Troubleshooting

### Update Not Showing

1. **Clear transients**: Go to Dashboard > Updates and click "Check again"
2. **Check GitHub API**: The updater caches results for 12 hours
3. **Verify version**: Make sure your installed version is older than the latest release

### Update Fails

1. **Check file permissions**: The plugin directory must be writable by WordPress
2. **Check GitHub access**: Your server must be able to reach `api.github.com`
3. **Manual update**: Download and install the release zip manually

### Plugin Deactivated After Update

This usually means the update was extracted into a versioned folder like `mxroute-mailer-v1.2.x/` instead of `mxroute-mailer/`. See [Release Zip Layout](#release-zip-layout) above for the fix.

### "Error: Cannot fetch update" Message

This usually means:

- Your server can't reach GitHub (firewall, DNS issue)
- The GitHub API is rate-limited (rare)
- The repository is private (it should be public)

Check if your server can reach GitHub:

```bash
curl -I https://api.github.com/repos/richardkentgates/mxroute-mailer/releases/latest
```

## Security

- Releases are built by GitHub Actions CI/CD
- Each push to `dev` runs PHP syntax lint, PHPUnit, zizmor workflow analysis, Semgrep PHP security scan, CodeQL analysis, and a pinned-Actions check
- The zip file is attached to the GitHub release, not hosted externally
- Version comparison prevents accidental downgrades
- Starting with `v1.2.19`, each release includes a SHA-256 checksum file; the plugin verifies the downloaded zip against this checksum before WordPress installs the update

## Disabling Auto-Updates

If you prefer to update manually, you can disable WordPress auto-updates for this plugin:

1. Go to **Plugins**
2. Click **Disable auto-updates** next to MXRoute Mailer

Or add to `wp-config.php`:

```php
add_filter( 'auto_update_plugin', function( $update, $item ) {
    if ( $item->slug === 'mxroute-mailer' ) {
        return false;
    }
    return $update;
}, 10, 2 );
```
