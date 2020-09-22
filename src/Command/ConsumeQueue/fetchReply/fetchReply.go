package main

import (
	"context"
	"database/sql"
	"fmt"
	"github.com/go-redis/redis/v8"
	_ "github.com/go-sql-driver/mysql"
	"golang.org/x/net/html/charset"
	"io/ioutil"
	"log"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"
)

type Reply struct {
	replyNo        string
	content        string
	images         string
	authorName     string
	authorCode     string
	replyTime      string
	fullAuthorName string
}

type Post struct {
	board int
	id    int
	dbId  int
	page  int
}

func getUrl(post Post) string {
	url := fmt.Sprintf("https://bbs.jjwxc.net/showmsg.php?board=%d&page=%d&id=%d", post.board, post.page, post.id)
	return url
}

func getAuthor(author []byte, reTag *regexp.Regexp, reAuthorname *regexp.Regexp) (string, string, string, string, string) {
	authorStr := string(reTag.ReplaceAll(author, []byte("")))
	authorStrArr := reAuthorname.FindStringSubmatch(authorStr)
	if len(authorStrArr) == 5 {
		return authorStrArr[1], authorStrArr[2], authorStrArr[3], authorStrArr[4], authorStrArr[0]
	}
	fmt.Printf("找不到author: %s\n", authorStr)
	return "", "", "", "", ""
}

func getImg(read []byte, reImg *regexp.Regexp) string {
	matchArr := reImg.FindAllSubmatch(read, -1)

	imgStringArr := make([]string, 0)
	for _, matchImg := range matchArr {
		imgStringArr = append(imgStringArr, string(matchImg[1]))
	}
	imgStr := strings.Join(imgStringArr, "|")
	//fmt.Printf("%s\n", imgStr)
	return imgStr
}

func getContent(read []byte, reTag *regexp.Regexp) string {
	contentStr := string(reTag.ReplaceAll(read, []byte("")))
	return contentStr
}

func fetchPost(post Post) []Reply {
	url := getUrl(post)
	req, err := http.NewRequest("GET", url, nil)
	//fmt.Printf("%s\n", url)

	client := &http.Client{}
	req.Header.Set("Cookie", "bbsnicknameAndsign=2%257E%2529%2524zzz; bbstoken=MjA5OTQ3OTFfMF9kMzUzMDkwNDMwNjdjYWExZTVlZjJjZTM1YTRiMTk5Ml8xX19fMQ%3D%3D")
	res, err := client.Do(req)
	if err != nil {
		log.Fatal(err)
	}
	respUtf8Reader, err := charset.NewReader(res.Body, "text/html")
	body, err := ioutil.ReadAll(respUtf8Reader)
	res.Body.Close()
	if err != nil {
		log.Fatal(err)
	}
	//fmt.Printf("%q\n", body)
	//ioutil.WriteFile("/tmp/jjwxc.html", body, 0644)

	reRead := regexp.MustCompile(`(?s)<td class="read"\s*>.*?</td>`)
	reAuthor := regexp.MustCompile(`(?s)<td class="authorname"\s*>.*?</td>`)
	reTag := regexp.MustCompile(`\s*<[^>]+>\s*`)
	reImg := regexp.MustCompile(`<img src="(?P<image>.*?)".*?>`)
	reAuthorname := regexp.MustCompile(`№(?P<reply_no>[[:digit:]]+)☆☆☆(?P<author_name>.*)\|(?P<author_code>[[:alnum:]]+)于(?P<reply_time>.*)留言☆☆☆`)

	reads := reRead.FindAll(body, -1)
	authors := reAuthor.FindAll(body, -1)

	replies := make([]Reply, 0)
	for k, read := range reads {
		replyNo, authorName, authorCode, replyTime, fullAuthorName := getAuthor(authors[k], reTag, reAuthorname)
		if replyNo != "" {
			replyInstance := Reply{
				content:        getContent(read, reTag),
				images:         getImg(read, reImg),
				replyNo:        replyNo,
				authorName:     authorName,
				fullAuthorName: fullAuthorName,
				authorCode:     authorCode,
				replyTime:      replyTime,
			}
			//fmt.Printf("%s\n", replyInstance)
			replies = append(replies, replyInstance)
		}
	}
	return replies
}

func getPosts(board int, batchSize int, rdb *redis.Client, ctx context.Context) []Post {
	postStrs := make([]string, 0)
	for i := 0; i < batchSize; i++ {
		// 读redis
		v, _ := rdb.BRPop(ctx, 0, "tuqu_post:"+strconv.Itoa(board)).Result()
		postStrs = append(postStrs, v[1])
	}
	//fmt.Printf("%s\n", postStrs)

	//fmt.Printf("%d\n", len(postStrs))
	postStrs = removeDuplicates(postStrs)
	//fmt.Printf("%d\n", len(postStrs))

	posts := make([]Post, 0)
	for _, postStr := range postStrs {
		postStrSlice := strings.Split(postStr, ":")
		post := Post{
			board: board,
		}
		post.id, _ = strconv.Atoi(postStrSlice[0])
		post.dbId, _ = strconv.Atoi(postStrSlice[1])
		post.page, _ = strconv.Atoi(postStrSlice[2])
		post.page--

		posts = append(posts, post)
	}
	return posts
}

