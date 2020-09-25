package main

import (
	//"fmt"
	//"time"
	"database/sql"
	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"log"
	"strconv"
)

func getTuquDb() *sql.DB {
	mysqlConfig := "root:FqcD123223!@tcp(127.0.0.1:3306)/tuqu"

	db, err := sql.Open("mysql", mysqlConfig)

	if err != nil {
		log.Fatal(err)
	}

	//db.SetConnMaxLifetime(time.Minute * 3)
	db.SetConnMaxLifetime(60 * 3)
	db.SetMaxOpenConns(10)
	db.SetMaxIdleConns(10)
	return db
}

type Reply struct {
	ReplyNo    string `json:"reply_no"`
	Content    string `json:"content"`
	AuthorName string `json:"author_name"`
	AuthorCode string `json:"author_code"`
	ReplyTime  string `json:"reply_time"`
	Images     string `json:"images"`
}

type BoardPost struct {
	Id      string `json:"id"`
	PostId  string `json:"tq_id"`
	Subject string `json:"subject"`
	Author  string `json:"author"`
	Idate   string `json:"idate"`
	Board   string `json:"board"`
}

type Msg struct {
	Code    int         `json:"code"`
	Message string      `json:"message"`
	Data    interface{} `json:"data"`
}

func getPosts(page int) []BoardPost {
	db := getTuquDb()
	defer db.Close()

	pageSize := 30
	offset := page * pageSize

	selectSql := "SELECT id, postid, idate, subject, author, board FROM post WHERE idate > '2019-07-01 00:00:00' AND (subject LIKE '%zyl%' OR subject LIKE '%朱一%' OR subject LIKE '%一鸣%' OR subject LIKE '%pnz%' OR subject LIKE '%亲爱的自己%' OR subject LIKE '%qad%' OR subject LIKE '%盗墓%' OR subject LIKE '%拢龙%' OR subject LIKE '%南派%' OR subject LIKE '%南麟%' OR subject LIKE '%徐磊%' OR subject LIKE '%镇魂%' OR subject REGEXP 'cq([^l]{1}|$)' OR subject LIKE '%胖丁%' OR subject LIKE '%xlb%' OR subject LIKE '%包酱%' OR subject LIKE '%([^w]{1})wx%' OR subject LIKE '%真朋友%' OR subject LIKE '%cym%' OR subject LIKE '%井然%' OR subject LIKE '%吴邪%' OR subject LIKE '%重启%' OR subject LIKE '%zql%' OR subject LIKE '%叛逆者%' OR subject LIKE '%pnz%' OR subject LIKE '%dffy%' OR subject LIKE '%东方飞云%' OR subject LIKE '%瓶邪%') ORDER BY idate DESC LIMIT " + strconv.Itoa(pageSize) + " OFFSET " + strconv.Itoa(offset)

	//fmt.Printf("%s\n", selectSql)
	results, err := db.Query(selectSql)

	if err != nil {
		log.Fatal(err)
	}

	bpList := make([]BoardPost, 0)
	for results.Next() {
		bp := BoardPost{}
		results.Scan(&bp.Id, &bp.PostId, &bp.Idate, &bp.Subject, &bp.Author, &bp.Board)
		bpList = append(bpList, bp)
	}

	return bpList
}

func getReplies(pid int) []Reply {
	db := getTuquDb()
	defer db.Close()

	selectSql := "SELECT raw_content, images, author, reply_no, author_code, reply_time FROM reply WHERE post_id = " + strconv.Itoa(pid) + " ORDER BY reply_no"

	results, err := db.Query(selectSql)

	if err != nil {
		log.Fatal(err)
	}

	list := make([]Reply, 0)
	for results.Next() {
		i := Reply{}
		results.Scan(&i.Content, &i.Images, &i.AuthorName, &i.ReplyNo, &i.AuthorCode, &i.ReplyTime)
		list = append(list, i)
	}

	return list
}

func returnMsg(list interface{}) Msg {
	msg := Msg{}
	msg.Code = 0
	msg.Message = "success"
	msg.Data = list
	return msg
}

func main() {

	//_ = fmt.Printf

	gin.SetMode(gin.ReleaseMode)
	r := gin.Default()
	r.GET("/posts/:page", func(c *gin.Context) {
		page, _ := strconv.Atoi(c.Param("page"))

		list := getPosts(page)
		c.JSON(200, returnMsg(list))
	})

	r.GET("/post/:id", func(c *gin.Context) {
		id, _ := strconv.Atoi(c.Param("id"))

		list := getReplies(id)
		c.JSON(200, returnMsg(list))
	})

	r.Static("/assets", "./html/dist")
	r.LoadHTMLFiles("html/dist/index.html")
	r.GET("/", func(c *gin.Context) {
		c.HTML(200, "index.html", gin.H{})
	})
	//r.Run()
	r.Run(":80")
}
