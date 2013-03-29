<?php 

namespace Simbiotica\CartoDBBundle\CartoDBLink\Mapping;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */

final class CartoDBColumn extends Annotation
{
    public $column;
    public $strong = false;
    public $index = false;
}

?>