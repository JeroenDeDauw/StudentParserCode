<?php
/**
 * This file contains a class for parsing inline query strings.
 * @file
 * @ingroup SMWQuery
 * @author Markus Krötzsch
 */

/**
 * Objects of this class are in charge of parsing a query string in order
 * to create an SMWDescription. The class and methods are not static in order
 * to more cleanly store the intermediate state and progress of the parser.
 * @ingroup SMWQuery
 */
abstract class SMWQueryParserState {

	protected $m_sepstack; // list of open blocks ("parentheses") that need closing at current step
	protected $m_curstring; // remaining string to be parsed (parsing eats query string from the front)
	protected $m_errors; // empty array if all went right, array of strings otherwise
	protected $m_defaultns; // description of the default namespace restriction, or NULL if not used

	protected $m_categoryprefix; // cache label of category namespace . ':'
	protected $m_conceptprefix; // cache label of concept namespace . ':'
	protected $m_categoryPrefixCannonical; // cache canonnical label of category namespace . ':'
	protected $m_conceptPrefixCannonical; // cache canonnical label of concept namespace . ':'
	protected $m_queryfeatures; // query features to be supported, format similar to $smwgQFeatures

	public function __construct( $queryFeatures = false ) {
		global $wgContLang, $smwgQFeatures;

		$this->m_categoryprefix = $wgContLang->getNsText( NS_CATEGORY ) . ':';
		$this->m_conceptprefix = $wgContLang->getNsText( SMW_NS_CONCEPT ) . ':';
		$this->m_categoryPrefixCannonical = 'Category:';
		$this->m_conceptPrefixCannonical = 'Concept:';

		$this->m_defaultns = null;
		$this->m_queryfeatures = $queryFeatures === false ? $smwgQFeatures : $queryFeatures;
	}

	/**
	 * Provide an array of namespace constants that are used as default restrictions.
	 * If NULL is given, no such default restrictions will be added (faster).
	 */
	public function setDefaultNamespaces( $namespaceArray ) {
		$this->m_defaultns = null;

		if ( !is_null( $namespaceArray ) ) {
			foreach ( $namespaceArray as $ns ) {
				$this->m_defaultns = $this->addDescription(
					$this->m_defaultns,
					new SMWNamespaceDescription( $ns ),
					false
				);
			}
		}
	}

	/**
	 * Compute an SMWDescription from a query string. Returns whatever descriptions could be
	 * wrestled from the given string (the most general result being SMWThingDescription if
	 * no meaningful condition was extracted).
	 *
	 * @param string $queryString
	 *
	 * @return SMWDescription
	 */
	public function getQueryDescription( $queryString ) {
		wfProfileIn( 'SMWQueryParser::getQueryDescription (SMW)' );

		$this->m_errors = array();
		$this->m_curstring = $queryString;
		$this->m_sepstack = array();
		$setNS = false;
		$result = $this->getSubqueryDescription( $setNS );

		if ( !$setNS ) { // add default namespaces if applicable
			$result = $this->addDescription( $this->m_defaultns, $result );
		}

		if ( is_null( $result ) ) { // parsing went wrong, no default namespaces
			$result = new SMWThingDescription();
		}

		wfProfileOut( 'SMWQueryParser::getQueryDescription (SMW)' );

		return $result;
	}

	/**
	 * Return array of error messages (possibly empty).
	 *
	 * @return array
	 */
	public function getErrors() {
		return $this->m_errors;
	}

	/**
	 * Return error message or empty string if no error occurred.
	 *
	 * @return string
	 */
	public function getErrorString() {
		return smwfEncodeMessages( $this->m_errors );
	}

	/**
	 * Compute an SMWDescription for current part of a query, which should
	 * be a standalone query (the main query or a subquery enclosed within
	 * "\<q\>...\</q\>". Recursively calls similar methods and returns NULL upon error.
	 *
	 * The call-by-ref parameter $setNS is a boolean. Its input specifies whether
	 * the query should set the current default namespace if no namespace restrictions
	 * were given. If false, the calling super-query is happy to set the required
	 * NS-restrictions by itself if needed. Otherwise the subquery has to impose the defaults.
	 * This is so, since outermost queries and subqueries of disjunctions will have to set
	 * their own default restrictions.
	 *
	 * The return value of $setNS specifies whether or not the subquery has a namespace
	 * specification in place. This might happen automatically if the query string imposes
	 * such restrictions. The return value is important for those callers that otherwise
	 * set up their own restrictions.
	 *
	 * Note that $setNS is no means to switch on or off default namespaces in general,
	 * but just controls query generation. For general effect, the default namespaces
	 * should be set to NULL.
	 *
	 * @return SMWDescription or null
	 */
	abstract protected function getDescription( $chunk, &$setNS ) ;

	

	
	/**
	 * Get the next unstructured string chunk from the query string.
	 * Chunks are delimited by any of the special strings used in inline queries
	 * (such as [[, ]], <q>, ...). If the string starts with such a delimiter,
	 * this delimiter is returned. Otherwise the first string in front of such a
	 * delimiter is returned.
	 * Trailing and initial spaces are ignored if $trim is true, and chunks
	 * consisting only of spaces are not returned.
	 * If there is no more qurey string left to process, the empty string is
	 * returned (and in no other case).
	 *
	 * The stoppattern can be used to customise the matching, especially in order to
	 * overread certain special symbols.
	 *
	 * $consume specifies whether the returned chunk should be removed from the
	 * query string.
	 */
	protected function readChunk( $stoppattern = '', $consume = true, $trim = true ) {
		if ( $stoppattern === '' ) {
			$stoppattern = '\[\[|\]\]|::|:=|<q>|<\/q>' .
				'|^' . $this->m_categoryprefix . '|^' . $this->m_categoryPrefixCannonical .
				'|^' . $this->m_conceptprefix . '|^' . $this->m_conceptPrefixCannonical .
				'|\|\||\|';
		}
		$chunks = preg_split( '/[\s]*(' . $stoppattern . ')/iu', $this->m_curstring, 2, PREG_SPLIT_DELIM_CAPTURE );
		if ( count( $chunks ) == 1 ) { // no matches anymore, strip spaces and finish
			if ( $consume ) {
				$this->m_curstring = '';
			}

			return $trim ? trim( $chunks[0] ) : $chunks[0];
		} elseif ( count( $chunks ) == 3 ) { // this should generally happen if count is not 1
			if ( $chunks[0] === '' ) { // string started with delimiter
				if ( $consume ) {
					$this->m_curstring = $chunks[2];
				}

				return $trim ? trim( $chunks[1] ) : $chunks[1];
			} else {
				if ( $consume ) {
					$this->m_curstring = $chunks[1] . $chunks[2];
				}

				return $trim ? trim( $chunks[0] ) : $chunks[0];
			}
		} else {
			return false;
		} // should never happen
	}

