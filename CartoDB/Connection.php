<?php

/**
 * Main connection class. Mostly based on Vizzuality's CartoDB PHP class
 * https://github.com/Vizzuality/cartodbclient-php
 * 
 * @author tiagojsag
 */

namespace Simbiotica\CartoDBBundle\CartoDB;

use Symfony\Component\HttpFoundation\Session\Session;
use Eher\OAuth\Request;
use Eher\OAuth\Consumer;
use Eher\OAuth\Token;
use Eher\OAuth\HmacSha1;
use Eher\OAuth;

abstract class Connection
{
    const SESSION_KEY_SEED = "cartodb";
    
    /**
     * Session to store token
     **/
    protected $session;
    protected $sessionKey;
    
    /**
     * Necessary data to connect to CartoDB
     */
    protected $subdomain;
    
    /**
     * Internal variables
     */
    public $authorized = false;
    public $json_decode = true;
    
    /**
     * Endpoint urls
     */
    protected $oauthUrl;
    protected $apiUrl;

    function __construct(Session $session, $subdomain)
    {
        $this->sessionKey = Connection::SESSION_KEY_SEED.'-'.$subdomain;
        
        $this->session = $session;
        $this->subdomain = $subdomain;
        $this->apiUrl = sprintf('https://%s.cartodb.com/api/v2/', $this->subdomain);
        
        $this->authorized = $this->getAccessToken();
    }
    
    abstract protected function getAccessToken();
    
    public function setSession(Session $session) {
        $this->session = $session;
    }

    public function runSql($sql)
    {
        $params = array('q' => $sql);
        $payload = $this->request('sql', 'POST', array('params' => $params));

        $info = $payload->getInfo();
        $rawResponse = $payload->getRawResponse();
        if ($info['http_code'] != 200) {
            if (!empty($rawResponse['return']['error']))
                throw new \RuntimeException(sprintf(
                    'There was a problem with your CartoDB request "%s": %s',
                    $payload->getRequest()->__toString(),
                    implode('<br>', $rawResponse['return']['error'])));
            else
                throw new \RuntimeException(sprintf(
                    'There was a problem with your CartoDB request "%s"',
                    $payload->getRequest()->__toString()));
        }
        
        return $payload;
    }

    public function createTable($table, $schema = NULL)
    {
        $params = array('name' => $table);
        if ($schema) {
            $cols = array();
            foreach ($schema as $key => $value) {
                $cols[] = "$key $value";
            }
            $params['schema'] = implode(',', $cols);
        }
        return $this->request('tables', 'POST', array('params' => $params));
    }

    public function dropTable($table)
    {
        return $this->request("tables/$table", 'DELETE');
    }

    public function addColumn($table, $column_name, $column_type)
    {
        $params = array();
        $params['name'] = $column_name;
        $params['type'] = $column_type;
        return $this
                ->request("tables/$table/columns", 'POST',
                        array('params' => $params));
    }

    public function dropColumn($table, $column)
    {
        return $this->request("tables/$table/columns/$column", 'DELETE');
    }

    public function changeColumn($table, $column, $new_column_name,
            $new_column_type)
    {
        $params = array();
        $params['name'] = $new_column_name;
        $params['type'] = $new_column_type;
        return $this
                ->request("tables/$table/columns/$column", 'PUT',
                        array('params' => $params));
    }

    public function getTableNames()
    {
        $sql = "SELECT \"pg_class\".\"oid\", \"pg_class\".\"relname\" FROM \"pg_class\" INNER JOIN \"pg_namespace\" ON (\"pg_namespace\".\"oid\" = \"pg_class\".\"relnamespace\") WHERE ((\"relkind\" = 'r') AND (\"nspname\" = 'public') AND (\"relname\" NOT IN ('spatial_ref_sys', 'geography_columns', 'geometry_columns', 'raster_columns', 'raster_overviews', 'cdb_tablemetadata')))";
                
        return $this->runSql($sql);
    }

    public function getRow($table, $row)
    {
        return $this->request("tables/$table/records/$row");
    }

    /**
     * Inserts $data row into $table. 
     * 
     * @param unknown $table name of table
     * @param unknown $data pairs of name_of_row/value
     * 
     * @return cartodb_id of inserted row
     */
    public function insertRow($table, $data)
    {
        $keys = implode(',', array_keys($data));
        $values = array();
        foreach(array_values($data) as $key => $elem)
        {
            if(is_null($elem))
                $values[$key] = null;
            if (is_int($elem))
                $values[$key] = sprintf('%d', $elem);
            elseif (is_bool($elem))
                $values[$key] = sprintf('%s', $elem?'1':'0');
            elseif (is_string($elem))
                $values[$key] = sprintf('\'%s\'', $elem);
        }
        $valuesString = implode(',', $values);
        
        $sql = "INSERT INTO $table ($keys) VALUES($valuesString) RETURNING cartodb_id;";
        
        return $this->runSql($sql);
    }

