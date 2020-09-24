package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"github.com/go-redis/redis/v8"
	_ "github.com/go-sql-driver/mysql"
	"golang.org/x/net/html/charset"
	"io/ioutil"
	"log"
	"net/http"
	"net/url"
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

type PostResult struct {
	dbId    int
	replies []Reply
}

type BoardPost struct {
	subject       string
	postId        string
	examineStatus string
	author        string
	idate         string
	ndate         string
	pages         string
	board         int
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
	uri := fmt.Sprintf("https://bbs.jjwxc.net/showmsg.php?board=%d&page=%d&id=%d", post.board, post.page, post.id)
	req, err := http.NewRequest("GET", uri, nil)
	//fmt.Printf("%s\n", uri)

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
	reAuthorname := regexp.MustCompile(`№(?P<reply_no>[[:digit:]]+)☆☆☆(?P<author_name>.*?)\|?(?P<author_code>[[:alnum:]]{8})?于(?P<reply_time>.*)留言☆☆☆`)

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

func getTuquDb() *sql.DB {
	mysqlConfig := "root:FqcD123223!@tcp(127.0.0.1:3306)/tuqu"

	db, err := sql.Open("mysql", mysqlConfig)

	if err != nil {
		log.Fatal(err)
	}

	db.SetConnMaxLifetime(time.Minute * 3)
	db.SetMaxOpenConns(10)
	db.SetMaxIdleConns(10)
	return db
}

func insertDb(board int, postResults []PostResult) {
	// 写数据库
	db := getTuquDb()
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

		_, err := db.Exec(insertSql)
		if err != nil {
			log.Fatal(err)
		}
		fmt.Printf("板块%d插入%d条新回复\n", board, count)
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
			insertPart := fmt.Sprintf("('%s','%s',%d,%s,'%s','%s','%s','%s')", escapeInsertSqlPart(re, reply.content), escapeInsertSqlPart(re, reply.fullAuthorName), postResult.dbId, reply.replyNo, reply.authorName, reply.authorCode, reply.replyTime, reply.images)
			insertParts = append(insertParts, insertPart)
		}
	}
	insertParts = removeDuplicates(insertParts)
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

func fetchReply(board int, rdb *redis.Client, ctx context.Context, batchSize int) {
	posts := getPosts(board, batchSize, rdb, ctx)
	r := batchFetchPosts(posts)
	insertDb(board, r)
}

func parseQueue(board int, rdb *redis.Client, ctx context.Context, key string, batchSize int) {
	keyLen, _ := rdb.LLen(ctx, key).Result()
	//fmt.Printf("%d\n", keyLen)
	if int64(float64(batchSize)*1.1) < keyLen {
		postStrs := make([]string, 0)
		for i := 0; i < batchSize; i++ {
			// 读redis
			v, _ := rdb.BRPop(ctx, 0, key).Result()
			postStrs = append(postStrs, v[1])
		}

		before := len(postStrs)
		//fmt.Printf("%d\n", before)
		postStrs = removeDuplicates(postStrs)
		after := len(postStrs)
		//fmt.Printf("%d\n", after)

		for _, postStr := range postStrs {
			_, _ = rdb.LPush(ctx, key, postStr).Result()
		}
		fmt.Printf("board%d去重%d条\n", board, before-after)
	}
}

