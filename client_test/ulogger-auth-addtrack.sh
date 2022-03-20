#!/bin/zsh
mydir=$(dirname $0)
cookie="ulogger-cookie.txt"

. ${mydir}/functions.sh

initialize

d=$(date '+%Y-%m-%d %H')
tn="ulogger client test $d"

set -x

curl -D - \
  -b "$cookie" \
  -c "$cookie" \
  --data "action=auth" \
  --data "user=${TS_USERNAME}" \
  --data "pass=${TS_PASSWORD}" \
  $TS_URL/client/index.php

curl -D - \
  -b "$cookie" \
  -c "$cookie" \
  --data "action=addtrack" \
  --data-urlencode "track=$tn" \
  $TS_URL/client/index.php
