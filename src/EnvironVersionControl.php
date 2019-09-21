<?php


namespace Phoenix;

/**
 * Class EnvironVersionControl
 *
 * @package Phoenix\Terminal
 */
class EnvironVersionControl extends AbstractDeployer
{

    /**
     * @var string
     */
    public $environ;

    /**
     * @var
     */
    private $config;

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
     *
     * @param Environ|cPanelAccount|cPanelSubdomain $environ
     * @param $config
     * @param TerminalClient|null $terminal
     * @param GithubClient|null $github
     * @param WHM|null $whm
     */
    public function __construct($environ, $config, TerminalClient $terminal = null, GithubClient $github = null, WHM $whm = null)
    {
        $this->environ = $environ;
        $this->config = $config;
        $this->terminal = $terminal;
        $this->github = $github;
        $this->whm = $whm;
        parent::__construct();
    }

    /**
     * @return bool|null
     * @throws \Github\Exception\MissingArgumentException
     */
    public function create(): ?bool
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
            return $this->logError('Upstream repository not found.');
        if ($this->environ->name !== 'local') {
            $sshKey = $this->whm->genkey($args['key']['name'], $args['key']['passphrase'], 2048, $args['cPanel_account']['user']);
            $success['sshKey'] = !empty($sshKey) ? true : false;
            if ($success['sshKey']) {
                $success['authorisedKey'] = $this->whm->authkey($args['key']['name'], 'authorize', $args['cPanel_account']['user']);
                $success['addedSSHConfig'] = $this->terminal->ssh_config()->create($args['key']['name'], 'github.com', $args['key']['name'], 'git');
            }

            $success['uploadedDeployKey'] = $this->github->deploy_key()->upload($args['repo']['name'], $args['key']['title'], $sshKey['key']);
            $sourceRepository = json_encode((object)[
                'url' => str_replace('git@github.com', $args['key']['name'], $upstream_repository['ssh_url']),
                'remote_name' => 'origin'
            ]);

            $success['clonedRepository'] = $this->whm->version_control('clone',
                $args['repo']['dir'],
                $args['repo']['downstream_name'],
                $sourceRepository,
                $args['cPanel_account']['user']
            );
            if (!$success['clonedRepository'])
                $success['clonedRepository'] = $this->whm->version_control('get', $args['repo']['dir'], $args['cPanel_account']['user']);

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

                $success['clonedRepository'] = $this->terminal->git()->clone([
                    //'url' => str_replace('git@github.com', 'github', $upstream_repository['ssh_url']),
                    'url' => $upstream_repository['ssh_url'],
                    'worktree_path' => $args['repo']['worktree'],
                    'repo_path' => $args['repo']['dir']
                ]);

            }

        }

        if (!empty($success['clonedRepository']) && $this->terminal->git()->waitForUnlock($args['repo']['dir'])) {
            if ($args['repo']['worktree'] !== $args['repo']['dir'])
                $success['createdDotGit'] = $this->terminal->dotGitFile()->create($args['repo']['worktree'], $args['repo']['dir']);

            if ($this->environ->name !== 'local')
                $success['purgedGit'] = $this->terminal->git()->purge($args['repo']['dir']);
            $success['createdGitignore'] = $this->terminal->gitignore()->create($args['repo']['worktree']);
            if ($this->environ->name !== 'local')
                $success['resetGit'] = $this->terminal->gitBranch()->reset(['worktree' => $args['repo']['worktree'], 'branch' => 'master']);

        }

        if ($this->environ->name === 'staging') { //webhook
            $success['webhook_config'] = $this->terminal->githubWebhookEndpointConfig()->create(
                $args['webhook']['endpoint_config_dir'],
                $args['repo']['worktree'],
                $args['webhook']['secret']
            );
            $success['webhook'] = $this->github->webhook()->create(
                $args['repo']['name'],
                $args['webhook']['url'],
                $args['webhook']['secret']
            );
        }

        $success['setGitUserConfig'] = $this->terminal->git()->setGitUser([
            'worktree_path' => $args['repo']['worktree'],
            'repo_path' => $args['repo']['dir'],
            'config_user' => $args['config']['user'],
            'config_email' => $args['config']['email']
        ]);

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
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return $this->logError("Couldn't get args");


        if ($this->environ->name !== 'local') {
            $success['downstream_repository'] = $this->whm->version_control('delete', $args['repo']['dir'], '', '', $args['cPanel_account']['user']);
            $success['sshKey'] = $this->whm->delkey($args['key']['name'], $args['cPanel_account']['user']);
            $success['deployKey'] = $this->github->deploy_key()->remove($args['repo']['name'], $args['key']['title']);
            $success['sshConfig'] = $this->terminal->ssh_config()->delete('github_' . $args['repo']['name']);
        }

        $success['gitignore'] = $this->terminal->gitignore()->delete($args['repo']['worktree']);

        $success['deleted_git_folder'] = $this->terminal->git()->delete($args['repo']['dir']);


        if ($this->environ->name === 'staging') { //webhook
            $success['webhook'] = $this->github->webhook()->remove($args['repo']['name'], $args['webhook']['url']);
            $success['webhook_config'] = $this->terminal->githubWebhookEndpointConfig()->delete($args['webhook']['endpoint_config_dir']);
        }

        if ($args['repo']['worktree'] !== $args['repo']['dir'])
            $success['dotGit'] = $this->terminal->dotGitFile()->delete($args['repo']['worktree']);

        $success = !in_array(false, $success, true) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    public function sync(): ?bool
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return false;

        $gitBranch = $this->terminal->gitBranch();

        $args['repo']['branch'] = 'master';
        $args['repo']['message'] = 'initial Deployer auto commit from ' . $this->environ->name . ' environment';
        $success['committedCurrent'] = $gitBranch->commit($args['repo']);
        $success['pulledCurrent'] = $gitBranch->pull($args['repo']);

        if ($this->environ->name !== 'live') {
            $args['repo']['branch'] = 'dev';
            $gitBranch->checkout($args['repo']);
            $args['repo']['stream'] = 'up';
            $args['repo']['message'] = 'create dev branch';
            if ($gitBranch->check($args['repo']))
                $success['syncDevBranch'] = $gitBranch->pull($args['repo']);
            else
                $success['syncDevBranch'] = $gitBranch->commit($args['repo']);
        }

        $success = !in_array(false, $success, true) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @return bool
     */
    private function validate(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    protected function getArgs(): bool
    {
        $environName = $this->environ->name;
        /*
        $root = $this->terminal->root;
        if (empty($root))
            return $this->logError(sprintf("Couldn't get %s environment root directory.", $environName));
*/
        $args['repo']['name'] = $this->config->version_control->repo_name ?? '';
        if (empty($args['repo']['name']))
            return $this->logError('Repository name is missing from config.');
        $args['repo']['dir'] = $this->environ->getEnvironDir('git');
        $args['repo']['downstream_name'] = $args['repo']['name'] . '_website';
        $args['repo']['worktree'] = $this->environ->getEnvironDir('worktree');

        $args['repo']['owner'] = $this->config->environ->$environName->dirs->web->owner ?? '';
        $args['repo']['group'] = $this->config->environ->$environName->dirs->web->group ?? '';


        if ($this->environ->name !== 'local') {
            $args['cPanel_account'] = $this->environ->findcPanel();
            if (empty($args['cPanel_account']))
                return $this->logError(sprintf("Couldn't find %s cPanel account.", $this->environ->name));

            $args['key']['name'] = $this->config->environ->$environName->ssh_keys->version_control_deploy_key->key_name ?? '';
            $args['key']['passphrase'] = '';
            $args['key']['title'] = ucfirst($environName) . ' cPanel';

            if ($this->environ->name === 'staging') {
                $args['webhook']['url'] = 'https://' . $args['cPanel_account']['domain'] . '/github-webhook.php?github=yes';
                $args['webhook']['endpoint_config_dir'] = $this->environ->getEnvironDir('github_webhook_endpoint_config') . '/' . $args['repo']['name'] . '.json';
                $args['webhook']['secret'] = $this->config->version_control->github->webhook->secret ?? '';
            }
        } else {
            $args['project'] = [
                'dir' => $this->environ->getEnvironDir('project') ?? '',
                'owner' => $this->config->environ->$environName->dirs->project->owner ?? '',
                'group' => $this->config->environ->$environName->dirs->project->group ?? '',
            ];
        }

        $args['config']['user'] = $this->config->environ->$environName->version_control->config->user ?? $this->config->version_control->config->user ?? '';
        $args['config']['email'] = $this->config->environ->$environName->version_control->config->email ?? $this->config->version_control->config->email ?? '';

        return $args;
    }

    //protected function mainStr(array $args = [])

    /**
     * @return string
     */
    protected function mainStr(): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        return $this->_mainStr[$action] = sprintf('%s version control components', $this->environ->name);
    }
}