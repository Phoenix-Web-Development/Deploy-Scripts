<?php

namespace Phoenix\Terminal;

/**
 * Class WP
 * @package Phoenix\Terminal
 */
class WP extends AbstractTerminal
{

    /**
     * @var string
     */
    protected $logElement = 'h4';

    const WP_FILES = array(
        'wp-admin/',
        'wp-content/',
        'wp-includes/',
        'index.php',
        'license.txt',
        'readme.html',
        'wp-activate.php',
        'wp-blog-header.php',
        'wp-comments-post.php',
        'wp-config.php',
        '../wp-config.php',
        'wp-config-sample.php',
        'wp-cron.php',
        'wp-links-opml.php',
        'wp-load.php',
        'wp-login.php',
        'wp-mail.php',
        'wp-settings.php',
        'wp-signup.php',
        'wp-trackback.php',
        'xmlrpc.php',
        '.htaccess',
        '.htaccess_lscachebak_orig',
        '.htaccess_lscachebak_01', //phpseclib SFTP doesn't support wildcards
        '.htaccess_lscachebak_02',
        '.htaccess_lscachebak_03',
        '.htaccess_lscachebak_04',
        '.htaccess_lscachebak_05',
        '.htaccess_lscachebak_06',
        '.htaccess_lscachebak_07',
        '.htaccess_lscachebak_08',
        '.htaccess_lscachebak_09',
        '.htaccess_lscachebak_10',
        'README.md',
        'wordfence-waf.php'
    );

    /**
     * @param array $args
     * @return bool
     */
    protected function check(array $args = [])
    {
        $output = $this->exec("wp core is-installed; echo $?", $args['directory']);
        d($output);
        $potential_errors = array(
            "This does not seem to be a WordPress install",
            "'wp-config.php' not found",
            "Error establishing a database connection",
            "The site you have requested is not installed"
        );
        foreach ($potential_errors as $potential_error) {
            if (stripos($output, $potential_error) !== false)
                return false;
        }
        if ($output == '0')
            return true;
        return false;
    }

    /**
     * alias of install
     *
     * @param array $args
     * @return bool
     */
    public function create($args = array())
    {
        return $this->install($args);
    }

