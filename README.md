# wp-plugin-trackserver
A WordPress plugin for GPS tracking and publishing

Getting your GPS tracks into Wordpress and publishing them has never been easier!

Trackserver is a plugin for storing and publishing GPS routes. It is a server
companion to several mobile apps for location tracking, and it can display maps
with your tracks on them by using a shortcode. It can also be used for live
tracking, where your visitors can follow you or your users on a map.

Unlike other plugins that are about maps and tracks, Trackserver's main focus
is not the publishing, but rather the collection and storing of tracks and
locations. It's all about keeping your data to yourself. Several mobile apps
and protocols are supported for getting tracks into trackserver:

* [TrackMe](http://www.luisespinosa.com/trackme_eng.html)
* [MapMyTracks protocol](https://github.com/MapMyTracks/api) for example using [OruxMaps](http://www.oruxmaps.com/index_en.html)
* [OsmAnd](http://osmand.net/) live tracking
* HTTP POST, for example using [AutoShare](https://play.google.com/store/apps/details?id=com.dngames.autoshare)

A shortcode is provided for displaying your tracks on a map. Maps are displayed
using the fantastic [Leaflet library](http://leafletjs.com/) and some useful Leaflet plugins
are included. Maps can be viewed in full-screen on modern browsers.

\[tsmap track=&lt;id&gt;\]

See the FAQ section for more information on the shortcode's supported attributes.

= Requirements =

Trackserver requires PHP 5.3 or newer and it needs both DOMDocument and
SimpleXML extensions installed.

# Credits

This plugin was written by Martijn Grendelman. It includes some code and libraries written by other people:

* [Polyline encoder](https://github.com/emcconville/polyline-encoder) by Eric McConville
* [Leaflet.js](http://leafletjs.com/) by Vladimir Agafonkin and others
* [Leaflet.fullscreen](https://github.com/Leaflet/Leaflet.fullscreen) by John Firebaugh and others
* [Leaflet-omnivore](https://github.com/mapbox/leaflet-omnivore) by Mapbox
* [GPXpress](https://wordpress.org/support/plugin/gpxpress) by David Keen was an inspiration sometimes

# Frequently Asked Questions

## What are the available shortcode attributes?

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
* kml: the URL to a KML file to be plotted on the map. 'track' and 'gpx'
  attributes take precedence over 'kml'.
* infobar: true (or 't', 'yes' or 'y'), or false (default), to specify whether
  an information bar should be shown on the map, when live tracking is active.
  This only works with 'track=live', and has no effect in other cases.
* color: the color of the track on the map, default comes from Leaflet.
* weight: the weight of the track on the map, default comes from Leaflet.
* opacity: the opacity of the track on the map, default comes from Leaflet.
* points: true (or 't', 'yes' or 'y'), or false (default), to specify whether
  the track should be displayed as a line or a collection of points.

Example: [tsmap track=39,84,live align=center class=mymap markers=n color=#ff0000]

In a feature request I was asked to make it possible to draw just a marker on the
last known location, and not draw a track at all. Use 'markers' and 'opacity' to
accomplish this:

Example: [tsmap track=live markers=e opacity=0.0]

## I used the shortcode but the map doesn't show

Trackserver tries to detect the usage of the [tsmap] shortcode to prevent
unnecessary loading of the plugin's JavaScript. In some circumstances, for
example, if your page setup is complex and uses multiple loops, the detection
could fail. In such a case, use this shortcode in the main post or page to
force loading the JavaScript:

[tsscripts]

## What is live tracking?

By using the shortcode with the 'track=live' parameter, the most recently updated track
belonging to the author of the current post/page is published on the map.

The track is updated with the latest trackpoints every 10 seconds and the map
view is always centered to the most recent trackpoint. A marker is shown in
that location. Live tracking can be stopped and restarted with a simple control
button that is shown on the map.

## What is a Trackserver user profile? (since v1.9)

Before v1.9, all of Trackserver's settings were stored in a single, global place
in WordPress, meaning they were shared among all users in the same WordPress
install. This was also the case for the OsmAnd access key that allows OsmAnd
users to use Trackserver for live tracking. Since Trackserver tries to be
multi-user, things like access keys do not belong in the global configuration.

In version 1.9, user profile settings were introduced as a place for per-user
settings, like access keys. There is a separate page in the WordPress admin for
the Trackserver profile (called 'Your profile' in English), that is accessible
by all users that have the right (capability) to use Trackerver.

## What changed for TrackMe authentication 1.9?

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

## What is this 'slug' you are talking about?

Slugs are generally defined as URL-friendly and unique versions of a name or
title. In WordPress, they are short descriptions of posts and pages, to be used
in URLs (permalinks). They are the part of the URL that makes WordPress serve a
particular page. Trackserver uses slugs to 'listen' for tracking requests from
mobile apps, and you can configure these slugs to be anything you want.
Trackserver comes with default values for these slugs, that should work for
most people. Changing them is usually not necessary nor recommended. Please
read the WARNING below before changing them.

Please refer to the [Wordpress Codex](http://codex.wordpress.org/Glossary#Slug) for more information
about slugs in general.

WARNING: please do not confuse the slugs that you configure in Trackserver
with the URLs (permalinks) that you use to publish your maps and tracks. Above
all, make sure there is no conflict between the permalink for a post or page
and the slugs that Trackserver uses for location updates. The slugs in
Trackerver's configuration are for the location updates ONLY. If you try to
open them in a browser, you will get errors like 'Illegal request'. Trackserver
operates on a low level within WordPress, so if there is a conflict between
Trackserver and a post or a page, Trackserver will win and the page will be
inaccessible.

To publish your tracks, simply create a page (with a non-conflicting permalink)
and use the [tsmap] shortcode to add a map.

## Can Trackserver support protocol X or device Y?

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

## What about security?

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

## What GPX namespaces are supported for GPX import (via HTTP POST or upload via backend)?

GPX 1.1 (http://www.topografix.com/GPX/1/1) and GPX 1.0 (http://www.topografix.com/GPX/1/0).

## Is it free?
Yes. Donations are welcome. Please visit
http://www.grendelman.net/wp/trackserver-wordpress-plugin/
for details.

