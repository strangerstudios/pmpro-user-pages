<?php
/*
Plugin Name: Paid Memberships Pro - User Pages Add On
Plugin URI: http://www.paidmembershipspro.com/pmpro-user-pages/
Description: When a user signs up, create a page for them that only they (and admins) have access to.
Version: .5.1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com

To setup:

	1. Create a top level page to store the user pages, e.g. "Members".
	2. Navigate to Memberships --> User Pages and complete the settings.
*/

//includes
require_once(dirname(__FILE__) . '/includes/settings.php');	//settings page for dashboard

/*
	Create user pages at checkout
*/
function pmproup_pmpro_after_checkout($user_id)
{
	global $wpdb;
	
	$options = pmproup_getOptions();
	
	//user info
	$user = get_userdata($user_id);
	
	//get the user's level
	$level = pmpro_getMembershipLevelForUser($user_id);
	
	if(in_array($level->ID, $options['levels']))
	{
		//do we have a page for this user yet?
		$user_page_id = get_user_meta($user_id, "pmproup_user_page", true);	
		if(!$user_page_id)
		{
			//need to create it
			$postdata = array(		 		  
			  'post_author' => $user_id,
			  'post_content' => "Pages for your purchases will be shown below.",		  
			  'post_name' => $user->user_login,
			  'post_parent' => $options['parent_page'],		  
			  'post_status' => "publish",
			  'post_title' => $user->display_name,
			  'post_type' => "page"		  
			); 
			
			$postdata = apply_filters("pmpro_user_page_postdata", $postdata, $user, $level);
			
			$user_page_id = wp_insert_post($postdata);
			
			if($user_page_id)
			{
				//add meta
				update_user_meta($user_id, "pmproup_user_page", $user_page_id);
			}		
		}
		
		if($user_page_id)
		{
			//create a new page for this order		
			$postdata = array(		 		  
			  'post_author' => $user_id,
			  'post_content' => "Thank you for your purchase. This page will be updated soon with updates on your order.",		  		 
			  'post_parent' => $user_page_id,		  
			  'post_status' => "publish",
			  'post_title' => $level->name,
			  'post_type' => "page"	  
			);  
			
			$postdata = apply_filters("pmpro_user_page_purchase_postdata", $postdata, $user, $level);
			
			$post_id = wp_insert_post($postdata);				
		}
	}
}
add_action("pmpro_after_checkout", "pmproup_pmpro_after_checkout");

