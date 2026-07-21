# Installation

## Requirements

- WordPress 5.0 or higher
- PHP 7.3 or higher
- A [MXRoute](https://mxroute.com) account with API credentials

## Method 1: Upload via WordPress Admin

1. Download the latest release from [GitHub Releases](https://github.com/richardkentgates/mxroute-mailer/releases)
2. Extract the `mxroute-mailer` folder from the zip file
3. Upload the `mxroute-mailer` folder to `/wp-content/plugins/` on your server
4. Log in to WordPress admin
5. Go to **Plugins** and find "MXRoute Mailer"
6. Click **Activate**

## Method 2: Git Clone (Recommended for Production)

SSH into your server and run:

```bash
cd /srv/www/wordpress/wp-content/plugins/
sudo git clone https://github.com/richardkentgates/mxroute-mailer.git mxroute-mailer
```

## Method 3: WP-CLI

```bash
wp plugin install --activate path/to/mxroute-mailer
```

## Post-Installation

1. Go to **Settings > MXRoute Mailer** in WordPress admin
2. Enter your MXRoute credentials:
   - **Server**: Your MXRoute server hostname (e.g., `chocobo.mxrouting.net`)
   - **Username**: Your MXRoute email address (e.g., `you@mxroute.com`)
   - **Password**: Your MXRoute password
3. Click **Save Changes**
4. Use the **Send Test Email** form to verify your configuration

## Verifying Installation

After activation, you should see:

- A new menu item under **Settings** called "MXRoute Mailer"
- A new item under **Tools** called "MXRoute Logs"
- A new item under **Tools** called "MXRoute Queue"
- The plugin listed on the **Plugins** page with version info

## WordPress Multisite

MXRoute Mailer supports WordPress Multisite networks.

### Network Activation

1. Go to **Network Admin > Plugins**
2. Find "MXRoute Mailer" and click **Network Activate**
3. The plugin is now active on all sites in the network

Each site has its own settings, logs, and queue. The logs table is created automatically when a new site is added to the network.

### Per-Site Configuration

Each site must configure its own MXRoute credentials:

1. Go to the site's **Settings > MXRoute Mailer**
2. Enter the MXRoute credentials for that site
3. Send a test email to verify

### Network Admin Access

Only users with the `manage_network_options` capability can access MXRoute Mailer settings on multisite installations. Regular site admins cannot access the plugin settings.

### Uninstall on Multisite

When you delete the plugin on a multisite network:

- If **Keep data** is checked: All logs and settings are preserved for each site
- If **Keep data** is unchecked: All logs, settings, and attachment files are removed from every site in the network

## Troubleshooting

If you encounter issues during installation, see the [Troubleshooting](https://github.com/richardkentgates/mxroute-mailer/wiki/Troubleshooting) page.
