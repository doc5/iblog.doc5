<?php

function wm_init() {
    global $wpdb, $table_prefix;

    $version = get_option("wm_version");

    switch ($version) {
        case '0.1.0' :
        case '0.1.4' :
        case '0.2.0' :
        case '0.2.5' :
        case '0.2.8' :
        case '0.2.9' :
        case '0.3.0' :
        case '0.3.1' :
            update_option('wm_version', WM_VERSION);
        case '0.3.3' :
            break;
        default :
            update_option('wm_version', WM_VERSION);
    }
    $query = "CREATE TABLE IF NOT EXISTS {$table_prefix}wm_timeline (
        mid INT NOT NULL,
        tid CHAR( 32 ) NOT NULL,
        md5 BINARY( 16 ) NULL,
        gmtime INT UNSIGNED NOT NULL,
        text TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
        other TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
        PRIMARY KEY ( mid, tid ),
        UNIQUE( md5 ),
        INDEX ( mid, gmtime ) )";
    $wpdb->query($query);
    if (!wp_next_scheduled('wm_update_timeline'))
        wp_schedule_event(time(), 'wm_update_schedule', 'wm_update_timeline');
    if (!wp_next_scheduled('wm_cleanup'))
        wp_schedule_event(time(), 'wm_cleanup_schedule', 'wm_cleanup');
}

function wm_register_ss() {
    global $wm_plugin_url;
    wp_register_script('jscolor', $wm_plugin_url . '/js/option.widget.js');
    wp_register_script('wm-option', $wm_plugin_url . '/js/option.js');
    wp_register_script('wm-widget', $wm_plugin_url . '/js/widget.js');
    wp_register_style('wm-option', $wm_plugin_url . '/option.css');
    wp_register_style('wm', $wm_plugin_url . '/style.css');
    do_action('wm_register_ss');
}

function wm_enqueue_widget_script() {
    wp_enqueue_script('wm-widget');
}

function wm_enqueue_widget_option_script() {
    wp_enqueue_script('jscolor');
}


function wm_enqueue_style() {
    wp_enqueue_style('wm');
}

function wm_add_schedule_options($schedules) {
    $frequency = MicroblogOption::get_option('frequency');
    $wm_schedule = array(
        'wm_update_schedule' => array('interval' => 60 * MicroblogOption::get_option('frequency'), 'display' => 'WP Microblogs Update Schedule')
    );
    if ($frequency * 10 < WM_MAX_CLEANUP_INTERVAL)
        $wm_schedule['wm_cleanup_schedule'] = array('interval' => 600 * MicroblogOption::get_option('frequency'), 'display' => 'WP Microblogs Cleanup Schedule');
    else if ($frequency < WM_MAX_CLEANUP_INTERVAL)
        $wm_schedule['wm_cleanup_schedule'] = array('interval' => 60 * WM_MAX_CLEANUP_INTERVAL, 'display' => 'WP Microblogs Cleanup Schedule');
    return array_merge($schedules, $wm_schedule);
}

function wm_deactivation() {
    wp_clear_scheduled_hook('wm_update_timeline');
    wp_clear_scheduled_hook('wm_cleanup');
    if (MicroblogOption::get_option('uninstall')) {
        global $wpdb, $table_prefix;
        MicroblogOption::clear_options();
        delete_option('wm_accounts');
        delete_option('wm_version');
        delete_option('widget_wm_show');
        $wpdb->query("DROP TABLE `{$table_prefix}wm_timeline`");
    }
}

function wm_restart_schedule() {
    $frequency = MicroblogOption::get_option('frequency');
    wp_clear_scheduled_hook('wm_update_timeline');
    wp_clear_scheduled_hook('wm_cleanup');
    wp_schedule_event(time() + 60 * $frequency, 'wm_update_schedule', 'wm_update_timeline');
    wp_schedule_event(time(), 'wm_cleanup_schedule', 'wm_cleanup');
    if ($frequency * 10 < WM_MAX_CLEANUP_INTERVAL)
        wp_schedule_event(time() + 600 * $frequency, 'wm_cleanup_schedule', 'wm_cleanup');
    else if ($frequency < WM_MAX_CLEANUP_INTERVAL)
        wp_schedule_event(time() + 60 * WM_MAX_CLEANUP_INTERVAL, 'wm_cleanup_schedule', 'wm_cleanup');
 }

