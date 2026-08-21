<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Tests\Functional;

use TacticMedia\QaBundle\Tests\Fixtures\encore\EncoreKernel;

final class EncoreHostTest extends BundlerHostTestCase
{
    protected static function getKernelClass(): string
    {
        return EncoreKernel::class;
    }

    protected function fixtureProjectDirectory(): string
    {
        return \dirname(__DIR__).'/Fixtures/encore';
    }
}
