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
     * @var
     */
    public $staging_cpanel_account;
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
    function __construct()
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
    function run()
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
                        $this->do_environ_stuff('delete', 'live');
                    if ($this->actionRequests->can_do('delete_staging_stuff'))
                        $this->do_environ_stuff('delete', 'staging');
                    if ($this->actionRequests->can_do('delete_local_stuff'))
                        $this->localStuff('delete');
                    break;
                case 'deploy':
                    if ($this->actionRequests->can_do('create_version_control'))
                        $this->versionControlMainRepo('create');
                    if ($this->actionRequests->can_do('create_live_stuff'))
                        $this->do_environ_stuff('create', 'live');
                    if ($this->actionRequests->can_do('create_staging_stuff'))
                        $this->do_environ_stuff('create', 'staging');
                    if ($this->actionRequests->can_do('create_local_stuff'))
                        $this->localStuff('create');
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
            $root = !empty($this->terminal('local')->root) ? $this->terminal('local')->root : 'unknown';
            $root = sprintf(' Local root directory is <strong>%s</strong>.', $root);
            $this->log($unixUser . $root, 'info');
            $diskspace = $this->getWHMDiskSpace();

            $plan = $this->config->environ->live->cpanel->create_account_args->plan ?? '';
            $package_size = $this->whm->get_pkg_info($plan)['data']['pkg']['QUOTA'];
            if (($diskspace['total'] - $diskspace['allocated']) > $package_size)
                $disk_message = 'You have';
            else
                $disk_message = 'Not enough';
            $this->log(sprintf('Total disk space - <strong>%s</strong>MB. Disk used - <strong>%s</strong>MB. Allocated disk space - <strong>%s</strong>MB. %s unallocated disk space for a new <strong>%s</strong>MB cPanel account.',
                $diskspace['total'], $diskspace['used'], $diskspace['allocated'], $disk_message, $package_size), 'info');
            $this->log('<h3>cPanel Staging Subdomains</h3>' . build_recursive_list((array)ph_d()->get_staging_subdomains()), 'light');
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

        $ssh_args = $this->get_environ_ssh_args($environment);
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
        //$passphrase = $this->config->environ->local->ssh_keys->live->passphrase ?? '';
        //$key_name = $this->config->environ->local->ssh_keys->live->key_name ?? '';
        if (!empty($passphrase) && !empty($key_name)) {
            $private_key_location = $this->config->environ->local->directory . $key_name;

            if (!file_exists($private_key_location)) {
                //$this->terminal('local')->localSSHKey('create', $key_name, $passphrase);
                //$this->terminal('local')->SSHConfig('create', $key_name, $passphrase);
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
        return $this->configControl->getConfig();
    }

    /**
     * @param string $environment
     * @return array|bool
     */
    function get_environ_ssh_args(string $environment = 'live')
    {
        $error_string = sprintf("Can't connect %s environment via SSH.", $environment);
        switch ($environment) {
            case 'live':
                $live_domain = $this->config->environ->live->domain ?? '';
                $live_username = $this->config->environ->live->cpanel->account->username ?? '';
                if (!$this->find_cpanel_account($live_domain, $live_username)) {
                    $this->log($error_string . " Couldn't locate live cPanel account.");
                    return false;
                }
                $ssh_args = $this->config->environ->live->cpanel->ssh ?? array();
                break;
            case 'staging':
                $subdomain = $this->find_staging_cpanel();
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
    function do_environ_stuff($action = 'create', $environment = 'live')
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s environ stuff.", $environment)))
            return false;
        if (!in_array($environment, array('live', 'staging'))) {
            $this->log(sprintf("Can't %s stuff. Environment must be 'live' or 'staging'.", $action), 'error');
            return false;
        }
        $this->log(sprintf('<h2>%s nominated %s stuff.</h2>', ucfirst($this->actions[$action]['present']), $environment), 'info');

        $actions = array(
            'live_site' => $this->actionRequests->can_do($action . '_live_site'),
            'subdomain' => $this->actionRequests->can_do($action . '_staging_subdomain'),
            'db' => $this->actionRequests->can_do($action . '_' . $environment . '_db'),
            'email_filters' => $this->actionRequests->can_do($action . '_' . $environment . '_email_filters'),
            'version_control' => $this->actionRequests->can_do($action . '_' . $environment . '_version_control'),
            'wp' => $this->actionRequests->can_do($action . '_' . $environment . '_wp'),
            'initial_commit' => $this->actionRequests->can_do($action . '_' . $environment . '_initial_git_commit')
        );

        //actual site environment and db - logic mess
        if ($environment == 'live' && $actions['live_site']) {
            //$live_account = $this->$action . '_live_cpanel_account'();
            $function_name = $action . '_live_cpanel_account';
            $live_cpanel_account = $this->$function_name();
            //$live_account = call_user_func(array($this, $action . '_live_cpanel_account'));
        } elseif ($action == 'create' && $actions['subdomain'] && $environment == 'staging') {
            $function_name = $action . '_staging_subdomain';
            $staging_subdomain = $this->$function_name();
        }

        if ($actions['db'] && ($action == 'create' || $environment == 'staging' || ($action == 'delete' && !$actions['live_site']))) {
            $databaseComponents = new DatabaseComponents($environment, $this->whm);
            $db = $databaseComponents->$action();
        }

        //shared actions
        if ($actions['email_filters']) {
            $email_filters = new EmailFilters($environment, $this->whm);
            $email_filters = $email_filters->$action;
        }

        if (($action == 'create' && $actions['initial_commit']) || $actions['version_control'])
            $versionControl = new EnvironVersionControl(
                $environment,
                $this->terminal($environment),
                $this->github,
                $this->whm
            );

        if ($actions['version_control'])
            $setupVersionControl = $versionControl->$action();

        if ($this->actionRequests->can_do($action . '_' . $environment . '_wp')) {
            $wp = new WordPress($environment, $this->terminal($environment), $this->actionRequests, $this->whm);
            $wp = $wp->$action();
        }

        if ($action == 'create' && $actions['initial_commit']) {
            $synced = $versionControl->sync();
        }

        if ($action == 'delete' && $actions['subdomain'] && $environment == 'staging') {
            $function_name = $action . '_staging_subdomain';
            $staging_subdomain = $this->$function_name();
        }

        if (((!$actions['live_site'] || !empty($live_cpanel_account)) || (!$actions['subdomain'] || !empty($staging_subdomain)))
            && (!$actions['db'] || !empty($db) || ($action == 'delete' && !empty($live_cpanel_account)))
            && (!$actions['email_filters'] || !empty($email_filters))
            && (!$actions['version_control'] || !empty($setupVersionControl))
            && (!$actions['wp'] || !empty($wp))
            && (!$actions['initial_commit'] || !empty($synced))
        ) {
            $this->log(sprintf('Successfully %s nominated %s stuff.', $this->actions[$action]['past'], $environment), 'success');
            return true;
        }
        $this->log(sprintf('Something may have gone wrong while %s nominated %s stuff.', $this->actions[$action]['present'], $environment), 'error');
        return false;

    }

    /**
     * @return bool
     */
    function create_live_cpanel_account()
    {
        $this->log("<h4>Creating live cPanel account<h4>", 'info');
        if (!isset($this->config->environ->live->domain, $this->config->environ->live->cpanel->account->username)) {
            $this->log("Can't create live cPanel account. Domain and/or cPanel username config missing.", 'error');
            return false;
        }
        $domain = $this->config->environ->live->domain;
        $username = $this->config->environ->live->cpanel->account->username;

        $this->log(sprintf('First we check if a cPanel account with domain <strong>%s</strong> or username <strong>%s</strong> already exists.',
            $domain, $username), 'info');
        if (!empty($this->whm->get_cpanel_account($username))) {
            $this->log(sprintf("Can't create live cPanel account. cPanel account with user <strong>%s</strong> already exists.", $username), 'error');
            return false;
        }
        if (!empty($this->whm->get_cpanel_account($domain, 'domain'))) {
            $this->log(sprintf("Can't create live cPanel account. cPanel account with domain <strong>%s</strong> already exists.", $domain), 'error');
            return false;
        }
        $this->log("No pre-existing live cPanel account so let's create a new one.", 'info');
        $create_account_args = $this->config->environ->live->cpanel->create_account_args ?? array();
        if ($this->whm->create_cpanel_account($username, $domain, (array)$create_account_args))
            return true;
        return false;
    }


    /**
     * @return bool
     */
    private function create_staging_subdomain()
    {
        $this->log("<h4>Creating staging subdomain.</h4>", 'info');

        $config = $this->config->environ->staging->cpanel ?? null;
        $error_string = "Can't create staging cPanel subdomain. ";
        if (!isset($config->subdomain->slug, $config->accounts)) {
            $this->log($error_string . "Staging accounts and/or subdomain slug missing.", 'error');
            return false;
        }
        if (empty($config->subdomain->directory)) {
            $this->log($error_string . "Directory input missing.", 'error');
            return false;
        }
        $staging_cpanel_account = $this->find_staging_cpanel();
        if ($staging_cpanel_account) {
            $this->log(sprintf("%sSubdomain with slug <strong>%s</strong> already exists in cPanel account with user <strong>%s</strong>. ",
                $error_string, $config->subdomain->slug, $staging_cpanel_account['user']), 'warning');
            return false;
        }
        //search for the lowest # staging cPanel account with enough available space and inodes
        $staging_cpanel_key = $this->decide_subdomain_cpanel_account($config->accounts);

        if (empty($staging_cpanel_key)) {
            $this->log(sprintf("%s Couldn't find staging cPanel account to use.", $error_string), 'error');
            return false;
        }
        $this->log(sprintf("Subdomain with slug <strong>%s</strong> doesn't exist so attempt to create a new subdomain.", $config->subdomain->slug), 'success');

        if ($this->whm->create_subdomain($config->subdomain->slug, $config->accounts->$staging_cpanel_key->domain, $config->subdomain->directory)) {
            $this->log('', 'success');
            return true;
        }
        $this->log('Something went wrong creating staging subdomain.');
        return false;

    }

    /**
     * @param string $action
     */
    function localStuff($action = 'create')
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

            $webDir = $this->get_environ_dir('local', 'web');
            $webOwner = $this->config->environ->local->dirs->web->owner ?? '';
            $webGroup = $this->config->environ->local->dirs->web->group ?? '';

            $logDir = $this->get_environ_dir('local', 'log');

            $localDirSetup = $this->terminal('local')->LocalProjectDirSetup();
            $localDirSetup->setProjectArgs([
                'dir' => $this->get_environ_dir('local', 'project') ?? '',
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
     * @param string $subdomain_slug
     * @param $cpanel_accounts
     * @return bool
     */
    function find_staging_cpanel(string $subdomain_slug = '', array $cpanel_accounts = array())
    {
        $error_string = "Can't find staging cPanel subdomain.";
        if (!empty($this->staging_cpanel_account))
            return $this->staging_cpanel_account;
        if (empty($subdomain_slug)) {
            if (empty($this->config->environ->staging->cpanel->subdomain->slug)) {
                $this->log($error_string . " cPanel subdomain slug missing.");
                return false;
            }
            $subdomain_slug = $this->config->environ->staging->cpanel->subdomain->slug;
        }
        if (empty($cpanel_accounts)) {
            if (empty($this->config->environ->staging->cpanel->accounts)) {
                $this->log($error_string . " cPanel accounts to search missing.");
                return false;
            }
            $cpanel_accounts = $this->config->environ->staging->cpanel->accounts;
        }
        foreach ($cpanel_accounts as $key => $cpanel_account) {
            $subdomain = $this->whm->get_subdomain($subdomain_slug, $cpanel_account->username);
            if ($subdomain) {
                $cpanel_account = $this->whm->get_cpanel_account($subdomain['user']);
                return $this->staging_cpanel_account = $cpanel_account;
            }
        }
        return false;
    }

    /**
     * @param string $environment
     * @return bool
     */
    function find_environ_cpanel(string $environment = 'live')
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
                $cPanel_account = $this->find_cpanel_account($cpanel_domain, $cpanel_username);
                break;
            case 'staging':
                $slug = $this->config->environ->staging->cpanel->subdomain->slug ?? '';
                if (empty($slug)) {
                    $this->log($error_string . " Staging cPanel subdomain slug missing from config.", 'error');
                    return false;
                }
                $cPanel_account = $this->find_staging_cpanel();
                break;
        }
        return $cPanel_account ?? false;
    }

    /**
     * @param $cpanel_accounts
     * @return bool|int|string
     */
    function decide_subdomain_cpanel_account($cpanel_accounts)
    {
        if (empty($cpanel_accounts))
            return false;
        $min_inodes = $this->config->environ->staging->cpanel->subdomain->min_inodes ?? 25000;
        $min_megabytes = $this->config->environ->staging->cpanel->subdomain->min_megabytes ?? 2500;

        foreach ($cpanel_accounts as $key => $account) {
            $quota = $this->whm->get_quota_info($account->username);

            $log_chunk = sprintf(' add the staging subdomain to the cPanel account with domain <strong>%s</strong> and username <strong>%s</strong>.',
                $account->domain, $account->username);

            if (empty($quota['inodes_remain']) || empty($quota['megabytes_remain'])) {
                $this->log("Can't" . $log_chunk . " Couldn't find out its quotas.");
                continue;
            }
            $log_criteria = '<li>It has <strong>%s</strong>%s available which is <span style="text-decoration:underline;">%s</span> than the minimum of <strong>%s</strong>.</li>';
            $inodes_operator = $quota['inodes_remain'] >= $min_inodes ? 'more' : 'less';
            //if ( $quota[ 'inodes_remain' ] >= $min_inodes ) $inodes_operator = 'more';
            //else $inodes_operator = 'less';
            $MB_operator = $quota['megabytes_remain'] >= $min_megabytes ? 'more' : 'less';
            //if ( $quota[ 'megabytes_remain' ] >= $min_megabytes ) $MB_operator = 'more';
            //else $MB_operator = 'less';
            $log = '<ul>' . sprintf($log_criteria, $quota['inodes_remain'], ' inodes', $inodes_operator, $min_inodes) .
                sprintf($log_criteria, $quota['megabytes_remain'], 'MB', $MB_operator, $min_megabytes) . '</ul>';

            if (strpos($log, 'less') === false) {
                $this->log("Can" . $log_chunk . $log, 'success');
                return $this->staging_cpanel_key = $key;
            } else {
                $this->log("Can't" . $log_chunk . $log);
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    private function delete_live_cpanel_account()
    {
        //find cPanel account
        $error_string = "Can't delete live cPanel account. ";
        if (!isset($this->config->environ->live->domain, $this->config->environ->live->cpanel->account->username)) {
            $this->log($error_string . "Live cPanel domain and/or username missing.", 'error');
            return false;
        }

        $domain = $this->config->environ->live->domain;
        $username = $this->config->environ->live->cpanel->account->username;
        $this->log(sprintf('<h4>Deleting live cPanel account.</h4>First we check for existence of the live site cPanel account with domain <strong>%s</strong> and/or username <strong>%s</strong>.', $domain, $username), 'info');

        $cPanel_account = $this->find_cpanel_account($domain, $username);
        if (!$cPanel_account) {
            $this->log(sprintf($error_string . "Apparently account with domain <strong>%s</strong> and username <strong>%s</strong> doesn't exist.", $domain, $username), 'warning');
            return false;
        }
        if (!empty($this->whm->protected_accounts)) {
            foreach ($this->whm->protected_accounts as $protected_account) {
                if ($protected_account['username'] == $cPanel_account['username'] || $protected_account['domain'] == $cPanel_account['domain']) {
                    $this->log($error_string . "It was flagged as protected.", 'error');
                    return false;
                }
            }
        }
        if ($this->whm->delete_cpanel_account($cPanel_account['domain'], 'domain')) {
            $this->log('Successfully deleted live site cPanel account.', 'success');
            return true;
        }
        $this->log('Failed to delete live site cPanel account.', 'error');
        return false;
    }

    /**
     * @param $domain
     * @param $username
     * @param string $operator
     * @return bool
     */
    function find_cpanel_account($domain, $username, $operator = 'AND')
    {
        if (!isset($domain, $username)) {
            $this->log("Can't find cPanel account. Domain and/or username input missing. If you only have one or the other just use WHM get_cpanel_account method. ");
            return false;
        }
        $cPanel_account = $this->whm->get_cpanel_account($username);
        if (empty($cPanel_account))
            $cPanel_account = $this->whm->get_cpanel_account($domain, 'domain');
        if (empty($cPanel_account)) {
            $this->log(sprintf("Can't find existing cPanel with domain <strong>%s</strong> and/or username <strong>%s</strong>.", $domain, $username), 'info');
            return false;
        }
        if ($cPanel_account['domain'] != $domain) {
            if ($operator == 'AND') {
                $this->log(sprintf("Found cPanel account with matching username <strong>%s</strong> but different domain name. Domain is <strong>%s</strong>, searched for <strong>%s</strong>.",
                    $username, $cPanel_account['domain'], $domain), 'error');
                return false;
            }
            return $cPanel_account;
        }
        if ($cPanel_account['user'] != $username) {
            if ($operator == 'AND') {
                $this->log(sprintf("Found cPanel account with matching domain <strong>%s</strong> but different username. Username is <strong>%s</strong>, searched for <strong>%s</strong>.",
                    $domain, $cPanel_account['user'], $username), 'error');
                return false;
            }
            return $cPanel_account;
        }
        return $cPanel_account;
    }

    /**
     * @return bool
     */
    function delete_staging_subdomain()
    {
        $this->log("<h4>Deleting cPanel subdomain for staging site.</h4>", 'info');
        if (empty($this->config->environ->staging->cpanel->subdomain->slug)) {
            $this->log("Can't delete staging site cPanel subdomain. Subdomain slug missing.", 'error');
            return false;
        }
        $slug = $this->config->environ->staging->cpanel->subdomain->slug;
        $error_string = "Can't delete staging site cPanel subdomain <strong>" . $slug . "</strong>. ";

        //check subdomain exists to delete.
        $staging_cpanel_account = $this->find_staging_cpanel();

        if (empty($staging_cpanel_account)) {
            $this->log($error_string . sprintf("Apparently subdomain <strong>%s</strong> doesn't exist in your staging cPanel accounts.", $slug), 'error');
            return false;
        }

        $this->log(sprintf("Deleting staging site subdomain <strong>%s</strong> in cPanel account with username <strong>%s</strong>.",
            $slug, $staging_cpanel_account['user']), 'info');
        $deleted_subdomain = $this->whm->delete_subdomain($slug);

        $directory = $this->get_environ_dir('staging', 'web');
        if (!empty($directory)) {
            $deletedSubdomainDirectory = $this->terminal('staging')->ssh->delete($directory);
            $prunedSubdomainDirectoryTree = $this->terminal('staging')->dir()->prune(dirname($directory));
        }
        if (!empty($deleted_subdomain) && !empty($deletedSubdomainDirectory) && !empty($prunedSubdomainDirectoryTree)) {
            return true;
        }
        $this->log(sprintf("Something went wrong deleting subdomain in account with user <strong>%s</strong>.", $staging_cpanel_account['user']), 'error');
        return false;

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
    function get_environ_url(string $environ = 'live', bool $scheme = false, bool $prefix = false)
    {
        $error_string = sprintf("Can't get %s environment url.", $environ);
        switch ($environ) {
            case 'staging':
                $staging_cpanel = $this->find_staging_cpanel();
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
    function get_environ_dir(string $environ = 'live', $type = 'web')
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
    function getWHMDiskSpace()
    {
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
    function updateWP($environment = 'live')
    {
        $mainStr = sprintf(" WordPress in %s environment. ", $environment);
        $this->log('<h2>Updating ' . $mainStr . '</h2>', 'info');
        $directory = $this->get_environ_dir($environment, 'web');
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

    /**
     * @return array
     */
    function get_staging_subdomains()
    {
        $domains = array();
        if (!empty($this->config->environ->staging->cpanel->accounts)) {
            foreach ($this->config->environ->staging->cpanel->accounts as $account) {
                if (isset($account->domain, $account->username))
                    $domains[$account->domain] = $this->whm->list_domains($account->username)['sub_domains'];
            }
        }
        return $domains;
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


