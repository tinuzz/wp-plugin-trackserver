var Trackserver = (function () {

    return {

        mapdata: {},
        mydata: {},
        timer: false,
        adminmap: false,

        Mapicon: L.CircleMarker.extend({
            options: {
                radius: 5,
                color: "#ffffff",
                weight: 2,
                opacity: 1,
                fillOpacity: 1
            }
        }),

        Trackpoint: L.CircleMarker.extend({
            options: {
                radius: 5,
                color: "#ffffff",
                fillColor: "#03f",
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }
        }),

        init: function (mapdata) {
            this.mapdata = mapdata;
            this.create_maps();
        },

        get_mydata: function(div_id, track_id, prop) {
            if (this.mydata.hasOwnProperty(div_id)) {
                if (this.mydata[div_id].hasOwnProperty(track_id)) {
                    if (this.mydata[div_id][track_id].hasOwnProperty(prop)) {
                        return this.mydata[div_id][track_id][prop];
                    }
                }
            }
            return false;
        },

        set_mydata: function (div_id, track_id, prop, value) {
            if (!this.mydata.hasOwnProperty(div_id)) {
                this.mydata[div_id] = {};
            }
            if (!this.mydata[div_id].hasOwnProperty(track_id)) {
                this.mydata[div_id][track_id] = {};
            }
            this.mydata[div_id][track_id][prop] = value;
        },

        process_data: function (data, options) {
            var o = typeof data === 'string' ?  JSON.parse(data) : data;
            this.set_mydata(options.div_id, options.track_id, 'metadata', o.metadata);
            return o.track;
        },

        get_sorted_keys: function( obj ) {
            var keys = [];
            for ( var key in obj ) {
                if ( obj.hasOwnProperty( key ) ) {
                    keys.push( key );
                }
            }
            keys.sort();
            return keys;
        },

        toggle_edit: function (div_id) {
            editables = this.get_mydata ( div_id, 'all', 'editables' );
            for ( var i = 0; i < editables.length; ++i ) {
                editables[i].toggleEdit();
            }
        },

        edit_enabled: function(div_id) {
            editables = this.get_mydata ( div_id, 'all', 'editables' );
            if (editables.length > 0) {
                return editables[0].editEnabled();
            }
            return false;
        },

        do_draw: function(i, mymapdata) {

            var map = mymapdata.map;
            var featuregroup = mymapdata.featuregroup;
            var div_id = mymapdata.div_id;
            var alltracks = this.get_mydata(div_id, 'all', 'alltracksdata');
            var track_id;
            var _this = this;

            track_id = mymapdata.tracks[i].track_id;

            // Values that are needed for drawing the track can be passed via layer_options.
            // Remember that 'mymapdata' is also available within the layer's on(ready) handler.
            var layer_options = {
                track_id: track_id,
                track_index: i,
                old_track: this.get_mydata(div_id, track_id, 'track'),
                old_markers: this.get_mydata(div_id, track_id, 'markers'),
            }

            if (mymapdata.tracks[i].style && !mymapdata.tracks[i].points) {
                layer_options.style = mymapdata.tracks[i].style;
            }

            // Values that are needed in the process_data method can be passed via track_options
            var track_options = {
                ondata: L.bind( this.process_data, this ),
                div_id: div_id,
                track_id: track_id,
            };

            if (mymapdata.tracks[i].points) {

                if (mymapdata.tracks[i].style && mymapdata.tracks[i].style.color) {
                    var pointColor = mymapdata.tracks[i].style.color;
                }
                else {
                    pointColor = '#03f';
                }

                track_options.geometry = 'points';
                layer_options.pointToLayer = function(feature, latlng) {
                    var pointOptions = { fillColor: pointColor };
                    if ( mymapdata.tracks[i].style && mymapdata.tracks[i].style.weight ) {
                        pointOptions.radius = mymapdata.tracks[i].style.weight;
                    }
                    return new _this.Trackpoint(latlng, pointOptions);
                }
            }

            var customLayer = L.geoJson(null, layer_options);
            var track_function, track_ref;
            var track_type = mymapdata.tracks[i].track_type;

            if ( track_type == 'polyline' ) {
                track_function = omnivore.polyline.parse;
                track_ref = alltracks[track_id].track;
            }

            if ( track_type == 'polylinexhr' ) {
                track_function = omnivore.polyline;
                track_ref = mymapdata.tracks[i].track_url;
            }

            if ( track_type == 'geojson' ) {
                track_function = omnivore.geojson;
                track_ref = mymapdata.tracks[i].track_url;
            }

            if ( track_type == 'gpx' ) {
                track_function = omnivore.gpx;
                track_ref = mymapdata.tracks[i].track_url;
                track_options = { 'div_id': div_id };
            }

            if ( track_type == 'kml' ) {
                track_function = omnivore.kml;
                track_ref = mymapdata.tracks[i].track_url;
                track_options = { 'div_id': div_id };
            }

            // First draw the new track...
            var runLayer = track_function(track_ref, track_options, customLayer )
                .on ('ready', function (e) {

                    var track_id    = this.options.track_id;
                    var track_index = this.options.track_index;
                    var old_track   = this.options.old_track;
                    var old_markers = this.options.old_markers;
                    var do_markers  = mymapdata.tracks[track_index].markers;
                    var do_points   = mymapdata.tracks[track_index].points;
                    var do_follow   = mymapdata.tracks[track_index].follow;

                    // ...and then delete the old one, to prevent flickering
                    if (old_track) {
                        featuregroup.removeLayer (old_track);
                    }

                    var popup = _this.get_mydata(div_id, 'all', 'errorpopup');
                    if (popup) {
                      popup.remove();
                    }

                    var layer_ids = _this.get_sorted_keys( this._layers );

                    var follow_id = 0;
                    var stored_follow_id = _this.get_mydata(div_id, 'all', 'follow_id');

                    if (do_follow) {
                        follow_id = track_id;
                    }
                    if (stored_follow_id ) {
                        follow_id = stored_follow_id;
                    }

                    var id, layer, start_latlng, end_latlng, start_marker, end_marker, point_layer;
                    var markers = [];
                    var editables = [];
                    var layer_index = 0;

                    // Get 'start_latlng' and 'end_latlng' for the current track(s). We need
                    // 'end_latlng' even if we don't draw any markers. The current layer may
                    // have multiple sub-layers that all represent tracks, for example when
                    // a GPX with multiple tracks is loaded. We may need to draw markers for
                    // all of them.
                    //
                    // When there are multiple sub-layers, and 'continuous' is true, we want
                    // markers for layers beyond the first one to be yellow instead of green,
                    // so we keep an index of layers that represent tracks and only use
                    // green when this index is 0 (should not happen) or 1.

                    for ( var i = 0; i < layer_ids.length; ++i ) {

                        id = layer_ids[i];
                        layer = this._layers[id];
                        if ('_latlngs' in layer) {
                            start_latlng = layer._latlngs[0];
                            end_latlng   = layer._latlngs[ layer._latlngs.length - 1 ];
                            layer_index++;
                            editables.push(layer);
                        }
                        else if (do_points && '_layers' in layer) {
                            // Iterate over the _layers object, in which every layer, each containing a
                            // point, has a numeric key. We need the first one and the last one.
                            var j=0;
                            for (var layerid in layer._layers) {
                                if (layer._layers.hasOwnProperty(layerid)) {
                                    point_layer = layer._layers[layerid];
                                    if (j == 0) {
                                        start_latlng = point_layer._latlng;
                                        j++;
                                    }
                                }
                            }
                            end_latlng = point_layer._latlng;
                            //editables.push(point_layer);
                            layer_index++;
                        }
                        else {
                            //  No tracks, no points? No markers.
                            continue;
                        }

                        if (do_markers) {
                            if ((track_index == 0 && layer_index <= 1) || !mymapdata.continuous) {
                                start_marker_color = '#009c0c';   // green
                            } else {
                                start_marker_color = '#ffcf00';   // yellow
                            }
                            end_marker_color = '#c30002';         // red

                            if (do_markers === true || do_markers == 'start') {
                                start_marker = new _this.Mapicon(start_latlng, { fillColor: start_marker_color }).addTo(featuregroup);
                                markers.push(start_marker);
                            }
                            if (do_markers === true || do_markers == 'end') {
                                end_marker = new _this.Mapicon(end_latlng, { fillColor: end_marker_color, track_id: track_id }).addTo(featuregroup).bringToBack();
                                if (mymapdata.is_live) {
                                    end_marker.on('click', function(e) {
                                        _this.set_mydata(div_id, 'all', 'follow_id', this.options.track_id);
                                        map.liveUpdateControl.updateNow();
                                    });
                                    if (typeof displayname == 'string') {
                                        var tooltip = end_marker.bindTooltip(displayname);
                                    }
                                }
                                markers.push(end_marker);
                            }
                            _this.set_mydata(div_id, track_id, 'markers', markers);
                            if (track_index == 0 && layer_index < 2) {
                                _this.set_mydata(div_id, 'all', 'first_marker', start_marker);
                            }
                        }
                    }
                    try {
                        this.bringToBack();
                    }
                    catch(e) {
                        console.log(e);
                    }

                    // Remove any old markers
                    if (old_markers) {
                        for ( var i = 0; i < old_markers.length; ++i ) {
                            featuregroup.removeLayer( old_markers[i] );
                        }
                    }

                    // Increment the 'ready' counter
                    var num_ready = _this.get_mydata(div_id, 'all', 'num_ready');
                    num_ready++;
                    _this.set_mydata(div_id, 'all', 'num_ready', num_ready);

                    // Append to the 'editables' list
                    var oldeditables = _this.get_mydata(div_id, 'all', 'editables');
                    _this.set_mydata(div_id, 'all', 'editables', oldeditables.concat(editables));

                    if (do_markers && num_ready == mymapdata.tracks.length) {
                        var first_marker = _this.get_mydata(div_id, 'all', 'first_marker');
                        if (first_marker) {
                            first_marker.bringToFront();
                        }
                        if (end_marker) {
                            end_marker.bringToFront();
                        }
                    }

                    // For live tracks, set the center of the map to the last
                    // point of the track we are supposed to follow according
                    // to the 'follow' metadata paramter. For ordinary tracks,
                    // wait for all tracks to be drawn and then set the
                    // viewport of the map to contain all of them.

                    if (mymapdata.is_live) {
                        if (track_id == follow_id) {

                            // Center the map on the last point / current position
                            this._map.setView(end_latlng, this._map.getZoom());

                            if (mymapdata.infobar) {

                                if (alltracks && alltracks[track_id]) {
                                    metadata = alltracks[track_id].metadata;
                                }
                                else {
                                    metadata = _this.get_mydata(div_id, track_id, 'metadata');
                                }

                                infobar_text = mymapdata.infobar_tpl;
                                infobar_text = infobar_text.replace(/\{lat\}/gi, end_latlng.lat);
                                infobar_text = infobar_text.replace(/\{lon\}/gi, end_latlng.lng);
                                infobar_text = infobar_text.replace(/\{timestamp\}/gi, metadata.last_trkpt_time);
                                infobar_text = infobar_text.replace(/\{altitude\}/gi, parseFloat(metadata.last_trkpt_altitude).toFixed(0));
                                infobar_text = infobar_text.replace(/\{altitudem\}/gi, parseFloat(metadata.last_trkpt_altitude).toFixed(0));
                                infobar_text = infobar_text.replace(/\{altitudeft\}/gi, (metadata.last_trkpt_altitude * 3.2808399).toFixed(0));
                                infobar_text = infobar_text.replace(/\{speedms\}/gi, parseFloat(metadata.last_trkpt_speed_ms).toFixed(0));
                                infobar_text = infobar_text.replace(/\{speedms1}/gi, parseFloat(metadata.last_trkpt_speed_ms).toFixed(1));
                                infobar_text = infobar_text.replace(/\{speedms2}/gi, parseFloat(metadata.last_trkpt_speed_ms).toFixed(2));
                                infobar_text = infobar_text.replace(/\{speedkmh\}/gi, (metadata.last_trkpt_speed_ms * 3.6).toFixed(0));
                                infobar_text = infobar_text.replace(/\{speedkmh1\}/gi, (metadata.last_trkpt_speed_ms * 3.6).toFixed(1));
                                infobar_text = infobar_text.replace(/\{speedkmh2\}/gi, (metadata.last_trkpt_speed_ms * 3.6).toFixed(2));
                                infobar_text = infobar_text.replace(/\{speedmph\}/gi, (metadata.last_trkpt_speed_ms * 2.23693629).toFixed(0));
                                infobar_text = infobar_text.replace(/\{speedmph1\}/gi, (metadata.last_trkpt_speed_ms * 2.23693629).toFixed(1));
                                infobar_text = infobar_text.replace(/\{speedmph2\}/gi, (metadata.last_trkpt_speed_ms * 2.23693629).toFixed(2));
                                infobar_text = infobar_text.replace(/\{distance\}/gi, parseFloat(metadata.distance).toFixed(0));
                                infobar_text = infobar_text.replace(/\{distancem\}/gi, parseFloat(metadata.distance).toFixed(0));
                                infobar_text = infobar_text.replace(/\{distancekm\}/gi, (metadata.distance / 1000).toFixed(0));
                                infobar_text = infobar_text.replace(/\{distancekm1\}/gi, (metadata.distance / 1000).toFixed(1));
                                infobar_text = infobar_text.replace(/\{distancekm2\}/gi, (metadata.distance / 1000).toFixed(2));
                                infobar_text = infobar_text.replace(/\{distancemi\}/gi, (metadata.distance / 1609.344).toFixed(0) );
                                infobar_text = infobar_text.replace(/\{distancemi1\}/gi, (metadata.distance / 1609.344).toFixed(1) );
                                infobar_text = infobar_text.replace(/\{distancemi2\}/gi, (metadata.distance / 1609.344).toFixed(2) );
                                infobar_text = infobar_text.replace(/\{distanceyd\}/gi, (metadata.distance * 1.0936133).toFixed(0) );
                                infobar_text = infobar_text.replace(/\{userid\}/gi, metadata.userid);
                                infobar_text = infobar_text.replace(/\{userlogin\}/gi, metadata.userlogin);
                                infobar_text = infobar_text.replace(/\{displayname\}/gi, metadata.displayname);
                                mymapdata.infobar_div.innerHTML = infobar_text;
                            }
                        }
                    } else {
                        if (num_ready == mymapdata.tracks.length) {
                            var fitOptions = {};
                            if (!mymapdata.fit) {
                                fitOptions = { maxZoom: mymapdata.default_zoom }
                            }
                            // or fit the entire collection of tracks on the map
                            this._map.fitBounds(featuregroup.getBounds(), fitOptions);
                        }
                    }
                })
                .on('error', function(err) {
                    console.log(err);
                    var extra = '';
                    if ( track_type !== 'polyline' && track_type != 'polylinexhr' ) {
                      extra = track_ref;
                    }
                    var str = err.error.status + ' ' + err.error.statusText + ' - ' + extra;
                    var popup = L.popup()
                        .setLatLng(mymapdata.center)
                        .setContent("Track could not be loaded:<br />" + str).openOn(this._map);
                    _this.set_mydata(div_id, 'all', 'errorpopup', popup);
                    //this._map.fitBounds(featuregroup.getBounds());
                    this._map.setView(mymapdata.center, this._map.getZoom());
                })
                /*
                .on('click', function(e) {
                    console.log(e.target.options.track_id);
                })
                */
                .addTo(featuregroup);

            this.set_mydata(div_id, track_id, 'track', runLayer);

            // In case of a polyline track, the layer is created synchronously and
            // we have to fire the 'ready' event ourselves.
            if ( mymapdata.tracks[i].track_type == 'polyline' ) {
                runLayer.fire('ready');
            }

        },

        draw_tracks: function (mymapdata) {

            this.set_mydata(mymapdata.div_id, 'all', 'num_ready', 0);
            this.set_mydata(mymapdata.div_id, 'all', 'editables', []);
            this.set_mydata(mymapdata.div_id, 'all', 'map', mymapdata.map);
            var drawcounter = this.get_mydata(mymapdata.div_id, 'all', 'drawcounter');
            this.set_mydata(mymapdata.div_id, 'all', 'drawcounter', drawcounter + 1);
            var _this = this;

            if ( mymapdata.alltracks ) {

                alltracksPromise = new Promise( function(resolve, reject) {
                    omnivore.xhr(mymapdata.alltracks, function(err,response) {
                        if (err) {
                            reject(err);
                        }
                        else {
                            var o = typeof response.responseText === 'string' ?  JSON.parse(response.responseText) : response.responseText;
                            resolve(o);
                        }
                    });
                });

                alltracksPromise.then(function(alltracks) {
                    _this.set_mydata(mymapdata.div_id, 'all', 'alltracksdata', alltracks);
                    if (mymapdata.tracks && mymapdata.tracks.length > 0) {
                        for (var i = 0; i < mymapdata.tracks.length; i++) {
                            if ( mymapdata.tracks[i].track_type == 'polyline' ) {

                                // Workaround for https://github.com/tinuzz/wp-plugin-trackserver/issues/7
                                if ( mymapdata.tracks[i].track_id in alltracks ) {
                                    _this.do_draw(i, mymapdata);
                                }
                                else {
                                    // Draw an error-popup once and store it for later checking
                                    if ( _this.get_mydata(mymapdata.div_id, 'all', 'errorpopup') === false ) {
                                        var popup = L.popup()
                                            .setLatLng(mymapdata.map.getCenter())
                                            .setContent("Track missing from server response.<br />Please reload the page.")
                                            .openOn(mymapdata.map);
                                        _this.set_mydata(mymapdata.div_id, 'all', 'errorpopup', popup);
                                    }
                                }
                            }
                        }
                    }
                    return alltracks;
                }, function(err) {
                    var str = err.status + ' ' + err.statusText + ' - ' + err.responseText;
                    var popup = L.popup()
                        .setLatLng(mymapdata.map.getCenter())
                        .setContent("Tracks could not be loaded:<br />" + str)
                        .openOn(mymapdata.map);
                });
            }

            if (mymapdata.tracks && mymapdata.tracks.length > 0) {

                for (var i = 0; i < mymapdata.tracks.length; i++) {

                    // 'polyline' tracks are triggered by the alltracksPromise
                    if ( mymapdata.tracks[i].track_type == 'polyline' ) { continue; }

                    // draw the rest the old fashioned way
                    this.do_draw(i, mymapdata);

                }
            }
        },

        // Callback function to update the track.
        // Wrapper for 'draw_tracks' that gets its data from the liveupdate object.
        update_tracks: function (liveupdate) {
            this.draw_tracks(liveupdate.options.mymapdata);
        },

        create_maps: function () {

            var mapdata = this.mapdata;

            for (var i = 0; i < mapdata.length; i++) {

                var lat        = parseFloat (mapdata[i]['default_lat']);
                var lon        = parseFloat (mapdata[i]['default_lon']);
                var zoom       = parseInt (mapdata[i]['default_zoom']);
                var center     = L.latLng(lat, lon);

                var mymapdata  = mapdata[i];

                /*
                 * The map div in the admin screen is re-used when viewing multiple maps.
                 * When closing the thickbox, the map object is normally removed and the
                 * div freed of Leaflet bindings, but just in case something goes wrong
                 * there, we have a fallback here, that empties the div and sets ._leaflet
                 * to false, making re-initialization possible.
                 */
                var container = L.DomUtil.get( mymapdata.div_id );
                if (container._leaflet) {
                    jQuery(container).empty();
                    container._leaflet = false;
                }

                var map_layer0 = L.tileLayer(
                    trackserver_settings['tile_url'],
                    { attribution: trackserver_settings['attribution'], maxZoom: 18 });

                var options = {center : center, zoom : zoom, layers: [map_layer0], messagebox: true };

                // Make admin map editable
                if (mymapdata.div_id == 'tsadminmap') {
                    options['editOptions'] = { 'skipMiddleMarkers': true };
                    options['editable'] = true;
                }

                var map = L.map( mymapdata.div_id, options );
                mymapdata.map = map;
                mymapdata.center = center;
                this.set_mydata( mymapdata.div_id, 'all', 'drawcounter', 0);

                // An ugly shortcut to be able to destroy the map in WP admin
                if ( mymapdata.div_id == 'tsadminmap' ) {
                    this.adminmap = map;
                }

                if (mymapdata.fullscreen) {
                    L.control.fullscreen().addTo(map);
                }

                if (mymapdata.tracks && mymapdata.tracks.length > 0) {

                    // Add a featuregroup to hold the track layers
                    var featuregroup = L.featureGroup().addTo(map);
                    mymapdata.featuregroup = featuregroup;

                    // Load and display the tracks. Use the liveupdate control to do it when appropriate.
                    if (mymapdata.is_live) {
                        var mapdivelement = L.DomUtil.get(mymapdata.div_id);
                        var infobar_container = L.DomUtil.create('div', 'trackserver-infobar-container', mapdivelement);
                        mymapdata.infobar_div = L.DomUtil.create('div', 'trackserver-infobar', infobar_container);
                        L.control.liveupdate ({
                            mymapdata: mymapdata,
                            update_map: L.bind(this.update_tracks, this)
                        })
                        .addTo( map )
                        .startUpdating();
                    }
                    else {
                        this.draw_tracks(mymapdata);
                    }
                }
                else if (!this.adminmap) {
                    var popup = L.popup()
                        .setLatLng(mymapdata.center)
                        .setContent(trackserver_i18n['no_tracks_to_display']).openOn(map);
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
