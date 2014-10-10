#!/bin/sh
dir=`dirname $0`
php=/usr/bin/php
while true; do
    $php $dir/server.php
    sleep 5;
done >> $dir/../logs/server.log 2>> $dir/../logs/server.error.log
