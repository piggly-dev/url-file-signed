<?php

namespace Piggly\UrlFileSigner;

use DateInterval;
use Piggly\UrlFileSigner\Entities\File;

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
     * Create a signed URL to file.
     * 
     * @param File $path File
     * @param DateInterval $ttl
     * @return string
     */
    public function sign ( File $path, DateInterval $ttl ) : string;
    
    /**
     * Validates a file URL and return file path if exist.
     * 
     * @param string $url
     * 
     * @return bool|string FALSE when not valid, FILE PATH when valid.
     */
    public function validate ( string $url );
}