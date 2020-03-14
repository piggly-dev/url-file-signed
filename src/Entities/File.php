<?php

namespace Piggly\UrlFileSigner\Entities;

use Piggly\UrlFileSigner\Collections\ParameterCollection;
use Piggly\UrlFileSigner\Collections\ParameterDict;
use Purl\Url;
use RuntimeException;

class File
{
    /** @var string File separator. */
    const FILE_SEPARATOR = '_';
    
    /** @var string File separator. */
    protected $separator;
    
    /** @var string The file extension. */
    private $ext;
    
    /** @var string The file name. */
    private $name;
    
    /** @var string The file path. */
    private $path;
    
    /** @var ParameterCollection A collection with all parameters allowed and your values. */
    public $parameters;
    
    /**
     * 
     * @param ParameterDict $params
     * @return $this
     */
    public function __construct ( ParameterDict $params )
    {
        $this->parameters = ParameterCollection::create ( $params );
        $this->separator  = addslashes(self::FILE_SEPARATOR);
        return $this;
    }
    
    /**
     * Creates a new instance.
     * 
     * @param ParameterDict $params
     * @return \static
     */
    public static function create ( ParameterDict $params ) 
    { return new static($params); }
    
    /**
     * Replace the file separator.
     * 
     * @param string $separator
     * @return \self
     */
    public function changeSeparator ( string $separator ) : self
    {
        $this->separator = addslashes($separator);
        return $this;
    }
    
    /**
     * Encode the file name path and updates $this->path.
     * 
     * @return string
     */
    public function encodePath () : string
    {
        if ( empty( $this->path ) )
        { return ''; }
        
        $folders = explode ( DIRECTORY_SEPARATOR, $this->path );
        $encoded = '';
        
        foreach ( $folders as $f )
        { 
            if ( empty( $f ) )
            { continue; }
            
            if ( intval( $f ) && $f >= 10 )
            { 
                // Char identifier from G to O
                $encoded .= chr(rand(71,79)) . dechex($f); 
            }
            else
            { 
                // Char identifier from Q to Y
                $encoded .= chr(rand(81,89)) . bin2hex($f); 
            }
        }
        
        $this->setPath(bin2hex ( base64_encode( $encoded ) ));
        $this->path = str_replace ( DIRECTORY_SEPARATOR, '/', $this->path );
        $this->path = '/' . $this->path;
        return $this->path;
    }
    
    /**
     * Decode the file name path and updates $this->path.
     * 
     * @return string
     */
    public function decodePath () : string
    {
        // Decode paths
        $decoded = base64_decode ( hex2bin ( trim( $this->path, '/' ) ) ); 
        
        if ( empty( $decoded ) || $decoded === DIRECTORY_SEPARATOR )
        { return $decoded; }
        
        $path = '';
        
        // Get all hexadecimal paths
        preg_match_all ( '/(?<code>[G-OQ-Y])(?<hex>[A-F0-9]+)/i', $decoded, $matches, PREG_PATTERN_ORDER );
        
        // Decode
        foreach ( $matches['code'] as $index => $code )
        {
            $ascii = ord($code);
            
            // Is decimal
            if ( $ascii >= 71 && $ascii <= 79 )
            { $path .= DIRECTORY_SEPARATOR . hexdec($matches['hex'][$index]); }
            // Is string
            else if ( $ascii >= 81 && $ascii <= 89 )
            { $path .= DIRECTORY_SEPARATOR . hex2bin($matches['hex'][$index]); }
        }
        
        $this->setPath($path);
        return $this->path;
    }
    
    /**
     * Encode the file name path to URI.
     * @return string
     */
    public function encodeToUri () : string
    {
        $filename = '/' . implode ( '/', $this->parameters->paramsToDisplay() ) . $this->getFileNameEncoded();
        $regex    = '/'.$this->separator.'('.implode('|',$this->parameters->allowed->aliases()).')([a-z0-9]+)?/i';
        $filename = preg_replace($regex, '', $filename);
        
        return $filename;
    }
    
    /**
     * Decode the file name path in URI.
     * 
     * @param Url $url
     * @return string
     */
    public static function decodeUri ( string $uri, array $order = null ) : string
    {        
        $uri = DIRECTORY_SEPARATOR . trim ( str_replace( '/', DIRECTORY_SEPARATOR, $uri ), DIRECTORY_SEPARATOR );
        
        $dict = ParameterDict::create();
        
        foreach ( $order as $param )
        { $dict->add ($param); }
        
        $file = self::create($dict);
        $file->set( $uri );
        
        // Extract parameters
        $file->parameters->allowed->sortInFileNameByAlias( $order );
        $path = $file->parameters->extractFromPath( $file->path );
        
        // Decode paths
        $file->discoverEncodedPath($path);
        
        return $file->getFileName();
    }
    
    /**
     * Find a encoded path in URI and updates $this->path.
     * @param string $uri
     */
    protected function discoverEncodedPath ( string $uri ) : string
    {
        // Get encoded paths
        $escapedDir = addcslashes(DIRECTORY_SEPARATOR, '\\\/');
        preg_match_all ( '/'.$escapedDir.'(?<hex>[a-fA-F0-9]+)'.$escapedDir.'/i', $uri, $matches, PREG_PATTERN_ORDER );
        
        if ( !empty( $matches['hex'] ) )
        { 
            $this->path = $matches['hex'][0]; 
            $this->decodePath();
        }
        
        return $this->path;
    }
    
