<?php

namespace BlueSpice\WikiFarm\DynamicConfig;

use MWStake\MediaWiki\Component\DynamicConfig\IDynamicConfig;

class CentralOAuthAccessToken implements IDynamicConfig {

	public const KEY = 'bs-wikifarm-central-oauth-access-token';

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return self::KEY;
	}

	/**
	 * @inheritDoc
	 */
	public function apply( string $serialized ): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function serialize( ?array $additionalData = [] ): string {
		$accessToken = $additionalData['access_token'] ?? '';
		return json_encode( [ 'access_token' => (string)$accessToken ] );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldAutoApply(): bool {
		return false;
	}

	/**
	 * @param string|null $serialized
	 * @return string|null
	 */
	public static function extractToken( ?string $serialized ): ?string {
		if ( !$serialized ) {
			return null;
		}

		$data = json_decode( $serialized, true );
		if ( !is_array( $data ) ) {
			return null;
		}

		$token = $data['access_token'] ?? null;
		if ( !is_string( $token ) || $token === '' ) {
			return null;
		}

		return $token;
	}
}
