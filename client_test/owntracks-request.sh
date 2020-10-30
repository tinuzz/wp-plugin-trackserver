#!/bin/zsh
mydir=$(dirname $0)

. ${mydir}/functions.sh

initialize

tn="Trackserver client test"
do=$trackserver_ts
sp=4.8
ang=117

json=$( jq -n \
	--arg xtype 'location' \
	--arg tst "$timestamp" \
	--arg lat "$latitude" \
	--arg lon "$longitude" \
	--arg alt "$altitude" \
	'{ _type: $xtype, tst: $tst, lat: $lat, lon: $lon, alt: $alt }' )

set -x

curl \
	-u "$TS_USERNAME:$TS_PASSWORD" \
	-H "Content-Type: application/json" \
	--data "$json" \
	$TS_URL
