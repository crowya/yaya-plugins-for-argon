<?php 
/*
Plugin Name: 鸦鸦的插件之友链RSS聚合
Description: 正文中输入短代码 [yaya-links-rss] 即可，使用WP自带的链接管理器管理RSS地址。
Author: crowya
Author URI: https://github.com/crowya
Version: 1.2
*/

// === 可配置参数（建议根据需要调整） ===
define('YAYA_LINK_CATEGORY_ID', 0);          // WP链接管理器中的分类ID，0表示全部
define('YAYA_RSS_ITEMS_PER_FEED', 5);        // 每个RSS源最多提取条数
define('YAYA_FRONT_FIRST_LOAD', 30);         // 初始显示条数
define('YAYA_FRONT_EACH_LOAD', 10);           // 加载更多条数

// === 定义缓存目录与JSON缓存路径 ===
define('YAYA_CACHE_PATH', plugin_dir_path(__FILE__) . 'cache');
define('YAYA_CACHE_FILE', YAYA_CACHE_PATH . '/rss-cache.json');
define('YAYA_CACHE_LOCK', YAYA_CACHE_PATH . '/.cache-lock');

// === 创建缓存目录（如果不存在）===
if (!file_exists(YAYA_CACHE_PATH)) {
    mkdir(YAYA_CACHE_PATH, 0755, true);
}

// === 注册计划周期 ===
add_filter('cron_schedules', function($schedules) {
    $schedules['every_4_hours'] = [
        'interval' => 14400,
        'display'  => 'Every 4 Hours'
    ];
    return $schedules;
});

// === RSS抓取逻辑类 ===
class BFCLinks {
    protected $parsed_args;
    protected $cachePath;

    public function __construct() {
        $this->parsed_args = ['category' => YAYA_LINK_CATEGORY_ID];
        $this->cachePath = YAYA_CACHE_PATH;
    }

    public function getLinks() {
        return get_bookmarks($this->parsed_args);
    }

    public function getRss($limit = 999) {
        $rssItems = [];
        $links = $this->getLinks();
        $feedurls = [];

        foreach ($links as $link) {
            if (!empty($link->link_rss)) {
                $feedurls[] = $link->link_rss;
            }
        }

        if (!empty($feedurls)) {
            include_once ABSPATH . WPINC . '/class-simplepie.php';
            $sp = new SimplePie();
            $sp->enable_cache(true);
            $sp->set_cache_location($this->cachePath);
            $sp->set_item_limit(YAYA_RSS_ITEMS_PER_FEED); //每个RSS源最多 YAYA_RSS_ITEMS_PER_FEED 条
            $sp->set_feed_url($feedurls);
            $sp->init();
            $rssItems = $sp->get_items(0, $limit);
        }

        return $rssItems;
    }
}

// === 缓存生成函数 ===
function yaya_generate_rss_cache() {
    if (file_exists(YAYA_CACHE_LOCK)) return;

    file_put_contents(YAYA_CACHE_LOCK, time()); // 加锁

    $bfc = new BFCLinks();
    $rssItems = $bfc->getRss();  //获取所有RSS项
    $entries = [];

    foreach ($rssItems as $item) {
        $feed = $item->get_feed();
        $dateTimeUnix = (int)$item->get_date('U');
        $dateTimeOriginal = $item->get_date('');
        $dateZone = explode('+', $dateTimeOriginal);

        if (isset($dateZone[1])) {
            $zone = $dateZone[1];
            if ($zone != "0800") {
                $zone = (int)$zone[1];
                $zone = (800 - $zone) * 36;
                $dateTimeUnix += $zone;
            } else {
                $dateTimeUnix += 28800;
            }
        }

        $dateTime = date('Y-m-d', $dateTimeUnix);

        $entries[] = [
            'date' => $dateTime,
            'title' => esc_html($item->get_title()),
            'link' => esc_url($item->get_permalink()),
            'desc' => esc_attr(wp_trim_words(strip_tags($item->get_description()), 140, '...')),
            'feed' => esc_html($feed->get_title())
        ];
    }

    // 使用带格式化和中文不转义的写文件方式：
    file_put_contents(YAYA_CACHE_FILE, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    unlink(YAYA_CACHE_LOCK); // 解锁
}

// === 安排定时任务 ===
function yaya_setup_cron() {
    if (! wp_next_scheduled('yaya_refresh_rss_cache')) {
        $now = time();
        $interval = 4 * 3600;
        $next = ceil(($now + 600) / $interval) * $interval; // +600秒确保不会立即触发
        wp_schedule_event($next, 'every_4_hours', 'yaya_refresh_rss_cache');
    }
}

// === 定时任务执行 ===
add_action('yaya_refresh_rss_cache', 'yaya_generate_rss_cache');

// === 插件激活钩子 ===
register_activation_hook(__FILE__, function () {
    yaya_setup_cron();
});

// === 插件卸载钩子 ===
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('yaya_refresh_rss_cache');
});

