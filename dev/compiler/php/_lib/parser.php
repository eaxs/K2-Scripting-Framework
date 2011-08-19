<?php
defined('K2_COMPILER') or die('DIRECT ACCESS TO THIS FILE DENIED');

/**
 * Parser class
 * Reads data from files
 *
 **/
class K2parser extends K2base
{
    /**
     * @var    $module_list    Array of folder names in the module dir
     *
     **/
    protected $module_list;

    /**
     * @var    $ini    The contents of a module ini file as assoc array
     *
     **/
    private $ini;


    /**
     * Constructor
     *
     **/
    public function __construct()
    {
        parent::__construct();

        $this->module_list = array();
        $this->ini = NULL;
    }


    /**
     * Reads the module dir and finds all module folders
     *
     * @return    array    $module_list    The array containing the folder names
     **/
    public function getModuleList()
    {
        if(count($this->module_list)) return $this->module_list;

        if(!is_dir(K2_PATH_MODULES)) return $this->logError('Directory not found: '.K2_PATH_MODULES);

        $h = opendir(K2_PATH_MODULES.DS);
        if($h === false) return $this->logError('Unable to read from: '.K2_PATH_MODULES);

        // Get package names
        while(false !== ($module = readdir($h)))
        {
            if($module == "." || $module == "..") continue;

            $path = K2_PATH_MODULES.DS.$module;
            $ini  = K2_PATH_MODULES.DS.$module.DS.'module.ini';

            if(!is_dir($path)) continue;

            if(!file_exists($ini)) {
                $this->logError('Missing ini file for module: '.$module);
                continue;
            }

            $this->module_list[] = $module;
        }
        closedir($h);

        return $this->module_list;
    }


    /**
     * Orders an array by the values of another array
     *
     * @param    array    $array     The array to sort
     * @param    array    $array2    The array to sort by
     *
     * @return   array    The sorted array
     **/
    public function relsort($array, $array2)
    {
        $ordered = array();

        $flip = array_flip($array);

        foreach($array2 as $k => $v)
        {
            if(array_key_exists($v, $flip)) {
                $k2 = $flip[$v];
                $ordered[] = $array[$k2];
                unset($array[$k2]);
            }
        }

        return array_merge($ordered, $array);
    }



