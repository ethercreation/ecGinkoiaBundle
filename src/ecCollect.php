<?php
namespace bundles\ecGinkoiaBundle\src;

use stdClass;

class ecCollect
{
    /**
     * @var bundles\ecGinkoiaBundle\src\ecCollect
     */
    private static $instance;
    /**
     * @var array
     */
    protected $infos = [];
    /**
     * @var array
     */
    protected $warnings = [];
    /**
     * @var array
     */
    protected $errors = [];
    /**
     * @var array
     */
    protected $returns = [];
    /**
     * @var bool
     */
    protected $dump_each_update = false;
    /**
     * @var int
     */
    protected $output_type = 0;
    /**
     * @var array
     */
    private $output_types = [
        0 => 'console',
        1 => 'log',
        2 => 'file',
    ];

    /**
     * @return self
     */
    private function __construct()
    {
        return $this;
    }

    /**
     * @return self
     */
    public static function get()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return array
     */
    public function getInfos()
    {
        return $this->infos;
    }

    /**
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param string $function
     * @return array
     */
    public function getReturns($function = null)
    {
        if ($function) {
            return $this->returns[$function] ?? null;
        }

        return $this->returns;
    }
    
    /**
     * @return array
     */
    public function getOutputTypes()
    {
        return $this->output_types;
    }

    /**
     * @param int $type
     * @return self
     */
    public function setOutputType(int $type)
    {
        $this->output_type = $type;
        
        return $this;
    }

    /**
     * @param string $info
     * @return self
     */
    public function addInfo($info)
    {
        $this->infos[] = $info;
        
        if ($this->dump_each_update) {
            $this->output();
        }
        
        return $this;
    }

    /**
     * @param string $warning
     * @return self
     */
    public function addWarning($warning)
    {
        $this->warnings[] = $warning;
        
        if ($this->dump_each_update) {
            $this->output();
        }
        
        return $this;
    }

    /**
     * @param string $error
     * @return self
     */
    public function addError($error)
    {
        $this->errors[] = $error;
        
        if ($this->dump_each_update) {
            $this->output();
        }
        
        return $this;
    }

    /**
     * @param string $function
     * @param string $return
     * @return self
     */
    public function addReturn($function, $return)
    {
        $this->returns[$function][] = $return;
        
        if ($this->dump_each_update) {
            $this->output();
        }
        
        return $this;
    }
    
    /**
     * @return self
     */
    public function clear()
    {
        $this->infos = [];
        $this->warnings = [];
        $this->errors = [];
        $this->returns = [];

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
    
    public function output()
    {
        echo $this . "\n";
    }

    public function __destruct()
    {
//        $this->output();
    }    
}