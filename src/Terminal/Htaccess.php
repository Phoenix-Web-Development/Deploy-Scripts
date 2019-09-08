<?php

namespace Phoenix\Terminal;

/**
 * Class Htaccess
 * @package Phoenix\Terminal
 */
class Htaccess extends AbstractTerminal
{
    /**
     * @var string
     */
    protected $logElement = 'h4';

    /**
     * Adds rules to htaccess to:
     * redirect http to https
     * www to non-www (or vice versa)
     * look for image at live server if not found in staging/local/etc
     *
     * @param string $webDir
     * @param bool $www
     * @param string $liveURL
     * @return bool|null
     */
    public function prepend(string $webDir = '', bool $www = false, $liveURL = '')
    {
        $this->mainStr($webDir, $www);
        $this->logStart();
        if (!$this->validate($webDir))
            return false;

        $wwwString = !empty($www) ? "
## Redirect non-www to www
    RewriteCond %{HTTP_HOST} !^www\. [NC]
    RewriteRule ^(.*)$ http://www.%{HTTP_HOST}/$1 [R=301,L]" : "
## Redirect www to non-www
    RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
    RewriteRule ^(.*)$ http://%1/$1 [R=301,L]";

        //If file in WP Uploads dir not found look for it at the live address
        $missingImageProxy = !empty($liveURL) ? "
## Query live server for WordPress upload if file not found
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^wp-content/uploads/[^/]+/ " . $liveURL . "%{REQUEST_URI} [R,QSA,L]" : '';

        $customRulesHeading = 'Phoenix Web Custom Rules';

        $htaccessRules = "### " . $customRulesHeading . " start ###
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    <IfModule mod_litespeed.c>
        RewriteRule .* - [E=noabort:1]
    </IfModule>" . $wwwString . "
## Redirect http to https             
    RewriteCond %{HTTPS} !on [NC]
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]" . $missingImageProxy . "         
</IfModule>
### " . $customRulesHeading . " end ###

";

        $remoteFile = self::trailing_slash($webDir) . '.htaccess';
        $existingHtaccess = $this->get($remoteFile);
        if (strpos($existingHtaccess, $htaccessRules) !== false)
            return $this->logFinish(true, "No need as the <code>.htaccess</code> file already contains new rules.");
        $newHtaccessRules = $htaccessRules . $existingHtaccess;

        $success = $this->put($remoteFile, $newHtaccessRules);
        return $this->logFinish($success);
    }

    /**
     * @param string $webDir
     * @return bool
     */
    protected function validate(string $webDir = '')
    {
        if (!$this->is_dir($webDir)) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $webDir));
        }
        if (!$this->file_exists(self::trailing_slash($webDir) . '.htaccess')) {
            return $this->logError(sprintf("No <code>.htaccess</code> file in directory <strong>%s</strong>.", $webDir));
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
        return $this->_mainStr = sprintf("%s environment <code>.htaccess</code> file%s%s", $this->environment, $webDir, $www);
    }
}