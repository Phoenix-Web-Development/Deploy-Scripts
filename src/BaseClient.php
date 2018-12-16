<?php

namespace Phoenix;


class BaseClient extends Base
{
    /**
     * BaseClient constructor.
     */
    public function __construct()
    {
        parent::__construct();
        return true;
    }

    /**
     * @param $name
     * @param $args
     * @return bool|Terminal\Gitignore|Terminal\WP|Terminal\WP_CLI|Terminal\WP_DB
     */
    public function __call($name, $args)
    {
        if (method_exists($this, 'api'))
            $api = $this->api($name);
        if (!empty($api))
            return $api;
        $this->log(sprintf("Undefined method <strong>%s</strong> called in client.", $name));
        return false;
    }
}