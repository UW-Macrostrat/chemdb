<?php

require '../vendor/autoload.php';
require 'config/database.php';

// Configure Doctrine Cli
// Normally these are arguments to the cli tasks but if they are set here the arguments will be auto-filled
$config = array(
    'data_fixtures_path'  =>  dirname(__FILE__) . '/fixtures',
    'models_path'         =>  dirname(__FILE__) . '/models',
    'migrations_path'     =>  dirname(__FILE__) . '/migrations',
    'sql_path'            =>  dirname(__FILE__) . '/sql',
    'yaml_schema_path'    =>  dirname(__FILE__) . '/schema',
);

$cli = new Doctrine_Cli($config);
$cli->run($_SERVER['argv']);
