<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Settings;

use InvalidArgumentException;

/**
 * Registry of redesigned settings: the keys that opt into the modern (dual-namespace) behaviour and
 * how each derives its value from the legacy options when no modern value has been stored yet.
 *
 * Registration is the opt-in to dual-namespace behaviour. A key that is not registered is, by
 * definition, not a redesigned setting; callers fall through to the legacy option transparently
 * (handled in {@see \Automattic\WooCommerce_Subscriptions\Settings}). This is what keeps non-settings
 * state (version markers, install flags, …) on the legacy prefix.
 *
 * Entries for each redesigned section are added incrementally — Switching first.
 *
 * @internal
 */
class Registry {
	/**
	 * Registered settings, keyed by the bare setting key (suffix without a leading underscore).
	 *
	 * Each value is a derivation callable mapping the current legacy option(s) to this setting's
	 * value, used by derive-on-read when the modern value is absent.
	 *
	 * @var array<string, callable>
	 */
	private array $definitions = array();

	/**
	 * Build the default registry, populated with every redesigned section's definitions.
	 *
	 * @return self
	 */
	public static function create_default(): self {
		$registry = new self();

		Switching_Definitions::register( $registry );
		Renewals_Definitions::register( $registry );
		Suspensions_Definitions::register( $registry );
		Add_To_Subscription_Definitions::register( $registry );
		Gifting_Definitions::register( $registry );
		// Future redesigned sections register their definitions here.

		return $registry;
	}

	/**
	 * Register a redesigned setting and how to derive its value from the legacy option(s).
	 *
	 * @param string   $key    Setting key (the suffix after the prefix; leading underscore optional).
	 * @param callable $derive Derivation mapping the current legacy option(s) to this setting's value.
	 */
	public function register( string $key, callable $derive ): void {
		$this->definitions[ $this->normalize( $key ) ] = $derive;
	}

	/**
	 * Whether a key is a registered (redesigned) setting.
	 *
	 * @param string $key Setting key (leading underscore optional).
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->definitions[ $this->normalize( $key ) ] );
	}

	/**
	 * Derive a registered setting's value from the current legacy options.
	 *
	 * @param string $key Setting key. Must be registered ({@see self::has()}).
	 * @return mixed
	 * @throws InvalidArgumentException If the key is not registered.
	 */
	public function derive( string $key ) {
		$normalized = $this->normalize( $key );

		if ( ! isset( $this->definitions[ $normalized ] ) ) {
			throw new InvalidArgumentException( 'No derivation registered for setting: ' . esc_html( $key ) );
		}

		return ( $this->definitions[ $normalized ] )();
	}

	/**
	 * Reduce a key to its bare suffix (no leading underscore) for consistent lookup.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function normalize( string $key ): string {
		return ltrim( $key, '_' );
	}
}
