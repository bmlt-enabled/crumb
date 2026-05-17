=== Crumb ===

Contributors: bmltenabled, pjaudiomv
Tags: narcotics anonymous, na, meetings, bmlt, meeting finder
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.7.0
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

`[crumb server="https://your-server/main_server" service_body="42" format_ids="17,54" view="map" geolocation="true" geolocation_radius="30"]`

Shortcode attributes:

* `server` — BMLT server URL (overrides the value set in Settings → Crumb)
* `service_body` — Service body ID or comma-separated list; leave empty to show all meetings
* `format_ids` — Format ID or comma-separated list of BMLT format IDs to lock the widget to; leave empty to show all formats
* `view` — Default view when the widget loads: `list` (default), `map`, or `both` (map above list with no toggle); can also be overridden at runtime via the `?view=` query parameter
* `geolocation` — Enable or disable geolocation for this page: `true` or `false`
* `geolocation_radius` — Geolocation search radius. Positive integer = fixed radius in miles (or km per server settings). Negative integer = BMLT auto-radius: the server expands the search until it finds roughly that many meetings (e.g. `-50` finds ~50 nearby meetings). Overrides the Geolocation Radius setting and Widget Configuration.
* `update_url` — URL template for the **Update Meeting Info** link shown at the bottom of the meeting detail panel. Supports tokens `{meeting_id}`, `{meeting_name}`, `{server_url}`, `{return_url}` (URL-encoded on substitution). Works with bmlt-workflow, hosted forms, or `mailto:` URLs.
* `columns` — Comma-separated list of columns to show in list view (e.g. `time,name,location,address,service_body`). Omit a name to hide that column. Leave unset to use the widget default.
* `language` — Force the widget UI language for this page (e.g. `en`, `es`, `fr`, `de`, `pt`, `it`, `sv`, `da`, `el`, `fa`, `pl`, `ru`, `ja`). Leave unset to auto-detect from the visitor's browser.
* `query` — Raw BMLT query string passed through to the widget's `rawQuery()` for filters the structured options can't express (e.g. multi-value `meeting_key_value[]`). When set, this **replaces** the default load entirely — `service_body`, `format_ids`, and `?services` are ignored — and forces geolocation off (the widget can't safely layer lat/long/geo_width on top of an arbitrary query). Encode brackets as `%5B` / `%5D` because WordPress shortcodes can't contain literal `[` or `]`. Example: `[crumb query="meeting_key=location_nation&meeting_key_value%5B%5D=USA"]`. Shortcode-only; no admin setting.

= Switching from Crouton =

Crumb is an alternative to the [crouton](https://wordpress.org/plugins/crouton/) plugin and can drop in without page edits in most cases. Activating Crumb will:

* Register the crouton shortcodes (`[bmlt_tabs]`, `[bmlt_map]`, `[crouton_tabs]`, `[crouton_map]`) and translate them to the Crumb widget. Map shortcodes render with `view="both"` (map + list) and tabs shortcodes render with `view="list"`. Shortcode attributes `root_server`, `service_body`, `service_body_1`, `formats`, `report_update_url`, and `query_string` are mapped to their Crumb equivalents (`query_string` becomes `data-query` and routes through the widget's `rawQuery()`).
* Reuse crouton's saved settings as fallbacks when the corresponding Crumb option is empty (BMLT server URL, service bodies, format IDs, update URL). Open **Settings → Crumb** to confirm the inherited values and click **Save Changes** to persist them.

Crumb only handles those shortcodes when crouton is deactivated — if both plugins are active, crouton continues to handle its own shortcodes. To switch: install Crumb, then deactivate crouton. No page edits required.

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

= I'm switching from the crouton plugin — do I have to edit my pages? =

No. Crumb registers the crouton shortcodes (`[bmlt_tabs]`, `[bmlt_map]`, `[crouton_tabs]`, `[crouton_map]`) and renders them with the Crumb widget. It also reads crouton's saved settings (server URL, service body, format IDs, update URL) as fallbacks. Just install Crumb, deactivate crouton, and existing pages keep working. See the **Switching from Crouton** section above for details.

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

= 1.7.0 =
* Added **Geolocation** admin setting — dedicated dropdown (Widget Default / On / Off) to enable or disable location-based search (the **Near Me** button and typed-location search). Complements the existing `geolocation` shortcode attribute, which still overrides per page.

= 1.6.0 =
* Added `query` shortcode attribute — raw BMLT query string passed through to the Crumb widget's `rawQuery()` for filters the structured options can't express (e.g. multi-value `meeting_key_value[]`). When set, it replaces the default load entirely (`service_body` / `format_ids` are ignored) and forces geolocation off so geo params can't be layered on top. Shortcode-only; no admin setting. Encode brackets as `%5B` / `%5D` because WordPress shortcodes can't contain literal `[` or `]`. Requires Crumb Widget 1.5.0+.
* Crouton compatibility: `query_string` on `[bmlt_tabs]` / `[bmlt_map]` / `[crouton_tabs]` / `[crouton_map]` now maps to the new `data-query` data attribute, preserving the crouton behavior of appending a raw BMLT query.

= 1.5.0 =
* Added **Language** setting on the global settings page and matching `language` shortcode attribute (e.g. `[crumb language="es"]`). Forces the widget UI language; leave empty to auto-detect from the visitor's browser. Supported codes: `en`, `es`, `fr`, `de`, `pt`, `it`, `sv`, `da`, `el`, `fa`, `pl`, `ru`, `ja`. Per-shortcode value overrides the saved setting; `widget_config` JSON `language` key still wins over both.

= 1.4.0 =
* Added `columns` shortcode attribute — comma-separated list of columns to show in list view (e.g. `[crumb columns="time,name,location,address,service_body"]`). Omit a name to hide that column. Pairs with the new `data-columns` reader in the Crumb widget; leave unset to use the widget default.
* Crouton compatibility: `has_areas` and `has_regions` on `[bmlt_tabs]` / `[bmlt_map]` / `[crouton_tabs]` / `[crouton_map]` now add the `service_body` column to the widget output, preserving crouton's behavior of surfacing the originating service body in the listing.

= 1.3.3 =
* Crouton compatibility: `show_map="1"` on `[bmlt_tabs]` or `[crouton_tabs]` now renders the Crumb widget with `view="both"` (map + list), matching crouton's behavior when a map is requested alongside the tabbed listing.

= 1.3.2 =
* Extended the crouton compatibility layer to register empty-string stubs for crouton's helper shortcodes (`[bmlt_count]`, `[meeting_count]`, `[group_count]`, `[service_body_names]`, `[root_service_body]`, `[bmlt_handlebar]`, `[init_crouton]`) so pages don't render the literal shortcode text after crouton is deactivated. These have no Crumb equivalent and output nothing; the surrounding page content remains intact.

= 1.3.1 =
* Added compatibility layer for the [crouton](https://wordpress.org/plugins/crouton/) plugin. Crumb now registers `[bmlt_tabs]`, `[bmlt_map]`, `[crouton_tabs]`, and `[crouton_map]` shortcodes and renders them with the Crumb widget when crouton is not active. Shortcode attributes (`root_server`, `service_body`, `service_body_1`, `formats`, `report_update_url`) are translated automatically.
* Crumb falls back to crouton's saved settings (server URL, service body, format IDs, update URL) when the corresponding Crumb option is empty — installing and activating is enough; no page or settings edits required.

= 1.3.0 =
* Added **Update Meeting URL** setting and `update_url` shortcode attribute — URL template that powers the "Update Meeting Info" link on the meeting detail panel. Supports tokens `{meeting_id}`, `{meeting_name}`, `{server_url}`, and `{return_url}` (URL-encoded on substitution). Works with [bmlt-workflow](https://github.com/bmlt-enabled/bmlt-workflow), arbitrary hosted forms, or `mailto:` URLs. Leave empty to hide the link.

= 1.2.2 =
* Added **Geolocation Radius** admin setting — dedicated field for geolocation search radius, separate from the JSON config textarea.
* Added `geolocation_radius` shortcode attribute to override the radius per page.
* Support BMLT auto-radius mode: a negative `geolocationRadius` value instructs the server to expand the search until roughly that many meetings are found (e.g. `-50` finds ~50 nearby meetings).

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

= 1.7.0 =
Adds dedicated Geolocation admin setting to enable or disable location-based search without editing JSON. Safe to update.

= 1.6.0 =
Adds `query` shortcode attribute and maps crouton's `query_string` to the Crumb widget's new raw-query support. Safe to update.

= 1.4.0 =
Adds `columns` shortcode attribute for selecting which columns appear in list view, and maps crouton's `has_areas` / `has_regions` to include the `service_body` column. Safe to update.

= 1.3.3 =
Honors `show_map="1"` on tabs shortcodes by switching the Crumb widget to `view="both"`. Safe to update.

= 1.3.2 =
Suppresses literal `[bmlt_count]`, `[meeting_count]`, `[group_count]`, `[service_body_names]`, `[root_service_body]`, `[bmlt_handlebar]`, and `[init_crouton]` shortcode text on pages after crouton is deactivated. Safe to update.

= 1.3.1 =
Adds drop-in compatibility for the crouton plugin: crouton shortcodes (`[bmlt_tabs]`, `[bmlt_map]`, `[crouton_tabs]`, `[crouton_map]`) now render with the Crumb widget and crouton's saved settings are used as fallbacks. Safe to update.

= 1.3.0 =
Adds Update Meeting URL setting and `update_url` shortcode attribute for the configurable "Update Meeting Info" link. Safe to update.

= 1.2.2 =
Adds Geolocation Radius admin setting, `geolocation_radius` shortcode attribute, and BMLT auto-radius support (negative values). Safe to update.

= 1.2.0 =
Adds format ID filtering. No breaking changes, safe to update.

= 1.1.1 =
Adds "both" as a valid view option. No breaking changes, safe to update.