    /**
     * @param array $args
     * @return bool
     */
    public function install(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        if ($this->check($args))
            return $this->logFinish(true, sprintf('WordPress already installed at <strong>%s</strong>.', $args['directory']));
        $output = $this->exec("wp core download --skip-content", $args['directory']);
        d($output);
        if (stripos($output, 'success') === false) {
            if (stripos($output, 'WordPress files seem to already be present here') === false)
                return $this->logError("WordPress download failed." . $output);
            $filesAlreadyDownloaded = true;
        }

        $config_constants = $this->getConfigConstants();
        $config_set = '';
        foreach ($config_constants as $config_constant => $constant) {
            $config_set .= sprintf("wp config set %s %s --raw --type=constant;", $config_constant, $constant);
        }

        $wp_lang = !empty($args['language']) ? 'wp language core install ' . $args['language'] . '; wp site switch-language ' . $args['language'] . ';' : '';
        $commands = "                
                wp config create --dbname='" . $args['db']['name'] . "' --dbuser='" . $args['db']['username'] . "' --dbpass='" . $args['db']['password'] . "' --dbprefix='" . rtrim($args['prefix'], '_') . "_'" . " --locale=en_AU;         
                " . $config_set . "
                wp core install --url='" . $args['url'] . "' --title='" . $args['title'] . "' --admin_user='" . $args['username']
            . "' --admin_password='" . $args['password'] . "' --admin_email='" . $args['email'] . "' --skip-email; wp post delete 1;"
            . $wp_lang . '              
                mv wp-config.php ../                
                wp plugin activate --all;
                rm wp-config-sample.php license.txt readme.html
                ';
        $output .= $this->exec($commands, $args['directory']);

        if (empty($filesAlreadyDownloaded)) {
            $wp_plugins = !empty($args['plugins']) ? sprintf('wp plugin install %s;', implode(' ', (array)$args['plugins'])) : '';
            //$wp_plugins = "wp plugin install jetpack --version=6.5; wp plugin install jetpack --version=7.0; ";
            //                wp plugin update --all;
            $output .= $this->exec($wp_plugins . 'wp theme install twentynineteen --activate;', $args['directory']);
        }

        $widgets = $this->exec("wp widget list sidebar-1 --format=ids", $args['directory']);
        if (!empty($widgets)) {
            foreach (array('search-1', 'search-2', 'search') as $search) {
                $widgets = str_replace($widgets, $search, '');
            }
            $output .= $this->exec("wp widget delete " . trim($widgets), $args['directory']);
        }
        return $this->logFinish($this->check($args), $output, $commands);
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function setPermissions(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $args['directory'] = self::trailing_slash($args['directory']);
        $commands = '
                find ' . $args['directory'] . ' -type d -exec chmod 770 {} \;
                echo status is $?;
                find ' . $args['directory'] . ' -type f -exec chmod 660 {} \;
                echo status is $?;                  
        ';

        $output = $this->exec($commands, $args['directory']);
        if (stripos($output, 'status is 0') !== false && stripos($output, 'status is 1') === false)
            $findCommands = true;
        if (!empty($findCommands)) {
            $configFilePath = $args['directory'] . '../wp-config.php';
            if ($this->file_exists($configFilePath))
                $wpConfig = $this->chmod($configFilePath, 0660);
            $htaccessFilePath = $args['directory'] . '.htaccess';
            if ($this->file_exists($htaccessFilePath))
                $htaccess = $this->chmod($htaccessFilePath, 0664);
        }
        $success = (!empty($findCommands) && !empty($wpConfig) && !empty($htaccess)) ? true : false;
        return $this->logFinish($success, $output, $commands);
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function setOption(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if (empty($args['option']['name']))
            return $this->logError("Option name not passed to setOption method");
        if (!isset($args['option']['value']) || $args['option']['value'] === '')
            return $this->logError("Option value not passed to setOption method");
        $command = 'wp option update ' . $args['option']['name'] . ' "' . $args['option']['value'] . '"';
        $output = $this->exec($command, $args['directory']);
        $success = (stripos($output, "Success:") !== false) ? true : false;
        return $this->logFinish($success, $output, $command);
    }

    /**
     * @return array
     */
    protected function getConfigConstants()
    {
        //$debug = !empty($args['debug']) && $args['debug'] ? 'true' : 'false';
        $debug = false;
        $config_constants = array(
            'AUTOSAVE_INTERVAL' => 300,
            'WP_POST_REVISIONS' => 6,
            'EMPTY_TRASH_DAYS' => 7,
            'DISALLOW_FILE_EDIT' => 'true',
            'WP_DEBUG' => $debug
        );
        if ($this->environment == 'live') {
            $config_constants['AUTOMATIC_UPDATER_DISABLED'] = 'true';
            $config_constants['DISALLOW_FILE_MODS'] = 'true';
        }
        return $config_constants;
    }

    /**
     * Alias of uninstall
     *
     * @param array $args
     * @return bool
     */
    public function delete(array $args = [])
    {
        return $this->uninstall($args);
    }

    /**
     * @param array $args
     * @return bool
     */
    public function uninstall(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if (!$this->is_dir($args['directory']))
            return $this->logFinish(true, sprintf("No need to delete as WordPress directory <strong>%s</strong> doesn't exist.", $args['directory']));

        if ($this->check($args)) {
            $output = $this->exec('wp db clean --yes;', $args['directory']);
            $cleanedDB = (stripos($output, 'Success') !== false && stripos($output, 'Tables dropped') !== false) ? true : false;
            if ($cleanedDB)
                $output = "Successfully cleaned DB of all WordPress tables. ";
        } else {
            $noNeedCleanDB = true;
            $output = "Skipped dropping DB tables as apparently WordPress isn't installed. ";
        }

        $wp_files = self::WP_FILES;
        foreach ($wp_files as $wp_file) {
            $wp_file_path = self::trailing_slash($args['directory']) . $wp_file;
            if ($this->file_exists($wp_file_path))
                $wp_file_paths[] = $wp_file_path;
        }
        $succeededDeleting = true;
        if (!empty($wp_file_paths)) {
            foreach ($wp_file_paths as $wp_file_path) {
                if ($this->deleteFile($wp_file_path)) {
                    $output .= "<br>Deleted <strong>" . $wp_file_path . "</strong>";
                } else {
                    $succeededDeleting = false;
                    $output .= "Failed to delete one or more WordPress files. ";
                    break;
                }
            }
            if ($succeededDeleting)
                $output .= "<br>Deleted WordPress files. ";
        } else {
            $output = "Apparently no WordPress files were found so no need to delete them. ";
            $noNeedDeleteFiles = true;
        }
        if (!empty($noNeedCleanDB) && !empty($noNeedDeleteFiles))
            return $this->logFinish(true, "No need to uninstall WordPress. " . $output);
        $success = (!$this->check($args) && (!empty($cleanedDB) || !empty($noNeedCleanDB)) && $succeededDeleting) ? true : false;
        return $this->logFinish($success, $output);
    }

    /**
     * @param array $args
     * @return bool
     */
    public function update(array $args = [])
    {
        if (!$this->validate($args))
            return false;
        $version = trim($this->exec('wp core version;', $args['directory']));
        $updateToVersion = ($version != '5.0' && $version != '5.0.1') ? ' --version=4.9.9' : '';
        $output = $this->exec('                     
            wp core update --locale="en_AU" ' . $updateToVersion . ';
            wp core update-db;
            wp theme update --all; 
            wp plugin update --all;
            wp core language update;
            wp language plugin update --all;
            wp language theme update --all;
            wp db optimize', $args['directory']
        );
        $success = null;
        if (stripos($output, 'error') !== false)
            $success = false;
        elseif (stripos($output, 'success') !== false)
            $success = true;
        return $this->logFinish($success, $output);
    }

    /**
     * @param array $args
     * @return bool
     */
    protected function validate(array $args = [])
    {
        $caller = $this->getCaller();
        if (empty($args['directory']))
            return $this->logError("File directory missing from <code>" . $caller . "</code> method input.");

        $args['directory'] = self::trailing_slash($args['directory']);
        if ($this->inSanityList($args['directory']))
            return $this->logError(sprintf("Shouldn't be %s WordPress in root directory <strong>%s</strong>.",
                $this->actions[$caller]['present'], $args['directory']));
        if ($caller != 'uninstall' && !$this->is_dir($args['directory'])) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $args['directory']));
        }
        if (!$this->client->WPCLI()->install_if_missing())
            return $this->logError("WP CLI missing and install failed.");

