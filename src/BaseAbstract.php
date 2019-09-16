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
     * @var string
     */
    protected $logElement = '';

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
        $i = 0;
        foreach ($dbt as $function) {
            if ($i == 5)
                break;
            $caller = $function['function'] ?? null;
            try {
                $reflection = new \ReflectionMethod($this, $caller);
                if ($reflection->isPublic()) {
                    $caller = explode('\\', $caller);
                    return end($caller);
                }
            } catch (\Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
            $i++;
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
        $caller = $this->getCaller() ?? 'missing action string';
        $action = !empty($caller) ? $this->actions[$caller]['action'] : 'missing action string';
        $string = sprintf("Can't %s %s.", $action, $this->mainStr());
        $string = $this->elementWrap($string) . ' ' . $error;
        $this->log($string, $type);
        return false;
    }

    /**
     * @param bool $success
     * @param string $message
     * @return bool|null
     */
    protected function logFinish(bool $success = false, string $message = '')
    {
        if (!empty($this->getCaller())) {
            $string = $this->getFinishStr($success);
            $string .= !empty($message) ? '<p>' . $message . '</p>' : '';
            $messageType = $success ? 'success' : 'error';
            $this->log($string, $messageType);
            return $success;
        }
        return null;
    }

    /**
     * @param $success
     * @return string
     */
    protected function getFinishStr(bool $success = false)
    {
        $string = $success ? 'Successfully' : 'Failed to';
        return $this->elementWrap($string . ' ' . $this->actions[$this->getCaller()]['action'] . ' ' . $this->mainStr());
    }

}