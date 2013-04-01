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
        $table = 'test1';
        $schema = array(
                'name' => 'text',
                'description' => 'text',
                'somenumber' => 'numeric',
                'somedate' => 'date'
        );
        
        $client = static::createClient();
        $container = $client->getContainer();
        
        //wrong config fails auth
        $privateFailClient =  $container->get('simbiotica.cartodb_connection.private_fail');
        $this->assertFalse($privateFailClient->authorized);
        
        //correct config passes auth
        $privateClient =  $container->get('simbiotica.cartodb_connection.private');
        $this->assertTrue($privateClient->authorized);
        
        //Database can be probed for columns
        $tableNames = $privateClient->getTableNames()->getData();
        if(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)))
        {
            //Cleanup
            $privateClient->dropTable($table);
        }
        
        //Table is created
        $privateClient->createTable($table, $schema);
        $tableNames = $privateClient->getTableNames()->getData();
        $this->assertContains($table, array_map(function($item){return $item->relname;}, $tableNames));
        
        //Table has the right columns
        $columnData = $privateClient->showTable($table, true)->getData();
        foreach($columnData as $column)
        {
            if ($column->column_name == 'cartodb_id')
                $this->assertTrue($column->data_type == 'integer');
            else
                $this->assertTrue($schema[$column->column_name] == $column->data_type);
        }
        $columnNames = array_map(function($item){return $item->column_name;}, $columnData);
        $this->assertContains('cartodb_id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('description', $columnNames);
        $this->assertContains('somenumber', $columnNames);
        $this->assertContains('somedate', $columnNames);
        
        //Table doesn't have columns it shouldn't
        if (in_array('someothercolumn', $columnNames))
            $privateClient->dropColumn($table, 'someothercolumn');
        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
        //Columns can be added
        $privateClient->addColumn($table, 'someothercolumn', 'date');
        $this->assertTrue(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
        //Column names can be changed
        $privateClient->changeColumnName($table, 'someothercolumn', 'someothercolumnnewname');
        $this->assertTrue(in_array('someothercolumnnewname', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
        //Column types can be changed
        $privateClient->changeColumnType($table, 'someothercolumnnewname', 'text');
        $columnData = $privateClient->showTable($table, true)->getData();
        foreach($columnData as $column)
        {
            if ($column->column_name == 'someothercolumnnewname')
                $this->assertTrue($column->data_type == 'text');
        }
        
        //Columns can be removed
        $privateClient->dropColumn($table, 'someothercolumnnewname');
        $this->assertFalse(in_array('someothercolumnnewname', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())));
        
        //Tables can be deleted
        $privateClient->dropTable($table);
        $tableNames = $privateClient->getTableNames()->getData();
        $this->assertFalse(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)));
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