<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\InstanceTemplateProvider;
use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Response;
use Throwable;
use Wikimedia\ParamValidator\ParamValidator;

class CreateInstanceHandler extends InstanceHandler {

	/**
	 * @var LanguageFactory
	 */
	private $languageFactory;

	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @param InstanceManager $instanceManager
	 * @param PermissionManager $permissionManager
	 * @param LanguageFactory $languageFactory
	 * @param Config $mainConfig
	 */
	public function __construct(
		InstanceManager $instanceManager, PermissionManager $permissionManager,
		LanguageFactory $languageFactory, Config $mainConfig
	) {
		parent::__construct( $instanceManager, $permissionManager );
		$this->languageFactory = $languageFactory;
		$this->mainConfig = $mainConfig;
	}

	/**
	 * @param string $instanceName
	 * @param array $bodyParams
	 * @return Response
	 * @throws Exception
	 */
	protected function doExecute( string $instanceName, array $bodyParams ): Response {
		$metadata = $bodyParams['metadata'] ?? [];
		/*if ( $metadata ) {
			$metadata = json_decode( $metadata, true );
		}*/
		if ( !is_array( $metadata ) ) {
			$metadata = [];
		}
		$config = $bodyParams['config'] ?? [];
		if ( !is_array( $config ) ) {
			$config = [];
		}
		$lang = $this->verifyLanguage( $bodyParams['language'] );
		$res = $this->executeAction( $instanceName, $bodyParams['displayName'], [
			'lang' => $lang,
			'userName' => $this->getCanonicalActorName(),
			'metadata' => $metadata,
			'config' => $config,
			'template' => $this->verifyTemplate( $bodyParams['template'] ?? '' )
		] );

		return $this->getResponseFactory()->createJson( $res );
	}

	/**
	 * @param string $instanceName
	 * @param string $displayName
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	protected function executeAction( string $instanceName, string $displayName, array $options ): array {
		$pid = $this->getInstanceManager()->createInstance( $instanceName, $displayName, $options );
		if ( $pid ) {
			return [
				'process' => $pid,
				'instanceUrl' => $this->getInstanceManager()->getUrlForNewInstance( $instanceName )
			];
		}
		throw new Exception( 'Failed to start creation process' );
	}

	public function getBodyParamSettings(): array {
		return [
			'displayName' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'description' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'language' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'metadata' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'config' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'template' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			]
		];
	}

	/**
	 * @return string[]
	 */
	protected function getRequiredPermissions(): array {
		return [ 'wikifarm-createwiki' ];
	}

	/**
	 * @param mixed $lang
	 * @return string
	 */
	protected function verifyLanguage( $lang ): string {
		if ( !is_string( $lang ) ) {
			return $this->mainConfig->get( 'LanguageCode' );
		}
		try {
			$this->languageFactory->getLanguage( $lang );
			return $lang;
		} catch ( Throwable $exception ) {
			return $this->mainConfig->get( 'LanguageCode' );
		}
	}

	/**
	 * @param string $template
	 * @return string
	 * @throws Exception
	 */
	private function verifyTemplate( string $template ): string {
		if ( !$template ) {
			return '';
		}
		$provider = new InstanceTemplateProvider( MediaWikiServices::getInstance()->getMainConfig() );
		$template = $provider->getTemplateSource( $template );
		if ( !file_exists( $template ) ) {
			throw new Exception( 'Template source file does not exist: ' . $template );
		}
		return $template;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function getCanonicalActorName(): string {
		$actor = RequestContext::getMain()->getUser();
		if ( !$actor || !$actor->isRegistered() ) {
			throw new Exception( 'Invalid actor' );
		}
		return $actor->getName();
	}

}
