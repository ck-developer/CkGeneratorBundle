<?php

namespace Ck\Bundle\GeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a form class based on a Doctrine entity.
 *
 * @author Claude Khedhiri <claude@khedhiri.com>
 */
class DoctrineFormGenerator extends Generator
{
    private $filesystem;
    private $className;
    private $classPath;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem A Filesystem instance
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getClassPath()
    {
        return $this->classPath;
    }

    /**
     * Generates the entity form class if it does not exist.
     *
     * @param BundleInterface   $bundle   The bundle in which to create the class
     * @param string            $entity   The entity relative class name
     * @param ClassMetadataInfo $metadata The entity metadata class
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $actions)
    {
        $parts       = explode('\\', $entity);
        $entityClass = array_pop($parts);
        $dirPath         = $bundle->getPath().'/Form';

        if ($actions) {
            foreach ($actions as $action) {
                $this->classPath = $dirPath.'/'.str_replace('\\', '/', $entity).'/Form'. ucfirst($action).'.php';
                $this->className = 'Form' . ucfirst($action);
                if (file_exists($this->classPath)) {
                    throw new \RuntimeException(sprintf('Unable to generate the %s form class as it already exists under the %s file', $this->className, $this->classPath));
                }

                if (count($metadata->identifier) > 1) {
                    throw new \RuntimeException('The form generator does not support entity classes with multiple primary keys.');
                }

                $parts = explode('\\', $entity);
                array_pop($parts);

                $this->renderFile('form/FormType.php.twig', $this->classPath, array(
                    'fields'           => $this->getFieldsFromMetadata($metadata),
                    'bundle_namespace' => $bundle->getNamespace(),
                    'namespace'        => $bundle->getNamespace() . '\\Form\\' . $entity,
                    'entity_namespace' => implode('\\', $parts),
                    'entity_class'     => $entityClass,
                    'bundle'           => $bundle->getName(),
                    'form_class'       => $this->className,
                    'submit_label'     => $action,
                    'form_type_name'   => strtolower(str_replace('Bundle', '_bundle', $bundle->getName()) . '_' . $entity . '_form_' . $action),
                ));
            }
        } else {
            $this->classPath = $dirPath.'/'.str_replace('\\', '/', $entity).'Type.php';

            if (file_exists($this->classPath)) {
                throw new \RuntimeException(sprintf('Unable to generate the %s form class as it already exists under the %s file', $this->className, $this->classPath));
            }

            if (count($metadata->identifier) > 1) {
                throw new \RuntimeException('The form generator does not support entity classes with multiple primary keys.');
            }

            $parts = explode('\\', $entity);
            array_pop($parts);

            $this->renderFile('form/FormType.php.twig', $this->classPath, array(
                'fields'           => $this->getFieldsFromMetadata($metadata),
                'namespace'        => $bundle->getNamespace(),
                'entity_namespace' => implode('\\', $parts),
                'entity_class'     => $entityClass,
                'bundle'           => $bundle->getName(),
                'form_class'       => $this->className,
                'form_type_name'   => strtolower(str_replace('\\', '_', $bundle->getNamespace()).($parts ? '_' : '').implode('_', $parts).'_'.substr($this->className, 0, -4)),
            ));
        }
    }

    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param  ClassMetadataInfo $metadata
     * @return array             $fields
     */
    private function getFieldsFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = (array) $metadata->fieldNames;

        $excludeFields = array('token', 'createdAt', 'updatedAt', 'created_at','updated_at', 'slug');

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
            $fields = array_diff($fields, $excludeFields);
        }

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }
}
