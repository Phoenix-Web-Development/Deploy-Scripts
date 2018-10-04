<?php

namespace Phoenix;

/**
 *
 * Class WHM
 */
class WHM extends Base
{
    /**
     * @var array
     */
    public $api_version = array('api.version' => 1);

    /**
     * @var Curl
     */
    public $curl;

    /**
     * @var array
     */
    private $cpanel_accounts = array('accounts' => array());

    public $_cpanel_api_functions = array(
        'DomainInfo' => array(
            'list_domains' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'list_domains'
            ),
            'single_domain_data' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'single_domain_data'
            )
        ),
        'Email' => array(
            'delete_filter' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'delete_filter'
            ),
            'get_filter' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'get_filter'
            ),
            'store_filter' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'store_filter'
            )
        ),
        'Mysql' => array(
            'create_database' => array(
                'cpanel_jsonapi_func' => 'create_database',
                'cpanel_jsonapi_apiversion' => 3,
            ),
            'create_user' => array(
                'cpanel_jsonapi_func' => 'create_user',
                'cpanel_jsonapi_apiversion' => 3,
            ),
            'delete_database' => array(
                'cpanel_jsonapi_func' => 'delete_database',
                'cpanel_jsonapi_apiversion' => 3,
            ),
            'delete_user' => array(
                'cpanel_jsonapi_func' => 'delete_user',
                'cpanel_jsonapi_apiversion' => 3,
            ),
            'get_privileges_on_database' => array(
                'cpanel_jsonapi_func' => 'get_privileges_on_database',
                'cpanel_jsonapi_apiversion' => 3,
            ),
            'set_privileges_on_database' => array(
                'cpanel_jsonapi_func' => 'set_privileges_on_database',
                'cpanel_jsonapi_apiversion' => 3,
                'privileges' => 'ALL PRIVILEGES'
            ),

        ),
        'Quota' => array(
            'get_quota_info' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'get_quota_info'
            )
        ),
        'SSH' => array(
            'importkey' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'importkey'
            )
        ),
        'SubDomain' => array(
            'addsubdomain' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'addsubdomain'
            ),
            'delsubdomain' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'delsubdomain'
            ),
        ),
        'VersionControl' => array(
            'create' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'create'
            ),
            'delete' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'delete'
            ),
            'retrieve' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'retrieve'
            ),
            'update' => array(
                'cpanel_jsonapi_apiversion' => 3,
                'cpanel_jsonapi_func' => 'update'
            ),

        )
    );

    /**
     * @var array
     */
    public $cpanel_api_functions = array(
        'create_user' => array(
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'create_user',
            'cpanel_jsonapi_apiversion' => 3,
        ),
        'delete_user' => array(
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'delete_user',
            'cpanel_jsonapi_apiversion' => 3,
        ),
        'create_database' => array(
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'create_database',
            'cpanel_jsonapi_apiversion' => 3,
        ),
        'delete_database' => array(
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'delete_database',
            'cpanel_jsonapi_apiversion' => 3,
        ),
        'set_privileges_on_database' => array(
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'set_privileges_on_database',
            'cpanel_jsonapi_apiversion' => 3,
            'privileges' => 'ALL PRIVILEGES'
        ),
        'get_privileges_on_database' => array(
            'cpanel_jsonapi_module' => 'Mysql',
            'cpanel_jsonapi_func' => 'get_privileges_on_database',
            'cpanel_jsonapi_apiversion' => 3,
        ),
        'addsubdomain' => array(
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_func' => 'addsubdomain'
        ),
        'delsubdomain' => array(
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_func' => 'delsubdomain'
        ),
        'single_domain_data' => array(
            'cpanel_jsonapi_module' => 'DomainInfo',
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_func' => 'single_domain_data'
        ),
        'list_domains' => array(
            'cpanel_jsonapi_module' => 'DomainInfo',
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_func' => 'list_domains'
        ),
        'get_filter' => array(
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_func' => 'get_filter'
        ),
        'store_filter' => array(
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_func' => 'store_filter'
        ),
        'delete_filter' => array(
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_func' => 'delete_filter'
        ),
        'get_quota_info' => array(
            'cpanel_jsonapi_module' => 'Quota',
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_func' => 'get_quota_info'
        ),
        'importkey' => array(
            'cpanel_jsonapi_module' => 'SSH',
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_func' => 'importkey'
        ),
    );

    /**
     * @var array
     */
    private $subdomains = array();

    /**
     * WHM constructor.
     *
     * @param Curl $curl
     */
    public function __construct(Curl $curl)
    {
        if (empty($curl)) {
            return false;
        }
        $this->curl = $curl;
        $cpanel_accounts = $this->get_cpanel_accounts();
        if (!empty($cpanel_accounts)) {
            $this->log(sprintf('Successfully connected WHM API. WHM contains %s cPanel accounts.',
                count($this->cpanel_accounts['accounts'])), 'success');
            return true;
        }
        return false;
    }

    /*
    public function __call( $method, $arguments ) {
        if ( method_exists( $this, $method ) ) {
            if ( in_array( $method, array( 'delete_db' ) ) ) {
                echo 'blegh';
                $account = $this->get_cpanel_account('blag','user');
            }
            return call_user_func_array( array( $this, $method ), $arguments );
        }
        return false;
    }
    */

    /**
     * @param bool $force_query
     * @param bool $verbose
     * @return bool|mixed
     */
    public
    function get_cpanel_accounts(bool $force_query = false, bool $verbose = false)
    {
        if (!empty($this->cpanel_accounts['accounts'] && !$force_query))
            return $this->cpanel_accounts['accounts'];
        if ($verbose)
            $this->log('Querying cPanel accounts.', 'info');
        if (!$result = $this->api_call('listaccts', array()))
            return false;

        if (!$result['success']) {
            $this->log('Failed to query cPanel accounts.' . $result['message']);
            return false;
        }

        if (!empty($result['result']['data']['acct'])) {
            return $this->cpanel_accounts['accounts'] = $result['result']['data']['acct'];
        }
        $this->log(sprintf('Successfully queried WHM but found no cPanel accounts. %s', $result['message']), 'info');
        return false;
    }

    /**
     * @param string $cpanel_parameter
     * @param string $type
     * @return bool
     */
    public
    function get_cpanel_account(string $cpanel_parameter = '', string $type = 'user')
    {
        if (empty($cpanel_parameter)) {
            if (!empty($this->cpanel_accounts['active']))
                return $this->cpanel_accounts['accounts'][$this->cpanel_accounts['active']];
            $this->log(sprintf("Can't get cPanel account. %s is missing and no previously queried account to return.", $type), 'error');
            return false;
        }
        if (!in_array($type, array('user', 'domain'))) {
            $this->log("Can't get cPanel account. Search parameter must be 'user' or 'domain'.", 'error');
            return false;
        }
        $log_append_string = sprintf('with %s <strong>%s</strong>.', $type, $cpanel_parameter);
        $cpanel_accounts = $this->get_cpanel_accounts();
        if (!empty($cpanel_accounts)) {
            foreach ($cpanel_accounts as $key => $cpanel_account) {
                if ($cpanel_account[$type] == $cpanel_parameter) {
                    $this->cpanel_accounts['active'] = $key;
                    return $cpanel_account;
                }
            }
        }

        //search for cPanel account using focused search as so many cPanel accounts it'll slow down process
        if (empty($found_cpanel_account) && (!empty($cpanel_accounts) && count($cpanel_accounts) > 150)) {
            //$this->log( sprintf( "cPanel account %s isn't in account list so querying WHM directly.", $log_append_string ), 'info' );
            if (!$result = $this->api_call('listaccts', array('search' => $cpanel_parameter, 'searchtype' => $type)))
                return false;
            if (!$result['success']) {
                $this->log('Failed to query cPanel accounts.' . $result['message']);
                return false;
            }
            if (empty($result['result']['data']['acct'])) {
                $this->log(sprintf('No cPanel account found %s. %s.', $log_append_string, $result['message']), 'info');
                return false;
            }
            foreach ($result['result']['data']['acct'] as $cpanel_account) {
                if ($cpanel_account[$type] == $cpanel_parameter) {
                    $this->log(sprintf("Found cPanel account %s. %s",
                        $log_append_string, $result['message']), 'info');
                    $found_cpanel_account = $cpanel_account;
                }
            }
        }

        //cPanel account found but isn't in account list so refreshing WHM account list
        if (empty($found_cpanel_account) || (!empty($cpanel_accounts) && count($cpanel_accounts) > 150 && !empty($found_cpanel_account))) {
            $cpanel_accounts = $this->get_cpanel_accounts(true);
            if (!empty($cpanel_accounts)) {
                foreach ($cpanel_accounts as $key => $cpanel_account) {
                    if ($cpanel_account[$type] == $cpanel_parameter) {
                        $this->cpanel_accounts['active'] = $key;
                        $found_cpanel_account = $cpanel_account;
                    }
                }
            }
        }
        if (!empty($result['message']))
            $log_append_string .= '. ' . $result['message'];
        if (!empty($found_cpanel_account)) {
            $this->log(sprintf("Found cPanel account %s", $log_append_string), 'info');
            return $found_cpanel_account;
        }
        $this->log(sprintf("cPanel account %s doesn't exist.", $log_append_string), 'info');
        return false;
    }

    /**
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function list_domains(string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log("Failed to list cPanel account domains. Couldn't find cPanel account to query for domains.");

        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['list_domains'], array(
            'user' => $cpanel_account['user']
        ))))
            return false;
        if ($result['success']) {
            //$this->log( 'Successfully listed domains. ' . $result[ 'message' ], 'success' );
            return $result['result']['result']['data'];
        }
        $this->log('Failed to list cPanel account domains. ' . $result['message']);
        return false;
    }

    /**
     * @param string $username
     * @param string $domain
     * @param $args
     * @return bool
     */
    public
    function create_cpanel_account(string $username = '', string $domain = '', array $args = array())
    {
        //$this->log( 'Creating cPanel Account', 'info' );
        $error_string = "Can't create cPanel account.";
        if (empty($username)) {
            $this->log($error_string . " Username to apply is missing.", 'error');
            return false;
        }
        if (empty($domain)) {
            $this->log($error_string . " Domain to apply is missing.", 'error');
            return false;
        }
        $args_message = !empty($args) ? ' and args: ' . build_recursive_list($args) : '';

        $finish_message_append = sprintf(" cPanel account with domain <strong>%s</strong> and username <strong>%s</strong>.",
            $domain, $username);

        $this->log(sprintf("Creating %s%s.", $finish_message_append, $args_message), 'info');
        if (!$result = $this->api_call('createacct', array_merge(array(
            'username' => $username,
            'domain' => $domain),
            (array)$args
        )))
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully created' . $finish_message_append, 'success');
            return $this->get_cpanel_account($username);
        }
        $this->log('Failed to create' . $finish_message_append);
        return false;
    }

    /**
     * @param string $cpanel_parameter
     * @param string $type
     * @return bool
     */
    public
    function delete_cpanel_account(string $cpanel_parameter = '', string $type = 'user')
    {
        //$this->log( 'Deleting cPanel account', 'info' );
        //check account exists
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $type);
        if (!$cpanel_account) {
            $this->log(sprintf("Can't delete cPanel account with %s %s. Apparently it doesn't exist.", $type, $cpanel_account[$type]), 'error');
            return false;
        }
        $finish_message_append = sprintf(" cPanel account with domain <strong>%s</strong> and username <strong>%s</strong>.",
            $cpanel_account['domain'], $cpanel_account['user']);
        $this->log('Deleting' . $finish_message_append, 'info');
        if (!$result = $this->api_call('removeacct', array(
            'user' => $cpanel_account['user']
        )))
            return false;
        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully deleted' . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to delete' . $finish_message_append);
        return false;
    }

    /**
     * Creates cPanel DB user
     *
     * @param string $db_user
     * @param string $db_password
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function create_db_user(string $db_user = '', string $db_password = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        //$this->log( 'Creating DB User', 'info' );
        $error_string_append = "Can't create DB user. ";
        if (empty($db_user)) {
            $this->log($error_string_append . 'DB user name is missing. ', 'error');
        }
        if (empty($db_password)) {
            $this->log($error_string_append . 'DB password is missing. ', 'error');
        }

        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to add DB user to.", 'error');
            return false;
        }
        $db_user = $this->db_prefix_check($db_user, $cpanel_account['user']);

        $finish_message_append = sprintf(" DB user <strong>%s</strong> for cPanel account with %s <strong>%s</strong>.", $db_user, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);

        $this->log('Creating' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['create_user'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'name' => $db_user,
            'password' => $db_password
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully created' . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to create' . $finish_message_append);
        return false;
    }

    /**
     * @param string $db_name
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function create_db(string $db_name = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        $error_string_append = "Can't create DB schema. ";
        //$this->log( 'Creating DB schema', 'info' );
        if (empty($db_name)) {
            $this->log($error_string_append . 'DB name is blank.', 'error');
            return false;
        }

        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log($error_string_append . "Couldn't find cPanel account to add DB schema to.", 'error');

        $db_name = $this->db_prefix_check($db_name, $cpanel_account['user']);

        $finish_message_append = sprintf(" DB schema <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $db_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $this->log('Creating' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['create_database'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'name' => $db_name
        )))
        )
            return false;
        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully created' . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to create' . $finish_message_append);
        return false;
    }

    /**
     * @param string $action
     * @param string $db_user
     * @param string $db_name
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    function db_user_privileges(string $action = 'get', string $db_user = '', string $db_name = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        if (!in_array($action, array('get', 'set'))) {
            $this->log("Can't do DB user privileges stuff. Action should be 'get' or 'set'.", 'error');
            return false;
        }
        $error_string_append = sprintf("Can't %s DB user privileges. ", $action);
        if (empty($db_user)) {
            $this->log($error_string_append . "DB user is blank.");
            return false;
        }
        if (empty($db_name)) {
            $this->log($error_string_append . "DB name is blank.");
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to add DB user privileges in.", 'error');
            return false;
        }
        $db_user = $this->db_prefix_check($db_user, $cpanel_account['user']);
        $db_name = $this->db_prefix_check($db_name, $cpanel_account['user']);

        $finish_message_append = sprintf(" privileges for DB user <strong>%s</strong> for DB schema <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $db_user, $db_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        switch ($action) {
            case 'get':
                $api_function = 'get_privileges_on_database';
                break;
            case 'set':
                $this->log(ucfirst($this->actions[$action]['present']) . $finish_message_append, 'info');
                $api_function = 'set_privileges_on_database';
                $existing_privileges = $this->db_user_privileges('get', $db_user, $db_name, $cpanel_account['user']);
                if (!empty($existing_privileges)) {
                    //@TODO This check only applies for ALL PRIVILEGES. But that's all we use at the moment.
                    if (count($existing_privileges) == 1 && $this->cpanel_api_functions[$api_function]['privileges'] == $existing_privileges[0]) {
                        $this->log("Can't set" . $finish_message_append . ' Existing DB privileges are identical.');
                        return false;
                    }
                    $this->log("Overwriting existing email DB user privileges which are: " . build_recursive_list($existing_privileges), 'info');
                }
                break;
        }
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions[$api_function], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'user' => $db_user,
            'database' => $db_name
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            if ($action != 'get') {
                $this->log('Successfully ' . $this->actions[$action]['past'] . $finish_message_append, 'success');
                return true;
            }
            return $result['result']['result']['data'];
        }
        $this->log('Failed to ' . $action . $finish_message_append);
        return false;
    }


    /**
     * @param string $db_user
     * @param string $db_name
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function set_db_user_privileges(string $db_user = '', string $db_name = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        return $this->db_user_privileges('set', $db_user, $db_name, $cpanel_parameter, $cpanel_parameter_type);
    }

    /**
     * @param string $db_user
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function delete_db_user(string $db_user = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        //$this->log( 'Deleting DB User', 'info' );
        $error_string_append = "Couldn't delete DB user. ";
        if (empty($db_user)) {
            $this->log($error_string_append . 'DB user name input is missing.', 'error');
            return false;
        }

        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log($error_string_append . "Couldn't find cPanel account to delete DB user from.", 'error');
        $db_user = $this->db_prefix_check($db_user, $cpanel_account['user']);

        $finish_message_append = sprintf(" DB user <strong>%s</strong> from cPanel account with %s <strong>%s</strong>.",
            $db_user, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);

        $this->log('Deleting' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['delete_user'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'name' => $db_user,
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully deleted' . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to delete' . $finish_message_append);
        return false;
    }

    /**
     * @param string $db_name
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function delete_db(string $db_name, string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        //$this->log( 'Deleting DB', 'info' );
        $error_string = "Couldn't delete DB schema. ";
        if (empty($db_name)) {
            $this->log($error_string . "DB name is blank.", 'error');
            return false;
        }

        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string . "Couldn't find cPanel account to delete DB schema from.", 'error');
            return false;
        }

        $db_name = $this->db_prefix_check($db_name, $cpanel_account['user']);

        $finish_message_append = sprintf(" DB schema <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $db_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $this->log("Deleting" . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['delete_database'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'name' => $db_name
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully deleted' . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to delete' . $finish_message_append);
        return false;
    }

    /**
     * @param string $db_input
     * @param string $cpanel_username
     * @return bool|string
     */
    private
    function db_prefix_check(string $db_input = '', string $cpanel_username = '')
    {
        //ensure input has the cPanel prefix on the front
        if (empty($db_input))
            return false;
        if (empty($cpanel_username)) {
            $cpanel_account = $this->get_cpanel_account();
            if (!$cpanel_account) {
                $this->log("Couldn't find cPanel account for prefix check.", 'error');
                return false;
            }
            $cpanel_username = $cpanel_account['user'];
        }
        if (empty($cpanel_username))
            return false;
        $strpos = strpos($db_input, substr($cpanel_username . '_', 0, 8));
        if ($strpos === false || $strpos > 0)
            $db_input = $cpanel_username . '_' . $db_input;
        return $db_input;
    }

    /**
     * @param string $subdomain
     * @param string $rootdomain
     * @param string $directory
     * @return bool
     */
    public
    function create_subdomain(string $subdomain = '', string $rootdomain = '', string $directory = '')
    {
        //$this->log( 'Adding subdomain to cPanel account', 'info' );
        if (!isset($subdomain, $rootdomain, $directory)) {
            $this->log("Can't add subdomain. Subdomain, root domain and/or directory is missing.");
            return false;
        }

        $cpanel_account = $this->get_cpanel_account($rootdomain, 'domain');
        if (!$cpanel_account)
            $this->log(sprintf("Can't add <strong>%s</strong> subdomain. No cPanel account with domain <strong>%s</strong> found.", $subdomain, $rootdomain), 'error');

        $finish_message_append = sprintf(' subdomain <strong>%s</strong> to cPanel account with domain <strong>%s</strong>.', $subdomain, $rootdomain);
        $this->log('Adding' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['addsubdomain'], array(
            'domain' => $subdomain,
            'rootdomain' => $rootdomain,
            'dir' => $directory,
            'cpanel_jsonapi_user' => $cpanel_account['user'],
        )))
        )
            return false;

        if ($result['success']) {
            $this->log('Successfully created' . $finish_message_append . $result['message'], 'success');
            return true;
        }
        $this->log('Failed to create' . $finish_message_append . $result['message']);
        return false;
    }

    /**
     * @param string $subdomain_slug
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function get_subdomain(string $subdomain_slug = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {

        //$this->log( 'Querying cPanel account for subdomain.', 'info' );
        $error_string = "Can't query subdomain. ";
        if (empty($subdomain_slug)) {
            $this->log($error_string . ". Subdomain slug input missing. ", 'error');
            return false;
        }

        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log($error_string . "Couldn't find cPanel account to query.", 'error');
        $subdomain_url = $subdomain_slug . '.' . $cpanel_account['domain'];

        $finish_message_append = sprintf(" subdomain <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $subdomain_url, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $this->log("Querying for" . $finish_message_append, 'info');

        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['single_domain_data'], array(
            'domain' => $subdomain_url,
            'cpanel_jsonapi_user' => $cpanel_account['user']
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $data = $result['result']['result']['data'];
            $this->log('Found' . $finish_message_append . '<h5>Subdomain Data:</h5>' . build_recursive_list($data), 'info');
            return $data;
        }
        $this->log("Couldn't find" . $finish_message_append, 'secondary');
        return false;
    }

    /**
     * @param string $subdomain_slug
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function delete_subdomain(string $subdomain_slug = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        //$this->log( 'Deleting cPanel subdomain', 'info' );
        $error_string = "Can't delete cPanel subdomain. ";
        if (empty($subdomain_slug)) {
            $this->log("Subdomain slug input is missing.", 'error');
            return false;
        }

        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log(sprintf($error_string . "Couldn't find cPanel account to delete <strong>%s</strong> subdomain from.", $subdomain_slug), 'error');
        $subdomain_url = $subdomain_slug . '.' . $cpanel_account['domain'];

        $finish_message_append = sprintf(" subdomain <strong>%s</strong> from cPanel account with %s <strong>%s</strong>.",
            $subdomain_url, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);

        $this->log('Deleting' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['delsubdomain'], array(
            'domain' => $subdomain_url,
            'cpanel_jsonapi_user' => $cpanel_account['user']
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully deleted' . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to delete' . $finish_message_append);
        return false;
    }

    /**
     * @param string $action
     * @param string $account
     * @param string $filter_name
     * @param array $args
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function email_filter($action = '', string $account = '', string $filter_name = '', $args = array(),
                          string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        if (!in_array($action, array('create', 'delete', 'get'))) {
            $this->log("Can't do email filter stuff. Action should be 'get', 'create', or 'delete'.", 'error');
            return false;
        }
        $error_string_append = sprintf("Can't %s email filter. ", $action);
        if (empty($account)) {
            $this->log($error_string_append . 'Email account input is missing. ', 'error');
            return false;
        }
        if (empty($filter_name)) {
            $this->log($error_string_append . 'Filter name input is missing. ', 'error');
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to add email filter to. ", 'error');
            return false;
        }
        $finish_message_append = sprintf(" email filter <strong>%s</strong> for email account <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $filter_name, $account, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $args_api_call = array();
        switch ($action) {
            case 'create':
                $this->log(ucfirst($this->actions[$action]['present']) . $finish_message_append, 'info');
                $api_function = 'store_filter';
                $queried_filter = $this->get_email_filter( //check if identical email filter exists because API doesn't check this
                    $account,
                    $filter_name,
                    $cpanel_parameter,
                    $cpanel_parameter_type
                );

                if (!empty($queried_filter)) {
                    foreach ($queried_filter['actions'] as $queried_action) {
                        $array_index = $queried_action['number'] - 1;
                        $args->dest->$array_index = !empty($args->dest->$array_index) ? $args->dest->$array_index : '';
                        $args->action->$array_index = !empty($args->action->$array_index) ? $args->action->$array_index : '';
                        if ($args->dest->$array_index != $queried_action['dest']
                            || $args->action->$array_index != $queried_action['action'])
                            $is_different = false;
                    }
                    foreach ($queried_filter['rules'] as $queried_rule) {
                        $array_index = $queried_action['number'] - 1;
                        $args->opt->$array_index = !empty($args->opt->$array_index) ? $args->opt->$array_index : '';
                        $args->part->$array_index = !empty($args->part->$array_index) ? $args->part->$array_index : '';
                        $args->val->$array_index = !empty($args->val->$array_index) ? $args->val->$array_index : '';
                        $args->match->$array_index = !empty($args->match->$array_index) ? $args->match->$array_index : '';
                        if ($args->opt->$array_index != $queried_rule['opt']
                            || $args->part->$array_index != $queried_rule['part']
                            || $args->val->$array_index != $queried_rule['val']
                            || $args->match->$array_index != $queried_rule['match']
                        )
                            $is_different = false;
                    }
                    if (isset($is_different) && !$is_different) {
                        $this->log($error_string_append . "Identical email filter already exists.");
                        return false;
                    } else
                        $this->log("Overwriting existing email filter which has the following attributes: " . build_recursive_list($queried_filter));
                }
                if (!empty($args)) {
                    foreach ($args as $action_parameter => $arg) {
                        $i = 1;
                        foreach ($arg as $item) {
                            if (!empty($item)) {
                                $args_api_call[$action_parameter . $i] = $item;
                                $i++;
                            }
                        }
                    }
                }
                break;
            case 'delete':
                $this->log(ucfirst($this->actions[$action]['present']) . $finish_message_append, 'info');
                $api_function = 'delete_filter';
                break;
            case 'get':
            default:
                $api_function = 'get_filter';
                break;
        }
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions[$api_function], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'account' => $account,
            'filtername' => $filter_name,
        ), $args_api_call)))
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            if ($action != 'get') {
                $this->log('Successfully ' . $this->actions[$action]['past'] . $finish_message_append, 'success');
                return true;
            }
            if (isset($result['result']['result']['data']['rules']['part']))
                return $result['result']['result']['data'];
        }
        $this->log('Failed to ' . $action . $finish_message_append);
        return false;
    }

    /**
     * @param string $account
     * @param string $filter_name
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function get_email_filter(string $account = '', string $filter_name = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        return $this->email_filter('get', $account, $filter_name, array(), $cpanel_parameter, $cpanel_parameter_type);
    }

    /**
     * @param string $account
     * @param string $filter_name
     * @param array $args
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function create_email_filter(string $account = '', string $filter_name = '',
                                 $args = array(), string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        return $this->email_filter('create', $account, $filter_name, $args, $cpanel_parameter, $cpanel_parameter_type);
    }

    /**
     * @param string $account
     * @param string $filter_name
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function delete_email_filter(string $account = '', string $filter_name = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        return $this->email_filter('delete', $account, $filter_name, array(), $cpanel_parameter, $cpanel_parameter_type);
    }


    /**
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function get_quota_info(string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        //$this->log( 'Getting quota info', 'info' );
        $error_string_append = "Can't get quota info. ";
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log($error_string_append . "Couldn't find cPanel account to get quota info from.", 'error');

        $finish_message_append = sprintf(" quota information from cPanel account with %s <strong>%s</strong>",
            $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);

        $this->log("Querying" . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['get_quota_info'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user']
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully queried' . $finish_message_append, 'success');
            return $result['result']['result']['data'];
        }
        $this->log('Failed to query' . $finish_message_append);
        return false;
    }

    public
    function import_key(string $key = '', string $key_name = '', string $key_pass = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        $error_string_append = "Can't import SSH key. ";
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log($error_string_append . "Couldn't find cPanel account to get quota info from.", 'error');

        $finish_message_append = sprintf(" SSH key named <strong>%s</strong> into cPanel account with %s <strong>%s</strong>",
            $key_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);

        $this->log("Importing " . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->cpanel_api_functions['importkey'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'key' => $key,
            'name' => $key_name,
            'pass' => $key_pass
        )))
        )
            return false;

        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully imported' . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to import' . $finish_message_append);
        return false;
    }

    /**
     * @param string $action
     * @param string $repo_name
     * @param string $repository_root
     * @param string $source_repository
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function version_control(string $action = '', string $repo_name, string $repository_root = '', string $source_repository = '',
                             string $cpanel_parameter = '',
                             string $cpanel_parameter_type = 'user'
    )
    {
        if (!in_array($action, array('create', 'delete', 'get', 'update'))) {
            $this->log("Can't do version control stuff. Non valid action.");
            return false;
        }
        $error_string_append = sprintf("Can't %s version control repository.", $action);

        if (empty($repo_name) || empty($repository_root) || empty($source_repository)) {
            $this->log(sprintf("%s Some or all function args missing.", $error_string_append));
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account)
            $this->log($error_string_append . " Couldn't find cPanel account.", 'error');
        $finish_message_append = sprintf(" git repository in cPanel account with %s <strong>%s</strong>",
            $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $this->log(ucfirst($this->actions[$action]['present']) . $finish_message_append, 'info');
        $args = array();
        switch ($action) {
            case 'create':
                $function = 'create';
                $args = array(
                    'name' => $repo_name,
                    'repository_root' => $repository_root,
                    'source_repository' => $source_repository
                );
                break;
            case 'delete':
                $function = 'delete';
                break;
            case 'get':
                $function = 'retrieve';
                break;
            case 'update':
                $function = 'update';
                break;
        }
        if (!$result = $this->api_call('cpanel', array_merge($this->_cpanel_api_functions['VersionControl'][$function], array(
            'cpanel_jsonapi_module' => 'VersionControl',
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'type' => 'git',
        ), $args))
        )
            return false;
        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully ' . $this->actions[$action]['past'] . $finish_message_append, 'success');
            return true;
        }
        $this->log('Failed to ' . $action . $finish_message_append);
        return false;
    }

    /**
     * @param string $query
     * @param array $args
     * @return mixed
     */
    public
    function api_call(string $query = 'listaccts', array $args = array())
    {

        $args = array_merge($this->api_version, $args);

        $result = $this->curl->api_call($query, $args, 'get');

        if ($result['http_status'] != 200) {
            $debug_backtrace = '';
            if (!empty(debug_backtrace()[1]['function']))
                $debug_backtrace = ' Api call by <code>' . debug_backtrace()[1]['function'] . '()</code> function.';
            $this->log('[!] Curl error: ' . $result['http_status'] . ' returned.' . $debug_backtrace . ' Error text: ' . $result['result']['cpanelresult']['error']);
        }

        $api = $this->get_api_version($result['result'], $query);
        $return = array(
            'result' => $result['result'],
            'message' => $this->get_api_message($result['result'], $api),
            'success' => $this->get_api_success($result['result'], $api)
        );
        return $return;
    }

    /**
     * @param array $curl_result
     * @param string $api
     * @return bool|null
     */
    function get_api_success(array $curl_result = array(), string $api = '')
    {
        if (empty($api))
            $api = $this->get_api_version($curl_result);

        switch ($api) {
            case 'cPanel API 2':
                if (isset($curl_result['cpanelresult']['data'][0]['result']))
                    $flag = $curl_result['cpanelresult']['data'][0]['result'] == 1;
                elseif (!empty($curl_result['cpanelresult']['event']['result']))
                    $flag = $curl_result['cpanelresult']['event']['result'] == 1;
                elseif (!empty($curl_result['cpanelresult']['error']))
                    $flag = 0;

                if (!empty($curl_result['cpanelresult']['error'])
                    || !empty($curl_result['cpanelresult']['data']['reason'])
                    || !empty($curl_result['cpanelresult']['data'][0]['reason']))
                    $flag = 0;
                break;
            case 'cPanel UAPI':
                if (isset($curl_result['result']['status']))
                    $flag = $curl_result['result']['status'];
                break;
            case 'WHM':
                if (isset($curl_result['metadata']['result']))
                    $flag = $curl_result['metadata']['result'];
                break;
        }
        if (isset($flag))
            return $flag == 1 ? true : false;
        $this->log(sprintf("Can't determine whether %s call succeeded or not. cURL result:%s", $api, build_recursive_list($curl_result), 'error'));
        return null;
    }

    /**
     * @param array $curl_result
     * @param string $function
     * @return bool|string
     */
    function get_api_version(array $curl_result = array(), string $function = '')
    {
        if (empty($curl_result) && empty($function))
            return false;
        if (!empty($curl_result)) {
            if (!empty($curl_result['cpanelresult']['apiversion']) && $curl_result['cpanelresult']['apiversion'] == 2)
                return 'cPanel API 2';
            if (!empty($curl_result['apiversion']) && $curl_result['apiversion'] == 3)
                return 'cPanel UAPI';
            if (!empty($curl_result['result'][0]['statusmsg']) || !empty($curl_result['metadata']['reason']))
                return 'WHM';
        }
        if (!empty($function)) {
            switch ($this->cpanel_api_functions[$function]['cpanel_jsonapi_apiversion']) {
                case 2:
                    return 'cPanel API 2';
                    break;
                case 3:
                    return 'cPanel UAPI';
                    break;
            }
        }
        return 'Unknown API';
    }


    /**
     * @param array $curl_result
     * @param string $api
     * @return string
     */
    private
    function get_api_message(array $curl_result = array(), string $api = '')
    {
        if (empty($api))
            $api = $this->get_api_version($curl_result);
        switch ($api) {
            case 'cPanel API 2':
                if (!empty($curl_result['cpanelresult']['data']['reason']))
                    $message = $curl_result['cpanelresult']['data']['reason'];
                else if (!empty($curl_result['cpanelresult']['data'][0]['reason']))
                    $message = $curl_result['cpanelresult']['data'][0]['reason'];
                else if (!empty($curl_result['cpanelresult']['error']))
                    $message = $curl_result['cpanelresult']['error'];
                else
                    $message = '&nbsp;';
                break;
            case 'cPanel UAPI':
                //if ( isset( $curl_result[ 'result' ][ 'errors' ] )
                $message = '';
                $uapi_message_types = array('messages', 'warnings', 'errors');
                foreach ($uapi_message_types as $message_type) {
                    if (!empty($curl_result['result'][$message_type])) {
                        $message .= $message_type . ': ' . build_recursive_list($curl_result['result'][$message_type]);
                    }
                }
                if (!empty($curl_result['type']) && $curl_result['type'] == 'text' && !empty($curl_result['error']))
                    $message .= $curl_result['error'];
                if (empty($message) && $this->get_api_success($curl_result, $api) == 1)
                    return '';
                break;
            case 'WHM':
                $api = 'WHM statusmsg';
                if (!empty($curl_result['result'][0]['statusmsg'])) {
                    if (!empty($curl_result['result'][0]['statusmsg']))
                        $message = $curl_result['result'][0]['statusmsg'];
                    if (!empty($curl_result['statusmsg']))
                        $message = $curl_result['statusmsg'];
                } elseif (!empty($curl_result['metadata']['reason'])) {
                    $message = $curl_result['metadata']['reason'];
                }
                break;
        }
        if (empty($message))
            $message = "Output from API but couldn't decode message." . build_recursive_list($curl_result);
        return !empty($message) ? ' <code><strong>' . $api . ':</strong> ' . $message . '</code>' : '';
    }

}