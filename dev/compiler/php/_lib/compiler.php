<?php
defined('K2_COMPILER') or die('DIRECT ACCESS TO THIS FILE DENIED');

/**
 * Script compiler class
 * Generates the K2 script code file
 *
 **/
class K2compiler extends K2parser
{
    /**
     * @var    $name    The name of the compiler
     *
     **/
    protected $name;

    /**
     * @var    $version    The version of the compiler
     *
     **/
    protected $version;

    /**
     * @var    $date    The release date of the compiler
     *
     **/
    protected $date;

    /**
     * @var    $author    The author of the compiler
     *
     **/
    protected $author;

    /**
     * @var    $website    The website of the compiler
     *
     **/
    protected $website;

    /**
     * @var    $core    Holds the names of the core modules as array
     *
     **/
    protected $core;

    /**
     * @var    $templates    Holds a list of template names as array
     *
     **/
    protected $templates;

    /**
     * @var    $hooks    Holds a list of base code hook names as array
     *
     **/
    protected $hooks;

    /**
     * @var    $notrace    Holds a list of method names which should not be traces as array
     *
     **/
    protected $notrace;

    /**
     * @var    $tpl_data    Holds all template content as array
     *
     **/
    private $tpl_data;

    /**
     * @var    $script_head    Holds the script head data as array
     *
     **/
    private $script_head;

    /**
     * @var    $script_body    Holds the script body data as array
     *
     **/
    private $script_body;

    /**
     * @var    $script_hook    Holds the script hook data as array
     *
     **/
    private $script_hook;

    /**
     * @var    $modules    Holds the objects of modules that have been compiled as array
     *
     **/
    private $modules;

    /**
     * @var    $modules    K2 module cfg var "self"
     *
     **/
    private $self;

    /**
     * @var    $output_k2    Contains the final k2 script
     *
     **/
    public $output_k2;

    /**
     * @var    $output_k2    Contains the final k2 script in a triggerlist
     *
     **/
    public $output_trigger;


    /**
     * Constructor
     * Read contents of the compiler.ini
     *
     * @return    object    Returns itself on success, or bool False on error
     **/
    public function __construct()
    {
        parent::__construct();

        $success = $this->parseCompilerINI();
        if(!$success) return false;

        $this->output_k2 = "";
        $this->output_trigger = "";

        return $this;
    }


    /**
     * Initialized the compiler
     * Starts of by reading all template files and compiling the core modules
     *
     * @return    void
     **/
    public function init()
    {
        $this->tpl_data = array();
        foreach($this->templates AS $tpl)
        {
            $data = $this->parseTemplate($tpl);

            $this->tpl_data[$tpl] = $this->parseTemplate($tpl);
        }

        $this->script_hook = array();
        foreach($this->hooks AS $hook)
        {
            $this->script_hook[$hook] = array();
        }

        $this->script_head = array();
        $this->script_body = array();

        $this->modules = array();

        // Compile the K2 core module
        $k2 = new K2module("K2");
        $this->module($k2);

        $cfg = $k2->config['self'];
        $this->self = $cfg->value;

        // Compile core modules
        foreach($this->core AS $module)
        {
            $m = new K2module($module);
            $this->module($m);
        }
    }


    /**
     * Compiles a module into the code base
     *
     * @param     object    $module    The module object to compile
     * @return    void
     **/
    public function module($module)
    {
        // Avoid compiling twice
        if(array_key_exists($module->name, $this->modules)) return true;

        // Check dependencies
        foreach($module->dependencies AS $m)
        {
            if(!in_array($m, $this->module_list)) {
                $this->logError("Unable to satisfy dependency of {$module->name}: {$m}");
                continue;
            }
            else {
                $dep_mod = new K2module($m);
                if($dep_mod) {
                    $this->module($dep_mod);
                }
                else {
                    $this->logError("Failed to load dependency of {$module->name}: {$m}");
                }
            }
        }

        $this->modules[$module->name] = $module;

        $is_k2 = ($module->name == 'K2') ? true : false;

        // Compile config vars
        foreach($module->config AS $cfg)
        {
            $this->config($cfg);
        }

        // Compile other vars
        foreach($module->vars AS $type => $code)
        {
            $this->variables($type, $code);
        }

        // Compile methods

        // Construct
        if(!$is_k2) $this->magicFunction('construct', $module);

        // Exec
        if(!$is_k2) $this->magicFunction('exec', $module);

        // User-defined
        $cont = array('__construct', '__exec', '__destruct');
        foreach($module->methods AS $method)
        {
            if(in_array($method->name, $cont)) continue;

            $trace = (in_array($method->name, $this->notrace)) ? false : true;

            $this->userFunction($module, $method, $trace, $is_k2);
            $this->registerMan('method', $module, $method->name);
        }

        // Destruct
        if(!$is_k2) $this->magicFunction('destruct', $module);
    }


