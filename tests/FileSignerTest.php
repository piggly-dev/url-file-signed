<?php

namespace Piggly\Tests\UrlFileSigner;

use DateInterval;
use Piggly\UrlFileSigner\FileSigner;
use Piggly\UrlFileSigner\Collections\ParameterDict;
use Piggly\UrlFileSigner\Entities\File;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileSignerTest extends TestCase
{
    protected $baseUrl;
    protected $file;
    protected $signatureKey;
    
    protected function setUp ()
    {
        $this->baseUrl      = 'https://cdn.example.com/';
        $this->signatureKey = 'random_key';
        
        $this->file = File::create( ParameterDict::create()->add('version')->add('size')->add('compression') )->set('/path/to/file/image.jpg');
        
        $this->file->parameters->add('version', '1')
                               ->add('size', '1080x1080');
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
        FileSigner::create( $this->baseUrl, '' );
    }
    
    /** @test */
    public function itReturnsFalseWhenValidatingAForgedUrl ()
    {        
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        $signedUrl = $fileSigner->sign( $this->file, new DateInterval('PT1S') );
        
        // Modified any part from signed url
        $signedUrl = str_replace ( '/s1080x1080', '', $signedUrl );
        
        $this->assertFalse( $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFalseWhenValidatingAnExpiredUrl ()
    {
        // Expired URL with valid signature
        $signedUrl = 'https://cdn.example.com/v1/s1080x1080/564667334d4459784e7a51324f4659334e445a6d556a59324e6a6b32597a593156773d3d/image.jpg?op=djo6cw&oe=5DDFE582&oh=a40ab71257fcaaa91f99dbba5d6b883f';
        
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        $this->assertFalse( $fileSigner->validate($signedUrl) );
    }
    
    /** @test */
    public function itReturnsFileNameWhenValidatingAnNonExpiredUrlWithoutParameters ()
    {
        $file = File::create( ParameterDict::create()->add('version')->add('size')->add('compression') )->set('/path/to/file/image.jpg');
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        $signedUrl = $fileSigner->sign( $file, new DateInterval('P7D') );
        
        $this->assertEquals( '/path/to/file/image.jpg', $fileSigner->validate($signedUrl)['file'] );
    }
    
    /** @test */
    public function itReturnsFileNameWhenValidatingAnNonExpiredUrl ()
    {
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        $signedUrl = $fileSigner->sign( $this->file, new DateInterval('P7D') );
        
        $this->assertEquals( '/path/to/file/image_v1_s1080x1080.jpg', $fileSigner->validate($signedUrl)['file'] );
    }
    
    /** @test */
    public function itReturnsTrueWhenValidatingAnNonExpiredUrlWithQueryString ()
    {
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        $signedUrl = $fileSigner->sign( $this->file, new DateInterval('P7D'), ['cached'=>true] );
        
        $this->assertEquals( '/path/to/file/image_v1_s1080x1080.jpg', $fileSigner->validate($signedUrl)['file'] );
    }
    
    /** @test */
    public function itReturnsTrueWhenValidatingAnNonExpiredUrlWithNonSignedQueryString ()
    {
        $fileSigner = FileSigner::create( $this->baseUrl, $this->signatureKey );
        $signedUrl = $fileSigner->sign( $this->file, new DateInterval('P7D') );
        
        $this->assertEquals( '/path/to/file/image_v1_s1080x1080.jpg', $fileSigner->validate($signedUrl.'&another_param=something')['file'] );
    }
    
    public function unsignedUrlProvider()
    {
        return [
            ['https://cdn.example.com/565659334d4459784e7a51324f4649334e445a6d557a59324e6a6b32597a593156513d3d/image.jpg?oh=0633ef94eeb563883c1fa30a4ac38f7a'],
            ['https://cdn.example.com/565659334d4459784e7a51324f4649334e445a6d557a59324e6a6b32597a593156513d3d/image.jpg?oe=5DE92509'],
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