    /**
     * Reads a module ini file and sets the K2module variables
     *
     * @return    bool    True on success, otherwise False
     **/
    protected function parseModuleINI()
    {
        $file = K2_PATH_MODULES.DS.$this->folder.DS.'module.ini';

        $this->ini = parse_ini_file($file, true);

        if(!$this->ini) return $this->logError('Failed to parse: '.$file);

        if(!isset($this->ini['module'])) return $this->logError('Missing "module" section in : '.$file);
        if(!isset($this->ini['module']['name'])) return $this->logError('Missing module name in : '.$file);

        // Parse module section
        $this->name = htmlspecialchars(trim($this->ini['module']['name']));
        $attribs = array(
            'desc'    => 'No description available',
            'author'  => 'Unknown',
            'version' => '0',
            'website' => 'None',
            'date'    => 'Unknown'
        );

        foreach($attribs AS $attrib => $def)
        {
            $this->$attrib = (isset($this->ini['module'][$attrib])) ? htmlspecialchars(trim($this->ini['module'][$attrib])) : $def;
        }

        // Parse dependencies section
        $this->dependencies = array();
        if(isset($this->ini['dependencies']['module'])) {
            if(is_array($this->ini['dependencies']['module'])) {
                foreach($this->ini['dependencies']['module'] AS $mod)
                {
                    $mod = trim($mod);
                    if($mod == '') continue;

                    $this->dependencies[] = htmlspecialchars($mod);
                }
            }
        }

        // Parse methods
        $this->methods = array();
        if(isset($this->ini['methods']['name']) && isset($this->ini['methods']['desc'])) {

            if(is_array($this->ini['methods']['name']) && is_array($this->ini['methods']['desc'])) {

                $descs = $this->ini['methods']['desc'];
                $names = $this->ini['methods']['name'];

                foreach($names AS $i => $name)
                {
                    // Check method name
                    $name = trim($name);
                    if(!$name) continue;

                    // Check method options
                    $section = 'opt:'.$name;
                    if(!isset($this->ini[$section]['name'])) {
                        $this->logError('Missing "'.$name.'" Options of module: '.$this->name);
                        continue;
                    }
                    if(!is_array($this->ini[$section]['name'])) {
                        $this->logError('Missing "'.$name.'" Options of module: '.$this->name);
                        continue;
                    }

                    // Check method file
                    $file = K2_PATH_MODULES.DS.$this->folder.DS.'methods'.DS.$name.'.cfg';
                    if(!file_exists($file)) {
                        $this->logError('Missing "'.$name.'" cfg file of module: '.$this->name);
                        continue;
                    }

                    $method = new stdClass();

                    $desc = (array_key_exists($i, $descs)) ? trim($descs[$i]) : 'No description available';
                    if($desc == '') $desc = 'No description available';


                    // Get all method options
                    $method->options = array();
                    $opt_names = $this->ini[$section]['name'];
                    $opt_descs = (isset($this->ini[$section]['desc'])) ? (array) $this->ini[$section]['desc'] : array();
                    $opt_types = (isset($this->ini[$section]['type'])) ? (array) $this->ini[$section]['type'] : array();
                    $opt_reqs  = (isset($this->ini[$section]['req']))  ? (array) $this->ini[$section]['req']  : array();

                    foreach($opt_names AS $i2 => $oname)
                    {
                        $oname = trim($oname);
                        if(!$oname) continue;

                        $odesc = (array_key_exists($i2, $opt_descs)) ? trim($opt_descs[$i2]) : 'No description available';
                        if($odesc == '') $odesc = 'No description available';

                        $otype = (array_key_exists($i2, $opt_types)) ? trim($opt_types[$i2]) : 'string';
                        if($otype == '') $otype = 'string';

                        $oreq = (array_key_exists($i2, $opt_reqs)) ? intval($opt_reqs[$i2]) : 0;
                        if($oname == $name) $oreq = 1;

                        $method->options[$oname] = new stdClass();
                        $method->options[$oname]->name = htmlspecialchars($oname);
                        $method->options[$oname]->desc = htmlspecialchars($odesc);
                        $method->options[$oname]->type = htmlspecialchars($otype);
                        $method->options[$oname]->req  = $oreq;
                    }

                    $method->name = htmlspecialchars($name);
                    $method->desc = htmlspecialchars($desc);

                    $this->methods[$name] = $method;
                }
            }
        }

        // Parse config settings
        $this->config = array();
        if(isset($this->ini['config']['name'])) {
            $cfg_names = (array) $this->ini['config']['name'];
            $cfg_descs = (isset($this->ini['config']['desc'])) ?  (array) $this->ini['config']['desc'] : array();
            $cfg_vals  = (isset($this->ini['config']['value'])) ? (array) $this->ini['config']['value'] : array();

            foreach($cfg_names AS $i => $name)
            {
                $name = trim($name);
                if(!$name) continue;

                $desc = (array_key_exists($i, $cfg_descs)) ? trim($cfg_descs[$i]) : 'No description available';
                if($desc == '') $desc = 'No description available';

                $val = (array_key_exists($i, $cfg_vals)) ? $cfg_vals[$i] : '';

                $this->config[$name] = new stdClass();
                $this->config[$name]->name  = htmlspecialchars($name);
                $this->config[$name]->desc  = htmlspecialchars($desc);
                $this->config[$name]->value = htmlspecialchars($val);
            }
        }

        return true;
    }


