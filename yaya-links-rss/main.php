<?php
/*
Plugin Name: 鸦鸦的插件之友链RSS聚合
Description: 友链RSS聚合 正文中输入短代码[yaya-links-rss]即可 使用WP自带的链接管理器（Links Manager）管理RSS地址
Author: crowya
Author URI: https://github.com/crowya
Version: 1.1
*/

// 当短代码被调用时运行的函数
function add_rss_button() {
    $html = '<link rel="stylesheet" type="text/css" href="' . plugins_url('yaya-links-rss/rss.css') . '" />';
    // 添加一个按钮
    $html .= '<button id="load-rss-btn" class="btn btn-primary">点击查看友友们的最新文章</button>';
    // 返回按钮和容器
    $html .= '<div id="links-rss-container"></div>';

    // 添加事件监听器
    $html .= "<script>
    
                var rss_btn = document.getElementById('load-rss-btn');
                
                function rss_btn_click() {
                    // 禁用按钮
                    rss_btn.disabled = true;
                
                    // 显示加载提示信息
                    rss_btn.textContent = 'RSS加载中，请稍候...';
                
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', '" . esc_url(admin_url('admin-ajax.php?action=load_links_rss')) . "', true); // 设置 AJAX 请求地址
                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 400) {
                            // 请求成功时将返回的内容插入到容器中
                            document.getElementById('links-rss-container').innerHTML = xhr.responseText;
                
                            // 隐藏按钮
                            rss_btn.style.display = 'none';
                        } else {
                            // 处理请求失败的情况
                            console.error('请求失败：' + xhr.statusText);
                            // 恢复按钮状态
                            rss_btn.disabled = false;
                            rss_btn.textContent = '点击查看友友们的最新文章';
                        }
                    };
                    xhr.onerror = function() {
                        // 处理请求错误的情况
                        console.error('请求错误');
                        // 恢复按钮状态
                        rss_btn.disabled = false;
                        rss_btn.textContent = '点击查看友友们的最新文章';
                    };
                    xhr.send();
                }
    
                if (rss_btn) {
                    rss_btn.addEventListener('click', rss_btn_click);
                }
                
                rss_btn_click();    //自动加载
                
            </script>";

    return $html;
}

// 注册短代码
add_shortcode('yaya-links-rss', 'add_rss_button');

// 添加用于处理 AJAX 请求的函数
add_action('wp_ajax_load_links_rss', 'load_links_rss');
add_action('wp_ajax_nopriv_load_links_rss', 'load_links_rss');

function load_links_rss() {
    // 生成内容的代码
    $category = 0;  // 链接分类
    $limit = 50;    // 显示条数
    include_once WP_PLUGIN_DIR.'/yaya-links-rss/links.php';
    $BFCLinks = new BFCLinks();
    $rssItems = $BFCLinks->getRss($category, $limit);

    $html = '<div class="wp-block-argon-collapse collapse-block shadow-sm" style="border-left-color:#4fd69c"><div class="collapse-block-title" style="background-color:#4fd69c33"><span><i class="fa fa-rss"></i> </span><span class="collapse-block-title-inner">以下是友友们的最新文章~</span><i class="collapse-icon fa fa-angle-down"></i></div><div class="collapse-block-body" style="">';
    $html .= '<div class="links-rss">';
    
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
    $html .= '<p style="text-align: center; margin-bottom: 0; opacity: 0.3;">友链RSS聚合插件由<a href="https://github.com/crowya/yaya-plugins-for-argon">鸦鸦</a>制作</p>';
    $html .= '</div></div>';

    // 输出 HTML 内容
    echo $html;

    // 结束响应
    wp_die();
}
?>
