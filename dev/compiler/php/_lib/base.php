<?php
defined('K2_COMPILER') or die('DIRECT ACCESS TO THIS FILE DENIED');

/**
 * Base class
 * Offers error logging and other basic functionality
 *
 **/
class K2base
{
    /**
     * @var    array    holds all error messages
     *
     **/
    private static $errors;


    /**
     * Constructor
     *
     **/
    protected function __construct()
    {
        $this->errors = array();
    }


    /**
     * Logs an error message
     *
     * @param     string    $msg     The message to log
     * @param     bool      $ret     What to return
     * @return    bool      $ret     Returns bool False by default
     **/
    protected function logError($msg, $ret = false)
    {
        $this->errors[] = $msg;

        return $ret;
    }


    /**
     * Returns all error messages
     *
     * @return    array    $errors    Error messages
     **/
    public function getError()
    {
        return $this->errors;
    }


    /**
     * Sets the value of a private var
     *
     * @param    string    $var      The name of the variable
     * @param    mixed     $value    The new value
     *
     * @return    void
     **/
    public function set($var, $value = NULL)
    {
        $this->var = $value;
    }


    /**
     * Returns the value of a private or protected var
     *
     * @param    string    $var      The name of the variable
     *
     * @return   mixed
     **/
    public function get($var)
    {
        if(isset($this->$var)) return $this->$var;
        return NULL;
    }
}
?>