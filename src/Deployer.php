<?php

namespace Phoenix;

use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use phpseclib\Net\SSH2;
use Github\Client;

//use Phoenix\Functions;

/**
 * @property WHM $whm
 * @property \stdClass $config
 * @property Bitbucket $bitbucket
 * @property GithubClient $github
 * @property cPanelSubdomain $cPanelSubdomain
 * @property TerminalClient $terminal
 *
 * Class Deployer
 */
final class Deployer extends Base
{


    /**
     * @var null
     */
    protected static $_instance = null;

    /**
     * @var
     */
    private $_bitbucket;

    /**
     * @var
     */
    private $_github;

    /**
     * @var
     */
    private $_cPanelSubdomain;

    /**
     * @var
     */
    private $_terminal;

    /**
     * @var
     */
    private $_whm;

    /**
     * @var
     */
    public $actionRequests;

    /**
     * @var
     */
    public $configControl;

    /**
     * @var
     */
    public $staging_cpanel_key;

    /**
     * @var array
     */

    /**
     * @var Template
     */
    public $template;


    /**
     * @return null|Deployer
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Deployer constructor.
     */
    public function __construct()
    {
        parent::__construct();
        if (!defined('BASE_DIR')) define('BASE_DIR', dirname(__FILE__));
        if (!defined('CONFIG_DIR')) define('CONFIG_DIR', BASE_DIR . '/../configs/');
        if (!defined('BASH_WRAPPER')) define('BASH_WRAPPER', BASE_DIR . '/../bash/wrapper.sh');

        new Logging();
        $this->actionRequests = new ActionRequests();
        $this->configControl = new ConfigControl();
        $this->template = new Template();

        if (!empty($this->config->environ->local->wp_cli_cache_dir))
            putenv('WP_CLI_CACHE_DIR=' . $this->config->environ->local->wp_cli_cache_dir);
        return true;
    }

