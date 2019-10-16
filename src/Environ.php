<?php

namespace Phoenix;

/**
 * Class Environ
 *
 * @package Phoenix
 */
class Environ extends AbstractDeployer
{
    /**
     * @var stdClass
     */
    public $config;

    /**
     * Name of environment
     *
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var TerminalClient
     */
    protected $terminal;

    /**
     * @param TerminalClient $terminal
     */
    public function setTerminal(TerminalClient $terminal): void
    {
        $this->terminal = $terminal;
    }

    /**
     * Environ constructor.
     *
     * @param string $environName
     * @param \stdClass $config
     */
    public function __construct(string $environName = '', \stdClass $config = null)
    {
        $this->name = $environName;
        $this->config = $config;
        parent::__construct();
    }

    /**
     * @return object|bool
     */
    public function getSSHArgs()
    {
        $errorString = sprintf("Can't connect %s environment via SSH.", $this->name);
        switch($this->name) {
            case 'live':
                $ssh_args = $this->config->environ->live->cpanel->ssh ?? array();
                break;
            case 'staging':
                $subdomain = $this->getSubdomaincPanel();
                if (!$subdomain) {
                    $slug = $this->config->environ->staging->cpanel->subdomain->slug ?? '';
                    $slug = !empty($slug) ? '<strong>' . $slug . '</strong> ' : '';
                    $this->log(sprintf("%s Apparently subdomain %sdoesn't exist in your staging cPanel accounts.",
                        $errorString, $slug));
                    return false;
                }
                $domain = $subdomain['domain'];
                $ssh_args = $this->config->environ->staging->cpanel->accounts->$domain->ssh ?? array();
                break;
            case 'local':
                $ssh_args = $this->config->environ->local->ssh ?? array();
                break;
        }
        if (empty($ssh_args) && !isset($ssh_args->hostname, $ssh_args->port)) {
            $this->log(sprintf('%s %s cPanel account SSH args missing.', $errorString, ucfirst($this->name)));
            return false;
        }
        return $ssh_args;
    }

    /**
     * @param string $type
     * @return bool|string
     */
    public function getEnvironDir(string $type = 'web')
    {
        if (empty($this->name)) {
            return false;
        }
        $errorString = sprintf("Couldn't determine %s environment %s directory.", $this->name, $type);

        $root = $this->terminal->root ?? '';
        if (empty($root) && $this->name !== 'local') {
            $this->log($errorString . " Couldn't get SSH root directory.");
            return false;
        }
        $environName = $this->name;

        switch($this->name) {
            case 'live':
                switch($type) {
                    case 'web':
                        $dir = '/public_html';
                        break;
                    case 'worktree':
                        $dir = $this->config->environ->$environName->version_control->worktree_dir ?? '/public_html';
                        break;
                    case 'git':
                        $dir = $this->config->environ->$environName->version_control->repo_dir ?? '/git/website';
                        break;
                    default:
                        $this->log($errorString . ' Type <strong>' . $type . '</strong> in <strong>' . $this->name . '</strong> environ not accounted for.');
                        return false;
                        break;
                }
                break;
            case 'staging':
                switch($type) {
                    case 'web':
                        $dir = $this->config->environ->$environName->cpanel->subdomain->directory ?? '';
                        break;
                    case 'worktree':
                        $dir = $this->config->environ->$environName->version_control->worktree_dir ??
                            $this->config->environ->$environName->cpanel->subdomain->directory ?? '';
                        break;
                    case 'git':
                        if (!empty($this->config->environ->$environName->version_control->repo_dir))
                            $dir = $this->config->environ->$environName->version_control->repo_dir;
                        else {
                            $repo_name = $this->config->version_control->repo_name ?? '';
                            if (empty($repo_name)) {
                                $this->log($errorString . ' Version control repo name missing from config.');
                                return false;
                            }
                            $dir = '/git/' . $repo_name . '/website';
                        }
                        break;
                    case 'github_webhook_endpoint_config':
                        $dir = '/.github_webhook_configs';
                        break;
                    default:
                        $this->log($errorString . ' Type <strong>' . $type . '</strong> in <strong>' . $this->name . '</strong> environ not accounted for.');
                        return false;
                        break;
                }
                break;
            case 'local':
                $rootWebDir = $this->config->environ->local->dirs->web_root->path ?? '';
                if (empty($rootWebDir)) {
                    $this->log($errorString . ' Root web dir missing from config.');
                    return false;
                }

                $projectDirName = $this->config->environ->$environName->dirs->project->name ?? $this->config->project->name ?? '';

                if (empty($projectDirName)) {
                    $this->log($errorString . ' Project name missing from config.');
                    return false;
                }
                $dir = $rootWebDir . $projectDirName;

                $public_html = 'public';
                switch($type) {
                    case 'web':
                        $dir .= $this->config->environ->$environName->dirs->web->path ?? '/Project/' . $public_html;
                        break;
                    case 'git':
                        $dir .= $this->config->environ->$environName->dirs->repo->path ?? '/Project/' . $public_html;
                        break;
                    case 'worktree':
                        $dir .= $this->config->environ->$environName->dirs->worktree->path ?? '/Project/' . $public_html;
                        break;
                    case 'log':
                        $dir .= $this->config->environ->$environName->dirs->log->path ?? '/Project/';
                        break;
                    case 'project':
                        break;
                    default:
                        $this->log($errorString . ' Type <strong>' . $type . '</strong> in <strong>' . $this->name . '</strong> environ not accounted for.');
                        return false;
                        break;
                }
                break;
        }
        if (empty($dir)) {
            $this->log($errorString);
            return false;
        }
        return $root . $dir;
    }

