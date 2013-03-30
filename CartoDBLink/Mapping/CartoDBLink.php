<?php 

namespace Simbiotica\CartoDBBundle\CartoDBLink\Mapping;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */

final class CartoDBLink extends Annotation
{
    public $connection; //name of the connection to use
    public $table; //name of the table it links to
    public $cascade = array('persist'); //can be persist, remove or all. Defines when sync will occur
}

?>