    /**
     * Get order of parameters set in file name.
     * 
     * @return array
     */
    public function getOrderOfParamsInFileName () : array
    {
        preg_match_all('/'.$this->separator.'(?<params>'.implode('|',$this->parameters->allowed->aliases()).')([a-z0-9]+)?/', $this->getFileName(), $matches, PREG_PATTERN_ORDER );
        
        if ( !empty( $matches['params'] ) )
        { return $matches['params']; }
        
        return [];
    }
    
    /**
     * Get the file separator character.
     * 
     * @return string
     */
    public function getSeparator () : string
    { return $this->fileSeparator; }
    
    /**
     * Get the file name.
     * 
     * @return string
     */
    public function getPath () : string
    { return $this->path; }
    
    /**
     * Get the file name.
     * 
     * @return string
     */
    public function getName ( bool $ext = false ) : string
    {
        if ( $ext )
        { return $this->name . $this->ext; }
        
        return $this->name; 
    }
    
    /**
     * Get the file extension.
     * 
     * @return string
     */
    public function getExtension () : string
    { return $this->ext; }
    
    /**
     * Get file name with parameters.
     * 
     * @return string
     * @throws RuntimeException
     */
    public function getFileName () : string
    {
        if ( empty( $this->ext ) )
        { throw new RuntimeException( sprintf( 'You did not set an extension to file.' ) ); }
        
        $media  = $this->path . $this->name;
        $params = $this->parameters->paramsToFileName();
        
        if ( !empty ( $params ) )
        { $media .= $this->separator . implode ( $this->separator, $params ); }
        
        return $media . $this->ext;
    }
    
    /**
     * Get encoded file name with parameters.
     * 
     * @return string
     * @throws RuntimeException
     */
    public function getFileNameEncoded () : string
    {
        $this->encodePath();
        return $this->getFileName();
    }
    
    /**
     * Get encoded file name with parameters.
     * 
     * @return string
     * @throws RuntimeException
     */
    public function getFileNameDecoded () : string
    {
        $this->decodePath();
        return $this->getFileName();
    }
            
    /**
     * Set the file
     * 
     * @param string $fileName
     * @return \self
     */
    public function set ( string $fileName ) : self
    {
        $this->setExtension( pathinfo( $fileName, PATHINFO_EXTENSION ) );
        $this->setName( pathinfo( $fileName, PATHINFO_FILENAME ) );
        $this->setPath( pathinfo( $fileName, PATHINFO_DIRNAME ) );
        
        return $this;
    }
    
    /**
     * Set the file name
     * 
     * @param string $name
     * @return \self
     */
    public function setName ( string $name ) : self
    {
        $this->name = $this->scanParamsInFileName ( trim( $name, DIRECTORY_SEPARATOR ) );
        return $this;
    }
    
    /**
     * Generate a unique numeric file name
     * 
     * @param string $name
     * @return \self
     */
    public function setRandomName () : self
    {
        $arr  = [ 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 5.5, 6 ];
        $tms  = round ( time() * $arr[ mt_rand( 0,count($arr)-1 ) ] ); 
        $mtms = number_format(round(microtime(true)*mt_rand(150000,300000)),0,'','');
        $rand = $this->randomNumber(19);
         
        $this->name = $tms . '_' . $mtms . '_' . $rand;
        return $this;
    }
    
    /**
     * Set the file path
     * 
     * @param string $path
     * @return \self
     */
    public function setPath ( string $path ) : self
    {
        if ( empty($path) || $path === '.' )
        { $path = ''; }
        else
        { $path = rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR; }
        
        $this->path = $path;
        return $this;
    }
    
    /**
     * Set the file extension
     * 
     * @param string $ext
     * @return \self
     */
    public function setExtension ( string $ext ) : self
    {
        if ( empty( $ext ) )
        { throw new RuntimeException( 'The file name needs to contain an extension.' ); }
        
        $this->ext = '.' . trim( $ext, '.');
        return $this;
    }
    
    /**
     * Defines a new sorting order for displaying parameters in the URL.
     * 
     * @param array $newSort
     * @throws RuntimeException
     * @return \self
     */
    public function sortToDisplay ( array $newSort ) : self
    {
        $this->parameters->allowed->sortToDisplay($newSort);
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
        $this->parameters->allowed->sortInFileName($newSort);
        return $this;
    }
    
    /**
     * Scan for parameters in filename.
     * 
     * @param string $name
     * @return string
     */
    protected function scanParamsInFileName ( string $name ) : string
    {        
        foreach ( $this->parameters->allowed->inFileName() as $param => $alias )
        {
            $regex = '/'.$this->separator.$alias.'([a-z0-9]+)?/i';
            
            preg_match( $regex, $name, $matches );

            if ( !empty( $matches ) )
            { 
                $value = !empty($matches[1]) ? $matches[1] : '';
                $this->parameters->add( $param, $value );
            }
        }
        
        if ( $this->parameters->count() !== 0 )
        {
            $regex = '/'.$this->separator.'('.implode('|',$this->parameters->allowed->aliases()).')([a-z0-9]+)?/i';
            return preg_replace($regex, '', $name);
        }
        
        return $name;
    }
    
    /**
     * Generate a random numberic string.
     * 
     * @param int $lenght
     * @return string
     */
    private function randomNumber ( $lenght ) : string
    {
        $str = '';
        
        for ( $i = 0; $i < $lenght; $i++ )
        { $str .= mt_rand(0,9); }
        
        return ltrim($str, '0');
    }
}