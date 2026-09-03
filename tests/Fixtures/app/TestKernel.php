<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Fixtures\app;

use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Extension\ImportMapExtension;
use Symfony\Bridge\Twig\Extension\ImportMapRuntime;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;
use TacticMedia\QaBundle\TacticMediaQaBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The smallest host that can run the review page. It has no SecurityBundle: the
 * page has no authentication, and `security.csrf.token_manager` is FrameworkBundle's,
 * registered from framework.csrf_protection with symfony/security-csrf installed.
 */
final class TestKernel extends Kernel
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
            'asset_mapper' => ['paths' => ['assets' => '']],
        ]);

        $container->extension('twig', ['strict_variables' => true]);

        $services = $container->services();

        // Keeps the expected 404s out of the test runner's output.
        $services->set('logger', NullLogger::class);

        // TwigBundle loads these two only when ContainerBuilder::willBeAvailable() reports
        // symfony/asset-mapper as a non-dev dependency of the root package. Here it is a dev
        // dependency, so the check reports it as unavailable and importmap() is not registered. A
        // real AssetMapper host requires the package directly and receives them from TwigBundle;
        // this fixture declares them itself to act as that host.
        $services->set('twig.runtime.importmap', ImportMapRuntime::class)
            ->args([service('asset_mapper.importmap.renderer')])
            ->tag('twig.runtime');
        $services->set('twig.extension.importmap', ImportMapExtension::class)
            ->tag('twig.extension');

        // StimulusBundle inlines the generator into its loader compiler, which removes the
        // definition the test container would expose.
        $services->alias('qa.tests.controllers_map_generator', 'stimulus.asset_mapper.controllers_map_generator')
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TacticMediaQaBundle/config/routes.php');
    }
}
