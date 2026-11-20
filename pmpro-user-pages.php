<?php
/*
Plugin Name: Paid Memberships Pro - User Pages Add On
Plugin URI: http://www.paidmembershipspro.com/pmpro-user-pages/
Description: When a user signs up, create a page for them that only they (and admins) have access to.
Version: 0.7.7
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
Text Domain: pmpro-user-pages
Domain Path: /languages

To setup:

	1. Create a top level page to store the user pages, e.g. "Members".
	2. Navigate to Memberships --> User Pages and complete the settings.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// load text domain
function pmpro_user_pages_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-user-pages', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'pmpro_user_pages_load_plugin_text_domain');

//includes
require_once(dirname(__FILE__) . '/includes/settings.php');	//settings page for dashboard

/*
	Create user pages at checkout

	@deprecated 0.7
*/
function pmproup_pmpro_after_checkout($user_id)
{
	// Deprecation warning.
	_deprecated_function( __FUNCTION__, 'pmproup_pmpro_after_checkout', '0.7', 'pmproup_pmpro_after_change_membership_level' );

	//user info
	$user = get_userdata($user_id);
	
	//get the user's level
	$level = pmpro_getMembershipLevelForUser($user_id);

	if ( ! empty( $level ) && ! empty( $level->ID ) ) {
		pmproup_pmpro_after_change_membership_level( $level->ID, $user_id );
	}
}

/**
 * When a user recieves a membership level, create a user page for them.
 *
 * @param int $level_id The ID of the level the user just received.
 * @param int $user_id The ID of the user who just received the level.
 */
function pmproup_pmpro_after_change_membership_level( $level_id, $user_id ) {
	$options = pmproup_getOptions();
	if ( ! empty( $level_id ) && in_array( $level_id, $options['levels']) ) {
		// Get user information.
		$user = get_userdata( $user_id );
		if ( empty( $user ) ) {
			return;
		}

		// Get level information.
		$level = pmpro_getSpecificMembershipLevelForUser( $user_id, $level_id );

		// Make sure that we have a page for this user.
		$user_page_id = pmproup_get_page_for_user( $user_id );
		if( empty( $user_page_id ) ) {
			//need to create it
			$postdata = array(		 		  
			  'post_author' => $user_id,
			  'post_content' => __( 'Pages for your purchases will be shown below.', 'pmpro-user-pages'),		  
			  'post_name' => $user->user_login,
			  'post_parent' => $options['parent_page'],		  
			  'post_status' => "publish",
			  'post_title' => $user->display_name,
			  'post_type' => "page"		  
			); 
			
			$postdata = apply_filters( 'pmpro_user_page_postdata', $postdata, $user, $level );
			
			$user_page_id = wp_insert_post( $postdata );
			
			// Update user meta.
			if ( $user_page_id ) {
				update_user_meta( $user_id, 'pmproup_user_page', $user_page_id );
			}		
		}

		// Create a new sub-page for this new level.
		if ( ! empty( $user_page_id ) ) {
			$postdata = array(		 		  
			  'post_author' => $user_id,
			  'post_content' => __( 'Thank you for your purchase. This page will be updated soon with updates on your order.', 'pmpro-user-pages'),  		 
			  'post_parent' => $user_page_id,		  
			  'post_status' => 'publish',
			  'post_title' => $level->name,
			  'post_type' => 'page'	  
			);  
			
			$postdata = apply_filters( 'pmpro_user_page_purchase_postdata', $postdata, $user, $level );
			
			$post_id = wp_insert_post( $postdata );				
		}
	}
}
add_action( 'pmpro_after_change_membership_level', 'pmproup_pmpro_after_change_membership_level', 10, 2 );

//show the user pages on the account page
function pmproup_pmpro_member_links_top()
{
	global $wpdb;
	
	//does this user have a user page?
	global $current_user;
	if(!empty($current_user->ID))
	{
		$user_page_id = pmproup_get_page_for_user( $current_user->ID );
		if ( ! empty( $user_page_id ) ) {
			//get children
			$pages = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, UNIX_TIMESTAMP(post_date) as post_date FROM $wpdb->posts WHERE post_parent = %d AND post_status = 'publish'", $user_page_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared lookup of the user page's child pages.
			if(!empty($pages)) {
				foreach($pages as $page) {
				?>
					<li><a href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>"><?php echo esc_html( $page->post_title ); ?> <span class="pmpro_userpage_date">(<?php echo esc_html( date( "m/d/Y", $page->post_date ) ); ?>)</span></a></li>
				<?php
				}
			}
		}
	}
}
add_action("pmpro_member_links_top", "pmproup_pmpro_member_links_top");

