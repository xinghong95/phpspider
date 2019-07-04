<?php
// +----------------------------------------------------------------------
// | PHPSpider [ A PHP Framework For Crawler ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 https://doc.phpspider.org All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Seatle Yang <seatle@foxmail.com>
// +----------------------------------------------------------------------

//----------------------------------
// PHPSpider核心类文件
//----------------------------------

namespace phpspider\core;

require_once __DIR__ . '/constants.php';

use phpspider\core\requests;
use phpspider\core\selector;
use phpspider\core\queue;
use phpspider\core\db;
use phpspider\core\util;
use phpspider\core\log;
use Exception;

// 启动的时候生成data目录
util::path_exists(PATH_DATA);
util::path_exists(PATH_DATA."/lock");
util::path_exists(PATH_DATA."/log");
util::path_exists(PATH_DATA."/cache");
util::path_exists(PATH_DATA."/status");

class phpspider
{
    /**
     * 版本号
     * @var string
     */
    const VERSION = '2.1.3';

    /**
     * 爬虫爬取每个网页的时间间隔,0表示不延时, 单位: 毫秒
     */
    const INTERVAL = 0;

    /**
     * 爬虫爬取每个网页的超时时间, 单位: 秒 
     */
    const TIMEOUT = 5;

    /**
     * 爬取失败次数, 不想失败重新爬取则设置为0 
     */
    const MAX_TRY = 0;

    /**
     * 爬虫爬取网页所使用的浏览器类型: pc、ios、android
     * 默认类型是PC
     */
    const AGENT_PC = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36";
    const AGENT_IOS = "Mozilla/5.0 (iPhone; CPU iPhone OS 9_3_3 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13G34 Safari/601.1";
    const AGENT_ANDROID = "Mozilla/5.0 (Linux; U; Android 6.0.1;zh_cn; Le X820 Build/FEXCNFN5801507014S) AppleWebKit/537.36 (KHTML, like Gecko)Version/4.0 Chrome/49.0.0.0 Mobile Safari/537.36 EUI Browser/5.8.015S";

    /**
     * pid文件的路径及名称
     * @var string
     */
    //public static $pid_file = '';

    /**
     * 日志目录, 默认在data根目录下
     * @var mixed
     */
    //public static $log_file = '';

    /**
     * 主任务进程ID 
     */
    //public static $master_pid = 0;

    /**
     * 所有任务进程ID 
     */
    //public static $taskpids = array();

    /**
     * Daemonize.
     *
     * @var bool
     */
    public static $daemonize = false;

    /**
     * 当前进程是否终止 
     */
    public static $terminate = false;

    /**
     * 当前服务器ID 
     */
    public static $serverid = 1;

    /**
     * 主任务进程 
     */
    public static $taskmaster = true;

    /**
     * 当前任务ID 
     */
    public static $taskid = 1;

    /**
     * 当前任务进程ID 
     */
    public static $taskpid = 1;

    /**
     * 并发任务数
     */
    public static $tasknum = 1;

    /**
     * 是否保存爬虫运行状态 
     */
    public static $save_running_state = false;

    /**
     * 配置 
     */
    public static $configs = array();

    /**
     * 要抓取的URL队列 
     md5(url) => array(
         'url'         => '',      // 要爬取的URL
         'url_type'    => '',      // 要爬取的URL类型,scan_page、list_page、content_page
         'method'      => 'get',   // 默认为"GET"请求, 也支持"POST"请求
         'headers'     => array(), // 此url的Headers, 可以为空
         'params'      => array(), // 发送请求时需添加的参数, 可以为空
         'context_data'=> '',      // 此url附加的数据, 可以为空
         'proxy'       => false,   // 是否使用代理
         'try_num'     => 0        // 抓取次数
         'max_try'     => 0        // 允许抓取失败次数
     ) 
     */
    public static $collect_queue = array();

    /**
     * 要抓取的URL数组
     * md5($url) => time()
     */
    public static $collect_urls = array();

    /**
     * 要抓取的URL数量
     */
    public static $collect_urls_num = 0;

    /**
     * 已经抓取的URL数量
     */
    public static $collected_urls_num = 0;

    /**
     * 当前进程采集成功数 
     */
    public static $collect_succ = 0;

    /**
     * 当前进程采集失败数 
     */
    public static $collect_fail = 0;

