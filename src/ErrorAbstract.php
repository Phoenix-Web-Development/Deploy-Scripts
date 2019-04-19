<?php

namespace Phoenix;

//use

class ErrorAbstract extends BaseAbstract
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