<?php
/**
 * Doctrine ORM Provider. Works with Silex's Doctrine DBAL Provider.
 *
 * Adapted from the work of Marc Jakubowski.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nutwerk\Provider;

use Doctrine\Common\Annotations\AnnotationRegistry;
use \Doctrine\DBAL\Configuration as DBALConfiguration,
    \Doctrine\DBAL\DriverManager;

use \Doctrine\ORM\Configuration as ORMConfiguration,
    \Doctrine\ORM\Mapping\Driver\AnnotationDriver,
    \Doctrine\ORM\Mapping\Driver\YamlDriver,
    \Doctrine\ORM\Mapping\Driver\XmlDriver,
    \Doctrine\ORM\EntityManager;

use \Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain,
    \Doctrine\Common\Annotations\AnnotationReader,
    \Doctrine\Common\Cache\ArrayCache,
    \Doctrine\Common\Cache\ApcCache,
    \Doctrine\Common\EventManager;

use \Silex\Application;
use \Silex\ServiceProviderInterface;

/**
 * Doctrine ORM Provider
 *
 * @author Marc Jakubowski
 * @author Baptiste "Talus" Clavié <clavie.b@gmail.com> (maintainer) 
 */
class DoctrineORMServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {        
        if (!$app['db'] instanceof \Doctrine\DBAL\Connection) {
            throw new \InvalidArgumentException('$app[\'db\'] must be an instance of \Doctrine\DBAL\Connection'); 
        }
        
        $this->loadDoctrineConfiguration($app);
        $this->setOrmDefaults($app);
        $this->loadDoctrineOrm($app);
    }

    public function boot(Application $app) { }

    private function loadDoctrineOrm(Application $app)
    {
        $app['db.orm.em'] = $app->share(function() use($app) {
            return EntityManager::create($app['db'], $app['db.orm.config']);
        });
    }

    private function setOrmDefaults(Application $app)
    {
        $defaults = array(
            'entities' => array(
                array(
                    'type' => 'annotation', 
                    'path' => 'Entity', 
                    'namespace' => 'Entity',
                )
            ),

            'proxies_dir'           => 'cache/doctrine/Proxy',
            'proxies_namespace'     => 'DoctrineProxy',
            'auto_generate_proxies' => true,
            'cache'                 => new ArrayCache,
        );

        foreach ($defaults as $key => $value) {
            if (!isset($app['db.orm.' . $key])) {
                $app['db.orm.'.$key] = $value;
            }
        }
    }

    public function loadDoctrineConfiguration(Application $app)
    {
        $app['db.orm.config'] = $app->share(function() use($app) {

            $cache = $app['db.orm.cache'];
            
            $config = new ORMConfiguration;
            $config->setMetadataCacheImpl($cache);
            $config->setQueryCacheImpl($cache);

            $chain = new MappingDriverChain;
            
            foreach((array) $app['db.orm.entities'] as $entity) {
                switch($entity['type']) {
                    case 'default':
                    case 'annotation':
                        $reader = new AnnotationReader();
                        $driver = new AnnotationDriver($reader, $entity['path']);
                        $chain->addDriver($driver, $entity['namespace']);
                        AnnotationRegistry::registerLoader('class_exists');
                        break;
                    case 'yml':
                        $driver = new YamlDriver((array)$entity['path']);
                        $driver->setFileExtension('.yml');
                        $chain->addDriver($driver, $entity['namespace']);
                        break;
                    case 'xml':
                        $driver = new XmlDriver((array)$entity['path'], $entity['namespace']);
                        $driver->setFileExtension('.xml');
                        $chain->addDriver($driver, $entity['namespace']);
                        break;
                    default:
                        throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $entity['type']));
                        break;
                }
            }

            $config->setMetadataDriverImpl($chain);

            $config->setProxyDir($app['db.orm.proxies_dir']);
            $config->setProxyNamespace($app['db.orm.proxies_namespace']);
            $config->setAutoGenerateProxyClasses($app['db.orm.auto_generate_proxies']);

            return $config;
        });
    }
}
