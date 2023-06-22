<?php

namespace Plasticode\DI\Containers;

use Closure;
use Exception;
use Plasticode\DI\Autowirer;
use Plasticode\DI\Exceptions\ContainerException;
use Plasticode\DI\Exceptions\InvalidConfigurationException;
use Plasticode\DI\Exceptions\NotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class AutowiringContainer extends AggregatingContainer
{
    /** @var array<string, object> */
    private array $resolved;

    private Autowirer $autowirer;

    public function __construct(Autowirer $autowirer, ?array $map = null)
    {
        parent::__construct($map);

        $this->resolved = [
            ContainerInterface::class => $this,
            Autowirer::class => $autowirer,
        ];

        $this->autowirer = $autowirer;
    }

    /**
     * Can get:
     *
     * - [this] -> return this
     * - [resolved] -> return resolved
     * - [undefined] -> try autowire, save to resolved
     * - [defined] => [object] -> save to resolved
     * - [defined] => [string] -> get(value), save to resolved
     */
    public function get($id)
    {
        // [resolved] -> return resolved
        if ($this->isResolved($id)) {
            return $this->getResolved($id);
        }

        // [defined] => ...
        if (parent::has($id)) {
            $value = parent::get($id);

            // - [defined] => [object] -> save to resolved
            if (!is_string($value)) {
                return $this->setResolved($id, $value);
            }

            // - [defined] => [string] -> get(value), save to resolved
            return $this->setResolved($id, $this->get($value));
        }

        // [undefined] -> try autowire, save to resolved
        return $this->setResolved($id, $this->autowire($id));
    }

    public function has($id)
    {
        return parent::has($id)
            || $this->isResolved($id)
            || $this->autowirer->canAutowire($this, $id);
    }

    protected function isResolved(string $id): bool
    {
        return array_key_exists($id, $this->resolved);
    }

    protected function getResolved(string $id): object
    {
        return $this->resolved[$id];
    }

    protected function setResolved(string $id, object $object): object
    {
        $resultObject = $object;

        if (is_callable($object)) {
            $resultObject = $this->resolveCallable($id, $object);
        }

        $this->resolved[$id] = $resultObject;

        return $resultObject;
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function resolveCallable(string $id, callable $object): object
    {
        if (!interface_exists($id) && !class_exists($id)) {
            return $object instanceof Closure
                ? $this->autowirer->autowireCallable($this, $object)
                : $object;
        }

        try {
            while (!($object instanceof $id) && is_callable($object)) {
                $object = $this->autowirer->autowireCallable($this, $object);
            }

            return $object;
        } catch (Exception $ex) {
            $message = sprintf("Error while resolving a callable for \"%s\".", $id);

            throw new ContainerException($message, 0, $ex);
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function autowire(string $className): object
    {
        try {
            return $this->autowirer->autowire($this, $className);
        }
        catch (InvalidConfigurationException $ex) {
            throw new NotFoundException(
                sprintf("Failed to autowire \"%s\".", $className),
                0,
                $ex
            );
        }
        catch (Exception $ex) {
            throw new ContainerException(
                sprintf("Error while autowiring \"%s\".", $className),
                0,
                $ex
            );
        }
    }
}
