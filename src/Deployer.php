<?php

namespace Phoenix;

use phpseclib\Net\SSH2;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use Github\Client;
use Phoenix\Functions;

/**
 * @property WHM $whm
 * @property \stdClass $config
 * @property Bitbucket $bitbucket
 * @property Terminal $terminal
 * @property Github $github
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
     * @var array
     */
    private $_config = array();
    /**
     * @var
     */
    public $staging_cpanel_key;

    /**
     * @var array
     */
    public $permissions = array(
        'create' => array('label' => 'Create'),
        'create_version_control' => array('label' => 'Create main version control repository',
            'condition' => array('create')),

        'create_live_stuff' => array('label' => 'Live stuff',
            'condition' => 'create'),
        'create_live_site' => array('label' => 'cPanel account',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_db' => array('label' => 'Database & DB User',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_email_filters' => array('label' => 'Email filters',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_version_control' => array('label' => 'Setup version control',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_wp' => array('label' => 'Install WordPress and WP CLI',
            'condition' => array('create', 'create_live_stuff')),

        'create_staging_stuff' => array('label' => 'Staging stuff',
            'condition' => array('create')),
        'create_staging_subdomain' => array('label' => 'Staging cPanel subdomain',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_db' => array('label' => 'Database & DB User',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_email_filters' => array('label' => 'Email filters',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_version_control' => array('label' => 'Setup version control',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_wp' => array('label' => 'Install WordPress and WP CLI',
            'condition' => array('create', 'create_staging_stuff')),

        'create_local_stuff' => array('label' => 'Create local stuff',
            'condition' => array('create')),
        'create_local_version_control' => array('label' => 'Setup version control',
            'condition' => array('create', 'create_local_stuff')),

        //'create_wp_auto_update' => array('label' => 'Setup WordPress auto-update',
        //  'condition' => array('create')),

        'delete' => array('label' => 'Delete'),
        'delete_version_control' => array('label' => 'Delete main version control repository',
            'condition' => array('delete')),
        'delete_live_stuff' => array('label' => 'Live stuff',
            'condition' => array('delete')),
        'delete_live_site' => array('label' => 'cPanel account',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_db' => array('label' => 'Database & DB User',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_email_filters' => array('label' => 'Email filters',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_version_control' => array('label' => 'Version control',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_wordpress' => array('label' => 'WordPress',
            'condition' => array('delete', 'delete_live_stuff')),

        'delete_staging_stuff' => array('label' => 'Staging stuff',
            'condition' => array('delete')),
        'delete_staging_subdomain' => array('label' => 'Staging cPanel subdomain',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_db' => array('label' => 'Database & DB User',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_email_filters' => array('label' => 'Email filters',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_version_control' => array('label' => 'Version control',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_wordpress' => array('label' => 'WordPress',
            'condition' => array('delete', 'delete_staging_stuff')),

        'delete_local_stuff' => array('label' => 'Delete local stuff',
            'condition' => array('delete')),
        'delete_local_version_control' => array('label' => 'Delete version control',
            'condition' => array('delete', 'delete_local_stuff')),


        'update' => array('label' => 'Update'),
        'update_wp' => array('label' => 'Update WordPress core, plugins and themes',
            'condition' => array('update')),
    );


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
     * @param $name
     * @param $value
     */
    function __set($name, $value)
    {
        if (method_exists($this, $name)) {
            $this->$name($value);
        } else {
            // Getter/Setter not defined so set as property of object
            $this->$name = $value;
        }
    }

    /**
     * @param $name
     * @return null
     */
    function __get($name)
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        } elseif (property_exists($this, $name)) {
            // Getter/Setter not defined so return property if it exists
            return $this->$name;
        }
        return null;
    }

    /**
     * Deployer constructor.
     */
    function __construct()
    {
        if (!defined('BASE_DIR')) define('BASE_DIR', dirname(__FILE__));
        foreach ($this->permissions as &$action) {
            if (!empty($action['condition']) && !is_array($action['condition']))
                $action['condition'] = array($action['condition']);
        }
        new Logging();
        $this->process_request();
        return true;
    }

    /**
     * @return bool
     */
    function init()
    {
        /*
        $rsa = new RSA();
        $rsa->setPassword('blegh');
        $rsa->setPublicKeyFormat( RSA::PUBLIC_FORMAT_OPENSSH );
        $key = extract( $rsa->createKey() ); // == $rsa->createKey(1024) where 1024 is the key size
        //d($key);
        d($privatekey);
        d($publickey);
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
     * @return bool|Client
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
        $github = new Github;
        $client = new \Github\Client;
        $client->authenticate($this->config->version_control->github->token, null, \Github\Client::AUTH_HTTP_TOKEN);
        d($client);
        $github->client = $client;
        if (empty($this->config->version_control->github->user))
            $this->log("Won't be able to make many Github API calls. Github user missing from config.");
        else
            $github->user = $this->config->version_control->github->user;
        return $this->_github = $github;
    }

    /**
     * @param string $environment
     * @return bool|Terminal
     */
    protected function terminal(string $environment = 'live')
    {
        if (!empty($this->_terminal->$environment))
            return $this->_terminal->$environment;
        if (empty($this->_terminal))
            $this->_terminal = new \stdClass();
        $error_string = sprintf("Can't connect %s environment terminal.", $environment);
        switch ($environment) {
            case 'live':
                if (!$this->find_cpanel_account($this->config->environ->live->domain, $this->config->environ->live->cpanel->account->username)) {
                    $this->log($error_string . " Couldn't locate live cPanel account.");
                    break;
                }
                $ssh_args = !empty($this->config->environ->live->cpanel->ssh) ? $this->config->environ->live->cpanel->ssh : array();
                break;
            case 'staging':
                $subdomain = $this->find_staging_cpanel();
                if (!$subdomain) {
                    $this->log(sprintf("%s Apparently subdomain <strong>%s</strong> doesn't exist in your staging cPanel accounts.",
                        $error_string, $this->config->environ->staging->cpanel->subdomain->slug));
                    break;
                }
                $domain = $subdomain['domain'];
                $ssh_args = !empty($this->config->environ->staging->cpanel->accounts->$domain->ssh) ? $this->config->environ->staging->cpanel->accounts->$domain->ssh : array();
                break;
        }
        $terminal = new Terminal($environment);
        if (!isset($ssh_args->hostname, $ssh_args->port) && $environment != 'local')
            $this->log(sprintf("%s %s cPanel account SSH args missing.", $error_string, $environment));
        if (!empty($ssh_args)) {
            $ssh = new SSH2($ssh_args->hostname, $ssh_args->port);
            $key = new RSA();
            $key->setPassword($passphrase);
            $key->loadKey(file_get_contents('privatekey'));
            if ($ssh->login($ssh_args->username, $ssh_args->password)) {
                $terminal->setSSH($ssh);
            } else
                $this->log("Couldn't authenticate via SSH");
        }
        return $this->_terminal->$environment = $terminal;
    }


    /**
     * @return array|bool|\stdClass
     */
    protected function config()
    {
        if (empty($this->_config)) {
            $base_config = include BASE_DIR . '/../configs/base-config.php';
            $site_config = include BASE_DIR . '/../configs/sites/imogen.php';
            $config = array_merge_recursive($base_config, $site_config);
            $this->_config = array_to_object($config);
        }
        return $this->_config;
    }

    /**
     * @return array|bool
     */
    function process_request()
    {
        if (empty($_POST))
            return false;
        $actions = $this->permissions;
        foreach ($actions as $key => &$action) {
            if (!empty($_POST[$key])) {
                $action['can_do'] = true;
                if (!empty($action['condition'])) {
                    $action['can_do'] = true;
                    foreach ($action['condition'] as $condition) {
                        if (empty($_POST[$condition])) {
                            $action['can_do'] = false;
                            break;
                        }
                    }
                }
            }
        }
        return $this->permissions = $actions;
    }

    /**
     * @param string $actions
     * @return bool
     */
    function can_do($actions = '', $operator = 'AND')
    {
        if (empty($actions) || empty($this->permissions))
            return false;
        if (!is_array($actions))
            $actions = array($actions);
        foreach ($actions as $action) {
            if ($operator = 'AND') {
                if (empty($this->permissions[$action]['can_do']))
                    return false;
            } else if ($operator = 'OR') {
                if (!empty($this->permissions[$action]['can_do']))
                    return true;
            }

        }
        return true;
    }

    /**
     * @return bool
     */
    function create_live_stuff()
    {
        $this->log('<h2>Creating nominated live stuff.</h2>', 'info');

        if ($this->can_do('create_live_site')) {
            $created_live_account = $this->create_live_cpanel_account();
        }

        if ($this->can_do('create_live_db')) {
            $created_db = $this->database_components('create', 'live');
        }

        if ($this->can_do('create_live_email_filters'))
            $created_email_filters = $this->email_filters('create', 'live');

        if ($this->can_do('create_live_version_control')) {
            $created_version_control = $this->versionControlAccess('create', 'live');
        }

        if ($this->can_do('create_live_wp')) {
            $created_wordpress = $this->wordpress('install', 'live');
        }

        if ((!$this->can_do('create_live_site') || !empty($created_live_account))
            && (!$this->can_do('create_live_db') || !empty($created_db))
            && (!$this->can_do('create_live_email_filters') || !empty($created_email_filters))
            && (!$this->can_do('create_live_version_control') || !empty($created_version_control))
            && (!$this->can_do('create_live_wp') || !empty($created_wordpress))
        ) {
            $this->log('Successfully created nominated live stuff.', 'success');
            return true;
        }
        $this->log('Something may have gone wrong creating nominated live stuff.');
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
        $create_account_args = !empty($this->config->environ->live->cpanel->create_account_args) ? $this->config->environ->live->cpanel->create_account_args : array();
        if ($this->whm->create_cpanel_account($username, $domain, (array)$create_account_args))
            return true;
        return false;
    }

    /**
     * @return bool
     */
    public function create_staging_stuff()
    {
        $this->log('<h2>Creating nominated staging stuff.</h2>', 'info');

        if ($this->can_do('create_staging_subdomain')) {
            $created_staging_subdomain = $this->create_staging_subdomain();
        }

        if ($this->can_do('create_staging_db')) {
            $created_db = $this->database_components('create', 'staging');
        }

        if ($this->can_do('create_staging_email_filters'))
            $created_email_filters = $this->email_filters('create', 'staging');

        if ($this->can_do('create_staging_version_control')) {
            $created_version_control = $this->versionControlAccess('create', 'staging');
        }

        if ($this->can_do('create_staging_wp')) {
            $this->wordpress('create', 'staging');
        }

        if ((!$this->can_do('create_staging_subdomain') || !empty($created_staging_subdomain))
            && (!$this->can_do('create_staging_db') || !empty($created_db))
            && (!$this->can_do('create_staging_email_filters') || !empty($created_email_filters))
            && (!$this->can_do('create_staging_version_control') || !empty($created_version_control))
        ) {
            $this->log('Successfully created nominated staging stuff.', 'success');
            return true;
        }
        $this->log('Something may have gone wrong creating nominated staging stuff.');
        return false;
    }

    /**
     * @return bool
     */
    private function create_staging_subdomain()
    {
        $this->log("<h4>Creating staging subdomain.</h4>", 'info');

        $config = !empty($this->config->environ->staging->cpanel) ? $this->config->environ->staging->cpanel : null;
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
                $error_string, $config->subdomain->slug, $staging_cpanel_account['user']), 'error');
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
     * @return bool
     */
    function localStuff($action = 'create')
    {

        if ($this->can_do('create_local_version_control')) {
            $this->versionControlAccess('create', 'local');
        }
        $key_name = $this->config->live->domain ?? null;
        $ssh_key = $this->terminal('local')->SSHKey($action, $key_name, $passphrase);
        if (!empty($ssh_key)) {
            $host = $this->config->live->domain ?? null;
            $hostname = $this->config->live->cpanel->ssh->hostname ?? null;
            $user = $this->config->live->cpanel->ssh->username ?? null;
            $port = $this->config->live->cpanel->ssh->port ?? null;
            $this->terminal('local')->SSHConfig($action, $host, $hostname, $key_name, $user, $port);

            $this->whm->import_key($ssh_key, $key_name, $key_pass, $cpanel_parameter, $cpanel_parameter_type);
        }
        $this->terminal('local')->virtualHost($action);
        $this->terminal('local')->Git('create');
        return true;
    }

    /**
     * @param string $subdomain_slug
     * @param $cpanel_accounts
     * @return bool
     */
    function find_staging_cpanel(string $subdomain_slug = '', array $cpanel_accounts = array())
    {
        $error_string = "Can't find staging cPanel subdomain.";
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
                return $cpanel_account;
            }
        }
        return false;
    }


    /**
     * @param $cpanel_accounts
     * @return bool|int|string
     */
    function decide_subdomain_cpanel_account($cpanel_accounts)
    {
        if (empty($cpanel_accounts))
            return false;
        $min_inodes = !empty($this->config->environ->staging->cpanel->subdomain->min_inodes) ? $this->config->environ->staging->cpanel->subdomain->min_inodes : 25000;
        $min_megabytes = !empty($this->config->environ->staging->cpanel->subdomain->min_megabytes) ? $this->config->environ->staging->cpanel->subdomain->min_megabytes : 2500;

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
        if (!in_array($action, array('create', 'delete'))) {
            $this->log("Can't do cPanel database stuff. Action must be 'create' or 'delete'.", 'error');
            return false;
        }
        $error_string = sprintf("Can't %s %s cPanel database components.", $action, $environment);
        $this->log(sprintf('<h4>%s database components for %s cPanel account.</h4>',
            ucfirst($this->actions[$action]['present']), $environment), 'info');
        $db_args = !empty($this->config->environ->$environment->db) ? $this->config->environ->$environment->db : null;
        if (!isset($db_args->name, $db_args->username, $db_args->password)) {
            $this->log($error_string . " DB name, username and/or password are missing from config.", 'error');
            return false;
        }
        switch ($environment) {
            case 'live':
                $cpanel_username = !empty($this->config->environ->$environment->cpanel->account->username) ? $this->config->environ->$environment->cpanel->account->username : '';
                $cpanel_domain = !empty($this->config->environ->$environment->domain) ? $this->config->environ->$environment->domain : '';
                if (empty($cpanel_domain) || empty($cpanel_username)) {
                    $this->log($error_string . " Domain and/or cPanel username are missing from config.", 'error');
                    return false;
                }
                $cPanel_account = $this->find_cpanel_account($cpanel_domain, $cpanel_username);
                if (!$cPanel_account) {
                    $this->log($error_string . " Couldn't locate cPanel account.");
                    return false;
                }
                break;
            case 'staging':
                $slug = !empty($this->config->environ->staging->cpanel->subdomain->slug) ? $this->config->environ->staging->cpanel->subdomain->slug : '';
                if (empty($slug)) {
                    $this->log($error_string . " Staging cPanel subdomain slug missing from config.", 'error');
                    return false;
                }
                if (!$this->find_staging_cpanel()) {
                    $this->log(sprintf("%s Apparently subdomain <strong>%s</strong> doesn't exist in your staging cPanel accounts.",
                        $error_string, $slug));
                    return false;
                }
                break;
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
    function delete_live_stuff()
    {
        $this->log('<h2>Deleting nominated live stuff.</h2>', 'info');

        if ($this->can_do('delete_live_site')) {
            $deleted_cpanel = $this->delete_live_cpanel_account();
        } else if ($this->can_do('delete_live_db')) {
            $deleted_cpanel = $this->database_components('delete', 'live');
        }

        if ($this->can_do('delete_live_email_filters')) {
            $deleted_email_filters = $this->email_filters('delete', 'live');
        }

        if (((!$this->can_do('delete_live_site') && !$this->can_do('delete_live_db')) || !empty($deleted_cpanel))
            && (!$this->can_do('delete_live_email_filters') || !empty($deleted_email_filters))) {
            $this->log('Successfully deleted nominated live stuff.', 'success');
            return true;
        }
        $this->log('Something may have gone wrong deleting nominated live stuff.', 'error');
        return false;
    }

    /**
     * @return bool
     *
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
            $this->log(sprintf($error_string . "Apparently account with domain <strong>%s</strong> and username <strong>%s</strong> doesn't exist.", $domain, $username), 'error');
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
    function delete_staging_stuff()
    {
        $this->log('<h2>Deleting nominated staging stuff.</h2>', 'info');
        if ($this->can_do('delete_staging_db')) {
            $deleted_db = $this->database_components('delete', 'staging');
        }
        if ($this->can_do('delete_staging_subdomain'))
            $delete_cPanel_subdomain = $this->delete_staging_subdomain();

        if ($this->can_do('delete_staging_email_filters')) {
            $delete_email_filter = $this->email_filters('delete', 'staging');
        }
        if ((!$this->can_do('delete_staging_subdomain') || !empty($delete_cPanel_subdomain))
            && (!$this->can_do('delete_staging_db') || !empty($deleted_db))
            && (!$this->can_do('delete_staging_email_filters') || !empty($delete_email_filter))) {
            $this->log('Successfully deleted nominated staging stuff.', 'success');
            return true;
        }
        $this->log('Something may have gone wrong while deleting nominated staging stuff.', 'error');
        return false;
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
        if ($this->whm->delete_subdomain($slug)) {
            return true;
        }
        $this->log(sprintf("Something went wrong deleting subdomain in account with user <strong>%s</strong>.", $staging_cpanel_account['user']), 'error');
        return false;

    }

    /**
     * @param string $action
     * @param string $environment
     * @return bool
     */
    function email_filters(string $action = 'create', string $environment = 'live')
    {
        if (!in_array($action, array('create', 'delete'))) {
            $this->log("Can't do cPanel email filter stuff. Action should be 'create' or 'delete'.", 'error');
            return false;
        }
        $this->log(sprintf('<h4>%s %s cPanel email filters.</h4> ', ucfirst($this->actions[$action]['present']), $environment), 'info');
        $error_string = sprintf("Can't %s %s email filters.", $action, $environment);
        if (empty($this->config->environ->$environment->cpanel->email_filters)) {
            $this->log($error_string . " Filter args missing from config.", 'error');
            return false;
        }
        $cpanel_username = !empty($this->config->environ->primary->cpanel->username) ? $this->config->environ->primary->cpanel->username : '';
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

    function versionControlMainRepo(string $action = 'create')
    {
        if (!in_array($action, array('create', 'delete'))) {
            $this->log("Can't do main version control repository stuff. Action should be 'create' or 'delete'.", 'error');
            return false;
        }
        $repo_name = !empty($this->config->project_name) ? $this->config->project_name : '';
        if (empty($repo_name)) {
            $this->log(sprintf("Can't %s version control main repository. Repository name is empty. Repo name supposed to be based on project name so it's probably missing from config.", $action));
            return false;
        }
        $domain = !empty($this->config->environ->live->domain) ? $this->config->environ->live->domain : '';
        $repository = $this->github->repo($action, $repo_name, $domain);
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
     */
    function versionControlAccess(string $action = 'create', string $environment = 'live')
    {
        if (!in_array($action, array('create', 'delete'))) {
            $this->log("Can't do version control stuff. Action should be 'create' or 'delete'.", 'error');
            return false;
        }
        $this->log(sprintf('<h2>%s Version Control components.</h2>', ucfirst($this->actions[$action]['present'])), 'info');
        $repo_name = !empty($this->config->project_name) ? $this->config->project_name : '';
        $message_string = sprintf('%s version control components.', $environment);
        if (empty($repo_name)) {
            $this->log(sprintf("Can't %s Repository name is empty. Repo name supposed to be based on project name so it's probably missing from config.",
                $message_string, $action));
            return false;
        }
        if ($environment == 'local') {
            $this->log(sprintf("Can't %s Environment is local where VC components not needed.",
                $message_string, $action));
            return false;
        }
        $passphrase = $this->config->$environment->version_control->deploy_key->passphrase ?? '';
        $ssh_key = $this->terminal($environment)->SSHKey($action, 'github_' . $repo_name, $passphrase);
        $ssh_config = $this->terminal($environment)->SSHConfig($action, 'github_' . $repo_name, 'github.com', 'github_' . $repo_name, 'git');
        switch ($action) {
            case 'create':
                $deploy_key = $this->github->deploy_key($ssh_key, $repo_name);
                $upstream_repository = $this->github->find_repo($repo_name);
                if ($upstream_repository) {
                    $repository_root = $this->terminal($environment)->root;
                    switch ($environment) {
                        case 'live':
                            $repository_root .= '/public_html';
                            break;
                        case 'staging':
                            $repository_root = '/public_html' . $this->config->staging->cpanel->subdomain->directory;
                            break;
                    }
                    $downstream_repository = $this->whm->version_control('create',
                        'wordpress_website',
                        $repository_root,
                        json_encode((object)['url' => $upstream_repository['ssh_url'], 'remote_name' => "origin"]),
                        $this->config->environ->live->cpanel->account->username
                    );
                }
                break;
            case 'delete':
                $deploy_key = true;
                break;
        }
        if (!empty($ssh_key) && !empty($ssh_config) && !empty($deploy_key) && !empty($downstream_repository)) {
            $this->log(sprintf('Successfully %s %s', $this->actions[$action]['past'], $message_string), 'success');
            return true;
        }
        $this->log(sprintf("Something may have gone wrong while %s %s", $this->actions[$action]['present'], $message_string), 'error');
        return false;

    }

    function get_environment_url(string $environment = 'live')
    {
        $error_string = sprintf("Can't get %s environment url.", $environment);
        if ($environment == 'staging') {
            if (!$this->find_staging_cpanel())
                return false;
            $slug = !empty($this->config->environ->staging->cpanel->subdomain->slug) ? $this->config->environ->staging->cpanel->subdomain->slug : '';
            if (empty($slug)) {
                $this->log($error_string . " Subdomain slug missing.", 'error');
                return false;
            }
            $subdomain = $this->whm->get_subdomain($slug);
            print_r($subdomain);
            //$url =
        } else {
            if (empty($this->config->environ->$environment->domain)) {
                $this->log($error_string . " Domain missing from config.", 'error');
                return false;
            }
            $url = $this->config->environ->$environment->domain;
        }
        $protocol = 'https://';
        if ($environment == 'local')
            $protocol = 'http://';
        if (strpos($url, $protocol) !== 0)
            $url = $protocol . $url;
        return $url;
    }

    /**
     * @param string $action
     * @param string $environment
     * @return bool
     */
    function wordpress(string $action = 'install', string $environment = 'live')
    {
        if (!in_array($action, array('install', 'delete'))) {
            $this->log("Can't do WordPress stuff. Action must be 'install' or 'delete'.", 'error');
            return false;
        }
        $this->log(sprintf('%s WordPress and WP CLI.', ucfirst($this->actions[$action]['present'])), 'info');
        switch ($action) {
            case 'install':
                $this->terminal($environment)->WPCLI('install');
                $wp_args = !empty($this->config->wordpress) ? $this->config->wordpress : null;
                $wp_args->title = !empty($this->config->project_name) ? $this->config->project_name : 'Insert Site Title Here';
                $wp_args->url = $this->get_environment_url($environment);
                $db_args = !empty($this->config->environ->$environment->db) ? $this->config->environ->$environment->db : null;
                $success = $this->terminal($environment)->WordPress('install', (array)$db_args, (array)$wp_args);
                break;
            case 'delete':
                $deleted_wp = $this->terminal($environment)->WordPress('delete');
                $deleted_wp_cli = $this->terminal($environment)->WPCLI('delete');
                if ($deleted_wp && $deleted_wp_cli)
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
        if (empty($filter_name) || empty($this->config->project_name)) {
            $this->log("Can't format email filter name. Filter name or project name input missing.");
            return false;
        }
        return ucwords($this->config->project_name) . ' ' . $filter_name;
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


