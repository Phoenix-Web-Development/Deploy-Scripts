<?php

namespace Phoenix;

/**
 * Class WordPress
 *
 * @package Phoenix
 */
class WordPress extends AbstractDeployer
{
    /**
     * @var ActionRequests
     */
    private $actionRequests;

    /**
     * @var
     */
    private $config;

    /**
     * @var cPanelAccount|cPanelSubdomain|Environ
     */
    private $environ;

    /**
     * @var string
     */
    private $liveURL;

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
     * @param cPanelAccount|cPanelSubdomain|Environ $environ
     * @param $config
     * @param TerminalClient|null $terminal
     * @param ActionRequests|null $actionRequests
     * @param WHM|null $whm
     * @param string $liveURL
     */
    public function __construct(
        $environ,
        $config,
        TerminalClient $terminal = null,
        ActionRequests $actionRequests = null,
        string $liveURL = '',
        WHM $whm = null
    )
    {
        $this->environ = $environ;
        $this->config = $config;
        $this->terminal = $terminal;
        $this->actionRequests = $actionRequests;
        $this->whm = $whm;

        $this->liveURL = $liveURL;
        parent::__construct();
    }

    /**
     * @return bool|null
     */
    public function create(): ?bool
    {
        return $this->install();
    }

    /**
     * @return bool|null
     */
    public function install(): ?bool
    {
        $this->mainStr();
        $this->logStart();

        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $success = [];
        $success['wp_cli'] = $this->terminal->wp_cli()->installOrUpdate();

        $wp = $this->terminal->wp();
        $success['download'] = $wp->download($args);

        if ($this->actionRequests->canDo('create_' . $this->environ->name . '_wp_config')) {
            $success['config'] = $wp->setupConfig($args);
        }

        if ($this->actionRequests->canDo('create_' . $this->environ->name . '_wp_install')) {

            $success['install'] = $wp->install($args);

            if ($success['install']) {
                if (!empty($args['options'])) {
                    $success['setOptions'] = $wp->setOptions($args);
                }
                $success['setRewriteRules'] = $wp->setRewriteRules($args);
                $success['installedLatestTheme'] = $wp->installLatestDefaultTheme($args);
                $success['updated'] = $wp->update($args);
            }
        }

        if ($this->actionRequests->canDo('create_' . $this->environ->name . '_wp_set_permissions')) {
            $success['permissions'] = $wp->setPermissions($args);
        }

        if ($this->actionRequests->canDo('create_' . $this->environ->name . '_wp_htaccess')) {
            $success['htaccess'] = $this->terminal->htaccess()->prepend($args);
        }

        $success = !in_array(false, $success, true) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    public function delete(): ?bool
    {
        $this->mainStr();
        $this->logStart();
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;


        $success = $this->terminal->wp()->delete($args);

        if ($success && $this->environ->name === 'live') {
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
     * @param $args
     * @return bool
     */
    private function validate($args): bool
    {
        if (empty($args))
            return $this->logError('No args found.');
        if (empty($args['db']['name']))
            return $this->logError(ucfirst($this->environ->name) . ' environ DB name missing from config.');
        if (empty($args['db']['username']))
            return $this->logError(ucfirst($this->environ->name) . ' environ DB username missing from config.');
        if (empty($args['db']['password']))
            return $this->logError(ucfirst($this->environ->name) . ' environ DB password missing from config.');
        return true;
    }

    /**
     * @return array|bool
     */
    protected
    function getArgs()
    {
        $environName = $this->environ->name;
        $args = (array)$this->config->wordpress;
        $args['www'] = $this->config->environ->$environName->www ?? false;
        $args['directory'] = $this->environ->getEnvironDir('web');
        $args['email'] = $this->config->environ->$environName->admin_email;
        $args['title'] = $this->config->project->title ?? 'Insert Site Title Here';
        $args['url'] = $this->environ->getEnvironURL(true, true);
        $args['db'] = !empty($this->config->environ->$environName->db) ? (array)$this->config->environ->$environName->db : [];

        $args['cli']['config']['path'] = $this->config->environ->$environName->dirs->web_root->path ?? '';
        if ($environName !== 'local') {
            $cpanel = $this->environ->findcPanel();
            if (empty($cpanel['user']))
                return $this->logError(sprintf("Couldn't work out %s cPanel username.", $environName));
            $args['db']['name'] = $this->whm->db_prefix_check($args['db']['name'], $cpanel['user']);
            $args['db']['username'] = $this->whm->db_prefix_check($args['db']['username'], $cpanel['user']);
        }
        $args['live_url'] = $this->liveURL;

        $args['options'] = $this->environ->getWPOptions();

        //wp_config.php constants
        $wpConfig = (array)$args['config'];
        $wpConfigConstants = (array)($wpConfig['all'] ?? []);
        if (!empty($wpConfig[$environName]))
            $args['config'] = array_replace_recursive($wpConfigConstants, (array)$wpConfig[$environName]);


        return $args;
    }

    /**
     * @return string
     */
    protected
    function mainStr(): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        return $this->_mainStr[$action] = sprintf('%s WordPress and WP CLI', $this->environ->name);
    }
}