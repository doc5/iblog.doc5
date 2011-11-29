<?php

/**
 * 生成静态页面
 *
 * @since 2009-7-9
 * @copyright http://www.114la.com
 */
!defined('PATH_ADMIN') && exit('Forbidden');

class mod_make_html
{

    public static $error_message = '';
    public static $show_process = true;
    private static $ok_num = 0;

    const OTHER_FOOTER_FILENAME = 'other_footer.htm';
    const OTHER_HEADER_FILENAME = 'other_header.htm';
    const READTIME_UPDATE_KEY = 'yl_make_html_realtime';

    /**
     * 首页静态化
     *
     * @return boolean
     */
    public static function make_html_index()
    {
        try
        {
            //生成普通首页
            $result = self::get_html_index();
            if (empty($result))
            {
                throw new Exception('首页生成失败', 10);
            }

            // 写文件
            $filename = PATH_ROOT . '/index.htm';
            if (false == mod_file::write($filename, $result, "wb+", 0))
            {
                throw new Exception('写文件 ' . $filename . ' (首页)失败', 10);
            }
            @chmod($filename, 0777);
            $filename = PATH_ROOT . '/index.html';
            if (false == mod_file::write($filename, $result, "wb+", 0))
            {
                throw new Exception('写文件 ' . $filename . ' (首页)失败', 10);
            }
            @chmod($filename, 0777);

            //生成宽屏首页
            $result = self::get_html_index('kp');
            if (empty($result))
            {
                throw new Exception('首页生成失败', 10);
            }

            // 写文件
            $filename = PATH_ROOT . '/kp.htm';
            if (false == mod_file::write($filename, $result, "wb+", 0))
            {
                throw new Exception('写文件 ' . $filename . ' (首页)失败', 10);
            }
            @chmod($filename, 0777);
            $filename = PATH_ROOT . '/kp.html';
            if (false == mod_file::write($filename, $result, "wb+", 0))
            {
                throw new Exception('写文件 ' . $filename . ' (首页)失败', 10);
            }
            @chmod($filename, 0777);

            //生成多个首页，数据库表ylmf_config的yl_mulindex字段，以|分开
            $yl_mulindex = mod_config::get_one_config('yl_mulindex');
            if (!empty($yl_mulindex))
            {
                foreach (explode('|', $yl_mulindex) as $indexname)
                {
                    if (eregi("^([a-z0-9]+).htm([l]?)$", $indexname))
                    {
                        $filename = PATH_ROOT . '/' . $indexname;
                        if (false == mod_file::write($filename, $result, "wb+", 0))
                        {
                            throw new Exception('写文件 ' . $filename . ' (首页)失败', 10);
                        }
                        @chmod($filename, 0777);
                    }
                }
            }
            return true;
        }
        catch (Exception $e)
        {
            self::$error_message = $e->getMessage();
            return false;
        }
    }

    /**
     * 静态化专题
     *
     * @param string $class_id[optional]
     * @return boolean
     */
    public static function make_html_zhuanti($key = '')
    {
        try
        {
            function_exists('set_time_limit') && @set_time_limit(200);

            app_db::query("SELECT `id`, `name`, `path` FROM `ylmf_zhuanti`");
            $topics = app_db::fetch_all();
            foreach ($topics as $topic) 
            {
                $result = self::get_html_zhuanti($topic['id']);
                if (empty($result))
                {
                    throw new Exception('专题页面生成 ' . $topic['name'] . '失败', 10);
                }

                // 写文件
                $path = empty($topic['path']) ? 'topic_' . $topic['id'] : $topic['path'];
                $filename = PATH_HTML . '/topic/' . $path . '/index.htm';
                if (false == mod_file::write($filename, $result, "wb+", 0))
                {
                    throw new Exception('写文件 ' . $filename . ' (专题页面)失败', 10);
                }
                @chmod($filename, 0777);
            }

            //生成专题的时候随便生成友情链接
            self::make_html_links();
            return true;
        }
        catch (Exception $e)
        {
            self::$error_message = $e->getMessage();
            return false;
        }
    }

    //生成所有城市导航
    public static function make_html_city()
    {
        try
        {
            function_exists('set_time_limit') && @set_time_limit(200);

            app_db::query("SELECT * FROM `ylmf_city_cityclass`");
            $rows = app_db::fetch_all();
            foreach ($rows as $row) 
            {
                $result = self::get_html_city($row['id']);
                if (empty($result)) 
                {
                    throw new Exception('城市页面生成 ' . $row['name'] . '失败', 10);
                }

                //写文件
                $path = empty($row['path']) ? 'city_' . $row['id'] : $row['path'];
                $filename = PATH_HTML . '/city/' . $path . '/index.htm';
                if (false == mod_file::write($filename, $result, "wb+", 0)) 
                {
                    throw new Exception('写文件' . $filename . ' (城市页面)失败', 10);
                }
                @chmod($filename, 0777);
            }
            // 生成首页调用JS
            app_db::query("SELECT id, name FROM `ylmf_city_cityclass`");
            $rows = app_db::fetch_all();
            foreach ($rows as $row) 
            {
                $id = intval($row['id']);
                $result = mod_city_cityclass::make_js($id);
            }
        }
        catch (Exception $e)
        {
            self::$error_message = $e->getMessage();
            return false;
        }

    }

