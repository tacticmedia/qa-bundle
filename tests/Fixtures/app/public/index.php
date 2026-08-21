<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use TacticMedia\QaBundle\Tests\Fixtures\app\TestKernel;

require_once __DIR__.'/../../../../vendor/autoload.php';

// Debug on: the asset mapper only serves its assets from the dev server in debug.
$kernel = new TestKernel($_SERVER['APP_ENV'] ?? 'test', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
