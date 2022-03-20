#!/bin/zsh
mydir=$(dirname $0)

. ${mydir}/functions.sh

initialize

set -x

curl -X POST \
	"$TS_URL/$TS_USERNAME/$TS_PASSWORD/?id=12345&timestamp=$timestamp&lat=$latitude&lon=$longitude&speed=0.0&bearing=0.0&altitude=$altitude&accuracy=18.19&batt=69.0"

