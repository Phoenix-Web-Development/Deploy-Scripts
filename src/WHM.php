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
            'authkey' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'authkey'
            ),
            'delkey' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'delkey'
            ),
            'fetchkey' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'fetchkey'
            ),
            'listkeys' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'listkeys'
            ),
            'genkey' => array(
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_func' => 'genkey'
            ),
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
            $this->log(sprintf("Can't get cPanel account. %s is missing and no previously queried account to return.", ucfirst($type)), 'error');
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

    public
    function get_disk_usage()
    {
        if (!$result = $this->api_call('getdiskusage', array()))
            return false;
        if ($result['success']) {
            return $result['result'];
        }
        $this->log(sprintf("Couldn't get WHM disk usage. %s", $result['message']));
        return false;
    }

    public
    function get_pkg_info(string $package = '')
    {
        $error_str = "Couldn't get package info. ";
        if (empty($package)) {
            $this->log(sprintf("%s No package name supplied to function.", $error_str));
            return false;
        }
        if (!$result = $this->api_call('getpkginfo', array('pkg' => $package)))
            return false;
        if ($result['success']) {
            return $result['result'];
        }
        $this->log(sprintf("Couldn't get %s package info. %s", $package, $result['message']));
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
        if (!$cpanel_account) {
            $this->log("Failed to list cPanel account domains. Couldn't find cPanel account to query for domains.");
            return false;
        }

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
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to add DB schema to.", 'error');
            return false;
        }

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
        if (!$this->validate_action($action, array('get', 'set'), "Can't do DB user privileges stuff."))
            return false;

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
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to delete DB user from.", 'error');
            return false;
        }
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
     * Ensure input has the cPanel prefix on the front
     *
     * @param $db_inputs
     * @param string $cpanel_username
     * @return bool|string
     */
    public
    function db_prefix_check($db_inputs = array(), string $cpanel_username = '')
    {
        if (empty($db_inputs))
            return false;
        if (is_string($db_inputs))
            $db_inputs = array($db_inputs);
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
        foreach ($db_inputs as &$db_input) {
            $strpos = strpos($db_input, substr($cpanel_username . '_', 0, 8));
            if ($strpos === false || $strpos > 0)
                $db_input = $cpanel_username . '_' . $db_input;
        }
        if (count($db_inputs) == 1)
            return $db_inputs[0];
        return $db_inputs;
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
        if (!$cpanel_account) {
            $this->log(sprintf("Can't add <strong>%s</strong> subdomain. No cPanel account with domain <strong>%s</strong> found.", $subdomain, $rootdomain), 'error');
            return false;
        }
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
        if (!$cpanel_account) {
            $this->log($error_string . "Couldn't find cPanel account to query.", 'error');
            return false;
        }
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
        if (!$cpanel_account) {
            $this->log(sprintf($error_string . "Couldn't find cPanel account to delete <strong>%s</strong> subdomain from.", $subdomain_slug), 'error');
            return false;
        }
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
     * Create, delete or get cPanel email filter
     *
     * @param string $action
     * @param string $account
     * @param string $filter_name
     * @param array $args
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @param bool $quiet
     * @return bool
     */
    public
    function email_filter($action = '', string $account = '', string $filter_name = '', $args = array(),
                          string $cpanel_parameter = '', string $cpanel_parameter_type = 'user', bool $quiet = false)
    {
        if (!$this->validate_action($action, array('create', 'delete', 'get'), "Can't do email filter stuff."))
            return false;
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
        if (!$quiet)
            $this->log(ucfirst($this->actions[$action]['present']) . $finish_message_append, 'info');
        switch ($action) {
            case 'create':
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
                if (!$quiet)
                    $this->log('Successfully ' . $this->actions[$action]['past'] . $finish_message_append, 'success');
                return true;
            }
            if (isset($result['result']['result']['data']['rules']['part']))
                return $result['result']['result']['data'];
        }
        if (!$quiet)
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
        return $this->email_filter('get', $account, $filter_name, array(), $cpanel_parameter, $cpanel_parameter_type, true);
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
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to get quota info from.", 'error');
            return false;
        }

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

    /**
     * @param string $key
     * @param string $key_name
     * @param string $key_pass
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function import_key(string $key = '', string $key_name = '', string $key_pass = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        $error_string_append = "Can't import SSH key into cPanel. ";
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to get quota info from.", 'error');
            return false;
        }

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
     * @param string $key_name
     * @param string $key_pass
     * @param string $key_bits
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function genkey(string $key_name = '', string $key_pass = '', string $key_bits = '2048', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        $error_string_append = "Can't generate cPanel SSH key. ";
        if (empty($key_name)) {
            $this->log($error_string_append . "No key name supplied to function.", 'error');
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to generate SSH key for.", 'error');
            return false;
        }
        if ($ssh_key = $this->fetchkey($key_name)) {
            $this->log(sprintf("%s Key named <strong>%s</strong> already exists.", $error_string_append, $key_name), 'error');
            return $ssh_key;
        }

        $finish_message_append = sprintf(" SSH key named <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $key_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $this->log('Generating' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->_cpanel_api_functions['SSH']['genkey'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'cpanel_jsonapi_module' => 'SSH',
            'name' => $key_name,
            'pass' => $key_pass,
            'bits' => $key_bits,
            'type' => 'rsa'
        )))
        )
            return false;
        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully generated' . $finish_message_append, 'success');
            return $this->fetchkey($key_name);
        }
        $this->log('Failed to generate' . $finish_message_append);
        return false;
    }

    /**
     * @param string $key_name
     * @param int $pub
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function fetchkey(
        string $key_name = '',
        int $pub = 1,
        string $cpanel_parameter = '',
        string $cpanel_parameter_type = 'user'
    )
    {
        $error_string_append = "Can't fetch SSH key. ";
        if (empty($key_name)) {
            $this->log($error_string_append . "No key name supplied to function.", 'error');
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to fetch SSH key for.", 'error');
            return false;
        }
        switch ($pub) {
            case 0:
                $key_type = 'private';
                break;
            case 1:
                $key_type = 'public';
                break;
            default:
                $this->log($error_string_append . "Key type must be 1 for public key or 0 for private key.", 'error');
                return false;
                break;
        }
        $finish_message_append = sprintf(" %s SSH key named <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $key_type, $key_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        //$this->log('Fetching' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->_cpanel_api_functions['SSH']['fetchkey'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'cpanel_jsonapi_module' => 'SSH',
            'name' => $key_name,
            'pub' => $pub,
        )))
        )
            return false;
        $finish_message_append .= $result['message'];
        if ($result['success'] && !empty($result['result']['cpanelresult']['data'][0]['key'])) {
            //$this->log('Successfully fetched' . $finish_message_append, 'success');
            return $result['result']['cpanelresult']['data'][0];
        }
        //$this->log('Failed to fetch' . $finish_message_append);
        return false;
    }

    /**
     * @param string $key_name
     * @param int $pub
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function listkeys(
        string $key_name = '',
        int $pub = 1,
        string $cpanel_parameter = '',
        string $cpanel_parameter_type = 'user'
    )
    {
        $error_string_append = "Can't list SSH keys. ";
        if (empty($key_name)) {
            $this->log($error_string_append . "No key name supplied to function.", 'error');
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . "Couldn't find cPanel account to list SSH keys for.", 'error');
            return false;
        }
        switch ($pub) {
            case 0:
                $key_type = 'private';
                break;
            case 1:
                $key_type = 'public';
                break;
            default:
                $this->log($error_string_append . "Key type must be 1 for public key or 0 for private key.", 'error');
                return false;
                break;
        }
        $finish_message_append = sprintf(" %s SSH keys named <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $key_type, $key_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        //$this->log('Fetching' . $finish_message_append, 'info');
        if (!$result = $this->api_call('cpanel', array_merge($this->_cpanel_api_functions['SSH']['listkeys'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'cpanel_jsonapi_module' => 'SSH',
            'keys' => $key_name,
            'pub' => $pub,
        )))
        )
            return false;
        $finish_message_append .= $result['message'];
        if ($result['success'] && !empty($result['result']['cpanelresult']['data'][0]['key'])) {
            //$this->log('Successfully listed' . $finish_message_append, 'success');
            return $result['result']['cpanelresult']['data'][0];
        }
        //$this->log('Failed to list' . $finish_message_append);
        return false;
    }

    /**
     * @param string $key_name
     * @param string $action
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function authkey(string $key_name = '', string $action = 'authorize', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        if (!$this->validate_action($action, array('authorize', 'deauthorize'), "Can't do cPanel key authorisation stuff."))
            return false;

        $error_string_append = sprintf("Can't %s SSH key.", $this->actions[$action]['action']);
        if (empty($key_name)) {
            $this->log($error_string_append . "No key name supplied to function.", 'error');
            return false;
        }

        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log(sprintf("%s Couldn't find cPanel account to %s SSH key for.",
                $error_string_append, $this->actions[$action]['present']), 'error');
            return false;
        }

        $finish_message_append = sprintf(" SSH key named <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $key_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $this->log(ucfirst($this->actions[$action]['present']) . ' ' . $finish_message_append, 'info');
        $error_string_append = "Can't " . $action . $finish_message_append;
        $key = $this->listkeys($key_name);
        if (empty($key)) {
            $this->log(sprintf("%s Key doesn't exist.",
                $error_string_append), 'error');
            return false;
        }
        if (($key['authstatus'] == 'authorized' && $action == 'authorize') || ($key['authstatus'] == 'not authorized' && $action == 'deauthorize')) {
            $this->log(sprintf("%s Key named <strong>%s</strong> is already %s.",
                $error_string_append, $key_name, $this->actions[$action]['past']), 'error');
            return false;
        }

        if (!$result = $this->api_call('cpanel', array_merge($this->_cpanel_api_functions['SSH']['authkey'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'cpanel_jsonapi_module' => 'SSH',
            'key' => $key_name,
            'action' => $action
        )))
        )
            return false;
        $finish_message_append .= $result['message'];
        if ($result['success']) {
            $this->log('Successfully ' . $this->actions[$action]['past'] . $finish_message_append, 'success');
            return true;
        }
        $this->log("Failed to " . $action . $finish_message_append);
        return false;
    }

    /**
     * @param string $key_name
     * @param string $action
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function delkey(string $key_name = '', string $cpanel_parameter = '', string $cpanel_parameter_type = 'user')
    {
        $action = 'delete';
        $error_string_append = sprintf("Can't delete SSH key.");
        if (empty($key_name)) {
            $this->log($error_string_append . "No key name supplied to function.", 'error');
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log(sprintf("%s Couldn't find cPanel account to %s SSH key for.",
                $error_string_append, $this->actions[$action]['present']), 'error');
            return false;
        }

        $finish_message_append = sprintf(" SSH key named <strong>%s</strong> in cPanel account with %s <strong>%s</strong>.",
            $key_name, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        $this->log(ucfirst($this->actions[$action]['present']) . $finish_message_append, 'info');
        $input = array_merge($this->_cpanel_api_functions['SSH']['delkey'], array(
            'cpanel_jsonapi_user' => $cpanel_account['user'],
            'cpanel_jsonapi_module' => 'SSH',
            'name' => $key_name,
        ));

        if (!$result = $this->api_call('cpanel', array_merge($input, array('pub' => 1)))
        )
            return false;
        if (!$result = $this->api_call('cpanel', array_merge($input, array('pub' => 0)))
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
     * @param string $repo_name
     * @param string $repository_root
     * @param string $source_repository
     * @param string $cpanel_parameter
     * @param string $cpanel_parameter_type
     * @return bool
     */
    public
    function version_control(string $action = '',
                             string $repository_root = '',
                             string $repo_name = '',
                             string $source_repository = '',
                             string $cpanel_parameter = '',
                             string $cpanel_parameter_type = 'user'
    )
    {
        if (!in_array($action, array('clone', 'delete', 'get', 'update'))) {
            $this->log("Can't do version control stuff. Non valid action.");
            return false;
        }
        $error_string_append = sprintf("Can't %s version control repository.", $action);
        if (empty($repository_root) && $action != 'get') {
            $this->log(sprintf("%s Repository root function arg is missing.", $error_string_append));
            return false;
        }
        $cpanel_account = $this->get_cpanel_account($cpanel_parameter, $cpanel_parameter_type);
        if (!$cpanel_account) {
            $this->log($error_string_append . " Couldn't find cPanel account.", 'error');
            return false;
        }
        $repository_url = json_decode($source_repository)->url ?? '';
        $repository_url_str = !empty($repository_url) ? sprintf(' sourced from <strong>%s</strong>', $repository_url) : '';
        $repository_root_str = !empty($repository_root) ? sprintf(' located at <strong>%s</strong>', $repository_root) : '';
        $finish_message_append = sprintf(" Git repository%s%s in cPanel account with %s <strong>%s</strong>.",
            $repository_root_str, $repository_url_str, $cpanel_parameter_type, $cpanel_account[$cpanel_parameter_type]);
        if ($action != 'get')
            $this->log(ucfirst($this->actions[$action]['present']) . $finish_message_append, 'info');
        $args = array();
        switch ($action) {
            case 'clone':
                if (empty($repo_name) || empty($source_repository)) {
                    $this->log(sprintf("%s Some creation function args are missing.", $error_string_append));
                    return false;
                }
                $existing_repo = $this->version_control('get', $repository_root, $repo_name, $source_repository);
                if (!empty($existing_repo)) {
                    if (!empty($existing_repo['repository_root']) && $existing_repo['repository_root'] == $repository_root)
                        $existing_repo_error = sprintf('with root <strong>%s</strong>', $repository_root);
                    elseif (!empty($existing_repo['name']) && $existing_repo['name'] == $repo_name)
                        $existing_repo_error = sprintf('named <strong>%s</strong>', $repo_name);
                    elseif (!empty($existing_repo['source_repository']['url']) && $existing_repo['source_repository']['url'] == $repository_url)
                        $existing_repo_error = sprintf('with remote url <strong>%s</strong>', $repository_url);
                    if (!empty($existing_repo_error)) {
                        $this->log(sprintf("%s Repository %s already exists.", $error_string_append, $existing_repo_error));
                        return false;
                    }
                }
                $function = 'create';
                $args = array(
                    'name' => $repo_name,
                    'repository_root' => $repository_root,
                    'source_repository' => $source_repository
                );
                break;
            case 'delete':
                $function = 'delete';
                $args = array(
                    'repository_root' => $repository_root,
                );
                break;
            case 'get':
                //$args['fields'] = '*';
                $args['fields'] = 'name,type,branch,last_update,source_repository';

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
            switch ($action) {
                case 'clone':
                    if ($this->version_control('get', $repository_root, $repo_name, $source_repository)) {
                        $success = true;
                    }
                    break;
                case 'delete':
                    if (!$this->version_control('get', $repository_root, $repo_name, $source_repository)) {
                        $success = true;
                    }
                    break;
                case 'get':
                    //$repos = $result['result']['result']['data'];
                    if (!empty($result['result']['result']['data'])) {
                        foreach ($result['result']['result']['data'] as $repo) {
                            if (!empty($repo['repository_root']) && $repo['repository_root'] == $repository_root
                                || !empty($repo['name']) && $repo['name'] == $repo_name
                                || !empty($repo['source_repository']['url']) && $repo['source_repository']['url'] == $repository_url
                            )
                                return $repo;
                        }
                    }
                    break;
                case 'update':
                    break;
            }
            if (!empty($success)) {
                $this->log('Successfully ' . $this->actions[$action]['past'] . $finish_message_append, 'success');
                return true;
            }
        }
        if ($action != 'get')
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
                    $flag = $curl_result['cpanelresult']['data'][0]['result'];
                elseif (!empty($curl_result['cpanelresult']['event']['result']))
                    $flag = $curl_result['cpanelresult']['event']['result'];
                elseif (!empty($curl_result['cpanelresult']['error']))
                    $flag = 0;

                if (!empty($curl_result['cpanelresult']['error']))
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
                } elseif (!empty($curl_result['result']['metadata']['reason'])) {
                    $message = $curl_result['result']['metadata']['reason'];
                }
                break;
        }
        if (empty($message))
            $message = "Output from API but couldn't decode message." . build_recursive_list($curl_result);
        return !empty($message) ? ' <code><strong>' . $api . ':</strong> ' . $message . '</code>' : '';
    }

}