// === AJAX处理函数：前端触发异步生成缓存 ===
add_action('wp_ajax_yaya_async_generate_cache', 'yaya_generate_rss_cache');
add_action('wp_ajax_nopriv_yaya_async_generate_cache', 'yaya_generate_rss_cache');

// === 短代码显示RSS聚合 ===
function yaya_display_rss_cache() {
    $css = '<link rel="stylesheet" type="text/css" href="' . plugins_url('rss.css', __FILE__) . '" />';
    $fallback = '<div id="yaya-loading-box"><i class="fa fa-spinner"></i><span>RSS加载中，请稍候…</span></div>';

    $jsonUrl = plugins_url('cache/rss-cache.json', __FILE__);
    $ajaxUrl = admin_url('admin-ajax.php');

    if (!file_exists(YAYA_CACHE_FILE)) {
        // 前端触发异步生成
        return $css . $fallback . <<<JS
<script>
fetch('$ajaxUrl?action=yaya_async_generate_cache')
  .then(() => {
    const reloadCheck = setInterval(() => {
      fetch('$jsonUrl', {cache: 'no-store'}).then(r => r.ok && r.json()).then(data => {
        if (data && data.length > 0) {
          clearInterval(reloadCheck);
          location.reload();
        }
      });
    }, 3000);
  });
</script>
JS;
    }

    $time = date('n月j日H:i', filemtime(YAYA_CACHE_FILE));

    $firstLoad = YAYA_FRONT_FIRST_LOAD;
    $eachLoad = YAYA_FRONT_EACH_LOAD;

    $html = <<<HTML
<div class="wp-block-argon-collapse collapse-block shadow-sm" style="border-left-color:#4fd69c">
<div class="collapse-block-title" style="background-color:#4fd69c33;font-weight:normal;">
    <span><i class="fa fa-rss"></i></span>
    <span class="collapse-block-title-inner">以下是友友们的最新文章~ </span>
    <span style="opacity:0.3;">更新于{$time}, Created by </span><a href="https://github.com/crowya/yaya-plugins-for-argon" target="_blank" style="opacity:0.6;">鸦鸦</a>
    <i class="collapse-icon fa fa-angle-down"></i>
</div>
<div class="collapse-block-body">
<div class="links-rss" id="yaya-rss-list"></div>
<div style="text-align:center; margin-top:10px;"><button id="yaya-load-more" class="btn btn-primary">加载更多</button></div>
</div>
</div>
<script>
fetch('$jsonUrl')
    .then(res => res.json())
    .then(data => {
        let yayaData = data;
        let yayaIndex = 0;
        let yayaFirst = $firstLoad;
        let yayaEach = $eachLoad;
        const yayaList = document.getElementById("yaya-rss-list");
        const yayaBtn = document.getElementById("yaya-load-more");

        function renderYayaItems(count) {
            let slice = yayaData.slice(yayaIndex, yayaIndex + count);
            slice.forEach(item => {
                let p = document.createElement('p');
                p.innerHTML = `<span>\${item.date}</span> &nbsp;&nbsp;&nbsp;<a target="_blank" href="\${item.link}" data-popover="\${item.desc}">\${item.title}</a><span style="float:right;">\${item.feed}</span>`;
                yayaList.appendChild(p);
            });
            yayaIndex += count;
            if (yayaIndex >= yayaData.length) {
                yayaBtn.style.display = 'none';
            }
        }

        renderYayaItems(yayaFirst);
        yayaBtn.onclick = () => renderYayaItems(yayaEach);
    });
</script>
HTML;

    return $css . $html;
}
add_shortcode('yaya-links-rss', 'yaya_display_rss_cache');