    /**
     * 提取到的字段数 
     */
    public static $fields_num = 0;

    /**
     * 爬虫开始时间 
     */
    public static $time_start = 0;

    // 导出类型配置
    public static $export_type = '';
    public static $export_file = '';
    public static $export_conf = '';
    public static $export_table = '';

    // 数据库配置
    public static $db_config = array();
    // 队列配置
    public static $queue_config = array();

    /**
     * 爬虫初始化时调用, 用来指定一些爬取前的操作 
     * 
     * @var mixed
     * @access public
     */
    public $on_start = null;

    /**
     * 网页状态码回调 
     * 
     * @var mixed
     * @access public
     */
    public $on_status_code = null;

    /**
     * 判断当前网页是否被反爬虫, 需要开发者实现 
     * 
     * @var mixed
     * @access public
     */
    public $is_anti_spider = null;

    /**
     * 在一个网页下载完成之后调用, 主要用来对下载的网页进行处理 
     * 
     * @var mixed
     * @access public
     */
    public $on_download_page = null;

    /**
     * 在一个attached_url对应的网页下载完成之后调用. 主要用来对下载的网页进行处理 
     * 
     * @var mixed
     * @access public
     */
    public $on_download_attached_page = null;

    /**
     * 在抽取到field内容之后调用, 对其中包含的img标签进行回调处理 
     * 
     * @var mixed
     * @access public
     */
    public $on_handle_img = null;

    /**
     * 当一个field的内容被抽取到后进行的回调, 在此回调中可以对网页中抽取的内容作进一步处理 
     * 
     * @var mixed
     * @access public
     */
    public $on_extract_field = null;

    /**
     * 在一个网页的所有field抽取完成之后, 可能需要对field进一步处理, 以发布到自己的网站 
     * 
     * @var mixed
     * @access public
     */
    public $on_extract_page = null;

    function __construct($configs = array())
    {
        // 产生时钟云，解决php7下面ctrl+c无法停止bug
        declare(ticks = 1);

        // 先打开以显示验证报错内容
        //log::$log_show = true;
        log::$log_file = isset($configs['log_file']) ? $configs['log_file'] : PATH_DATA.'/phpspider'.date("Ymd").'.log';
        log::$log_type = isset($configs['log_type']) ? $configs['log_type'] : false;

        $configs['name']       = isset($configs['name'])       ? $configs['name']       : 'phpspider';
        $configs['proxy']      = isset($configs['proxy'])      ? $configs['proxy']      : false;
        $configs['user_agent'] = isset($configs['user_agent']) ? $configs['user_agent'] : self::AGENT_PC;
        $configs['client_ip']  = isset($configs['client_ip'])  ? $configs['client_ip']  : array();
        $configs['interval']   = isset($configs['interval'])   ? $configs['interval']   : self::INTERVAL;
        $configs['timeout']    = isset($configs['timeout'])    ? $configs['timeout']    : self::TIMEOUT;
        $configs['max_try']    = isset($configs['max_try'])    ? $configs['max_try']    : self::MAX_TRY;
        $configs['max_depth']  = isset($configs['max_depth'])  ? $configs['max_depth']  : 0;
        $configs['max_fields'] = isset($configs['max_fields']) ? $configs['max_fields'] : 0;
        $configs['export']     = isset($configs['export'])     ? $configs['export']     : array();

        // csv、sql、db
        self::$export_type  = isset($configs['export']['type'])  ? $configs['export']['type']  : '';
        self::$export_file  = isset($configs['export']['file'])  ? $configs['export']['file']  : '';
        self::$export_table = isset($configs['export']['table']) ? $configs['export']['table'] : '';
        self::$db_config    = isset($configs['db_config'])       ? $configs['db_config']       : array();
        self::$queue_config = isset($configs['queue_config'])    ? $configs['queue_config']    : array();

        // 是否设置了保留运行状态
        if (isset($configs['save_running_state'])) 
        {
            self::$save_running_state = $configs['save_running_state'];
        }

        // 当前服务器ID
        if (isset($configs['serverid'])) 
        {
            self::$serverid = $configs['serverid'];
        }

        self::$configs = $configs;
    }

    public function get_config($name)
    {
        return empty(self::$configs[$name]) ? array() : self::$configs[$name];
    }

