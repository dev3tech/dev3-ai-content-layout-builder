<?php
/**
 * Plugin settings stored in a single option.
 *
 * @package Dev3\AiContentLayoutBuilder
 */

namespace Dev3\AiContentLayoutBuilder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitizes and persists the dev3cl_settings option.
 */
class Settings {

	const OPTION = 'dev3cl_settings';

	const TASK_GROUPS = array( 'copy', 'seo', 'translate', 'layout', 'vision' );

	const TONES = array( 'professional', 'casual', 'persuasive', 'technical', 'friendly', 'witty', 'formal', 'empathetic' );

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_key'             => '',
			'default_model'       => 'gemini-2.5-flash',
			'pro_model'           => 'gemini-2.5-pro',
			'task_models'         => array(
				'copy'      => 'default',
				'seo'       => 'default',
				'translate' => 'default',
				'layout'    => 'pro',
				'vision'    => 'default',
			),
			'temperature'         => 0.7,
			'max_tokens'          => 4096,
			'reasoning'           => 'low',
			'default_tone'        => 'professional',
			'default_language'    => 'English',
			'brand_voice'         => '',
			'integrations'        => array(
				'elementor' => true,
				'wpbakery'  => true,
				'gutenberg' => true,
				'media'     => true,
			),
			'history_enabled'     => true,
			'history_days'        => 90,
			'usage_logging'       => true,
			'rate_limit'          => 30,
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * All settings merged with defaults. The API key stays encrypted.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback when missing.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Decrypted API key. The DEV3CL_GEMINI_API_KEY constant wins when defined.
	 *
	 * @return string
	 */
	public static function api_key() {
		if ( defined( 'DEV3CL_GEMINI_API_KEY' ) && DEV3CL_GEMINI_API_KEY ) {
			return (string) DEV3CL_GEMINI_API_KEY;
		}
		return Encryption::decrypt( (string) self::get( 'api_key', '' ) );
	}

	/**
	 * Whether the key comes from wp-config.php.
	 *
	 * @return bool
	 */
	public static function key_from_constant() {
		return defined( 'DEV3CL_GEMINI_API_KEY' ) && DEV3CL_GEMINI_API_KEY;
	}

	/**
	 * Key shown to the browser: never the real key.
	 *
	 * @return string
	 */
	public static function masked_key() {
		$key = self::api_key();
		if ( '' === $key ) {
			return '';
		}
		return str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * Model id for a task group.
	 *
	 * @param string $group One of TASK_GROUPS.
	 * @return string
	 */
	public static function model_for( $group ) {
		$all    = self::all();
		$choice = isset( $all['task_models'][ $group ] ) ? $all['task_models'][ $group ] : 'default';
		if ( 'pro' === $choice ) {
			return $all['pro_model'];
		}
		if ( 'default' === $choice || '' === $choice ) {
			return $all['default_model'];
		}
		return $choice;
	}

	/**
	 * Settings safe to expose to the admin app.
	 *
	 * @return array
	 */
	public static function public_settings() {
		$all = self::all();
		unset( $all['api_key'] );
		$all['api_key_masked']       = self::masked_key();
		$all['api_key_set']          = '' !== self::api_key();
		$all['api_key_constant']     = self::key_from_constant();
		$all['encryption_available'] = Encryption::available();
		return $all;
	}

	/**
	 * Sanitizes and saves a partial update.
	 *
	 * @param array $input Raw input.
	 * @return array Public settings after save.
	 */
	public static function update( array $input ) {
		$current = self::all();
		$clean   = self::sanitize( $input, $current );
		update_option( self::OPTION, $clean, false );
		self::$cache = null;
		return self::public_settings();
	}

	/**
	 * Sanitizes input against the current values.
	 *
	 * @param array $input   Raw input.
	 * @param array $current Current settings.
	 * @return array
	 */
	public static function sanitize( array $input, array $current ) {
		$out = $current;

		if ( array_key_exists( 'api_key', $input ) ) {
			$key = trim( sanitize_text_field( wp_unslash( (string) $input['api_key'] ) ) );
			// The masked placeholder coming back from the UI means "unchanged".
			if ( false === strpos( $key, '•' ) ) {
				$out['api_key'] = '' === $key ? '' : Encryption::encrypt( $key );
			}
		}

		foreach ( array( 'default_model', 'pro_model' ) as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$model = self::sanitize_model( $input[ $field ] );
				if ( '' !== $model ) {
					$out[ $field ] = $model;
				}
			}
		}

		if ( isset( $input['task_models'] ) && is_array( $input['task_models'] ) ) {
			foreach ( self::TASK_GROUPS as $group ) {
				if ( isset( $input['task_models'][ $group ] ) ) {
					$value                        = (string) $input['task_models'][ $group ];
					$out['task_models'][ $group ] = in_array( $value, array( 'default', 'pro' ), true ) ? $value : self::sanitize_model( $value );
				}
			}
		}

		if ( isset( $input['temperature'] ) ) {
			$out['temperature'] = round( min( 1.0, max( 0.0, (float) $input['temperature'] ) ), 2 );
		}
		if ( isset( $input['max_tokens'] ) ) {
			$out['max_tokens'] = min( 65536, max( 64, absint( $input['max_tokens'] ) ) );
		}
		if ( isset( $input['reasoning'] ) ) {
			$out['reasoning'] = in_array( $input['reasoning'], array( 'low', 'auto' ), true ) ? $input['reasoning'] : 'low';
		}
		if ( isset( $input['default_tone'] ) && in_array( $input['default_tone'], self::TONES, true ) ) {
			$out['default_tone'] = $input['default_tone'];
		}
		if ( isset( $input['default_language'] ) ) {
			$out['default_language'] = substr( sanitize_text_field( (string) $input['default_language'] ), 0, 60 );
		}
		if ( isset( $input['brand_voice'] ) ) {
			$out['brand_voice'] = substr( sanitize_textarea_field( (string) $input['brand_voice'] ), 0, 2000 );
		}
		if ( isset( $input['integrations'] ) && is_array( $input['integrations'] ) ) {
			foreach ( array_keys( self::defaults()['integrations'] ) as $name ) {
				if ( isset( $input['integrations'][ $name ] ) ) {
					$out['integrations'][ $name ] = (bool) $input['integrations'][ $name ];
				}
			}
		}
		foreach ( array( 'history_enabled', 'usage_logging', 'delete_on_uninstall' ) as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$out[ $flag ] = (bool) $input[ $flag ];
			}
		}
		if ( isset( $input['history_days'] ) ) {
			$out['history_days'] = min( 3650, max( 1, absint( $input['history_days'] ) ) );
		}
		if ( isset( $input['rate_limit'] ) ) {
			$out['rate_limit'] = min( 600, max( 0, (int) $input['rate_limit'] ) );
		}

		return $out;
	}

	/**
	 * Model ids look like gemini-2.5-flash or models/gemini-2.5-pro-preview-05-06.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_model( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '#^models/#', '', $value );
		return preg_match( '/^[a-z0-9][a-z0-9._-]{1,79}$/', $value ) ? $value : '';
	}

	/**
	 * Clears the request cache (tests, after direct option writes).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Recursive merge that keeps default keys for nested arrays.
	 *
	 * @param array $defaults Defaults.
	 * @param array $stored   Stored values.
	 * @return array
	 */
	private static function merge( array $defaults, array $stored ) {
		foreach ( $stored as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_array( $value ) ) {
				$defaults[ $key ] = self::merge( $defaults[ $key ], $value );
			} elseif ( array_key_exists( $key, $defaults ) ) {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}
}