    /**
     * Reads a compiler ini file and sets the K2compiler variables
     *
     * @return    bool    True on success, otherwise False
     **/
    protected function parseCompilerINI()
    {
        $file = K2_PATH.DS.'compiler.ini';
        if(!file_exists($file)) return $this->logError("Missing file: ".$file);

        $this->ini = parse_ini_file($file);
        if(!$this->ini) return $this->logError('Failed to parse: '.$file);

        $attribs = array('name', 'version', 'date', 'author', 'website', 'core',
        'templates', 'hooks', 'notrace');

        $s = true;
        foreach($attribs AS $attrib)
        {
            if(!isset($this->ini[$attrib])) {
                $s = $this->logError("Missing \"$attrib\" in file: ".$file);
                continue;
            }

            $this->$attrib = $this->ini[$attrib];
        }

        $tmp  = explode(',', $this->core);
        $core = array();
        foreach($tmp AS $module)
        {
            $core[] = trim($module);
        }

        $this->core = $core;


        $tmp = explode(',', $this->templates);
        $templates = array();
        foreach($tmp AS $tpl)
        {
            $templates[] = trim($tpl);
        }

        $this->templates = $templates;

        $tmp  = explode(',', $this->hooks);
        $hooks = array();
        foreach($tmp AS $hook)
        {
            $hooks[] = trim($hook);
        }

        $this->hooks = $hooks;

        $tmp  = explode(',', $this->notrace);
        $notrace = array();
        foreach($tmp AS $ntrace)
        {
            $notrace[] = trim($ntrace);
        }

        $this->notrace = $notrace;

        return $s;
    }


    /**
     * Reads a template file into an array and returns it
     *
     * @param     string    $name    The name of the template file
     * @return    array              The data as array
     **/
    protected function parseTemplate($name)
    {
        $file = K2_PATH_TPL.DS.$name.'.cfg';
        if(!file_exists($file)) return $this->logError("Missing file: ".$file);

        $tmpdata = file($file, FILE_SKIP_EMPTY_LINES);
        $data    = array();

        foreach($tmpdata AS $d)
        {
            $data[] = trim($d);
        }

        return $data;
    }


    /**
     * Reads all cfg files from a module /vars folder and returns the content
     *
     * @param     string    $module    The module folder name
     * @return    array                The data as assoc array
     **/
    protected function parseVars($module)
    {
        $dir = K2_PATH_MODULES.DS.$module.DS.'vars';
        if(!is_dir($dir)) return $this->logError('Directory not found:'.$dir, array());

        $h = opendir($dir);
        if($h === false) return $this->logError('Unable to open dir: '.$dir, array());

        // Get file list
        $vars = array();
        while(false !== ($file = readdir($h)))
        {
            if($file == "." || $file == "..") continue;
            if(substr($file, -3) != 'cfg') continue;

            $path = K2_PATH_MODULES.DS.$module.DS.'vars';
            $cfg  = $path.DS.$file;

            $tmpdata = file($cfg, FILE_SKIP_EMPTY_LINES);
            $data    = array();

            foreach($tmpdata AS $d)
            {
                $data[] = trim($d);
            }

            $key = 'var_'.substr($file, 0, -4);
            $vars[$key] = $data;
        }
        closedir($h);

        return $vars;
    }


    /**
     * Reads a method cfg file and returns its content
     *
     * @param     string    $module    The module folder name
     * @param     string    $method    The method name
     * @return    array                The data as assoc array
     **/
    protected function parseMethod($module, $method)
    {
        $path = K2_PATH_MODULES.DS.$module.DS.'methods'.DS.$method.'.cfg';
        if(!file_exists($path)) return $this->logError('File not found:'.$path, array());

        $tmpdata = file($path, FILE_SKIP_EMPTY_LINES);
        $data    = array();

        foreach($tmpdata AS $d)
        {
            $data[] = trim($d);
        }

        return $data;
    }


    /**
     * Finds the longest object property and returns its length
     *
     * @param     array     $arr     The target array
     * @param     string    $prop    The name of the object property
     * @return    int       $max     The max length
     **/
    protected function getMaxPropLen($arr, $prop)
    {
        $max = 0;
        foreach($arr AS $obj)
        {
            $len = strlen($obj->$prop);
            $max = ($len > $max) ? $len : $max;
        }
        return $max;
    }
}
?>