func removeDuplicates(data []string) []string {
	strMap := make(map[string]int)
	for _, i := range data {
		strMap[i] = 0
	}
	result := make([]string, 0)
	for k := range strMap {
		result = append(result, k)
	}
	return result
}

type PostResult struct {
	dbId    int
	replies []Reply
}

func goFetchPosts(post Post, c chan PostResult) {
	replies := fetchPost(post)
	pa := PostResult{
		dbId:    post.dbId,
		replies: replies,
	}
	c <- pa
}

func batchFetchPosts(posts []Post) []PostResult {
	// 并发请求
	c := make(chan PostResult, len(posts))
	r := make([]PostResult, len(posts))
	for k, post := range posts {
		go goFetchPosts(post, c)
		r[k] = <-c
	}
	return r
}

func insertDb(board int, postResults []PostResult) {
	// 写数据库
	db, err := sql.Open("mysql", "root:FqcD123223!@tcp(127.0.0.1:3306)/tuqu")

	if err != nil {
		log.Fatal(err)
	}

	db.SetConnMaxLifetime(time.Minute * 3)
	db.SetMaxOpenConns(10)
	db.SetMaxIdleConns(10)

	defer db.Close()

	postIds := make([]string, 0)
	for _, postResult := range postResults {
		postIds = append(postIds, strconv.Itoa(postResult.dbId))
	}
	postIdStr := strings.Join(postIds, ",")

	sql := "SELECT post_id, MAX(reply_no) AS max_reply FROM reply WHERE post_id IN (" + postIdStr + ") GROUP BY post_id"
	//fmt.Printf("%s\n", sql)
	results, err := db.Query(sql)

	if err != nil {
		log.Fatal(err)
	}

	resultMap := make(map[int]int)
	for results.Next() {
		var postId int
		var maxReplyNo int
		results.Scan(&postId, &maxReplyNo)
		resultMap[postId] = maxReplyNo
	}
	//fmt.Printf("%s\n", resultMap)

	postResultsToInsert := filterPostReplies(resultMap, postResults)
	//fmt.Printf("%s\n", postResultsToInsert)

	if len(postResultsToInsert) > 0 {
		insertSql, count := genInsertSql(postResultsToInsert)
		//fmt.Printf("%s\n", insertSql)

		insert, err := db.Query(insertSql)
		if err != nil {
			log.Fatal(err)
		}
		fmt.Printf("板块%d插入%d条新回复\n", board, count)
		defer insert.Close()
	}
}

func escapeInsertSqlPart(re *regexp.Regexp, part string) string {
	partBytes := []byte(part)
	part = string(re.ReplaceAll(partBytes, []byte("\\'")))
	return part
}

func genInsertSql(postResults []PostResult) (string, int) {
	insertParts := make([]string, 0)
	re := regexp.MustCompile(`'`)
	for _, postResult := range postResults {
		for _, reply := range postResult.replies {
			insertPart := fmt.Sprintf("('%s','%s',%s,%s,'%s','%s','%s','%s')", escapeInsertSqlPart(re, reply.content), escapeInsertSqlPart(re, reply.fullAuthorName), strconv.Itoa(postResult.dbId), reply.replyNo, reply.authorName, reply.authorCode, reply.replyTime, reply.images)
			insertParts = append(insertParts, insertPart)
		}
	}
	insertPartStr := strings.Join(insertParts, ",")
	sql := "INSERT INTO reply (raw_content, raw_authorname, post_id, reply_no, author, author_code, reply_time, images) VALUES " + insertPartStr
	return sql, len(insertParts)
}

func filterPostReplies(resultMap map[int]int, postResults []PostResult) []PostResult {
	postResultsToInsert := make([]PostResult, 0)
	for _, postResult := range postResults {
		postId := postResult.dbId
		postReplies := make([]Reply, 0)

		// 过滤已经插入的回复
		if maxReplyNo, ok := resultMap[postId]; ok {
			//fmt.Printf("%s, %s\n", postId, maxReplyNo)
			for _, reply := range postResult.replies {
				replyNo, _ := strconv.Atoi(reply.replyNo)
				if replyNo > maxReplyNo {
					postReplies = append(postReplies, reply)
				}
			}
			//fmt.Printf("%s\n", postReplies)
		} else {
			postReplies = postResult.replies
		}

		if len(postReplies) > 0 {
			postResultToInsert := PostResult{
				dbId:    postId,
				replies: postReplies,
			}
			postResultsToInsert = append(postResultsToInsert, postResultToInsert)
		}
	}
	return postResultsToInsert
}

func fetchBoard(board int, rdb *redis.Client, ctx context.Context) {
	batchSize := 10
	for {
		posts := getPosts(board, batchSize, rdb, ctx)
		fmt.Printf("板块%d开始处理%d条post\n", board, len(posts))
		r := batchFetchPosts(posts)
		insertDb(board, r)
		time.Sleep(2 * time.Second)
	}
}

func main() {
	ctx := context.Background()
	rdb := redis.NewClient(&redis.Options{
		Addr:     "localhost:6379",
		Password: "", // no password set
		DB:       1,  // use default DB
	})
	var wg sync.WaitGroup
	wg.Add(1)
	go fetchBoard(2, rdb, ctx)
	go fetchBoard(3, rdb, ctx)
	wg.Wait()
}
