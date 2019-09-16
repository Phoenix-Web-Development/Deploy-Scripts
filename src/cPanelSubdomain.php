<?php

namespace Phoenix;

/**
 * Class WordPress
 * @package Phoenix
 */
class cPanelSubdomain extends AbstractDeployer
{
    /**
     * @var ActionRequests
     */
    private $actionRequests;

    /**
     * @var string
     */
    public $config;

    /**
     * @var string
     */
    public $environ;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var
     */
    public $stagingcPanelAccount;

    /**
     * @var TerminalClient
     */
    private $terminal;

    /**
     * @var WHM
     */
    private $whm;

    /**
     * cPanelSubdomain constructor.
     * @param string $environ
     * @param null $config
     * @param TerminalClient|null $terminal
     * @param ActionRequests|null $actionRequests
     * @param WHM|null $whm
     */
    public function __construct($environ = 'live', $config = null, TerminalClient $terminal = null, ActionRequests $actionRequests = null, WHM $whm = null)
    {
        $this->config = $config;
        $this->environ = $environ;
        $this->terminal = $terminal;
        $this->whm = $whm;
        $this->actionRequests = $actionRequests;
        parent::__construct();
    }

    public function install()
    {
        $this->create();
    }

    /**
     * @return bool|null
     */
    public function create()
    {
        $this->mainStr();
        $this->logStart();

        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $stagingcPanelAccount = $this->findSubdomaincPanel();
        if ($stagingcPanelAccount) {
            return $this->logFinish(true, sprintf("Subdomain with slug <strong>%s</strong> already exists in cPanel account with user <strong>%s</strong>. ",
                $args->subdomain->slug, $stagingcPanelAccount['user']));
        }
        //search for the lowest # staging cPanel account with enough available space and inodes
        $stagingcPanelKey = $this->decideSubdomaincPanelAccount($args->accounts);

        if (empty($stagingcPanelKey))
            return $this->logFinish(false, "Couldn't find staging cPanel account to use.");

        $this->log(sprintf("Subdomain with slug <strong>%s</strong> doesn't exist so attempt to create a new subdomain.", $args->subdomain->slug), 'success');

        $success = $this->whm->create_subdomain($args->subdomain->slug, $args->accounts->$stagingcPanelKey->domain, $args->subdomain->directory))
        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    public function delete()
    {
        $this->mainStr();
        $this->logStart();
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;


        //check subdomain exists to delete.
        $stagingcPanelAccount = $this->findSubdomaincPanel();

        if (empty($stagingcPanelAccount))
            return $this->logFinish(true, sprintf("Apparently subdomain <strong>%s</strong> doesn't exist in your staging cPanel accounts.", $args->subdomain->slug));

        /*
                $this->log(sprintf("Deleting staging site subdomain <strong>%s</strong> in cPanel account with username <strong>%s</strong>.",
                    $args->subdomain->slug, $stagingcPanelAccount['user']), 'info');
        */
        $success['subdomain'] = $this->whm->delete_subdomain($args->subdomain->slug);

        $directory = $this->getEnvironDir('staging', 'web');
        if (!empty($directory)) {
            $success['deletedDirectory'] = $this->terminal->ssh->delete($directory);
            $success['prunedDirectory'] = $this->terminal->dir()->prune(dirname($directory));
        }

        $success = !in_array(false, $success) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param $args
     * @return bool
     */
    protected function validate(array $args = [])
    {
        if (empty($args))
            return false;

        if (empty($args->subdomain->slug))
            return $this->logError("Subdomain slug missing.");

        if (empty($args->accounts))
            return $this->logError("Staging accounts missing.");

        if (empty($args->subdomain->directory))
            return $this->logError("Directory input missing.");
        return true;
    }

    /**
     * @return array|bool
     */
    protected
    function getArgs()
    {
        $environ = $this->environ;
        $args = $this->config->environ->staging->cpanel ?? null;
        return $args;
    }

    /**
     * @return array
     */
    public function getStagingSubdomains()
    {
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $domains = [];
        if (!empty($args->accounts)) {
            foreach ($args->accounts as $account) {
                if (isset($account->domain, $account->username))
                    $domains[$account->domain] = $this->whm->list_domains($account->username)['sub_domains'];
            }
        }
        return $domains;
    }

    /**
     * Finds cPanel account for staging subdomain
     *
     * @param string $subdomain_slug
     * @param $cpanel_accounts
     * @return bool
     */
    public function findSubdomaincPanel()
    {
        if (!empty($this->stagingcPanelAccount))
            return $this->stagingcPanelAccount;

        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        if (empty($args->subdomain->slug))
            return $this->logError("cPanel subdomain slug missing.");


        $subdomain_slug = $args->subdomain->slug;
        if (empty($args->accounts))
            return $this->logError("cPanel accounts to search missing.");


        $cpanel_accounts = $args->accounts;
        foreach ($cpanel_accounts as $key => $cpanel_account) {
            $subdomain = $this->whm->get_subdomain($subdomain_slug, $cpanel_account->username);
            if ($subdomain) {
                $cpanel_account = $this->whm->get_cpanel_account($subdomain['user']);
                return $this->stagingcPanelAccount = $cpanel_account;
            }
        }
        return false;
    }


    /**
     * @param $cpanel_accounts
     * @return bool|int|string
     */
    public function decideSubdomaincPanelAccount()
    {
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $min_inodes = $args->subdomain->min_inodes ?? 25000;
        $min_megabytes = $args->subdomain->min_megabytes ?? 2500;

        foreach ($args->accounts as $key => $account) {
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
                return $this->stagingcPanelKey = $key;
            } else {
                $this->log("Can't" . $log_chunk . $log);
            }
        }
        return false;
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
        return $this->_mainStr[$action] = sprintf('%s cPanel subdomain', $this->environ);
    }
}