<?php
/*
  Plugin Name: WP-UTF8-Excerpt
  Version: 0.6.2
  Author: Betty
  Author URI: http://myfairland.net/
  Plugin URI: http://myfairland.net/wp-utf8-excerpt/
  Description: This plugin generates a better excerpt for multibyte language users (Chinese, for example). Besides, it keeps the html tags in the excerpt. 为使用多字节语言（如中文）的Wordpress用户提供更好的摘要算法，以解决Wordpress默认摘要算法只考虑西方语言的不足。此外，此插件产生的摘要可保留原文中的格式。
 */



/* if the host doesn't support the mb_ functions, we have to define them. From Yskin's wp-CJK-excerpt, thanks to Yskin. */
if (!function_exists('mb_strlen')) {

    function mb_strlen($text, $encode) {
        if ($encode == 'UTF-8') {
            return preg_match_all('%(?:
					  [\x09\x0A\x0D\x20-\x7E]           # ASCII
					| [\xC2-\xDF][\x80-\xBF]            # non-overlong 2-byte
					|  \xE0[\xA0-\xBF][\x80-\xBF]       # excluding overlongs
					| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
					|  \xED[\x80-\x9F][\x80-\xBF]       # excluding surrogates
					|  \xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
					| [\xF1-\xF3][\x80-\xBF]{3}         # planes 4-15
					|  \xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
					)%xs', $text, $out);
        } else {
            return strlen($text);
        }
    }

}

/* from Internet, author unknown */
if (!function_exists('mb_substr')) {

    function mb_substr($str, $start, $len = '', $encoding="UTF-8") {
        $limit = strlen($str);

        for ($s = 0; $start > 0; --$start) {// found the real start
            if ($s >= $limit)
                break;

            if ($str[$s] <= "\x7F")
                ++$s;
            else {
                ++$s; // skip length

                while ($str[$s] >= "\x80" && $str[$s] <= "\xBF")
                    ++$s;
            }
        }

        if ($len == '')
            return substr($str, $s);
        else
            for ($e = $s; $len > 0; --$len) {//found the real end
                if ($e >= $limit)
                    break;

                if ($str[$e] <= "\x7F")
                    ++$e;
                else {
                    ++$e; //skip length

                    while ($str[$e] >= "\x80" && $str[$e] <= "\xBF" && $e < $limit)
                        ++$e;
                }
            }

        return substr($str, $s, $e - $s);
    }

}


/* default option values */
define('HOME_EXCERPT_LENGTH', 300);
define('ARCHIVE_EXCERPT_LENGTH', 150);
define('ALLOWD_TAG', '<a><b><blockquote><br><cite><code><dd><del><div><dl><dt><em><h1><h2><h3><h4><h5><h6><i><img><li><ol><p><pre><span><strong><ul>');
define('READ_MORE_LINK', __('Read more', 'wp-utf8-excerpt'));


/* the core excerpt function */
if (!function_exists('utf8_excerpt')) {

    function utf8_excerpt($text, $type) {


        //get the full post content
        global $post;

        //whether it is hooked to the_excerpt or the_content
        switch ($type) {
            case 'content':
                //in this case, the passed parameter $text is the full content
                //get the manual excerpt
                $manual_excerpt = $post->post_excerpt;
                break;

            case 'excerpt':
                //in this case, the passed parameter $text is the manual excerpt
                $manual_excerpt = $text;

                //get and trim the full post content
                $text = $post->post_content;
                $text = str_replace(']]>', ']]&gt;', $text);
                $text = trim($text);
                break;

            default:
                break;
        }



        //only show excerpt on home and archive pages. search result page should be considered archive page.
        if (!is_home() && !is_archive() && !is_search()) {
            return $text;
        }

        //if there is manual excerpt, show the manual  excerpt
        if ('' !== $manual_excerpt) {
            $text = $manual_excerpt;
            $text = utf8_excerpt_readmore($text);
            return $text;
        }

        //if there is a <!--more--> tag, return stuff before that
        switch ($type) {
            case 'content':
                //in this case, the_content passes formatted content, which has turned <!--more--> tag into a link, which is in turn turned to special mark by me
                $more_position = stripos($text, 'UTF8_EXCERPT_HAS_MORE');
                if ($more_position !== false) {
                    //remove UTF8_EXCERPT_HAS_MORE at the end, which has 21 characters
                    $text = substr($text, 0, -21);
                    $text = utf8_excerpt_readmore($text);
                    return $text;
                }
                break;

            case 'excerpt':
                //in this case, I get the raw content with the <!--more--> tag
                $more_position = stripos($text, "<!--more-->");
                if ($more_position !== false) {
                    $text = substr($text, 0, $more_position);
                    $text = utf8_excerpt_readmore($text);
                    return $text;
                }
                break;

            default:
                break;
        }


        //get the options
        $home_excerpt_length = get_option('home_excerpt_length') ? get_option('home_excerpt_length') : HOME_EXCERPT_LENGTH;
        $archive_excerpt_length = get_option('archive_excerpt_length') ? get_option('archive_excerpt_length') : ARCHIVE_EXCERPT_LENGTH;
        $allowd_tag = get_option('allowd_tag') ? get_option('allowd_tag') : ALLOWD_TAG;
        if (is_home()) {
            $length = $home_excerpt_length;
        } elseif (is_archive() || is_search()) {
            $length = $archive_excerpt_length;
        }

        //will make this an option for the user to decide
        $strip_short_post = true;

        //if the post is already short and the user wants to strip tags
        if (($length > mb_strlen(strip_tags($text), 'utf-8')) && ($strip_short_post === true)) {
            $text = strip_tags($text, $allowd_tag);
            $text = trim($text);
            //$text = utf8_excerpt_readmore ($text);
            return $text;
        }

        //other cases
        $text = strip_tags($text, $allowd_tag);
        $text = trim($text);
        //check if the character is worth counting (ie. not part of an HTML tag). From Bas van Doren's Advanced Excerpt, thanks to Bas van Doren.
        $num = 0;
        $in_tag = false;
        for ($i = 0; $num < $length || $in_tag; $i++) {
            if (mb_substr($text, $i, 1) == '<')
                $in_tag = true;
            elseif (mb_substr($text, $i, 1) == '>')
                $in_tag = false;
            elseif (!$in_tag)
                $num++;
        }
        $text = mb_substr($text, 0, $i, 'utf-8');

        $text = trim($text);
        $text = utf8_excerpt_readmore($text);
        return $text;
    }

}

