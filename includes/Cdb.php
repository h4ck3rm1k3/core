<?php
/**
 * Native CDB file reader and writer.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * Read from a CDB file.
 * Native and pure PHP implementations are provided.
 * http://cr.yp.to/cdb.html
 */
abstract class CdbReader {
	/**
	 * Open a file and return a subclass instance
	 *
	 * @param $fileName string
	 *
	 * @return CdbReader
	 */
	public static function open( $fileName ) {
		if ( self::haveExtension() ) {
			return new CdbReader_DBA( $fileName );
		} else {
			wfDebug( "Warning: no dba extension found, using emulation.\n" );
			return new CdbReader_PHP( $fileName );
		}
	}

	/**
	 * Returns true if the native extension is available
	 *
	 * @return bool
	 */
	public static function haveExtension() {
		if ( !function_exists( 'dba_handlers' ) ) {
			return false;
		}
		$handlers = dba_handlers();
		if ( !in_array( 'cdb', $handlers ) || !in_array( 'cdb_make', $handlers ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Construct the object and open the file
	 */
	abstract function __construct( $fileName );

	/**
	 * Close the file. Optional, you can just let the variable go out of scope.
	 */
	abstract function close();

	/**
	 * Get a value with a given key. Only string values are supported.
	 *
	 * @param $key string
	 */
	abstract public function get( $key );
}

/**
 * Write to a CDB file.
 * Native and pure PHP implementations are provided.
 */
abstract class CdbWriter {
	/**
	 * Open a writer and return a subclass instance.
	 * The user must have write access to the directory, for temporary file creation.
	 *
	 * @param $fileName string
	 *
	 * @return CdbWriter_DBA|CdbWriter_PHP
	 */
	public static function open( $fileName ) {
		if ( CdbReader::haveExtension() ) {
			return new CdbWriter_DBA( $fileName );
		} else {
			wfDebug( "Warning: no dba extension found, using emulation.\n" );
			return new CdbWriter_PHP( $fileName );
		}
	}

	/**
	 * Create the object and open the file
	 *
	 * @param $fileName string
	 */
	abstract function __construct( $fileName );

	/**
	 * Set a key to a given value. The value will be converted to string.
	 * @param $key string
	 * @param $value string
	 */
	abstract public function set( $key, $value );

	/**
	 * Close the writer object. You should call this function before the object
	 * goes out of scope, to write out the final hashtables.
	 */
	abstract public function close();
}

/**
 * Reader class which uses the DBA extension
 */
class CdbReader_DBA {
	var $handle;

	function __construct( $fileName ) {
		$this->handle = dba_open( $fileName, 'r-', 'cdb' );
		if ( !$this->handle ) {
			throw new MWException( 'Unable to open CDB file "' . $fileName . '"' );
		}
	}

	function close() {
		if( isset($this->handle) ) {
			dba_close( $this->handle );
		}
		unset( $this->handle );
	}

	function get( $key ) {
		return dba_fetch( $key, $this->handle );
	}
}


/**
 * Writer class which uses the DBA extension
 */
class CdbWriter_DBA {
	var $handle, $realFileName, $tmpFileName;

	function __construct( $fileName ) {
		$this->realFileName = $fileName;
		$this->tmpFileName = $fileName . '.tmp.' . mt_rand( 0, 0x7fffffff );
		$this->handle = dba_open( $this->tmpFileName, 'n', 'cdb_make' );
		if ( !$this->handle ) {
			throw new MWException( 'Unable to open CDB file for write "' . $fileName . '"' );
		}
	}

	function set( $key, $value ) {
		return dba_insert( $key, $value, $this->handle );
	}

	function close() {
		if( isset($this->handle) ) {
			dba_close( $this->handle );
		}
		if ( wfIsWindows() ) {
			unlink( $this->realFileName );
		}
		if ( !rename( $this->tmpFileName, $this->realFileName ) ) {
			throw new MWException( 'Unable to move the new CDB file into place.' );
		}
		unset( $this->handle );
	}

	function __destruct() {
		if ( isset( $this->handle ) ) {
			$this->close();
		}
	}
}

