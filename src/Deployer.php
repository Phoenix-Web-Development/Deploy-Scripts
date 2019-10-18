<?php

namespace Phoenix;

use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use phpseclib\Net\SSH2;
use Github\Client;
use stdClass;
use Phoenix\Curl;
use Phoenix\WHM;

//use Phoenix\Functions;

/**
 * Class Deployer
 *
 * @property WHM $whm
 * @property stdClass $config
 * @property Bitbucket $bitbucket
 * @property array $environ
 * @property GithubClient $github
 * @property array $placeholders
 * @property TerminalClient $terminal
 *
 * @package Phoenix
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
    private $_environ;

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
    private $_placeholders;

    /**
     * @var
     */
    public $configControl;

    /**
     * @var Template
     */
    public $template;

    /**
     * @return null|Deployer
     */
    public static function instance(): ?Deployer
    {
        if (self::$_instance === null) {
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
        if (!defined('BASE_DIR')) define('BASE_DIR', __DIR__);
        if (!defined('CONFIG_DIR')) define('CONFIG_DIR', BASE_DIR . '/../configs/');
        if (!defined('BASH_WRAPPER')) define('BASH_WRAPPER', BASE_DIR . '/../bash/wrapper.sh');

        new Logging();
        $this->actionRequests = new ActionRequests();

        $this->configControl = new ConfigControl();
        if (!empty($this->configControl->getConfigSelected())) {
            $config = $this->configControl->substitutePlaceholders($this->config, $this->placeholders);
            $this->configControl->setConfig($config);
        }

        $this->template = new Template();

        if (!empty($this->config->environ->local->wp_cli_cache_dir))
            putenv('WP_CLI_CACHE_DIR=' . $this->config->environ->local->wp_cli_cache_dir);
    }

    /**
     * @return array|bool
     */
    protected function placeholders()
    {
        if (!empty($this->_placeholders))
            return $this->_placeholders;

        $placeholders = array(
            'project_name' => ucwords($this->config->project->name ?? ''),
            'root_email_folder' => $this->config->project->root_email_folder ?? '',
            'live_domain' => $this->environ('live')->getEnvironURL() ?? '',
            'staging_domain' => $this->environ('staging')->getEnvironURL() ?? '',
            'live_cpanel_username' => $this->config->environ->live->cpanel->account->username ?? '',
            //'live_admin_email' => $this->environ('live')->getAdminEmail() ?? '',
            //'staging_admin_email' => $this->environ('staging')->getAdminEmail() ?? '',
            //'local_admin_email' => $this->environ('local')->getAdminEmail() ?? '',
        );
        $return = [];
        foreach ($placeholders as $placeholderName => $placeholder) {
            if (empty($placeholder)) {
                $this->log('Couldn\'t obtain value for <strong>' . $placeholderName . '</strong> config placeholder.');
                return [];
            }
            $return['%' . $placeholderName . '%'] = $placeholder;
        }
        return $this->_placeholders = $return;
    }

    /**
     * @return bool
     * @throws \Github\Exception\MissingArgumentException
     */
    public function run(): bool
    {
        $this->template->get('header');
        $action = '';
        if ($this->actionRequests->canDo('update'))
            $action = 'update';
        elseif ($this->actionRequests->canDo('create'))
            $action = 'deploy';
        elseif ($this->actionRequests->canDo('transfer'))
            $action = 'transfer';
        elseif ($this->actionRequests->canDo('delete'))
            $action = 'delete';
        if (!empty($action)) {
            $environments = array('live', 'staging', 'local');
            switch($action) {
                case 'delete':
                    if ($this->actionRequests->canDo('delete_version_control'))
                        $this->versionControlMainRepo('delete');
                    if ($this->actionRequests->canDo('delete_live_stuff'))
                        $this->doRemoteEnvironStuff('delete', 'live');
                    if ($this->actionRequests->canDo('delete_staging_stuff'))
                        $this->doRemoteEnvironStuff('delete', 'staging');
                    if ($this->actionRequests->canDo('delete_local_stuff'))
                        $this->doLocalStuff('delete');
                    break;
                case 'deploy':
                    if ($this->actionRequests->canDo('create_version_control'))
                        $this->versionControlMainRepo('create');
                    if ($this->actionRequests->canDo('create_live_stuff'))
                        $this->doRemoteEnvironStuff('create', 'live');
                    if ($this->actionRequests->canDo('create_staging_stuff'))
                        $this->doRemoteEnvironStuff('create', 'staging');
                    if ($this->actionRequests->canDo('create_local_stuff'))
                        $this->doLocalStuff('create');
                    break;
                case 'update':
                    foreach ($environments as $environment) {
                        if ($this->actionRequests->canDo('update_' . $environment . '_stuff'))
                            $this->updateWP($environment);
                    }
                    break;
                case 'transfer':
                    foreach ($environments as $fromEnvironment) {
                        foreach ($environments as $destEnvironment) {
                            if ($fromEnvironment !== $destEnvironment && $this->actionRequests->canDo('transfer_wpdb_' . $fromEnvironment . '_to_' . $destEnvironment)) {
                                $wpdb = new TransferWPDB($this->config,
                                    $this->environ($fromEnvironment),
                                    $this->terminal($fromEnvironment),
                                    $this->environ($destEnvironment),
                                    $this->terminal($destEnvironment),
                                    $this->actionRequests);
                                $wpdb->transfer();
                            }
                        }
                    }
                    break;
            }
            $this->log(sprintf('<h2>Finished %s</h2>', ucfirst($this->actions[$action]['present'])), 'info');
            template()->get('reload');
        } else {
            template()->get('form');
            $unixUser = posix_getpwuid(posix_geteuid())['name'] ?? 'unknown';
            $unixUser = sprintf('Local server unix user is <strong>%s</strong>.', $unixUser);
            $root = $this->terminal('local')->root;
            $root = !empty($root) ? $root : 'unknown';
            $root = sprintf(' Local root directory is <strong>%s</strong>.', $root);
            $this->log($unixUser . $root, 'info');
            $diskSpace = $this->getWHMDiskSpace();
            $plan = $this->config->environ->live->cpanel->create_account_args->plan ?? '';
            if (!empty($plan)) {
                $package_size = $this->whm->get_pkg_info($plan)['data']['pkg']['QUOTA'];
                if (($diskSpace['total'] - $diskSpace['allocated']) > $package_size)
                    $disk_message = 'You have';
                else
                    $disk_message = 'Not enough';
                $this->log(sprintf('Total disk space - <strong>%s</strong>MB. Disk used - <strong>%s</strong>MB. Allocated disk space - <strong>%s</strong>MB. %s unallocated disk space for a new <strong>%s</strong>MB cPanel account.',
                    $diskSpace['total'], $diskSpace['used'], $diskSpace['allocated'], $disk_message, $package_size), 'info');
                $this->log('<h3>cPanel Staging Subdomains</h3>' . build_recursive_list((array)$this->environ('staging')->getSubdomains()), 'light');
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
            || !class_exists(Curl::class)
            || !class_exists(WHM::class)
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
     * @param string $name
     * @return cPanelAccount|cPanelSubdomain|Environ|string
     */
    protected function environ(string $name = 'live')
    {
        if (!empty($this->_environ[$name]))
            return $this->_environ[$name];
        switch($name) {
            case 'live':
                $environ = new cPanelAccount($name, $this->config, $this->whm, $this->actionRequests);
                break;
            case 'staging':
                $environ = new cPanelSubdomain($name, $this->config, $this->whm);
                break;
            case 'local':
                $environ = new Environ($name, $this->config);
                break;
        }

        return $this->_environ[$name] = $environ ?? null;
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
            $this->log(sprintf('%s Token missing from config.', $error_string));
            return false;
        }
        if (empty($this->config->version_control->github->user)) {
            $this->log(sprintf('%s Github user missing from config.', $error_string));
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
     * @param string $environ
     * @return bool|TerminalClient
     */
    public function terminal(string $environ = 'live')
    {
        if (!empty($this->_terminal->$environ))
            return $this->_terminal->$environ;
        if (empty($this->_terminal))
            $this->_terminal = new stdClass();
        //$error_string = sprintf("Can't connect %s environment via SSH.", $environ);
        $terminal = new TerminalClient($environ);

        if ($environ !== 'local') {
            $sftp = $this->get_phpseclib('sftp', $environ);
            if (!empty($sftp)) {
                $terminal->ssh = $sftp;
            }
        }
        //$this->_terminal->$environ = $terminal;
        //$this->environ($environ)->setRoot($terminal->root);
        $this->environ($environ)->setTerminal($terminal);
        return $this->_terminal->$environ = $terminal;;
    }

    /**
     * @param string $protocol
     * @param string $environ
     * @return bool|SSH2|SFTP
     */
    private function get_phpseclib($protocol = 'ssh', string $environ = 'live')
    {
        $message = sprintf('%s environ %s connection.', $environ, $protocol);
        $sshArgs = $this->environ($environ)->getSSHArgs();
        if (empty($sshArgs)) {
            $this->log('Can\'t connect via SSH. SSH args missing.');
            return false;
        }
        if (empty(gethostbyname($sshArgs->hostname))) {
            $this->log('Can\'t connect via SSH to <strong>' . $sshArgs->hostname . '</strong>. Couldn\'t obtain IP.');
            return false;
        }
        switch($protocol) {
            case 'ssh':
                $ssh = new SSH2($sshArgs->hostname, $sshArgs->port);
                break;
            case 'sftp':
                $ssh = new SFTP($sshArgs->hostname, $sshArgs->port);
                break;
            default:
                return false;
                break;
        }
        //$passphrase = $this->config->environ->local->ssh_keys->live->passphrase ?? '';
        //$key_name = $this->config->environ->local->ssh_keys->live->key_name ?? '';
        if ($ssh === null) {
            return false;
        }

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
        set_error_handler(array($this, 'phpseclibErrorHandler'), E_USER_NOTICE);
        if ($ssh->login($sshArgs->username, $sshArgs->password))
            $this->log('Successfully authenticated ' . $message, 'success');
        else {
            $lastError = error_get_last();
            if (strpos($lastError['file'], '/vendor/phpseclib/phpseclib/phpseclib/') !== false) {
                $message .= '<strong>phpseclib Error:</strong><code>' . $lastError['message'] . '<br>Error on line <strong>' . $lastError['line'] . '</strong> in file <strong>' . $lastError['file'] . '</strong></code>';
            }
            $this->log("Couldn't authenticate " . $message);
        }
        restore_error_handler();
        return $ssh;
    }

    /**
     * @param $number
     * @param $string
     * @param $file
     * @param $line
     * @return bool
     */
    public function phpseclibErrorHandler($number, $string, $file, $line): bool
    {
        if (strpos($file, '/vendor/phpseclib/phpseclib/phpseclib/') !== false) {
            return true;
        }
        return false;
    }


    /**
     * @return array|bool|stdClass
     */
    protected function config()
    {
        return $this->configControl->config;
    }


    /**
     * @param string $action
     * @param string $environ
     * @return bool
     * @throws \Github\Exception\MissingArgumentException
     */
    public function doRemoteEnvironStuff($action = 'create', $environ = 'live'): bool
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s environ stuff.", $environ)))
            return false;
        if (!in_array($environ, array('live', 'staging'))) {
            $this->log(sprintf("Can't %s stuff. Environment must be 'live' or 'staging'.", $action), 'error');
            return false;
        }
        $this->log(sprintf('<h2>%s nominated %s stuff.</h2>', ucfirst($this->actions[$action]['present']), $environ), 'info');
        $success = [];

        //actual site environment and db - logic mess
        if ($environ === 'live' && $this->actionRequests->canDo($action . '_live_site'))
            $success['live_cpanel_account'] = $this->environ('live')->$action();
        elseif ($action === 'create' && $environ === 'staging' && $this->actionRequests->canDo($action . '_staging_subdomain'))
            $success['staging_subdomain'] = $this->environ('staging')->$action();

        if ($this->actionRequests->canDo($action . '_' . $environ . '_db')
            && ($action === 'create' || $environ === 'staging' || ($action === 'delete' && !$this->actionRequests->canDo($action . '_live_site')))) {
            //$dbConfig = (array)$this->config->environ->$environ->db;
            $dbConfig = $this->config->environ->$environ->db ?? null;
            $databaseComponents = new DatabaseComponents($environ, $dbConfig, $this->whm);
            $success['db'] = $databaseComponents->$action();
        }

        //shared actions
        if ($this->actionRequests->canDo($action . '_' . $environ . '_email_filters')) {
            $email_filters = new EmailFilters($environ, $this->whm);
            $success['email_filters'] = $email_filters->$action();
        }

        if (($action === 'create' && $this->actionRequests->canDo($action . '_' . $environ . '_initial_git_commit'))
            || $this->actionRequests->canDo($action . '_' . $environ . '_version_control')) {
            $versionControl = new EnvironVersionControl(
                $this->environ($environ),
                $this->config,
                $this->terminal($environ),
                $this->github,
                $this->whm
            );
            if ($this->actionRequests->canDo($action . '_' . $environ . '_version_control'))
                $success['setup_version_control'] = $versionControl->$action();
        }

        if ($this->actionRequests->canDo($action . '_' . $environ . '_wp')) {
            $liveURL = $this->environ('live')->getEnvironURL(true, true);
            $wp = new WordPress($this->environ($environ), $this->config, $this->terminal($environ), $this->actionRequests, $liveURL, $this->whm);
            $success['wp'] = $wp->$action();
        }

        if ($action === 'create' && $this->actionRequests->canDo($action . '_' . $environ . '_initial_git_commit'))
            $success['synced'] = $versionControl->sync();

        if ($action === 'delete' && $environ === 'staging' && $this->actionRequests->canDo($action . '_staging_subdomain')) {
            $terminal = $this->terminal('staging');
            $success['staging_subdomain'] = $this->environ('staging')->$action();
        }

        $success = !in_array(false, $success, true) ? true : false;

        if ($success) {
            $this->log(sprintf('Successfully %s nominated %s stuff.', $this->actions[$action]['past'], $environ), 'success');
            return true;
        }
        $this->log(sprintf('Something may have gone wrong while %s nominated %s stuff.', $this->actions[$action]['present'], $environ), 'error');
        return false;
    }

    /**
     * @param string $action
     */
    public function doLocalStuff($action = 'create'): void
    {
        $environ = $this->environ('local');

        if ($this->actionRequests->canDo($action . '_local_version_control') ||
            ($action === 'create' && $this->actionRequests->canDo('create_local_initial_git_commit')))
            $versionControl = new EnvironVersionControl(
                $this->environ('local'),
                $this->config,
                $this->terminal('local'),
                $this->github,
                $this->whm
            );

        if ($this->actionRequests->canDo($action . '_local_version_control'))
            $versionControl->$action();

        if ($this->actionRequests->canDo($action . '_local_virtual_host')) {
            $domain = $this->config->environ->local->domain ?? '';
            $sitesAvailable = $this->config->environ->local->dirs->sites_available->path ?? '';

            $webDir = $environ->getEnvironDir('web');
            $webOwner = $this->config->environ->local->dirs->web->owner ?? '';
            $webGroup = $this->config->environ->local->dirs->web->group ?? '';

            $logDir = $environ->getEnvironDir('log');

            $localDirSetup = $this->terminal('local')->LocalProjectDirSetup();
            $localDirSetup->setProjectArgs([
                'dir' => $environ->getEnvironDir('project') ?? '',
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


            if (!$logDirSuccess && $action === 'delete') {
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

        if ($this->actionRequests->canDo($action . '_local_database_components')) {
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

            $dbConfig = $this->config->environ->$environ->db ?? null;
            $databaseComponents = new DatabaseComponents('local', $dbConfig, null, $client);
            $databaseComponents->$action();
        }

        if ($this->actionRequests->canDo($action . '_local_wp')) {
            $liveURL = $this->environ('live')->getEnvironURL(true, true);
            $wp = new WordPress($this->environ('local'), $this->config, $this->terminal('local'), $this->actionRequests, $liveURL);
            $wp->$action;
        }

        if ($action === 'create' && $this->actionRequests->canDo('create_local_initial_git_commit')) {
            $versionControl->sync();
        }
    }


    /**
     * @param string $action
     * @return bool
     */
    public function versionControlMainRepo(string $action = 'create'): bool
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
        $this->log(sprintf('Something may have gone wrong while %s version control main repository.', $this->actions[$action]['present']), 'error');
        return false;
    }


    /**
     * @return array|bool
     */
    public function getWHMDiskSpace()
    {
        $accounts = $this->whm->get_cpanel_accounts();
        $diskUsed = 0;
        $diskLimit = 0;
        foreach ($accounts as $account) {
            $diskUsed += (int)$account['diskused'];
            $diskLimit += (int)$account['disklimit'];
        }
        $diskUsage = $this->whm->get_disk_usage();
        $diskTotal = $diskUsage['total'] ?? $this->config->whm->disk_total ?? 'unknown ';
        return array(
            'used' => $diskUsed,
            'allocated' => $diskLimit,
            'total' => $diskTotal
        );
    }

    /**
     * @param string $environ
     * @return bool
     */
    public function updateWP($environ = 'live'): bool
    {
        $mainStr = sprintf(' WordPress in %s environment. ', $environ);
        $this->log('<h2>Updating ' . $mainStr . '</h2>', 'info');
        $directory = $this->environ('live')->getEnvironDir('web');
        $errorString = "Can't update " . $mainStr;
        if (empty($directory)) {
            $this->log($errorString . " Couldn't get web directory.");
            return false;
        }
        $WPCLI = $this->terminal->wp_cli()->installOrUpdate();
        if (!$WPCLI) {
            $this->log(sprintf('Failed to update WordPress in %s environment.', $environ));
            return false;
        }
        $backupDB = new TransferWPDB();
        $backup = $backupDB->backup($environ, $this->terminal($environ));
        if ($backup) {
            $gitPull = $this->terminal($environ)->gitBranch()->pull(['worktree' => $directory, 'branch' => 'dev']);
            if ($gitPull) {
                $wp_update = $this->terminal($environ)->wp()->update(['directory' => $directory]);
                if ($wp_update)
                    $git_commit = $this->terminal($environ)->gitBranch()->commit($directory, 'dev');
            }
        }
        if (!empty($backup) && !empty($gitPull) && !empty($wp_update) && !empty($git_commit)) {
            $this->log(sprintf('Successfully updated WordPress in %s environment.', $environ), 'success');
            return true;
        }
        $this->log(sprintf('Failed to update WordPress in %s environment.', $environ));
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