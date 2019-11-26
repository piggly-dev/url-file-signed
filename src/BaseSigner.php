<?php

namespace Piggly\UrlFileSigner;

use DateInterval;

interface BaseSigner
{    
    /**
     * Create a new instance.
     * 
     * @param string $baseUrl
     * @param string $signatureKey
     * @return \static
     */
    public static function create ( string $baseUrl, string $signatureKey );
    
    /**
     * Create a signed URL to a image URI.
     * 
     * @param string $path Image path
     * @param DateInterval $ttl
     * @return string
     */
    public function sign ( string $path, DateInterval $ttl ) : string;
    
    /**
     * Validates a Image URL and return Image File Path if exist.
     * 
     * @param string $url
     * 
     * @return bool|string FALSE when not valid, IMAGE FILE PATH when valid.
     */
    public function validate ( string $url );
}