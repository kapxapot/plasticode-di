<?php

namespace Plasticode\DI\Tests;

use PHPUnit\Framework\TestCase;
use Plasticode\DI\Autowirer;
use Plasticode\DI\Containers\AutowiringContainer;
use Plasticode\DI\ParamResolvers\UntypedContainerParamResolver;
use Plasticode\DI\Tests\Classes\Linker;
use Plasticode\DI\Tests\Classes\LinkerInterface;
use Plasticode\DI\Tests\Classes\Session;
use Plasticode\DI\Tests\Classes\SessionFactory;
use Plasticode\DI\Tests\Classes\SessionInterface;
use Plasticode\DI\Tests\Classes\SettingsProvider;
use Plasticode\DI\Tests\Classes\SettingsProviderInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use stdClass;

final class AutowiringTest extends TestCase
{
    private Autowirer $autowirer;

    public function setUp(): void
    {
        parent::setUp();

        $this->autowirer = new Autowirer();
    }

    public function tearDown(): void
    {
        unset($this->autowirer);

        parent::tearDown();
    }

    private function createContainer(?array $map = null): AutowiringContainer
    {
        return new AutowiringContainer($this->autowirer, $map);
    }

    public function testAutowireFails(): void
    {
        $container = $this->createContainer();

        $this->assertFalse(
            $container->has(SettingsProviderInterface::class)
        );

        $this->expectException(ContainerExceptionInterface::class);

        $container->get(SettingsProviderInterface::class);
    }

    public function testAutowireSimple(): void
    {
        $container = $this->createContainer([
            SettingsProviderInterface::class => SettingsProvider::class,
        ]);

        $this->assertTrue(
            $container->has(SettingsProviderInterface::class)
        );

        $settingsProvider = $container->get(
            SettingsProviderInterface::class
        );

        $this->assertInstanceOf(SettingsProviderInterface::class, $settingsProvider);
        $this->assertInstanceOf(SettingsProvider::class, $settingsProvider);
    }

    public function testAutowireDependency(): void
    {
        $container = $this->createContainer([
            LinkerInterface::class => Linker::class,
            SettingsProviderInterface::class => SettingsProvider::class,
        ]);

        $this->assertTrue(
            $container->has(LinkerInterface::class)
        );

        $linker = $container->get(
            LinkerInterface::class
        );

        $this->assertInstanceOf(LinkerInterface::class, $linker);
        $this->assertInstanceOf(Linker::class, $linker);

        $settingsProvider = $container->get(
            SettingsProviderInterface::class
        );

        $this->assertInstanceOf(SettingsProviderInterface::class, $settingsProvider);
        $this->assertInstanceOf(SettingsProvider::class, $settingsProvider);
    }

    public function testAutowireFactory(): void
    {
        $container = $this->createContainer([
            SettingsProviderInterface::class => SettingsProvider::class,
            SessionInterface::class => SessionFactory::class,
        ]);

        $this->assertTrue(
            $container->has(SessionInterface::class)
        );

        $session = $container->get(SessionInterface::class);

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertInstanceOf(Session::class, $session);
    }

    public function testAutowireCallableFactory(): void
    {
        $container = $this->createContainer([
            SettingsProviderInterface::class => SettingsProvider::class,
            SessionInterface::class =>
                fn (ContainerInterface $container) =>
                    new Session($container->get(SettingsProviderInterface::class)),
        ]);

        $this->assertTrue(
            $container->has(SessionInterface::class)
        );

        $session = $container->get(SessionInterface::class);

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertInstanceOf(Session::class, $session);
    }

    public function testAutowireCallable(): void
    {
        // add a resolver for an untyped `$container` param
        $this->autowirer->withUntypedParamResolver(
            new UntypedContainerParamResolver()
        );

        $outerContainer = $this->createContainer([
            'aaa' => SettingsProvider::class,
        ]);

        $callable = fn ($container) => $container->get('aaa');

        $ccc = $this->autowirer->autowireCallable($outerContainer, $callable);

        $this->assertInstanceOf(SettingsProvider::class, $ccc);
    }

    public function testUntypedContainerResolution(): void
    {
        // add a resolver for an untyped `$container` param
        $this->autowirer->withUntypedParamResolver(
            new UntypedContainerParamResolver()
        );

        $outerContainer = $this->createContainer([
            'aaa' => SettingsProvider::class,
            'ccc' => fn ($container) => $container->get('aaa'),
        ]);

        $ccc = $outerContainer->get('ccc');

        $this->assertInstanceOf(SettingsProvider::class, $ccc);
    }

    public function testAliasAndRedirect(): void
    {
        $container = $this->createContainer([
            'aaa' => 'bbb',
            'bbb' => new stdClass(),
            'ccc' => fn (ContainerInterface $c) => $c->get('bbb'),
        ]);

        $ccc = $container->get('ccc');
        $bbb = $container->get('bbb');
        $aaa = $container->get('aaa');

        $this->assertEquals($aaa, $bbb);
        $this->assertEquals($ccc, $bbb);
    }
}
