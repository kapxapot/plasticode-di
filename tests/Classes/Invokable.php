<?php

namespace Plasticode\DI\Tests\Classes;

use Plasticode\DI\Tests\Interfaces\InvokableInterface;
use Plasticode\DI\Tests\Interfaces\TerminusInterface;

/**
 * This class can be invoked like a factory.
 */
class Invokable implements InvokableInterface
{
    public function __invoke(TerminusInterface $terminus): TerminusInterface
    {
        return $terminus;
    }
}
