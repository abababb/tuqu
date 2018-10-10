# tuqu

### 查看兔区标题关键词帖子数目统计
http://45.77.40.238/column/graph

### 抓微博最新内容
curl -s https://api.weibo.com/2/statuses/home_timeline.json\?access_token\=2.0074G3ED0XPZMWfeeb39858e8Eaz6D | jq '.statuses | .[] | .text'
