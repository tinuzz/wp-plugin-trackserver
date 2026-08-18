/* global L, Trackserver, trackserver_admin_settings, trackserver_extra_settings, wp, jQuery, ajaxurl */

let tb_window_width;
let tb_window_height;
let trackserver_mapdata;
const trackserver_map_profile = () => trackserver_admin_settings.map_profile;

// Override tb_click()
const old_tb_click = window.tb_click;
window.tb_click = function() {
    const ts_action = jQuery(this).attr("data-action");
    const row = jQuery(this).closest("tr");
    const tds = row.find("th,td");

    trackserver_mapdata = false;

    const geometry = {
        'edit':    [600, 335],
        'addpass': [600, 250],
        'view':    [window.innerWidth - 40, window.innerHeight - 40],
        'fences':  [window.innerWidth - 40, window.innerHeight - 40],
        'howto':   [928, 685]
    };

    [tb_window_width, tb_window_height] = geometry[ts_action];

    if (ts_action === 'view' || ts_action === 'edit') {
        // track_base_url comes from WP via wp_localize_script()
        const track_url = new URL(trackserver_extra_settings.track_base_url);
        track_url.searchParams.append("admin", 1);
        let nonce = false;
        let track_id;

        // Loop over the table columns and set up the 'trackserver-edit-track' form with the data
        jQuery.each(tds, function() {

            // Extract the column name from the assigned CSS class
            const col_arr = /(column-)?([^-\s]+)(-column)?/.exec(this.className);
            const col = col_arr[2];

            switch (col) {
                case 'check':
                    track_id = jQuery(this).find('input').val();
                    track_url.searchParams.append("id", track_id);
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
                    track_url.searchParams.append("_wpnonce", nonce);
                    jQuery('#_wpnonce').val(nonce);
                    break;
            }
        });
        trackserver_mapdata = [{
            div_id: "tsadminmap",
            tracks: [{
                track_id,
                track_url: track_url.toString(),
                track_type: "polylinexhr",
                markers: true,
                nonce
            }],
            default_zoom: "12",
            fullscreen: true,
            is_live: false,
            continuous: false,
            profile: trackserver_map_profile()
        }];
    }

    if (ts_action === 'fences') {
        trackserver_mapdata = [{
            div_id: "tsadminmap",
            default_zoom: "12",
            fullscreen: true,
            is_live: false,
            continuous: false,
            profile: trackserver_map_profile()
        }];
    }

    old_tb_click.call(this); // Pass the clicked element as context
    return false;
};

// Override tb_show()
const old_tb_show = window.tb_show;
window.tb_show = function(c, u, i) {
    old_tb_show(c, u, i);

    const $TB_window = jQuery("#TB_window").css({
        width: `${tb_window_width}px`,
        height: `${tb_window_height}px`,
        "max-width": "100%",
        "max-height": "100%"
    });
    const w = $TB_window.width();
    const h = $TB_window.height();

    // Reposition using actual size
    $TB_window.css({ "margin-left": '-' + parseInt((w / 2),10) + 'px'});
    $TB_window.css({ "margin-top": '-' + parseInt((h / 2),10) + 'px'});

    if (w < tb_window_width) {
        $TB_window.css({ left: "0", "margin-left": "0" });
    }
    if (h < tb_window_height) {
        $TB_window.css({ top: "0", "margin-top": "0" });
    }

    jQuery("#TB_ajaxContent").css({ width: (w - 30) + 'px', height: (h - 45) + 'px' });
    jQuery("#trackserver-adminmap-container").css({ width: (w - 32) + 'px', height: (h - 60) });

    // Initialize the map if needed.
    // TODO: only do this if the Thickbox is actually showing a map!!
    if (trackserver_mapdata) {
        Trackserver.init(trackserver_mapdata);
        TrackserverAdmin.setup();
        if (typeof trackserver_mapdata[0].tracks !== "undefined") {
            TrackserverAdmin.setup_leaflet_controls();
        }
    }
    if (typeof trackserver_admin_geofences === 'object') {
        TrackserverAdmin.draw_geofences();
    }
};

