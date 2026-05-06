=== Crumb ===

Contributors: bmltenabled, pjaudiomv
Tags: narcotics anonymous, na, meetings, bmlt, meeting finder
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Embeds the Crumb Widget NA meeting finder on any page or post via a simple shortcode.

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

`[crumb server="https://your-server/main_server" service_body="42" format_ids="17,54" view="map" geolocation="true"]`

Shortcode attributes:

* `server` — BMLT server URL (overrides the value set in Settings → Crumb)
* `service_body` — Service body ID or comma-separated list; leave empty to show all meetings
* `format_ids` — Format ID or comma-separated list of BMLT format IDs to lock the widget to; leave empty to show all formats
* `view` — Default view when the widget loads: `list` (default), `map`, or `both` (map above list with no toggle); can also be overridden at runtime via the `?view=` query parameter
* `geolocation` — Enable or disable geolocation for this page: `true` or `false`

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

Yes. Choose **Map** from the Default View dropdown in **Settings → Crumb**, or use `view="map"` in the shortcode. You can also choose **Both** to display the map above the meeting list simultaneously with no list/map toggle. Visitors can also switch views at runtime via the `?view=` query parameter.

= Can I get clean URLs without the # in them? =

Yes. Enter the page slug (e.g. `meetings`) in the **Base Path for Pretty URLs** setting under **Settings → Crumb**. After saving, go to **Settings → Permalinks** and click **Save Changes** to update rewrite rules. Meeting detail URLs will then look like `/meetings/monday-night-meeting-42` instead of `/#/monday-night-meeting-42`.

= Can I deep-link to a specific view? =

Yes. Append `?view=list` or `?view=map` to any page URL that contains the `[crumb]` shortcode to open the widget in that view. This works regardless of the default view set in Settings → Crumb, making it easy to link directly to the map view from a button or menu item.

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

== Screenshots ==

1. List view — meeting results with day, time, location, and address. Click any meeting name to see full details.
2. Map view — meeting locations plotted on an interactive map with the same search and filter controls.

== Changelog ==

= 1.2.1 =
* Fixed numeric widget config values (e.g. `geolocationRadius`) being coerced to strings by `wp_localize_script`; switched to `wp_add_inline_script` to preserve JSON types.

= 1.2.0 =
* Added **Format IDs** setting and `format_ids` shortcode attribute to lock the widget to specific BMLT formats (single ID or comma-separated list).

= 1.1.1 =
* Added `both` as a valid `view` option — displays the map above the meeting list with no list/map toggle.

= 1.1.0 =
* Added **Base Path for Pretty URLs** setting — enables clean meeting detail URLs (e.g. `/meetings/monday-night-meeting-42`) using WordPress rewrite rules. Leave empty to keep default hash-based routing.

= 1.0.3 =
* Added Widget Configuration setting (JSON) for CrumbWidgetConfig options (language, geolocation, darkMode, columns, map tiles, etc.).
* Added `geolocation` shortcode attribute to enable or disable geolocation per page.

= 1.0.2 =
* Updated readme to document external services (CDN and BMLT server) with privacy policy links.
* Fixed late-escaping of inline CSS output per WordPress coding standards.

= 1.0.1 =
* Added `view` shortcode attribute and admin setting to set the default widget view (`list` or `map`).

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.1 =
Bug fix: numeric widget config values (e.g. `geolocationRadius`) now passed correctly. Safe to update.

= 1.2.0 =
Adds format ID filtering. No breaking changes, safe to update.

= 1.1.1 =
Adds "both" as a valid view option. No breaking changes, safe to update.
