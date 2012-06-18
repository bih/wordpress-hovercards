/*
	Plugin Name: WordPress Hovercards
	Plugin URI: http://github.com/bilawal360/wordpress-hovercards
	Description: Enable post & pages hovercards within your WordPress blog.
	Author: Bilawal Hameed
	Author URI: http://www.bilawal.co.uk
	License: GPLv2
	Version: 1.0.1
*/

var WP_Hovercards = {
	
	/* @note: This is to ensure compatibility for future versions. */
	version: '1.0.1',
	
	/* @note: This is to stop any problems loading multiple hovercards at once. */
	is_busy: false,
	
	/* @note: This prevents making constant requests to the server. It's a bit pointless for partially static data. */
	cache: new Array(),
	
	html: function(obj, json) {
		// We'll be storing our entire output in the html variable
		var html;
		
		// We're starting off with the inner wrapper
		html = "<div class='content" + json.post.post_custom_class + "'";
		
		// This checks if there's a background image attached
		if(json.post.post_image) {
			html += " style='background-image:url(" + json.post.post_timthumb + "&w=700&h=200&zc=1&f=0";
			// If the user requests to disable the blur, let's do that
			if(json.post.post_custom_class.indexOf("nogblur") == -1) { html += "|8"; }
			// And if they want to negate the image
			if(json.post.post_custom_class.indexOf("negate") > -1) { html += "|1"; }
			// Or perhaps black and white background?
			if(json.post.post_custom_class.indexOf("bw") > -1) { html += "|2"; }
			html += ");'";
		}
		
		// Now to add the post title
		html += "> <h3>" + json.post.post_title + "</h3>";
		
		// The excerpt
		html += "<div class='excerpt'><p>" + json.post.post_excerpt + "</p></div>";
		
		// Details on the right
		html += "<div class='details'><ul><li>Posted: " + json.post.post_date + "</li><li>Category: " + json.post.post_category_single + "</li><li>" + json.post.post_comments + " Comments</li></ul></div>";
		
		// The small wordpress icon on the right
		html += "<div class='wp'></div>";
		
		// Now we're on the last wrapper
		return html + "</div>";
	},
	
	/*
		@note: WP_Hovercards.response is called when a call to WordPress (server side) has returned data
	*/
	response: function(_url, obj, event, output) {
		// If the _url has unwanted data, let's strip that from it
		_url = _url.replace('?wp_hovercards=json', '');
		
		// If the HTML element isn't there, it won't work
		if(jQuery('div.wp-hovercards-root').length == 0) {
			return;
		}
		
		if(_url == (parent.document.location || document.location)) {
			// If this is the page we're on - surely we don't need it?
			return;
		}
		
		if(output.version !== WP_Hovercards.version) {
			// A different version to the one on the server
			// No problems right now, but in future versions, it may be needed.
		}
		
		// If we didn't make a successful request, let's still cache this to the engine.
		if(output.res !== 'success') {
			WP_Hovercards.cache[_url] = output;
			return;
		}
		
		// Let's show the element now!
		jQuery('div.wp-hovercards-root').show().css({
			top: (event.pageY + 25) + 'px',
			left: (event.clientX - 17) + 'px'
		});
		
		// Now we're on the element, anywhere we move now will be tracked
		jQuery(document).mousemove(function(ev){
			// Let's update the CSS accordingly
			jQuery('div.wp-hovercards-root').show().css({
				top: (ev.pageY + 25) + 'px',
				left: (ev.clientX - 17) + 'px'
			});
		});
		
		// And now update the HTML
		jQuery('div.wp-hovercards-root').html( WP_Hovercards.html(obj, output) );
				
		// And store in the local cache!
		if(typeof WP_Hovercards.cache[_url] === 'undefined') {
			WP_Hovercards.cache[_url] = output;
		}
	},
	
	// This is to cancel all hovercards within the browser.
	clear: function(obj, event) {
	 	// A bug fix for showing the native 'title' value
		if(jQuery(obj).data('title')) {
			jQuery(obj).attr('title', jQuery(obj).data('title'));
		}
		
		// We don't want to track mouse movement anymore
		jQuery(document).unbind('mousemove');
		
		// Empty the hovercard UI element now
		jQuery("div.wp-hovercards-root").hide().empty();
	},
	
	/*
		@note: By passing in jQuery selector, WordPress Hovercards can pull up the appropriate hovercard.
		@example: WP_Hovercard.call(jQuery("a:first-child"));
	*/
	call: function(obj, event) {
		// _url will store the URL address
		var _url = jQuery(obj).attr('href') || '';
		
		// If it has a hashtag in the URL, let's strip that
		if(_url.indexOf("#") >= 0) {
			_url = _url.split('#');
			_url = _url[0];
		}
		
		// If there's a cache stored in the browser, let's pull that up and save a HTTP request
		if(typeof WP_Hovercards.cache[_url] !== 'undefined') {
			return WP_Hovercards.response(_url, obj, event, WP_Hovercards.cache[_url]);
		}
		
		// This is to stop any requests currently being made. We don't want them anymore!
		if(WP_Hovercards.is_busy != false) {
			WP_Hovercards.is_busy.abort();
			WP_Hovercards.is_busy = false;
		}
		
		// Naw.. we need to make a HTTP request to pull up the data.
		WP_Hovercards.is_busy = jQuery.getJSON(_url + '?wp_hovercards=json', function(data){ WP_Hovercards.response(_url, obj, event, data); });
	},
	
	/*
		@note: WP_Hovercards.init is called when the document is fully loaded to start up this beauty.
	*/
	init: function()
	{
	
		// We need jQuery. It is mandatory, it provides too much of our awesomeness.
		if( typeof jQuery === 'undefined' )
		{
			console.log('jQuery Library needed to run WordPress Hovercards.');
			return;
		}
		
		// Now let's include the root element in the browser that will hold hovercard HTML
		jQuery("body").append("<div class='wp-hovercards-root'></div>");
		
		// This enables us to filter down to internal links. 99% reliable.
		jQuery.expr[':'].internal = function(obj, index, meta, stack){
			// Obtain the root domain
			var url = jQuery(obj).attr('href')||'';
			var rootUrl = 'http://' + document.location.host + '/';
			
			var ifExternalLink = 'http';
			var isInternalLink = 0;

			// Run checks to see if it corresponds to our root url
			// Disables /wp-admin/ and /wp-content/ url's as they are meaningless
			if( url.substr(0, ifExternalLink.length) === ifExternalLink && url.indexOf("/wp-admin/") === -1 && url.indexOf("/wp-content/") === -1) {
 				if( url.substring(0, rootUrl.length) === rootUrl){
					isInternalLink = 1;
				}
			} else if(url.indexOf(':') == -1) {
				isInternalLink = 1;
			}
			
			// Result
			return isInternalLink;
		};
		
		// This holds the selector we will send to jQuery.
		var selector = '';
		
		if(jQuery('.entry-content').length) {
			selector = '.entry-content ';
		}
		
		else if(jQuery('.post-content').length) {
			selector = '.post-content ';
		}
		
		else if(jQuery('.content').length) {
			selector = '.content ';
		}
		
		else if(jQuery('#content').length) {
			selector = '#content ';
		}
		
		// Got the selector, let's run this.
		jQuery(selector + "a:internal:not(.disable), a.hovercards:internal, a#hovercards:internal").mouseenter(function(event){
			var _this = jQuery(this);
			
			// A bug fix to stop the HTML 'title' showing!
			if(_this.attr('title')) {
				_this.data('title', _this.attr('title')).removeAttr('title');
			}
			
			WP_Hovercards.call(_this, event);
		}).mouseleave(function(event){
			// And when they browse out, we need to turn off the hovercard!
			WP_Hovercards.clear(jQuery(this), event);
		});

	}
};

/*
	@note: This requires jQuery to run. In the event you want WP Hovercards to run at another time, remove the line below and call WP_Hovercards.init() when you want to call it. It must be called once the document has loaded!
*/
jQuery(document).ready(function(){ WP_Hovercards.init(); });