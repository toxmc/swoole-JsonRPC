<?php

/**
 * 启动 php index.php
 */

include 'Jsonrpc/autoload.php';

define('BASEDIR',__DIR__);

spl_autoload_register('autoload');
$run = new \Jsonrpc\Server();