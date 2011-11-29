<?php

/**
 * 站内搜索
 *
 * @since 2009-7-11
 * @copyright http://www.114la.com
 */
require 'init.php';

header("content-type:text/html; charset=utf-8");

$keyword = trim(empty($_GET['keyword']) ? '' : $_GET['keyword']);
if (empty($keyword))
{
    exit;
}

//记录总数
$allrc = 55;

//关键字长度限制(传入的关键字只接受 [0-9 a-z . -] 和中文)
$maxlen = 20;

//更新搜索库网址的时间(24小时更新一次)
$lock_data = PATH_ROOT . '/admin/data/log/search_lock.txt';

//进行更新前写入锁定文件
$update_lock_file = PATH_ROOT . '/admin/data/log/search_update_lock.txt';
if( file_exists($update_lock_file) )
{
    exit('数据库正在维护中，请稍等...');
}

//unlink($lock_data);
//unlink($update_lock_file);
//exit;
//每天更新search库
if( !file_exists($lock_data) || date('Ymd', time()) > date('Ymd', filemtime($lock_data)) )
{
    //函数设置与客户机断开是否会终止脚本的执行,true：则忽略与用户的断开，false：会导致脚本停止运行，未设置该参数，直接返回当前的设置
    ignore_user_abort(true);
    set_time_limit(3600);
    file_put_contents($update_lock_file, time());
    $create_info = 'CREATE TABLE IF NOT EXISTS `ylmf_site_search` (
            `id` int(11) NOT NULL,
            `displayorder` INT NOT NULL ,
            `pinyin` VARCHAR( 255 ) NOT NULL ,
            `name` VARCHAR( 255 ) NOT NULL ,
            `url` VARCHAR( 255 ) NOT NULL ,
            PRIMARY KEY  (`id`),
            KEY `displayorder` (`displayorder`)
        ) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;
    ';
    app_db::query( $create_info );
    app_db::query( ' TRUNCATE TABLE `ylmf_site_search`  ' );
    $sql = "SELECT `id`, `displayorder`, `name`, `url` FROM `ylmf_site` order by `displayorder` ";
    app_db::query($sql);
    $list = app_db::fetch_all();

    foreach ($list as $row)
    {
        if($row['name'] === 'NULL')
        {
            continue;
        }
        $pys = mod_pinyin::get($row['name'], 'utf-8'); //针对UTF-8
        $pinyin = $pys['py'].' '.$pys['pinyin'];
        $name = addslashes( $row['name'] );
        $url = addslashes( $row['url'] );
        app_db::query(" Insert into `ylmf_site_search`(`id`, `displayorder`, `pinyin`, `name`, `url`)  Values('{$row['id']}', '{$row['displayorder']}', '{$pinyin}', '{$name}', '{$url}'); ");
    }
    unlink( $update_lock_file );
    file_put_contents($lock_data, time());
}

//分析字符
$skeyword = iconv('UTF-8', 'GB18030//IGNORE', $keyword);
$klen = strlen( $keyword );
if($klen==0 || $klen > $maxlen || ($klen == 1 && $keyword == '.'))
{
    exit;
}
$keyword = '';
$has_cn = false;
for($i=0; $i < $klen; $i++)
{
    $n = ord( $skeyword[$i] );
    if( $n < 128 )
    {
        if( preg_match('/[a-z0-9\.-]/i', $skeyword[$i]) )
        {
            $keyword .= $skeyword[$i];
        }
    }
    else
    {
        $has_cn = true;
        if( isset($skeyword[$i+1]) )
        {
            $keyword .= $skeyword[$i].$skeyword[$i+1];
            $i++;
        }
    }
}
if ( strlen($keyword) < 1 )
{
    exit;
}

//搜索站点
$output = $like_more = '';
//如果有中文只搜索name， 否则搜索拼音
if( $has_cn )
{
    //如果只有一个拼音或文字，只用 keyword% 形式搜索(前字匹配模式),否则用 %keyword%(任意匹配模式)
    if( strlen($keyword) > 2 )
    {
        $like_more = '%';
    }
    $keyword = @iconv('GB18030', 'UTF-8//IGNORE', $keyword); //针对UTF-8
    $rs = app_db::query("SELECT * FROM `ylmf_site_search` WHERE `name` LIKE '{$like_more}".$keyword."%' order by `displayorder` asc LIMIT  {$allrc} ");
    $result = app_db::fetch_all();
    //记录数太少，并且是前字匹配模式，改为任意匹配再查询
    $num_row = app_db::num_rows($rs);
    if( $num_row < 5 && $like_more == '')
    {
        $keyword = @iconv('GB18030', 'UTF-8//IGNORE', $keyword); //针对UTF-8
        $rs = app_db::query("SELECT * FROM `ylmf_site_search` WHERE `name` LIKE '%".$keyword."%' order by `displayorder` asc LIMIT  {$allrc} ");
        $result = app_db::fetch_all();
    }
}
else    //搜索拼音
{   
    if( strlen($keyword) > 1 )
    {
        $like_more = '%';
    }
    $rs = app_db::query("SELECT * FROM `ylmf_site_search` WHERE `pinyin` LIKE '{$like_more}".$keyword."%' order by `displayorder` asc LIMIT  {$allrc} ");
    $result = app_db::fetch_all();
}

