<?php

namespace Automattic\WooCommerce_Subscriptions;

use Automattic\WooCommerce_Subscriptions\Internal\Settings\Feature_Flag;
use Automattic\WooCommerce_Subscriptions\Internal\Settings\Registry;
use Automattic\WooCommerce_Subscriptions\Internal\Settings\Settings_Ui_Feature_Flag;

/**
 * Public accessor for WooCommerce Subscriptions settings.
 *
 * Subscriptions settings are moving to a two-namespace† model so the modern (settings-ui) and the
 * classic experiences can coexist and a store can safely roll back: the legacy options are left
 * untouched while the modern experience reads and writes a parallel namespace. This class is the
 * single seam that resolves which namespace is active and returns the appropriate value.
 *
 * † Namespaces here essentially meaning option prefixes.
 *
 * It lives outside `Internal\` deliberately. Third-party developers may need to read Subscriptions
 * settings without coupling to specific option keys, so the static facade — `Settings::get( 'key' )`
 * and `Settings::prefix()` — is intended as supported API. Those statics delegate to a canonical
 * instance; the instance carries its dependencies (the feature-state seam and the settings registry)
 * so the resolution logic can be unit-tested in isolation. The constructor is internal wiring, not
 * part of the consumer API.
 *
 * D-numbered decisions cited below (e.g. D7) → sdd/settings-ui-refresh/implementation/persistence-and-rollback.md
 */
class Settings {
	/**
	 * Option prefix for the classic (legacy) settings namespace.
	 */
	private const LEGACY_PREFIX = 'woocommerce_subscriptions';

	/**
	 * Option prefix for the modern (settings-ui) settings namespace.
	 *
	 * Deliberately a distinct sibling of the legacy prefix, not a sub-namespace of it: the `_modern_`
	 * segment keeps the two stores unmistakable side by side and gives the eventual sunset a clean
	 * prefix to target. Settling on this before any write path persists under it avoids a stored-data
	 * migration later.
	 */
	private const MODERN_PREFIX = 'woocommerce_subscriptions_modern';

	/**
	 * Sentinel used to distinguish "option not stored" from a stored falsy value.
	 */
	private const UNSET = '__wcs_settings_unset__';

	/**
	 * Canonical instance backing the static facade and the plugin accessor.
	 *
	 * @var Settings|null
	 */
	private static ?Settings $instance = null;

	/**
	 * Reports whether the modern settings experience is active.
	 *
	 * @var Feature_Flag
	 */
	private $feature_flag;

	/**
	 * The redesigned-settings registry.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Memoized active state of the modern experience for this instance (per request).
	 *
	 * @var bool|null
	 */
	private ?bool $modern_active = null;

	/**
	 * Constructor.
	 *
	 * @internal Constructed via {@see self::instance()} (or the plugin's `settings()` accessor). The
	 * explicit dependencies exist for injection in tests, not as consumer API.
	 *
	 * @param Feature_Flag $feature_flag Reports whether the modern settings experience is active.
	 * @param Registry     $registry     The redesigned-settings registry.
	 */
	public function __construct( Feature_Flag $feature_flag, Registry $registry ) {
		$this->feature_flag = $feature_flag;
		$this->registry     = $registry;
	}

	/**
	 * The canonical instance, lazily built with default dependencies.
	 *
	 * @return Settings
	 */
	public static function instance(): Settings {
		if ( null === self::$instance ) {
			self::$instance = new self( new Settings_Ui_Feature_Flag(), Registry::create_default() );
		}

		return self::$instance;
	}

	/**
	 * Replace (or reset) the canonical instance.
	 *
	 * @internal Test seam. Pass null to rebuild the default instance on next access.
	 *
	 * @param Settings|null $instance Instance to use as canonical.
	 */
	public static function set_instance( ?Settings $instance ): void {
		self::$instance = $instance;
	}

	/**
	 * Get a Subscriptions setting.
	 *
	 * Static facade over the canonical instance.
	 *
	 * @param string $key           Setting key: the suffix after the prefix, with or without a leading
	 *                              underscore (e.g. 'allow_switching' or '_allow_switching').
	 * @param mixed  $default_value Value returned when an *unregistered* key has no stored option. It
	 *                              does not apply to registered (redesigned) keys: those always resolve
	 *                              to a stored modern value or a derived one, so they have no unset
	 *                              state for a default to cover. See {@see self::get_value()}.
	 * @return mixed
	 */
	public static function get( string $key, $default_value = false ) {
		return self::instance()->get_value( $key, $default_value );
	}

	/**
	 * Get the active option prefix.
	 *
	 * Static facade over the canonical instance. This is the prefix used when persisting redesigned
	 * settings; per-key read resolution lives in {@see self::get_value()}.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		return self::instance()->get_prefix();
	}

	/**
	 * Instance implementation of {@see self::get()}.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Value returned only on the unregistered pass-through path; ignored
	 *                              for registered keys, which always derive or read a stored value.
	 * @return mixed
	 */
	public function get_value( string $key, $default_value = false ) {
		// Not a redesigned setting: pass straight through to the legacy option (D7).
		if ( ! $this->registry->has( $key ) ) {
			return get_option( $this->option_name( self::LEGACY_PREFIX, $key ), $default_value );
		}

		// Redesigned setting, classic experience: derive the modern value from the current legacy
		// settings. The modern key is not stored in this mode, and its name need not match a legacy
		// option, so we never read it directly here (D3/D13).
		if ( ! $this->is_modern_active() ) {
			return $this->registry->derive( $key );
		}

		// Redesigned setting, modern experience: stored modern value if present, else derive-on-read.
		$modern = get_option( $this->option_name( self::MODERN_PREFIX, $key ), self::UNSET );

		return self::UNSET === $modern ? $this->registry->derive( $key ) : $modern;
	}

	/**
	 * Instance implementation of {@see self::prefix()}.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return $this->is_modern_active() ? self::MODERN_PREFIX : self::LEGACY_PREFIX;
	}

	/**
	 * Whether the modern settings experience is active, memoized for this instance.
	 *
	 * @return bool
	 */
	private function is_modern_active(): bool {
		if ( null === $this->modern_active ) {
			$this->modern_active = $this->feature_flag->is_active();
		}

		return $this->modern_active;
	}

	/**
	 * Build the fully-qualified option name for a key under a given prefix.
	 *
	 * @param string $prefix Option prefix.
	 * @param string $key    Setting key (leading underscore optional).
	 * @return string
	 */
	private function option_name( string $prefix, string $key ): string {
		return $prefix . '_' . ltrim( $key, '_' );
	}
}