    public function start()
    {
        // 爬虫开始时间
        self::$time_start = time();
        // 当前任务ID
        self::$taskid = 1;
        // 当前任务进程ID
        self::$taskpid = function_exists('posix_getpid') ? posix_getpid() : 1;
        self::$collect_succ = 0;
        self::$collect_fail = 0;

        //--------------------------------------------------------------------------------
        // 运行前验证
        //--------------------------------------------------------------------------------

        // 检查PHP版本
        if (version_compare(PHP_VERSION, '5.3.0', 'lt')) 
        {
            log::error('PHP 5.3+ is required, currently installed version is: ' . phpversion());
            exit;
        }

        // 检查CURL扩展
        if(!function_exists('curl_init'))
        {
            log::error("The curl extension was not found");
            exit;
        }

        // 检查导出
        $this->check_export();

        // 放这个位置, 可以添加入口页面
        if ($this->on_start) 
        {
            call_user_func($this->on_start, $this);
        }

        // 开始采集
        $fields = $this->do_collect_page();

        if (empty(self::$configs['export']))
        {
            return $fields;
        }
    }

    public function do_collect_page() 
    {
        while( $queue_lsize = $this->queue_lsize() )
        {
            // 抓取页面
            $fields = $this->collect_page();

            if (empty(self::$configs['export']))
            {
                return $fields;
            }
        }
    }

