<?php

namespace Piggly\UrlFileSigner\Collections;

use RuntimeException;

class ParameterCollection
{
    /** @var array A key pair array with name and alias for all files parameters. */
    private $params = array();
    
    /** @var ParameterDict A dict with all parameters allowed. */
    public $allowed;
    
    /**
     * 
     * @param ParameterDict $params
     * @return $this
     */
    public function __construct ( ParameterDict $params )
    {
        $this->allowed = $params;
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
     * Add a parameter with name and value.
     * 
     * @param string $name
     * @param mixed $value
     * @throws RuntimeException
     * @return \self
     */
    public function add ( string $name, $value ) : self
    { 
        if ( !$this->allowed->nameExists ( $name ) )
        { throw new RuntimeException( sprintf( 'The parameter `%s` is not allowed.', $name ) ); }
        
        $this->params[$name] = $value; 
        return $this;
    }
    
    /**
     * Add one or many parameters values.
     * 
     * @param array $params
     * @throws RuntimeException
     * @return \self
     */
    public function fill ( $params ) : self
    {
        foreach ( $params as $param => $value )
        { $this->add( $param, $value ); }
        
        return $this;
    }
    
    /**
     * Extract and set parameters from path string and removes parameters
     * from path string.
     * 
     * @param type $path
     * @return string Path without parameters
     */
    public function extractFromPath ( $path ) : string 
    {
        foreach ( $this->allowed->inFileName() as $param => $alias )
        {
            $escapedDir = addcslashes(DIRECTORY_SEPARATOR, '\\\/');
            $regex = '/'.$escapedDir.$alias.'([a-z0-9]+)?/i';
            
            preg_match( $regex, $path, $matches );

            if ( !empty( $matches ) )
            { 
                $value = !empty($matches[1]) ? $matches[1] : '';
                $this->add( $param, $value );
            }
        }
        
        if ( $this->count() !== 0 )
        {
            $escapedDir = addcslashes(DIRECTORY_SEPARATOR, '\\\/');
            $regex = '/'.$escapedDir.'('.implode('|',$this->allowed->aliases()).')([a-z0-9]+)?/i';
            return preg_replace($regex, '', $path);
        }
        
        return $path;
    }
    
    /**
     * Delete a parameter by name.
     * 
     * @param string $name
     * @return \self
     */
    public function delete ( string $name ) : self
    { 
        $this->getOrFail($name);
        unset( $this->params[$name] ); 
        return $this;
    }
    
    /**
     * Replace the parameter alias.
     * 
     * @param string $name
     * @param mixed $value
     * @return \self
     */
    public function replace ( string $name, $value ) : self
    { 
        $this->getOrFail($name);
        $this->params[$name] = $value;       
        return $this;
    }
    
    /**
     * Get the parameter value.
     * 
     * @param string $name
     * @return string
     */
    public function get ( string $name ) : string
    { 
        $this->getOrFail($name);
        return $this->params[$name];         
    }
            
    /**
     * Get only the required parameters.
     * 
     * @return array
     */
    public function onlyParams ( array $names ) : array
    { 
        if ( empty( $this->params ) )
        { return []; }
        
        $params = [];

        foreach ( $this->allowed->params() as $param => $alias )
        {
            if ( in_array( $param, $names ) && $this->valueExists( $param ) )
            { $params[$param] = $this->get($param); }
        }
        
        return $params;
    }
    
    /**
     * Get all parameters formed.
     * 
     * @return array
     */
    public function params () : array
    { return $this->params; }
    
    /**
     * Get all parameters formed.
     * 
     * @return array
     */
    public function paramsToFileName () : array
    { 
        if ( empty( $this->params ) )
        { return []; }
        
        $params = [];

        foreach ( $this->allowed->inFileName() as $param => $alias )
        {
            if ( $this->valueExists($param) )
            { $params[] = $alias.$this->get($param); }
        }
        
        return $params;
    }
    
    /**
     * Get all parameters formed.
     * 
     * @return array
     */
    public function paramsToDisplay () : array
    { 
        if ( empty( $this->params ) )
        { return []; }
        
        $params = [];

        foreach ( $this->allowed->display() as $param => $alias )
        {
            if ( $this->valueExists($param) )
            { $params[] = $alias.$this->get($param); }
        }
        
        return $params;
    }
    
    /**
     * Check if a parameter name exists.
     * 
     * @param string $name
     * @return bool
     */
    public function valueExists ( string $name ) : bool
    { return isset( $this->params[$name] ); }
    
    /**
     * Get only parameters names.
     * 
     * @return array
     */
    public function names () : array
    { return array_keys( $this->params ); }
            
    /**
     * Get only parameters values.
     * 
     * @return array
     */
    public function values () : array
    { return array_values( $this->params ); }
    
    /**
     * Get parameters count.
     * 
     * @return int
     */
    public function count() : int
    { return count($this->params); }
    
    /**
     * Try to get a parameter name or throw an exception.
     * 
     * @param string $name
     * @throws RuntimeException
     */
    protected function getOrFail ( string $name )
    {
        if ( !$this->valueExists ( $name ) )
        { throw new RuntimeException( sprintf( 'Parameter `%s` is invalid or does not exist.', $name ) ); }
    }
}