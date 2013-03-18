<?php 

namespace Simbiotica\CartoDBBundle\Tests\CartoDB;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Simbiotica\CartoDBBundle\DependencyInjection\SimbioticaCartoDBExtension;
use Simbiotica\CartoDBBundle\CartoDB\CartoDBClient;

class CalculatorTest extends WebTestCase
{
    public function testBasicConnection()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $cartoDBClient =  $container->get('simbiotica.cartodb.client');
        
        $this->assertTrue($cartoDBClient->authorized);
        $tables = $cartoDBClient->getTables();
        
        print_r($tables->__toString());
    }
}

?>