    /**
     * 爬取页面
     * 
     * @param mixed $collect_url    要抓取的链接
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public function collect_page() 
    {
        // 先进先出
        $link = $this->queue_rpop();
        $link = $this->link_uncompress($link);
        $url = $link['url'];

        // 爬取页面开始时间
        $page_time_start = microtime(true);

        requests::$input_encoding = null;
        $html = $this->request_url($url, $link);

        if (!$html) 
        {
            return false;
        }
        // 当前正在爬取的网页页面的对象
        $page = array(
            'url'     => $url,
            'raw'     => $html,
            'request' => array(
                'url'          => $url,
                'method'       => $link['method'],
                'headers'      => $link['headers'],
                'params'       => $link['params'],
                'context_data' => $link['context_data'],
                'try_num'      => $link['try_num'],
                'max_try'      => $link['max_try'],
                'depth'        => $link['depth'],
                'taskid'       => self::$taskid,
            ),
        );
        //printf("memory usage: %.2f M\n", memory_get_usage() / 1024 / 1024 ); 
        unset($html);

        //--------------------------------------------------------------------------------
        // 处理回调函数
        //--------------------------------------------------------------------------------

        // 判断当前网页是否被反爬虫了, 需要开发者实现 
        if ($this->is_anti_spider) 
        {
            $is_anti_spider = call_user_func($this->is_anti_spider, $url, $page['raw'], $this);
            // 如果在回调函数里面判断被反爬虫并且返回true
            if ($is_anti_spider) 
            {
                return false;
            }
        }

        // 在一个网页下载完成之后调用. 主要用来对下载的网页进行处理.
        // 比如下载了某个网页, 希望向网页的body中添加html标签
        if ($this->on_download_page) 
        {
            $return = call_user_func($this->on_download_page, $page, $this);
            // 针对那些老是忘记return的人
            if (isset($return)) $page = $return;
        }

        // 如果是内容页, 分析提取HTML页面中的字段
        // 列表页也可以提取数据的, source_type: urlcontext, 未实现
        if ($link['url_type'] == 'content_page') 
        {
            $fields = $this->get_html_fields($page['raw'], $url, $page);
        }

        // 处理页面耗时时间
        $time_run = round(microtime(true) - $page_time_start, 3);
        log::debug("Success process page {$url} in {$time_run} s");

        $spider_time_run = util::time2second(intval(microtime(true) - self::$time_start));
        log::info("Spider running in {$spider_time_run}");

        // 爬虫爬取每个网页的时间间隔, 单位: 毫秒
        if (!isset(self::$configs['interval']))
        {
            // 默认睡眠100毫秒, 太快了会被认为是ddos
            self::$configs['interval'] = 100;
        }
        usleep(self::$configs['interval'] * 1000);
        if (empty(self::$configs['export']))
        {
            return $fields;
        }
    }

    /**
     * 下载网页, 得到网页内容
     * 
     * @param mixed $url
     * @param mixed $link
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public function request_url($url, $link = array())
    {
        $time_start = microtime(true);

        // 设置了编码就不要让requests去判断了
        if (isset(self::$configs['input_encoding'])) 
        {
            requests::$input_encoding = self::$configs['input_encoding'];
        }
        // 得到的编码如果不是utf-8的要转成utf-8, 因为xpath只支持utf-8
        requests::$output_encoding = 'utf-8';
        requests::set_timeout(self::$configs['timeout']);
        requests::set_useragent(self::$configs['user_agent']);
        if (self::$configs['client_ip']) 
        {
            requests::set_client_ip(self::$configs['client_ip']);
        }

        // 是否设置了代理
        if ($link['proxy']) 
        {
            requests::set_proxy($link['proxy']);
        }

        // 是否设置了 HTTP Headers
        if (!empty($link['headers'])) 
        {
            foreach ($link['headers'] as $k=>$v) 
            {
                requests::set_header($k, $v);
            }
        }

        $method = empty($link['method']) ? 'get' : strtolower($link['method']);
        $params = empty($link['params']) ? array() : $link['params'];
        $html = requests::$method($url, $params);
        // 此url附加的数据不为空, 比如内容页需要列表页一些数据, 拼接到后面去
        if ($html && !empty($link['context_data'])) 
        {
            $html .= $link['context_data'];
        }

        $http_code = requests::$status_code;

        if ($this->on_status_code) 
        {
            $return = call_user_func($this->on_status_code, $http_code, $url, $html, $this);
            if (isset($return)) 
            {
                $html = $return;
            }
            if (!$html) 
            {
                return false;
            }
        }

        if ($http_code != 200)
        {
            // 如果是301、302跳转, 抓取跳转后的网页内容
            if ($http_code == 301 || $http_code == 302) 
            {
                $info = requests::$info;
                //if (isset($info['redirect_url'])) 
                if (!empty($info['redirect_url'])) 
                {
                    $url = $info['redirect_url'];
                    requests::$input_encoding = null;
                    $method = empty($link['method']) ? 'get' : strtolower($link['method']);
                    $params = empty($link['params']) ? array() : $link['params'];
                    $html = requests::$method($url, $params);
                    // 有跳转的就直接获取就好，不要调用自己，容易进入死循环
                    //$html = $this->request_url($url, $link);
                    if ($html && !empty($link['context_data'])) 
                    {
                        $html .= $link['context_data'];
                    }
                }
                else 
                {
                    return false;
                }
            }
            else 
            {
                if ($http_code == 407) 
                {
                    // 扔到队列头部去, 继续采集
                    $this->queue_rpush($link);
                    log::error("Failed to download page {$url}");
                    self::$collect_fail++;
                }
                elseif (in_array($http_code, array('0','502','503','429'))) 
                {
                    // 采集次数加一
                    $link['try_num']++;
                    // 抓取次数 小于 允许抓取失败次数
                    if ( $link['try_num'] <= $link['max_try'] ) 
                    {
                        // 扔到队列头部去, 继续采集
                        $this->queue_rpush($link);
                    }
                    log::error("Failed to download page {$url}, retry({$link['try_num']})");
                }
                else 
                {
                    log::error("Failed to download page {$url}");
                    self::$collect_fail++;
                }
                log::error("HTTP CODE: {$http_code}");
                return false;
            }
        }

        // 爬取页面耗时时间
        $time_run = round(microtime(true) - $time_start, 3);
        log::debug("Success download page {$url} in {$time_run} s");
        self::$collect_succ++;

        return $html;
    }

    /**
     * 连接对象压缩
     * 
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-11-05 18:58
     */
    public function link_compress($link)
    {
        if (empty($link['url_type'])) 
        {
            unset($link['url_type']);
        }

        if (empty($link['method']) || strtolower($link['method']) == 'get') 
        {
            unset($link['method']);
        }

        if (empty($link['headers'])) 
        {
            unset($link['headers']);
        }

        if (empty($link['params'])) 
        {
            unset($link['params']);
        }

        if (empty($link['context_data'])) 
        {
            unset($link['context_data']);
        }

        if (empty($link['proxy'])) 
        {
            unset($link['proxy']);
        }

        if (empty($link['try_num'])) 
        {
            unset($link['try_num']);
        }

        if (empty($link['max_try'])) 
        {
            unset($link['max_try']);
        }

        if (empty($link['depth'])) 
        {
            unset($link['depth']);
        }
        //$json = json_encode($link);
        //$json = gzdeflate($json);
        return $link;
    }

