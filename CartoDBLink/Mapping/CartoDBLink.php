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
    
    public function __toString()
    {
        return sprintf("Connection: %s; Table: %s", $this->connection, $this->table);
    }
}

?>