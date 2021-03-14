<?php

namespace NoSQL\Inc\Mongodb;

use MongoDB\Database;
use PurpleDsHub\Inc\Interfaces\Hooks_Interface;
use \PurpleDsHub\Inc\Utilities\Torque_Urls;
use const PurpleDsHub\Inc\Api\PURPLE_IN_ISSUES;

if ( ! class_exists( 'Update_Menu' ) ) {
	class Update_Menu {

		/**
		 * Component's handle.
		 */
		const HANDLE = 'update-menu';

		/**
		 * Connection to db.
		 *
		 * @var Database $connection Connection to db.
		 */
		private $connection;

		/**
		 * Update_Menu constructor.
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
			add_action( 'wp_update_nav_menu', array( $this, 'save_menu' ) );
		}

		/**
		 * Save modified menu.
		 *
		 * @param int $menu_id id of current menu.
		 */
		public function save_menu( $menu_id ) {
			$menu            = wp_get_nav_menu_object( $menu_id );
			$items           = wp_get_nav_menu_items( $menu_id );
			$menu_connection = $this->connection->selectCollection( 'menus' );
			$menu_connection->updateOne(
				array( 'source_menu_id' => get_current_blog_id() . '_' . $menu->term_id ),
				array(
					'$set' => array(
						'menu_id'        => $menu->term_id,
						'source_menu_id' => get_current_blog_id() . '_' . $menu->term_id,
						'name'           => $menu->name,
						'slug'           => $menu->slug,
						'menu_items'     => array_column( $items, 'ID' ),
					),
				),
				array( 'upsert' => true )
			);
			$menu_items_connection = $this->connection->selectCollection( 'menuItems' );
			foreach ( $items as $item ) {
				$item['source_menu_items_id'] = get_current_blog_id() . '_' . $item->ID;
				$menu_items_connection->updateOne(
					array( 'source_menu_items_id' => get_current_blog_id() . '_' . $item->ID ),
					array(
						'$set' => $item,
					),
					array( 'upsert' => true )
				);
			}
		}
	}
}