	/**
	 * Enter a new subblock in the query, which must at some time be terminated by the
	 * given $endstring delimiter calling popDelimiter();
	 */
	protected function pushDelimiter( $endstring ) {
		array_push( $this->m_sepstack, $endstring );
	}

	/**
	 * Exit a subblock in the query ending with the given delimiter.
	 * If the delimiter does not match the top-most open block, false
	 * will be returned. Otherwise return true.
	 */
	protected function popDelimiter( $endstring ) {
		$topdelim = array_pop( $this->m_sepstack );
		return ( $topdelim == $endstring );
	}

	/**
	 * Extend a given description by a new one, either by adding the new description
	 * (if the old one is a container description) or by creating a new container.
	 * The parameter $conjunction determines whether the combination of both descriptions
	 * should be a disjunction or conjunction.
	 *
	 * In the special case that the current description is NULL, the new one will just
	 * replace the current one.
	 *
	 * The return value is the expected combined description. The object $curdesc will
	 * also be changed (if it was non-NULL).
	 */
	protected function addDescription( $curdesc, $newdesc, $conjunction = true ) {
		$notallowedmessage = 'smw_noqueryfeature';
		if ( $newdesc instanceof SMWSomeProperty ) {
			$allowed = $this->m_queryfeatures & SMW_PROPERTY_QUERY;
		} elseif ( $newdesc instanceof SMWClassDescription ) {
			$allowed = $this->m_queryfeatures & SMW_CATEGORY_QUERY;
		} elseif ( $newdesc instanceof SMWConceptDescription ) {
			$allowed = $this->m_queryfeatures & SMW_CONCEPT_QUERY;
		} elseif ( $newdesc instanceof SMWConjunction ) {
			$allowed = $this->m_queryfeatures & SMW_CONJUNCTION_QUERY;
			$notallowedmessage = 'smw_noconjunctions';
		} elseif ( $newdesc instanceof SMWDisjunction ) {
			$allowed = $this->m_queryfeatures & SMW_DISJUNCTION_QUERY;
			$notallowedmessage = 'smw_nodisjunctions';
		} else {
			$allowed = true;
		}

		if ( !$allowed ) {
			$this->m_errors[] = wfMessage(
				$notallowedmessage,
				str_replace( '[', '&#x005B;', $newdesc->getQueryString() )
			)->inContentLanguage()->text();
			return $curdesc;
		}

		if ( is_null( $newdesc ) ) {
			return $curdesc;
		} elseif ( is_null( $curdesc ) ) {
			return $newdesc;
		} else { // we already found descriptions
			if ( ( ( $conjunction ) && ( $curdesc instanceof SMWConjunction ) ) ||
			     ( ( !$conjunction ) && ( $curdesc instanceof SMWDisjunction ) ) ) { // use existing container
				$curdesc->addDescription( $newdesc );
				return $curdesc;
			} elseif ( $conjunction ) { // make new conjunction
				if ( $this->m_queryfeatures & SMW_CONJUNCTION_QUERY ) {
					return new SMWConjunction( array( $curdesc, $newdesc ) );
				} else {
					$this->m_errors[] = wfMessage(
						'smw_noconjunctions',
						str_replace( '[', '&#x005B;', $newdesc->getQueryString() )
					)->inContentLanguage()->text();
					return $curdesc;
				}
			} else { // make new disjunction
				if ( $this->m_queryfeatures & SMW_DISJUNCTION_QUERY ) {
					return new SMWDisjunction( array( $curdesc, $newdesc ) );
				} else {
					$this->m_errors[] = wfMessage(
						'smw_nodisjunctions',
						str_replace( '[', '&#x005B;', $newdesc->getQueryString() )
					)->inContentLanguage()->text();
					return $curdesc;
				}
			}
		}
	}
}
