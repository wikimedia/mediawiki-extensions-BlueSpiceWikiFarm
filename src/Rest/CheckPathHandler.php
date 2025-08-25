<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstancePathGenerator;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class CheckPathHandler extends SimpleHandler {

	/**
	 * @var InstancePathGenerator
	 */
	private $pathGenerator;

	/**
	 * @param InstancePathGenerator $pathGenerator
	 */
	public function __construct( InstancePathGenerator $pathGenerator ) {
		$this->pathGenerator = $pathGenerator;
	}

	public function execute() {
		$this->assertRootCall();
		$path = $this->getValidatedParams()['path'];
		$isValid = $this->pathGenerator->checkIfValid( $path, true );
		if ( !$isValid ) {
			throw new HttpException( 'Invalid path', 409 );
		}
		return $this->getResponseFactory()->createJson( [ 'isValid' => true ] );
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
			'path' => [
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
