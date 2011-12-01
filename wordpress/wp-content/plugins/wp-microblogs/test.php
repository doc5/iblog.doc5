<?php
require_once( dirname(__FILE__) . '/../../../wp-admin/admin.php' );
include_once( dirname(__FILE__) . '/class.microblog.php' );
?>
<html>
    <head>
        <title>WP Microblogs 测试</title>
    </head>
    <body>
        <h1>WP Microblogs 测试</h1>
        <p>如果发生不自动更新微博的情况，请运行此测试并将结果反馈给作者，谢谢。</p>
        <p>以下是测试结果，如果为空说明此测试没有发现问题。</p>
        <p>
        <?php
        $fun_arr = array(
            'set_time_limit',
            'array_change_key_case',
            'array_map',
            'array_keys',
            'array_flip',
            'array_merge',
            'array_push',
            'is_scalar',
            'implode',
            'explode',
            'ucwords',
            'is_user_logged_in',
            'current_user_can',
            'json_encode',
            'json_decode',
            'base64_encode',
            'base64_decode',
            'urlencode',
            'urldecode',
            'hash_hmac',
            'curl_close',
            'curl_exec',
            'curl_init',
            'curl_setopt',
            'http_build_query',
//            'openssl_get_privatekey',
//            'openssl_get_publickey',
//            'openssl_sign',
//            'openssl_free_key',
//            'openssl_verify',
            'file_get_contents',
            'strtoupper',
            'microtime',
            'mt_rand',
            'wp_schedule_event',
            'wp_next_scheduled',
            'wp_register_script',
            'wp_enqueue_script',
            'wp_register_style',
            'wp_enqueue_style',
            'wp_clear_scheduled_hook',
            'serialize',
            'md5',
            'vsprintf',
            'wp_parse_args'
        );
        foreach( $fun_arr as $fun ) {
            if ( !function_exists($fun) )
                echo $fun . ' 函数不存在！<br />';
        }
        ?>
        </p>
        <p>---------------------------------------------<br />测试结束</p>
    </body>
</html>