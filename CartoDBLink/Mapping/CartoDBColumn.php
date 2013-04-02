<?php 

namespace Simbiotica\CartoDBBundle\CartoDBLink\Mapping;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */

final class CartoDBColumn extends Annotation
{
    public $column; //name of cartodb column
    public $strong = false; //if true, will override local value with cartodb's on load
    public $index = false; //if true, this cartodb column will store the local id
    public $get = '%s';
    public $set = '%s';
}

?>