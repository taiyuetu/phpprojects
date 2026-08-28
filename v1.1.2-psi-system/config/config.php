<?php
/**
 * Central configuration file.
 * Switch DB_DRIVER to 'mysql' for production; 'sqlite' works out of the box
 * for local development / evaluation with zero setup.
 */
return [
    'app_name'   => 'PSI System — Purchase Sales Inventory',
    'debug'      => true, // show detailed errors; set false in production
    'base_url'   => '/', // change if hosted in a sub-folder
    'db' => [
        'driver'   => 'sqlite',                     // 'sqlite' | 'mysql'
        'sqlite_path' => __DIR__ . '/../database/database.sqlite',

        // Used only when driver = mysql
        'host'     => '127.0.0.1',
        'name'     => 'psi_system',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    'session_name' => 'psi_session',
];
