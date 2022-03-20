#!/bin/zsh
mydir=$(dirname $0)

. ${mydir}/functions.sh

initialize

d=$(date '+%Y-%m-%d %H')

df=$trackserver_ts

set -x

#a=show&id=12549e02b541c782&db=8&lat=51.4636998&long=5.4707173&df=2022-01-21%2011:00:09

curl -G \
	--data "a=show" \
	--data-urlencode "lat=$latitude" \
	--data-urlencode "long=$longitude" \
	$TS_URL/$TS_USERNAME/$TS_PASSWORD/cloud.z
