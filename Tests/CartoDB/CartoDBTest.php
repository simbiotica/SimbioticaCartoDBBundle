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
        $table = 'test_f923nf93mv9a';
        $schema = array(
                'name' => 'text',
                'description' => 'text',
                'somenumber' => 'numeric',
                'somedate' => 'timestamp without time zone'
        );
        
        $client = static::createClient();
        $container = $client->getContainer();
        
        //wrong config fails auth
        $privateFailClient =  $container->get('simbiotica.cartodb_connection.private_fail');
        $this->assertFalse($privateFailClient->authorized, 'Wrong credentials should make auth fail');
        
        //correct config passes auth
        $privateClient =  $container->get('simbiotica.cartodb_connection.private');
        $this->assertTrue($privateClient->authorized, 'Correct credentials should make auth succeed');
        
        /**
         * A change in CartoDB prevents fetching metadata, so this section is skipped.
         */
        $privateClient->dropTable($table);
        $privateClient->createTable($table, $schema);
        
//        //Database can be probed for columns
//        $tableNames = $privateClient->getTableNames()->getData();
//        if(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)))
//        {
//            //Cleanup
//            $privateClient->dropTable($table);
//        }
//        
//        //Table is created
//        $privateClient->createTable($table, $schema);
//        $tableNames = $privateClient->getTableNames()->getData();
//        $this->assertContains($table, array_map(function($item){return $item->relname;}, $tableNames), 'Created table should exist after created');
//        
//        //Table has the right columns
//        $columnData = $privateClient->showTable($table, true)->getData();
//        foreach($columnData as $column)
//        {
//            if ($column->column_name == 'cartodb_id')
//                $this->assertEquals($column->data_type, 'integer', 'Created columns should have the correct data types');
//            else
//                $this->assertEquals($schema[$column->column_name], $column->data_type, 'Created columns should have the correct data types');
//        }
//        $columnNames = array_map(function($item){return $item->column_name;}, $columnData);
//        $this->assertContains('cartodb_id', $columnNames, 'Created table should have the correct column names');
//        $this->assertContains('name', $columnNames, 'Created table should have the correct column names');
//        $this->assertContains('description', $columnNames, 'Created table should have the correct column names');
//        $this->assertContains('somenumber', $columnNames, 'Created table should have the correct column names');
//        $this->assertContains('somedate', $columnNames, 'Created table should have the correct column names');
//        
//        //Table doesn't have columns it shouldn't
//        if (in_array('someothercolumn', $columnNames))
//            $privateClient->dropColumn($table, 'someothercolumn');
//        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())), 'Created table shouldnt have extra columns');
//        
//        //Columns can be added
//        $privateClient->addColumn($table, 'someothercolumn', 'date');
//        $this->assertTrue(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())), 'Added columns should be present');
//        
//        //Column names can be changed
//        $privateClient->changeColumnName($table, 'someothercolumn', 'someothercolumnnewname');
//        $this->assertTrue(in_array('someothercolumnnewname', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())), 'Renamed columns should have new name');
//        $this->assertFalse(in_array('someothercolumn', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())), 'Renamed columns shouldnt have old name');
//        
//        //Column types can be changed
//        $privateClient->changeColumnType($table, 'someothercolumnnewname', 'text');
//        $columnData = $privateClient->showTable($table, true)->getData();
//        foreach($columnData as $column)
//        {
//            if ($column->column_name == 'someothercolumnnewname')
//                $this->assertTrue($column->data_type == 'text', 'Columns with changed data types must have new data type');
//        }
//        
//        //Columns can be removed
//        $privateClient->dropColumn($table, 'someothercolumnnewname');
//        $this->assertFalse(in_array('someothercolumnnewname', array_map(function($item){return $item->column_name;}, $privateClient->showTable($table, true)->getData())), 'Removed columns shouldnt exist afterwards');
        
        //Table is empty
        $privateClient->getAllRows($table);
        $this->assertEquals(0, $privateClient->getAllRows($table)->getRowCount(), 'Created tables should be empty');
        
        //Rows can be inserted
        $row1 = array(
                'name' => 'name of test row 1',
                'description' => 'description of test row 1',
                'somenumber' => 111,
                'somedate' => new \DateTime(),
        );
        $privateClient->insertRow($table, $row1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(1, $payload->getRowCount(), 'Row count should increase after insert operations');
        $data = $payload->getData();
        foreach(reset($data) as $name => $value)
        {
            if ($name == 'cartodb_id')
                $this->assertGreaterThanOrEqual(1, $value, 'Cartodb_id should be equal or greater than 1 after insert');
            //for now, skip dates, as we have to do some timezone jugling I don't have time for right now
            elseif($schema[$name] != 'timestamp without time zone')
                $this->assertEquals($row1[$name], $value, 'Inserted row values should match');
        }
        
        //Rows can be updated.
        $updatedRow1 = array(
                'name' => 'renamed test row 1',
                'description' => 'renamed description of test row 1',
                'somenumber' => 222,
                'somedate' => new \DateTime(),
        );
        $privateClient->updateRow($table, 1, $updatedRow1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(1, $payload->getRowCount(), 'Update operation should return updated row count');
        $data = $payload->getData();
        foreach(reset($data) as $name => $value)
        {
            if ($name == 'cartodb_id')
                $this->assertGreaterThanOrEqual(1, $value, 'Cartodb_id should be equal or greater than 1 after update');
            //for now, skip dates, as we have to do some timezone jugling I don't have time for right now
            elseif($schema[$name] != 'timestamp without time zone')
            $this->assertEquals($updatedRow1[$name], $value, 'Updated row values should match');
        }
        
        //Rows can be deleted
        $privateClient->deleteRow($table, 1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(0, $payload->getRowCount(), 'Row deletion should reduce row count');
        
        //Reinserting rows
        $privateClient->insertRow($table, $row1);
        $privateClient->insertRow($table, $row1);
        $privateClient->insertRow($table, $row1);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(3, $payload->getRowCount(), 'Row count should increase after insert operations');
        
        //Tables can be truncated
        $privateClient->truncateTable($table);
        $payload = $privateClient->getAllRows($table);
        $this->assertEquals(0, $payload->getRowCount(), 'Row deletion should be 0 after truncate oprations');
        
        //Tables can be deleted
        $privateClient->dropTable($table);
//        $tableNames = $privateClient->getTableNames()->getData();
//        $this->assertFalse(in_array($table, array_map(function($item){return $item->relname;}, $tableNames)), 'Tables shouldnt exist after dropped');
    }
    
    public function testPublicConnection()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        
        /**
         * A change in CartoDB prevents fetching this data, so this section is skipped.
         */
//        $publicFailClient =  $container->get('simbiotica.cartodb_connection.public_fail');
//        $this->assertFalse($publicFailClient->authorized, 'Connection to public tables should fail if parameters are wrong');
        
        $publicClient =  $container->get('simbiotica.cartodb_connection.public');
        $this->assertTrue($publicClient->authorized, 'Connection to public tables should succeed if parameters are correct');
    }
}

?>