    /**
     * 静态化友情链接
     *
     * @return boolean
     */
    public static function make_html_links()
    {
        try
        {
            function_exists('set_time_limit') && @set_time_limit(200);
            $result = self::get_html_links();
            if (empty($result))
                throw new Exception('友情链接页面生成 ' . $val . '失败', 10);

            // 写文件
            $filename = PATH_HTML . '/catalog/links.htm';
            if (false == mod_file::write($filename, $result, "wb+", 0))
            {
                throw new Exception('写文件 ' . $filename . ' (友情链接页面)失败', 10);
            }
            @chmod($filename, 0777);
            return true;
        }
        catch (Exception $e)
        {
            self::$error_message = $e->getMessage();
            return false;
        }
    }

    /**
     * 静态化所有分类导航
     *
     * @return boolean
     */
    public static function make_html_all_catalog()
    {
        try
        {
            function_exists('set_time_limit') && @set_time_limit(200);

            $rt = app_db::select('ylmf_class', 'classid, classname', 'parentid = 0');
            if (empty($rt))
            {
                throw new Exception('数据库中没有分类');
            }

            $pid = '';
            foreach ($rt as $row)
            {
                $pid .= $row['classid'] . ',';
            }
            unset($rt);

            $pid = substr($pid, 0, -1);

            $rs = app_db::select('ylmf_class', 'classid, classname', "parentid IN ({$pid}) ORDER BY parentid");
            if (empty($rs))
            {
                throw new Exception('数据库中没有子分类');
            }
            foreach ($rs as $class)
            {
                self::make_html_one_catalog($class['classid']);
            }

            // 更新时间
            $timestamp = time();
            app_db::update('ylmf_site', array('end' => 1), "endtime <> 0 AND endtime <= {$timestamp}");
            return true;
        }
        catch (Exception $e)
        {
            self::$error_message = $e->getMessage();
            return false;
        }
    }

