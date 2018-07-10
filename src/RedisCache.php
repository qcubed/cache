<?php
/**
 *
 * Part of the QCubed PHP framework.
 *
 * @license MIT
 *
 */

namespace QCubed\Cache;

use Psr\SimpleCache\CacheInterface;
use \Predis;

/**
 * Class RedisCache
 *
 * Caching based on Redis using the `predis` library. Functions used in this class depend on the predis library being
 * available and autoloadable. If you installed QCubed\Cache using composer, you should already have predis library.
 * If you installed QCubed\Cache using some other method, it is your responsibility to ensure that predis is available
 * and loadable using autoloader.
 *
 * In addition to the predis library, you (of course) also need the Redis server running on the machine where you intend
 * to use QCubed\Cache. Ensure that you have the server running at the given port and IP address.
 *
 * NOTE: Redis clusters are not supported.
 *
 * @package QCubed\Cache
 */
class RedisCache extends CacheBase implements CacheInterface
{
    /** @var int */
    protected $ttl = 86400; // default ttl, one day between cache drops

    /** @var null|Predis\Client  */
    protected $conn = null;

    /**
     * RedisCache constructor.
     *
     * @param string $hostname The server where redis is running
     * @param int $port The port on which redis is accepting connections on the host
     */
    public function __construct($hostname = 'localhost', $port = 6379)
    {
        // Create the predis instance
        $this->conn = new Predis\Client([
            'scheme' => 'tcp',
            'host'   => $hostname,
            'port'   => $port
        ]);
    }

    /**
     * Get the object that has the given key from the cache
     *
     * @param string $strKey the key of the object in the cache
     * @param null|mixed $default
     * @return null|mixed
     */
    public function get($strKey, $default = null)
    {
        if ($this->blnUseApcu) {
            $value = apcu_fetch($strKey, $success);
        }
        else {
            $value = apc_fetch($strKey, $success);
        }
        if ($success) {
            return $value;
        }
        else {
            return $default;
        }
    }

    /**
     * Set the object into the cache with the given key
     *
     * @param string $strKey The key to use for the object
     * @param mixed $objValue The object to put in the cache. Can be any serializable object or value.
     * @param int|null|\DateInterval $ttl Number of seconds after which the key has to expire. Zero value means persist
     *                         indefinitely. Negative value means expire immediately.
     *
     * @return bool true on success
     * @throws Exception\InvalidArgument
     */
    public function set($strKey, $objValue, $ttl = null)
    {
        // PSR-16 is for some reason picky about what characters you can have in the key, thinking it will "some day" use certain characters to mean other things.
        $search = strpbrk($strKey, '{}()/\@:');
        if ($search !== false) {
            throw new Exception\InvalidArgument('Invalid character found in the key: ' . $search[0]);
        }

        if ($ttl === null) {
            $ttl = $this->ttl;
        }
        elseif ($ttl instanceof \DateInterval) {
            // convert DateInterval to total seconds
            $reference = new \DateTimeImmutable;
            $endTime = $reference->add($ttl);
            $ttl = $endTime->getTimestamp() - $reference->getTimestamp();
        }

        if($this->blnUseApcu) {
            $blnSuccess = apcu_store ($strKey, $objValue, $ttl);
        } else {
            $blnSuccess = apc_store($strKey, $objValue, $ttl);
        }

        return $blnSuccess;
    }

    /**
     * Delete the object that has the given key from the cache
     * @param string $strKey the key of the object in the cache
     * @return void
     */
    public function delete($strKey)
    {
       if ($this->blnUseApcu) {
           apcu_delete($strKey);
       }
       else {
           apc_delete($strKey);
       }
    }

    /**
     * Invalidate all the objects in the cache
     * @return void
     */
    public function deleteAll()
    {
        $this->clear();
    }

    /**
     * Clear the cache
     *
     * return @void
     */
    public function clear()
    {
        if ($this->blnUseApcu) {
            apcu_clear_cache();
        }
        else {
            apc_clear_cache('user');
        }
    }

    /**
     * Returns an array of values if given an array of keys to search.
     *
     * @param iterable $keys
     * @param mixed $default
     * @return iterable
     * @throws Exception\InvalidArgument
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) && !$keys instanceof \Traversable) {
            throw new Exception\InvalidArgument();
        }

        if ($this->blnUseApcu) {
            $values = apcu_fetch($keys);
        }
        else {
            $values = apc_fetch($keys);
        }
        if ($values !== false) {
            foreach ($keys as $key) {
                if (!isset($values[$key])) {
                    $values[$key] = $default;
                }
            }
            return $values;
        }
        else {
            return []; // some way of showing an error
        }
    }

    /**
     * @param iterable $values Collection of key=>value pairs
     * @param int|null|\DateInterval $ttl Number of seconds after which the key has to expire. Zero value means persist
     *                         indefinitely. Negative value means expire immediately.
     * @return bool
     * @throws \Exception
     */
    public function setMultiple($values, $ttl = null){
        if ($ttl === null) {
            $ttl = $this->ttl;
        }
        elseif ($ttl instanceof \DateInterval) {
            // convert DateInterval to total seconds
            $reference = new \DateTimeImmutable;
            $endTime = $reference->add($ttl);
            $ttl = $endTime->getTimestamp() - $reference->getTimestamp();
        }

        if($this->blnUseApcu) {
            $blnSuccess = apcu_store ($values, null, $ttl);
        } else {
            $blnSuccess = apc_store($values, null, $ttl);
        }

        return $blnSuccess;
    }

    /**
     * Deletes multiple keys at once.
     *
     * @param iterable $keys
     * @return void
     * @throws Exception\InvalidArgument
     */
    public function deleteMultiple($keys) {
        if (!is_array($keys) && !$keys instanceof \Traversable) {
            throw new Exception\InvalidArgument();
        }

        if ($this->blnUseApcu) {
            apcu_delete($keys);
        }
        else {
            apc_delete($keys);
        }
    }

    /**
     * Return true if the given key exists. Do not rely on this to query the value after using this in a multi-user environment,
     * because the value might get deleted before asking for the value. However, this could be useful if you are setting keys
     * to boolean values.
     *
     * @param string $key
     * @return bool
     */
    public function has($key) {
        if ($this->blnUseApcu) {
            return apcu_exists($key);
        }
        else {
            return apc_exists($key);
        }
    }

    /**
     * TODO: Add additional utility functions APC has which are not part of the PSR standard.
     */
}
