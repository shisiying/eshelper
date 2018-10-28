<?php

namespace Sevenshi\Eshelper;

use Elasticsearch\ClientBuilder;


class Eshelper
{

    protected  $es;

    protected $params;

    function __construct($host)
    {
        $this->es = ClientBuilder::create()->setHosts($host)->build();
    }


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

}
