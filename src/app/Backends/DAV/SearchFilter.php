<?php

namespace App\Backends\DAV;

use App\Backends\DAV;

class SearchFilter
{
    public $filters = [];

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Create string representation of the search
     *
     * @return string
     */
    public function __toString()
    {
        $filter = '<c:filter>';

        foreach ($this->filters as $sub_filter) {
            $filter .= (string) $sub_filter;
        }

        $filter .= '</c:filter>';

        return $filter;
    }
}
