<?php
/**
 * Debug Logger service
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @since 7.4.0
 * @package C3_CloudFront_Cache_Controller
 */

namespace C3_CloudFront_Cache_Controller\WP;

use C3_CloudFront_Cache_Controller\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Logger
 *
 * Centralizes debug logging functionality for the C3 plugin.
 * Provides context-aware logging methods and manages debug settings
 * with filter hook integration for runtime customization.
 *
 * @since 7.4.0
 * @package C3_CloudFront_Cache_Controller
 */
class Debug_Logger {
	/**
	 * Hook service
	 *
	 * @var Hooks
	 */
	private $hook_service;

	/**
	 * Log cron operations flag
	 *
	 * @var boolean
	 */
	private $log_cron_operations;

	/**
	 * Log invalidation parameters flag
	 *
	 * @var boolean
	 */
	private $log_invalidation_params;

	/**
	 * Initialize the debug logger and apply filter hooks
	 *
	 * @param mixed ...$args Optional Hooks service for dependency injection.
	 */
	function __construct( ...$args ) {
		$this->hook_service = new Hooks();

		if ( $args && ! empty( $args ) ) {
			foreach ( $args as $key => $value ) {
				if ( $value instanceof Hooks ) {
					$this->hook_service = $value;
				}
			}
		}

		$base_cron_setting = $this->get_debug_setting( Constants::DEBUG_LOG_CRON_REGISTER_TASK );
		$base_invalidation_setting = $this->get_debug_setting( Constants::DEBUG_LOG_INVALIDATION_PARAMS );

		$this->log_cron_operations = $this->hook_service->apply_filters( 'c3_log_cron_invalidation_task', $base_cron_setting );
		$this->log_invalidation_params = $this->hook_service->apply_filters( 'c3_log_invalidation_params', $base_invalidation_setting );
	}

	/**
	 * Check if cron operations should be logged
	 *
	 * @return boolean True if cron operations logging is enabled.
	 */
	public function should_log_cron_operations() {
		return $this->log_cron_operations;
	}

	/**
	 * Check if invalidation parameters should be logged
	 *
	 * @return boolean True if invalidation parameters logging is enabled.
	 */
	public function should_log_invalidation_params() {
		return $this->log_invalidation_params;
	}

	/**
	 * Log cron job start
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function log_cron_start( $message = '===== C3 Invalidation cron is started ===', $context = array() ) {
		if ( ! $this->log_cron_operations ) {
			return;
		}
		error_log( $message );
		if ( ! empty( $context ) ) {
			error_log( print_r( $context, true ) );
		}
	}

	/**
	 * Log cron job skip
	 *
	 * @param string $reason Reason for skipping.
	 * @param array  $context Additional context data.
	 */
	public function log_cron_skip( $reason, $context = array() ) {
		if ( ! $this->log_cron_operations ) {
			return;
		}
		error_log( $reason );
		if ( ! empty( $context ) ) {
			error_log( print_r( $context, true ) );
		}
	}

	/**
	 * Log cron job completion
	 *
	 * @param array $context Additional context data.
	 */
	public function log_cron_complete( $context = array() ) {
		if ( ! $this->log_cron_operations ) {
			return;
		}
		error_log( '===== C3 Invalidation cron has been COMPLETED ===' );
		if ( ! empty( $context ) ) {
			error_log( print_r( $context, true ) );
		}
	}

	/**
	 * Log cron registration start
	 *
	 * @param array $context Additional context data.
	 */
	public function log_cron_registration_start( $context = array() ) {
		if ( ! $this->log_cron_operations ) {
			return;
		}
		error_log( '===== C3 CRON Job registration [START] ===' );
		if ( ! empty( $context ) ) {
			error_log( print_r( $context, true ) );
		}
	}

	/**
	 * Log cron registration skip
	 *
	 * @param string $reason Reason for skipping.
	 * @param array  $context Additional context data.
	 */
	public function log_cron_registration_skip( $reason, $context = array() ) {
		if ( ! $this->log_cron_operations ) {
			return;
		}
		error_log( $reason );
		if ( ! empty( $context ) ) {
			error_log( print_r( $context, true ) );
		}
	}

	/**
	 * Log cron registration completion
	 *
	 * @param array $context Additional context data.
	 */
	public function log_cron_registration_complete( $context = array() ) {
		if ( ! $this->log_cron_operations ) {
			return;
		}
		error_log( '===== C3 CRON Job registration [COMPLETE] ===' );
		if ( ! empty( $context ) ) {
			error_log( print_r( $context, true ) );
		}
	}

	/**
	 * Log invalidation parameters
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function log_invalidation_params( $message, $context = array() ) {
		if ( ! $this->log_invalidation_params ) {
			return;
		}
		error_log( $message );
		if ( ! empty( $context ) ) {
			error_log( print_r( $context, true ) );
		}
	}

	/**
	 * Log invalidation request details
	 *
	 * @param array $params Invalidation parameters.
	 */
	public function log_invalidation_request( $params ) {
		if ( ! $this->log_invalidation_params ) {
			return;
		}
		if ( isset( $params['query'] ) ) {
			error_log( 'C3 Invalidation Started - Query: ' . print_r( $params['query'], true ) );
		}
		if ( isset( $params['force'] ) ) {
			error_log( 'C3 Invalidation Started - Force: ' . ( $params['force'] ? 'true' : 'false' ) );
		}
		if ( isset( $params['distribution_id'] ) ) {
			error_log( 'C3 CloudFront Invalidation Request - Distribution ID: ' . $params['distribution_id'] );
		}
		if ( isset( $params['paths'] ) ) {
			error_log( 'C3 CloudFront Invalidation Request - Paths: ' . print_r( $params['paths'], true ) );
		}
		if ( isset( $params['full_params'] ) ) {
			error_log( 'C3 CloudFront Invalidation Request - Full Params: ' . print_r( $params['full_params'], true ) );
		}
	}

	/**
	 * Get debug setting value from WordPress options
	 *
	 * @param string $setting_key Debug setting key.
	 * @return boolean Debug setting value.
	 */
	private function get_debug_setting( $setting_key ) {
		$debug_options = get_option( Constants::DEBUG_OPTION_NAME, array() );
		$value = isset( $debug_options[ $setting_key ] ) ? $debug_options[ $setting_key ] : false;
		return $value;
	}
}