//show a user's pages on their user page
function pmproup_add_user_pages_below_the_content($content)
{
	global $current_user, $post, $wpdb;
	
	//if a user is logged in and this is a main user page, show the owner's level pages (template_redirect limits who can view it)
	if(!empty($current_user->ID) && !empty($post->ID))
	{
		$up_user_id = pmproup_get_user_page_owner( $post );
		if(empty($up_user_id))
			return $content;
		
		$user_page_id = pmproup_get_page_for_user( $up_user_id );
		if( ! empty( $user_page_id ) && $post->ID == $user_page_id) {
			//alright, let's show the page list at the end of the_content			
			$pages = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, UNIX_TIMESTAMP(post_date) as post_date FROM $wpdb->posts WHERE post_parent = %d AND post_status = 'publish'", $user_page_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared lookup of the user page's child pages.
			if(!empty($pages)) {
				$content .= "\n<ul class='user_page_list'>";
				foreach($pages as $page) {
					$content .= '<li><a href="' . esc_url( get_permalink( $page->ID ) ) . '">' . esc_html( $page->post_title ) . ' <span class="pmpro_userpage_date">(' . esc_html( date( "m/d/Y", $page->post_date ) ) . ')</span></a></li>';			
				}
				$content .= "\n</ul>";
			}
		}
	}
	
	return $content;
}
add_action("the_content", "pmproup_add_user_pages_below_the_content");

//redirect non admins away from parent page
function pmproup_wp_parent_page()
{
	global $wpdb, $post, $current_user;	
	
	$options = pmproup_getOptions();
	
	if(!is_admin() && !empty($post) && !empty($options['parent_page']) && $post->ID == $options['parent_page'])
	{
		if(!current_user_can("manage_options"))		
		{
			$user_page_id = pmproup_get_page_for_user( $current_user->ID );
			if(!empty($user_page_id)) {
				//redirect to the user's user page
				wp_redirect(get_permalink($user_page_id)); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Permalink of the user's own page; page_link can be filtered to another domain (e.g. domain mapping), which wp_safe_redirect() would reject.
				exit;
			} else { 
				//redirect away
				wp_safe_redirect(home_url());
				exit;
			}
		}
	}
}
add_action("wp", "pmproup_wp_parent_page");

