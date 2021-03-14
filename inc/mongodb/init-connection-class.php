<?php

namespace NoSQL\Inc\Mongodb;

use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use PurpleDsHub\Inc\Utilities\General_Utilities;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;

if ( ! class_exists( 'Init_Connection' ) ) {
	class Init_Connection {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'init-connection';

		/**
		 */
		private $connection;

		public function __construct() {
			if ( is_null( $this->connection ) ) {
				$ssl_dir  = '/etc/ssl/certs';
				$ssl_file = 'rds-combined-ca-bundle.pem';

				// connect to mongodb.
				$m = new \MongoDB\Client(
					DOCUMENTDB_URL,
					DOCUMENTDB_USERNAME ? array(
						'username'  => rawurlencode( DOCUMENTDB_USERNAME ),
						'password'  => rawurlencode( DOCUMENTDB_PASSWORD ),
						'ssl'       => true,
						'tlsCAFile' => $ssl_dir . '/' . $ssl_file,
					) : array()
				);

				// select database by blog id.
				$db               = $m->selectDatabase( 'wp_' . DOCUMENTDB_STAGE );
				$this->connection = $db;

				$update_post = new Update_Post( $this->connection );
				$update_post->init_hooks();

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
	}
}

