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
    private function fetchHeaders()
    {
        if (!function_exists('getallheaders'))
            $this->throw_exception("function getallheaders() unavailable.");
        $this->headers = apache_request_headers();
        if (empty($this->headers)) {
            $this->throw_exception("No headers returned.");
        }
        if (empty($this->headers['Content-Type'])) {
            $this->throw_exception("Missing HTTP 'Content-Type' header. " . print_r($this->headers, true));
        }
        if (empty($this->headers['X-GitHub-Event'])) {
            $this->throw_exception("Missing HTTP 'X-GitHub-Event' header. " . print_r($this->headers, true));
        }
        return true;
    }

    /**
     * @throws Exception
     */
    private function fetchConfig()
    {
        $payload = $this->fetchPayload();
        $repo_name = $payload['repository']['name'];

        $this->repo_name = $repo_name;
        $this->writeLog('Starting Github Webhook Endpoint');
        $config = file_get_contents('../.github_webhook_configs/' . $repo_name . '.json');
        $config = json_decode($config, true);
        if (empty($config))
            $this->throw_exception("Couldn't get config file '" . $repo_name . ".json'.");
        if (empty($config['secret']))
            $this->throw_exception("Couldn't get Webhook secret from config file '" . $repo_name . ".json'.");
        if (empty($config['worktree']))
            $this->throw_exception("Couldn't get worktree filepath from config file '" . $repo_name . ".json'.");

        $this->hookSecret = $config['secret'];
        $this->worktree = $config['worktree'];
    }

    /**
     * @return array|mixed
     * @throws Exception
     */
    public function fetchPayload()
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
                $this->throw_exception("Unsupported content type: $this->headers['Content-Type']");
        }
        if (empty($json)) {
            $this->throw_exception("Payload is empty");
            return false;
        }
        return $this->payload = json_decode($json, true);

    }

    /**
     * @param $msg
     */
    public function sendmail($msg)
    {
        $msg = "This is a message from your GitHub webhook endpoint: \n\n" . $msg;
        mail($this->errorMail, 'Github Webhook Endpoint Message', $msg);
    }


    /**
     * @throws Exception
     */
    public function run()
    {
        if (!$this->fetchHeaders())
            return false;
        $this->fetchConfig();
        // check if payload passes given secret
        if ($this->hookSecret !== NULL) {
            $this->checkHookSecret();
        }

        $payload = $this->fetchPayload();

        // get github event
        switch (strtolower($this->headers['X-GitHub-Event'])) {
            case 'ping':
                echo 'pong';
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
        $worktree_dir = $this->worktree;
        $cd = "cd " . $worktree_dir . '; ';
        $glue = ' .... ';

        exec($cd . "git diff", $output_git_diff);
        $output_git_diff = implode($glue, $output_git_diff);
        if (!empty($output_git_diff))
            $this->throw_exception('Uncommitted changes in Git repo: ' . $output_git_diff);
        exec($cd . 'git pull', $output_git_pull);
        $output_git_pull = implode($glue, $output_git_pull);
        $this->writeLog($output_git_pull);
    }

    /**
     * @throws Exception
     */
    private function checkHookSecret()
    {
        if (empty($this->headers['X-Hub-Signature'])) {
            $this->throw_exception("HTTP header 'X-Hub-Signature' is missing. " . print_r($this->headers, true));
        } elseif (!extension_loaded('hash')) {
            $this->throw_exception("Missing 'hash' extension to check the secret code validity.");
        }
        // split signature into algorithm and hash
        list($algo, $hash) = explode('=', $this->headers['X-Hub-Signature'], 2) + array('', '');
        if (!in_array($algo, hash_algos(), TRUE)) {
            $this->throw_exception("Hash algorithm '$algo' is not supported.");
        }
        // get payload, calculate hash, check if hashs are equal
        $rawPost = file_get_contents('php://input');
        if ($hash !== hash_hmac($algo, $rawPost, $this->hookSecret)) {
            $this->throw_exception('Hook secret does not match.');
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
            $logtext = date(DATE_ATOM) . "  " . $_SERVER['REMOTE_ADDR'] . "  " . $repo_name . $msg . "\r\n";

            error_log($logtext, 3, $this->logfile);
            return true;
        }
        return false;
    }

    /**
     * @param string $exception
     * @throws Exception
     */
    public function throw_exception($exception = '')
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
    $endpoint->sendmail($msg);
    header('HTTP/1.1 500 Internal Server Error');
    $errorMsg = "Error on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
    echo $errorMsg;
    $endpoint->writeLog(str_replace("\n", ". ", $errorMsg));
    die();
}