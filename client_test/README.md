# Trackserver client test scripts

This directory contains some scripts to test Trackserver's request handlers.

All scripts need a server URL and a username. Some need the user's WordPress
password, others, like TrackMe, need a secret access key. These should be set
in environment variables:

* TS_URL
* TS_USERNAME
* TS_PASSWORD
* TS_SECRET
