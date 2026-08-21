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
 * The /_dev/screenshots prefix lives on the controller, because the host's dev
 * firewall pattern has to match it anyway.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../src/Controller/', 'attribute');
};
