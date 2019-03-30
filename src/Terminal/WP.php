<?php

namespace Phoenix\Terminal;

/**
 * Class WP
 * @package Phoenix\Terminal
 */
class WP extends AbstractTerminal
{
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
     * @param string $wp_dir
     * @return bool
     */
    public function check($wp_dir = '')
    {
        if (!$this->validate($wp_dir))
            return false;
        $output = $this->exec(" wp core is-installed;", $wp_dir);
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
        return true;
    }

    /**
     * alias of install
     *
     * @param string $wp_dir
     * @param array $db_args
     * @param array $wp_args
     * @return bool
     */
    public function create(string $wp_dir = '', array $db_args = array(), array $wp_args = array())
    {
        return $this->install($wp_dir, $db_args, $wp_args);
    }

    /**
     * @param string $wp_dir
     * @param array $db_args
     * @param array $wp_args
     * @return bool
     */
    public function install(string $wp_dir = '', array $db_args = array(), array $wp_args = array())
    {
        $this->mainStr($wp_dir);
        $this->logStart();
        if (!$this->validate($wp_dir))
            return false;
        if ($this->check($wp_dir))
            return $this->logError(sprintf('WordPress already installed at <strong>%s</strong>.', $wp_dir), 'warning');
        if (!isset($db_args['name'], $db_args['username'], $db_args['password']))
            return $this->logError("DB name, username and/or password are missing from config.");
        if (!isset($wp_args['username'], $wp_args['password'], $wp_args['email'], $wp_args['url'], $wp_args['title'], $wp_args['prefix']))
            return $this->logError("WordPress username, password, email, url, title and/or prefix are missing from config.");
        $wp_plugins = !empty($wp_args['plugins']) ? sprintf('wp plugin install %s;', implode(' ', (array)$wp_args['plugins'])) : '';
        //$wp_plugins = "wp plugin install jetpack --version=6.5; wp plugin install jetpack --version=7.0; ";
        //                wp plugin update --all;
        $output = $this->exec("wp core download --skip-content;", $wp_dir);
        $this->ssh->setTimeout(false); //downloading WP can take a while
        if (stripos($output, 'success') === false && strpos($output, 'WordPress files seem to already be present here') === false)
            return $this->logError("WordPress download failed.");

        $config_constants = $this->getConfigConstants();
        $config_set = '';
        foreach ($config_constants as $config_constant => $constant) {
            $config_set .= sprintf("wp config set %s %s --raw --type=constant;", $config_constant, $constant);
        }

        $wp_lang = !empty($wp_args['language']) ? 'wp language core install ' . $wp_args['language'] . '; wp site switch-language ' . $wp_args['language'] . ';' : '';
        $commands1 = "                
                wp config create --dbname='" . $db_args['name'] . "' --dbuser='" . $db_args['username'] . "' --dbpass='" . $db_args['password'] . "' --dbprefix='" . rtrim($wp_args['prefix'], '_') . "_'" . " --locale=en_AU;         
                " . $config_set . "
                wp core install --url='" . $wp_args['url'] . "' --title='" . $wp_args['title'] . "' --admin_user='" . $wp_args['username']
            . "' --admin_password='" . $wp_args['password'] . "' --admin_email='" . $wp_args['email'] . "' --skip-email;"
            . $wp_plugins . '
                wp post delete 1;
                wp theme install twentyseventeen --activate;'
            . $wp_lang;
        $commands2 = '              
                mv wp-config.php ../
                wp rewrite structure "/%postname%/";
                wp rewrite flush --hard;
                wp plugin activate --all;
                rm wp-config-sample.php license.txt readme.html
                ';
        $output .= $this->exec($commands1, $wp_dir);
        $output .= $this->exec($commands2, $wp_dir);
        $setOption = $this->setOption($wp_dir, 'default_comment_status', 'closed');
        $setOption2 = $this->setOption($wp_dir, 'blogdescription', 'Enter tagline for ' . $wp_args['title'] . ' here');
        $wp_blog_public = $this->environment == 'live' ? 1 : 0;
        $setOption3 = $this->setOption($wp_dir, 'blog_public', $wp_blog_public);
        if (!empty($wp_args['timezone']))
            $setOption4 = $this->setOption($wp_dir, 'timezone_string', $wp_args['timezone']);

        $widgets = $this->exec("wp widget list sidebar-1 --format=ids", $wp_dir);
        if (!empty($widgets)) {
            foreach (array('search-1', 'search-2', 'search') as $search) {
                $widgets = str_replace($widgets, $search, '');
            }
            $output .= $this->exec("wp widget delete " . trim($widgets), $wp_dir);
        }
        $update = $this->update($wp_dir);
        $success = $this->check($wp_dir) ? true : false;
        return $this->logFinish($success, $output, $commands1 . $commands2);
    }

