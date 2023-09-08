<?php

namespace Plasticode\DI\Containers;

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
    protected function resolveCallable(string $id, callable $object): object
    {
        $isKnown = interface_exists($id) || class_exists($id);

        if (!$isKnown) {
            return is_callable($object)
                ? $this->autowireCallable($object)
                : $object;
        }

        try {
            while (!($object instanceof $id) && is_callable($object)) {
                $object = $this->autowireCallable($object);
            }

            if ($object instanceof $id) {
                return $object;
            }

            throw new Exception(
                sprintf(
                    'The callable chain ended up with "%s". "%s" was not found.',
                    is_object($object) ? get_class($object) : gettype($object),
                    $id
                )
            );
        } catch (Exception $ex) {
            $message = sprintf('Error while resolving a callable for "%s".', $id);

            throw new ContainerException($message, 0, $ex);
        }
    }

    /**
     * @return mixed
     */
    protected function autowireCallable(callable $callable)
    {
        return $this->autowirer->autowireCallable($this, $callable);
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
                sprintf('Failed to autowire "%s".', $className),
                0,
                $ex
            );
        }
        catch (Exception $ex) {
            throw new ContainerException(
                sprintf('Error while autowiring "%s".', $className),
                0,
                $ex
            );
        }
    }
}
