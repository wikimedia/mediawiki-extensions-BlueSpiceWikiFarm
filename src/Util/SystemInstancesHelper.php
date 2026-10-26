<?php

namespace BlueSpice\WikiFarm\Util;

use MediaWiki\Config\Config;

class SystemInstancesHelper {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @return bool
	 */
	public function usesSharedResourcesInstance(): bool {
		$sharedInstancesPath = $this->farmConfig->get( 'sharedResourcesWikiPath' );
		return in_array( $sharedInstancesPath, $this->getSystemInstancePaths() );
	}

	/** #
	 * @return array
	 */
	public function getSystemInstancePaths(): array {
		$systemInstances = [];
		if (
			$this->farmConfig->get( 'shareUserSessions' ) &&
			$this->farmConfig->get( 'shareUsers' ) &&
			$this->farmConfig->get( 'useSharedResources' )
		) {
			$systemInstances[] = $this->farmConfig->get( 'sharedResourcesWikiPath' );
		}

		return $systemInstances;
	}
}