    /**
     * @return bool
     * @throws \Github\Exception\MissingArgumentException
     */
    public function run()
    {

        $this->template->get('header');

        $action = '';

        if ($this->actionRequests->can_do('update'))
            $action = 'update';
        elseif ($this->actionRequests->can_do('create'))
            $action = 'deploy';
        elseif ($this->actionRequests->can_do('transfer'))
            $action = 'transfer';
        elseif ($this->actionRequests->can_do('delete'))
            $action = 'delete';
        if (!empty($action)) {
            switch ($action) {
                case 'delete':
                    if ($this->actionRequests->can_do('delete_version_control'))
                        $this->versionControlMainRepo('delete');
                    if ($this->actionRequests->can_do('delete_live_stuff'))
                        $this->doRemoteEnvironStuff('delete', 'live');
                    if ($this->actionRequests->can_do('delete_staging_stuff'))
                        $this->doRemoteEnvironStuff('delete', 'staging');
                    if ($this->actionRequests->can_do('delete_local_stuff'))
                        $this->doLocalStuff('delete');
                    break;
                case 'deploy':
                    if ($this->actionRequests->can_do('create_version_control'))
                        $this->versionControlMainRepo('create');
                    if ($this->actionRequests->can_do('create_live_stuff'))
                        $this->doRemoteEnvironStuff('create', 'live');
                    if ($this->actionRequests->can_do('create_staging_stuff'))
                        $this->doRemoteEnvironStuff('create', 'staging');
                    if ($this->actionRequests->can_do('create_local_stuff'))
                        $this->doLocalStuff('create');
                    break;
                case 'update':
                    if ($this->actionRequests->can_do('update_live_stuff'))
                        $this->updateWP('live');
                    if ($this->actionRequests->can_do('update_staging_stuff'))
                        $this->updateWP('staging');
                    if ($this->actionRequests->can_do('update_local_stuff'))
                        $this->updateWP('local');
                    break;
                case 'transfer':

                    $wpdb = new TransferWPDB();
                    //transfer_wp_db_live_to_local
                    if ($this->actionRequests->can_do('transfer_wp_db_live_to_staging'))
                        $wpdb->transfer('live', 'staging', $this->terminal('live'), $this->terminal('staging'));
                    if ($this->actionRequests->can_do('transfer_wp_db_live_to_local'))
                        $wpdb->transfer('live', 'local', $this->terminal('live'), $this->terminal('local'));
                    if ($this->actionRequests->can_do('transfer_wp_db_staging_to_live'))
                        $wpdb->transfer('staging', 'live', $this->terminal('staging'), $this->terminal('live'));
                    if ($this->actionRequests->can_do('transfer_wp_db_staging_to_local'))
                        $wpdb->transfer('staging', 'local', $this->terminal('staging'), $this->terminal('local'));
                    if ($this->actionRequests->can_do('transfer_wp_db_local_to_live'))
                        $wpdb->transfer('local', 'live', $this->terminal('local'), $this->terminal('live'));
                    if ($this->actionRequests->can_do('transfer_wp_db_local_to_staging'))
                        $wpdb->transfer('local', 'staging', $this->terminal('local'), $this->terminal('staging'));
                    break;
            }
            $this->log(sprintf('<h2>Finished %s</h2>', ucfirst($this->actions[$action]['present'])), 'info');
            template()->get('reload');
        } else {
            template()->get('form');
            $unixUser = posix_getpwuid(posix_geteuid())['name'] ?? 'unknown';
            $unixUser = sprintf('Local server unix user is <strong>%s</strong>.', $unixUser);
            $root = $this->terminal('local')->root ?? 'unknown';
            $root = sprintf(' Local root directory is <strong>%s</strong>.', $root);
            $this->log($unixUser . $root, 'info');
            $diskspace = $this->getWHMDiskSpace();

            $plan = $this->config->environ->live->cpanel->create_account_args->plan ?? '';
            if (!empty($this->whm) && !empty($plan)) {
                $package_size = $this->whm->get_pkg_info($plan)['data']['pkg']['QUOTA'];
                if (($diskspace['total'] - $diskspace['allocated']) > $package_size)
                    $disk_message = 'You have';
                else
                    $disk_message = 'Not enough';
                $this->log(sprintf('Total disk space - <strong>%s</strong>MB. Disk used - <strong>%s</strong>MB. Allocated disk space - <strong>%s</strong>MB. %s unallocated disk space for a new <strong>%s</strong>MB cPanel account.',
                    $diskspace['total'], $diskspace['used'], $diskspace['allocated'], $disk_message, $package_size), 'info');
                $this->log('<h3>cPanel Staging Subdomains</h3>' . build_recursive_list((array)ph_d()->getStagingSubdomains()), 'light');
            }
        }
        $this->log('<h3>Input Config Array</h3>' . build_recursive_list((array)ph_d()->config), 'light');


        $this->template->get('footer');

        //exit();
        /*
        $rsa = new RSA();
        $rsa->setPassword('blegh');
        $rsa->setPublicKeyFormat( RSA::PUBLIC_FORMAT_OPENSSH );
        $key = extract( $rsa->createKey() ); // == $rsa->createKey(1024) where 1024 is the key size

        */


        //$this->terminal( 'live' )->git( 'blegh' );


        //$this->whm->import_key( $privatekey, 'jackthekey', '', 'imogen' );
        //$this->whm->import_key( $publickey, 'jackthekey', '', 'imogen' );

        return true;
    }

    /**
     * @return bool|null|Bitbucket
     */
    protected function bitbucket()
    {
        if (!empty($this->_bitbucket))
            return $this->_bitbucket;
        if (empty($this->config->version_control->bitbucket) || !class_exists('\Phoenix\Bitbucket'))
            return false;
        $bitbucket_args = $this->config->version_control->bitbucket;
        if (!isset($bitbucket_args->password, $bitbucket_args->team->name))
            return false;

        return $this->_bitbucket = new Bitbucket($this->config->version_control->bitbucket->password, $this->config->version_control->bitbucket->team->name);
    }

    /**
     * @return bool|WHM
     */
    protected function whm()
    {
        if (!empty($this->_whm))
            return $this->_whm;
        if (!isset($this->config->whm->user, $this->config->whm->token, $this->config->whm->query_url)
            || !class_exists('\Phoenix\Curl')
            || !class_exists('\Phoenix\WHM')
        )
            return false;
        $whm_curl = new Curl(
            $this->config->whm->query_url,
            array('type' => 'whm', 'user' => $this->config->whm->user, 'password' => $this->config->whm->token),
            false,
            false
        );
        if (!empty($whm_curl)) {
            $whm = new WHM($whm_curl);
            return $this->_whm = $whm;
        }
        return false;
    }

