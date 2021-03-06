<?php

namespace Phoenix\Terminal;

/**
 * Class SSHConfig
 *
 * @package Phoenix\Terminal
 */
class SSHConfig extends AbstractTerminal
{
    /**
     * @var string
     */
    protected $logElement = 'h4';

    /**
     * Adds an entry to the SSH config file
     *
     * @param string $host
     * @param string $hostname
     * @param string $key_name
     * @param string $user
     * @param int $port
     * @return bool
     */
    public function create(
        string $host = '',
        string $hostname = '',
        string $key_name = 'id_rsa',
        string $user = '',
        int $port = 22): bool
    {
        $this->mainStr($host, $hostname, $key_name, $user, $port);
        $this->logStart();
        if (empty($this->filepath()))
            return $this->logError(sprintf("Couldn't get %s environ home directory.", $this->environ));
        if ($this->file_exists($this->filepath()))
            $config_before = $this->get($this->filepath());
        else {
            $config_before = '';
            $this->ssh->touch($this->filepath());
        }
        $perms = substr(decoct($this->ssh->fileperms($this->filepath())), -3, 3);
        if ($perms != 0600) {
            $this->log(sprintf('SSH Config file permissions <strong>%s</strong> are wrong. Setting to 0600.', $perms), 'warning');
            $this->chmod($this->filepath(), 0600);
        }
        if ($this->check($host))
            return $this->logError(sprintf('Config entry for <strong>%s</strong> already exists.', $host), 'warning');
        $output = $this->exec('echo -e "Host ' . $host . '\n  Hostname ' . $hostname . '\n  User ' . $user
            . '\n  IdentityFile ~/.ssh/' . $key_name . '\n  Port ' . $port . '" >> ' . $this->filepath() . ';');
        $config_after = $this->get($this->filepath());
        if ($config_before == $config_after)
            return $this->logFinish(false, 'Config file is unchanged after attempting to add to it. ' . $output);
        $success = $this->check($host) ? true : false;
        return $this->logFinish($success, $output);
    }

    /**
     * @param string $host
     * @return bool
     */
    public function delete(string $host = ''): bool
    {
        $this->mainStr($host);
        $this->logStart();
        if (empty($this->filepath()))
            return $this->logError(sprintf("Couldn't get %s environ home directory.", $this->environ));
        if (!$this->file_exists($this->filepath()))
            return $this->logError(sprintf("Config file doesn't exist at <strong>%s</strong>.", $this->filepath()));
        if (!$this->check($host))
            return $this->logError(sprintf("Config entry for <strong>%s</strong> doesn't exist.", $host), 'warning');

        $config_before = $this->get($this->filepath());

        $output = $this->exec('sed "s/^Host/\n&/" ' . $this->filepath() . ' | sed "/^Host "' . $host
            . '"$/,/^$/d;/^$/d" > ' . $this->filepath() . '-dummy; mv ' . $this->filepath() . '-dummy ' . $this->filepath() . ';');
        $config_after = $this->get($this->filepath());
        if (strpos($output, "unterminated `s' command") !== false)
            return $this->logError($output);
        if ($config_before == $config_after)
            return $this->logFinish(false, 'Config file is unchanged after attempting to delete from it. ' . $output);
        $success = !$this->check($host) ? true : false;
        return $this->logFinish($success, $output);
    }

    /**
     * @param string $host
     * @return bool
     */
    public function check(string $host = ''): bool
    {
        if (!$this->file_exists($this->filepath()))
            return false;
        $config_entry_exists = $this->exec('grep "Host ' . $host . '" ' . $this->filepath());
        return $config_entry_exists = (strlen($config_entry_exists) > 0 && strpos($config_entry_exists, 'Host ' . $host) !== false) ? true : false;
    }

    /**
     * @param string $host
     * @param string $hostname
     * @param string $key_name
     * @param string $user
     * @param int $port
     * @return string
     */
    protected
    function mainStr(string $host = '',
                     string $hostname = '',
                     string $key_name = 'id_rsa',
                     string $user = '',
                     int $port = 22): string
    {
        if (!empty($this->_mainStr) && func_num_args() === 0)
            return $this->_mainStr;
        $host = !empty($host) ? sprintf(' for host named <strong>%s</strong>', $host) : '';
        $hostname = !empty($hostname) ? sprintf(' with hostname <strong>%s</strong>', $hostname) : '';
        $key_name = !empty($key_name) ? sprintf(' and key named <strong>%s</strong>', $key_name) : '';
        $user = !empty($user) ? sprintf(' and user <strong>%s</strong>', $user) : '';
        $port = !empty($port) ? sprintf(' and port <strong>%s</strong>', $port) : '';
        $dirStr = !empty($this->filepath) ? sprintf(' in config file at <strong>%s</strong>', $this->filepath) : '';
        return $this->_mainStr = sprintf('%s environment SSH config%s%s%s%s%s%s', $this->environ, $host, $hostname, $key_name, $user, $port, $dirStr);
    }

    /**
     * @return bool|string
     */
    protected function filepath()
    {
        if ($this->root != false)
            return self::trailing_slash($this->root) . '.ssh/config';
        return false;
    }
}