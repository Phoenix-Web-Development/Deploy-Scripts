<?php

namespace Phoenix;

use Phoenix\Base;

class BaseAbstract extends Base
{
    /**
     * @var
     */
    protected $_mainStr;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return bool|mixed
     */
    protected function getCaller()
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
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
     * @param string $output
     * @param bool $success
     * @return bool|null
     */
    protected function logFinish($output = '', $success = false)
    {
        $action = $this->getCaller();
        if (!empty($action)) {
            $output = $this->format_output($output);
            if (!empty($success)) {
                $this->log(sprintf('Successfully %s %s. %s', $this->actions[$this->getCaller()]['past'], $this->mainStr(), $output), 'success');
                return true;
            }
            $this->log(sprintf('Failed to %s %s. %s', $this->getCaller(), $this->mainStr(), $output));
            return false;
        }
        return null;
    }

    /**
     * @param string $error
     * @return bool
     */
    protected function logError($error = '')
    {
        $this->log(sprintf("Can't %s %s. %s", $this->getCaller(), $this->mainStr(), $error));
        return false;
    }
}