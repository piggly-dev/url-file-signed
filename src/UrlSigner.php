<?php

namespace Piggly\UrlFileSigner;

use DateInterval;
use DateTime;
use Piggly\UrlFileSigner\Collections\ParameterDict;
use Piggly\UrlFileSigner\Entities\File;
use Purl\Url;
use RuntimeException;

abstract class UrlSigner implements BaseSigner
{
    /**
     * @var string The base URL to use in all methods.
     */
    protected $baseUrl;
    
    /**
     * @var string The key that is used to generate secure signatures.
     */
    protected $signatureKey;
    
    /**
     * @var ParameterDict Query parameters names.
     */
    protected $queryParams;
    
    /**
     * @var File A file to manipulate.
     */
    protected $file;
    
    /**
     * 
     * @param string $baseUrl
     * @param string $signatureKey
     * @return \self
     * 
     * @throws \RuntimeException When signature key is empty.
     */
    public function __construct ( string $baseUrl, string $signatureKey )
    {
        if ( empty( $signatureKey ) )
        { throw new RuntimeException( 'The signature key cannot be empty.' ); }

        $this->baseUrl = trim( $baseUrl, '/' );
        $this->signatureKey = $signatureKey;
        
        $this->queryParams = ParameterDict::create()
                                ->add('parameters','op')
                                ->add('expiration','oe')
                                ->add('signature','oh');
        
        return $this;
    }
    
    /**
     * Creates a new instance.
     * 
     * @param string $baseUrl
     * @param string $signatureKey
     * @return \static
     */
    public static function create ( string $baseUrl, string $signatureKey ) 
    { return new static( $baseUrl, $signatureKey ); }
    
    /**
     * Create a signed URL to a File Path.
     * 
     * @param string $filePath File path
     * @param DateInterval $ttl
     * @return string
     */
    public function sign ( File $file, DateInterval $ttl, array $queryStrings = [] ) : string
    {
        // Check if query has reseverd parameters
        if ( count ( array_intersect ( array_keys ( $queryStrings ), $this->queryParams->aliases() ) ) !== 0 )
        { throw new RuntimeException( sprintf( 'You cannot set reserved query parameters `%s`', implode(',', $this->queryParams->aliases() ) ) ); }
        
        // Url component
        $url = new Url($this->baseUrl);
        
        // Encode TTL
        $ttl = $this->encodeTTL($ttl);
        
        // Attach pathes
        $url->set( 'path', $file->encodeToUri() );
        
        // Order of parameters in fileName
        if ( count($order = $file->getOrderOfParamsInFileName()) !== 0 )
        {
            $order = 
                [
                    $this->queryParams->getAlias('parameters') 
                        => trim ( base64_encode( implode( '::', $order ) ), '=' )
                ];
        }
        
        // Merge order of parameters to query strings
        $queryStrings = array_merge( $queryStrings, $order );
        
        return $this->appendQueryParameters($url, $ttl, $queryStrings);
    }
    
    /**
     * Validates a File URL and return File File Path if exist.
     * 
     * @param string $url
     * 
     * @return bool|string FALSE when not valid, IMAGE FILE PATH when valid.
     */
    public function validate ( string $url )
    {        
        $url = new Url($url);
        
        // Setup
        $queries = $url->query->getData();
        
        // Check if has all required params
        if ( count ( array_diff ( $this->queryParams->onlyAliases(['expiration','signature']), array_keys( $queries ) ) ) !== 0 )
        { return false; }
                
        $exp = $queries[$this->queryParams->getAlias('expiration')];
        $sig = $queries[$this->queryParams->getAlias('signature')];
        $par = isset ( $queries[$this->queryParams->getAlias('parameters')] ) ? $queries[$this->queryParams->getAlias('parameters')] : null ;
        
        // Empty query data
        $url->query->setData([]);
        
        // Check if it is expired
        if ( $this->decodeTTL( $exp ) < time() )
        { return false; }
                
        // Check domain if required
        if (  $this->queryParams->nameExists('domain') )
        {
            if ( $this->baseUrl !== $queries[$this->queryParams->getAlias('domain')] )
            { return false; }
        } 
        
        // Ignore all query parameters after signature parameter
        $queryParams = [];
            
        foreach ( $queries as $name => $value )
        { 
            if ( $value === $sig ) { break; }
            $queryParams[$name] = $value;  
        }
        
        $url->query->setData($queryParams);
        
        // Check if has a valid signature
        $signature         = $this->createSignature( $url, $exp );
        $providedSignature = $sig;
        
        if ( !hash_equals ( $signature, $providedSignature ) )
        { return false; }
        
        // Return File Path
        $output = [];
        
        $par            = !empty($par) ? explode( '::', base64_decode ( $par ) ) : [];
        $output['exp']  = $this->decodeTTL( $exp );
        $output['file'] = File::decodeUri( $url->path, $par );
        
        return $output;
    }
    
    /**
     * Generate a token to identify the URL.
     *
     * @param \League\Uri\Uri|string $url
     * @param string $ttl
     *
     * @return string
     */
    abstract protected function createSignature ( $url, string $ttl );
    
    /**
     * Replace the order of parameters query parameter name.
     * 
     * @param string $name
     * @return \self
     */
    public function changeOrderOfParametersParam ( string $name ) : self
    {
        $this->queryParams->replaceAlias('parameters', $name);
        return $this;
    }
    
    /**
     * Replace the expiration query parameter name.
     * 
     * @param string $name
     * @return \self
     */
    public function changeExpirationParam ( string $name ) : self
    {
        $this->queryParams->replaceAlias('expiration', $name);
        return $this;
    }
    
    /**
     * Replace the signature query parameter name.
     * 
     * @param string $name
     * @return \self
     */
    public function changeSignatureParam ( string $name ) : self
    {
        $this->queryParams->replaceAlias('signature', $name);
        return $this;
    }
    
    /**
     * Replace the domain query parameter name.
     * 
     * @param string $name
     * @return \self
     */
    public function enableDomainParam ( string $name = 'od' ) : self
    {
        $this->queryParams->add('domain', $name);
        return $this;
    }
    
    /**
     * Append domain, signature and expiration parameters to URL.
     * 
     * @param string $url
     * @param string $ttl
     * @return string
     */
    protected function appendQueryParameters ( Url $url, string $ttl, array $queryStrings = [] ) : string
    {
        // Final url
        if ( $this->queryParams->nameExists ( 'domain' ) )
        { $queryStrings[$this->queryParams->getAlias('domain')] = $this->baseUrl; }
        
        // Add expires parameter
        $queryStrings[$this->queryParams->getAlias('expiration')] = $ttl;
        
        // Add parameters to query
        $url->query->setData($queryStrings);
        
        // Add signature parameter
        $queryStrings[$this->queryParams->getAlias('signature')] = $this->createSignature( (string)$url, $ttl );
        
        // Add parameters to query
        $url->query->setData($queryStrings);
        
        return (string)$url;
    }
    
    /**
     * Convert a UNIX Timestamp to HEXADECIMAL uppercase string.
     *  
     * @param DateInterval $ttl
     * @return string
     */
    protected function encodeTTL ( DateInterval $ttl )
    { return strtoupper( dechex ( (new DateTime('now'))->add($ttl)->getTimestamp() ) ); }
    
    /**
     * Convert encoded HEXADECIMAL uppercase string to integer Timestamp.
     * 
     * @param string $encoded
     * @return int
     */
    protected function decodeTTL ( string $encoded )
    { return intval ( hexdec( $encoded ) ); }
}