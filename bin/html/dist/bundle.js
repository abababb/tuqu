!function(e){var t={};function n(a){if(t[a])return t[a].exports;var r=t[a]={i:a,l:!1,exports:{}};return e[a].call(r.exports,r,r.exports,n),r.l=!0,r.exports}n.m=e,n.c=t,n.d=function(e,t,a){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:a})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var a=Object.create(null);if(n.r(a),Object.defineProperty(a,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var r in e)n.d(a,r,function(t){return e[t]}.bind(null,r));return a},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="/assets/",n(n.s=0)}([function(e,t,n){"use strict";n.r(t);n(1);let a=0;function r(e){const t=document.getElementsByClassName(e);for(;t.length>0;)t[0].remove()}!function(){const e=document.createElement("button");e.innerHTML="第"+a+"页",e.dataset.page=a,e.addEventListener("click",e=>{a++,e.target.innerHTML="第"+a+"页",function(e){const t=location.origin;fetch(t+"/posts/"+e).then(e=>e.json()).then(e=>{r("post");e.data.map(e=>{const n=document.createElement("div");n.className="post",n.dataset.id=e.id,n.dataset.expand=0,n.innerHTML="<div class='row'>id: "+e.tq_id+"</div><div class='row'>"+e.subject+"</div><div class='row'><span class='author'>"+e.author+"</span> | <span class='replytime'>"+e.idate+"</span></div>",n.addEventListener("click",e=>{const n=e.target.parentNode,a=n.getAttribute("data-id");"0"===n.getAttribute("data-expand")?(fetch(t+"/post/"+a).then(e=>e.json()).then(e=>{e.data.map(e=>{const t=document.createElement("div");t.className="reply",t.innerHTML="<div class='row-reply'>no."+e.reply_no+"</div><div class='row-reply'>"+e.content+"</div><div class='row-reply'><span class='authorname'>"+e.author_name+"</span> | <span>"+e.author_code+"</span> | <span class='replytime'>"+e.reply_time+"</span></div>",n.appendChild(t)})}),n.dataset.expand=1):(r("row-reply"),n.dataset.expand=0)}),document.body.appendChild(n)})})}(a)}),document.body.appendChild(e)}()},function(e,t,n){}]);