//show admins a list of users on the parent page
function pmproup_parent_page_content($content)
{
	global $post, $wpdb;
	
	$options = pmproup_getOptions();
	
	if(!is_admin() && !empty($post->ID) && !empty($options['parent_page']) && $post->ID == $options['parent_page'])
	{
		if(current_user_can("manage_options"))		
		{
			//alright, let's show the page list at the end of the_content			
			$users = $wpdb->get_results("SELECT u.display_name, um.meta_value FROM $wpdb->usermeta um LEFT JOIN $wpdb->users u ON um.user_id = u.ID WHERE um.meta_key = 'pmproup_user_page' AND u.ID IS NOT NULL GROUP BY um.user_id"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Static query with no user input.
			if(!empty($users))
			{
				$content .= "\n<ul class='user_page_list'>";
				foreach($users as $user)
				{
					$content .= '<li><a href="' . esc_url( get_permalink( $user->meta_value ) ) . '">' . esc_html( $user->display_name ) . '</a></li>';		
				}
				$content .= "\n</ul>";
			}	
		}
	}	

	return $content;
}
add_filter("the_content", "pmproup_parent_page_content");

/**
 * Lock down a page that was created for a user.
 */
function pmproup_template_redirect() {
	global $post, $wpdb, $current_user;
		
	if ( empty( $post->ID ) ) {
		return;
	}

	// Check if this post or any of its ancestors are user pages.
	$page_user_id = pmproup_get_user_page_owner( $post );

	// If we are not on a user page, return.
	if ( empty( $page_user_id ) ) {
		return;
	}

	// We are now definitely on a user page. Check whether we should allow access for the current user or redirect.
	// By default, only allow access if this is the current user's page or the user is an admin.
	$allow_access = $page_user_id == $current_user->ID || current_user_can( 'manage_options' );

	/**
	 * Filter whether to allow access to the user page.
	 *
	 * @since 0.7
	 *
	 * @param bool $allow_access Whether to allow access to the user page.
	 * @param int $page_user_id The user ID of the user page.
	 * @return bool Whether to allow access to the user page.
	 */
	$allow_access = apply_filters( 'pmproup_allow_access_to_user_page', $allow_access, $page_user_id );

	// Redirect if we are not allowing access.
	if ( ! $allow_access ) {
		wp_safe_redirect( home_url() );
		exit;
	}
}
add_action("template_redirect", "pmproup_template_redirect");

/**
 * Get the owner of a user page, checking the post and its ancestors.
 *
 * @param WP_Post $post The post to check.
 * @return int|null The user ID that owns the page, or null if this is not a user page.
 */
function pmproup_get_user_page_owner( $post ) {
	global $wpdb;

	// Cache the owner for each post for this request, since several checks can run on the same page.
	static $owners = array();
	if ( array_key_exists( $post->ID, $owners ) ) {
		return $owners[ $post->ID ];
	}

	// Build an array of all related posts that could be user pages.
	$ancestors      = array_map( 'intval', get_post_ancestors( $post ) );
	$pages_to_check = array_merge( $ancestors, array( (int) $post->ID ) );

	// Check if any of the related posts are user pages.
	$owners[ $post->ID ] = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmproup_user_page' AND meta_value IN(" . implode(",", $pages_to_check) . ") LIMIT 1"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $pages_to_check is run through intval above.

	// Pages below the parent page are still user pages when their user page is deleted (WordPress moves the
	// children up to the parent page) or their user is deleted (the user meta is removed). Treat the author as the owner.
	if ( empty( $owners[ $post->ID ] ) ) {
		$options = pmproup_getOptions();
		if ( ! empty( $options['parent_page'] ) && in_array( (int) $options['parent_page'], $ancestors, true ) ) {
			// Pages with no author (e.g. after a direct database edit) get -1 so they stay restricted to admins.
			$owners[ $post->ID ] = ! empty( $post->post_author ) ? $post->post_author : -1;
		}
	}

	return $owners[ $post->ID ];
}

/**
 * Add a body class to user pages so they can be targeted with CSS.
 *
 * @param array $classes The body classes.
 * @return array The body classes.
 */
function pmproup_body_class( $classes ) {
	if ( ! is_singular() ) {
		return $classes;
	}

	$queried_object = get_queried_object();
	if ( $queried_object instanceof WP_Post && ! empty( pmproup_get_user_page_owner( $queried_object ) ) ) {
		$classes[] = 'pmpro-user-page';
	}

	return $classes;
}
add_filter( 'body_class', 'pmproup_body_class' );

/**
 * Apply the user page access check to REST API requests as well.
 *
 * Runs on rest_request_before_callbacks so denied requests can return an error before the
 * posts controller prepares a response (returning an error from rest_prepare_page
 * would fatal when the controller calls link_header() on it). The page ID comes from the
 * request, not the route, because a query string or JSON body `id` takes precedence over the
 * ID in the URL when the controller reads `$request['id']`.
 *
 * @since 0.7.6 Replaces pmproup_rest_pre_dispatch(), which read the page ID from the route.
 *
 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
 * @param array                                            $handler  Route handler used for the request.
 * @param WP_REST_Request                                  $request  Request used to generate the response.
 * @return mixed
 */
function pmproup_rest_request_before_callbacks_page( $response, $handler, $request ) {
	if ( ! empty( $response ) ) {
		return $response;
	}

	// Only check requests for a single post.
	if ( empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) || ! $handler['callback'][0] instanceof WP_REST_Posts_Controller || 'get_item' !== $handler['callback'][1] ) {
		return $response;
	}

	// Use the same post ID the posts controller uses.
	$post = get_post( (int) $request['id'] );
	if ( empty( $post ) ) {
		return $response;
	}

	// Only check the post's own controller, so other post types' routes keep returning their usual error for this ID.
	$post_type_object = get_post_type_object( $post->post_type );
	if ( empty( $post_type_object ) || $handler['callback'][0] !== $post_type_object->get_rest_controller() ) {
		return $response;
	}

	// If this is not a user page, return.
	$page_user_id = pmproup_get_user_page_owner( $post );
	if ( empty( $page_user_id ) ) {
		return $response;
	}

	// Match the front-end access check, but also allow users who can edit this page (e.g. Editors using the block editor).
	$allow_access = $page_user_id == get_current_user_id() || current_user_can( 'edit_page', $post->ID );
	$allow_access = apply_filters( 'pmproup_allow_access_to_user_page', $allow_access, $page_user_id );

	if ( ! $allow_access ) {
		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view this page.', 'pmpro-user-pages' ), array( 'status' => rest_authorization_required_code() ) );
	}

	return $response;
}
add_filter( 'rest_request_before_callbacks', 'pmproup_rest_request_before_callbacks_page', 10, 3 );

