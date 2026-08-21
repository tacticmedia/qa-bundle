<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Fixtures\reprise;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Reprise\RepriseBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use TacticMedia\QaBundle\TacticMediaQaBundle;

/**
 * A Symfony Reprise host, the Vite and Rsbuild counterpart of {@see \TacticMedia\QaBundle\Tests\Fixtures\encore\EncoreKernel}.
 * symfony/reprise needs PHP 8.4 and Symfony 7.4, so it cannot sit in require-dev and this
 * class only loads where CI installed it.
 */
final class RepriseKernel extends Kernel
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
        yield new RepriseBundle();
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
            'asset_mapper' => ['enabled' => false],
        ]);

        $container->extension('twig', ['strict_variables' => true]);

        $container->extension('reprise', [
            'output_path' => '%kernel.project_dir%/public/build',
        ]);

        $container->services()->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TacticMediaQaBundle/config/routes.php');
    }
}
