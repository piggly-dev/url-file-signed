<?php

namespace Piggly\UrlFileSigner;

use DateInterval;
use DateTime;
use Piggly\UrlFileSigner\Dict\ParameterDict;
use Purl\Url;
use RuntimeException;

abstract class UrlSigner implements BaseSigner
{
    /** @var string File separator. */
    const FILE_SEPARATOR = '_';
    
    /**
     * @var string The mounted URL to use in all methods.
     */
    protected $montedUrl;
    
    /**
     * @var string The base URL to use in all methods.
     */
    protected $baseUrl;
    
    /**
     * @var string The key that is used to generate secure signatures.
     */
    protected $signatureKey;
    
    /**
     * @var string File separator.
     */
    protected $fileSeparator;
    
    /**
     * @var ParameterDict Query parameters names.
     */
    protected $queryParams;
    
    /**
     * @var ParameterDict File parameters to get.
     */
    protected $fileParams;
    
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
        $this->fileSeparator = self::FILE_SEPARATOR;
        
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
    public function sign ( string $filePath, DateInterval $ttl, array $queryStrings = [] ) : string
    {
        // Check if query has reseverd parameters
        if ( count ( array_intersect ( array_keys ( $queryStrings ), $this->queryParams->aliases() ) ) !== 0 )
        { throw new RuntimeException( sprintf( 'You cannot set reserved query parameters `%s`', implode(',', $this->queryParams->aliases() ) ) ); }
        // FIX PATH
        $path = $this->fixDirectorySeparator($filePath);
        // Url component
        $url = new Url($this->baseUrl);
        // Encode TTL
        $ttl = $this->encodeTTL($ttl);
        // Extract media from URL
        $media = $this->parseMedia($path);
        // Attach pathes
        $url->set( 'path', $media['paths'] . $this->encodePaths($path) . $media['media']);
        // Merge order of parameters to query strings
        $queryStrings = array_merge( $queryStrings, $media['order'] );
        
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
        $path     = $url->path;
        $filePath = $this->decodePaths( $path ) . '/' . $this->createMediaName( $path, $par );
        
        return $this->recoverDirectorySeparator($filePath);
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
     * Replace the file separator.
     * 
     * @param string $fileSeparator
     * @return \self
     */
    public function changeFileSeparator ( string $fileSeparator ) : self
    {
        $this->fileSeparator = $fileSeparator;
        return $this;
    }
    
    /**
     * Get the file separator character.
     * 
     * @return string
     */
    public function getFileSeparator () : string
    { return $this->fileSeparator; }
    
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
     * Add a collection of allowed parameters to files.
     * 
     * @param ParameterDict $params
     * @return \self
     */
    public function addAllowedFileParams ( ParameterDict $params ) : self
    { 
        $this->fileParams = $params; 
        return $this;
    }
    
    /**
     * Get the collection fo allowed parameters to files.
     * 
     * @return array
     */
    public function getAllowedFileParams ()
    { return $this->fileParams; }
    
    /**
     * Defines a new sorting order for displaying parameters in the URL.
     * 
     * @param array $newSort
     * @throws RuntimeException
     * @return \self
     */
    public function sortToDisplay ( array $newSort ) : self
    {
        $this->fileParams->sortToDisplay($newSort);
        return $this;
    }
    
    /**
     * Defines a new sorting order for setting parameters in the file name.
     * 
     * @param array $newSort
     * @throws RuntimeException
     * @return \self
     */
    public function sortInFileName ( array $newSort ) : self
    {
        $this->fileParams->sortInFileName($newSort);
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
     * Parse media extracting and removing file parameters respecting added order.
     * 
     * @param string $paths URI Paths.
     * @return array
     */
    protected function parseMedia ( string $paths ) : array
    {
        $media = [ 'paths' => '', 'media' => '', 'order' => [] ];
        
        if ( !empty( $this->fileParams ) )
        {
            foreach ( $this->fileParams->display() as $param )
            {
                $regex = '/'.$this->fileSeparator.$param.'([a-z0-9]+)?/i';

                preg_match( $regex, $paths, $matches );

                if ( !empty( $matches ) )
                { 
                    $value = !empty($matches[1]) ? $matches[1] : '';
                    $media['paths'] .= '/' . $param . $value; 
                }
            }

            // Remove parameters from string
            $regex          = '/'.$this->fileSeparator.'('.implode('|',$this->fileParams->aliases()).')([a-z0-9]+)?/i';
            $media['media'] = preg_replace($regex, '', $paths);
            $media['order'] = [ $this->queryParams->getAlias ('parameters') => $this->detectParams( $paths ) ];
        }
        else
        { $media['media'] = $paths; }
        
        $media['media'] = '/' . basename ( $media['media'] );
        return $media;
    }
    
    /**
     * Find parameters order in file name
     * 
     * @param string $paths
     * @return string
     */
    protected function detectParams ( string $paths ) : string
    {
        preg_match_all('/_(?<params>'.implode('|',$this->fileParams->aliases()).')([a-z0-9]+)?/', $paths, $matches, PREG_PATTERN_ORDER );
        
        if ( !empty( $matches['params'] ) )
        { return base64_encode ( implode( '::', $matches['params'] ) ); }
        
        return '';
    }
    
    /**
     * Generate media name including file parameters.
     * 
     * @param string $path
     * @param string $paramsOrder
     * @return string
     */
    protected function createMediaName ( string $path, string $paramsOrder = null ) : string
    {
        $params = !is_null( $paramsOrder ) ? explode( '::', base64_decode($paramsOrder) ) : null;
        
        $ext   = pathinfo( $path, PATHINFO_EXTENSION );
        $media = pathinfo( $path, PATHINFO_FILENAME );
        
        if ( !empty( $params ) )
        {
            foreach ( $params as $param )
            {
                $regex = '/\/'.$param.'([a-z0-9]+)?/i';
                
                preg_match( $regex, $path, $matches );
                
                if ( !empty( $matches ) )
                { 
                    $value = !empty($matches[1]) ? $matches[1] : '';
                    $media .= $this->fileSeparator . $param . $value; 
                }
            }
        }
        
        return $media.'.'.$ext;
    }
    
    /**
     * Convert each path to a HEXADECIMAL string, then encode to BASE64 and again
     * convert to a HEXADECIMAL string.
     * 
     * @param string $paths
     * @return string
     */
    protected function encodePaths ( string $paths ) : string
    {
        $paths = trim ( dirname ( $paths ), '/' );
        
        if ( $paths === '.' )
        { return ''; }
        
        $folders = explode ( '/', $paths );
        $folder  = '';
        
        if ( !empty($folders) )
        {
            foreach ( $folders as $f )
            { 
                if ( intval( $f ) )
                { 
                    // Char identifier from G to O
                    $folder .= chr(rand(71,79)) . dechex($f); 
                }
                else
                { 
                    // Char identifier from Q to Y
                    $folder .= chr(rand(81,89)) . bin2hex($f); 
                }
            }

            return '/' . bin2hex ( base64_encode( strtoupper($folder) ) );
        }
        
        return '';
    }
    
    /**
     * Decode the HEXADECIMAL path in URI paths
     * 
     * @param string $paths
     * @return string
     */
    protected function decodePaths ( string $paths ) : string
    {
        $uri = dirname( $paths );
        
        if ( $paths === '.' )
        { return ''; }
        
        // Removes all file parameters from paths
        if ( !empty ( $this->fileParams ) )
        { 
            $regex = '/\/('.implode('|',$this->fileParams->aliases()).')([a-z0-9]+)?/i';
            $uri   = preg_replace($regex, '', $uri);
        }
        
        // Decode paths
        $uri = base64_decode ( hex2bin ( trim ( $uri, '/' ) ) ); 
        // Get all hexadecimal paths
        preg_match_all ( '/(?<code>[G-OQ-Y])(?<hex>[A-F0-9]+)/i', $uri, $matches, PREG_PATTERN_ORDER );
        $folder = '';

        // Decode
        foreach ( $matches['code'] as $index => $code )
        {
            $ascii = ord($code);
            
            // Is decimal
            if ( $ascii >= 71 && $ascii <= 79 )
            { $folder .= '/' . hexdec($matches['hex'][$index]); }
            // Is string
            else if ( $ascii >= 81 && $ascii <= 89 )
            { $folder .= '/' . hex2bin($matches['hex'][$index]); }
        }
        
        return $folder;
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
    
    /**
     * Fix for systems that uses \ as DIRECTORY_SEPARATOR
     * @param string $path
     */
    protected function fixDirectorySeparator ( string $path ) : string
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        { $path = str_replace( DIRECTORY_SEPARATOR, '/', $path ); }
        
        return $path;
    }
    
    /**
     * Recover for systems that uses \ as DIRECTORY_SEPARATOR
     * @param string $path
     */
    protected function recoverDirectorySeparator ( string $path ) : string
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        { $path = str_replace( '/', DIRECTORY_SEPARATOR, $path ); }
        
        return $path;
    }
}