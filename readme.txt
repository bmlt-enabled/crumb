=== Crumb ===

Contributors: bmltenabled, pjaudiomv
Tags: narcotics anonymous, na, meetings, bmlt, meeting finder
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.1
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

`[crumb server="https://your-server/main_server" service_body="42" view="map"]`

Shortcode attributes:

* `server` — BMLT server URL (overrides the value set in Settings → Crumb)
* `service_body` — Service body ID or comma-separated list; leave empty to show all meetings
* `view` — Default view when the widget loads: `list` (default) or `map`; can also be overridden at runtime via the `?view=` query parameter

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

= Can I set the widget to open in map view by default? =

Yes. Choose **Map** from the Default View dropdown in **Settings → Crumb**, or use `view="map"` in the shortcode. Visitors can also switch views at runtime via the `?view=` query parameter.

= Does it work with page builders? =

The shortcode works in any context that processes WordPress shortcodes. If your page builder does not render shortcodes automatically, use its dedicated shortcode block.

== External services ==

This plugin relies on two external services. Both are part of the BMLT (Basic Meeting List Toolkit) ecosystem — free, open-source tools built for Narcotics Anonymous service bodies (https://bmlt.app).

**1. Crumb Widget CDN**

The widget JavaScript is loaded from a CDN operated by the BMLT project.

- Service: cdn.aws.bmlt.app — delivers the Crumb Widget JavaScript file
- Purpose: Provides the JavaScript component that renders the meeting list interface
- Data sent: Standard HTTP request headers (IP address, browser user-agent, referring URL). No personal or meeting-search data is sent to the CDN.
- When: The script is loaded once per page load on any page that contains the [crumb] shortcode
- Privacy policy: https://crumb.bmlt.app/privacy.html
- Terms of use / License: https://github.com/bmlt-enabled/crumb-widget/blob/main/LICENSE

**2. BMLT Server (meeting data)**

The widget fetches meeting data from a BMLT server whose URL you configure in Settings → Crumb. This server is typically operated by a regional NA service body and is not a service operated by the Crumb project.

- Service: Your configured BMLT server (e.g. https://your-region.bmlt.app/main_server/)
- Purpose: Retrieve NA meeting listings (names, times, locations, formats) to display in the widget
- Data sent: Search query parameters (filters, keyword, selected formats). If geolocation is enabled and the user consents via a browser prompt, the user's geographic coordinates are also sent to this server.
- When: On each search or filter action within the widget
- Privacy policy: Determined by the operator of your configured BMLT server. Learn more at https://bmlt.app.

== Changelog ==

= 1.0.1 =
* Added `view` shortcode attribute and admin setting to set the default widget view (`list` or `map`).

= 1.0.0 =
* Initial release.
