// use jQuery and hoverIntent if loaded
if ( typeof(jQuery) != 'undefined' ) {
	if ( typeof(jQuery.fn.hoverIntent) == 'undefined' )
		(function(a){a.fn.hoverIntent=function(l,j){var m={sensitivity:7,interval:100,timeout:0};m=a.extend(m,j?{over:l,out:j}:l);var o,n,h,d;var e=function(f){o=f.pageX;n=f.pageY};var c=function(g,f){f.hoverIntent_t=clearTimeout(f.hoverIntent_t);if((Math.abs(h-o)+Math.abs(d-n))<m.sensitivity){a(f).unbind("mousemove",e);f.hoverIntent_s=1;return m.over.apply(f,[g])}else{h=o;d=n;f.hoverIntent_t=setTimeout(function(){c(g,f)},m.interval)}};var i=function(g,f){f.hoverIntent_t=clearTimeout(f.hoverIntent_t);f.hoverIntent_s=0;return m.out.apply(f,[g])};var b=function(q){var f=this;var g=(q.type=="mouseover"?q.fromElement:q.toElement)||q.relatedTarget;while(g&&g!=this){try{g=g.parentNode}catch(q){g=this}}if(g==this){if(a.browser.mozilla){if(q.type=="mouseout"){f.mtout=setTimeout(function(){k(q,f)},30)}else{if(f.mtout){f.mtout=clearTimeout(f.mtout)}}}return}else{if(f.mtout){f.mtout=clearTimeout(f.mtout)}k(q,f)}};var k=function(p,f){var g=jQuery.extend({},p);if(f.hoverIntent_t){f.hoverIntent_t=clearTimeout(f.hoverIntent_t)}if(p.type=="mouseover"){h=g.pageX;d=g.pageY;a(f).bind("mousemove",e);if(f.hoverIntent_s!=1){f.hoverIntent_t=setTimeout(function(){c(g,f)},m.interval)}}else{a(f).unbind("mousemove",e);if(f.hoverIntent_s==1){f.hoverIntent_t=setTimeout(function(){i(g,f)},m.timeout)}}};return this.mouseover(b).mouseout(b)}})(jQuery);

	jQuery(document).ready(function($){
		$('#wpadminbar').removeClass('nojq').removeClass('nojs').find('li.menupop').hoverIntent({
			over: function(e){
				$(this).addClass('hover');
			},
			out: function(e){
				$(this).removeClass('hover');
			},
			timeout: 180,
			sensitivity: 7,
			interval: 100
		});

		$('#wp-admin-bar-get-shortlink').click(function(e){
			e.preventDefault();
			$(this).addClass('selected').children('.shortlink-input').blur(function(){
				$(this).parents('#wp-admin-bar-get-shortlink').removeClass('selected');
			}).focus().select();
		});

		$('.ab-top-menu > li > .ab-item').bind('focus.adminbar', function(){
			$(this).parent().addClass('hover').find('.ab-item').each(function(i, el){
				var node = $(el), tab = node.attr('tabindex');
				if ( tab )
					node.attr('tabindex', '0').attr('tabindex', tab);
			});
		}).bind('blur.adminbar', function(){
			var t = $(this);

			setTimeout(function(){
				if ( !t.siblings('div').find('.ab-item:focus').length )
					t.parent().removeClass('hover');
			}, 200);
		});

		$('.ab-top-menu li ul li .ab-item').bind('blur.adminbar', function(){
			var top = $(this).parents('li.menupop');

			setTimeout(function(){
				if ( !top.find('.ab-item:focus').length )
					top.removeClass('hover');
			}, 150);
		});

	});
} else {
	(function(d, w) {
		var addEvent = function( obj, type, fn ) {
			if (obj.addEventListener)
				obj.addEventListener(type, fn, false);
			else if (obj.attachEvent)
				obj.attachEvent('on' + type, function() { return fn.call(obj, window.event);});
		},

		aB, hc = new RegExp('\\bhover\\b', 'g'), q = [],
		rselected = new RegExp('\\bselected\\b', 'g'),

		/**
		 * Get the timeout ID of the given element
		 */
		getTOID = function(el) {
			var i = q.length;
			while( i-- )
				if ( q[i] && el == q[i][1] )
					return q[i][0];
			return false;
		},

		addHoverClass = function(t) {
			var i, id, inA, hovering, ul, li,
				ancestors = [],
				ancestorLength = 0;

			while ( t && t != aB && t != d ) {
				if( 'LI' == t.nodeName.toUpperCase() ) {
					ancestors[ ancestors.length ] = t;
					id = getTOID(t);
					if ( id )
						clearTimeout( id );
					t.className = t.className ? ( t.className.replace(hc, '') + ' hover' ) : 'hover';
					hovering = t;
				}
				t = t.parentNode;
			}

			// Remove any selected classes.
			if ( hovering && hovering.parentNode ) {
				ul = hovering.parentNode;
				if ( ul && 'UL' == ul.nodeName.toUpperCase() ) {
					i = ul.childNodes.length;
					while ( i-- ) {
						li = ul.childNodes[i];
						if ( li != hovering )
							li.className = li.className ? li.className.replace( rselected, '' ) : '';
					}
				}
			}

			/* remove the hover class for any objects not in the immediate element's ancestry */
			i = q.length;
			while ( i-- ) {
				inA = false;
				ancestorLength = ancestors.length;
				while( ancestorLength-- ) {
					if ( ancestors[ ancestorLength ] == q[i][1] )
						inA = true;
				}

				if ( ! inA )
					q[i][1].className = q[i][1].className ? q[i][1].className.replace(hc, '') : '';
			}
		},

		removeHoverClass = function(t) {
			while ( t && t != aB && t != d ) {
				if( 'LI' == t.nodeName.toUpperCase() ) {
					(function(t) {
						var to = setTimeout(function() {
							t.className = t.className ? t.className.replace(hc, '') : '';
						}, 500);
						q[q.length] = [to, t];
					})(t);
				}
				t = t.parentNode;
			}
		},

		clickShortlink = function(e) {
			var i, l, node,
				t = e.target || e.srcElement;

			// Make t the shortlink menu item, or return.
			while ( true ) {
				// Check if we've gone past the shortlink node,
				// or if the user is clicking on the input.
				if ( ! t || t == d || t == aB )
					return;
				// Check if we've found the shortlink node.
				if ( t.id && t.id == 'wp-admin-bar-get-shortlink' )
					break;
				t = t.parentNode;
			}

			// IE doesn't support preventDefault, and does support returnValue
			if ( e.preventDefault )
				e.preventDefault();
			e.returnValue = false;

			if ( -1 == t.className.indexOf('selected') )
				t.className += ' selected';

			for ( i = 0, l = t.childNodes.length; i < l; i++ ) {
				node = t.childNodes[i];
				if ( node.className && -1 != node.className.indexOf('shortlink-input') ) {
					node.focus();
					node.select();
					node.onblur = function() {
						t.className = t.className ? t.className.replace( rselected, '' ) : '';
					};
					break;
				}
			}
			return false;
		};

		addEvent(w, 'load', function() {
			aB = d.getElementById('wpadminbar');

			if ( d.body && aB ) {
				d.body.appendChild( aB );

				if ( aB.className )
					aB.className = aB.className.replace(/nojs/, '');

				addEvent(aB, 'mouseover', function(e) {
					addHoverClass( e.target || e.srcElement );
				});

				addEvent(aB, 'mouseout', function(e) {
					removeHoverClass( e.target || e.srcElement );
				});

				addEvent(aB, 'click', clickShortlink );
			}

			if ( w.location.hash )
				w.scrollBy(0,-32);
		});
	})(document, window);

}

