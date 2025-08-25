<?php

namespace BlueSpice\WikiFarm\AccessControl;

use JsonSerializable;

class Team implements JsonSerializable {

	private int $memberCount = 0;

	/**
	 * @param int $id
	 * @param string $name
	 * @param string $description
	 */
	public function __construct(
		private readonly int $id,
		private readonly string $name,
		private readonly string $description
	) {
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @param int $cnt
	 * @return void
	 */
	public function setMemberCount( int $cnt ) {
		$this->memberCount = $cnt;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'memberCount' => $this->memberCount
		];
	}
}
