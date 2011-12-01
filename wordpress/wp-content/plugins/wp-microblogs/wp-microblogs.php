<?php
/*
  Plugin Name: WP Microblogs
  Plugin URI: http://beamnote.com/2011/wp-microblogs.html
  Description: 在 WordPress 中显示最新微博
  Version: 0.3.3
  Author: Ray Chow
  Author URI: http://beamnote.com/
*/

/*
Copyright (C) 2011 Ray Chow

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// 垃圾清理间隔最长时间( 单位: 分钟 )
define('WM_MAX_CLEANUP_INTERVAL', 120);

// 定义版本，请不要修改！
define('WM_VERSION', '0.3.3');

$wm_plugin_url = plugins_url(NULL, __FILE__);

include_once( dirname(__FILE__) . '/option.php' );
include_once( dirname(__FILE__) . '/functions.php' );
include_once( dirname(__FILE__) . '/class.widget.php' );

register_activation_hook(__FILE__, 'wm_init');

$wm_option = new MicroblogOption();
add_action('admin_menu', array( $wm_option, 'add_menu'));
add_action('load-widgets.php', 'wm_enqueue_widget_option_script');
add_action('init', 'wm_register_ss');
add_action('template_redirect', 'wm_enqueue_style');
add_action('widgets_init', 'wm_widgets_init');
add_action('wm_update_timeline', 'wm_update_timeline');
add_action('wm_cleanup', 'wm_cleanup');
add_filter('cron_schedules', 'wm_add_schedule_options');
add_shortcode('wm_tweet', 'wm_shortcode_tweet');
add_shortcode('wm_tweets', 'wm_shortcode_tweets');

register_deactivation_hook(__FILE__, 'wm_deactivation');
?>