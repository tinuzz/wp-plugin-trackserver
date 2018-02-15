/* Default dimensions, suitable for 'edit' */
var tb_window_width;
var tb_window_height;
var trackserver_mapdata;
var tracklib;

// Override tb_click()
var old_tb_click = window.tb_click;
var tb_click = function(e)
{
    var ts_action = jQuery(this).attr("data-action");
    var row = jQuery(this).closest("tr");
    var tds = row.find("th,td");

    trackserver_mapdata = false;

    if (ts_action == 'edit') {
        tb_window_width = 600;
        tb_window_height = 320;
    }
    if (ts_action == 'view' || ts_action == 'fences') {
        tb_window_width = 1024;
        tb_window_height = 768;
    }
    if (ts_action == 'howto') {
        tb_window_width = 850;
        tb_window_height = 560;
    }

    if (ts_action == 'view' || ts_action == 'edit') {

        // track_base_url should come from WP via wp_localize_script()
        var track_url = track_base_url + "admin=1";
        var nonce = false;

        // Loop over the table columns and set up the 'trackserver-edit-track' form with the data
        jQuery.each(tds, function() {

            // Extract the column name from the assigned CSS class
            col_arr = /(column-)?([^-\s]+)(-column)?/.exec(this.className);
            col = col_arr[2];

            switch (col) {
                case 'check':
                    track_id = jQuery(this).find('input').val();
                    track_url += "&id=" + track_id;
                    jQuery('#track_id').val(track_id);
                    break;
                case 'name':
                    jQuery('#input-track-name').val(jQuery(this).text());
                    break;
                case 'source':
                    jQuery('#input-track-source').val(jQuery(this).text());
                    break;
                case 'comment':
                    jQuery('#input-track-comment').val(jQuery(this).text());
                    break;
                case 'nonce':
                    nonce = jQuery(this).text();
                    track_url += "&_wpnonce="+nonce;
                    jQuery('#_wpnonce').val(nonce);
                    break;
            }
        });
        trackserver_mapdata = [{"div_id":"tsadminmap","tracks":[{"track_id":track_id,"track_url":track_url,"track_type":"polylinexhr","markers":true,"nonce":nonce}],"default_lat":"51.44815","default_lon":"5.47279","default_zoom":"12","fullscreen":true,"is_live":false,"continuous":false}];
    }

    if (ts_action == 'fences') {
        trackserver_mapdata = [{"div_id":"tsadminmap","default_lat":"51.44815","default_lon":"5.47279","default_zoom":"12","fullscreen":true,"is_live":false,"continuous":false}];
    }

    old_tb_click.call(this); // Pass the clicked element as context
    return false;
};


// Override tb_show()
var old_tb_show = window.tb_show;
var tb_show = function(c, u, i)
{
    old_tb_show(c, u, i);
    jQuery("#TB_window").css({"width": tb_window_width + 'px', "height": tb_window_height + 'px', "max-width": "100%", "max-height": "100%"});
    w = jQuery("#TB_window").width();
    h = jQuery("#TB_window").height();

    // Reposition using actual size
    jQuery("#TB_window").css({"margin-left": '-' + parseInt((w / 2),10) + 'px'});
    jQuery("#TB_window").css({"margin-top": '-' + parseInt((h / 2),10) + 'px'});

    if (w < tb_window_width) {
        jQuery("#TB_window").css({"left": "0", "margin-left": "0"});
    }
    if (h < tb_window_height) {
        jQuery("#TB_window").css({"top": "0", "margin-top": "0"});
    }
    jQuery("#TB_ajaxContent").css({"width": (w - 30) + 'px', "height": (h - 45) + 'px'});
    jQuery("#tsadminmapcontainer").css({"width": (w - 32) + 'px', "height": (h - 60)});
    if (trackserver_mapdata) {
        Trackserver.init( trackserver_mapdata );
        TrackserverAdmin.setup();
        if (typeof trackserver_mapdata[0].tracks != "undefined") {
            TrackserverAdmin.setup_editables();
        }
    }
    if (typeof trackserver_admin_geofences == 'object') {
        TrackserverAdmin.draw_geofences();
    }
};

var old_tb_remove = window.tb_remove;
var tb_remove = function ()
{
    // Clean up the map when appropriate
    if (typeof Trackserver !== 'undefined' && Trackserver.adminmap) {

        // Show 'unsaved data' if necessary.
        if (TrackserverAdmin.show_savedialog_if_modified()) return;
        old_tb_remove()
        Trackserver.adminmap.remove();
    }
    else {
        old_tb_remove()
    }
    trackserver_mapdata = false;
    TrackserverAdmin.clear_modified();  // probably redundant, but cheap
}

