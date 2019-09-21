<?php

namespace Phoenix;
//$project = $this->bitbucket->project( 'create', $this->config->bitbucket->project->key, $this->config->bitbucket->project->name );
//$repo = $this->bitbucket->repository( 'create', $this->config->bitbucket->project->key, $this->config->bitbucket->repo->name );

//$repo = $this->bitbucket->repository( 'delete', $bb_args->project->key, $bb_args->repo->name );
//$project = $this->bitbucket->project( 'delete', $bb_args->project->key, $bb_args->project->name );

class Bitbucket extends Base
{
    public $curl;

    public $team;

    private $base_url = 'https://api.bitbucket.org/';
    //private $base_url = 'https://api.bitbucket.org/1.0/';

    private $token;

    private $functions = array(
        'project' => array(
            'create' => 'PUT',
            'update' => 'PUT',
            'get' => 'GET',
            'delete' => 'DELETE'
        ),
        'repository' => array(
            'create' => 'POST',
            'update' => 'PUT',
            'get' => 'GET',
            'delete' => 'DELETE'
        )
    );

    private $action_type = array(
        'create' => array(
            'present' => 'creating',
            'past' => 'created',
            'action' => 'create',
            'method' => 'PUT' //was PUT
        ),
        'delete' => array(
            'present' => 'deleting',
            'past' => 'deleted',
            'action' => 'delete',
            'method' => 'DELETE'
        ),
        'update' => array(
            'present' => 'updating',
            'past' => 'updated',
            'action' => 'update',
            'method' => 'PUT'
        ));

    protected static $_instance = null;

