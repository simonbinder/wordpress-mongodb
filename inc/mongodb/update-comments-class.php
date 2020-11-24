<?php

namespace NoSQL\Inc\Mongodb;

use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;
use const PurpleDsHub\Inc\Api\PURPLE_IN_ISSUES;

if ( ! class_exists( 'Update_Comments' ) ) {
	class Update_Comments {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'update-comments';

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
			add_action( 'deleted_comment', array( $this, 'delete_comment_from_db' ) );
			add_action( 'trashed_comment', array( $this, 'update_comment' ) );
			add_action( 'comment_post', array( $this, 'update_comment' ) );
			add_action( 'edit_comment', array( $this, 'update_comment' ) );
		}

		public function update_comment( $comment_id ) {
			$comments_connection = $this->connection->selectCollection( 'comments_' . get_current_blog_id() );
			$comment             = get_comment( $comment_id );
			$comments_connection->updateOne(
				array( 'comment_id' => intval( $comment->comment_ID ) ),
				array(
					'$set' => array(
						'comment_id'      => intval( $comment->comment_ID ),
						'author'          => intval( $comment->user_id ),
						'comment_date'    => $comment->comment_date,
						'comment_post_ID' => intval( $comment->comment_post_ID ),
						'text'            => get_comment_text( $comment->comment_ID ),
						'status'          => wp_get_comment_status( $comment_id ),
					),
				),
				array( 'upsert' => true )
			);
			$post_connection = $this->connection->posts;
			$update          = array( '$push' => array( 'comments' => intval( $comment->comment_ID ) ) );
			$result          = $post_connection->updateOne( array( 'postId' => intval( $comment->comment_post_ID ) ), $update );
		}

		public function delete_comment_from_db( $comment_id ) {
			$comments_connection = $this->connection->selectCollection( 'comments_' . get_current_blog_id() );
			$comment_connection->deleteOne(
				array( 'comment_id' => intval( $comment_id ) )
			);
			$mongo_posts = $this->connection->posts;
			$mongo_posts->updateMany(
				array(),
				array( '$pull' => array( 'comments' => $comment_id ) )
			);
		}
	}
}