    public function updateRow($table, $row_id, $data)
    {
        $keys = implode(',', array_keys($data));
        $values = array();
        foreach(array_values($data) as $key => $elem)
        {
            if(is_null($elem))
                $values[$key] = null;
            if (is_int($elem))
                $values[$key] = sprintf('%d', $elem);
            elseif (is_bool($elem))
                $values[$key] = sprintf('%s', $elem?'1':'0');
            elseif (is_string($elem))
                $values[$key] = sprintf('\'%s\'', $elem);
        }
        $valuesString = implode(',', $values);
        
        $sql = "UPDATE $table SET ($keys) = ($valuesString) WHERE cartodb_id = $row_id RETURNING cartodb_id;";
        
        return $this->runSql($sql);
    }

    public function deleteRow($table, $row_id)
    {
        $sql = "DELETE FROM $table WHERE cartodb_id = $row_id;";
        return $this->runSql($sql);
    }

    /**
     * Gets all the records of a defined table.
     * @param $table the name of table
     * @param $params array of parameters.
     *   Valid parameters:
     *   - 'rows_per_page' : Number of rows per page.
     *   - 'page' : Page index.
     *   - 'order' : array of $column => asc/desc.
     */
    public function getAllRows($table, $params = array())
    { 
        return $this->getAllRowsForColumns($table, null, $params);
    }

    /**
     * Gets given columns from all the records of a defined table.
     * @param $table the name of table
     * @param $params array of parameters.
     *   Valid parameters:
     *   - 'rows_per_page' : Number of rows per page.
     *   - 'page' : Page index.
     *   - 'order' : array of $column => asc/desc.
     */
    public function getAllRowsForColumns($table, $columns = null, $params = array())
    {
        return $this->getRowsForColumns($table, $columns, $filter = null, $params);
    }
    
    /**
     * Gets given columns from the records of a defined table that match the given condition.
     * @param $table the name of table
     * @param $params array of parameters.
     *   Valid parameters:
     *   - 'rows_per_page' : Number of rows per page.
     *   - 'page' : Page index.
     *   - 'order' : array of $column => asc/desc.
     */
    public function getRowsForColumns($table, $columns = null, $filter = null, $params = array())
    {
        if ($columns == null || !is_array($columns) || empty($columns))
            $columnsString = "*";
        else
            $columnsString = implode(', ', $columns);
        
        if ($filter == null || !is_array($filter) || empty($filter))
            $filterString = "1=1";
        else
        {
            $filterString = implode(' AND ', array_map(function($key, $elem)
            {
                if (is_int($elem))
                    return sprintf('%s = %d', $key, $elem);
                if (is_bool($elem))
                    return sprintf('%s = %s', $key, $elem?'1':'0');
                if (is_string($elem))
                    return sprintf('%s = \'%s\'', $key, $elem);
            }, array_keys($filter), $filter));
        }
        
        $extrasString = '';
        if (isset($params['rows_per_page']))
        {
            $extrasString .= sprintf(" LIMIT %s", $params['rows_per_page']);
            if (isset($params['page']))
                $extrasString .= sprintf(" OFFSET %s", $params['page']);
        }
        if (isset($params['order']))
        {
            $extrasString .= 'ORDER BY '.implode(',', array_map(function ($field, $order){
                return sprintf('%s %s', $field, $order);
            }, array_flip($params['order']), $params['order']));
        }
        
        $sql = sprintf("SELECT %s FROM %s WHERE %s %s", $columnsString, $table, $filterString, $extrasString);
        
        return $this->runSql($sql);
    }

    protected function http_parse_headers($header)
    {
        $retVal = array();
        $fields = explode("\r\n",
                preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e',
                        'strtoupper("\0")', strtolower(trim($match[1])));
                if (isset($retVal[$match[1]])) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        return $retVal;
    }

    protected function parse_query($var, $only_params = false)
    {
        /**
         *  Use this function to parse out the query array element from
         *  the output of parse_url().
         */
        if (!$only_params) {
            $var = parse_url($var, PHP_URL_QUERY);
            $var = html_entity_decode($var);
        }

        $var = explode('&', $var);
        $arr = array();

        foreach ($var as $val) {
            $x = explode('=', $val);
            $arr[$x[0]] = $x[1];
        }
        unset($val, $x, $var);
        return $arr;
    }
    
    protected function getToken() {
        return unserialize($this->session->get($this->sessionKey, null));
    }
    
    protected function setToken(Token $token) {
        if ($this->session == null)
            throw new \RuntimeException("Need a valid session to store CartoDB auth token");
        $this->session->set($this->sessionKey, serialize($token));
    }
}

?>