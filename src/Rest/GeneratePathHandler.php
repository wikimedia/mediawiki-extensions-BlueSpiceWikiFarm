<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstancePathGenerator;
use BlueSpice\WikiFarm\InstanceStore;
use Exception;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class GeneratePathHandler extends SimpleHandler {

	/**
	 * @var InstancePathGenerator
	 */
	private $pathGenerator;

	/**
	 * @var InstanceStore
	 */
	private $instanceStore;

	/**
	 * @param InstancePathGenerator $pathGenerator
	 * @param InstanceStore $instanceStore
	 */
	public function __construct( InstancePathGenerator $pathGenerator, InstanceStore $instanceStore ) {
		$this->pathGenerator = $pathGenerator;
		$this->instanceStore = $instanceStore;
	}

	public function execute() {
		$this->assertRootCall();
		$name = $this->getValidatedParams()['name'];
		try {
			$path = $this->pathGenerator->generateFromName( $name );
			$nameExists = $this->instanceStore->nameExists( $name );
		} catch ( Exception $e ) {
			throw new HttpException( $e->getMessage(), 400 );
		}

		return $this->getResponseFactory()->createJson( [
			'path' => $path,
			'nameExists' => $nameExists
		] );
	}

	/**
	 * @return bool
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'name' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function assertRootCall() {
		if ( !defined( 'FARMER_IS_ROOT_WIKI_CALL' ) || !FARMER_IS_ROOT_WIKI_CALL ) {
			throw new HttpException( 'This call is only available from the root instance', 409 );
		}
	}
}
