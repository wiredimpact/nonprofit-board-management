=== Nonprofit Board Management ===
Contributors: wiredimpact
Tags: nonprofits, boards, non-profits, directors, board governance
Requires at least: 3.0
Tested up to: 6.8
Stable tag: 1.3
License: GPLv3
License URI: http://www.gnu.org/licenses/lgpl-3.0.html

A simple, free way to manage your nonprofit’s board.

== Description ==

It’s simple.  It’s free.  And it makes managing your board a whole lot easier.

**Why is the Nonprofit Board Management plugin helpful?**

* **Easily See Board Member Info -** You can easily see a list of everyone on the board including their name, phone number, email address, all the committees they serve on, and a picture of each member.
* **Post Upcoming Events and Accept RSVPs -** You can list all of your upcoming events, easily get directions to each event and see which board members have RSVPed to attend.
* **Find Who’s On Each Committee -** You can see a complete list of every committee on the board and who serves on each committee.
* **Access Important Board Resources -** A customizable section for board members to find links to documents such as meeting minutes or bylaws, notes for board members, or any other content you think is helpful.
* **Get Support via Video –** We’ve included a variety of support videos that walk you through all the major features, making it easy to find help when you need it.

**Can I see the plugin in action?**

Of course you can.  Here you go.

[youtube https://www.youtube.com/watch?v=LF3LnqR1JMs]


== Installation ==

**How Do I Install the Plugin?**

The easiest way to install the Nonprofit Board Management plugin is to go to Plugins >> Add New in the WordPress backend and search for "Nonprofit Board Management." On the far right side of the search results, click "Install." If that doesn't work follow the steps below:

1.	Download the Nonprofit Board Management plugin and unzip the files.
1.	Upload the nonprofit-board-management folder to the /wp-content/plugins/ directory.
1.	Activate the Nonprofit Board Management plugin through the "Plugins" menu in WordPress.


== Frequently Asked Questions ==

Here are some frequently asked questions about how to use the plugin.

= How do you add a board member? =

[youtube https://www.youtube.com/watch?v=kCwsqWrwkaA]

= How do you change your personal information? =

[youtube https://www.youtube.com/watch?v=GPwL7A-3d-M]

= How do you serve on the board as a WordPress admin? =

[youtube https://www.youtube.com/watch?v=ZYYaIFYtG88]

= How do you add a board event? =

[youtube https://www.youtube.com/watch?v=TfQIeeIVyt8]

= How do you RSVP to a board event? =

[youtube https://www.youtube.com/watch?v=Nk6blZ3Zopc]

= How do you create a committee and add committee members? =

[youtube https://www.youtube.com/watch?v=yInKtr36Y5s]

= How do you edit your board resources? =

[youtube https://www.youtube.com/watch?v=XsXXEHAs9TU]

= How do you list your board members on your public website? =

[youtube https://www.youtube.com/watch?v=kYdP0dtueEE]

Note: With the new WordPress editor, which is used in WordPress 5.0 and above, you can use the Shortcode block and paste the shortcode "[list_board_members]" into the block to display your board members.

If you're comfortable working with code, you can customize the information that displays by copying and editing the template
file located at `wp-content/plugins/nonprofit-board-management/templates/list-board-members.php` to your website's active theme.

= How do you get more help? =

If you have more questions you can always head over to https://wordpress.org/support/plugin/nonprofit-board-management and fill out a support request.


== Screenshots ==

1. A list of all of your board members with contact info
2. Every board committee including who serves on each
3. Upcoming board events with buttons to RSVP for each event
4. Editable board resources to provide content and links for board members
5. Support videos embedded within the plugin to help you along the way


== Changelog ==

= 1.3 =
* Tested up to WordPress 6.8.

= 1.2.1 =
* Removed the YouTube video titled *Nonprofit Board Management: Getting Started* since it's no longer available online.

= 1.2 =
* Updated links to point correctly to external websites.
* Fixed a few minor typos.
* Fixed issues where some strings weren't handling translation correctly.
* Tested up to WordPress 6.7.

= 1.1.19 =
* Fixed critical error in WordPress 6.1 where the get_users() function was called before the cache_users() function was defined.
* Tested up to WordPress 6.1.

= 1.1.18 =
* Tested up to WordPress 6.0.

= 1.1.17 =
* Fixed bug that prevented a super admin of a multisite from adding themselves as a board member.
* Tested up to WordPress 5.8.

= 1.1.16 =
* Fixed the Board Member role not being available on subsites of a multisite installation if the plugin was network activated.
* Tested up to WordPress 5.7.

= 1.1.15 =
* Removed all references to premium extensions that will no longer be for sale.

= 1.1.14 =
* Tested up to WordPress 5.6.

= 1.1.13 =
* Tested up to WordPress 5.5.

= 1.1.12 =
* Updated the way the Help tab is removed for Board Members due to the 'contextual_help' filter being deprecated.
* Tested up to WordPress 5.4.

= 1.1.11 =
* Tested up to WordPress 5.3.

= 1.1.10 =
* Tested up to WordPress 5.2.

= 1.1.9 =
* Updated the instructions for listing board members using the new WordPress editor.
* Tested up to WordPress 5.0.

= 1.1.8 =
* Tested up to WordPress 4.9 and removed outdated code that's no longer used (deprecated) in WordPress.

= 1.1.7 =
* Tested up to WordPress 4.8 and made minor accessibility improvements.

= 1.1.6 =
* Tested up to WordPress 4.7.

= 1.1.5 =
* Updated plugin to allow for translation

= 1.1.4 =
* Remove the reference assignment on the object to prevent any strict warnings and broken headers on plugin activation/deactivation.
* Tested up to WordPress 4.4.

= 1.1.3 =
* Fixed XSS security vulnerability with add_query_arg() and remove_query_arg().
* Tested up to WordPress 4.2.

= 1.1.2 =
* Tested up to WordPress 4.1.

= 1.1.1 =
* Fixed bug that caused error when adding new board member users.
* Removed the activity dashboard widget for all board members that aren't admins.
* Tested up to WordPress 4.0.

= 1.1.0 =
* Added an updated menu icon for the new admin design.
* Adjusted styles of the Board Management Upgrades sidebar to match the new admin design.
* Added the ability to show a list of your board members publicly by using the [list_board_members] shortcode.
* Other compatibility changes for WordPress 3.8.

= 1.0.5 =
* Fixed bug causing phone numbers not to save for board members.

= 1.0.4 =
* Fixed bug that showed a board member's previous RSVP on the next event on the Board Events page.
* Updated the demo video for the WordPress plugin repository.

= 1.0.3 =
* Now showing the total number of board members above the Board Members table.
* Updated wiredimpact.com URL in the Board Resources Helpful Resources section.

= 1.0.2 =
* Fixed premium extension URLs for sidebar on support and board resources pages.

= 1.0.1 =
* Updated WordPress.org URLs to match the wiredimpact.com website.

= 1.0 =
* Initial release.

== Upgrade Notice ==
