<?php

// sets up the timezone array

$arrTimezone = array();
$timezone_identifiers = DateTimeZone::listIdentifiers();
for ($i=0; $i < count($timezone_identifiers); $i++) {
	date_default_timezone_set($timezone_identifiers[$i]);
	$date = date('P');
    $arrTimezone[$timezone_identifiers[$i]] = str_replace(":","",$date);
}
?>