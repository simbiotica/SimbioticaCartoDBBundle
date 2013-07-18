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
    
    /**
     * These are pending relations in case it does not
     * have an identifier yet
     *
     * @var array
     */
    protected $pendingRelatedObjects = array();

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
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
                
                foreach($config['columns'] as $field => $column)
                {
                    if ($column->index)
                        $cartodbid = $meta->getReflectionProperty($field)->getValue($object);
                }
                
                $connection = $this->container->get("simbiotica.cartodb_connection.".$config['connection']);
                
                $data = array();
                $transformers = array();
                foreach ($ea->getObjectChangeSet($uow, $object) as $field => $changes) {
                    $value = $changes[1];
                    if ( !in_array($field, array_keys($config['columns'])) || empty($config['columns'][$field]->set)) {
                        continue;
                    }
                    
                    if ($meta->isSingleValuedAssociation($field) && $value) {
                        $wrappedAssoc = AbstractWrapper::wrap($value, $om);
                        $metaAssoc = $wrappedAssoc->getMetadata();
                        if ($configAssoc = $this->getConfiguration($om, $metaAssoc->name)) {
                            
                            foreach($configAssoc['columns'] as $fieldAssoc => $columnAssoc)
                            {
                                if ($columnAssoc->index)
                                    $relatedId = $metaAssoc->getReflectionProperty($fieldAssoc)->getValue($value);
                            }
                            $data[$config['columns'][$field]->column] = $relatedId;
                        }
                        else
                        {
                            $data[$config['columns'][$field]->column] = $wrappedAssoc->getIdentifier(true);
                        }
                    }
                    else
                    {
                        $data[$config['columns'][$field]->column] = $value;
                        if($config['columns'][$field]->set != '%s') $transformers[$config['columns'][$field]->column] = $config['columns'][$field]->set;
                    }
                }
                
                if(count($data) == 0)
                {
                    //nothing to update
                    continue;
                }
                $payload = $connection->updateRow($config['table'], $cartodbid, $data, $transformers);
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
                $transformers = array();
                $thisPendingRelatedObjects = array();
                foreach ($ea->getObjectChangeSet($uow, $object) as $field => $changes) {
                    if (!in_array($field, array_keys($config['columns'])) || empty($config['columns'][$field]->set)) {
                        continue;
                    }
                    $value = $changes[1];
                    if ($meta->isSingleValuedAssociation($field) && $value) {
                        $oid = spl_object_hash($value);
                        $wrappedAssoc = AbstractWrapper::wrap($value, $om);
                        $metaAssoc = $wrappedAssoc->getMetadata();
                        if ($configAssoc = $this->getConfiguration($om, $metaAssoc->name)) {
                            //parent object has configuration, so we might need to keep it
                            $identifier = $wrappedAssoc->getIdentifier(false);
                            if (!is_array($identifier) && !$identifier) {
                                $thisPendingRelatedObjects[$oid][] = array(
                                        'connection' => $config['connection'],
                                        'table' => $config['table'],
                                        'field' => $config['columns'][$field]->column,
                                );
                            }
                            else
                            {
                                foreach($configAssoc['columns'] as $fieldAssoc => $columnAssoc)
                                {
                                    if ($columnAssoc->index)
                                        $relatedId = $metaAssoc->getReflectionProperty($fieldAssoc)->getValue($value);
                                }
                                $data[$config['columns'][$field]->column] = $relatedId;
                            }
                        }
                        else
                        {
                            $data[$config['columns'][$field]->column] = $wrappedAssoc->getIdentifier(true);
                        }
                    }
                    else
                    {
                        $data[$config['columns'][$field]->column] = $value;
                        if($config['columns'][$field]->set != '%s') $transformers[$config['columns'][$field]->column] = $config['columns'][$field]->set;
                    }
                }
                $index = null;
                foreach($config['columns'] as $field => $column)
                {
                    if ($column->index)
                        $index = $field;
                }
                
                $payload = $connection->insertRow($config['table'], $data, $transformers);
                $payloadData = $payload->getData();
                $row = reset($payloadData);
                $meta->getReflectionProperty($index)->setValue($object, $row->cartodb_id);
                
                $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $object);
                $objectId = $wrapped->getIdentifier();
                if (!$objectId) {
                    $this->pendingInserts[spl_object_hash($object)] = $row->cartodb_id;
                    foreach($thisPendingRelatedObjects as $oid => $pendingRelatedObjectList)
                    {
                        foreach($pendingRelatedObjectList as $key => $pendingRelatedObject)
                        {
                            $pendingRelatedObject['cartodbid'] = $row->cartodb_id;
                            $this->pendingRelatedObjects[$oid][] = $pendingRelatedObject;
                        }
                    }
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
        if ($this->pendingRelatedObjects && array_key_exists($oid, $this->pendingRelatedObjects)) {
            $wrapped = AbstractWrapper::wrap($object, $om);
            $meta = $wrapped->getMetadata();
            $config = $this->getConfiguration($om, $meta->name);
            $identifiers = $wrapped->getIdentifier(false);
            foreach ($this->pendingRelatedObjects[$oid] as $configAssoc) {
                $id = $wrapped->getIdentifier();
                
                //check if entity is mapped
                //if it is, get cartodb_id for that entity
                //if not, use local id
                if(array_key_exists('columns', $config))
                {
                    foreach($config['columns'] as $field => $column)
                    {
                        if ($column->index)
                            $cartodbid = $meta->getReflectionProperty($field)->getValue($object);
                    }
                }
                else
                {
                    $cartodbid = $id;
                }
                
                $connection = $this->container->get("simbiotica.cartodb_connection.".$configAssoc['connection']);
                $payload = $connection->updateRow($configAssoc['table'], $configAssoc['cartodbid'], array($configAssoc['field'] => $cartodbid));
            }
            unset($this->pendingRelatedObjects[$oid]);
        }
    }
    
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
                if (empty($column->get))
                    continue;
                elseif ($column->index)
                    $cartodbid = $meta->getReflectionProperty($field)->getValue($object);
                elseif($column->strong)
                    $data[$field] = sprintf($config['columns'][$field]->get, $column->column);
            }
    
            if(!$cartodbid)
                return;
    
            $payload = $connection->getRowsForColumns($config['table'], $data, array('cartodb_id' => $cartodbid));
            $payloadData = $payload->getData();
            $row = reset($payloadData);
    
            if ($row)
            {
                foreach($data as $field => $column)
                {
                    $meta->getReflectionProperty($field)->setValue($object, $row->$column);
                }
            }
        }
    }
    
    protected function getNamespace()
    {
        // mapper must know the namespace of extension
        return __NAMESPACE__;
    }

}

?>