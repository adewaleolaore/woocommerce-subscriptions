<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Settings;

/**
 * Describes whether the modern (settings-ui) experience is active.
 *
 * Injected into {@see \Automattic\WooCommerce_Subscriptions\Settings} so the namespace-resolution
 * branch can be exercised in tests without bootstrapping WooCommerce's feature system.
 *
 * @internal
 */
interface Feature_Flag {
	/**
	 * Whether the modern settings experience is active for the current request.
	 *
	 * @return bool
	 */
	public function is_active(): bool;
}
