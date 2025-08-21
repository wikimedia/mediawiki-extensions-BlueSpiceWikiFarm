<?php

namespace BlueSpice\WikiFarm\Session;

class SessionCache extends \SqlBagOStuff {
	/** @var string */
	protected $tableName = 'wikifarm_session_cache';
}