    /**
     * @return cPanelSubdomain
     */
    protected function cPanelSubdomain()
    {
        if (!empty($this->_cPanelSubdomain))
            return $this->_cPanelSubdomain;
        return $this->_cPanelSubdomain = new cPanelSubdomain(
            'staging',
            $this->config,
            $this->terminal('staging'),
            $this->actionRequests,
            $this->whm
        );
    }

    /**
     * @return bool|GithubClient
     */
    protected function github()
    {
        if (!empty($this->_github))
            return $this->_github;
        $error_string = "Can't connect to GitHub API.";
        if (empty($this->config->version_control->github->token)) {
            $this->log(sprintf("%s Token missing from config.", $error_string));
            return false;
        }
        if (empty($this->config->version_control->github->user)) {
            $this->log(sprintf("%s Github user missing from config.", $error_string));
            return false;
        }
        $github = new GithubClient();
        $client = new \Github\Client;
        $token = $this->config->version_control->github->token ?? '';
        $client->authenticate($token, null, \Github\Client::AUTH_HTTP_TOKEN);
        $github->client = $client;
        if (empty($this->config->version_control->github->user))
            $this->log("Won't be able to make many Github API calls. Github user missing from config.");
        else
            $github->user = $this->config->version_control->github->user;
        return $this->_github = $github;
    }

    /**
     * @param string $environment
     * @return bool|TerminalClient
     */
    public function terminal(string $environment = 'live')
    {
        if (!empty($this->_terminal->$environment))
            return $this->_terminal->$environment;
        if (empty($this->_terminal))
            $this->_terminal = new \stdClass();
        //$error_string = sprintf("Can't connect %s environment via SSH.", $environment);
        $terminal = new TerminalClient($environment);

        if ($environment != 'local') {
            $sftp = $this->get_phpseclib('sftp', $environment);
            if (!empty($sftp)) {
                $terminal->ssh = $sftp;
                //$terminal->ssh($sftp);
            }
        }
        return $this->_terminal->$environment = $terminal;
    }

    /**
     * @param string $protocol
     * @param string $environment
     * @return bool|SSH2|SFTP
     */
    function get_phpseclib($protocol = 'ssh', string $environment = 'live')
    {
        $message = sprintf("%s environment %s connection.", $environment, $protocol);
        //if ($environment != 'local') {

        $ssh_args = $this->getEnvironSSHArgs($environment);
        if (!empty($ssh_args)) {
            switch ($protocol) {
                case 'ssh':
                    $ssh = new SSH2($ssh_args->hostname, $ssh_args->port);
                    break;
                case 'sftp':
                    $ssh = new SFTP($ssh_args->hostname, $ssh_args->port);
                    break;
            }
        }
        $passphrase = $this->config->environ->local->ssh_keys->live->passphrase ?? '';
        $key_name = $this->config->environ->local->ssh_keys->live->key_name ?? '';
        if (!empty($passphrase) && !empty($key_name)) {
            $private_key_location = $this->config->environ->local->directory . $key_name;

            if (!file_exists($private_key_location)) {
                $this->terminal('local')->localSSHKey('create', $key_name, $passphrase);
                $this->terminal('local')->SSHConfig('create', $key_name, $passphrase);
            }
            if (file_exists($private_key_location . '.pub')) {
                $public_key = file_get_contents($private_key_location . '.pub');
                $this->whm->import_key($public_key, $key_name);
                $this->whm->authkey($key_name);
                $key = new RSA();
                $key->setPassword($passphrase);
                $key->loadKey(file_get_contents($private_key_location));
            }
        }
        if (!empty($ssh) && $ssh->login($ssh_args->username, $ssh_args->password)) {
            $this->log("Successfully authenticated " . $message, 'success');
            return $ssh;
        }
        //}
        $this->log("Couldn't authenticate " . $message);
        return false;
    }

    /**
     * @return array|bool|\stdClass
     */
    protected function config()
    {
        return $this->configControl->config;
    }