    /**
     * @param string $wp_dir
     * @return bool|null
     */
    public function setPermissions(string $wp_dir = '')
    {
        $this->mainStr($wp_dir);
        $this->logStart();
        if (!$this->validate($wp_dir))
            return false;
        if (!$this->check($wp_dir))
            return $this->logError(sprintf('WordPress not installed at <strong>%s</strong>.', $wp_dir));
        $wp_dir = self::trailing_slash($wp_dir);
        $commands = '
                find ' . $wp_dir . ' -type d -exec chmod 755 {} \;
                echo status is $?;
                find ' . $wp_dir . ' -type f -exec chmod 644 {} \;
                echo status is $?;  
                find ' . $wp_dir . 'wp-content -type d -exec chmod 775 {} \;
                echo status is $?;
                find ' . $wp_dir . 'wp-content -type f -exec chmod 664 {} \;
                echo status is $?;
        ';
        $output = $this->exec($commands, $wp_dir);
        if (stripos($output, 'status is 0') !== false && stripos($output, 'status is 1') === false)
            $findCommands = true;
        if (!empty($findCommands)) {
            $configFilePath = $wp_dir . '../wp-config.php';
            if ($this->file_exists($configFilePath))
                $wpConfig = $this->ssh->chmod(0660, $configFilePath);
            $htaccessFilePath = $wp_dir . '.htaccess';
            if ($this->file_exists($htaccessFilePath))
                $htaccess = $this->ssh->chmod(0644, $htaccessFilePath);
        }
        $success = (!empty($findCommands) && !empty($wpConfig) && !empty($htaccess)) ? true : false;
        return $this->logFinish($success, $output, $commands);
    }

    /**
     * @param string $wp_dir
     * @param string $option
     * @param string $value
     * @return bool|null
     */
    public function setOption(string $wp_dir = '', $option = '', $value = '')
    {
        $this->mainStr($wp_dir);
        $this->logStart();
        if (!$this->validate($wp_dir))
            return false;
        if (!$this->check($wp_dir))
            return $this->logError(sprintf('WordPress not installed at <strong>%s</strong>.', $wp_dir));
        if (empty($option))
            return $this->logError("Option name not passed to setOption method");
        if (!isset($value) || $value === '')
            return $this->logError("Option value not passed to setOption method");
        $command = 'wp option update ' . $option . ' "' . $value . '"';
        $output = $this->exec($command, $wp_dir);
        $success = (stripos($output, "Success:") !== false) ? true : false;
        return $this->logFinish($success, $output, $command);
    }