    /**
     * 静态化某个分类
     *
     * @param int $class_id
     * @param int $class_name[optional]
     * @return boolean
     */
    public static function make_html_one_catalog($class_id, $class_name = '')
    {
        try
        {
            if ($class_id < 1)
            {
                throw new Exception('分类 ID 错误');
            }

            // 检查是否是一级分类
            $old = mod_class::get_a_class($class_id);
            if ($old['parentid'] == 0)
            {
                $old = mod_class::get_class_list_by_parent($class_id);
                if (!empty($old))
                {
                    foreach ($old as $tmp)
                    {
                        $result = self::make_html_one_catalog($tmp['classid'], $tmp['classname']);
                    }
                    return $result;
                }
            }
            else
            {
                $main_class_cache = mod_class::get_class_list();
                if (!preg_match('#^http[s]?://#i', $main_class_cache[$class_id]['path']))
                {
                    if (self::$show_process)
                    {
                        self::flush('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
                        self::flush($main_class_cache[$class_id]['classname'] . '……');
                    }

                    $result = self::get_html_catalog($class_id);
                    if (empty($result))
                    {
                        self::flush('<span style="color: red;">未添加网站，跳过</span>，');
                        self::flush('<br/>');
                        return true;
                    }
                    if (self::$show_process)
                    {
                        self::flush('<span style="color: green;">成功</span>，');
                        self::$ok_num++;
                        if (self::$ok_num >= 6)
                        {
                            self::flush('<br/>');
                            self::$ok_num = 0;
                        }
                    }
                }
                unset($main_class_cache);

                return true;
            }
        }
        catch (Exception $e)
        {
            self::$error_message = $e->getMessage();
            return false;
        }
    }

    /**
     * 生成首页 HTML
     *
     * @return string
     */
    private static function get_html_index($type = '')
    {
        $timestamp = $_SERVER['REQUEST_TIME'];
        $output = '';
        $dir_tpls_main = mod_config::get_one_config('yl_dirtplmain');
        empty($dir_tpls_main) && $dir_tpls_main = 'default';
        $path_tpls_main = PATH_TPLS_MAIN . '/' . $dir_tpls_main;


        /**
         * 取得页头
         */
        // title
        app_tpl::$instance = null; //初始化，之前是头中底部分开的，每fetch一次就要初始化一次
        app_tpl::assign('URL', URL, $path_tpls_main);
        app_tpl::assign('title', mod_config::get_one_config('yl_sysname'), $path_tpls_main);

        // top ad
        //app_tpl::assign('index_top_left', self::get_index_top_ad_left(), $path_tpls_main);
        //app_tpl::assign('index_top_center', self::get_index_top_ad_center(), $path_tpls_main);
        //app_tpl::assign('index_top_right', self::get_index_top_ad_right(), $path_tpls_main);
        // keyword, description
        app_tpl::assign('index_meta_keyword', mod_config::get_one_config('yl_metakeyword'), $path_tpls_main);
        app_tpl::assign('index_meta_description', mod_config::get_one_config('yl_metadescrip'), $path_tpls_main);

        /**
         * 主体
         */

        //首页广告代码 写入html
        mod_advert::update_cache_main_advert();
        mod_advert::update_cache_advert_js();
        $cache_main_advert = mod_advert::get_cache_main_advert();
        // 搜索框下方的广告
        if (!empty($cache_main_advert['footer']))
        {
            app_tpl::assign('advert_search_footer', $cache_main_advert['footer'], $path_tpls_main);
        }

        // 名站下方
        $index_text_ad = '';
        if (!empty($cache_main_advert['notice']))
        {
            foreach ($cache_main_advert['notice'] as $val)
            {
                $index_text_ad .= $val['code'] . '&nbsp;&nbsp;&nbsp;';
            }
            app_tpl::assign('advert_notice', $index_text_ad, $path_tpls_main);
        }

        // 网站分类
        $class_query = app_db::query('SELECT p.`classname` AS p_classname,
                                             s.`classid` AS s_classid, s.`classname` AS s_classname, s.`path` AS s_path
                                      FROM ylmf_class AS p
                                      INNER JOIN ylmf_class AS s ON s.parentid = p.classid
                                      WHERE p.parentid = 0
                                      ORDER BY p.displayorder, p.classid, s.displayorder');
        if (!empty($class_query))
        {
            $site_class = array();
            while ($row = app_db::fetch_one($class_query))
            {
                if ($row['s_path'] == '')
                {
                    $row['urlpath'] = URL_HTML . "/catalog/{$row['s_classid']}.htm";
                }
                elseif (preg_match("#^http[s]?://|\.htm$|\.html$#i", $row['s_path']))
                {
                    $row['urlpath'] = $row['s_path'];
                }
                else
                {
                    $row['urlpath'] = URL_HTML . '/' . $row['s_path'] . '/index.htm';
                }
                $site_class[$row['p_classname']][] = array('urlpath' => $row['urlpath'], 'classname' => $row['s_classname'], 'classname_len' => strlen($row['s_classname']));
            }
            app_tpl::assign('site_class', $site_class, $path_tpls_main);
            app_tpl::assign('yl_honghe', mod_config::get_one_config('yl_honghe'), $path_tpls_main);
            unset($site_class);
        }
        unset($class_query);

        /*
         * 实用工具 26个
         */
        $cache_main_index_tool = mod_index_tool::get_index_tool_list();
        if (!empty($cache_main_index_tool))
        {
            $tools = array();
            $tooldbtmp = $cache_main_index_tool;
            foreach ($tooldbtmp as $key => $tool)
            {
                $tools[] = array(
                    'name' => $tool['name'],
                    'url' => $tool['url'],
                    'color' => $tool['color'],
                );
            }
            app_tpl::assign('tools', array_slice($tools, 0, 22), $path_tpls_main);
            unset($tools);
        }
        unset($cache_main_index_tool);

        // 名站首行 mztop
        $mztop_list = mod_mztop::get_mztop_list();
        if ($mztop_list)
        {
            $mz_top = array();
            foreach ($mztop_list as $mztop)
            {
                if ($mztop['show'])
                {
                    $mz_top[] = $mztop;
                }
            }
            app_tpl::assign('mz_top', $mz_top, $path_tpls_main);
            unset($mz_top);
        }

        // 公告
        $notice_list = mod_notice::get_notice_list();
        if ($notice_list)
        {
            app_tpl::assign('notice_list', $notice_list, $path_tpls_main);
            unset($notice_list);
        }

        // 名站导航
        $class = app_db::select('ylmf_mingzhan', '*', "starttime <= {$timestamp} AND (endtime = 0 OR endtime >= {$timestamp})
                                                       ORDER BY displayorder, starttime, endtime LIMIT 48");
        if (!empty($class))
        {
            app_tpl::assign('mz_list', array_slice($class, 0, 48), $path_tpls_main);
            //名站2，不知道有什么用，反正在首页代码里面没看到相关代码
            if (count($class) >= 35)
            {
                app_tpl::assign('mz_list2', array_slice($class, 35, 5), $path_tpls_main);
            }
        }
        unset($class);

        // 轮播名站
        $cache_famous_loop = mod_famous_loop::get_famous_loop_list();
        if (!empty($cache_famous_loop))
        {
            foreach ($cache_famous_loop as $site)
            {
                if ($site['name'] == '')
                {
                    continue;
                }
                $style = ($site['color'] != '') ? " style=\"color: {$site['color']}\"" : '';
                $famous_loop .= "<li><a href=\"{$site['url']}\"{$style} target=\"_blank\">{$site['name']}</a></li>\n";
            }
        }
        app_tpl::assign('famous_loop', $famous_loop, $path_tpls_main);
        unset($famous_loop);
        unset($cache_famous_loop);

        // 名站导航切换栏
        app_tpl::assign('famous_tab', mod_famous_tab::get_famous_tab_list(), $path_tpls_main);

        //酷站导航 首页显示25个分类
        $cool_site_list = mod_cool_site::get_homepage_cool_site();
        if (!empty($cool_site_list))
        {
            $kz_list = array();
            foreach ($cool_site_list as $row)
            {
                $tmp = array();
                $tmp['name'] = $row['name'];
                $tmp['namecolor'] = $row['namecolor'];
                $tmp['url'] = $row['url'];
                $kz_list[$row['classname']]['son'][] = $tmp;
                unset($tmp);
                $kz_list[$row['classname']]['url'] = URL_HTML . '/' . $row['classurl'];
            }
            app_tpl::assign('kz_list', $kz_list, $path_tpls_main);
            unset($kz_list);
        }

        // 专题
        app_db::query("SELECT * FROM `ylmf_zhuanti` WHERE inindex = 1");
        $topics = app_db::fetch_all();
        if (!empty($topics))
        {
            $i=0;
            foreach ($topics as $topic)
            {
                $topics[$i]['path'] = empty($topic['path']) ? 'topic_'.$topic['id'] : $topic['path'];
                $limit = ' LIMIT 14';
                $rt = app_db::select('ylmf_zhuanticlass', '*', "zhuanti = '{$topic['id']}' AND inindex = 1 ORDER BY displayorder " . $limit);
                if (empty($rt))
                {
                    continue;
                }
                foreach ($rt as $info)
                {
                    $topics[$i]['son'][] = $info;
                }
                unset($rt);
                $i++;
            }
            unset($i);
            app_tpl::assign('topics', $topics, $path_tpls_main);
            unset($topics);
        }

        //友情链接
        $links = mod_links::get_links_list(TRUE);
        app_tpl::assign('links', $links, $path_tpls_main);

        //搜索引擎
        $search_class = mod_search_class::get_search_class_list();
        app_tpl::assign('search_class', $search_class, $path_tpls_main);
        $search = mod_search::get_search_list("", 1);
        app_tpl::assign('search', $search, $path_tpls_main);
        $search_keyword = mod_search_keyword::get_search_keyword_list();
        app_tpl::assign('search_keyword', $search_keyword, $path_tpls_main);
        /*
         * 取得主体页尾
         */
        app_tpl::assign('tongji', mod_config::get_one_config('yl_ipstat'), $path_tpls_main);
        app_tpl::assign('icp', mod_config::get_one_config('yl_icp'), $path_tpls_main);
        app_tpl::assign('icpurl', mod_config::get_one_config('yl_icpurl'), $path_tpls_main);
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        if ($type == 'kp')
        {
            $output = app_tpl::fetch('kp.tpl', $path_tpls_main);
        }
        else
        {
            $output = app_tpl::fetch('index.tpl', $path_tpls_main);
        }
        return $output;
    }

    /**
     * 生成专题 HTML
     *
     * @param string $type 专题名
     * @return string
     */
    private static function get_html_zhuanti($zhuanti_id)
    {
        /**
         * 初始化smarty 
        */
        app_tpl::$instance = null;

        $dir_tpls_main = mod_config::get_one_config('yl_dirtplmain');
        empty($dir_tpls_main) && $dir_tpls_main = 'default';
        $path_tpls_main = PATH_TPLS_MAIN . '/' . $dir_tpls_main . '/topic' ;
        app_tpl::assign('URL', URL, $path_tpls_main);
        //专题ID不为空 改专题下有专题分类
        if (empty($zhuanti_id) || app_db::get_rows_num('ylmf_zhuanticlass', "zhuanti = '{$zhuanti_id}'") < 1)
        {
            return false;
        }

        $timestamp = time();
        $output = '';

        //专题详细信息
        app_db::query("SELECT * FROM `ylmf_zhuanti` WHERE `id`='{$zhuanti_id}'");
        $topic = app_db::fetch_one();

        //SEO
        $title = (!empty($topic['name'])) ? $topic['name'] . '-' . mod_config::get_one_config('yl_sysname') :
        mod_config::get_one_config('yl_sysname');
        $keywords = empty($topic['keywords']) ? mod_config::get_one_config('yl_metakeyword') : $topic['keywords'];
        $description = empty($topic['description']) ? mod_config::get_one_config('yl_metadescrip') : $topic['description'];
        app_tpl::assign('title', $title, $path_tpls_main);
        app_tpl::assign('keywords', $keywords, $path_tpls_main);
        app_tpl::assign('description', $description, $path_tpls_main);

        // 您的位置
        if (!empty($topic['name']))
        {
            app_tpl::assign('current_class_name', $topic['name'], $path_tpls_main);
        }

        $site_list = $key_list = array();
        $query = app_db::query("SELECT * FROM ylmf_zhuanticlass WHERE `zhuanti` = '{$zhuanti_id}' ORDER BY displayorder");
        while ($info = app_db::fetch_one($query))
        {
            $tools_query = app_db::query("SELECT `id`, `class`, `name`, `url`, `namecolor` FROM ylmf_zhuantisite
                                            WHERE class='{$info['id']}' AND starttime <= {$timestamp}
                                            AND (endtime = 0 OR endtime >= {$timestamp}) ORDER BY displayorder");
            $tmp_tool_list = array();
            if (!empty($tools_query))
            {
                while ($tool = app_db::fetch_one($tools_query))
                {
                    $tool['domain'] = get_domain($tool['url']);
                    $tmp_tool_list[] = $tool;
                }
            }
            $site_list[$info['name']] = $tmp_tool_list;
            unset($tmp_tool_list, $tools_query);

            $info['url'] = '#' . $info['id'];
            $info['classname'] = $info['name'];
            $info['classid'] = $info['id'];
            $key_list[$info['classname']] = $info;
        }
        app_tpl::assign('key_list', $key_list, $path_tpls_main);
        app_tpl::assign('site_list', $site_list, $path_tpls_main);
        unset($query, $key_list, $site_list);

        app_tpl::assign('icp', mod_config::get_one_config('yl_icp'), $path_tpls_main);
        app_tpl::assign('icpurl', mod_config::get_one_config('yl_icpurl'), $path_tpls_main);
        app_tpl::assign('tongji', mod_config::get_one_config('yl_ipstat'), $path_tpls_main);
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        $tpl_file = empty($topic['template']) ? 'topic.tpl' : $topic['template'];
        $output .= app_tpl::fetch($tpl_file, $path_tpls_main);

        return $output;
    }

    /**
     * 生成城市导航 HTML
     *
     * @param string $id 城市ID
     * @return string
    */
    private static function get_html_city($id)
    {
        /**
         * 初始化smarty 
        */
        app_tpl::$instance = null;

        //load config
        $dir_tpls_main = mod_config::get_one_config('yl_dirtplmain');
        //defined
        empty($dir_tpls_main) && $dir_tpls_main = 'default';
        $path_tpls_main = PATH_TPLS_MAIN . '/' . $dir_tpls_main . '/city' ;
        app_tpl::assign('URL', URL, $path_tpls_main);

        //静态目录
        $staticfolder = empty($GLOBALS ['database'] ['staticfolder']) ? '/' : $GLOBALS ['database'] ['staticfolder'];
        app_tpl::assign('staticfolder', $staticfolder, $path_tpls_main);

        if (empty($id))
        {
            return false;
        }

        $timestamp = time();
        $output = '';

        //城市栏目详细信息
        app_db::query("SELECT * FROM `ylmf_city_cityclass` WHERE `id`='{$id}'");
        $city = app_db::fetch_one();
        // title
        $title = (!empty($city['name'])) ? $city['name'] . '-' . mod_config::get_one_config('yl_sysname') :
        mod_config::get_one_config('yl_sysname');
        app_tpl::assign('title', $title, $path_tpls_main);

        // 不填写 keywords 和 description 的时候调用系统默认
        $keywords = empty($city['keywords']) ? mod_config::get_one_config('yl_metakeyword') : $city['keywords'];
        $description = empty($city['description']) ? mod_config::get_one_config('yl_metadescrip') : $city['description'];
        app_tpl::assign('keywords', $keywords, $path_tpls_main);
        app_tpl::assign('description', $description, $path_tpls_main);

        // 您的城市
        if (!empty($city['name']))
        {
            app_tpl::assign('current_city', $city['name'], $path_tpls_main);
        }

        //名站
        app_db::query("SELECT * FROM `ylmf_city_mingzhan` WHERE `cityclass_id`='{$id}'");
        $mingzhan_list = app_db::fetch_all();
        //酷站
        app_db::query("SELECT * FROM `ylmf_city_coolclass`");
        $coolclass_list = app_db::fetch_all();
        $i=0;
        foreach ($coolclass_list as $coolclass) 
        {
            app_db::query("SELECT * FROM `ylmf_city_coolsite` WHERE coolclass_id='{$coolclass['id']}' AND cityclass_id='{$id}'");
            $coolclass_list[$i]['son'] = app_db::fetch_all();
            $i++;
        }
        unset($i);
        app_tpl::assign('mingzhan_list', $mingzhan_list, $path_tpls_main);
        app_tpl::assign('coolclass_list', $coolclass_list, $path_tpls_main);
        unset($coolclass_list, $mingzhan_list);

        /*
         * 实用工具 26个
         */
        $cache_main_index_tool = mod_index_tool::get_index_tool_list();
        if (!empty($cache_main_index_tool))
        {
            $tools = array();
            $tooldbtmp = $cache_main_index_tool;
            foreach ($tooldbtmp as $key => $tool)
            {
                $tools[] = array(
                    'name' => $tool['name'],
                    'url' => $tool['url'],
                    'color' => $tool['color'],
                );
            }
            app_tpl::assign('tools', array_slice($tools, 0, 22), $path_tpls_main);
            unset($tools);
        }
        unset($cache_main_index_tool);

        // 网站分类
        $class_query = app_db::query('SELECT p.`classname` AS p_classname,
                                             s.`classid` AS s_classid, s.`classname` AS s_classname, s.`path` AS s_path
                                      FROM ylmf_class AS p
                                      INNER JOIN ylmf_class AS s ON s.parentid = p.classid
                                      WHERE p.parentid = 0
                                      ORDER BY p.displayorder, p.classid, s.displayorder');
        if (!empty($class_query))
        {
            $site_class = array();
            while ($row = app_db::fetch_one($class_query))
            {
                if ($row['s_path'] == '')
                {
                    $row['urlpath'] = URL_HTML . "/catalog/{$row['s_classid']}.htm";
                }
                elseif (preg_match("#^http[s]?://|\.htm$|\.html$#i", $row['s_path']))
                {
                    $row['urlpath'] = $row['s_path'];
                }
                else
                {
                    $row['urlpath'] = URL_HTML . '/' . $row['s_path'] . '/index.htm';
                }
                $site_class[$row['p_classname']][] = array('urlpath' => $row['urlpath'], 'classname' => $row['s_classname'], 'classname_len' => strlen($row['s_classname']));
            }
            app_tpl::assign('site_class', $site_class, $path_tpls_main);
            app_tpl::assign('yl_honghe', mod_config::get_one_config('yl_honghe'), $path_tpls_main);
            unset($site_class);
        }
        unset($class_query);

        //其他城市
        app_db::query("SELECT * FROM `ylmf_city_cityclass` WHERE `id`!='{$id}'");
        $other_city_list = app_db::fetch_all();
        $i=0;
        foreach ($other_city_list as $other_list) 
        {
            $other_city_list[$i]['path'] = empty($other_list['path']) ? 'city_'.$other_list['id'] : $other_list['path'];
            $i++;
        }
        unset($i);
        app_tpl::assign('other_city_list', $other_city_list, $path_tpls_main);
        unset($other_city_list);

        app_tpl::assign('icp', mod_config::get_one_config('yl_icp'), $path_tpls_main);
        app_tpl::assign('icpurl', mod_config::get_one_config('yl_icpurl'), $path_tpls_main);
        app_tpl::assign('tongji', mod_config::get_one_config('yl_ipstat'), $path_tpls_main);
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        $tpl_file = empty($city['template']) ? 'city.tpl' : $city['template'];
        $output .= app_tpl::fetch($tpl_file, $path_tpls_main);

        return $output;
    }

    /**
     * 生成友情链接 HTML
     *
     * @return string
     */
    private static function get_html_links()
    {
        $dir_tpls_main = mod_config::get_one_config('yl_dirtplmain');
        empty($dir_tpls_main) && $dir_tpls_main = 'default';
        $path_tpls_main = PATH_TPLS_MAIN . '/' . $dir_tpls_main;
        app_tpl::assign('URL', URL, $path_tpls_main);

        $output = '';

        /**
         * 取得页头
         */
        app_tpl::$instance = null;
        // title
        $title = "友情链接" . '-' . mod_config::get_one_config('yl_sysname');
        app_tpl::assign('title', $title, $path_tpls_main);
        $class_meta_keyword = "友情链接-" . mod_config::get_one_config('yl_metakeyword');
        $class_meta_description = "友情链接-" . mod_config::get_one_config('yl_metadescrip');
        app_tpl::assign('class_meta_keyword', $class_meta_keyword, $path_tpls_main);
        app_tpl::assign('class_meta_description', $class_meta_description, $path_tpls_main);

        unset($title);


        // 您的位置
        app_tpl::assign('current_class_name', '友情连接', $path_tpls_main);
        $data = mod_links::get_links_list();
        app_tpl::assign('data', $data, $path_tpls_main);
        unset($data);

        /*
         * 取得主体页尾
         */

        app_tpl::assign('icp', mod_config::get_one_config('yl_icp'), $path_tpls_main);
        app_tpl::assign('icpurl', mod_config::get_one_config('yl_icpurl'), $path_tpls_main);
        app_tpl::assign('tongji', mod_config::get_one_config('yl_ipstat'), $path_tpls_main);
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        $output .= app_tpl::fetch('links.tpl', $path_tpls_main);

        return $output;
    }

    /**
     * 生成分类页面 HTML
     *
     * @param int $cid
     * @return void
     */
    private static function get_html_catalog($cid)
    {
        $timestamp = $_SERVER['REQUEST_TIME'];
        $output = '';
        $dir_tpls_main = mod_config::get_one_config('yl_dirtplmain');
        empty($dir_tpls_main) && $dir_tpls_main = 'default';
        $path_tpls_main = PATH_TPLS_MAIN . '/' . $dir_tpls_main . '/class';
        app_tpl::assign('URL', URL, $path_tpls_main);
        $cid = intval($cid);
        $class_list = mod_class::get_class_list();

        if ($cid < 0 || empty($class_list[$cid]) || !mod_class::get_subclass_list($cid))
        {
            return false;
        }

        /**
         * 取得页头
         */
        app_tpl::$instance = null;
        if (!empty($class_list[$cid]['classname']))
        {
            $class_name = $class_list[$cid]['classname'];
        }
        
        // 热门关键字
        //app_tpl::assign('search_keyword', self::get_hot_keyword(), $path_tpls_main);
        unset($title);


        // 主体内容
        $site_list = array(); //站点列表
        $key_list = array(); //分类导航

        $subclass_list = mod_class::get_subclass_list($cid);
        if (!empty($subclass_list))
        {
            foreach ($subclass_list as $info)
            {
                $site_query = app_db::query("SELECT `id`, `name`, `url`, `class`, `good`, `namecolor` FROM ylmf_site
                                             WHERE class = '{$info['classid']}' AND starttime <= {$timestamp}
                                                   /* AND (endtime = 0 OR endtime >= {$timestamp}) */ ORDER BY displayorder");
                $tmp_site = array();
                if (!empty($site_query))
                {
                    while ($site = app_db::fetch_one($site_query))
                    {
                        substr($site['url'], -1) == '/' && $site['url'] = substr($site['url'], 0, -1);
                        $site['good'] = (empty($site['good'])) ? '' : '<span style="color:#f00">√</span>';

                        if ($site['endtime'] > 0 && $timestamp > $site['endtime'])
                        {
                            $site['name'] = 'NULL';
                            $site['url'] = '#';
                        }
                        $site['domain'] = get_domain($site['url']);
                        $tmp_site[] = $site;
                    }
                }
                unset($result);

                // 查询是不是4级分类
                if (!mod_class::get_subclass_list($info['classid']))
                {
                    $info['url'] = '#' . $info['classid'];
                    $info['txtclass'] = '';
                    $key_list[$info['classname']] = $info;
                    $site_list[$info['classname']] = $tmp_site;
                }
                // 四级目录
                else
                {
                    //自定义路径
                    if (!empty($class_list[$info['classid']]['path']))
                    {
                        $filepath = $class_list[$info['classid']]['path'];
                        $filename = (preg_match("#\.htm[l]?$#i", $filepath)) ? $filepath : $filepath . '.htm';
                    }
                    else
                    {
                        $filename = $info['classid'] . '.htm';
                    }
                    $catalog_filename = 'catalog/' . $filename;

                    $parent_filepath = 'catalog';
                    if ($class_list[$cid]['path'] != '' && !preg_match('#^http[s]?://#i', $class_list[$cid]['path']))
                    {
                        $parent_filepath = $class_list[$cid]['path'];
                    }
                    $filename = $parent_filepath . '/' . $filename;
                    $info['url'] = URL_HTML . '/' . $filename;

                    $class4_query = app_db::query("SELECT classid, classname FROM ylmf_class WHERE parentid = '{$info['classid']}' ORDER BY displayorder");
                    $class4_list = array();
                    while ($tmp_class4 = app_db::fetch_one($class4_query))
                    {
                        $tmp_class4['name'] = $tmp_class4['classname'];
                        $tmp_class4['url'] = $info['url'] . '#' . $tmp_class4['classid'];
                        $class4_list[] = $tmp_class4;
                    }
                    $info['txtclass'] = 'class="mclass"';
                    $site_list[$info['classname']] = $class4_list;
                    $key_list[$info['classname']] = $info; //所有

                    /*
                     * 生成四级分类
                     */
                    $class4_cid = $info['classid'];
                    $class4_html = self::get_html_4catalog($class4_cid);


                    // 写文件
                    mod_file::write(PATH_HTML . '/' . $filename, $class4_html, 'wb+', 0);
                    @chmod(PATH_HTML . '/' . $filename, 0777);
                    mod_file::write(PATH_HTML . '/' . $catalog_filename, $class4_html, 'wb+', 0);
                    @chmod(PATH_HTML . '/' . $catalog_filename, 0777);
                    unset($class4_cid, $class4_html, $class4_list);
                    //$reurl = substr($filepath, 1);
                }
            }
        }
        app_tpl::assign('key_list', $key_list, $path_tpls_main);
        app_tpl::assign('site_list', $site_list, $path_tpls_main);
        unset($key_list, $site_list);

        // 您的位置
        $parent_id = $class_list[$cid]['parentid'];
        if (!empty($parent_id) && $class_list[$parent_id]['parentid'] != 0)
        {
            $parent_class_name = $class_list[$parent_id]['classname'];
            $parent_path = $class_list[$parent_id]['path'];

            // 注意自定义路径
            $parent_filepath = (empty($parent_path)) ? URL_HTML . '/catalog/' . $parent_id . '.htm' : URL_HTML . "/{$parent_path}/index.htm";
            app_tpl::assign('parent_class_name', "<a href=\"{$parent_filepath}\" >" . $parent_class_name . "</a>", $path_tpls_main);
        }
        if (!empty($class_list[$cid]['classname']))
        {
            app_tpl::assign('current_class_name', $class_list[$cid]['classname'], $path_tpls_main);
        }

        /*
         * 取得主体页尾
         */
        app_tpl::assign('icp', mod_config::get_one_config('yl_icp'), $path_tpls_main);
        app_tpl::assign('icpurl', mod_config::get_one_config('yl_icpurl'), $path_tpls_main);
        app_tpl::assign('tongji', mod_config::get_one_config('yl_ipstat'), $path_tpls_main);
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        //如果自定义了模版，使用自定义的模版，否则使用默认的 class.tpl 模版
        $template = empty($class_list[$cid]['template']) ? 'class.tpl' : $class_list[$cid]['template'];

        //meta
        $title = (!empty($class_name)) ? $class_name . '-' . mod_config::get_one_config('yl_sysname') : mod_config::get_one_config('yl_sysname');
        app_tpl::assign('title', $title, $path_tpls_main);
        // 内页不填写 keywords 和 description 的时候调用系统默认
        $class_meta_keyword = empty($class_list[$cid]['keywords']) ? mod_config::get_one_config('yl_metakeyword') : $class_list[$cid]['keywords'];
        $class_meta_description = empty($class_list[$cid]['description']) ? mod_config::get_one_config('yl_metadescrip') : $class_list[$cid]['description'];
        app_tpl::assign('class_meta_keyword', $class_meta_keyword, $path_tpls_main);
        app_tpl::assign('class_meta_description', $class_meta_description, $path_tpls_main);


        $output = app_tpl::fetch($template, $path_tpls_main);

        /*
         * 写文件
         */
        if ($class_list[$cid]['path'] != '' && !preg_match('#^http[s]?://#i', $class_list[$cid]['path']))
        {
            $path = PATH_HTML . '/' . $class_list[$cid]['path'];
            if (!is_dir($path))
            {
                @mkdir($path, 0777);
                @chmod($path, 0777);
            }
            $filename = $path . '/index.htm';
            if (false == mod_file::write($filename, $output, "wb+", 0))
            {
                throw new Exception('写文件 ' . $path . '/index.htm' . ' (分类导航)失败');
            }
            @chmod($filename, 0777);
        }

        //  补充 生成 catalog 下文件
        $filename = PATH_HTML . '/catalog/' . $cid . '.htm';
        if (false == mod_file::write($filename, $output, "wb+", 0))
        {
            throw new Exception('写文件 ' . $filename . ' (分类导航)失败');
        }
        @chmod($filename, 0777);

        return true;
    }

    /**
     * 生成4级分类 HTML
     *
     * @param int $cid
     * @return string
     */
    private static function get_html_4catalog($cid)
    {
        $cid = intval($cid);
        $dir_tpls_main = mod_config::get_one_config('yl_dirtplmain');
        empty($dir_tpls_main) && $dir_tpls_main = 'default';
        $path_tpls_main = PATH_TPLS_MAIN . '/' . $dir_tpls_main;
        app_tpl::assign('URL', URL, $path_tpls_main);
        $class_list = mod_class::get_class_list();
        if ($cid < 0 || empty($class_list[$cid]) || app_db::get_rows_num('ylmf_class', "parentid = '{$cid}'") < 1)
        {
            return false;
        }

        $output = '';
        $timestamp = time();

        /*
         * 取得页头
         */
        app_tpl::$instance = null;
        if (!empty($class_list[$cid]['classname']))
        {
            $class_name = $class_list[$cid]['classname']; // 2级目录
        }
        $title = (!empty($class_name)) ? $class_name . '-' . mod_config::get_one_config('yl_sysname') :
                mod_config::get_one_config('yl_sysname');
        app_tpl::assign('title', $title, $path_tpls_main);
        app_tpl::assign('class_meta_keyword', $class_list[$cid]['keywords'], $path_tpls_main);
        app_tpl::assign('class_meta_description', $class_list[$cid]['description'], $path_tpls_main);
        // 热门关键字
        //app_tpl::assign('search_keyword', self::get_hot_keyword(), $path_tpls_main);
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        $output .= app_tpl::fetch('class_header.tpl', $path_tpls_main);
        unset($title);

        /**
         * 您的位置
         */
        app_tpl::$instance = null;
        $parent_id = $class_list[$cid]['parentid'];
        if (!empty($parent_id))
        {
            $parent_class_name = $class_list[$parent_id]['classname'];

            // 注意自定义路径
            $parent_path = $class_list[$parent_id]['path'];
            $parent_filepath = (empty($parent_path)) ? URL_HTML . '/catalog/' . $parent_id . '.htm' : URL_HTML . "/{$parent_path}/index.htm";
            app_tpl::assign('parent_class_name', "<a href=\"{$parent_filepath}\" >" . $parent_class_name . "</a>", $path_tpls_main);
        }
        if (!empty($class_name))
        {
            app_tpl::assign('current_class_name', $class_name, $path_tpls_main);
        }

        $site_list = array(); //站点列表
        $key_list = array(); //分类导航

        $query = app_db::query('SELECT * FROM ylmf_class WHERE parentid = ' . $cid . ' ORDER BY displayorder');
        while ($info = app_db::fetch_one($query))
        {
            $result = app_db::select('ylmf_site', '`id`, `name`, `url`, `class`, `displayorder`, `good`, `namecolor`',
                            "class = '{$info['classid']}' AND  starttime <= {$timestamp}
                                                    /* AND (endtime = 0 OR endtime >= $timestamp) */ ORDER BY displayorder");
            $tmp_site = array();
            if (!empty($result))
            {
                foreach ($result as $site)
                {
                    substr($site['url'], -1) == '/' && $site['url'] = substr($site['url'], 0, -1);
                    $site['good'] = (empty($site['good'])) ? '' : '<span  style="color:#f00">√</span>';

                    if ($site['endtime'] > 0 && $timestamp > $site['endtime'])
                    {
                        $site['name'] = 'NULL';
                        $site['url'] = '#';
                    }
                    $site['domain'] = get_domain($site['url']);
                    $tmp_site[] = $site;
                }
            }
            $info['url'] = '#' . $info['classid'];
            $key_list[$info['classname']] = $info;
            $site_list[$info['classname']] = $tmp_site;
        }
        app_tpl::assign('key_list', $key_list, $path_tpls_main);
        app_tpl::assign('site_list', $site_list, $path_tpls_main);
        unset($rt, $key_list, $site_list);

        /*
         * 取得主体页尾
         */
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        $output .= app_tpl::fetch('class_body.tpl', $path_tpls_main);

        app_tpl::$instance = null;
        app_tpl::assign('icp', mod_config::get_one_config('yl_icp'), $path_tpls_main);
        app_tpl::assign('icpurl', mod_config::get_one_config('yl_icpurl'), $path_tpls_main);
        app_tpl::assign('tongji', mod_config::get_one_config('yl_ipstat'), $path_tpls_main);
        app_tpl::$instance->compile_id = mod_config::get_one_config('yl_dirtplmain');
        $output .= app_tpl::fetch('class_footer.tpl', $path_tpls_main);

        return $output;
    }

    /**
     * 获取首页顶部广告（左）
     *
     * @return string
     */
    private static function get_index_top_ad_left()
    {
        $tmp = app_db::select('ylmf_advert', 'varname', 'varname = "header_1" AND state = 1');
        if (empty($tmp))
        {
            return false;
        }
        $filename = PATH_ROOT . '/static/js/header_1.js';
        if (!file_exists($filename) || false == $output = file_get_contents($filename))
        {
            mod_advert::update_cache_advert_js();
            $output = @file_get_contents($filename);
        }
        return (!empty($output)) ? '<script type="text/javascript">' . $output . '</script>' : false;
    }

    /**
     * 获取首页顶部广告（中）
     *
     * @return string
     */
    private static function get_index_top_ad_center()
    {
        $tmp = app_db::select('ylmf_advert', 'varname', 'varname = "header_2" AND state = 1');
        if (empty($tmp))
        {
            return false;
        }

        $filename = PATH_ROOT . '/static/js/header_2.js';
        if (!file_exists($filename) || false == $output = file_get_contents($filename))
        {
            mod_advert::update_cache_advert_js();
            $output = @file_get_contents($filename);
        }
        return (!empty($output)) ? '<script type="text/javascript">' . $output . '</script>' : false;
    }

    /**
     * 获取首页顶部广告（右）
     *
     * @return string
     */
    private static function get_index_top_ad_right()
    {
        $tmp = app_db::select('ylmf_advert', 'varname', 'varname = "header_3" AND state = 1');
        if (empty($tmp))
        {
            return false;
        }

        $filename = PATH_ROOT . '/static/js/header_3.js';
        if (!file_exists($filename) || false == $output = file_get_contents($filename))
        {
            mod_advert::update_cache_advert_js();
            $output = @file_get_contents($filename);
        }
        return (!empty($output)) ? '<script type="text/javascript">' . $output . '</script>' : false;
    }

    /**
     * 生成全站
     *
     * @return 
     */
    public static function make_html_whole_site()
    {
        self::make_html_index();
        self::make_html_zhuanti();
        self::make_html_all_catalog();
        self::make_html_city();
        self::make_html_links();
        //self::make_html_other();
    }

    /**
     * 根据设置自动更新HTML type index:首页, catalog: 分类, zhuanti: 专题, other: 其他页面, all: 所有
     *
     *
     * @param stirng $type
     */
    public static function auto_update($type = 'index', $key = 0)
    {
        if (!mod_config::get_one_config(self::READTIME_UPDATE_KEY))
        {
            return false;
        }

        if ($type == 'index')
        {
            self::make_html_index();
        }
        elseif ($type == 'zhuanti')
        {
            self::make_html_zhuanti($key);
        }
        elseif ($type == 'catalog')
        {
            self::make_html_index();
            if ($key > 0)
            {
                self::make_html_one_catalog($key);
            }
            else
            {
                self::make_html_all_catalog();
            }
        }
        elseif ($type == 'order')
        {
            //self::make_html_other();
        }
        elseif ($type == 'all')
        {
            self::make_html_whole_site();
        }
    }

    /**
     * 即时输出提示信息
     *
     * @return 
     */
    public static function flush($msg)
    {
        echo $msg;
        ob_flush();
        flush();
    }

}

?>
