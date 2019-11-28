<?php

namespace Piggly\Tests\UrlFileSigner\Dict;

use Piggly\UrlFileSigner\Collections\ParameterCollection;
use Piggly\UrlFileSigner\Collections\ParameterDict;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ParameterCollectionTest extends TestCase
{
    protected $paramsDict; 
    
    protected function setUp ()
    { $this->paramsDict = ParameterDict::create()->add('version')->add('size')->add('compression'); }
    
    /** @test */
    public function itIsInitialized ()
    {
        $collection = ParameterCollection::create($this->paramsDict);
        $this->assertInstanceOf( ParameterCollection::class, $collection );
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenParameterIsNotAllowed ()
    {
        $this->expectException( RuntimeException::class );
        ParameterCollection::create($this->paramsDict)->add ( 'brightness', 100 );
    }
    
    /** @test */
    public function itAddedParametersWithSuccess ()
    {
        $collection = ParameterCollection::create($this->paramsDict)
                            ->add ( 'size', 1080 )
                            ->add ( 'version', 1 );
        
        $this->assertEquals( ['size'=>1080,'version'=>1], $collection->params());
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenDeleteANonExistingParameter ()
    {
        $this->expectException( RuntimeException::class );
        ParameterCollection::create($this->paramsDict)->delete('size');
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenReplaceANonExistingParameter ()
    {
        $this->expectException( RuntimeException::class );
        ParameterCollection::create($this->paramsDict)->replace('size',1024);
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenGetANonExistingParameter ()
    {
        $this->expectException( RuntimeException::class );
        ParameterCollection::create($this->paramsDict)->get('size');
    }
    
    /** @test */
    public function itReturnsOnlySomeParameters ()
    {
        $collection = ParameterCollection::create($this->paramsDict)
                            ->add ( 'size', 1080 )
                            ->add ( 'version', 1 );
        
        $this->assertEquals( ['size'=>1080], $collection->onlyParams(['size']));
    }
    
    /** @test */
    public function itReturnsParametersToFileName ()
    {
        $collection = ParameterCollection::create($this->paramsDict)
                            ->add ( 'size', 1080 )
                            ->add ( 'version', 1 );
        
        $this->assertEquals( ['v1','s1080'], $collection->paramsToFileName());
    }
    
    /** @test */
    public function itReturnsParametersToDisplay ()
    {
        $collection = ParameterCollection::create($this->paramsDict)
                            ->add ( 'size', 1080 )
                            ->add ( 'version', 1 );
        
        $this->assertEquals( ['v1','s1080'], $collection->paramsToDisplay());
    }
    
    /** @test */
    public function itReturnsSortedParametersToFileName ()
    {
        $collection = ParameterCollection::create($this->paramsDict);
        $collection->allowed->sortInFileName( ['size'] );
        $collection->add( 'size', 1080 )->add( 'version', 1 );
        
        $this->assertEquals( ['s1080','v1'], $collection->paramsToFileName());
    }
    
    /** @test */
    public function itReturnsSortedParametersToDisplay ()
    {
        $collection = ParameterCollection::create($this->paramsDict);
        $collection->allowed->sortToDisplay( ['size'] );
        $collection->add( 'size', 1080 )->add( 'version', 1 );
        
        $this->assertEquals( ['s1080','v1'], $collection->paramsToDisplay());
    }
    
    /** @test */
    public function itSetParametersExtractedFromPath ()
    {
        $collection = ParameterCollection::create($this->paramsDict);
        $collection->extractFromPath('/v1/s1080');
        
        $this->assertEquals( ['version'=>1,'size'=>1080], $collection->params() );
    }
    
    /** @test */
    public function itReturnsAPathWithoutParameters ()
    {
        $collection = ParameterCollection::create($this->paramsDict);        
        $this->assertEquals( '/last', $collection->extractFromPath('/v1/s1080/last') );
    }
}