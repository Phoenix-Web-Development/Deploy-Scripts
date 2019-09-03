<?php


namespace Phoenix;

/**
 * Class EnvironVersionControl
 * @package Phoenix\Terminal
 */
class EnvironVersionControl extends AbstractDeployer
{

    /**
     * @var string
     */
    public $environ;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var GithubClient
     */
    private $github;

    /**
     * @var TerminalClient
     */
    private $terminal;

    /**
     * @var WHM
     */
    private $whm;

    /**
     * EnvironVersionControl constructor.
     * @param TerminalClient $terminal
     * @param GithubClient $github
     * @param WHM|null $whm
     * @param string $environ
     */
    function __construct($environ = 'live', TerminalClient $terminal = null, GithubClient $github = null, WHM $whm = null)
    {
        $this->environ = $environ;
        $this->logElement = 'h3';
        $this->terminal = $terminal;
        $this->github = $github;
        $this->whm = $whm;
        parent::__construct();
    }

    /**
     * @return bool|null
     * @throws \Github\Exception\MissingArgumentException
     */
    function create()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;

        $args = $this->getArgs();
        if (!$args)
            return $this->logError("Couldn't get args");

        $upstream_repository = $this->github->repo()->get($args['repo']['name']);
        if (!$upstream_repository)
            return $this->logError("Upstream repository not found.");
        if ($this->environ != 'local') {
            $sshKey = $this->whm->genkey($args['key']['name'], $args['key']['passphrase'], 2048, $args['cPanel_account']['user']);
            if (!empty($sshKey)) {
                $authorisedKey = $this->whm->authkey($args['key']['name'], 'authorize', $args['cPanel_account']['user']);
                $addedSSHConfig = $this->terminal->ssh_config()->create($args['key']['name'], 'github.com', $args['key']['name'], 'git');
            }

            $uploadedDeployKey = $this->github->deploy_key()->upload($args['repo']['name'], $args['key']['title'], $sshKey['key']);
            $sourceRepository = json_encode((object)[
                'url' => str_replace('git@github.com', $args['key']['name'], $upstream_repository['ssh_url']),
                'remote_name' => "origin"
            ]);

            $clonedRepository = $this->whm->version_control('clone',
                $args['repo']['dir'],
                $args['repo']['downstream_name'],
                $sourceRepository,
                $args['cPanel_account']['user']
            );
            if (!$clonedRepository)
                $clonedRepository = $this->whm->version_control('get', $args['repo']['dir'], $args['cPanel_account']['user']);

        } else {
            $localDirSetup = $this->terminal->localProjectDirSetup();
            $localDirSetup->setProjectArgs($args['project']);

            $webDirArgs = [
                'dir' => $args['repo']['dir'],
                'owner' => $args['repo']['owner'],
                'group' => $args['repo']['group'],
                'purpose' => 'git'
            ];

            if ($localDirSetup->create($webDirArgs)) {

                $clonedRepository = $this->terminal->git()->clone([
                    //'url' => str_replace('git@github.com', 'github', $upstream_repository['ssh_url']),
                    'url' => $upstream_repository['ssh_url'],
                    'worktree_path' => $args['repo']['worktree'],
                    'repo_path' => $args['repo']['dir']
                ]);

            }

        }


        if (!empty($clonedRepository) && $this->terminal->git()->waitForUnlock($args['repo']['dir'])) {
            if ($args['repo']['worktree'] != $args['repo']['dir'])
                $createdDotGit = $this->terminal->dotGitFile()->create($args['repo']['worktree'], $args['repo']['dir']);
            else
                $createdDotGit = true;
            if ($this->environ != 'local')
                $purgedGit = $this->terminal->git()->purge($args['repo']['dir']);
            $createdGitignore = $this->terminal->gitignore()->create($args['repo']['worktree']);
            if ($this->environ != 'local')
                $resetGit = $this->terminal->gitBranch()->reset(['worktree' => $args['repo']['worktree'], 'branch' => 'master']);

        }


        if ($this->environ == 'staging') { //webhook
            $webhook_config = $this->terminal->githubWebhookEndpointConfig()->create(
                $args['webhook']['endpoint_config_dir'],
                $args['repo']['worktree'],
                $args['webhook']['secret']
            );
            $webhook = $this->github->webhook()->create(
                $args['repo']['name'],
                $args['webhook']['url'],
                $args['webhook']['secret']
            );
        }

        $setGitUserConfig = $this->terminal->git()->setGitUser([
            'worktree_path' => $args['repo']['worktree'],
            'repo_path' => $args['repo']['dir'],
            'config_user' => $args['config']['user'],
            'config_email' => $args['config']['email']
        ]);

        $success = (
            ($this->environ == 'local' || (
                    !empty($sshKey)
                    && !empty($authorisedKey)
                    && !empty($addedSSHConfig)
                    && !empty($uploadedDeployKey)
                    && !empty($purgedGit)
                    && !empty($resetGit)
                ))
            && !empty($clonedRepository)
            && !empty($createdDotGit)
            && !empty($createdGitignore)
            && !empty($setGitUserConfig)
            && ($this->environ != 'staging' || (!empty($webhook) && !empty($webhook_config)))
        ) ? true : false;


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
        if (!$args)
            return $this->logError("Couldn't get args");


