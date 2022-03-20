#!/bin/zsh
mydir=$(dirname $0)

. ${mydir}/functions.sh

initialize

set -x

# Use '-F' to post RFC 2388 style form data (Content-Type: multipart/form-data).
# The field name for the file is arbitrary.
curl -u "$TS_USERNAME:$TS_PASSWORD" -F 'abc=@test.gpx' "$TS_URL"
