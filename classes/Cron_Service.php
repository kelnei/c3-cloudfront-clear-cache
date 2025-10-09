<?php
/**
 * Cron service
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @since 6.1.1
 * @package C3_CloudFront_Cache_Controller
 */

namespace C3_CloudFront_Cache_Controller;
use C3_CloudFront_Cache_Controller\Constants;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron service
 *
 * @since 6.1.1
 * @package C3_CloudFront_Cache_Controller
 */
class Cron_Service {
	/**
	 * Hook
	 *
	 * @var WP\Hooks
	 */
	private $hook_service;

	/**
	 * WP Transient service
	 *
	 * @var WP\Transient_Service
	 */
	private $transient_service;

	/**
	 * Debug logger service
	 *
	 * @var WP\Debug_Logger
	 */
	private $debug_logger;

	/**
	 * CloudFront service
	 *
	 * @var \C3_CloudFront_Cache_Controller\AWS\CloudFront_Service
	 */
	private $cf_service;

	/**
	 * Inject a external services
	 *
	 * @param mixed ...$args Inject class.
	 */
	function __construct( ...$args ) {
		$this->hook_service      = new WP\Hooks();
		$this->transient_service = new WP\Transient_Service();
		$this->cf_service        = new AWS\CloudFront_Service();
		$this->debug_logger      = new WP\Debug_Logger();

		if ( $args && ! empty( $args ) ) {
			foreach ( $args as $key => $value ) {
				if ( $value instanceof WP\Hooks ) {
					$this->hook_service = $value;
				} elseif ( $value instanceof WP\Transient_Service ) {
					$this->transient_service = $value;
				} elseif ( $value instanceof AWS\CloudFront_Service ) {
					$this->cf_service = $value;
				} elseif ( $value instanceof WP\Debug_Logger ) {
					$this->debug_logger = $value;
				}
			}
		}
		$this->hook_service->add_action(
			'c3_cron_invalidation',
			array(
				$this,
				'run_schedule_invalidate',
			)
		);
	}

	/**
	 * Run the schedule invalidation
	 *
	 * @return boolean
	 */
	public function run_schedule_invalidate() {
		$this->debug_logger->log_cron_start();
		if ( $this->hook_service->apply_filters( 'c3_disabled_cron_retry', false ) ) {
			$this->debug_logger->log_cron_skip( '===== C3 Invalidation cron has been SKIPPED [Disabled] ===' );
			return false;
		}
		$invalidation_batch = $this->transient_service->load_invalidation_query();
		$this->debug_logger->log_invalidation_params( '', array( $invalidation_batch ) );
		if ( ! $invalidation_batch || empty( $invalidation_batch ) ) {
			$this->debug_logger->log_cron_skip( '===== C3 Invalidation cron has been SKIPPED [No Target Item] ===' );
			return false;
		}
		$distribution_id = $this->cf_service->get_distribution_id();
		$query           = array(
			'DistributionId'    => esc_attr( $distribution_id ),
			'InvalidationBatch' => $invalidation_batch,
		);
		$this->debug_logger->log_invalidation_params( '', array( $query ) );

		/**
		 * Execute the invalidation.
		 */
		$result = $this->cf_service->create_invalidation( $query );
		if ( $this->debug_logger->should_log_cron_operations() ) {
			if ( is_wp_error( $result ) ) {
				error_log( 'C3 Cron: Invalidation failed: ' . $result->get_error_message() );
			} else {
				error_log( 'C3 Cron: Invalidation completed successfully' );
			}
		}
		$this->transient_service->delete_invalidation_query();
		$this->debug_logger->log_cron_complete();
		return true;
	}

}
