<?php

class widget_wm_show extends WP_Widget {

    private static $all_options = array(
        'title' => '',
        'mid' => 0,
        'count' => 20,
        'autowidth' => 1,
        'autoheight' => 0,
        'width' => 200,
        'height' => 300,
        'scroll' => 0,
        'bgcolor' => '',
        'arrowcolor' => 0,
        'arrowbgcolor' => '',
        'rtbordercolor' => '',
        'textcolor' => '',
        'linkcolor' => '',
        'metacolor' => '',
        'headdisplay' => 0,
        'headmid' => 1,
        'showtime' => 1,
        'relative' => 0,
        'timeformat' => '',
        'showfrom' => 1,
        'jsoutput' => 0
    );

    function options_filter($options) {
        $returns = array();
        if (isset($options['mid'])) {
            foreach (self::$all_options as $key => $value) {
                if (isset($options[$key]))
                    switch ($key) {
                        case 'title' :
                            $returns[$key] = strip_tags($options[$key]);
                            break;
                        case 'mid' :
                            $returns[$key] = max((int) $options[$key], 0);
                            break;
                        case 'count' :
                            $returns[$key] = min(max((int) $options[$key], 1), MicroblogOption::get_option($key));
                            break;
                        case 'autowidth' :
                        case 'autoheight' :
                        case 'showtime' :
                        case 'relative' :
                        case 'showfrom' :
                        case 'jsoutput' :
                            $returns[$key] = $options[$key] == 1 ? 1 : 0;
                            break;
                        case 'width' :
                        case 'height' :
                            $returns[$key] = max((int) $options[$key], 0);
                            break;
                        case 'scroll' :
                            $returns[$key] = min(max((int) $options[$key], 0), 1);
                            break;
                        case 'bgcolor' :
                        case 'arrowbgcolor' :
                        case 'rtbordercolor' :
                        case 'textcolor' :
                        case 'linkcolor' :
                        case 'metacolor' :
                            preg_match('/[0-9a-f]{6}|[0-9a-f]{3}/i', $options[$key], $match);
                            $returns[$key] = $match[0];
                            break;
                        case 'arrowcolor' :
                            $returns[$key] = min(max((int) $options[$key], 0), 9);
                            break;
                        case 'headdisplay' :
                            $returns[$key] = min(max((int) $options[$key], 0), 2);
                            break;
                        case 'headmid' :
                            $returns[$key] = max((int) $options[$key], 1);
                            break;
                        default :
                            $returns[$key] = $options[$key];
                    }
                else
                    switch ($key) {
                        case 'autowidth' :
                        case 'autoheight' :
                        case 'showtime' :
                        case 'relative' :
                        case 'showfrom' :
                        case 'jsoutput' :
                            $returns[$key] = 0;
                            break;
                        default :
                            $returns[$key] = self::$all_options[$key];
                    }
            }
        } else
            $returns = self::$all_options;
        return $returns;
    }

    function ids() {
        $returns = array();
        foreach (self::$all_options as $key => $value) {
            $returns[$key] = $this->get_field_id($key);
        }
        return $returns;
    }

    function names() {
        $returns = array();
        foreach (self::$all_options as $key => $value) {
            $returns[$key] = $this->get_field_name($key);
        }
        return $returns;
    }

    function widget_wm_show() {
        $widget_ops = array('classname' => 'wm_show', 'description' => '您的最新微博');
        $this->WP_Widget('wm_show', '微博', $widget_ops);

        if (is_active_widget(false, false, $this->id_base))
            add_action('template_redirect', 'wm_enqueue_widget_script');
    }