function wm_update_timeline() {
    include_once( dirname(__FILE__) . '/class.microblog.php' );
    @set_time_limit(MicroblogOption::get_option('frequency') * 60 - 5);
    global $wpdb, $table_prefix;
    $unique = MicroblogOption::get_option('unique');
    $count = MicroblogOption::get_option('count');
    $similar = MicroblogOption::get_option('similar');
    $accounts = MicroblogOption::getAccounts();
    if ($accounts) {
        $md5_cache = array_change_key_case(array_flip($wpdb->get_col("SELECT HEX(md5) FROM {$table_prefix}wm_timeline WHERE md5 IS NOT NULL")), CASE_LOWER);
        if ($unique)
            $last_cache = $wpdb->get_var("SELECT MIN( gmtime ) AS earliest FROM {$table_prefix}wm_timeline WHERE md5 IS NOT NULL");
        $update_cache0 = false;
        foreach ($accounts as $mid => $account) {
            if ($account['suspend'])
                continue;
            $client = new MicroblogClient($account['type'], $account['authtype'], base64_decode($account['token']), base64_decode($account['secret']));
            $returns = $client->user_timeline($account['uid'], $account['name'], $count);
            if (!$returns)
                continue;
            $return_first = true;
            $query = "INSERT IGNORE INTO {$table_prefix}wm_timeline VALUES ";
            $returns = $returns['timeline'];
            foreach ($returns as $return) {
                if ($account['lastid'] == $return['tid'])
                    break;
                if ($return_first) {
                    MicroblogOption::updateAccount($mid, array('lastid' => $return['tid']));
                    $return_first = false;
                } else
                    $query .= ",";
                // 需要生成 MD5，（并且缓存（即生成 MD5 的行）数量不够，或者此微博的时间比缓存最早的时间要晚（即需要更新缓存））
                if ($unique && (count($md5_cache) < MicroblogOption::get_option('count') || $last_cache < $return['timestamp'])) {
                    $tmd5 = wm_unique_md5($return['text'], isset($return['other']) ? $return['other'] : NULL);
                    if (!isset($md5_cache[$tmd5])) {
                        $update_cache0 = true;
                        $md5 = 'x\'' . $tmd5 . '\'';
                        $md5_cache[$tmd5] = 1;
                        if (!$last_cache || $last_cache > $return['timestamp'])
                            $last_cache = $return['timestamp'];
                    }
                } else {
                    $md5 = 'NULL';
                }
                $query .= "('" . $mid . "', '" . mysql_real_escape_string($return['tid']) . "', " . $md5 . ", '" . $return['timestamp'] . "', '" . mysql_real_escape_string($return['text']) . "', " . (isset($return['other']) ? "'" . mysql_real_escape_string(serialize($return['other'])) . "'" : 'NULL') . ")";
            }

            if (!$return_first) {
                $wpdb->query($query);
                wm_delete_cache($mid);
            }
        }
        if ($update_cache0)
            wm_delete_cache(0);
    }
    if (MicroblogOption::get_option('frequency') >= WM_MAX_CLEANUP_INTERVAL)
        wm_cleanup ();
}

function wm_cleanup() {
    global $wpdb, $table_prefix;
    $results = $wpdb->get_results("SELECT DISTINCT( mid ) FROM {$table_prefix}wm_timeline");
    foreach ($results as $result) {
        $limit = $wpdb->get_var("SELECT COUNT( * ) FROM {$table_prefix}wm_timeline WHERE mid = $result->mid") - 20;
        $query = "DELETE FROM {$table_prefix}wm_timeline WHERE mid = $result->mid ORDER BY gmtime ASC LIMIT $limit";
        $wpdb->query($query);
    }
}

function wm_widgets_init() {
    register_widget('widget_wm_show');
}

function wm_clear_timeline() {
    global $wpdb, $table_prefix;
    MicroblogOption::clearLastid();
    $wpdb->query("TRUNCATE TABLE {$table_prefix}wm_timeline");
    delete_option('wm_cache');
}

