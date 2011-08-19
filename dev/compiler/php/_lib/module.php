<?php
defined('K2_COMPILER') or die('DIRECT ACCESS TO THIS FILE DENIED');

/**
 * K2 module object
 * This class merely generates an object from each module.ini file
 * It is then passed on to the compiler for processing
 *
 **/
class K2module extends K2parser
{
    /**
     * @var    $folder    The folder name in which the module resides
     *
     **/
    public $folder;

    /**
     * @var    $name    The name of the module
     *
     **/
    public $name;

    /**
     * @var    $desc    The description of the module
     *
     **/
    public $desc;

    /**
     * @var    $author    The author of the module
     *
     **/
    public $author;

    /**
     * @var    $version    The version of the module
     *
     **/
    public $version;

    /**
     * @var    $website    The website of the module
     *
     **/
    public $website;

    /**
     * @var    $dependencies    List of modules on which this module relies on
     *
     **/
    public $dependencies;

    /**
     * @var    $methods    List of methods, including options
     *
     **/
    public $methods;

    /**
     * @var    $config    List of config options for this module
     *
     **/
    public $config;

    /**
     * @var    $vars    All the code as assoc array, read from the /vars folder
     *
     **/
    public $vars;


    /**
     * Constructor
     * Parses the module.ini and other files, then returns itself
     *
     * @param     string     $folder    The folder in which to look for the module data
     * @return    object     $this     Returns itself on success or bool False otherwise
     **/
    public function __construct($folder)
    {
        parent::__construct();

        $this->folder = $folder;

        $success = $this->parseModuleINI();
        if(!$success) return false;

        $this->vars = $this->parseVars($this->folder);

        return $this;
    }
}
?>