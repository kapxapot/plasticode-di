<?php

namespace Plasticode\DI\Tests\Factories;

class DependantFactoryFactory
{
    public function __invoke(): DependantFactory
    {
        return new DependantFactory();
    }
}
