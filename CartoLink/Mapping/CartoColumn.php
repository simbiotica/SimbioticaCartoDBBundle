<?php

namespace Simbiotica\CartoBundle\CartoLink\Mapping;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class CartoColumn extends Annotation
{
    public $column; //name of cartodb column
    public $strong = false; //if true, will override local value with cartodb's on load
    public $index = false; //if true, this cartodb column will store the local id
    public $get = '%s';
    public $set = '%s';
}

?>