    /**
     * @param string $environment
     * @return array|bool
     */
    public function getEnvironSSHArgs(string $environment = 'live')
    {
        $error_string = sprintf("Can't connect %s environment via SSH.", $environment);
        switch ($environment) {
            case 'live':
                $live_domain = $this->config->environ->live->domain ?? '';
                $live_username = $this->config->environ->live->cpanel->account->username ?? '';
                if (!$this->findcPanelAccount($live_domain, $live_username)) {
                    $this->log($error_string . " Couldn't locate live cPanel account.");
                    return false;
                }
                $ssh_args = $this->config->environ->live->cpanel->ssh ?? array();
                break;
            case 'staging':
                $subdomain = $this->cPanelSubdomain->findSubdomaincPanel();
                if (!$subdomain) {
                    $slug = $this->config->environ->staging->cpanel->subdomain->slug ?? '';
                    $slug = !empty($slug) ? "<strong>" . $slug . "</strong> " : '';
                    $this->log(sprintf("%s Apparently subdomain %sdoesn't exist in your staging cPanel accounts.",
                        $error_string, $slug));
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
            $this->log(sprintf("%s %s cPanel account SSH args missing.", $error_string, ucfirst($environment)));
            return false;
        }
        return $ssh_args;
    }

    /**
     * @param string $action
     * @param string $environment
     * @return bool
     * @throws \Github\Exception\MissingArgumentException
     */
    public function doRemoteEnvironStuff($action = 'create', $environment = 'live')
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s environ stuff.", $environment)))
            return false;
        if (!in_array($environment, array('live', 'staging'))) {
            $this->log(sprintf("Can't %s stuff. Environment must be 'live' or 'staging'.", $action), 'error');
            return false;
        }
        $this->log(sprintf('<h2>%s nominated %s stuff.</h2>', ucfirst($this->actions[$action]['present']), $environment), 'info');
        $success = [];

        //actual site environment and db - logic mess
        if ($environment == 'live' && $this->actionRequests->can_do($action . '_live_site')) {
            $cPanelAccount = new cPanelAccount('live', $this->config, $this->terminal('staging'), $this->actionRequests, $this->whm);
            $success['live_cpanel_account'] = $cPanelAccount->$function_name();
        } elseif ($action == 'create' && $environment == 'staging' && $this->actionRequests->can_do($action . '_staging_subdomain'))
            $success['staging_subdomain'] = $this->cPanelSubdomain->$action();

        if ($this->actionRequests->can_do($action . '_' . $environment . '_db')
            && ($action == 'create' || $environment == 'staging' || ($action == 'delete' && !$this->actionRequests->can_do($action . '_live_site')))) {
            $databaseComponents = new DatabaseComponents($environment, $this->whm);
            $success['db'] = $databaseComponents->$action();
        }

        //shared actions
        if ($this->actionRequests->can_do($action . '_' . $environment . '_email_filters')) {
            $email_filters = new EmailFilters($environment, $this->whm);
            $success['email_filters'] = $email_filters->$action();
        }

        if (($action == 'create' && $this->actionRequests->can_do($action . '_' . $environment . '_initial_git_commit'))
            || $this->actionRequests->can_do($action . '_' . $environment . '_version_control')) {
            $versionControl = new EnvironVersionControl(
                $environment,
                $this->terminal($environment),
                $this->github,
                $this->whm
            );
            if ($this->actionRequests->can_do($action . '_' . $environment . '_version_control'))
                $success['setup_version_control'] = $versionControl->$action();
        }


        if ($this->actionRequests->can_do($action . '_' . $environment . '_wp')) {
            $wp = new WordPress($environment, $this->terminal($environment), $this->actionRequests, $this->whm);
            $success['wp'] = $wp->$action();
        }

        if ($action == 'create' && $this->actionRequests->can_do($action . '_' . $environment . '_initial_git_commit'))
            $success['synced'] = $versionControl->sync();

        if ($action == 'delete' && && $environment == 'staging' && $this->actionRequests->can_do($action . '_staging_subdomain'))
            $success['staging_subdomain'] = $this->cPanelSubdomain->$action();

        $success = !in_array(false, $success) ? true : false;

        if ($success) {
            $this->log(sprintf('Successfully %s nominated %s stuff.', $this->actions[$action]['past'], $environment), 'success');
            return true;
        }
        $this->log(sprintf('Something may have gone wrong while %s nominated %s stuff.', $this->actions[$action]['present'], $environment), 'error');
        return false;
    }

