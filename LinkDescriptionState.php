<?php
class LinkDescriptionState extends SMWQueryParserState{

 public getDescription( $chunk, &$setNS ){
 
 }

	protected function finishLinkDescription( $chunk, $hasNamespaces, $result, &$setNS ) {
		if ( is_null( $result ) ) { // no useful information or concrete error found
			$this->m_errors[] = wfMessage( 'smw_badqueryatom' )->inContentLanguage()->text();
		} elseif ( !$hasNamespaces && $setNS && !is_null( $this->m_defaultns  ) ) {
			$result = $this->addDescription( $result, $this->m_defaultns );
			$hasNamespaces = true;
		}

		$setNS = $hasNamespaces;

		if ( $chunk == '|' ) { // skip content after single |, but report a warning
			// Note: Using "|label" in query atoms used to be a way to set the mainlabel in SMW <1.0; no longer supported now
			$chunk = $this->readChunk( '\]\]' );
			$labelpart = '|';
			if ( $chunk != ']]' ) {
				$labelpart .= $chunk;
				$chunk = $this->readChunk( '\]\]' );
			}
			$this->m_errors[] = wfMessage(
				'smw_unexpectedpart',
				$labelpart
			)->inContentLanguage()->escaped();
		}

		if ( $chunk != ']]' ) {
			// What happended? We found some chunk that could not be processed as
			// link content (as in [[Category:Test<q>]]), or the closing ]] are
			// just missing entirely.
			if ( $chunk !== '' ) {
				$this->m_errors[] = wfMessage(
					'smw_misplacedsymbol',
					$chunk
				)->inContentLanguage()->escaped();

				// try to find a later closing ]] to finish this misshaped subpart
				$chunk = $this->readChunk( '\]\]' );

				if ( $chunk != ']]' ) {
					$chunk = $this->readChunk( '\]\]' );
				}
			}
			if ( $chunk === '' ) {
				$this->m_errors[] = wfMessage( 'smw_noclosingbrackets' )->inContentLanguage()->text();
			}
		}

		return $result;
	}
}