/*
  Copyright (c) 2009 Open Lab, http://www.open-lab.com/
  Written by Roberto Bicchierai http://roberto.open-lab.com.
  Permission is hereby granted, free of charge, to any person obtaining
  a copy of this software and associated documentation files (the
  "Software"), to deal in the Software without restriction, including
  without limitation the rights to use, copy, modify, merge, publish,
  distribute, sublicense, and/or sell copies of the Software, and to
  permit persons to whom the Software is furnished to do so, subject to
  the following conditions:

  The above copyright notice and this permission notice shall be
  included in all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
  MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
  NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
  LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
  OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
  WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
/**
   * options.tags an object array [{tag:"tag1",freq:1},{tag:"tag2",freq:2}, {tag:"tag3",freq:3},{tag:"tag4",freq:4} ].
   * options.jsonUrl an url returning a json object array in the same format of options.tag. The url will be called with
   *                              "search" parameter to be used server side to filter results
   * option.autoFilter true/false  default=true when active show only matching tags, "false" should be used for server-side filtering
   * option.autoStart true/false  default=false when active dropdown will appear entering field, otherwise when typing
   * options.sortBy "frequency"|"tag"|"none"  default="tag"
   * options.tagSeparator default="," any separator char as space, comma, semicolumn
   * options.boldify true/false default trrue boldify the matching part of tag in dropdown
   *
   * options.suggestedTags callback an object array like ["sugtag1","sugtag2","sugtag3"]
   * options.suggestedTagsPlaceHolder  jquery proxy for suggested tag placeholder. When placeholder is supplied (hence unique), tagField should be applied on a single input
   *                          (something like  $("#myTagFiled").tagField(...) will works fine: $(":text").tagField(...) probably not!)
   */

if (typeof(String.prototype.trim)  == "undefined"){
    String.prototype.trim = function () {
      return this.replace(/^\s*(\S*(\s+\S+)*)\s*$/, "$1");
    };
}



