<?php 

$qaplus_options = get_option( 'qaplus_options' ); 
$qaplus_auth = get_option( 'qaplus_auth' ); 

//our custom post functions
require ( Q_A_PLUS_PATH . 'inc/custom-post.php' );

// If the version numbers don't match, run the upgrade script
if ( $qaplus_options['version'] < Q_A_PLUS_VERSION ) { 
	require ( Q_A_PLUS_PATH . 'inc/upgrader.php' );
}

//shortcodes
require ( Q_A_PLUS_PATH . 'inc/shortcodes.php' );

//Reorder script
require_once(dirname(__FILE__).'/reorder.php');

//shortcodes
require ( Q_A_PLUS_PATH . 'inc/ratings.php' );

//action link http://www.wpmods.com/adding-plugin-action-links
function qaplus_action_links( $links, $file ) {
	static $this_plugin;
	$qaplus_auth = get_option( 'qaplus_auth' ); 

	if ( !$this_plugin ) {
		$this_plugin = Q_A_PLUS_LOCATION;
	}

	// check to make sure we are on the correct plugin
	if ( $file == $this_plugin ) {
		// the anchor tag and href to the URL we want. For a "Settings" link, this needs to be the url of your settings page
		$settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page=qaplus">Settings</a>';
		// add the link to the list
		array_unshift( $links, $settings_link );
	}
	return $links;
}

add_filter( 'plugin_action_links', 'qaplus_action_links', 10, 2 );

/* Our rewrite functions */

function qaplus_rewrites() {
	global $qaplus_options;
	if ( ! $qaplus_options['faq_slug'] ) { $slug = 'faqs'; } else { $slug = strtolower( $qaplus_options['faq_slug'] ); }
	
	add_rewrite_rule( $qaplus_options['faq_slug'] . '/search/?([^/]*)','index.php?s=$matches[1]&post_type=qa_faqs','top');
	  
  	add_rewrite_rule( $qaplus_options['faq_slug'] . '/page/?([^/]*)','index.php?pagename=' . $qaplus_options['faq_slug'] . '&paged=$matches[1]','top');
   
	add_rewrite_rule( $qaplus_options['faq_slug'] . '/category/?([^/]*)/page/?([^/]*)','index.php?pagename=' . $qaplus_options['faq_slug'] . '&category_name=$matches[1]&paged=$matches[2]','top');
	
	add_rewrite_rule( $qaplus_options['faq_slug'] . '/category/?([^/]*)','index.php?pagename=' . $qaplus_options['faq_slug'] . '&category_name=$matches[1]','top');

}
		
add_action('init', 'qaplus_rewrites');

/* Load scripts for front */

if ( ! is_admin() ) {
	add_action('init', 'qaplus_public');
}

function qaplus_public() {
	global $qaplus_options;
	// jQuery  
	if ( $qaplus_options['jquery'] == "force" ) {
		wp_deregister_script( 'jquery' ); 
		wp_register_script( 'jquery', ( "http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js" ), false, '1', false); 
		wp_enqueue_script( 'jquery' );
	} elseif ( $qaplus_options['jquery'] == "wp" ) {
		wp_enqueue_script( 'jquery' );
	}

	wp_enqueue_script( 'q-a-plus', Q_A_PLUS_URL . '/js/q-a-plus.js', false, $qaplus_options['version'], true ); 

	wp_enqueue_style( 'q-a-plus', Q_A_PLUS_URL . '/css/q-a-plus.css', false, $qaplus_options['version'], 'screen' );	
}

add_action( 'wp_head', 'qaplus_head' );

if ( ! function_exists( 'qaplus_head' ) ) {
	function qaplus_head() {
		global $qaplus_options;
		echo '<!-- Q and A Plus -->
		<noscript><link rel="stylesheet" type="text/css" href="' .  plugins_url( "css/q-a-plus-noscript.css?ver=" . $qaplus_options['version'], dirname(__FILE__) ) . '" /></noscript>';
	} // end qaplus_head 
}

/* Custom template redirection hook 
 * Todo: Make sure there's a good fallback if search template doesn't exist. 
 */ 

function qaplus_template_redirect() {
    global $wp;
	global $wp_query;
	global $post;
	
	if ( is_single() && 'qa_faqs' == get_post_type($post) ) {
    
		if ( file_exists( TEMPLATEPATH . '/single-qa_faqs.php') ) {	
			$page_template = TEMPLATEPATH . '/single-qa_faqs.php';
		} elseif ( file_exists( TEMPLATEPATH . '/page.php') ) {	
			$page_template = TEMPLATEPATH . '/page.php';
		} elseif ( file_exists( TEMPLATEPATH . '/single.php' )) {
			$page_template = TEMPLATEPATH . '/single.php';
		} else {
			$page_template = TEMPLATEPATH . '/index.php';
		}
	}
	        
    if ( isset( $page_template ) ) {
		if ( have_posts() ) {
			include( $page_template );
			exit;
		} else {
			$wp_query->is_404 = true;
		}
	}
}

