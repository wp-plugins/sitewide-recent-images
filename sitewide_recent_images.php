<?php
/*
Plugin Name: Sitewide Recent Images
Plugin URI: http://blogs.longwood.edu/librarywerx/
Description: A widget for multisite blogs to feature recent images from all the blogs on their network
Author: Chris Harper (Longwood University)
Version: 1.0
Author URI: http://blogs.longwood.edu
*/

class SitewideRecentImages extends WP_Widget {
	//these are the widget-wide default settings
	private $default_settings = array(
		"title" => "Sitewide Recent Images", 
		"template" => '
<div class="sitewide-recent-images" style="float: left; margin-right: 5px; margin-bottom: 5px; text-align: center; background-color: #e0e0e0; width:100px; height: 100px;">
<a href="%POST_URL%">
<img src="%THUMB_URL%" width="100" height="100" title="%TITLE%" alt="Recent Image">
</a>
</div>', 
		"number_images" => 8, 
		"cache_interval" => 300
	);

	//constructor
	function SitewideRecentImages() {
		$widget_ops = array('classname' => 'sitewide_recent_images', 'description' => 'Displays most recent images on a multisite blog.');
        parent::WP_Widget(false, $name = 'Sitewide Recent Images', $widget_ops);	
    }
	
	//overrides parent function
	//the meat of the widget, this function loads the cache and if it's too old, 
	//  regenerates the cache from the db, then prints everything out
	function widget($args, $instance) {
		global $wpdb;
		extract($args);
		
		//print the widget title before we get going
		$title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title']);
		print $before_widget;
		if (!empty($title)) print $before_title . $title . $after_title;
		
		//see if we can use the cache or it's time to regenerate
		$used_cache = false;
		if ((isset($instance["cache"])) && (is_array($instance["cache"])) && (($instance["last_cached"] + $instance["cache_interval"]) > time())) {
			if (count($instance["cache"]) == $instance["number_images"]) {
				$recent_images = $instance["cache"];
				$used_cache = true;
				print "<!-- Sitewide Recent Images using cache -->";
			}
		}
		
		//if we did not successfully use the cache, then regenerate
		if (!$used_cache) {
			$recent_images = array();
			$date_cutoff = 0;
			
			//get all the blogs from the db
			$blog_list = $wpdb->get_results("SELECT domain, path, blog_id, last_updated FROM wp_blogs WHERE deleted != 1 AND public > 0 ORDER BY last_updated DESC"); //don't list private / deleted blogs 
			
			/* FOR EACH BLOG */
			foreach ($blog_list as $blog) {
				$blog->last_updated = strtotime($blog->last_updated); //convert last update to unix
				if ($blog->last_updated < $date_cutoff) continue; //optimization that skips blogs that are older than our oldest image
				$blog->url = "http://" . $blog->domain . $blog->path;
				
				//get attachments from posts that are more recent than the cutoff, are images, and have a parent post (i.e. they're attached to a post)
				$attachments = $wpdb->get_results("SELECT ID, post_date, post_content, post_title, post_excerpt, guid, post_parent FROM wp_" . $blog->blog_id . "_posts WHERE `post_type` = 'attachment' AND `post_date` > FROM_UNIXTIME($date_cutoff) AND (post_mime_type = 'image/jpeg' OR post_mime_type = 'image/gif' OR post_mime_type = 'image/png') AND post_parent > 0 ORDER BY `post_date` DESC;");
				
				/* FOR EACH ATTACHMENT */
				foreach ($attachments as $attachment) {
					$attachment->post_date = strtotime($attachment->post_date);
					if ($attachment->post_date > $date_cutoff) { //double-check this image is recent enough and then let it into the party
						//get the parent post URL and title
						$parent = $wpdb->get_results("SELECT ID, guid, post_title, post_status FROM wp_" . $blog->blog_id . "_posts WHERE `ID` = " . $attachment->post_parent . " LIMIT 1;");
						$parent = $parent[0];
						if ($parent->post_status != "publish") continue; //skip if parent post is not public
						
						//put all our data into an array
						$image = array(
							"title" => $attachment->post_title, 
							"caption" => $attachment->post_excerpt, 
							"description" => $attachment->post_content, 
							"URL" => $attachment->guid, 
							"timestamp" => $attachment->post_date, 
							"blog_URL" => $blog->url, 
							"parent_post_title" => $parent->post_title, 
							"parent_post_url" => get_blog_permalink($blog->blog_id, $parent->ID)
						);
						
						//get the meta data for this attachment
						$meta = $wpdb->get_results("SELECT * FROM wp_" . $blog->blog_id . "_postmeta WHERE post_id = '" . $attachment->ID . "' AND `meta_key` = '_wp_attachment_metadata'");
						$attachment->meta = maybe_unserialize($meta[0]->meta_value);
						
						//see how big the original image is
						//if it's already pretty small, we can just show that
						if (SRI_isThumkOK($attachment->meta["width"], $attachment->meta["height"])) {
							$image["thumb_URL"] = $image["URL"];
						} else {
							//else we're gonna need to look at the different versions of the image to find a suitable size
							//thumbnail version should be first, but we'll loop through them anyways
							if (count($attachment->meta["sizes"]) > 0) {
								$size_ok = false;
								foreach ($attachment->meta["sizes"] as $image_size) {
									if (SRI_isThumkOK($image_size["width"], $image_size["height"])) {
										$image["thumb_URL"] = dirname($image["URL"]) . "/" . $image_size["file"];
										$size_ok = true;
									}
								}
								if ($size_ok == false) $image["thumb_URL"] = $image["URL"]; //fallback if none of the sizes were cool
							} else {
								$image["thumb_URL"] = $image["URL"]; //fallback if there are no alternate sizes
							}
						}
						
						//find insertion place
						$inserted = false;
						for ($i = 0; $i < count($recent_images); $i++) {
							if ($image["timestamp"] > $recent_images[$i]["timestamp"]) {
								//insert at $i
								$recent_images = SRI_insertArrayIndex($recent_images, $image, $i);
								
								//remove last element if over $instance["number_images"]
								if (count($recent_images) > $instance["number_images"]) array_pop($recent_images);
								
								$inserted = true;
								break;
							}
						}
						
						//if not inserted, then append if not full
						if (!$inserted) {
							if (count($recent_images) < $instance["number_images"]) $recent_images[] = $image;
						}
						
						//set the cutoff only if we've filled the array
						if (count($recent_images) >= $instance["number_images"]) $date_cutoff = $recent_images[$instance["number_images"]-1]["timestamp"];
					}
				}
			}
			
			//write the cache and reset timestamp
			if (count($recent_images) > 0) $this->internal_update(array("cache" => $recent_images, "last_cached" => time()));
		}
		
		print '<div class="sitewide-recent-images-container">' . "\n";
		
		//hurray! output!
		foreach ($recent_images as $recent) {
			//for each image, replace the patterns with the cache data
			$output = $instance["template"];
			$output = str_ireplace("%FULL_URL%", $recent["URL"], $output);
			$output = str_ireplace("%THUMB_URL%", $recent["thumb_URL"], $output);
			$output = str_ireplace("%BLOG_URL%", $recent["blog_URL"], $output);
			$output = str_ireplace("%TITLE%", $recent["title"], $output);
			$output = str_ireplace("%CAPTION%", $recent["caption"], $output);
			$output = str_ireplace("%DESCRIPTION%", $recent["description"], $output);
			$output = str_ireplace("%POST_TITLE%", $recent["parent_post_title"], $output);
			$output = str_ireplace("%POST_URL%", $recent["parent_post_url"], $output);
			$output = str_ireplace("%DATE%", date("D, M j, Y g:i A", $recent["timestamp"]), $output);
			
			print $output;
		}
		
		print '<div style="clear: both"></div>' . "\n";
		print "</div>\n";
		print $after_widget;
	}
	