var ts_tb_show = function (div, caption, width, height) {
    tb_window_width = width;
    tb_window_height = height;
    tb_show(caption, "#TB_inline?width=&inlineId=" + div, "");
    return false;
};

// Put our own stuff in a separate namespace
// This relies on the global variable trackserver_admin_settings['msg']
// for translated messages.
//
var TrackserverAdmin = (function () {

    return {

        modified_locations: {},
        latlngs: {},    // object containing list of latlngs per track, that doesn't change when deleting a vertex
        geofences: {},  // hash of objects that hold leaflet shapes for geofences

        init: function() {
            this.checked = false;
            this.setup_eventhandlers();
        },

        setup: function() {
            this.map = Trackserver.adminmap;
        },

        check_selection: function( action ) {
            if (action == -1) {
                alert('No action selected');
                return false;
            }

            var min_items = 1;
            var min_str = trackserver_admin_settings['msg']['track'];
            if ( action == 'merge' ) {
                min_items = 2;
                min_str = trackserver_admin_settings['msg']['tracks'];
            }
            this.checked = jQuery('input[name=track\\[\\]]:checked');
            if (this.checked.length < min_items) {
                actionstr = trackserver_admin_settings['msg'][action] || action;
                errorstr = trackserver_admin_settings['msg']['selectminimum'].
                    replace(/%1\$s/g, actionstr).
                    replace(/%2\$s/g, min_items).
                    replace(/%3\$s/g, min_str);
                alert(errorstr);
                return false;
            }
            return true;
        },

        // This function is called from the click event of a submit-button. If it
        // returns true, the form will be submitted
        handle_bulk_action: function (action) {
            if (action == 'delete') {
                if (confirm(trackserver_admin_settings['msg']['areyousure'])) {
                    return true;
                }
            }
            if (action == 'merge') {
                // Get the last selected row
                var last = this.checked.last();
                var row = last.closest("tr");
                var tds = row.find("td");
                var merged_name;
                // Loop over the cells to find the track name
                jQuery.each(tds, function() {
                    switch (this.className) {
                        case 'name column-name':
                            merged_name = jQuery(this).text();
                            break;
                    }
                });
                jQuery('#input-merged-name').val(merged_name + ' (merged)');
                ts_tb_show('ts-merge-modal', 'Merge tracks', 600, 250);
                return false;
            }
            if (action == 'recalc' || action == 'dlgpx') {
                return true;
            }
            if (action == 'view') {
                var tracks = [];
                var track_url = track_base_url + "admin=1";
                var nonce =  false;
                jQuery.each(this.checked, function() {
                    var url = track_url + '&id=' + this.value;
                    var row = jQuery(this).closest("tr");
                    var tds = row.find("th,td");
                    jQuery.each(tds, function() {

                        // Extract the column name from the assigned CSS class
                        col_arr = /(column-)?([^-\s]+)(-column)?/.exec(this.className);
                        col = col_arr[2];

                        switch (col) {
                            case 'nonce':
                                nonce = jQuery(this).text();
                                url += "&_wpnonce="+nonce;
                                break;
                        }
                    });
                    tracks.push( { track_id: this.value, track_type: 'polylinexhr', markers: true, nonce: nonce, track_url: url });
                });
                trackserver_mapdata = [{"div_id":"tsadminmap","tracks":tracks,"default_lat":"51.44815","default_lon":"5.47279","default_zoom":"12","fullscreen":true,"is_live":false,"continuous":false}];
                ts_tb_show('ts-view-modal', 'Track', 1024, 768);
                return false;
            }
            return false;
        },

        setup_eventhandlers: function() {

            _this = this;

            jQuery('#doaction').click( function() {
                var action = jQuery('#bulk-action-selector-top').val();
                if (! _this.check_selection( action )) return false;
                return _this.handle_bulk_action( action );
            });

            jQuery('#doaction2').click( function() {
                var action = jQuery('#bulk-action-selector-bottom').val();
                if (! _this.check_selection( action )) return false;
                return _this.handle_bulk_action( action );
            });

            // When submitting for 'merge', add a hidden field to the bulk
            // action form, containing the name for the merged track.
            jQuery('#merge-submit-button').click( function() {
                var merged_name = jQuery('#input-merged-name').val();
                jQuery('#trackserver-tracks').append(
                    jQuery('<input>').attr({
                        type: 'hidden',
                        name: 'merged_name',
                        value: merged_name
                    }))
                .submit();
            });

            jQuery('#author-select-top,#author-select-bottom').change( function () {
                var author = jQuery('#' + this.id).val();
                jQuery('#trackserver-tracks').append(
                    jQuery('<input>').attr({
                        type: 'hidden',
                        name: 'author',
                        value: author
                    }))
                .submit();
            });

            jQuery('#addtrack-button-top,#addtrack-button-bottom').click( function () {
                ts_tb_show('ts-upload-modal', 'Upload GPX files', 600, 400);
                return false;
            });

            // Button that activates the file input
            jQuery('#ts-select-files-button').click( function () {
                jQuery('#ts-file-input').click();
            });

            // Process selected files. The upload button stays disabled if any
            // non-GPX files are selected.
            jQuery('#ts-file-input').change( function (e) {
                jQuery('#ts-file-input').each(function() {
                    var out='<ul style="list-style:square inside">';
                    var f = e.target.files,
                        len = f.length,
                        re = /\.gpx$/i,
                        error = false;
                    for (var i=0;i<len;i++){
                        if (! re.test(f[i].name)) {
                                error = '<i>Error: You have selected non-GPX files. Please upload GPX files only.</i>';
                        }
                        out += '<li>' + f[i].name + '</li>';
                    }
                    out += '</ul>';
                    if (!error) {
                        jQuery('#ts-upload-files-button').removeAttr('disabled');
                    }
                    else {
                        jQuery('#ts-upload-files-button').attr('disabled', 'disabled');
                    }
                    jQuery('#ts-upload-filelist').html(out);
                    jQuery('#ts-upload-warning').html(error || '');
                });
            });

            jQuery('#ts-upload-files-button').click( function() {
                jQuery('#ts-upload-files-button').attr('disabled', 'disabled').html('Wait...');
                jQuery('#ts-upload-form').submit();
            });

            jQuery('#ts-delete-track').click( function() {
                if (confirm(trackserver_admin_settings['msg']['areyousure'])) {
                    jQuery('#trackserver-edit-action').val('delete');
                    jQuery('#trackserver-edit-track').submit();
                }
            });

            jQuery('.ts-input-geofence').on('change', function(e) {
                jQuery('#ts_geofences_changed').css({display: 'block'});
            });
        },

        show_savedialog_if_modified: function( callback=false ) {

            var _this = this;

            if ( Object.keys(this.modified_locations).length > 0 ) {
                var savedialog = L.popup()
                    .setLatLng(Trackserver.adminmap.getCenter())
                    .setContent( trackserver_admin_settings['msg']['unsavedchanges'] +
                        '<br><br><button id="savedialogsavebutton">' +
                        trackserver_admin_settings['msg']['save'] +
                        '</button> <button id="savedialogdiscardbutton">' +
                        trackserver_admin_settings['msg']['discard'] +
                        '</button> <button id="savedialogcancelbutton">' +
                        trackserver_admin_settings['msg']['cancel'] +
                        '</button>')
                Trackserver.adminmap.openPopup(savedialog);
                jQuery('#savedialogsavebutton').on('click', function(e) {
                    _this.save_modified(true, callback);
                });
                jQuery('#savedialogcancelbutton').on('click', function(e) {
                    savedialog.remove();
                });
                jQuery('#savedialogdiscardbutton').on('click', function(e) {
                    _this.clear_modified();
                    tb_remove();
                    if (callback) callback.call(_this);
                });
                return true;
            }
            if (callback) callback.call(this);
            return false;
        },

        save_modified: function( close=false, callback=false ) {
            var map = Trackserver.adminmap;

            if ( Object.keys(this.modified_locations).length > 0 ) {
                var data = {
                    action: 'trackserver_save_track',
                    modifications: JSON.stringify(this.modified_locations),
                    _wpnonce: trackserver_mapdata[0].tracks[0].nonce,
                    t: trackserver_mapdata[0].tracks[0].track_id
                }

                var saving = L.popup()
                    .setLatLng(map.getCenter())
                    .setContent('Saving...');
                map.openPopup(saving);

                jQuery.post(ajaxurl, data, function(response) {
                    saving.remove();
                    if (close) {
                        tb_remove();
                    }
                    if (callback) {
                        callback.call(_this);
                    }
                });
            }
            this.clear_modified();
        },

        modify_location: function(track_id, loc_index, action, latlng) {
            if (!this.modified_locations.hasOwnProperty(track_id)) {
                this.modified_locations[track_id] = {};
            }
            if (!this.modified_locations[track_id].hasOwnProperty(loc_index)) {
                this.modified_locations[track_id][loc_index] = {};
            }
            mod = { action: action }
            if (latlng) {
                mod['lat'] = latlng.lat;
                mod['lng'] = latlng.lng;
            }
            this.modified_locations[track_id][loc_index] = mod;
        },

        location_delete: function(track_id, loc_index) {
            this.modify_location(track_id, loc_index, 'delete', null);
        },

        location_move: function(track_id, loc_index, latlng) {
            this.modify_location(track_id, loc_index, 'move', latlng);
        },

        clear_modified: function() {
            this.modified_locations = {}
            this.latlngs = {};
        },

        // Clone the list of latlngs, because we need immutable indexes
        init_latlngs: function(track_id, latlngs) {
            this.latlngs[track_id] = this.latlngs[track_id] || latlngs.slice(0);
        },

        // Get the original index of the vertex
        get_vertex_index: function(vertex) {
            var track_id = vertex.editor.feature.options.track_id;
            this.init_latlngs(track_id, vertex.latlngs);
            return this.latlngs[track_id].indexOf(vertex.latlng);
        },

        delete_vertex: function(vertex) {
            var track_id = vertex.editor.feature.options.track_id;
            var vertex_index = this.get_vertex_index(vertex);
            vertex.delete();
            this.location_delete(track_id, vertex_index);
        },

        move_vertex: function(vertex) {
            var track_id = vertex.editor.feature.options.track_id;
            var vertex_index = this.get_vertex_index(vertex);
            this.location_move(track_id, vertex_index, vertex.latlng);
        },

        setup_editables: function() {
            var map = Trackserver.adminmap;
            var _this = this;

            this.clear_modified();

            // Workaround for https://github.com/Leaflet/Leaflet.draw/issues/692
            L.Editable.include({
                createVertexIcon: function (options) {
                    return (L.Browser.mobile && L.Browser.touch) ? new L.Editable.TouchVertexIcon(options) : new L.Editable.VertexIcon(options);
                }
            });

            L.EditControl = L.Control.extend({
                options: {
                    position: 'topleft',
                    html: '',
                    title: {
                        'edit': trackserver_admin_settings['msg']['edittrack'],
                        'save': trackserver_admin_settings['msg']['savechanges']
                    }

                },

                onAdd: function (map) {
                    var container = L.DomUtil.create('div', 'leaflet-control-edit leaflet-bar leaflet-control');

                    this.link = L.DomUtil.create('a', 'leaflet-control-edit-button leaflet-bar-part', container);
                    this.link.href = '#';
                    this.link.title = trackserver_admin_settings['msg']['edittrack'];
                    this.link.innerHTML = this.options.html;
                    this._map = map;

                    L.DomEvent.on(this.link, 'click', this._click, this);

                    return container;
                },

                _click: function (e) {
                    L.DomEvent.stopPropagation(e);
                    L.DomEvent.preventDefault(e);
                    this.toggleEdit();
                },

                toggleEdit: function() {
                    var container = this.getContainer();
                    var edit_enabled = Trackserver.edit_enabled('tsadminmap');
                    if (edit_enabled) {
                        _this.save_modified();
                        L.DomUtil.removeClass(container, 'leaflet-control-edit-enabled');
                        this.link.title = this.options.title['edit'];
                    }
                    else {
                        L.DomUtil.addClass(container, 'leaflet-control-edit-enabled');
                        this.link.title = this.options.title['save'];
                    }
                    Trackserver.toggle_edit('tsadminmap');
                }

            });

            map.addControl(new L.EditControl());

            map.on('editable:vertex:contextmenu', function(e) {
                var vertex = e.vertex;
                var vertex_index = _this.get_vertex_index(vertex);

                map.once('popupopen', function(e) {

                    jQuery('.deletepoint').on('click', function(e) {
                        _this.delete_vertex(vertex);
                    });

                    jQuery('.splittrack').on('click', function(e) {
                        if (confirm(trackserver_admin_settings['msg']['areyousure'])) {

                            // Handle unsaved modifications, submit the 'split' action as a callback function.
                            // This function will be called with TrackserverAdmin as context.
                            _this.show_savedialog_if_modified( function() {
                                jQuery('#trackserver-edit-action').val('split');
                                jQuery('#trackserver-edit-track').append(
                                    jQuery('<input>').attr({
                                        type: 'hidden',
                                        name: 'vertex',
                                        value: vertex_index
                                    }))
                                .submit();
                            });
                        }
                    });
                });

                var popup = e.vertex.bindPopup('<a href="#" data-id="' + vertex_index + '" class="deletepoint" >' + trackserver_admin_settings['msg']['deletepoint'] + ' ' + vertex_index + '</a><br><a href="#" class="splittrack">' + trackserver_admin_settings['msg']['splittrack'] + '</a>').openPopup();

            });

            // Cancel rawclick event to prevent the default behaviour of deleting the vertex
            map.on('editable:vertex:rawclick', function(e) {
                e.cancel();
            });

            // Delete the vertex on ctrl-click or meta-click
            map.on('editable:vertex:metakeyclick editable:vertex:ctrlclick', function(e) {
                _this.delete_vertex(e.vertex);
            });

            //  Record modification after dragging a vertex
            map.on('editable:vertex:dragend', function(e) {
                _this.move_vertex(e.vertex);
            });
        },

        draw_geofence_shape: function(latlng, radius, featuregroup, i) {
            var _this = this;
            var map = featuregroup._map;
            var new_entry_id = jQuery('tr[data-newentry]').attr("data-id");
            var outer = L.circle(latlng, {radius: radius, color: '#ff0000', weight: 2, opacity: 0.4 }).addTo(featuregroup);
            var inner = new Trackserver.Mapicon(latlng, { fillColor: '#ff0000' }).addTo(featuregroup)
                .on('click', function(e) {
                    var popLocation= e.latlng;
                    var popup = L.popup()
                    .setLatLng(popLocation)
                    .setContent('<a href="#" id="remove-fence" data-id="' + i + '">Remove ' + i + '</a>')
                    .openOn(map);

                    jQuery('#remove-fence').on('click', function(e) {
                        jQuery('input[name="ts_geofence_lat[' + i + ']"]').val('0');
                        jQuery('input[name="ts_geofence_lon[' + i + ']"]').val('0');
                        jQuery('input[name="ts_geofence_radius[' + i + ']"]').val('0').focus();
                        jQuery('select[name="ts_geofence_action[' + i + ']"]').val('hide').change();
                        _this.remove_geofence(featuregroup, i);
                        map.closePopup();
                        return false;
                    });
                    L.DomEvent.stopPropagation(e);
                    L.DomEvent.preventDefault(e);
            });
            this.remove_geofence(featuregroup, i);
            this.geofences[i] = { outer: outer, inner: inner };
            return { outer: outer, inner: inner };
        },

        remove_geofence: function(featuregroup, i) {
            if (this.geofences.hasOwnProperty(i)) {
                featuregroup.removeLayer(this.geofences[i].outer);
                featuregroup.removeLayer(this.geofences[i].inner);
                delete(this.geofences[i]);
            }
        },

        draw_geofences: function() {
            var _this = this;
            var featuregroup = L.featureGroup().addTo(this.map);
            var valid_fences = 0;
            var new_entry_id = jQuery('tr[data-newentry]').attr("data-id");

            // Use data from the HTML table for drawing
            jQuery('tr.trackserver_geofence').each( function ( index, el ) {
                var input_lat = jQuery(el).find('input.ts-input-geofence-lat');
                var input_lon = jQuery(el).find('input.ts-input-geofence-lon');
                var input_radius = jQuery(el).find('input.ts-input-geofence-radius');
                var radius = parseInt(input_radius.val());
                if (radius > 0) {
                    var lat = parseFloat(input_lat.val());
                    var lon = parseFloat(input_lon.val());
                    var entry_id = jQuery(el).attr('data-id');
                    _this.draw_geofence_shape([lat,lon], radius, featuregroup, entry_id);
                    valid_fences++;
                }
            });

            if (valid_fences > 0) {  // Prevent 'Bounds are not valid' error
                this.map.fitBounds(featuregroup.getBounds());
            }

            this.map.on('click', function(e) {
                var popLocation= e.latlng;
                var popup = L.popup()
                .setLatLng(popLocation)
                .setContent('<b>Add Geofence</b><br><a href="#" id="ts-gf-pick-location">Pick location</a>')
                .openOn(_this.map);

                jQuery('#ts-gf-pick-location').on('click', function(e) {
                    jQuery('input[name="ts_geofence_lat[' + new_entry_id + ']"]').val(popLocation.lat);
                    jQuery('input[name="ts_geofence_lon[' + new_entry_id + ']"]').val(popLocation.lng);
                    jQuery('input[name="ts_geofence_radius[' + new_entry_id + ']"]').val('100').focus();
                    jQuery('select[name="ts_geofence_action[' + new_entry_id + ']"]').change();
                    _this.draw_geofence_shape([popLocation.lat,popLocation.lng], 100, featuregroup, new_entry_id);
                    _this.map.closePopup();
                    return false;
                });
            });
        }

    };
})();

TrackserverAdmin.init();
