<?php
/*
Plugin Name: PMPro User Pages
Plugin URI: http://www.paidmembershipspro.com/pmpro-user-pages/
Description: When a user signs up, create a page for them that only they (and admins) have access to.
Version: .2
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

define("PMPROUP_PARENT_PAGE_ID", 382);

function pmproup_pmpro_after_checkout($user_id)
{
	global $wpdb;
	
	//user info
	$user = get_userdata($user_id);
	
	//get the user's level
	$level = pmpro_getMembershipLevelForUser($user_id);
	
	//do we have a page for this user yet?
	$user_page_id = get_user_meta($user_id, "pmproup_user_page", true);	
	if(!$user_page_id)
	{
		//need to create it
		$postdata = array(		 		  
		  'post_author' => $user_id,
		  'post_content' => "Pages for your purchases will be shown below.",		  
		  'post_name' => $user->user_login,
		  'post_parent' => PMPROUP_PARENT_PAGE_ID,		  
		  'post_status' => "publish",
		  'post_title' => $user->display_name,
		  'post_type' => "page"		  
		);  
		
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
		
		$post_id = wp_insert_post($postdata);				
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

//lock down a page that was created for a user
function pmproup_wp()
{
	global $post, $wpdb, $current_user;
	
	if(empty($post->ID))
		return;
	
	$page_user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'pmproup_user_page' AND (meta_value = '" . $post->ID . "' OR meta_value = '" . $post->post_parent . "') LIMIT 1");
	
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
add_action("wp", "pmproup_wp");

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
		$lastpage = $wpdb->get_row("SELECT ID, post_title FROM $wpdb->posts WHERE post_parent = '" . $user_page_id . "' ORDER BY ID DESC LIMIT 1");
		
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
	
	All user pages are children of the PMPROUP_PARENT_PAGE_ID page. So lets hide those pages (the main user pages) and the children of those pages (the purchased pages).
*/
function pmproup_pre_get_posts($query)
{
	//don't fix anything on the admin side, and also let admins see everything
	if(is_admin() || current_user_can("manage_options"))
		return $query;
	
	//Using a global to cache the user page ids. If it is not set, we need to look them up. (Note we're ignoring posts where the current user is author.)
	global $wpdb, $current_user, $all_pmpro_user_page_ids;	
	if(!isset($all_pmpro_user_page_ids))
	{
		//these are the top level member pages
		if(!empty($current_user->ID))
			$main_user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = '" . PMPROUP_PARENT_PAGE_ID . "' AND ID <> '" . PMPROUP_PARENT_PAGE_ID . "' AND post_author <> '" . $current_user->ID . "'");	
		else
			$main_user_page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = '" . PMPROUP_PARENT_PAGE_ID . "' AND ID <> '" . PMPROUP_PARENT_PAGE_ID . "'");	
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