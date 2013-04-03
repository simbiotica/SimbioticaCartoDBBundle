<?php 

namespace Simbiotica\CartoDBBundle\CartoDB;

use Eher\OAuth\Token;
use Symfony\Component\HttpFoundation\Session\Session;
use Simbiotica\CartoDBClient\TokenStorageInterface;

class SymfonySessionStorage implements TokenStorageInterface
{
    const SESSION_KEY_SEED = "cartodb";
    
    protected $session;
    protected $sessionKey;
    
    function __construct(Session $session, $subdomain)
    {
        $this->sessionKey = Connection::SESSION_KEY_SEED.'-'.$subdomain;
        $this->session = $session;
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