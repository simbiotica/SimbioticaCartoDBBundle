<?php

namespace Simbiotica\CartoDBBundle\CartoDBLink\Mapping\Driver;

use Gedmo\Exception\InvalidMappingException;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Gedmo\Mapping\Driver\AbstractAnnotationDriver;
use Doctrine\Common\Annotations\AnnotationReader;

class Annotation extends AbstractAnnotationDriver
{
    const CARTODBLINK =  'Simbiotica\\CartoDBBundle\\CartoDBLink\\Mapping\\CartoDBLink';
    const CARTODBCOLUMN =  'Simbiotica\\CartoDBBundle\\CartoDBLink\\Mapping\\CartoDBColumn';
    
    public function readExtendedMetadata($meta, array &$config)
    {
        $class = $this->getMetaReflectionClass($meta);
        // class annotations
        if ($annot = $this->reader->getClassAnnotation($class, self::CARTODBLINK)) {
            $config['link'] = true;
            if (!$annot->connection || !$annot->table) {
                throw new InvalidMappingException("CartoDBLink requires \"connection\" and \"table\" configurations, found ".$annot);
            }
            else 
            {
                $config['connection'] = $annot->connection;
                $config['table'] = $annot->table;
            }
        }
        
        // property annotations
        foreach ($class->getProperties() as $property) {
            if ($meta->isMappedSuperclass && !$property->isPrivate() ||
                    $meta->isInheritedField($property->name) ||
                    isset($meta->associationMappings[$property->name]['inherited'])
            ) {
                continue;
            }
            // linked property
            if ($column = $this->reader->getPropertyAnnotation($property, self::CARTODBCOLUMN)) {
                $field = $property->getName();
                if (!$column->column) {
                    throw new InvalidMappingException("CartoDBColumn requires \"column\" configuration, found ".$column);
                }
                // fields cannot be overrided and throws mapping exception
                $config['columns'][] = $column;
            }
        }
        
        if (!$meta->isMappedSuperclass && $config) {
            if (isset($config['columns']) && !isset($config['connection'])) {
                throw new InvalidMappingException("Class must be annoted with CartoDBLink annotation in order to link columns from class - {$meta->name}");
            }
        }
    }
}

?>