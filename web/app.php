<?php

ini_set('display_errors', 1);
error_reporting(-1);

require_once __DIR__.'/../src/App/bootstrap.php';

$app['http_cache']->run();
//$app->run();
