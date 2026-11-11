<?php

namespace BlueSpice\WikiFarm\Tests\MCP;

use BlueSpice\WikiFarm\MCP\ListWikis;
use BlueSpice\WikiFarm\MCP\ListWikisExecution;
use MediaWiki\Extension\WikiMCPTools\ExecutionHandler\DirectExecution;
use PHPUnit\Framework\TestCase;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * @covers \BlueSpice\WikiFarm\MCP\ListWikis
 */
class ListWikisTest extends TestCase {

	public function testKey() {
		$this->assertSame( 'listWikis', ( new ListWikis() )->getKey() );
	}

	public function testTakesNoInput() {
		$definition = ( new ListWikis() )->getDefinition()->jsonSerialize();

		$this->assertSame( [], $definition['inputSchema']->jsonSerialize()['properties'] );
		$this->assertNotEmpty( $definition['description'] );
	}

	public function testOutputListsTheWikiFields() {
		$output = ( new ListWikis() )->getDefinition()->jsonSerialize()['outputSchema']->jsonSerialize();
		$item = $output['properties']['wikis']['items'];

		$this->assertSame( [ 'wikis' ], $output['required'] );
		$this->assertSame(
			[ 'wiki_id', 'sitename', 'description', 'path', 'url', 'is_main' ],
			array_keys( $item['properties'] )
		);
		$this->assertSame( array_keys( $item['properties'] ), $item['required'] );
	}

	public function testRunsDirectlyWithTheFarmServices() {
		$method = ( new ListWikis() )->getExecutionMethod();
		$this->assertInstanceOf( DirectExecution::class, $method );

		$objectFactory = $this->createMock( ObjectFactory::class );
		$objectFactory->expects( $this->once() )->method( 'createObject' )->willReturnCallback(
			function ( array $spec ) {
				$this->assertSame( ListWikisExecution::class, $spec['class'] );
				$this->assertSame(
					[ 'BlueSpiceWikiFarm.WikiMap', 'BlueSpiceWikiFarm.AccessControlStore', 'BlueSpiceWikiFarm._Config' ],
					$spec['services']
				);
				return $this->createMock( ListWikisExecution::class );
			}
		);
		$method->getHandler( $objectFactory );
	}
}
