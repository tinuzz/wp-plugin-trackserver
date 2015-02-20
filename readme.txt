=== Trackserver ===
Contributors: tinuzz
Donate link: http://www.grendelman.net/wp/trackserver-wordpress-plugin/
Tags: gps, gpx, map, leaflet, track, mobile, tracking
Requires at least: 4.0
Tested up to: 4.1
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
* [MapMyTracks protocol][mapmytracks], for example using [OruxMaps][oruxmaps]
* HTTP POST, for example using [AutoShare][autoshare]

A shortcode is provided for displaying your tracks on a map. Maps are displayed
using the fantastic [Leaflet library][leafletjs] and some useful Leaflet plugins
are included. Maps can be viewed in full-screen on modern browsers.

[trackme]: http://www.luisespinosa.com/trackme_eng.html
[mapmytracks]: https://github.com/MapMyTracks/api
[oruxmaps]: http://www.oruxmaps.com/index_en.html
[autoshare]: https://play.google.com/store/apps/details?id=com.dngames.autoshare
[leafletjs]: http://leafletjs.com/

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

* Support [OsmAnd's](http://osmand.net/) tracking protocol
* Support [GpsGate](http://gpsgate.com/) tracking protocol
* Explicitly allow/disallow users to use the tracking features (or use minimal capabilities)
* More shortcode parameters and map options
* More track management features
* Track statistics, like distance, average speed, etc.
* Add map profiles, maybe include [leaflet-providers](https://github.com/leaflet-extras/leaflet-providers) plugin
* Internationalization
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

* track: track id or 'live'
* width: map width
* height: map height
* align: 'left', 'center' or 'right'
* class: a CSS class to add to the map div for customization
* markers: true (default) or false (or 'f', 'no' or 'n') to disable start/end markers on the track

Example: [tsmap track=39 align=center class=mymap markers=n]

= What is live tracking? =

By using the shortcode with the 'track=live' parameter, the most recently updated track
belonging to the author of the current post/page is published on the map.

The track is updated with the latest trackpoints every 10 seconds and the map
view is always centered to the most recent trackpoint. A marker is shown in
that location. Live tracking can be stopped and restarted with a simple control
button that is shown on the map.

= What about security? =

By installing this plugin, all your local users get the ability to use the
tracking features. There is currently no way to allow/disallow users to use these
features.

Users who can create/edit posts can also use the [tsmap] shortcode
and publish maps with their own tracks. It is not possible for users (not even
admins) to publish other people's tracks.

Track management is restricted to users with the 'manage_options' capability,
which are only administrators by default. So, users who are not administrators
can create tracks but not manage them. This will be addressed in a future version.

Tracks can only be published in Wordpress posts or pages, and cannot be
downloaded from outside Wordpress. Requests for downloading tracks need to
have a cryptographic signature that only Wordpress can generate.

= What GPX namespaces are supported for GPX import (via HTTP POST)? =
Only http://www.topografix.com/GPX/1/1 at the moment.

= Is it free? =
Yes. Donations are welcome. Please visit
http://www.grendelman.net/wp/trackserver-wordpress-plugin/
for details.

== Screenshots ==

1. The track management page in the WordPress admin
2. Configuration of OruxMaps for use with Trackserver / WordPress

== Changelog ==

= UNRELEASED =
* Allow GPX files to be uploaded and added to posts/pages via the WP media manager

= v1.1 =
* Implement 'markers' shortcode to disable start/end markers on tracks
* Make tile server URL a configurable option
* Bugfixes

= v1.0 =
* Implement 'delete' bulk-action in track management
* Implement 'merge' bulk-action in track management
* Implement 'Upload tracks' from track management
* Code cleanups

= v0.9 =
* Initial release, with tracking supoort, simple shortcode and track management interface

== Upgrade Notice ==

= 1.0 =
This will be the first stable release.

