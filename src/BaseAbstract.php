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
    protected $_mainStr = '';

    /**
     * @var string
     */
    private $logElement = '';

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
     * @param string $string
     * @return string
     */
    protected function elementWrap(string $string = '')
    {
        $logElement = $this->logElement;
        if (empty($logElement))
            return $string;
        return '<' . $logElement . '>' . $string . '</' . $logElement . '>';
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
        $string = ucfirst($this->actions[$this->getCaller()]['present']) . ' ' . $this->mainStr() . '.';
        $string = $this->elementWrap($string);
        $this->log($string, 'info');
    }

    /**
     * @param string $error
     * @param string $type
     * @return bool
     */
    protected function logError($error = '', $type = 'error')
    {
        $string = sprintf("Can't %s %s. %s", $this->actions[$this->getCaller()]['action'], $this->mainStr(), $error);
        $string = $this->elementWrap($string);
        $this->log($string, $type);
        return false;
    }

    /**
     * @param bool $success
     * @return bool|null
     */
    protected function logFinish($success = false)
    {
        $action = $this->getCaller();
        if (!empty($action)) {
            if (!empty($success)) {
                $string = sprintf('Successfully %s %s.', $this->actions[$this->getCaller()]['past'], $this->mainStr());
                $messageType = 'success';
                $return = true;
            } else {
                $string = sprintf('Failed to %s %s.', $this->getCaller(), $this->mainStr());
                $messageType = 'error';
                $return = false;
            }
            $string = $this->elementWrap($string);
            $this->log($string, $messageType);
            return $return;
        }
        return null;
    }
}