<?php

namespace Piggly\UrlFileSigner\Tests;

use DateInterval;
use Piggly\UrlFileSigner\FileSigner;
use Piggly\UrlFileSigner\Dict\ParameterDict;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileSignerTest extends TestCase
{
    protected $baseUrl;
    protected $filePath;
    protected $signatureKey;
    
    protected function setUp ()
    {
        $this->baseUrl = 'https://cdn.example.com/';
        
        $this->filePath = DIRECTORY_SEPARATOR 
                . 'path' 
                . DIRECTORY_SEPARATOR 
                . 'to' 
                . DIRECTORY_SEPARATOR 
                . 'file' 
                . DIRECTORY_SEPARATOR 
                . 'image.jpg';
        
        $this->signatureKey = 'random_key';
    }

    /** @test */
    public function itIsInitialized ()
    {
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        
        $this->assertInstanceOf( FileSigner::class, $fileSigner );
    }
    
    /** @test */
    public function itWillThrowAnExceptionForAnEmptySignatureKey ()
    {
        $this->expectException( RuntimeException::class );
        
        $fileSigner = FileSigner::create( $this->baseUrl, '' );
    }
    
    /** @test */
    public function itReturnsAnUniqueNumericFileNameWithExtensionIncludingDot ()
    {
        $fileName = FileSigner::createUniqueFileName('.jpg');
        
        $this->assertRegExp('/^[0-9]{8,}_[0-9]{15,}_[0-9]{1,19}.jpg$/', $fileName);
    }
    
    /** @test */
    public function itReturnsAnUniqueNumericFileNameWithExtensionNotIncludingDot ()
    {
        $fileName = FileSigner::createUniqueFileName('jpg');
        
        $this->assertRegExp('/^[0-9]{8,}_[0-9]{15,}_[0-9]{1,19}.jpg$/', $fileName);
    }
    
    /** @test */
    public function itWillThrowAnExceptionForNonFileParamsWhenAppendingParamsToFileName ()
    {
        $this->expectException( RuntimeException::class );
        
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        FileSigner::appendParamsToFileName( $fileSigner, '/path/to/file/image.jpg', ['size'=>250] );
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenNotUsingFileExtensionWhileAppendingParamsToFileName ()
    {
        $this->expectException( RuntimeException::class );
        
        $fileParams = ParameterDict::create()->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        FileSigner::appendParamsToFileName( $fileSigner, '/path/to/file/image', ['size'=>250] );
    }
    
    /** @test */
    public function itWillThrowAnExceptionForNotValidParametersWhileAppendingParamsToFileName ()
    {
        $this->expectException( RuntimeException::class );
        
        $fileParams = ParameterDict::create()->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        FileSigner::appendParamsToFileName( $fileSigner, '/path/to/file/image.jpg', ['size'=>250,'version'=>1] );
    }
    
    /** @test */
    public function itReturnsAFileNameWithParams ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        $fileName   = FileSigner::appendParamsToFileName( $fileSigner, 'image.jpg', ['version' => 'private', 'size' => 1024]);
        
        $this->assertEquals( 'image_vprivate_s1024.jpg', $fileName );
    }
    
    /** @test */
    public function itReturnsAFileNameWithOnlyOneParam ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        $fileName   = FileSigner::appendParamsToFileName( $fileSigner, 'image.jpg', ['size' => 1024]);
        
        $this->assertEquals( 'image_s1024.jpg', $fileName );
    }
    
    /** @test */
    public function itReturnsAFileNameWithOnlyOneParamWithAlias ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size','sx');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        $fileName   = FileSigner::appendParamsToFileName( $fileSigner, 'image.jpg', ['size' => 1024]);
        
        $this->assertEquals( 'image_sx1024.jpg', $fileName );
    }
    
    /** @test */
    public function itReturnsAFileNameIncludindDirectoryWithParams ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $fileName = FileSigner::appendParamsToFileName( $fileSigner, $this->filePath, ['version' => 'private', 'size' => 1024]);
        
        $expected = DIRECTORY_SEPARATOR 
                . 'path' 
                . DIRECTORY_SEPARATOR 
                . 'to' 
                . DIRECTORY_SEPARATOR 
                . 'file' 
                . DIRECTORY_SEPARATOR 
                . 'image_vprivate_s1024.jpg';
        
        $this->assertEquals( $expected, $fileName );
    }
    
    /** @test */
    public function itReturnsFalseWhenValidatingAForgedUrl ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $fileName  = FileSigner::appendParamsToFileName($fileSigner, $this->filePath, ['version' => 'private', 'size' => 1024]);
        $signedUrl = $fileSigner->sign( $fileName, new DateInterval('P7D') );
        
        // Modified any part from signed url
        $signedUrl = str_replace ( '/s1024', '', $signedUrl );
        
        $this->assertFalse( $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFalseWhenValidatingAnExpiredUrl ()
    {
        // Expired URL with valid signature
        $signedUrl = 'https://cdn.example.com/vprivate/sx1024/566a63774e6a45334e445934565463304e6b5a554e6a59324f545a444e6a553d/image.jpg?oh=f91d84088d3d30ff6ec6b9fc723301ff&oe=5DBB7580';
        
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $this->assertFalse( $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFileNameWhenValidatingAnNonExpiredWithoutParameters ()
    {
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        $signedUrl = $fileSigner->sign( $this->filePath, new DateInterval('P7D') );
        
        $this->assertEquals( $this->filePath, $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFileNameWhenValidatingAnNonExpiredUrl ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $fileName  = FileSigner::appendParamsToFileName($fileSigner, $this->filePath,['version'=> '', 'size' => 1024]);
        $signedUrl = $fileSigner->sign( $fileName, new DateInterval('P7D') );
        
        $this->assertEquals( $fileName, $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFileNameWhenValidatingAnNonExpiredUrlDifferentPath ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $fileName  = FileSigner::appendParamsToFileName($fileSigner, 'image.jpg',['version'=> '', 'size' => 1024]);
        $signedUrl = $fileSigner->sign( $fileName, new DateInterval('P7D') );
        
        $this->assertEquals( DIRECTORY_SEPARATOR.$fileName, $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFileNameWhenValidatingAnNonExpiredUrlAnotherDifferentPath ()
    {
        $fileParams = ParameterDict::create()->add('version')->add('size');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $fileName  = FileSigner::appendParamsToFileName($fileSigner, 'test/image.jpg',['version'=> '', 'size' => 1024]);
        $signedUrl = $fileSigner->sign( $fileName, new DateInterval('P7D') );
        
        $this->assertEquals( DIRECTORY_SEPARATOR.$fileName, $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFileNameWhenValidatingAnNonExpiredUrlSortingDisplayUrlParams ()
    {
        $fileParams = ParameterDict::create()->add('size')->add('version');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        // \path\to\file\image_s1024_v1.jpg
        $fileName  = FileSigner::appendParamsToFileName($fileSigner, $this->filePath,['version'=> '1', 'size' => 1024]);
        
        // Ordering display parameters to version,size
        $fileSigner->getAllowedFileParams()->sortToDisplay(['version']);
        
        $signedUrl = $fileSigner->sign( $fileName, new DateInterval('P7D') );
        
        $this->assertEquals( $fileName, $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsTrueWhenValidatingAnNonExpiredUrlWithQueryString ()
    {
        $fileParams = ParameterDict::create()->add('size')->add('version');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $fileName  = FileSigner::appendParamsToFileName($fileSigner, $this->filePath, ['size' => 1024]);
        $signedUrl = $fileSigner->sign( $fileName, new DateInterval('P7D'), ['cached'=>true] );
        
        $this->assertEquals( $fileName, $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsTrueWhenValidatingAnNonExpiredUrlWithNonSignedQueryString ()
    {
        $fileParams = ParameterDict::create()->add('size')->add('version');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey )
                        ->addAllowedFileParams ( $fileParams );
        
        $fileName  = FileSigner::appendParamsToFileName($fileSigner, $this->filePath, ['size' => 1024]);
        $signedUrl = $fileSigner->sign( $fileName, new DateInterval('P7D') );
        
        $this->assertEquals( $fileName, $fileSigner->validate($signedUrl.'&another_param=something') );
    }
    
    public function unsignedUrlProvider()
    {
        return [
            ['https://cdn.example.com/vprivate/s1024/546a64464d314a43/image-for-testing.jpg?oe=5DBB7580'],
            ['https://cdn.example.com/vprivate/s1024/546a64464d314a43/image-for-testing.jpg?oh=0d3b9ca9977b5bd4d152dccd8f59f322'],
        ];
    }
    
    /**
     * @test
     * @dataProvider unsignedUrlProvider
     */
    public function itReturnsFalseWhenValidatingAnUnsignedUrl ($unsignedUrl)
    {
        // Generating a valid signed url
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        
        $this->assertFalse($fileSigner->validate($unsignedUrl));
    }
}
