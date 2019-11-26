<?php

namespace Piggly\UrlFileSigner\Tests\Dict;

use Piggly\UrlFileSigner\Dict\ParameterDict;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ParameterDictTest extends TestCase
{
    /** @test */
    public function itIsInitialized ()
    {
        $dict = ParameterDict::create();
        $this->assertInstanceOf( ParameterDict::class, $dict );
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenAliasAlreadyExists ()
    {
        $this->expectException( RuntimeException::class );
        $dict = ParameterDict::create()
                    ->add('compression')
                    ->add('contrast');
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenNameAlreadyExists ()
    {
        $this->expectException( RuntimeException::class );
        $dict = ParameterDict::create()
                    ->add('compression','c')
                    ->add('compression','cc');
    }
    
    /** @test */
    public function itAddedNamesWithSuccess ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version');
        
        $this->assertEquals( ['size'=>'s','version'=>'v'], $dict->params());
    }
    
    /** @test */
    public function itAddedNamesAndAliasesWithSuccess ()
    {
        $dict = ParameterDict::create()
                    ->add('size','x')
                    ->add('version','vs');
        
        $this->assertEquals( ['size'=>'x','version'=>'vs'], $dict->params());
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenDeleteANonExistingParameter ()
    {
        $this->expectException( RuntimeException::class );
        $dict = ParameterDict::create()->delete('size');
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenGetANonExistingParameter ()
    {
        $this->expectException( RuntimeException::class );
        $dict = ParameterDict::create()->getAlias('size');
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenSortingANonExistingParameterToDisplay ()
    {
        $this->expectException( RuntimeException::class );
        $dict = ParameterDict::create()->sortToDisplay(['size']);
    }
    
    /** @test */
    public function itWillThrowAnExceptionWhenSortingANonExistingParameterInFileName ()
    {
        $this->expectException( RuntimeException::class );
        $dict = ParameterDict::create()->sortInFileName(['size']);
    }
    
    /** @test */
    public function itSortedToDisplay ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version')
                    ->sortToDisplay (['version','size']);
        
        $this->assertEquals( ['version'=>'v','size'=>'s'], $dict->display());
    }
    
    /** @test */
    public function itSortedOnlySomeParametersToDisplay ()
    {
        $dict = ParameterDict::create()
                    ->add('compression')
                    ->add('size')
                    ->add('version')
                    ->sortToDisplay (['version']);
        
        $this->assertEquals( ['version'=>'v','compression'=>'c','size'=>'s'], $dict->display());
    }
    
    /** @test */
    public function itSortedToSetInFileName ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version')
                    ->sortInFileName (['version','size']);
        
        $this->assertEquals( ['version'=>'v','size'=>'s'], $dict->display());
    }
    
    /** @test */
    public function itSortedOnlySomeParametersToSetInFileName ()
    {
        $dict = ParameterDict::create()
                    ->add('compression')
                    ->add('size')
                    ->add('version')
                    ->sortInFileName (['version']);
        
        $this->assertEquals( ['version'=>'v','compression'=>'c','size'=>'s'], $dict->display());
    }
    
    /** @test */
    public function itReturnsTheDefaultSortWhenNotSortingToDisplay ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version');
        
        $this->assertEquals( ['size'=>'s','version'=>'v'], $dict->display());
    }
    
    /** @test */
    public function itReturnsTheDefaultSortWhenNotSortingToSetInFileName ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version');
        
        $this->assertEquals( ['size'=>'s','version'=>'v'], $dict->inFileName());
    }
    
    /** @test */
    public function itReturnsOnlyParameterNames ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version');
        
        $this->assertEquals( ['size','version'], $dict->names());
    }
    
    /** @test */
    public function itReturnsOnlyParameterAliases ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version');
        
        $this->assertEquals( ['s','v'], $dict->aliases());
    }
    
    /** @test */
    public function itReturnsParametersCount ()
    {
        $dict = ParameterDict::create()
                    ->add('size')
                    ->add('version');
        
        $this->assertEquals( 2, $dict->count());
    }
}