if ( 'QA_REDIRECTS' != FALSE ) {
	add_action("template_redirect", 'qaplus_template_redirect');
}

function addQaplusPage(){
	global $qaplus_options;
	$qaplus_admin = get_option( 'qaplus_admin_options' );
	$new_page =  str_replace('-', ' ', $qaplus_options['faq_slug']);
	$new_page = ucwords( $new_page );
	// Create post object
	$qaplus_post = array(
		'post_title' => $new_page,
	  'post_content' => '[qa]',
	  'post_status' => 'publish',
	  'post_type' => 'page',
	  'post_author' => 1
	);
	
	// Insert the post into the database
	wp_insert_post( $qaplus_post );
	
	$page_exists = get_page_by_path( $qaplus_options['faq_slug'] );
	if ( $page_exists ) {
		echo "<p>Page was successfully created!</p>";
	} else {
		echo "<p>Page could not be created. Please create it and add the shortcode manually.</p>";
	}
	die();
}

add_action('wp_ajax_addQaplusPage', 'addQaplusPage');
add_action('wp_ajax_nopriv_addQaplusPage', 'addQaplusPage'); // not really needed

function dismissQaplusCreate(){
	$qaplus_admin = get_option( 'qaplus_admin_options' );
	
	// Insert the post into the database
	$qaplus_admin['dismiss_slug'] = "true";
	update_option( 'qaplus_admin_options', $qaplus_admin );
	
	die();
}

add_action('wp_ajax_dismissQaplusCreate', 'dismissQaplusCreate');
add_action('wp_ajax_nopriv_dismissQaplusCreate', 'dismissQaplusCreate');

/* Excerpt functions borrowed from TwentyEleven */


/**
 * Sets the post excerpt length to 40 words.
 */

function qaplus_excerpt_length( $length ) {
	return 40;
}
add_filter( 'excerpt_length', 'qaplus_excerpt_length' );

/**
 * Returns a "Continue Reading" link for excerpts
 */

function qaplus_continue_reading_link() {
	return ' <a href="'. esc_url( get_permalink() ) . '">' . __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'twentyeleven' ) . '</a>';
}

/**
 * Replaces "[...]" (appended to automatically generated excerpts) with an ellipsis and twentyeleven_continue_reading_link().
 */

function qaplus_auto_excerpt_more( $more ) {
	return ' &hellip;' . qaplus_continue_reading_link();
}
add_filter( 'excerpt_more', 'qaplus_auto_excerpt_more' );

/**
 * Adds a pretty "Continue Reading" link to custom post excerpts.
 *
 */

function qaplus_custom_excerpt_more( $output ) {
	if ( has_excerpt() && ! is_attachment() ) {
		$output .= qaplus_continue_reading_link();
	}
	return $output;
}
add_filter( 'get_the_excerpt', 'qaplus_custom_excerpt_more' );

/* Filter page titles on the FAQ category pages */

add_filter('the_title','update_page_title');
function update_page_title($data){
    global $post, $qaplus_options;

	$qa_catname = (get_query_var('category_name'));
    
    if ( !empty($qa_catname) && !is_archive() ) {
		$qa_category = get_term_by( 'slug', $qa_catname, 'faq_category' );
	   	
	    if ( in_the_loop() && "qa_faqs" != get_post_type() ) {
	    	$homelink =  '<a href="' . home_url() . '/' . $qaplus_options['faq_slug'] . '">' . $post->post_title . '</a>';
	    	$data = $homelink . __(' / ', 'qa_plus') . $qa_category->name;
	   	} 
	} 

	if ( is_single() && in_the_loop() && "qa_faqs" == get_post_type() ) {
		$homeID = url_to_postid($qaplus_options['faq_slug']);
		$single_query = new WP_Query( "page_id=$homeID" );	
		
		while( $single_query->have_posts() ): $single_query->the_post();
			global $post;
			$title = $post->post_title;
		endwhile;

		wp_reset_query();
		
   		$homelink =  '<a href="' . home_url() . '/' . $qaplus_options['faq_slug'] . '">' . $title . '</a>';
   		$data = $homelink . __(' / ', 'qa_plus') . $data;
	 }

	return $data;
}