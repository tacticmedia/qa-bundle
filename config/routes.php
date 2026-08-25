<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import this from the host under `when@dev`:
 *
 *     when@dev:
 *         qa:
 *             resource: '@TacticMediaQaBundle/config/routes.php'
 *             type: php
 *
 * The /_dev/screenshots prefix is on the controller, because the dev firewall
 * pattern of the host must match it.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../src/Controller/', 'attribute');
};
