<?php


namespace Phoenix;

/**
 * Class EmailFilters
 * @package Phoenix\Terminal
 */
class EmailFilters extends AbstractDeployer
{

    /**
     * @var string
     */
    public $environ;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var WHM
     */
    private $whm;

    /**
     * EnvironVersionControl constructor.
     * @param WHM|null $whm
     * @param string $environ
     */
    function __construct($environ = 'live', WHM $whm = null)
    {
        $this->environ = $environ;
        $this->whm = $whm;
        parent::__construct();
    }

    /**
     * @return bool|null
     */
    function create()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return false;

        $number_of_filters = 0;
        $problems = 0;
        $success = true;
        foreach ($args['filters'] as $filter_name => $email_filter) {
            $number_of_filters++;
            if (!$this->whm->create_email_filter(
                $email_filter->account,
                $filter_name,
                $email_filter->args,
                $args['cpanel_username']
            )) {
                $success = false;
                $problems++;
            }
        }
        if (!$success)
            $this->mainStr($problems, $number_of_filters);
        else
            $this->mainStr($number_of_filters, $number_of_filters);
        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    function delete()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return false;

        $number_of_filters = 0;
        $problems = 0;
        $success = true;
        foreach ($args['filters'] as $filter_name => $email_filter) {
            $number_of_filters++;
            if (!$this->whm->delete_email_filter(
                $email_filter->account,
                $filter_name,
                $args['cpanel_username']
            )) {
                $success = false;
                $problems++;
            }
        }
        if (!$success)
            $this->mainStr($problems, $number_of_filters);
        else
            $this->mainStr($number_of_filters, $number_of_filters);
        return $this->logFinish($success);
    }

    /**
     * @return bool
     */
    function validate()
    {
        return true;
    }

    /**
     * @return bool
     */
    protected function getArgs()
    {
        $environ = $this->environ;

        $args['filters'] = ph_d()->config->environ->$environ->cpanel->email_filters ?? false;
        if (empty($args['filters']))
            return $this->logError("Filter args missing from config.");
        $args['filters'] = $this->substitutePlaceholders($args['filters']);
        if (!$args['filters'])
            return false;
        $args['cpanel_username'] = ph_d()->config->environ->primary->cpanel->username ?? false;
        if (empty($args['cpanel_username']))
            return $this->logError("Primary cPanel account username missing from config.");
        return $args;
    }

    /**
     * @param $filters
     * @return bool|string
     */
    private function substitutePlaceholders($filters)
    {


        $placeholders['root_email_folder'] = ph_d()->config->project->root_email_folder ?? '';
        $placeholders['project_name'] = ucwords(ph_d()->config->project->name) ?? '';
        $placeholders['staging_domain'] = ph_d()->get_environ_url('staging') ?? '';
        $placeholders['live_domain'] = ph_d()->get_environ_url('live') ?? '';
        $placeholders['live_cpanel_username'] = ph_d()->config->environ->live->cpanel->account->username ?? '';

        foreach ($placeholders as $placeholder => $actualValue) {
            $find = '%' . $placeholder . '%';

            //if (empty($actualValue))
            //  return $this->logError(sprintf("Couldn't obtain value for <strong>%s</strong> placeholder.", $placeholder));
            foreach ($filters as $filterName => $filter) {
                if (strpos($filterName, $find) !== false) {
                    //d($placeholder . ' ' .$actualValue);
                    if (empty($actualValue))
                        return $this->logError(sprintf("Couldn't obtain value for <strong>%s</strong> placeholder.", $placeholder));

                    $newFilterName = str_replace($find, $actualValue, $filterName);
                    $filters->$newFilterName = $filter;
                    unset($filters->$filterName);
                }
            }
            if ($this->getCaller() != 'delete') {
                foreach ($filters as $filterName => $filter) {
                    foreach ($filter->args as $args) {
                        foreach ($args as &$arg) {
                            if (strpos($arg, $find) !== false) {
                                if (empty($actualValue))
                                    return $this->logError(sprintf("Couldn't obtain value for <strong>%s</strong> placeholder.", $placeholder));
                                $arg = str_replace($find, $actualValue, $arg);
                            }
                        }
                        /*
                        $arg_values = (array) $email_filter->args->val;
                        ksort($arg_values);
                        $email_filter->args->val = array_to_object($arg_values);
                        */
                    }
                }
            }
        }
        return $filters;
    }

    /**
     * @param int $problems
     * @param int $number_of_filters
     * @return string
     */
    protected
    function mainStr(int $problems = 0, int $number_of_filters = 0)
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $finishStr = $number_of_filters > 0 ? sprintf("%d out of %d ", $problems, $number_of_filters) : '';
        return $this->_mainStr[$action] = sprintf('%s%s cPanel email filters', $finishStr, $this->environ);
    }
}