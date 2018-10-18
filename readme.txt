=== Trackserver ===
Contributors: tinuzz
Donate link: http://www.grendelman.net/wp/trackserver-wordpress-plugin/
Tags: gps, gpx, map, leaflet, track, mobile, tracking
Requires at least: 4.0
Tested up to: 4.9
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

GPS Track Server for TrackMe, OruxMaps and others

== Description ==

Getting your GPS tracks into Wordpress and publishing them has never been easier!

Trackserver is a plugin for storing and publishing GPS routes. It is a server companion to several mobile apps for location tracking, and it can display maps with your tracks on them by using a shortcode. It can also be used for live tracking, where your visitors can follow you or your users on a map.

Unlike other plugins that are about maps and tracks, Trackserver's main focus is not the publishing, but rather the collection and storing of tracks and locations. It's all about keeping your data to yourself. Several mobile apps and protocols are supported for getting tracks into trackserver:

* [TrackMe][trackme]
* [MapMyTracks protocol][mapmytracks], for example using [OruxMaps][oruxmaps], including upload functionality
* [OsmAnd's][osmand] live tracking protocol
* [SendLocation][sendlocation]
* [OwnTracks][owntracks] (experimental support)
* HTTP POST, for example using [AutoShare][autoshare]

A shortcode [tsmap] is provided for displaying your tracks on a map. Maps are displayed using the fantastic [Leaflet library][leafletjs] and some useful Leaflet plugins are included. Maps can be viewed in full-screen on modern browsers.

To publish a map with a track in a post or a page, just include the shortcode:

[tsmap track=&lt;id&gt;]

See the FAQ section for more information on the shortcode's supported attributes.

[trackme]: http://www.luisespinosa.com/trackme_eng.html
[mapmytracks]: https://github.com/MapMyTracks/api
[osmand]: http://osmand.net/
[oruxmaps]: http://www.oruxmaps.com/index_en.html
[sendlocation]: https://itunes.apple.com/bm/app/sendlocation/id377724446?mt=8
[owntracks]: http://owntracks.org/
[autoshare]: https://play.google.com/store/apps/details?id=com.dngames.autoshare
[leafletjs]: http://leafletjs.com/

= Requirements =

Trackserver requires PHP 5.3 or newer and it needs both DOMDocument and SimpleXML extensions installed.

= Credits =

This plugin was written by Martijn Grendelman. Development is tracked on Github: https://github.com/tinuzz/wp-plugin-trackserver

It includes some code and libraries written by other people:

* [Polyline encoder](https://github.com/emcconville/polyline-encoder) by Eric McConville
* [Leaflet.js][leafletjs] by Vladimir Agafonkin and others
* [Leaflet.fullscreen](https://github.com/Leaflet/Leaflet.fullscreen) by John Firebaugh and others
* [Leaflet-omnivore](https://github.com/mapbox/leaflet-omnivore) by Mapbox
* [Promise-polyfill](https://github.com/taylorhakes/promise-polyfill) by Taylor Hakes
* [GPXpress](https://wordpress.org/support/plugin/gpxpress) by David Keen was an inspiration sometimes

= TODO =

* More track management features, like folders/collections
* Better permissions/authorization system
* Track statistics, like distance, average speed, etc.
* Add map profiles, maybe include [leaflet-providers](https://github.com/leaflet-extras/leaflet-providers) plugin
* Add track decorations, for example with the [PolylineDecorator](https://github.com/bbecquet/Leaflet.PolylineDecorator) plugin
* ...

More TODO-items and feature ideas in the TODO file contained in the plugin archive.

== Installation ==

1. Use Wordpress' built-in plugin installer, or copy the folder from the plugin archive to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Configure the slugs that the plugin will listen on for location updates.
1. Configure your mobile apps and start tracking!
1. Use the shortcode [tsmap] to include maps and tracks in your posts and pages.

== Frequently Asked Questions ==

= What are the available shortcode attributes? =

For the [tsmap] shortcode:

* track: one or more track IDs, separated by commas, or 'live' (deprecated, 'user=@' is preferred).
* id: an alias for 'track'
* user: one or more user IDs, separated by commas, who's latest track to follow 'live'. A literal '@' means the author of the post (you). When viewing the map, the liveupdate feature will follow the track of the first user specified. When the end-marker is enabled for a live track (and why shouldn't it?), clicking it will change the focus of the liveupdate to that track. The map view will follow the latest location and the infobar (if present) will display its information.
* live: true (or 't', 'yes' or 'y'), or false (default), to force live tracking for this map. This can be used for example with an externally updated GPX or KML file.
* maxage: the maximum age of a live track for it to be included on the map. If this parameter is given, all user tracks that have not been updated in the last X amount of time, will not be displayed. The value is a time expression in the form of a number and a unit, for example: '120s', '30m', '2h', '3d'.
* width: map width, default: 100%.
* height: map height, default: 480px.
* align: 'left', 'center' or 'right', default: not set.
* class: a CSS class to add to the map div for customization, default: not set.
* continuous: true (default) or false (or 'f', 'no' or 'n'), for lack of a better word, to indicate whether multiple tracks should be considered as one continuous track. The only effect this has, at the moment, is that intermediate start markers are yellow instead of green.
* gpx: one or more URLs to GPX files to be plotted on the map. Multiple URLs should be separated by spaces, and the value as a whole enclosed by double quotes (gpx="http://....gpx http://....gpx"). If enabled in the settings, when a url is prefixed with the string 'proxy:', the request is proxied through Trackserver.
* kml: one or more URLs to KML files to be plotted on the map. Multiple URLs should be separated by spaces, and the value as a whole enclosed by double quotes (kml="http://....kml http://....kml"). If enabled in the settings, when a url is prefixed with the string 'proxy:', the request is proxied through Trackserver.
* infobar: true (or 't', 'yes' or 'y'), false (default), or a template string, to specify whether an information bar should be shown on the map, when live tracking is active. This only works with 'track=live' or the 'user' parameter, and has no effect in other cases. When multiple live tracks are requested, the infobar will display the data of the first track only. Instead of 'true' or 'yes', a template string containing one or more placeholders (like {lat}, {lon}, {speedkmh}, etc.) can be given to the attribute, in which case it overrides the value specified in the user profile. Please check the Trackserver user profile page in the WordPress backend for which placeholders are supported.
* zoom: the zoom factor to use for the map, a number between 0 and 18. For a map with live tracks, this number is absolute. For a map with only static tracks, this number represents the maximum zoom level, so the map will always fit all the tracks.

The following attributes apply to tracks that are drawn on the map. Each of them can contain multiple values, separated by commas (or colons, in the case of 'dash'), to be applied to different tracks in order. If there a are less values than tracks, the last value will be applied to the remaining tracks.

* markers: one or more of the following values: true (default) or false (or 'f', 'no' or 'n') to disable start/end markers on the track. The value can also be 'start', 's', 'end' or 'e', to draw markers only for the start or the end of a track respectively.
* color: one or more colors, separated by commas, to use for the tracks on the map. Default comes from Leaflet.
* weight: one or more weights, separated by commas, to use for the tracks on the map. Default comes from Leaflet.
* opacity: one or more opacities, separated by commas, to use for the tracks on the map. Default comes from Leaflet.
* dash: one or more [dashArrays][dasharray], seperated by colons (:), to use for the tracks on the map. Default is no dashes.
* points: true (or 't', 'yes' or 'y'), or false (default), to specify whether the track should be displayed as a line or a collection of points.

[dasharray]: https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-dasharray

Example: [tsmap track=39,84 user=@ align=center class=mymap markers=n color=#ff0000]

When you specify multiple values, please be aware of the following. While track order will be preserved within each track type, different track types are evaluated in a specific order, and styling values are applied in that order too. The order is:

1. Static tracks (track=a,b,c)
2. Live user tracks (user=x,y,z)
3. GPX tracks (gpx=...)
4. KML tracks (kml=...)

To prevent confusion, I suggest you specify tracks in this order in your shortcode too.

Example: [tsmap gpx=/url/for/file.gpx user=jim track=10,99 color=red,blue,green,yellow]

In this case, the GPX track will be yellow, Jim's live track will be green and tracks 10 and 99 will be red and blue respectively.

In a feature request I was asked to make it possible to draw just a marker on the last known location, and not draw a track at all. Use 'markers' and 'opacity' to accomplish this:

Example: [tsmap user=@ markers=e opacity=0.0]

Attributes for the [tslink] shortcode:

* track: same as for [tsmap]
* id: same as for [tsmap]
* user: same as for [tsmap]
* text: the text to render insde the &lt;a&gt;...&lt;/a&gt; tags
* class: a CSS class to apply to the 'a' element
* format: the format in which to download the tracks. Only 'gpx' (the default) is supported at this time. Other formats like KML may follow. Send a feature request if you need a specific format.

Example: [tslink track=1,2,3,4 text="Download the tracks of our 4-day hike in GPX format"]

Instead of using the 'text' attribute, you can also use shortcode to enclose the text:

Example: [tslink track=1,2,3,4]Download the tracks of our 4-day hike in GPX format[/tslink]

= I used the shortcode but the map doesn't show =

Trackserver tries to detect the usage of the [tsmap] shortcode to prevent unnecessary loading of the plugin's JavaScript. In some circumstances, for example, if your page setup is complex and uses multiple loops, the detection could fail. In such a case, use this shortcode in the main post or page to force loading the JavaScript:

[tsscripts]

There is one caveat. Trackserver only works on 'real' posts and pages, in WordPress terms. For example, some WordPress themes (I have seen one) offer the possibility to specify a static homepage right in the theme settings, completely overriding WordPress' internal post logic. In this case, the page lacks an author as well as other properties that a regular WordPress post or page has. Trackserver's [tsmap] shortcode does not work on pages like that.

All that said, Trackserver's shortcode detection should be reasonably fool-proof these days, because if the early detection mechanism fails, the shortcode itself is used to trigger the inclusion of Trackserver's JavaScript and CSS. The only adverse side effect this may have, is that the CSS is loaded in the footer of the page, rather than in the head section. It's hard to say whether this will actually be a problem, but just in case it is, the [tsscripts] shortcode is there.

Beware though: the [tsscripts] shortcode doesn't actually do anything. At all. It is merely an extra shortcode that Trackserver tries to detect. Therefore, it is absolutely useless to include [tsscripts] in the same context as your [tsmap]. I appreciate that this can be hard to understand, so let me illustrate with an example. Take the 'Posts in page' plugin, that allows you to use a shortcode in a post (let's call it A) to include other posts an pages inline (let's call them B and C). If [tsmap] is used in B or C, but the page requested by the user is A, which does not have a [tsmap], Trackserver's shortcode detection used to fail in earlier versions, and the Javascript and CSS would not be loaded. By using the [tsscripts] shortcode in page A, the loading of JS and CSS can be forced. The CSS will then be loaded in the head of the page, instead of in the footer.

= Trying to show a GPX or KML file shows an error popup: "Track could not be loaded: undefined undefined" =

This will happen when you try to load a file from a different domain than your site is running on, and the remote server doesn't serve a 'Access-Control-Allow-Origin' header that allows acces to the file. Your webbrowser refuses to process the file. Check the console in your developer tools for an error message. Please read up on [Cross-Origin Resource Sharing (CORS)](https://en.wikipedia.org/wiki/Cross-origin_resource_sharing) for more information. This is not something that Trackserver can fix.

= What is live tracking? =

By using the shortcode with the 'user=...' or 'track=live' parameter, the most recently updated track belonging to the specified user(s) or the author of the current post/page is published on the map.

The track is updated with the latest trackpoints every 10 seconds and the map view is always centered to the most recent trackpoint. A marker is shown in that location. Live tracking can be stopped and restarted with a simple control button that is shown on the map.

To publish other users' tracks, the author of the page needs the 'trackserver_publish' capability, which is by default only granted to administrators and editors.

= What is a Trackserver user profile? (since v1.9) =

Before v1.9, all of Trackserver's settings were stored in a single, global place in WordPress, meaning they were shared among all users in the same WordPress install. This was also the case for the OsmAnd access key that allows OsmAnd users to use Trackserver for live tracking. Since Trackserver tries to be multi-user, things like access keys do not belong in the global configuration.

In version 1.9, user profile settings were introduced as a place for per-user settings, like access keys. There is a separate page in the WordPress admin for the Trackserver profile (called 'Your profile' in English), that is accessible by all users that have the right (capability) to use Trackerver.

= What changed for TrackMe authentication 1.9? =

TrackMe, like OsmAnd, uses HTTP GET requests for communication with Trackserver.  This means that all data from the app, including your password, becomes part of the URL. Because URLs are not generally considered secret, and may be logged in access logs and what not, this is quite insecure, even with HTTPS.

For OsmAnd, that has no built-in authentication, Trackserver has used an access key instead of your WordPress password from the very beginning. For TrackMe, this was implemented in v1.9. So from version 1.9 onward, every WordPress user that is allowed to use Trackserver has its own separate access key for OsmAnd and a separate password for TrackMe, settable in the Trackme user profile.

If you have been using Trackserver with TrackMe before v1.9, you should now set the password in TrackMe to this new password, instead of your WordPress password.  Like your password, you should keep your access keys to yourself, but the idea is that the security impact of such a key is low, compared to your WordPress password, and that you can (and should!) change the keys regularly.  No real effort is made to keep the keys secure (they are stored in the database unhashed, for example), so in any case, try not to use a sensitive password.

= What is this 'slug' you are talking about? =

Slugs are generally defined as URL-friendly and unique versions of a name or title. In WordPress, they are short descriptions of posts and pages, to be used in URLs (permalinks). They are the part of the URL that makes WordPress serve a particular page. Trackserver uses slugs to 'listen' for tracking requests from mobile apps, and you can configure these slugs to be anything you want.  Trackserver comes with default values for these slugs, that should work for most people. Changing them is usually not necessary nor recommended. Please
read the WARNING blow before changing them.

Please refer to the [Wordpress Codex](http://codex.wordpress.org/Glossary#Slug) for more information about slugs in general.

WARNING: please do not confuse the slugs that you configure in Trackserver with the URLs (permalinks) that you use to publish your maps and tracks. Above all, make sure there is no conflict between the permalink for a post or page and the slugs that Trackserver uses for location updates. The slugs in Trackerver's configuration are for the location updates ONLY. If you try to open them in a browser, you will get errors like 'Illegal request'. Trackserver operates on a low level within WordPress, so if there is a conflict between Trackserver and a post or a page, Trackserver will win and the page will be inaccessible.

To publish your tracks, simply create a page (with a non-conflicting permalink) and use the [tsmap] shortcode to add a map.

= Can Trackserver support protocol X or device Y? =

Trackserver, being a WordPress plugin, can only support HTTP-based protocols for tracking. Many tracking devices use TCP- but not HTTP-based protocols for online tracking, and as such, Trackserver cannot support them, at least not without some middleware that translates the device's protocol to HTTP.

If a device or an app does use HTTP as a transport, adding support for it in Trackserver should be quite easy. For example, I have been thinking about support for the GpsGate Server Protocol. It could be added if there is any demand for it.

If a device or an app uses a different transport, support could be added by implementing some sort of middleware. For example, [OwnTracks](http://owntracks.org/) uses MQTT and ships with a script ([m2s](https://github.com/owntracks/backend/tree/master/m2s)) for storing the data. Storage methods in m2s are pluggable, so one could write an m2s-plugin for shipping the data to Trackserver.

= What about security? =

== Using Trackserver ==

The plugin uses a few custom Wordpress capabilities ('use_trackserver', 'trackserver_publish' and 'trackserver_admin') to manage the various levels of access within Trackserver:

* To use the tracking features and manage and publish ones own tracks, the 'use_trackserver' capability is required. It is granted to authors, editors and administrators, but not to subscribers.
* To publish other people's tracks, the 'trackserver_publish' capability is required. It is granted to editors and administrators.
* To manage Trackserver's options, the 'trackserver_admin' capability is required. Only admins get this capability by default.

If you remove one or more capabilities from the listed roles, they will be re-granted on (re)activation of the plugin.

Tracks can only be published in Wordpress posts or pages, and cannot be downloaded from outside Wordpress. Requests for downloading tracks need to have a cryptographic signature (called a 'nonce') that only Wordpress can generate.

Regarding the use of apps for live tracking and uploading to Wordpress, please read the considerations about authentication above.

== External tracks proxy ==

Trackserver contains code that can proxy requests to- and serve content from remote 3rd-party servers. This allows authors to work around CORS restrictions. Instead of letting the visitor's browser get the GPX or KML from the remote server (which only works if the server implements CORS headers to allow the request), the request is sent to WordPress, where Trackserver will fetch the track from the remote server and send it to the browser.

This opens all kinds of interesting possibilities, but it is also a security risk. Your authors can use the proxy function to invoke HTTP requests to remote servers, which now originate from your server, and the response of which will be processed by your WordPress installation. This could have different adverse effects, ranging from legal liability to denial-of-service on your server.

Therefore, the proxy function is disabled by default. It can be enabled in the 'advanced' section of Trackserver's settings, but I recommend to only enable it if you need it, and if you trust your authors not to use it on harmful URLs.

The proxy code can be invoked via a 'gettrack' request, but like all requests for tracks, it has to be signed with a valid nonce, so it should be impossible to abuse the proxy from outside WordPress.

= What GPX namespaces are supported for GPX import (via HTTP POST or upload via backend)? =

GPX 1.1 (http://www.topografix.com/GPX/1/1) and GPX 1.0 (http://www.topografix.com/GPX/1/0).

= Is it free? =
Yes. Donations are welcome. Please visit http://www.grendelman.net/wp/trackserver-wordpress-plugin/ for details.

== Screenshots ==

1. The track management page in the WordPress admin
2. Configuration of OruxMaps for use with Trackserver / WordPress

== Changelog ==

= v4.2.2 =
Release date: 18 October 2018

* Fix Leaflet JS/CSS URLs, this change was forgotten to be included in v4.2

= v4.2.1 =
Release date: 18 October 2018

* Update Dutch translation for changes in v4.2

= v4.2 =
Release date: 18 October 2018

* Add some replacement tags for the infobar. All relevant metric and imperial units are now represented, and some of the tags now have different variants for specifying the number of decimals. Please see the text near the form field in your Trackserver profile for more information. Backwards-incompatible change: the distance and speed tags now have 0 decimals by default, that used to be 2.
* After a network error, remove error popup from the map on the next succesful track load.
* Updated Leaflet to version 1.3.4.

= v4.1 =
Release data: 8 October 2018

Added:
* OwnTracks Friends and Cards responses to Location requests.
* WordPress user avatars to OwnTracks responses, if available.
* Input fields on the Trackserver user profile page for managing OwnTracks share/follow friends.
* Stub functions for responding more nicely to TrackMe cloud sharing requests.
* A mouseover-tooltip on end-markers of live tracks, showing the user's displayname.
* The user's display name in the title of the Trackserver profile page.

Fixed:
* Missing gettext domain on some localized strings.
* Added some missing inline documentation in the code.

= v4.0.2 =
Release date: 23 February 2018

Fixed:
* Properly escape user-supplied input that is used in printf() format strings
* Make the default tile server URL use https instead of http

= v4.0.1 =
Release date: 23 February 2018

IMPORTANT BUGFIX: If you did a fresh install of v4.0, a column was missing from a database table,
causing location updates and GPX file uploads to fail. This release adds the column if it is missing.
Upgrades from 3.0 to 4.0 are not affected by this bug (but should still update).

Fixed:
* Updated Lithuanian translation. Thanks, Dainius Kaupaitis.
* Add title to the overlay for viewing geofences.
* Add missing database column for new installations.

= v4.0 =
Release date: 22 February 2018

This is another big update. Read more about it here: http://www.grendelman.net/wp/trackserver-v4-0-released/.

Added:
* A track editor in the WP admin, based on Leaflet.Editable. It allows you to move track points and split tracks.
* Bulk action for viewing multiple tracks at once in the admin. Editing them also works.
* Geofencing support, allowing you to hide or drop location updates within certain areas.
* A proxy for external KML and GPX tracks, to work around CORS restrictions.
* 'maxage' shortcode parameter to impose time-based limit on live tracks.
* OwnTracks HTTP support, locations request handling only for now.
* Bulk action for downloading tracks as GPX.
* A {distance} tag for infobar template, for total track distance in meters.
* Information about live tracking URLs and howto's for mobile apps on the user's Trackserver profile.
* Information on how to use live tracking in OsmAnd.
* Lithuanian translation, thanks to Dainius Kaupaitis.
* PHP coding style checks and automated testing with TravisCI.

Changed:
* Make the 'infobar' shortcode attribute accept a string, to override the template set in the user profile.
* Show a popup on the map with an internationalized message when there are not tracks to display.
* When a (live) track that is currently shown on the map is no longer present in the server response, show a nice popup, suggesting a page reload.
* Limit the TrackMe 'gettriplist' command to the 25 latest tracks, serve them in reverse order.
* Increase WP-admin 'View track' modal window size to 1024x768.
* Updated Polyline encoder from Eric McConville to v1.3.
* Updated Leaflet to version 1.3.1.
* Updated Leaflet-fullscreen to version 1.0.2.

Fixed:
* In JavaScript, store track information from the server more reliably.
* Improve HTTP responses around authentication failure.
* Recalculate the total track distance after merging multiple tracks.
* Easier access to Leaflet map object from 3rd party JavaScript (issue #9).
* Handle localized decimal numbers from SendLocation (issue #12).
* Some minor JavaScript and PHP issues.
* Many many many PHP coding style fixes.

= v3.0.1 =
Release date: 28 February 2017

* Add cache busters for JavaScript files

= v3.0 =
Release date: 27 February 2017

This is a BIG update. Please read https://www.grendelman.net/wp/trackserver-v3-0-released/ for the full story!

* Fix a bug where Trackserver would only import the first segment of a track from GPX.
* Get rid of suboptimal shortcode detection fallback mechanism.
* Upgrade Leaflet to version 1.0.3.
* Sync trackserver-omnivore.js with leaflet-omnivore-0.3.4.
* Update leaflet-liveupdate to version 1.1
* Fix a bug in the admin where some superfluous text was included in track URLs.
* Implement loading all tracks for a map in a single HTTP request.
* Add WordPress MultiUser support.
* Replace track marker images by L.CircleMarker objects and gain dynamic marker colouring. The old images are removed from the plugin installation.
* Add {userid}, {userlogin} and {displayname} as possible tags in the infobar template.
* Add 'user' attribute to 'tsmap' shortcode, for displaying multiple users' live tracks in a map.
* Add 'live' attribute to force live-update without live tracks, for use with external (gpx/kml) tracks. It can also be used to force-disable live updates for maps with user tracks.
* Add 'zoom' attribute to influence the initial zoom level of the map. Behaves differently for maps with live tracks than for maps with only static tracks.
* Add 'id' attribute as an alias for 'track', because I keep using that for some strange reason.
* Maps without live tracks now start at zoom level 12 instead of 6, until the tracks are loaded.
* Remove support for GeoJSON as Trackserver's internal trackformat, only polyline remains.
* Shortcode parameters 'markers', 'color', 'weight', 'opacity' and 'points' can now contain a comma-separated list of values, which will be applied to respective tracks in the 'track', 'user', 'gpx' or 'kml' attributes (in that order). If less values than tracks are given, the last value is applied to all remaining tracks.
* Add 'dash' attribute, behaving the same as 'markers', 'color', etc. to specify a 'dashArray' to use for the track(s).
* Make the 'weight' parameter control the point radius when 'points=y'
* It is now possible to mix 'track', 'user', 'gpx' and 'kml' in a single map.
* Add a new shortcode called 'tslink', that produces a download link for one or more tracks in a single file. Only GPX is supported at this time.
* Documentation updates and additions.
* Make end-markers on a live-map clickable to make liveupdate follow that track.
* Rename a column in the tracks table, it has been misnamed since v1.0.
* Add an uninstall.php file that removes database tables and options.
* Add WordPress MultiUser support.
* Add a debug function for writing stuff to debug.log.
* Add Promise-polyfill by Taylor Hakes, to support older browsers (most notably IE 9-11).

= v2.3 =
Release date: 23 December 2016

This release is long overdue; most of these changes were made months ago, and I apologize.

* Fix a bug with the 'Upload tracks' buttons in the admin
* Support loading KML files just like GPX, introducing a new shortcode parameter 'kml'
* Fix long standing bug with missing Thickbox on the options page
* Improve admin modal windows positioning

= v2.2 =
Release date: 19 July 2016

* Calculate and update speed per trackpoint when calculating the track distance.
* New replacement tags for a live track's infobar: altitude and speed in m/s, km/h and mph.
* Add shortcode parameter 'points=y|n', to draw a track as a collection of points instead of a line. The 'color' parameter applies to the fill of the points in this case. Be careful with too many points (thousands).
* Change the default tile server URL to the basic OpenStreetMap

= v2.1 =
Release date: 6 June 2016

* Implement MapMyTracks 'upload_activity' request. You can now upload tracks to trackserver directly from OruxMaps without the use of AutoShare.
* Support values 'start', 's', 'end' and 'e' for the 'markers' attribute, to restrict drawing markers to start or end point only.
* Introduce new capability 'trackserver_admin' and grant it to administrators.
* Allow admins to manage and publish other users' tracks, using the new capability.
* CSS fixes for track management on small screens.

= v2.0.2 =
Release date: 23 December 2015

* Previous bugfix was incomplete. Thanks to eHc for another report.

= v2.0.1 =
Release date: 23 December 2015

* Fix bug with incorrect use of $wpdb->prepare. Thanks to eHc for finding it.

= v2.0 =
Release date: 22 December 2015

* Support mutiple tracks in a single map. Use a comma-separated list of track IDs (and/or 'live') in the 'track' parameter to show multiple tracks.
* New shortcode parameter 'infobar' (default false) for displaying an information bar with some information about the latest trackpoint during live tracking. The information bar can be formatted via the Trackserver user profile.
* Support upload of GPX 1.0 files in addition to GPX 1.1.
* Map width is now 100% by default.
* Experimental support for SendLocation iOS app (https://itunes.apple.com/nl/app/sendlocation/id377724446).
* Fix a bug with the OsmAnd timestamp calculation on 32-bit systems.
* Improve performance of the track management page by adding some DB indexes.
* Update Leaflet to version 0.7.7.
* Support (wrapped) GeoJSON as a format for serving tracks. Polyline is still the default, because GeoJSON is 10 times as big.
* Add a [tsscipts] shortcode to force-load the plugin's JavaScript in case the main shortcode usage detection fails.
* Rework the loading and customizing of external JavaScript, to fix some cases where the main shortcode detection would fail.

= v1.9 =
Release date: 1 September 2015

IMPORTANT: This release resets the OsmAnd access key and changes the TrackMe authentication! Please read the changelog and the FAQ, and review your new Trackserver profile for user-specific settings.

* Add Trackserver user profile for per-user options like access codes. Please see the FAQ section for more information!
* Move the OsmAnd access key to the user profile.
* Added a separate password for tracking with TrackMe, for use instead of the WordPress password. Please see the FAQ section for more information!
* Better error handling and meaningful feedback when uploading a file that is too large.
* Fix viewing tracks in admin in recent versions of WordPress.
* Update Leaflet to version 0.7.4.

= v1.8 =
Release date: 29 July 2015

* Fix parsing of MapMyTracks points with negative coordinates. Thanks to Michel Boulet for helping me find the problem.
* Add some more documentation and FAQs.

= v1.7 =
Release date: 15 June 2015

* Add a 'Delete' link in the track edit modal, so you don't have to use a bulk action to delete a single track.
* Do not omit tracks with zero locations in track management.
* Full i18n and Dutch translation.
* Support the 'getttripfull' action for TrackMe, to allow TrackMe 2.0 to import your tracks from the server.
* Use HTTPS in default map tile URL.

= v1.6 =
Release date: 29 April 2015

* Add option for setting the map tile attribution, as required by most tile services and data providers like OSM. If non-empty, the attribution is displayed on every map.
* Properly escape option values on the options page.

= v1.5 =
Release date: 15 April 2015

* Make plugin run a cheap 'update' routine on every request, because we cannot assume that the activation hook is run every time the plugin is updated.
* Fix a bug where tracks management would not show any tracks due to a broken SQL query.

= v1.4 =
Release date: 8 March 2015

* Add OsmAnd live tracking support.
* Fix buggy timezone offset calculation, that would break during DST.
* Draw a start/end marker for each track on a map. Loading tracks from a GPX URL already supports multiple tracks.
* Add a compatibility fix for PHP < 5.4
* Fix a bug with viewing tracks in WP admin without pretty permalinks

= v1.3 =
Release date: 28 February 2015

* Fix a grave bug, that made Trackserver usable ONLY on WP installs using mod-rewrite and Pretty Permalinks.
* Add 'use_trackserver' capability for authors, editors and admins and use it to restrict tracking to those roles, while at the same time allowing non- admins to manage their own tracks.
* Add shortcode attribute 'gpx' for loading a GPX file directly from an external URL.
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

1.9 - This release resets the OsmAnd access key and changes the TrackMe authentication! Please read the changelog and the FAQ, and review your new Trackserver profile for user-specific settings.
