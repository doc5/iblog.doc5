<?php 

class WPSEO_OpenGraph {

	public function __construct() {
		$options = get_wpseo_options();

		add_action( 'wpseo_head', array(&$this, 'opengraph') );

		$options = get_wpseo_options();

		if ( isset( $options['opengraph'] )  && $options['opengraph'] )
			add_filter('language_attributes', array(&$this, 'add_opengraph_namespace'));
	}

	public function opengraph() {
		$options = get_wpseo_options();

		global $wp_query, $paged;
		
		wp_reset_query();
		
		$this->id();
		$this->title();
		$this->description();
		$this->url();
		$this->site_name();
		$this->type();
		$this->image();
		do_action('wpseo_opengraph');
	}

	public function add_opengraph_namespace( $output ) {
		return $output . ' xmlns:og="http://opengraphprotocol.org/schema/"';
	}
	
	public function id() {
		$options = get_wpseo_options();
		if ( isset($options['fb_adminid']) && trim($options['fb_adminid']) != '' )
			echo "<meta property='fb:admins' content='".esc_attr( strip_tags( stripslashes( $options['fb_adminid'] ) ) )."'/>\n";

		if ( isset($options['fb_pageid']) && trim($options['fb_pageid']) != '' )
			echo "<meta property='fb:page_id' content='".esc_attr( strip_tags( stripslashes( $options['fb_pageid'] ) ) )."'/>\n";

		if ( isset($options['fb_appid']) && trim($options['fb_appid']) != '' )
			echo "<meta property='fb:app_id' content='".esc_attr( strip_tags( stripslashes( $options['fb_appid'] ) ) )."'/>\n";
	}
	
	private function title( ) {
		global $post, $wp_query;
		if ( empty($post) && is_singular() ) {
			$post = $wp_query->get_queried_object();
		}

		$options = get_wpseo_options();

		if ( is_home() && 'posts' == get_option('show_on_front') ) {
			if ( isset($options['title-home']) && $options['title-home'] != '' )
				$title = wpseo_replace_vars( $options['title-home'], array() );
			else
				$title = get_bloginfo('name');
		} else if ( is_home() && 'posts' != get_option('show_on_front') ) {
			// For some reason, in some instances is_home returns true for the front page when page_for_posts is not set.
			if ( get_option('page_for_posts') == 0 )
				$post = get_post( get_option( 'page_on_front') );
			else
				$post = get_post( get_option( 'page_for_posts' ) );
			$fixed_title = wpseo_get_value('title');
			if ( $fixed_title ) { 
				$title = $fixed_title; 
			} else {
				if (isset($options['title-'.$post->post_type]) && !empty($options['title-'.$post->post_type]) )
					$title = wpseo_replace_vars($options['title-'.$post->post_type], (array) $post );
				else
					$title = get_bloginfo('name');
			}
		} else if ( is_singular() ) {
			$fixed_title = wpseo_get_value('title');
			if ( $fixed_title ) { 
				$title = $fixed_title; 
			} else {
				if (isset($options['title-'.$post->post_type]) && !empty($options['title-'.$post->post_type]) ) {
					$title = wpseo_replace_vars( $options['title-'.$post->post_type], (array) $post );
				} else {
					$title = get_the_title();
					$title = apply_filters('single_post_title', $title);
				}
			}
		} else if ( is_category() || is_tag() || is_tax() ) {
			$term = $wp_query->get_queried_object();
			$title = trim( wpseo_get_term_meta( $term, $term->taxonomy, 'title' ) );
			if ( !$title || empty($title) ) {
				if ( isset($options['title-'.$term->taxonomy]) && !empty($options['title-'.$term->taxonomy]) ) {
					$title = wpseo_replace_vars($options['title-'.$term->taxonomy], (array) $term );
				} else {
					if ( is_category() )
						$title = single_cat_title('', false);
					else if ( is_tag() )
						$title = single_tag_title('', false);
					else if ( is_tax() ) {
						if ( function_exists('single_term_title') ) {
							$title = single_term_title('', false);
						} else {
							$term = $wp_query->get_queried_object();
							$title = $term->name;
						}
					}
				}
			}
		} else if ( is_search() ) {
			if ( isset($options['title-search']) && !empty($options['title-search']) )
				$title = wpseo_replace_vars($options['title-search'], (array) $wp_query->get_queried_object() );	
			else
				$title = __('Search for "').get_search_query().'"';
		} else if ( is_author() ) {
			$author_id = get_query_var('author');
			$title = get_the_author_meta('wpseo_title', $author_id);
			if ( empty($title) ) {
				if ( isset($options['title-author']) && !empty($options['title-author']) )
					$title = wpseo_replace_vars($options['title-author'], array() );
				else
					$title = get_the_author_meta('display_name', $author_id); 
			}
		} else if ( is_post_type_archive() ) {
			$post_type = get_post_type();
			if ( isset($options['title-ptarchive-'.$post_type]) && '' != $options['title-ptarchive-'.$post_type] ) {
				return $options['title-ptarchive-'.$post_type];
			} else {
				$post_type_obj = get_post_type_object( $post_type );
				$title = $post_type_obj->labels->menu_name;
			}
		} else if ( is_archive() ) {
		 	if ( isset($options['title-archive']) && !empty($options['title-archive']) )
				$title = wpseo_replace_vars($options['title-archive'], array('post_title' => $title) );
			else if ( is_month() ) 
				$title = single_month_title(' ', false).' '.__('Archives'); 
			else if ( is_year() )
				$title = get_query_var('year').' '.__('Archives'); 
		} else if ( is_404() ) {
		 	if ( isset($options['title-404']) && !empty($options['title-404']) )
				$title = wpseo_replace_vars($options['title-404'], array('post_title' => $title) );
			else
				$title = __('Page not found');
		} 
		echo "<meta property='og:title' content='".esc_attr( strip_tags( stripslashes( $title ) ) )."'/>\n";
	}
		