/**
 * Get the IDs of all user pages and the pages below them, for every member.
 *
 * @since 0.7.5
 *
 * @return int[] Post IDs.
 */
function pmproup_get_all_user_page_ids() {
	global $wpdb;

	// Cache the IDs for this request, since several checks can run in one request.
	static $all_user_page_ids = null;
	if ( null !== $all_user_page_ids ) {
		return $all_user_page_ids;
	}

	$all_user_page_ids = array();
	$options = pmproup_getOptions();
	if ( empty( $options['parent_page'] ) ) {
		return $all_user_page_ids;
	}

	// User pages are children of the parent page. Walk down from there to get the pages at every level below it.
	$child_page_ids = array( (int) $options['parent_page'] );
	while ( ! empty( $child_page_ids ) ) {
		$child_page_ids = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_parent IN (" . implode( ',', $child_page_ids ) . ") AND post_type NOT IN ( 'revision', 'attachment' )" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IDs are integers; results are cached for the request above.
		$child_page_ids = array_diff( $child_page_ids, $all_user_page_ids, array( (int) $options['parent_page'] ) );
		$all_user_page_ids = array_merge( $all_user_page_ids, $child_page_ids );
	}

	return $all_user_page_ids;
}

/**
 * Get the IDs of users whose only published posts of the given post types are user pages.
 *
 * WordPress lists users with published posts in the REST API users endpoint. Members author their own
 * user pages, so without this check every member with a user page would be listed there.
 *
 * @since 0.7.5
 *
 * @param string[] $post_types Post types that make a user public.
 * @return int[] User IDs.
 */
function pmproup_get_user_page_only_author_ids( $post_types ) {
	global $wpdb;

	$post_types = array_values( array_filter( (array) $post_types, 'is_string' ) );
	$user_page_ids = pmproup_get_all_user_page_ids();
	if ( empty( $post_types ) || empty( $user_page_ids ) ) {
		return array();
	}

	// Cache the result for each set of post types for this request.
	static $author_ids = array();
	sort( $post_types );
	$cache_key = implode( ',', $post_types );
	if ( isset( $author_ids[ $cache_key ] ) ) {
		return $author_ids[ $cache_key ];
	}

	$user_page_ids_sql = implode( ',', array_map( 'intval', $user_page_ids ) );
	$post_types_sql = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
	$author_ids[ $cache_key ] = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE ID IN ($user_page_ids_sql) AND post_status = 'publish' AND post_type IN ($post_types_sql) AND post_author NOT IN ( SELECT post_author FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ($post_types_sql) AND ID NOT IN ($user_page_ids_sql) )", array_merge( $post_types, $post_types ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $user_page_ids_sql is integers and $post_types_sql is %s placeholders; results are cached for the request above.

	return $author_ids[ $cache_key ];
}

/**
 * Leave members out of the REST API users list when their user pages are their only published posts.
 *
 * @since 0.7.5
 *
 * @param array           $prepared_args Arguments for WP_User_Query.
 * @param WP_REST_Request $request       The REST API request.
 * @return array
 */
