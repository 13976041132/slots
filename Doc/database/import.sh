#!/bin/bash

echo ENV=$PHP_ENV

if [ $PHP_ENV = 'product' ];then
    read -p "This is product environment. Continue? (y/n): " continue
    if [ $continue != 'y' ];then
        exit
    fi
fi

cd `dirname $0`

php configTransform.php
php import.php

if [ $PHP_ENV = 'product' ];then
    sh ../../Scripts/rsync.sh
fi