var Trackserver = (function () {

    return {

        mapdata: {},
        ts_tracks: {},
        ts_latlngs: {},
        ts_titles: {},
        ts_markers: {},
        timer: false,
        adminmap: false,

        init: function (mapdata) {
            this.mapdata = mapdata;
            this.create_maps();
        },

        process_data: function (data, options) {
            var o = typeof data === 'string' ?  JSON.parse(data) : data;
            var ts_latlng = new L.latLng(o.metadata.last_trkpt_lat, o.metadata.last_trkpt_lon);
            this.ts_latlngs[options.div_id] = ts_latlng;
            var ts_title = o.metadata.last_trkpt_time;
            this.ts_titles[options.div_id] = ts_title;
            return o.track;
        },

        draw_track: function (map, track_url, div_id, is_live) {

            if (track_url) {
                var old_track = false;
                var old_marker = false;
                var ts_markers = this.ts_markers;
                var ts_latlngs = this.ts_latlngs;
                var ts_titles = this.ts_titles;

                // Identify any existing track layer and marker
                if (this.ts_tracks.hasOwnProperty(div_id)) {
                    old_track = this.ts_tracks[div_id];
                }
                if (this.ts_markers.hasOwnProperty(div_id)) {
                    old_marker = this.ts_markers[div_id];
                }

                // First draw the new track...
                var runLayer = omnivore.polyline(track_url, {'ondata': L.bind(this.process_data, this), 'div_id': div_id} )
                    .on ('ready', function () {
                        // ...and then delete the old one, to prevent flickering
                        if (old_track) map.removeLayer (old_track);
                        if (is_live) {
                            if (old_marker) map.removeLayer (old_marker);
                            ts_markers[div_id] = new L.marker(ts_latlngs[div_id], {title: ts_titles[div_id]}).addTo(map);
                            // Then, center the map on the last point / current position
                            this._map.setView(ts_latlngs[div_id], map.getZoom());
                        }
                        else {
                            this._map.fitBounds(this.getBounds());
                        }
                    })
                    .on('error', function(err) {
                        var str = err.error.status + ' ' + err.error.statusText + ' - ' + err.error.responseText;
                        var popup = L.popup()
                            .setLatLng(center)
                            .setContent("Track could not be loaded:<br />" + str).openOn(this._map);
                    })
                    .addTo(map);
                this.ts_tracks[div_id] = runLayer;
            }
        },

        // Callback function to update the track.
        // Wrapper for 'draw_track' that gets its data from the liveupdate object.
        update_track: function (liveupdate) {

            var map       = liveupdate._map,
                track_url = liveupdate.options.track_url,
                div_id    = liveupdate.options.div_id;

            this.draw_track( map, track_url, div_id, true);
        },

        create_maps: function () {
            /*
                'div_id'       => $div_id,
                'track_url'    => $track_url,
                'default_lat'  => '51.44815',
                'default_lon'  => '5.47279',
                'default_zoom' => '12',
                'fullscreen'   => true,
            */

            var mapdata = this.mapdata;

            for (i = 0; i < mapdata.length; i++) {

                var div_id     = mapdata[i]['div_id'];
                var track_url  = mapdata[i]['track_url'];
                var lat        = parseFloat (mapdata[i]['default_lat']);
                var lon        = parseFloat (mapdata[i]['default_lon']);
                var zoom       = parseInt (mapdata[i]['default_zoom']);
                var fullscreen = mapdata[i]['fullscreen'];
                var center     = L.latLng(lat, lon);
                var is_live    = mapdata[i]['is_live'];

                /*
                 * The map div in the admin screen is re-used when viewing multiple maps.
                 * When closing the thickbox, the map object is normally removed and the
                 * div freed of Leaflet bindings, but just in case something goes wrong
                 * there, we have a fallback here, that empties the div and sets ._leaflet
                 * to false, making re-initialization possible.
                 */
                var container = L.DomUtil.get(div_id);
                if (container._leaflet) {
                    jQuery(container).empty();
                    container._leaflet = false;
                }

                var map_layer0 = L.tileLayer(
                    'https://{s}.tiles.mapbox.com/v4/dennisl.4e2aab76/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoidGludXp6IiwiYSI6IlVXYUYwcG8ifQ.pe5iF9bAH3zx3ztc6PzHFA',
                    { maxZoom: 18 });

                var map_layer1 = L.tileLayer(
                    'https://www.grendelman.net/map/tms_r.ashx?x={x}&y={y}&z={z}',
                    { maxZoom: 18 });

                var map_layer2 = L.tileLayer(
                    'https://{s}.tiles.mapbox.com/v4/aj.um7z9lus/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoidGludXp6IiwiYSI6IlVXYUYwcG8ifQ.pe5iF9bAH3zx3ztc6PzHFA',
                    { maxZoom: 18 });

                var options = {center : center, zoom : zoom, layers: [map_layer0], messagebox: true };
                var map = L.map(div_id, options);

                // An ugly shortcut to able to destroy the map in WP admin
                if (div_id == 'tsadminmap') {
                    this.adminmap = map;
                }

                if (fullscreen) {
                    L.control.fullscreen().addTo(map);
                }

                // Load and display the track. Use the liveupdate control to do it when appropriate.
                if (is_live) {
                    L.control.liveupdate ({
                        track_url: track_url,
                        div_id: div_id,
                        update_map: L.bind(this.update_track, this)
                    })
                    .addTo( map )
                    .startUpdating();
                }
                else {
                    this.draw_track (map, track_url, div_id, is_live);
                }
            }
        }
    };

})();


// Requires global variable 'trackserver_mapdata' to be set
if (typeof trackserver_mapdata != 'undefined')
{
    Trackserver.init( trackserver_mapdata );
}