function wm_reset_timeline() {
    global $wpdb, $table_prefix;
    $wpdb->query("UPDATE {$table_prefix}wm_timeline SET md5 = NULL");
    if (!MicroblogOption::get_option('unique'))
        return;
    $count = MicroblogOption::get_option('count');
    $limit = 0;
    $md5_cache = array();
    $row_count = $wpdb->get_var("SELECT COUNT( * ) FROM {$table_prefix}wm_timeline");
    $cache_count = 0;
    while ($limit < $row_count && $cache_count < $count) {
        $results = $wpdb->get_results("SELECT mid, tid, text, other FROM {$table_prefix}wm_timeline ORDER BY gmtime DESC, mid ASC LIMIT $limit, $count");
        foreach ($results as $result) {
            $md5 = wm_unique_md5($result->text, isset($result->other) ? $result->other : NULL);
            if (!isset($md5_cache[$md5])) {
                $wpdb->query("UPDATE IGNORE {$table_prefix}wm_timeline SET md5 = x'$md5' WHERE mid = '$result->mid' AND tid = '$result->tid'");
                $md5_cache[$md5] = 1;
                $cache_count++;
                if ($cache_count >= $count)
                    break 2;
            }
        }
        $limit += $count;
    }
    delete_option('wm_cache');
}

function wm_unique_md5($str, $other = NULL, $similar = NULL) {
    if (isset($other))
        $str .= serialize($other);
    if (!isset($similar))
        $similar = MicroblogOption::get_option('similar');
    if ($similar) {
        $rep = preg_replace('/(http|https|ftp):\/\/[!-~]+|\n|\t|\r|\s/i', '', $str);
        if ($rep != '')
            $str = $rep;
    }
    $md5 = md5($str);
    return $md5;
}

function wm_get_user_url($mid, $domain = NULL) {
    global $wpdb, $table_prefix;
    $account = MicroblogOption::getAccount($mid);
    if (!$account)
        return false;
    switch ($account['type']) {
        case 1 :
            if (!$domain)
                $domain = $account['uid'];
            $url = "http://weibo.com/$domain";
            break;
        case 2 :
            if (!$domain)
                $domain = $account['uid'];
            $url = "http://t.qq.com/$domain";
            break;
        case 3 :
            if (!$domain)
                $domain = $account['name'];
            $url = "http://twitter.com/$domain";
            break;
        case 4 :
            if (!$domain)
                $domain = $account['name'];
            $url = "http://t.163.com/$domain";
            break;
        case 5 :
            if (!$domain)
                $domain = $account['uid'];
            $url = "http://t.sohu.com/u/$domain";
            break;
        case 6 :
            if (!$domain)
                $domain = $account['name'];
            $url = "http://digu.com/$domain";
            break;
        case 7 :
            if (!$domain)
                $domain = $account['name'];
            $url = "http://fanfou.com/$domain";
            break;
        case 8 :
            if (!$domain)
                $domain = $account['name'];
            $url = "http://zuosa.com/$domain";
            break;
        case 9 :
            if (!$domain)
                $domain = $account['name'];
            $url = "http://renjian.com/$domain";
            break;
        case 10 :
            if (!$domain)
                $domain = $account['uid'];
            $url = "http://www.douban.com/people/$domain/";
            break;
    }
    return isset($url) ? $url : false;
}

function wm_get_tweet_url($mid, $tid) {
    global $wpdb, $table_prefix;
    $account = MicroblogOption::getAccount($mid);
    if (!$account)
        return false;
    switch ($account['type']) {
        case 1 :
            $url = "http://weibo.com/{$account['uid']}/" . wm_idn2s($tid);
            break;
        case 2 :
            $url = "http://t.qq.com/p/t/$tid";
            break;
        case 3 :
            $url = "http://twitter.com/{$account['name']}/status/$tid";
            break;
        case 4 :
            $url = "http://t.163.com/{$account['name']}/status/$tid";
            break;
        case 5 :
            $url = "http://t.sohu.com/m/$tid";
            break;
        case 6 :
            $url = "http://digu.com/detail/$tid";
            break;
        case 7 :
            $url = "http://fanfou.com/statuses/$tid";
            break;
        case 8 :
            $url = "http://zuosa.com/Status/$tid";
            break;
        case 9 :
            $url = "http://renjian.com/c/$tid";
            break;
        case 10 :
            $url = "http://www.douban.com/people/{$account['uid']}/miniblogs";
            break;
    }
    return isset($url) ? $url : false;
}

function wm_get_tweet_time($timestamp, $relative = FALSE) {
    if ($relative && date('Y-n-j', $timestamp + get_option('gmt_offset') * 3600) == date_i18n('Y-n-j')) {
        $difference = time() - $timestamp;
        if ($difference < 60)
            $return = sprintf("%s 秒前", $difference);
        else if ($difference < 3600)
            $return = sprintf("%s 分钟前", floor($difference / 60));
        else
            $return = sprintf("今天 %s", date('H:i', $timestamp + get_option('gmt_offset') * 3600));
    } else
        $return = date ('n月j日 H:i', $timestamp + get_option('gmt_offset') * 3600);
    return $return;
}

