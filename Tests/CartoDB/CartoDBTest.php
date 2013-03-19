<?php 

namespace Simbiotica\CartoDBBundle\Tests\CartoDB;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Simbiotica\CartoDBBundle\DependencyInjection\SimbioticaCartoDBExtension;
use Simbiotica\CartoDBBundle\CartoDB\CartoDBClient;

class CalculatorTest extends WebTestCase
{
    public function testPrivateConnection()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        
        $privateFailClient =  $container->get('simbiotica.cartodb_connection.private_fail');
        $this->assertFalse($privateFailClient->authorized);
        
        $privateClient =  $container->get('simbiotica.cartodb_connection.private');
        $this->assertTrue($privateClient->authorized);
    }
    
    public function testPublicConnection()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        
        $publicFailClient =  $container->get('simbiotica.cartodb_connection.public_fail');
        $this->assertFalse($publicFailClient->authorized);
        
        $publicClient =  $container->get('simbiotica.cartodb_connection.public');
        $this->assertTrue($publicClient->authorized);
    }
}

?>