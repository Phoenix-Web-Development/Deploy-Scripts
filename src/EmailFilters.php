<?php


namespace Phoenix;

/**
 * Class EmailFilters
 *
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
     *
     * @param WHM|null $whm
     * @param string $environ
     */
    public function __construct($environ = 'live', WHM $whm = null)
    {
        $this->environ = $environ;
        $this->whm = $whm;
        parent::__construct();
    }

    /**
     * @return bool|null
     */
    public function create(): ?bool
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
    public function delete(): ?bool
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
    private function validate(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    protected function getArgs(): bool
    {
        $environ = $this->environ;

        $args['filters'] = ph_d()->config->environ->$environ->cpanel->email_filters ?? false;
        if (empty($args['filters']))
            return $this->logError('Filter args missing from config.');
        //$args['filters'] = $this->substitutePlaceholders($args['filters']);
        if (!$args['filters'])
            return false;
        $args['cpanel_username'] = ph_d()->config->environ->primary->cpanel->username ?? false;
        if (empty($args['cpanel_username']))
            return $this->logError('Primary cPanel account username missing from config.');
        return $args;
    }

    /**
     * @param int $problems
     * @param int $number_of_filters
     * @return string
     */
    protected
    function mainStr(int $problems = 0, int $number_of_filters = 0): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        $finishStr = $number_of_filters > 0 ? sprintf('%d out of %d ', $problems, $number_of_filters) : '';
        return $this->_mainStr[$action] = sprintf('%s%s cPanel email filters', $finishStr, $this->environ);
    }
}