    /**
     * 连接对象解压缩
     * 
     * @param mixed $link
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-11-05 18:58
     */
    public function link_uncompress($link)
    {
        $link = array(
            'url'          => isset($link['url'])          ? $link['url']          : '',             
            'url_type'     => isset($link['url_type'])     ? $link['url_type']     : '',             
            'method'       => isset($link['method'])       ? $link['method']       : 'get',             
            'headers'      => isset($link['headers'])      ? $link['headers']      : array(),    
            'params'       => isset($link['params'])       ? $link['params']       : array(),           
            'context_data' => isset($link['context_data']) ? $link['context_data'] : '',                
            'proxy'        => isset($link['proxy'])        ? $link['proxy']        : self::$configs['proxy'],             
            'try_num'      => isset($link['try_num'])      ? $link['try_num']      : 0,                 
            'max_try'      => isset($link['max_try'])      ? $link['max_try']      : self::$configs['max_try'],
            'depth'        => isset($link['depth'])        ? $link['depth']        : 0,             
        );

        return $link;
    }

    /**
     * 分析提取HTML页面中的字段
     * 
     * @param mixed $html
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public function get_html_fields($html, $url, $page) 
    {
        $fields = $this->get_fields(self::$configs['fields'], $html, $url, $page);
        if (!empty($fields)) 
        {
            if ($this->on_extract_page) 
            {
                $return = call_user_func($this->on_extract_page, $page, $fields);
                if (!isset($return))
                {
                    log::warn("on_extract_page return value can't be empty");
                }
                // 返回false，跳过当前页面，内容不入库
                elseif ($return === false)
                {
                    return false;
                }
                elseif (!is_array($return))
                {
                    log::warn("on_extract_page return value must be an array");
                }
                else 
                {
                    $fields = $return;
                }
            }

            if (isset($fields) && is_array($fields)) 
            {
                $fields_num = $this->incr_fields_num();
                if (self::$configs['max_fields'] != 0 && $fields_num > self::$configs['max_fields']) 
                {
                    exit(0);
                }

                if (version_compare(PHP_VERSION,'5.4.0','<'))
                {
                    $fields_str = json_encode($fields);
                    $fields_str = preg_replace_callback( "#\\\u([0-9a-f]{4})#i", function($matchs) {
                        return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
                    }, $fields_str ); 
                } 
                else
                {
                    $fields_str = json_encode($fields, JSON_UNESCAPED_UNICODE);
                }

                /*if (util::is_win())
                {
                    $fields_str = mb_convert_encoding($fields_str, 'gb2312', 'utf-8');
                }*/
                //log::info("Result[{$fields_num}]: ".$fields_str);

