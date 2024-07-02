<?php

namespace App\Backends\DAV;

class Opaque extends CommonObject
{
    protected $content;

    public function __construct($filename)
    {
        $this->content = file_get_contents($filename);
    }

    /**
     * Create string representation of the DAV object
     *
     * @return string
     */
    public function __toString()
    {
        return $this->content;
    }
}