        if ($caller == 'install') {
            if (!isset($args['db']['name'], $args['db']['username'], $args['db']['password']))
                return $this->logError("DB name, username and/or password are missing from config.");
            if (!isset($args['username'], $args['password'], $args['email'], $args['url'], $args['title'], $args['prefix']))
                return $this->logError("WordPress username, password, email, url, title and/or prefix are missing from config.");
            if (!$this->is_writable($args['directory']))
                return $this->logError("Nominated WordPress directory is not writable.");
        }
        if ($caller != 'install' && $caller != 'uninstall') {
            if (!$this->check($args))
                return $this->logError(sprintf('WordPress not installed at <strong>%s</strong>.', $args['directory']));
        }
        return true;
    }

    public function setRewriteRules(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        if (!$this->canRewriteFlushHard($args)) {
            $WPCLIConfig = $this->client->wp_cli_config();
            $WPCLIConfig->dirPath = $args['directory'];
            $WPCLIConfig->create();
            if (!$this->canRewriteFlushHard($args))
                return $this->logError("Can't regenerate .htaccess file because mod_rewrite isn't loaded in WP CLI.");
        }

        $command = 'wp rewrite structure "/%postname%/";wp rewrite flush --hard';
        $output = $this->exec($command, $args['directory']);

        //check success
        $successMessages = array(
            'Success: Rewrite rules flushed.',
            'Success: Rewrite structure set.',
            'Success: Rewrite rules flushed.'
        );
        $success = true;
        foreach ($successMessages as $successMessage) {
            if (strpos($output, $successMessage) === false) {
                $success = false;
                break;
            }
        }
        $failMessages = array('Warning: Regenerating a .htaccess file requires special configuration. See usage docs.');
        foreach ($failMessages as $failMessage) {
            if (strpos($output, $failMessage) !== false) {
                $success = false;
                break;
            }
        }
        return $this->logFinish($success, $output);
    }

    /**
     * @param array $args
     * @return bool
     */
    protected function canRewriteFlushHard(array $args = [])
    {
        $WPCLIparams = $this->exec("wp cli param-dump --with-values", $args['directory']);
        $WPCLIparams = json_decode($WPCLIparams, true);
        d($WPCLIparams);
        $apacheModules = $WPCLIparams['apache_modules']['current'] ?? [];
        if (in_array('mod_rewrite', $apacheModules))
            return true;
        return false;
    }
    /*
        protected function getLatestDefaultTheme(string $args['directory'] = '')
        {
            $output = $this->exec("wp theme search --per-page=30 --fields=name,author,slug --format=json Twenty");
            $themes = json_decode($output);
            d($themes);
            foreach($themes as $theme){
                if($theme['author'] == 'wordpressdotorg')
            }

        }
    */

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
        $optionStr = !empty($args['option']['name']) && !empty($args['option']['value']) ?
            sprintf(' with option "<strong>%s</strong>" and value "<strong>%s</strong>"',
                $args['option']['name'], $args['option']['value']) : '';

        return $this->_mainStr = sprintf("%s environment WordPress%s%s", $this->environment, $dirStr, $optionStr);
    }
}