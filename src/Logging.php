<?php

namespace Phoenix;

/**
 * Class Logging
 */
class Logging
{

    /**
     * @var null
     */
    protected static $_instance = null;

    /**
     * @var string
     */
    public $email_message = '';
    /**
     * @var string
     */
    public $message_prepend = '';
    /**
     * @var array
     */
    public $email_args = array();
    /**
     * @var array
     */
    public $messages = array();

    /**
     * @return null|Logging
     */
    public static function instance(): ?Logging
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Logging constructor.
     */
    public function __construct()
    {
    }


    /*
    function init($message_prepend = false, $email_args = false): bool
    {
        if (!empty($message_prepend))
            $this->message_prepend = $message_prepend;
        if (!empty($email_args)) {
            $this->email_args['subject'] = !empty($email_args['subject']) ? $email_args['subject'] : 'CRON log';

            if (!empty($email_args['to']))
                $this->email_args['to'] = $email_args['to'];
            elseif (defined('TO_EMAIL'))
                $this->email_args['to'] = TO_EMAIL;

            if (!empty($email_args['from']))
                $this->email_args['from'] = $email_args['from'];
            elseif (defined('FROM_EMAIL'))
                $this->email_args['from'] = FROM_EMAIL;
        }
        return true;
    }
*/

    /**
     * @param string $message_string
     * @param string $message_type
     * @return bool
     */
    public function add(string $message_string = '', string $message_type = 'error'): bool
    {
        if (!in_array($message_type, array('info', 'success', 'warning', 'light', 'error')))
            return false;
        if (empty($message_string))
            return false;
        $this->messages[] = array('string' => $message_string . PHP_EOL, 'type' => $message_type);
        /*
        if ( $message_type == 'error' )
            trigger_error( $this->message_prepend . $message_string );
        */
        $this->email_message .= $message_string;
        return true;
    }

    /**
     * @param array $list_array
     * @param string $title
     * @param string $heading_tag
     * @param string $message_type
     * @return bool
     */
    function add_list($list_array = array(), $title = 'Array List', $heading_tag = 'h3', $message_type = 'info'): bool
    {
        if (!empty($list_array)) {
            $str = '<' . $heading_tag . '>' . $title . '</' . $heading_tag . '>' . build_recursive_list($list_array);
            $this->add($str, $message_type);
            return true;
        }
        return false;
    }

    /*
    function email_log(): bool
    {
        if (empty($this->email_args['from']) || empty($this->email_args['to']) || empty($this->email_args['subject']))
            return false;
        $headers = 'From: Mabarrack CRM <' . $this->email_args['from'] . '>' . "\r\n";
        $headers .= 'Mime-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        if (mail($this->email_args['to'], $this->email_args['subject'], '<h1>Results</h1>' . $this->email_message, $headers))
            return true;
        return false;
    }
*/
    /**
     * @return bool
     */
    public function display(): ?bool
    {
        if (empty($this->messages))
            return false;

        $message_html = '';
        foreach ($this->messages as $message) {
            if (!empty($message['string'])) {
                $message_type = !empty($message['type']) ? $message['type'] : 'error';
                switch($message_type) {
                    case 'info':
                        $css_class = 'primary';
                        break;
                    case 'success':
                        $css_class = 'success';
                        break;
                    case 'warning':
                        $css_class = 'warning';
                        break;
                    case 'light':
                        $css_class = 'light';
                        break;
                    case 'error':
                    default:
                        $css_class = 'danger';
                        break;
                }
                /*
                switch ( $message_type ) {
                    case 1:
                    case 'notification':
                        $css_class = 'notification';
                        break;
                    case 'main_notification':
                    case 'start_level_1':
                        $tag = 'h2';
                        $css_class = 'primary';
                        break;
                    case 'start_level_2':
                    case 'start':
                    case 3:
                        $css_class = 'notification';
                        $tag = 'h3';
                        break;
                    case 'start_level_3':
                        $css_class = 'notification';
                        $tag = 'h5';
                        break;
                    case 'debug':
                    case 10:
                        $css_class = 'notification';
                        $tag = 'code';
                        break;
                    case 'secondary':
                        $css_class = 'secondary';
                        break;
                    case 'success':
                        $css_class = 'success';
                        break;
                    case 'warning':
                        $css_class = 'warning';
                        break;
                    case 'info':
                        $css_class = 'info';
                        break;
                    case 2:
                    case 'error':
                    case 'danger':
                    default:
                        $css_class = 'danger';
                        break;
                }
                <br>
                */
                $message_html .= sprintf('<div class="alert alert-%s" role="alert">%s</div>', $css_class, $message['string']);
            }
        }
        echo $message_html; //nl2br( $message_html );
        return true;
    }
}

/**
 * Main instance of Logging.
 *
 */
function logger()
{
    return Logging::instance();
}