=== PMPro User Pages ===
Contributors: strangerstudios
Tags: pmpro, membership, user pages
Requires at least: 3.0
Tested up to: 3.4.2
Stable tag: .2

When users checkout from a PMPro registration page, a page is created for them that only that user and WP admins will have access to.

== Description ==
This plugin currently requires Paid Memberships Pro. 

== Installation ==

1. Upload the `pmpro-user-pages` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Edit the PMPROUP_PARENT_PAGE_ID constant in the plugin file.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-user-pages/issues

== Changelog ==
= .2 =
* Added pre_get_posts filter to keep user pages out of searches, etc.
* The main user page will now show a list of sub pages.

= .1 =
* This is the initial version of the plugin.