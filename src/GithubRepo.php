<?php


namespace Phoenix;

/**
 * Class GithubRepo
 *
 * @package Phoenix\Terminal
 */
class GithubRepo extends AbstractDeployer
{

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
     * GithubRepo constructor.
     *
     * @param $config
     * @param GithubClient|null $github
     */
    public function __construct($config, GithubClient $github = null)
    {
        $this->config = $config;
        $this->github = $github;
        parent::__construct();
    }

    /**
     * @return bool
     */
    public function create(): bool
    {
        $args = $this->getArgs();
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $success = $this->github->repo()->create($args['name'], $args['domain']);
        return $this->logFinish($success);
    }

    /**
     * @return bool
     */
    public function delete(): bool
    {
        $args = $this->getArgs();
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $success = $this->github->repo()->delete($args['name'], $args['domain']);
        return $this->logFinish($success);
    }

    /**
     * @param array $args
     * @return bool
     */
    private function validate(array $args = []): bool
    {
        if (empty($args['name']))
            return $this->logError('Repository name missing from config.');
        if (empty($args['domain']))
            return $this->logError('Domain name missing from config.');
        return true;
    }

    /**
     * @return array
     */
    protected function getArgs(): array
    {
        $args = [];
        $args['name'] = $this->config->version_control->repo_name ?? '';
        $args['domain'] = $this->config->environ->live->domain ?? '';

        return $args;
    }

    /**
     * @param array $args
     * @return string
     */
    protected function mainStr(array $args = []): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];

        $nameStr = !empty($args['name']) ? ' named ' . $args['name'] : '';

        return $this->_mainStr[$action] = 'upstream Github repository' . $nameStr;
    }
}