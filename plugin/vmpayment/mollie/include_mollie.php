<?php

spl_autoload_register(function ($class) {
    // Namespace to directory mappings
    $prefixes = [
        'Mollie\\BusinessLogic\\' => __DIR__ . '/core/BusinessLogic/',
        'Mollie\\Infrastructure\\' => __DIR__ . '/core/Infrastructure/',
        'Mollie\\Payment\\' => __DIR__ . '/src/',
        'Mollie\\Component\\' => JPATH_ADMINISTRATOR . '/components/com_mollie/src/',
    ];

    // Check if class uses one of our namespaces
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Get the relative class name
        $relativeClass = substr($class, $len);

        // Replace namespace separators with directory separators
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    return false;
});
