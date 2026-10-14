<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use TacticMedia\QaBundle\Tests\Fixtures\app\TestKernel;

// As the router of the built-in server, it leaves an existing file to the server.
if ('cli-server' === \PHP_SAPI && is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH))) {
    return false;
}

require_once __DIR__.'/../../../../vendor/autoload.php';

$kernel = new TestKernel($_SERVER['APP_ENV'] ?? 'test', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
