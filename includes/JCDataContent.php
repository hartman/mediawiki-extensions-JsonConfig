<?php

namespace JsonConfig;

use Html;
use Language;
use Parser;
use ParserOptions;
use stdClass;
use Title;

/**
 * @package JsonConfig
 */
abstract class JCDataContent extends JCObjContent {

	/**
	 * Derived classes must implement this method to perform custom validation
	 * using the check(...) calls
	 */
	public function validateContent() {

		if ( !$this->thorough() ) {
			// We are not doing any modifications to the original, so no need to validate it
			return;
		}

		$this->test( 'license', JCValidators::isStringLine(), self::isValidLicense() );
		$this->testOptional( 'description', [ 'en' => '' ], JCValidators::isLocalizedString() );
		$this->testOptional( 'sources', '', JCValidators::isString() );
	}

	/** Returns a validator function to check if the value is a valid string
	 * @return callable
	 */
	public static function isValidLicense() {
		return function ( JCValue $v, array $path ) {
			global $wgJsonConfigAllowedLicenses, $wgLang;
			if ( !in_array( $v->getValue(), $wgJsonConfigAllowedLicenses, true ) ) {
				$v->error( 'jsonconfig-err-license', $path,
					$wgLang->commaList( $wgJsonConfigAllowedLicenses ) );
				return false;
			}
			return true;
		};
	}

	/**
	 * Get data as localized for the given language
	 * @param Language $lang
	 * @return mixed
	 */
	public function getLocalizedData( Language $lang ) {
		if ( !$this->isValid() ) {
			return null;
		}
		$result = new stdClass();
		$this->localizeData( $result, $lang );
		return $result;
	}

	/**
	 * Resolve any override-specific localizations, and add it to $result
	 * @param object $result
	 * @param Language $lang
	 */
	protected function localizeData( $result, Language $lang ) {
		$data = $this->getData();
		if ( property_exists( $data, 'description' ) ) {
			$result->description = JCUtils::pickLocalizedString( $data->description, $lang );
		}
		$license = $this->getLicenseObject();
		if ( $license ) {
			$text = $license['text']->inLanguage( $lang )->plain();
			if ( $license['laterVersion'] ) {
				$text = wfMessage( 'jsonconfig-license-or-later', $text )
					->inLanguage( $lang )->plain();
			}
			$result->license = (object)[
				'code' => $license['code'],
				'text' => $text,
				'url' => $license['url']->inLanguage( $lang )->plain(),
			];
		}
		if ( property_exists( $data, 'sources' ) ) {
			$result->sources = $data->sources;
		}
	}

	public function renderDescription( $lang ) {
		$description = $this->getField( 'description' );

		if ( $description && !$description->error() ) {
			$description = JCUtils::pickLocalizedString( $description->getValue(), $lang );
			$html = Html::element( 'p', [ 'class' => 'mw-jsonconfig-description' ], $description );
		} else {
			$html = '';
		}

		return $html;
	}

	/**
	 * Renders license HTML, including optional "or later version" clause
	 *     <a href="...">Creative Commons 1.0</a>, or later version
	 * @return string
	 */
	public function renderLicense() {
		$license = $this->getLicenseObject();
		if ( $license ) {
			$text = Html::element( 'a', [
				'href' => $license['url']->plain()
			], $license['text']->plain() );

			if ( $license['laterVersion'] ) {
				$text = wfMessage( 'jsonconfig-license-or-later', $text )->plain();
			}

			$html = Html::rawElement( 'p', [ 'class' => 'mw-jsonconfig-license' ], $text );
		} else {
			$html = '';
		}

		return $html;
	}

	private function getLicenseObject() {
		$license = $this->getField( 'license' );
		if ( $license && !$license->error() ) {
			$code = $license->getValue();
			$laterVersion = substr( $code, -1 ) === '+';
			$baseCode = $laterVersion ? substr( $code, 0, - 1 ) : $code;

			return [
				'code' => $code,
				'laterVersion' => $laterVersion,
				'text' => wfMessage( 'jsonconfig-license-' . $baseCode ),
				'url' => wfMessage( 'jsonconfig-license-url-' . $baseCode ),
			];
		}
		return false;
	}

	public function renderSources( Parser $parser, Title $title, $revId, ParserOptions $options ) {
		$sources = $this->getField( 'sources' );

		if ( $sources && !$sources->error() ) {
			$markup = $sources->getValue();
			$html = Html::rawElement( 'p', [ 'class' => 'mw-jsonconfig-sources' ],
				$parser->parse( $markup, $title, $options, true, true, $revId )->getRawText() );
		} else {
			$html = '';
		}

		return $html;
	}
}
