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
    const SESSION_KEY = "cartodb";
    
    /**
     * Session to store token
     **/
    protected $session;
    
    /**
     * Necessary data to connect to CartoDB
     */
    protected $key;
    protected $secret;
    protected $email;
    protected $password;
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

    public function setSession(Session $session) {
        $this->session = $session;
    }

    function __toString()
    {
        return "OAuthConsumer[key=$this->key, secret=$this->secret]";
    }

    public function runSql($sql)
    {
        $params = array('q' => $sql);
        $payload = $this->request('sql', 'POST', array('params' => $params));

        if ($payload->getInfo()['http_code'] != 200) {
            throw new \RuntimeException(
                    'There was a problem with your request: '
                            . implode('<br>', $payload->getRawResponse()['return']));
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
//         $sql = "select * from information_schema.tables WHERE table_type='BASE TABLE'";
        $sql = "SELECT \"pg_class\".\"oid\", \"pg_class\".\"relname\" FROM \"pg_class\" INNER JOIN \"pg_namespace\" ON (\"pg_namespace\".\"oid\" = \"pg_class\".\"relnamespace\") WHERE ((\"relkind\" = 'r') AND (\"nspname\" = 'public') AND (\"relname\" NOT IN ('spatial_ref_sys', 'geography_columns', 'geometry_columns', 'raster_columns', 'raster_overviews', 'cdb_tablemetadata')))";
                
        return $this->runSql($sql);
    }

    public function getRow($table, $row)
    {
        return $this->request("tables/$table/records/$row");
    }

    public function insertRow($table, $data)
    {
        $keys = implode(',', array_keys($data));
        $values = implode(',', array_values($data));
        $sql = "INSERT INTO $table ($keys) VALUES($values);";
        $sql .= "SELECT $table.cartodb_id as id, $table.* FROM $table ";
        $sql .= "WHERE cartodb_id = currval('public." . $table
                . "_cartodb_id_seq');";
        return $this->runSql($sql);
    }

    public function updateRow($table, $row_id, $data)
    {
        $keys = implode(',', array_keys($data));
        $values = implode(',', array_values($data));
        $sql = "UPDATE $table SET ($keys) = ($values) WHERE cartodb_id = $row_id;";
        $sql .= "SELECT $table.cartodb_id as id, $table.* FROM $table ";
        $sql .= "WHERE cartodb_id = currval('public." . $table
                . "_cartodb_id_seq');";
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
     */
    public function getRecords($table, $params = array())
    {
        return $this
                ->request("tables/$table/records", 'GET',
                        array('params' => $params));
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
        return unserialize($this->session->get(self::SESSION_KEY, null));
    }
    
    protected function setToken(Token $token) {
        if ($this->session == null)
            throw new \RuntimeException("Need a valid session to store CartoDB auth token");
        $this->session->set(self::SESSION_KEY, serialize($token));
    }
}

?>