<?php

namespace JsonConfig;

use FormatJson;
use MWHttpRequest;

/**
 * Various useful utility functions (all static)
 */
class JCUtils {

	/**
	 * Uses wfLogWarning() to report an error. All complex arguments are escaped with FormatJson::encode()
	 * @param string $msg
	 */
	public static function warn( $msg, $vals, $query = false ) {
		if ( !is_array( $vals ) ) {
			$vals = array( $vals );
		}
		if ( $query ) {
			foreach ( $query as $k => &$v ) {
				if ( stripos( $k, 'password' ) !== false ) {
					$v = '***';
				}
			}
			$vals['query'] = $query;
		}
		$isFirst = true;
		foreach ( $vals as $k => &$v ) {
			if ( $isFirst ) {
				$isFirst = false;
				$msg .= ': ';
			} else {
				$msg .= ', ';
			}
			if ( is_string( $k ) ) {
				$msg .= $k . '=';
			}
			if ( is_string( $v ) || is_int( $v ) ) {
				$msg .= $v;
			} else {
				$msg .= FormatJson::encode( $v );
			}
		}
		wfLogWarning( $msg );
	}

	/** Init HTTP request object to make requests to the API, and login
	 * @param string $url
	 * @param string $username
	 * @param string $password
	 * @throws \MWException
	 * @return \CurlHttpRequest|\PhpHttpRequest|false
	 */
	public static function initApiRequestObj( $url, $username, $password ) {
		$apiUri = wfAppendQuery( $url, array( 'format' => 'json' ) );
		$options = array(
			'timeout' => 3,
			'connectTimeout' => 'default',
			'method' => 'POST',
		);
		$req = MWHttpRequest::factory( $apiUri, $options );

		if ( $username && $password ) {
			$query = array(
				'action' => 'login',
				'lgname' => $username,
				'lgpassword' => $password,
			);
			$res = self::callApi( $req, $query, 'login' );
			if ( $res !== false ) {
				if ( isset( $res['login']['token'] ) ) {
					$query['lgtoken'] = $res['login']['token'];
					$res = self::callApi( $req, $query, 'login with token' );
				}
			}
			if ( $res === false ) {
				$req = false;
			} elseif ( !isset( $res['login']['result'] ) ||
			     $res['login']['result'] !== 'Success'
			) {
				self::warn( 'Failed to login', array(
						'url' => $url,
						'user' => $username,
						'result' => isset( $res['login']['result'] ) ? $res['login']['result'] : '???'
					) );
				$req = false;
			}
		}
		return $req;
	}

	/**
	 * Make an API call on a given request object and warn in case of failures
	 * @param \CurlHttpRequest|\PhpHttpRequest $req logged-in session
	 * @param array $query api call parameters
	 * @param string $debugMsg extra message for debug logs in case of failure
	 * @return array|false api result or false on error
	 */
	public static function callApi( $req, $query, $debugMsg ) {
		$req->setData( $query );
		$status = $req->execute();
		if ( !$status->isGood() ) {
			self::warn( 'API call failed to ' . $debugMsg, array( 'status' => $status->getWikiText() ),
				$query );
			return false;
		}
		$res = FormatJson::decode( $req->getContent(), true );
		if ( isset( $res['warnings'] ) ) {
			self::warn( 'API call had warnings trying to ' . $debugMsg,
				array( 'warnings' => $res['warnings'] ), $query );
		}
		if ( isset( $res['error'] ) ) {
			self::warn( 'API call failed trying to ' . $debugMsg, array( 'error' => $res['error'] ), $query );
			return false;
		}
		return $res;
	}

	/**
	 * Helper function to check if the given value is an array,
	 * and all keys are integers (non-associative array)
	 * @param array $array array to check
	 * @return bool
	 */
	public static function isList( $array ) {
		return is_array( $array ) &&
		       count( array_filter( array_keys( $array ), 'is_int' ) ) === count( $array );
	}

	/**
	 * Helper function to check if the given value is an array,
	 * and all keys are strings (associative array)
	 * @param array $array array to check
	 * @return bool
	 */
	public static function isDictionary( $array ) {
		return is_array( $array ) &&
		       count( array_filter( array_keys( $array ), 'is_string' ) ) === count( $array );
	}

	/**
	 * Helper function to check if the given value is an array and if each value in it is a string
	 * @param array $array array to check
	 * @return bool
	 */
	public static function allValuesAreStrings( $array ) {
		return is_array( $array ) && count( array_filter( $array, 'is_string' ) ) === count( $array );
	}
}
