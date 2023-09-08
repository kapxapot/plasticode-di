<?php

namespace Plasticode\DI\Tests\Factories;

use Plasticode\DI\Tests\Classes\Invokable;
use Plasticode\DI\Tests\Interfaces\InvokableInterface;

class InvokableFactory
{
    public function __invoke(): InvokableInterface
    {
        return new Invokable();
    }
}
