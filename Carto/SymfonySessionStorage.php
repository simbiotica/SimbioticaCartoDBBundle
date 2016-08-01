<?php

namespace Simbiotica\CartoBundle\Carto;

use Simbiotica\CartoDBClient\TokenStorageInterface;
use Eher\OAuth\Token;
use Symfony\Component\HttpFoundation\Session\Session;

class SymfonySessionStorage implements TokenStorageInterface
{
    const SESSION_KEY_SEED = "cartodb";

    protected $session;
    protected $sessionKey;

    function __construct(Session $session, $subdomain)
    {
        $this->sessionKey = SymfonySessionStorage::SESSION_KEY_SEED.'-'.$subdomain;
        $this->session = $session;
    }

    public function getToken()
    {
        return unserialize($this->session->get($this->sessionKey, null));
    }

    public function setToken(Token $token)
    {
        if ($this->session == null) {
            throw new \RuntimeException("Need a valid session to store CartoDB auth token");
        }
        $this->session->set($this->sessionKey, serialize($token));
    }
}


?>