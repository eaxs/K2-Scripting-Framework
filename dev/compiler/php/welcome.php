<?php
defined('K2_COMPILER') or die('DIRECT ACCESS TO THIS FILE DENIED');

$errors   = array();
$parser   = new K2parser();
$compiler = new K2compiler();

// Get list of available modules
$mod_list  = $parser->relsort($parser->getModuleList(), $compiler->get('core'));
$core_list = $compiler->get('core');

// Read base html template
$html = file_get_contents(K2_PATH_HTML.DS.'index.html');

// Prepare Welcome message
$html_welcome = file_get_contents(K2_PATH_HTML.DS.'welcome.html');

// Prepare module list
$html_modules = file_get_contents(K2_PATH_HTML.DS.'modules.html');
$m_list = '';
$c_list = '';
foreach($mod_list AS $i => $folder)
{
    $module = new K2module($folder);
    $mhtml  = '';

    if(!$module) continue;

    $desc = str_replace('\n', "\n", $module->desc);
    $desc = html_entity_decode($desc);


    if(in_array($module->name, $core_list)) {
        $cb = '<label class="core-mod" for="mod_'.$i.'">This is a core module and is automatically added
            <input type="checkbox" id="mod_'.$i.'" name="modules[]" value="'.$module->name.'" style="display:none"/>
        </label>';

    }
    else {
        $cb = '<label for="mod_'.$i.'">Add this module
            <input type="checkbox" id="mod_'.$i.'" name="modules[]" value="'.$module->name.'"/>
        </label>';
    }

    $mhtml .= '
    <div class="module-item">
        <div class="title"><div class="module-name">'.$module->name.'</div>
            <div class="opt">
                '.$cb.'
            </div>
            <div class="clr"></div>
        </div>
        <div class="clr"></div>
        <div class="author">
            Written by <span class="author-name">'.$module->author.'</span>
            &nbsp;|&nbsp;
            Version '.$module->version.'
            &nbsp;|&nbsp;
            Last updated on <span class="updated">'.$module->date.'</span>
        </div>
        <div class="clr"></div>
        <div class="module-desc">'.nl2br($desc).'</div>
        <div class="clr"></div>
    </div><div class="clr"></div>';

    if(in_array($module->name, $core_list)) {
        $c_list .= $mhtml;
    }
    else {
        $m_list .= $mhtml;
    }
}
$m_list = $c_list.$m_list;
$html_modules = str_replace('{modules}', $m_list, $html_modules);


// Prepare error list (if any)
$errors = array_merge($errors, $parser->getError());
$html_error = '';
if(count($errors)) {
    $html_error = file_get_contents(K2_PATH_HTML.DS.'error.html');

    $e_list = '<ul>';
    foreach($errors AS $msg)
    {
        $e_list .= '<li>'.htmlspecialchars($msg).'</li>';
    }
    $e_list .= '</ul>';

    $html_error = str_replace('{list}', $e_list, $html_error);
}


// Replace with html content
$search  = array('{error}', '{welcome}', '{modules}', '{code}');
$replace = array($html_error, $html_welcome, $html_modules, '');
$html    = str_replace($search, $replace, $html);

// Send to browser
echo $html;
?>