function wm_widget_get_tweet_time($timestamp, $relative = FALSE, $format = 'n月j日 H:i') {
    if ($relative && $timestamp > strtotime('today'))
        return false;
    else
        return date ($format, $timestamp + get_option('gmt_offset') * 3600);
}

function wm_tweet_filter($args) {
    if (!isset($args['content']))
        return;
    $return = $args['content'];
    if (isset($args['timestamp'])) {
        $timestr = array();
        foreach ($args['timestamp'] as $timestamp) {
            array_push($timestr, wm_get_tweet_time($timestamp, true));
        }
        $return = vsprintf($return, $timestr);
    } else
        $return = str_replace ('%%', '%', $return);
    return $return;
}

function wm_html_filter($str) {
    return str_replace('%', '%%', $str);
}


function wm_add_rel($ret) {
    return str_replace('<a ', '<a rel="external nofollow"', $ret);
}

function wm_make_clickable($ret) {
    return preg_replace('/(http|https|ftp):\/\/[!-~]+/i', '<a href="\\0" rel="external nofollow">\\0</a>', $ret);
}

function wm_get_tweet($args = array ()) {
	$defaults = array(
        'count' => 1,
        'list_wrapper' => '<div class="tweet">%s</div>',
        'tweet_wrapper' => '',
        'tweet_format' => '%2$s : %6$s%17$s <span class="meta">( <a href="%3$s">%5$s</a> 来自 <a href="%4$s" rel="external nofollow">%1$s</a> )</span>',
        'pic_format' => '',
        'rt_pic_format' => '',
        'rt_format' => ''
    );
	$args = wp_parse_args( $args, $defaults );
    return wm_get_tweets($args);
}

function wm_tweet($args = array ()) {
    echo wm_get_tweet($args);
}

function wm_get_tweet_arr($mid = NULL) {
    $returns = wm_get_tweets_arr($mid, 1);
    if (isset($returns[0]))
        return $returns[0];
    else
        return false;
}

