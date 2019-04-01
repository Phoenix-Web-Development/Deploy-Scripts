<?php


namespace Phoenix;

/**
 * Class EnvironVersionControl
 * @package Phoenix\Terminal
 */
class EnvironVersionControl extends AbstractDeployer
{

    public $environ;

    protected $logElement = 'h3';

    private $github;

    private $terminal;

    private $whm;

    function __construct(TerminalClient $terminal, GithubClient $github, WHM $whm = null, $environ = 'live')
    {
        $this->environ = $environ;
        $this->logElement = 'h3';
        $this->terminal = $terminal;
        $this->github = $github;
        $this->whm = $whm;
        parent::__construct();
    }

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
            $sshKey = $this->whm->genkey($args['key']['name'], $args['key_passphrase'], 2048, $args['cPanel_account']['user']);
            if (!empty($sshKey)) {
                $authorisedKey = $this->whm->authkey($args['key']['name']);
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
                $sourceRepository
            );
            if (!$clonedRepository)
                $clonedRepository = $this->whm->version_control('get', $args['repo']['dir']);
        } else {
            d($this->terminal->whoami());
            $clonedRepository = $this->terminal->Git()->clone([
                //'url' => str_replace('git@github.com', 'github', $upstream_repository['ssh_url']),
                'url' => $upstream_repository['ssh_url'],
                'worktree_path' => $args['repo']['dir']
            ]);
        }


        if (!empty($clonedRepository) && $this->terminal->git()->waitForUnlock($args['repo']['dir'])) {
            if ($args['repo']['worktree'] != $args['repo']['dir'])
                $createdDotGit = $this->terminal->dotGitFile()->create($args['repo']['worktree'], $args['repo']['dir']);
            $createdGitignore = $this->terminal->gitignore()->create($args['repo']['worktree']);
            $purgedGit = $this->terminal->git()->purge($args['repo']['dir']);
            if ($clonedRepository)
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

        $this->terminal->exec('git config --global user.name "James Jones"; git config --global user.email "james.jones@phoenixweb.com.au"');
        $success = (!empty($sshKey)
            && !empty($authorisedKey)
            && !empty($addedSSHConfig)
            && !empty($uploadedDeployKey)
            && !empty($clonedRepository)
            && !empty($createdDotGit)
            && !empty($createdGitignore)
            && !empty($purgedGit)
            && !empty($resetGit)
            && ($this->environ != 'staging' || (!empty($webhook) && !empty($webhook_config)))
        ) ? true : false;


        return $this->logFinish($success);

    }

    function delete()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return $this->logError("Couldn't get args");


        $gitignore = $this->terminal->gitignore()->delete($args['repo']['worktree']);
        $downstream_repository = $this->whm->version_control('delete', $args['repo']['dir'], '', '', $args['cPanel_account']['user']);
        $deleted_git_folder = $this->terminal->git()->delete($args['repo']['dir']);
        $sshKey = $this->whm->delkey($args['key']['name'], $args['cPanel_account']['user']);
        $sshConfig = $this->terminal->ssh_config()->delete('github_' . $args['repo']['name']);
        $deployKey = $this->github->deploy_key()->remove($args['repo']['name'], $args['key']['title']);

        if ($this->environ == 'staging') { //webhook
            $webhook = $this->github->webhook()->remove($args['repo']['name'], $args['webhook']['url']);
            $webhook_config = $this->terminal->githubWebhookEndpointConfig()->delete($args['webhook']['endpoint_config_dir']);
        }
        $dotGit = $this->terminal->ssh->delete($args['repo']['worktree'] . '/.git');

        $success = (!empty($gitignore)
            && !empty($downstream_repository)
            && !empty($sshKey)
            && !empty($sshConfig)
            && !empty($deployKey)
            && !empty($deleted_git_folder)
            && ((!empty($webhook) && !empty($webhook_config)) || $this->environ != 'staging')
            && !empty($dotGit)
        ) ? true : false;

        return $this->logFinish($success);

    }

    function validate()
    {
        return true;
    }

    protected function getArgs()
    {
        $environ = $this->environ;
        $root = $this->terminal->root;
        if (empty($root))
            return $this->logError(sprintf("Couldn't get %s environment root directory.", $environ));


        $args['repo']['name'] = ph_d()->config->version_control->repo_name ?? '';
        if (empty($args['repo']['name']))
            return $this->logError("Repository name is missing from config.");
        $args['repo']['dir'] = ph_d()->get_environ_dir($environ, 'git');
        $args['repo']['downstream_name'] = $args['repo']['name'] . '_website';
        $args['repo']['worktree'] = ph_d()->get_environ_dir($environ, 'web');

        if ($environ != 'local') {
            $args['cPanel_account'] = ph_d()->find_environ_cpanel($environ);
            if (empty($args['cPanel_account']))
                return $this->logError(sprintf("Couldn't find %s cPanel account.", $environ));

            $args['key']['name'] = ph_d()->config->environ->$environ->ssh_keys->version_control_deploy_key->key_name ?? '';
            $args['key']['passphrase'] = '';
            $args['key']['title'] = ucfirst($environ) . ' cPanel';

            $args['webhook']['url'] = 'https://' . $args['cPanel_account']['domain'] . '/github-webhook.php?github=yes';
            $args['webhook']['endpoint_config_dir'] = ph_d()->get_environ_dir($environ, 'github_webhook_endpoint_config') . '/' . $args['repo']['name'] . '.json';
            $args['webhook']['secret'] = ph_d()->config->version_control->github->webhook->secret ?? '';
        }
        return $args;
    }

    //protected function mainStr(array $args = [])
    protected function mainStr()
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        d($action);
        d($this->_mainStr);
        d($this->environ);
        return $this->_mainStr[$action] = sprintf('%s version control components', $this->environ);
    }
}