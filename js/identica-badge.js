// identica badge -- updated to work with the native API, 12-4-2008
// Modified to point to Identi.ca, 2-20-2009 by Zach
// copyright Kent Brewster 2008
// see http://kentbrewster.com/identica-badge for info
( function() { 
   var trueName = '';
   for (var i = 0; i < 16; i++) { 
      trueName += String.fromCharCode(Math.floor(Math.random() * 26) + 97); 
   }
   window[trueName] = {};
   var $ = window[trueName];
   $.f = function() {
      return { 
         runFunction : [],
         init : function(target) {
            var theScripts = document.getElementsByTagName('SCRIPT');
            for (var i = 0; i < theScripts.length; i++) {
               if (theScripts[i].src.match(target)) {
                  $.a = {};
                  if (theScripts[i].innerHTML) {
                     $.a = $.f.parseJson(theScripts[i].innerHTML);
                  }
                  if ($.a.err) {
                     alert('bad json!');
                  }
                  $.f.loadDefaults();
                  $.f.buildStructure();
                  $.f.buildPresentation();
                  theScripts[i].parentNode.insertBefore($.s, theScripts[i]);
                  theScripts[i].parentNode.removeChild(theScripts[i]);
                  break;
               }
            }         
         },
         parseJson : function(json) {
            this.parseJson.data = json;
            if ( typeof json !== 'string') {
               return {"err":"trying to parse a non-string JSON object"};
            }
            try {
               var f = Function(['var document,top,self,window,parent,Number,Date,Object,Function,',
                  'Array,String,Math,RegExp,Image,ActiveXObject;',
                  'return (' , json.replace(/<\!--.+-->/gim,'').replace(/\bfunction\b/g,'function&shy;') , ');'].join(''));
               return f();
            } catch (e) {
               return {"err":"trouble parsing JSON object"};
            }
         },
         loadDefaults : function() {
            $.d = { 
               "user":"7000",
               "headerText" : "",
               "height" : 350,
               "width" : 300,
               "background" : "#193441",
               "border" : "1px solid black",
               "userFontSize" : "inherit",
               "userColor" : "inherit",
               "headerBackground" : "transparent", 
               "headerColor" : "white",
               "evenBackground" : "#fff",
               "oddBackground" : "#eee",
               "thumbnailBorder" : "1px solid black",
               "thumbnailSize" : 24,
               "padding" : 3,
               "server" : "identi.ca"
            };
            for (var k in $.d) { if ($.a[k] === undefined) { $.a[k] = $.d[k]; } }
         },
          buildPresentation : function () {
            var ns = document.createElement('style');
            document.getElementsByTagName('head')[0].appendChild(ns);
            if (!window.createPopup) {
               ns.appendChild(document.createTextNode(''));
               ns.setAttribute("type", "text/css");
            }
            var s = document.styleSheets[document.styleSheets.length - 1];
            var rules = {
               "" : "{zoom:1;margin:0;padding:0;width:" + $.a.width + "px;background:" + $.a.background + ";border:" + $.a.border + ";font:13px/1.2em tahoma, veranda, arial, helvetica, clean, sans-serif;*font-size:small;*font:x-small;}",
               "a" : "{cursor:pointer;text-decoration:none;}",
               "a:hover" : "{text-decoration:underline;}",
               "cite" : "{font-weight:bold;margin:0 0 0 4px;padding:0;display:block;font-style:normal;line-height:" + ($.a.thumbnailSize/2) + "px;}",
               "cite a" : "{color:#C15D42;}",
               "date":"{font-size:87%;margin:0 0 0 4px;padding:0;display:block;font-style:normal;line-height:" + ($.a.thumbnailSize/2) + "px;}",
               "date:after" : "{clear:both; content:\".\"; display:block; height:0; visibility:hidden; }",
               "date a" : "{color:#676;}",
               "h3" : "{margin:0;padding:" + $.a.padding + "px;font-weight:bold;background:" + $.a.headerBackground + " url('http://" + $.a.server + "/favicon.ico') " + $.a.padding + "px 50% no-repeat;text-indent:" + ($.a.padding + 16) + "px;}",
               "h3.loading" : "{background-image:url('http://l.yimg.com/us.yimg.com/i/us/my/mw/anim_loading_sm.gif');}",
               "h3 a" : "{font-size:92%; color:" + $.a.headerColor + ";}",
               "h4" : "{font-weight:normal; background:" + $.a.headerBackground + ";text-align:right;margin:0;padding:" + $.a.padding + "px;}",
               "h4 a" : "{font-size:92%; color:" + $.a.headerColor + ";}",
               "img":"{float:left; height:" + $.a.thumbnailSize + "px;width:" + $.a.thumbnailSize + "px;border:" + $.a.thumbnailBorder + ";margin-right:" + $.a.padding + "px;}",
               "p" : "{margin:0; padding:0;width:" + ($.a.width - 22) + "px;overflow:hidden;font-size:87%;}",
               "p a" : "{color:#C15D42;}",
               "ul":"{margin:0; padding:0; height:" + $.a.height + "px;width:" + $.a.width + "px;overflow:auto;}",
               "ul li":"{background:" + $.a.evenBackground + ";margin:0;padding:" + $.a.padding + "px;list-style:none;width:" + ($.a.width - 22) + "px;overflow:hidden;border-bottom:1px solid #D8E2D7;}",
               "ul li:hover":"{background:#f3f8ea;}"
            };
            var ieRules = "";
            // brute-force each and every style rule here to !important
            // sometimes you have to take off and nuke the site from orbit; it's the only way to be sure
            for (var z in rules) {
               var selector = '.' + trueName + ' ' + z;
               var rule = rules[z];
               if (typeof rule === 'string') {
                  var important = rule.replace(/;/gi, '!important;');
                  if (!window.createPopup) {
                     var theRule = document.createTextNode(selector + important);
                     ns.appendChild(theRule);
                  } else {
                     ieRules += selector + important;
                  }
               }
            }
            if (window.createPopup) { s.cssText = ieRules; }
         },
         buildStructure : function() {
            $.s = document.createElement('DIV');
            $.s.className = trueName;         
            $.s.h = document.createElement('H3');
            $.s.h.a = document.createElement('A');
            $.s.h.a.target = '_laconica';
            $.s.h.appendChild($.s.h.a);
            $.s.appendChild($.s.h);
            $.s.r = document.createElement('UL');
            $.s.appendChild($.s.r);
            $.s.f = document.createElement('H4');
            var a = document.createElement('A');
            a.innerHTML = 'get this';
            a.target = '_blank';
            a.href = 'http://identi.ca/doc/badge';
            $.s.f.appendChild(a);
            $.s.appendChild($.s.f);
            $.f.getUser();
         },
         getUser : function() {
            if (!$.f.runFunction) { $.f.runFunction = []; }
            var n = $.f.runFunction.length;
            var id = trueName + '.f.runFunction[' + n + ']';
            $.f.runFunction[n] = function(r) {
               delete($.f.runFunction[n]);
               var a = document.createElement('A');
               a.rel = $.a.user;
               a.rev = r.name; 
               a.id = r.screen_name;
               $.f.removeScript(id);
               $.f.changeUserTo(a);
            };
            var url = 'http://' + $.a.server + '/api/users/show/' + $.a.user + '.json?callback=' + id;
            $.f.runScript(url, id);
         },
         changeUserTo : function(el) {
            $.a.user = el.rel;
            $.s.h.a.innerHTML = el.rev + $.a.headerText;
            $.s.h.a.href = 'http://' + $.a.server + '/' + el.id;
            $.f.runSearch(); 
         },
         runSearch : function() {
            $.s.h.className = 'loading';
            $.s.r.innerHTML = '';
            if (!$.f.runFunction) { $.f.runFunction = []; }
            var n = $.f.runFunction.length;
            var id = trueName + '.f.runFunction[' + n + ']';
            $.f.runFunction[n] = function(r) {
               delete($.f.runFunction[n]);
               $.f.removeScript(id);
               $.f.renderResult(r); 
            };
            var url = 'http://' + $.a.server + '/api/statuses/friends/' + $.a.user + '.json?callback=' + id;
            $.f.runScript(url, id);
         },
         renderResult: function(r) { 
            for (var i = 0; i < r.length; i++) {
               if (!r[i].status) {
                  r.splice(i, 1);
               } else {
                  r[i].status_id = parseInt(r[i].status.id);
               }
            }
            r = $.f.sortArray(r, "status_id", true);
            $.s.h.className = '';
            for (var i = 0; i < r.length; i++) {
               var li = document.createElement('LI');
               var icon = document.createElement('A');
               if (r[i] && r[i].url) {
                  icon.href = r[i].url;
                  icon.target = '_laconica'; 
                  icon.title = 'Visit ' + r[i].screen_name + ' at ' + r[i].url;
               } else {
                  icon.href = 'http://' + $.a.server + '/' + r[i].screen_name;
                  icon.target = '_laconica'; 
                  icon.title = 'Visit ' + r[i].screen_name + ' at http://' + $.a.server + '/' + r[i].screen_name;
               }

               var img = document.createElement('IMG');
               img.src = r[i].profile_image_url;
               icon.appendChild(img);
               li.appendChild(icon); 
               
               var user = document.createElement('CITE');
               var a = document.createElement('A');
               a.rel = r[i].id;
               a.rev = r[i].name;
               a.id = r[i].screen_name;
               a.innerHTML = r[i].name; 
               a.href = 'http://' + $.a.server + '/' + r[i].screen_name;
               a.onclick = function() {
                  $.f.changeUserTo(this);
                  return false;
               };
               user.appendChild(a);
               li.appendChild(user);
               var updated = document.createElement('DATE');
               if (r[i].status && r[i].status.created_at) {
                  var date_link = document.createElement('A');
                  date_link.innerHTML = r[i].status.created_at.split(/\+/)[0];
                  date_link.href = 'http://' + $.a.server + '/notice/' + r[i].status.id;
                  date_link.target = '_laconica';
                  updated.appendChild(date_link);
                  if (r[i].status.in_reply_to_status_id) {
                     updated.appendChild(document.createTextNode(' in reply to '));
                     var in_reply_to = document.createElement('A');
                     in_reply_to.innerHTML = r[i].status.in_reply_to_status_id;
                     in_reply_to.href = 'http://' + $.a.server + '/notice/' + r[i].status.in_reply_to_status_id;
                     in_reply_to.target = '_laconica';
                     updated.appendChild(in_reply_to);
                  }
               } else {
                  updated.innerHTML = 'has not updated yet';
               }
               li.appendChild(updated);
               var p = document.createElement('P');
               if (r[i].status && r[i].status.text) {
                  var raw = r[i].status.text;
                  var cooked = raw;
                  cooked = cooked.replace(/http:\/\/([^ ]+)/g, "<a href=\"http://$1\" target=\"_laconica\">http://$1</a>");
                  cooked = cooked.replace(/@([\w*]+)/g, '@<a href="http://' + $.a.server + '/$1" target=\"_laconica\">$1</a>');
                  cooked = cooked.replace(/#([\w*]+)/g, '#<a href="http://' + $.a.server + '/tag/$1" target="_laconica">$1</a>');
                  p.innerHTML = cooked;
               }
               li.appendChild(p);
               var a = p.getElementsByTagName('A');
               for (var j = 0; j < a.length; j++) {
                  if (a[j].className == 'changeUserTo') {
                     a[j].className = '';
                     a[j].href = 'http://' + $.a.server + '/' + a[j].innerHTML;
                     a[j].rel = a[j].innerHTML;
                     a[j].onclick = function() { 
                        $.f.changeUserTo(this); 
                        return false;
                     } 
                  }
               }
               $.s.r.appendChild(li);
            }         
         },
         sortArray : function(r, k, x) {
            if (window.createPopup) { 
               return r; 
            }
            function s(a, b) {
               if (x === true) {
                   return b[k] - a[k];
               } else {
                   return a[k] - b[k];
               }
            }
            r = r.sort(s);
            return r;
         },         
         runScript : function(url, id) {
            var s = document.createElement('script');
            s.id = id;
            s.type ='text/javascript';
            s.src = url;
            document.getElementsByTagName('body')[0].appendChild(s);
         },
         removeScript : function(id) {
            if (document.getElementById(id)) {
               var s = document.getElementById(id);
               s.parentNode.removeChild(s);
            }
         }         
      };
   }();
//   var thisScript = /^https?:\/\/[^\/]*r8ar.com\/identica-badge.js$/;
   var thisScript = /identica-badge.js$/;
   if(typeof window.addEventListener !== 'undefined') {
      window.addEventListener('load', function() { $.f.init(thisScript); }, false);
   } else if(typeof window.attachEvent !== 'undefined') {
      window.attachEvent('onload', function() { $.f.init(thisScript); });
   }
} )();

