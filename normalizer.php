<?php

// namespace URL;

/**
 * Syntax based normalization of URI's
 *
 * This normalises URI's based on the specification RFC 3986
 * https://tools.ietf.org/html/rfc3986
 *
 * Example usage:
 * <code>
 * require_once 'vendor/autoload.php';
 *
 * $url = 'eXAMPLE://a/./b/../b/%63/%7bfoo%7d';
 * $un = new URL\Normalizer( $url );
 * echo $un->normalize();
 *
 * // result: "example://a/b/c/%7Bfoo%7D"
 * </code>
 *
 * @author Glen Scott <glen@glenscott.co.uk>
 */
class mc_normalizer {

	private $url;
	private $scheme;
	private $host;
	private $port;
	private $user;
	private $pass;
	private $path;
	private $query;
	private $fragment;
	private $default_scheme_ports = array(
		'http:'  => 80,
		'https:' => 443,
	);
	private $components           = array( 'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment' );
	private $remove_empty_delimiters;
	private $sort_query_params;

	/**
	 * Does the original URL have a ? query delimiter
	 */
	private $query_delimiter;

	public function __construct( $url = null, $remove_empty_delimiters = false, $sort_query_params = false ) {
		if ( $url ) {
			$this->setUrl( $url );
		}

		$this->remove_empty_delimiters = $remove_empty_delimiters;
		$this->sort_query_params       = $sort_query_params;
	}

	private function getQuery( $query ) {
		$qs = array();
		foreach ( $query as $qk => $qv ) {
			if ( is_array( $qv ) ) {
				$qs[ rawurldecode( $qk ) ] = $this->getQuery( $qv );
			} else {
				$qs[ rawurldecode( $qk ) ] = rawurldecode( $qv );
			}
		}
		return $qs;
	}

	public function getUrl() {
		return $this->url;
	}

	public function setUrl( $url ) {
		$this->url = $url;

		if ( strpos( $this->url, '?' ) !== false ) {
			$this->query_delimiter = true;
		} else {
			$this->query_delimiter = false;
		}

		// parse URL into respective parts
		$url_components = $this->mbParseUrl( $this->url );
        
		if ( ! $url_components ) {
			// Reset URL
			$this->url = '';

			// Flush properties
			foreach ( $this->components as $key ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = '';
				}
			}

			return false;
		} else {
            
			// Update properties
			foreach ( $url_components as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}

			// Flush missing components
			$missing_components = array_diff(
				array_values( $this->components ),
				array_keys( $url_components )
			);

			foreach ( $missing_components as $key ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = '';
				}
			}

