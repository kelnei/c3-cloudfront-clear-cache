<?php
/**
 * ECS Credential Provider
 *
 * @author hideokamoto <hide.okamoto@digitalcube.jp>
 * @package C3_CloudFront_Cache_Controller
 */

namespace C3_CloudFront_Cache_Controller\AWS;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ECS Credential Provider
 *
 * @package C3_CloudFront_Cache_Controller
 */
class ECS_Credential_Provider {

	const ECS_SERVER_HOST_IPV4 = '169.254.170.2';
	const EKS_SERVER_HOST_IPV4 = '169.254.170.23';
	const EKS_SERVER_HOST_IPV6 = 'fd00:ec2::23';
	const ENV_AUTH_TOKEN = 'AWS_CONTAINER_AUTHORIZATION_TOKEN';
	const ENV_AUTH_TOKEN_FILE = 'AWS_CONTAINER_AUTHORIZATION_TOKEN_FILE';
	const ENV_FULL_URI = 'AWS_CONTAINER_CREDENTIALS_FULL_URI';
	const ENV_URI = 'AWS_CONTAINER_CREDENTIALS_RELATIVE_URI';
	const SERVER_URI = 'http://169.254.170.2';

	/**
	 * HTTP request timeout in seconds
	 *
	 * @var int
	 */
	private $timeout = 5;

	/**
	 * Cached credentials
	 *
	 * @var array|null
	 */
	private $cached_credentials = null;

	/**
	 * Cache expiry timestamp
	 *
	 * @var int|null
	 */
	private $cache_expiry = null;

	/**
	 * Get temporary credentials from ECS task role
	 *
	 * @return array|null Array with 'key', 'secret', 'token' or null if not available.
	 */
	public function get_credentials() {
		if ( $this->cached_credentials && $this->cache_expiry && time() < $this->cache_expiry - 300 ) {
			return $this->cached_credentials;
		}

		$credentials = $this->fetch_credentials();

		if ( $credentials ) {
			$this->cached_credentials = $credentials;
			$this->cache_expiry       = isset( $credentials['expiration'] ) ? strtotime( $credentials['expiration'] ) : time() + 3600;
		}

		return $credentials;
	}

	/**
	 * Fetch credentials from metadata service
	 *
	 * @return array|null
	 */
	private function fetch_credentials() {
		$uri = $this->get_ecs_uri();

		if ( !$this->is_valid_ecs_uri( $uri ) ) {
			return null;
		}

		$headers = array();
		$token = $this->get_ecs_auth_token();

		if ( ! empty( $token ) ) {
			$headers['Authorization'] = $token;
		}

		$creds_response = wp_remote_request(
			$uri,
			array(
				'method'  => 'GET',
				'headers' => $headers,
				'timeout' => $this->timeout,
			)
		);

		if ( is_wp_error( $creds_response ) || wp_remote_retrieve_response_code( $creds_response ) !== 200 ) {
			return null;
		}

		$creds_data = json_decode( wp_remote_retrieve_body( $creds_response ), true );
		if ( ! $creds_data ) {
			return null;
		}

		return array(
			'key'        => $creds_data['AccessKeyId'],
			'secret'     => $creds_data['SecretAccessKey'],
			'token'      => $creds_data['Token'],
			'expiration' => $creds_data['Expiration'],
		);
	}

	/**
	 * Fetches the authorizationt token from file or env variable.
	 *
	 * @return string|null
	 */
	private function get_ecs_auth_token() {
		$path = getenv( static::ENV_AUTH_TOKEN_FILE );

		if (! empty( $path ) && is_readable( $path ) ) {
			$token = @file_get_contents( $path );

			if ( $token ) {
				return $token;
			}
		}

		return getenv( static::ENV_AUTH_TOKEN );
	}


	/**
	 * Determines the container metadata endpoint from environment variables.
	 *
	 * @return string Returns container metadata URI
	 */
	public function get_ecs_uri() {
		$uri = getenv( static::ENV_URI );

		if ( empty( $uri ) ) {
			$uri = getenv( static::ENV_FULL_URI );

			if ( ! empty( $uri ) ) {
				return $uri;
			}
		}

		return static::SERVER_URI . $uri;
	}

	/**
	 * Determines if the specified URI a valid ECS credential request URI.
	 *
	 * @param string $uri
	 *
	 * @return bool
	 */
	private function is_valid_ecs_uri( $uri ) {
		$parsed = parse_url($uri);

		if ($parsed['scheme'] !== 'https') {
			$host = trim($parsed['host'], '[]'); // [fd00:ec2::23] -> fd00:ec2::23

			if ( $host !== static::ECS_SERVER_HOST_IPV4 &&
				 $host !== static::EKS_SERVER_HOST_IPV4 &&
				 $host !== static::EKS_SERVER_HOST_IPV6 &&
				 !$this->is_loopback( gethostname( $host ) )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the specified IP is a loopback address
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	private function is_loopback( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return false;

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $ip === '::1';
		}

		$n = ip2long( $ip );

		return ( $n >= ip2long( '127.0.0.0' ) && $n <= ip2long( '127.255.255.255' ) );
	}

	/**
	 * @return bool
	 */
	public static function should_use_ecs_credentials() {
		return ! empty( getenv( static::ENV_URI ) )
			|| ! empty( getenv( static::ENV_FULL_URI ) );
	}
}
