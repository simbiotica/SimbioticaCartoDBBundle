<?php

namespace Simbiotica\CartoBundle\CartoLink\Mapping\Driver;

use Gedmo\Exception\InvalidMappingException;
use Gedmo\Mapping\Driver\AbstractAnnotationDriver;

class Annotation extends AbstractAnnotationDriver
{
    const CARTOLINK = 'Simbiotica\\CartoBundle\\CartoLink\\Mapping\\CartoLink';
    const CARTOCOLUMN = 'Simbiotica\\CartoBundle\\CartoLink\\Mapping\\CartoColumn';

    public function readExtendedMetadata($meta, array &$config)
    {
        $class = $this->getMetaReflectionClass($meta);
        // class annotations
        if ($annotation = $this->reader->getClassAnnotation($class, self::CARTOLINK)) {
            if (!$annotation->connection || !$annotation->table) {
                throw new InvalidMappingException(
                    "CartoDBLink requires \"connection\" and \"table\" configurations, found ".$annotation
                );
            } else {
                $config['connection'] = $annotation->connection;
                $config['table'] = $annotation->table;
                $config['cascade'] = $annotation->cascade;
            }
            if ($annotation->cascade) {
                if (count(array_diff($annotation->cascade, array('fetch', 'persist', 'remove', 'all'))) > 0) {
                    throw new InvalidMappingException(
                        "CartoDBLink: cascade can have \"fetch\", \"persist\", \"remove\" or \"all\", found: ".implode(
                            ", ",
                            array_diff($annotation->cascade, array('persist', 'remove', 'all'))
                        )
                    );
                }
            }
        } else {
            //if no CartoDBLink is found, just quit. This will allow easy commenting of CartoDB Annotations by just commenting the CartoDBLink
            return;
        }

        $index = false;
        // property annotations
        foreach ($class->getProperties() as $property) {
            if ($meta->isMappedSuperclass && !$property->isPrivate() ||
                $meta->isInheritedField($property->name) ||
                isset($meta->associationMappings[$property->name]['inherited'])
            ) {
                continue;
            }
            // linked property
            if ($column = $this->reader->getPropertyAnnotation($property, self::CARTOCOLUMN)) {
                $field = $property->getName();
                if (!$column->column) {
                    throw new InvalidMappingException(
                        "CartoDBColumn requires \"column\" configuration, found ".$column
                    );
                }
                $config['columns'][$field] = $column;
                if ($column->index) {
                    $index = true;
                }
            }
        }

        if (!$meta->isMappedSuperclass && $config) {
            if (isset($config['columns']) && !isset($config['connection'])) {
                throw new InvalidMappingException(
                    "Class must be annotated with CartoDBLink annotation in order to link columns from class - {$meta->name}"
                );
            }
        }
        if ($annotation && !$index) {
            throw new InvalidMappingException("At least one CartoDBColumn must be used as index - {$meta->name}");
        }
    }
}

?>