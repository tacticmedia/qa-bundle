<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\StimulusBundle\AssetMapper\ControllersMapGenerator;
use Symfony\UX\StimulusBundle\AssetMapper\MappedControllerAsset;

/**
 * The path from the host's controllers.json to a file of this package.
 *
 * StimulusBundle resolves each entry of controllers.json against `assets/package.json`
 * and then against the registered AssetMapper paths. The only registration of
 * `assets/dist` is {@see \TacticMedia\QaBundle\TacticMediaQaBundle::prependExtension()},
 * which runs where the host has the bundle enabled. Where a `main` value, a controller
 * name or that registration moves, the host gets
 * "Could not find an asset mapper path that points to the ... controller".
 */
final class StimulusControllersMapTest extends KernelTestCase
{
    /** The fetch mode comes from the host block, which docs/host-setup.md publishes. */
    private const EXPECTED = [
        'qa-annotate' => ['annotate_controller.js', false],
        'qa-clipboard' => ['clipboard_controller.js', true],
        'qa-confirm' => ['confirm_controller.js', true],
        'qa-anchor-highlight' => ['anchor_highlight_controller.js', true],
    ];

    #[TestDox('Each controller of the host block resolves to a shipped file under the package namespace')]
    public function testTheHostBlockResolvesToTheShippedControllers(): void
    {
        self::bootKernel();

        foreach ($this->controllersMap() as $name => $controller) {
            [$file, $isLazy] = self::EXPECTED[$name];

            self::assertSame('@tacticmedia/qa-bundle/'.$file, $controller->asset->logicalPath);
            self::assertSame(realpath(__DIR__.'/../../assets/dist/'.$file), $controller->asset->sourcePath);
            self::assertSame($isLazy, $controller->isLazy);
        }
    }

    /**
     * @return array<string, MappedControllerAsset>
     */
    private function controllersMap(): array
    {
        $generator = self::getContainer()->get('qa.tests.controllers_map_generator');
        self::assertInstanceOf(ControllersMapGenerator::class, $generator);

        $map = $generator->getControllersMap();
        self::assertSame(array_keys(self::EXPECTED), array_keys($map));

        return $map;
    }
}
