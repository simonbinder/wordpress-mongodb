<?php

namespace NoSQL\Inc\Mongodb;

use MongoDB\Database;
use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use PurpleDsHub\Inc\Utilities\General_Utilities;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;

if ( ! class_exists( 'Update_Post' ) ) {
	class Update_Post {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'update-post';

		const PURPLE_IN_ISSUES = 'purple_in_issues';

		/**
		 * Conection to db.
		 *
		 * @var Database $connection connection to database.
		 */
		private $connection;

		public $rest_service;
		/**
		 * Update_Post constructor.
		 *
		 * @param Database $connection conection to database.
		 */
		public function __construct( $connection ) {
			$this->connection = $connection;
		}

		/**
		 * Initialize all hooks.
		 *
		 * @return mixed|void
		 */
		public function init_hooks() {
			add_action( 'save_post', array( $this, 'save_in_db' ), 10, 3 );
			add_action( 'delete_post', array( $this, 'delete_from_db' ) );
			add_filter( 'wp_insert_post_data', array( $this, 'add_purple_id' ), 99, 2 );

			add_action( 'updated_post_meta', array( $this, 'update_post_after_meta' ), 10, 2 );
		}

		public function update_post_after_meta( $meta_id, $post_id ) {
			$post = get_post( $post_id );
			$this->save_in_db( $post_id, $post, false );
		}

		/**
		 * This method adds a unique ID to all blocks except for the post type rss_feed.
		 *
		 * @param array $data array of slashed, sanitized, and processed post data.
		 * @param array $postarr array array of sanitized (and slashed) but otherwise unmodified post data.
		 * @return mixed
		 */
		public function add_purple_id( $data, $postarr ) {
			$post   = get_post( $postarr['ID'] );
			$is_rss = $post->post_type == 'rss_feed' || $data['post_type'] == 'rss_feed';
			if ( ! $is_rss ) {
				$blocks          = parse_blocks( stripslashes( $postarr['post_content'] ) );
				$blocks_filtered = array_filter( $blocks, array( $this, 'filter_blocks' ) );
				foreach ( $blocks_filtered as $key => $block ) {
					if ( $block['attrs']['purpleId'] === null ) {
						$blocks_filtered = $this->add_content_attr( $block['innerHTML'], $blocks_filtered, $key, true );
					}
				}
				$data['post_content'] = addslashes( serialize_blocks( $blocks_filtered ) );
			}
			return $data;
		}

		/**
		 * Update all posts in the db.
		 */
		public function save_all_posts() {
			$args      = array(
				'numberposts' => -1,
				'post_status' => 'any',
				'post_type'   => get_post_types( '', 'names' ),
			);
			$all_posts = get_posts( $args );
			foreach ( $all_posts as $single_post ) {
				$this->save_in_db( $single_post->ID, $single_post, null );
			}
		}

		/**
		 * Delete post from db.
		 *
		 * @param int $postid id of the post that gets deleted.
		 */
		public function delete_from_db( int $postid ) {
			$post        = get_post( $postid );
			$mongo_posts = $this->connection->selectCollection( 'posts' );
			$mongo_posts->deleteOne(
				array( 'source_post_id' => get_current_blog_id() . '_' . $postid )
			);
			$result = $mongo_posts->updateOne(
				array( 'source_post_id' => get_current_blog_id() . '_' . $postid ),
				array(
					'$set' => array(
						'source_post_id' => get_current_blog_id() . '_' . $postid,
						'deleted'        => true,
						'post_modified'  => $post->post_modified,
					),
				),
				array( 'upsert' => true )
			);
			$mongo_posts->deleteMany(
				array( 'post_parent' => get_current_blog_id() . '_' . $postid )
			);

			$this->notify_acm();
		}

		/**
		 * Filter blocks that are empty.
		 *
		 * @param array $block block that gets filtered.
		 * @return bool
		 */
		private function filter_blocks( $block ) {
			return $block['blockName'] !== null;
		}

		/**
		 * Save post in db.
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post Post object.
		 * @param bool     $update Whether this is an existing post being updated.
		 */
		public function save_in_db( int $post_id, \WP_Post $post, bool $update ) {
			$is_rss = $post->post_type == 'rss_feed';
			if ( ! $is_rss ) {
				$mongo_posts = null;
				if ( $post->post_type === 'revision' ) {
					$mongo_posts = $this->connection->selectCollection( 'revisions' );
				} else {
					$mongo_posts = $this->connection->selectCollection( 'posts' );
				}
				/*
				$mongo_posts->createIndex(
					array(
						'post_content.attrs.content' => 'text',
						'post_title'                 => 'text',
					)
				);*/
				$mongo_posts->createIndex( array( 'post_id' => 1 ) );
				$mongo_posts->createIndex( array( 'post_content.blockName' => 1 ) );

				$post_categories = $this->retrieve_categories( $post_id );
				$blocks          = parse_blocks( $post->post_content );
				$blocks_filtered = array_filter( $blocks, array( $this, 'filter_blocks' ) );
				foreach ( $blocks_filtered as $key => $block ) {
					$blocks_filtered = $this->add_content_attr( $block['innerHTML'], $blocks_filtered, $key );
				}

				$issues = get_post_meta( $post_id, self::PURPLE_IN_ISSUES, true ) ?: array();
				$issues = array_map(
					function ( $issue_id ) {
						return get_post( $issue_id );
					},
					$issues
				);

				$comments     = get_comments(
					array(
						'post_id' => $post_id,
					)
				);
				$comments_ids = array_column( $comments, 'comment_ID' );
				$comments_ids = array_map(
					function ( $comment_id ) {
						return get_current_blog_id() . '_' . $comment_id;
					},
					$comments_ids
				);

				$advanced_custom_fields = $this->retrieve_acf_fields( $post_id );
				$articles               = $this->retrieve_articles( $post_id );
				$post_content_html      = $this->retrieve_post_content_html( $post_id, $post );
				$custom_fields          = $this->retrieve_custom_fields( $post_id );
				$author_id              = get_post_field( 'post_author', $post_id );
				$term_ids               = $this->retrieve_term_ids( $post );

				$post_terms_filtered = $this->filter_post_terms( $post );
				$taxonomies          = $this->retrieve_taxonomies( $post_terms_filtered );

				$purple_issue_articles = array_map(
					function ( $article_id ) {
						return get_current_blog_id() . '_' . $article_id;
					},
					array_column( $articles, 'ID' )
				);

				$target            = get_post_meta( $post_id, 'purple_linked_issue', true );
				$linked_issue      = get_post_meta( $issues[0]->ID, 'purple_linked_issue', true );
				$upload_properties = get_post_meta( $post_id, 'purple_upload_properties', true );

				$post_array                   = (array) $post;
				$post_array['featured_media'] = get_post_thumbnail_id( $post );
				$current_blog_details         = get_blog_details( array( 'blog_id' => get_current_blog_id() ) );

				$mongo_posts->updateOne(
					array( 'source_post_id' => get_current_blog_id() . '_' . $post_id ),
					array(
						'$set' => array(
							'post_id'                 => $post_id,
							'source_post_id'          => get_current_blog_id() . '_' . $post_id,
							'post_content'            => array_values( $blocks_filtered ),
							'categories'              => $post_categories,
							'custom_fields'           => $custom_fields,
							'advanced_custom_fields'  => $advanced_custom_fields,
							'author'                  => get_current_blog_id() . '_' . intval( $author_id ),
							'tags'                    => $term_ids,
							'post_title'              => $post->post_title,
							'post_status'             => $post->post_status,
							'post_parent'             => $post->post_parent,
							'post_excerpt'            => $post->post_excerpt,
							'comment_status'          => $post->comment_status,
							'post_image_url'          => get_the_post_thumbnail_url( $post_id ),
							'permalink'               => get_permalink( $post_id ),
							'post_name'               => $post->post_name,
							'post_modified'           => $post->post_modified,
							'post_modified_gmt'       => $post->post_modified_gmt,
							'guid'                    => $post->guid,
							'post_type'               => $post->post_type,
							'post_excerpt'            => $post->post_excerpt,
							'comment_count'           => $post->comment_count,
							'comments'                => array_map( 'intval', $comments_ids ),
							'purple_issue_id'         => $issues[0]->ID ? get_current_blog_id() . '_' . $issues[0]->ID : null,
							'purple_manager_issue_id' => $linked_issue['issue']['id'],
							'target'                  => array(
								'app_id'         => $target['app']['id'],
								'publication_id' => $target['publication']['id'],
								'issue_id'       => $target['issue']['id'],
							),
							'purple_issue_title'      => $issues[0]->post_title,
							'purple_issue_articles'   => $purple_issue_articles,
							'ping_status'             => $post->ping_status,
							'post_password'           => $post->post_password,
							'to_ping'                 => $post->to_ping,
							'pinged'                  => $post->pinged,
							'post_content_filtered'   => $post->post_content_filtered,
							'menu_order'              => $post->menu_order,
							'post_mime_type'          => $post->post_mime_type,
							'filter'                  => $post->filter,
							'taxonomies'              => $taxonomies,
							'source_id'               => get_current_blog_id(),
							'source_title'            => $current_blog_details->blogname,
							'source_href'             => $current_blog_details->siteurl,
							'featured_image'          => get_the_post_thumbnail_url( $post_id ),
							'post_content_html'       => $post_content_html,
							'post_published'          => get_the_date( 'Y-m-d h:m:s', $post ),
							'access_level'            => $upload_properties['accessLevel'],
						),
					),
					array( 'upsert' => true )
				);
				$this->update_comments( $comments );
				$this->update_users();
				$this->update_taxonomies( $post );

				$this->notify_acm();
			}
		}

		private function notify_acm() {
			$last_notified_date = get_option( 'sprylab_purple_nosql_last_notified' );
			if ( abs( time() - $last_notified_date ) > 60 ) {
				$url     = get_option( Nosql_Settings::PURPLE_NOSQL_ACM_URL );
				$api_key = get_option( Nosql_Settings::PURPLE_NOSQL_ACM_API_KEY );
				$data    = array(
					'username' => get_option( Nosql_Settings::PURPLE_NOSQL_ACM_USERNAME ),
					/*
				  'from_date' => date( 'Y/m/d, H:i:s', $last_notified_date ),
						'to_date'   => date( 'Y/m/d, H:i:s' ),*/
				);
				wp_remote_post(
					$url . '/core/index/articles',
					array(
						'method'      => 'POST',
						'timeout'     => 45,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking'    => true,
						'headers'     => array(
							'X-API-Key'    => $api_key,
							'Content-Type' => 'application/json; charset=utf-8',
						),
						'body'        => wp_json_encode( $data ),
						'cookies'     => array(),
					)
				);
				update_option( 'sprylab_purple_nosql_last_notified', time() );
			}
		}

		/**
		 * Retrieve path to app package.
		 *
		 * @param int $post_id Post ID.
		 * @return string
		 */
		private function get_app_package_path( int $post_id ) {
			global $blog_id;
			$path = '';

			if ( is_multisite() ) {
				$path = ABSPATH . 'wp-content/uploads/sites/' . $blog_id . '/importzip/' . $post_id . '/' . $post_id . '.zip';
			} else {
				$path = ABSPATH . 'wp-content/uploads/importzip/' . $post_id . '/' . $post_id . '.zip';
			}
			$zip_file_path = General_Utilities::sprylab_purple_get_user_zip_path() . '/' . get_the_ID();
			if ( ! file_exists( $zip_file_path ) ) {
				mkdir( $zip_file_path, 0777, true );
			}

			$zip = new \ZipArchive();
			if ( $zip->open( $path ) === true ) {
				$zip->extractTo( $zip_file_path );
				$zip->close();
				$files = glob( $zip_file_path . '/content/*.html' );
				$file  = basename( $files[0] );
				return get_site_url() . '/wp-content/uploads/temp/' . General_Utilities::sprylab_purple_prefix_user() . '/' . get_the_ID() . '/content/' . $file;
			} else {
				return '';
			}
		}

		/**
		 * Generate unique ID.
		 *
		 * @return string
		 */
		private function guidv4() {
			if ( function_exists( 'com_create_guid' ) === true ) {
				return trim( com_create_guid(), '{}' );
			}

			$data    = openssl_random_pseudo_bytes( 16 );
			$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
			$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10
			return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
		}

		/**
		 * Add purpleID and content attribute to blocks
		 *
		 * @param string $inner_html HTML of WP Block.
		 * @param array  $blocks_filtered blocks of the post.
		 * @param int    $key current block index.
		 * @return array
		 */
		private function add_content_attr( $inner_html, array $blocks_filtered, $key, $update_id = false ): array {
			$text                                        = wp_strip_all_tags( $inner_html );
			$blocks_filtered[ $key ]['attrs']['content'] = $text;
			$purple_id                                   = $blocks_filtered[ $key ]['attrs']['purpleId'];
			if ( $purple_id === 'test' || $purple_id === null && $update_id ) {
				$blocks_filtered[ $key ]['attrs']['purpleId'] = $this->guidv4();
			}
			foreach ( $blocks_filtered[ $key ]['innerBlocks'] as $key2 => $block ) {
				if ( $blocks_filtered[ $key ]['innerBlocks'] && count( $blocks_filtered[ $key ]['innerBlocks'] ) > 0 ) {
					$blocks_filtered[ $key ]['innerBlocks'] = $this->add_content_attr( $block['innerHTML'], $blocks_filtered[ $key ]['innerBlocks'], $key2 );
				}
			}
			return $blocks_filtered;
		}

		/**
		 * Update all taxonomies related to the post.
		 *
		 * @param \WP_Post $post current post.
		 */
		private function update_taxonomies( $post ) {
			$taxonomies          = get_object_taxonomies( $post );
			$taxonomy_connection = $this->connection->selectCollection( 'taxonomies' );
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post, $taxonomy );
				foreach ( $terms as $term ) {
					$term->source_term_id = get_current_blog_id() . '_' . $term->term_id;
					$term->source_parent  = $term->parent !== 0 ? get_current_blog_id() . '_' . $term->parent : 0;
					$term->source_id      = get_current_blog_id();
					if ( $term->taxonomy !== 'author' ) {
						$taxonomy_connection->updateOne(
							array( 'source_term_id' => get_current_blog_id() . '_' . $term->term_id ),
							array(
								'$set' => $term,
							),
							array( 'upsert' => true )
						);
					}
				}
			}
		}

		/**
		 * Update all comments related to the post.
		 *
		 * @param array $comments all comments belonging to the post.
		 */
		private function update_comments( $comments ) {
			$comments_connection = $this->connection->selectCollection( 'comments' );
			foreach ( $comments as $comment ) {
				$comments_connection->updateOne(
					array( 'source_comment_id' => get_current_blog_id() . '_' . intval( $comment->comment_ID ) ),
					array(
						'$set' => array(
							'comment_id'        => intval( $comment->comment_ID ),
							'source_comment_id' => get_current_blog_id() . '_' . intval( $comment->comment_ID ),
							'author'            => intval( $comment->user_id ),
							'comment_date'      => $comment->comment_date,
							'comment_post_ID'   => intval( $comment->comment_post_ID ),
							'text'              => get_comment_text( $comment->comment_ID ),
							'status'            => wp_get_comment_status( $comment->comment_ID ),
						),
					),
					array( 'upsert' => true )
				);
			}
		}

		/**
		 * Update all users related to the post.
		 */
		private function update_users() {
			$users_connection = $this->connection->selectCollection( 'users' );
			$users            = get_users();
			foreach ( $users as $user ) {
				$users_connection->updateOne(
					array(
						'source_user_id' => get_current_blog_id() . '_' . $user->ID,
					),
					array(
						'$set' => array(
							'user_id'         => $user->ID,
							'source_user_id'  => get_current_blog_id() . '_' . $user->ID,
							'login'           => $user->data->user_login,
							'display_name'    => $user->data->display_name,
							'email'           => $user->data->user_email,
							'roles'           => $user->roles,
							'caps'            => $user->caps,
							'password'        => $user->user_pass,
							'user_registered' => $user->data->user_registered,
						),
					),
					array( 'upsert' => true )
				);
			}
		}

		/**
		 * Retrieve all categories belonging to the post.
		 *
		 * @param int $post_id current post id.
		 */
		private function retrieve_categories( int $post_id ) {
			$post_categories = wp_get_post_categories( $post_id );
			return array_map(
				function ( $val ) {
					return get_current_blog_id() . '_' . $val;
				},
				$post_categories
			);
		}

		/**
		 * Retrieve all acf fields belonging to the post.
		 *
		 * @param int $post_id current post id.
		 */
		private function retrieve_acf_fields( int $post_id ) {
			$advanced_custom_fields = array();
			if (
				in_array(
					'advanced-custom-fields/acf.php',
					apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
					true
				) ||
				in_array(
					'advanced-custom-fields-pro/acf.php',
					apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
					true
				)
			) {
				foreach ( get_field_objects( $post_id ) as $field_object ) {
					array_push(
						$advanced_custom_fields,
						array(
							'fieldId' => get_current_blog_id() . '_' . $field_object['ID'],
							'value'   => $field_object['value'],
						)
					);
				}
			}
			return $advanced_custom_fields;
		}

		/**
		 * Get all articles belonging to the issue.
		 *
		 * @param int $post_id current post id.
		 */
		private function retrieve_articles( int $post_id ) {
			$articles = get_post_meta( $post_id, 'purple_issue_articles', true );
			return array_map(
				function ( $article_id ) {
					$post                      = get_post( $article_id );
					$post->{'permalink'}       = get_permalink( $article_id );
					$post->{'author_name'}     = get_the_author_meta( 'display_name', $post->post_author );
					$post->{'article_options'} = get_post_meta( $article_id, 'purple_content_options', true );

					return $post;
				},
				$articles
			);
		}

		/**
		 * Read post content from html file and generate html string from it.
		 *
		 * @param int      $post_id current post id.
		 * @param \WP_Post $post current post.
		 */
		private function retrieve_post_content_html( int $post_id, \WP_Post $post ) {
			$post_content_html = '';
			if ( get_post_meta( $post_id, 'sprylab_purple_post_app_package' ) ) {
				$path = $this->get_app_package_path( $post_id );
				$d    = new \DOMDocument();
				$mock = new \DOMDocument();
				$d->loadHTML( file_get_contents( $path ) );
				$body = $d->getElementsByTagName( 'body' )->item( 0 );
				foreach ( $body->childNodes as $child ) {
					if ( $child->tagName !== 'script' ) {
						$mock->appendChild( $mock->importNode( $child, true ) );
					}
				}

				$post_content_html = $mock->saveHTML();
			} else {
				$post_content_html = apply_filters( 'the_content', $post->post_content );
			}
			return $post_content_html;
		}

		/**
		 * Get all custom fields related to current post
		 *
		 * @param int $post_id current post id.
		 */
		private function retrieve_custom_fields( int $post_id ) {
			$custom_fields = array();
			$post_meta     = get_post_meta( $post_id, '', true );

			foreach ( $post_meta as $meta_key => $meta_value ) {
				if ( Torque_Urls::starts_with( $meta_key, 'purple_custom_meta_' ) ) {
					$stripped_key = str_replace( 'purple_custom_meta_', '', $meta_key );
					array_push(
						$custom_fields,
						array(
							'field' => $stripped_key,
							'value' => $meta_value[0],
						)
					);
				}
			}
			return $custom_fields;
		}

		/**
		 * Filter post terms for the ones not defined in other fields.
		 *
		 * @param \WP_Post $post current post.
		 */
		private function filter_post_terms( \WP_Post $post ) {
			$taxonomies = get_object_taxonomies( $post );
			$post_terms = array();
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post, $taxonomy );
				foreach ( $terms as $term ) {
					array_push( $post_terms, $term );
				}
			}
			return array_filter(
				$post_terms,
				function ( $var ) {
					return ( $var->taxonomy !== 'author' && $var->taxonomy !== 'post_tag' && $var->taxonomy !== 'category' );
				}
			);
		}

		/**
		 * Get all taxonomies related to the post.
		 *
		 * @param array $post_terms_filtered filtered post terms.
		 */
		private function retrieve_taxonomies( array $post_terms_filtered ) {
			return array_map(
				function ( $term_id ) {
					return get_current_blog_id() . '_' . $term_id;
				},
				array_column( $post_terms_filtered, 'term_id' )
			);
		}

		/**
		 * Get all term ids.
		 *
		 * @param \WP_Post $post current post.
		 */
		private function retrieve_term_ids( \WP_Post $post ) {
			$term_ids  = array();
			$term_list = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'all' ) );
			foreach ( $term_list as $term ) {
				array_push(
					$term_ids,
					get_current_blog_id() . '_' . $term->term_id
				);
			}
			return $term_ids;
		}
	}
}

