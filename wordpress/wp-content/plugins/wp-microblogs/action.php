<?php

if (!isset($_POST['action']))
    wm_exit();

require_once( dirname(__FILE__) . '/../../../wp-load.php' );
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wm_exit(401);
}

if ($_POST['action'] != 1) {
    $mid = (int)$_POST['mid'];
    if (!is_numeric($_POST['mid']) || $mid < 1)
        wm_exit(400, '无效参数');
}

require_once( dirname(__FILE__) . '/functions.php' );
require_once( dirname(__FILE__) . '/class.microblog.php' );

global $table_prefix, $wpdb;

switch ($_POST['action']) {
    case 1:
        if (!isset($_POST['type'], $_POST['authtype'], $_POST['token']))
            wm_exit(400, '无效参数');
        echo json_encode(wm_add_or_modify(1, $_POST['type'], $_POST['authtype'], $_POST['token'], isset($_POST['secret']) ? $_POST['secret'] : NULL, isset($_POST['request_token']) ? $_POST['request_token'] : NULL, isset($_POST['request_token_secret']) ? $_POST['request_token_secret'] : NULL ));
        break;
    case 2 :
        MicroblogOption::removeAccount($mid);
        $wpdb->query("DELETE FROM {$table_prefix}wm_timeline WHERE mid = $mid");
        wm_delete_cache($mid);
        $echo = array(
            'mid' => $mid
        );
        echo json_encode($echo);
        break;
    case 3 :
        if (!isset($_POST['authtype'], $_POST['token']))
            wm_exit(400, '无效参数');
        $type = MicroblogOption::getAccountVar($mid, 'type');
        echo json_encode(wm_add_or_modify(3, $type, $_POST['authtype'], $_POST['token'], isset($_POST['secret']) ? $_POST['secret'] : NULL, isset($_POST['request_token']) ? $_POST['request_token'] : NULL, isset($_POST['request_token_secret']) ? $_POST['request_token_secret'] : NULL, $mid));
        break;
    case 4 :
        MicroblogOption::updateAccount($mid, array( 'suspend' => -1, 'lastid' => NULL ) );
        $normal = MicroblogOption::getNormalAccountList();
        $wpdb->query("DELETE FROM {$table_prefix}wm_timeline" . $normal ? " WHERE mid NOT IN (" . implode(', ', MicroblogOption::getNormalAccountList()) . ")" : "");
        wm_delete_cache($mid);
        $suspend = MicroblogOption::getAccountVar($mid, 'suspend');
        if ($suspend)
            wm_reset_timeline();
        $echo = array(
            'mid' => $mid,
            'suspend' => $suspend
        );
        echo json_encode($echo);
        break;
    case 5 :
        $row = MicroblogOption::getAccount($mid);
        $access_token = array(
            'oauth_token' => $row['token'],
            'oauth_token_secret' => $row['secret']
        );
        $info = wm_get_info($row['type'], $row['authtype'], $access_token, $row['uid']);
        MicroblogOption::updateAccount($mid, array(
            'uid' => $info['uid'],
            'name' => $info['name'],
            'nick' => $info['nick'],
            'head' => $info['head']
        ));
        $echo = array(
            'mid' => $mid,
            'nick' => $info['nick']
        );
        echo json_encode($echo);
        break;
}

function wm_exit($status_code, $msg = '') {
    $description = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    );
    header('HTTP/1.1 ' . $status_code . ' ' . $description[$status_code]);
    header('Content-Type: text/plain');
    echo $msg;
    exit;
}

function wm_add_or_modify($action, $type, $authtype, $token, $secret = NULL, $request_token = NULL, $request_token_secret = NULL, $mid = NULL) {
    global $wpdb, $table_prefix;
    switch ($authtype) {
        case 1 :
            $client = new MicroblogClient($type, 1, $token);
            $msg = $client->user_timeline(NULL, $token, 1);
            if (!isset($msg['timeline'][0]))
                wm_exit(401, '不能获得最新微博，请检查此微博的隐私设置。');
            if ($action == 1) {
                $return = MicroblogOption::addAccount(array (
                    'type' => (int)$type,
                    'uid' => $msg['user']['uid'],
                    'name' => $msg['user']['name'],
                    'nick' => $msg['user']['nick'],
                    'head' => $msg['user']['head'],
                    'suspend' => FALSE,
                    'authtype' => 1,
                    'token' => NULL,
                    'secret' => NULL,
                    'lastid' => NULL
                ));
            } else {
                $return = MicroblogOption::updateAccount($mid, array(
                    'uid' => $msg['user']['uid'],
                    'name' => $msg['user']['name'],
                    'nick' => $msg['user']['nick'],
                    'head' => $msg['user']['head'],
                    'authtype' => 1,
                    'token' => NULL,
                    'secret' => NULL
                ));
            }
            $msg['nick'] = $msg['user']['nick'];
            break;
        case 2 :
            $auth = new MicroblogOAuth($type, $request_token, $request_token_secret);
            $access_token = $auth->getAccessToken($token);
            if (!$access_token['oauth_token'] || !$access_token['oauth_token_secret'])
                wm_exit(401, '授权失败，请尝试重新获得授权');
            $msg = wm_get_info($type, 2, $access_token);
            if ($action == 1) {
                $return = MicroblogOption::addAccount(array (
                    'type' => (int)$type,
                    'uid' => $msg['uid'],
                    'name' => $msg['name'],
                    'nick' => $msg['nick'],
                    'head' => $msg['head'],
                    'suspend' => FALSE,
                    'authtype' => 2,
                    'token' => $access_token['oauth_token'],
                    'secret' => $access_token['oauth_token_secret'],
                    'lastid' => NULL
                ));
            } else {
                $return = MicroblogOption::updateAccount($mid, array(
                    'uid' => $msg['uid'],
                    'name' => $msg['name'],
                    'nick' => $msg['nick'],
                    'head' => $msg['head'],
                    'authtype' => 2,
                    'token' => $access_token['oauth_token'],
                    'secret' => $access_token['oauth_token_secret']
                ));
            }
            break;
        case 8 :
            $client = new MicroblogClient($type, 8, $token, $secret);
            $msg = $client->basic_verify_credentials();
            if (!$msg['uid'])
                wm_exit(401, '授权失败');
            if ($action == 1) {
                $return = MicroblogOption::addAccount(array (
                    'type' => (int)$type,
                    'uid' => $msg['uid'],
                    'name' => $msg['name'],
                    'nick' => $msg['nick'],
                    'head' => $msg['head'],
                    'suspend' => FALSE,
                    'authtype' => 8,
                    'token' => $token,
                    'secret' => $secret,
                    'lastid' => NULL
                ));
            } else {
                $return = MicroblogOption::updateAccount($mid, array(
                    'uid' => $msg['uid'],
                    'name' => $msg['name'],
                    'nick' => $msg['nick'],
                    'head' => $msg['head'],
                    'authtype' => 8,
                    'token' => $token,
                    'secret' => $secret
                ));
            }
            break;
    }

    if (!$return)
        wm_exit(409, '更新失败，可能此微博已存在');
    $echo = array(
        'mid' => ( $action == 1 ? $return : $mid ),
        'type' => $type,
        'nick' => $msg['nick'],
        'authtype' => $authtype
    );
    return $echo;
}

function wm_get_info($type, $authtype, $access_token, $uid = NULL ) {
    $client = new MicroblogClient($type, $authtype, $access_token['oauth_token'], $access_token['oauth_token_secret']);
    $msg = $client->user_info($uid);
    if (!isset($msg['uid']))
        wm_exit(401, '授权失败');
    return $msg;
}
?>
