=== BMLT Client ===

Contributors: bmltenabled, pjaudiomv
Tags: narcotics anonymous, na, meetings, bmlt, meeting finder
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Embeds the BMLT Client meeting finder widget on any page or post.

== Description ==

BMLT Client is a lightweight WordPress plugin that embeds the [BMLT Client](https://client.bmlt.app/) meeting finder widget on any page or post using a simple shortcode.

Features:

* List and map views with real-time search and filters
* Meeting detail with directions and virtual meeting join link
* Geolocation-based nearby search (optional)
* Multi-language support
* Configurable columns, map tiles, and custom markers
* Shareable per-meeting URLs via hash routing

= Usage =

Add the shortcode to any page or post:

`[bmlt_client]`

Override settings per page:

`[bmlt_client root_server="https://your-server/main_server" service_body="42" view="map"]`

= Documentation =

Full documentation at [client.bmlt.app](https://client.bmlt.app/).

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/bmlt-client/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **Settings → BMLT Client** and enter your root server URL.
4. Add `[bmlt_client]` to any page or post.

== Frequently Asked Questions ==

= Where do I find my root server URL? =

It is the URL to your BMLT root server, typically ending in `/main_server`. Contact your service body's regional tech team if you are unsure.

= Can I show only meetings from a specific service body? =

Yes. Enter the service body ID (or a comma-separated list of IDs) in the Service Body IDs field, or use the `service_body` shortcode attribute. Child service bodies are always included automatically.

= Does it work with page builders? =

The shortcode works in any context that processes WordPress shortcodes. If your page builder does not render shortcodes automatically, use its dedicated shortcode block.

== Changelog ==

= 1.0.0 =
* Initial release.
