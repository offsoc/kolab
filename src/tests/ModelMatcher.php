<?php

namespace Tests;

use Mockery\Matcher\MatcherAbstract;

class ModelMatcher extends MatcherAbstract
{
    public function match(&$actual)
    {
        return $this->_expected->is($actual); // @phpstan-ignore-line
    }

    public function __toString()
    {
        return '<Model>';
    }
}
