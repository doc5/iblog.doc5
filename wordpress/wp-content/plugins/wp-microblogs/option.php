<?php

class MicroblogOption {

    private static $option_key = 'wm_options';
    private static $all_options = array(
        'frequency' => 5,
        'count' => 20,
        'unique' => 1,
        'similar' => 1,
        'autolink' => 0,
        'uninstall' => 0
    );
    public static $authName = array(
        1 => '不认证',
        2 => 'OAuth',
        4 => 'XAuth',
        8 => 'Basic Auth'
    );
    public static $screenName = array(
        1 => '新浪微博',
        2 => '腾讯微博',
        3 => 'Twitter',
        4 => '网易微博',
        5 => '搜狐微博',
        6 => '嘀咕',
        7 => '饭否',
        8 => '做啥',
        9 => '人间',
        10 => '豆瓣'
    );

    private static function option_filter($option, $value) {
        switch ($option) {
            case 'count' :
                if ($value > self::$all_options[$option])
                    $value = $all_options[$option];
            case 'frequency' :
                return $value >= 1 ? (int) $value : self::$all_options[$option];
                break;
            case 'unique' :
            case 'similar' :
            case 'autolink' :
            case 'uninstall' :
                return $value == 1 ? 1 : 0;
                break;
            default :
                return $value;
        }
    }

    public static function get_option($option) {
        $options = get_option(self::$option_key, self::$all_options);
        return self::option_filter($option, $options[$option]);
    }
    
    public static function clear_options() {
        delete_option(self::$option_key);
    }

    private function get_options() {
        $options_db = get_option(self::$option_key, self::$all_options);
        $options = array();
        foreach (self::$all_options as $option => $value) {
            $options[$option] = self::option_filter($option, $options_db[$option]);
        }
        return $options;
    }

    private function update_options($options) {
        $unique = self::get_option('unique');
        $similar = self::get_option('similar');
        $new_unique = self::option_filter('unique', $options['unique']);
        $new_similar = self::option_filter('similar', $options['similar']);
        $options_db = array();
        foreach (self::$all_options as $option => $value)
            $options_db[$option] = self::option_filter($option, $options[$option]);
        update_option(self::$option_key, $options_db);
        if ($new_unique != $unique || $new_unique == 1 && $new_similar != $similar)
            wm_reset_timeline();
    }
    
    public function enqueue_script() {
        global $wm_plugin_url;
        $accounts = MicroblogOption::getAccounts();
//        $results = $wpdb->get_results($query);
        ?>
        <script type="text/javascript">
            var wm_plugin_url = "<?php echo $wm_plugin_url; ?>";
            var wm_account_list = new Array(
        <?php
        $first = true;
        if ($accounts)
		        foreach ($accounts as $mid => $account) {
		            if ($first)
		                $first = false;
		            else
		                echo ', '; echo "new Array( $mid, " . (int) $account['type'] . ", \"" . htmlspecialchars($account['nick']) . "\", " . (int) $account['suspend'] . ", " . (int) $account['authtype'] . " )";
		        }
        ?>
        );
        </script>
        <?php
        wp_enqueue_script('wm-option');
    }

    public function enqueue_style() {
        wp_enqueue_style('wm-option');
    }

    public function add_menu() {
        $page = add_options_page('WP Microblogs', 'WP Microblogs', 8, __FILE__, array($this, 'option_page'));
        add_action('admin_print_scripts-' . $page, array(&$this, 'enqueue_script'));
        add_action('admin_print_styles-' . $page, array(&$this, 'enqueue_style'));
    }