    /**
     * @return array
     */
    protected function getConfigConstants()
    {
        //$debug = !empty($wp_args['debug']) && $wp_args['debug'] ? 'true' : 'false';
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
     * @param string $wp_dir
     * @return bool
     */
    public function delete($wp_dir = '')
    {
        return $this->uninstall($wp_dir);
    }

    /**
     * @param string $wp_dir
     * @return bool
     */
    public function uninstall($wp_dir = '')
    {
        $this->mainStr($wp_dir);
        $this->logStart();
        if (!$this->validate($wp_dir))
            return false;
        if ($this->check($wp_dir)) {
            $output = $this->exec('wp db clean --yes;', $wp_dir);
            $cleanedDB = (stripos($output, 'Success') !== false && stripos($output, 'Tables dropped') !== false) ? true : false;
            if ($cleanedDB)
                $output = "Successfully cleaned DB of all WordPress tables. ";
        } else {
            $noNeedCleanDB = true;
            $output = "Skipped dropping DB tables as apparently WordPress isn't installed. ";
        }

        $wp_files = self::WP_FILES;
        foreach ($wp_files as $wp_file) {
            $wp_file_path = self::trailing_slash($wp_dir) . $wp_file;
            if ($this->file_exists($wp_file_path))
                $wp_file_paths[] = $wp_file_path;
        }
        $succeededDeleting = true;
        if (!empty($wp_file_paths)) {
            foreach ($wp_file_paths as $wp_file_path) {
                if (!$this->deleteFile($wp_file_path)) {
                    $succeededDeleting = false;
                    $output .= "Failed to delete one or more WordPress files. ";
                    break;
                }
            }
            if ($succeededDeleting)
                $output .= "Deleted WordPress files. ";
        } else {
            $output = "Apparently no WordPress files were found so no need to delete them. ";
            $noNeedDeleteFiles = true;
        }
        if (!empty($noNeedCleanDB) && !empty($noNeedDeleteFiles)) {
            $this->log("No need to uninstall WordPress. " . $output, 'warning');
            return true;
        }
        $success = (!$this->check($wp_dir) && (!empty($cleanedDB) || !empty($noNeedCleanDB)) && $succeededDeleting) ? true : false;
        return $this->logFinish($success, $output);
    }

    /**
     * @param string $wp_dir
     * @return bool
     */
    public function update($wp_dir = '')
    {
        if (!$this->validate($wp_dir))
            return false;
        if (!$this->check($wp_dir))
            return $this->logError(sprintf('WordPress not installed at <strong>%s</strong>.', $wp_dir));
        $version = trim($this->exec('wp core version;', $wp_dir));
        $updateToVersion = ($version != '5.0' && $version != '5.0.1') ? ' --version=4.9.9' : '';
        $output = $this->exec('                     
            wp core update --locale="en_AU" ' . $updateToVersion . ';
            wp core update-db;
            wp theme update --all; 
            wp plugin update --all;
            wp core language update;
            wp language plugin update --all;
            wp language theme update --all;
            wp db optimize', $wp_dir
        );
        $success = null;
        if (stripos($output, 'error') !== false)
            $success = false;
        elseif (stripos($output, 'success') !== false)
            $success = true;
        return $this->logFinish($success, $output);
    }

    /**
     * @param string $wp_dir
     * @return bool
     */
    protected function validate(string $wp_dir = '')
    {
        if (empty($wp_dir))
            return $this->logError("File directory missing from function input.");
        $wp_dir = self::trailing_slash($wp_dir);
        if (in_array($wp_dir, array('~/', self::trailing_slash($this->client->root))))
            return $this->logError(sprintf("Shouldn't be %s WordPress in root directory <strong>%s</strong>.",
                $this->actions[$this->getCaller()]['present'], $wp_dir));
        if (!$this->is_dir($wp_dir))
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $wp_dir));
        if (!$this->client->WPCLI()->install_if_missing())
            return $this->logError("WP CLI missing and install failed.");
        return true;
    }

    /**
     * @param string $wp_dir
     * @return bool|string
     */
    protected
    function mainStr(string $wp_dir = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }

        $wp_dir = !empty($wp_dir) ? sprintf(' in directory <strong>%s</strong>', $wp_dir) : '';
        return $this->_mainStr = sprintf("%s environment WordPress%s", $this->environment, $wp_dir);
    }
}