# tuqu


## 抓数据功能

### crontab配置
* * * * * /usr/bin/php /home/zzz/tuqu/bin/console app:fetch:posts '' 0 2 >> /tmp/tuqu.log
* * * * * sleep 20; /usr/bin/php /home/zzz/tuqu/bin/console app:fetch:posts '' 0 2 >> /tmp/tuqu.log
* * * * * sleep 40; /usr/bin/php /home/zzz/tuqu/bin/console app:fetch:posts '' 0 2 >> /tmp/tuqu.log

* * * * * sleep 10; /usr/bin/php /home/zzz/tuqu/bin/console app:fetch:posts '' 0 2 3 >> /tmp/xq.log
* * * * * sleep 30; /usr/bin/php /home/zzz/tuqu/bin/console app:fetch:posts '' 0 2 3 >> /tmp/xq.log
* * * * * sleep 50; /usr/bin/php /home/zzz/tuqu/bin/console app:fetch:posts '' 0 2 3 >> /tmp/xq.log

### supervisor.conf配置

[program:tuqu_fetch_replies_0]
command=/usr/bin/php /home/zzz/tuqu/bin/console --env=prod app:consume:fetch:replies

[program:xq_fetch_replies_0]
command=/usr/bin/php /home/zzz/tuqu/bin/console --env=prod app:consume:fetch:replies 10 3

注: 按现在的服务器配置每个板块worker数4个

[program:tuqu_process_queue]
command=/usr/bin/php /home/zzz/tuqu/bin/console --env=prod app:process:queue 100 2

[program:xq_process_queue]
command=/usr/bin/php /home/zzz/tuqu/bin/console --env=prod app:process:queue 100 3

## 其余零散功能

### 查看兔区标题关键词帖子数目统计
http://45.77.40.238/column/graph

### 抓微博最新内容
curl -s https://api.weibo.com/2/statuses/home_timeline.json\?access_token\=2.0074G3ED0XPZMWfeeb39858e8Eaz6D | jq '.statuses | .[] | .text'
