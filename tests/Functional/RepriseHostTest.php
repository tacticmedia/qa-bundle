<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Functional;

use Symfony\Reprise\RepriseBundle;
use TacticMedia\QaBundle\Tests\Fixtures\reprise\RepriseKernel;

final class RepriseHostTest extends BundlerHostTestCase
{
    protected function setUp(): void
    {
        if (!class_exists(RepriseBundle::class)) {
            self::markTestSkipped('symfony/reprise needs PHP 8.4 and Symfony 7.4, so it is not in require-dev.');
        }

        parent::setUp();
    }

    protected static function getKernelClass(): string
    {
        return RepriseKernel::class;
    }

    protected function fixtureProjectDirectory(): string
    {
        return \dirname(__DIR__).'/Fixtures/reprise';
    }
}
