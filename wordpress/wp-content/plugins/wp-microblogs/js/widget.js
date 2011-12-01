new function(){
    dom = [];
    dom.isReady = false;
    dom.isFunction = function(obj){
        return Object.prototype.toString.call(obj) === "[object Function]";
    }
    dom.Ready = function(fn){
        dom.initReady();
        if(dom.isFunction(fn)){
            if(dom.isReady){
                fn();
            }else{
                dom.push(fn);
            }
        }
    }
    dom.fireReady = function(){
        if (dom.isReady)  return;
        dom.isReady = true;
        for(var i = 0, n = dom.length; i < n; i++){
            var fn = dom[i];
            fn();
        }
        dom.length = 0;
    }
    dom.initReady = function(){
        if (document.addEventListener) {
            document.addEventListener( "DOMContentLoaded", function(){
                document.removeEventListener( "DOMContentLoaded", arguments.callee, false );
                dom.fireReady();
            }, false );
        }else{
            if (document.getElementById) {
                document.write("<script id=\"ie-domReady\" defer='defer'src=\"//:\"><\/script>");
                document.getElementById("ie-domReady").onreadystatechange = function() {
                    if (this.readyState === "complete") {
                        dom.fireReady();
                        this.onreadystatechange = null;
                        this.parentNode.removeChild(this)
                    }
                };
            }
        }
    }
}

var setScroll = function(wrapper, speed) {
    var container = getElementsByClass('mcontainer', wrapper, 'div')[0];
    var up = getElementsByClass('up', wrapper, 'a')[0];
    var down = getElementsByClass('down', wrapper, 'a')[0];
    var obj = getElementsByClass('microblogs', container, 'ul')[0];
    if (!up || !down || !obj)
        return;
    var over_timer, down_timer;
    speed = speed || 10;
    var scrollingUpSlow = function() {scrolling(-3);}
    var scrollingUpFast = function() {scrolling(-6);}
    var scrollingDownSlow = function() {scrolling(3);}
    var scrollingDownFast = function() {scrolling(6);}
    var scrolling = function(step) {
        var containerTop = container.scrollTop + step;
        var max = obj.offsetHeight - container.clientHeight;
        container.scrollTop = step > 0 ? Math.min(containerTop, max) : Math.max(containerTop, 0);
    }
    up.onmouseover = function() {clearInterval(down_timer); over_timer = setInterval(scrollingUpSlow, speed);}
    up.onmouseout = function() {clearInterval(over_timer);}
    up.onmousedown = function() {down_timer = setInterval(scrollingUpFast, speed);}
    up.onmouseup = function() {clearInterval(down_timer);}
    down.onmouseover = function() {clearInterval(down_timer); over_timer = setInterval(scrollingDownSlow, speed);}
    down.onmouseout = function() {clearInterval(over_timer);}
    down.onmousedown = function() {down_timer = setInterval(scrollingDownFast, speed);}
    down.onmouseup = function() {clearInterval(down_timer);}
}

function getElementsByClass(searchClass, node, tag) {
    var classElements = new Array();
    if ( node == null )
        node = document;
    if ( tag == null )
        tag = '*';
    var els = node.getElementsByTagName(tag);
    var elsLen = els.length;
    var pattern = new RegExp("(^|\\s)" + searchClass + "(\\s|$)");
    for (var i = 0, j = 0; i < elsLen; i++) {
        if ( pattern.test(els[i].className) ) {
            classElements[j] = els[i];
            j++;
        }
    }
    return classElements;
}

function setImagesWidth(containerClass, containerTag) {
    var picContainers = getElementsByClass(containerClass, document, containerTag);
    var picContainer, picImg, i;
    for (i in picContainers) {
        picContainer = picContainers[i];
        if (picContainer.getElementsByTagName) {
            picImg = picContainer.getElementsByTagName('img')[0];
            if (picImg.width > picContainer.offsetWidth) {
                picImg.height = picContainer.offsetWidth / picImg.width *  picImg.height;
                picImg.width = picContainer.offsetWidth;
            }
        }
    }
}

function externalLinks() {
    var microblogs = getElementsByClass('mwrapper', null, 'div');
    var i;
    for (i in microblogs) {
        var anchors = microblogs[i].getElementsByTagName("a");
        for (var j=0; j < anchors.length; j++) {
            var anchor = anchors[j];
            var rel = anchor.getAttribute("rel");
            if (rel && rel.match(new RegExp("(^|\\s)external(\\s|$)")))
                anchor.target = "_blank";
        }
    }
}

dom.Ready(function() {
    externalLinks();

    var i;
    var mwrapper = getElementsByClass('mwrapper', document, 'div');
    for (i in mwrapper) {
        setScroll(mwrapper[i]);
    }
});