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
* HTTP POST, for example using [AutoShare](https://play.google.com/store/apps/details?id=com.dngames.autoshare)

A shortcode is provided for displaying your tracks on a map. Maps are displayed
using the fantastic [Leaflet library](http://leafletjs.com/) and some useful Leaflet plugins
are included. Maps can be viewed in full-screen on modern browsers.

# Credits

This plugin was written by Martijn Grendelman. It includes some code and libraries written by other people:

* [Polyline encoder](https://github.com/emcconville/polyline-encoder) by Eric McConville
* [Leaflet.js](http://leafletjs.com/) by Vladimir Agafonkin and others
* [Leaflet.fullscreen](https://github.com/Leaflet/Leaflet.fullscreen) by John Firebaugh and others
* [Leaflet-omnivore](https://github.com/mapbox/leaflet-omnivore) by Mapbox
* [GPXpress](https://wordpress.org/support/plugin/gpxpress) by David Keen was an inspiration sometimes

# Frequently Asked Questions

## What are the available shortcode attributes?

* track: track id or 'live'
* width: map width
* height: map height
* align: 'left', 'center' or 'right'
* class: a CSS class to add to the map div for customization

Example: [tsmap track=39 align=center class=mymap]

## What is live tracking?

By using the shortcode with the 'track=live' parameter, the most recently updated track
belonging to the author of the current post/page is published on the map.

The track is updated with the latest trackpoints every 10 seconds and the map
view is always centered to the most recent trackpoint. A marker is shown in
that location. Live tracking can be stopped and restarted with a simple control
button that is shown on the map.

## What about security?

The plugin uses a custom Wordpress capability to manage who can use the
tracking features and manage their own tracks. The capability is granted to
authors, editors and administrators, but not to subscribers. This is hardcoded
for now, and (re)activation of the plugin will re-grant the capability to
the three listed roles.

Users who can create/edit posts can also use the [tsmap] shortcode
and publish maps with their own tracks. It is not possible for users (not even
admins) to publish (or manage) other people's tracks.

Tracks can only be published in Wordpress posts or pages, and cannot be
downloaded from outside Wordpress. Requests for downloading tracks need to
have a cryptographic signature that only Wordpress can generate.

## What GPX namespaces are supported for GPX import (via HTTP POST)?
Only http://www.topografix.com/GPX/1/1 at the moment.

## Is it free?
Yes. Donations are welcome. Please visit
http://www.grendelman.net/wp/trackserver-wordpress-plugin/
for details.

