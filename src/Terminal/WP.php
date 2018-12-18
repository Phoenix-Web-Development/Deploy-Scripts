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
        $output = $this->exec("cd " . $wp_dir . "; wp core is-installed;");
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
        $debug = !empty($wp_args['debug']) && $wp_args['debug'] ? 'true' : 'false';
        $wp_plugins = !empty($wp_args['plugins']) ? sprintf('wp plugin install %s;', implode(' ', (array)$wp_args['plugins'])) : '';
        $wp_plugins = "wp plugin install jetpack --version=6.5; wp plugin install jetpack --version=7.0; ";
        //                wp plugin update --all;
        $output = $this->exec("cd " . $wp_dir . "; wp core download --skip-content;");
        $this->ssh->setTimeout(false); //downloading WP can take a while
        if (stripos($output, 'success') === false && strpos($output, 'WordPress files seem to already be present here') === false)
            return $this->logError("WordPress download failed.");

        $config_constants = $this->getConfigConstants();
        $config_set = '';
        foreach ($config_constants as $config_constant => $constant) {
            $config_set .= sprintf("wp config set %s %s --raw --type=constant;", $config_constant, $constant);
        }

        $wp_blog_public = $this->environment == 'live' ? 1 : 0;
        $wp_lang = !empty($wp_args['language']) ? 'wp language core install ' . $wp_args['language'] . '; wp site switch-language ' . $wp_args['language'] . ';' : '';
        $wp_timezone = !empty($wp_args['timezone']) ? 'wp option update timezone_string "' . $wp_args['timezone'] . '";' : '';

        $output .= $this->exec("
                cd " . $wp_dir . "; 
                wp config create --dbname='" . $db_args['name'] . "' --dbuser='" . $db_args['username'] . "' --dbpass='" . $db_args['password'] . "' --dbprefix='" . rtrim($wp_args['prefix'], '_') . "_'" . " --locale=en_AU;         
                " . $config_set . "
                wp core install --url='" . $wp_args['url'] . "' --title='" . $wp_args['title'] . "' --admin_user='" . $wp_args['username']
            . "' --admin_password='" . $wp_args['password'] . "' --admin_email='" . $wp_args['email'] . "' --skip-email;"
            . $wp_plugins . '
                wp post delete 1;
                wp option update default_comment_status closed; 
                wp option update blogdescription "Enter tagline for ' . $wp_args['title'] . ' here";
                wp theme install twentyseventeen --activate;
                wp option update blog_public ' . $wp_blog_public . ';'
            . $wp_lang . $wp_timezone
        );
        $output .= $this->exec('cd ' . $wp_dir . ';
                find ' . $wp_dir . ' -type d -exec chmod 755 {} \;
                find ' . $wp_dir . ' -type f -exec chmod 644 {} \;  
                find ' . self::trailing_slash($wp_dir) . 'wp-content -type d -exec chmod 775 {} \;
                find ' . self::trailing_slash($wp_dir) . 'wp-content -type f -exec chmod 664 {} \;
                chmod 660 wp-config.php
                mv wp-config.php ../
                wp rewrite structure "/%postname%/";
                wp rewrite flush;
                wp plugin activate --all;
                rm wp-config-sample.php license.txt readme.html
                ');
        // /%postname%/
        $widgets = $this->exec("cd " . $wp_dir . "; wp widget list sidebar-1 --format=ids");
        if (!empty($widgets)) {
            foreach (array('search-1', 'search-2', 'search') as $search) {
                $widgets = str_replace($widgets, $search, '');
            }
            $output .= $this->exec("cd " . $wp_dir . "; wp widget delete " . trim($widgets));
        }
        $success = $this->check($wp_dir) ? true : false;
        return $this->logFinish($output, $success);
    }

    /**
     * @return array
     */
    protected function getConfigConstants()
    {
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
            $db_clean = ' wp db clean --yes;';
            $output = $this->exec("cd " . $wp_dir . ";"
                . $db_clean
            );
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
            if ($this->ssh->file_exists($wp_file_path))
                $wp_file_paths[] = $wp_file_path;
        }
        $succeededDeleting = true;
        if (!empty($wp_file_paths)) {
            foreach ($wp_file_paths as $wp_file_path) {
                if (!$this->ssh->delete($wp_file_path)) {
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
        return $this->logFinish($output, $success);
    }

    /**
     * @param string $wp_dir
     * @return bool
     */
    public function update($wp_dir = '')
    {
        if (!$this->$this->validate())
            return false;
        if (!$this->check($wp_dir))
            return $this->logError(sprintf('WordPress not installed at <strong>%s</strong>.', $wp_dir));
        $this->exec(
            'cd ' . $wp_dir . ';                        
        wp core update --locale="en_AU";
        wp core update-db;
        wp theme update --all; 
        wp plugin update --all; 
        wp core language update; 
        wp db optimize'
        );
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
        if (!$this->ssh->is_dir($wp_dir))
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