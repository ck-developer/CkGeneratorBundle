<?php

namespace Ck\Bundle\GeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Container;

/**
 * Generates a bundle.
 *
 * @author Claude Khedhiri <claude@khedhiri.com>
 */
class BundleGenerator extends Generator
{
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function generate($namespace, $bundle, $dir, $format, $structure, $web)
    {
        $dir .= '/'.strtr($namespace, '\\', '/');
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" exists but is a file.', realpath($dir)));
            }
            $files = scandir($dir);
            if ($files != array('.', '..')) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not empty.', realpath($dir)));
            }
            if (!is_writable($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not writable.', realpath($dir)));
            }
        }

        $basename = substr($bundle, 0, -6);
        $parameters = array(
            'namespace' => $namespace,
            'bundle'    => $bundle,
            'format'    => $format,
            'bundle_basename' => $basename,
            'extension_alias' => Container::underscore($basename),
        );

        $this->renderBundle($web, $parameters, $format, $dir, $structure, $bundle);

    }

    public function renderBundle($web, $parameters, $format, $dir, $structure, $bundle)
    {

        if ('xml' === $format || 'annotation' === $format) {
            $this->renderFile('bundle/services.xml.twig', $dir.'/Resources/config/services.xml', $parameters);
        } else {
            $this->renderFile('bundle/services.'.$format.'.twig', $dir.'/Resources/config/services.'.$format, $parameters);
        }

        if ('annotation' != $format) {
            $this->renderFile('bundle/routing.'.$format.'.twig', $dir.'/Resources/config/routing.'.$format, $parameters);
        }

        if ($structure) {
            $this->renderFile('bundle/messages.fr.xlf', $dir . '/Resources/translations/messages.fr.xlf', $parameters);

            $this->filesystem->mkdir($dir . '/Resources/doc');
            $this->filesystem->touch($dir . '/Resources/doc/index.rst');
            $this->filesystem->mkdir($dir . '/Resources/translations');
        }

        $this->renderFile('bundle/Bundle.php.twig', $dir.'/'.$bundle.'.php', $parameters);
        $this->renderFile('bundle/Extension.php.twig', $dir.'/DependencyInjection/'.$parameters['bundle_basename'].'Extension.php', $parameters);
        $this->renderFile('bundle/Configuration.php.twig', $dir.'/DependencyInjection/Configuration.php', $parameters);

        if ($web) {

            // render backend controller
            $parameters['namespace'] = $parameters['namespace'] . '\\Controller\\Backend';
            $this->renderFile('bundle/DefaultController.php.twig', $dir.'/Controller/Backend/BackendController.php', $parameters);
            $this->renderFile('bundle/DefaultControllerTest.php.twig', $dir.'/Tests/Controller/Backend/BackendControllerTest.php', $parameters);

            // render frontend controller
            $parameters['namespace'] = $parameters['namespace'] . '\\Controller\\Frontend';
            $this->renderFile('bundle/DefaultController.php.twig', $dir.'/Controller/Frontend/FrontendController.php', $parameters);
            $this->renderFile('bundle/DefaultControllerTest.php.twig', $dir.'/Tests/Controller/Frontend/FrontendControllerTest.php', $parameters);

            if ($structure) {
                $this->filesystem->mkdir($dir.'/Resources/public/backend/css');
                $this->filesystem->mkdir($dir.'/Resources/public/backend/images');
                $this->filesystem->mkdir($dir.'/Resources/public/backend/js');

                $this->filesystem->mkdir($dir.'/Resources/public/frontend/css');
                $this->filesystem->mkdir($dir.'/Resources/public/frontend/images');
                $this->filesystem->mkdir($dir.'/Resources/public/frontend/js');

                $this->filesystem->mkdir($dir.'/Resources/public/global/css');
                $this->filesystem->mkdir($dir.'/Resources/public/global/images');
                $this->filesystem->mkdir($dir.'/Resources/public/global/js');
            }

        } else {
            // render controller
            $parameters['namespace'] = $parameters['namespace'] . '\\Controller';
            $this->renderFile('bundle/DefaultController.php.twig', $dir.'/Controller/DefaultController.php', $parameters);
            $this->renderFile('bundle/DefaultControllerTest.php.twig', $dir.'/Tests/Controller/DefaultControllerTest.php', $parameters);

            if ($structure) {
                $this->filesystem->mkdir($dir.'/Resources/public/css');
                $this->filesystem->mkdir($dir.'/Resources/public/images');
                $this->filesystem->mkdir($dir.'/Resources/public/js');
            }
        }
    }
}
