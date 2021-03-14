<?php

namespace NoSQL\Inc\Mongodb;

use MongoDB\Database;
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
		 * Connection to db.
		 *
		 * @var Database $connection Connection to db.
		 */
		private $connection;

		/**
		 * Update_Taxonomies constructor.
		 *
		 * @param Database $connection connection do db.
		 */
		public function __construct( $connection ) {
			$this->connection = $connection;
		}

		/**
		 * Initialize all hooks.
		 */
		public function init_hooks() {
			add_action( 'edited_term', array( $this, 'update_taxonomy' ), 10, 3 );
			add_action( 'create_term', array( $this, 'update_taxonomy' ), 10, 3 );
			add_action( 'delete_term', array( $this, 'delete_term_from_db' ), 10, 3 );
		}

		/**
		 * Update modified taxonomy.
		 *
		 * @param int    $term_id Term ID.
		 * @param int    $tt_id Term taxonomy ID.
		 * @param string $taxonomy Taxonomy slug.
		 */
		public function update_taxonomy( $term_id, $tt_id, $taxonomy ) {
			$taxonomy_connection = $this->connection->selectCollection( 'taxonomies' );
			if ( $taxonomy_connection ) {
				$term                 = get_term( $term_id, $taxonomy );
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

		/**
		 * Delete term from db.
		 *
		 * @param int    $term_id Term ID.
		 * @param int    $tt_id Term taxonomy ID.
		 * @param string $taxonomy Taxonomy slug.
		 */
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

