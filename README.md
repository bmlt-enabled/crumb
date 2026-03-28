# BMLT Client for WordPress

WordPress plugin that embeds the [BMLT Client](https://client.bmlt.app/) meeting finder widget via shortcode.

## Usage

```
[bmlt_client]
```

Override settings per page:

```
[bmlt_client root_server="https://your-server/main_server" service_body="42" view="map"]
```

## Installation

1. Upload to `/wp-content/plugins/bmlt-client/`
2. Activate in WordPress admin
3. Go to **Settings → BMLT Client** and enter your root server URL
4. Add `[bmlt_client]` to any page or post

## Settings

Configured under **Settings → BMLT Client**. All settings can be overridden per-shortcode via attributes.

| Setting | Shortcode Attribute | Description |
|---|---|---|
| Root Server URL | `root_server` | Required. Full URL to your BMLT root server |
| Service Body IDs | `service_body` | Optional. Single ID or comma-separated list |
| Default View | `view` | `list` or `map` |

Full documentation at **[client.bmlt.app](https://client.bmlt.app/)**.
