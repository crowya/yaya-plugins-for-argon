<?php
/**
 * 链接类
 * @author 阿锋
 *
 */
class BFCLinks {
    // 获取链接参数
    protected $parsed_args;
    
    // 存储缓存信息标识（存储在option标中）
    protected $cacheInfoName;
    
    // 缓存信息
    protected $cacheInfo;
    
    // 缓存间隔，秒
    protected $cacheInterval;
    
    // 当前时间
    protected $currentTime;
    
    // 缓存目录
    protected $cachePath;
    
    /**
     * 初始化
     */
    public function __construct() {
        $this->parsed_args = [
            // 分类排序
            'category_orderby' => 'id',
            'category_order' => 'DESC',
            'category_before' => '<div class="alignfull" id="linkcat-[category id]"><div class="linkcat-title">',
            'category_after' => '</div></div>',
            // 分类标题
            'title_before' => '<h5>',
            'title_after' => '</h5>',
            
            // 链接排序
            'orderby' => 'rand',
            // 'order' => 'ASC',
            
            // 链接面板
            'before' => '<div class="linkcard">',
            'after' => '</div>',
            
            'link_before' => '<span class="oook">',
            'link_after' => '</span>',
            
            'between' => '<br /><br />',
            'show_images' => 1,
            'show_description' => 1,
            'show_name' => 1,
        ];
        // 定义缓存标识
        $this->cacheInfoName = 'bfc_link_rss_info';
        // 当前时间
        $this->currentTime = time();
        // 间隔时间
        $this->cacheInterval = 3600;
        $this->cachePath = WP_PLUGIN_DIR.'/yaya-links-rss/cache';
        
    }
    /**
     * 获取链接分类数据
     * @param [] $param 分类参数
     */
    public function getCats($param = []) {
        $parsed_args = array_merge($this->parsed_args, $param);
        $cats = get_terms(
            array(
                'taxonomy'     => 'link_category',
                'name__like'   => $parsed_args['category_name'],
                'include'      => $parsed_args['category'],
                'exclude'      => $parsed_args['exclude_category'],
                'orderby'      => $parsed_args['category_orderby'],
                'order'        => $parsed_args['category_order'],
                'hierarchical' => 0,
            )
        );
        return $cats;
    }
    
    /**
     * 获取链接列表数据
     * @param int|[] $category 链接分类
     */
    public function getLinks($category = null) {
        if (empty($category)) {
            $parsed_args = $this->parsed_args;
        }else {
            $parsed_args = array_merge( $this->parsed_args, ['category' => $category] );
        }
        $bookmarks = get_bookmarks( $parsed_args );
        return $bookmarks;
    }
    
    /**
     * 获取RSS聚合
     * @param [] $category 分类
     * @param int 数量
     */
    public function getRss($category = 0, $limit = 10) {
        $rssItems = [];
        
//         $page = get_query_var('paged', 1);
//         if ($page <= 0) {
//             $page = 1;
//         }
//         $start = ($page - 1) * $limit - 1;
//         if ($start < 0) {
//             $start = 0;
//         }
//         $end = ($page * $limit);

        $start = 0;
        $end = $limit;
        
        $links = $this->getLinks($category);
        if ($links) {
            $feedurls = [];
            foreach ($links as $link) {
                $feedurls[] = $link->link_rss;
            }
            include_once ABSPATH . WPINC . '/class-simplepie.php';
            $SP = new SimplePie();
            $SP->enable_cache(true);
            $SP->set_cache_location($this->cachePath);
            $SP->set_item_limit(5);
            $SP->set_feed_url($feedurls);
            $SP->init();
            $rssItems = $SP->get_items($start, $end);
            
        }
        return $rssItems;
    }
}