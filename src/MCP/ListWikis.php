<?php

namespace BlueSpice\WikiFarm\MCP;

use MediaWiki\Extension\WikiMCPTools\ExecutionHandler\DirectExecution;
use MediaWiki\Extension\WikiMCPTools\FieldSchema;
use MediaWiki\Extension\WikiMCPTools\IMcpTool;
use MediaWiki\Extension\WikiMCPTools\IMcpToolExecutionHandler;
use MediaWiki\Extension\WikiMCPTools\ToolDefinition;

class ListWikis implements IMcpTool {

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'listWikis';
	}

	/**
	 * @return ToolDefinition
	 */
	public function getDefinition(): ToolDefinition {
		$wikiSchema = ( new FieldSchema() )
			->addString( 'wiki_id', 'ID of the wiki, to be used as "wiki_id" in other tools' )
			->addString( 'sitename', 'Name of the wiki' )
			->addString( 'description', 'Description of the wiki, may be empty' )
			->addString( 'path', 'Path of the wiki in the farm' )
			->addUri( 'url', 'URL of the wiki' )
			->addBoolean( 'is_main', 'Whether this is the main wiki of the farm' )
			->setRequired( [ 'wiki_id', 'sitename', 'description', 'path', 'url', 'is_main' ] );

		$outputSchema = ( new FieldSchema() )
			->addArray( 'wikis', 'Wikis the current user can read', $wikiSchema )
			->setRequired( [ 'wikis' ] );

		return new ToolDefinition(
			'List wikis',
			'List the wikis of this wiki farm that the current user can read, with their wiki_id, name ' .
				'and description. Use it to find the wiki_id of a wiki the user refers to by name.',
			new FieldSchema(),
			$outputSchema
		);
	}

	/**
	 * @return IMcpToolExecutionHandler
	 */
	public function getExecutionMethod(): IMcpToolExecutionHandler {
		return new DirectExecution( [
			'class' => ListWikisExecution::class,
			'services' => [
				'BlueSpiceWikiFarm.WikiMap',
				'BlueSpiceWikiFarm.AccessControlStore',
				'BlueSpiceWikiFarm._Config'
			]
		] );
	}
}