    /**
     * @param string $action
     */
    public function doLocalStuff($action = 'create')
    {
        if ($this->actionRequests->can_do($action . "_local_version_control") ||
            ($action == 'create' && $this->actionRequests->can_do("create_local_initial_git_commit")))
            $versionControl = new EnvironVersionControl(
                'local',
                $this->terminal('local'),
                $this->github,
                $this->whm
            );

        if ($this->actionRequests->can_do($action . "_local_version_control"))
            $versionControl->$action();


        if ($this->actionRequests->can_do($action . "_local_virtual_host")) {
            $domain = $this->config->environ->local->domain ?? '';
            $sitesAvailable = $this->config->environ->local->dirs->sites_available->path ?? '';

            $webDir = $this->getEnvironDir('local', 'web');
            $webOwner = $this->config->environ->local->dirs->web->owner ?? '';
            $webGroup = $this->config->environ->local->dirs->web->group ?? '';

            $logDir = $this->getEnvironDir('local', 'log');

            $localDirSetup = $this->terminal('local')->LocalProjectDirSetup();
            $localDirSetup->setProjectArgs([
                'dir' => $this->getEnvironDir('local', 'project') ?? '',
                'owner' => $this->config->environ->local->dirs->project->owner ?? '',
                'group' => $this->config->environ->local->dirs->project->group ?? '',
            ]);
            $logDirSuccess = $localDirSetup->$action([
                'dir' => $logDir,
                'owner' => $webOwner ?? '',
                'group' => $webGroup ?? '',
                'purpose' => 'log'
            ]);
            $localDirSetup->$action([
                'dir' => $webDir,
                'owner' => $webOwner ?? '',
                'group' => $webGroup ?? '',
                'purpose' => 'web'
            ]);


            $virtualHostArgs = [
                'domain' => $domain,
                'conf_path' => $sitesAvailable . $domain . '.conf',
                'web_dir' => $webDir,
                'admin_email' => $this->config->environ->local->email ?? '',
                'log_dir' => $logDir
            ];
            $this->terminal('local')->localVirtualHost()->$action($virtualHostArgs);


            if (!$logDirSuccess && $action == 'delete') {
                $this->terminal('local')->LocalProjectDirSetup()->delete([
                    'dir' => $logDir . 'access.log',
                    'purpose' => 'access log'
                ]);
                $this->terminal('local')->LocalProjectDirSetup()->delete([
                    'dir' => $logDir . 'error.log',
                    'purpose' => 'error log'
                ]);
            }
        }

        if ($this->actionRequests->can_do($action . "_local_database_components")) {
            $pdoWrap = null;
            try {
                $pdoWrap = new PDOWrap([
                    'host' => '127.0.0.1',
                    'user' => $this->config->environ->local->db->root->username ?? '',
                    'password' => $this->config->environ->local->db->root->password ?? '',
                    'port' => $this->config->environ->local->db->port ?? 3306
                ]);
            } catch (\PDOException $e) {
                $pdoWrap = $e;
            }

            $client = new DBComponentsClient('local', $pdoWrap);

            $databaseComponents = new DatabaseComponents('local', null, $client);
            $databaseComponents->$action();
        }

        if ($this->actionRequests->can_do($action . "_local_wp")) {
            $wp = new WordPress('local', $this->terminal('local'), $this->actionRequests);
            $wp->$action;
        }

        if ($action == 'create' && $this->actionRequests->can_do("create_local_initial_git_commit")) {
            $versionControl->sync();
        }
    }

    /**
     * @param string $environment
     * @return bool
     */
    function findEnvironcPanel(string $environment = 'live')
    {
        $error_string = sprintf("Can't find %s environment cPanel.", $environment);
        switch ($environment) {
            case 'live':
                $cpanel_username = $this->config->environ->$environment->cpanel->account->username ?? '';
                $cpanel_domain = $this->config->environ->$environment->domain ?? '';
                if (empty($cpanel_domain) || empty($cpanel_username)) {
                    $this->log($error_string . " Domain and/or cPanel username are missing from config.", 'error');
                    return false;
                }
                $cPanel_account = $this->findcPanelAccount($cpanel_domain, $cpanel_username);
                break;
            case 'staging':
                $slug = $this->config->environ->staging->cpanel->subdomain->slug ?? '';
                if (empty($slug)) {
                    $this->log($error_string . " Staging cPanel subdomain slug missing from config.", 'error');
                    return false;
                }
                $cPanel_account = $this->cPanelSubdomain->findSubdomaincPanel();
                break;
        }
        return $cPanel_account ?? false;
    }




