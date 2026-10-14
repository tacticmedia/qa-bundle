<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Fixtures\app;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use TacticMedia\QaBundle\TacticMediaQaBundle;

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
        ]);

        $container->extension('twig', ['strict_variables' => true]);

        // Keeps the expected 404s out of the test runner's output.
        $container->services()->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TacticMediaQaBundle/config/routes.php');
    }
}