const old_tb_remove = window.tb_remove;
window.tb_remove = function() {
    if (typeof Trackserver !== 'undefined' && Trackserver.adminmap) {
        if (TrackserverAdmin.show_savedialog_if_modified()) return;
        old_tb_remove();
        if ( Trackserver.adminmap._containerId ) {
          Trackserver.adminmap.remove();
        }
    } else {
        old_tb_remove();
    }
    trackserver_mapdata = false;
    TrackserverAdmin.clear_modified();
};

const ts_tb_show = (div, caption, width, height) => {
    tb_window_width = width;
    tb_window_height = height;
    window.tb_show(caption, `#TB_inline?width=&inlineId=${div}`, "");
    return false;
};

// Put our own stuff in a separate namespace
// This relies on the global variable 'trackserver_admin_settings'
// for translated messages.
const TrackserverAdmin = (() => {

    return {
        modified_locations: {},
        latlngs: {},    // object containing list of latlngs per track, that doesn't change when deleting a vertex
        geofences: {},  // hash of objects that hold leaflet shapes for geofences
        app_password: 'NO_PASSWORD_SET',   // last shown app password

        init() {
            this.checked = false;
            this.setup_eventhandlers();
        },

        setup() {
            this.map = Trackserver.adminmap;
        },

        check_selection(action) {
            if (action === -1) {
                alert('No action selected');
                return false;
            }
            let min_items = 1;
            let min_str = trackserver_admin_settings['msg']['track'];
            if (action === 'merge') {
                min_items = 2;
                min_str = trackserver_admin_settings['msg']['tracks'];
            }
            this.checked = jQuery('input[name=track\\[\\]]:checked');
            if (this.checked.length < min_items) {
                const actionstr = trackserver_admin_settings['msg'][action] || action;
                const errorstr = trackserver_admin_settings['msg']['selectminimum']
                    .replace(/%1\$s/g, actionstr)
                    .replace(/%2\$s/g, min_items)
                    .replace(/%3\$s/g, min_str);
                alert(errorstr);
                return false;
            }
            return true;
        },

        // This function is called from the click event of a submit-button. If it
        // returns true, the form will be submitted
        handle_bulk_action(action) {
            if (action === 'delete' || action === 'duplicate') {
                const selector = action + 'cap';
                const actionstr = trackserver_admin_settings['msg'][selector];
                const msg = actionstr + ' ' + this.checked.length + ' ' +
                  trackserver_admin_settings['msg']['tracks'] + '. ' +
                  trackserver_admin_settings['msg']['areyousure'];
                if (confirm(msg)) {
                    return true;
                }
            }
            if (action === 'merge') {
                // Get the last selected row
                const last = this.checked.last();
                const row = last.closest("tr");
                const tds = row.find("td");
                let merged_name;
                // Loop over the cells to find the track name
                tds.each(function() {
                    switch (this.className) {
                        case 'name column-name':
                            merged_name = jQuery(this).text();
                            break;
                    }
                });
                jQuery('#input-merged-name').val(`${merged_name} (merged)`);
                ts_tb_show('trackserver-merge-modal', 'Merge tracks', 600, 250);
                return false;
            }
            if (action === 'recalc' || action === 'dlgpx') {
                return true;
            }
            if (action === 'view') {
                const tracks = [];
                const track_url = new URL(trackserver_extra_settings.track_base_url);
                track_url.searchParams.append("admin", 1);
                let nonce =  false;
                this.checked.each(function() {
                    track_url.searchParams.append("id", this.value);
                    const row = jQuery(this).closest("tr");
                    const tds = row.find("th,td");
                    tds.each(function() {
                        // Extract the column name from the assigned CSS class
                        const col_arr = /(column-)?([^-\s]+)(-column)?/.exec(this.className);
                        const col = col_arr[2];
                        switch (col) {
                            case 'nonce':
                                nonce = jQuery(this).text();
                                track_url.searchParams.append("_wpnonce", nonce);
                                break;
                        }
                    });
                    tracks.push({
                        track_id: this.value,
                        track_type: 'polylinexhr',
                        markers: true,
                        nonce,
                        track_url: track_url.toString()
                    });
                });
                trackserver_mapdata = [{
                    div_id: "tsadminmap",
                    tracks,
                    default_zoom: "12",
                    fullscreen: true,
                    is_live: false,
                    continuous: false,
                    profile: trackserver_map_profile()
                }];
                ts_tb_show('trackserver-view-modal', 'Track', window.innerWidth - 40, window.innerHeight - 40);
                return false;
            }
            return false;
        },

        setup_eventhandlers() {
            const _this = this;

            jQuery('#doaction').click(function() {
                const action = jQuery('#bulk-action-selector-top').val();
                if (!_this.check_selection(action)) return false;
                return _this.handle_bulk_action(action);
            });

            jQuery('#doaction2').click(function() {
                const action = jQuery('#bulk-action-selector-bottom').val();
                if (!_this.check_selection(action)) return false;
                return _this.handle_bulk_action(action);
            });

            // When submitting for 'merge', add a hidden field to the bulk
            // action form, containing the name for the merged track.
            jQuery('#merge-submit-button').click(function() {
                const merged_name = jQuery('#input-merged-name').val();
                jQuery('#trackserver-tracks').append(
                    jQuery('<input>').attr({
                        type: 'hidden',
                        name: 'merged_name',
                        value: merged_name
                    })
                ).submit();
            });

            jQuery('#author-select-top,#author-select-bottom').change(function() {
                const author = jQuery('#' + this.id).val();
                jQuery('#trackserver-tracks').append(
                    jQuery('<input>').attr({
                        type: 'hidden',
                        name: 'author',
                        value: author
                    })
                ).submit();
            });

            jQuery('#per-page-select-top,#per-page-select-bottom').change(function() {
                const per_page = jQuery('#' + this.id).val();
                jQuery('#trackserver-tracks').append(
                    jQuery('<input>').attr({
                        type: 'hidden',
                        name: 'per_page',
                        value: per_page
                    })
                ).submit();
            });

            jQuery('#addtrack-button-top,#addtrack-button-bottom').click(function() {
                ts_tb_show('trackserver-upload-modal', 'Upload GPX files', 600, 400);
                return false;
            });

            jQuery('#ts-select-files-button').click(function() {
                jQuery('#trackserver-file-input').click();
            });

            // Process selected files. The upload button stays disabled if any
            // non-GPX files are selected.
            jQuery('#trackserver-file-input').change(function(e) {
                jQuery('#trackserver-file-input').each(function() {
                    let out = '<ul style="list-style:square inside">';
                    const f = e.target.files;
                    const len = f.length;
                    const re = /\.gpx$/i;
                    let error = false;
                    for (let i = 0; i < len; i++) {
                        if (!re.test(f[i].name)) {
                            error = '<i>Error: You have selected non-GPX files. Please upload GPX files only.</i>';
                        }
                        out += `<li>${f[i].name}</li>`;
                    }
                    out += '</ul>';
                    if (!error) {
                        jQuery('#trackserver-upload-files').removeAttr('disabled');
                    } else {
                        jQuery('#trackserver-upload-files').attr('disabled', 'disabled');
                    }
                    jQuery('#trackserver-upload-filelist').html(out);
                    jQuery('#trackserver-upload-warning').html(error || '');
                });
            });

            jQuery('#trackserver-upload-files').click(function() {
                jQuery(this).attr('disabled', 'disabled').html('Wait...');
                jQuery('#trackserver-upload-form').submit();
            });

            jQuery('#trackserver-delete-track').click(function() {
                let msg = trackserver_admin_settings['msg']['delete1'] + ' ' +
                  trackserver_admin_settings['msg']['track'] + '. ' +
                  trackserver_admin_settings['msg']['areyousure'];
                if (confirm(msg)) {
                    jQuery('#trackserver-edit-action').val('delete');
                    jQuery('#trackserver-edit-track').submit();
                }
            });

            jQuery('.ts-input-geofence').on('change', function() {
                jQuery('#ts_geofences_changed').css({ display: 'block' });
            });

            jQuery('.ts-view-pass').on('click', function() {
                const button = jQuery(this);
                const id = button.data('id');
                if (button.data('action') === 'view') {
                    const password = jQuery(`#pass${id}`).data('password');
                    jQuery(`#passtext${id}`).text(password);
                    jQuery('.apppassword').text(password);
                    _this.app_password = password;
                    button.val(trackserver_admin_settings['profile_msg']['hide']);
                    button.data('action', 'hide');
                } else {
                    jQuery(`#passtext${id}`).text('**********');
                    jQuery('.apppassword').text('**********');
                    button.val(trackserver_admin_settings['profile_msg']['view']);
                    button.data('action', 'view');
                }
                return false;
            });

            jQuery('.ts-delete-pass').on('click', function() {
                if (confirm(trackserver_admin_settings['msg']['areyousure'])) {
                    const button = jQuery(this);
                    const id = button.data('id');
                    jQuery('input[name="apppass_action"]').val('delete');
                    jQuery('input[name="apppass_id"]').val(id);
                    return true;
                } else {
                    return false;
                }
            });

            jQuery('#ts-view-pass-button').on('click', function() {
                if (jQuery('#ts-apppass-input').attr('type') === 'password') {
                    jQuery('#ts-apppass-input').attr('type', 'text');
                    jQuery('#ts-view-pass-button').val('Hide');
                } else {
                    jQuery('#ts-apppass-input').attr('type', 'password');
                    jQuery('#ts-view-pass-button').val('View');
                }
            });

            jQuery('#ts-gen-pass-button').on('click', function() {
                const pass = btoa(Math.random() * 999).substr(0, 10);
                jQuery('#ts-apppass-input').val(pass.toLowerCase());
            });

            const password = jQuery('#pass0').data('password');
            if (typeof password !== 'undefined') {
                _this.app_password = password;
            }

            if (navigator.clipboard) {
                jQuery('.trackserver-copy-url').on('click', function() {
                    const src_el = `trackserver-url${this.id.slice(-1)}`;
                    const content = jQuery(`#${src_el}`).text().replace(/\*\*\*+/, _this.app_password);
                    navigator.clipboard.writeText(content);
                });
            } else {
                // Hide the copy buttons in browsers that lack navigator.clipboard support
                jQuery('#trackserver-copy-url1-button').css('display', 'none');
                jQuery('#trackserver-copy-url2-button').css('display', 'none');
            }

            jQuery('#add-map-profile-button').on('click', function() {
                const last_id = parseInt(jQuery('#map-profile-table tr:last').data('id'));
                const next_id = last_id + 1;
                const label = `profile${next_id}`;
                const selected_profile = jQuery('input[name="default_profile"]:checked').val();
                const is_vector = jQuery(`#vector${selected_profile}`).is(':checked');
                const vector = is_vector === true ? ' checked' : '';                     // safe value
                const tile_url = jQuery(`#tile_url${selected_profile}`).val();           // unsafe value
                const attribution = jQuery(`#attribution${selected_profile}`).val();     // unsafe value
                const minzoom = parseInt(jQuery(`#minzoom${selected_profile}`).val());   // safe value
                const maxzoom = parseInt(jQuery(`#maxzoom${selected_profile}`).val());   // safe value
                const lat = parseFloat(jQuery(`#latitude${selected_profile}`).val());    // safe value
                const lon = parseFloat(jQuery(`#longitude${selected_profile}`).val());   // safe value

                const row = `<tr id="profile-row${next_id}" data-id="${next_id}" class="trackserver-map-profile">` +
                    `<td style="text-align:center"><input type="radio" name="default_profile" value="${next_id}"></td>` +
                    `<td><input type="text" style="width: 100%" name="trackserver_map_profiles[${next_id}][label]" value="${label}"></td>` +
                    `<td><textarea id="tile_url${next_id}" name="trackserver_map_profiles[${next_id}][tile_url]"></textarea></td>` +
                    `<td style="text-align:center"><input type="checkbox" id="vector${next_id}" name="trackserver_map_profiles[${next_id}][vector]"${vector}></td>` +
                    `<td><textarea id="attribution${next_id}" name="trackserver_map_profiles[${next_id}][attribution]"></textarea></td>` +
                    `<td><input type="text" style="width: 100%" id="minzoom${next_id}" name="trackserver_map_profiles[${next_id}][min_zoom]" value="${minzoom}"></td>` +
                    `<td><input type="text" style="width: 100%" id="maxzoom${next_id}" name="trackserver_map_profiles[${next_id}][max_zoom]" value="${maxzoom}"></td>` +
                    `<td><input type="text" style="width: 100%" id="latitude${next_id}" name="trackserver_map_profiles[${next_id}][default_lat]" value="${lat}"></td>` +
                    `<td><input type="text" style="width: 100%" id="longitude${next_id}" name="trackserver_map_profiles[${next_id}][default_lon]" value="${lon}"></td>` +
                    `<td><a id="delete-profile-button${next_id}" title="Delete profile" class="button ts-delete-profile-button" data-id="${next_id}" data-action="deleteprofile">Delete</a></td></tr>`;

                jQuery('#map-profile-table > tbody:last-child').append(row);

                // Set unsafe values this way to prevent escaping issues.
                jQuery(`#tile_url${next_id}`).val(tile_url);
                jQuery(`#attribution${next_id}`).val(attribution);

                jQuery(`#delete-profile-button${next_id}`).on('click', function() {
                    const row_id = jQuery(this).data('id');
                    jQuery(`#profile-row${row_id}`).remove();
                    jQuery('#ts_map_profiles_changed').css({ display: 'block' });
                });

                wp.apiRequest({
                    path: '/trackserver/v1/add-map-profile',
                    method: 'POST',
                    data: {
                        label,
                        tile_url,
                        vector: Boolean(trackserver_admin_settings.map_profile['vector']),
                        attribution,
                        min_zoom: minzoom,
                        max_zoom: maxzoom,
                        default_lat: lat,
                        default_lon: lon
                    }
                })
                .then(response => {
                    jQuery('#add-profile-result').html(` &nbsp; ${response.message} &nbsp; `);
                    setTimeout(() => {
                        jQuery('#add-profile-result').html('');
                    }, 3000);
                })
                .catch(error => {
                    console.error('Request failed:', error);
                    jQuery('#ts_map_profiles_changed').css({ display: 'block' });
                });
            });

            jQuery('.ts-delete-profile-button').on('click', function() {
                const row_id = jQuery(this).data('id');
                jQuery(`#profile-row${row_id}`).remove();
                jQuery('#ts_map_profiles_changed').css({ display: 'block' });
            });
        },

        show_savedialog_if_modified(callback = false) {
            const _this = this;

            if (Object.keys(this.modified_locations).length > 0) {
                const savedialog = L.popup()
                    .setLatLng(Trackserver.adminmap.getCenter())
                    .setContent(trackserver_admin_settings['msg']['unsavedchanges'] +
                        '<br><br><button id="savedialogsavebutton">' +
                        trackserver_admin_settings['msg']['save'] +
                        '</button> <button id="savedialogdiscardbutton">' +
                        trackserver_admin_settings['msg']['discard'] +
                        '</button> <button id="savedialogcancelbutton">' +
                        trackserver_admin_settings['msg']['cancel'] +
                        '</button>');
                Trackserver.adminmap.openPopup(savedialog);
                jQuery('#savedialogsavebutton').on('click', function() {
                    _this.save_modified(true, callback);
                });
                jQuery('#savedialogcancelbutton').on('click', function() {
                    savedialog.remove();
                });
                jQuery('#savedialogdiscardbutton').on('click', function() {
                    _this.clear_modified();
                    window.tb_remove();
                    if (callback) callback.call(_this);
                });
                return true;
            }
            if (callback) callback.call(this);
            return false;
        },

        save_modified(close = false, callback = false) {
            const _this = this;
            const map = Trackserver.adminmap;

            if (Object.keys(this.modified_locations).length > 0) {
                const data = {
                    action: 'trackserver_save_track',
                    modifications: JSON.stringify(this.modified_locations),
                    _wpnonce: trackserver_mapdata[0].tracks[0].nonce,
                    t: trackserver_mapdata[0].tracks[0].track_id
                };

                const saving = L.popup()
                    .setLatLng(map.getCenter())
                    .setContent('Saving...');
                map.openPopup(saving);

                jQuery.post(ajaxurl, data, function() {
                    saving.remove();
                    if (close) {
                        window.tb_remove();
                    }
                    if (callback) {
                        callback.call(_this);
                    }
                });
            }
            this.clear_modified();
        },

        modify_location(track_id, loc_index, action, latlng) {
            if (!Object.hasOwn(this.modified_locations, track_id)) {
                this.modified_locations[track_id] = {};
            }
            if (!Object.hasOwn(this.modified_locations[track_id], loc_index)) {
                this.modified_locations[track_id][loc_index] = {};
            }
            const mod = { action };
            if (latlng) {
                mod['lat'] = latlng.lat;
                mod['lng'] = latlng.lng;
            }
            this.modified_locations[track_id][loc_index] = mod;
        },

        location_delete(track_id, loc_index) {
            this.modify_location(track_id, loc_index, 'delete', null);
        },

        location_move(track_id, loc_index, latlng) {
            this.modify_location(track_id, loc_index, 'move', latlng);
        },

        clear_modified() {
            this.modified_locations = {};
            this.latlngs = {};
        },

        // Clone the list of latlngs, because we need immutable indexes
        init_latlngs(track_id, latlngs) {
            this.latlngs[track_id] = this.latlngs[track_id] || latlngs.slice(0);
        },

        // Get the original index of the vertex
        get_vertex_index(vertex) {
            const track_id = vertex.editor.feature.options.track_id;
            this.init_latlngs(track_id, vertex.latlngs);
            return this.latlngs[track_id].indexOf(vertex.latlng);
        },

        delete_vertex(vertex) {
            const track_id = vertex.editor.feature.options.track_id;
            const vertex_index = this.get_vertex_index(vertex);
            vertex.delete();
            this.location_delete(track_id, vertex_index);
        },

        move_vertex(vertex) {
            const track_id = vertex.editor.feature.options.track_id;
            const vertex_index = this.get_vertex_index(vertex);
            this.location_move(track_id, vertex_index, vertex.latlng);
        },

        setup_leaflet_controls() {
            const map = Trackserver.adminmap;
            const _this = this;

            this.clear_modified();

            // Workaround for https://github.com/Leaflet/Leaflet.draw/issues/692
            L.Editable.include({
                createVertexIcon(options) {
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

                onAdd(map) {
                    const container = L.DomUtil.create('div', 'leaflet-control-edit leaflet-bar leaflet-control');
                    this.link = L.DomUtil.create('a', 'leaflet-control-edit-button leaflet-bar-part', container);
                    this.link.href = '#';
                    this.link.title = trackserver_admin_settings['msg']['edittrack'];
                    this.link.innerHTML = this.options.html;
                    this._map = map;
                    L.DomEvent.on(this.link, 'click', this._click, this);
                    return container;
                },

                _click(e) {
                    L.DomEvent.stopPropagation(e);
                    L.DomEvent.preventDefault(e);
                    this.toggleEdit();
                },

                toggleEdit() {
                    const container = this.getContainer();
                    const edit_enabled = Trackserver.edit_enabled('tsadminmap');
                    if (edit_enabled) {
                        _this.save_modified();
                        L.DomUtil.removeClass(container, 'leaflet-control-edit-enabled');
                        this.link.title = this.options.title['edit'];
                    } else {
                        L.DomUtil.addClass(container, 'leaflet-control-edit-enabled');
                        this.link.title = this.options.title['save'];
                    }
                    Trackserver.toggle_edit('tsadminmap');
                }
            });

            map.addControl(new L.EditControl());

            map.on('editable:vertex:contextmenu', function(e) {
                const vertex       = e.vertex;
                const vertex_index = _this.get_vertex_index(vertex);
                const track_id     = vertex.editor.feature.options.track_id;
                const nonce        = trackserver_mapdata[0].tracks.find(t => t.track_id === track_id).nonce;

                map.once('popupopen', function() {
                    jQuery('.deletepoint').on('click', function() {
                        _this.delete_vertex(vertex);
                    });
                    jQuery('.splittrack').on('click', function() {
                        if (confirm(trackserver_admin_settings['msg']['areyousure'])) {
                            // Handle unsaved modifications, submit the 'split' action as a callback function.
                            // This function will be called with TrackserverAdmin as context.
                            _this.show_savedialog_if_modified(function() {
                                jQuery('#trackserver-edit-action').val('split');
                                jQuery('#track_id').val(track_id);
                                jQuery('#_wpnonce').val(nonce);
                                jQuery('#trackserver-edit-track').append(
                                    jQuery('<input>').attr({
                                        type: 'hidden',
                                        name: 'vertex',
                                        value: vertex_index
                                    })
                                ).submit();
                            });
                        }
                    });
                });

                vertex.bindPopup(
                    `<button data-id="${vertex_index}" class="deletepoint">${trackserver_admin_settings['msg']['deletepoint']} ${vertex_index}</button><br>` +
                    `<button class="splittrack">${trackserver_admin_settings['msg']['splittrack']}</button>`
                ).openPopup();
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

            L.DeleteControl = L.Control.extend({
                options: {
                    position: 'topleft',
                    html: trackserver_admin_settings['icons']['trashcan'],
                    title: trackserver_admin_settings['msg']['delete1'],
                },

                onAdd(map) {
                    const container = L.DomUtil.create('div', 'leaflet-control-delete leaflet-bar leaflet-control');
                    this.link = L.DomUtil.create('a', 'leaflet-control-delete-button leaflet-bar-part', container);
                    this.link.href = '#';
                    this.link.title = trackserver_admin_settings['msg']['delete1'];
                    this.link.innerHTML = this.options.html;
                    this._map = map;
                    L.DomEvent.on(this.link, 'click', this._click, this);
                    L.DomEvent.on(this.link, 'dblclick', L.DomEvent.stop);
                    L.DomEvent.on(this.link, 'mousedown', L.DomEvent.stop);
                    L.DomEvent.on(this.link, 'mouseup', L.DomEvent.stop);
                    return container;
                },

                _click(e) {
                    L.DomEvent.stopPropagation(e);
                    L.DomEvent.preventDefault(e);
                    const msg = trackserver_admin_settings['msg']['deletecap'] + ' ' +
                      trackserver_mapdata[0].tracks.length  + ' ' +
                      trackserver_admin_settings['msg']['tracks'] + '. ' +
                      trackserver_admin_settings['msg']['areyousure'];
                    if (confirm(msg)) {
                        if (trackserver_mapdata[0].tracks.length === 1) {
                            jQuery('#track_id').val(trackserver_mapdata[0].tracks[0].track_id);
                            jQuery('#trackserver-edit-action').val('delete');
                            jQuery('#trackserver-edit-track').submit();
                        } else {
                            jQuery('#bulk-action-selector-top').val('delete');
                            jQuery('#trackserver-tracks').submit();
                        }
                    }
                }
            });

            map.addControl(new L.DeleteControl());
        },

        draw_geofence_shape(latlng, radius, featuregroup, i) {
            const _this = this;
            const map = featuregroup._map;
            const outer = L.circle(latlng, { radius, color: '#ff0000', weight: 2, opacity: 0.4 }).addTo(featuregroup);
            const inner = new Trackserver.Mapicon(latlng, { fillColor: '#ff0000' }).addTo(featuregroup)
                .on('click', function(e) {
                    const popLocation = e.latlng;
                    L.popup()
                        .setLatLng(popLocation)
                        .setContent(`<a href="#" id="remove-fence" data-id="${i}">Remove ${i}</a>`)
                        .openOn(map);

                    jQuery('#remove-fence').on('click', function() {
                        jQuery(`input[name="ts_geofence_lat[${i}]"]`).val('0');
                        jQuery(`input[name="ts_geofence_lon[${i}]"]`).val('0');
                        jQuery(`input[name="ts_geofence_radius[${i}]"]`).val('0').focus();
                        jQuery(`select[name="ts_geofence_action[${i}]"]`).val('hide').change();
                        _this.remove_geofence(featuregroup, i);
                        map.closePopup();
                        return false;
                    });
                    L.DomEvent.stopPropagation(e);
                    L.DomEvent.preventDefault(e);
                });
            this.remove_geofence(featuregroup, i);
            this.geofences[i] = { outer, inner };
            return { outer, inner };
        },

        remove_geofence(featuregroup, i) {
            if (Object.hasOwn(this.geofences, i)) {
                featuregroup.removeLayer(this.geofences[i].outer);
                featuregroup.removeLayer(this.geofences[i].inner);
                delete this.geofences[i];
            }
        },

        draw_geofences() {
            const _this = this;
            const featuregroup = L.featureGroup().addTo(this.map);
            let valid_fences = 0;
            const new_entry_id = jQuery('tr[data-newentry]').attr("data-id");

            // Use data from the HTML table for drawing
            jQuery('tr.trackserver_geofence').each(function() {
                const input_lat = jQuery(this).find('input.ts-input-geofence-lat');
                const input_lon = jQuery(this).find('input.ts-input-geofence-lon');
                const input_radius = jQuery(this).find('input.ts-input-geofence-radius');
                const radius = parseInt(input_radius.val());
                if (radius > 0) {
                    const lat = parseFloat(input_lat.val());
                    const lon = parseFloat(input_lon.val());
                    const entry_id = jQuery(this).attr('data-id');
                    _this.draw_geofence_shape([lat, lon], radius, featuregroup, entry_id);
                    valid_fences++;
                }
            });

            if (valid_fences > 0) {  // Prevent 'Bounds are not valid' error
                this.map.fitBounds(featuregroup.getBounds());
            }

            this.map.on('click', function(e) {
                const popLocation = e.latlng;
                L.popup()
                    .setLatLng(popLocation)
                    .setContent('<b>Add Geofence</b><br><a href="#" id="ts-gf-pick-location">Pick location</a>')
                    .openOn(_this.map);

                jQuery('#ts-gf-pick-location').on('click', function() {
                    jQuery(`input[name="ts_geofence_lat[${new_entry_id}]"]`).val(popLocation.lat);
                    jQuery(`input[name="ts_geofence_lon[${new_entry_id}]"]`).val(popLocation.lng);
                    jQuery(`input[name="ts_geofence_radius[${new_entry_id}]"]`).val('100').focus();
                    jQuery(`select[name="ts_geofence_action[${new_entry_id}]"]`).change();
                    _this.draw_geofence_shape([popLocation.lat, popLocation.lng], 100, featuregroup, new_entry_id);
                    _this.map.closePopup();
                    return false;
                });
            });
        }
    };
})();

TrackserverAdmin.init();