$k = 0;
if ($result)
{
    foreach ($result as $row)
    {
        $ids[] = $row['id'];
        if( $has_cn === false )
        {
            $row['name'] = @iconv('UTF-8', 'GB18030//IGNORE', $row['name']); //针对UTF-8
            $row['showname'] = mod_pinyin::revert_pinyin( $keyword, $row['name'] );
            $row['name'] = @iconv('GB18030', 'UTF-8//IGNORE', $row['name']); //针对UTF-8
            $row['showname'] = @iconv('GB18030', 'UTF-8//IGNORE', $row['showname']); //针对UTF-8
        }
        else
        {
            $row['showname'] = preg_replace("/$keyword/is", mod_pinyin::highlight("\\0"), $row['name']);
        }

        $output .= "<li><a href='{$row['url']}' target='_blank' title='{$row['name']}'>{$row['showname']}</a></li>\n";
        $k++;
    }
}

//非中文关键字在记录不足情况下搜索网址
if( !$has_cn && $k < $allrc )
{
    $limit = $allrc - $k;
    if( !empty($ids) )
    {
        $ids = 'And (NOT id in('.join(',', $ids).'))';
    }
    else
    {
        $ids = '';
    }
    $rs = app_db::query("SELECT * FROM `ylmf_site_search` WHERE `url` LIKE '%".$keyword."%' {$ids} order by `displayorder` asc LIMIT  {$allrc} ");
    $result = app_db::fetch_all();
    if ($result)
    {
        foreach ($result as $row)
        {
            $row['showname'] = preg_replace("/$keyword/is", mod_pinyin::highlight("\\0"), $row['name']);
            $output .= "<li><a href='{$row['url']}' target='_blank' title='{$row['name']}'>{$row['showname']}</a></li>\n";
            $k++;
        }
    }
}
/*
$key = 0;
// 找站点
$rs = app_db::select('ylmf_site', 'name, url, namecolor, class', "name LIKE '%{$keyword}%' LIMIT 55");
if (!empty($rs))
{
    // 缓存
    $cache_main_class = mod_class::get_class_list();

    foreach ($rs as $row)
    {
        if (empty($cache_main_class[$row['class']]))
        {
            continue;
        }
        $pid = $cache_main_class[$row['class']]['parentid'];
        if (!empty($pid))
        {
            $classname = $cache_main_class[$pid]['classname'] . ' - ' . $cache_main_class[$row['class']]['classname'];
        }
        else
        {
            $classname = $cache_main_class[$row['class']]['classname'];
        }
        //关键字替换成红色字
        $row['showname'] = preg_replace("/($keyword)/is", '<font color="red">$1</font>', $row['name']);

        $style = (empty($row['namecolor'])) ? '' : " style=\"color:{$row['namecolor']}\"";
        $output .= "<li><a href=\"{$row['url']}\" target=\"_blank\" title=\"类别:{$classname}\r\n名称:{$row['name']}\"{$style}>{$row['showname']}</a></li>";
        $key++;
    }
}

$num = 38 - $key;
// 找工具
if ($num > 0)
{
    $rs = app_db::select('ylmf_tool', '*', "name LIKE '%{$keyword}%' LIMIT {$num}");
    if (!empty($rs))
    {

        $tool_class = mod_zhuanti_class::get_list();
        foreach ($rs as $row)
        {
            if (empty($tool_class['data'][$row['class']]))
            {
                continue;
            }
            $classname = $tool_class['data'][$row['class']]['name'];
            $row['showname'] = preg_replace("/($keyword)/is", '<font color="red">$1</font>', $row['name']);
            $output .= "<li><a href=\"{$row['url']}\" target=\"_blank\" title=\"类别:{$classname}\r\n名称:{$row['name']}\">{$row['showname']}</a></li>";
        }
    }
}*/

exit($output);
?>
