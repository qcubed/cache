<?php
/**
 *
 * Part of the QCubed PHP framework.
 *
 * @license MIT
 *
 */

namespace QCubed\Cache;

abstract class CacheBase
{
    /**
     * Create a key appropriate for this cache provider
     * @return string the key
     */
    public function createKey(/* ... */)
    {
        $objArgsArray = array();
        $arg_list = func_get_args();

        array_walk_recursive($arg_list, function ($val, $index) use (&$objArgsArray) {
            $objArgsArray[] = $val;
        });

        return implode("~", $objArgsArray);
    }

    /**
     * Create a key from an array of values
     *
     * @param $a
     * @return string
     */
    public function createKeyArray($a)
    {
        return implode("~", $a);
    }

    /**
     * Validates the key and ensures that it follows the PSR 16 standards
     *
     * @param $key string
     * @return bool True when key passes checks
     * @throws Exception\InvalidArgument
     */
    public static function validateKey($key)
    {
        if (strlen($key) == 0) {
            throw new Exception\InvalidArgument ('Invalid key: key length is 0');
        }

        if (preg_match('/[{}()\/\\\@:]/', $key)) {
            throw new Exception\InvalidArgument ('Invalid key: key cannot contain any character from {}()/\@: ');
        }

        return true;
    }
}
