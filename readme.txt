=== Sitewide Recent Images ===
Contributors: harpercl
Tags: widget, multisite, network, images, gallery, photos
Requires at least: 3.0
Tested up to: 3.1.4
Stable tag: trunk

A widget for multisite blogs to feature recent images from all the blogs on their network

== Description ==

This plugin will add a widget that displays thumbnails of the most recently posted images from all the blogs in a network. It's highly optimized to be scalable for large networks, so it should need only about 100 database queries even for a network of thousands of sites. And to keep any performance drain as minimal as possible, the plugin utilizes caching with an adjustable update interval.

* Note that images are taken only from blogs and posts which don't have privacy options enabled (compatible with [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/)) and the image must be attached to a post. So essentially, only public images are shown.

The widget formatting is completely customizable via a template and/or CSS (if available). The template uses patterns that will plug in values for the following:

* Image Title
* Image Caption
* Image Description
* Image Thumbnail URL
* Full Image URL
* Image Publish Date
* Parent Post Title
* Parent Post URL
* Blog URL

== Installation ==

If you want to install this plugin for all the blogs on your network, follow the typical plugin install procedure:

1. Copy the contents of this archive to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the 'Appearance > Widgets' menu and add 'Sitewide Recent Images' to a sidebar

Alternatively, you can add this plugin to a theme that can be used on only a subset of your blogs:

1. Copy the file 'sitewide_recent_images.php' from this archive and into the theme's directory
1. Edit the theme's 'functions.php' file, adding the following that will include the plugin:
	`include_once(get_template_directory() . '/sitewide_recent_images.php');`
1. Activate the modified theme in 'Appearance > Themes'
1. Go to the 'Appearance > Widgets' menu and add 'Sitewide Recent Images' to a sidebar

== Frequently Asked Questions ==

= The images are wrapping into only one column. There is too much white-space to the right of the images. =

As the width of sidebars varies between themes, you might have to adjust the default width of 100 pixels to get the images to wrap perfectly. You can adjust the width in the widget's 'Image Template' option. Lower the width if images are wrapping into one column. And increase the width if there is too much white-space on the right side. For example, the Twenty-Ten theme works best with 95px width.

== Screenshots ==

1. A mostly default view of the widget on the front-end
2. Widget options on the back-end

== Changelog ==

= 1.0 =
* First public release