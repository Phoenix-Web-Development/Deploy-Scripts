<?php

namespace Phoenix;

/**
 * Class WordPress
 * @package Phoenix
 */
class WordPress extends AbstractDeployer
{
    /**
     * @var ActionRequests
     */
    private $actionRequests;

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
     *
     * @param string $environ
     * @param TerminalClient|null $terminal
     * @param WHM|null $whm
     * @param ActionRequests|null $actionRequests
     */
    function __construct($environ = 'live', TerminalClient $terminal = null, ActionRequests $actionRequests = null, WHM $whm = null)
    {
        $this->environ = $environ;
        $this->terminal = $terminal;
        $this->whm = $whm;
        $this->actionRequests = $actionRequests;
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
        $success = [];
        $success['wp_cli'] = $this->terminal->wp_cli()->installOrUpdate();

        $wp = $this->terminal->wp();
        $success['download'] = $wp->download($args);

        if ($this->actionRequests->can_do('create_' . $this->environ . '_wp_config')) {
            $success['config'] = $wp->setupConfig($args);
        }

        if ($this->actionRequests->can_do('create_' . $this->environ . '_wp_install')) {

            $success['install'] = $wp->install($args);

            if ($success['install']) {
                $wp_blog_public = $this->environment == 'live' ? 1 : 0;
                $setOptions = array(
                    array('name' => 'default_comment_status', 'value' => 'closed'),
                    array('name' => 'blog_public', 'value' => $wp_blog_public),
                    array('name' => 'blogdescription', 'value' => 'Enter tagline for ' . $args['title'] . ' here'),
                );
                if (!empty($args['timezone']))
                    $setOptions[] = array('name' => 'timezone_string', 'value' => $args['timezone']);


                $success['setOptions'] = [];
                foreach ($setOptions as $option) {
                    $args['option'] = $option;
                    $success['setOptions'][] = $wp->setOption($args);
                }
                $success['setOptions'] = !in_array(false, $success['setOptions']) ? true : false;

                $success['setRewriteRules'] = $wp->setRewriteRules($args);

                $success['installedLatestTheme'] = $wp->installLatestDefaultTheme($args);
                $success['permissions'] = $wp->setPermissions($args);
                $success['updated'] = $wp->update($args);
            }

            //$success = !in_array(false, $setOptionSuccess) ? true : false;
        }


        if ($this->actionRequests->can_do('create_' . $this->environ . '_wp_htaccess')) {
            $www = ($this->environ == 'live' && !empty($args['www'])) ? true : false;
            $htaccess = $this->terminal->htaccess()->prepend($args['directory'], $www, $args['live_url']);
            $success['htaccess'] = $htaccess;
        }

        $success = !in_array(false, $success) ? true : false;
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

        $success = $this->terminal->wp()->delete($args);

        if ($success && $this->environ == 'live') {
            $success = $this->terminal->wp_cli()->delete();
            if ($success) {
                $WPCLIConfig = $this->terminal->wp_cli_config();
                $WPCLIConfig->dirPath = dirname($args['directory']);
                $success = $WPCLIConfig->delete();
            }
        }
        //$success = $deleted_wp && !empty($deleted_wp_cli) && !empty($deletedWPConfig) ? true : false;
        return $this->logFinish($success);

    }

    /**
     *
     */
    function htaccess()
    {

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
        $args['www'] = ph_d()->config->environ->$environ->www ?? false;
        $args['directory'] = ph_d()->get_environ_dir($environ, 'web');

        $args['title'] = ph_d()->config->project->title ?? 'Insert Site Title Here';
        $args['url'] = ph_d()->get_environ_url($environ, true, true);

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

        $args['live_url'] = $environ != 'live' ? ph_d()->get_environ_url('live', true, true) : '';

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