			return true;
		}
        
	}

	public function normalize() {

		// URI Syntax Components
		// scheme authority path query fragment
		// @link https://tools.ietf.org/html/rfc3986#section-3

		// Scheme
		// @link https://tools.ietf.org/html/rfc3986#section-3.1

		if ( $this->scheme ) {
			// Converting the scheme to lower case
			$this->scheme = strtolower( $this->scheme ) . ':';
		}

		// Authority
		// @link https://tools.ietf.org/html/rfc3986#section-3.2

		$authority = '';
		if ( $this->host ) {
			$authority .= '//';

			// User Information
			// @link https://tools.ietf.org/html/rfc3986#section-3.2.1

			if ( $this->user ) {
				if ( $this->pass ) {
					$authority .= $this->user . ':' . $this->pass . '@';
				} else {
					$authority .= $this->user . '@';
				}
			}

			// Host
			// @link https://tools.ietf.org/html/rfc3986#section-3.2.2

			// Converting the host to lower case
			if ( mb_detect_encoding( $this->host ) == 'UTF-8' ) {
				$authority .= mb_strtolower( $this->host, 'UTF-8' );
			} else {
				$authority .= strtolower( $this->host );
			}

			// Port
			// @link https://tools.ietf.org/html/rfc3986#section-3.2.3

			// Removing the default port
			if ( isset( $this->default_scheme_ports[ $this->scheme ] )
					&& $this->port == $this->default_scheme_ports[ $this->scheme ] ) {
				$this->port = '';
			}

			if ( $this->port ) {
				$authority .= ':' . $this->port;
			}
		}

		// Path
		// @link https://tools.ietf.org/html/rfc3986#section-3.3

		if ( $this->path ) {
			$this->path = $this->removeAdditionalPathPrefixSlashes( $this->path );
			$this->path = $this->removeDotSegments( $this->path );
			$this->path = $this->urlDecodeUnreservedChars( $this->path );
			$this->path = $this->urlDecodeReservedSubDelimChars( $this->path );
		} elseif ( $this->url ) {
			// Add default path only when valid URL is present
			// Adding trailing /
			$this->path = '/';
		}

		// Query
		// @link https://tools.ietf.org/html/rfc3986#section-3.4

		if ( $this->query ) {
			$query = $this->parseStr( $this->query );

			// encodes every parameter correctly
			$qs = $this->getQuery( $query );

			$this->query = '?';

			if ( $this->sort_query_params ) {
				ksort( $qs );
			}

			foreach ( $qs as $key => $val ) {
				if ( strlen( $this->query ) > 1 ) {
					$this->query .= '&';
				}

				if ( is_array( $val ) ) {
					for ( $i = 0; $i < count( $val ); $i++ ) {
						if ( $i > 0 ) {
							$this->query .= '&';
						}
						$this->query .= rawurlencode( $key ) . '=' . rawurlencode( $val[ $i ] );
					}
				} else {
					$this->query .= rawurlencode( $key ) . '=' . rawurlencode( $val );
				}
			}

			// Fix http_build_query adding equals sign to empty keys
			$this->query = str_replace( '=&', '&', rtrim( $this->query, '=' ) );
		} elseif ( $this->query_delimiter && ! $this->remove_empty_delimiters ) {
				$this->query = '?';
		}

		// Fragment
		// @link https://tools.ietf.org/html/rfc3986#section-3.5

		if ( $this->fragment ) {
			$this->fragment = rawurldecode( $this->fragment );
			$this->fragment = rawurlencode( $this->fragment );
			$this->fragment = '#' . $this->fragment;
		}
        
		$this->setUrl( $this->scheme . $authority . $this->path . $this->query . $this->fragment );
        // print_r( $this );
		return $this->getUrl();
	}

	/**
	 * Path segment normalization
	 * https://tools.ietf.org/html/rfc3986#section-5.2.4
	 */
	public function removeDotSegments( $path ) {
		$new_path = '';

		while ( ! empty( $path ) ) {
			// A
			$pattern_a   = '!^(\.\./|\./)!x';
			$pattern_b_1 = '!^(/\./)!x';
			$pattern_b_2 = '!^(/\.)$!x';
			$pattern_c   = '!^(/\.\./|/\.\.)!x';
			$pattern_d   = '!^(\.|\.\.)$!x';
			$pattern_e   = '!(/*[^/]*)!x';

			if ( preg_match( $pattern_a, $path ) ) {
				// remove prefix from $path
				$path = preg_replace( $pattern_a, '', $path );
			} elseif ( preg_match( $pattern_b_1, $path, $matches ) || preg_match( $pattern_b_2, $path, $matches ) ) {
				$path = preg_replace( '!^' . $matches[1] . '!', '/', $path );
			} elseif ( preg_match( $pattern_c, $path, $matches ) ) {
				$path = preg_replace( '!^' . preg_quote( $matches[1], '!' ) . '!x', '/', $path );

				// remove the last segment and its preceding "/" (if any) from output buffer
				$new_path = preg_replace( '!/([^/]+)$!x', '', $new_path );
			} elseif ( preg_match( $pattern_d, $path ) ) {
				$path = preg_replace( $pattern_d, '', $path );
			} elseif ( preg_match( $pattern_e, $path, $matches ) ) {
					$first_path_segment = $matches[1];

					$path = preg_replace( '/^' . preg_quote( $first_path_segment, '/' ) . '/', '', $path, 1 );

					$new_path .= $first_path_segment;
			}
		}

		return $new_path;
	}

	public function getScheme() {
		return $this->scheme;
	}

	/**
	 * Decode unreserved characters
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-2.3
	 */
	public function urlDecodeUnreservedChars( $string ) {
		$string = rawurldecode( $string );
		$string = rawurlencode( $string );
		$string = str_replace( array( '%2F', '%3A', '%40' ), array( '/', ':', '@' ), $string );

		return $string;
	}

	/**
	 * Decode reserved sub-delims
	 *
	 * @link https://tools.ietf.org/html/rfc3986#section-2.2
	 */
	public function urlDecodeReservedSubDelimChars( $string ) {
		return str_replace(
			array( '%21', '%24', '%26', '%27', '%28', '%29', '%2A', '%2B', '%2C', '%3B', '%3D' ),
			array( '!', '$', '&', "'", '(', ')', '*', '+', ',', ';', '=' ),
			$string
		);
	}

	/**
	 * Replacement for PHP's parse_string which does not deal with spaces or dots in key names
	 *
	 * @param string $string URL query string
	 * @return array key value pairs
	 */
	private function parseStr( $string ) {
		$params = array();

		$pairs = explode( '&', $string );

		foreach ( $pairs as $pair ) {
			if ( ! $pair ) {
				continue;
			}

			$var = explode( '=', $pair, 2 );
			$val = ( isset( $var[1] ) ? $var[1] : '' );

			if ( isset( $params[ $var[0] ] ) ) {
				if ( is_array( $params[ $var[0] ] ) ) {
					$params[ $var[0] ][] = $val;
				} else {
					$params[ $var[0] ] = array( $params[ $var[0] ], $val );
				}
			} else {
				$params[ $var[0] ] = $val;
			}
		}

		return $params;
	}

	private function mbParseUrl( $url ) {
		$result = false;

		// Build arrays of values we need to decode before parsing
		$entities     = array( '%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%24', '%2C', '%2F', '%3F', '%23', '%5B', '%5D' );
		$replacements = array( '!', '*', "'", '(', ')', ';', ':', '@', '&', '=', '$', ',', '/', '?', '#', '[', ']' );

		// Create encoded URL with special URL characters decoded so it can be parsed
		// All other characters will be encoded
		$encodedURL = str_replace( $entities, $replacements, urlencode( $url ) );

		// Parse the encoded URL
		$encodedParts = parse_url( $encodedURL );

		// Now, decode each value of the resulting array
		if ( $encodedParts ) {
			foreach ( $encodedParts as $key => $value ) {
				$result[ $key ] = urldecode( str_replace( $replacements, $entities, $value ) );
			}
		}
        // print_r($result);
		return $result;
	}

	/*
	 * Converts ////foo to /foo within each path segment
	 */
	private function removeAdditionalPathPrefixSlashes( $path ) {
		return preg_replace( '/(\/)+/', '/', $path );
	}
}

