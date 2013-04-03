<?php

/**
 * @author tiagojsag
 */

namespace Simbiotica\CartoDBBundle\CartoDB;

use Simbiotica\CartoDBClient\PublicConnection;
use Simbiotica\CartoDBClient\PrivateConnection;
use Symfony\Component\HttpFoundation\Session\Session;

class ConnectionFactory
{
    protected $session;
    
    function __construct(Session $session)
    {
        $this->session = $session;
    }
    
    /**
     * Create a private connection by name.
     */
    public function createPrivateConnection($subdomain, $apiKey, $consumerKey, $consumerSecret, $email, $password)
    {
        $storage = new SymfonySessionStorage($this->session, $subdomain);
        return new PrivateConnection($storage, $subdomain, $apiKey, $consumerKey, $consumerSecret, $email, $password);
    }
    
    /**
     * Create a public connection by name.
     */
    public function createPublicConnection($subdomain)
    {
        $storage = new SymfonySessionStorage($this->session, $subdomain);
        return new PublicConnection($storage, $subdomain);
    }
}

?>