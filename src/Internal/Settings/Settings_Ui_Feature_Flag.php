<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Settings;

use Automattic\Jetpack\Constants;
use Automattic\WooCommerce\Admin\Features\Features;

/**
 * Default {@see Feature_Flag}: reports the modern settings experience as active when WooCommerce's
 * `settings-ui` feature is enabled.
 *
 * Temporary additional gate: the modern settings experience is not yet considered stable, so it is
 * also held behind the `WCS_MODERN_SETTINGS_UI` constant. Even where Core's `settings-ui` feature is
 * on, the modern persistence/migration path stays inactive unless a site explicitly opts in with
 * `define( 'WCS_MODERN_SETTINGS_UI', 'ENABLE_WHILE_UNSTABLE' )`. The opt-in is a sentinel string, not
 * a boolean, so enabling it is a deliberate acknowledgement that the feature may still change. Remove
 * this constant gate once the experience is declared stable (tracked in
 * sdd/settings-ui-refresh/clean-up.md).
 *
 * @internal
 */
class Settings_Ui_Feature_Flag implements Feature_Flag {
	/**
	 * Whether the modern settings experience is active for the current request.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		// Temporary safeguard while the modern settings experience stabilizes. The opt-in is a sentinel
		// string rather than a boolean so that enabling it is a deliberate, eyes-open act: a stray
		// `true`/`1` or even `'off'` cannot switch it on — the value must name the acknowledgement exactly.
		if ( 'ENABLE_WHILE_UNSTABLE' !== Constants::get_constant( 'WCS_MODERN_SETTINGS_UI' ) ) {
			return false;
		}

		return class_exists( Features::class ) && Features::is_enabled( 'settings-ui' );
	}
}
