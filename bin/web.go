package main

import (
	"database/sql"
	"fmt"
	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"log"
	"time"
)

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

func getPosts() []BoardPost {
	db := getTuquDb()
	defer db.Close()

	selectSql := "SELECT id, postid, idate, subject, author, board FROM post WHERE idate > '2019-07-01 00:00:00' AND (subject LIKE '%zyl%' OR subject LIKE '%朱一%' OR subject LIKE '%一鸣%' OR subject LIKE '%pnz%' OR subject LIKE '%亲爱的自己%' OR subject LIKE '%qad%' OR subject LIKE '%盗墓%' OR subject LIKE '%拢龙%' OR subject LIKE '%南派%' OR subject LIKE '%南麟%' OR subject LIKE '%徐磊%' OR subject LIKE '%镇魂%' OR subject REGEXP 'cq([^l]{1}|$)' OR subject LIKE '%胖丁%' OR subject LIKE '%xlb%' OR subject LIKE '%包酱%' OR subject LIKE '%([^w]{1})wx%' OR subject LIKE '%真朋友%' OR subject LIKE '%cym%' OR subject LIKE '%井然%' OR subject LIKE '%吴邪%' OR subject LIKE '%重启%' OR subject LIKE '%zql%' OR subject LIKE '%叛逆者%' OR subject LIKE '%pnz%' OR subject LIKE '%dffy%' OR subject LIKE '%东方飞云%' OR subject LIKE '%瓶邪%') ORDER BY idate DESC LIMIT 30"

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

func getReplies(pid string) []Reply {
	db := getTuquDb()
	defer db.Close()

	selectSql := "SELECT raw_content, images, author, reply_no, author_code, reply_time FROM reply WHERE post_id = " + pid + " ORDER BY reply_no"

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

func main() {

	_ = fmt.Printf

	r := gin.Default()
	r.GET("/posts", func(c *gin.Context) {
		bpList := getPosts()
		//fmt.Printf("%s\n", bpList)
		msg := Msg{}
		msg.Code = 0
		msg.Message = "success"
		msg.Data = bpList
		c.JSON(200, msg)
	})

	r.GET("/post/:id", func(c *gin.Context) {
		id := c.Param("id")
		list := getReplies(id)
		//fmt.Printf("%s\n", bpList)
		msg := Msg{}
		msg.Code = 0
		msg.Message = "success"
		msg.Data = list
		c.JSON(200, msg)
	})
	r.Run()
}