    /**
     * @return null|Bitbucket
     */
    public static function instance($stuff): ?Bitbucket
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($stuff);
        }
        return self::$_instance;
    }

    /**
     * Bitbucket constructor.
     * @param string $password
     * @param string $team
     */
    public function __construct(string $password = '', string $team = '')
    {
        $this->team_name = $team;
        $this->auth($password);
    }

    /**
     * @return bool
     */
    private function auth($password = ''): bool
    {
        if (empty($password))
            return false;
        $this->log('Obtaining Bitbucket user token.', 'info');
        $auth = new Curl(
            'https://bitbucket.org/site/oauth2/access_token',
            false,
            $password,
            false
        );
        $result = $auth->api_call('', 'grant_type=client_credentials', false, 'POST');
        if (!empty($result)) {
            $this->log('Successfully obtained Bitbucket user token.', 'success');
            $this->token = $result['result'];
            return true;
        }
        $this->log('Failed to authorise with Bitbucket API.');
        return false;
    }

    public function delete_project($project_key = ''): bool
    {
        return $this->project('delete', $project_key);
    }

    /**
     * @param string $action
     * @param string $project_key
     * @param string $project_name
     * @return bool
     */
    public function project(string $action = 'create', string $project_key = '', string $project_name = ''): bool
    {
        if (!in_array($action, array('create', 'delete'))) {
            //action is something else.
            $this->log("Can't do Bitbucket project stuff. Action should be 'create' or 'delete'.", 'error');
            return false;
        }
        $error_string = sprintf("Can't %s Bitbucket project. ", $this->action_type[$action]['action']);

        if (empty($project_name) && $action != 'delete') {
            $this->log($error_string . 'Project name missing.');
            return false;
        }
        if (empty($project_key)) {
            $this->log($error_string . 'Project key missing.');
            return false;
        }
        $project_name_string = !empty($project_name) ? 'named <strong>' . $project_name . '</strong> ' : '';
        $this->log(sprintf('%s Bitbucket project %swith project key <strong>%s</strong>.', ucfirst($this->action_type[$action]['present']), $project_name_string, $project_key), 'info');
        /*make sure we have permissions to create a project.*/
        if (empty($this->token['scopes']) || strpos($this->token['scopes'], 'project:write') === false) {
            $this->log('Insufficient permissions to ' . $this->action_type[$action]['action'] . ' Bitbucket project.');
            return false;
        }

        $args = array(
            'name' => $project_name,
            'description' => 'Project for ' . $project_name . ' repositories.',
            //'links' => array( 'avatar' => array( 'href' => 'data:image/gif;base64,R0lGODlhEAAQAMQAAORHHOVSKudfOulrSOp3WOyDZu6QdvCchPGolfO0o/...' ) ),
            'is_private' => true
        );
        $result = $this->api_call('2.0/teams/' . $this->team_name . '/projects/' . $project_key, $args, $this->functions['project'][$action]);


        if ($result['http_status'] == 201) {
            $this->log('Successfully ' . $this->action_type[$action]['past'] . ' Bitbucket project \'' . $project_name . '\'.', 'success');
            return true;
        }
        $this->log(sprintf("Couldn't %s project. %s", $this->action_type[$action]['action'], $this->parse_error($result)));
        return false;


    }

    /**
     * @param string $repo_name
     * @param string $project_key
     * @param string $action
     * @return bool
     */
    public function repository(string $action = 'create', string $project_key = '', string $repo_name = ''): bool
    {
        if (!in_array($action, array('create', 'delete'))) {
            //action is something else.
            $this->log("Can't do Bitbucket repository stuff. Action should be 'create' or 'delete'.", 'error');
            return false;
        }
        $this->log(sprintf('%s Bitbucket repository.', $this->action_type[$action]['present']), 'info');
        if (empty($project_key)) {
            $this->log(sprintf("Can't %s Bitbucket repository. Project key missing.", $this->action_type[$action]['action']));
            return false;
        }
        if (empty($repo_name)) {
            $this->log(sprintf("Can't %s Bitbucket repository. Repo name missing.", $this->action_type[$action]['action']));
            return false;
        }
        $this->log(sprintf('%s Bitbucket repository named <strong>%s</strong> in project <strong>%s</strong>.', $this->action_type[$action]['present'], $repo_name, $project_key), 'info');

        /*first make sure we have permissions to create a repository.*/
        if (empty($this->token['scopes']) || strpos($this->token['scopes'], 'repository:write') === false)
            return false;

        $args = array(
            'scm' => 'git',
            'is_private' => 'true',
            'fork_policy' => 'no_public_forks',
            'language' => 'php',
            'project' => array(
                'key' => $project_key
            ),
            'description' => 'Repository for ' . $repo_name . '.'
        );

        $result = $this->api_call('2.0/repositories/' . $this->team_name . '/' . $repo_name, $args, $this->functions['repository'][$action]);
        if ($result['type'] == 'error') {
            $this->log(sprintf("Couldn't %s repository. %s", $this->action_type[$action]['action'], $this->parse_error($result)));
            return false;
        }
        $this->log('Successfully ' . $this->action_type[$action]['past'] . ' Bitbucket repository \'' . $repo_name . '\' in project \'' . $project_key . '\'.',
            'success');
        return true;
    }

    /**
     * @param array $result
     * @return bool|string
     */
    public function parse_error(array $result = array())
    {
        if (empty($result))
            return false;
        $error_string = '<code>Bitbucket message: ' . $result['error']['message'];
        if (!empty($result['error']['detail']))
            $error_string .= $result['error']['detail'];
        elseif (!empty($result['error']['fields'])) {
            foreach ($result['error']['fields'] as $field => $error) {
                $error_string .= $field . ' ' . $error[0] . ',';
            }
        }
        return $error_string . '</code>';
    }

    public
    function access_key($repo_name = '', $project_key = 'TP', $action = 'create'): bool
    {
        /*
        if ( !in_array( $action, array( 'create', 'delete' ) ) ) {
            //action is something else.
            $this->log( 'Action should be \'create\' or \'delete\'.' );
            return false;
        }

        $start_string = $this->action_type[ $action ][ 'present' ] . ' Bitbucket repository';
        if ( empty( $repo_name ) ) {
            $this->log( $start_string . '.', 'info' );
            $this->log( 'Cannot ' . $this->action_type[ $action ][ 'action' ] . ' Bitbucket repository. Repo name missing.' );
            return false;
        }
        $this->log( $start_string . ' \'' . $repo_name . '\'.', 'info' );
        */

        /*first make sure we have permissions to create a repository.*/

        /*
        if ( empty( $this->token[ 'scopes' ] ) || strpos( $this->token[ 'scopes' ], 'repository:write' ) === false )
            return false;
        if ( empty( $repo_name ) ) {
            $this->log( 'Cannot ' . $this->action_type[ $action ][ 'action' ] . ' Bitbucket repository. Repo name missing.' );
            return false;
        }

        $args = array(
            'scm' => 'git',
            'is_private' => 'true',
            'fork_policy' => 'no_public_forks',
            'language' => 'php',
            'project' => array(
                'key' => $project_key
            ),
            'description' => 'Repository for ' . $repo_name . '.'
        );
        */
        $args = array();
        $result = $this->api_call('repositories/' . $this->team_name . '/' . $repo_name . '/deploy-keys', $args, 'GET');

        //print_r( $result );

        if ($result['type'] == 'error') {
            $error_log = 'Failed to ' . $this->action_type[$action]['action'] . ' Bitbucket repository. ' . $result['error']['message'] . ' - ';
            if (!empty($result['error']['detail']))
                $error_log .= $result['error']['detail'];
            elseif (!empty($result['error']['fields'])) {
                foreach ($result['error']['fields'] as $field => $error) {
                    $error_log .= $field . ' ' . $error[0] . ',';
                }
            }

            $this->log($error_log);
            return false;
        }
        $this->log('Successfully ' . $this->action_type[$action]['past'] . ' access key.',
            'success');
        return true;
    }

    /**
     * @param string $query
     * @param bool $args
     * @param string $request_type
     * @return mixed
     */
    public
    function api_call($query = '', $args = false, $request_type = 'GET')
    {
        if (empty($this->curl)) {
            $this->curl = new Curl(
                $this->base_url,
                array('type' => 'Bearer', 'user' => $this->token['access_token'])
            );
        }

        $result = $this->curl->api_call($query, $args, 'json', $request_type);
        if (!in_array($result['http_status'], array(200, 201))) {
            $debug_backtrace = '';
            if (!empty(debug_backtrace()[1]['function']))
                $debug_backtrace = ' Api call by <code>' . debug_backtrace()[1]['function'] . '()</code> function.';
            $this->log('[!] Curl error: ' . $result['http_status'] . ' returned.' . $debug_backtrace . ' Error text: ' . $result['result']['error']['message']);
        }
        //print_r($result);

        return $result;
    }

}


function bitbucket($stuff = false)
{
    return Bitbucket::instance($stuff);
}