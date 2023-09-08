<?php

namespace Plasticode\DI\Tests\Factories;

use Plasticode\DI\Tests\Classes\Dependant;
use Plasticode\DI\Tests\Interfaces\DependantInterface;
use Plasticode\DI\Tests\Interfaces\TerminusInterface;

class DependantFactory
{
    public function __invoke(TerminusInterface $terminus): DependantInterface
    {
        return new Dependant($terminus);
    }
}
