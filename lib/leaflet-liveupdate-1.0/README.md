A Leaflet plugin to periodically ('live') update something on a map

# Leaflet.Liveupdate

Leaflet.Liveupdate is a simple control to update features of a
[Leaflet](http://leafletjs.com/) map periodically using a callback function.

A control button is added to the map, with which the live updates can
be stopped and restarted.

## Using the Liveupdate

    L.control.liveupdate ({
        update_map: function () {
            ...
        },
        position: 'topleft'
    })
    .addTo(map)
    .startUpdating();

## Available Options:

There are some options:

`position:` (string) The standard Leaflet.Control position parameter. Optional, defaults to 'topleft'

`update_map:` (function) The callback function that is called periodically

`title:` (object) An object that defines the message that is displayed on the map when liveupdate is
toggled on or off. A [Leaflet.Messagebox](https://github.com/tinuzz/leaflet-messagebox)
must be added to the map for this to work. Optional, defaults to

    {
        'false': 'Start live updates',
        'true': 'Stop live updates'
    }

`interval:` (integer) The number of milliseconds in the interval in which the
update should be repeated. Optional, defaults to 10000 (10 seconds).

## Styling ##

The liveupdate button can be styled with CSS, see [the css file](leaflet-liveupdate.css) for details.

# License

Leaflet.Liveupdate is free software. Please see [LICENSE](LICENSE) for details.
