<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Settings;

/**
 * Registry definitions for the redesigned Renewals section.
 *
 * Renewals is pure core and carries 1:1 — five checkboxes, relabelled only (migration plan §4a).
 * Each modern key is a yes/no copy of its legacy counterpart; the only nuance is the default for
 * early renewal (§4b), which is not a flat constant: it mirrors the legacy resolution, where an
 * absent option means OFF for an existing store (subscriptions present) and ON for a fresh one. The
 * two child checkboxes derive independently of their parents, so dormant child values are preserved
 * (§4c), and the renewal-mode toggle stays top-level and independent (§4d).
 *
 * §4a, §4b, §4c, §4d → sdd/settings-ui-refresh/context/migration.md
 *
 * @internal
 */
class Renewals_Definitions {
	private const LEGACY_ACCEPT_MANUAL          = 'woocommerce_subscriptions_accept_manual_renewals';
	private const LEGACY_TURN_OFF_AUTOMATIC     = 'woocommerce_subscriptions_turn_off_automatic_payments';
	private const LEGACY_ENABLE_EARLY           = 'woocommerce_subscriptions_enable_early_renewal';
	private const LEGACY_ENABLE_EARLY_VIA_MODAL = 'woocommerce_subscriptions_enable_early_renewal_via_modal';
	private const LEGACY_AUTO_RENEWAL_TOGGLE    = 'woocommerce_subscriptions_enable_auto_renewal_toggle';

	/**
	 * Register the Renewals section's settings with the registry.
	 *
	 * @param Registry $registry Registry to populate.
	 */
	public static function register( Registry $registry ): void {
		// Early renewal — §4b. Mirror WCS_Early_Renewal_Manager::is_early_renewal_enabled(): an unset
		// option resolves by store age — existing stores (subscriptions present) default OFF, fresh
		// stores default ON — rather than a flat 'yes'. We reproduce the value without the legacy
		// lazy-write, so the result holds regardless of whether any early-renewal code path has run
		// this request (derivations must stay side-effect-free; legacy storage is never written).
		$registry->register(
			'renewal_allow_early',
			static function () {
				$enabled = get_option( self::LEGACY_ENABLE_EARLY );
				if ( false === $enabled ) {
					$enabled = wcs_do_subscriptions_exist() ? 'no' : 'yes';
				}
				return 'yes' === $enabled ? 'yes' : 'no';
			}
		);
		$registry->register(
			'renewal_allow_early_via_my_account',
			static function () {
				return 'yes' === get_option( self::LEGACY_ENABLE_EARLY_VIA_MODAL, 'no' ) ? 'yes' : 'no';
			}
		);
		$registry->register(
			'renewal_allow_manual',
			static function () {
				return 'yes' === get_option( self::LEGACY_ACCEPT_MANUAL, 'no' ) ? 'yes' : 'no';
			}
		);
		$registry->register(
			'renewal_turn_off_automatic_payments',
			static function () {
				return 'yes' === get_option( self::LEGACY_TURN_OFF_AUTOMATIC, 'no' ) ? 'yes' : 'no';
			}
		);
		$registry->register(
			'renewal_allow_mode_change',
			static function () {
				return 'yes' === get_option( self::LEGACY_AUTO_RENEWAL_TOGGLE, 'no' ) ? 'yes' : 'no';
			}
		);
	}
}
