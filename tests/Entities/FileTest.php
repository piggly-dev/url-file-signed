<?php

namespace Piggly\Tests\UrlFileSigner\Entities;

use Piggly\UrlFileSigner\Collections\ParameterDict;
use Piggly\UrlFileSigner\Entities\File;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileTest extends TestCase
{
    protected $paramsDict; 
    
    protected function setUp ()
    { 
        if ( DIRECTORY_SEPARATOR === '\\' ) 
        { $this->markTestSkipped('To this test DIRECTORY_SEPARATOR constant must be `/`.'); }
        
        $this->paramsDict = ParameterDict::create()->add('version')->add('size')->add('compression');
    }

    /** @test */
    public function itIsInitialized ()
    {
        $file = File::create( $this->paramsDict );
        $this->assertInstanceOf( File::class, $file );
    }

    /** @test */
    public function itRetursASimpleFileNames ()
    {
        $file = File::create( $this->paramsDict )->set('/path/to/file/image.jpg');
        $this->assertEquals( '/path/to/file/image.jpg', $file->getFileName() );
    }

    /** @test */
    public function itRetursASimpleFileNameWithoutFirstBackslash ()
    {
        $file = File::create( $this->paramsDict )->set('path/to/file/image.jpg');
        $this->assertEquals( 'path/to/file/image.jpg', $file->getFileName() );
    }

    /** @test */
    public function itRetursASimpleFileNameWithoutPath ()
    {
        $file = File::create( $this->paramsDict )->set('image.jpg');
        $this->assertEquals( 'image.jpg', $file->getFileName() );
    }

    /** @test */
    public function itRetursASimpleFileNameCreatedByHand ()
    {
        $file = File::create( $this->paramsDict )
                    ->setName('image')
                    ->setPath('path/to/file')
                    ->setExtension('png');
        
        $this->assertEquals( 'path/to/file/image.png', $file->getFileName() );
    }
    
    /** @test */
    public function itReturnsAnUniqueNumericFileName ()
    {
        $file = File::create( $this->paramsDict )
                    ->setRandomName()
                    ->setPath('path/to/file/')
                    ->setExtension('png');
        
        $this->assertRegExp('/^path\/to\/file\/[0-9]{8,}_[0-9]{15,}_[0-9]{1,19}.png/', $file->getFileName() );
    }

    /** @test */
    public function itRetursAFileNameWithParameters ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/path/to/file/image.jpg')
                ->parameters->add('version', '1')
                            ->add('size', '1080x1080');
        
        $this->assertEquals( '/path/to/file/image_v1_s1080x1080.jpg', $file->getFileName() );
    }
    
    /** @test */
    public function itReturnsAnUniqueNumericFileNameWithParameters ()
    {
        $file = File::create( $this->paramsDict )
                    ->setRandomName()
                    ->setPath('path/to/file/')
                    ->setExtension('png');
        
        $file->parameters->add('version', '1')->add('size', '1080x1080');        
        $this->assertRegExp('/^path\/to\/file\/[0-9]{8,}_[0-9]{15,}_[0-9]{1,19}_v1_s1080x1080.png/', $file->getFileName() );
    }

    /** @test */
    public function itRetursAFileNameWithOneParameter ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/path/to/file/image.jpg')
                ->parameters->add('size', '1080x1080');
        
        $this->assertEquals( '/path/to/file/image_s1080x1080.jpg', $file->getFileName() );
    }

    /** @test */
    public function itRetursAFileNameWithParametersChangingOrder ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/path/to/file/image.jpg')
                ->sortInFileName( ['size','compression'] )
                ->parameters->add('version', '1')
                            ->add('size', '1080x1080');
        
        $this->assertEquals( '/path/to/file/image_s1080x1080_v1.jpg', $file->getFileName() );
    }

    /** @test */
    public function itRetursAnEncodedPath ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/2019/11/image.jpg')
                ->parameters->add('size', '1080x1080');
        
        $this->assertRegExp('/^\/[a-f0-9]+\/$/', $file->encodePath() );
    }

    /** @test */
    public function itRetursADecodedPath ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/2019/11/image.jpg')
                ->parameters->add('size', '1080x1080');
        
        $file->encodePath();
        $this->assertEquals( '/2019/11/', $file->decodePath() );
    }

    /** @test */
    public function itRetursAFileNameWithEncodedPaths ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/2019/11/image.jpg')
                ->parameters->add('size', '1080x1080');
        
        $this->assertRegExp('/^\/[a-f0-9]+\/image_s1080x1080.jpg$/', $file->getFileNameEncoded() );
    }

    /** @test */
    public function itRetursAFileNameWithDecodedPaths ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/2019/11/image.jpg')
                ->parameters->add('size', '1080x1080');
        
        $file->encodePath();
        $this->assertEquals( '/2019/11/image_s1080x1080.jpg', $file->getFileNameDecoded() );
    }

    /** @test */
    public function itRetursAEncodedFileNameFromUri ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/2019/11/image.jpg')
                ->sortInFileName( ['size'] )
                ->parameters->add('version', '1')
                            ->add('size', '1080x1080');
        
        $this->assertRegExp('/^\/v1\/s1080x1080\/[a-f0-9]+\/image.jpg$/', $file->encodeToUri() );
    }

    /** @test */
    public function itRetursADecodedFileNameFromUri ()
    {
        $file = File::create( $this->paramsDict );
        
        $file->set('/2019/11/image.jpg')
                ->sortInFileName( ['size'] )
                ->parameters->add('version', '1')
                            ->add('size', '1080x1080');
        
        $uri = $file->encodeToUri();
        $ord = $file->getOrderOfParamsInFileName();
                
        $this->assertEquals('/2019/11/image_s1080x1080_v1.jpg', File::decodeUri( $uri, $ord ) );
    }

    /** @test */
    public function itWillThrowAnExceptionWhenFileNameHasNoExtension ()
    {
        $this->expectException( RuntimeException::class );
        File::create( $this->paramsDict )->set('image');
    }

    /** @test */
    public function itWillThrowAnExceptionWhenFileNameCreatedByHandHasNoExtension ()
    {
        $this->expectException( RuntimeException::class );
        File::create( $this->paramsDict )
            ->setName('image')
            ->setPath('path/to/file')
            ->getFileName();
    }
}
