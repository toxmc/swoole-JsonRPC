<?php

/**
 * 启动 php index.php
 * @author smalleyes
 */
include 'Jsonrpc/autoload.php';
define('BASEDIR',__DIR__);
spl_autoload_register('autoload');
new \Jsonrpc\Server();