    /**
     * Creates the actual script output
     *
     * @return    void
     **/
    public function finish()
    {
        // Register modules
        $this->registerModules();

        // Generate script header
        $this->head();

        // Generate body
        $this->body();

        $this->formatCode($this->script_body);

        // normal script
        $this->output_k2 = implode("\r\n", $this->script_head)
                         . "\r\n"
                         . implode("\r\n", $this->script_body);

        // trigger list
        $code = str_replace("\r\n", '&#13;&#10;', $this->output_k2);
        $tpl = implode("\r\n", $this->tpl_data['triggerlist']);
        $tpl = implode("\r\n", $this->tpl_data['triggerlist']);
        $this->output_trigger = str_replace('{code}', $code, $tpl);
    }


    /**
     * Removes comments, whitespace and makes sure there's a semi-colon at
     * the end of every line
     *
     * @param    mixed    $code    Can be either array or string
     * @return   void
     **/
    private function formatCode(&$code)
    {
        if(is_array($code)) {
            $tmp = array();

            foreach($code AS $c)
            {
                $c = trim($c);
                $is_comment = (substr($c,0,2) == '//') ? true : false;
                $is_lbl     = (substr($c,0,1) == '@')  ? true : false;
                $has_semi   = (substr($c,-1,1) == ';') ? true : false;

                if($is_comment || $c == "") continue;
                if(!$has_semi && !$is_lbl) $c .= ';';

                $c = str_replace(';;', ';',$c);
                if($c == ';') continue;

                if($is_lbl && (substr($c,-1,1) == ';')) $c = str_replace(';','',$c);

                $tmp[] = $c;
            }
            $code = $tmp;
        }
        else {
            $code = trim($code);
            $is_comment = (substr($code,0,2) == '//') ? true : false;
            $is_lbl     = (substr($code,0,1) == '@')  ? true : false;
            $has_semi   = (substr($code,-1,1) == ';') ? true : false;

            if($is_comment) $code = '';
            if(!$has_semi && !$is_lbl && $code != '') $code .= ';';
            if($code == ';') $code = '';
            $code = str_replace(';;', ';',$code);
        }
    }


    /**
     * Compiles a magic function of a module
     *
     * @param    string    $func      The name of the function, excluding underscores
     * @param    object    $module    The module object from which to compile
     * @return   void
     **/
    private function magicFunction($func, $module)
    {
        $tpl   = implode("\r\n", $this->tpl_data[$func]);
        $code  = '';
        $mod_u = $module->name;
        $mod_l = strtolower($module->name);

        $search  = array('{mod_u}', '{mod_l}');
        $replace = array($mod_u, $mod_l);
        $tpl     = str_replace($search, $replace, $tpl);
        $key     = '__'.$func;
        $file    = K2_PATH_MODULES.DS.$module->folder.DS.'methods'.DS.$key.'.cfg';

        if(file_exists($file)) {
            $mcode = $this->parseMethod($module->name,$key);
            $code  = implode("\r\n", $mcode);
        }
        else {
            if($func == 'exec') {
                $mkeys = array_keys($module->methods);

                foreach($mkeys AS $method)
                {
                    $tpl_code = implode("\r\n", $this->tpl_data['req_method']);
                    $search   = array('{mod_u}', '{mod_l}', '{method}');
                    $replace  = array($mod_u, $mod_l, $method);
                    $code .= str_replace($search, $replace, $tpl_code)."\r\n";
                }
            }

            if($func == 'destruct') {
                $mkeys = array_keys($module->methods);

                foreach($mkeys AS $method)
                {
                    // Unlock method code
                    $code .= 'if #StringLength(|#GetScriptParam('.$mod_u.'.'.$method.')|#)# "#$# __'.$mod_l.'_'.$method.'_lock 0";';
                    $code .= "\r\n";
                }
            }
        }

        $tpl = str_replace('{code}', $code, $tpl);
        $tpl = explode("\r\n", $tpl);

        foreach($tpl AS $c)
        {
            $this->script_hook['mod'][] = trim($c);
        }
    }


