<?php

namespace Piggly\UrlFileSigner\Dict;

use RuntimeException;

class ParameterDict
{
    /** @var array A key pair array with name and alias for all files parameters. */
    private $params = array();
    
    /** @var array A key pair array with name and alias orderned to show in URL. */
    private $display;
    
    /** @var array A key pair array with name and alias orderned to get in file name. */
    private $inFile;
    
    /**
     * Creates a new instance.
     * 
     * @return \static
     */
    public static function create () 
    { return new static(); }
    
    /**
     * Add a parameter with name and alias. When alias is equal to null, then
     * the default alias is the first letter of parameter name.
     * 
     * @param type $name
     * @param type $alias
     * @throws RuntimeException
     * @return \self
     */
    public function add ( string $name, string $alias = null ) : self
    { 
        if ( empty( $alias ) )
        { $alias = substr ( $name, 0, 1 ); }

        if ( $this->aliasExists ( $alias ) )
        { throw new RuntimeException( sprintf( 'The parameter `%s` as `%s` is already used.', $name, $alias ) ); }
        
        if ( $this->nameExists ( $name ) )
        { throw new RuntimeException( sprintf( 'Parameter `%s` already in use.', $name ) ); }
        
        $this->params[$name] = $alias; 
        return $this;
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
     * @param string $alias
     * @return \self
     */
    public function replaceAlias ( string $name, string $alias ) : self
    { 
        $this->getOrFail($name);
        $this->params[$name] = $alias;       
        return $this;
    }
    
    /**
     * Get the parameter alias.
     * 
     * @param string $name
     * @return string
     */
    public function getAlias ( string $name ) : string
    { 
        $this->getOrFail($name);
        return $this->params[$name];         
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
        $this->display = [];

        foreach ( $newSort as $param )
        {
            if ( $this->nameExists ( $param ) )
            { $this->display[$param] = $this->params[$param]; }
            else
            { throw new RuntimeException( sprintf( 'Parameter `%s` is invalid or does not exist.', $param ) ); }
        }

        $this->display = array_merge( $this->display, array_diff( $this->params, $this->display ) );
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
        $this->inFile = [];

        foreach ( $newSort as $param )
        {
            if ( $this->nameExists ( $param ) )
            { $this->inFile[$param] = $this->params[$param]; }
            else
            { throw new RuntimeException( sprintf( 'Parameter `%s` is invalid or does not exist.', $param ) ); }
        }

        $this->inFile = array_merge( $this->inFile, array_diff( $this->params, $this->inFile ) );
        return $this;
    }
    
    /**
     * Get all parameters without any sort.
     * 
     * @return array
     */
    public function params () : array
    { return $this->params; }
    
    /**
     * Get sorted parameters to display in URL.
     * 
     * @return array
     */
    public function display () : array
    { return isset ( $this->display ) ? $this->display : $this->params; }
    
    /**
     * Get sorted parameters to set in file name.
     * 
     * @return array
     */
    public function inFileName ()
    { return isset ( $this->inFile ) ? $this->inFile : $this->params; }
    
    /**
     * Check if a parameter name exists.
     * 
     * @param string $name
     * @return bool
     */
    public function nameExists ( string $name ) : bool
    { return isset( $this->params[$name] ); }
    
    /**
     * Check if a parameter alias exists.
     * 
     * @param string $alias
     * @return bool
     */
    public function aliasExists ( string $alias ) : bool
    { return in_array( $alias, array_values( $this->params ) ); }
            
    /**
     * Get only parameters names.
     * 
     * @return array
     */
    public function names () : array
    { return array_keys( $this->params ); }
            
    /**
     * Get only parameters alias.
     * 
     * @return array
     */
    public function aliases () : array
    { return array_values( $this->params ); }
            
    /**
     * Get only parameters alias.
     * 
     * @return array
     */
    public function onlyAliases ( array $names ) : array
    { 
        $output = [];
        
        foreach ( $names as $name )
        { $output[$name] = $this->getAlias($name); }
        
        return $output;
    }
    
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
        if ( !$this->nameExists ( $name ) )
        { throw new RuntimeException( sprintf( 'Parameter `%s` is invalid or does not exist.', $name ) ); }
    }
}