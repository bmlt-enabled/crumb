# Crumb for WordPress

WordPress plugin that embeds the [Crumb Widget](https://crumb.bmlt.app/) meeting finder widget via shortcode.

## Usage

```
[crumb]
```

Override settings per page:

```
[crumb server="https://your-server/main_server" service_body="42"]
```

## Installation

1. Upload to `/wp-content/plugins/crumb/`
2. Activate in WordPress admin
3. Go to **Settings → Crumb** and enter your BMLT Server URL
4. Add `[crumb]` to any page or post

## Settings

Configured under **Settings → Crumb**. All settings can be overridden per-shortcode via attributes.

| Setting          | Shortcode Attribute | Description                            |
|------------------|----------------|---------------------------------------------|
| Server URL       | `server`       | Required. Full URL to your BMLT Server      |
| Service Body IDs | `service_body` | Optional. Single ID or comma-separated list |

Full documentation at **[crumb.bmlt.app](https://crumb.bmlt.app/)**.