    /**
     * Compiles a user defined function of a module
     *
     * @param    object    $module    The module object from which to compile
     * @param    object    $method    The method object to compile
     * @param    bool      $trace     If set to yet, will automatically add tracing code
     * @param    bool      $is_k2     If set to yet, the method will be compiled as part of the K2 module
     * @return   void
     **/
    private function userFunction($module, $method, $trace = false, $is_k2 = false)
    {
        $mod_u = $module->name;
        $mod_l = strtolower($module->name);

        $tpl   = implode("\r\n", $this->tpl_data['method']);
        $code  = $this->parseMethod($module->name, $method->name);
        $code  = implode("\r\n", $code);
        $tcode = '';

        // Locking code
        $lcode = 'if [__'.$mod_l.'_'.$method->name.'_lock == 1] "'
               // . '#<# \"^r'.$mod_u.'::'.$method->name.': Concurrent Call. Method is locked\";'
               //. (($method->name == 'log') ? '' : '#$e# #self# \"'.$mod_u.'::'.$method->name.': Concurrent Call. Method is locked\" -w 1; ')
               //. '#@# \"'.$mod_u.'::__exec_skip_'.$method->name.'{\"";';
               . '#@# \"K2::__exec_skip_'.$mod_u.'.'.$method->name.'{\"";';

        $lcode .= "\r\n #$# __".$mod_l."_".$method->name."_lock 1";

        $code = $lcode."\r\n".$code;
        $this->script_hook['var_priv_dynamic'][] = "#$# __".$mod_l."_".$method->name."_lock 0";

        if($trace) {
            $tvars = '';
            $i = 0;
            foreach($method->options AS $option)
            {
                if($i == 0) {
                    $i++;
                    continue;
                }
                $cred   = ($method->name == 'log') ? '^r' : '^200';
                $cyel   = ($method->name == 'log') ? '^y' : '^440';
                $cpur   = ($method->name == 'log') ? '^607' : '^204';
                $rcolor = ($option->req == 1) ? $cred : $cyel;
                $tvar   = $option->name;

                $tvars .= ' '.$rcolor.$tvar.' '.$cpur.'<"#GetScriptParam('.$tvar.')#">';
                $i++;
            }
            $tpl_name   = ($is_k2) ? 'trace_k2' : 'trace';
            $tpl_name   = ($method->name == 'log') ? 'trace_log' : $tpl_name;
            $tpl_trace  = implode("\r\n", $this->tpl_data[$tpl_name]);
            $search     = array('{mod_u}', '{mod_l}', '{method}', '{tvars}');
            $replace    = array($mod_u, $mod_u, $method->name, $tvars);
            $tcode      = str_replace($search, $replace, $tpl_trace);
        }

        $search  = array('{mod_u}', '{method}', '{code}', '{trace}');
        $replace = array($mod_u, $method->name, $code, $tcode);

        $tpl = str_replace($search, $replace, $tpl);
        $tpl = explode("\r\n", $tpl);

        foreach($tpl AS $c)
        {
            if($is_k2) {
                $this->script_hook['k2'][] = trim($c);
            }
            else {
                $this->script_hook['mod'][] = trim($c);
            }
        }

        // Register K2 method
        if($is_k2) {
            $call = 'if #StringLength(|#GetScriptParam('.$method->name.')|#)# "#@# \"K2::'.$method->name.'{\"";';
            $this->script_hook['reg_method'][] = $call;

            if($method->name != 'log') {
                $skip = '@K2::__exec_skip_'.$method->name.'{';
                $this->script_hook['reg_method'][] = $skip;
            }

            $unlock = 'if #StringLength(|#GetScriptParam('.$method->name.')|#)# "#$# __'.$mod_l.'_'.$method->name.'_lock 0";';
            $this->script_hook['k2_destruct'][] = $unlock;
        }
    }


    /**
     * Compiles a module config setting
     *
     * @param    object    $cfg    The config object to compile
     * @return   void
     **/
    private function config($cfg)
    {
        $code = '#$# '.$cfg->name.' "'.$cfg->value.'"';
        $this->script_hook['var_cfg'][] = $code;
    }


