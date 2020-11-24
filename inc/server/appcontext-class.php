<?php
namespace NoSQL\Inc\Server;

/**
 * Instance available in all GraphQL resolvers as 3rd argument
 */
class App_Context
{
	/**
	 * @var string
	 */
	public $rootUrl;

	/**
	 * @var \mixed
	 */
	public $request;
}
