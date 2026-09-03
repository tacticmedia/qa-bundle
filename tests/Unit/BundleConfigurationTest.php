<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use TacticMedia\QaBundle\TacticMediaQaBundle;

/**
 * Notes and crops are kept after a journey run empties the screenshot tree. That
 * holds only while the two directories are separate, so the bundle rejects a host
 * configuration in which they are not.
 */
final class BundleConfigurationTest extends TestCase
{
    #[TestDox('The default trees are siblings, and both become parameters')]
    public function testTheDefaultsAreAccepted(): void
    {
        $container = $this->load([]);

        self::assertSame('%kernel.project_dir%/var/screenshots', $container->getParameter('qa.screenshots_dir'));
        self::assertSame('%kernel.project_dir%/var/review', $container->getParameter('qa.review_dir'));
    }

    #[TestDox('A review_dir inside screenshots_dir is rejected before a run can empty it')]
    public function testAReviewDirectoryInsideTheScreenshotTreeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['review_dir' => '%kernel.project_dir%/var/screenshots/review']);
    }

    #[TestDox('A sibling whose name only starts with the screenshot tree is accepted')]
    public function testASiblingSharingAPrefixIsAccepted(): void
    {
        $container = $this->load(['review_dir' => '%kernel.project_dir%/var/screenshots-review']);

        self::assertSame('%kernel.project_dir%/var/screenshots-review', $container->getParameter('qa.review_dir'));
    }

    /**
     * @param array<string, string> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.project_dir' => '/app',
            'kernel.environment' => 'test',
            'kernel.debug' => true,
        ]));

        $extension = (new TacticMediaQaBundle())->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        $extension->load([$config], $container);

        return $container;
    }
}
