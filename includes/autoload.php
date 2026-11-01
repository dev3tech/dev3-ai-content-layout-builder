<?php
/**
 * PSR-4 style autoloader for the Dev3\AiContentLayoutBuilder namespace.
 *
 * Maps Dev3\AiContentLayoutBuilder\Api\Gemini_Client to includes/Api/Gemini_Client.php.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Dev3\\AiContentLayoutBuilder\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
