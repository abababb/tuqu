import './style.css';

let page = 0
function init () {
  const ele = document.createElement('button');
  ele.innerHTML = "第"+page+"页"
  ele.dataset.page = page
  ele.addEventListener('click', (e) => {
    page++
    e.target.innerHTML = "第"+page+"页"
    loadPage(page)
  })
  document.body.appendChild(ele);
}
function loadPage(page) {
  const host = location.origin
  fetch(host+'/posts/'+page)
    .then(response => response.json())
    .then(result => {
      removeByClass('post')
      const data = result.data
      const body = data.map(i => {
        const ele = document.createElement('div');
        ele.className = "post"
        ele.dataset.id = i.id
        ele.dataset.expand = 0
        ele.innerHTML = "<div class='row'>id: "+i.tq_id+"</div>"
          + "<div class='row'>"+i.subject+"</div>"
          + "<div class='row'><span class='author'>"+i.author+"</span> | <span class='replytime'>"+i.idate+"</span></div>"
        ele.addEventListener('click', (e) => {
          const post = e.target.parentNode
          const id = post.getAttribute("data-id")
          let expand = post.getAttribute("data-expand")
          if (expand === "0") {
            fetch(host+'/post/'+id)
              .then(response => response.json())
              .then(result => {
                const data = result.data
                data.map(i => {
                  const reply = document.createElement('div');
                  reply.innerHTML = "<div class='row-reply'>no."+i.reply_no+"</div>"
                    + "<div class='row-reply'>"+i.content+"</div>"
                    + "<div class='row-reply'><span class='authorname'>"+i.author_name+"</span> | <span>"+i.author_code+"</span> | <span class='replytime'>"+i.reply_time+"</span></div>"
                  post.appendChild(reply)
                })
              })
            post.dataset.expand = 1
          } else {
            removeByClass('row-reply')
            post.dataset.expand = 0
          }
        })
        document.body.appendChild(ele);
      })
    })

}

function removeByClass (cName) {
  const replies = document.getElementsByClassName(cName)
  while (replies.length > 0) {
    replies[0].remove()
  }
}

init()
