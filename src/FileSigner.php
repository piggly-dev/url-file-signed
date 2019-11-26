<?php

namespace Piggly\UrlFileSigner;

use RuntimeException;

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
    
    /**
     * Create a unique numeric file name with extension.
     * 
     * @param string $extension
     * @return string
     */
    public static function createUniqueFileName ( string $extension ) : string
    {
        $arr  = [ 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 5.5, 6 ];
        $tms  = round ( time() * $arr[ mt_rand( 0,count($arr)-1 ) ] ); 
        $mtms = number_format(round(microtime(true)*mt_rand(150000,300000)),0,'','');
        $rand = self::randomNumber(19);
         
        return $tms . '_' . $mtms . '_' . $rand . '.' . ltrim( $extension, '.' );
    }
    
    /**
     * Append parameters to a file name, parameters is an array with key value pair.
     * Where key is the parameters name in $fileParameters and value is the respective
     * value for parameter. It will append parameters following $fileParameters
     * order.
     * 
     * It is useful for attaching parameters without boring on how to do this.
     * 
     * @param \Piggly\UrlFileSigner\FileSigner $fileSigner
     * @param string $fileName
     * @param array $params
     * @return type
     * @throws RuntimeException
     */
    public static function appendParamsToFileName ( FileSigner $fileSigner, string $fileName, array $params )
    {
        if ( empty( $fileSigner->getAllowedFileParams() ) )
        { throw new RuntimeException( 'The signed-object needs to have file parameters.' ); }
        
        $fileParameters = $fileSigner->getAllowedFileParams();
        
        $fileName = $fileSigner->fixDirectorySeparator($fileName);
        $ext      = pathinfo( $fileName, PATHINFO_EXTENSION );
        $media    = pathinfo( $fileName, PATHINFO_FILENAME );
        $path     = pathinfo( $fileName, PATHINFO_DIRNAME );
        
        if ( empty( $ext ) )
        { throw new RuntimeException( sprintf( 'The file name `%s` needs to contain an extension.', $fileName ) ); }
        
        if ( !empty( array_diff( array_keys($params), $fileParameters->names() ) ) )
        { 
            throw new RuntimeException 
                        (   
                            sprintf( 'You sent `%s` and the file parameters expected are `%s`.', 
                                implode (',', array_keys($params)), 
                                implode (',', $fileParameters->names()) )
                        ); 
        }

        foreach ( $fileParameters->inFileName() as $param => $alias )
        {
            if ( isset( $params[$param] ) )
            { $media .= $fileSigner->getFileSeparator().$alias.$params[$param]; }
        }
        
        if ( empty( $path ) || $path === '.' )
        { return $media . '.' . $ext; }
        
        return $fileSigner->recoverDirectorySeparator($path . '/' . $media.'.'.$ext);
    }
    
    /**
     * Generate a random numberic string.
     * 
     * @param int $lenght
     * @return string
     */
    protected static function randomNumber ( $lenght ) : string
    {
        $str = '';
        
        for ( $i = 0; $i < $lenght; $i++ )
        { $str .= mt_rand(0,9); }
        
        return ltrim($str, '0');
    }
}