        if ($this->environ != 'local') {
            $downstream_repository = $this->whm->version_control('delete', $args['repo']['dir'], '', '', $args['cPanel_account']['user']);
            $sshKey = $this->whm->delkey($args['key']['name'], $args['cPanel_account']['user']);
            $deployKey = $this->github->deploy_key()->remove($args['repo']['name'], $args['key']['title']);
            $sshConfig = $this->terminal->ssh_config()->delete('github_' . $args['repo']['name']);
        }

        $gitignore = $this->terminal->gitignore()->delete($args['repo']['worktree']);

        $deleted_git_folder = $this->terminal->git()->delete($args['repo']['dir']);


        if ($this->environ == 'staging') { //webhook
            $webhook = $this->github->webhook()->remove($args['repo']['name'], $args['webhook']['url']);
            $webhook_config = $this->terminal->githubWebhookEndpointConfig()->delete($args['webhook']['endpoint_config_dir']);
        }

        if ($args['repo']['worktree'] != $args['repo']['dir'])
            $dotGit = $this->terminal->dotGitFile()->delete($args['repo']['worktree']);
        else
            $dotGit = true;
        $success = (
            ($this->environ == 'local' || (
                    !empty($downstream_repository)
                    && !empty($sshKey)
                    && !empty($deployKey)
                    && !empty($sshConfig)
                ))
            && !empty($gitignore)
            && !empty($deleted_git_folder)
            && ($this->environ != 'staging' || (
                    !empty($webhook)
                    && !empty($webhook_config))
            )
            && !empty($dotGit)
        ) ? true : false;

        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    function sync()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return false;
        $environ = $this->environ;

        $gitBranch = $this->terminal->gitBranch();

        $args['repo']['branch'] = 'master';
        $args['repo']['message'] = 'initial Deployer auto commit from ' . $environ . ' environment';
        $committedCurrent = $gitBranch->commit($args['repo']);
        $pulledCurrent = $gitBranch->pull($args['repo']);

        if ($environ != 'live') {
            $args['repo']['branch'] = 'dev';
            $gitBranch->checkout($args['repo']);
            $args['repo']['stream'] = 'up';
            $args['repo']['message'] = 'create dev branch';
            if ($gitBranch->check($args['repo']))
                $syncDevBranch = $gitBranch->pull($args['repo']);
            else
                $syncDevBranch = $gitBranch->commit($args['repo']);
        }

        $success = !empty($committedCurrent) && !empty($pulledCurrent) && ($environ != 'live' || !empty($syncDevBranch)) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @return bool
     */
    function validate()
    {
        return true;
    }

    /**
     * @return bool
     */
    protected function getArgs()
    {
        $environ = $this->environ;
        /*
        $root = $this->terminal->root;
        if (empty($root))
            return $this->logError(sprintf("Couldn't get %s environment root directory.", $environ));
*/
        $args['repo']['name'] = ph_d()->config->version_control->repo_name ?? '';
        if (empty($args['repo']['name']))
            return $this->logError("Repository name is missing from config.");
        $args['repo']['dir'] = ph_d()->get_environ_dir($environ, 'git');
        $args['repo']['downstream_name'] = $args['repo']['name'] . '_website';
        $args['repo']['worktree'] = ph_d()->get_environ_dir($environ, 'worktree');

        $args['repo']['owner'] = ph_d()->config->environ->$environ->dirs->web->owner ?? '';
        $args['repo']['group'] = ph_d()->config->environ->$environ->dirs->web->group ?? '';


        if ($environ != 'local') {
            $args['cPanel_account'] = ph_d()->find_environ_cpanel($environ);
            if (empty($args['cPanel_account']))
                return $this->logError(sprintf("Couldn't find %s cPanel account.", $environ));

            $args['key']['name'] = ph_d()->config->environ->$environ->ssh_keys->version_control_deploy_key->key_name ?? '';
            $args['key']['passphrase'] = '';
            $args['key']['title'] = ucfirst($environ) . ' cPanel';

            if ($environ == 'staging') {
                $args['webhook']['url'] = 'https://' . $args['cPanel_account']['domain'] . '/github-webhook.php?github=yes';
                $args['webhook']['endpoint_config_dir'] = ph_d()->get_environ_dir($environ, 'github_webhook_endpoint_config') . '/' . $args['repo']['name'] . '.json';
                $args['webhook']['secret'] = ph_d()->config->version_control->github->webhook->secret ?? '';
            }
        } else {
            $args['project'] = [
                'dir' => ph_d()->get_environ_dir('local', 'project') ?? '',
                'owner' => ph_d()->config->environ->$environ->dirs->project->owner ?? '',
                'group' => ph_d()->config->environ->$environ->dirs->project->group ?? '',
            ];
        }

        $args['config']['user'] = ph_d()->config->environ->$environ->version_control->config->user ?? ph_d()->config->version_control->config->user ?? '';
        $args['config']['email'] = ph_d()->config->environ->$environ->version_control->config->email ?? ph_d()->config->version_control->config->email ?? '';

        return $args;
    }

    //protected function mainStr(array $args = [])

    /**
     * @return string
     */
    protected function mainStr()
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        return $this->_mainStr[$action] = sprintf('%s version control components', $this->environ);
    }
}