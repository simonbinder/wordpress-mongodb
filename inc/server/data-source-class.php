<?php

namespace NoSQL\Inc\Server;

class Data_Source {

	private static $pdo;

	public static function init( $config ) {
		self::$pdo = new PDO( "mysql:host={$config['host']};dbname={$config['database']}", $config['username'], $config['password'] );
		self::$pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ );
	}

	public static function select( $query ) {
		$statement = self::$pdo->query( $query );
		return $statement->fetchAll();
	}
}
