<?php

namespace Phoenix;

use Phoenix\Base;

/**
 * @property string $mainStr
 *
 * Class BaseAbstract
 * @package Phoenix
 */
class BaseAbstract extends Base
{
    /**
     * @var
     */
    protected $_mainStr;

    /**
     * BaseAbstract constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return bool|mixed
     */
    protected function getCaller()
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        array_shift($dbt);
        foreach ($dbt as $function) {
            $caller = isset($function['function']) ? $function['function'] : null;
            try {
                $reflection = new \ReflectionMethod($this, $caller);
                if ($reflection->isPublic()) {
                    $caller = explode('\\', $caller);
                    return end($caller);
                }
            } catch (\Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
        }
        return false;
    }

    /**
     * @return string
     */
    protected function mainStr()
    {
        return "I probably shouldn't have been called";
    }

    /**
     *
     */
    protected function logStart()
    {
        $this->log(ucfirst($this->actions[$this->getCaller()]['present']) . ' ' . $this->mainStr() . '.', 'info');
    }

    /**
     * @param string $error
     * @param string $type
     * @return bool
     */
    protected function logError($error = '', $type = 'error')
    {
        $this->log(sprintf("Can't %s %s. %s", $this->actions[$this->getCaller()]['action'], $this->mainStr(), $error), $type);
        return false;
    }
}