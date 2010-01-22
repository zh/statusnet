// identica badge -- updated to work with the native API, 12-4-2008
// Modified to point to Identi.ca, 2-20-2009 by Zach
// Modified for XHTML, 27-9-2009 by Will Daniels
// (see http://willdaniels.co.uk/blog/tech-stuff/26-identica-badge-xhtml)
// copyright Kent Brewster 2008
// see http://kentbrewster.com/identica-badge for info

function createHTMLElement(tagName) {
   if(document.createElementNS)
      var elem = document.createElementNS("http://www.w3.org/1999/xhtml", tagName);
   else
      var elem = document.createElement(tagName);

   return elem;
}

function isNumeric(value) {
  if (value == null || !value.toString().match(/^[-]?\d*\.?\d*$/)) return false;
  return true;
}

function markupPost(raw, server) {
  var start = 0; var p = createHTMLElement('p');

  raw.replace(/((http|https):\/\/|\!|@|#)(([\w_]+)?[^\s]*)/g,
    function(sub, type, scheme, url, word, offset, full)
    {
      if(!scheme && !word) return; // just punctuation
      var label = ''; var href = '';
      var pretext = full.substr(start, offset - start);

      moniker = word.split('_'); // behaviour with underscores differs
      if(type == '#') moniker = moniker.join('');
      else word = moniker = moniker[0].toLowerCase();

      switch(type) {
        case 'http://': case 'https://': // html links
          href = scheme + '://' + url; break;
        case '@': // link users
          href = 'http://' + server + '/' + moniker; break;
        case '!': // link groups
          href = 'http://' + server + '/group/' + moniker; break;
        case '#': // link tags
          href = 'http://' + server + '/tag/' + moniker; break;
        default: // bad call (just reset position for text)
          start = offset;
      }
      if(scheme) { // only urls will have scheme
        label = sub; start = offset + sub.length;
      } else {
        label = word; pretext += type;
        start = offset + word.length + type.length;
      }
      p.appendChild(document.createTextNode(pretext));

      var link = createHTMLElement('a');
      link.appendChild(document.createTextNode(label));
      link.href = href; link.target = '_statusnet';
      p.appendChild(link);
    });

  if(start != raw.length) {
    endtext = raw.substr(start);
    p.appendChild(document.createTextNode(endtext));
  }
  return p;
}
(function() {
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
            var theScripts = document.getElementsByTagName('script');
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
            // fix inout units
            if(isNumeric($.a.width)) {
               $.a.innerWidth = ($.a.width - 22) + 'px'; $.a.width += 'px';
            } else {
               $.a.innerWidth = 'auto';
            }
            if(isNumeric($.a.height)) $.a.height += 'px';
         },
         buildPresentation : function () {
            var setZoom = ''; if(navigator.appName == 'Microsoft Internet Explorer') setZoom = 'zoom:1;';
            var ns = createHTMLElement('style');
            document.getElementsByTagName('head')[0].appendChild(ns);
            if (!window.createPopup) {
               ns.appendChild(document.createTextNode(''));
               ns.setAttribute("type", "text/css");
            }
            var s = document.styleSheets[document.styleSheets.length - 1];
            var rules = {
               "" : "{margin:0px;padding:0px;width:" + $.a.width + ";background:" + $.a.background + ";border:" + $.a.border + ";font:87%/1.2em tahoma, veranda, arial, helvetica, clean, sans-serif;}",
               "a" : "{cursor:pointer;text-decoration:none;}",
               "a:hover" : "{text-decoration:underline;}",
               ".cite" : "{" + setZoom + "font-weight:bold;margin:0px 0px 0px 4px;padding:0px;display:block;font-style:normal;line-height:" + ($.a.thumbnailSize/2) + "px;vertical-align:middle;}",
               ".cite a" : "{color:#C15D42;}",
               ".date":"{margin:0px 0px 0px 4px;padding:0px;display:block;font-style:normal;line-height:" + ($.a.thumbnailSize/2) + "px;vertical-align:middle;}",
               ".date:after" : "{clear:both;content:\".\"; display:block;height:0px;visibility:hidden;}",
               ".date a" : "{color:#676;}",
               "h3" : "{margin:0px;padding:" + $.a.padding + "px;font-weight:bold;background:" + $.a.headerBackground + " url('http://" + $.a.server + "/favicon.ico') " + $.a.padding + "px 50% no-repeat;padding-left:" + ($.a.padding + 20) + "px;}",
               "h3.loading" : "{background-image:url('http://l.yimg.com/us.yimg.com/i/us/my/mw/anim_loading_sm.gif');}",
               "h3 a" : "{font-size:92%; color:" + $.a.headerColor + ";}",
               "h4" : "{font-weight:normal;background:" + $.a.headerBackground + ";text-align:right;margin:0px;padding:" + $.a.padding + "px;}",
               "h4 a" : "{font-size:92%; color:" + $.a.headerColor + ";}",
               "img":"{float:left;height:" + $.a.thumbnailSize + "px;width:" + $.a.thumbnailSize + "px;border:" + $.a.thumbnailBorder + ";margin-right:" + $.a.padding + "px;}",
               "p" : "{margin:2px 0px 0px 0px;padding:0px;width:" + $.a.innerWidth + ";overflow:hidden;line-height:normal;}",
               "p a" : "{color:#C15D42;}",
               "ul":"{margin:0px; padding:0px; height:" + $.a.height + ";width:" + $.a.innerWidth + ";overflow:auto;}",
               "ul li":"{background:" + $.a.evenBackground + ";margin:0px;padding:" + $.a.padding + "px;list-style:none;width:auto;overflow:hidden;border-bottom:1px solid #D8E2D7;}",
               "ul li:hover":"{background:#f3f8ea;}"
            };
            var ieRules = "";
            // brute-force each and every style rule here to !important
            // sometimes you have to take off and nuke the site from orbit; it's the only way to be sure
            for (var z in rules) {
               if(z.charAt(0)=='.') var selector = '.' + trueName + '-' + z.substring(1);
               else var selector = '.' + trueName + ' ' + z;
               var rule = rules[z];
               if (typeof rule === 'string') {
                  var important = rule.replace(/;/gi, '!important;');
                  if (!window.createPopup) {
                     var theRule = document.createTextNode(selector + important + '\n');
                     ns.appendChild(theRule);
                  } else {
                     ieRules += selector + important;
                  }
               }
            }
            if (window.createPopup) { s.cssText = ieRules; }
         },
         buildStructure : function() {
            $.s = createHTMLElement('div');
            $.s.className = trueName;         
            $.s.h = createHTMLElement('h3');
            $.s.h.a = createHTMLElement('a');
            $.s.h.a.target = '_statusnet';
            $.s.h.appendChild($.s.h.a);
            $.s.appendChild($.s.h);
            $.s.r = createHTMLElement('ul');
            $.s.appendChild($.s.r);
            $.s.f = createHTMLElement('h4');
            var a = createHTMLElement('a');
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
               var a = createHTMLElement('a');
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
            $.s.h.a.appendChild(document.createTextNode(el.rev + $.a.headerText));
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
            $.s.h.className = ''; // for IE6
            $.s.h.removeAttribute('class');
            for (var i = 0; i < r.length; i++) {
               var li = createHTMLElement('li');
               var icon = createHTMLElement('a');
               if (r[i] && r[i].url) {
                  icon.href = r[i].url;
                  icon.target = '_statusnet'; 
                  icon.title = 'Visit ' + r[i].screen_name + ' at ' + r[i].url;
               } else {
                  icon.href = 'http://' + $.a.server + '/' + r[i].screen_name;
                  icon.target = '_statusnet'; 
                  icon.title = 'Visit ' + r[i].screen_name + ' at http://' + $.a.server + '/' + r[i].screen_name;
               }

               var img = createHTMLElement('img');
               img.alt = 'profile image for ' + r[i].screen_name;
               img.src = r[i].profile_image_url;
               icon.appendChild(img);
               li.appendChild(icon);
               
               var user = createHTMLElement('span');
               user.className = trueName + '-cite';
               var a = createHTMLElement('a');
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
               var updated = createHTMLElement('span');
               updated.className = trueName + '-date';
               if (r[i].status && r[i].status.created_at) {
                  var date_link = createHTMLElement('a');
                  date_link.innerHTML = r[i].status.created_at.split(/\+/)[0];
                  date_link.href = 'http://' + $.a.server + '/notice/' + r[i].status.id;
                  date_link.target = '_statusnet';
                  updated.appendChild(date_link);
                  if (r[i].status.in_reply_to_status_id) {
                     updated.appendChild(document.createTextNode(' in reply to '));
                     var in_reply_to = createHTMLElement('a');
                     in_reply_to.innerHTML = r[i].status.in_reply_to_status_id;
                     in_reply_to.href = 'http://' + $.a.server + '/notice/' + r[i].status.in_reply_to_status_id;
                     in_reply_to.target = '_statusnet';
                     updated.appendChild(in_reply_to);
                  }
               } else {
                  updated.innerHTML = 'has not updated yet';
               }
               li.appendChild(updated);
               var p = createHTMLElement('p');
               if (r[i].status && r[i].status.text) {
                  var raw = r[i].status.text;
                  p = markupPost(raw, $.a.server);
               }
               li.appendChild(p);
               var a = p.getElementsByTagName('a');
               for (var j = 0; j < a.length; j++) {
                  if (a[j].className == 'changeUserTo') {
                     a[j].removeAttribute('class');
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
            var s = createHTMLElement('script');
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


