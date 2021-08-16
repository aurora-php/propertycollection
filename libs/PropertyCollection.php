<?php

declare(strict_types=1);

/*
 * This file is part of the 'octris/propertycollection' package.
 *
 * (c) Harald Lapp <harald@octris.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Octris;

use Octris\PropertyCollection\Exception;
use Psr\Container\ContainerInterface;

/**
 * Property collection allows access to stored values using dot notation.
 *
 * @copyright   copyright (c) 2020-present by Harald Lapp
 * @author      Harald Lapp <harald@octris.org>
 */
class PropertyCollection implements \IteratorAggregate, \JsonSerializable, \Countable, ContainerInterface
{
    /**
     * Data of collection.
     *
     * @var     array
     */
    protected array $data = [];

    /**
     * Access cache.
     * 
     * @var     array
     */
    protected array $cache = [];

    /**
     * Constructor.
     *
     * @param   array          $data            Optional data to initialize collection with.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function __debugInfo(): array
    {
        return [
            'data' => $this->data,
            'cache' => array_keys($this->cache)
        ];
    }

    public function __serialize(): array
    {
        return $this->data;
    }

    public function __unserialize(array $data):  void
    {
        $this->data = $data;
    }

    /**
     * Return array copy of internal data structure.
     *
     * @return  array
     */
    public function getArrayCopy(): array
    {
        return $this->data;
    }

    protected function createSubCollection(array &$data): PropertyCollection
    {
        return new class($data) extends PropertyCollection {
            public function __construct(array &$data)
            {
                $this->data =& $data;
            }
        };
    }

    /**
     * Normalize key - remove duplicate dots, leading and trailing dots and whitespace characters.
     *
     * @param   string
     * @return  string
     */
    protected function normalizeKey(string $key): string
    {
        return preg_replace('/\.{2,}/', '.', trim(preg_replace('/\s/', '', $key), '.'));
    }

    /**
     * Get a value for a key.
     *
     * @param   string      $key
     * @param   mixed       $default
     * @return  mixed
     */
    public function get(string $key, $default = null)
    {
        $key = $this->normalizeKey($key);

        if (!array_key_exists($key, $this->cache)) {
            $parts = explode('.', $key);

            if (strpos($key, '.') !== false) {
                $ret =& $this->data;

                for ($i = 0, $cnt = count($parts); $i < $cnt; ++$i) {
                    if (!is_array($ret) || !array_key_exists($parts[$i], $ret)) {
                        trigger_error('Undefined index "' . $parts[$i] . '" in "' . $key . '".');

                        return $default;
                    } else {
                        $ret =& $ret[$parts[$i]];
                    }
                }
            } elseif (!array_key_exists($key, $this->data)) {
                return $default;
            } else {
                $ret =& $this->data[$key];
            }

            $this->cache[$key] =& $ret;
        }

        if (is_array($this->cache[$key])) {
            return $this->createSubCollection($this->cache[$key]);
        }

        return $this->cache[$key];
    }

    /**
     * Set value for a key.
     *
     * @param   string      $key        Offset to set value at.
     * @param   mixed       $value      Value to set at offset.
     * @throws  Exception\InvalidAccessException
     */
    public function set(string $key, $value): void
    {
        $key = $this->normalizeKey($key);

        if (array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $value;
        } else {
            $parts = explode('.', $key);

            $data =& $this->data;

            for ($i = 0, $cnt = count($parts); $i < $cnt; ++$i) {
                if (!is_array($data)) {
                    throw new Exception\InvalidAccessException(sprintf(
                        'Invalid property access for "%s", property "%s" is not an array.',
                        $key,
                        implode('.', array_slice($parts, 0, $i))
                    ));
                } elseif (!array_key_exists($parts[$i], $data)) {
                    $data[$parts[$i]] = [];
                }

                $data =& $data[$parts[$i]];
            }

            $this->cache[$key] =& $data;
            $data = $value;
        }
    }

    /**
     * Check whether a key exists.
     *
     * @param   string      $key       Offset to check.
     * @return  bool                    Returns true, if offset exists.
     */
    public function has(string $key): bool
    {
        $key = $this->normalizeKey($key);

        if (!($ret = (array_key_exists($key, $this->cache) || array_key_exists($key, $this->data)))) {
            $parts = explode('.', $key);
            $data =& $this->data;

            for ($i = 0, $cnt = count($parts); $i < $cnt; ++$i) {
                if (!($ret = (is_array($data) && array_key_exists($parts[$i], $data)))) {
                    break;
                }

                $data =& $ret[$parts[$i]];
            }
        }

        return $ret;
    }

    /**
     * Unset data in collection at specified offset. Allows access by dot-notation.
     *
     * @param   string      $key       Offset to unset.
     */
    public function unset(string $key): void
    {
        $key = $this->normalizeKey($key);

        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            unset($this->cache[$key]);
        } else {
            $parts = explode('.', $key);
            $data =& $this->data;

            for ($i = 0, $cnt = count($parts); $i < $cnt; ++$i) {
                if (!(is_array($data) && array_key_exists($parts[$i], $data))) {
                    break;
                }

                if ($i == $cnt - 1) {
                    unset($data[$parts[$i]]);
                } else {
                    $data =& $data[$parts[$i]];
                }
            }
        }
    }

    /** IteratorAggregate */

    /**
     * Create iterator.
     *
     * @return  \Generator
     */
    public function getIterator(): \Generator
    {
        return (function () {
            foreach ($this->data as $key => $value) {
                if (is_array($value)) {
                    yield $key => $this->createSubCollection($value);
                } else {
                    yield $key => $value;
                }
            }
        })();
    }

    /** JsonSerializable **/

    /**
     * Gets called when something wants to json-serialize the collection.
     *
     * @return  string                      Json-serialized content of collection.
     */
    public function jsonSerialize(): string
    {
        return json_encode($this->data);
    }

    /** Countable **/

    /**
     * Return number of items in collection.
     *
     * @return  int                         Number of items.
     */
    public function count(): int
    {
        return count($this->data);
    }
}
