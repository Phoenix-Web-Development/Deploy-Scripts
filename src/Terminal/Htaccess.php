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
     * @param array $args
     * @return bool|null
     */
    public function prepend(array $args = [])
    {

        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $wwwString = !empty($args['www']) ? "
## Redirect non-www to www
    RewriteCond %{HTTP_HOST} !^www\. [NC]
    RewriteRule ^(.*)$ http://www.%{HTTP_HOST}/$1 [R=301,L]" : "
## Redirect www to non-www
    RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
    RewriteRule ^(.*)$ http://%1/$1 [R=301,L]";

        //If file in WP Uploads dir not found look for it at the live address
        $missingImageProxy = !empty($args['live_url']) && $this->environment != 'live' ? "
## Query live server for WordPress upload if file not found
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^wp-content/uploads/[^/]+/ " . $args['live_url'] . "%{REQUEST_URI} [R,QSA,L]" : '';

        $customRulesHeading = $args['htaccess_heading'] ?? 'Custom Rules';
        $customRulesHeadingStart = "### " . $customRulesHeading . " start ###";
        $customRulesHeadingEnd = "### " . $customRulesHeading . " end ###";
        $htaccessRules = $customRulesHeadingStart . "
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
" . $customRulesHeadingEnd;

        $remoteFile = self::trailing_slash($args['directory']) . '.htaccess';
        $existingHtaccess = $this->get($remoteFile);
        if (strpos($existingHtaccess, $htaccessRules) !== false)
            return $this->logFinish(true, "No need as the <code>.htaccess</code> file already contains new rules.");

        //Remove old custom rules to be replaced by new ones
        $customRulesStartPoint = strpos($existingHtaccess, $customRulesHeadingStart);
        $customRulesEndPoint = strpos($existingHtaccess, $customRulesHeadingEnd);
        if ($customRulesStartPoint !== false && $customRulesEndPoint !== false) {
            $length = $customRulesEndPoint - $customRulesStartPoint + strlen($customRulesHeadingEnd);
            $existingHtaccess = substr_replace($existingHtaccess, '', $customRulesStartPoint, $length);
        }

        $newHtaccessRules = $htaccessRules . $existingHtaccess;

        $success = $this->put($remoteFile, $newHtaccessRules);
        return $this->logFinish($success);
    }

    /**
     * @param array $args
     * @return bool
     */
    protected function validate(array $args = [])
    {
        if (!$this->is_dir($args['directory'])) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $args['directory']));
        }
        if (!$this->file_exists(self::trailing_slash($args['directory']) . '.htaccess')) {
            return $this->logError(sprintf("No <code>.htaccess</code> file in directory <strong>%s</strong>.", $args['directory']));
        }
        return true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected
    function mainStr(array $args = [])
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $dirStr = !empty($args['directory']) ? sprintf(' in directory <strong>%s</strong>', $args['directory']) : '';
        $wwwStr = !empty($args['www']) ? ' for www containing URL' : ' for non-www URL';
        return $this->_mainStr = sprintf("%s environment <code>.htaccess</code> file%s%s", $this->environment, $dirStr, $wwwStr);
    }
}