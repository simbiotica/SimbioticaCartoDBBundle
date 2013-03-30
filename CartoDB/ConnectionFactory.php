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
     * Create a private connection by name.
     */
    public function createPrivateConnection($subdomain, $apiKey, $consumerKey, $consumerSecret, $email, $password)
    {
        return new PrivateConnection($this->session, $subdomain, $apiKey, $consumerKey, $consumerSecret, $email, $password);
    }
    
    /**
     * Create a public connection by name.
     */
    public function createPublicConnection($subdomain)
    {
        return new PublicConnection($this->session, $subdomain);
    }
}

?>