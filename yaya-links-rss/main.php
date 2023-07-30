<?php
/*
Plugin Name: 鸦鸦的插件之友链RSS聚合
Description: 友链RSS聚合 正文中输入短代码[yaya-links-rss]即可
Author: crowya
Author URI: https://github.com/crowya
Version: 1.0
*/

// 当短代码被调用时运行的函数
function get_links_rss() {
    
    $category = 0;  // 链接分类
    $limit = 50;    // 显示条数
    include_once WP_PLUGIN_DIR.'/yaya-links-rss/links.php';
    $BFCLinks = new BFCLinks();
    $rssItems = $BFCLinks->getRss($category, $limit);
    
    $html = '<link rel="stylesheet" type="text/css" href="' . plugins_url('yaya-links-rss/popover.css') . '" /><div class="links-rss">';
    
    foreach ($rssItems as $item) {
        $feed = $item->get_feed();
        $author = $item->get_author();
        $format = 'Y-m-d';
        $dateTimeUnix = (int) $item->get_date('U');
        $dateTimeOriginal = $item->get_date('');
        $dateZone = explode('+', $dateTimeOriginal);
        if (isset($dateZone[1])) {
            $dateZone = $dateZone[1];
            if ($dateZone != "0800") {
                $dateZone = (int) $dateZone[1];
                $dateZone = (800 - $dateZone) * 36;
                $dateTimeUnix = $dateTimeUnix + $dateZone;
            }
            if ($dateZone == "0800") {
                $dateTimeUnix = $dateTimeUnix + 28800;
            }
        }
        $dateTime = date($format, $dateTimeUnix);

        $html .= '
        <p>
            <span>'. esc_attr($dateTime) . '</span> &nbsp &nbsp 
            <a target="_blank" href="' . esc_attr($item->get_permalink()) .'" data-popover="' . wp_trim_words(sanitize_textarea_field($item->get_description()), 140, '...') . '">' . esc_attr($item->get_title()) . '</a>
            <span style="float:right;">' . esc_attr($feed->get_title()) . '</span>
        </p>';
    }
    
    $html .= '</div>';
    return $html;
}

// 注册短代码
add_shortcode('yaya-links-rss', 'get_links_rss');
?>