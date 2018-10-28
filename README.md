# eshelper
基于 elasticsearch 的 PHP 简化查询以及实现了 Elasticsearch 索引结构的无缝迁移的组件

## 安装
```bash
$ composer require sevenshi/eshelper -vvv
$ php artisan vendor:publish
```
然后选择sevehis/eshelper，按回车

## 配置
因为本插件基于elasticsearch/elasticsearch，因此需要在laravel进行配置
Elasticsearch 的配置很简单，我们只需要 Elasticsearch 服务器的 IP 和端口即可：

- config/database.php
```bash
.
.
.
    'elasticsearch' => [
        // Elasticsearch 支持多台服务器负载均衡，因此这里是一个数组
        'hosts' => explode(',', env('ES_HOSTS')),
    ]
```

我们本地环境的 Elasticsearch 的 IP 和端口是 localhost:9200，如果端口是 9200 则可以忽略不写：

- .env
```bash
.
.
.
ES_HOSTS=localhost
```


## 使用

### 索引结构的定义以及无缝迁移

#### 定义索引结构类

在app\Esindices目录下，我们可以定义我们需要创建的索引类，并且让该类集成BaseIndex，可看参考demo，ProductsIndex.php文件
需要实现的方法如下所示:
```bash
  //定义索引的别名
    abstract static function getAliasName();
    //定义索引的type
    abstract static function getTypesName();
    //定义索引的类型
    abstract static function getProperties();
    //索引的相关配置
    abstract static function getSettings();
    //重建数据
    abstract static function rebuild($indexName,$type);

```

#### 定义索引数据同步命令
在上述rebuild重建修改索引需要同步数据，我们这边以自定义来同步数据，方便复用
这里也给出了一个demo，可在app\Console\Commands\SyncComands目录下找到SyncProducts.php，因此，当你创建了新的索引之后，也应该创建一个数据同步类

#### 使用索引更新迁移命令
当你定义好了索引类，需要在app\Console\Commands\EsMigrate.php文件中的$indices注册增加你的索引类文件，如附带的demo
```bash
  $indices = [
            \App\Esindices\ProductsIndex::class,
        ];
```
然后，直接输入命令
```bash
php artisan eshelper:migrate
```
进行索引创建，减少了我们在命令行下的操作，并且，我们也很清晰的看到我们定义的索引结构

其次，假设你在app\Esindices定义的索引更改了结构之后，这里使用了es的别名来进行更新，也可以使用命令来

```bash
php artisan eshelper:migrate
```

进行更新



### 查询

使用

use Sevenshi\Eshelper\Eshelper;

可以用两种方式来获取 Sevenshi\Eshelper\Eshelper 实例：

- 方法参数注入

```bash
   .
    .
    .
    public function edit(Eshelper $eshelper) 
    {
        $eshelper->bulid('products','_doc')
                    ->filter('on_sale',true)
                    ->paginate(10,1)
                    ->search();   
    }
    .
    .
    .
```
 
- 服务名访问

```bash
    .
    .
    .
    public function edit() 
    {
        app('eshelper')->bulid('products','_doc')
                    ->filter('on_sale',true)
                    ->paginate(10,1)
                    ->search();   
    }
    .
    .
    .
```

- 支持的方法

可用方法如下，基本的电商查询可覆盖到，可组合使用，列表如下:

获取结果只需调用search方法，支持链式调用
```bash


    //初始化查询参数设置索引以及设置索引的类型
    public function bulid($indexName,$type)
    {
        $this->params =  [
            'index' => $indexName,
            'type'  => $type,
            'body'  => [
                'query' => [
                    'bool' => [
                        'filter' => [],
                        'must'   => [],
                    ],
                ],
            ],
        ];
        return $this;
    }

    /**
     * @param $size 页的大小
     * @param $page 页码
     * @return $this
     */
    public function paginate($size, $page)
    {
        $this->params['body']['from'] = ($page - 1) * $size;
        $this->params['body']['size'] = $size;
        return $this;
    }


    /**
     * @param $key
     * @param $value
     * @param string $type 支持filter/must/should/must_not
     * @return $this
     */
    public function filter($key,$value,$type='filter')
    {
        $this->params['body']['query']['bool'][$type][] =
            ['term' => [$key =>$value]];
        return $this;
    }


    /**
     * @param $key
     * @param $value
     * @param string $type 支持filter/must/should/must_not
     * @param $path
     * @return $this
     */
    public function nestedFilter($key,$value,$type='filter',$path)
    {
        $this->params['body']['query']['bool'][$type][] = [
            // 由于我们要筛选的是 nested 类型下的属性，因此需要用 nested 查询
            'nested' => [
                // 指明 nested 字段
                'path'  => $path,
                'query' => [
                    ['term' => [$type => $value]],
                ],
            ],
        ];

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * 类似于sql中的like查詢
     */
    public function prefix($key,$value)
    {
            $this->params['body']['query']['bool']['filter'][] = [
                'prefix' => [$key => $value]
            ];
    }


    /**
     * @param $field 排序的字段
     * @param $direction 排序的方向
     * @return $this
     */
    public function orderBy($field, $direction)
    {
        if (!isset($this->params['body']['sort'])) {
            $this->params['body']['sort'] = [];
        }
        $this->params['body']['sort'] = [[$field => $direction]];

        return $this;
    }


    /**
     * @param $keywords 搜索关键词，可为array或者string
     * @param $fields 查找的字段，数组，可按照权重来查询如：
     * [
            'title^3',
            'long_title^2',
            'category^2',
            'description',
            'skus_title',
            'skus_description',
            'properties_value',
            ]
     * @return $this
     */

    public function keywords($keywords,$fields)
    {
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        foreach ($keywords as $keyword) {
            $this->params['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query'  => $keyword,
                    'fields' =>$fields,
                ],
            ];
        }
        return $this;
    }


    /**
     * @param $key
     * @param $value
                'aggs'   => [
                 // 聚合的名称
                'properties' => [
                // terms 聚合，用于聚合相同的值
                'terms' => [
                  // 我们要聚合的字段名
                'field' => 'properties.name',
                    ],
                    'aggs'  => [
                        'value' => [
                            'terms' => [
                            'field' => 'properties.value',
                            ],
                        ],
                        ],
                    ],
                ],
      * @return $this

     */
    public function agg($key,$value)
    {
        $this->params['body']['aggs'] = [
            'properties' => [
                'nested' => [
                    'path' => $key,
                ],
                'aggs'=>$value,
            ],
        ];

        return $this;
    }


    /**
     * @param $count
     * @return $this
     * 设置should最小满足的条件
     */
    public function minShouldMatch($count)
    {
        $this->params['body']['query']['bool']['minimum_should_match'] = (int)$count;

        return $this;
    }

    /**
     * @return array
     * 获取构建的所有参数
     */

    private function getParams()
    {
        return $this->params;
    }

    /**
     * @return array
     * 最终调用的方法
     */
    public function search()
    {
        return $this->es->search($this->getParams());
    }

```
# License
MIT