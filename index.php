<?php

/*
 * Fallback for shared hosting where the domain points at the project folder
 * and mod_rewrite is off: hand the request to Laravel's real entry point.
 * With mod_rewrite on, the root .htaccess sends requests to public/ instead.
 */
require __DIR__.'/public/index.php';
