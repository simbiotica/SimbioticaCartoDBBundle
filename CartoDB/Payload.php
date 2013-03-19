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

    function getColumnValues($name) {
        if(is_null($this->data) )
        {
            return null;
        }
        elseif( isset(reset($this->data)->$name))
        {
            $result = array();
            foreach ($this->data as $index => $values)
            {
                $result[$index] = isset($values->$name)?$values->$name:null;
            }
            return $result;
        }
        return null;
    }
}

?>