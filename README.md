<p align="center">
  <img src="crumb-logo.svg" alt="Crumb Widget logo" width="128" height="128">
</p>

# Crumb for WordPress

[![WordPress Plugin](https://img.shields.io/wordpress/plugin/v/crumb)](https://wordpress.org/plugins/crumb/)
[![WordPress Tested](https://img.shields.io/wordpress/plugin/tested/crumb)](https://wordpress.org/plugins/crumb/)
[![PHP Version](https://img.shields.io/wordpress/plugin/required-php/crumb)](https://wordpress.org/plugins/crumb/)
[![docs](https://img.shields.io/badge/docs-crumb.bmlt.app-blue)](https://crumb.bmlt.app/)

[WordPress plugin](https://wordpress.org/plugins/crumb/) that embeds the [Crumb Widget](https://github.com/bmlt-enabled/crumb-widget) meeting finder via shortcode.

## Usage

```
[crumb]
```

Override settings per page:

```
[crumb server="https://your-server/main_server" service_body="42" geolocation="true"]
```

## Installation

1. Upload to `/wp-content/plugins/crumb/`
2. Activate in WordPress admin
3. Go to **Settings → Crumb** and enter your BMLT Server URL
4. Add `[crumb]` to any page or post

## Settings

Configured under **Settings → Crumb**. Settings can be overridden per-shortcode via attributes.

| Setting              | Shortcode Attribute | Description                              |
|----------------------|---------------------|------------------------------------------|
| Server URL           | `server`            | Required. Full URL to your BMLT Server   |
| Service Body IDs     | `service_body`      | Optional. Single ID or comma-separated list |
| Base Path            | —                   | Optional. Page slug for pretty URLs      |
| —                    | `geolocation`       | Optional. `true` or `false` per page     |
| Widget Configuration | —                   | Optional. JSON for CrumbWidgetConfig     |

### Pretty URLs

By default, meeting detail URLs use hash-based routing (`#/monday-night-meeting-42`). To enable clean URLs like `/meetings/monday-night-meeting-42`, enter the page slug (e.g. `meetings`) in the **Base Path for Pretty URLs** setting. After saving, go to **Settings → Permalinks** and click **Save Changes** to update rewrite rules.

Full documentation at **[crumb.bmlt.app](https://crumb.bmlt.app/)**.
