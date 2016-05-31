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
    var tds = row.find("td");
    if (ts_action == 'edit') {

        tb_window_width = 600;
        tb_window_height = 320;
    }
    if (ts_action == 'view') {

        tb_window_width = 800;
        tb_window_height = 600;

        // track_base_url should come from WP via wp_localize_script()
        track_url = track_base_url + "admin=1";
        jQuery.each(tds, function() {
            col_arr = /column-(\S+)/.exec(this.className);
            col = col_arr[1];
            switch (col) {
                case 'id':
                    track_url += "&id="+jQuery(this).text();
                    break;
                case 'nonce':
                    track_url += "&_wpnonce="+jQuery(this).text();
                    break;
            }
        });
        trackserver_mapdata = [{"div_id":"tsadminmap","tracks":[{"track_id":"0","track_url":track_url,"track_type":"polyline"}],"default_lat":"51.44815","default_lon":"5.47279","default_zoom":"12","fullscreen":true,"is_live":false,"markers":true,"continuous":false}];
    }
    if (ts_action == 'howto') {

        tb_window_width = 850;
        tb_window_height = 560;
    }

    if (ts_action == 'edit') {
        // http://stackoverflow.com/questions/14460421/jquery-get-the-contents-of-a-table-row-with-a-button-click
        jQuery.each(tds, function() {
            col_arr = /column-(\S+)/.exec(this.className);
            col = col_arr[1];
            switch (col) {
                case 'id':
                    // Strip everything from the first non-numeric character
                    id_val = jQuery(this).text().replace(/[^0-9].*/, '');
                    jQuery('#track_id').val(id_val);
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
                    jQuery('#_wpnonce').val(jQuery(this).text());
                    break;
            }
        });
    }
    old_tb_click.call(this); // Pass the clicked element as context
    return false;
};


// Override tb_show()
var old_tb_show = window.tb_show;
var tb_show = function(c, u, i)
{
    old_tb_show(c, u, i);
    margin = '-' + parseInt((tb_window_width / 2),10) + 'px';
    jQuery("#TB_window").css({"width": tb_window_width + 'px', "height": tb_window_height + 'px', "margin-left": margin, "max-width": "100%", "max-height": "100%"});
    w = jQuery("#TB_window").width();
    h = jQuery("#TB_window").height();
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
    }
};

var old_tb_remove = window.tb_remove;
var tb_remove = function ()
{
    old_tb_remove()
    // Clean up the map when appropriate
    if (typeof Trackserver !== 'undefined' && Trackserver.adminmap) {
        Trackserver.adminmap.remove();
    }
    trackserver_mapdata = false;
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

        init: function() {
            this.checked = false;
            this.setup_eventhandlers();
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
            //var checked = jQuery('input[name=track\\[\\]]:checked');
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
            if (action == 'recalc') {
                return true;
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

            jQuery('#addtrack-button').click( function () {
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
        }
    };
})();

TrackserverAdmin.init();
