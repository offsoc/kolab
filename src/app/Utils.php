<?php

namespace App;

use Ramsey\Uuid\Uuid;

/**
 * Small utility functions for App.
 */
class Utils
{
    /**
     * Returns a UUID in the form of an integer.
     *
     * @return integer
     */
    public static function uuidInt()
    {
        $hex = Uuid::uuid4();
        $bin = pack('h*', str_replace('-', '', $hex));
        $ids = unpack('L', $bin);
        $id = array_shift($ids);

        return $id;
    }

    /**
     * Returns a UUID in the form of a string.
     *
     * @return string
     */
    public static function uuidStr()
    {
        return (string) Uuid::uuid4();
    }
}
