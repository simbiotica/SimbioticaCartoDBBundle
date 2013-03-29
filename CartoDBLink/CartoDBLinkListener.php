<?php 

namespace Simbiotica\CartoDBBundle\CartoDBLink;

use Doctrine\Common\EventArgs;
use Gedmo\Mapping\MappedEventSubscriber;

class CartoDBLinkListener extends MappedEventSubscriber
{
    public function getSubscribedEvents()
    {
        return array(
            'postPersist',
            'postUpdate',
            'postLoad',
            'loadClassMetadata'
        );
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


    protected function getNamespace()
    {
        // mapper must know the namespace of extension
        return __NAMESPACE__;
    }

}

?>