<?php


/**
 * Class GithubWebhookEndpoint
 */
class GithubWebhookEndpoint
{
    /**
     * @var array
     */
    private $payload = array();

    /**
     * secret of the hook, set NULL if no secret given
     *
     * @var null
     */
    private $hookSecret = '';

    /**
     * @var string
     */
    private $worktree = '';

    /**
     * @var array
     */
    private $headers = array();

    /**
     * where to mail on error
     *
     * @var string
     */
    private $errorMail = 'james.jones@phoenixweb.com.au';

    /**
     * @var string
     */
    private $logfile = 'github_webhook_endpoint.log';

    /**
     * @var string
     */
    private $repo_name = '';

    /**
     * GithubWebhookEndpoint constructor.
     * @throws Exception
     */
    public function __construct()
    {
    }

    /**
     * @throws Exception
     */
    private function getHeaders()
    {
        if (!function_exists('getallheaders'))
            $this->throwException("function getallheaders() unavailable.");
        $this->headers = apache_request_headers();
        if (empty($this->headers)) {
            $this->throwException("No headers returned.");
        }
        if (empty($this->headers['Content-Type'])) {
            $this->throwException("Missing HTTP 'Content-Type' header. " . print_r($this->headers, true));
        }
        if (empty($this->headers['X-GitHub-Event'])) {
            $this->throwException("Missing HTTP 'X-GitHub-Event' header. " . print_r($this->headers, true));
        }
        return true;
    }

    /**
     * @throws Exception
     */
    private function getConfig()
    {
        $repo_name = $this->getPayload()['repository']['name'];

        $this->repo_name = $repo_name;
        $this->writeLog('Starting Github Webhook Endpoint');
        $config = file_get_contents('../.github_webhook_configs/' . $repo_name . '.json');
        $config = json_decode($config, true);
        if (empty($config))
            $this->throwException("Couldn't get config file '" . $repo_name . ".json'.");
        if (empty($config['secret']))
            $this->throwException("Couldn't get Webhook secret from config file '" . $repo_name . ".json'.");
        if (empty($config['worktree']))
            $this->throwException("Couldn't get worktree filepath from config file '" . $repo_name . ".json'.");

        $this->hookSecret = $config['secret'];
        $this->worktree = $config['worktree'];
    }

    /**
     * @return array|mixed
     * @throws Exception
     */
    public function getPayload()
    {
        if (!empty($this->payload))
            return $this->payload;
        // get payload, depends on hook type
        switch ($this->headers['Content-Type']) {
            case 'application/json':
                $json = file_get_contents('php://input');
                break;
            case 'application/x-www-form-urlencoded':
                $json = $_POST['payload'];
                break;
            default:
                $this->throwException("Unsupported content type: $this->headers['Content-Type']");
        }
        if (empty($json)) {
            $this->throwException("Payload is empty");
            return false;
        }
        return $this->payload = json_decode($json, true);

    }

    /**
     * @param $msg
     */
    public function sendMail($msg)
    {
        $msg = "This is a message from your GitHub webhook endpoint: \n\n" . $msg;
        mail($this->errorMail, 'Github Webhook Endpoint Message', $msg);
    }


    /**
     * @throws Exception
     */
    public function run()
    {
        if (!$this->getHeaders())
            return false;
        $this->getConfig();
        // check if payload passes given secret
        if ($this->hookSecret !== NULL) {
            $this->checkHookSecret();
        }

        $payload = $this->getPayload();

        // get github event
        switch (strtolower($this->headers['X-GitHub-Event'])) {
            case 'ping':
                $this->writeLog('Received a ping from Github.');
                echo 'Received a ping from Github.<br>';
                break;
            case 'push':
                $this->githubEvent_push();
                break;
            //  case 'create':
            //		break;
            // for debug
            default:
                header('HTTP/1.0 404 Not Found');
                echo "Event: " . $this->headers['X-GitHub-Event'] . "\nPayload: \n";
                print_r($payload);
                die();
        }
        $this->writeLog('Finished Github Webhook Endpoint');
        return true;
    }

