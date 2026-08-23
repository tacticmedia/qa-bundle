<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TacticMedia\QaBundle\Review\ScreenshotCatalog;
use TacticMedia\QaBundle\Review\SelectionCropper;

/**
 * The agent brief prints paths relative to the project directory, which inside a
 * container is the review application and not the reviewed project. /data stands
 * in for the project root instead, so mounting each tree at the relative path it
 * has on the host makes the brief name paths the host can open.
 *
 * A compiler pass rather than a service override: the bundle's extension
 * registers these definitions while the container compiles, after anything this
 * application declares.
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
