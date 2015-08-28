=== Paid Memberships Pro - User Pages Add On ===
Contributors: strangerstudios
Tags: paid memberships pro, pmpro, membership, user pages
Requires at least: 3.5
Tested up to: 4.3
Stable tag: .5.1

When users checkout from a PMPro registration page, a page is created for them that only that user and WP admins will have access to.

== Description ==
This plugin currently requires Paid Memberships Pro. 

== Installation ==

1. Upload the `pmpro-user-pages` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Create an empty members page as the top level page for all user pages.
1. Go to Memberships -> User Pages in the dashboard to set the top level page and choose which levels should generate user pages.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-user-pages/issues

== Changelog ==
= .5.1 =
* BUG: Fixed DB warning when checking for user page access. (Thanks, Gary Fichardt) 

= .5 =
* Created a settings page for parent page and user pages levels. This takes the place of the PMPROUP_PARENT_PAGE_ID and PMPROUP_LEVELS constants. Go to Memberships -> User Pages to set the settings and then remove your constant definitions.
* Added an option to the settings page to create user pages for existing members.
* Fixed issue where admins viewing a user page wouldn't see the subpages.
* All subpages of users pages (added manually by admins) are now similarly protected the same as the main subpage.

= .4 =
* Fixed some warnings.
* Readme and title/description updates.

= .3.1 =
* Added PMPROUP_LEVELS constant to pass a comma-separated list of level ids to create user pages for.

= .3 =
* Added a redirect away from the parent page for non-admins and a list of user pages for admins
* Fixed bug where incorrect URL might show up on the confirmation page.
* Added pmpro_user_page_postdata and pmpro_user_page_purchase_postdata to adjust the user pages that are created at checkout.

= .2 =
* Added pre_get_posts filter to keep user pages out of searches, etc.
* The main user page will now show a list of sub pages.

= .1 =
* This is the initial version of the plugin.