	public function url() {
		$url = WPSEO_Frontend::canonical( false );
		echo "<meta property='og:url' content='".esc_attr( $url )."'/>\n";
	}
	
	public function type() {
		if ( is_singular() ) {
			$type = wpseo_get_value('og_type');
			if (!$type || $type == '')
				$type = 'article';
		} else {
			$type = 'website';
		}
		echo "<meta property='og:type' content='".esc_attr( $type )."'/>\n";
	}
		
	public function image( $image = '' ) {
		global $post;
		// Grab the featured image
		if ( is_singular() ) {
			if ( empty( $image ) && function_exists('has_post_thumbnail') && has_post_thumbnail( $post->ID ) ) {
				$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'thumbnail' );
				if ( $thumbnail )
					$image = $thumbnail[0];
			// If that's not there, grab the first attached image
			} else {
				$files = get_children( 
							array( 
							'post_parent' => $post->ID,
							'post_type' => 'attachment',
							'post_mime_type' => 'image',
							) 
						);
			    if ( $files ) {
			        $keys = array_reverse( array_keys( $files ) );
			        $image = image_downsize( $keys[0], 'thumbnail' );
			        $image = $image[0];
			    }
			}	
		} 
		if ( $image != '' )
			echo "<meta property='og:image' content='".esc_attr( $image )."'/>\n";
	}
		
	public function description() {
		$desc = wpseo_get_value('opengraph-description');
		
		if ( !$desc )
			$desc = WPSEO_Frontend::metadesc( false );

		if ( $desc && $desc != '' )
			echo "<meta property='og:description' content='".esc_attr( $desc )."'/>\n";
	}

	public function site_name() {
		echo "<meta property='og:site_name' content='".esc_attr( get_bloginfo('name') )."'/>\n";
	}
}

$wpseo_og = new WPSEO_OpenGraph;