    /**
     * @param string $action
     * @return bool
     */
    function versionControlMainRepo(string $action = 'create')
    {
        if (!$this->validate_action($action, array('create', 'delete'), "Can't do main version control repository stuff."))
            return false;
        $repo_name = $this->config->version_control->repo_name ?? '';
        if (empty($repo_name)) {
            $this->log(sprintf("Can't %s version control main repository. Repository name missing from config.", $action));
            return false;
        }
        $domain = $this->config->environ->live->domain ?? '';
        $repository = $this->github->repo()->$action($repo_name, $domain);
        if (!empty($repository)) {
            $this->log(sprintf('Successfully %s version control main repository.', $this->actions[$action]['past']), 'success');
            return true;
        }
        $this->log(sprintf("Something may have gone wrong while %s version control main repository.", $this->actions[$action]['present']), 'error');
        return false;
    }

    /**
     * @param string $environ
     * @param bool $scheme
     * @param bool $prefix
     * @return bool|string
     */
    public function getEnvironURL(string $environ = 'live', bool $scheme = false, bool $prefix = false)
    {

        $error_string = sprintf("Can't get %s environment url.", $environ);
        switch ($environ) {
            case 'staging':
                $staging_cpanel = $this->cPanelSubdomain->findSubdomaincPanel();
                if (!$staging_cpanel)
                    return false;
                $slug = $this->config->environ->staging->cpanel->subdomain->slug ?? '';
                if (empty($slug)) {
                    $this->log($error_string . " Subdomain slug missing.", 'error');
                    return false;
                }
                $url = $slug . '.' . $staging_cpanel['domain'];
                //$url = $this->whm->get_subdomain($slug)['domain'];
                break;
            case 'local':
            case 'live':
            default:
                if (empty($this->config->environ->$environ->domain)) {
                    $this->log($error_string . " Domain missing from config.", 'error');
                    return false;
                }
                $url = $this->config->environ->$environ->domain;
                break;
        }

        if ($prefix && !empty($this->config->environ->$environ->www)) {
            $url = 'www.' . $url;
        }
        if ($scheme) {
            //$protocol = $environ == 'local' ? 'http://' : 'https://';
            $protocol = 'https://';
            if (strpos($protocol, $url) !== 0)
                $url = $protocol . $url;
        }
        return $url;
    }

