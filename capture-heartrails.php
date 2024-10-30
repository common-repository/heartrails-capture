<?php
/*
Plugin Name: HeartRails Capture
Plugin URI: http://pronama.jp/?heartrails-capture
Description: Add website screenshot using HeartRails Capture API. Use [capture url="http://example.com"] shortcode.
Version: 1.8
Author: jz5
Author URI: http://pronama.jp/
License: GPLv2 or later
*/
/*
Copyright 2013 jz5

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


function capture_heartrails_get_options() {
    $defalut_template = <<<EOF
<div>
<a href="{url}"><img src="{src}" /></a>
<p><a href="{url}">{title}</a></p>
</div>
EOF;

    $default = array(
        'template' => $defalut_template,
        'option' => ''
    );

    return get_option('capture_heartrails_plugin', $default);
}

function capture_heartrails_get_page_title($url) {
    $html = file_get_contents($url);
    $html = mb_convert_encoding($html, mb_internal_encoding(), 'JIS, eucjp-win, sjis-win, UTF-8');
    if ( preg_match("/<title>(.*?)<\/title>/is", $html, $matches)) {
        return str_replace(array("\r\n","\r","\n"), '', $matches[1]);
    } else {
        return '';
    }
}

// [capture url="http://pronama.jp"]
add_shortcode('capture', 'capture_heartrails_func');
function capture_heartrails_func($atts) {

    extract(shortcode_atts(array(
        'url' => 'http://pronama.jp/',
        'title' => null,
        'option' => ''
    ), $atts));


    $option = capture_heartrails_get_options();

    if ($title === null && strpos($option['template'], '{title}') !== FALSE) {
        $title = htmlspecialchars(capture_heartrails_get_page_title($url), ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/&amp;(.+?);/', '&$1;', $title);
    }

    $src = "http://capture.heartrails.com/{$option['option']}?{$url}";

    $result = str_replace('{url}', $url, $option['template']);
    $result = str_replace('{title}', $title, $result);
    $result = str_replace('{src}', $src, $result);

    return $result;
}

// Plugin option menu
add_action('admin_menu', 'capture_heartrails_plugin_menu');
function capture_heartrails_plugin_menu() {
    add_options_page(
        'HeartRails Capture Options',
        'HeartRails Capture',
        'administrator',
        'capture-heartrails-admin-menu',
        'capture_heartrails_plugin_options'
    );
}

function capture_heartrails_plugin_options() {
    $option = capture_heartrails_get_options();

    $updated = false;
    if (isset($_POST['template'])) {
        $option['template'] = stripslashes_deep($_POST['template']);
        $updated = TRUE;
    }
    if (isset($_POST['option'])) {
        $option['option'] = stripslashes_deep($_POST['option']);
        $updated = TRUE;
    }

    if ($updated) {
        update_option('capture_heartrails_plugin', $option);
    }
?>
<div>
    <h2>HeartRails Capture Options</h2>
    <form name="form1" method="post" action="">
        <h3><label for="option">Default parameter:</label></h3>
        <p>http://capture.heartrails.com/<input type="text" name="option" value="<?php echo (empty($option['option'])) ? '' : add_magic_quotes($option['option']); ?>" size="10" />?http://example.com</p>
        <p>HeartRails Capture API detail: <a href="http://capture.heartrails.com/help/use_api" target="_blank">http://capture.heartrails.com/help/use_api</a></p>

        <h3><label for="template">Template:</label></h3>
        <p><textarea name="template" cols="40" rows="5"><?php echo (empty($option['template'])) ? '' : add_magic_quotes($option['template']); ?></textarea></p>

        <p>Template example:<br />
            &lt;div&gt;<br />&lt;a href="{url}"&gt;&lt;img src="{src}" /&gt;&lt;/a&gt;<br />&lt;p&gt;&lt;a href="{url}"&gt;{title}&lt;/a&gt;&lt;/p&gt;<br />&lt;/div&gt;
        </p>
        <p><input type="submit" name="Submit" value="Update" /></p>
    </form>
    <hr />
    <h2>Shortcode Usage</h2>
    <ul>
        <li>[capture url="http://example.com"]</li>
        <li>[capture url="http://example.com" title="Example"]</li>
        <li>[capture url="http://example.com" title="Example" option="400x300"]</li>
    </ul>
</div>
<?php
}

// Uninstall
if (function_exists('register_uninstall_hook')) {
    register_uninstall_hook( __FILE__, 'capture_heartrails_uninstall_hook');
}
function capture_heartrails_uninstall_hook() {
    delete_option('capture_heartrails_plugin');
}

// Update
register_activation_hook( __FILE__, 'capture_heartrails_register_activation_hook');
function capture_heartrails_register_activation_hook() {

    $default = capture_heartrails_get_options();
    $installed = FALSE;

    $template = get_option('capture_heartrails_plugin_template');
    if ($template !== FALSE) {
        $default['template'] = $template;
        $installed = TRUE;
        delete_option('capture_heartrails_plugin_template');
    }

    $option = get_option('capture_heartrails_plugin_option');
    if ($template !== FALSE) {
        $default['option'] = $option;
        $installed = TRUE;
        delete_option('capture_heartrails_plugin_option');
    }

    if ($installed) {
        update_option('capture_heartrails_plugin', $default);
    }
}

function capture_heartrails_insert_post_data($data) {
	if (false === has_shortcode($data['post_content'], 'capture')) {
		return $data;
	}
	
	$pattern = get_shortcode_regex();
	$data['post_content'] = preg_replace_callback('/'. $pattern .'/s', function ($matches) {
			
		if ($matches[2] !== 'capture') {
			return $matches[0];
		}
		
		if ($matches[1] == '[' && $matches[6] == ']') {
			return $matches[0];
		}

		$atts = shortcode_parse_atts(stripslashes($matches[3]));

		if ($atts['title'] !== null) {
			return $matches[0];
		}
		
		$url = $atts['url'];
		$title = capture_heartrails_get_page_title($url);
		$op = $atts['option'];

		return "[capture url=\"$url\" title=\"$title\"" . (($op) ? " option=\"$op\"]" : "]");
		
	}, $data['post_content']);

	return $data;
}
add_filter('wp_insert_post_data', 'capture_heartrails_insert_post_data');


?>