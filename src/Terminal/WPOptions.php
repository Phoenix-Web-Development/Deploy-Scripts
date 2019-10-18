<?php

namespace Phoenix\Terminal;

use Phoenix\PDOWrap;

/**
 * Class WPOptions
 *
 * @package Phoenix\Terminal
 */
class WPOptions extends AbstractTerminal
{

    /**
     * @var string
     */
    protected $logElement = 'h4';

    /**
     * @param array $args
     * @return array|false
     */
    public function getOptions(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $optionValues = [];
        $output = '';
        $success = [];
        foreach ($args['options'] as $optionName => $option) {
            $args['option'] = $option;
            $args['option']['name'] = $optionName ?? '';

            $result = $this->getOption($args);
            $optionValues[$optionName] = $result['option'] ?? null;
            $output .= '<br>' . $result['string'];
            $success[$optionName] = $result['success'];
        }
        $success = !in_array(false, $success, true) ? true : false;
        $this->logFinish($success, $output);
        return $optionValues;
    }

    /**
     * @param array $args
     * @return array|bool
     */
    public function getOption(array $args = [])
    {
        $this->mainStr($args);
        if (!$this->validate($args))
            return false;

        $command = 'wp option get ' . $args['option']['name'];
        $output = $this->exec($command, $args['directory']);
        $doesntExistString = "Could not get '" . $args['option']['name'] . "' option. Does it exist?";

        if (!empty($args['option']['key_path']) && $this->checkWPCLI($output) && stripos($output, $doesntExistString) === false) {
            $pluckCommand = 'wp option pluck ' . $args['option']['name'] . ' ' . $args['option']['key_path'];
            $output = $this->exec($pluckCommand, $args['directory']);
            $command .= '<br>' . $pluckCommand;
        }

        $return['success'] = $this->checkWPCLI($output);

        if (!$return['success']) {
            if (strpos($output, $doesntExistString) !== false) {
                $return['success'] = true;
                $return['string'] = 'Option' . $this->mainStr($args) . ' doesn\'t exist';
            } else {
                $this->logFinish(false, $output, $command);
                $return['string'] = $this->getFinishStr($return['success']);
            }
        } else {
            $return['option'] = $args['option'];
            $return['option']['value'] = $output;
            $return['string'] = $this->getFinishStr($return['success']);
        }
        return $return;
    }

    /**
     * @param array $args
     * @return bool
     */
    public function setOptions(array $args = []): bool
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $output = '';
        $success = [];

        foreach ($args['options'] as $optionName => $option) {
            $args['option'] = $option;
            if (empty($args['option']['name']))
                $args['option']['name'] = $optionName ?? '';
            $result = $this->setOption($args);
            if (empty($result))
                $success[$optionName] = false;
            else {
                if (!empty($result['string']))
                    $output .= '<br>' . $result['string'];
                $success[$optionName] = $result['success'];
            }
        }

        $success = !in_array(false, $success, true) ? true : false;
        return $this->logFinish($success, $output);
    }

    /**
     * @param array $args
     * @return array|bool
     */
    public function setOption(array $args = [])
    {
        $this->mainStr($args);
        if (!$this->validate($args))
            return false;
        if (!isset($args['option']['value']))
            return $this->logError('Option value missing');

        if (!is_numeric($args['option']['value']))
            $args['option']['value'] = '"' . $args['option']['value'] . '"';

        if (!empty($args['option']['key_path']))
            $command = 'wp option patch update ' . $args['option']['name'] . ' ' . $args['option']['key_path'] . ' ' . $args['option']['value'];
        else
            $command = 'wp option update ' . $args['option']['name'] . ' ' . $args['option']['value'];
        $output = $this->exec($command, $args['directory']);

        $return['success'] = $this->checkWPCLI($output, true);
        $return['string'] = $this->getFinishStr($return['success']);
        $this->logFinish($return['success'], $output, $command);
        return $return;
    }

    /**
     * @param array $args
     * @return bool
     */
    protected function validate(array $args = []): bool
    {
        if (empty($args['directory']))
            return $this->logError('File directory missing from args.');
        $args['directory'] = self::trailing_slash($args['directory']);
        if ($this->is_dir($args['directory']) === false) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $args['directory']));
        }

        $action = $this->getCaller();

        if (empty($args['options']) && in_array($action, array('getOptions', 'setOptions')))
            return $this->logError('Options missing from args.');
        elseif (in_array($action, array('getOption', 'setOption'))) {
            if (empty($args['option']))
                return $this->logError('Option array missing from args');
            if (empty($args['option']['name']))
                return $this->logError('Option name missing from args');
        }

        if (!$this->client->WPCLI()->check())
            return $this->logError('WP CLI missing.');
        if (!$this->client->WP()->check($args))
            return $this->logError(sprintf('WordPress not installed at <strong>%s</strong>.', $args['directory']));
        return true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected
    function mainStr(array $args = []): string
    {
        if (!empty($this->_mainStr) && func_num_args() === 0)
            return $this->_mainStr;
        $action = $this->getCaller();

        if (in_array($action, array('setOption', 'getOption'))) {
            $optionStr = '';
            if (!empty($args['option']['name']))
                $optionStr .= ' named "<strong>' . $args['option']['name'] . '</strong>"';
            if (!empty($args['option']['key_path']))
                $optionStr .= ' with key path "<strong>' . $args['option']['key_path'] . '</strong>"';
            if (!empty($args['option']['value']) && stripos($action, 'setOption') !== false)
                $optionStr .= ' with value "<strong>' . $args['option']['value'] . '</strong>"';
            return $this->_mainStr = $optionStr;
        }

        $dirStr = !empty($args['directory']) && stripos($action, 'options') !== false ? ' in directory <strong>' . $args['directory'] . '</strong>' : '';
        return $this->_mainStr = sprintf(' for %s environment WordPress%s', $this->environ, $dirStr);
    }
}