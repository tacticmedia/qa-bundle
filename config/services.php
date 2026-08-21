<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TacticMedia\QaBundle\Controller\ScreenshotReviewController;
use TacticMedia\QaBundle\Review\FeedbackStore;
use TacticMedia\QaBundle\Review\ScreenshotCatalog;
use TacticMedia\QaBundle\Review\SelectionCropper;
use TacticMedia\QaBundle\Review\SelectionResolver;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(ScreenshotCatalog::class)
        ->arg('$root', param('qa.screenshots_dir'))
        ->arg('$projectDirectory', param('kernel.project_dir'));

    $services->set(FeedbackStore::class)
        ->arg('$reviewDirectory', param('qa.review_dir'));

    $services->set(SelectionCropper::class)
        ->arg('$reviewDirectory', param('qa.review_dir'))
        ->arg('$projectDirectory', param('kernel.project_dir'));

    $services->set(SelectionResolver::class);

    $services->set(ScreenshotReviewController::class);
};