func fetchBoard(board int, rdb *redis.Client, ctx context.Context) {
	// 拉取数据
	resp, err := http.PostForm("http://bbs.jjwxc.net/bbsapi.php?action=board", url.Values{
		"board":       {strconv.Itoa(board)},
		"page":        {"1"},
		"sign":        {"t1y30KJlifEX7XJeoSv3NvZifLl08tdwcBxJKi130qUIi1mJOpMGV7om4rii/AzhM3h3RhnMFS4%3D"},
		"source":      {"IOS"},
		"versionCode": {"195"},
		"topic":       {"3"},
	})

	if err != nil {
		log.Fatal(err)
	}
	body, err := ioutil.ReadAll(resp.Body)
	resp.Body.Close()
	if err != nil {
		log.Fatal(err)
	}
	//fmt.Printf("%s\n", body)

	var v map[string]interface{}
	err = json.Unmarshal(body, &v)
	if err != nil {
		log.Fatal(err)
	}

	data, ok := v["data"]
	if !ok {
		return
	}

	dataType := fmt.Sprintf("%T", data)
	if dataType != "[]interface {}" {
		return
	}

	bp := make([]BoardPost, 0)
	insertPids := make([]string, 0)
	for _, post := range data.([]interface{}) {
		p := post.(map[string]interface{})
		boardPost := BoardPost{
			subject:       p["subject"].(string),
			postId:        p["id"].(string),
			examineStatus: p["examine_status"].(string),
			board:         board,
			author:        p["author"].(string),
			idate:         p["idate"].(string),
			ndate:         p["ndate"].(string),
			pages:         p["pages"].(string),
		}
		if boardPost.idate == "00-00-00 00:00" {
			boardPost.idate = "71-01-01 00:00"
		}
		if boardPost.ndate == "00-00-00 00:00" {
			boardPost.ndate = "71-01-01 00:00"
		}
		//fmt.Printf("%s\n", boardPost)

		bp = append(bp, boardPost)
		insertPids = append(insertPids, boardPost.postId)
	}

	//插入mysql和redis
	db := getTuquDb()
	defer db.Close()

	selectSql := "SELECT id, postid FROM post WHERE postid IN (" + strings.Join(insertPids, ",") + ")"
	//fmt.Printf("%s\n", selectSql)
	results, err := db.Query(selectSql)

	if err != nil {
		log.Fatal(err)
	}

	resultMap := make(map[int]int)
	for results.Next() {
		var dbId int
		var postId int
		results.Scan(&dbId, &postId)
		resultMap[postId] = dbId
	}
	//fmt.Printf("%s\n", resultMap)

	toAddQueue := make([]string, 0)
	toInsert := make([]BoardPost, 0)
	for _, boardPost := range bp {
		pid, _ := strconv.Atoi(boardPost.postId)
		if dbId, ok := resultMap[pid]; !ok {
			toInsert = append(toInsert, boardPost)
		} else {
			toAdd := boardPost.postId + ":" + strconv.Itoa(dbId) + ":" + boardPost.pages
			toAddQueue = append(toAddQueue, toAdd)
		}
	}

	if len(toInsert) > 0 {
		re := regexp.MustCompile(`'`)
		insertParts := make([]string, 0)
		for _, boardPost := range toInsert {
			insertPart := fmt.Sprintf("('%s','%s',%s,'%s','%s','%s',%d)", escapeInsertSqlPart(re, boardPost.subject), boardPost.postId, boardPost.examineStatus, escapeInsertSqlPart(re, boardPost.author), boardPost.idate, boardPost.ndate, board)
			insertParts = append(insertParts, insertPart)
		}
		insertSql := "INSERT INTO post (subject, postid, examine_status, author, idate, ndate, board) VALUES " + strings.Join(insertParts, ",")
		//fmt.Printf("%s\n", insertSql)
		insertResult, err := db.Exec(insertSql)
		if err != nil {
			log.Fatal(err)
		}
		lastInsertId, err := insertResult.LastInsertId()
		//fmt.Printf("%s\n", lastInsertId)
		if err != nil {
			log.Fatal(err)
		}
		for k, boardPost := range toInsert {
			toAdd := boardPost.postId + ":" + strconv.FormatInt(lastInsertId+int64(k), 10) + ":" + boardPost.pages
			toAddQueue = append(toAddQueue, toAdd)
		}
	}

	//fmt.Printf("%s\n", toAddQueue)
	if len(toAddQueue) > 0 {
		rkey := "tuqu_post:" + strconv.Itoa(board)
		for _, postStr := range toAddQueue {
			_, _ = rdb.LPush(ctx, rkey, postStr).Result()
		}
	}
	fmt.Printf("board%d已插入数据库%d条，已加入redis队列%d条\n", board, len(toInsert), len(toAddQueue))
}

func foreverFetchReply(board int, rdb *redis.Client, ctx context.Context) {
	batchSize := 10
	for {
		fetchReply(board, rdb, ctx, batchSize)
		time.Sleep(2 * time.Second)
	}
}

func foreverParseQueue(board int, rdb *redis.Client, ctx context.Context) {
	key := "tuqu_post:" + strconv.Itoa(board)
	batchSize := 300
	for {
		parseQueue(board, rdb, ctx, key, batchSize)
		time.Sleep(30 * time.Second)
	}
}

func foreverFetchBoard(board int, rdb *redis.Client, ctx context.Context) {
	for {
		fetchBoard(board, rdb, ctx)
		time.Sleep(5 * time.Second)
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
	go foreverFetchReply(2, rdb, ctx)
	go foreverParseQueue(2, rdb, ctx)
	go foreverFetchBoard(2, rdb, ctx)
	go foreverFetchReply(3, rdb, ctx)
	go foreverParseQueue(3, rdb, ctx)
	go foreverFetchBoard(3, rdb, ctx)
	wg.Wait()
}