function wm_get_tweets($args = array()) {
    $patterns = array(
        '/\[type\]/',           // 1
        '/\[name\]/',           // 2
        '/\[tweet_url\]/',      // 3
        '/\[user_url\]/',       // 4
        //'/\[time\]/',           // 5
        '/\[text\]/',           // 5
        '/\[pic_small\]/',      // 6
        '/\[pic_big\]/',        // 7
        '/\[rt_name\]/',        // 8
        '/\[rt_tweet_url\]/',   // 9
        '/\[rt_user_url\]/',    // 10
        '/\[rt_text\]/',        // 11
        '/\[rt_pic_small\]/',   // 12
        '/\[rt_pic_big\]/',     // 13
        '/\[user_head\]/',      // 14
        '/\[pic\]/',            // 15
        '/\[rt_pic\]/',         // 16
        '/\[rt\]/',             // 17
    );
    $replacements = array (
        '%1$s',
        '%2$s',
        '%3$s',
        '%4$s',
        '%5$s',
        '%6$s',
        '%7$s',
        '%8$s',
        '%9$s',
        '%10$s',
        '%11$s',
        '%12$s',
        '%13$s',
        '%14$s',
        '%15$s',
        '%16$s',
        '%17$s'
    );
    $defaults = array(
        'mid' => 0,
        'count' => 20,
        'list_wrapper' => '<ul class="microblogs">%s</ul>',
        'tweet_wrapper' => '<li class="tweet">%s</li>',
        'tweet_format' => '[text][pic][rt]<div class="meta"><a href="[tweet_url]" rel="external nofollow">[time]</a> 来自 <a href="[user_url]" rel="external nofollow">[type]</a></div>',
        'pic_format' => '<div class="pic orig-pic"><a href="[pic_big]" rel="external nofollow"><img src="[pic_small]" /></a></div>',
        'rt_pic_format' => '<div class="pic rt-pic"><a href="[rt_pic_big]" rel="external nofollow"><img src="[rt_pic_small]" /></a></div>',
        'rt_format' => '<div class="rt"><a href="[rt_user_url]" rel="external nofollow">[rt_name]</a> : [rt_text][rt_pic]</div>',
        'time_format' => 'n月j日 H:i',
        'relative' => 1
    );
	$args = wp_parse_args( $args, $defaults );
    $args = (object)$args;
    if (!$args->list_wrapper)
        $args->list_wrapper = '%s';
    if (!$args->tweet_wrapper)
        $args->tweet_wrapper = '%s';

    $key = md5(serialize($args));

    $cache_content = wm_get_cache($args->mid, $key);
    if ($cache_content)
        return $cache_content;
    
    if (isset($args->tweet_format))
        $args->tweet_format = preg_replace ($patterns, $replacements, $args->tweet_format);
    if (isset($args->pic_format))
        $args->pic_format = preg_replace ($patterns, $replacements, $args->pic_format);
    if (isset($args->rt_pic_format))
        $args->rt_pic_format = preg_replace ($patterns, $replacements, $args->rt_pic_format);
    if (isset($args->rt_format))
        $args->rt_format = preg_replace ($patterns, $replacements, $args->rt_format);

    global $wpdb, $table_prefix;
    $output = '';
    $accounts = MicroblogOption::getAccounts();
    if ($args->mid) {
        $condition = "WHERE mid=$args->mid ORDER BY gmtime DESC";
    } else {
        if (MicroblogOption::get_option('unique'))
            $condition = "WHERE md5 IS NOT NULL ORDER BY gmtime DESC";
        else
            $condition = "WHERE ORDER BY gmtime DESC";
    }
    $query = "SELECT * FROM {$table_prefix}wm_timeline $condition LIMIT $args->count";
    $results = $wpdb->get_results($query);
    $autolink = MicroblogOption::get_option('autolink');
    $timecache = array();
    if (isset($results[0])) {
        foreach ($results as $result) {
            $other = unserialize($result->other);
            $sargs = array (
                MicroblogOption::$screenName[$accounts[$result->mid]['type']],                                                                  // 1 type
                $accounts[$result->mid]['nick'],                                                                                                // 2 name
                wm_get_tweet_url($result->mid, $result->tid),                                                                                   // 3 tweet_url
                wm_get_user_url($result->mid),                                                                                                  // 4 user_url
                //$time,                                                                                                                        // 5 time
                $accounts[$result->mid]['type'] == 10 ? wm_add_rel($result->text) : ( $autolink ? wm_make_clickable($result->text) : $result->text ),                                                                   // 5 text
                isset($other['pic']['small']) ? $other['pic']['small'] : '',                                                                    // 6 pic_small
                isset($other['pic']['big']) ? $other['pic']['big'] : '',                                                                        // 7 pic_big
                isset($other['rt']['nick']) ? $other['rt']['nick'] : '',                                                                        // 8 rt_name
                isset($other['rt']['tid']) ? wm_get_tweet_url($result->mid, $other['rt']['tid']) : '',                                          // 9 rt_tweet_url
                isset($other['rt']['domain']) ? wm_get_user_url($result->mid, $other['rt']['domain']) : '',                                     // 10 rt_user_url
                isset($other['rt']['text']) ? $autolink ?  wm_make_clickable($other['rt']['text']) : $other['rt']['text'] : '',                 // 11 rt_text
                isset($other['rt']['pic']['small']) ? $other['rt']['pic']['small'] : '',                                                        // 12 rt_pic_small
                isset($other['rt']['pic']['big']) ? $other['rt']['pic']['big'] : '',                                                            // 13 rt_pic_big
                $accounts[$result->mid]['head']                                                                                                 // 14 user_head
            );
            if (isset($other['pic']))                                                                                                           // 15 pic
                array_push($sargs, vsprintf($args->pic_format, $sargs));
            else
                array_push($sargs, '');
            if (isset($other['rt']['pic']))                                                                                                     // 16 rt_pic
                array_push($sargs, vsprintf($args->rt_pic_format, $sargs));
            else
                array_push($sargs, '');
            if (isset($other['rt']))                                                                                                            // 17 rt
                array_push($sargs, vsprintf($args->rt_format, $sargs));
            else
                array_push($sargs, '');

            $tweet = wm_html_filter(vsprintf($args->tweet_format, $sargs));
            $time = wm_widget_get_tweet_time($result->gmtime, $args->relative, $args->time_format);
            if ($time === false) {
                $time = '%s';
                array_push($timecache, $result->gmtime);
            } else
                $time = wm_html_filter ($time);
            $tweet = str_replace('[time]', $time, $tweet);

            $output .= sprintf($args->tweet_wrapper, $tweet);
        }
    } else {
        $output .= '<li>没有内容可以显示，可能的原因是：<ul><li>没有设置微博帐号；</li><li>帐号已被禁用；</li><li>微博网站出现问题；</li><li>如果您刚刚保存的设置，内容不会立刻出现，请稍等一会儿，或者在后台选择“更新缓存”。</li></ul></li>';
    }
    $output = sprintf($args->list_wrapper, $output);
    $cache_content = array();
    $cache_content['content'] = $output;
    if ($args->relative)
        $cache_content['timestamp'] = $timecache;
    wm_update_cache($args->mid, $key, $cache_content);
    return wm_tweet_filter($cache_content);
}

