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

		public function __construct( $connection ) {
			$this->connection = $connection;
		}

		/**
		 * @return mixed|void
		 */
		public function init_hooks() {
			add_action( 'edited_term', array( $this, 'update_taxonomy' ), 10, 3 );
			add_action( 'create_term', array( $this, 'update_taxonomy' ), 10, 3 );
			add_action( 'delete_term', array( $this, 'delete_term_from_db' ), 10, 3 );
		}

		public function update_taxonomy( $term_id, $tt_id, $taxonomy ) {
			$taxonomy_connection = $this->connection->selectCollection( 'taxonomies' );
			if ( $taxonomy_connection ) {
				$term                     = get_term( $term_id, $taxonomy );
				$term->source_term_id = get_current_blog_id() . '_' . $term->term_id;
				$term->source_parent  = $term->parent !== 0 ? get_current_blog_id() . '_' . $term->parent : 0;
				$term->source_id      = get_current_blog_id();
				$taxonomy_connection->updateOne(
					array( 'source_term_id' => get_current_blog_id() . '_' . $term->term_id ),
					array(
						'$set' => $term,
					),
					array( 'upsert' => true )
				);
			}
		}

		public function delete_term_from_db( $term_id, $tt_id, $taxonomy ) {
			$taxonomy_connection = $this->connection->selectCollection( 'taxonomies' );
			$taxonomy_connection->deleteOne(
				array( 'source_term_id' => get_current_blog_id() . '_' . $term_id )
			);
			$mongo_posts = $this->connection->posts;
			if ( $taxonomy === 'category' ) {
				$mongo_posts->updateMany(
					array(),
					array( '$pull' => array( 'categories' => get_current_blog_id() . '_' . $term_id ) )
				);
			} elseif ( $taxonomy === 'post_tag' ) {
				$mongo_posts->updateMany(
					array(),
					array( '$pull' => array( 'post_tag' => get_current_blog_id() . '_' . $term_id ) )
				);
			}
		}
	}
}

