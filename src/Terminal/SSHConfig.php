<?php

namespace Phoenix\Terminal;

/**
 * Class SSHConfig
 * @package Phoenix\Terminal
 */
class SSHConfig extends AbstractTerminal
{
    /**
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
        int $port = 22)
    {
        $this->mainStr($host, $hostname, $key_name, $user, $port);
        $this->logStart();
        if ($this->ssh->file_exists($this->filepath()))
            $config_before = $this->ssh->get($this->filepath());
        else {
            $config_before = '';
            $this->ssh->touch($this->filepath());
        }
        if ($this->ssh->fileperms($this->filepath()) != '0600')
            $this->ssh->chmod(0600, $this->filepath());
        if ($this->check($host))
            return $this->logError(sprintf("Config entry for <strong>%s</strong> already exists.", $host));
        $output = $this->exec('echo -e "Host ' . $host . '\n  Hostname ' . $hostname . '\n  User ' . $user
            . '\n  IdentityFile ~/.ssh/' . $key_name . '\n  Port ' . $port . '" >> ' . $this->filepath() . ';');
        $config_after = $this->ssh->get($this->filepath());
        if ($config_before == $config_after)
            return $this->logFinish("Config file is unchanged after attempting to add to it. " . $output, false);
        $success = $this->check($host) ? true : false;
        return $this->logFinish($output, $success);
    }

    /**
     * @param string $host
     * @return bool
     */
    public function delete(string $host = '')
    {
        $this->mainStr($host);
        $this->logStart();
        if (!$this->ssh->file_exists($this->filepath()))
            return $this->logError(sprintf("Config file doesn't exist at <strong>%s</strong>.", $this->filepath()));
        if (!$this->check($host))
            return $this->logError(sprintf("Config entry for <strong>%s</strong> doesn't exist.", $host));

        $config_before = $this->ssh->get($this->filepath());

        $output = $this->exec('sed "s/^Host/\n&/" ' . $this->filepath() . ' | sed "/^Host "' . $host
            . '"$/,/^$/d;/^$/d" > ' . $this->filepath() . '-dummy; mv ' . $this->filepath() . '-dummy ' . $this->filepath() . ';');
        $config_after = $this->ssh->get($this->filepath());
        if (strpos($output, "unterminated `s' command") !== false)
            return $this->logError($output);
        if ($config_before == $config_after)
            return $this->logFinish("Config file is unchanged after attempting to delete from it. " . $output, false);
        $success = !$this->check($host) ? true : false;
        return $this->logFinish($output, $success);
    }

    /**
     * @param string $host
     * @return bool
     */
    public function check(string $host = '')
    {
        if (!$this->ssh->file_exists($this->filepath()))
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
                     int $port = 22)
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $host = !empty($host) ? sprintf(' for host named <strong>%s</strong>', $host) : '';
        $hostname = !empty($hostname) ? sprintf(' with hostname <strong>%s</strong>', $hostname) : '';
        $key_name = !empty($key_name) ? sprintf(' and key named <strong>%s</strong>', $key_name) : '';
        $user = !empty($user) ? sprintf(' and user <strong>%s</strong>', $user) : '';
        $port = !empty($port) ? sprintf(' and port <strong>%s</strong>', $port) : '';
        return $this->_mainStr = sprintf("%s environment SSH config%s%s%s%s%s", $this->environment, $host, $hostname, $key_name, $user, $port);
    }

    protected function filepath()
    {
        return self::trailing_slash($this->client->root) . '.ssh/config';
    }
}