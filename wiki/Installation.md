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
   - **Server**: Your MXRoute server hostname (e.g., `mxroute.example.com`)
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

## Troubleshooting

If you encounter issues during installation, see the [Troubleshooting](https://github.com/richardkentgates/mxroute-mailer/wiki/Troubleshooting) page.
