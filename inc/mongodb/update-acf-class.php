<?php

namespace NoSQL\Inc\Mongodb;

use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;
use const PurpleDsHub\Inc\Api\PURPLE_IN_ISSUES;

if ( ! class_exists( 'Update_Acf' ) ) {
	class Update_Acf {


		/**
		 * Component's handle.
		 */
		const HANDLE = 'update-acf';

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
			add_action( 'acf/update_field_group', array( $this, 'update_acf' ) );
			/*
			add_action( 'trashed_comment', array( $this, 'update_comment' ) );
			add_action( 'comment_post', array( $this, 'update_comment' ) );
			add_action( 'edit_comment', array( $this, 'update_comment' ) );*/
		}

		public function update_acf( $field_group ) {
			$fields = acf_get_fields_by_id($field_group['ID']);
			$field_group['fields'] = $fields;
			$advanced_custom_fields_connection = $this->connection->selectCollection( 'advanced_custom_fields_' . get_current_blog_id() );
			$advanced_custom_fields_connection->updateOne(
				array( 'ID' => $field_group['ID'] ),
				array(
					'$set' => $field_group,
				),
				array( 'upsert' => true )
			);
		}

	}
}

