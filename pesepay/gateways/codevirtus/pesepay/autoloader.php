<?php

/**
 * Root namespace for the application
 */
define('ROOT_NAMESPACE', 'Codevirtus\Payments\\');

spl_autoload_register(function ($class) {
    // Remove the root namespace
    if (substr($class, 0, strlen(ROOT_NAMESPACE)) == ROOT_NAMESPACE) {
        $relative = substr($class, strlen(ROOT_NAMESPACE));
    }

    // Bring in the file
    $filename = __DIR__ . "/src/" . str_replace('\\', '/', $relative) . ".php";

    // Check if the file exists
    if (file_exists($filename)) {
        require_once ($filename);
        if (class_exists($class)) {
            return true;
        }
    }

    return false;
});
