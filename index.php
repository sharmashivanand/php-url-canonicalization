<?php

define( 'DEBUG', 0 );

class sfCanonicalUrl {
    protected $url;

    public function __construct($url) {
        $this->url = $this->canonicalize($url);
    }

    private function canonicalize($url) {
        // Step 1: Pre-processing and cleaning
        $url = trim($url);
        $url = $this->addSchemeIfNeeded($url);
        $url = $this->removeSpecialCharacters($url);
        
        // Parse URL and handle its components
        $parsedUrl = parse_url($url);
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = isset($parsedUrl['host']) ? $this->canonicalizeHostname($parsedUrl['host']) : '';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = isset($parsedUrl['path']) ? $this->canonicalizePath($parsedUrl['path']) : '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';

        // Remove fragments
        $url = "$scheme://$host$port$path$query";
        $url = $this->repeatedlyUnescape($url);
        $url = $this->escapeSpecialCharacters($url);
        return $url;
    }

    private function addSchemeIfNeeded($url) {
        if (!preg_match('~^[a-zA-Z]+://~', $url)) {
            return "http://$url";
        }
        return $url;
    }

    private function removeSpecialCharacters($url) {
        return str_replace(["\t", "\n", "\r"], '', $url);
    }

    private function canonicalizeHostname($host) {
        $host = strtolower($host);
        $host = trim($host, '.');
        $host = preg_replace('/\.{2,}/', '.', $host);
        
        // Normalize IP address
        if ($ip = $this->normalizeIpAddress($host)) {
            return $ip;
        }
        return $host;
    }

    private function normalizeIpAddress($host) {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $ipLong = ip2long($host);
        if ($ipLong !== false) {
            return long2ip($ipLong);
        }
        return $host;
    }

    private function canonicalizePath($path) {
        $path = preg_replace('#/\.?/#', '/', $path);
        $path = preg_replace('#(?<=/)(?!\.\./)[^/]+/\.\./#', '', $path);
        return preg_replace('#/+#', '/', $path);
    }

    private function repeatedlyUnescape($url) {
        $previous = null;
        while ($previous !== $url) {
            $previous = $url;
            $url = rawurldecode($url);
        }
        return $url;
    }

    private function escapeSpecialCharacters($url) {
        return preg_replace_callback('/[\x00-\x20\x7F-\xFF#%]/', function ($matches) {
            return sprintf("%%%02X", ord($matches[0]));
        }, $url);
    }

    public function getUrl() {
        return $this->url;
    }
}


function canonicalize( $url ) {
	// echo 'INPUT URL: ' . $url . PHP_EOL;
	$aurl = new sfCanonicalUrl( $url );
	$aurl = $aurl->getUrl();
	llog( $aurl );
	echo PHP_EOL;
	if ( DEBUG ) {
		echo PHP_EOL;
	}
}

function llog( $thing ) {
	print_r( $thing );
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
