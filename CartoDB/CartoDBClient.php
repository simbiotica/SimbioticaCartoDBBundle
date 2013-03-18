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

class CartoDBClient
{
    const SESSION_KEY = "cartodb";
    
    /**
     * Session to store token
     **/
    protected $session;
    
    /**
     * Necessary data to connect to CartoDB
     */
    public $key;
    public $secret;
    public $email;
    public $password;
    public $subdomain;
    
    /**
     * Internal variables
     */
    public $authorized = false;
    public $json_decode = true;
    
    /**
     * Endpoint urls
     */
    private $oauthUrl;
    private $apiUrl;

    /**
     * Constructs CartoDB connection and stores token in session
     * @throws RuntimeException on connection or auth failure
     * 
     * @param Session $session
     * @param unknown $key
     * @param unknown $secret
     * @param unknown $subdomain
     * @param unknown $email
     * @param unknown $password
     */
    function __construct(Session $session, $key, $secret, $subdomain, $email, $password)
    {
        $this->session = $session;
        
        $this->key = $key;
        $this->secret = $secret;
        $this->subdomain = $subdomain;
        $this->email = $email;
        $this->password = $password;

        $this->oauthUrl = sprintf('https://%s.cartodb.com/oauth/', $this->subdomain);
        $this->apiUrl = sprintf('https://%s.cartodb.com/api/v2/', $this->subdomain);

        $this->authorized = $this->getAccessToken();
    }
    
    public function setSession(Session $session) {
        $this->session = $session;
    }

    function __toString()
    {
        return "OAuthConsumer[key=$this->key, secret=$this->secret]";
    }

    private function request($uri, $method = 'GET', $args = array())
    {
        $url = $this->apiUrl . $uri;
        $sig_method = new HmacSha1();
        $consumer = new Consumer($this->key, $this->secret, NULL);
        $token = $this->getToken();

        $acc_req = Request::from_consumer_and_token($consumer, $token,
                $method, $url, isset($args['params'])?$args['params']:array());
        if (!isset($args['headers']['Accept'])) {
            $args['headers']['Accept'] = 'application/json';
        }

        $acc_req->sign_request($sig_method, $consumer, $token);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $acc_req->to_postdata());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);

        $response = array();
        $response['return'] = ($this->json_decode) ? (array) json_decode(
                        curl_exec($ch)) : curl_exec($ch);
        $response['info'] = curl_getinfo($ch);

        curl_close($ch);

        if ($response['info']['http_code'] == 401) {
            $this->authorized = $this->getAccessToken();
            return $this->request($uri, $method, $args);
        }
        
        $payload = new Payload($acc_req);
        $payload->setRawResponse($response);
        return $payload;
    }

    public function runSql($sql)
    {
        $params = array('q' => $sql);
        $payload = $this->request('sql', 'POST', array('params' => $params));

        if ($payload->getInfo()['http_code'] != 200) {
            throw new \RuntimeException(
                    'There was a problem with your request: '
                            . implode('<br>', $payload->getRawResponse()['return']['error']));
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

    public function getTables()
    {
        $sql = "select * from information_schema.tables WHERE table_type='BASE TABLE'";
                
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

    private function getAccessToken()
    {
        $sig_method = new HmacSha1();
        $consumer = new Consumer($this->key, $this->secret, NULL);

        $params = array('x_auth_username' => $this->email,
                'x_auth_password' => $this->password,
                'x_auth_mode' => 'client_auth');

        $acc_req = Request::from_consumer_and_token($consumer, NULL,
                "POST", $this->oauthUrl . 'access_token', $params);

        $acc_req->sign_request($sig_method, $consumer, NULL);
        $ch = curl_init($this->oauthUrl . 'access_token');
        curl_setopt($ch, CURLOPT_POST, True);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $acc_req->to_postdata());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] != 200) {
            throw new \RuntimeException('Authorization failed for this key and secret.');
        }
        //Got the token, now let's store it in session
        $rawTokenData = $this->parse_query($response, true);
        $this->setToken(new Token($rawTokenData['oauth_token'],
                $rawTokenData['oauth_token_secret']));
        return true;
    }

    private function http_parse_headers($header)
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

    private function parse_query($var, $only_params = false)
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
    
    private function getToken() {
        return unserialize($this->session->get(self::SESSION_KEY, null));
    }
    
    private function setToken(Token $token) {
        if ($this->session == null)
            throw new \RuntimeException("Need a valid session to store CartoDB auth token");
        $this->session->set(self::SESSION_KEY, serialize($token));
    }
}

?>