<?php
/*
Plugin Name: Kindeditor For Wordpress
Plugin URI: http://www.panxianhai.com/kindeditor-for-wordpress.html
Description: kindeditor是一款轻量级的在线编辑器。
Version: 1.1.1  
Author: hevin
Author URI: http://www.panxianhai.com/
*/


require_once('kindeditor_class.php');
add_action('personal_options_update', array(&$kindeditor, 'user_personalopts_update'));
add_action('admin_head', array(&$kindeditor, 'add_admin_head'));
add_action('edit_form_advanced', array(&$kindeditor, 'load_kindeditor'));
add_action('edit_page_form', array(&$kindeditor, 'load_kindeditor'));
add_action('simple_edit_form', array(&$kindeditor, 'load_kindeditor'));
add_action('admin_print_styles', array(&$kindeditor, 'add_admin_style'));
add_action('admin_print_scripts', array(&$kindeditor, 'add_admin_js'));
register_activation_hook(basename(dirname(__FILE__)).'/' . basename(__FILE__), array(&$kindeditor, 'activate'));
register_deactivation_hook(basename(dirname(__FILE__)).'/' . basename(__FILE__), array(&$kindeditor, 'deactivate'));