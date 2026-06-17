<?php

namespace BlueSpice\WikiFarm\ExtendedSearch\LookupModifier;

use BS\ExtendedSearch\Backend;
use BS\ExtendedSearch\Source\LookupModifier\LookupModifier;

class WikiIdAggregation extends LookupModifier {

	/**
	 * @return void
	 */
	public function apply() {
		$this->lookup->setBucketTermsAggregation( 'wiki_id' );
	}

	/**
	 * @return void
	 */
	public function undo() {
		$this->lookup->removeBucketTermsAggregation( 'wiki_id' );
	}

	/**
	 * @return array|string[]
	 */
	public function getSearchTypes() {
		return [ Backend::QUERY_TYPE_SEARCH ];
	}
}
