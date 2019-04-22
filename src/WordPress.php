<?php

namespace Phoenix;

/**
 * Class WordPress
 * @package Phoenix
 */
class WordPress extends AbstractDeployer
{

    /**
     * @var string
     */
    public $environ;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var TerminalClient
     */
    private $terminal;

    /**
     * @var WHM
     */
    private $whm;

    /**
     * WordPress constructor.
     * @param string $environ
     * @param TerminalClient $terminal
     * @param WHM|null $whm
     */
    function __construct($environ = 'live', TerminalClient $terminal = null, WHM $whm = null)
    {
        $this->environ = $environ;
        $this->terminal = $terminal;
        $this->whm = $whm;
        parent::__construct();
    }

    function create()
    {
        $this->install();
    }

    /**
     * @return bool|null
     */
    function install()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (empty($args))
            return false;
        $environ = $this->environ;

        $WPCLI = $this->terminal->wp_cli()->install();

        $wp = $this->terminal->wp();
        $installed = $wp->install($args);

        if ($installed) {
            $wp_blog_public = $this->environment == 'live' ? 1 : 0;
            $setOptions = array(
                array('name' => 'default_comment_status', 'value' => 'closed'),
                array('name' => 'blog_public', 'value' => $wp_blog_public),
                array('name' => 'blogdescription', 'value' => 'Enter tagline for ' . $args['title'] . ' here'),
            );
            if (!empty($args['timezone']))
                $setOptions[] = array('name' => 'timezone_string', 'value' => $args['timezone']);

            $setOptionSuccess = [];
            foreach ($setOptions as $option) {
                $args['option'] = $option;
                $setOptionSuccess[] = $wp->setOption($args);
            }

            $www = ($environ == 'live' && !empty($args['www'])) ? true : false;
            $setRewriteRules = $wp->setRewriteRules($args);
            if ($setRewriteRules)
                $htaccess = $this->terminal->htaccess()->prepend($args['directory'], $www);
            $installedLatestTheme = $wp->installLatestDefaultTheme($args);
            $permissions = $wp->setPermissions($args);
            $updated = $wp->update($args);
        }

        $success = !empty($WPCLI)
        && !empty($installed)
        && !empty($setRewriteRules)
        && !empty($htaccess)
        && !empty($permissions)
        && (!in_array(false, $setOptionSuccess))
        && !empty($installedLatestTheme)
        && !empty($updated) ? true : false;

        return $this->logFinish($success);

    }

    /**
     * @return bool|null
     */
    function delete()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (empty($args))
            return false;

        $deleted_wp = $this->terminal->wp()->delete($args);

        if ($deleted_wp) {
            $deleted_wp_cli = $this->environ == 'live' ? $this->terminal->wp_cli()->delete() : true;

            $WPCLIConfig = $this->terminal->wp_cli_config();
            $WPCLIConfig->dirPath = dirname($args['directory']);
            $deletedWPConfig = $WPCLIConfig->delete();
        }
        $success = $deleted_wp && !empty($deleted_wp_cli) && !empty($deletedWPConfig) ? true : false;
        return $this->logFinish($success);

    }

    /**
     * @return bool
     */
    function validate()
    {
        return true;
    }

    /**
     * @return array|bool
     */
    protected
    function getArgs()
    {
        $environ = $this->environ;

        $args = (array)ph_d()->config->wordpress;

        $args['directory'] = ph_d()->get_environ_dir($environ, 'web');

        $args['title'] = ph_d()->config->project->title ?? 'Insert Site Title Here';
        $args['url'] = ph_d()->get_environ_url($environ);

        $args['db'] = (array)ph_d()->config->environ->$environ->db ?? '';
        if (empty($args['db']['name']))
            return $this->logError(ucfirst($environ) . " environ DB name missing from config.");
        if (empty($args['db']['username']))
            return $this->logError(ucfirst($environ) . " environ DB username missing from config.");
        if (empty($args['db']['username']))
            return $this->logError(ucfirst($environ) . " environ DB password missing from config.");

        $args['cli']['config']['path'] = ph_d()->config->environ->$environ->dirs->web_root->path ?? '';

        if ($environ != 'local') {
            $cpanel = ph_d()->find_environ_cpanel($environ);
            if (empty($cpanel['user']))
                return $this->logError(sprintf("Couldn't work out %s cPanel username.", $environ));
            $args['db']['name'] = $this->whm->db_prefix_check($args['db']['name'], $cpanel['user']);
            $args['db']['username'] = $this->whm->db_prefix_check($args['db']['username'], $cpanel['user']);
        }
        return $args;
    }

    /**
     * @return string
     */
    protected
    function mainStr()
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        return $this->_mainStr[$action] = sprintf('%s WordPress and WP CLI', $this->environ);
    }
}