package main

import (
	"fmt"
	"log"
	"regexp"
    "strings"
    "strconv"
	"io/ioutil"
	"net/http"
	"golang.org/x/net/html/charset"
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
    dbId int
    page  int
}

func getUrl(post Post) string {
	url := fmt.Sprintf("https://bbs.jjwxc.net/showmsg.php?board=%d&page=%d&id=%d", post.board, post.page, post.id)
	return url
}

func getAuthor(author []byte, reTag *regexp.Regexp, reAuthorname *regexp.Regexp) (string, string, string, string, string) {
	authorStr := string(reTag.ReplaceAll(author, []byte("")))
	authorStrArr := reAuthorname.FindStringSubmatch(authorStr)
	//fmt.Printf("%s\n", authorStrArr)
	return authorStrArr[1], authorStrArr[2], authorStrArr[3], authorStrArr[4], authorStrArr[0]
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

func getPost(post Post) []Reply {
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
		replyInstance := Reply{
			content:    getContent(read, reTag),
			images:     getImg(read, reImg),
			replyNo:    replyNo,
			authorName: authorName,
            fullAuthorName: fullAuthorName,
			authorCode: authorCode,
			replyTime:  replyTime,
		}
		//fmt.Printf("%s\n", replyInstance)
		replies = append(replies, replyInstance)
	}
    return replies
}

//todo 读redis
func getPosts() []Post {
    postStrs := []string{
        "8694080:444278:1",
        "8687409:434542:5",
        "8693971:444114:1",
        "8694066:444261:1",
        "8694052:444235:1",
        "8694047:444232:1",
        "8693986:444132:1",
        "8693995:444144:1",
        "8694041:444215:1",
        "8691467:440497:1",
    }
    posts := make([]Post, 0)
    for _, postStr := range postStrs {
        postStrSlice := strings.Split(postStr, ":")
        post := Post{
            board: 2,
        }
        post.id, _ = strconv.Atoi(postStrSlice[0])
        post.dbId, _ = strconv.Atoi(postStrSlice[1])
        post.page, _ = strconv.Atoi(postStrSlice[2])
        post.page--

        posts = append(posts, post)
    }
    //fmt.Printf("%s\n", posts)
    return posts
}

type PostResult struct {
    dbId int
    replies []Reply
}

func goGetPosts(post Post, c chan PostResult) {
    replies := getPost(post)
    pa := PostResult{
        dbId: post.dbId,
        replies: replies,
    }
    c <- pa
}

func batchGetPosts(posts[]Post) []PostResult {
	// 并发请求
    c := make(chan PostResult, len(posts))
    r := make([]PostResult, len(posts))
    for k, post := range posts {
        go goGetPosts(post, c)
        r[k] = <-c
    }
    return r
}

func main() {
    posts := getPosts()
    r := batchGetPosts(posts)
    fmt.Printf("%s\n", r)
	//todo 写数据库
}