    public function option_page() {
        if (function_exists('current_user_can') && !current_user_can('manage_options'))
            wm_exit();
        wp_enqueue_script('wm_option_script');
        wm_init();
        global $wm_plugin_url;
        ?>
        <div class="wrap">
            <h2>WP Microblogs 设置</h2>
            <p>测试版本，帮助信息与高级使用方法请移步<a href="http://beamnote.com/2011/wp-microblogs.html">插件主页</a>。</p>
            <?php
            if (isset($_POST['submit'])) {
                $this->update_options($_POST);
                wm_restart_schedule();
               ?>
                <div class="updated"><p><strong>设置已保存。</strong></p></div>
                <?php
            }
            if (isset($_POST['update'])) {
                ?>
                <div class="updated"><p><strong id="cache-update">缓存更新中，请不要关闭窗口……</strong></p></div>
                <?php
                wp_ob_end_flush_all();
                flush();
                wm_clear_timeline();
                do_action('wm_update_timeline');
                ?>
                <script type="text/javascript">document.getElementById('cache-update').innerHTML="缓存已更新。";</script>
                <?php
            }
            $options = $this->get_options();
            ?>
            <div id="container">
                如果插件不工作，<a href="<?php echo $wm_plugin_url; ?>/test.php" target="_blank">按这里进行测试</a>。
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="account-list">微博帐号</label></th>
                        <td id="account-td">
                            <div id="account-list-wrapper">
                                <select name="account-list" id="account-list" size="6">
                                </select>
                                <input type="button" id="ajax-add" value="添加"><br />
                                <input type="button" id="ajax-remove" value="删除"><br />
                                <input type="button" id="ajax-suspend" value="停 / 启用"><br />
                                <input type="button" id="ajax-edit" value="编辑"><br />
                                <input type="button" id="ajax-update" value="更新信息">
                            </div>
                            <div id="account-info-div" style="display: none;">
                                <h3 id="account-info-title">添加微博帐号</h3>
                                <table class="account-info-table">
                                    <tr>
                                        <th><label for="microblog-type-list">微博</label></th>
                                        <td id="td-add">
                                            <select name="microblog-type-list" id="microblog-type-list">
                                            </select>
                                        </td>
                                        <td id="td-edit">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="auth-type">认证方式</label></th>
                                        <td>
                                            <select name="auth-type" id="auth-type">
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th id="th-title"><label for="token-or-username">OAuth 密匙</label></th>
                                        <td>
                                            <input type="text" name="token-or-username" id="token-or-username">
                                            <input type="button" id="oauth-get-token" class="ajax-secondary" value="获得授权"><p id="oauth-msg-popup">如果腾讯微博、Twitter 获得授权失败，请允许弹出式窗口。</p>
                                        </td>
                                    </tr>
                                    <tr id="tr-password">
                                        <th><label for="basic-password">密码</label></th>
                                        <td>
                                            <input type="password" name="basic-password" id="basic-password">
                                        </td>
                                    </tr>
                                </table>
                                <input type="button" id="ajax-submit" value="提交">
                                <input type="button" id="ajax-cancel" value="取消">
                            </div>
                            <div id="wait" style="display: none;">请等一下...</div>
                        </td>
                    </tr>
                </table>
                <p>下列选项需要单击“保存更改”才会生效。</p>
                <form id="form1" name="form1" method="post">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">更新频率</th>
                            <td>
                                <label for="frequency">
                                    每 <input type="text" name="frequency" id="frequency" class="small-text" value="<?php echo $options['frequency']; ?>"> 分钟更新一次
                                </label><br />
                                <?php
                                $next_cron = wp_next_scheduled('wm_update_timeline');
                                if (!empty($next_cron)) {
                                    ?>
                                下一次更新发生在 <?php echo gmdate(get_option('date_format') . ' ' . get_option('time_format'), $next_cron + (get_option('gmt_offset') * 3600)); ?><br />
                                <?php
                                }
                                ?>
                                <label for="count">
                                    每个帐号保存 <input type="text" name="count" id="count" class="small-text" value="<?php echo $options['count']; ?>"> 条微博 最多保存 20 条
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">重复排除</th>
                            <td>
                                <label for="unique">
                                    <input type="checkbox" name="unique" id="unique" value="1"<?php echo $options['unique'] == 1 ? ' checked="checked"' : ''; ?>> 过滤重复微博
                                </label><br />
                                <label for="similar">
                                    <input type="checkbox" name="similar" id="similar" value="1"<?php echo $options['similar'] == 1 ? ' checked="checked"' : ''; ?>> 使用近似匹配<br />
                                    <ol>
                                        <li>近似匹配指忽略空格与网址后进行匹配，避免无关字符与不同缩短网址服务的影响；</li>
                                        <li>近似匹配不能保证绝对消除重复，但大部分情况下都能正常工作；</li>
                                        <li>近似匹配的算法不一，如果您对匹配算法有任何意见，请在<a href="http://beamnote.com/">我的网站</a>留言。</li>
                                    </ol>
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">输出格式</th>
                            <td>
                                <label for="autolink">
                                    <input type="checkbox" name="autolink" id="autolink" value="1"<?php echo $options['autolink'] == 1 ? ' checked="checked"' : ''; ?>> 为微博中提到的 URL 添加链接
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">卸载选项</th>
                            <td>
                                <label for="uninstall">
                                    <input type="checkbox" name="uninstall" id="uninstall" value="1"<?php echo $options['uninstall'] == 1 ? ' checked="checked"' : ''; ?>> 禁用插件时删除插件配置与数据。<br />
                                    如果您准备卸载插件，请在禁用插件之前选择这个选项，以清除插件遗留信息。<br />
                                    如果是因为无法获得微博等原因准备卸载插件，希望您能反馈到<a href="http://beamnote.com/2011/wp-microblogs.html#respond">插件主页</a>，谢谢。
                                </label>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" id="submit" name="submit" class="button-primary" value="保存更改">
                        <input type="submit" id="update" name="update" value="更新缓存">
                    </p>
                </form>
            </div>
            <div id="oauth-iframe">
                <p><a href="#" id="oauth-back">&lt;&lt; 返回设置界面</a></p>
                <iframe></iframe>
            </div>
        </div>
        <?php
    }

