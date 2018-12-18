<?php

namespace Phoenix\Terminal;

use \phpseclib\Net\SFTP;

/**
 * Class Htaccess
 * @package Phoenix\Terminal
 */
class Htaccess extends AbstractTerminal
{

    /**
     * @param string $webDir
     * @param bool $www
     * @return bool|null
     */
    public function prepend(string $webDir = '', bool $www = false)
    {
        $this->mainStr($webDir, $www);
        $this->logStart();
        if (!$this->validate($webDir))
            return false;

        if ($www)
            $wwwString = "RewriteCond %{HTTP_HOST} !^www\. [NC]
                RewriteRule ^(.*)$ http://www.%{HTTP_HOST}/$1 [R=301,L]";
        elseif (!$www)
            $wwwString = "RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
            RewriteRule ^(.*)$ http://%1/$1 [R=301,L]";
        $htaccessRules = "<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    <IfModule mod_litespeed.c>
        RewriteRule .* - [E=noabort:1]
    </IfModule>
    " . $wwwString . "          
    RewriteCond %{HTTPS} !on [NC]
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
        ";
        $remoteFile = self::trailing_slash($webDir) . '.htaccess';
        $existingHtaccess = $this->ssh->get($remoteFile);
        if (strpos($existingHtaccess, $htaccessRules) !== false)
            return $this->logError("The .htaccess file already contains new rules.", 'warning');
        $newHtaccessRules = $htaccessRules . $existingHtaccess;

        $success = $this->ssh->put($remoteFile, $newHtaccessRules);
        return $this->logFinish('', $success);
    }

    /**
     * @param string $webDir
     * @return bool
     */
    protected function validate(string $webDir = '')
    {
        if (!$this->ssh->is_dir($webDir)) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $webDir));
        }
        if (!$this->ssh->file_exists(self::trailing_slash($webDir) . '.htaccess')) {
            return $this->logError(sprintf("No .htaccess file in directory <strong>%s</strong>.", $webDir));
        }
        return true;
    }

    /**
     * @param string $webDir
     * @param bool $www
     * @return string
     */
    protected
    function mainStr(string $webDir = '', bool $www = false)
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $webDir = !empty($webDir) ? sprintf(' in directory <strong>%s</strong>', $webDir) : '';
        $www = $www ? ' for www containing URL' : ' for non-www URL';
        return $this->_mainStr = sprintf("%s environment htaccess file%s%s", $this->environment, $webDir, $www);
    }
}