	//overrides parent function
	//shows the widget settings fields in the widget editor page
	function form($instance) {
		$instance = wp_parse_args((array) $instance, $this->default_settings);
		$title = strip_tags($instance['title']);
		$template = format_to_edit($instance['template']);
		$number_images = esc_attr($instance['number_images']);
		$cache_interval = esc_attr($instance['cache_interval']);
		?>
		
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		<p><label for="<?php echo $this->get_field_id('number_images'); ?>"><?php _e('Number of Images:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('number_images'); ?>" name="<?php echo $this->get_field_name('number_images'); ?>" type="text" value="<?php echo $number_images; ?>" />
		</p>
		<p><label for="<?php echo $this->get_field_id('cache_interval'); ?>"><?php _e('Cache Interval (sec):'); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id('cache_interval'); ?>" name="<?php echo $this->get_field_name('cache_interval'); ?>" type="text" value="<?php echo $cache_interval; ?>" />
		</p>
		<p><label for="<?php echo $this->get_field_id('template'); ?>"><?php _e('Image Template:'); ?></label> 
			<textarea class="widefat" rows="8" cols="20" id="<?php echo $this->get_field_id('template'); ?>" name="<?php echo $this->get_field_name('template'); ?>"><?php echo $template; ?></textarea>
		</p>
		<div><a href="javascript:void(0)" onclick="document.getElementById('<?php echo $this->get_field_id('template'); ?>-patterns').style.display = 'block'; this.style.display = 'none';">Show Patterns</a></div>
		<div id="<?php echo $this->get_field_id('template'); ?>-patterns" style="display: none;">
		%FULL_URL%<br />
		%THUMB_URL%<br />
		%BLOG_URL%<br />
		%TITLE%<br />
		%CAPTION%<br />
		%DESCRIPTION%<br />
		%DATE%<br />
		%POST_TITLE%<br />
		%POST_URL%
		</div>

		<?php
	}
	
	//overrides parent function
	//saves settings for this widget's instance
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		
		if (isset($new_instance['title'])) $instance['title'] = empty($new_instance['title']) ? $this->default_settings['title'] : strip_tags($new_instance['title']);
		if (!empty($new_instance['number_images'])) $instance['number_images'] = SRI_getIntOption($new_instance['number_images'], $this->default_settings['number_images'], 1, 1000);
		if (!empty($new_instance['cache_interval'])) $instance['cache_interval'] = SRI_getIntOption($new_instance['cache_interval'], $this->default_settings['cache_interval'], 0, 86400);
		if (isset($new_instance['template'])) $instance['template'] = empty($new_instance['template']) ? $this->default_settings['template'] : $new_instance['template'];
		
		if (isset($new_instance['last_cached'])) $instance['last_cached'] = $new_instance['last_cached'];
		if (isset($new_instance['cache'])) $instance['cache'] = $new_instance['cache'];

		return $instance;
	}
	
