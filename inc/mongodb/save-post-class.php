<?php

namespace NoSQL\Inc\Mongodb;

use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;

if ( ! class_exists( 'Save_Post' ) ) {
	class Save_Post {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'save-post';

		const PURPLE_IN_ISSUES = 'purple_in_issues';

		/**
		 */
		private $connection;


		public function __construct() {
			if ( is_null( $this->connection ) ) {
				// connect to mongodb.
				$m = new \MongoDB\Client( DOCUMENTDB_URL );

				// select database by blog id.
				$db = $m->selectDatabase( 'wp' );

				$this->connection = $db;

				$update_comments = new Update_Comments( $this->connection );
				$update_comments->init_hooks();

				$update_categories = new Update_Taxonomies( $this->connection );
				$update_categories->init_hooks();

				$update_user = new Update_User( $this->connection );
				$update_user->init_hooks();

				$update_menu = new Update_Menu( $this->connection );
				$update_menu->init_hooks();

				$update_acf = new Update_Acf( $this->connection );
				$update_acf->init_hooks();
			}
		}

		/**
		 * @return mixed|void
		 */
		public function init_hooks() {
			add_action( 'save_post', array( $this, 'save_in_db' ), 10, 3 );
			add_action( 'delete_post', array( $this, 'delete_from_db' ) );
			/*          add_action( 'wp_loaded',  array( $this,'update_all_posts') );*/
			add_filter( 'wp_insert_post_data', array( $this, 'add_purple_id' ), 99, 2 );
		}

		public function add_purple_id( $data, $postarr ) {
			$blocks          = parse_blocks( stripslashes( $postarr['post_content'] ) );
			$blocks_filtered = array_filter( $blocks, array( $this, 'filter_blocks' ) );
			$matches         = null;
			foreach ( $blocks_filtered as $key => $block ) {
				if ( $block['attrs']['purpleId'] === null ) {
					$blocks_filtered = $this->add_content_attr( $block['innerHTML'], $blocks_filtered, $key, true );
				}
			}
			$data['post_content'] = addslashes( serialize_blocks( $blocks_filtered ) );
			return $data;
		}

		public function update_all_posts() {
			$args      = array(
				'post_type'   => 'purple_issue',
				'numberposts' => -1,
			);
			$all_posts = get_posts( $args );
			foreach ( $all_posts as $single_post ) {
				$single_post->post_title = $single_post->post_title . '';
				wp_update_post( $single_post );
			}
		}

		public function delete_from_db( int $postid ) {
			$mongo_posts = $this->connection->selectCollection( 'posts_' . get_current_blog_id() );
			$mongo_posts->deleteOne(
				array( 'postId' => $postid )
			);
			$mongo_posts->deleteMany(
				array( 'post_parent' => $postid )
			);
		}

		private function filter_blocks( $var ) {
			return $var['blockName'] !== null;
		}

		public function save_in_db( $post_id, \WP_Post $post, $update ) {
			if ( has_blocks( $post->post_content ) || $post->post_type === 'purple_issue' ) {
				$mongo_posts = null;
				if ( $post->post_type === 'revision' ) {
					$mongo_posts = $this->connection->selectCollection( 'revisions_' . get_current_blog_id() );
				} else {
					$mongo_posts = $this->connection->selectCollection( 'posts_' . get_current_blog_id() );
				}
				foreach ( $mongo_posts->listIndexes() as $index ) {
					debug( $index );
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

				$post_categories = wp_get_post_categories( $post_id );
				$blocks          = parse_blocks( $post->post_content );
				$blocks_filtered = array_filter( $blocks, array( $this, 'filter_blocks' ) );
				foreach ( $blocks_filtered as $key => $block ) {
					$blocks_filtered = $this->add_content_attr( $block['innerHTML'], $blocks_filtered, $key );
				}

				$issues = get_post_meta( $post_id, self::PURPLE_IN_ISSUES, true ) ?: array();
				$issues = array_map(
					function ( $issue_id ) {
						$post = get_post( $issue_id );

						return $post;
					},
					$issues
				);

				$advanced_custom_fields = array();
				if (
				in_array(
					'advanced-custom-fields/acf.php',
					apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
					true
				)
				) {
					foreach ( get_field_objects( $post_id ) as $field_object ) {
						array_push(
							$advanced_custom_fields,
							array(
								'fieldId' => $field_object['ID'],
								'value'   => $field_object['value'],
							)
						);
					}
				}

				$comments     = get_comments(
					array(
						'post_id' => $post_id,
					)
				);
				$comments_ids = array_column( $comments, 'comment_ID' );

				$articles = get_post_meta( $post_id, 'purple_issue_articles', true );
				$articles = array_map(
					function ( $article_id ) {
						$post                      = get_post( $article_id );
						$post->{'permalink'}       = get_permalink( $article_id );
						$post->{'author_name'}     = get_the_author_meta( 'display_name', $post->post_author );
						$post->{'article_options'} = get_post_meta( $article_id, 'purple_content_options', true );

						return $post;
					},
					$articles
				);

				$custom_fields = array();
				$post_meta     = get_post_meta( $post_id, '', true );
				foreach ( $post_meta as $meta_key => $meta_value ) {
					if ( self::starts_with( $meta_key, 'purple_custom_meta_' ) ) {
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
				$author_id = get_post_field( 'post_author', $post_id );
				$term_list = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'all' ) );
				$term_ids  = array();
				foreach ( $term_list as $term ) {
					array_push(
						$term_ids,
						$term->term_id
					);
				}
				$mongo_posts->updateOne(
					array( 'post_id' => $post_id ),
					array(
						'$set' => array(
							'post_id'                => $post_id,
							'post_content'           => array_values( $blocks_filtered ),
							'categories'             => $post_categories,
							'custom_fields'          => $custom_fields,
							'advanced_custom_fields' => $advanced_custom_fields,
							'author'                 => intval( $author_id ),
							'tags'                   => $term_ids,
							'post_title'             => $post->post_title,
							'post_status'            => $post->post_status,
							'post_parent'            => $post->post_parent,
							'comment_status'         => $post->comment_status,
							'thumbnail'              => get_the_post_thumbnail_url( $post_id ),
							'permalink'              => get_permalink( $post_id ),
							'post_name'              => $post->post_name,
							'post_modified'          => $post->post_modified,
							'post_modified_gmt'      => $post->post_modified_gmt,
							'guid'                   => $post->guid,
							'post_type'              => $post->post_type,
							'post_excerpt'           => $post->post_excerpt,
							'comment_count'          => $post->comment_count,
							'comments'               => array_map( 'intval', $comments_ids ),
							'purple_issue'           => $issues[0]->ID,
							'purple_issue_articles'  => array_column( $articles, 'ID' ),
							'ping_status'            => $post->ping_status,
							'post_password'          => $post->post_password,
							'to_ping'                => $post->to_ping,
							'pinged'                 => $post->pinged,
							'post_content_filtered'  => $post->post_content_filtered,
							'menu_order'             => $post->menu_order,
							'post_mime_type'         => $post->post_mime_type,
							'filter'                 => $post->filter,
						),
					),
					array( 'upsert' => true ),
				);
				$this->update_categories();
				$this->update_comments( $comments );
				$this->update_users();
				$this->update_tags();
			}
		}

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
		 * @param $inner_html
		 * @param array      $blocks_filtered
		 * @param $key
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

		public static function starts_with( $haystack, $needle ) {
			// search backwards starting from haystack length characters from the end.
			return '' === $needle || strrpos( $haystack, $needle, -strlen( $haystack ) ) !== false;
		}

		private function update_categories() {
			$categories          = get_categories();
			$category_connection = $this->connection->selectCollection( 'categories_' . get_current_blog_id() );
			foreach ( $categories as $category ) {
				$category_connection->updateOne(
					array( 'term_id' => $category->term_id ),
					array(
						'$set' => array(
							'term_id' => $category->term_id,
							'name'    => $category->name,
							'slug'    => $category->slug,
						),
					),
					array( 'upsert' => true )
				);
			}
		}

		/**
		 * @param $comments
		 */
		private function update_comments( $comments ) {
			$comments_connection = $this->connection->selectCollection( 'comments_' . get_current_blog_id() );
			foreach ( $comments as $comment ) {
				$comments_connection->updateOne(
					array( 'comment_id' => intval( $comment->comment_ID ) ),
					array(
						'$set' => array(
							'comment_id'      => intval( $comment->comment_ID ),
							'author'          => intval( $comment->user_id ),
							'comment_date'    => $comment->comment_date,
							'comment_post_ID' => intval( $comment->comment_post_ID ),
							'text'            => get_comment_text( $comment->comment_ID ),
							'status'          => wp_get_comment_status( $comment->comment_ID ),
						),
					),
					array( 'upsert' => true )
				);
			}
		}

		private function update_users() {
			$users_connection = $this->connection->selectCollection( 'users_' . get_current_blog_id() );
			$users            = get_users();
			foreach ( $users as $user ) {
				$insert_result = $users_connection->updateOne(
					array(
						'user_id' => $user->ID,
					),
					array(
						'$set' => array(
							'user_id'         => $user->ID,
							'login'           => $user->data->user_login,
							'display_name'    => $user->data->display_name,
							'email'           => $user->data->user_email,
							'roles'           => $user->roles,
							'caps'            => $user->caps,
							'user_registered' => $user->data->user_registered,
						),
					),
					array( 'upsert' => true )
				);
			}
		}

		private function update_tags() {
			$tags_connection = $this->connection->selectCollection( 'tags_' . get_current_blog_id() );
			$tags            = get_tags();
			foreach ( $tags as $tag ) {
				$insert_result = $tags_connection->updateOne(
					array(
						'term_id' => $tag->term_id,
					),
					array(
						'$set' => array(
							'term_id' => $tag->term_id,
							'name'    => $tag->name,
							'slug'    => $tag->slug,
						),
					),
					array( 'upsert' => true )
				);
			}
		}

	}
}
