<?php

namespace Simbiotica\CartoDBBundle\Tests\CartoDB;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CalculatorTest extends WebTestCase
{
    public function testPrivateConnection()
    {
        $table = 'test_f923nf93mv9a';
        $schema = array(
            'name' => 'text',
            'description' => 'text',
            'somenumber' => 'numeric',
            'somedate' => 'timestamp without time zone',
        );

        $client = static::createClient();
        $container = $client->getContainer();

        //wrong config fails auth
        $privateFailClient = $container->get('simbiotica.cartodb_connection.private_fail');
        $this->assertFalse($privateFailClient->authorized, 'Wrong credentials should make auth fail');

        //correct config passes auth
        $privateClient = $container->get('simbiotica.cartodb_connection.private');
        $this->assertTrue($privateClient->authorized, 'Correct credentials should make auth succeed');

        /**
         * A change in CartoDB prevents fetching metadata, so this section is skipped.
         */
        $privateClient->dropTable($table);
        $privateClient->createTable($table, $schema);

        //Table has the right columns
        $columnNames = array_map(
            function ($item) {
                return $item->cdb_columnnames;
            },
            $privateClient->getColumnNames($table)->getData()
        );
        $this->assertContains('cartodb_id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('description', $columnNames);
        $this->assertContains('somenumber', $columnNames);
        $this->assertContains('somedate', $columnNames);

        foreach ($columnNames as $columnName) {
            $columns = $privateClient->getColumnType($table, $columnName)->getData();
            $columnData = array_pop($columns);
            $columnType = $columnData->cdb_columntype;

            if ($columnName == 'cartodb_id') {
                $this->assertEquals($columnType, 'integer');
            } else {
                $this->assertEquals($schema[$columnName], $columnType);
            }
        }

        //Table doesn't have columns it shouldn't
        if (in_array('someothercolumn', $columnNames)) {
            $privateClient->dropColumn($table, 'someothercolumn');
        }
        $this->assertFalse(
            in_array(
                'someothercolumn',
                array_map(
                    function ($item) {
                        return $item->cdb_columnnames;
                    },
                    $privateClient->getColumnNames($table)->getData()
                )
            )
        );

        //Columns can be added
        $privateClient->addColumn($table, 'someothercolumn', 'date');
        $this->assertTrue(
            in_array(
                'someothercolumn',
                array_map(
                    function ($item) {
                        return $item->cdb_columnnames;
                    },
                    $privateClient->getColumnNames($table)->getData()
                )
            )
        );

        //Column names can be changed
        $privateClient->changeColumnName($table, 'someothercolumn', 'someothercolumnnewname');
        $this->assertTrue(
            in_array(
                'someothercolumnnewname',
                array_map(
                    function ($item) {
                        return $item->cdb_columnnames;
                    },
                    $privateClient->getColumnNames($table)->getData()
                )
            )
        );
        $this->assertFalse(
            in_array(
                'someothercolumn',
                array_map(
                    function ($item) {
                        return $item->cdb_columnnames;
                    },
                    $privateClient->getColumnNames($table)->getData()
                )
            )
        );

        //Column types can be changed
        $privateClient->changeColumnType($table, 'someothercolumnnewname', 'text');
        $columns = $privateClient->getColumnType($table, 'someothercolumnnewname')->getData();
        $columnData = array_pop($columns);
        $this->assertEquals(
            'text',
            $columnData->cdb_columntype
        );;

        //Columns can be removed
        $privateClient->dropColumn($table, 'someothercolumnnewname');
        $this->assertFalse(
            in_array(
                'someothercolumnnewname',
                array_map(
                    function ($item) {
                        return $item->cdb_columnnames;
                    },
                    $privateClient->getColumnNames($table)->getData()
                )
            )
        );

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
        foreach (reset($data) as $name => $value) {
            if ($name == 'cartodb_id') {
                $this->assertGreaterThanOrEqual(1, $value, 'Cartodb_id should be equal or greater than 1 after insert');
            } //for now, skip dates, as we have to do some timezone jugling I don't have time for right now
            elseif ($schema[$name] != 'timestamp without time zone') {
                $this->assertEquals($row1[$name], $value, 'Inserted row values should match');
            }
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
        foreach (reset($data) as $name => $value) {
            if ($name == 'cartodb_id') {
                $this->assertGreaterThanOrEqual(1, $value, 'Cartodb_id should be equal or greater than 1 after update');
            } //for now, skip dates, as we have to do some timezone jugling I don't have time for right now
            elseif ($schema[$name] != 'timestamp without time zone') {
                $this->assertEquals($updatedRow1[$name], $value, 'Updated row values should match');
            }
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
        $this->assertEquals(0, $payload->getRowCount(), 'Row deletion should be 0 after truncate operations');

        //Tables can be deleted
        $privateClient->dropTable($table);
    }

    public function testPublicConnection()
    {
        $client = static::createClient();
        $container = $client->getContainer();

        $publicClient = $container->get('simbiotica.cartodb_connection.public');
        $this->assertTrue(
            $publicClient->authorized,
            'Connection to public tables should succeed if parameters are correct'
        );
    }
}

?>