                // 如果设置了导出选项
                if (!empty(self::$configs['export'])) 
                {
                    self::$export_type = isset(self::$configs['export']['type']) ? self::$configs['export']['type'] : '';
                    if (self::$export_type == 'csv') 
                    {
                        util::put_file(self::$export_file, util::format_csv($fields)."\n", FILE_APPEND);
                    }
                    elseif (self::$export_type == 'sql') 
                    {
                        $sql = db::insert(self::$export_table, $fields, true);
                        util::put_file(self::$export_file, $sql.";\n", FILE_APPEND);
                    }
                    elseif (self::$export_type == 'db') 
                    {
                        db::insert(self::$export_table, $fields);
                    }
                }
                else
                {
                    return $fields_str;
                }
            }
        }
    }

    /**
     * 根据配置提取HTML代码块中的字段
     * 
     * @param mixed $confs
     * @param mixed $html
     * @param mixed $page
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-23 17:13
     */
    public function get_fields($confs, $html, $url, $page) 
    {
        $fields = array();
        foreach ($confs as $conf) 
        {
            // 当前field抽取到的内容是否是有多项
            $repeated = isset($conf['repeated']) && $conf['repeated'] ? true : false;
            // 当前field抽取到的内容是否必须有值
            $required = isset($conf['required']) && $conf['required'] ? true : false;

            if (empty($conf['name'])) 
            {
                log::error("The field name is null, please check your \"fields\" and add the name of the field\n");
                exit;
            }

            $values = NULL;
            // 如果定义抽取规则
            if (!empty($conf['selector'])) 
            {
                // 如果这个field是上一个field的附带连接
                if (isset($conf['source_type']) && $conf['source_type']=='attached_url') 
                {
                    // 取出上个field的内容作为连接, 内容分页是不进队列直接下载网页的
                    if (!empty($fields[$conf['attached_url']])) 
                    {
                        $collect_url = $this->fill_url($fields[$conf['attached_url']], $url);
                        log::debug("Find attached content page: {$collect_url}");
                        $link['url'] = $collect_url;
                        $link = $this->link_uncompress($link);
                        requests::$input_encoding = null;
                        //$method = empty($link['method']) ? 'get' : strtolower($link['method']);
                        //$params = empty($link['params']) ? array() : $link['params'];
                        //$html = requests::$method($collect_url, $params);
                        $html = $this->request_url($collect_url, $link);
                        // 在一个attached_url对应的网页下载完成之后调用. 主要用来对下载的网页进行处理.
                        if ($this->on_download_attached_page) 
                        {
                            $return = call_user_func($this->on_download_attached_page, $html, $this);
                            if (isset($return)) 
                            {
                                $html = $return;
                            }
                        }

                        // 请求获取完分页数据后把连接删除了 
                        unset($fields[$conf['attached_url']]);
                    }
                }

                // 没有设置抽取规则的类型 或者 设置为 xpath
                if (!isset($conf['selector_type']) || $conf['selector_type']=='xpath') 
                {
                    $values = $this->get_fields_xpath($html, $conf['selector'], $conf['name']);
                }
                elseif ($conf['selector_type']=='css') 
                {
                    $values = $this->get_fields_css($html, $conf['selector'], $conf['name']);
                }
                elseif ($conf['selector_type']=='regex') 
                {
                    $values = $this->get_fields_regex($html, $conf['selector'], $conf['name']);
                }

                // field不为空而且存在子配置
                if (isset($values) && !empty($conf['children'])) 
                {
                    // 如果提取到的结果是字符串，就转为数组，方便下面统一foreach
                    if (!is_array($values)) 
                    {
                        $values = array($values);
                    }
                    $child_values = array();
                    // 父项抽取到的html作为子项的提取内容
                    foreach ($values as $child_html) 
                    {
                        // 递归调用本方法, 所以多少子项目都支持
                        $child_value = $this->get_fields($conf['children'], $child_html, $url, $page);
                        if (!empty($child_value)) 
                        {
                            $child_values[] = $child_value;
                        }
                    }
                    // 有子项就存子项的数组, 没有就存HTML代码块
                    if (!empty($child_values)) 
                    {
                        $values = $child_values;
                    }
                }
            }

            if (!isset($values)) 
            {
                // 如果值为空而且值设置为必须项, 跳出foreach循环
                if ($required) 
                {
                    log::warn("Selector {$conf['name']}[{$conf['selector']}] not found, It's a must");
                    // 清空整个 fields，当前页面就等于略过了
                    $fields = array();
                    break;
                }
                // 避免内容分页时attached_url拼接时候string + array了
                $fields[$conf['name']] = '';
                //$fields[$conf['name']] = array();
            }
            else 
            {
                if (is_array($values)) 
                {
                    if ($repeated) 
                    {
                        $fields[$conf['name']] = $values;
                    }
                    else 
                    {
                        $fields[$conf['name']] = $values[0];
                    }
                }
                else 
                {
                    $fields[$conf['name']] = $values;
                }
                // 不重复抽取则只取第一个元素
                //$fields[$conf['name']] = $repeated ? $values : $values[0];
            }
        }

        if (!empty($fields)) 
        {
            foreach ($fields as $fieldname => $data) 
            {
                $pattern = "/<img.*src=[\"']{0,1}(.*)[\"']{0,1}[> \r\n\t]{1,}/isU";
                /*$pattern = "/<img.*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.jpeg|\.png]))[\'|\"].*?[\/]?>/i"; */
                // 在抽取到field内容之后调用, 对其中包含的img标签进行回调处理
                if ($this->on_handle_img && preg_match($pattern, $data)) 
                {
                    $return = call_user_func($this->on_handle_img, $fieldname, $data);
                    if (!isset($return))
                    {
                        log::warn("on_handle_img return value can't be empty\n");
                    }
                    else 
                    {
                        // 有数据才会执行 on_handle_img 方法, 所以这里不要被替换没了
                        $data = $return;
                    }
                }

                // 当一个field的内容被抽取到后进行的回调, 在此回调中可以对网页中抽取的内容作进一步处理
                if ($this->on_extract_field) 
                {
                    $return = call_user_func($this->on_extract_field, $fieldname, $data, $page);
                    if (!isset($return))
                    {
                        log::warn("on_extract_field return value can't be empty\n");
                    }
                    else 
                    {
                        // 有数据才会执行 on_extract_field 方法, 所以这里不要被替换没了
                        $fields[$fieldname] = $return;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * 验证导出
     * 
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-10-02 23:37
     */
    public function check_export()
    {
        if (!empty(self::$configs['export']))
        {
            if (self::$export_type == 'csv')
            {
                if (empty(self::$export_file))
                {
                    log::error("Export data into CSV files need to Set the file path.");
                    exit;
                }
            }
            elseif (self::$export_type == 'sql')
            {
                if (empty(self::$export_file))
                {
                    log::error("Export data into SQL files need to Set the file path.");
                    exit;
                }
            }
            elseif (self::$export_type == 'db')
            {
                if (!function_exists('mysqli_connect'))
                {
                    log::error("Export data to a database need Mysql support, unable to load mysqli extension.");
                    exit;
                }

                if (empty(self::$db_config))
                {
                    log::error("Export data to a database need Mysql support, you have not set a config array for connect.");
                    exit;
                }

                $config = self::$db_config;
                @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
                if(mysqli_connect_errno())
                {
                    log::error("Export data to a database need Mysql support, ".mysqli_connect_error());
                    exit;
                }

                db::set_connect('default', $config);
                db::init_mysql();

                if (!db::table_exists(self::$export_table))
                {
                    log::error("Table ".self::$export_table." does not exist");
                    exit;
                }
            }
        }
    }

    /**
     * 从队列右边插入
     * 
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-23 17:13
     */
    public function queue_rpush($link = array(), $allowed_repeat = false)
    {
        if (empty($link) || empty($link['url'])) 
        {
            return false;
        }

        $url = $link['url'];

        $status = false;
        $key = md5($url);
        if (!array_key_exists($key, self::$collect_urls))
        {
            self::$collect_urls_num++;
            self::$collect_urls[$key] = time();
            array_unshift(self::$collect_queue, $link);
            $status = true;
        }
        return $status;
    }

    /**
     * 从队列右边取出
     * 
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-23 17:13
     */
    public function queue_rpop()
    {
        $link = array_shift(self::$collect_queue);
        return $link;
    }

    /**
     * 队列长度
     * 
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-23 17:13
     */
    public function queue_lsize()
    {
        $lsize = count(self::$collect_queue);
        return $lsize;
    }

    /**
     * 提取到的field数目加一
     * 
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-23 17:13
     */
    public function incr_fields_num()
    {
        self::$fields_num++;
        $fields_num = self::$fields_num;
        return $fields_num;
    }

    /**
     * 采用xpath分析提取字段
     * 
     * @param mixed $html
     * @param mixed $selector
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public function get_fields_xpath($html, $selector, $fieldname) 
    {
        $result = selector::select($html, $selector);
        if (selector::$error)
        {
            log::error("Field(\"{$fieldname}\") ".selector::$error."\n");
        }
        return $result;
    }

    /**
     * 采用正则分析提取字段
     * 
     * @param mixed $html
     * @param mixed $selector
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public function get_fields_regex($html, $selector, $fieldname) 
    {
        $result = selector::select($html, $selector, 'regex');
        if (selector::$error) 
        {
            log::error("Field(\"{$fieldname}\") ".selector::$error."\n");
        }
        return $result;
    }

    /**
     * 采用CSS选择器提取字段
     * 
     * @param mixed $html
     * @param mixed $selector
     * @param mixed $fieldname
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public function get_fields_css($html, $selector, $fieldname) 
    {
        $result = selector::select($html, $selector, 'css');
        if (selector::$error) 
        {
            log::error("Field(\"{$fieldname}\") ".selector::$error."\n");
        }
        return $result;
    }
}