//show the user pages on the account page
function pmproup_pmpro_member_links_top()
{
	global $wpdb;
	
	//does this user have a user page?
	global $current_user;
	if(!empty($current_user->ID))
	{
		$user_page_id = get_user_meta($current_user->ID, "pmproup_user_page", true);
		if($user_page_id)
		{
			//get children
			$pages = $wpdb->get_results("SELECT ID, post_title, UNIX_TIMESTAMP(post_date) as post_date FROM $wpdb->posts WHERE post_parent = '" . $user_page_id . "' AND post_status = 'publish'");
			if(!empty($pages))
			{
				foreach($pages as $page)
				{
				?>
					<li><a href="<?php echo get_permalink($page->ID); ?>"><?php echo $page->post_title; ?> (<?php echo date("m/d/Y", $page->post_date)?>)</a></li>
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
	
	//is this the current user's members page?	
	if(!empty($current_user->ID))
	{
		$up_user = get_user_by('slug', $post->post_name);
		if(empty($up_user))
			return $content;
		
		$user_page_id = get_user_meta($up_user->ID, "pmproup_user_page", true);
		if($user_page_id && $post->ID == $user_page_id)
		{
			//alright, let's show the page list at the end of the_content			
			$pages = $wpdb->get_results("SELECT ID, post_title, UNIX_TIMESTAMP(post_date) as post_date FROM $wpdb->posts WHERE post_parent = '" . $user_page_id . "' AND post_status = 'publish'");
			if(!empty($pages))
			{
				$content .= "\n<ul class='user_page_list'>";
				foreach($pages as $page)
				{
					$content .= '<li><a href="' . get_permalink($page->ID) . '">' . $page->post_title . ' (' . date("m/d/Y", $page->post_date) . ')</a></li>';			
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
	global $wpdb, $post;	
	
	$options = pmproup_getOptions();
	
	if(!is_admin() && !empty($post) && !empty($options['parent_page']) && $post->ID == $options['parent_page'])
	{
		if(!current_user_can("manage_options"))		
		{
			//redirect away
			wp_redirect(home_url());
			exit;
		}
	}
}
add_action("wp", "pmproup_wp_parent_page");

//show admins a list of users on the parent page
function pmproup_parent_page_content($content)
{
	global $post, $wpdb;
	
	$options = pmproup_getOptions();
	
	if(!is_admin() && !empty($options['parent_page']) && $post->ID == $options['parent_page'])
	{
		if(current_user_can("manage_options"))		
		{
			//alright, let's show the page list at the end of the_content			
			$users = $wpdb->get_results("SELECT u.display_name, um.meta_value FROM $wpdb->usermeta um LEFT JOIN $wpdb->users u ON um.user_id = u.ID WHERE um.meta_key = 'pmproup_user_page' GROUP BY um.user_id");
			if(!empty($users))
			{
				$content .= "\n<ul class='user_page_list'>";
				foreach($users as $user)
				{
					$content .= '<li><a href="' . get_permalink($user->meta_value) . '">' . $user->display_name . '</a></li>';		
				}
				$content .= "\n</ul>";
			}	
		}
	}	

	return $content;
}
add_filter("the_content", "pmproup_parent_page_content");

//lock down a page that was created for a user
function pmproup_template_redirect()
{
	global $post, $wpdb, $current_user;
		
	if(empty($post->ID))
		return;
	
	$ancestors = get_post_ancestors($post);
	
	//add current post ID to the array
	if(empty($ancestors))
		$ancestors = array($post->ID);
	else
		$ancestors[] = $post->ID;
			
	//no ancestors? must not be a user page then	
	$page_user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmproup_user_page' AND meta_value IN(" . implode(",", $ancestors) . ") LIMIT 1");
		
	if(!empty($page_user_id))
	{
		//must be logged in
		if(!$current_user->ID)
		{
			wp_redirect(home_url());
			exit;
		}
		elseif($page_user_id != $current_user->ID && !current_user_can("manage_options"))
		{
			wp_redirect(home_url());
			exit;
		}
	}
}
add_action("template_redirect", "pmproup_template_redirect");

//update the confirmation page
function pmproup_pmpro_confirmation_message($message)
{
	global $wpdb, $current_user;
	
	if(empty($current_user->ID))
		return $message;
	
	$user_page_id = get_user_meta($current_user->ID, "pmproup_user_page", true);
		
	if(!empty($user_page_id))
	{
		//get the last page created for them
		$lastpage = $wpdb->get_row("SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'page' AND post_parent = '" . $user_page_id . "' ORDER BY ID DESC LIMIT 1");
		
		if(!empty($lastpage))
		{
			//okay update the message
			$message .= "<p><strong>Important</strong>. Updates on your order will be posted here: <a href=\"" . get_permalink($lastpage->ID) . "\">" . $lastpage->post_title . "</a></p>";
		}
	}
	
	return $message;
}
add_action("pmpro_confirmation_message", "pmproup_pmpro_confirmation_message");

/*
	Remove user pages from frontend searches/etc.
	
	All user pages are children of the User Pages parent page. So lets hide those pages (the main user pages) and the children of those pages (the purchased pages).
*/
function pmproup_pre_get_posts($query)
{
	$options = pmproup_getOptions();
	
	//don't fix anything on the admin side, and also let admins see everything
	if(is_admin() || current_user_can("manage_options") || empty($options['parent_page']))
		return $query;
	
	//Using a global to cache the user page ids. If it is not set, we need to look them up. (Note we're ignoring posts where the current user is author.)
	global $wpdb, $current_user, $all_pmpro_user_page_ids;	
	if(!isset($all_pmpro_user_page_ids))
	{
		//these are the top level member pages
		if(!empty($current_user->ID))
			$main_user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = '" . $options['parent_page'] . "' AND ID <> '" . $options['parent_page'] . "' AND post_author <> '" . $current_user->ID . "'");	
		else
			$main_user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = '" . $options['parent_page'] . "' AND ID <> '" . $options['parent_page'] . "'");	
		if(empty($main_user_page_ids))
			return $query;		//didn't find anything
			
		//these are the individually purchased user pages
		if(!empty($current_user->ID))
			$user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent IN (" . implode(",", $main_user_page_ids) . ") AND post_author <> '" . $current_user->ID . "'");	
		else
			$user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent IN (" . implode(",", $main_user_page_ids) . ")");	
			
		//combine the top level and sub pages
		global $all_pmpro_user_page_ids;	
		$all_pmpro_user_page_ids = array_merge($main_user_page_ids, $user_page_ids);
	}	
		
	//add user page ids to the post__not_in query var
	$query->set('post__not_in', array_merge($query->query_vars['post__not_in'], $all_pmpro_user_page_ids));	
			
	return $query;
}
add_filter("pre_get_posts", "pmproup_pre_get_posts");

/*
Function to add links to the plugin row meta
*/
function pmproup_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-user-pages.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plugins-on-github/pmpro-user-pages/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmproup_plugin_row_meta', 10, 2);
