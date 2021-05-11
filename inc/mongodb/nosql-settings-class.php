<?php

namespace NoSQL\Inc\Mongodb;

use function DI\get;

if ( ! class_exists( 'Nosql_Settings' ) ) {

	/**
	 * Class Nosql_Settings
	 *
	 * @package PurpleDsHub\Inc\Settings
	 */
	class Nosql_Settings {

		/**
		 * Component's handle.
		 */
		const HANDLE                             = 'mongodb-settings';
		const PURPLE_NOSQL_SETTINGS_PAGE         = 'purple_mongodb_settings_page';
		const PURPLE_NOSQL_BASE_SETTINGS_SECTION = 'purple_base_settings_section';
		const PURPLE_NOSQL_DATABASE_NAME         = 'purple_nosql_database_name';
		const PURPLE_NOSQL_SETTINGS_GROUP        = 'purple_nosql_settings_group';
		const PURPLE_NOSQL_ACM_URL               = 'purple_nosql_acm_url';
		const PURPLE_NOSQL_ACM_USERNAME          = 'purple_nosql_acm_username';
		const PURPLE_NOSQL_ACM_API_KEY           = 'purple_nosql_acm_api_key';

		/**
		 * Initialize all used hooks
		 */
		public function init_hooks() {
			add_action( 'admin_menu', array( $this, self::PURPLE_NOSQL_SETTINGS_PAGE ) );
			add_action( 'admin_init', array( $this, self::PURPLE_NOSQL_BASE_SETTINGS_SECTION ) );
			add_action( 'admin_footer', array( $this, 'sync_db_script' ), 20 );
			add_action( 'wp_ajax_sync_db', array( $this, 'sync_db' ) );
		}

		/**
		 * Updates the whitelisted Gutenberg Blocks
		 **/
		public function sync_db() {
			$args      = array(
				'numberposts' => -1,
				'post_status' => 'any',
				'post_type'   => array( 'post', 'purple_issue' ),
			);
			$all_posts = get_posts( $args );

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

			$db_name = get_option( self::PURPLE_NOSQL_DATABASE_NAME );

			// select database by blog id.
			$db         = $m->selectDatabase( 'wp_' . $db_name );
			$connection = $db;

			$update_post = new Update_Post( $connection );

			foreach ( $all_posts as $single_post ) {
				$update_post->save_in_db( $single_post->ID, $single_post, false );
			}
		}

		public function sync_db_script() {
			?>
			<script>
				jQuery('#syncDb-button').click(function (i) {
					jQuery('#sync-message').show();
					jQuery.ajax({
						method: 'POST',
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						data: {
							'action': 'sync_db',
						},
						beforeSend: function (xhr) {
							xhr.overrideMimeType('text/plain; charset=utf-8');
						}
					});
				})
			</script>
			<?php
		}

		/**
		 * Button to refresh block whitelist
		 */
		public function purple_print_sync_db_button() {
			?>
				<input type="button" name="submit" class="button-secondary" id="syncDb-button"
					   value="<?php esc_attr_e( 'Synchronize database', 'purpledshub' ); ?>"/>

				<p class="description"><?php esc_html_e( 'Synchronizes all data with the DocumentDB database.', 'purpledshub' ); ?></p>
				<p class="description" id="sync-message" style="display: none"><?php esc_html_e( 'The data is being synchronized in the background.', 'purpledshub' ); ?></p>
			<?php
		}

		/**
		 * Adds the Purple Admin Setting as a submenu page
		 */
		public function purple_mongodb_settings_page() {
			add_submenu_page(
				'options-general.php',
				'Purple DS HUB NoSQL',
				'Purple DS HUB NoSQL',
				'activate_plugins',
				'purple-nosql-settings-page',
				array( $this, 'purple_print_settings_page' )
			);
		}

		/**
		 * Creates the 'Base Settings' section
		 */
		public function purple_base_settings_section() {
			add_settings_section(
				self::PURPLE_NOSQL_BASE_SETTINGS_SECTION,
				'Base Settings',
				array( $this, 'purple_print_base_settings_section' ),
				self::PURPLE_NOSQL_SETTINGS_PAGE
			);

			add_settings_field(
				self::PURPLE_NOSQL_DATABASE_NAME,
				'Database name',
				array( $this, 'purple_print_nosql_database_name' ),
				self::PURPLE_NOSQL_SETTINGS_PAGE,
				self::PURPLE_NOSQL_BASE_SETTINGS_SECTION
			);

			add_settings_field(
				self::PURPLE_NOSQL_ACM_URL,
				'ACM URL',
				array( $this, 'purple_print_nosql_acm_url' ),
				self::PURPLE_NOSQL_SETTINGS_PAGE,
				self::PURPLE_NOSQL_BASE_SETTINGS_SECTION
			);

			add_settings_field(
				self::PURPLE_NOSQL_ACM_USERNAME,
				'ACM Username',
				array( $this, 'purple_print_nosql_acm_username' ),
				self::PURPLE_NOSQL_SETTINGS_PAGE,
				self::PURPLE_NOSQL_BASE_SETTINGS_SECTION
			);

			add_settings_field(
				self::PURPLE_NOSQL_ACM_API_KEY,
				'ACM API Key',
				array( $this, 'purple_print_nosql_acm_api_key' ),
				self::PURPLE_NOSQL_SETTINGS_PAGE,
				self::PURPLE_NOSQL_BASE_SETTINGS_SECTION
			);

			add_settings_field(
				'refresh-block-sync-db',
				'Synchronize DocumentDB',
				array( $this, 'purple_print_sync_db_button' ),
				self::PURPLE_NOSQL_SETTINGS_PAGE,
				self::PURPLE_NOSQL_BASE_SETTINGS_SECTION
			);

			register_setting( self::PURPLE_NOSQL_SETTINGS_GROUP, self::PURPLE_NOSQL_DATABASE_NAME );
			register_setting( self::PURPLE_NOSQL_SETTINGS_GROUP, self::PURPLE_NOSQL_ACM_URL );
			register_setting( self::PURPLE_NOSQL_SETTINGS_GROUP, self::PURPLE_NOSQL_ACM_USERNAME );
			register_setting( self::PURPLE_NOSQL_SETTINGS_GROUP, self::PURPLE_NOSQL_ACM_API_KEY );
		}

		/**
		 * Print settings page
		 */
		public function purple_print_settings_page() {
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Purple DS HUB NoSQL Settings', 'purpledshub' ); ?></h2>
				<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
					<?php
					do_settings_sections( self::PURPLE_NOSQL_SETTINGS_PAGE );
					settings_fields( self::PURPLE_NOSQL_SETTINGS_GROUP );
					?>
					<p class="submit">
						<input type="submit" name="submit" class="button-primary"
							   value="<?php esc_attr_e( 'Save Settings', 'purpledshub' ); ?>"/>
					</p>
				</form>
			</div>
			<?php
		}


		/**
		 * Base settings section
		 */
		public function purple_print_base_settings_section() {
			echo 'Base settings for the Purple DS HUB NoSQL plugin.';
		}

		/**
		 * Creates the 'Purple Base URL' input field
		 */
		public function purple_print_nosql_database_name() {
			$setting = get_option( self::PURPLE_NOSQL_DATABASE_NAME );
			?>
			<label>
				<input type="text" name="purple_nosql_database_name" class="regular-text"
					   value="<?php echo esc_attr( $setting ); ?>"
				<p class="description"><?php esc_html_e( 'Name of the database where the data will be saved.', 'purpledshub' ); ?></p>
			</label>
			<?php
		}

		/**
		 * Creates the 'Purple Base URL' input field
		 */
		public function purple_print_nosql_acm_url() {
			$setting = get_option( self::PURPLE_NOSQL_ACM_URL );
			?>
			<label>
				<input type="text" name="purple_nosql_acm_url" class="regular-text"
					   value="<?php echo esc_attr( $setting ); ?>"
				<p class="description"><?php esc_html_e( 'URL of ACM instance that needs to be synchronized.', 'purpledshub' ); ?></p>
			</label>
			<?php
		}

		/**
		 * Creates the 'Purple Base URL' input field
		 */
		public function purple_print_nosql_acm_username() {
			$setting = get_option( self::PURPLE_NOSQL_ACM_USERNAME );
			?>
			<label>
				<input type="text" name="purple_nosql_acm_username" class="regular-text"
					   value="<?php echo esc_attr( $setting ); ?>"
				<p class="description"><?php esc_html_e( 'ACM Username.', 'purpledshub' ); ?></p>
			</label>
			<?php
		}

		/**
		 * Creates the 'Purple Base URL' input field
		 */
		public function purple_print_nosql_acm_api_key() {
			$setting = get_option( self::PURPLE_NOSQL_ACM_API_KEY );
			?>
			<label>
				<input type="text" name="purple_nosql_acm_api_key" class="regular-text"
					   value="<?php echo esc_attr( $setting ); ?>"
				<p class="description"><?php esc_html_e( 'ACM API Key.', 'purpledshub' ); ?></p>
			</label>
			<?php
		}


	}
}