function pmproup_rest_user_query( $prepared_args, $request ) {
	// WordPress only limits the list to users with published posts for visitors who can't list users.
	// Users who can edit others' pages can already see user pages and their authors.
	if ( empty( $prepared_args['has_published_posts'] ) || current_user_can( 'list_users' ) || current_user_can( 'edit_others_pages' ) ) {
		return $prepared_args;
	}

	$post_types = true === $prepared_args['has_published_posts'] ? get_post_types( array( 'show_in_rest' => true ), 'names' ) : (array) $prepared_args['has_published_posts'];
	$hidden_user_ids = array_diff( pmproup_get_user_page_only_author_ids( $post_types ), array( get_current_user_id() ) );
	if ( empty( $hidden_user_ids ) ) {
		return $prepared_args;
	}

	// WP_User_Query ignores exclude when include is set, so remove the hidden users from include as well.
	if ( ! empty( $prepared_args['include'] ) ) {
		$include = array_diff( wp_parse_id_list( $prepared_args['include'] ), $hidden_user_ids );
		$prepared_args['include'] = empty( $include ) ? array( 0 ) : array_values( $include );
	} else {
		$exclude = empty( $prepared_args['exclude'] ) ? array() : wp_parse_id_list( $prepared_args['exclude'] );
		$prepared_args['exclude'] = array_values( array_unique( array_merge( $exclude, $hidden_user_ids ) ) );
	}

	return $prepared_args;
}
add_filter( 'rest_user_query', 'pmproup_rest_user_query', 20, 2 );

/**
 * Deny REST API requests for a single user when their user pages are their only published posts.
 *
 * WordPress lets anyone view a user through /wp/v2/users/<id> once they have a published post,
 * and has no filter specific to that check (the `get_usernumposts` filter applies to every post count
 * on the site), so this runs before the users controller's callbacks and returns the same error
 * WordPress returns for users without published posts.
 *
 * @since 0.7.5
 *
 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
 * @param array                                            $handler  Route handler used for the request.
 * @param WP_REST_Request                                  $request  Request used to generate the response.
 * @return mixed
 */
function pmproup_rest_request_before_callbacks_user( $response, $handler, $request ) {
	if ( ! empty( $response ) ) {
		return $response;
	}

	// Only check requests for a single user.
	if ( empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) || ! $handler['callback'][0] instanceof WP_REST_Users_Controller || 'get_item' !== $handler['callback'][1] ) {
		return $response;
	}

	// Use the same user ID the users controller uses.
	$user_id = (int) $request['id'];

	// Match the WordPress check: users can always view themselves, and users who can edit or list users can view anyone.
	// Users who can edit others' pages can already see user pages and their authors.
	if ( get_current_user_id() === $user_id || current_user_can( 'edit_user', $user_id ) || current_user_can( 'list_users' ) || current_user_can( 'edit_others_pages' ) ) {
		return $response;
	}

	if ( ! in_array( $user_id, pmproup_get_user_page_only_author_ids( get_post_types( array( 'show_in_rest' => true ), 'names' ) ), true ) ) {
		return $response;
	}

	return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.', 'pmpro-user-pages' ), array( 'status' => rest_authorization_required_code() ) );
}
add_filter( 'rest_request_before_callbacks', 'pmproup_rest_request_before_callbacks_user', 10, 3 );

/**
 * Don't allow oEmbed for user pages.
 *
 * Without this, the oEmbed endpoint returns the title and author of any user page.
 * Returning 0 makes the endpoint respond the same as it does for a URL that isn't a post.
 * This applies to everyone, including the page owner: embeds of same-site links are cached and
 * shown to every visitor, and the embed itself redirects visitors who can't access the page.
 * It also ignores the pmproup_allow_access_to_user_page filter for the same reason.
 *
 * @since 0.7.5
 *
 * @param int    $post_id The post ID found for the requested URL.
 * @param string $url     The requested URL.
 * @return int
 */
function pmproup_oembed_request_post_id( $post_id, $url ) {
	$post = empty( $post_id ) ? null : get_post( $post_id );
	if ( empty( $post ) || empty( pmproup_get_user_page_owner( $post ) ) ) {
		return $post_id;
	}

	return 0;
}
add_filter( 'oembed_request_post_id', 'pmproup_oembed_request_post_id', 10, 2 );

