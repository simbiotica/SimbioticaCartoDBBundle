<?php 

namespace Simbiotica\CartoDBBundle\CartoDBLink\Mapping;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */

final class CartoDBLink extends Annotation
{
    public $connection;
    public $table;
    public $cascade = array('persist');
}

?>