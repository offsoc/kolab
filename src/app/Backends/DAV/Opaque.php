<?php

namespace App\Backends\DAV;

class Opaque extends CommonObject
{
    protected $content;

    public function __construct($content, $is_file = false)
    {
        if ($is_file) {
            $this->content = file_get_contents($content);
        } else {
            $this->content = $content;
        }
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
