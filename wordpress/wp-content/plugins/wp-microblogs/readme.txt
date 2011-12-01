=== WP Microblogs ===
Contributors: raychow
Donate link: http://beamnote.com/
Tags: microblog, widgets, sidebar, twitter, sina, tencent, fanfou
Requires at least: 2.9.2
Tested up to: 3.2.1
Stable tag: 0.3.3
License: GPLv2 or later

WP Microblogs displays the latest microblog in WordPress.

== Description ==

WP Microblogs displays the latest microblog in WordPress, support for Twitter, Sina Weibo, Tencent Weibo, fanfou
 and other microblogs.

**Chinese version ONLY temporarily**.

WP Microblogs 可以在 WordPress 中显示最新微博，目前支持新浪微博、腾讯微博、Twitter、网易微博、搜狐微博、豆瓣、嘀咕、饭否、做啥、人间
除 XAuth 之外的所有可用的认证方式对于更加开放的微博（例如 Twitter、嘀咕、饭否、做啥），只输入用户名即可展示微博。

在目前的版本中，至少已经包含下列功能：

*   提供一种直接展示最新微博的小工具；
*   智能过滤重复微博，为微博中提到的 URL 添加链接；
*   使用 `wm_tweet()`、`wm_tweets()`(函数) 或 `[wm_tweet]`、`[wm_tweets]`(短代码) 在指定位置展示最新的一条或数条微博；
*   使用 `wm_get_tweet_arr()` 或 `wm_get_tweets_arr()` 获得微博原始数据；
*   较完善的缓存机制，减少资源占用；
*   提供数个过滤器(filter)与动作(action)自定义展示方式。

== Installation ==

= English =
1. Upload `wp-microblogs` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Add microblog accounts in 'WP Microblogs' panel
1. Add microblog widgets

= 中文 =
2. 上传 `wp-microblogs` 文件夹到 `/wp-content/plugins/` 目录
2. 在插件面板中激活插件
2. 在 'WP Microblogs' 面板中添加微博帐号
2. 在小工具面板中添加"微博"小工具

== Changelog ==

= 0.1.0 =
*  0.1.0 Released.

= 0.1.4 =
*  修正腾讯微博未登录时获取 OAuth 授权时的错误；
*  增加调用接口：头像。

= 0.2.0 =
*  增加了小工具自定义功能，目前可以自定义尺寸、颜色与头像显示方式；
*  代码优化。

= 0.2.5 =
*  增加小工具滚动样式选择；
*  增加 JS 输出微博功能（测试中）；
*  修正小工具输出的错误；
*  修正 IE6 兼容性。

= 0.2.8 =
*  增加豆瓣广播支持；
*  修正 JS 输出的问题；
*  增加两个短代码：`[wm_tweet]` 与 `[wm_tweets]`，在文章与页面中直接插入即可显示最新微博。

= 0.2.9 =
*  后台增加缓存更新时间预告；
*  支持到 WordPress 3.1；
*  优化缓存更新逻辑；
*  增加卸载选项；
*  修正后台部分选项无法修改的问题；
*  修正 WordPress 2.9.2 后台无法添加帐号的问题。

= 0.3.0 =
*  增加小工具微博时间与来源开关；
*  修正潜在的 OAuth 类冲突问题（可以与其它微博插件共存）。

= 0.3.1 =
*  修正潜在的无法显示微博的问题；
*  提供一个简单的测试程序，用于帮助寻找插件不工作的原因。

= 0.3.3 =
*  修正新浪微博 ID 记录为科学记数法的问题；
*  其它关于新浪微博平台升级的修正。