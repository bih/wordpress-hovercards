<?php
/*
	Plugin Name: WordPress Hovercards
	Plugin URI: http://github.com/bilawal360/wordpress-hovercards
	Description: Enable post & pages hovercards within your WordPress blog.
	Author: Bilawal Hameed
	Author URI: http://www.bilawal.co.uk
	License: GPLv2
	Version: 1.0.1
*/

// If you set WP_DEBUG to true, we'll automatically show errors from our app. I &hearts; integration.
if(constant('WP_DEBUG') == 0) {
	error_reporting(0);
}

// This defines the version of WordPress Hovercards
define('WP_HOVERCARDS', '1.0.1');

/*
	@added: v0.0.1
	@note: This runs the engine on the WordPress installation, and starts the chain.
*/
function wp_hovercards_init()
{
	// We need the $wp goodness in our plugin!
	global $wp;
	
	// I hope someone can find a better option than this. It won't be future-proof.
	$current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	
	// What should be detected at the end of the end of the URL to output JSON goodness.
	$endpoint = '?wp_hovercards=json';
	
	// It's quite obvious that if we don't have json_encode(), we can't do anything.
	if( ! function_exists('json_encode') )
	{
		return wp_hovercards_json_error('JSON encoder not found in native PHP');
	}
	
	// If $endpoint isn't found at the end of the URL, abort WordPress Hovercards.
	// The reason why this doesn't use wp_hovercards_json_error is because we don't want to throw an error
	if( substr($current_url, strlen($current_url) - strlen($endpoint), strlen($endpoint)) != $endpoint )
	{
		return;
	}
	
	// We don't want our API on the homepage, it's quite literally useless.
	if( $current_url == site_url('/') )
	{
		return wp_hovercards_json_error('Please choose a valid URL');
	}
	
	// All checks cleared, let's remove the $endpoint from the URL and proceed.
	$current_url = substr($current_url, 0, strlen($current_url) - strlen($endpoint));
	
	// Let's search WordPress to find the post ID under the current URL
	$id = url_to_postid($current_url);
	
	// If $id is empty, we can't do anything. Throw an error.
	if( empty($id) )
	{
		return wp_hovercards_json_error('Entry not found in WordPress');
	}
	
	// Now we have a nice numeric ID, we can pull up the post from the WP database.
	$post = get_post($id);
	
	// If the results don't show the ID, clearly no data has been pulled up.
	if( empty($post->ID) )
	{
		return wp_hovercards_json_error('Entry not found in WordPress');
	}
	
	// Everything is all good, let's encode the results and extract data we need.
	return wp_hovercards_json_encode($post);
}

/*
	@added: v0.0.1
	@note: This tells WordPress what we need on the website for our plugin to work. These will be included in wp_head()
*/
function wp_hovercards_js() {
	// Thank you WordPress for having jQuery already!
    wp_enqueue_script( 'jquery' );
    
    // Let's included the minified version of WordPress Hovercards
    wp_deregister_script( 'wp-hovercards' );
    wp_register_script( 'wp-hovercards', plugins_url('wordpress-hovercards/jquery.wp-hovercards.min.js'));
    wp_enqueue_script( 'wp-hovercards' );
    
    // And now the CSS file!
    wp_deregister_style( 'wp-css-hovercards' );
    wp_register_style( 'wp-css-hovercards', plugins_url('wordpress-hovercards/wp-hovercards.css'));
    wp_enqueue_style( 'wp-css-hovercards' );
}

function wp_hovercards_admin_js() {
	// Admin only css
    echo '<link rel="stylesheet" href="'. plugins_url('wordpress-hovercards/wp-hovercards-admin.css') .'" type="text/css" />';
}


/*
	@added: v0.0.1
	@note: This provides an JSON style error for frontend interaction and debugging.
*/
function wp_hovercards_json_error($err)
{
	// This provides a simple JavaScript type encoding in the browser
	header("Content-Type: text/javascript");
	
	//This is the JSON output that is given when an error is thrown
	$error = array(
		'res' => 'error',
		'wpurl' => site_url(),
		'err' => $err,
		'time' => time(),
		'version' => constant('WP_HOVERCARDS')
	);
	
	// Let's do the array to JSON encoding and stop WordPress by exit()
	echo json_encode( $error );
	exit;
}

