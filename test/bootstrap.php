<?php

$autoload_file = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload_file)) {
  throw new RuntimeException('Install dependencies to run the test suite.');
}
require $autoload_file;

$loader = new \Composer\Autoload\ClassLoader();
$loader->add('Acquia\Zendesk\Test', 'test');
$loader->register();

