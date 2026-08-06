<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Settings;

/**
 * Registry definitions for the redesigned Suspensions section.
 *
 * The single legacy dropdown `_max_customer_suspensions` (0–12 or "unlimited", default 0) becomes
 * three progressive controls (migration plan §5a), loss-free:
 *
 *  - `suspensions_enable`      — off only for 0 / unset; on for any number or "unlimited".
 *  - `suspensions_limit`       — on only for 1–12 (a finite cap); off for "unlimited" or when disabled.
 *  - `suspensions_per_period`  — the cap when 1–12; otherwise the stepper default of 1 (§5b).
 *
 * §5a, §5b → sdd/settings-ui-refresh/context/migration.md
 *
 * @internal
 */
class Suspensions_Definitions {
	private const LEGACY_MAX_SUSPENSIONS = 'woocommerce_subscriptions_max_customer_suspensions';

	/**
	 * Register the Suspensions section's settings with the registry.
	 *
	 * @param Registry $registry Registry to populate.
	 */
	public static function register( Registry $registry ): void {
		$registry->register(
			'suspensions_enable',
			static function () {
				$value = (string) get_option( self::LEGACY_MAX_SUSPENSIONS, '0' );
				return ( '0' === $value || '' === $value ) ? 'no' : 'yes';
			}
		);

		$registry->register(
			'suspensions_limit',
			static function () {
				$value = get_option( self::LEGACY_MAX_SUSPENSIONS, '0' );
				return ( is_numeric( $value ) && (int) $value >= 1 ) ? 'yes' : 'no';
			}
		);

		$registry->register(
			'suspensions_per_period',
			static function () {
				$value = get_option( self::LEGACY_MAX_SUSPENSIONS, '0' );
				// A finite cap carries its number; "unlimited" / off fall back to the stepper default (§5b).
				return ( is_numeric( $value ) && (int) $value >= 1 ) ? (string) (int) $value : '1';
			}
		);
	}
}
