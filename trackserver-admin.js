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
            switch (this.className) {
                case 'id column-id':
                    track_url += "&id="+jQuery(this).text();
                    break;
                case 'nonce column-nonce':
                    track_url += "&_wpnonce="+jQuery(this).text();
                    break;
            }
        });
        trackserver_mapdata = [{"div_id":"tsadminmap","track_url":track_url,"default_lat":"51.44815","default_lon":"5.47279","default_zoom":"12","fullscreen":true,"is_live":false}];
    }
    if (ts_action == 'howto') {

        tb_window_width = 850;
        tb_window_height = 560;
    }

    if (ts_action == 'edit') {
        // http://stackoverflow.com/questions/14460421/jquery-get-the-contents-of-a-table-row-with-a-button-click
        jQuery.each(tds, function() {
            switch (this.className) {
                case 'id column-id':
                    jQuery('#track_id').val(jQuery(this).text());
                    break;
                case 'name column-name':
                    jQuery('#input-track-name').val(jQuery(this).text());
                    break;
                case 'source column-source':
                    jQuery('#input-track-source').val(jQuery(this).text());
                    break;
                case 'nonce column-nonce':
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
    jQuery("#TB_window").css({"width": tb_window_width + 'px', "height": tb_window_height + 'px', "marginleft": margin});
    jQuery("#TB_ajaxContent").css({"width": (tb_window_width - 30) + 'px', "height": (tb_window_height - 45) + 'px'});
    jQuery("#tsadminmapcontainer").css({"width": (tb_window_width - 30) + 'px', "height": (tb_window_height - 118)});
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
}

var action_selected = function (val) {
    if (val == -1) {
        alert('No action selected');
        return false;
    }
    return true;
}

jQuery('#doaction').click(function(){
    var val = jQuery('#bulk-action-selector-top').val();
    if (! action_selected( val )) return false;
    alert('Are you sure? ' + val);
});
jQuery('#doaction2').click(function(){
    var val = jQuery('#bulk-action-selector-bottom').val();
    if (! action_selected( val )) return false;
    alert('Are you sure? ' + val);
});
