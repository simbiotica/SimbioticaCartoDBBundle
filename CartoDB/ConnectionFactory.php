<?php

/**
 * @author tiagojsag
 */

namespace Simbiotica\CartoDBBundle\CartoDB;

use Symfony\Component\HttpFoundation\Session\Session;

class ConnectionFactory
{
    protected $session;
    
    function __construct(Session $session)
    {
        $this->session = $session;
    }
    
    /**
     * Create a connection by name.
     */
    public function createConnection($key, $secret, $subdomain, $email, $password)
    {
        return new Connection($this->session, $key, $secret, $subdomain, $email, $password);
    }
}

?>