    /**
     * @param bool $scheme
     * @param bool $prefix
     * @return bool|string
     */
    public function getEnvironURL(bool $scheme = false, bool $prefix = false)
    {
        $environName = $this->name;
        $errorString = sprintf("Can't get %s environment url.", $this->name);
        switch($this->name) {
            case 'staging':

                $staging_cpanel = $this->getSubdomaincPanel();
                if (!$staging_cpanel) {
                    return false;
                }
                $slug = $this->config->environ->staging->cpanel->subdomain->slug ?? '';
                if (empty($slug)) {
                    $this->log($errorString . ' Subdomain slug missing.');
                    return false;
                }
                $url = $slug . '.' . $staging_cpanel['domain'];
                break;
            case 'local':
            case 'live':
            default:
                if (empty($this->config->environ->$environName->domain)) {
                    $this->log($errorString . ' Domain missing from config.');
                    return false;
                }
                $url = $this->config->environ->$environName->domain;
                break;
        }
        if ($prefix && !empty($this->config->environ->$environName->www)) {
            $url = 'www.' . $url;
        }
        if ($scheme) {
            //$protocol = $this->name == 'local' ? 'http://' : 'https://';
            $protocol = 'https://';
            if (strpos($protocol, $url) !== 0)
                $url = $protocol . $url;
        }
        return $url;
    }

    /**
     * Get custom WordPress DB options to set when installing or transferring WordPress
     *
     * @param string $type
     * @return array|bool
     */
    public function getWPOptions($type = 'fresh_install')
    {
        if (empty($type))
            return false;
        if (empty($this->config->wordpress->options->$type))
            return false;
        $anyEnvironOptions = (array)($this->config->wordpress->options->$type->any ?? []);
        $environName = $this->name;
        $specificEnvironOptions = (array)($this->config->wordpress->options->$type->$environName ?? []);
        //convert object to array
        $options = [];
        foreach (array_merge($anyEnvironOptions, $specificEnvironOptions) as $key => $option) {
            $options[$key] = (array)$option;
        }
        return $options;
    }

    /**
     * @return array|bool
     */
    public function findcPanel()
    {
        switch($this->name) {
            case 'live':
                $cPanelAccount = $this->getcPanel();
                break;
            case 'staging':
                $cPanelAccount = $this->getSubdomaincPanel();
                break;
            case 'local':
                return false;
                break;
        }
        if (empty($cPanelAccount))
            $this->log('Can\'t find ' . $this->name . ' environment cPanel.');
        return $cPanelAccount ?? false;
    }


    /**
     * @return array|bool
     */
    protected
    function getArgs()
    {
        $environName = $this->name;
        $args['domain'] = $this->config->environ->$environName->domain ?? '';
        $args['protected_accounts'] = $this->config->whm->protected_accounts ?? array();
        $args['username'] = $this->config->environ->$environName->cpanel->account->username ?? '';

        $args['cpanel'] = $this->config->environ->$environName->cpanel ?? array();
        return $args;
    }
}
