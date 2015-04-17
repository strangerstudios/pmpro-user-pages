<?php
/*
	Function get options.
*/
function pmproup_getOptions()
{
	$options = get_option('pmpro_user_pages', false);

	//default options
	if(empty($options))
	{
		//defaults
		$options = array('parent_page'=>'', 
				'levels'=>array(), 
				'user_page_title'=>'',
				'user_page_content'=>'Pages for your purchases will be shown below.',
				'level_page_title'=>'',
				'user_page_content'=>'Thank you for your purchase. This page will be updated soon with updates on your order.'
				);
		
		//check for constants from earlier versions
		if(defined('PMPROUP_PARENT_PAGE_ID'))
			$option['parent_page'] = PMPROUP_PARENT_PAGE_ID;
			
		if(defined('PMPROUP_LEVELS'))
			$options['levels'] = explode(PMPROUP_LEVELS);
		
		update_option("pmpro_user_pages", $options);
	}
	
	return $options;
}

/*
	Add user pages settings page to admin
*/
function pmproup_add_pages()
{
	add_submenu_page('pmpro-membershiplevels', 'User Pages', 'User Pages', 'manage_options', 'pmpro-user-pages', 'pmproup_adminpage');
}
add_action('admin_menu', 'pmproup_add_pages', 20);

/*
	Add page to admin bar
*/
function pmproup_admin_bar_menu() 
{
	global $wp_admin_bar;
	if ( !is_super_admin() || !is_admin_bar_showing() )
		return;	
	$wp_admin_bar->add_menu( array(
	'id' => 'pmpro-user-pages',
	'parent' => 'paid-memberships-pro',
	'title' => __( 'User Pages', 'pmpro'),
	'href' => get_admin_url(NULL, '/admin.php?page=pmpro-user-pages') ) );	
}
add_action('admin_bar_menu', 'pmproup_admin_bar_menu', 1000);

/*
	Settings Page
*/
function pmproup_adminpage()
{
	global $wpdb;
	
	//get options
	$options = pmproup_getOptions();
	
	//saving?
	if(!empty($_REQUEST['savesettings']))
	{
		//get parent page
		$parent_page = intval($_REQUEST['parent_page']);
		
		//get levels and make sure they are all ints
		if(!empty($_REQUEST['levels']))
			$olevels = $_REQUEST['levels'];
		else
			$olevels = array();
		$levels = array();
		foreach($olevels as $olevel)
			$levels[] = intval($olevel);
		
		//update options
		$options['parent_page'] = $parent_page;
		$options['levels'] = $levels;
		update_option('pmpro_user_pages', $options);
		
		//see if existing member checkbox is checked		
		if(!empty($_REQUEST['existing_members']) && !empty($levels))
		{
			//find all members
			$member_ids = $wpdb->get_col("SELECT user_id FROM $wpdb->pmpro_memberships_users WHERE status = 'active' AND membership_id IN('" . implode("','", $levels) . "')");
			
			//loop through
			if(!empty($member_ids))
			{
				echo "<p>Generating user pages... ";
				
				$count = 0;
				foreach($member_ids as $member_id)
				{
					//check for user page
					$user_page_id = get_user_meta($member_id, "pmproup_user_page", true);
					
					//no page, create one
					if(empty($user_page_id))
					{
						$count++;
						echo ". ";
						pmproup_pmpro_after_checkout($member_id);
					}
				}
				
				echo " Done. " . $count . " " . _n('member', 'members', $count) . " setup.</p>";
			}						
		}
	}
	
	require_once(PMPRO_DIR . "/adminpages/admin_header.php");		
	?>
		<form action="" method="post" enctype="multipart/form-data"> 
			<h2>User Pages Settings</h2>
		
			<?php
				if(defined('PMPROUP_PARENT_PAGE_ID') || defined('PMPROUP_LEVELS'))
				{
				?>
				<div id="message" class="error"><p><strong>Warning:</strong> PMPROUP_PARENT_PAGE_ID and PMPROUP_LEVELS seem to be defined already... maybe in your wp-config.php. These constants are no longer needed and you should find their definitions and delete them. The settings here will control the User Pages addon.</p></div>
				<?php
				}
			?>
		
			<p>The User Pages addon can be used to create a "user page" for new members of specific levels. The user pages will only be visible to site admins and the user it was created for.</p>
			
			<hr />
			
			<p>The <strong>Top Level Page</strong> is the WordPress page under which all user pages will be created. You can create a page called "User Pages" and then choose it from the dropdown below.</p>						
		
			<table class="form-table">
			<tbody>				
				<tr>
					<th scope="row" valign="top">
						<label for="parent_page"><?php _e('Top Level Page', 'pmpro');?>:</label>
					</th>
					<td>
						<?php
							wp_dropdown_pages(array("name"=>"parent_page", "show_option_none"=>"-- Choose One --", "selected"=>$options['parent_page']));
						?>						
					</td>
				</tr>
			</tbody>
			</table>
			
			<hr />
			
			<p>Only members of the levels specified below will have user pages created for them. Hold the Control button (or Command button on Macs) and click to select/deselect multiple levels.</p>
			
			<table class="form-table">
			<tbody>				
				<tr>
					<th scope="row" valign="top">
						<label for="levels"><?php _e('User Pages Levels', 'pmpro');?>:</label>
					</th>
					<td>
						<select id="levels" name="levels[]" multiple="yes">
							<?php
								$levels = pmpro_getAllLevels(true,true);
								foreach($levels as $level)
								{
								?>
									<option value="<?php echo $level->id;?>" <?php if(in_array($level->id, $options['levels'])) echo 'selected="selected"';?>><?php echo $level->name;?></option>
								<?php
								}
							?>
						</select>						
					</td>
				</tr>
			</tbody>
			</table>
			
			<hr />
						
			<p>If you have existing members from before the User Pages addon was activated, you can <strong>check this box and click the Save Settings button to generate user pages for existing members</strong>. Only members in the above selected levels will have user pages created. Links to the User Pages will show up in the Member Links section of each user's membership account page. Users will not otherwise be notified of the creation of this page.</p>
			
			<table class="form-table">
			<tbody>				
				<tr>
					<th scope="row" valign="top">
						
					</th>
					<td>
						<input type="checkbox" id="existing_members" name="existing_members" value="1" />
						<label for="existing_members">Generate User Pages for existing members.</label>					
					</td>
				</tr>
			</tbody>
			</table>						
			
			<hr />
			
			<p class="submit">            
				<input name="savesettings" type="submit" class="button button-primary" value="<?php _e('Save Settings', 'pmpro');?>" /> 		                			
			</p>
		</form>
	<?php
	
	require_once(PMPRO_DIR . "/adminpages/admin_footer.php");
}
