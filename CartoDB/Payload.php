<?php 

namespace Simbiotica\CartoDBBundle\CartoDB;

class Payload {

    /**
     * Request data
     */
    protected $request;
    
    /**
     * Response metadata
     */
    protected $time;
    protected $rowCount;
    protected $info;
    
    /**
     * Actual information requested in the query
     */
    protected $data;
    protected $rawResponse;

    function __construct($request)
    {
        $this->request = $request;
    }
    
    public function setRawResponse(array $rawResponse)
    {
        $this->rawResponse = $rawResponse;
        $this->time = isset($rawResponse['return']['time'])?$rawResponse['return']['time']:null;
        $this->rowCount = isset($rawResponse['return']['total_rows'])?$rawResponse['return']['total_rows']:null;
        $this->info = isset($rawResponse['info'])?$rawResponse['info']:null;
        $this->data = isset($rawResponse['return']['rows'])?$rawResponse['return']['rows']:array();
    }
    
    function __toString()
    {
        $return = $this->info['url'].' - HTTP CODE:'.$this->info['http_code'];
        if ($this->info['http_code'] == 200)
        {
            $return = $return.' - Row count:'.$this->rowCount;
        }
        return $return;
    }
    
    public function getRawResponse()
    {
        return $this->rawResponse;
    }
    
    public function getRequest()
    {
        return $this->request;
    }
    
    public function getTime()
    {
        return $this->time;
    }

    public function setTime($time)
    {
        $this->time = $time;
    }

    public function getRowCount()
    {
        return $this->rowCount;
    }

    public function setRowCount($rowCount)
    {
        $this->rowCount = $rowCount;
    }

    public function getInfo()
    {
        return $this->info;
    }

    public function setInfo($info)
    {
        $this->info = $info;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Gets an array with all values of $name column, indexed by $index
     * if $index is null, it will keep the original order returned from cartodb
     * 
     * @param string $name name of the column
     * @param string $index optional: index
     * @return NULL|multitype:
     */
    function getSingleColumnValues($name, $index = null) {
        if(is_null($this->data) )
        {
            return null;
        }
        elseif(isset(reset($this->data)->$name) && ($index == null || reset($this->data)->$index))
        {
            $result = array();
            foreach($this->data as $key => $obj)
            {
                $result[$index?$obj->$index:$key] = $obj->$name;
            }
            return $result;
        }
        return null;
    }
    
    /**
     * For each row, return an array with the values of columns $columns, indexed by $index
     * if $index is null, it will keep the original order returned from cartodb
     *
     * @param array $name names of the columns
     * @param string $index optional: index
     * @return NULL|multitype: array of rows, each with array of values
     */
    function getColumnsValues(array $columns, $index = null) {
        if(is_null($this->data) )
        {
            return null;
        }
        elseif($index == null || reset($this->data)->$index)
        {
            $result = array();
            foreach($this->data as $key => $obj)
            {
                $result[$index?$obj->$index:$key] = array_intersect_key(get_object_vars($obj), array_flip($columns));
            }
            
            var_dump($result);
            die;
            return $result;
            
            $mapper = function($obj) use ($columns) {
                return array_intersect_key(get_object_vars($obj), array_flip($columns));
            };
            
            return array_map($mapper, $this->data);
        }
    }
}

?>