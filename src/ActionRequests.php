<?php

namespace Phoenix;

/**
 * Class ActionRequests
 *
 * @package Phoenix
 */
class ActionRequests
{
    /**
     * @var array
     */
    public $permissions = array(
        'create' => array('label' => 'Create'),
        'create_version_control' => array('label' => 'Create main version control repository',
            'condition' => array('create')),

        'create_live_stuff' => array('label' => 'Live stuff',
            'condition' => 'create'),
        'create_live_site' => array('label' => 'cPanel account',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_db' => array('label' => 'Database & DB User',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_email_filters' => array('label' => 'Email filters',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_version_control' => array('label' => 'Setup version control',
            'condition' => array('create', 'create_live_stuff')),


        'create_live_wp' => array('label' => 'WordPress & WP CLI Setup',
            'condition' => array('create', 'create_live_stuff')),
        'create_live_wp_config' => array('label' => 'Setup wp-config.php',
            'condition' => array('create', 'create_live_stuff', 'create_live_wp')),
        'create_live_wp_install' => array('label' => 'Install WordPress',
            'condition' => array('create', 'create_live_stuff', 'create_live_wp')),
        'create_live_wp_htaccess' => array('label' => 'Add custom WP .htaccess rules',
            'condition' => array('create', 'create_live_stuff', 'create_live_wp')),


        'create_live_initial_git_commit' => array('label' => 'Initial Git commit',
            'condition' => array('create', 'create_live_stuff')),
        'create_staging_stuff' => array('label' => 'Staging stuff',
            'condition' => array('create')),
        'create_staging_subdomain' => array('label' => 'Staging cPanel subdomain',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_db' => array('label' => 'Database & DB User',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_email_filters' => array('label' => 'Email filters',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_version_control' => array('label' => 'Setup version control',
            'condition' => array('create', 'create_staging_stuff')),


        'create_staging_wp' => array('label' => 'WordPress & WP CLI Setup',
            'condition' => array('create', 'create_staging_stuff')),
        'create_staging_wp_config' => array('label' => 'Setup wp-config.php',
            'condition' => array('create', 'create_staging_stuff', 'create_staging_wp')),
        'create_staging_wp_install' => array('label' => 'Install WordPress',
            'condition' => array('create', 'create_staging_stuff', 'create_staging_wp')),
        'create_staging_wp_htaccess' => array('label' => 'Add custom WP .htaccess rules',
            'condition' => array('create', 'create_staging_stuff', 'create_staging_wp')),


        'create_staging_initial_git_commit' => array('label' => 'Initial Git commit',
            'condition' => array('create', 'create_staging_stuff')),

        'create_local_stuff' => array('label' => 'Local stuff',
            'condition' => array('create')),
        'create_local_version_control' => array('label' => 'Setup version control',
            'condition' => array('create', 'create_local_stuff')),
        'create_local_virtual_host' => array('label' => 'Virtual Host',
            'condition' => array('create', 'create_local_stuff')),
        'create_local_database_components' => array('label' => 'Database & DB User',
            'condition' => array('create', 'create_local_stuff')),

        'create_local_wp' => array('label' => 'Install WordPress & WP CLI',
            'condition' => array('create', 'create_local_stuff')),
        'create_local_wp_config' => array('label' => 'Setup wp-config.php',
            'condition' => array('create', 'create_local_stuff', 'create_local_wp')),
        'create_local_wp_install' => array('label' => 'Install WordPress',
            'condition' => array('create', 'create_local_stuff', 'create_local_wp')),
        'create_local_wp_htaccess' => array('label' => 'Add custom WP .htaccess rules',
            'condition' => array('create', 'create_local_stuff', 'create_local_wp')),

        'create_local_initial_git_commit' => array('label' => 'Initial Git commit',
            'condition' => array('create', 'create_local_stuff')),
        //'create_wp_auto_update' => array('label' => 'Setup WordPress auto-update',
        //  'condition' => array('create')),
        'delete' => array('label' => 'Delete'),
        'delete_version_control' => array('label' => 'Delete main version control repository',
            'condition' => array('delete')),
        'delete_live_stuff' => array('label' => 'Live stuff',
            'condition' => array('delete')),
        'delete_live_site' => array('label' => 'cPanel account',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_db' => array('label' => 'Database & DB User',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_email_filters' => array('label' => 'Email filters',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_version_control' => array('label' => 'Remove version control',
            'condition' => array('delete', 'delete_live_stuff')),
        'delete_live_wp' => array('label' => 'Remove WordPress & WP CLI',
            'condition' => array('delete', 'delete_live_stuff')),

        'delete_staging_stuff' => array('label' => 'Staging stuff',
            'condition' => array('delete')),
        'delete_staging_subdomain' => array('label' => 'Staging cPanel subdomain',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_db' => array('label' => 'Database & DB User',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_email_filters' => array('label' => 'Email filters',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_version_control' => array('label' => 'Remove version control',
            'condition' => array('delete', 'delete_staging_stuff')),
        'delete_staging_wp' => array('label' => 'Remove WordPress & WP CLI',
            'condition' => array('delete', 'delete_staging_stuff')),

        'delete_local_stuff' => array('label' => 'Delete local stuff',
            'condition' => array('delete')),
        'delete_local_version_control' => array('label' => 'Delete version control',
            'condition' => array('delete', 'delete_local_stuff')),
        'delete_local_virtual_host' => array('label' => 'Virtual host',
            'condition' => array('delete', 'delete_local_stuff')),
        'delete_local_database_components' => array('label' => 'Database & DB User',
            'condition' => array('delete', 'delete_local_stuff')),
        'delete_local_wp' => array('label' => 'Remove WordPress & WP CLI',
            'condition' => array('delete', 'delete_local_stuff')),

        'update' => array('label' => 'Update'),
        'update_live_stuff' => array('label' => 'Live stuff',
            'condition' => array('update')),
        'update_live_wp' => array('label' => 'Update WordPress core, plugins and themes',
            'condition' => array('update', 'update_live_stuff')),
        'update_staging_stuff' => array('label' => 'Staging stuff',
            'condition' => array('update')),
        'update_staging_wp' => array('label' => 'Update WordPress core, plugins and themes',
            'condition' => array('update', 'update_staging_stuff')),
        'update_local_stuff' => array('label' => 'Local stuff',
            'condition' => array('update')),
        'update_local_wp' => array('label' => 'Update WordPress core, plugins and themes',
            'condition' => array('update', 'update_local_stuff')),

        'transfer' => array('label' => 'Transfer'),
        'transfer_wp_db' => array('label' => 'WordPress DB',
            'condition' => array('transfer')),
        'transfer_wp_db_from_live' => array('label' => 'From live',
            'condition' => array('transfer', 'transfer_wp_db')),
        'transfer_wp_db_live_to_staging' => array('label' => 'to staging server',
            'condition' => array('transfer', 'transfer_wp_db_from_live', 'transfer_wp_db')),
        'transfer_wp_db_live_to_local' => array('label' => 'to local server',
            'condition' => array('transfer', 'transfer_wp_db_from_live', 'transfer_wp_db')),
        'transfer_wp_db_from_staging' => array('label' => 'From staging',
            'condition' => array('transfer', 'transfer_wp_db')),
        'transfer_wp_db_staging_to_live' => array('label' => 'to live server',
            'condition' => array('transfer', 'transfer_wp_db_from_staging', 'transfer_wp_db')),
        'transfer_wp_db_staging_to_local' => array('label' => 'to local server',
            'condition' => array('transfer', 'transfer_wp_db_from_staging', 'transfer_wp_db')),
        'transfer_wp_db_from_local' => array('label' => 'From local',
            'condition' => array('transfer', 'transfer_wp_db')),
        'transfer_wp_db_local_to_live' => array('label' => 'to live server',
            'condition' => array('transfer', 'transfer_wp_db_from_local', 'transfer_wp_db')),
        'transfer_wp_db_local_to_staging' => array('label' => 'to staging server',
            'condition' => array('transfer', 'transfer_wp_db_from_local', 'transfer_wp_db')),
    );

    /**
     * ActionRequests constructor.
     */
    function __construct()
    {
        foreach ($this->permissions as &$action) {
            if (!empty($action['condition']) && !is_array($action['condition']))
                $action['condition'] = array($action['condition']);
        }
        $this->process_request();
        return true;
    }

    /**
     * @return array|bool
     */
    function process_request()
    {
        if (empty($_POST))
            return false;
        $actions = $this->permissions;
        foreach ($actions as $key => &$action) {
            if (!empty($_POST[$key])) {
                $action['can_do'] = true;
                if (!empty($action['condition'])) {
                    $action['can_do'] = true;
                    foreach ($action['condition'] as $condition) {
                        if (empty($_POST[$condition])) {
                            $action['can_do'] = false;
                            break;
                        }
                    }
                }
            }
        }
        return $this->permissions = $actions;
    }

    /**
     * @param string $actions
     * @param string $operator
     * @return bool
     */
    function can_do($actions = '', $operator = 'AND')
    {
        if (empty($actions) || empty($this->permissions))
            return false;
        if (!is_array($actions))
            $actions = array($actions);
        foreach ($actions as $action) {
            if ($operator = 'AND') {
                if (empty($this->permissions[$action]['can_do']))
                    return false;
            } else if ($operator = 'OR') {
                if (!empty($this->permissions[$action]['can_do']))
                    return true;
            }
        }
        return true;
    }
}