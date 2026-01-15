<?php

namespace BlueSpice\WikiFarm\Storage;

use MWStake\MediaWiki\Component\FileStorageUtilities\TransactionBase;

class InstanceTransaction extends TransactionBase {

	/**
	 * @param string $op
	 * @param string $src
	 * @param string $dst
	 * @param bool $prepare
	 * @param array $exclude
	 * @return InstanceTransaction
	 */
	private function instanceDirectoryOperation(
		string $op, string $src, string $dst, bool $prepare, array $exclude = []
	): InstanceTransaction {
		$fl = $this->fileBackend->getFileList( [
			'dir' => $this->makeInstancePath( $src ), 'topOnly' => false
		] );
		foreach ( $fl as $fileLocation ) {
			$bits = explode( '/', $fileLocation );
			$filename = array_pop( $bits );
			if ( in_array( $filename, $exclude ) ) {
				continue;
			}
			$path = implode( '/', $bits );
			if ( $path && $prepare ) {
				$this->operations[] = [
					'op' => 'prepare',
					'dir' => $this->makeInstancePath( $dst, $path ),
				];
			}
			$opDef = [
				'op' => $op,
				'src' => $this->makeInstancePath( $src, $fileLocation ),
			];
			if ( $dst ) {
				$opDef['dst'] = $this->makeInstancePath( $dst, $fileLocation );
			}

			$this->operations[] = $opDef;
		}

		return $this;
	}

	/**
	 * @param string $src
	 * @param string $dst
	 * @return InstanceTransaction
	 */
	public function copyInstance( string $src, string $dst ): InstanceTransaction {
		return $this->instanceDirectoryOperation( 'copy', $src, $dst, true, [ '.smw.json' ] );
	}

	/**
	 * @param string $src
	 * @param string $dst
	 * @return InstanceTransaction
	 */
	public function moveInstanceDirectory( string $src, string $dst ): InstanceTransaction {
		 $this->instanceDirectoryOperation( 'move', $src, $dst, true );
		 $this->addClean( $this->makeInstancePath( $src ) );

		 return $this;
	}

	/**
	 * @param string $src
	 * @return InstanceTransaction
	 */
	public function deleteInstanceDirectory( string $src ): InstanceTransaction {
		$this->instanceDirectoryOperation( 'delete', $src, '', false );
		$this->addClean( $this->makeInstancePath( $src ) );
		return $this;
	}

	/**
	 * @param string $sourcePath
	 * @param string $targetFile
	 * @param array $opts
	 * @return $this
	 */
	public function storeToArchive( string $sourcePath, string $targetFile, array $opts = [] ): InstanceTransaction {
		$this->operations[] = array_merge( [
			'op' => 'store',
			'src' => $sourcePath,
			'dst' => $this->makeArchiveInstancePath( $targetFile )
		], $opts );

		return $this;
	}

	/**
	 * @param string $root
	 * @param string $file
	 * @return string
	 */
	public function makeInstancePath( string $root, string $file = '' ): string {
		$backendName = $this->fileBackend->getName();
		$root = trim( $root, '/' );
		if ( !$file ) {
			return "mwstore://$backendName/instances-public/$root";
		}
		return "mwstore://$backendName/instances-public/$root/" . trim( $file, '/' );
	}

	/**
	 * @param string $file
	 * @return string
	 */
	public function makeArchiveInstancePath( string $file ): string {
		$backendName = $this->fileBackend->getName();
		$file = trim( $file, '/' );
		if ( !$file ) {
			return "mwstore://$backendName/archive-public";
		}
		return "mwstore://$backendName/archive-public/$file";
	}
}
