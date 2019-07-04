# phpspider -- PHP蜘蛛爬虫接口
由于公司项目需要根据寄售编号、邮编爬取https://www.xdp.co.uk/ 的物流数据，且我司未与该公司沟通合作开通相关查询接口，因此，对https://github.com/owner888/phpspider 项目进行了二次开发，删减了部分代码，修改为web爬虫接口，修改了日志存储策略，并将数据以json形式返回。

```
$configs = array(
	//爬虫名称
    'name' => 'xdp',
	//前台页面是否显示日志
    'log_show' => false,
	//记录日志类型，默认记录所有类型
    //'log_type' => 'error',
	//同时工作的爬虫任务数
    'tasknum' => 1,
	//保存爬虫运行状态
    //'save_running_state' => true,
	//爬虫爬取每个网页的超时时间，单位：秒
	'timeout' => 5,
    //爬虫爬取每个网页失败后尝试次数，网络不好可能导致爬虫在超时时间内抓取失败, 可以设置此项允许爬虫重复爬取
    'max_try' => 0,
    //设置爬取时间间隔为1秒,默认睡眠100毫秒,太快了会被认为是ddos
    //'interval' => 1000,
	//随机浏览器类型，用于破解防采集
	'user_agent' => array(
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36",
		"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:57.0) Gecko/20100101 Firefox/57.0",
		"Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Mobile Safari/537.36",
		"Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1",
		"Mozilla/5.0 (iPhone; CPU iPhone OS 10_3 like Mac OS X) AppleWebKit/602.1.50 (KHTML, like Gecko) CriOS/56.0.2924.75 Mobile/14E5239e Safari/602.1",
		"Mozilla/5.0 (iPad; CPU OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1",
		"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36",
		"Mozilla/5.0 (iPhone; CPU iPhone OS 9_3_3 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13G34 Safari/601.1",
		"Mozilla/5.0 (Linux; U; Android 6.0.1;zh_cn; Le X820 Build/FEXCNFN5801507014S) AppleWebKit/537.36 (KHTML, like Gecko)Version/4.0 Chrome/49.0.0.0 Mobile Safari/537.36 EUI Browser/5.8.015S",
	),
	//随机伪造IP，用于破解防采集
	'client_ip' => array(
		'192.168.0.2', 
		'192.168.1.3',
		'192.168.2.4',
		'192.168.3.5',
		'192.168.4.6',
		'192.168.5.7',
		'192.168.6.8',
		'192.168.7.9',
		'192.168.8.10',
		'192.168.9.11',
		'192.168.10.12',
	),
	//定义内容页url的规则
	/* 'content_url_regexes' => array(
		"https://www.xdp.co.uk/track.php?c=\[a-zA-Z0-9]+&code=(.*)",
	) */

	//定义内容页的抽取规则
    'fields' => array(
        //收货人姓名
        array(
            'name' => "Consignee_Name",
            'selector_type' => 'regex',
            'selector' => '#>Consignee Name</label>(.*?)<label#s',
            'required' => false,
        ),
        //公司
        array(
            'name' => "Company",
            'selector_type' => 'regex',
            'selector' => '#>Company</label>(.*?)<label#s',
            'required' => false,
        ),
        //邮政编码
        array(
            'name' => "Delivery_Postcode",
            'selector_type' => 'regex',
            'selector' => '#>Delivery Postcode</label>(.*?)<label#s',
            'required' => false,
        ),
        //参考信息
        array(
            'name' => "References",
            'selector_type' => 'regex',
            'selector' => '#>References</label>(.*?)</p>#s',
            'required' => false,
        ),
        //件数
        array(
            'name' => "Pieces",
            'selector_type' => 'regex',
            'selector' => '#>Pieces</label>(.*?)<label#s',
            'required' => false,
        ),
        //重量
        array(
            'name' => "Weight",
            'selector_type' => 'regex',
            'selector' => '#>Weight</label>(.*?)<label#s',
            'required' => false,
        ),
        //服务商
        array(
            'name' => "Service",
            'selector_type' => 'regex',
            'selector' => '#>Service</label>(.*?)</p>#s',
            'required' => false,
        ),
        //详细信息
        array(
            'name' => "Data",
            'selector_type' => 'regex',
            'repeated' => true,
            'selector' => '{<div class="xdp_content" style="background:#242427;padding:0;border:0">(.*?)</div>}s',
            'children' => array(
                array(
                    'name' => "Date/Time",
                    'selector_type' => 'regex',
                    'selector' => '{<td style=\"width:20%;text-align:left;color:#ebb84e\">(.*?)</td>}s',
                    'repeated' => false,
                ),
                array(
                    'name' => "Bar_Code",
                    'selector_type' => 'regex',
                    'selector' => '{<td style="width:15%;text-align:left">(.*?)</td>}s',
                    'repeated' => false,
                ),
                array(
                    'name' => "Location",
                    'selector_type' => 'regex',
                    'selector' => '{<td style="width:12%;text-align:left">(.*?)</td>   <td style="width:12%}s',
                    'repeated' => false,
                ),
                array(
                    'name' => "Status_Type",
                    'selector_type' => 'regex',
                    'selector' => '{<td style="width:12%;text-align:left">(?:.*?)</td>   <td style="width:12%;text-align:left">(.*?)</td>}s',
                    'repeated' => false,
                ),
                array(
                    'name' => "Status_Data",
                    'selector_type' => 'regex',
                    'selector' => '{<td style="width:41%;text-align:left;border-right:0">(.*?)</td>}s',
                    'repeated' => false,
                ),
            )
        )
    ),
);
```
爬虫的整体框架就是这样, 首先定义了一个$configs数组, 里面设置了待爬网站的一些信息, 然后通过调用```$spider = new phpspider($configs);```和```$spider->start();```来配置并启动爬虫.



更多详细内容，移步到：

[开发文档](http://doc.phpspider.org)
