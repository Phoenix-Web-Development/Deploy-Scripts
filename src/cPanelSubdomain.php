<?php

namespace Phoenix;

/**
 * Class cPanelSubdomain
 *
 * @package Phoenix
 */
class cPanelSubdomain extends Environ
{
    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * cPanel account containing the subdomain
     *
     * @var
     */
    public $cPanelAccount;

    /**
     * @var WHM
     */
    private $whm;

    /**
     * cPanelSubdomain constructor.
     *
     * @param string $environName
     * @param null $config
     * @param WHM|null $whm
     */
    public function __construct(string $environName = 'staging', $config = null, WHM $whm = null)
    {
        $this->whm = $whm;
        parent::__construct($environName, $config);
    }

    public function install(): void
    {
        $this->create();
    }

    /**
     * @return bool|null
     */
    public function create(): ?bool
    {
        $this->mainStr();
        $this->logStart();

        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $cPanelAccount = $this->getSubdomaincPanel();
        if ($cPanelAccount) {
            return $this->logFinish(true, sprintf('Subdomain with slug <strong>%s</strong> already exists in cPanel account with user <strong>%s</strong>. ',
                $args['cpanel']->subdomain->slug, $cPanelAccount['user']));
        }
        //search for the lowest # staging cPanel account with enough available space and inodes
        $rootDomain = $this->decidecPanelAccount();

        if (empty($rootDomain))
            return $this->logFinish(false, "Couldn't find staging cPanel account to use.");

        $this->log(sprintf("Subdomain with slug <strong>%s</strong> doesn't exist so attempt to create a new subdomain.", $args['cpanel']->subdomain->slug), 'success');

        $success = $this->whm->create_subdomain($args['cpanel']->subdomain->slug, $rootDomain, $args['cpanel']->subdomain->directory);
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


        //check subdomain exists to delete.
        $cPanelAccount = $this->getSubdomaincPanel();

        if (empty($cPanelAccount))
            return $this->logFinish(true, sprintf("Apparently subdomain <strong>%s</strong> doesn't exist in your staging cPanel accounts.", $args['cpanel']->subdomain->slug));

        $success['subdomain'] = $this->whm->delete_subdomain($args['cpanel']->subdomain->slug);

        $directory = $this->getEnvironDir('web');
        if (!empty($directory)) {
            $success['deletedDirectory'] = $this->terminal->ssh->delete($directory);
            $success['prunedDirectory'] = $this->terminal->dir()->prune(dirname($directory));
        }

        $success = !in_array(false, $success, true) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param $args
     * @return bool
     */
    protected function validate($args): bool
    {
        if (empty($args['cpanel']))
            return false;

        if ($this->getCaller() != 'getSubdomains') {

            if (empty($args['cpanel']->subdomain->slug))
                return $this->logError('Subdomain slug missing from config.');

            if (empty($args['cpanel']->accounts))
                return $this->logError('Staging cPanel accounts missing from config.');

            if (empty($args['cpanel']->subdomain->directory))
                return $this->logError('Directory input missing from config.');
        }
        return true;
    }

    /*
        protected
        function getArgs()
        {
            $environ = $this->name;
            return $this->config->environ->$environ->cpanel ?? null;
        }
    */
    /**
     * @return array|bool
     */
    public function getSubdomains()
    {
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $domains = [];
        if (!empty($args['cpanel']->accounts)) {
            foreach ($args['cpanel']->accounts as $account) {
                if (isset($account->domain, $account->username))
                    $domains[$account->domain] = $this->whm->list_domains($account->username)['sub_domains'];
            }
        }
        return $domains;
    }

    /**
     * Finds the cPanel account which contains the staging subdomain
     *
     * @return array|bool
     */
    public function getSubdomaincPanel()
    {
        if (!empty($this->cPanelAccount))
            return $this->cPanelAccount;
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        foreach ($args['cpanel']->accounts as $key => $cPanelAccount) {
            $subdomain = $this->whm->get_subdomain($args['cpanel']->subdomain->slug, $cPanelAccount->username);
            if ($subdomain) {
                $cPanelAccount = $this->whm->get_cpanel_account($subdomain['user']);
                return $this->cPanelAccount = $cPanelAccount;
            }
        }
        return false;
    }


    /**
     * Returns which cPanel account to use for new subdomain
     *
     * @return string|bool
     */
    protected function decidecPanelAccount()
    {
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $min_inodes = $args['cpanel']->subdomain->min_inodes ?? 50000;
        $min_megabytes = $args['cpanel']->subdomain->min_megabytes ?? 2500;

        foreach ($args['cpanel']->accounts as $account) {
            $quota = $this->whm->get_quota_info($account->username);
            if (empty($quota['inodes_remain']) || empty($quota['megabytes_remain']))
                return $this->logError(" Couldn't get a quota.");

            $log_chunk = sprintf(' add the staging subdomain to the cPanel account with domain <strong>%s</strong> and username <strong>%s</strong>.',
                $account->domain, $account->username);

            $log_criteria = '<li>It has <strong>%s</strong>%s available which is <span style="text-decoration:underline;">%s</span> than the minimum of <strong>%s</strong>.</li>';

            $inodesOperator = $quota['inodes_remain'] >= $min_inodes ? 'more' : 'less';
            $diskSpaceOperator = $quota['megabytes_remain'] >= $min_megabytes ? 'more' : 'less';

            $log = '<ul>' . sprintf($log_criteria, $quota['inodes_remain'], ' inodes', $inodesOperator, $min_inodes) .
                sprintf($log_criteria, $quota['megabytes_remain'], 'MB', $diskSpaceOperator, $min_megabytes) . '</ul>';

            if (strpos($log, 'less') === false) {
                $this->log('Can' . $log_chunk . $log, 'success');
                return $account->domain;
            }
            $this->log("Can't" . $log_chunk . $log);
        }
        return false;
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

        $accountStr = !empty($this->cPanelAccount['user']) ? ' in cPanel account with username <strong>%s</strong>' : '';
        $plural = $action == 'getSubdomains' ? 's' : '';
        return $this->_mainStr[$action] = $this->name . ' environ cPanel subdomain' . $plural . $accountStr;
    }
}