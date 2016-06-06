=== Trackserver ===
Contributors: tinuzz
Donate link: http://www.grendelman.net/wp/trackserver-wordpress-plugin/
Tags: gps, gpx, map, leaflet, track, mobile, tracking
Requires at least: 4.0
Tested up to: 4.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

GPS Track Server for TrackMe, OruxMaps and others

== Description ==

Getting your GPS tracks into Wordpress and publishing them has never been
easier!

Trackserver is a plugin for storing and publishing GPS routes. It is a server
companion to several mobile apps for location tracking, and it can display maps
with your tracks on them by using a shortcode. It can also be used for live
tracking, where your visitors can follow you or your users on a map.

Unlike other plugins that are about maps and tracks, Trackserver's main focus
is not the publishing, but rather the collection and storing of tracks and
locations. It's all about keeping your data to yourself. Several mobile apps
and protocols are supported for getting tracks into trackserver:

* [TrackMe][trackme]
* [MapMyTracks protocol][mapmytracks], for example using [OruxMaps][oruxmaps], including upload functionality
* [OsmAnd's][osmand] live tracking protocol
* HTTP POST, for example using [AutoShare][autoshare]

A shortcode [tsmap] is provided for displaying your tracks on a map. Maps are displayed
using the fantastic [Leaflet library][leafletjs] and some useful Leaflet plugins
are included. Maps can be viewed in full-screen on modern browsers.

To publish a map with a track in a post or a page, just include the shortcode:

[tsmap track=&lt;id&gt;]

See the FAQ section for more information on the shortcode's supported attributes.

[trackme]: http://www.luisespinosa.com/trackme_eng.html
[mapmytracks]: https://github.com/MapMyTracks/api
[osmand]: http://osmand.net/
[oruxmaps]: http://www.oruxmaps.com/index_en.html
[autoshare]: https://play.google.com/store/apps/details?id=com.dngames.autoshare
[leafletjs]: http://leafletjs.com/

= Requirements =

Trackserver requires PHP 5.3 or newer.

= Credits =

This plugin was written by Martijn Grendelman. Development is tracked on Github:
https://github.com/tinuzz/wp-plugin-trackserver

It includes some code and libraries written by other people:

* [Polyline encoder](https://github.com/emcconville/polyline-encoder) by Eric McConville
* [Leaflet.js][leafletjs] by Vladimir Agafonkin and others
* [Leaflet.fullscreen](https://github.com/Leaflet/Leaflet.fullscreen) by John Firebaugh and others
* [Leaflet-omnivore](https://github.com/mapbox/leaflet-omnivore) by Mapbox
* [GPXpress](https://wordpress.org/support/plugin/gpxpress) by David Keen was an inspiration sometimes

= TODO =

* Support [SendLocation](https://itunes.apple.com/nl/app/sendlocation/id377724446) iOS app
* Support [GpsGate](http://gpsgate.com/) tracking protocol
* Better permissions/authorization system
* More shortcode parameters and map options
* More track management features, like folders/collections and editing / splitting tracks
* A shortcode for downloading a track in GPX or other formats
* Track statistics, like distance, average speed, etc.
* Add map profiles, maybe include [leaflet-providers](https://github.com/leaflet-extras/leaflet-providers) plugin
* ...

More TODO-items and feature ideas in the TODO file contained in the plugin archive.

== Installation ==

1. Use Wordpress' built-in plugin installer, or copy the folder from the plugin
   archive to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Configure the slugs that the plugin will listen on for location updates.
1. Configure your mobile apps and start tracking!
1. Use the shortcode [tsmap] to include maps and tracks in your posts and pages.

== Frequently Asked Questions ==

= What are the available shortcode attributes? =

* track: one or more track IDs, separated by commas, or 'live'.
* width: map width, default: 100%.
* height: map height, default: 480px.
* align: 'left', 'center' or 'right', default: not set.
* class: a CSS class to add to the map div for customization, default: not set.
* markers: true (default) or false (or 'f', 'no' or 'n') to disable start/end
  markers on the track. The value can also be 'start', 's', 'end' or 'e', to
  draw markers only for the start or the end of a track respectively.
* continuous: true (default) or false (or 'f', 'no' or 'n'), for lack of a
  better word, to indicate whether multiple tracks should be considered as
  one continuous track. The only effect this has, at the moment, is that
  intermediate start markers are yellow instead of green.
* gpx: the URL to a GPX file to be plotted on the map. 'track' attribute takes
  precedence over 'gpx'.
* infobar: true (or 't', 'yes' or 'y'), or false (default), to specify whether
  an information bar should be shown on the map, when live tracking is active.
  This only works with 'track=live', and has no effect in other cases.
* color: the color of the track on the map, default comes from Leaflet.
* weight: the weight of the track on the map, default comes from Leaflet.
* opacity: the opacity of the track on the map, default comes from Leaflet.

Example: [tsmap track=39,84,live align=center class=mymap markers=n color=#ff0000]

In a feature request I was asked to make it possible to draw just a marker on the
last known location, and not draw a track at all. Use 'markers' and 'opacity' to
accomplish this:

Example: [tsmap track=live markers=e opacity=0.0]

= I used the shortcode but the map doesn't show =

Trackserver tries to detect the usage of the [tsmap] shortcode to prevent
unnecessary loading of the plugin's JavaScript. In some circumstances, for
example, if your page setup is complex and uses multiple loops, the detection
could fail. In such a case, use this shortcode in the main post or page to
force loading the JavaScript:

[tsscripts]

= What is live tracking? =

By using the shortcode with the 'track=live' parameter, the most recently updated track
belonging to the author of the current post/page is published on the map.

The track is updated with the latest trackpoints every 10 seconds and the map
view is always centered to the most recent trackpoint. A marker is shown in
that location. Live tracking can be stopped and restarted with a simple control
button that is shown on the map.

= What is a Trackserver user profile? (since v1.9) =

Before v1.9, all of Trackserver's settings were stored in a single, global place
in WordPress, meaning they were shared among all users in the same WordPress
install. This was also the case for the OsmAnd access key that allows OsmAnd
users to use Trackserver for live tracking. Since Trackserver tries to be
multi-user, things like access keys do not belong in the global configuration.

In version 1.9, user profile settings were introduced as a place for per-user
settings, like access keys. There is a separate page in the WordPress admin for
the Trackserver profile (called 'Your profile' in English), that is accessible
by all users that have the right (capability) to use Trackerver.

= What changed for TrackMe authentication 1.9? =

TrackMe, like OsmAnd, uses HTTP GET requests for communication with Trackserver.
This means that all data from the app, including your password, becomes part of
the URL. Because URLs are not generally considered secret, and may be logged in
access logs and what not, this is quite insecure, even with HTTPS.

For OsmAnd, that has no built-in authentication, Trackserver has used an access
key instead of your WordPress password from the very beginning. For TrackMe,
this was implemented in v1.9. So from version 1.9 onward, every WordPress user
that is allowed to use Trackserver has its own separate access key for OsmAnd
and a separate password for TrackMe, settable in the Trackme user profile.

If you have been using Trackserver with TrackMe before v1.9, you should now set
the password in TrackMe to this new password, instead of your WordPress
password.  Like your password, you should keep your access keys to yourself,
but the idea is that the security impact of such a key is low, compared to your
WordPress password, and that you can (and should!) change the keys regularly.
No real effort is made to keep the keys secure (they are stored in the database
unhashed, for example), so in any case, try not to use a sensitive password.

= What is this 'slug' you are talking about? =

Slugs in WordPress are short descriptions of posts and pages, to be used in
URLs (permalinks). They are the part of the URL that makes WordPress serve
a particular page. Trackserver uses slugs to 'listen' for tracking requests
from mobile apps, and you can configure these slugs to be anything you want.
Trackserver comes with default values for these slugs, that should work for
most people. Changing them is usually not necessary.

Please refer to the [Wordpress Codex](http://codex.wordpress.org/Glossary#Slug) for more information
about slugs in general.

WARNING: please do not confuse the slugs that you configure in Trackserver
with the URLs (permalinks) that you use to publish your maps and tracks. Above
all, make sure there is no conflict between the permalink for a post or page
and the URLs (slugs) that Trackserver uses for location updates. The slugs in
Trackerver's configuration are for the location updates only. If you try to
open them in a browser, you will get errors like 'Illegal request'. Trackserver
operates on a low level within WordPress, so if there is a conflict between
Trackserver and a post or a page, Trackserver will win and the page will be
inaccessible.

To publish your tracks, simply create a page (with a non-conflicting permalink)
and use the [tsmap] shortcode to add a map.

= Can Trackserver support protocol X or device Y? =

Trackserver, being a WordPress plugin, can only support HTTP-based protocols for
tracking. Many tracking devices use TCP- but not HTTP-based protocols for online
tracking, and as such, Trackserver cannot support them, at least not without
some middleware that translates the device's protocol to HTTP.

If a device or an app does use HTTP as a transport, adding support for it in
Trackserver should be quite easy. For example, I have been thinking about support
for the GpsGate Server Protocol. It could be added if there is any demand for it.

If a device or an app uses a different transport, support could be added by
implementing some sort of middleware. For example, [OwnTracks](http://owntracks.org/)
uses MQTT and ships with a script ([m2s](https://github.com/owntracks/backend/tree/master/m2s))
for storing the data. Storage methods in m2s are pluggable, so one could write an
m2s-plugin for shipping the data to Trackserver.

= What about security? =

The plugin uses a custom Wordpress capability ('use_trackserver') to manage who
can use the tracking features and manage their own tracks. The capability is
granted to authors, editors and administrators, but not to subscribers. This is
hardcoded for now, and (re)activation of the plugin will re-grant the
capability to the three listed roles.

Users who can create/edit posts can also use the [tsmap] shortcode and publish
maps with their own tracks. In addition, administrators (and anyone else with
the 'trackserver_admin' capability) can manage and publish other users' tracks.

Tracks can only be published in Wordpress posts or pages, and cannot be
downloaded from outside Wordpress. Requests for downloading tracks need to
have a cryptographic signature that only Wordpress can generate.

Regarding the use of apps for live tracking and uploading to Wordpress, please
read the considerations about authentication above.

= What GPX namespaces are supported for GPX import (via HTTP POST or upload via backend)? =

GPX 1.1 (http://www.topografix.com/GPX/1/1) and GPX 1.0 (http://www.topografix.com/GPX/1/0).

= Is it free? =
Yes. Donations are welcome. Please visit
http://www.grendelman.net/wp/trackserver-wordpress-plugin/
for details.

== Screenshots ==

1. The track management page in the WordPress admin
2. Configuration of OruxMaps for use with Trackserver / WordPress

== Changelog ==

= UNRELEASED =

* Implement MapMyTracks 'upload_activity' request. You can now upload tracks to
  trackserver directly from OruxMaps without the use of AutoShare.
* CSS fixes for track management on small screens.
* Support values 'start', 's', 'end' and 'e' for the 'markers' attribute, to
  restrict drawing markers to start or end point only.
* Introduce new capability 'trackserver_admin' and grant it to administrators.
* Allow admins to manage and publish other users' tracks, using the new capability.

= v2.0.2 =
Release date: 23 December 2015

* Previous bugfix was incomplete. Thanks to eHc for another report.

= v2.0.1 =
Release date: 23 December 2015

* Fix bug with incorrect use of $wpdb->prepare. Thanks to eHc for finding it.

= v2.0 =
Release date: 22 December 2015

* Support mutiple tracks in a single map. Use a comma-separated list of track
  IDs (and/or 'live') in the 'track' parameter to show multiple tracks.
* New shortcode parameter 'infobar' (default false) for displaying an
  information bar with some information about the latest trackpoint during live
	tracking. The information bar can be formatted via the Trackserver user
	profile.
* Support upload of GPX 1.0 files in addition to GPX 1.1.
* Map width is now 100% by default.
* Experimental support for SendLocation iOS app
  (https://itunes.apple.com/nl/app/sendlocation/id377724446).
* Fix a bug with the OsmAnd timestamp calculation on 32-bit systems.
* Improve performance of the track management page by adding some DB indexes.
* Update Leaflet to version 0.7.7.
* Support (wrapped) GeoJSON as a format for serving tracks. Polyline is still
  the default, because GeoJSON is 10 times as big.
* Add a [tsscipts] shortcode to force-load the plugin's JavaScript in case
  the main shortcode usage detection fails.
* Rework the loading and customizing of external JavaScript, to fix some cases
  where the main shortcode detection would fail.

= v1.9 =
Release date: 1 September 2015

IMPORTANT: This release resets the OsmAnd access key and changes the TrackMe
authentication! Please read the changelog and the FAQ, and review your new
Trackserver profile for user-specific settings.

* Add Trackserver user profile for per-user options like access codes. Please
  see the FAQ section for more information!
* Move the OsmAnd access key to the user profile.
* Added a separate password for tracking with TrackMe, for use instead of the
  WordPress password. Please see the FAQ section for more information!
* Better error handling and meaningful feedback when uploading a file
  that is too large.
* Fix viewing tracks in admin in recent versions of WordPress.
* Update Leaflet to version 0.7.4.

= v1.8 =
Release date: 29 July 2015

* Fix parsing of MapMyTracks points with negative coordinates. Thanks to
	Michel Boulet for helping me find the problem.
* Add some more documentation and FAQs.

= v1.7 =
Release date: 15 June 2015

* Add a 'Delete' link in the track edit modal, so you don't have to use a
  bulk action to delete a single track.
* Do not omit tracks with zero locations in track management.
* Full i18n and Dutch translation.
* Support the 'getttripfull' action for TrackMe, to allow TrackMe 2.0
  to import your tracks from the server.
* Use HTTPS in default map tile URL.

= v1.6 =
Release date: 29 April 2015

* Add option for setting the map tile attribution, as required by most tile
  services and data providers like OSM. If non-empty, the attribution is
  displayed on every map.
* Properly escape option values on the options page.

= v1.5 =
Release date: 15 April 2015

* Make plugin run a cheap 'update' routine on every request, because we cannot
  assume that the activation hook is run every time the plugin is updated.
* Fix a bug where tracks management would not show any tracks due to a broken
  SQL query.

= v1.4 =
Release date: 8 March 2015

* Add OsmAnd live tracking support.
* Fix buggy timezone offset calculation, that would break during DST.
* Draw a start/end marker for each track on a map. Loading tracks from a
  GPX URL already supports multiple tracks.
* Add a compatibility fix for PHP < 5.4
* Fix a bug with viewing tracks in WP admin without pretty permalinks

= v1.3 =
Release date: 28 February 2015

* Fix a grave bug, that made Trackserver usable ONLY on WP installs using
  mod-rewrite and Pretty Permalinks.
* Add 'use_trackserver' capability for authors, editors and admins and use it
  to restrict tracking to those roles, while at the same time allowing non-
  admins to manage their own tracks.
* Add shortcode attribute 'gpx' for loading a GPX file directly from an
  external URL.
* Calculate and store the total distance of a track at the time of upload.
* Add bulk action to recalculate track distances.
* Add shortcode attributes: color, weight, opacity, for controlling track display style.
* Fix security bug where it was possible to delete or merge other users' tracks.
* Code cleanup and inline documentation.

= v1.2 =
Release date: 20 February 2015

* Allow GPX files to be uploaded and added to posts/pages via the WP media manager.
* Drastically improve performance of importing GPX files (via WP admin or HTTP POST).
* Fix UTC to local time conversion for GPX imports, correct for DST in most cases.
* Show start/end markers in track view modal in the admin.
* Show map name as modal title when viewing from track management in the admin.
* Optimize track view modal layout.
* Bugfixes.

= v1.1 =
Release date: 12 February 2015

* Implement 'markers' shortcode to disable start/end markers on tracks.
* Make tile server URL a configurable option.
* Bugfixes.

= v1.0 =
Release date: 10 February 2015

* Implement 'delete' bulk-action in track management.
* Implement 'merge' bulk-action in track management.
* Implement 'Upload tracks' from track management.
* Code cleanups.

= v0.9 =
Release date: 2 January 2015

* Initial release, with tracking supoort, simple shortcode and track management interface.

== Upgrade Notice ==

1.9 - This release resets the OsmAnd access key and changes the TrackMe authentication! Please
read the changelog and the FAQ, and review your new Trackserver profile for user-specific settings.
