<?php

/*
Plugin Name: Form 2 Post
Plugin URI: http://www.raphaelramos.com.br/wp/plugins/form-2-post/
Description: Store WPCF7 posted data as post.
Author: Raphael Ramos
Author URI: http://www.raphaelramos.com.br/
Text Domain: form-2-post
Domain Path: /lang/
Version: 0.3.1
Date: 2015-05-12
*/

	class Form_2_Post {

		// post name
		private $cpt = 'form2post';

		function __construct(){

			// deal with form submit
			add_action( 'wpcf7_before_send_mail', array( $this, 'data' ) );

			// create cpt for store data
			add_action( 'init', array( $this, 'cpt' ) );
			
			// shortcode
			add_shortcode( "f2p", array( $this, 'shortcode' ) );
		}

		
		/***
		 *	Deal with posted data
		 *==============================*/
		public function data( $cf7 ){

			// posted data
			$form_data = $_POST;
			unset(
				$form_data[ "_wpcf7" ]
				, $form_data[ "_wpcf7_version" ]
				, $form_data[ "_wpcf7_locale" ]
				, $form_data[ "_wpcf7_unit_tag" ]
				, $form_data[ "_wpnonce" ]
			);

			// user data 
			$user = wp_get_current_user();
			
			// mail body
			$mail = $cf7->prop( 'mail' );
			if( function_exists( 'all_fields_wpcf7_before_send_mail' ) ){
				$mail = all_fields_wpcf7_before_send_mail( $mail );
			}
			$body = wpcf7_mail_replace_tags( $mail[ 'body' ], $cf7 );

			// post data for saving
			$post = array(
				'post_author'		=> $user->ID
				, 'post_type' 		=> $this->cpt
				, 'post_title' 		=> $cf7->title() .' - '. date( 'Y-m-d H:i:s' )
				, 'post_content' 	=> $body // Store the email body as the post content
				, 'post_parent' 	=> $cf7->id()
				, 'post_status' 	=> 'pending' // Set the default post status to 'pending'
				, 'comment_status' 	=> 'closed' // Disable comments
				, 'ping_status' 	=> 'closed' // Disable pingbacks
			);

			// create post
			$postID = wp_insert_post( $post, true );

			// check for error
			if( is_wp_error( $postID ) ) return $postID->get_error_message();
			
			
			// files data
			$submit = WPCF7_Submission::get_instance();
			$files = $submit->uploaded_files();
			
			// deal with files
			if( count( $files ) ){
				$data_file = array();
				$body .= "\n\n------------------------------\n";
				foreach( $files as $k => $file ){
					if( $upload = $this->_media( $file, $postID ) ){
						$form_data[ $k ] = $upload;
						$data_file[] = array( 'name' => $k, 'src' => $upload );
						$body .= "{$k}: <a href='{$upload}'>{$upload}</a>\n";
					}
				}
				$form_data[ 'files' ] = json_encode( $data_file );

				// update post content with files links
				$post = array(
					'ID' => $postID,
					'post_content' => $body
				);
				wp_update_post( $post );
			}

			// save fields as post meta
			foreach( $form_data as $key => $val ){
				add_post_meta( $postID, $key, $val, true ) or update_post_meta( $postID, $key, $val );
			}
		}


		/***
		 *	Deal with media files
		 *==============================*/
		private function _media( $file, $pid ){
			$filename = basename( $file );
			$testtype = wp_check_filetype_and_ext( $file, $filename, null );

			// Check if a proper filename was given for in incorrect filename and use it instead
			if( $testtype[ 'proper_filename' ] )
				$filename = $testtype[ 'proper_filename' ];

			// need check this
			if( ( !$testtype[ 'type' ] || !$testtype[ 'ext' ]) && !current_user_can( 'unfiltered_upload' ) ){
				#return __( 'Sorry, this file type is not permitted for security reasons.' );
				return false;
			}

			if( !$testtype[ 'ext' ] )
				$testtype[ 'ext' ] = ltrim( strrchr( $filename, '.' ), '.' );

			// Check if the uploads directory exists/create it.  If it fails, the parent directory probably isn't writable.
			if( !( $uploads = wp_upload_dir( null ) ) ){
				// return $uploads[ 'error' ];
				return false;
			}

			// adjust file name for unique name
			$filename = wp_unique_filename( $uploads[ 'path' ], $filename );

			// full ath of new file
			$new_file = $uploads[ 'path' ] .'/'. $filename;

			// Copy the uploaded file to the correct uploads folder.
			if( false === @copy( $file, $new_file ) ){
				// return sprintf( __('The file %s could not be moved to %s.'), $file, $new_file );
				return false;
			}

			// Set correct file permissions
			$stat = stat( dirname( $new_file ) );
			$perms = $stat[ 'mode' ] & 0000666;
			@chmod( $new_file, $perms );

			// Add the attachment
			$attachment = array(
				'post_mime_type'    => $testtype[ 'type' ],
				'post_title'        => preg_replace( '/\.[^.]+$/', '', $filename ),
				'post_content'      => '',
				'post_status'       => 'inherit',
			);
			
			if( $att_id = wp_insert_attachment( $attachment, $new_file, $pid ) ){
			
				// Test for an image and only perform the rest if the upload is an image...
				if( @getimagesize( $new_file ) !== false ){
					require_once ABSPATH .'wp-admin/includes/image.php';
					$att_data = wp_generate_attachment_metadata( $att_id, $new_file );
					wp_update_attachment_metadata( $att_id, $att_data );
				}
				
				return wp_get_attachment_url( $att_id );
			}
			return false;
		}
	
		public function cpt(){
			register_post_type( $this->cpt, array(
				'label'     => 'Form 2 Post',
				'labels'    => array(
									'singular_name' => 'Form Submit',
									'all_items'     => 'View Submissions',
									'edit_item'     => 'Edit Submit',
									'new_item'      => 'New Submit',
									'view_item'     => 'View Submit',
									'search_items'  => 'Search Form Submissions',
									'not_found'     => 'No matching submission were found',
									'menu_name'     => 'Form 2 Post',
							   ),
				'description'       => 'Submitted form entries for Contact Form 7.',
				'show_ui'           => true,
				'show_in_menu'      => true,
				// 'menu_position'     => 5,
				'menu_icon' 		=> 'dashicons-index-card',
				'hierarchical'      => true, // Allows specifying a parent, which will be the Form ID
				'supports'          => array( 'title', 'editor', 'author', 'custom-fields', 'revisions' ),
			) );
		}
		
		public function shortcode( $atts ){
			extract( shortcode_atts( array(
				'form_id' => 0
				, 'html_class' => ''
				, 'html_id' => ''
				, 'include_css' => false
				, 'height' => ''
			), $atts ) );
			
			$parent = get_post( $form_id );
			
			if( $html_class != '' ) $html_class = ' '. trim( $html_class );
			
			if( $html_id != '' ) $html_id = ' id="'. trim( $html_id ) .'"';
			
			$count = 0;
			
			$data = array();
			
			$html = array();
			
			if( $include_css !== false ) $html[] = '<link rel="stylesheet" href="'. plugin_dir_url( __FILE__ ) .'f2p.css" />'."\n";
			
			$style = '';
			if( $height != '' ) $style = ' style="height: '. $height .'px"';
			
			$html[] = '<div'. $html_id .' class="f2p-viewer row'. $html_class .'">';
			$html[] = '<h3>Formulário: '. $parent->post_title .'</h3>';
			$html[] = '<div class="f2p-list col col-1of3"'. $style .'>';
			$html[] = '<ul>';
			
			$q = new WP_Query( array( 'post_type' => $this->cpt, 'posts_per_page' => '-1', 'post_parent' => $form_id, 'post_status' => 'any' ) );
			if( $q->have_posts() ){
				while( $q->have_posts() ){
					$q->the_post();
					$html[] = '<li><a href="#" data-f2p-id="'. get_the_ID() .'">'. get_field( 'nome' ) .' ('. get_field( 'mail' ) .') <span>'. get_the_date( 'd-m-Y H:i:s' ) .'</span></a></li>';
					$content = apply_filters( 'the_content', get_the_content( '' ) );
					$data[] = array( 'id' => get_the_ID(), 'body' => $content, 'fields' => get_post_custom() );
				}
				wp_reset_postdata();
			}
			
			$html[] = '</ul>';
			$html[] = '</div>';
			$html[] = '<div class="col col-2of3"><div class="f2p-content"'. $style .'></div></div>';
			$html[] = '</div>';
			$html[] = '<script>';
			$html[] = 'var $f2p_data = '. json_encode( array( 'count' => count( $data ), 'data' => $data ) ) .';';
			$html[] = '</script>';
			
			return implode( "\n", $html );
			
		}

	}
	
	$f2p = new Form_2_Post;
