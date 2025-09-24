/* global L, trackserver_mapdata, trackserver_i18n, omnivore, jQuery */

const Trackserver = (() => {
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

        init(mapdata) {
            this.mapdata = mapdata;
            this.create_maps();
        },

        get_mydata(div_id, track_id, prop) {
            if (
                Object.hasOwn(this.mydata, div_id) &&
                Object.hasOwn(this.mydata[div_id], track_id) &&
                Object.hasOwn(this.mydata[div_id][track_id], prop)
            ) {
                return this.mydata[div_id][track_id][prop];
            }
            return false;
        },

        set_mydata(div_id, track_id, prop, value) {
            if (!Object.hasOwn(this.mydata, div_id)) {
                this.mydata[div_id] = {};
            }
            if (!Object.hasOwn(this.mydata[div_id], track_id)) {
                this.mydata[div_id][track_id] = {};
            }
            this.mydata[div_id][track_id][prop] = value;
        },

        process_data(data, options) {
            const o = typeof data === 'string' ? JSON.parse(data) : data;
            this.set_mydata(options.div_id, options.track_id, 'metadata', o.metadata);
            return o.track;
        },

        get_sorted_keys(obj) {
            const keys = [];
            for (const key in obj) {
                if (Object.hasOwn(obj, key)) {
                    keys.push(key);
                }
            }
            keys.sort();
            return keys;
        },

        toggle_edit(div_id) {
            const editables = this.get_mydata(div_id, 'all', 'editables');
            for (let i = 0; i < editables.length; ++i) {
                editables[i].toggleEdit();
            }
        },

        edit_enabled(div_id) {
            const editables = this.get_mydata(div_id, 'all', 'editables');
            if (editables.length > 0) {
                return editables[0].editEnabled();
            }
            return false;
        },

        do_draw(i, mymapdata) {
            const map = mymapdata.map;
            const featuregroup = mymapdata.featuregroup;
            const div_id = mymapdata.div_id;
            const alltracks = this.get_mydata(div_id, 'all', 'alltracksdata');
            const track_id = mymapdata.tracks[i].track_id;
            const _this = this;

            // Values needed for drawing the track can be passed via layer_options.
            // Remember that 'mymapdata' is also available within the layer's on(ready) handler.
            const layer_options = {
                track_id,
                track_index: i,
                old_track: this.get_mydata(div_id, track_id, 'track'),
                old_markers: this.get_mydata(div_id, track_id, 'markers'),
            };

            if (mymapdata.tracks[i].style && !mymapdata.tracks[i].points) {
                layer_options.style = mymapdata.tracks[i].style;
            }

            // Values needed in the process_data method can be passed via track_options
            let track_options = {
                ondata: L.bind(this.process_data, this),
                div_id,
                track_id,
            };

            if (mymapdata.tracks[i].points) {
                let pointColor;
                if (mymapdata.tracks[i].style && mymapdata.tracks[i].style.color) {
                    pointColor = mymapdata.tracks[i].style.color;
                }
                else {
                    pointColor = '#03f';
                }

                track_options.geometry = 'points';
                layer_options.pointToLayer = function (feature, latlng) {
                    const pointOptions = { fillColor: pointColor };
                    if (mymapdata.tracks[i].style && mymapdata.tracks[i].style.weight) {
                        pointOptions.radius = mymapdata.tracks[i].style.weight;
                    }
                    return new _this.Trackpoint(latlng, pointOptions);
                };
            }

            const customLayer = L.geoJson(null, layer_options);
            let track_function, track_ref;
            const track_type = mymapdata.tracks[i].track_type;
            let follow_id = 0;

            if (track_type === 'polyline') {
                track_function = omnivore.polyline.parse;
                track_ref = alltracks[track_id].track;
            } else if (track_type === 'polylinexhr') {
                track_function = omnivore.polyline;
                track_ref = mymapdata.tracks[i].track_url;
            } else if (track_type === 'geojson') {
                track_function = omnivore.geojson;
                track_ref = mymapdata.tracks[i].track_url;
            } else if (track_type === 'gpx') {
                track_function = omnivore.gpx;
                track_ref = mymapdata.tracks[i].track_url;
                track_options = { div_id };
                follow_id = 'gpx0';
            } else if (track_type === 'kml') {
                track_function = omnivore.kml;
                track_ref = mymapdata.tracks[i].track_url;
                track_options = { div_id };
                follow_id = 'kml0';
            } else if (track_type === 'json') {
                track_function = omnivore.geojson;
                track_ref = mymapdata.tracks[i].track_url;
                track_options = { div_id };
                follow_id = 'json0';
            }

            const runLayer = track_function(track_ref, track_options, customLayer)
                .on('ready', function () {
                    const track_id     = this.options.track_id;
                    const track_index  = this.options.track_index;
                    const old_track    = this.options.old_track;
                    const old_markers  = this.options.old_markers;
                    const do_markers   = mymapdata.tracks[track_index].markers;
                    const do_arrows    = mymapdata.tracks[track_index].arrows;
                    const is_live      = mymapdata.tracks[track_index].is_live;
                    const do_follow    = mymapdata.tracks[track_index].follow;
                    const markersize   = mymapdata.tracks[track_index].markersize || 5;

                    let metadata;
                    if (alltracks && alltracks[track_id]) {
                        metadata = alltracks[track_id].metadata;
                    } else {
                        metadata = _this.get_mydata(div_id, track_id, 'metadata');
                    }

                    // Delete old track after drawing new one, to prevent flickering
                    if (old_track) {
                        featuregroup.removeLayer(old_track);
                    }

                    const popup = _this.get_mydata(div_id, 'all', 'errorpopup');
                    if (popup) popup.remove();

                    const layer_ids = _this.get_sorted_keys(this._layers);
                    const stored_follow_id = _this.get_mydata(div_id, 'all', 'follow_id');

                    if (do_follow) {
                        follow_id = track_id;
                    }
                    if (stored_follow_id) {
                        follow_id = stored_follow_id;
                    }

                    let id, layer, start_latlng, end_latlng, start_marker, end_marker, point_layer;
                    const markers = [];
                    const editables = [];
                    let layer_index = 0;

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
                    for (let j = 0; j < layer_ids.length; ++j) {
                        id = layer_ids[j];
                        layer = this._layers[id];

                        if ('_latlngs' in layer) {
                            start_latlng = layer._latlngs[0];
                            end_latlng = layer._latlngs[layer._latlngs.length - 1];
                            layer_index++;
                            editables.push(layer);
                        } else if ('_layers' in layer) {
                            // The _layers object may contain sublayers containing points with a single _latlng. It may
                            // also contain sublayers that are tracks, with a _latlngs property that is an array.  Iterate
                            // over the object, inspect the sublayers and get the coordinates. We need the first one and the
                            // last one.
                            let k = 0;
                            for (const layerid in layer._layers) {
                                if (Object.hasOwn(layer._layers, layerid)) {
                                    point_layer = layer._layers[layerid];
                                    if (k === 0) {
                                        if ('_latlng' in point_layer) {
                                            start_latlng = point_layer._latlng;
                                        } else if ('_latlngs' in point_layer) {
                                            start_latlng = point_layer._latlngs[0];
                                        }
                                        k++;
                                    }
                                }
                            }
                            if ('_latlng' in point_layer) {
                                end_latlng = point_layer._latlng;
                            } else if ('_latlngs' in point_layer) {
                                end_latlng = point_layer._latlngs[point_layer._latlngs.length - 1];
                            }
                            layer_index++;
                        } else {
                            //  No tracks, no points? No markers.
                            continue;
                        }

                        if (do_markers) {
                            let start_marker_color, end_marker_color;
                            if ((track_index === 0 && layer_index <= 1) || !mymapdata.continuous) {
                                start_marker_color = '#009c0c';   // green
                            } else {
                                start_marker_color = '#ffcf00';   // yellow
                            }
                            end_marker_color = '#c30002';         // red

                            if (do_markers === true || do_markers === 'start') {
                                start_marker = new _this.Mapicon(start_latlng, { fillColor: start_marker_color, radius: markersize }).addTo(featuregroup);
                                markers.push(start_marker);
                            }
                            if (do_markers === true || do_markers === 'end') {
                                end_marker = new _this.Mapicon(end_latlng, { fillColor: end_marker_color, radius: markersize, track_id }).addTo(featuregroup).bringToBack();
                                if (mymapdata.is_live) {
                                    end_marker.on('click', function () {
                                        _this.set_mydata(div_id, 'all', 'follow_id', this.options.track_id);
                                        map.liveUpdateControl.updateNow();
                                    });
                                    if (typeof metadata.displayname === 'string') {
                                        end_marker.bindTooltip(metadata.displayname);
                                    }
                                }
                                markers.push(end_marker);
                            }
                            _this.set_mydata(div_id, track_id, 'markers', markers);
                            if (track_index === 0 && layer_index < 2) {
                                _this.set_mydata(div_id, 'all', 'first_marker', start_marker);
                            }
                        }

                        if (do_arrows) {
                            // Draw textPath
                            let text = '➜';
                            //text = '➤';
                            layer.setText(text, {
                                repeat: 12,
                                offset: 0,
                                attributes: {
                                    'font-size': '14px',
                                    'fill': layer.options.color,
                                    'fill-opacity': layer.options.opacity,
                                    'opacity': layer.options.opacity,  // both text and shadow
                                    dy: '5px',
                                    style: 'text-shadow: 1px 1px 0 white, -1px 1px 0 white, 1px -1px 0 white, -1px -1px 0 white, 0px 1px 0 white, 0px -1px 0 white, -1px 0px 0 white, 1px 0px 0 white; -webkit-font-smoothing: antialiased; -webkit-touch-callout: none; -webkit-user-select: none; -khtml-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;',
                                }
                            });
                        }
                    }

                    // Render live tracks on top
                    try {
                        if (is_live) {
                            this.bringToFront();
                        } else {
                            this.bringToBack();
                        }
                    } catch (e) {
                        console.log(e);
                    }

                    // Remove any old markers
                    if (old_markers) {
                        for (let j = 0; j < old_markers.length; ++j) {
                            featuregroup.removeLayer(old_markers[j]);
                        }
                    }

                    // Increment the 'ready' counter
                    let num_ready = _this.get_mydata(div_id, 'all', 'num_ready');
                    num_ready++;
                    _this.set_mydata(div_id, 'all', 'num_ready', num_ready);

                    // Append to the 'editables' list
                    const oldeditables = _this.get_mydata(div_id, 'all', 'editables');
                    _this.set_mydata(div_id, 'all', 'editables', oldeditables.concat(editables));

                    if (do_markers && num_ready === mymapdata.tracks.length) {
                        const first_marker = _this.get_mydata(div_id, 'all', 'first_marker');
                        if (first_marker) first_marker.bringToFront();
                        if (end_marker) end_marker.bringToFront();
                    }

                    // For live tracks, set the center of the map to the last
                    // point of the track we are supposed to follow according
                    // to the 'follow' metadata parameter. For ordinary tracks,
                    // wait for all tracks to be drawn and then set the
                    // viewport of the map to contain all of them.
                    if (mymapdata.is_live) {
                        if (track_id == follow_id) {
                            // Center the map on the last point / current position
                            this._map.setView(end_latlng, this._map.getZoom());

                            if (mymapdata.infobar) {
                                let infobar_text = mymapdata.infobar_tpl;
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
                                infobar_text = infobar_text.replace(/\{distancemi\}/gi, (metadata.distance / 1609.344).toFixed(0));
                                infobar_text = infobar_text.replace(/\{distancemi1\}/gi, (metadata.distance / 1609.344).toFixed(1));
                                infobar_text = infobar_text.replace(/\{distancemi2\}/gi, (metadata.distance / 1609.344).toFixed(2));
                                infobar_text = infobar_text.replace(/\{distanceyd\}/gi, (metadata.distance * 1.0936133).toFixed(0));
                                infobar_text = infobar_text.replace(/\{userid\}/gi, metadata.userid);
                                infobar_text = infobar_text.replace(/\{userlogin\}/gi, metadata.userlogin);
                                infobar_text = infobar_text.replace(/\{displayname\}/gi, metadata.displayname);
                                infobar_text = infobar_text.replace(/\{trackname\}/gi, metadata.trackname);

                                // Escape HTML entities &, < and >
                                infobar_text = infobar_text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                                mymapdata.infobar_div.innerHTML = infobar_text;
                            }
                        }
                    } else {
                        if (num_ready === mymapdata.tracks.length) {
                            let fitOptions = {};
                            if (!mymapdata.fit) {
                                fitOptions = { maxZoom: mymapdata.default_zoom };
                            }
                            this._map.fitBounds(featuregroup.getBounds(), fitOptions);
                        }
                    }
                })
                .on('error', function (err) {
                    console.log(err);
                    let extra = '';
                    if (track_type !== 'polyline' && track_type !== 'polylinexhr') {
                        extra = track_ref;
                    }
                    const str = err.error.status + ' ' + err.error.statusText + ' - ' + extra;
                    _this.show_popup(mymapdata, "Track could not be loaded:<br />" + str);
                    this._map.setView(mymapdata.center, this._map.getZoom());
                })
                /*
                .on('click', function(e) {
                    console.log(e.target.options.track_id);
                })
                */
                .addTo(featuregroup);

            this.set_mydata(div_id, track_id, 'track', runLayer);

            // For a polyline track, the layer is created synchronously and
            // we have to fire the 'ready' event ourselves.
            if (mymapdata.tracks[i].track_type === 'polyline') {
                runLayer.fire('ready');
            }
        },

        show_popup(mymapdata, text) {
            let popup = this.get_mydata(mymapdata.div_id, 'all', 'errorpopup');
            if (popup === false) {
                popup = L.popup();
                this.set_mydata(mymapdata.div_id, 'all', 'errorpopup', popup);
            }
            popup.setLatLng(mymapdata.map.getCenter())
                .setContent(text)
                .openOn(mymapdata.map);
        },

        draw_tracks(mymapdata) {
            this.set_mydata(mymapdata.div_id, 'all', 'num_ready', 0);
            this.set_mydata(mymapdata.div_id, 'all', 'editables', []);
            this.set_mydata(mymapdata.div_id, 'all', 'map', mymapdata.map);
            const drawcounter = this.get_mydata(mymapdata.div_id, 'all', 'drawcounter');
            this.set_mydata(mymapdata.div_id, 'all', 'drawcounter', drawcounter + 1);
            const _this = this;

            if (mymapdata.alltracks) {
                const alltracksPromise = new Promise((resolve, reject) => {
                    omnivore.xhr(mymapdata.alltracks, (err, response) => {
                        if (err) {
                            reject(err);
                        } else {
                            const o = typeof response.responseText === 'string' ? JSON.parse(response.responseText) : response.responseText;
                            resolve(o);
                        }
                    });
                });

                alltracksPromise.then(function (alltracks) {
                    _this.set_mydata(mymapdata.div_id, 'all', 'alltracksdata', alltracks);
                    if (mymapdata.tracks && mymapdata.tracks.length > 0) {
                        for (let i = 0; i < mymapdata.tracks.length; i++) {
                            if (mymapdata.tracks[i].track_type === 'polyline') {
                                if (mymapdata.tracks[i].track_id in alltracks) {
                                    if (alltracks[mymapdata.tracks[i].track_id].track.length > 0) {
                                        _this.do_draw(i, mymapdata);
                                    } else {
                                        _this.show_popup(mymapdata, "Track has no locations. Please try again later.");
                                    }
                                } else {
                                    _this.show_popup(mymapdata, "Track missing from server response.<br />Please reload the page.");
                                }
                            }
                        }
                    }
                }, function (err) {
                    const str = err.status + ' ' + err.statusText + ' - ' + err.responseText;
                    _this.show_popup(mymapdata, "Tracks could not be loaded:<br />" + str);
                });
            }

            if (mymapdata.tracks && mymapdata.tracks.length > 0) {
                for (let i = 0; i < mymapdata.tracks.length; i++) {
                    // 'polyline' tracks are triggered by the alltracksPromise
                    if (mymapdata.tracks[i].track_type === 'polyline') continue;
                    // draw the rest the old fashioned way
                    this.do_draw(i, mymapdata);
                }
            }
        },

        // Callback function to update the track.
        // Wrapper for 'draw_tracks' that gets its data from the liveupdate object.
        update_tracks(liveupdate) {
            this.draw_tracks(liveupdate.options.mymapdata);
        },

        create_maps() {
            for (let i = 0; i < this.mapdata.length; i++) {
                const mymapdata = this.mapdata[i];
                const lat       = parseFloat(mymapdata['profile']['default_lat']);
                const lon       = parseFloat(mymapdata['profile']['default_lon']);
                const zoom      = parseInt(mymapdata['default_zoom']);
                const center    = L.latLng(lat, lon);

                /*
                 * The map div in the admin screen is re-used when viewing multiple maps.
                 * When closing the thickbox, the map object is normally removed and the
                 * div freed of Leaflet bindings, but just in case something goes wrong
                 * there, we have a fallback here, that empties the div and sets ._leaflet
                 * to false, making re-initialization possible.
                 */
                const container = L.DomUtil.get(mymapdata.div_id);
                if (container._leaflet) {
                    jQuery(container).empty();
                    container._leaflet = false;
                }

                // Add a basemap raster or vector tile layer.
                let map_layer0;
                if (mymapdata['profile']['vector']) {
                    map_layer0 = L.maplibreGL({
                        style: mymapdata['profile']['tile_url'],
                        attribution: mymapdata['profile']['attribution'],
                        attributionControl: { customAttribution: mymapdata['profile']['attribution'] },
                        minZoom: mymapdata['profile']['min_zoom'],
                        maxZoom: mymapdata['profile']['max_zoom']
                    });
                } else {
                    map_layer0 = L.tileLayer(
                        mymapdata['profile']['tile_url'],
                        {
                            attribution: mymapdata['profile']['attribution'],
                            minZoom: mymapdata['profile']['min_zoom'],
                            maxZoom: mymapdata['profile']['max_zoom']
                        }
                    );
                }

                const options = { center: center, zoom: zoom, layers: [map_layer0], messagebox: true };

                if (mymapdata.div_id === 'tsadminmap') {
                    options['editOptions'] = { 'skipMiddleMarkers': true };
                    options['editable'] = true;
                }

                const map = L.map(mymapdata.div_id, options);
                mymapdata.map = map;
                mymapdata.center = center;
                this.set_mydata(mymapdata.div_id, 'all', 'drawcounter', 0);

                // An ugly shortcut to be able to destroy the map in WP admin
                if (mymapdata.div_id === 'tsadminmap') {
                    this.adminmap = map;
                }

                if (mymapdata.fullscreen) {
                    L.control.fullscreen().addTo(map);
                }

                if (mymapdata.locate) {
                    const locateControl = L.control.locate({
                        locateOptions: { 'enableHighAccuracy': true },
                        setView: 'always',
                        keepCurrentZoomLevel: true,
                        initialZoomLevel: false,
                        drawCircle: true,
                        showPopup: false
                    })
                        .addTo(map);

                    map.on('locateactivate', function () {
                        if ('liveUpdateControl' in this) {
                            this.liveUpdateControl.stopUpdating();
                        }
                    });

                    map.on('liveupdatestart', function () {
                        locateControl.stop();
                    });
                }

                if (mymapdata.tracks && mymapdata.tracks.length > 0) {

                    // Add a featuregroup to hold the track layers
                    mymapdata.featuregroup = L.featureGroup().addTo(map);

                    // Load and display the tracks. Use the liveupdate control to do it when appropriate.
                    if (mymapdata.is_live) {
                        const mapdivelement = L.DomUtil.get(mymapdata.div_id);
                        const infobar_container = L.DomUtil.create('div', 'trackserver-infobar-container', mapdivelement);
                        mymapdata.infobar_div = L.DomUtil.create('div', 'trackserver-infobar', infobar_container);
                        L.control.liveupdate({
                            mymapdata: mymapdata,
                            update_map: L.bind(this.update_tracks, this)
                        })
                            .addTo(map)
                            .startUpdating();
                    } else {
                        this.draw_tracks(mymapdata);
                    }
                } else if (!this.adminmap && !mymapdata.quiet) {
                    L.popup()
                        .setLatLng(mymapdata.center)
                        .setContent(trackserver_i18n['no_tracks_to_display'])
                        .openOn(map);
                }
            }
        }
    };
})();

if (typeof trackserver_mapdata !== 'undefined') {
    Trackserver.init(trackserver_mapdata);
}
