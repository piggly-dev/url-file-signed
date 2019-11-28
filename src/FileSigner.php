<?php

namespace Piggly\UrlFileSigner;

class FileSigner extends UrlSigner
{
    /**
     * Generate a token to identify the URL.
     *
     * @param \League\Uri\Uri|string $url
     * @param string $ttl
     *
     * @return string
     */
    protected function createSignature ( $url, string $ttl )
    {
        $url = (string) $url;
        return md5("{$url}::{$ttl}::{$this->signatureKey}");
    }
}