function wm_tweets($args = array()) {
    echo wm_get_tweets($args);
}

function wm_get_tweets_arr($mid = 0, $count = 20, $cache = TRUE) {
    if ($cache) {
        $cache_content = wm_get_cache($mid, "count$count");
        if ($cache_content)
            return $cache_content;
    }
    global $wpdb, $table_prefix;
    $results = $wpdb->get_results("SELECT * FROM {$table_prefix}wm_timeline" . ($mid ? "WHERE mid=$mid" : '') . " ORDER BY gmtime DESC, md5 DESC LIMIT $count");
    $accounts = MicroblogOption::getAccounts();
    $returns = array ();
    if (isset($results[0])) {
        foreach ($results as $key => $result) {
            $other = unserialize($result->other);
            $return = array (
                'type_id' => $accounts[$result->mid]['type'],
                'type' => MicroblogOption::$screenName[$accounts[$result->mid]['type']],
                'name' => $accounts[$result->mid]['nick'],
                'tweet_url' => wm_get_tweet_url($result->mid, $result->tid),
                'user_url' => wm_get_user_url($result->mid, $result->tid),
                'text' => $result->text,
                'time' => $result->gmtime
            );
            if (isset($other['pic']))
                $return['pic'] = array (
                    'small' => $other['pic']['small'],
                    'big' => $other['pic']['big']
                );
            if (isset($other['rt'])) {
                $return['rt'] = array (
                    'name' => $other['rt']['nick'],
                    'tweet_url' => wm_get_tweet_url($result->mid, $other['rt']['tid']),
                    'user_url' => wm_get_user_url($result->mid, $other['rt']['domain']),
                    'text' => $other['rt']['text']
                );
                if (isset($other['rt']['pic']))
                    $return['rt']['pic'] = array (
                        'small' => $other['rt']['pic']['small'],
                        'big' => $other['rt']['pic']['big']
                    );
            }
            $returns[$key] = $return;
        }
    }
    if ($cache)
        wm_update_cache ($mid, "count$count", $returns);
    return $returns;
}

function wm_get_cache($mid, $key) {
    $cache = get_option('wm_cache');
    if (!is_array($cache))
        $cache = array();
    if (isset($cache[$mid][$key]))
        return wm_tweet_filter($cache[$mid][$key]);
    else
        return false;
}

function wm_update_cache($mid, $key, $content) {
    $cache = get_option('wm_cache');
    if (!is_array($cache))
        $cache = array();
    $cache[$mid][$key] = $content;
    update_option('wm_cache', $cache);
}

function wm_delete_cache($mid) {
    $widget_cache = get_option('wm_cache');
    if (isset($widget_cache[$mid]))
        unset($widget_cache[$mid]);
    update_option('wm_cache', $widget_cache);
}

function wm_shortcode_tweet($atts) {
    wm_tweet($atts);
}

function wm_shortcode_tweets($atts) {
    wm_tweets($atts);
}

function wm_62($n) {
    $s = "";
    $keys = array(
        "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", 
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j",
        "k", "l", "m", "n", "o", "p", "q", "r", "s", "t",
        "u", "v", "w", "x", "y", "z",
        "A", "B", "C", "D", "E", "F", "G", "H", "I", "J",
        "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T",
        "U", "V", "W", "X", "Y", "Z"); 
    while ($n != 0) {
        $s = $keys[$n % 62] . $s;
        $n = intval($n / 62);
    }
    return $s;
}

function wm_idn2s($idn) {
    $ids = '';
    $idns = str_split(strrev($idn), 7);
    foreach ($idns as $idn) {
        $ids = wm_62(strrev($idn)) . $ids;
    }
    return $ids;
}

?>