    /**
     * @throws Exception
     */
    private function githubEvent_push()
    {
        $output_git_diff = $this->exec("git status --porcelain", $this->worktree);
        if (!empty($output_git_diff))
            $this->throwException('Uncommitted changes in Git repo: ' . $output_git_diff);


        $payload = $this->getPayload();
        $branch = explode('/', $payload['ref']);
        $branch = end($branch);
        $currentBranch = $this->getCurrentGitBranch();
        if ($branch == $currentBranch) {
            $last_commit_message = $this->exec("git log -1 --pretty=%B", $this->worktree);
            if ($payload['head_commit']['message'] == $last_commit_message)
                $this->writeLog("Skipping git pull. As payload and last log git message are identical, commit was probably made from this machine.");
            else
                $command = 'git pull';
        } else {
            //taken from answer at https://stackoverflow.com/questions/18994609/how-to-git-pull-into-a-branch-that-is-not-the-current-one
            $command = 'git fetch origin ' . $branch . ';' .
                ' git clone . ../dummy -b ' . $branch . ';' .
                ' cd ../dummy; git pull origin origin/' .
                $branch . ';' .
                ' git push origin ' . $branch . ';' .
                ' cd ' . $this->worktree . ';' .
                ' rm -rf ../dummy';
        }
        if (!empty($command)) {
            $output_git_pull = $this->exec($command, $this->worktree);
            $this->writeLog($output_git_pull);
        }
    }

    /**
     * @param string $branch
     * @return bool|null
     */
    public function isGitBranch(string $branch = '')
    {
        $exists = $this->exec("git show-ref --verify refs/heads/" . $branch, $this->worktree);
        if (strpos($exists, 'not a valid ref') !== false)
            return false;
        if (strpos($exists, "refs/heads/" . $branch) !== false)
            return true;
        return null;
    }

    /**
     * @return bool|string
     */
    public function getCurrentGitBranch()
    {
        $currentBranch = $this->exec("git symbolic-ref --short HEAD", $this->worktree);
        if (strlen($currentBranch) > 0)
            return $currentBranch;
        return false;
    }

    /**
     * @param string $command
     * @param string $dir
     * @return string
     */
    public function exec(string $command = '', string $dir = '')
    {
        $cd = !empty($dir) ? "cd " . $dir . '; ' : '';
        exec($cd . $command, $output);
        $filtered_output = array();
        foreach ($output as $string) {
            if (!empty(trim($string)))
                $filtered_output[] = trim($string);
        }
        $glue = ' .... ';
        $filtered_output = implode($glue, $filtered_output);
        return trim($filtered_output);
    }

    /**
     * @throws Exception
     */
    private function checkHookSecret()
    {
        if (empty($this->headers['X-Hub-Signature'])) {
            $this->throwException("HTTP header 'X-Hub-Signature' is missing. " . print_r($this->headers, true));
        } elseif (!extension_loaded('hash')) {
            $this->throwException("Missing 'hash' extension to check the secret code validity.");
        }
        // split signature into algorithm and hash
        list($algo, $hash) = explode('=', $this->headers['X-Hub-Signature'], 2) + array('', '');
        if (!in_array($algo, hash_algos(), TRUE)) {
            $this->throwException("Hash algorithm '$algo' is not supported.");
        }
        // get payload, calculate hash, check if hashs are equal
        $rawPost = file_get_contents('php://input');
        if ($hash !== hash_hmac($algo, $rawPost, $this->hookSecret)) {
            $this->throwException('Hook secret does not match.');
        }
    }

    /**
     * @param $msg
     * @return bool
     */
    public function writeLog($msg = '')
    {
        if (!empty($this->logfile) && !empty($msg)) {
            $repo_name = !empty($this->repo_name) ? ucfirst($this->repo_name) . ' - ' : '';
            $logtext = date(DATE_ATOM) . "  " . $_SERVER['REMOTE_ADDR'] . "  " . $repo_name . $msg;
            echo $msg . "\r\n";
            error_log($logtext . "\r\n", 3, $this->logfile);
            return true;
        }
        return false;
    }

    /**
     * @param string $exception
     * @throws Exception
     */
    public function throwException($exception = '')
    {
        $repo_name = !empty($this->repo_name) ? ucfirst($this->repo_name) . ' - ' : '';
        throw new \Exception($repo_name . $exception);
    }
}


try {
    if (!empty($_GET['github'] == 'yes')) {
        $endpoint = new GithubWebhookEndpoint();
        $endpoint->run();

    }
} catch (Exception $e) {
    $msg = $e->getMessage();
    $endpoint->sendMail($msg);
    header('HTTP/1.1 500 Internal Server Error');
    $errorMsg = "Error on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
    echo $errorMsg;
    $endpoint->writeLog(str_replace("\n", ". ", $errorMsg));
    die();
}