//check if the post has a <!--more--> tag
//the_content passes formatted content, which has turned <!--more--> tag into a link, so I have to leave a special mark
function utf8_excerpt_has_more($more) {
    if ('' !== $more) {
        return 'UTF8_EXCERPT_HAS_MORE';
    }
}

add_filter('the_content_more_link', 'utf8_excerpt_has_more');


// add a "read more" link
if (!function_exists('utf8_excerpt_readmore')) {

    function utf8_excerpt_readmore($text) {
        //get options
        $read_more_link = get_option('read_more_link') ? get_option('read_more_link') : READ_MORE_LINK;

        //add read_more_link
        $text .= " ......";
        $text = force_balance_tags($text);
        $text .= "<p class='read-more'><a href='" . get_permalink() . "'>" . $read_more_link . "</a></p>";
        return $text;
    }

}


//hook on the_excerpt hook
if (!function_exists('utf8_excerpt_for_excerpt')) {

    function utf8_excerpt_for_excerpt($text) {
        return utf8_excerpt($text, 'excerpt');
    }

}
add_filter('get_the_excerpt', 'utf8_excerpt_for_excerpt', 9);

//hook on the_content hook
if (!function_exists('utf8_excerpt_for_content')) {

    function utf8_excerpt_for_content($text) {
        return utf8_excerpt($text, 'content');
    }

}
add_filter('the_content', 'utf8_excerpt_for_content', 9);


/* the options  */

function utf8_excerpt_menu() {
    add_options_page(__('Excerpt Options', 'wp-utf8-excerpt'), __('Excerpt Options', 'wp-utf8-excerpt'), 8, __FILE__, 'utf8_excerpt_options');
}

function utf8_excerpt_options() {
    ?>
    <div class="wrap">
        <h2>
    <?php _e('Excerpt Options', 'wp-utf8-excerpt'); ?>
        </h2>

        <form name="form1" method="post" action="options.php">
    <?php wp_nonce_field('update-options'); ?>

            <!-- If the options are not set, load the default values.  -->
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Length of excerpts on homepage:', 'wp-utf8-excerpt'); ?></th>
                    <td><input type="text" name="home_excerpt_length" value="<?php echo get_option('home_excerpt_length') ? get_option('home_excerpt_length') : HOME_EXCERPT_LENGTH ?>" /><?php _e('characers', 'wp-utf8-excerpt'); ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Length of excerpts on archive pages:', 'wp-utf8-excerpt'); ?></th>
                    <td><input type="text" name="archive_excerpt_length" value="<?php echo get_option('archive_excerpt_length') ? get_option('archive_excerpt_length') : ARCHIVE_EXCERPT_LENGTH ?>"/><?php _e('characers', 'wp-utf8-excerpt'); ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Allow these HTML tags:', 'wp-utf8-excerpt'); ?></th>
                    <td><input type="text" name="allowd_tag" value="<?php echo get_option('allowd_tag') ? get_option('allowd_tag') : ALLOWD_TAG ?>" style="width:400px"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Display the "read more" link as:', 'wp-utf8-excerpt'); ?></th>
                    <td><input type="text" name="read_more_link" value="<?php echo get_option('read_more_link') ? get_option('read_more_link') : READ_MORE_LINK ?>" style="width:400px" /></td>
                </tr>

            </table>

            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="home_excerpt_length,archive_excerpt_length, allowd_tag, read_more_link" />

            <p class="submit">
                <input type="submit" class="button-primary" name="Submit" value="<?php _e('Save Changes', 'wp-utf8-excerpt') ?>" />
            </p>

        </form>
    </div>
    <?php
}

load_plugin_textdomain('wp-utf8-excerpt', false, basename(dirname(__FILE__)) . '/languages');
add_action('admin_menu', 'utf8_excerpt_menu');
?>