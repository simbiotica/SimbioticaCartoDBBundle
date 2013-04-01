<?php 

namespace Simbiotica\CartoDBBundle\CartoDBLink;

use Gedmo\Tool\Wrapper\AbstractWrapper;

use Doctrine\ORM\Event\PreFlushEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\Common\EventArgs;
use Gedmo\Mapping\MappedEventSubscriber;

class CartoDBLinkListener extends MappedEventSubscriber
{
    protected $container;
    
    /**
     * List of entries which do not have the id, and need to be updated on cartodb later
     *
     * @var array
     */
    protected $pendingInserts = array();

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    
    public function getSubscribedEvents()
    {
        return array(
            'onFlush',
            'postLoad',
            'postPersist',
            'loadClassMetadata'
        );
    }

    public function onFlush(EventArgs $eventArgs)
    {
        $ea = $this->getEventAdapter($eventArgs);
        $om = $ea->getObjectManager();
        $uow = $om->getUnitOfWork();
        
        // on update
        foreach ($ea->getScheduledObjectUpdates($uow) as $object) {
            $meta = $om->getClassMetadata(get_class($object));
            if ($config = $this->getConfiguration($om, $meta->name)) {
                //no persist nor all? then skip
                if (!in_array("persist", $config['cascade']) && !in_array("all", $config['cascade']))
                    continue;
                
                $connection = $this->container->get("simbiotica.cartodb_connection.".$config['connection']);
                
                $data = array();
                foreach ($ea->getObjectChangeSet($uow, $object) as $field => $changes) {
                    if (!$changes[1] || !in_array($field, array_keys($config['columns']))) {
                        continue;
                    }
                    $data[$config['columns'][$field]->column] = $changes[1];
                }
                
                if(count($data) == 0)
                {
                    //nothing to update
                    continue;
                }
                
                foreach($config['columns'] as $field => $column)
                {
                    if ($column->index)
                        $cartodbid = $meta->getReflectionProperty($field)->getValue($object);
                }
                
                $payload = $connection->updateRow($config['table'], $cartodbid, $data);
            }
        }
        // on insertion
        foreach ($ea->getScheduledObjectInsertions($uow) as $object) {
            $wrapped = AbstractWrapper::wrap($object, $om);
            $meta = $wrapped->getMetadata();
            if ($config = $this->getConfiguration($om, $meta->name)) {
                //no persist nor all? then skip
                if (!in_array("persist", $config['cascade']) && !in_array("all", $config['cascade']))
                    continue;
                
                $connection = $this->container->get("simbiotica.cartodb_connection.".$config['connection']);
                
                $data = array();
                foreach ($ea->getObjectChangeSet($uow, $object) as $field => $changes) {
                    if (!$changes[1] || !in_array($field, array_keys($config['columns']))) {
                        continue;
                    }
                    $data[$config['columns'][$field]->column] = $changes[1];
                }
                $index = null;
                foreach($config['columns'] as $field => $column)
                {
                    if ($column->index)
                        $index = $field;
                }
                
                $payload = $connection->insertRow($config['table'], $data);
                $payloadData = $payload->getData();
                $row = reset($payloadData);
                $meta->getReflectionProperty($index)->setValue($object, $row->cartodb_id);
                
                $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $object);
                $objectId = $wrapped->getIdentifier();
                if (!$objectId) {
                    $this->pendingInserts[spl_object_hash($object)] = $row->cartodb_id;
                }
            }
        }
        // on removal
        foreach ($ea->getScheduledObjectDeletions($uow) as $object) {
            $meta = $om->getClassMetadata(get_class($object));
            
            if ($config = $this->getConfiguration($om, $meta->name)) {
                //no persist nor all? then skip
                if (!in_array("remove", $config['cascade']) && !in_array("all", $config['cascade']))
                    continue;
                
                $connection = $this->container->get("simbiotica.cartodb_connection.".$config['connection']);
                
                foreach($config['columns'] as $field => $column)
                {
                    if ($column->index)
                        $cartodbid = $meta->getReflectionProperty($field)->getValue($object);
                }
                if ($cartodbid)
                    $payload = $connection->deleteRow($config['table'], $cartodbid);
            }
        }
    }
    
    public function loadClassMetadata(EventArgs $args)
    {
        $ea = $this->getEventAdapter($args);
        // this will check for our metadata
        $this->loadMetadataForObjectClass(
            $ea->getObjectManager(),
            $args->getClassMetadata()
        );
    }

/**
     * After object is loaded, listener updates the translations
     * by currently used locale
     *
     * @param EventArgs $args
     * @return void
     */
    public function postLoad(EventArgs $args)
    {
        $ea = $this->getEventAdapter($args);
        $om = $ea->getObjectManager();
        $object = $ea->getObject();
        $meta = $om->getClassMetadata(get_class($object));
        $config = $this->getConfiguration($om, $meta->name);
        if ($config = $this->getConfiguration($om, $meta->name)) {
            if (!in_array("fetch", $config['cascade']) && !in_array("all", $config['cascade']))
                return;
            
            $connection = $this->container->get("simbiotica.cartodb_connection.".$config['connection']);
            
            $data = array();
            foreach($config['columns'] as $field => $column)
            {
                if ($column->index)
                    $cartodbid = $meta->getReflectionProperty($field)->getValue($object);
                elseif($column->strong)
                    $data[$field] = $column->column;
            }
            
            if(!$cartodbid)
                return;
            
            $payload = $connection->getRowsForColumns($config['table'], $data, array('cartodb_id' => $cartodbid));
            $payloadData = $payload->getData();
            $row = reset($payloadData);
            
            foreach($data as $field => $column)
            {
                $meta->getReflectionProperty($field)->setValue($object, $row->$column);
            }
            
        }
    }

    /**
     * Checks for inserted object to update its cartodb entry
     * foreign key
     *
     * @param EventArgs $args
     * @return void
     */
    public function postPersist(EventArgs $args)
    {
        $ea = $this->getEventAdapter($args);
        $object = $ea->getObject();
        $om = $ea->getObjectManager();
        $oid = spl_object_hash($object);
        $uow = $om->getUnitOfWork();
        
        if ($this->pendingInserts && array_key_exists($oid, $this->pendingInserts)) {
            $wrapped = AbstractWrapper::wrap($object, $om);
            $meta = $wrapped->getMetadata();
            $config = $this->getConfiguration($om, $meta->name);
            $id = $wrapped->getIdentifier();
    
            $connection = $this->container->get("simbiotica.cartodb_connection.".$config['connection']);
            $index = null;
            foreach($config['columns'] as $field => $column)
            {
                if ($column->index)
                    $index = $column->column;
            }
            
            $payload = $connection->updateRow($config['table'], $this->pendingInserts[$oid], array($index => $id));
            unset($this->pendingInserts[$oid]);
        }
    }
    
    protected function getNamespace()
    {
        // mapper must know the namespace of extension
        return __NAMESPACE__;
    }

}

?>