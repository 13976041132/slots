#!/bin/bash

if [ $PHP_ENV = 'product' ];then
    rm -f /home/wwwroot/triplewin/App/Admin/View/Compile/*

    echo -e "\n重置opcache[3.239.94.34]"
    curl -u www:au2U8^b4vFmufyWt http://3.239.94.34/tools/ocp_reset.php
fi