function canonicalize( $url ) {
	$url = new mc_normalizer( $url );
	$url->normalize();
	echo $url->getUrl() . PHP_EOL;
}

canonicalize( 'http://host/%25%32%35' );
canonicalize( 'http://host/%25%32%35%25%32%35' );
canonicalize( 'http://host/%2525252525252525' );
canonicalize( 'http://host/asdf%25%32%35asd' );
canonicalize( 'http://host/%%%25%32%35asd%%' );
canonicalize( 'http://www.google.com/' );
canonicalize( 'http://%31%36%38%2e%31%38%38%2e%39%39%2e%32%36/%2E%73%65%63%75%72%65/%77%77%77%2E%65%62%61%79%2E%63%6F%6D/' );
canonicalize( 'http://195.127.0.11/uploads/%20%20%20%20/.verify/.eBaysecure=updateuserdataxplimnbqmn-xplmvalidateinfoswqpcmlx=hgplmcx/' );
canonicalize( 'http://host%23.com/%257Ea%2521b%2540c%2523d%2524e%25f%255E00%252611%252A22%252833%252944_55%252B' );
canonicalize( 'http://3279880203/blah' );
canonicalize( 'http://www.google.com/blah/..' );
canonicalize( 'www.google.com/' );
canonicalize( 'www.google.com' );
canonicalize( 'http://www.evil.com/blah#frag' );
canonicalize( 'http://www.GOOgle.com/' );
canonicalize( 'http://www.google.com.../' );
canonicalize( "http://www.google.com/foo\tbar\rbaz\n2" );
canonicalize( 'http://www.google.com/q?' );
canonicalize( 'http://www.google.com/q?r?' );
canonicalize( 'http://www.google.com/q?r?s' );
canonicalize( 'http://evil.com/foo#bar#baz' );
canonicalize( 'http://evil.com/foo;' );
canonicalize( 'http://evil.com/foo?bar;' );
canonicalize( "http://\x01\x80.com/" );
canonicalize( 'http://notrailingslash.com' );
canonicalize( 'http://www.gotaport.com:1234/' );
canonicalize( '  http://www.google.com/  ' );
canonicalize( 'http:// leadingspace.com/' );
canonicalize( 'http://%20leadingspace.com/' );
canonicalize( '%20leadingspace.com/' );
canonicalize( 'https://www.securesite.com/' );
canonicalize( 'http://host.com/ab%23cd' );
canonicalize( 'http://host.com//twoslashes?more//slashes' );

canonicalize( 'почта@престашоп.рф' ); // Russian, Unicode
canonicalize( 'modulez.ru' ); // English, ASCII
canonicalize( 'xn--80aj2abdcii9c.xn--p1ai' ); // Russian, ASCII
canonicalize( 'xn--80a1acn3a.xn--j1amh' ); // Ukrainian, ASCII
canonicalize( 'xn--srensen-90a.example.com' ); // German, ASCII
canonicalize( 'xn--mxahbxey0c.xn--xxaf0a' ); // Greek, ASCII
canonicalize( 'xn--fsqu00a.xn--4rr70v' ); // Chinese, ASCII
canonicalize( 'xn--престашоп.xn--рф' ); // Russian, Unicode
canonicalize( 'xn--prestashop.рф' ); // Russian, Unicode
canonicalize( 'münchen.de' );