    function widget($args, $instance) {
        global $wm_plugin_url;
        $instance = $this->options_filter($instance);
        extract($args);
        extract($instance);
        $output = $before_widget;
        if (!empty($title))
            $output .= $before_title . $title . $after_title;

        // output start
        $style = '';
        if (!$autowidth && isset($width))
            $style .= "#mwrapper-$widget_id, #mwrapper-$widget_id .head, #mwrapper-$widget_id .mcontainer {width: {$width}px}\n";
        if (!$autoheight) {
            if (isset($height))
                $style .= "#mwrapper-$widget_id .mcontainer {height: {$height}px; overflow: hidden;}\n";
            if ($scroll == 0) {
                if ($arrowcolor)
                    $style .= "#mwrapper-$widget_id .up img {background-position: -" . $arrowcolor * 10 . "px 0}\n#mwrapper-$widget_id .down img {background-position: -" . $arrowcolor * 10 . "px -7px}\n";
                if (!empty($arrowbgcolor))
                    $style .= "#mwrapper-$widget_id .scroll:hover {background-color: #$arrowbgcolor}\n";
            } else
                $style .= "#mwrapper-$widget_id .mcontainer {overflow-y: scroll; padding-right: 5px;}";
        }
        if (!empty($bgcolor))
            $style .= "#mwrapper-$widget_id {background-color: #$bgcolor}\n";
        if (!empty($rtbordercolor))
            $style .= "#mwrapper-$widget_id .microblogs .rt {border-color: #$rtbordercolor}\n";
        if (!empty($textcolor))
            $style .= "#mwrapper-$widget_id {color: #$textcolor}\n";
        if (!empty($linkcolor))
            $style .= "#mwrapper-$widget_id a {color: #$linkcolor}\n";
        if (!empty($metacolor))
            $style .= "#mwrapper-$widget_id .microblogs .meta {color: #$metacolor}\n#mwrapper-$widget_id .microblogs .meta a {color: #$metacolor}\n";
        if (!empty($style))
            echo "<style type=\"text/css\" media=\"screen\">\n" . $style . "</style>\n";

        $output .= "<div class=\"mwrapper\" id=\"mwrapper-$widget_id\">";
        $head = '';
        $tweet_format = '';
        $meta_format = '';
        if (!$showtime || !$showfrom) {
            if ($showtime)
                $meta_format .= '<a href="[tweet_url]" rel="external nofollow">[time]</a>';
            if ($showfrom)
                $meta_format .= '来自 <a href="[user_url]" rel="external nofollow">[type]</a>';
            $meta_format = $meta_format ? '<div class="meta">' . $meta_format . '</div>' : '';
        } else
            $meta_format = '<div class="meta"><a href="[tweet_url]" rel="external nofollow">[time]</a> 来自 <a href="[user_url]" rel="external nofollow">[type]</a></div>';
        if ($headdisplay == 2)
            $tweet_format = '&tweet_format=<img class="thead" src="[user_head]"/>[text][pic][rt]' . $meta_format;
        else
            $tweet_format = '&tweet_format=[text][pic][rt]' . $meta_format;
        $timeformat = $timeformat ? "&time_format=$timeformat" : '';
        $content = wm_get_tweets(apply_filters('wm_widget_args', "mid=$mid&count=$count&relative=$relative$tweet_format$timeformat", $mid, $count, $relative));
        $content = '<div class="mcontainer">' . $content . '</div>';
        if (!$autoheight && $scroll == 0)
            $content = '<a class="scroll up" href="javascript:;"><img src="' . $wm_plugin_url . '/images/transparent.gif" /></a>' . $content . '<a class="scroll down" href="javascript:;"><img src="' . $wm_plugin_url . '/images/transparent.gif" /></a>';
        if ($headdisplay == 1) {
            if ($mid != 0)
                $headmid = $mid;
            $account = MicroblogOption::getAccount($headmid);
            $content = '<div class="head"><img src="' . $account['head'] . '" /><a href="' . wm_get_user_url($headmid) . '" rel="external nofollow">' . $account['nick'] . '</a><a href="' . wm_get_user_url($headmid) . '" class="wmpage" rel="external nofollow">微博主页</a></div>' . $content;
        }
        if (!$jsoutput)
            $output .= $content;
        $output .= '</div>';
        $output .= $after_widget;
        if ($jsoutput) {
            $output .= "<script type=\"text/javascript\">\n";
            $output .= "var mc = \"" . str_replace("\r", '', str_replace("\n", '', addslashes($content))) . "\";\n";
            $output .= "document.getElementById('mwrapper-$widget_id').innerHTML = mc;\n";
            $output .= "</script>";
        }
        // output finish
        echo $output;
    }

    function update($new_instance, $old_instance) {
        if (isset($old_instance['mid']))
            wm_delete_cache($old_instance['mid']);
        return $this->options_filter($new_instance);
    }

