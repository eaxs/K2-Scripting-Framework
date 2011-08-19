<?php
// Maximum error reporting
error_reporting(E_ALL);

// Set as initalized
if(!defined('K2_COMPILER')) define('K2_COMPILER', 1);

// Define paths
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

if(!defined('K2_PATH'))         define('K2_PATH',         dirname(__FILE__));
if(!defined('K2_PATH_LIB'))     define('K2_PATH_LIB',     K2_PATH.DS.'_lib');
if(!defined('K2_PATH_TPL'))     define('K2_PATH_TPL',     K2_PATH.DS.'_tpl');
if(!defined('K2_PATH_HTML'))    define('K2_PATH_HTML',    K2_PATH.DS.'_html');
if(!defined('K2_PATH_BASE'))    define('K2_PATH_BASE',    K2_PATH.DS.'base');
if(!defined('K2_PATH_MODULES')) define('K2_PATH_MODULES', K2_PATH.DS.'modules');

// Check file presence
$files = array(
    K2_PATH.DS.'welcome.php',
    K2_PATH.DS.'compile.php',
    K2_PATH.DS.'compiler.ini',
    K2_PATH_HTML.DS.'code.html',
    K2_PATH_HTML.DS.'error.html',
    K2_PATH_HTML.DS.'index.html',
    K2_PATH_HTML.DS.'modules.html',
    K2_PATH_HTML.DS.'welcome.html',
    K2_PATH_HTML.DS.'welcome2.html',
    K2_PATH_LIB.DS.'base.php',
    K2_PATH_LIB.DS.'compiler.php',
    K2_PATH_LIB.DS.'module.php',
    K2_PATH_LIB.DS.'parser.php',
    K2_PATH_TPL.DS.'base.cfg',
    K2_PATH_TPL.DS.'call_mod.cfg',
    K2_PATH_TPL.DS.'construct.cfg',
    K2_PATH_TPL.DS.'destruct.cfg',
    K2_PATH_TPL.DS.'exec.cfg',
    K2_PATH_TPL.DS.'method.cfg',
    K2_PATH_TPL.DS.'req_method.cfg',
    K2_PATH_TPL.DS.'req_mod.cfg',
    K2_PATH_TPL.DS.'trace.cfg',
    K2_PATH_TPL.DS.'triggerlist.cfg'
);

foreach($files AS $file)
{
    if(!file_exists($file)) die('Error - Missing file: '.$file);
}

// Get user input
$input = array();
$input['action'] = (isset($_POST['action'])) ? $_POST['action'] : NULL;

// Include libs
require_once(K2_PATH_LIB.DS.'base.php');
require_once(K2_PATH_LIB.DS.'parser.php');
require_once(K2_PATH_LIB.DS.'compiler.php');
require_once(K2_PATH_LIB.DS.'module.php');

if(is_null($input['action'])) {
    // Show start screen
    require_once(K2_PATH.DS.'welcome.php');
}
else {
    $input['modules'] = (isset($_POST['modules'])) ? (array) $_POST['modules'] : array();
    // Compile script
    require_once(K2_PATH.DS.'compile.php');
}
?>