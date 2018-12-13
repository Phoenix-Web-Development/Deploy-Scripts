<?php

namespace Phoenix\Terminal;


class Error extends AbstractTerminal
{
    /**
     * @param $name
     * @param $args
     * @return bool
     */
    public function __call($name, $args)
    {
        return false;
    }
}