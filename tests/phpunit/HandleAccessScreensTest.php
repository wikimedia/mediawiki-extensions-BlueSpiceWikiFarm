<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\Hook\BeforeInitialize\HandleAccessScreens;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\NonExistingInstanceEntity;
use MediaWiki\Config\HashConfig;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\WebRequest;
use MediaWiki\Request\WebResponse;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \BlueSpice\WikiFarm\Hook\BeforeInitialize\HandleAccessScreens
 */
class HandleAccessScreensTest extends TestCase {
	/** @var bool */
	private bool $hadFarmConfig;
	/** @var mixed */
	private $previousFarmConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->hadFarmConfig = array_key_exists( 'wgWikiFarmConfigInternal', $GLOBALS );
		$this->previousFarmConfig = $GLOBALS['wgWikiFarmConfigInternal'] ?? null;
	}

	protected function tearDown(): void {
		if ( $this->hadFarmConfig ) {
			$GLOBALS['wgWikiFarmConfigInternal'] = $this->previousFarmConfig;
		} else {
			unset( $GLOBALS['wgWikiFarmConfigInternal'] );
		}
		parent::tearDown();
	}

	/**
	 * @dataProvider provideNonReadyAccess
	 * @param bool $missing
	 * @param bool $allowed
	 * @param bool $registered
	 */
	public function testNonReadyAccess( bool $missing, bool $allowed, bool $registered ): void {
		$instance = $missing ?
			new NonExistingInstanceEntity( 'missing-wiki' ) : $this->createMock( InstanceEntity::class );
		if ( !$missing ) {
			$instance->method( 'getStatus' )->willReturn( InstanceEntity::STATUS_SUSPENDED );
		}
		$store = $this->createMock( InstanceStore::class );
		$store->method( 'getCurrentInstance' )->willReturn( $instance );
		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( $registered );
		$permissions = $this->createMock( PermissionManager::class );
		$permissions->expects( $this->once() )->method( 'userHasRight' )
			->with( $user, $missing ? 'wikifarm-createwiki' : 'read' )
			->willReturn( $allowed );
		$permissions->expects( $this->never() )->method( 'userCan' );
		$response = $this->createMock( WebResponse::class );
		$response->expects( $allowed ? $this->never() : $this->once() )
			->method( 'statusHeader' )->with( 404 );
		$response->expects( $this->once() )->method( 'header' )
			->with( 'Content-Type: text/html; charset=utf-8' )
			->willThrowException( new RuntimeException( 'response-selected' ) );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'response' )->willReturn( $response );
		$hook = new HandleAccessScreens( $store, $permissions );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'response-selected' );
		$hook->onBeforeInitialize( $this->createMock( Title::class ), null, null, $user, $request, null );
	}

	/**
	 * @return array
	 */
	public static function provideNonReadyAccess(): array {
		return [
			'missing, creator' => [ true, true, true ],
			'missing, non-creator' => [ true, false, true ],
			'suspended, reader' => [ false, true, true ],
			'suspended, public reader' => [ false, true, false ],
			'suspended, denied' => [ false, false, true ],
			'suspended, anonymous denied' => [ false, false, false ],
		];
	}

	public function testAnonymousMissingWikiRedirectsToLogin(): void {
		$store = $this->createMock( InstanceStore::class );
		$store->method( 'getCurrentInstance' )
			->willReturn( new NonExistingInstanceEntity( 'missing-wiki' ) );
		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( false );
		$permissions = $this->createMock( PermissionManager::class );
		$permissions->expects( $this->never() )->method( 'userHasRight' );
		$permissions->expects( $this->never() )->method( 'userCan' );
		$output = $this->createMock( OutputPage::class );
		$output->expects( $this->once() )->method( 'redirect' )
			->with( $this->callback( function ( $url ) {
				$this->assertSame( '/farm/w/index.php', parse_url( $url, PHP_URL_PATH ) );
				$this->assertSame( 'http://wiki.example', parse_url( $url, PHP_URL_SCHEME ) . '://' . parse_url( $url, PHP_URL_HOST ) );
				parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
				$this->assertSame( 'Special:UserLogin', $query['title'] );
				$this->assertSame( 'MissingPage', $query['returnto'] );
				$this->assertSame( 'sfr=MMT', $query['returntoquery'] );
				return true;
			} ) );
		$output->expects( $this->once() )->method( 'output' )
			->willThrowException( new RuntimeException( 'login-redirected' ) );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getQueryValues' )->willReturn( [
			'title' => 'MissingPage',
			'sfr' => 'MMT',
			'returnto' => 'Special:UserLogin',
			'returntoquery' => 'nested=parameters',
		] );
		$request->expects( $this->never() )->method( 'response' );
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )->with( 'Userlogin' )->willReturn( false );
		$title->method( 'getPrefixedText' )->willReturn( 'MissingPage' );
		$GLOBALS['wgWikiFarmConfigInternal'] = new HashConfig( [
			'globalServer' => 'http://wiki.example',
			'basePath' => '/farm',
		] );
		$hook = new HandleAccessScreens( $store, $permissions );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'login-redirected' );
		$hook->onBeforeInitialize( $title, null, $output, $user, $request, null );
	}

	public function testAnonymousLoginPageIsNotRedirectedAgain(): void {
		$store = $this->createMock( InstanceStore::class );
		$store->expects( $this->never() )->method( 'getCurrentInstance' );
		$permissions = $this->createMock( PermissionManager::class );
		$permissions->expects( $this->never() )->method( 'userHasRight' );
		$permissions->expects( $this->never() )->method( 'userCan' );
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )->with( 'Userlogin' )->willReturn( true );
		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( false );
		$output = $this->createMock( OutputPage::class );
		$output->expects( $this->never() )->method( 'redirect' );
		$request = $this->createMock( WebRequest::class );
		$hook = new HandleAccessScreens( $store, $permissions );

		$hook->onBeforeInitialize( $title, null, $output, $user, $request, null );
	}
}
