<?php
/*
Plugin Name: Post As Guest
Plugin URI: http://www.powie.de/wordpress/post-as-guest/
Description: Post as Guest - Creates a form (shortcode) to a page to allow guests to post
Version: 0.9.1
License: GPLv2
Author: Thomas Ehrhardt
Author URI: http://www.powie.de
*/

//Define some stuff
define( 'PAG_PLUGIN_DIR', dirname( plugin_basename( __FILE__ ) ) );
define( 'PAG_PLUGIN_URL', plugins_url( dirname( plugin_basename( __FILE__ ) ) ) );
load_plugin_textdomain( 'pag', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

//create custom plugin settings menu
add_action('admin_menu', 'pag_create_menu');
add_action('admin_init', 'pag_register_settings' );
//add_action('wp_head', 'plinks_websnapr_header');
//Shortcode
add_shortcode('post-as-guest', 'pag_shortcode');
//Hook for Activation
register_activation_hook( __FILE__, 'pag_activate' );
//Hook for Deactivation
register_deactivation_hook( __FILE__, 'pag_deactivate' );

//PAG Ajax Javascripts Admin
add_action( 'admin_enqueue_scripts', 'pagjs' );
function pagjs(){
	wp_enqueue_script( 'pag-js', PAG_PLUGIN_URL.'/pag.js', false );
}

//Nonces!!! nont nonsens :)
add_action('init','pagnonces_create');
function pagnonces_create(){
	$pagnonce = wp_create_nonce('pagnonce');
}

//PAG JS Frontend
// thanks to http://www.garyc40.com/2010/03/5-tips-for-using-ajax-in-wordpress/
// embed the javascript file that makes the AJAX request

wp_enqueue_script( 'pag-ajax-request', plugin_dir_url( __FILE__ ) . '/pagfe.js', array( 'jquery' ) );
// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
wp_localize_script( 'pag-ajax-request', 'PagAjax',
	array(  'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'enter_title' => __('Please enter title', 'pag'),
			'enter_content' => __('Please enter content', 'pag' )
	)
);

//Create Menus
function pag_create_menu() {
	// create PAG menu page
	add_menu_page( __('Post as Guest','pag'), __('Post as Guest','pag'), 8, PAG_PLUGIN_DIR.'/pag_review.php','',PAG_PLUGIN_URL.'/images/pag.png');
	add_submenu_page( PAG_PLUGIN_DIR.'/pag_review.php', __('Settings','pag'), __('Settings','pag'), 8, PAG_PLUGIN_DIR.'/pag_settings.php');
}

function pag_register_settings() {
	//register settings
	register_setting( 'pag-settings', 'postfield-rows', 'intval' );
	register_setting( 'pag-settings', 'postfield-legend');
	register_setting( 'pag-settings', 'prepost-code');
	register_setting( 'pag-settings', 'afterpost-code' );
	register_setting( 'pag-settings', 'after-post-msg' );
}

function pag_shortcode( $atts ) {
	//var_Dump($atts);
	/*
	extract( shortcode_atts( array(
		'foo' => 'something',
		'bar' => 'something else',
	), $atts ) );
	return "Hallo -> foo = {$foo}";
	*/
	$sc = '<!-- post-as-guest -->';
	$sc.= '<div id="pag_form"><form method="post" id="pag" action="">
			<input type="hidden" name="action" value="pag_post" />';
    $sc.= wp_nonce_field( 'pagnonce', 'post_nonce', true, false );
	$sc.= '<legend>'.__('Title', 'pag').'</legend>
        	 <input type="text" size="50" name="pagtitle" id="pagtitle" />
        	<legend>'.get_option('postfield-legend').'</legend>
        	 <textarea rows="'.get_option('postfield-rows').'" name="pagcontent" id="pagcontent" style="width:100%;"></textarea>
    	   <input type="submit" id="pagsubmit" name="pagsubmit" value="'.__("Send In", 'pag').'" /></form>
		   </div>';
	$sc.='<!-- /post-as-guest -->';
	return $sc;
}

//Activate
function pag_activate() {
	// do not generate any output here
	add_option('postfield-rows',5);
	add_option('after-post-msg', __('Thanks for your post. We will review your post befor publication.','pag'));
}

//Deactivate
function pag_deactivate() {
	// do not generate any output here
}

//Post as Guest - get count of pages waiting for review
function pag_get_posts_wait_count(){
	$args = array( 'post_type' => 'post', 'post_status' => 'review', 'numberposts' => 100 );
	$data =get_posts($args);
	return(count($data));
}

function pag_get_posts_wait(){
	$args = array( 'post_type' => 'post', 'post_status' => 'pending', 'orderby' => 'post_date', 'order' => 'DESC',
					'numberposts' => 100 );
	return get_posts($args);
}

//Ajax Functions Backend!
// Post Preview
add_action('wp_ajax_pag_post_preview', 'pag_post_preview');
function pag_post_preview(){
	$id = intval($_POST['id']);
	$post = get_post($id);
	$content =  apply_filters('the_content', $post->post_content);
	$output = "<tr class=\"pag_preview\" id=\"preview-{$post->ID}\">";
	$output.= "    <td colspan=\"7\">$content</td>\n";
	$output.= "</tr>";
	echo $output;
	die();
}
//Remove Post
add_action('wp_ajax_pag_post_remove', 'pag_post_remove');
function pag_post_remove(){
	$id = intval($_POST['id']);
	wp_delete_post($id);
	$response = json_encode( array( 'success' => true ) );
	header( "Content-Type: application/json" );
	echo $response;
	die();
}
//Approve Post
add_action('wp_ajax_pag_post_approve', 'pag_post_approve');
function pag_post_approve(){
	$id = intval($_POST['id']);
	wp_publish_post($id);
	$response = json_encode( array( 'success' => true ) );
	header( "Content-Type: application/json" );
	echo $response;
	die();
}

//Ajax Function Frontend
add_action('wp_ajax_pag_post', 'pag_post');
add_action('wp_ajax_nopriv_pag_post', 'pag_post' );
function pag_post(){
	//ccheck nonce
	if (! wp_verify_nonce($_POST['post_nonce'], 'pagnonce') ) die('Security check');
	$content = trim(get_option('prepost-code')).$_POST['pagcontent'].trim(get_option('afterpost-code'));
	$post = array(  'post_title' => $_POST['pagtitle'],
					'post_content' => $content ,
					'post_type' => 'post',
					'post_status' => 'pending');
	$id = wp_insert_post( $post, $wp_error );
	$response = json_encode( array( 'success' => true ,
								    'msg' => get_option('after-post-msg') ) );
	header( "Content-Type: application/json" );
	echo $response;
	//var_dump($_POST);
	die();
}

?>