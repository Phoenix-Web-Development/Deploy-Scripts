<?php

namespace Phoenix;

use Exception;
use ReflectionMethod;

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
     * @return bool|mixed
     */
    protected function getCaller()
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        array_shift($dbt);
        $i = 0;
        foreach ($dbt as $function) {
            if ($i === 5)
                break;
            $caller = $function['function'] ?? null;
            try {
                $reflection = new ReflectionMethod($this, $caller);
                if ($reflection->isPublic()) {
                    $caller = explode('\\', $caller);
                    return end($caller);
                }
            } catch (Exception $e) {
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
    protected function elementWrap(string $string = ''): string
    {
        $logElement = $this->logElement;
        if (empty($logElement))
            return $string;
        return '<' . $logElement . '>' . $string . '</' . $logElement . '>';
    }

    /**
     * @return string
     */
    protected function mainStr(): string
    {
        return "I probably shouldn't have been called";
    }

    /**
     *
     */
    protected function logStart(): void
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
    protected function logError($error = '', $type = 'error'): bool
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
    protected function logFinish(bool $success = false, string $message = ''): bool
    {
        $string = $this->elementWrap($this->getFinishStr($success));
        $string .= !empty($message) ? '<p>' . $message . '</p>' : '';
        $messageType = $success ? 'success' : 'error';
        $this->log($string, $messageType);
        return $success;
    }

    /**
     * @param $success
     * @return string
     */
    protected function getFinishStr(bool $success = false): string
    {
        if ($success) {
            $string = 'Successfully';
            $tense = 'past';

        } else {
            $string = 'Failed to';
            $tense = 'action';
        }
        $action = $this->getCaller();
        $action = $this->actions[$action][$tense] ?? $action ?? '<strong>Unknown Action</strong>';
        return $string . ' ' . $action . ' ' . $this->mainStr();
    }
}