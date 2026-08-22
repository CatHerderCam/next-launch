=== SpaceDevs Next Launch ===
Contributors: catherdercam
Tags: rocket launch, spacex, countdown, shortcode, widget
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shortcode widget showing upcoming rocket launches from the launch sites you choose, backed by The Space Devs Launch Library 2 API.

== Description ==

SpaceDevs Next Launch adds a `[next_launch]` shortcode that displays upcoming rocket launches, pulled from The Space Devs' free Launch Library 2 API. Point it at any launch site (or all of them), pick how much detail to show, and drop it into a post, page, or widget area.

**Features**

* `[next_launch]` shortcode with attributes for location, launch count, layout, color theme, and which fields to show (image, countdown, provider, pad, orbit, status, description).
* Three layouts (card, list, compact), each with an optional slider mode that shows one launch at a time with prev/next arrows.
* Optional light or dark color theme that forces a matching card background, independent of the surrounding page or the visitor's OS preference.
* A visual Shortcode Builder screen (Settings > Next Launch Builder) with toggle switches, dropdowns, and a live-updating shortcode preview you can copy with one click.
* A searchable, multi-select location picker backed by the live API, pre-populated with the most active launch sites.
* Launches never call the API during a normal page view: a WP-Cron job refreshes a cached copy on a schedule you control, and the last good response is served if the API is ever unreachable or rate-limited.
* Automatically filters out launches the upstream API still lists as "upcoming" moments after they've already flown.

= Where the data comes from =

Launch data is provided by [The Space Devs' Launch Library 2 API](https://ll.thespacedevs.com/), a free, public, community-funded API. This plugin is not affiliated with The Space Devs. See "External services" below for what this plugin sends and when.

== External services ==

This plugin connects to The Space Devs' Launch Library 2 API to retrieve upcoming launch data and launch-site names.

* **Service:** The Space Devs Launch Library 2 (`https://ll.thespacedevs.com`), or `https://lldev.thespacedevs.com` if the "development endpoint" option is turned on in Settings > Next Launch.
* **What is sent:** only the numeric launch-location IDs configured in the shortcode/settings, and any text an administrator types into the location search box on the Settings or Shortcode Builder screens. No visitor data, personal information, or site content is ever transmitted.
* **When it's sent:** on a background WP-Cron schedule (hourly by default, configurable) to refresh the cached launch list, and on-demand when an administrator uses the location search. The API is never called while a visitor is viewing a page.
* **Terms:** [thespacedevs.com/llapi](https://www.thespacedevs.com/llapi). Review their terms before relying on this plugin for a production site; the API is funded through Patreon and a credit link is appreciated.

== Installation ==

1. Take a backup of your site first.
2. Upload the plugin through Plugins > Add New > Upload Plugin, or upload the `next-launch` folder to `wp-content/plugins/`.
3. Activate the plugin.
4. Go to Settings > Next Launch to set default locations, and Settings > Next Launch Builder to build a shortcode visually.
5. Place `[next_launch]` (or a customized version of it) into any post, page, or widget area that supports shortcodes.

== Frequently Asked Questions ==

= How do I find a launch site's location ID? =

Use the search box on the Settings > Next Launch screen, or the location picker on Settings > Next Launch Builder — both query the live API and show real IDs. Don't guess; Cape Canaveral SFS and Kennedy Space Center, for example, are separate locations with separate IDs.

= Will this slow down my site or get me rate-limited? =

No. The plugin never calls the API on a normal page view. A WP-Cron job refreshes a cached copy on a schedule you control (15 minutes minimum), and the last good response is served if the API is ever down or rate-limited.

= What happens if the API is unreachable? =

Visitors see the last successfully cached data instead of an empty box. Only if there has never been a successful response does the shortcode fall back to its configurable "empty" message. Errors are written to the debug log when `WP_DEBUG` is on, never shown to visitors.

= Can I make it match my site's dark or light design? =

Yes. The `theme` attribute (or the "Color surface" option in the Shortcode Builder) accepts `auto` (blends into the page, follows the visitor's OS preference), `light`, or `dark`. The bundled CSS also exposes custom properties (`--sdnl-border`, `--sdnl-muted`, `--sdnl-go`, and others) for deeper theme integration, or you can disable the bundled stylesheet entirely in Settings.

= Does the slider work without JavaScript? =

Yes. Without JavaScript the launches simply stack vertically like the non-slider layouts, so nothing traps a visitor on a single item.

== Changelog ==

= 1.1.1 =
* Fixed: the plugin folder and main file were renamed from `spacedevs-next-launch` to `next-launch` to match the plugin's slug and text domain.

= 1.1.0 =
* Added: a visual Shortcode Builder admin screen with toggle switches, dropdowns, and a live, copyable shortcode preview.
* Added: a searchable, multi-select location picker backed by the live API.
* Added: `theme` attribute (`auto` / `light` / `dark`) for a forced light or dark card background.
* Added: `slider` attribute — shows one launch at a time with prev/next arrows, compatible with all three layouts.
* Fixed: the location-search API endpoint was pointed at a path that returned a 404 on the current API version; location search now works.
* Fixed: the "upcoming" launch list could briefly keep showing a launch for hours after it had already flown; results are now filtered by status before display.
* Fixed: a box-sizing issue that could misalign slider frames by the width of their padding and border.

= 1.0.0 =
* Initial release: `[next_launch]` shortcode, settings screen with location search, WP-Cron-backed caching with stale-response fallback.

== Upgrade Notice ==

= 1.1.1 =
Renames the plugin folder and main file to `next-launch` to match the plugin slug. Deactivate and delete the old `spacedevs-next-launch` install before activating this version.

= 1.1.0 =
Adds a visual shortcode builder, light/dark themes, and a slider mode. Fixes a location-search bug and a display bug that could show already-flown launches. No settings are changed by this update.
