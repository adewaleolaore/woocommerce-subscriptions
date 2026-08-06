<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Settings;

/**
 * Registry definitions for the redesigned Add to Subscription section (settings inherited via the
 * APFS consolidation).
 *
 * Two legacy dropdowns — Products (`wcsatt_add_product_to_subscription`) and Cart Contents
 * (`wcsatt_add_cart_to_subscription`), each off / plans-only / any-product, default off — collapse
 * into two channel checkboxes plus one merged "Eligible products" dropdown (migration plan §2).
 *
 * Unlike the loss-free splits elsewhere, this is a *merge*, so eligibility can be lossy when the two
 * channels disagree. The conflict rule is most-restrictive-wins (§2c): if either enabled channel is
 * plans-only, or the channels disagree, the merged value is "subscription products only"; eligibility
 * is "any product" only when every enabled channel allowed any product. With no channel enabled the
 * dormant value is the conservative "subscription products only" (§2b).
 *
 * Note: these legacy options use the `wcsatt_` prefix, not `woocommerce_subscriptions_`. The
 * derivations read them explicitly; the flag-off pass-through in Settings does not yet account for
 * the differing prefix (tracked in clean-up.md for consumer migration).
 *
 * §2, §2b, §2c → sdd/settings-ui-refresh/context/migration.md
 *
 * @internal
 */
class Add_To_Subscription_Definitions {
	private const LEGACY_PRODUCTS = 'wcsatt_add_product_to_subscription';
	private const LEGACY_CART     = 'wcsatt_add_cart_to_subscription';

	/**
	 * Register the Add to Subscription section's settings with the registry.
	 *
	 * @param Registry $registry Registry to populate.
	 */
	public static function register( Registry $registry ): void {
		$registry->register(
			'add_to_subscription_products',
			static function () {
				return 'off' === get_option( self::LEGACY_PRODUCTS, 'off' ) ? 'no' : 'yes';
			}
		);

		$registry->register(
			'add_to_subscription_cart',
			static function () {
				return 'off' === get_option( self::LEGACY_CART, 'off' ) ? 'no' : 'yes';
			}
		);

		$registry->register(
			'add_to_subscription_eligible',
			static function () {
				$products = (string) get_option( self::LEGACY_PRODUCTS, 'off' );
				$cart     = (string) get_option( self::LEGACY_CART, 'off' );

				$eligibilities = array();
				if ( 'off' !== $products ) {
					$eligibilities[] = self::eligibility_of( $products );
				}
				if ( 'off' !== $cart ) {
					$eligibilities[] = self::eligibility_of( $cart );
				}

				// No enabled channel, any restricted channel, or a disagreement → most restrictive (§2b/§2c).
				if ( empty( $eligibilities ) || in_array( 'subscription_products', $eligibilities, true ) ) {
					return 'subscription_products';
				}

				return 'any_product';
			}
		);
	}

	/**
	 * Translate a channel's legacy eligibility value to the merged vocabulary.
	 *
	 * @param string $value Legacy channel value ('on', 'matching_schemes', 'plans_only').
	 * @return string 'any_product' or 'subscription_products'.
	 */
	private static function eligibility_of( string $value ): string {
		// 'on' = any product; 'matching_schemes' / 'plans_only' = subscription products only.
		return 'on' === $value ? 'any_product' : 'subscription_products';
	}
}
