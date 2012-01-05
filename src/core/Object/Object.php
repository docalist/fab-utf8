<?php
/**
 * @package     fab
 * @subpackage  core
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe anc�tre de toutes les classes de fab.
 *
 * @package     fab
 * @subpackage  core
 */
class Object
{
    /**
     * La configuration de cette classe.
     *
     * @var ObjectConfiguration
     */
    protected static $config=null;



// Code � ajouter dans l'autoload de fab pour qu'un objet charge automatiquement
// sa propre config :
/*

        // todo : ConfigName=basename(path) sans l'extension = nom du fichier config � charger


        if (is_subclass_of($class, 'Object') && class_exists('Config'))
        {
            if (file_exists($path=Runtime::$fabRoot.'config' . DIRECTORY_SEPARATOR . $class . '.config'))
            {
                echo 'charge ', $path, '<br />';
                Config::load($path, $class);
            }
            if (file_exists($path=Runtime::$root.'config' . DIRECTORY_SEPARATOR . $class . '.config'))
            {
                echo 'charge ', $path, '<br />';
                Config::load($path, $class);
            }

            if (!empty(Runtime::$env))   // charge la config sp�cifique � l'environnement
            {
                if (file_exists($path=Runtime::$fabRoot.'config'.DIRECTORY_SEPARATOR. $class . '.' . Runtime::$env . '.config'))
                {
                    echo 'charge ', $path, '<br />';
                    Config::load($path, $class);
                }
                if (file_exists($path=Runtime::$root.'config'.DIRECTORY_SEPARATOR. $class . '.' . Runtime::$env . '.config'))
                {
                    echo 'charge ', $path, '<br />';
                    Config::load($path, $class);
                }
            }
            //eval ('')
            //$class::$config=Config::get($class);
        }
*/
}



class ObjectConfiguration extends Parameters
{
}
?>