    /**
     * @param string $environ
     * @param string $type
     * @return bool|string
     */
    public function getEnvironDir(string $environ = 'live', $type = 'web')
    {
        if (empty($environ))
            return false;
        $error_string = sprintf("Couldn't determine %s environment %s directory.", $environ, $type);
        $root = '';
        if ($environ != 'local') {
            $root = $this->terminal($environ)->root;
            if (empty($root)) {
                $this->log($error_string . " Couldn't get SSH root directory.");
                return false;
            }
        }

        switch ($environ) {
            case 'live':
                switch ($type) {
                    case 'web':
                        $dir = '/public_html';
                        break;
                    case 'worktree':
                        $dir = $this->config->environ->$environ->version_control->worktree_dir ?? '/public_html';
                        break;
                    case 'git':
                        $dir = $this->config->environ->$environ->version_control->repo_dir ?? '/git/website';
                        break;
                    default:
                        $this->log($error_string . " Type <strong>" . $type . "</strong> in <strong>" . $environ . "</strong> environ not accounted for.");
                        return false;
                        break;
                }
                break;
            case 'staging':
                switch ($type) {
                    case 'web':
                        $dir = $this->config->environ->$environ->cpanel->subdomain->directory ?? '';
                        break;
                    case 'worktree':
                        $dir = $this->config->environ->$environ->version_control->worktree_dir ??
                            $this->config->environ->$environ->cpanel->subdomain->directory ?? '';
                        break;
                    case 'git':
                        if (!empty($this->config->environ->$environ->version_control->repo_dir))
                            $dir = $this->config->environ->$environ->version_control->repo_dir;
                        else {
                            $repo_name = $this->config->version_control->repo_name ?? '';
                            if (empty($repo_name)) {
                                $this->log($error_string . ' Version control repo name missing from config.');
                                return false;
                            }
                            $dir = '/git/' . $repo_name . '/website';
                        }
                        break;
                    case 'github_webhook_endpoint_config':
                        $dir = '/.github_webhook_configs';
                        break;
                    default:
                        $this->log($error_string . " Type <strong>" . $type . "</strong> in <strong>" . $environ . "</strong> environ not accounted for.");
                        return false;
                        break;
                }
                break;
            case 'local':
                $rootWebDir = $this->config->environ->local->dirs->web_root->path ?? '';
                if (empty($rootWebDir)) {
                    $this->log($error_string . ' Root web dir missing from config.');
                    return false;
                }

                $projectDirName = $this->config->environ->$environ->dirs->project->name ?? $this->config->project->name ?? '';

                if (empty($projectDirName)) {
                    $this->log($error_string . ' Project name missing from config.');
                    return false;
                }
                $dir = $rootWebDir . $projectDirName;

                $public_html = 'public';
                switch ($type) {
                    case 'web':
                        $dir .= $this->config->environ->$environ->dirs->web->path ?? '/Project/' . $public_html;
                        break;
                    case 'git':
                        $dir .= $this->config->environ->$environ->dirs->repo->path ?? '/Project/' . $public_html;
                        break;
                    case 'worktree':
                        $dir .= $this->config->environ->$environ->dirs->worktree->path ?? '/Project/' . $public_html;
                        break;
                    case 'log':
                        $dir .= $this->config->environ->$environ->dirs->log->path ?? '/Project/';
                        break;
                    case 'project':
                        break;
                    default:
                        $this->log($error_string . " Type <strong>" . $type . "</strong> in <strong>" . $environ . "</strong> environ not accounted for.");
                        return false;
                        break;
                }
                break;
        }
        if (empty($dir)) {
            $this->log($error_string);
            return false;
        }
        $directory = $root . $dir;
        return $directory;
    }

    /**
     * @return array
     */
    public function getWHMDiskSpace()
    {
        if (empty($this->whm))
            return false;
        $accounts = $this->whm->get_cpanel_accounts();
        $diskused = 0;
        $disklimit = 0;
        foreach ($accounts as $account) {
            $diskused += intval($account['diskused']);
            $disklimit += intval($account['disklimit']);
        }
        $diskusage = $this->whm->get_disk_usage();
        $disktotal = $diskusage['total'] ?? 100000;
        return array(
            'used' => $diskused,
            'allocated' => $disklimit,
            'total' => $disktotal
        );
    }

    /**
     * @param string $environment
     * @return bool
     */
    public function updateWP($environment = 'live')
    {
        $mainStr = sprintf(" WordPress in %s environment. ", $environment);
        $this->log('<h2>Updating ' . $mainStr . '</h2>', 'info');
        $directory = $this->getEnvironDir($environment, 'web');
        $errorString = "Can't update " . $mainStr;
        if (empty($directory)) {
            $this->log($errorString . " Couldn't get web directory.");
            return false;
        }
        $WPCLI = $this->terminal->wp_cli()->installOrUpdate();
        if (!$WPCLI) {
            $this->log(sprintf('Failed to update WordPress in %s environment.', $environment));
            return false;
        }
        $backupDB = new TransferWPDB();
        $backup = $backupDB->backup($environment, $this->terminal($environment));
        if ($backup) {
            $gitPull = $this->terminal($environment)->gitBranch()->pull(['worktree' => $directory, 'branch' => 'dev']);
            if ($gitPull) {
                $wp_update = $this->terminal($environment)->wp()->update(['directory' => $directory]);
                if ($wp_update)
                    $git_commit = $this->terminal($environment)->gitBranch()->commit($directory, 'dev');
            }
        }
        if (!empty($backup) && !empty($gitPull) && !empty($wp_update) && !empty($git_commit)) {
            $this->log(sprintf('Successfully updated WordPress in %s environment.', $environment), 'success');
            return true;
        }
        $this->log(sprintf('Failed to update WordPress in %s environment.', $environment));
        return false;
    }
}

/**
 * Main instance of Deployer.
 *
 */
function ph_d()
{
    return Deployer::instance();
}


