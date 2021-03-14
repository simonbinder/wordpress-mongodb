<?php

namespace NoSQL\Inc\Mongodb;

use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;
use const PurpleDsHub\Inc\Api\PURPLE_IN_ISSUES;
use MongoDB\Database;

if ( ! class_exists( 'Update_Acf' ) ) {
	class Update_Acf {


		/**
		 * Component's handle.
		 */
		const HANDLE = 'update-acf';

		/**
		 * Connection to db.
		 *
		 * @var Database $connection Connection to db.
		 */
		private $connection;

		/**
		 * Update_Acf constructor.
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
			add_action( 'acf/update_field_group', array( $this, 'update_acf' ) );
		}

		/**
		 * Update "advanced custom fields" field group
		 *
		 * @param array $field_group updated field group.
		 */
		public function update_acf( $field_group ) {
			$fields                            = acf_get_fields_by_id( $field_group['ID'] );
			$field_group['fields']             = $fields;
			$advanced_custom_fields_connection = $this->connection->selectCollection( 'advanced_custom_fields' );
			$field_group['source_acf_id']      = get_current_blog_id() . '_' . $field_group['ID'];
			$advanced_custom_fields_connection->updateOne(
				array( 'source_acf_id' => get_current_blog_id() . '_' . $field_group['ID'] ),
				array(
					'$set' => $field_group,
				),
				array( 'upsert' => true )
			);
		}

	}
}

