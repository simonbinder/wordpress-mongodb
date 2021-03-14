<?php
namespace NoSQL\Inc\Server;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use \GraphQL\Type\Schema;
use \GraphQL\GraphQL;
use \GraphQL\Error\FormattedError;
use \GraphQL\Error\DebugFlag;
use MongoDB\Client;

if ( ! class_exists( 'Graphql_Init' ) ) {
	class Graphql_Init {

		/**
		 */
		private $connection;

		private $db;

		public function __construct() {
				// database connection configuration.
				$config = array(
					'host'     => 'db_host',
					'database' => 'db_name',
					'username' => 'db_user',
					'password' => 'db_password',
				);
				// connect to mongodb
				$m = new \MongoDB\Client();

				// select a database
				$this->db         = $m->selectDatabase( 'wp' );
				$this->connection = $this->db->selectCollection( 'posts' );

				add_action( 'rest_api_init', array( $this, 'register_endpoint' ) );
		}

		public function register_graphql() {
			try {
				$category_type = new ObjectType(
					array(
						'name'   => 'category',
						'fields' => array(
							'name'    => Type::string(),
							'slug'    => Type::string(),
							'term_id' => Type::int(),
						),
					)
				);

				$tag_type = new ObjectType(
					array(
						'name'   => 'tag',
						'fields' => array(
							'name'    => Type::string(),
							'slug'    => Type::string(),
							'term_id' => Type::int(),
						),
					)
				);

				$custom_field_type = new ObjectType(
					array(
						'name'   => 'custom_field',
						'fields' => array(
							'field' => Type::string(),
							'value' => Type::string(),
						),
					)
				);

				$comment_type = new ObjectType(
					array(
						'name'   => 'comment',
						'fields' => array(
							'comment_date'    => Type::string(),
							'text'            => Type::string(),
							'status'          => Type::string(),
							'comment_id'      => Type::int(),
							'author'          => Type::int(),
							'comment_post_ID' => Type::int(),
						),
					)
				);

				$user_type = new ObjectType(
					array(
						'name'   => 'user',
						'fields' => array(
							'display_name' => Type::string(),
							'email'        => Type::string(),
							'login'        => Type::string(),
							'user_id'      => Type::int(),
						),
					)
				);

				$attrs_content_type = new ObjectType(
					array(
						'name'   => 'attrs',
						'fields' => array(
							'align'     => Type::string(),
							'className' => Type::string(),
							'content'   => Type::string(),
							'purpleId'  => Type::string(),
							'id'        => Type::int(),
							'level'     => Type::int(),
							'sizeSlug'  => Type::string(),
						),
					)
				);

				$post_content_type = new ObjectType(
					array(
						'name'   => 'postContent',
						'fields' => array(
							'blockName' => Type::string(),
							'innerHTML' => Type::string(),
							'attrs'     => $attrs_content_type,
						),
					)
				);

				$post_type = new ObjectType(
					array(
						'name'        => 'Post',
						'description' => 'WP Post',
						'fields'      => array(
							'postId'                 => Type::int(),
							'post_title'             => Type::string(),
							'post_type'              => Type::string(),
							'categories'             => Type::listOf( Type::int() ),
							'cats'                   => Type::listOf( $category_type ),
							'comment_status'         => Type::string(),
							'guid'                   => Type::string(),
							'post_modified'          => Type::string(),
							'post_modified_gmt'      => Type::string(),
							'post_name'              => Type::string(),
							'post_parent'            => Type::int(),
							'post_status'            => Type::string(),
							'post_title'             => Type::string(),
							'post_type'              => Type::string(),
							'comment_count'          => Type::string(),
							'thumbnail'              => Type::string(),
							'permalink'              => Type::string(),
							'post_excerpt'           => Type::string(),
							'purpleIssue'            => Type::int(),
							'purpleIssueArticles'    => Type::listOf( Type::int() ),
							'comments'               => Type::listOf( Type::int() ),
							'author'                 => Type::int(),
							'user'                   => Type::listOf( $user_type ),
							'tags'                   => Type::listOf( $tag_type ),
							'commentfields'          => Type::listOf( $comment_type ),
							'postContent'            => Type::listOf( $post_content_type ),
							'custom_fields'          => Type::listOf( $custom_field_type ),
							'advanced_custom_fields' => Type::listOf( $custom_field_type ),
						),
					)
				);

				$query_type = new ObjectType(
					array(
						'name'   => 'Query',
						'fields' => array(
							'post'          => array(
								'type'    => $post_type,
								'args'    => array(
									'id' => Type::int(),
								),
								'resolve' => function ( $root, $args ) {
									$result = $this->connection->findOne( array( 'postId' => $args['id'] ) );
									return $result;
								},
							),
							'blockId'       => array(
								'type'    => $post_type,
								'args'    => array(
									'id' => Type::string(),
								),
								'resolve' => function ( $root, $args ) {
									$result = $this->connection->findOne( array( 'postContent.attrs.purpleId' => $args['id'] ), array( 'postContent.$' => 1 ) );
									return $result;
								},
							),
							'postAggregate' => array(
								'type'    => Type::listOf( $post_type ),
								'args'    => array(
									'id' => Type::int(),
								),
								'resolve' => function ( $root, $args ) {
									$pipeline = array(
										array(
											'$match' => array( 'postId' => $args['id'] ),
										),
										array(
											'$lookup' => array(
												'from' => 'users',
												'localField' => 'author',
												'foreignField' => 'user_id',
												'as'   => 'user',
											),
										),
										array(
											'$lookup' => array(
												'from' => 'tags',
												'localField' => 'tags',
												'foreignField' => 'term_id',
												'as'   => 'tags',
											),
										),
										array(
											'$lookup' => array(
												'from' => 'categories',
												'localField' => 'categories',
												'foreignField' => 'term_id',
												'as'   => 'cats',
											),
										),
										array(
											'$lookup' => array(
												'from' => 'comments',
												'localField' => 'comments',
												'foreignField' => 'comment_id',
												'as'   => 'commentfields',
											),
										),
									);
									return $this->connection->aggregate( $pipeline );
								},
							),
						),
					)
				);

				// See docs on schema options:
				// http://webonyx.github.io/graphql-php/type-system/schema/#configuration-options.
				$schema = new Schema(
					array(
						'query' => $query_type,
					)
				);
				// gets the root of the sent json {"query"=>"query{accidentsData(...)}"}.
				$raw_input       = file_get_contents( 'php://input' );
				$input           = json_decode( $raw_input, true );
				$query           = $input['query'];
				$variable_values = isset( $input['variables'] ) ? $input['variables'] : null;
				$result          = GraphQL::executeQuery( $schema, $query, null, null, $variable_values );
				$output          = $result->toArray();
				return $output;
			} catch ( \Exception $e ) {
				$output = array(
					'error' => array(
						'message' => $e->getMessage(),
					),
				);
			}
		}

		public function test() {
			$pipeline = array(
				array(
					'$match' => array( 'postId' => 23222 ),
				),
				array(
					'$lookup' => array(
						'from'         => 'users',
						'localField'   => 'author',
						'foreignField' => 'user_id',
						'as'           => 'user',
					),
				),
			);
			$result   = $this->connection->aggregate( $pipeline );
			foreach ( $result as $document ) {
				debug( $result );
			}
			return $result;
		}

		public function register_endpoint() {
			register_rest_route(
				'graphql',
				'/posts',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'register_graphql' ),
				)
			);
		}
	}
}


