<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TacticMedia\QaBundle\Review\ScreenshotCatalog;
use TacticMedia\QaBundle\Review\SelectionCropper;

/**
 * The agent brief prints paths relative to the project directory, which inside a
 * container is the review application and not the reviewed project. /data replaces
 * the project root, so when each tree is mounted at the relative path it has on the
 * host, the brief prints paths that the host can open.
 *
 * A compiler pass and not a service override: it replaces one argument of the
 * bundle's definitions and leaves the rest of each service as the bundle declares it.
 */
final class MountedProjectPathsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ([ScreenshotCatalog::class, SelectionCropper::class] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)
                    ->setArgument('$projectDirectory', '%env(default:app.default_project_root:QA_PROJECT_ROOT)%');
            }
        }
    }
}