/*
	@note: get_post_image is a WordPress function that tries to find a photo from a specific WordPress post
*/
function wp_hovercards_get_post_image($post_id = 0)
{
	// Let's get if there is a custom value called 'image' (used for external url's)
	$v = get_post_custom_values('image', $post_id);
	
	if ( ! empty($v[0]) )
	{
		// Okay, we got something. Let's return this!
		return $v[0];
	}
	
	// Okay, maybe not. Now to check if a featured image has been set!
	else if(has_post_thumbnail( $post_id ))
	{
		// Yup we got a featured image. Let's return this!
		$v = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'single-post-thumbnail' );
		return $v[0];
	}
	
	else {
		// :( We got nothing. Ah well.
		return FALSE;
	}
}

/*
	@note: This converts object type data from WordPress and extracts data used in hovercards
*/
function wp_hovercards_json_encode($post)
{
	// This provides a simple JavaScript type encoding in the browser
	header("Content-Type: text/javascript");
	
	// Let's pull up the category data and only extract data we need!
	$_cat = get_the_category($post->ID);
	foreach($_cat as $cat) {
		$category[] = $cat->name;
	}
	
	// Used as a construct for custom classes in the frontend
	// As CSS works by spaces. (i.e. two classes is 'hello hello2') we can just space each class and it will render perfectly.
	$_custom_class = ' ';
	$_custom_class .= implode(' ', unserialize(get_post_meta($post->ID, '_hovercard', 1)));
	if($_custom_class != ' ') { $_custom_class .= ' '; }
	$_custom_class .= str_replace( array( ', ', ',' ), ' ', trim(get_post_meta($post->ID, '_hovercard_class', 1)));
	
	// The array which will be converted to JSON
	$data = array(
		'res' => 'success',
		'wpurl' => site_url(),
		'time' => time(),
		'version' => constant('WP_HOVERCARDS'),
		'post' => array(
			'ID' => $post->ID,
			'post_timthumb' => plugins_url( 'wordpress-hovercards/external/timthumb.php?src=' ) . wp_hovercards_get_post_image($post->ID),
			'post_image' => wp_hovercards_get_post_image($post->ID),
			'post_custom_class' => $_custom_class,
			'post_category_single' => implode(', ', $category),
			'post_category_array' => (array) $category,
			'post_title' => $post->post_title,
			'post_type' => $post->post_type,
			'post_excerpt' => $post->post_excerpt,
			'post_date' => date( 'dS M Y' , strtotime($post->post_date) ),
			'post_date_ago' => human_time_diff( strtotime($post->post_date) , current_time('timestamp') ),
			'post_comments' => $post->comment_count,
			'permalink' => get_permalink( $post->ID )
		)
	);
	
	// This will now encode the array to JSON and it will stop WordPress from running
	echo json_encode($data);
	exit;
}

/*
	@note: This will appear on the edit posts/pages screens
*/
function wp_hovercards_edit() {
	// Adds a meta box to posts
	add_meta_box( 
		'wp-hovercards-edit',
		__( 'WP Hovercards' ),
		'wp_hovercards_edit_html',
		'post' 
	);
	
	// Same meta box but for pages!
	add_meta_box( 
		'wp-hovercards-init',
		__( 'WP Hovercards' ),
		'wp_hovercards_edit_html',
		'page' 
	);
}

function wp_hovercards_edit_save( $post_id ) {
	
	// And.. check for the nonce
	if( !wp_verify_nonce( $_POST['wp_hovercards_nonce'], plugin_basename( __FILE__ ) ) ) {
		return;
	}
	
	// Check permissions for both posts and pages
	if ( 'page' == $_POST['post_type'] ) 
	{
		if ( !current_user_can( 'edit_page', $post_id ) ) {
        	return;
		}
	}
	else
	{
		if ( !current_user_can( 'edit_post', $post_id ) ) {
        	return;
        }
	}

	// This converts POST data into spaced CSS classes.
	$edits = array();

	if($_POST['_hovercard_disable']) {
		// To disable the hover card
		$edits[] = 'disable';
	}
	
	if($_POST['_hovercard_negate']) {
		// Negate the background
		$edits[] = 'negate';
	}
	
	if($_POST['_hovercard_gblur']) {
		// Disable blurring
		$edits[] = 'nogblur';
	}
	
	if($_POST['_hovercard_bw']) {
		// Greyscale the background
		$edits[] = 'bw';
	}
	
	// Let's serialise the array to store in WP
	$_edit_phps = serialize($edits);
	
	// Pre-configured settings are stored under _hovercard
	$_edits = get_post_meta($post_id, '_hovercard', 1);
	if( isset($_edits) ) {
		update_post_meta($post_id, '_hovercard', $_edit_phps, $_edits);
	} else {
		add_post_meta($post_id, '_hovercard', $_edit_phps, 1);
	}
	
	// And custom classes via text box is stored in _hovercard_class
	// They are separated to avoid conflicts.
	$_class = get_post_meta($post_id, '_hovercard_class', 1);
	if( isset($_class) ) {
		update_post_meta($post_id, '_hovercard_class', $_POST['_hovercard_class'], $_class);
	} else {
		add_post_meta($post_id, '_hovercard_class', $_POST['_hovercard_class'], 1);
	}
}