    public static function getAccounts() {
        $accounts = get_option('wm_accounts');
        if (isset($accounts['accounts']))
            return $accounts['accounts'];
        else
            return false;
    }

    public static function getOrigAccounts() {
        $accounts = get_option('wm_accounts');
        if (!is_array($accounts))
            $accounts = array('counter' => 0, 'accounts' => array());
        return $accounts;
    }

    public static function getAccount($mid) {
        $accounts = self::getAccounts();
        if (isset($accounts[$mid])) {
            $account = $accounts[$mid];
            if (isset($account['token']))
                $account['token'] = base64_decode($account['token']);
            if (isset($account['secret']))
                $account['secret'] = base64_decode($account['secret']);
            return $account;
        } else
            return false;
    }

    public static function getAccountVar($mid, $key) {
        $account = self::getAccount($mid);
        if (isset($account[$key]))
            return $account[$key];
        else
            return false;
    }

    public static function getNormalAccountList() {
        $accounts = self::getAccounts();
        $return = false;
        if ($accounts)
	        foreach ($accounts as $mid => $account) {
	            if (!$return)
	                $return = array();
	            if ($account['suspend'] == 0)
	                array_push($return, $mid);
	        }
        return $return;
    }

    public static function updateAccount($mid, $args) {
        $accounts = self::getOrigAccounts();
        if (!isset($accounts['accounts'][$mid]))
            return false;
        $default = $accounts['accounts'][$mid];
        if (isset($default['token']))
            $default['token'] = base64_decode($default['token']);
        if (isset($default['secret']))
            $default['secret'] = base64_decode($default['secret']);
        if (isset($args['suspend']) && $args['suspend'] == -1)
            $args['suspend'] = !$default['suspend'];
        $args = wp_parse_args($args, $default);
        $args['token'] = base64_encode($args['token']);
        $args['secret'] = base64_encode($args['secret']);
        $accounts['accounts'][$mid] = $args;
        update_option('wm_accounts', $accounts);
        return $mid;
    }

    public static function clearLastid() {
        $accounts = self::getOrigAccounts();
        foreach ($accounts['accounts'] as $key => $account)
            $accounts['accounts'][$key]['lastid'] = NULL;
        update_option('wm_accounts', $accounts);
        return true;
    }

    public static function addAccount($args) {
        $accounts = self::getOrigAccounts();
        foreach ($accounts['accounts'] as $account)
            if ($account['type'] == $args['type'] && $account['uid'] == $args['uid'])
                return false;
        $args['token'] = base64_encode($args['token']);
        $args['secret'] = base64_encode($args['secret']);
        $mid = ++$accounts['counter'];
        $accounts['accounts'][$mid] = $args;
        update_option('wm_accounts', $accounts);
        return $mid;
    }

    public static function removeAccount($mid) {
        $accounts = self::getOrigAccounts();
        if (!isset($accounts['accounts'][$mid]))
            return false;
        unset($accounts['accounts'][$mid]);
        update_option('wm_accounts', $accounts);
        return $mid;
    }

}
?>