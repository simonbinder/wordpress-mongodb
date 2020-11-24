<?php

namespace NoSQL\Inc\Mongodb;

use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;
use const PurpleDsHub\Inc\Api\PURPLE_IN_ISSUES;

if ( ! class_exists( 'Update_Taxonomies' ) ) {
	class Update_Taxonomies {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'update-categories';

		/**
		 */
		private $connection;

		public function __construct($connection) {
			$this->connection = $connection;
		}

		/**
		 * @return mixed|void
		 */
		public function init_hooks() {
			add_action( 'edited_term', array( $this, 'update_category' ), 10, 3 );
			add_action( 'delete_term', array( $this, 'delete_term_from_db' ), 10, 3 );
		}

		public function update_category( $term_id, $tt_id, $taxonomy ) {
			$collection_connection = null;
			if ( $taxonomy === 'category' ) {
				$collection_connection = $this->connection->selectCollection( 'categories_' . get_current_blog_id() );
			} elseif ( $taxonomy === 'post_tag' ) {
				$collection_connection = $this->connection->selectCollection( 'tags_' . get_current_blog_id() );
			}
			if ( $collection_connection ) {
				$term = get_term( $term_id, $taxonomy );
				$collection_connection->updateOne(
					array( 'term_id' => $term->term_id ),
					array(
						'$set' => array(
							'term_id' => $term->term_id,
							'name'    => $term->name,
							'slug'    => $term->slug,
						),
					),
					array( 'upsert' => true )
				);
			}
		}

		public function delete_term_from_db( $term_id, $tt_id, $taxonomy ) {
			$collection_connection = null;
			if ( $taxonomy === 'category' ) {
				$collection_connection = $this->connection->selectCollection( 'categories_' . get_current_blog_id() );
			} elseif ( $taxonomy === 'post_tag' ) {
				$collection_connection = $this->connection->selectCollection( 'tags_' . get_current_blog_id() );
			}
			if ( $collection_connection ) {
				$collection_connection->deleteOne(
					array( 'term_id' => $term_id )
				);
			}
			$mongo_posts = $this->connection->posts;
			if ( $taxonomy === 'category' ) {
				$mongo_posts->updateMany(
					array(),
					array( '$pull' => array( 'categories' => $term_id ) )
				);
			} elseif ( $taxonomy === 'post_tag' ) {
				$mongo_posts->updateMany(
					array(),
					array( '$pull' => array( 'tags' => $term_id ) )
				);
			}
		}
	}
}

