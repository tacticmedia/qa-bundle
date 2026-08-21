<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->scalarNode('screenshots_dir')
                ->info('Tree the journeys write, and the review reads.')
                ->defaultValue('%kernel.project_dir%/var/screenshots')
            ->end()
            ->scalarNode('review_dir')
                ->info('Review notes and the crops the prompt points at. Never inside screenshots_dir, which every run empties.')
                ->defaultValue('%kernel.project_dir%/var/review')
            ->end()
        ->end();
};
