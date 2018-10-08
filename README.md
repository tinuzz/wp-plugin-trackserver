# wp-plugin-trackserver
A WordPress plugin for GPS tracking and publishing [![Build Status](https://travis-ci.org/tinuzz/wp-plugin-trackserver.svg?branch=master)](https://travis-ci.org/tinuzz/wp-plugin-trackserver)


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
* [SendLocation](https://itunes.apple.com/bm/app/sendlocation/id377724446?mt=8)
* [OwnTracks](http://owntracks.org/) (experimental support)
* HTTP POST, for example using [AutoShare](https://play.google.com/store/apps/details?id=com.dngames.autoshare)

A shortcode is provided for displaying your tracks on a map. Maps are displayed
using the fantastic [Leaflet library](http://leafletjs.com/) and some useful Leaflet plugins
are included. Maps can be viewed in full-screen on modern browsers.

\[tsmap track=&lt;id&gt;\]

See the FAQ section for more information on the shortcode's supported attributes.

For more information, please see:
* [Trackserver WordPress plugin](https://www.grendelman.net/wp/trackserver-wordpress-plugin/) - Information and demos
* [Introducing Trackserver WordPress plugin](https://www.grendelman.net/wp/introducing-trackserver-wordpress-plugin/) (11 February 2015)
* [Trackserver v2.0 released](https://www.grendelman.net/wp/trackserver-v2-0-released/) (23 December 2015)
* [Trackserver v3.0 released](https://www.grendelman.net/wp/trackserver-v3-0-released/) (27 February 2017)

# Requirements

Trackserver requires PHP 5.3 or newer and it needs both DOMDocument and
SimpleXML extensions installed.

# Credits

This plugin was written by Martijn Grendelman. It includes some code and libraries written by other people:

* [Polyline encoder](https://github.com/emcconville/polyline-encoder) by Eric McConville
* [Leaflet.js](http://leafletjs.com/) by Vladimir Agafonkin and others
* [Leaflet.fullscreen](https://github.com/Leaflet/Leaflet.fullscreen) by John Firebaugh and others
* [Leaflet-omnivore](https://github.com/mapbox/leaflet-omnivore) by Mapbox
* [Promise-polyfill](https://github.com/taylorhakes/promise-polyfill) by Taylor Hakes
* [GPXpress](https://wordpress.org/support/plugin/gpxpress) by David Keen was an inspiration sometimes

# Frequently Asked Questions

## What are the available shortcode attributes?

For the [tsmap] shortcode:

* **track**: one or more track IDs, separated by commas, or 'live' (deprecated,
  'user=@' is preferred).
* **id**: an alias for 'track'
* **user**: one or more user IDs, separated by commas, who's latest track to follow
  'live'. A literal '@' means the author of the post (you). When viewing the
  map, the liveupdate feature will follow the track of the first user specified.
  When the end-marker is enabled for a live track (and why shouldn't it?),
  clicking it will change the focus of the liveupdate to that track. The map view
  will follow the latest location and the infobar (if present) will display its
  information.
* **live**: true (or 't', 'yes' or 'y'), or false (default), to force live tracking
  for this map. This can be used for example with an externally updated GPX or
  KML file.
* **maxage**: the maximum age of a live track for it to be included on the map.
  If this parameter is given, all user tracks that have not been updated in the
  last X amount of time, will not be displayed. The value is a time expression in
  the form of a number and a unit, for example: '120s', '30m', '2h', '3d'.
* **width**: map width, default: 100%.
* **height**: map height, default: 480px.
* **align**: 'left', 'center' or 'right', default: not set.
* **class**: a CSS class to add to the map div for customization, default: not set.
* **continuous**: true (default) or false (or 'f', 'no' or 'n'), for lack of a
  better word, to indicate whether multiple tracks should be considered as one
  continuous track. The only effect this has, at the moment, is that intermediate
  start markers are yellow instead of green.
* **gpx**: one or more URLs to GPX files to be plotted on the map. Multiple URLs
  should be separated by spaces, and the value as a whole enclosed by double
  quotes (gpx="http://....gpx http://....gpx"). If enabled in the settings, when
  a url is prefixed with the string 'proxy:', the request is proxied through
  Trackserver.
* **kml**: one or more URLs to KML files to be plotted on the map. Multiple URLs
  should be separated by spaces, and the value as a whole enclosed by double
  quotes (kml="http://....kml http://....kml"). If enabled in the settings, when
  a url is prefixed with the string 'proxy:', the request is proxied through
  Trackserver.
* **infobar**: true (or 't', 'yes' or 'y'), false (default), or a template string,
  to specify whether an information bar should be shown on the map, when live
  tracking is active. This only works with 'track=live' or the 'user' parameter,
  and has no effect in other cases. When multiple live tracks are requested, the
  infobar will display the data of the first track only. Instead of 'true' or
  'yes', a template string containing one or more placeholders (like {lat},
  {lon}, {speedkmh}, etc.) can be given to the attribute, in which case it
  overrides the value specified in the user profile. Please check the Trackserver
  user profile page in the WordPress backend for which placeholders are supported.
* **zoom**: the zoom factor to use for the map, a number between 0 and 18. For a
  map with live tracks, this number is absolute. For a map with only static
  tracks, this number represents the maximum zoom level, so the map will always
  fit all the tracks.

The following attributes apply to tracks that are drawn on the map. Each of
them can contain multiple values, separated by commas (or colons, in the case
of 'dash'), to be applied to different tracks in order. If there a are less
values than tracks, the last value will be applied to the remaining tracks.

* **markers**: one or more of the following values: true (default) or false (or
  'f', 'no' or 'n') to disable start/end markers on the track. The value can
  also be 'start', 's', 'end' or 'e', to draw markers only for the start or the
  end of a track respectively.
* **color**: one or more colors, separated by commas, to use for the tracks on the
  map. Default comes from Leaflet.
* **weight**: one or more weights, separated by commas, to use for the tracks on
  the map. Default comes from Leaflet.
* **opacity**: one or more opacities, separated by commas, to use for the tracks on
  the map. Default comes from Leaflet.
* **dash**: one or more [dashArrays](https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-dasharray),
  seperated by colons (:), to use for the tracks on the map. Default is no dashes.
* **points**: true (or 't', 'yes' or 'y'), or false (default), to specify whether
  the track should be displayed as a line or a collection of points.

Example: [tsmap track=39,84,live align=center class=mymap markers=n color=#ff0000]

When you specify multiple values, please be aware of the following. While track
order will be preserved within each track type, different track types are
evaluated in a specific order, and styling values are applied in that order
too. The order is:

1. Static tracks (track=a,b,c)
2. Live user tracks (user=x,y,z)
3. GPX tracks (gpx=...)
4. KML tracks (kml=...)

To prevent confusion, I suggest you specify tracks in this order in your shortcode too.

Example: [tsmap gpx=/url/for/file.gpx user=jim track=10,99 color=red,blue,green,yellow]

In this case, due to the evaluation order, the GPX track will be yellow, Jim's
live track will be green and tracks 10 and 99 will be red and blue respectively.

In a feature request I was asked to make it possible to draw just a marker on the
last known location, and not draw a track at all. Use 'markers' and 'opacity' to
accomplish this:

Example: [tsmap track=live markers=e opacity=0.0]

Attributes for the [tslink] shortcode:

* **track**: same as for [tsmap]
* **id**: same as for [tsmap]
* **user**: same as for [tsmap]
* **text**: the text to render insde the &lt;a&gt;...&lt;/a&gt; tags
* **class**: a CSS class to apply to the 'a' element
* **format**: the format in which to download the tracks. Only 'gpx' (the default) is supported at this time. Other formats like KML may follow. Send a feature request if you need a specific format.

Example: [tslink track=1,2,3,4 text="Download the tracks of our 4-day hike in GPX format"]

Instead of using the 'text' attribute, you can also use shortcode to enclose the text:

Example: [tslink track=1,2,3,4]Download the tracks of our 4-day hike in GPX format[/tslink]

## I used the shortcode but the map doesn't show

Trackserver tries to detect the usage of the [tsmap] shortcode to prevent
unnecessary loading of the plugin's JavaScript. In some circumstances, for
example, if your page setup is complex and uses multiple loops, the detection
could fail. In such a case, use this shortcode in the main post or page to
force loading the JavaScript:

[tsscripts]

There is one caveat. Trackserver only works on 'real' posts and pages, in
WordPress terms. For example, some WordPress themes (I have seen one) offer the
possibility to specify a static homepage right in the theme settings,
completely overriding WordPress' internal post logic. In this case, the page
lacks an author as well as other properties that a regular WordPress post or
page has. Trackserver's [tsmap] shortcode does not work on pages like that.

All that said, Trackserver's shortcode detection should be reasonably
fool-proof these days, because if the early detection mechanism fails, the
shortcode itself is used to trigger the inclusion of Trackserver's JavaScript
and CSS. The only adverse side effect this may have, is that the CSS is loaded
in the footer of the page, rather than in the head section. It's hard to say
whether this will actually be a problem, but just in case it is, the
[tsscripts] shortcode is there.

Beware though: the [tsscripts] shortcode doesn't actually do anything. At all.
It is merely an extra shortcode that Trackserver tries to detect. Therefore, it
is absolutely useless to include [tsscripts] in the same context as your
[tsmap]. I appreciate that this can be hard to understand, so let me illustrate
with an example. Take the 'Posts in page' plugin, that allows you to use a
shortcode in a post (let's call it A) to include other posts an pages inline
(let's call them B and C). If [tsmap] is used in B or C, but the page requested
by the user is A, which does not have a [tsmap], Trackserver's shortcode
detection used to fail in earlier versions, and the Javascript and CSS would
not be loaded. By using the [tsscripts] shortcode in page A, the loading of JS
and CSS can be forced. The CSS will then be loaded in the head of the page,
instead of in the footer.

## Trying to show a GPX or KML file shows an error popup: "Track could not be loaded: undefined undefined"

This will happen when you try to load a file from a different domain than your
site is running on, and the remote server doesn't serve a
'Access-Control-Allow-Origin' header that allows acces to the file. Your
webbrowser refuses to process the file. Check the console in your developer
tools for an error message. Please read up on [Cross-Origin Resource Sharing
(CORS)](https://en.wikipedia.org/wiki/Cross-origin_resource_sharing) for more
information. This is not something that Trackserver can fix.

## What is live tracking?

By using the shortcode with the 'user=...' or 'track=live' parameter, the most
recently updated track belonging to the specified user(s) or the author of the
current post/page is published on the map.

The track is updated with the latest trackpoints every 10 seconds and the map
view is always centered to the most recent trackpoint. A marker is shown in
that location. Live tracking can be stopped and restarted with a simple control
button that is shown on the map.

To publish other users' tracks, the author of the page needs the
'trackserver_publish' capability, which is by default only granted to
administrators and editors.

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

### Using Trackserver

The plugin uses a few custom Wordpress capabilities ('use_trackserver',
'trackserver_publish' and 'trackserver_admin') to manage the various levels of
access within Trackserver:

* To use the tracking features and manage and publish ones own tracks, the
  'use_trackserver' capability is required. It is granted to authors, editors
  and administrators, but not to subscribers.
* To publish other people's tracks, the 'trackserver_publish' capability is
  required. It is granted to editors and administrators.
* To manage Trackserver's options, the 'trackserver_admin' capability is
  required. Only admins get this capability by default.

If you remove one or more capabilities from the listed roles, they will be
re-granted on (re)activation of the plugin.

Tracks can only be published in Wordpress posts or pages, and cannot be
downloaded from outside Wordpress. Requests for downloading tracks need to have
a cryptographic signature (called a 'nonce') that only Wordpress can generate.

Regarding the use of apps for live tracking and uploading to Wordpress, please
read the considerations about authentication above.

### External tracks proxy

Trackserver contains code that can proxy requests to- and serve content from
remote 3rd-party servers. This allows authors to work around CORS restrictions.
Instead of letting the visitor's browser get the GPX or KML from the remote
server (which only works if the server implements CORS headers to allow the
request), the request is sent to WordPress, where Trackserver will fetch the
track from the remote server and send it to the browser.

This opens all kinds of interesting possibilities, but it is also a security
risk. Your authors can use the proxy function to invoke HTTP requests to remote
servers, which now originate from your server, and the response of which will
be processed by your WordPress installation. This could have different adverse
effects, ranging from legal liability to denial-of-service on your server.

Therefore, the proxy function is disabled by default. It can be enabled in the
'advanced' section of Trackserver's settings, but I recommend to only enable it
if you need it, and if you trust your authors not to use it on harmful URLs.

The proxy code can be invoked via a 'gettrack' request, but like all requests
for tracks, it has to be signed with a valid nonce, so it should be impossible
to abuse the proxy from outside WordPress.


## What GPX namespaces are supported for GPX import (via HTTP POST or upload via backend)?

GPX 1.1 (http://www.topografix.com/GPX/1/1) and GPX 1.0 (http://www.topografix.com/GPX/1/0).

## Is it free?
Yes. Donations are welcome. Please visit
http://www.grendelman.net/wp/trackserver-wordpress-plugin/
for details.

