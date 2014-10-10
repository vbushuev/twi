#!/bin/sh
dir=`dirname $0`
php=/usr/bin/php
while true; do
    $php $dir/client.php
    sleep 5;
done >> $dir/../logs/client.log 2>> $dir/../logs/client.error.log
