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

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    
    public function getSubscribedEvents()
    {
        return array(
            'onFlush',
            'postLoad',
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
                
            }
        }
        // on insertion
        foreach ($ea->getScheduledObjectInsertions($uow) as $object) {
            $meta = $om->getClassMetadata(get_class($object));
            if ($config = $this->getConfiguration($om, $meta->name)) {
                var_dump($config);
                $connection = $this->container->get("simbiotica.cartodb_connection.".$config['connection']);
                
                $data = array();
                foreach ($ea->getObjectChangeSet($uow, $object) as $field => $changes) {
                    if (!$changes[1] || !in_array($field, array_keys($config['columns']))) {
                        continue;
                    }
                    $data[$config['columns'][$field]->column] = $changes[1];
                }
                
                
                $output = $connection->insertRow($config['table'], $data);
                
                var_dump($output);
                die;
            }
        }
        // on removal
        foreach ($ea->getScheduledObjectDeletions($uow) as $object) {
            $meta = $om->getClassMetadata(get_class($object));
            if ($config = $this->getConfiguration($om, $meta->name)) {
                var_dump($config);die;
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

    public function postLoad()
    {
        
    }

    protected function getNamespace()
    {
        // mapper must know the namespace of extension
        return __NAMESPACE__;
    }

}

?>