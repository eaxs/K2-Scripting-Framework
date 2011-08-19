<?php
defined('K2_COMPILER') or die('DIRECT ACCESS TO THIS FILE DENIED');

$parser   = new K2parser();
$compile  = new K2compiler();
$mod_list = $parser->getModuleList();

// Compile modules
$compile->init();
foreach($input['modules'] AS $m)
{
    $m = new K2module($m);
    if(!$m) continue;

    $compile->module($m);
}
$compile->finish();

// Read base html template
$html = file_get_contents(K2_PATH_HTML.DS.'index.html');

// Prepare info message
$html_welcome = file_get_contents(K2_PATH_HTML.DS.'welcome2.html');

// Prepare error list (if any)
$errors = $compile->getError();
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

// Empty modules html
$html_modules = '';


// Prepare code output
$html_code = file_get_contents(K2_PATH_HTML.DS.'code.html');
$code1 = $compile->output_k2;
$code2 = $compile->output_trigger;
$html_code = str_replace('{code1}', htmlspecialchars($code1), $html_code);
$html_code = str_replace('{code2}', htmlspecialchars($code2), $html_code);


// Replace with html content
$search  = array('{error}', '{welcome}', '{modules}', '{code}');
$replace = array($html_error, $html_welcome, $html_modules, $html_code);
$html    = str_replace($search, $replace, $html);

// Send to browser
echo $html;
?>