function wp_hovercards_edit_html( $post ) {
	
	// Use a nonce
	wp_nonce_field( plugin_basename( __FILE__ ), 'wp_hovercards_nonce' );
	
	// If the user wants to hide their hover card
	wp_hovercard_edit_html_each( $post, '_hovercard_disable', 'disable', 'Hide Hovercard' );
	
	// If the user wants to negate the background image (if any)
	wp_hovercard_edit_html_each( $post, '_hovercard_negate', 'negate', 'Negate image' );
	
	// And same for undo'ing the blurring that's done by default
	wp_hovercard_edit_html_each( $post, '_hovercard_gblur', 'nogblur', 'Unblur image' );
	
	// Greyscale the background image
	wp_hovercard_edit_html_each( $post, '_hovercard_bw', 'bw', 'Greyscale image' );
	
	// The custom classes HTML box
	echo "<br><p><label>Add custom classes (separate by comma): <input type='text' name='_hovercard_class' value='". get_post_meta($post->ID, '_hovercard_class', 1) ."'></label></p>";
	
	// Learn more?
	echo "<a href='". plugins_url('/wordpress-hovercards/html/documentation.html') ."' target='_blank'>Learn more about these settings</a>";
}

function wp_hovercard_edit_html_each( $post, $custom_data_name, $frontend_js_name, $name ) {
	// Avoids repeating the same HTML for pre-configured settings
	echo "<p><label><input type='checkbox' name='{$custom_data_name}' value='1'";
	if(in_array($frontend_js_name, unserialize(get_post_meta($post->ID, '_hovercard', 1)))) { echo " checked='checked'"; }
	echo "> {$name}</label></p>";
}


/*
	@note: This will be for the backend users to see.
*/
function wp_hovercards_admin() {
	// This adds the admin page
   add_submenu_page('options-general.php', 'WordPress Hovercards', 'WP Hovercards', 'administrator', 'wp-hovercards', 'wp_hovercards_admin_html');
}

function wp_hovercards_admin_html() {
    // And the HTML of course for the admin page!
	echo "<div class='wp-hovercards'>
		<img class='wphc-logo' src='".plugins_url('/wordpress-hovercards/images/wp-hovercards.png')."'>
		<!-- h2>WordPress Hovercards</h2 -->
		
		<p class='paragraph'>Thanks for downloading WP Hovercards, my first free WordPress plugin!</p>
		
		<p class='paragraph'>Good news! There's nothing to configure, our plugin works out of the box. I &hearts; minimalism. This page mostly exists to see if Hovercards is running, to credit future contributors, and (maybe) add one or three settings in the future. I &hearts; to think ahead.</p>
		
		<p class='paragraph'>P.S. If you have any feedback to give me about WP Hovercards, <a href='http://bilawal.wufoo.com/forms/q7x3a1/' target='_blank'>click here</a>.</a>
		
		<p class='footer'>Version ". constant('WP_HOVERCARDS') ." &mdash; Produced by <a href='http://twitter.com/bilawalhameed' target='_blank'>@bilawalhameed</a>. Developed by a 19 year old genius.</p>
		<a href='http://www.bilawal.co.uk/?utm_source=wp-hovercards' target='_blank'><div class='by'></div></a>
	</div>";
}

/*
	@note: This is the end. Let's get the bottle open and start this party!
*/
add_action('init', 'wp_hovercards_init');
add_action('wp_enqueue_scripts', 'wp_hovercards_js');
add_action('add_meta_boxes', 'wp_hovercards_edit');
add_action('save_post', 'wp_hovercards_edit_save');
add_action('admin_menu', 'wp_hovercards_admin');
add_action('admin_head', 'wp_hovercards_admin_js');