    function form($instance) {
        global $wpdb, $table_prefix;
        $options = $this->options_filter($instance);
        $ids = $this->ids();
        $names = $this->names();
        ?>
        <p>
            <label for="<?php echo $ids['title']; ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $ids['title']; ?>" name="<?php echo $names['title']; ?>" type="text" value="<?php echo $options['title']; ?>"/>
        </p>
        <p>
            <label for="<?php echo $ids['mid']; ?>">输出：</label>
            <select name="<?php echo $names['mid']; ?>" id="<?php echo $ids['mid']; ?>" class="widefat mid">
                <option value="0"<?php echo!$options['mid'] ? ' selected="selected"' : ''; ?>>所有微博</option>
                <?php
                $accounts = MicroblogOption::getAccounts();
                if ($accounts)
		                foreach ($accounts as $mid => $account) {
		                    if ($account['suspend'] == 0)
		                        echo "<option value=\"$mid\"" . ($options['mid'] == $mid ? ' selected="selected"' : '') . '>' . MicroblogOption::$screenName[$account['type']] . ' - ' . $account['nick'] . '</option>';
		                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $ids['count']; ?>">显示多少微博条目：</label>
            <input type="text" size="3" value="<?php echo $options['count']; ?>" name="<?php echo $names['count']; ?>" id="<?php echo $ids['count']; ?>" /><br />
            （ 最多 <?php echo MicroblogOption::get_option('count'); ?> 条 ）
        </p>
        <p>
            <input class="checkbox shrink" type="checkbox" id="<?php echo $ids['autowidth']; ?>" value="1"<?php echo $options['autowidth'] ? ' checked="checked"' : ''; ?> name="<?php echo $names['autowidth']; ?>" /><label for="<?php echo $ids['autowidth']; ?>">自动调整宽度</label><br />
            <span style="padding-left: 15px; display: <?php if ($options['autowidth']) echo 'none'; else echo 'block'; ?>;">
                <label for="<?php echo $ids['width']; ?>">宽度</label> <input type="text" size="3" value="<?php echo $options['width']; ?>" name="<?php echo $names['width']; ?>" id="<?php echo $ids['width']; ?>"> px<br />
            </span>
            <input class="checkbox shrink" type="checkbox" id="<?php echo $ids['autoheight']; ?>" value="1"<?php echo $options['autoheight'] ? ' checked="checked"' : ''; ?> name="<?php echo $names['autoheight']; ?>" /><label for="<?php echo $ids['autoheight']; ?>">自动调整高度</label><br />
            <span style="padding-left: 15px; display: <?php if ($options['autoheight']) echo 'none'; else echo 'block'; ?>;">
                <label for="<?php echo $ids['height']; ?>">高度</label> <input type="text" size="3" value="<?php echo $options['height']; ?>" name="<?php echo $names['height']; ?>" id="<?php echo $ids['height']; ?>" /> px<br />
                <label for="<?php echo $ids['scroll']; ?>">滚动样式：</label>
                <select class="value0" name="<?php echo $names['scroll']; ?>" id="<?php echo $ids['scroll']; ?>">
                    <option value="0"<?php echo $options['scroll'] == 0 ? ' selected="selected"' : ''; ?>>上下箭头</option>
                    <option value="1"<?php echo $options['scroll'] == 1 ? ' selected="selected"' : ''; ?>>滚动条</option>
                </select><br />
                <span style="display: <?php if ($options['scroll'] == 1) echo 'none'; else echo 'block'; ?>;">
                    <label for="<?php echo $ids['arrowcolor']; ?>">箭头颜色：</label>
                    <select name="<?php echo $names['arrowcolor']; ?>" id="<?php echo $ids['arrowcolor']; ?>">
                        <option value="0"<?php echo $options['arrowcolor'] == 0 ? ' selected="selected"' : ''; ?> style="background: #ccc;">灰</option>
                        <option value="1"<?php echo $options['arrowcolor'] == 1 ? ' selected="selected"' : ''; ?> style="background: #fff;">白</option>
                        <option value="2"<?php echo $options['arrowcolor'] == 2 ? ' selected="selected"' : ''; ?> style="background: #000; color: #fff;">黑</option>
                        <option value="3"<?php echo $options['arrowcolor'] == 3 ? ' selected="selected"' : ''; ?> style="background: #f00;">红</option>
                        <option value="4"<?php echo $options['arrowcolor'] == 4 ? ' selected="selected"' : ''; ?> style="background: #0f0;">绿</option>
                        <option value="5"<?php echo $options['arrowcolor'] == 5 ? ' selected="selected"' : ''; ?> style="background: #00f;">蓝</option>
                        <option value="6"<?php echo $options['arrowcolor'] == 6 ? ' selected="selected"' : ''; ?> style="background: #ff0;">黄</option>
                        <option value="7"<?php echo $options['arrowcolor'] == 7 ? ' selected="selected"' : ''; ?> style="background: #f0f;">紫</option>
                        <option value="8"<?php echo $options['arrowcolor'] == 8 ? ' selected="selected"' : ''; ?> style="background: #0ff;">青</option>
                        <option value="9"<?php echo $options['arrowcolor'] == 9 ? ' selected="selected"' : ''; ?> style="background: #f40;">橙</option>
                    </select>
                </span>
            </span>
        </p>
        <p id="<?php echo $this->get_field_id('tp') ?>">
            <label>背景颜色：#<input type="text" size="6" class="jscolor" value="<?php echo $options['bgcolor']; ?>" name="<?php echo $names['bgcolor']; ?>" id="<?php echo $ids['bgcolor']; ?>" /></label><br />
            <label>箭头底色：#<input type="text" size="6" class="jscolor" value="<?php echo $options['arrowbgcolor']; ?>" name="<?php echo $names['arrowbgcolor']; ?>" id="<?php echo $ids['arrowbgcolor']; ?>" /></label><br />
            <label>转发线色：#<input type="text" size="6" class="jscolor" value="<?php echo $options['rtbordercolor']; ?>" name="<?php echo $names['rtbordercolor']; ?>" id="<?php echo $ids['rtbordercolor']; ?>" /></label><br />
            <label>文字颜色：#<input type="text" size="6" class="jscolor" value="<?php echo $options['textcolor']; ?>" name="<?php echo $names['textcolor']; ?>" id="<?php echo $ids['textcolor']; ?>" /></label><br />
            <label>链接颜色：#<input type="text" size="6" class="jscolor" value="<?php echo $options['linkcolor']; ?>" name="<?php echo $names['linkcolor']; ?>" id="<?php echo $ids['linkcolor']; ?>" /></label><br />
            <label>信息颜色：#<input type="text" size="6" class="jscolor" value="<?php echo $options['metacolor']; ?>" name="<?php echo $names['metacolor']; ?>" id="<?php echo $ids['metacolor']; ?>" /></label><br />
            ( 空则继承默认设置 )<br />
        </p>
        <fieldset class="headdisplay" style="margin-bottom: 1em;">
            <legend>头像显示：</legend>
            <label><input class="radio" type="radio" value="0"<?php echo $options['headdisplay'] == 0 ? ' checked="checked"' : ''; ?> name="<?php echo $names['headdisplay']; ?>" />不显示头像</label><br />
            <label><input class="radio" type="radio" value="1"<?php echo $options['headdisplay'] == 1 ? ' checked="checked"' : ''; ?> name="<?php echo $names['headdisplay']; ?>" />在顶部显示头像与信息</label><br />
            <span class="headmid" style="padding-left: 15px;  display: <?php if (!($options['mid'] == 0 && $options['headdisplay'] == 1)) echo 'none'; else echo 'block'; ?>;">
                <label for="<?php echo $ids['headmid']; ?>">显示此微博：</label>
                <select name="<?php echo $names['headmid']; ?>" id="<?php echo $ids['headmid']; ?>" class="widefat">
                    <?php
                    $accounts = MicroblogOption::getAccounts();
                    if ($accounts)
		                    foreach ($accounts as $mid => $account) {
		                        if ($account['suspend'] == 0)
		                            echo "<option value=\"$mid\"" . ($options['headmid'] == $mid ? ' selected="selected"' : '') . '>' . MicroblogOption::$screenName[$account['type']] . ' - ' . $account['nick'] . '</option>';
		                    }
                    ?>
                </select>
            </span>
            <label><input class="radio" type="radio" value="2"<?php echo $options['headdisplay'] == 2 ? ' checked="checked"' : ''; ?> name="<?php echo $names['headdisplay']; ?>" />在每一条微博显示头像</label><br />
        </fieldset>
        <p>
            <input class="checkbox stretch" type="checkbox" id="<?php echo $ids['showtime']; ?>" value="1"<?php echo $options['showtime'] ? ' checked="checked"' : ''; ?> name="<?php echo $names['showtime']; ?>" /> <label for="<?php echo $ids['showtime']; ?>">显示时间</label><br />
            <span style="padding-left: 15px;  display: <?php if ($options['showtime'] == 0) echo 'none'; else echo 'block'; ?>;">
                <input class="checkbox" type="checkbox" id="<?php echo $ids['relative']; ?>" value="1"<?php echo $options['relative'] ? ' checked="checked"' : ''; ?> name="<?php echo $names['relative']; ?>" /> <label for="<?php echo $ids['relative']; ?>">显示相对时间</label><br />
                <label for="<?php echo $ids['timeformat']; ?>">时间格式：</label>
                <input type="text" value="<?php echo $options['timeformat']; ?>" name="<?php echo $names['timeformat']; ?>" id="<?php echo $ids['timeformat']; ?>" /><br />
            </span>
            <input class="checkbox" type="checkbox" id="<?php echo $ids['showfrom']; ?>" value="1"<?php echo $options['showfrom'] ? ' checked="checked"' : ''; ?> name="<?php echo $names['showfrom']; ?>" /> <label for="<?php echo $ids['showfrom']; ?>">显示来源</label><br />
            <!-- <input class="checkbox" type="checkbox" id="<?php echo $ids['jsoutput']; ?>" value="1"<?php echo $options['jsoutput'] ? ' checked="checked"' : ''; ?> name="<?php echo $names['jsoutput']; ?>" /> <label for="<?php echo $ids['jsoutput']; ?>">以 JS 动态输出，禁止搜索引擎收录</label> -->
        </p>
        <script type="text/javascript">
            jscolorbind(document.getElementById('<?php echo $this->get_field_id('tp'); ?>'));
        </script>
        <?php
    }

}
?>