	//new function to save this instance's data when not in widget editor
	private function internal_update($instance) {
		//get all instances of this widget
		$all_instances = $this->get_settings();
		
		//get our current instance
		$old_instance = isset($all_instances[$this->number]) ? $all_instances[$this->number] : array();
		
		//call the overriding update function on this instance
		$instance = $this->update($instance, $old_instance);

		//if we got something back, plug it back into the array of all instances
		if ($instance !== false) $all_instances[$this->number] = $instance;

		//and save all instances of this widget
		$this->save_settings($all_instances);
	}
}

//alternative to array_splice, which has issues with objects
function SRI_insertArrayIndex($array, $new_element, $index) {
	$start = array();
	// get the start of the array
	$start = array_slice($array, 0, $index); 
	// get the end of the array
	$end = array_slice($array, $index);
	// add the new element to the start array
	$start[] = $new_element;
	// merge them back together and return
	return array_merge($start, $end);
}

//puts size bounds on our thumbnails
function SRI_isThumkOK($width, $height) {
	if ((($width <= 200) && ($height <= 200)) && (($width >= 60) && ($height >= 60))) return true; else return false;
}

function SRI_getIntOption($request_opt, $default_opt = 0, $min_val = NULL, $max_val = NULL) {
	if ((isset($request_opt)) && (is_numeric($request_opt))) {
		if ((!is_null($min_val)) && ($request_opt < $min_val)) return $min_val;
		if ((!is_null($max_val)) && ($request_opt > $max_val)) return $max_val;
		return $request_opt;
	} else {
		return $default_opt;
	}
}

function SRI_register() {
	register_widget('SitewideRecentImages');
}

add_action( 'widgets_init', 'SRI_register' );