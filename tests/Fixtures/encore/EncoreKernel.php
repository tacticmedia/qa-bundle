<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Fixtures\encore;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;
use TacticMedia\QaBundle\TacticMediaQaBundle;

/**
 * A Webpack Encore host with no override of the review layout.
 */
final class EncoreKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new WebpackEncoreBundle();
        yield new TacticMediaQaBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'qa-bundle',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['handler_id' => null, 'storage_factory_id' => 'session.storage.factory.mock_file'],
            'assets' => true,
        ]);

        $container->extension('twig', ['strict_variables' => true]);

        $container->extension('webpack_encore', [
            'output_path' => '%kernel.project_dir%/public/build',
        ]);

        $container->services()->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TacticMediaQaBundle/config/routes.php');
    }
}
