<?php

namespace Plasticode\DI\Tests;

use PHPUnit\Framework\TestCase;
use Plasticode\DI\Autowirer;
use Plasticode\DI\Containers\AutowiringContainer;
use Plasticode\DI\ParamResolvers\UntypedContainerParamResolver;
use Plasticode\DI\Tests\Classes\Linker;
use Plasticode\DI\Tests\Classes\Session;
use Plasticode\DI\Tests\Classes\SettingsProvider;
use Plasticode\DI\Tests\Factories\SessionFactory;
use Plasticode\DI\Tests\Interfaces\LinkerInterface;
use Plasticode\DI\Tests\Interfaces\SessionInterface;
use Plasticode\DI\Tests\Interfaces\SettingsProviderInterface;
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

    /**
     * Creates a container with the provided mappings.
     */
    private function createContainer(?array $map = null): AutowiringContainer
    {
        return new AutowiringContainer($this->autowirer, $map);
    }

    /**
     * Tests that an "empty" container successfully resolves:
     * - ContainerInterface
     * - Autowirer
     */
    public function testAutowireDefaults(): void
    {
        $container = $this->createContainer();

        $this->assertSame($container, $container->get(ContainerInterface::class));
        $this->assertSame($this->autowirer, $container->get(Autowirer::class));
    }

    /**
     * Tests that if something cannot be autowired:
     * - `has()` returns false
     * - `get()` throws an exception
     */
    public function testAutowireFails(): void
    {
        $container = $this->createContainer();

        $this->assertFalse(
            $container->has(SettingsProviderInterface::class)
        );

        $this->expectException(ContainerExceptionInterface::class);

        $container->get(SettingsProviderInterface::class);
    }

    /**
     * Tests the simple mapping `interface` -> `class`.
     *
     * SettingsProviderInterface -(maps to)-> SettingsProvider
     */
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

    /**
     * Tests the dependency injection of a class into another class.
     *
     * LinkerInterface -(maps to)-> Linker
     * SettingsProviderInterface -(is injected into)-> Linker
     * SettingsProviderInterface -(maps to)-> SettingsProvider
     *
     * As a result:
     *
     * SettingsProvider -(is injected into)-> Linker
     */
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

        $settingsProvider = $linker->settingsProvider();

        $this->assertInstanceOf(SettingsProviderInterface::class, $settingsProvider);
        $this->assertInstanceOf(SettingsProvider::class, $settingsProvider);
    }

    /**
     * Tests the class instantiation using a factory (an invokable class).
     *
     * SessionInterface -(maps to)-> SessionFactory
     * SessionFactory -(makes)-> Session
     * SettingsProviderInterface -(is injected into)-> SessionFactory
     * SettingsProviderInterface -(maps to)-> SettingsProvider
     *
     * As a result:
     *
     * SettingsProvider -(is injected into)-> Session
     */
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

        $settingsProvider = $session->settingsProvider();

        $this->assertInstanceOf(SettingsProviderInterface::class, $settingsProvider);
        $this->assertInstanceOf(SettingsProvider::class, $settingsProvider);
    }

    /**
     * Tests the class instantiation using a function (callable factory).
     *
     * This mapping is equivalent to the standalone factory, but the class
     * creation is made inline.
     *
     * SessionInterface -(maps to)-> function
     * function -(makes)-> Session
     * SettingsProviderInterface -(is injected into)-> function
     * SettingsProviderInterface -(maps to)-> SettingsProvider
     *
     * As a result:
     *
     * SettingsProvider -(is injected into)-> Session
     */
    public function testAutowireFunctionFactory(): void
    {
        $container = $this->createContainer([
            SettingsProviderInterface::class => SettingsProvider::class,
            SessionInterface::class =>
                fn (SettingsProviderInterface $sp) => new Session($sp),
        ]);

        $this->assertTrue(
            $container->has(SessionInterface::class)
        );

        $session = $container->get(SessionInterface::class);

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertInstanceOf(Session::class, $session);

        $settingsProvider = $session->settingsProvider();

        $this->assertInstanceOf(SettingsProviderInterface::class, $settingsProvider);
        $this->assertInstanceOf(SettingsProvider::class, $settingsProvider);
    }

    /**
     * This test checks that the untyped parameter can be resolver using
     * a custom resolver.
     *
     * SettingsProviderInterface -(maps to)-> function with untyped param
     * function -(resolves)-> aaa
     * aaa -(maps to)-> SettingsProvider
     *
     * As a result:
     *
     * SettingsProviderInterface -(maps to)-> SettingsProvider
     */
    public function testParamResolver(): void
    {
        // add a resolver for an untyped `$container` param
        $this->autowirer->withUntypedParamResolver(
            new UntypedContainerParamResolver()
        );

        $outerContainer = $this->createContainer([
            SettingsProviderInterface::class => fn ($container) => $container->get('aaa'),
            'aaa' => SettingsProvider::class,
        ]);

        $settingsProvider = $outerContainer->get(SettingsProviderInterface::class);

        $this->assertInstanceOf(SettingsProviderInterface::class, $settingsProvider);
        $this->assertInstanceOf(SettingsProvider::class, $settingsProvider);
    }

    /**
     * Tests that an aliasing works.
     *
     * Usually it's used for interface1 -> interface2 mapping.
     *
     * aaa -(maps to)-> stdClass
     * bbb -(is aliased to)-> aaa
     * ccc -(resolves as)-> bbb
     *
     * As a result:
     *
     * aaa, bbb and ccc are resolved as the same object.
     */
    public function testAlias(): void
    {
        $container = $this->createContainer([
            'aaa' => new stdClass(),
            'bbb' => 'aaa',
            'ccc' => fn (ContainerInterface $c) => $c->get('bbb'), // equivalent to 'ccc' => 'bbb'
        ]);

        $aaa = $container->get('aaa');
        $bbb = $container->get('bbb');
        $ccc = $container->get('ccc');

        // all keys are resolved as the same object
        $this->assertSame($aaa, $bbb);
        $this->assertSame($bbb, $ccc);
        $this->assertSame($ccc, $aaa);
    }
}