//update the confirmation page
function pmproup_pmpro_confirmation_message($message, $order = null)
{
	global $wpdb, $current_user;
	
	if(empty($current_user->ID))
		return $message;
	
	// Only link a page if the confirmed level creates user pages.
	$options = pmproup_getOptions();
	if ( ! empty( $order->membership_id ) && ! in_array( $order->membership_id, $options['levels'] ) ) {
		return $message;
	}
	
	$user_page_id = pmproup_get_page_for_user( $current_user->ID );
		
	if(!empty($user_page_id)){
		//get the last page created for them
		$lastpage = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_title, post_status FROM $wpdb->posts WHERE post_type = 'page' AND post_parent = %d ORDER BY ID DESC LIMIT 1", $user_page_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared lookup of the user's latest page.
		
		// Only link the newest page if it's published, so we never point to an older order's page.
		if(!empty($lastpage) && 'publish' === $lastpage->post_status)
		{
			//okay update the message
			$message .= sprintf(
				/* translators: %s: link to the user's most recent order page. */
				'<p>' . __( '<strong>Important</strong>. Updates on your order will be posted here: %s', 'pmpro-user-pages' ) . '</p>',
				'<a href="' . esc_url( get_permalink( $lastpage->ID ) ) . '">' . esc_html( $lastpage->post_title ) . '</a>'
			);
		}
	}
	
	return $message;
}
add_filter("pmpro_confirmation_message", "pmproup_pmpro_confirmation_message", 10, 2);

/**
 * Get the IDs of user pages that should be hidden from the current user.
 *
 * All user pages are children of the User Pages parent page. This returns those pages (the main user pages)
 * and the children of those pages (the purchased pages), ignoring pages where the current user is author.
 * Admins see everything. Nothing is hidden on the admin side, except in admin-ajax requests from users who can't edit others' pages.
 *
 * @return array
 */
function pmproup_get_hidden_user_page_ids()
{
	// Bail if pluggable functions aren't loaded yet (e.g. another plugin queries posts while loading).
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return array();
	}

	$options = pmproup_getOptions();
	
	//don't hide anything on the admin side (admin-ajax.php requests can come from the front end, so still hide there unless the user can edit others' pages), and also let admins see everything
	if( ( is_admin() && ( ! wp_doing_ajax() || current_user_can( 'edit_others_pages' ) ) ) || current_user_can("manage_options") || empty($options['parent_page']))
		return array();
	
	//Using a global to cache the user page ids. If it is not set, we need to look them up. (Note we're ignoring posts where the current user is author.)
	global $wpdb, $current_user, $all_pmpro_user_page_ids;	
	if(!isset($all_pmpro_user_page_ids))
	{
		//these are the top level member pages
		if(!empty($current_user->ID))
			$main_user_page_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND ID <> %d AND post_author <> %d", $options['parent_page'], $options['parent_page'], $current_user->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared; results cached in a global below.	
		else
			$main_user_page_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND ID <> %d", $options['parent_page'], $options['parent_page'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared; results cached in a global below.	
		if(empty($main_user_page_ids)) {
			$all_pmpro_user_page_ids = array();	//cache the empty result for this request
			return array();		//didn't find anything
		}
			
		//these are the individually purchased user pages
		if(!empty($current_user->ID))
			$user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent IN (" . implode(",", $main_user_page_ids) . ") AND post_author <> '" . $current_user->ID . "'"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $main_user_page_ids are integer wp_posts.ID values from the query above; $current_user->ID is an integer.	
		else
			$user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent IN (" . implode(",", $main_user_page_ids) . ")"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $main_user_page_ids are integer wp_posts.ID values from the query above.	
			
		//also hide any pages nested further below the purchased pages
		$author_where = ! empty( $current_user->ID ) ? $wpdb->prepare( ' AND post_author <> %d', $current_user->ID ) : '';
		$child_page_ids = $user_page_ids;
		while ( ! empty( $child_page_ids ) ) {
			$child_page_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_parent IN (" . implode( ',', array_map( 'intval', $child_page_ids ) ) . ")" . $author_where ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IDs are run through intval and $author_where is prepared above.
			$child_page_ids = array_diff( $child_page_ids, $main_user_page_ids, $user_page_ids );
			$user_page_ids = array_merge( $user_page_ids, $child_page_ids );
		}

		//combine the top level and sub pages
		$all_pmpro_user_page_ids = array_merge($main_user_page_ids, $user_page_ids);
	}

	return $all_pmpro_user_page_ids;
}