    /**
     * Compiles other module variables
     *
     * @param    string    $hook    The target code base hook
     * @param    array     $code    The variables file code to compile
     * @return   void
     **/
    private function variables($hook, $code)
    {
        foreach($code AS $c)
        {
            $this->script_hook[$hook][] = $c;
        }
    }


    /**
     * Generates a script head which has details about the
     * compiler and the modules that are in the script
     *
     * @return   void
     **/
    private function head()
    {
        $this->script_head[] = 'if [__K2_INI == 1] "#@# \"K2::__exec{\"";';
        $this->script_head[] = '//****';
        $this->script_head[] = '// * Compiled with "'.$this->name.'" on '.date('m-d-Y H:i:s');
        $this->script_head[] = '// *';
        $this->script_head[] = '// * @version '.$this->version;
        $this->script_head[] = '// * @date    '.$this->date;
        $this->script_head[] = '// * @author  '.$this->author;
        $this->script_head[] = '// * @web     '.$this->website;
        $this->script_head[] = '// *';

        foreach($this->modules AS $module)
        {
            $descs = explode('\n', $module->desc);
            $this->script_head[] = '//****';
            $this->script_head[] = '// * '.$module->name;

            foreach($descs AS $d)
            {
                $this->script_head[] = '// * '.html_entity_decode($d);
            }

            $this->script_head[] = '// * ';
            $this->script_head[] = '// * @version '.$module->version;
            $this->script_head[] = '// * @date    '.$module->date;
            $this->script_head[] = '// * @author  '.$module->author;
            $this->script_head[] = '// * @web     '.$module->website;
            $this->script_head[] = '// * ';
        }
        $this->script_head[] = '//****';
    }


    /**
     * Replaces all hook placeholders in the code base with the actual code
     *
     * @return   void
     **/
    private function body()
    {
        $tpl = $this->tpl_data['base'];
        $this->formatCode($tpl);
        $tpl = implode("\r\n", $tpl);

        // Insert data
        foreach($this->script_hook AS $hook => $data)
        {
            $data = implode("\r\n", $data);
            $tpl = str_replace('{'.$hook.'}', $data, $tpl);
        }

        $tpl = explode("\r\n", $tpl);

        foreach($tpl AS $c)
        {
            $this->script_body[] = trim($c);
        }
    }


    /**
     * Registers modules and their methods in the core
     *
     * @return   void
     **/
    private function registerModules()
    {
        $module_list = array_keys($this->modules);
        $this->script_hook['var_priv_dynamic'][] = '#$# __k2modules "'.implode('&',$module_list).'";';

        foreach($this->modules AS $module)
        {
            $method_list = array_keys($module->methods);
            $this->script_hook['var_priv_dynamic'][] = '#$# __k2_'.$module->name.'_methods "'.implode(',', $method_list).'";';
            $this->registerMan('module', $module);

            if($module->name != 'K2') {
                foreach($method_list AS $method)
                {
                    $call = 'if #StringLength(|#GetScriptParam('.$module->name.'.'.$method.')|#)# "#@# \"'.$module->name.'::__construct{\"";';
                    $this->script_hook['reg_mod'][] = $call;
                    $skip = '@K2::__exec_skip_'.$module->name.'.'.$method.'{';
                    $this->script_hook['reg_mod'][] = $skip;
                }
            }
        }
    }


