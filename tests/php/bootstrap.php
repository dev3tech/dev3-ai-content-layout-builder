<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite (wp-env sets WP_TESTS_DIR) and the plugin.
 *
 * Run: npx wp-env run tests-cli --env-cwd=wp-content/plugins/dev3-ai-content-layout-builder vendor/bin/phpunit
 *
 * @package Dev3\AiContentLayoutBuilder
 */

$dev3cl_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/wordpress-phpunit';
if ( ! file_exists( $dev3cl_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found in {$dev3cl_tests_dir}. Run the tests inside wp-env (tests-cli).\n"; // phpcs:ignore
	exit( 1 );
}
require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $dev3cl_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/dev3-ai-content-layout-builder.php';
	}
);

require $dev3cl_tests_dir . '/includes/bootstrap.php';
\Dev3\AiContentLayoutBuilder\Core\Installer::install_tables();
