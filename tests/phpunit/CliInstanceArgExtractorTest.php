<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\CliInstanceArgExtractor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BlueSpice\WikiFarm\CliInstanceArgExtractor
 */
class CliInstanceArgExtractorTest extends TestCase {

	/**
	 *
	 * @param array $givenArgs
	 * @param string $expectedInstanceName
	 * @param array $expectedArgs
	 * @return void
	 * @dataProvider provideTestExtractInstanceNameData
	 */
	public function testExtractInstanceName( $givenArgs, $expectedInstanceName, $expectedArgs ) {
		$extractor = new CliInstanceArgExtractor( $givenArgs );
		$actualInstanceName = $extractor->extractInstanceIdentifier();

		$this->assertEquals( $expectedInstanceName, $actualInstanceName );
		$this->assertEquals( $expectedArgs, $givenArgs );
	}

	public function provideTestExtractInstanceNameData() {
		return [
			'no-instance-name' => [
				[ 'maintenance/update.php', '--quick' ],
				'',
				[ 'maintenance/update.php', '--quick' ]
			],
			'instance-name-without-equals' => [
				[ 'maintenance/update.php', '--sfr', 'Test1', '--quick' ],
				'Test1',
				[ 'maintenance/update.php', '--quick' ]
			],
			'instance-name--with-equals' => [
				[ 'maintenance/update.php', '--quick', '--sfr=Test1' ],
				'Test1',
				[ 'maintenance/update.php', '--quick' ]
			],
			'instance-name--with-equals-and-quotes' => [
				[ 'maintenance/update.php', '--quick', '--sfr="Test1"' ],
				'Test1',
				[ 'maintenance/update.php', '--quick' ]
			],
			'instance-name-equals-and-quote-and-spaces' => [
				[ 'maintenance/update.php', '--sfr = "Test1"', '--quick' ],
				'Test1',
				[ 'maintenance/update.php', '--quick' ]
			]
		];
	}
}
