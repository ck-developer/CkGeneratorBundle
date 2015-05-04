<?php

namespace Ck\Bundle\GeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a CRUD controller.
 *
 * @author Claude Khedhiri <claude@khedhiri.com>
 */
class DoctrineCrudGenerator extends Generator
{
    protected $filesystem;
    protected $routePrefix;
    protected $routeNamePrefix;
    protected $bundle;
    protected $entity;
    protected $metadata;
    protected $format;
    protected $actions;
    protected $office;
    protected $template;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem A Filesystem instance
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem  = $filesystem;
    }

    /**
     * Generate the CRUD controller.
     *
     * @param BundleInterface   $bundle           A bundle object
     * @param string            $entity           The entity relative class name
     * @param ClassMetadataInfo $metadata         The entity class metadata
     * @param string            $format           The configuration format (xml, yaml, annotation)
     * @param string            $routePrefix      The route name prefix
     * @param array             $needWriteActions Wether or not to generate write actions
     *
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $format, $routePrefix, $needWriteActions, $forceOverwrite, $office)
    {
        if (count($metadata->identifier) != 1) {
            throw new \RuntimeException('The CRUD generator does not support entity classes with multiple or no primary keys.');
        }

        $this->entity   = $entity;
        $this->bundle   = $bundle;
        $this->metadata = $metadata;
        $this->setFormat($format);

        $this->routePrefix = $routePrefix;
        $this->actions = $needWriteActions ? array('index', 'show', 'new', 'edit', 'delete') : array('index', 'show');
        $this->office = $office == 'default' ? '' : ucfirst($office);
        $this->template = $this->office ? $this->bundle->getName() .':'. $this->office .'/'. $this->entity : $this->bundle->getName() .':'. $this->entity ;

        if ($this->office) {
            $dir = sprintf('%s/Resources/views/%s/%s', $this->bundle->getPath(), $this->office, str_replace('\\', '/', $this->entity));
            $this->routeNamePrefix = strtolower(str_replace('Bundle', '', $this->bundle->getName()) .'_'. $this->office .'_'. str_replace('\\', '/', $this->entity));
        } else {
            $dir = sprintf('%s/Resources/views/%s', $this->bundle->getPath(), str_replace('\\', '/', str_replace('\\', '/', $this->entity)));
            $this->routeNamePrefix = strtolower($this->bundle->getName() .'_'. str_replace('\\', '/', $this->entity));
        }

        if (!file_exists($dir)) {
            $this->filesystem->mkdir($dir, 0777);
        }

        $this->generateControllerClass($forceOverwrite);

        $this->generateIndexView($dir);

        if (in_array('show', $this->actions)) {
            $this->generateShowView($dir);
        }

        if (in_array('new', $this->actions)) {
            $this->generateNewView($dir);
        }

        if (in_array('edit', $this->actions)) {
            $this->generateEditView($dir);
        }

        $this->generateTestClass();
        $this->generateConfiguration();
    }

    /**
     * Sets the configuration format.
     *
     * @param string $format The configuration format
     */
    private function setFormat($format)
    {
        switch ($format) {
            case 'yml':
            case 'xml':
            case 'php':
            case 'annotation':
                $this->format = $format;
                break;
            default:
                $this->format = 'yml';
                break;
        }
    }

    /**
     * Generates the routing configuration.
     *
     */
    protected function generateConfiguration()
    {
        if (!in_array($this->format, array('yml', 'xml', 'php'))) {
            return;
        }

        if ($this->office) {
            $target = sprintf(
                '%s/Resources/config/routing/%s/%s.%s',
                $this->bundle->getPath(),
                $this->office,
                strtolower(str_replace('\\', '_', $this->entity)),
                $this->format
            );
        } else {
            $target = sprintf(
                '%s/Resources/config/routing/%s.%s',
                $this->bundle->getPath(),
                strtolower(str_replace('\\', '_', $this->entity)),
                $this->format
            );
        }

        $this->renderFile('crud/config/routing.'.$this->format.'.twig', $target, array(
            'actions'           => $this->actions,
            'route_prefix'      => $this->routePrefix,
            'route_name_prefix' => $this->routeNamePrefix,
            'bundle'            => $this->bundle->getName(),
            'entity'            => $this->entity,
        ));
    }

    /**
     * Generates the controller class only.
     *
     */
    protected function generateControllerClass($forceOverwrite)
    {
        $dir = $this->bundle->getPath();

        $entity = $this->entity;
        $entityNamespace = $this->bundle->getNamespace() . '\\Entity\\' . $entity;
        $formNamespace = $this->bundle->getNamespace() . '\\Form\\' . $entity . '\\Form';

        $parameters = array(
            'actions'           => $this->actions,
            'route_prefix'      => $this->routePrefix,
            'route_name_prefix' => $this->routeNamePrefix,
            'bundle'            => $this->bundle->getName(),
            'namespace'         => $this->bundle->getNamespace(),
            'entity'            => $entity,
            'entity_class'      => $entity,
            'entity_namespace'  => $entityNamespace,
            'form_namespace'    => $formNamespace,
            'format'            => $this->format,
            'template'          => $this->template
        );

        if ($this->office) {
            $parameters['controller_namespace'] = $this->bundle->getNamespace() . '\\Controller\\' . $this->office;
            $target = sprintf(
                '%s/Controller/%s/%sController.php',
                $dir,
                $this->office,
                $entity
            );
        } else {
            $parameters['controller_namespace'] = $this->bundle->getNamespace() . '\\Controller';
            $target = sprintf(
                '%s/Controller/%sController.php',
                $dir,
                $entity
            );
        }


        if (!$forceOverwrite && file_exists($target)) {
            throw new \RuntimeException('Unable to generate the controller as it already exists.');
        }

        $this->renderFile('crud/controller.php.twig', $target, $parameters);
    }

    /**
     * Generates the functional test class only.
     *
     */
    protected function generateTestClass()
    {
        $parts = explode('\\', $this->entity);
        $entityClass = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $dir    = $this->bundle->getPath().'/Tests/Controller';
        $target = $dir.'/'.str_replace('\\', '/', $entityNamespace).'/'.$entityClass.'ControllerTest.php';

        $this->renderFile('crud/tests/test.php.twig', $target, array(
            'route_prefix'      => $this->routePrefix,
            'route_name_prefix' => $this->routeNamePrefix,
            'entity'            => $this->entity,
            'bundle'            => $this->bundle->getName(),
            'entity_class'      => $entityClass,
            'namespace'         => $this->bundle->getNamespace(),
            'entity_namespace'  => $entityNamespace,
            'actions'           => $this->actions,
            'form_type_name'    => strtolower(str_replace('\\', '_', $this->bundle->getNamespace()).($parts ? '_' : '').implode('_', $parts).'_'.$entityClass),
        ));
    }

    /**
     * Generates the index.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateIndexView($dir)
    {
        if ('api' !== strtolower($this->office)) {
            $fields = (array)$this->metadata->fieldMappings;

            $excludeFields = array('token', 'slug');

            $fields = array_diff_key($fields, array_flip($this->metadata->identifier));
            $fields = array_diff_key($fields, array_flip($excludeFields));

            $templatePath = $this->office ? 'crud/views/' . $this->office . '/index.html.twig.twig' : 'crud/views/index.html.twig.twig';

            $this->renderFile($templatePath, $dir . '/index.html.twig', array(
                'bundle' => $this->bundle->getName(),
                'entity' => $this->entity,
                'identifier' => $this->metadata->identifier[0],
                'fields' => $fields,
                'actions' => $this->actions,
                'record_actions' => $this->getRecordActions(),
                'route_prefix' => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
            ));
        }
    }

    /**
     * Generates the show.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateShowView($dir)
    {
        if ('api' !== strtolower($this->office)) {
            $templatePath = $this->office ? 'crud/views/'.$this->office.'/show.html.twig.twig' : 'crud/views/show.html.twig.twig';

            $this->renderFile($templatePath, $dir.'/show.html.twig', array(
                'bundle'            => $this->bundle->getName(),
                'entity'            => $this->entity,
                'identifier'        => $this->metadata->identifier[0],
                'fields'            => $this->metadata->fieldMappings,
                'actions'           => $this->actions,
                'route_prefix'      => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
            ));
        }
    }

    /**
     * Generates the new.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateNewView($dir)
    {
        if ('api' !== strtolower($this->office)) {
            $templatePath = $this->office ? 'crud/views/' . $this->office . '/new.html.twig.twig' : 'crud/views/new.html.twig.twig';

            $this->renderFile($templatePath, $dir . '/new.html.twig', array(
                'bundle' => $this->bundle->getName(),
                'entity' => $this->entity,
                'route_prefix' => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'actions' => $this->actions,
            ));
        }
    }

    /**
     * Generates the edit.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateEditView($dir)
    {
        if ('api' !== strtolower($this->office)) {
            $templatePath = $this->office ? 'crud/views/' . $this->office . '/edit.html.twig.twig' : 'crud/views/edit.html.twig.twig';

            $this->renderFile($templatePath, $dir . '/edit.html.twig', array(
                'route_prefix' => $this->routePrefix,
                'route_name_prefix' => $this->routeNamePrefix,
                'identifier' => $this->metadata->identifier[0],
                'entity' => $this->entity,
                'fields' => $this->metadata->fieldMappings,
                'bundle' => $this->bundle->getName(),
                'actions' => $this->actions,
            ));
        }
    }

    /**
     * Returns an array of record actions to generate (edit, show).
     *
     * @return array
     */
    protected function getRecordActions()
    {
        return array_filter($this->actions, function ($item) {
            return in_array($item, array('show', 'edit'));
        });
    }
}
