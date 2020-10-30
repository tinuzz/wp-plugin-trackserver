check_env() {

	if test -z $TS_URL || test -z $TS_USERNAME || test -z $TS_PASSWORD
	then
		echo "Environment variable(s) not set."
		exit 1
	fi

}

generate_data() {

	base_lat=51.44
	base_lon=5.48

	random=$(php -r 'echo rand(-999999,999999);')
	offset_lat=$((random * 0.000001))
	random=$(php -r 'echo rand(-999999,999999);')
	offset_lon=$((random * 0.000001))
	random=$(php -r 'echo rand(-100,500);')
	offset_alt=$((random * 0.1))

	latitude=$((base_lat + $offset_lat))
	longitude=$((base_lon + $offset_lon))
	altitude=$offset_alt

	trackserver_ts=$(date '+%Y-%m-%d %H:%M:%S')
	timestamp=$(date '+%s')

}

initialize() {
	check_env
	generate_data
}
