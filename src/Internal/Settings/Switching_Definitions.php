<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Settings;

/**
 * Registry definitions for the redesigned Switching section.
 *
 * Translates the legacy Switching options into the modern (decomposed) keys, and provides the
 * legacy → modern derivation used by derive-on-read while a modern value is not yet stored. The
 * behavioural mapping follows the redesign's migration plan; the modern value vocabulary is defined
 * here (it is what the modern settings UI reads and writes).
 *
 * Legacy → modern at a glance:
 *  - `_allow_switching` (composite no|variable|grouped|variable_grouped) → three checkboxes.
 *  - `_allow_switching_product_plans` (yes/no, default yes) → the plans checkbox.
 *  - `_apportion_recurring_price` (5 values) → first-billing behaviour + Virtual/Physical pair.
 *  - `_apportion_sign_up_fee` (3 values) → sign-up fee behaviour.
 *  - `_apportion_length` (3 values) → fixed-term behaviour + Virtual/Physical pair.
 *  - `_switch_button_text` → button text (label preserved; see note below).
 *
 * @internal
 */
class Switching_Definitions {
	private const LEGACY_ALLOW_SWITCHING       = 'woocommerce_subscriptions_allow_switching';
	private const LEGACY_ALLOW_SWITCHING_PLANS = 'woocommerce_subscriptions_allow_switching_product_plans';
	private const LEGACY_APPORTION_RECURRING   = 'woocommerce_subscriptions_apportion_recurring_price';
	private const LEGACY_APPORTION_SIGN_UP_FEE = 'woocommerce_subscriptions_apportion_sign_up_fee';
	private const LEGACY_APPORTION_LENGTH      = 'woocommerce_subscriptions_apportion_length';
	private const LEGACY_SWITCH_BUTTON_TEXT    = 'woocommerce_subscriptions_switch_button_text';

	/**
	 * Register the Switching section's settings with the registry.
	 *
	 * @param Registry $registry Registry to populate.
	 */
	public static function register( Registry $registry ): void {
		// Allow switching — the composite splits into two checkboxes; plans is a separate option.
		$registry->register(
			'switch_allow_variations',
			static function () {
				return false !== strpos( (string) get_option( self::LEGACY_ALLOW_SWITCHING, 'no' ), 'variable' ) ? 'yes' : 'no';
			}
		);
		$registry->register(
			'switch_allow_grouped',
			static function () {
				return false !== strpos( (string) get_option( self::LEGACY_ALLOW_SWITCHING, 'no' ), 'grouped' ) ? 'yes' : 'no';
			}
		);
		$registry->register(
			'switch_allow_plans',
			static function () {
				return 'yes' === get_option( self::LEGACY_ALLOW_SWITCHING_PLANS, 'yes' ) ? 'yes' : 'no';
			}
		);

		// First billing behaviour (was "Prorate Recurring Payment") → behaviour + product-type pair.
		$registry->register(
			'switch_first_billing_behavior',
			static function () {
				switch ( get_option( self::LEGACY_APPORTION_RECURRING, 'no' ) ) {
					case 'virtual-upgrade':
					case 'yes-upgrade':
						return 'upgrades';
					case 'virtual':
					case 'yes':
						return 'upgrades_and_downgrades';
					default:
						return 'full';
				}
			}
		);
		$registry->register(
			'switch_first_billing_virtual',
			static function () {
				// Virtual applies to every prorating value; the "Never" value shows no product types.
				return 'no' === get_option( self::LEGACY_APPORTION_RECURRING, 'no' ) ? 'no' : 'yes';
			}
		);
		$registry->register(
			'switch_first_billing_physical',
			static function () {
				// Physical applies only to the "All Subscription Products" values.
				return in_array( get_option( self::LEGACY_APPORTION_RECURRING, 'no' ), array( 'yes-upgrade', 'yes' ), true ) ? 'yes' : 'no';
			}
		);

		// Sign-up fee behaviour (was "Prorate Sign up Fee").
		$registry->register(
			'switch_signup_fee_behavior',
			static function () {
				switch ( get_option( self::LEGACY_APPORTION_SIGN_UP_FEE, 'no' ) ) {
					case 'full':
						return 'full';
					case 'yes':
						return 'prorate';
					default:
						return 'none';
				}
			}
		);

		// Fixed-term behaviour (was "Prorate Subscription Length") → behaviour + product-type pair.
		$registry->register(
			'switch_fixed_term_behavior',
			static function () {
				return 'no' === get_option( self::LEGACY_APPORTION_LENGTH, 'no' ) ? 'none' : 'prorate';
			}
		);
		$registry->register(
			'switch_fixed_term_virtual',
			static function () {
				return 'no' === get_option( self::LEGACY_APPORTION_LENGTH, 'no' ) ? 'no' : 'yes';
			}
		);
		$registry->register(
			'switch_fixed_term_physical',
			static function () {
				return 'yes' === get_option( self::LEGACY_APPORTION_LENGTH, 'no' ) ? 'yes' : 'no';
			}
		);

		// Switch button text — preserve the current visible label. The unset default is wrapped in
		// __() to mirror the legacy read (class-wc-subscriptions-switcher.php), so a localized store
		// with no stored value derives its translated label rather than the English source string.
		// The modern "Switch" default applies to empty/new modern values via the modern field's
		// default, not to this derivation.
		$registry->register(
			'switch_button_text',
			static function () {
				return get_option( self::LEGACY_SWITCH_BUTTON_TEXT, __( 'Upgrade or Downgrade', 'woocommerce-subscriptions' ) );
			}
		);
	}
}
