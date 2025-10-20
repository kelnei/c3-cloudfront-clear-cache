<?php
/**
 * Debug Settings Integration Test
 *
 * Tests the integration of debug settings across different services to ensure
 * that the correct filter names are used and settings are properly applied.
 * This test prevents regression of bugs related to incorrect filter usage.
 *
 * @package C3_CloudFront_Cache_Controller\Test
 * @since 7.3.0
 */

use PHPUnit\Framework\TestCase;
use C3_CloudFront_Cache_Controller\Invalidation_Service;
use C3_CloudFront_Cache_Controller\Cron_Service;
use C3_CloudFront_Cache_Controller\AWS\CloudFront_Service;
use C3_CloudFront_Cache_Controller\Constants;
use C3_CloudFront_Cache_Controller\Test\Helpers\WP_Mock_Helper;

/**
 * Debug Settings Integration Test
 *
 * @since 7.3.0
 */
class Debug_Settings_Integration_Test extends TestCase {

	/**
	 * Set up test environment.
	 *
	 * Defines required constants for testing and initializes the test environment.
	 *
	 * @since 7.3.0
	 */
	public function setUp(): void {
		// Define the plugin root constant required for text domain resolution.
		if ( ! defined( 'C3_PLUGIN_ROOT' ) ) {
			define( 'C3_PLUGIN_ROOT', dirname( dirname( __DIR__ ) ) . '/c3-cloudfront-clear-cache.php' );
		}
	}

	/**
	 * Clean up after each test.
	 *
	 * Removes any debug settings that were created during testing to ensure
	 * test isolation and prevent side effects.
	 *
	 * @since 7.3.0
	 */
	public function tearDown(): void {
		\Mockery::close();
		delete_option( Constants::DEBUG_OPTION_NAME );
	}

	/**
	 * Test that debug filters use correct names when enabled.
	 *
	 * This test verifies that each service uses the appropriate filter name
	 * for debug logging. This prevents regression of bugs where incorrect
	 * filter names were used, causing debug settings to not work properly.
	 *
	 * @since 7.3.0
	 */
	public function test_debug_filters_use_correct_names() {
		update_option( Constants::DEBUG_OPTION_NAME, array(
			Constants::DEBUG_LOG_INVALIDATION_PARAMS => true,
			Constants::DEBUG_LOG_CRON_REGISTER_TASK => true
		) );

		$mock_env = WP_Mock_Helper::create_mock_environment( 'E123456789' );
		$mock_options = WP_Mock_Helper::create_mock_options_service( array(
			'distribution_id' => 'E123456789',
			'access_key' => 'test-key',
			'secret_key' => 'test-secret'
		) );
		$mock_hooks = WP_Mock_Helper::create_mock_hooks_service();

		$debug_logger = new \C3_CloudFront_Cache_Controller\WP\Debug_Logger( $mock_hooks );
		$reflection = new ReflectionClass( $debug_logger );
		
		$cron_property = $reflection->getProperty( 'log_cron_operations' );
		$cron_property->setAccessible( true );
		$cron_debug = $cron_property->getValue( $debug_logger );
		$this->assertTrue( $cron_debug, 'Debug_Logger should apply c3_log_cron_invalidation_task filter' );
		
		$invalidation_property = $reflection->getProperty( 'log_invalidation_params' );
		$invalidation_property->setAccessible( true );
		$invalidation_debug = $invalidation_property->getValue( $debug_logger );
		$this->assertTrue( $invalidation_debug, 'Debug_Logger should apply c3_log_invalidation_params filter' );

		$invalidation_service = new Invalidation_Service( $mock_env, $mock_options, $mock_hooks, $debug_logger );
		$reflection = new ReflectionClass( $invalidation_service );
		$debug_property = $reflection->getProperty( 'debug_logger' );
		$debug_property->setAccessible( true );
		$logger = $debug_property->getValue( $invalidation_service );
		$this->assertInstanceOf( 'C3_CloudFront_Cache_Controller\WP\Debug_Logger', $logger );
		$this->assertTrue( $logger->should_log_invalidation_params() );

		$cron_service = new Cron_Service( $mock_hooks, $debug_logger );
		$reflection = new ReflectionClass( $cron_service );
		$debug_property = $reflection->getProperty( 'debug_logger' );
		$debug_property->setAccessible( true );
		$logger = $debug_property->getValue( $cron_service );
		$this->assertInstanceOf( 'C3_CloudFront_Cache_Controller\WP\Debug_Logger', $logger );
		$this->assertTrue( $logger->should_log_cron_operations() );

		$cf_service = new CloudFront_Service( $mock_env, $mock_options, $mock_hooks, $debug_logger );
		$reflection = new ReflectionClass( $cf_service );
		$debug_property = $reflection->getProperty( 'debug_logger' );
		$debug_property->setAccessible( true );
		$logger = $debug_property->getValue( $cf_service );
		$this->assertInstanceOf( 'C3_CloudFront_Cache_Controller\WP\Debug_Logger', $logger );
		$this->assertTrue( $logger->should_log_invalidation_params() );
	}

	/**
	 * Test that debug filters work correctly when disabled.
	 *
	 * This test verifies that when debug settings are disabled, all services
	 * properly respect the disabled state and do not output debug logs.
	 * This ensures the debug settings can be properly turned off.
	 *
	 * @since 7.3.0
	 */
	public function test_debug_filters_work_when_disabled() {
		update_option( Constants::DEBUG_OPTION_NAME, array(
			Constants::DEBUG_LOG_INVALIDATION_PARAMS => false,
			Constants::DEBUG_LOG_CRON_REGISTER_TASK => false
		) );

		$mock_env = WP_Mock_Helper::create_mock_environment( 'E123456789' );
		$mock_options = WP_Mock_Helper::create_mock_options_service( array(
			'distribution_id' => 'E123456789',
			'access_key' => 'test-key',
			'secret_key' => 'test-secret'
		) );
		$mock_hooks = WP_Mock_Helper::create_mock_hooks_service();

		$debug_logger = new \C3_CloudFront_Cache_Controller\WP\Debug_Logger( $mock_hooks );
		$this->assertFalse( $debug_logger->should_log_cron_operations() );
		$this->assertFalse( $debug_logger->should_log_invalidation_params() );

		$invalidation_service = new Invalidation_Service( $mock_env, $mock_options, $mock_hooks, $debug_logger );
		$reflection = new ReflectionClass( $invalidation_service );
		$debug_property = $reflection->getProperty( 'debug_logger' );
		$debug_property->setAccessible( true );
		$logger = $debug_property->getValue( $invalidation_service );
		$this->assertFalse( $logger->should_log_invalidation_params() );

		$cron_service = new Cron_Service( $mock_hooks, $debug_logger );
		$reflection = new ReflectionClass( $cron_service );
		$debug_property = $reflection->getProperty( 'debug_logger' );
		$debug_property->setAccessible( true );
		$logger = $debug_property->getValue( $cron_service );
		$this->assertFalse( $logger->should_log_cron_operations() );

		$cf_service = new CloudFront_Service( $mock_env, $mock_options, $mock_hooks, $debug_logger );
		$reflection = new ReflectionClass( $cf_service );
		$debug_property = $reflection->getProperty( 'debug_logger' );
		$debug_property->setAccessible( true );
		$logger = $debug_property->getValue( $cf_service );
		$this->assertFalse( $logger->should_log_invalidation_params() );
	}
}
