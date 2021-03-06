<?php
namespace JsonConfig;

use ApiBase;
use ApiResult;
use ApiFormatJson;

/**
 * Get localized json data, similar to Lua's mw.data.get() function
 */
class JCDataApi extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$jct = JCSingleton::parseTitle( $params['title'], NS_DATA );
		if ( !$jct ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['title'] ) ] );
			} else {
				$this->dieUsageMsg( [ 'invalidtitle', $params['title'] ] );
			}
		}

		$data = JCSingleton::getContent( $jct );
		if ( !$data ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError(
					[ 'apierror-invalidtitle', wfEscapeWikiText( $jct->getPrefixedText() ) ]
				);
			} else {
				$this->dieUsageMsg( [ 'invalidtitle', $jct ] );
			}
		} elseif ( !method_exists( $data, 'getLocalizedData' ) ) {
			$data = $data->getData();
		} else {
			/** @var JCDataContent $data */
			$data = $data->getLocalizedData( $this->getLanguage() );
		}

		// Armor any API metadata in $data
		$data = ApiResult::addMetadataToResultVars( (array)$data, is_object( $data ) );

		$this->getResult()->addValue( null, $this->getModuleName(), $data );

		$this->getMain()->setCacheMaxAge( 24 * 60 * 60 ); // seconds
		$this->getMain()->setCacheMode( 'public' );
	}

	public function getAllowedParams() {
		return [
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=jsondata&formatversion=2&format=jsonfm&title=Sample.tab'
				=> 'apihelp-jsondata-example-1',
			'action=jsondata&formatversion=2&format=jsonfm&title=Sample.tab&uselang=fr'
				=> 'apihelp-jsondata-example-2',
		];
	}

	public function isInternal() {
		return true;
	}
}
