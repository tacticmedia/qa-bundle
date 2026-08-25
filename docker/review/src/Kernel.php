<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;
use TacticMedia\QaBundle\Controller\ScreenshotReviewController;
use TacticMedia\QaBundle\TacticMediaQaBundle;

/**
 * The review page and nothing else. Both trees come from the environment, so one
 * built image serves whatever is mounted at /data, and nothing about the paths is
 * baked into the compiled container.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new StimulusBundle();
        yield new TacticMediaQaBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new MountedProjectPathsPass());
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()
            ->set('app.default_secret', 'qa-review')
            ->set('app.default_project_root', '/data')
            ->set('app.default_screenshots_dir', '/data/var/screenshots')
            ->set('app.default_review_dir', '/data/var/review');

        $container->extension('framework', [
            'secret' => '%env(default:app.default_secret:APP_SECRET)%',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['handler_id' => null, 'cookie_secure' => 'auto', 'cookie_samesite' => 'lax'],
            'asset_mapper' => ['paths' => ['assets' => '']],
        ]);

        $container->extension('qa', [
            'screenshots_dir' => '%env(default:app.default_screenshots_dir:QA_SCREENSHOTS_DIR)%',
            'review_dir' => '%env(default:app.default_review_dir:QA_REVIEW_DIR)%',
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TacticMediaQaBundle/config/routes.php');

        $routes->add('home', '/')
            ->controller(RedirectController::class.'::redirectAction')
            ->defaults(['route' => ScreenshotReviewController::ROUTE_INDEX]);
    }
}