    /**
     * Generates a man page
     *
     * @param    string    $type      Can be either module or method
     * @param    object    $module    The module from which to generate the page
     * @param    string    $method    The method name for which to generate the page
     *
     * @return   void
     **/
    private function registerMan($type, $module, $method = NULL)
    {
        $code   = array();
        $search = array('&quot;',',',';','(',')');
        $replace = array('"'    ,'' ,'' ,'' ,'');

        if($type == 'module') {
            $code[] = '^w'.$module->name.' '
                    . '^555Version: ^w'.$module->version
                    . '^555 | Release date: ^w'.$module->date
                    . '^555 | Author: ^w'.$module->author;

            if($module->website) $code[] = '^555Web: ^w'.$module->website;
            $code[] = '';

            $desc = str_replace($search, $replace, htmlspecialchars_decode($module->desc));
            $desc = explode('\n', $module->desc);

            foreach($desc AS $l)
            {
                $code[] = $l;
            }

            if(count($module->config)) {
                $code[] = '';
                $code[] = '^wAvailable Config Vars:';

                $max_n = $this->getMaxPropLen($module->config,'name');
                $max_d = $this->getMaxPropLen($module->config,'desc');

                foreach($module->config AS $cfg)
                {
                    $desc_pad = ($max_n + 4);
                    $cfg_data = '^607'.str_pad($cfg->name, $max_n).'    ';

                    $desc = str_replace($search, $replace, htmlspecialchars_decode($cfg->desc));
                    $desc = explode('\n', $desc);
                    foreach($desc AS $i => $l)
                    {
                        if($i == 0) {
                            $cfg_data .= '^555'.$l;
                        }
                        else {
                            $d = str_replace('&quot;', '"', $l);
                            $cfg_data .= '&^555'.str_pad($d,(strlen($d)+$desc_pad), ' ', STR_PAD_LEFT);
                        }
                    }

                    $code[] = $cfg_data;
                }
                $code[] = '';
                $code[] = 'To change a variable type: ^500Set ^w<variable> ^607<value>';
            }

            if(count($module->methods)) {
                $code[] = '';
                $code[] = '^wAvailable Methods:';

                foreach($module->methods AS $method)
                {
                    $code[] = '^607'.$method->name;
                }
                $code[] = '';

                if($module->name == 'K2') {
                    $code[] = 'For additional help type: ^500ExecScript ^w'.$this->self.' ^rman ^607<method>';
                }
                else {
                    $code[] = 'For additional help type: ^500ExecScript ^w'.$this->self.' ^rman ^607'.$module->name.'.<method>';
                }

                $code[] = '';
                $code[] = '^wUsage:';

                if($module->name == 'K2') {
                    $code[] = '^500ExecScript ^w'.$this->self.' ^r<method or module> ^607<options>';
                }
                else {
                    $code[] = '^500ExecScript ^w'.$this->self.' ^r'.$module->name.'.<method> ^607<options>';
                }
            }

            $code = implode('&',$code);
            $this->script_hook['var_man'][] = '#$# __man_'.$module->name.' "'.addslashes($code).'"';
        }

        if($type == 'method') {
            $m = $module->methods[$method];
            $code[] = '^w'.$module->name.'::'.$m->name;

            $desc = str_replace($search, $replace, htmlspecialchars_decode($m->desc));
            $desc = explode('\n', $desc);
            foreach($desc AS $l)
            {
                $code[] = $l;
            }

            $options = '';

            if(count($m->options)) {
                $code[] = '';
                $code[] = '^wOptions:';

                $max_n = $this->getMaxPropLen($m->options,'name');
                $max_d = $this->getMaxPropLen($m->options,'desc');
                $max_t = $this->getMaxPropLen($m->options,'type');

                foreach($m->options AS $option)
                {
                    $desc_pad = ($max_n + $max_t + 8);
                    $descs = str_replace($search, $replace, htmlspecialchars_decode($option->desc));
                    $descs = explode('\n', $descs);
                    $desc  = array();
                    foreach($descs AS $i => $l)
                    {
                        if($i == 0) {
                            $desc[] = '^555'.$l;
                        }
                        else {
                            $d = $l;
                            $desc[] = '^555'.str_pad($d, (strlen($d)+$desc_pad), " ", STR_PAD_LEFT);
                        }
                    }

                    $rcolor = ($option->req == 1) ? "^r" : "^y";
                    $code[] = $rcolor.str_pad($option->name, $max_n).'^607'
                            . '    '.str_pad($option->type, $max_t)
                            . '    '.implode("&", $desc);

                    $options .= ' '.$rcolor.$option->name.' ^607<'.$option->type.'>';
                }

                $code[] = '';
                $code[] = '^wLegend:';
                $code[] = 'Options in ^rred ^555are required and must be provided.';
                $code[] = 'Options in ^yyellow ^555are optional and are not required.';
            }

            $code[] = '';
            $code[] = '^wUsage:';
            $code[] = '^500ExecScript ^wK2'.$options;

            $code = implode('&',$code);
            $this->script_hook['var_man'][] = '#$# __man_'.$module->name.'.'.$method.' "'.addslashes($code).'"';
            if($module->name == 'K2') {
                $this->script_hook['var_man'][] = '#$# __man_'.$method.' #__man_'.$module->name.'.'.$method.'#';
            }
        }
    }
}
?>