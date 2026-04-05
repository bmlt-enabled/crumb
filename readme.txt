=== Crumb ===

Contributors: bmltenabled, pjaudiomv
Tags: narcotics anonymous, na, meetings, bmlt, meeting finder
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Embeds the Crumb Widget meeting finder on any page or post.

== Description ==

Crumb is a lightweight WordPress plugin that embeds the [Crumb Widget](https://crumb.bmlt.app/) meeting finder on any page or post using a simple shortcode.

Features:

* List and map views with real-time search and filters
* Meeting detail with directions and virtual meeting join link
* Multi-language support
* Shareable per-meeting URLs

= Usage =

Add the shortcode to any page or post:

`[crumb]`

Override settings per page:

`[crumb server="https://your-server/main_server" service_body="42"]`

= Documentation =

Full documentation at [crumb.bmlt.app](https://crumb.bmlt.app/).

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/crumb/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **Settings → Crumb** and enter your BMLT Server URL.
4. Add `[crumb]` to any page or post.

== Frequently Asked Questions ==

= Where do I find my server URL? =

It is the URL to your BMLT server, typically ending in `/main_server`. Contact your service body's regional tech team if you are unsure.

= Can I show only meetings from a specific service body? =

Yes. Enter the service body ID (or a comma-separated list of IDs) in the Service Body IDs field, or use the `service_body` shortcode attribute. Child service bodies are always included automatically.

= Does it work with page builders? =

The shortcode works in any context that processes WordPress shortcodes. If your page builder does not render shortcodes automatically, use its dedicated shortcode block.

== External services ==

This plugin relies on external services to function properly:

**Crumb Widget**
- **Service**: Crumb Widget (https://crumb.bmlt.app)
- **Purpose**: Provides the JavaScript component that renders the meeting list interface
- **Data sent**: No user data is transmitted to this service. The plugin only loads the JavaScript library.
- **When**: The script is loaded whenever a page contains the [crumb] shortcode
- **Terms of use**: https://github.com/bmlt-enabled/crumb-widget/blob/main/LICENSE

== Changelog ==

= 1.0.0 =
* Initial release.
