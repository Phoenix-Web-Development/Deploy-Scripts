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
        if (!defined('BACKUPS_DIR')) define('BACKUPS_DIR', BASE_DIR . '/../backups/');
        if (!defined('BASH_WRAPPER')) define('BASH_WRAPPER', BASE_DIR . '/../bash/wrapper.sh');

        new Logging();
        $this->actionRequests = new ActionRequests();
        $this->configControl = new ConfigControl();
        $this->template = new Template();

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
                    if ($this->actionRequests->can_do('transfer_wp_db_live_to_staging'))
                        $this->transferDB('live', 'staging');
                    if ($this->actionRequests->can_do('transfer_wp_db_live_to_local'))
                        $this->transferDB('live', 'local');
                    if ($this->actionRequests->can_do('transfer_wp_db_staging_to_live'))
                        $this->transferDB('staging', 'live');
                    if ($this->actionRequests->can_do('transfer_wp_db_staging_to_local'))
                        $this->transferDB('staging', 'local');
                    if ($this->actionRequests->can_do('transfer_wp_db_local_to_live'))
                        $this->transferDB('local', 'live');
                    if ($this->actionRequests->can_do('transfer_wp_db_local_to_staging'))
                        $this->transferDB('local', 'staging');
                    break;
            }
            $this->log(sprintf('<h2>Finished %s</h2>', ucfirst($this->actions[$action]['present'])), 'info');
            template()->get('reload');
        } else {
            template()->get('form');
            $this->log(sprintf('Apache user is <strong>%s</strong>.', $this->terminal('local')->whoami()), 'info');
            //if (!empty($this->terminal('staging')->whoami()))
            //$this->log(sprintf('Staging Apache user is <strong>%s</strong>.', $this->terminal('staging')->whoami()), 'info');
            $diskspace = $this->getWHMDiskSpace();

            $plan = $this->config->environ->live->cpanel->create_account_args->plan ?? '';
            $package_size = $this->whm->get_pkg_info($plan)['data']['pkg']['QUOTA'];
            if (($diskspace['total'] - $diskspace['allocated']) > $package_size)
                $disk_message = 'You have';
            else
                $disk_message = 'Not enough';
            $this->log(sprintf('Total disk space - <strong>%s</strong>MB. Disk used - <strong>%s</strong>MB. Allocated disk space - <strong>%s</strong>MB. %s enough unallocated disk space for a new <strong>%s</strong>MB cPanel account.',
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
                $this->terminal('local')->localSSHKey('create', $key_name, $passphrase);
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

        if ($actions['db'] && ($action == 'create' || $environment == 'staging' || ($action == 'delete' && !$actions['live_site'])))
            $db = $this->database_components($action, $environment);

        //shared actions
        if ($actions['email_filters'])
            $email_filters = $this->email_filters($action, $environment);
        if ($actions['version_control']) {
            $version_control = $this->environVersionControl($action, $environment);
            //$version_control =
        }
        if ($actions['wp']) {
            $wp = $this->wordpress($action, $environment);
        }
        if ($action == 'create' && $actions['initial_commit']) {
            $inital_commit = $this->environInitialCommit($environment);
        }

        //if ($actions['version_control'])
        //$version_control = $this->versionControlInitialCommit($action, $environment);
        if ($action == 'delete' && $actions['subdomain'] && $environment == 'staging') {
            $function_name = $action . '_staging_subdomain';
            $staging_subdomain = $this->$function_name();
        }

        if (((!$actions['live_site'] || !empty($live_cpanel_account)) || (!$actions['subdomain'] || !empty($staging_subdomain)))
            && (!$actions['db'] || !empty($db) || ($action == 'delete' && !empty($live_cpanel_account)))
            && (!$actions['email_filters'] || !empty($email_filters))
            && (!$actions['version_control'] || !empty($version_control))
            && (!$actions['wp'] || !empty($wp))
            && (!$actions['initial_commit'] || !empty($inital_commit))
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


    function localStuff($action = 'create')
    {
        $projectDir = $this->get_environ_dir('local', 'project');
        $webDir = $this->get_environ_dir('local', 'web');

        if ($this->actionRequests->can_do($action . "_local_virtual_host")) {
            $admin_email = $this->config->environ->local->email ?? '';
            $domain = $this->config->environ->local->domain ?? '';
            $sitesAvailable = $this->config->environ->local->sites_available ?? '';

            $virtualHostArgs = [
                'domain' => $domain,
                'sites_available_path' => $sitesAvailable . $domain . '.conf',
                'web_dir' => $webDir,
                'admin_email' => $admin_email
            ];
            $this->terminal('local')->localVirtualHost()->$action($virtualHostArgs);
        }

        if ($this->actionRequests->can_do($action . "_local_version_control")) {
            $version_control = new EnvironVersionControl(
                $this->terminal('local'),
                $this->github,
                $this->whm,
                'local'
            );
            $version_control->create();
        }

        if ($this->actionRequests->can_do($action . "_local_web_directory")) {
            $owner = $this->config->environ->local->owner ?? '';
            $group = $this->config->environ->local->group ?? '';
            $webDirArgs = [
                'project_dir' => $projectDir,
                'web_dir' => $webDir,
                'owner' => $owner,
                'group' => $group
            ];
            $this->terminal('local')->localWebDir()->$action($webDirArgs);
        }

        //$this->terminal('staging')->ssh->delete($directory);
        /*
        if ($this->actionRequests->can_do('create_local_version_control')) {
            $this->environVersionControl('create', 'local');
        }


        $key_name = $this->config->live->domain ?? '';
        $passphrase = $this->config->local->ssh_keys->live->passphrase ?? '';
        $ssh_key = $this->terminal('local')->SSHKey($action, $key_name, $passphrase);
        if (!empty($ssh_key)) {
            $host = $this->config->live->domain ?? '';
            $hostname = $this->config->live->cpanel->ssh->hostname ?? '';
            $user = $this->config->live->cpanel->ssh->username ?? '';
            $port = $this->config->live->cpanel->ssh->port ?? '';
            $this->terminal('local')->SSHConfig($action, $host, $hostname, $key_name, $user, $port);

            $cpanel_username = $this->config->environ->live->cpanel->account->username ?? '';
            $this->whm->import_key($ssh_key, $key_name, $passphrase, $cpanel_username);
        }
        $this->terminal('local')->virtualHost($action);

        $github_user = $this->config->version_control->github->user ?? '';
        $project_name = $this->config->project->name ?? '';

        $this->terminal('local')->Git('create', $github_user, $project_name);
        return true;
        */
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
     * @param string $action
     * @param string $environment
     * @return bool
     */
    function database_components(string $action = 'create', string $environment = 'live')
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s cPanel database stuff.", $environment)))
            return false;

        $error_string = sprintf("Can't %s %s cPanel database components.", $action, $environment);
        $this->log(sprintf('<h4>%s database components for %s cPanel account.</h4>',
            ucfirst($this->actions[$action]['present']), $environment), 'info');
        $db_args = $this->config->environ->$environment->db ?? null;
        if (!isset($db_args->name, $db_args->username, $db_args->password)) {
            $this->log($error_string . " DB name, username and/or password are missing from config.", 'error');
            return false;
        }

        $cPanel_account = $this->find_environ_cpanel($environment);
        if (!$cPanel_account) {
            $this->log(sprintf("%s Couldn't find %s cPanel account.",
                $error_string, $environment));
            return false;
        }

        switch ($action) {
            case 'create':
                $created_db_user = $this->whm->create_db_user($db_args->username, $db_args->password);
                $created_db = $this->whm->create_db($db_args->name);
                $added_user_to_db = $this->whm->db_user_privileges('set', $db_args->username, $db_args->name);
                if ($created_db_user && $created_db && $added_user_to_db)
                    $success = true;
                break;
            case 'delete':
                $deleted_db = $this->whm->delete_db($db_args->name);
                $deleted_db_user = $this->whm->delete_db_user($db_args->username);
                if ($deleted_db && $deleted_db_user)
                    $success = true;
                break;
        }
        if (!empty($success)) {
            $this->log(sprintf('Successfully %s %s database components.', $this->actions[$action]['past'], $environment), 'success');
            return true;
        }
        $this->log(sprintf('Something may have gone wrong %s %s database components.', $this->actions[$action]['present'], $environment), 'error');
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
            $prunedSubdomainDirectoryTree = $this->terminal('staging')->api()->pruneDirTree(dirname($directory));
        }
        if (!empty($deleted_subdomain) && !empty($deletedSubdomainDirectory) && !empty($prunedSubdomainDirectoryTree)) {
            return true;
        }
        $this->log(sprintf("Something went wrong deleting subdomain in account with user <strong>%s</strong>.", $staging_cpanel_account['user']), 'error');
        return false;

    }

    /**
     * Creates, deletes or gets cPanel email filter
     *
     * @param string $action
     * @param string $environment
     * @return bool
     */
    function email_filters(string $action = 'create', string $environment = 'live')
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s cPanel email filter stuff.", $environment)))
            return false;

        $this->log(sprintf('<h4>%s %s cPanel email filters.</h4> ', ucfirst($this->actions[$action]['present']), $environment), 'info');
        $error_string = sprintf("Can't %s %s email filters.", $action, $environment);
        if (empty($this->config->environ->$environment->cpanel->email_filters)) {
            $this->log($error_string . " Filter args missing from config.", 'error');
            return false;
        }
        $cpanel_username = $this->config->environ->primary->cpanel->username ?? '';
        if (empty($cpanel_username)) {
            $this->log($error_string . " Primary cPanel account username missing from config.", 'error');
            return false;
        }

        $number_of_filters = 0;
        $problems = 0;
        foreach ($this->config->environ->$environment->cpanel->email_filters as $filter_name => $email_filter) {
            $number_of_filters++;
            switch ($action) {
                case 'create':
                    $success = $this->whm->create_email_filter(
                        $email_filter->account,
                        $this->format_email_filtername($filter_name),
                        $email_filter->args,
                        $cpanel_username
                    );
                    break;
                case 'delete':
                    $success = $this->whm->delete_email_filter(
                        $email_filter->account,
                        $this->format_email_filtername($filter_name),
                        $cpanel_username
                    );
            }
            if (empty($success)) {
                $problem = true;
                $problems++;
            }
        }
        if (!empty($problem)) {
            $this->log(sprintf("Failed to %s %d out of %d %s email filters.", $action, $problems, $number_of_filters, $environment), 'error');
            return false;
        }
        $this->log(sprintf("Successfully %s %s email filters.", $this->actions[$action]['past'], $environment), 'success');
        return true;
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
     * @param string $action
     * @param string $environment
     * @return bool
     * @throws \Github\Exception\MissingArgumentException
     */
    function environVersionControl(string $action = 'create', string $environment = 'live')
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s environment version control stuff.", $environment)))
            return false;

        $this->log(sprintf('<h3>%s %s version control components.</h3>', ucfirst($this->actions[$action]['present']), $environment), 'info');
        $message_string = sprintf('%s version control components.', $environment);
        $error_string = sprintf("Can't %s %s", $action, $message_string);
        if ($environment == 'local') {
            $this->log(sprintf("%s Environment is local where VC access components not needed.",
                $error_string));
            return false;
        }
        $repo_name = $this->config->version_control->repo_name ?? '';
        if (empty($repo_name)) {
            $this->log(sprintf("%s Repository name is missing from config.",
                $error_string));
            return false;
        }
        $cPanel_account = $this->find_environ_cpanel($environment);
        if (!$cPanel_account) {
            $this->log(sprintf("%s Couldn't find %s cPanel account.",
                $error_string, $environment));
            return false;
        }
        $key_name = $this->config->environ->$environment->ssh_keys->version_control_deploy_key->key_name ?? '';
        //$passphrase = $this->config->environ->$environment->ssh_keys->version_control_deploy_key->passphrase ?? '';
        $passphrase = '';

        $root = $this->terminal($environment)->root;
        if (empty($root)) {
            $this->log(sprintf("%s Couldn't get %s environment SSH root directory.", $error_string, $environment));
            return false;
        }
        $web_dir = $this->get_environ_dir($environment, 'web');
        $repo_location = $this->get_environ_dir($environment, 'git');
        $downstream_repo_name = $repo_name . '_website';
        $key_title = ucfirst($environment) . ' cPanel';
        $webhook_url = 'https://' . $cPanel_account['domain'] . '/github-webhook.php?github=yes';
        $webhook_endpoint_config_dir = $this->get_environ_dir($environment, 'github_webhook_endpoint_config') . '/' . $repo_name . '.json';
        switch ($action) {
            case 'create':
                $ssh_key = $this->whm->genkey($key_name, $passphrase, 2048, $cPanel_account['user']);
                if (!empty($ssh_key)) {
                    $authkey = $this->whm->authkey($key_name);
                    $ssh_config = $this->terminal($environment)->ssh_config()->create($key_name, 'github.com', $key_name, 'git');
                }
                $upstream_repository = $this->github->repo()->get($repo_name);

                if ($upstream_repository) {
                    if (!empty($ssh_key)) {
                        $deploy_key = $this->github->deploy_key()->upload($repo_name, $key_title, $ssh_key['key']);
                        $ssh_url = str_replace('git@github.com', $key_name, $upstream_repository['ssh_url']);
                        $source_repository = json_encode((object)['url' => $ssh_url, 'remote_name' => "origin"]);
                        $downstream_repository = $this->whm->version_control('clone',
                            $repo_location,
                            $downstream_repo_name,
                            $source_repository
                        );
                        if (!$downstream_repository)
                            $downstream_repository = $this->whm->version_control('get', $repo_location);
                        if ($downstream_repository && $this->terminal($environment)->git()->waitForUnlock($repo_location)) {
                            $dotGit = $this->terminal($environment)->dotGitFile()->create($web_dir, $repo_location);
                            $gitignore = $this->terminal($environment)->gitignore()->create($web_dir);
                            $gitPurge = $this->terminal($environment)->git()->purge($repo_location);
                            if ($downstream_repository)
                                $gitReset = $this->terminal($environment)->gitBranch()->reset(['worktree' => $web_dir, 'branch' => 'master']);
                        }

                    }

                    if ($environment == 'staging') { //webhook
                        $secret = $this->config->version_control->github->webhook->secret ?? '';
                        $webhook_config = $this->terminal($environment)->githubWebhookEndpointConfig()->create($webhook_endpoint_config_dir, $web_dir, $secret);
                        $webhook = $this->github->webhook()->create($repo_name, $webhook_url, $secret);

                    }
                } else
                    $this->log("Can't upload deploy key or clone repository. Upstream repository not found.");

                $this->terminal($environment)->exec('git config --global user.name "James Jones"; git config --global user.email "james.jones@phoenixweb.com.au"');
                if (!empty($ssh_key)
                    && !empty($authkey)
                    && !empty($ssh_config)
                    && !empty($deploy_key)
                    && !empty($downstream_repository)
                    && !empty($dotGit)
                    && !empty($gitignore)
                    && !empty($gitPurge)
                    && !empty($gitReset)
                    && ($environment != 'staging' || (!empty($webhook) && !empty($webhook_config)))
                )
                    $success = true;
                break;
            case 'delete':
                $gitignore = $this->terminal($environment)->gitignore()->delete($web_dir);
                $downstream_repository = $this->whm->version_control('delete', $repo_location, '', '', $cPanel_account['user']);
                $deleted_git_folder = $this->terminal($environment)->git()->delete($repo_location);
                $ssh_key = $this->whm->delkey($key_name, $cPanel_account['user']);
                $ssh_config = $this->terminal($environment)->ssh_config()->delete('github_' . $repo_name);
                $deploy_key = $this->github->deploy_key()->remove($repo_name, $key_title);

                if ($environment == 'staging') { //webhook
                    $webhook = $this->github->webhook()->remove($repo_name, $webhook_url);
                    $webhook_config = $this->terminal($environment)->githubWebhookEndpointConfig()->delete($webhook_endpoint_config_dir);
                }
                $dotGit = $this->terminal($environment)->ssh->delete($web_dir . '/.git');

                if (!empty($gitignore)
                    && !empty($downstream_repository)
                    && !empty($ssh_key)
                    && !empty($ssh_config)
                    && !empty($deploy_key)
                    && !empty($deleted_git_folder)
                    && ((!empty($webhook) && !empty($webhook_config)) || $environment != 'staging')
                    && !empty($dotGit)
                )
                    $success = true;

                break;
        }
        if (!empty($success)) {
            $this->log(sprintf('Successfully %s %s', $this->actions[$action]['past'], $message_string), 'success');
            return true;
        }
        $this->log(sprintf("Something may have gone wrong while %s %s", $this->actions[$action]['present'], $message_string), 'error');
        return false;
    }

    /**
     * @param string $environment
     * @return bool
     */
    function environInitialCommit(string $environment = 'live')
    {
        $mainStr = sprintf(" initial %s environment files to repository.", $environment);
        $this->log("<h3>Committing" . $mainStr . "</h3>", 'info');
        $web_dir = $this->get_environ_dir($environment, 'web');
        $commitMaster = $this->terminal($environment)->gitBranch()->commit($web_dir, 'master', 'initial Deployer commit from ' . $environment . ' environment');

        if ($commitMaster && $environment == 'staging') {
            $checkoutDevBranch = $this->terminal($environment)->gitBranch()->checkout($web_dir, 'dev');
            if ($checkoutDevBranch) {
                if ($this->terminal($environment)->gitBranch()->check($web_dir, 'dev', 'up'))
                    $syncDevBranch = $this->terminal($environment)->gitBranch()->pull(['worktree' => $web_dir, 'branch' => 'dev']);
                else
                    $syncDevBranch = $this->terminal($environment)->gitBranch()->commit($web_dir, 'dev', 'create dev branch');
            }
        }
        if (!empty($commitMaster) && ($environment != 'staging') || !empty($syncDevBranch)) {
            $this->log(sprintf('Successfully committed %s', $mainStr), 'success');
            return true;
        }
        $this->log(sprintf("Something may have gone wrong while committing %s", $mainStr));
        return false;
    }

    /**
     * @param string $environment
     * @return bool|string
     */
    function get_environ_url(string $environment = 'live')
    {
        $error_string = sprintf("Can't get %s environment url.", $environment);
        if ($environment == 'staging') {
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
        } else {
            if (empty($this->config->environ->$environment->domain)) {
                $this->log($error_string . " Domain missing from config.", 'error');
                return false;
            }
            $url = $this->config->environ->$environment->domain;
        }
        //$protocol = $environment == 'local' ? 'http://' : 'https://';
        $protocol = 'https://';
        if (strpos($protocol, $url) !== 0)
            $url = $protocol . $url;
        return $url;
    }

    /**
     * @param string $environment
     * @param string $type
     * @return bool|string
     */
    function get_environ_dir(string $environment = 'live', $type = 'web')
    {
        $error_string = sprintf("Couldn't determine %s environment %s directory.", $environment, $type);
        $root = '';
        if ($environment != 'local') {
            $root = $this->terminal($environment)->root;
            if (empty($root)) {
                $this->log($error_string . " Couldn't get SSH root directory.");
                return false;
            }
        }


        switch ($environment) {
            case 'live':
                switch ($type) {
                    case 'web':
                        $dir = '/public_html';
                        break;
                    case 'git':
                        $dir = '/git/website';
                        break;
                }
                break;
            case 'staging':
                switch ($type) {
                    case 'web':
                        $dir = $this->config->environ->$environment->cpanel->subdomain->directory ?? '';
                        break;
                    case 'git':
                        $repo_name = $this->config->version_control->repo_name ?? '';
                        if (empty($repo_name)) {
                            $this->log($error_string . ' Version control repo name missing from config.');
                            return false;
                        }
                        $dir = '/git/' . $repo_name . '/website';
                        break;
                    case 'github_webhook_endpoint_config':
                        $dir = '/.github_webhook_configs';
                        break;
                }
                break;
            case 'local':
                $rootWebDir = $this->config->environ->local->root_web_dir ?? '';
                if (empty($rootWebDir)) {
                    $this->log($error_string . ' Root web dir missing from config.');
                    return false;
                }
                $projectName = $this->config->project->name ?? '';
                if (empty($projectName)) {
                    $this->log($error_string . ' Project name missing from config.');
                    return false;
                }
                $dir = $rootWebDir . $projectName;
                if ($type != 'project')
                    $dir .= '/Project/public';
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
     * @param string $action
     * @param string $environment
     * @return bool
     */
    function wordpress(string $action = 'install', string $environment = 'live')
    {
        if (!$this->validate_action($action, array('create', 'install', 'delete'), "Can't do WordPress stuff."))
            return false;
        $action = $action == 'create' ? 'install' : $action;

        $this->log(sprintf('<h3>%s %s WordPress and WP CLI</h3>', ucfirst($this->actions[$action]['present']), $environment), 'info');

        $directory = $this->get_environ_dir($environment, 'web');

        switch ($action) {
            case 'install':
                $WPCLI = $this->terminal($environment)->wp_cli()->install();
                $WPCLIConfig = $this->terminal($environment)->wpcliconfig()->create();
                $wp_args = $this->config->wordpress ?? null;
                $wp_args->title = $this->config->project->title ?? 'Insert Site Title Here';
                $wp_args->url = $this->get_environ_url($environment);
                //$wp_args->debug = $this->get_environ_url($environment);
                $db_args['name'] = $this->config->environ->$environment->db->name ?? '';
                $db_args['username'] = $this->config->environ->$environment->db->username ?? '';
                if (empty($db_args['name'])) {
                    $this->log(sprintf("Can't install WordPress. %w DB name missing from config.", $environment));
                    return false;
                }
                if (empty($db_args['username'])) {
                    $this->log(sprintf("Can't install WordPress. %s DB username missing from config.", $environment));
                    return false;
                }
                $cpanel = $this->find_environ_cpanel($environment);
                if (empty($cpanel['user'])) {
                    $this->log(sprintf("Can't install WordPress. Couldn't work out %s cPanel username.", $environment));
                    return false;
                }
                $db_args['name'] = $this->whm->db_prefix_check($db_args['name'], $cpanel['user']);
                $db_args['username'] = $this->whm->db_prefix_check($db_args['username'], $cpanel['user']);
                $db_args['password'] = $this->config->environ->$environment->db->password ?? '';
                $installed = $this->terminal($environment)->wp()->install($directory, $db_args, (array)$wp_args);
                $www = ($environment == 'live' && !empty($wp_args->www)) ? true : false;
                $htaccess = $this->terminal($environment)->htaccess()->prepend($directory, $www);
                $permissions = $this->terminal($environment)->wp()->setPermissions($directory);
                if (!empty($WPCLI) && !empty($WPCLIConfig) && !empty($installed) && !empty($htaccess) && !empty($permissions))
                    $success = true;
                break;
            case 'delete':
                $deleted_wp = $this->terminal($environment)->wp()->delete($directory);
                if ($deleted_wp && $environment == 'live') {
                    $deleted_wp_cli = $this->terminal($environment)->wp_cli()->delete();
                    $deletedWPConfig = $this->terminal($environment)->wpcliconfig()->delete();
                } else {
                    $deleted_wp_cli = true;
                    $deletedWPConfig = true;
                }
                if ($deleted_wp && $deleted_wp_cli && $deletedWPConfig)
                    $success = true;
                break;
        }
        if (!empty($success)) {
            $this->log(sprintf("Successfully %s WordPress in %s environment.", $this->actions[$action]['past'], $environment), 'success');
            return true;
        }
        $this->log(sprintf('Something may have gone wrong %s %s environment WordPress.', $this->actions[$action]['present'], $environment), 'error');
        return false;
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
        $backup = $this->backupDB($environment);
        if ($backup) {
            $gitPull = $this->terminal($environment)->git()->pull(['worktree' => $directory, 'branch' => 'dev']);
            if ($gitPull) {
                $wp_update = $this->terminal($environment)->wp()->update($directory);
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
     * @param string $from_environment
     * @param string $dest_environment
     * @return bool
     */
    protected function transferDB(string $from_environment = '', string $dest_environment = '')
    {
        $message = sprintf(' %s DB to %s environment', $from_environment, $dest_environment);
        $this->log('<h2>Migrating' . $message . '</h2>', 'info');

        $from_directory = $this->get_environ_dir($from_environment, 'web');
        $from_db_name = $this->config->environ->$from_environment->db->name ?? null;
        if ($from_environment != 'local') {
            $from_cpanel = $this->find_environ_cpanel($from_environment);
            if (!empty($from_cpanel))
                $from_db_name = $this->whm->db_prefix_check($from_db_name, $from_cpanel['user']);
        }
        $date_format = "-Y-m-d-H_i_s";

        $from_filename = $from_db_name . '-' . $from_environment . date($date_format) . '.sql';

        $export = $this->terminal($from_environment)->wp_db()->export($from_directory, BACKUPS_DIR . $from_filename);

        if ($export) {
            $to_directory = $this->get_environ_dir($dest_environment, 'web');

            $from_url = $this->get_environ_url($from_environment);
            $dest_url = $this->get_environ_url($dest_environment);

            $backup = $this->backupDB($dest_environment);
            if ($backup) {
                $import = $this->terminal($dest_environment)->wp_db()->import($to_directory, BACKUPS_DIR . $from_filename . '.gz', $from_url, $dest_url);
                if ($import) {
                    $blogPublic = $dest_environment == 'live' ? 1 : 0;
                    $updateSearchVisibility = $this->terminal($dest_environment)->wp()->setOption($to_directory, 'blog_public', $blogPublic);
                }
            }
        }
        if (!empty($export) && !empty($backup) && !empty($import) && !empty($updateSearchVisibility)) {
            $this->log('<h3>Finished migrating ' . $message . '</h3>', 'success');
            return true;
        }
        $this->log('<h3>Something may have gone wrong migrating ' . $message . '</h3>');
        return false;

    }

    /**
     * @param string $environment
     * @return bool
     */
    protected function backupDB(string $environment = '')
    {
        $errorString = sprintf("Can't backup %s environment WordPress DB. ", $environment);
        $directory = $this->get_environ_dir($environment, 'web');
        if (empty($directory)) {
            $this->log($errorString . " Couldn't get web directory.");
            return false;
        }
        $db_name = $this->config->environ->$environment->db->name ?? null;
        if (empty($db_name)) {
            $this->log($errorString . " DB name missing from config");
            return false;
        }
        if ($environment != 'local') {
            $cpanel = $this->find_environ_cpanel($environment);
            if (!empty($cpanel))
                $db_name = $this->whm->db_prefix_check($db_name, $cpanel['user']);
        }
        $date_format = "-Y-m-d-H_i_s";
        $backup_filename = $db_name . '-' . $environment . date($date_format) . '-backup.sql';
        $backup = $this->terminal($environment)->wp_db()->export($directory, BACKUPS_DIR . $backup_filename);
        if ($backup) {
            return true;
        }
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

    /**
     * @param string $filter_name
     * @return bool|string
     */
    function format_email_filtername(string $filter_name = '')
    {
        $project_name = $this->config->project->name ?? '';
        if (empty($filter_name) || empty($project_name)) {
            $this->log("Can't format email filter name. Filter name or project name input missing.");
            return false;
        }
        return ucwords($project_name) . ' ' . $filter_name;
    }

    /**
     * Return true if same, false if different
     *
     * @param $filter1
     * @param $filter2
     * @return bool|void
     */
    function compare_email_filter($config_filter_args, $queried_filter)
    {

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


