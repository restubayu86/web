<?php

/**
 * PHP Built-in Server Router
 * This file handles URL rewriting for the PHP built-in development server
 * It allows clean URLs without index.php
 */

// Get the request URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Get the public directory
$public_dir = __DIR__;

// Remove leading slash
$uri = ltrim($uri, '/');

// Check if requesting a real file
if ($uri && file_exists($public_dir . DIRECTORY_SEPARATOR . $uri)) {
    // File exists, serve it
    return false;
}

// Check if requesting a directory
if ($uri && is_dir($public_dir . DIRECTORY_SEPARATOR . $uri)) {
    // Directory exists, return false to let PHP handle it
    return false;
}

// Otherwise, route to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public_dir . DIRECTORY_SEPARATOR . 'index.php';

// Include index.php
require $public_dir . DIRECTORY_SEPARATOR . 'index.php';
