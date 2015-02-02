var Trackserver = (function () {

    return {

        mapdata: {},
        mydata: {},
        timer: false,
        adminmap: false,

        Mapicon: L.Icon.extend({
            iconSize:     [15, 15],
            iconAnchor:   [8, 8],
            popupAnchor:  [0, 8]
        }),

        init: function (mapdata) {
            this.mapdata = mapdata;
            this.create_maps();
        },

        get_mydata: function(div_id, prop) {
            if (this.mydata.hasOwnProperty(div_id)) {
                if (this.mydata[div_id].hasOwnProperty(prop)) {
                    return this.mydata[div_id][prop];
                }
            }
            return false;
        },

        set_mydata: function (div_id, prop, value) {
            if (!this.mydata.hasOwnProperty(div_id)) {
                this.mydata[div_id] = {};
            }
            this.mydata[div_id][prop] = value;
        },

        process_data: function (data, options) {
            var o = typeof data === 'string' ?  JSON.parse(data) : data;
            var start_latlng = new L.latLng(o.metadata.first_trkpt);
            var end_latlng = new L.latLng(o.metadata.last_trkpt);
            var title = o.metadata.last_trkpt_time;

            this.set_mydata(options.div_id, 'start', start_latlng);
            this.set_mydata(options.div_id, 'end', end_latlng);
            this.set_mydata(options.div_id, 'title', title);
            return o.track;
        },

        draw_track: function (map, track_url, div_id, is_live) {

            if (track_url) {
                var start_icon = new this.Mapicon ({iconUrl: trackserver_iconpath + 'greendot_15.png'});
                var end_icon = new this.Mapicon ({iconUrl: trackserver_iconpath + 'reddot_15.png'});

                // Identify any existing track layer and marker
                var old_track = this.get_mydata(div_id, 'track');
                var old_start_marker = this.get_mydata(div_id, 'start_marker');
                var old_end_marker = this.get_mydata(div_id, 'end_marker');

                var _this = this;

                // First draw the new track...
                var runLayer = omnivore.polyline(track_url, {'ondata': L.bind(this.process_data, this), 'div_id': div_id} )
                    .on ('ready', function () {
                        // ...and then delete the old one, to prevent flickering
                        if (old_track) map.removeLayer (old_track);

                        if (is_live) {
                            if (old_start_marker) map.removeLayer (old_start_marker);
                            if (old_end_marker) map.removeLayer (old_end_marker);

                            start_latlng = _this.get_mydata(div_id, 'start');
                            start_marker = new L.marker(start_latlng, {icon: start_icon}).addTo(map);
                            _this.set_mydata(div_id, 'start_marker', start_marker);

                            end_latlng = _this.get_mydata(div_id, 'end');
                            end_title = _this.get_mydata(div_id, 'title');
                            end_marker = new L.marker(end_latlng, {icon: end_icon, title: end_title }).addTo(map);
                            _this.set_mydata(div_id, 'end_marker', end_marker);

                            // Then, center the map on the last point / current position
                            this._map.setView(end_latlng, map.getZoom());
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
                this.set_mydata(div_id, 'track', runLayer);
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
