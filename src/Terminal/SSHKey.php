<?php

namespace Phoenix\Terminal;

/**
 * Class SSHKey
 * @package Phoenix\Terminal
 */
class SSHKey extends AbstractTerminal
{

    /**
     * @var string
     */
    protected $logElement = 'h4';

    /**
     * @param string $key_name
     * @param string $passphrase
     * @return bool|string
     */
    public function create(string $key_name = 'id_rsa', string $passphrase = '')
    {
        $this->mainStr($key_name);
        $this->logStart();
        if (!$this->validate($key_name))
            return false;
        $filepath = $this->filepath($key_name);
        $private_exists = $this->file_exists($filepath);
        $pub_exists = $this->file_exists($filepath . '.pub');
        if ($private_exists || $pub_exists) {
            if ($private_exists && $pub_exists) {
                $this->log("Can't create " . $this->mainStr() . " Public and private key already exists.", 'info');
                return true;
            }
            return $this->logError("Public or private key already exists.");
        }
        $output = $this->exec('ssh-keygen -q -t rsa -N "' . $passphrase . '" -f ' . $filepath .
            '; cat ' . $filepath . '.pub');

        $success = ($this->file_exists($filepath) && $this->file_exists($filepath . '.pub') && strpos($output, 'ssh-rsa ') !== false) ? true : false;
        $this->logFinish($success, $output);
        if ($success)
            return $output;
        return false;
    }

    /**
     * @param string $key_name
     * @return bool
     */
    public function delete(string $key_name = 'id_rsa')
    {
        $this->mainStr($key_name);
        $this->logStart();
        if (!$this->validate($key_name))
            return false;
        $filepath = $this->filepath($key_name);
        if (!$this->file_exists($filepath) && !$this->file_exists($filepath . '.pub'))
            return $this->logError("Public and private key file doesn't exist.");
        $success = ($this->deleteFile($filepath) && $this->deleteFile($filepath . ' . pub')) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param string $key_name
     * @return bool
     */
    protected function validate($key_name = '')
    {
        if (empty($key_name))
            return $this->logError("Key name function input is missing.");
        if (empty($this->filepath()))
            return $this->logError(sprintf("Couldn't get %s environ home directory.", $this->environment));
        return true;
    }

    /**
     * @param string $key_name
     * @return string
     */
    protected function mainStr($key_name = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $key_name = !empty($key_name) ? ' named <strong>' . $key_name . '</strong>' : '';
        $dirStr = !empty($this->filepath) ? sprintf(" at path <strong>%s</strong>", $this->filepath) : '';
        return $this->_mainStr = sprintf("%s environment SSH key%s%s", $this->environment, $key_name, $dirStr);
    }

    /**
     * @param string $key_name
     * @return bool|string|null
     */
    protected
    function filepath($key_name = '')
    {
        if (empty($key_name))
            return false;
        if (empty($this->root))
            return false;
        return $this->root . '/.ssh/' . $key_name;
    }
}