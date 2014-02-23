<?php
/**
 * A dummy description that describes any object. Corresponds to
 * owl:thing, the class of all abstract objects. Note that it is
 * not used for datavalues of attributes in order to support type
 * hinting in the API: descriptions of data are always
 * SMWValueDescription objects.
 * @ingroup SMWQuery
 */
class SMWThingDescription extends SMWDescription {

	public function getQueryString( $asValue = false ) {
		return $asValue ? '+' : '';
	}

	public function isSingleton() {
		return false;
	}

	public function getSize() {
		return 0; // no real condition, no size or depth
	}

	public function prune( &$maxsize, &$maxdepth, &$log ) {
		return $this;
	}

}

/**
 * Description of a single class as given by a wiki category, or of a
 * disjunction of such classes. Corresponds to (disjunctions of) atomic classes
 * in OWL and to (unions of) classes in RDF.
 * @ingroup SMWQuery
 */
class SMWClassDescription extends SMWDescription {

	/**
	 * @var array of SMWDIWikiPage
	 */
	protected $m_diWikiPages;

	/**
	 * Constructor.
	 *
	 * @param mixed $content SMWDIWikiPage or array of SMWDIWikiPage
	 *
	 * @throws Exception
	 */
	public function __construct( $content ) {
		if ( $content instanceof SMWDIWikiPage ) {
			$this->m_diWikiPages = array( $content );
		} elseif ( is_array( $content ) ) {
			$this->m_diWikiPages = $content;
		} else {
			throw new Exception( "SMWClassDescription::__construct(): parameter must be an SMWDIWikiPage object or an array of such objects." );
		}
	}

	/**
	 * @param SMWClassDescription $description
	 */
	public function addDescription( SMWClassDescription $description ) {
		$this->m_diWikiPages = array_merge( $this->m_diWikiPages, $description->getCategories() );
	}

	/**
	 * @return array of SMWDIWikiPage
	 */
	public function getCategories() {
		return $this->m_diWikiPages;
	}

	public function getQueryString( $asValue = false ) {
		$first = true;
		foreach ( $this->m_diWikiPages as $wikiPage ) {
			$wikiValue = \SMW\DataValueFactory::getInstance()->newDataItemValue( $wikiPage, null );
			if ( $first ) {
				$result = '[[' . $wikiValue->getPrefixedText();
				$first = false;
			} else {
				$result .= '||' . $wikiValue->getText();
			}
		}

		$result .= ']]';

		if ( $asValue ) {
			return ' <q>' . $result . '</q> ';
		} else {
			return $result;
		}
	}

	public function isSingleton() {
		return false;
	}

	public function getSize() {
		global $smwgQSubcategoryDepth;

		if ( $smwgQSubcategoryDepth > 0 ) {
			return 1; // disj. of cats should not cause much effort if we compute cat-hierarchies anyway!
		} else {
			return count( $this->m_diWikiPages );
		}
	}

	public function getQueryFeatures() {
		if ( count( $this->m_diWikiPages ) > 1 ) {
			return SMW_CATEGORY_QUERY | SMW_DISJUNCTION_QUERY;
		} else {
			return SMW_CATEGORY_QUERY;
		}
	}

	public function prune( &$maxsize, &$maxdepth, &$log ) {
		if ( $maxsize >= $this->getSize() ) {
			$maxsize = $maxsize - $this->getSize();
			return $this;
		} elseif ( $maxsize <= 0 ) {
			$log[] = $this->getQueryString();
			$result = new SMWThingDescription();
		} else {
			$result = new SMWClassDescription( array_slice( $this->m_diWikiPages, 0, $maxsize ) );
			$rest = new SMWClassDescription( array_slice( $this->m_diWikiPages, $maxsize ) );

			$log[] = $rest->getQueryString();
			$maxsize = 0;
		}

		$result->setPrintRequests( $this->getPrintRequests() );
		return $result;
	}

}
