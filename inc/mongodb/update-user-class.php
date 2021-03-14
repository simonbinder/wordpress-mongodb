<?php

namespace NoSQL\Inc\Mongodb;

use MongoDB\Database;
use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;
use const PurpleDsHub\Inc\Api\PURPLE_IN_ISSUES;

if ( ! class_exists( 'Update_User' ) ) {
	class Update_User {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'update-user';

		/**
		 * Connection to db.
		 *
		 * @var Database $connection Connection to db.
		 */
		private $connection;

		/**
		 * Update_User constructor.
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
			add_action( 'profile_update', array( $this, 'update_user' ) );
		}

		/**
		 * Update user in db.
		 *
		 * @param int $user_id current user id.
		 */
		public function update_user( $user_id ) {
			$user_connection = $this->connection->selectCollection( 'users_' . get_current_blog_id() );
			$user            = get_user_by( 'id', $user_id );
			$user_connection->updateOne(
				array( 'source_user_id' => get_current_blog_id() . '_' . $user->ID ),
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
}

