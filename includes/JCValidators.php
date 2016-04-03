<?php
namespace JsonConfig;
use Closure;

/**
 * Class JCValidators contains various static validation functions
 * @package JsonConfig
 */
class JCValidators {

	/** Call one or more validator functions with the given parameters.
	 * Validator parameters:  function ( JCValue $jcv, string $fieldPath, JCContent $content )
	 * Validator should update $jcv object with any errors it finds by using error() function. Validator
	 * may also change the value or set default/same-as-default flags.
	 * Setting status to JCValue::MISSING will delete this value (but not its parent)
	 * @param array $validators an array of validator function closures
	 * @param JCValue $value value to validate, modify, and change status of
	 * @param array $path path to the field, needed by the error messages
	 * @param JCContent $content
	 */
	public static function run( array $validators, JCValue $value, array $path, JCContent $content ) {
		if ( $validators ) {
			foreach ( $validators as $validator ) {
				if ( !call_user_func( $validator, $value, $path, $content ) ) {
					break;
				}
			}
		}
	}

	/** Returns a validator function to check if the value is a valid boolean (true/false)
	 * @return callable
	 */
	public static function isBool() {
		return function ( JCValue $v, array $path ) {
			if ( !is_bool( $v->getValue() ) ) {
				$v->error( 'jsonconfig-err-bool', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if the value is a valid string
	 * @return callable
	 */
	public static function isString() {
		return function ( JCValue $v, array $path ) {
			if ( !is_string( $v->getValue() ) ) {
				$v->error( 'jsonconfig-err-string', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if the value is a valid single line string
	 * @param int $maxlength maximum allowed string size
	 * @return callable
	 */
	public static function isStringLine( $maxlength = 400 ) {
		return function ( JCValue $v, array $path ) use ( $maxlength ) {
			$str = $v->getValue();
			if ( !JCUtils::isValidLineString( $str, $maxlength ) ) {
				$v->error( 'jsonconfig-err-stringline', $path, $maxlength );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if the value is a valid integer
	 * @return callable
	 */
	public static function isInt() {
		return function ( JCValue $v, array $path ) {
			if ( !is_int( $v->getValue() ) ) {
				$v->error( 'jsonconfig-err-integer', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if the value is a valid integer
	 * @return callable
	 */
	public static function isNumber() {
		return function ( JCValue $v, array $path ) {
			if ( !is_double( $v->getValue() ) && !is_int( $v->getValue() ) ) {
				$v->error( 'jsonconfig-err-number', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if the value is an non-associative array (list)
	 * @return callable
	 */
	public static function isList() {
		return function ( JCValue $v, array $path ) {
			if ( !JCUtils::isList( $v->getValue() ) ) {
				$v->error( 'jsonconfig-err-array', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if the value is an associative array
	 * @return callable
	 */
	public static function isDictionary() {
		return function ( JCValue $v, array $path ) {
			if ( !is_object( $v->getValue() ) ) {
				$v->error( 'jsonconfig-err-assoc-array', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if the value is an associative array
	 * @return callable
	 */
	public static function isUrl() {
		return function ( JCValue $v, array $path ) {
			if ( false === filter_var( $v->getValue(), FILTER_VALIDATE_URL ) ) {
				$v->error( 'jsonconfig-err-url', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function that will substitute missing value with default
	 * @param mixed $default value to use in case field is not present, or a closure function to generate that value
	 * @param bool $validateDefault if true, the default value will be verified by the validators
	 * @return callable
	 */
	public static function useDefault( $default, $validateDefault = true ) {
		return function ( JCValue $v ) use ( $default, $validateDefault ) {
			if ( $v->isMissing() ) {
				if ( is_object( $default ) && ( $default instanceof Closure ) ) {
					$default = $default();
				}
				$v->setValue( $default );
				return $validateDefault;
			}
			return true;
		};
	}

	/** Returns a validator function that informs that this field should be deleted
	 * @return callable
	 */
	public static function deleteField() {
		return function ( JCValue $v ) {
			$v->status( JCValue::MISSING );
			// continue executing validators - there could be a custom one that changes it further
			return true;
		};
	}

	/** Returns a validator function that will wrap a string value into an array
	 * @return callable
	 */
	public static function stringToList() {
		return function ( JCValue $v ) {
			if ( is_string( $v->getValue() ) ) {
				$v->setValue( [ $v->getValue() ] );
			}
			return true;
		};
	}

	/** Returns a validator function that will ensure the list is sorted and each value is unique
	 * @return callable
	 */
	public static function uniqueSortStrList() {
		return function ( JCValue $v ) {
			if ( !$v->isMissing() ) {
				$arr = array_unique( $v->getValue() );
				sort( $arr );
				$v->setValue( $arr );
			}
			return true;
		};
	}

	/** Returns a validator function that will ensure that the given value is a non-empty object,
	 * with each key being an allowed language code, and each value being a single line string.
	 * @param int $maxlength
	 * @return Closure
	 */
	public static function isLocalizedString( $maxlength = 400 ) {
		return function ( JCValue $jcv, array $path ) use ( $maxlength ) {
			if ( !$jcv->isMissing() ) {
				$v = $jcv->getValue();
				if ( is_object( $v ) ) {
					$v = (array)$v;
				}
				if ( JCUtils::isLocalizedArray( $v, $maxlength ) ) {
					// Sort array so that the values are sorted alphabetically
					ksort( $v );
					$jcv->setValue( (object)$v );
					return true;
				}
			}
			$jcv->error( 'jsonconfig-err-localized', $path );
			return false;
		};
	}

	/** Returns a validator function to check if the value is a valid header string
	 * @return callable
	 */
	public static function isHeaderString() {
		return function ( JCValue $v, array $path ) {
			$value = $v->getValue();
			// must be a string, begins with a letter or '_', and only has letters/digits/'_'
			if ( !is_string( $value ) || !preg_match( '/^[\pL_][\pL\pN_]*$/ui', $value ) ) {
				$v->error( 'jsonconfig-err-bad-header-string', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if value is a list of unique strings
	 * @return callable
	 */
	public static function listHasUniqueStrings() {
		return function ( JCValue $v, array $path ) {
			$value = $v->getValue();
			if ( is_array( $value ) &&
				 count( $value ) !== count( array_unique( array_map( function ( JCValue $vv ) {
					return $vv->getValue();
				}, $value ) ) )
			) {
				$v->error( 'jsonconfig-err-unique-strings', $path );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function to check if value is a list of a given size
	 * @param integer $count
	 * @param string $field
	 * @return callable
	 */
	public static function checkListSize( $count, $field ) {
		return function ( JCValue $v, array $path ) use ( $count, $field ) {
			$list = $v->getValue();
			if ( is_array( $list ) && count( $list ) !== $count ) {
				$v->error( 'jsonconfig-err-array-count', $path, count( $list ), $count, $field );
				return false;
			}
			return true;
		};
	}

	/** Returns a validator function asserting a string to be one of the valid data types.
	 * Additionally, a validator function for that data type is appended to the $validators array.
	 * @param array $validators
	 * @return Closure
	 */
	public static function validateDataType( &$validators ) {
		return function ( JCValue $v, array $path ) use ( & $validators ) {
			$value = $v->getValue();
			$validator = false;
			if ( is_string( $value ) ) {
				switch ( $value ) {
					case 'string':
						$validator = JCValidators::isStringLine();
						break;
					case 'boolean':
						$validator = JCValidators::isBool();
						break;
					case 'number':
						$validator = JCValidators::isNumber();
						break;
					case 'localized':
						$validator = JCValidators::isLocalizedString();
						break;
				}
			}
			if ( $validator === false ) {
				$v->error( 'jsonconfig-err-bad-type', $path );
				return false;
			}
			$validators[] = $validator;
			return true;
		};
	}

}