/*
	Remove user pages from frontend searches/etc.
*/
function pmproup_pre_get_posts($query)
{
	if($query->is_singular())
		return $query;
	
	$all_pmpro_user_page_ids = pmproup_get_hidden_user_page_ids();
	if ( ! empty( $all_pmpro_user_page_ids ) ) {
		//add user page ids to the post__not_in query var
		$post_not_in = function_exists( 'pmpro_get_post_not_in' ) ? pmpro_get_post_not_in( $query ) : $query->get( 'post__not_in' );
		if ( ! is_array( $post_not_in ) ) {
			$post_not_in = array();
		}
		$query->set( 'post__not_in', array_merge( $post_not_in, $all_pmpro_user_page_ids ) );

		// WP_Query ignores post__not_in when post__in is set (e.g. the REST API's "include" parameter), so also remove user pages from post__in.
		// Users who can edit a user page can still request it by ID, matching the REST API access check for single pages.
		$post__in = $query->get( 'post__in' );
		if ( ! empty( $post__in ) && is_array( $post__in ) ) {
			$hidden_user_page_ids = array_map( 'intval', $all_pmpro_user_page_ids );
			$allowed_post__in = array();
			foreach ( $post__in as $post_in_id ) {
				// WP_Query runs post__in through absint(), so compare the same value.
				$post_in_id = absint( $post_in_id );
				if ( ! in_array( $post_in_id, $hidden_user_page_ids, true ) || current_user_can( 'edit_post', $post_in_id ) ) {
					$allowed_post__in[] = $post_in_id;
				}
			}

			if ( count( $allowed_post__in ) !== count( $post__in ) ) {
				// If every requested post was removed, make sure the query returns nothing instead of ignoring post__in.
				$query->set( 'post__in', empty( $allowed_post__in ) ? array( 0 ) : $allowed_post__in );
			}
		}
	}
	
	return $query;
}
add_filter("pre_get_posts", "pmproup_pre_get_posts");

/*
	Remove user pages from previous/next post links. get_adjacent_post() doesn't use WP_Query, so pre_get_posts doesn't cover it.
*/
function pmproup_adjacent_post_where($where)
{
	$all_pmpro_user_page_ids = pmproup_get_hidden_user_page_ids();
	if ( ! empty( $all_pmpro_user_page_ids ) ) {
		$where .= " AND p.ID NOT IN (" . implode( ",", array_map( "intval", $all_pmpro_user_page_ids ) ) . ")";
	}
	
	return $where;
}
add_filter("get_previous_post_where", "pmproup_adjacent_post_where");
add_filter("get_next_post_where", "pmproup_adjacent_post_where");

/**
 * Get the post_id for a user's page.
 *
 * @since 0.7

 *
 * @param int|null $user_id to get page for.
 * @return int|null
 */
function pmproup_get_page_for_user( $user_id = null ) {
	// Get current user's id if none passed.
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Return if no user_id passed and user not logged in.
	if ( empty( $user_id ) ) {
		return null;
	}

	// Return if user does not have a page.
	$user_page_id = get_user_meta($user_id, "pmproup_user_page", true);
	if ( empty( $user_page_id ) ) {
		return null;
	}

	// If the page does not exist, remove the meta and return null.
	$post = get_post( $user_page_id );
	if ( empty( $post ) ) {
		delete_user_meta( $user_id, "pmproup_user_page" );
		return null;
	}

	// If the post is not in 'publish' status, return null.
	if ( 'publish' != $post->post_status ) {
		return null;
	}

	// Return the user's page.
	return $user_page_id;
}

/*
Function to add links to the plugin row meta
*/
function pmproup_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-user-pages.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plus-add-ons/pmpro-user-pages/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-user-pages' ) ) . '">' . __( 'Docs', 'pmpro-user-pages' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-user-pages' ) ) . '">' . __( 'Support', 'pmpro-user-pages' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmproup_plugin_row_meta', 10, 2);
