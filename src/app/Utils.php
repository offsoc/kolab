<?php

namespace App;

use Ramsey\Uuid\Uuid;

/**
 * Small utility functions for App.
 */
class Utils
{
    /**
     * Provide all unique combinations of elements in $input, with order and duplicates irrelevant.
     *
     * @param array $input The input array of elements.
     *
     * @return array[]
     */
    public static function powerSet($input)
    {
        $output = [];

        for ($x = 0; $x < count($input); $x++) {
            self::combine($input, $x + 1, 0, array(), 0, $output);
        }

        return $output;
    }

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

    private static function combine($input, $r, $index, $data, $i, &$output)
    {
        $n = count($input);

        // Current cobination is ready
        if ($index == $r) {
            $output[] = array_slice($data, 0, $r);
            return;
        }

        // When no more elements are there to put in data[]
        if ($i >= $n) {
            return;
        }

        // current is included, put next at next location
        $data[$index] = $input[$i];
        self::combine($input, $r, $index + 1, $data, $i + 1, $output);

        // current is excluded, replace it with next (Note that i+1
        // is passed, but index is not changed)
        self::combine($input, $r, $index, $data, $i + 1, $output);
    }
}
