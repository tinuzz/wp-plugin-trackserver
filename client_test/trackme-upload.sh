#!/bin/zsh
mydir=$(dirname $0)

. ${mydir}/functions.sh

initialize

d=$(date '+%Y-%m-%d %H')

tn="Trackserver client test $d"
do=$trackserver_ts
sp=4.8
ang=117

set -x

curl -G \
	--data "a=upload" \
	--data-urlencode "tn=$tn" \
	--data-urlencode "do=$do" \
	--data-urlencode "lat=$latitude" \
	--data-urlencode "long=$longitude" \
	--data-urlencode "alt=$altitude" \
	--data-urlencode "sp=$sp" \
	--data-urlencode "ang=$ang" \
	$TS_URL/$TS_USERNAME/$TS_PASSWORD/requests.z
