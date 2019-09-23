<?php

namespace Phoenix;

/**
 * @property array $actions
 *
 * Class Base
 */
class Base
{
    /**
     * @var array
     */
    private $_actions = array(
        'authorize' => array(
            'present' => 'authorising',
            'past' => 'authorised',
            'action' => 'authorise'
        ),
        'backup' => array(
            'present' => 'backing up',
            'past' => 'backed up',
            'action' => 'back up'
        ),
        'check' => array(
            'present' => 'checking',
            'past' => 'checked',
            'action' => 'check'
        ),
        'checkForChanges' => array(
            'present' => 'checking for changes',
            'past' => 'checked for changes',
            'action' => 'check for changes'
        ),
        'checkout' => array(
            'present' => 'checking out',
            'past' => 'checked out',
            'action' => 'checkout'
        ),
        'clone' => array(
            'present' => 'cloning',
            'past' => 'cloned',
            'action' => 'clone'
        ),
        'commit' => array(
            'present' => 'committing',
            'past' => 'committed',
            'action' => 'commit'
        ),
        'create' => array(
            'present' => 'creating',
            'past' => 'created',
            'action' => 'create'
        ),
        'deauthorize' => array(
            'present' => 'deauthorising',
            'past' => 'deauthorised',
            'action' => 'deauthorise'
        ),
        'delete' => array(
            'present' => 'deleting',
            'past' => 'deleted',
            'action' => 'delete'
        ),
        'deploy' => array(
            'present' => 'deploying',
            'past' => 'deployed',
            'action' => 'deploy'
        ),
        'download' => array(
            'present' => 'downloading',
            'past' => 'downloaded',
            'action' => 'download'
        ),
        'export' => array(
            'present' => 'exporting',
            'past' => 'exported',
            'action' => 'export'
        ),
        'generate' => array(
            'present' => 'generating',
            'past' => 'generated',
            'action' => 'generate'
        ),
        'get' => array(
            'present' => 'getting',
            'past' => 'got',
            'action' => 'get'
        ),
        'getSubdomains' => array(
            'present' => 'getting',
            'past' => 'got',
            'action' => 'get'
        ),
        'getSubdomaincPanel' => array(
            'present' => 'getting',
            'past' => 'got',
            'action' => 'get'
        ),
        'give' => array(
            'present' => 'giving',
            'past' => 'gave',
            'action' => 'give'
        ),
        'import' => array(
            'present' => 'importing',
            'past' => 'imported',
            'action' => 'import'
        ),
        'install' => array(
            'present' => 'installing',
            'past' => 'installed',
            'action' => 'install'
        ),
        'installLatestDefaultTheme' => array(
            'present' => 'installing latest default theme',
            'past' => 'installed latest default theme',
            'action' => 'install latest default theme'
        ),
        'move' => array(
            'present' => 'moving',
            'past' => 'moved',
            'action' => 'move'
        ),
        'pull' => array(
            'present' => 'pulling',
            'past' => 'pulled',
            'action' => 'pull'
        ),
        'prepend' => array(
            'present' => 'prepending',
            'past' => 'prepended',
            'action' => 'prepend'
        ),
        'prune' => array(
            'present' => 'pruning',
            'past' => 'pruned',
            'action' => 'prune'
        ),
        'purge' => array(
            'present' => 'purging',
            'past' => 'purged',
            'action' => 'purge'
        ),
        'remove' => array(
            'present' => 'removing',
            'past' => 'removed',
            'action' => 'remove'
        ),
        'replaceURLs' => array(
            'present' => 'replacing URLs',
            'past' => 'replaced URLs',
            'action' => 'replace URLs'
        ),
        'reset' => array(
            'present' => 'resetting',
            'past' => 'reset',
            'action' => 'reset'
        ),
        'set' => array(
            'present' => 'setting',
            'past' => 'set',
            'action' => 'set'
        ),
        'setGitUser' => array(
            'present' => 'setting user config for',
            'past' => 'set user config for',
            'action' => 'set user config for'
        ),
        'setOption' => array(
            'present' => 'setting option for',
            'past' => 'set option for',
            'action' => 'set option for'
        ),
        'setOptions' => array(
            'present' => 'setting options for',
            'past' => 'set options for',
            'action' => 'set options for'
        ),
        'setPermissions' => array(
            'present' => 'setting permissions for',
            'past' => 'set permissions for',
            'action' => 'set permissions for'
        ),
        'setRewriteRules' => array(
            'present' => 'setting rewrite rules for',
            'past' => 'set rewrite rules for',
            'action' => 'set rewrite rules for'
        ),
        'setupConfig' => array(
            'present' => 'setting up wp-config.php for',
            'past' => 'set up wp-config.php for',
            'action' => 'set up wp-config.php for'
        ),
        'sync' => array(
            'present' => 'syncing',
            'past' => 'synced',
            'action' => 'sync'
        ),
        'transfer' => array(
            'present' => 'transferring',
            'past' => 'transferred',
            'action' => 'transfer'
        ),
        'uninstall' => array(
            'present' => 'uninstalling',
            'past' => 'uninstalled',
            'action' => 'uninstall'
        ),
        'update' => array(
            'present' => 'updating',
            'past' => 'updated',
            'action' => 'update'
        ),
        'upload' => array(
            'present' => 'uploading',
            'past' => 'uploaded',
            'action' => 'upload'
        )
    );

    /**
     * Base constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (method_exists($this, $name)) {
            $this->$name($value);
        } else {
            // Getter/Setter not defined so set as property of object
            $this->$name = $value;
        }
    }

    /**
     * @param $name
     * @return null
     */
    public function __get($name)
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        }
        if (property_exists($this, $name)) {
            // Getter/Setter not defined so return property if it exists
            return $this->$name;
        }
        return null;
    }

    public function actions(array $actions = array())
    {
        if (!empty($actions))
            $this->_actions = $actions;
        elseif (!empty($this->_actions))
            return $this->_actions;
        return false;
    }

    /**
     * @param string $message_string
     * @param string $message_type
     * @return bool
     */
    public
    function log(string $message_string = '', string $message_type = 'error'): bool
    {
        return logger()->add($message_string, $message_type);
    }

    /**
     * @param string $action
     * @param array $actions
     * @param string $message
     * @return bool
     */
    public static function validate_action($action = '', $actions = array(), $message = ''): bool
    {
        if (empty($action)) {
            $fail = true;
            $fail_string = 'No action inputted to %s.';
        } elseif (!in_array($action, $actions)) {
            $fail = true;
            $fail_string = "%s received '<strong>%s</strong>' as input action.";
        }
        if (!empty($fail)) {
            $debug_backtrace = !empty(debug_backtrace()[1]['function']) ? '<code>' . debug_backtrace()[1]['function'] . '()</code> function' : 'function';
            $fail_string = sprintf($fail_string, $debug_backtrace, $action, $debug_backtrace);
            self::log(sprintf('%s Action must be %s. %s',
                $message, self::implodeItemStr($actions), $fail_string), 'error');
            return false;
        }
        return true;
    }

    /**
     * @param array $array
     * @return string
     */
    private static function implodeItemStr(array $array = array()): string
    {
        foreach ($array as &$item) {
            $item = "'" . $item . "'";
        }
        $last = array_slice($array, -1);
        $first = join(', ', array_slice($array, 0, -1));
        $both = array_filter(array_merge(array($first), $last), 'strlen');
        return join(' or ', $both);
    }
}