jQuery.fn.tagInput = function(options) {
  // --------------------------  start default option values --------------------------
  if (!options.tags && !options.jsonUrl) {
    options.tags = [ { tag:"tag1", freq:1 }, { tag:"tag2", freq:2 }, { tag:"tag3", freq:3 }, { tag:"tag4", freq:4 } ];
  }

  if (typeof(options.tagSeparator) == "undefined")
    options.tagSeparator = ",";

  if (typeof(options.autoFilter) == "undefined")
    options.autoFilter = true;

  if (typeof(options.autoStart) == "undefined")
    options.autoStart = false;

  if (typeof(options.boldify) == "undefined")
    options.boldify = true;

  if (typeof(options.animate) == "undefined")
    options.animate = true;

  if (typeof(options.animate) != "function") {
    options._animate = options.animate;
    options.animate = function(show, el, cb) {
      var func = (options._animate) ? (show ? 'fadeIn' : 'fadeOut') : (show ? 'show' : 'hide');
      el[func](cb);
    }
  }

  if (typeof(options.sortBy) == "undefined")
    options.sortBy = "tag";

  if (typeof(options.sortBy) == "string") {
    options._sortBy = options.sortBy;
    options.sortBy = function(obj) { return obj[options._sortBy]; }
  }

  if (typeof(options.formatLine) == "undefined")
    options.formatLine = function (i, obj, search, matches) {
      var tag = obj.tag;
      if (options.boldify && matches) {
        tag = "<b>" + tag.substring(0, search.length) + "</b>" + tag.substring(search.length);
      }

      var line = $("<div/>");
      line.append("<div class='tagInputLineTag'>" + tag + "</div>");
      if (obj.freq)
        line.append("<div class='tagInputLineFreq'>" + obj.freq + "</div>");
      return line;
    }

  if (typeof(options.formatValue == "undefined"))
    options.formatValue = function (obj, i) {
      return obj.tag;
    }
  // --------------------------  end default option values --------------------------


  this.each(function() {

    var theInput = $(this);
    var theDiv;

    theInput.addClass("tagInput");
    theInput.tagOptions=options;
    theInput.attr('autocomplete', 'off');

    var suggestedTagsPlaceHolder=options.suggestedTagsPlaceHolder;
    //create suggested tags place if the case
    if (options.suggestedTags){
      if (!suggestedTagsPlaceHolder){
        //create a placeholder
        var stl=$("<div class='tagInputSuggestedTags'><span class='label'>suggested tags: </span><span class='tagInputSuggestedTagList'></span></div>");
        suggestedTagsPlaceHolder=stl.find(".tagInputSuggestedTagList");
        theInput.after(stl);
      }

      //fill with suggestions
      for (var tag in options.suggestedTags) {
        suggestedTagsPlaceHolder.append($("<span class='tag'>" + options.suggestedTags[tag] + "</span>"));
      }

      // bind click on suggestion tags
      suggestedTagsPlaceHolder.find(".tag").click(function() {
        var element = $(this);
        var val = theInput.val();
        var tag = element.text();

        //check if already present
        var re = new RegExp(tag + "\\b","g");
        if (containsTag(val, tag)) {
          val = val.replace(re, ""); //remove all the tag
          element.removeClass("tagUsed");
        } else {
          val = val + options.tagSeparator + tag;
          element.addClass("tagUsed");
        }
        theInput.val(refurbishTags(val));
//        selectSuggTagFromInput();

      });

    }


    // --------------------------  INPUT FOCUS --------------------------
    var tagInputFocus = function () {
      theDiv = $("#__tagInputDiv");
      // check if the result box exists
      if (theDiv.size() <= 0) {
        //create the div
        theDiv = $("<div id='__tagInputDiv' class='tagInputDiv' style='width:" + theInput.get(0).clientWidth + ";display:none; '></div>");
        theInput.after(theDiv);
        theDiv.css({left:theInput.position().left});
      }
      if (options.autoStart)
        tagInputRefreshDiv(theInput, theDiv);
    };


    // --------------------------  INPUT BLUR --------------------------
    var tagInputBlur = function () {
      // reformat string
      theDiv = $("#__tagInputDiv");
      theInput.val(refurbishTags(theInput.val()));

      options.animate(0, theDiv, function() {
        theDiv.remove();
      });
    };


    // --------------------------  INPUT KEYBOARD --------------------------
    var tagInputKey = function (e) {
      var rows = theDiv.find("div.tagInputLine");
      var rowNum = rows.index(theDiv.find("div.tagInputSel"));

      var ret = true;
      switch (e.which) {
        case 38: //up arrow
          rowNum = (rowNum < 1 ? 0 : rowNum - 1 );
          tagInputHLSCR(rows.eq(rowNum), true);
          break;

        case 40: //down arrow
          rowNum = (rowNum < rows.size() - 1 ? rowNum + 1 : rows.size() - 1 );
          tagInputHLSCR(rows.eq(rowNum), false);
          break;

        case 9: //tab
        case 13: //enter
          if (theDiv.is(":visible")){
            var theRow = rows.eq(rowNum);
            tagInputClickRow(theRow);
            ret = false;
          }
          break;

        case 27: //esc
          options.animate(0, theDiv);
          break;

        default:
          $(document).stopTime("tagInputRefresh");
          $(document).oneTime(400, "tagInputRefresh", function() {
            tagInputRefreshDiv();
          });
          break;
      }
      return ret;
    };


    // --------------------------  TAG DIV HIGHLIGHT AND SCROLL --------------------------
    var tagInputHLSCR = function(theRowJQ, isUp) {
      if (theRowJQ.size() > 0) {
        var div = theDiv.get(0);
        var theRow = theRowJQ.get(0);
        if (isUp) {
          if (theDiv.scrollTop() > theRow.offsetTop) {
            theDiv.scrollTop(theRow.offsetTop);
          }
        } else {
          if ((theRow.offsetTop + theRow.offsetHeight) > (div.scrollTop + div.offsetHeight)) {
            div.scrollTop = theRow.offsetTop + theRow.offsetHeight - div.offsetHeight;
          }
        }
        theDiv.find("div.tagInputSel").removeClass("tagInputSel");
        theRowJQ.addClass("tagInputSel");
      }
    };


    // --------------------------  TAG LINE CLICK --------------------------
    var tagInputClickRow = function(theRow) {
 
      var lastComma = theInput.val().lastIndexOf(options.tagSeparator);
      var sep= lastComma<=0? (""):(options.tagSeparator+ (options.tagSeparator==" "?"":" "));
      var newVal = (theInput.val().substr(0, lastComma) + sep + theRow.attr('id').replace('val-','')).trim();
      theInput.val(newVal);
      theDiv.hide();
      $().oneTime(200, function() {
        theInput.focus();
      });
    };


    // --------------------------  REFILL TAG BOX --------------------------
    var tagInputRefreshDiv = function () {

      var lastComma = theInput.val().lastIndexOf(options.tagSeparator);
      var search = theInput.val().substr(lastComma + 1).trim();


      // --------------------------  FILLING THE DIV --------------------------
      var fillingCallbak = function(tags) {
        tags = tags.sort(function (a, b) {
          if (options.sortBy(a) < options.sortBy(b))
            return 1;
          if (options.sortBy(a) > options.sortBy(b))
            return -1;
          return 0;
        });

        for (var i in tags) {
          tags[i]._val = options.formatValue(tags[i], i);
          var el = tags[i];
          var matches = el._val.toLocaleLowerCase().indexOf(search.toLocaleLowerCase()) == 0;
          if (!options.autoFilter || matches) {
            var line = $(options.formatLine(i, el, search, matches));
            if (!line.is('.tagInputLine'))
              line = $("<div class='tagInputLine'></div>").append(line);
            line.attr('id', 'val-' + el._val);
            theDiv.append(line);
          }
        }
        if (theDiv.html()!=""){
            options.animate(true, theDiv);
        }

        theDiv.find("div:first").addClass("tagInputSel");
        theDiv.find("div.tagInputLine").bind("click", function() {
          tagInputClickRow($(this));
        });
      };


      if (search != "" || options.autoStart) {
        theDiv.html("");

        if (options.tags)
          fillingCallbak(options.tags);
        else{
          var data = {search:search};
          $.getJSON(options.jsonUrl, data, fillingCallbak );
        }
      } else {
          options.animate(false, theDiv);
      }
    };

    // --------------------------  CLEAN THE TAG LIST FROM EXTRA SPACES, DOUBLE COMMAS ETC. --------------------------
    var refurbishTags = function (tagString) {
      var splitted = tagString.split(options.tagSeparator);
      var res = "";
      var first = true;
      for (var i = 0; i < splitted.length; i++) {
        if (splitted[i].trim() != "") {
          if (first) {
            first = false;
            res = res + splitted[i].trim();
          } else {
            res = res + options.tagSeparator+ (options.tagSeparator==" "?"":" ") + splitted[i].trim();
          }
        }
      }
      return( res);
    };

    // --------------------------  TEST IF TAG IS PRESENT --------------------------
    var containsTag=function (tagString,tag){
      var splitted = tagString.split(options.tagSeparator);
      var res="";
      var found=false;
      tag=tag.trim();
      for(i = 0; i < splitted.length; i++){
        var testTag=splitted[i].trim();
        if (testTag==tag){
          found=true;
          break;
        }
      }
      return found;
    };


    // --------------------------  SELECT TAGS BASING ON USER INPUT --------------------------
    var delayedSelectTagFromInput= function(){
      var element = $(this);
      $().stopTime("suggTagRefresh");
      $().oneTime(200, "suggTagRefresh", function() {
        selectSuggTagFromInput();
      });

    };

    var selectSuggTagFromInput = function () {
      var val = theInput.val();
      suggestedTagsPlaceHolder.find(".tag").each(function(){
        var el = $(this);
        var tag=el.text();

        //check if already present
        if (containsTag(val,tag)) {
          el.addClass("tagUsed");
        } else {
          el.removeClass("tagUsed");
        }
      });

    };




    // --------------------------  INPUT BINDINGS --------------------------
    $(this).bind("focus", tagInputFocus).bind("blur", tagInputBlur).bind("keydown", tagInputKey);
    if (options.suggestedTags)
      $(this).bind("keyup",delayedSelectTagFromInput) ;


  });
  return this;
};


