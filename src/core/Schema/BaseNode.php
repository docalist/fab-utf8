<?php
/**
 * This file is part of the Fab package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fab
 * @subpackage  Schema
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fab\Schema;

use IteratorAggregate;
use ArrayIterator;
use XMLWriter;

/**
 * Classe abstraite représentant un noeud du schéma.
 *
 * @package     Fab
 * @subpackage  Schema
 */
abstract class BaseNode implements IteratorAggregate
{
    /**
     * Les données du noeud.
     *
     * @var array
     */
    protected $data = array();


    /**
     * Le noeud parent de ce noeud.
     *
     * Cette propriété est initialisée automatiquement lorsqu'un noeud
     * est ajouté dans une {@link Nodes collection de noeuds}.
     *
     * @var BaseNode
     */
    protected $parent = null;


    /**
     * Retourne le noeud parent de ce noeud ou null si le noeud
     * n'a pas encore été ajouté comme fils d'un noeud existant.
     *
     * @return BaseNode
     */
    protected function getParent()
    {
        return $this->parent;
    }

    /**
     * Modifie le parent de ce noeud.
     *
     * @param BaseNode $parent
     * @return BaseNode $this
     */
    protected function setParent(BaseNode $parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Retourne le schéma dont fait partie ce noeud ou null si
     * le noeud n'a pas encore été ajouté à un schéma.
     *
     * @return Schema
     */
    public function getSchema()
    {
        return is_null($this->parent) ? null : $this->parent->getSchema();
    }

    /**
     * Retourne les données du noeud.
     *
     * Pour un objet {@link Node}, la méthode retourne les propriétés du noeud.
     * Pour un objet {@link Nodes} elle retourne la liste des noeuds fils.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Implémente l'interface {@link IteratorAggregate}.
     *
     * Permet d'itérer sur les propriétés d'un noeud avec une boucle foreach.
     *
     * Pour un objet {@link Node}, la boucle itère sur les propriétés du noeud.
     * Pour un objet {@link Nodes} la boucle permet de parcourir tous les noeuds fils.
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }

    private $cache = array();

    /**
     * Retourne l'élément ayant le nom indiqué ou null si l'élément
     * demandé n'existe pas.
     *
     * Si la classe contient un getter pour cette propriété (i.e. une méthode nommée
     * get + nom de la propriété), celui-ci est appellé.
     *
     * @param string $name le nom de l'élément recherché.
     * @return mixed
     */
    public function get($name)
    {
        static $cache = array();

        if( isset($this->cache[$name])) return $this->cache[$name];
        $sav = $name;
        $name = strtolower($name);

        // Remarque : il n'y a pas besoin d'appeller ucfirst() pour construire le nom
        // exact de la méthode à appeller car "php methods are case insensitive"
        // (documenté explictement dans la page php.net/functions.user-defined)

        $getter = 'get' . $name;
        if (method_exists($this, $getter))
        {
            return $this->cache[$sav] = $this->$getter($name);
        }

        if (array_key_exists($name, $this->data))
        {
            return $this->cache[$sav] = $this->data[$name];
        }

        return $this->cache[$sav] = null;
    }

    /**
     * Indique si l'objet contient l'élément dont le nom est indiqué.
     *
     * @param string $name le nom de l'élément recherché.
     * @return boolean
     */
    public function has($name)
    {
        return isset($this->data[strtolower($name)]);
    }

    /**
     * Supprime l'élément ayant le nom indiqué.
     *
     * @param string $name le nom de l'élément à supprimer.
     *
     * @return BaseNode $this
     */
    public function delete($name)
    {
        unset($this->data[strtolower($name)]);
        return $this;
    }

    /**
     * Retourne l'élément ayant le nom indiqué ou null si l'élément
     * demandé n'existe pas.
     *
     * Cette méthode fait la même chose que {@link get()} mais permet
     * d'employer la syntaxe $object->element.
     *
     * @param string $name le nom de l'élément recherché.
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Indique si une propriété existe.
     *
     * Cette méthode fait la même chose que {@link has()} mais permet
     * d'employer la syntaxe isset($object->element).
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return $this->has($name);
    }

    /**
     * Supprime l'élément ayant le nom indiqué.
     *
     * Cette méthode fait la même chose que {@link delete()} mais permet
     * d'employer la syntaxe unset($object->element).
     *
     * @param string $name le nom de l'élément à supprimer.
     */
    public function __unset($name)
    {
        $this->delete($name);
    }

    /**
     * Méthode utilisée par {@link Schema::toXml()} pour sérialiser un schéma en XML.
     *
     * Ajoute les propriétés du noeud dans l'objet {@link XMLWriter} passé en paramètre.
     *
     * @param XMLWriter $xml
     */
    protected abstract function _toXml(XMLWriter $xml);

    /**
     * Méthode utilisée par {@link Schema::toJson()} pour sérialiser un schéma en JSON.
     *
     * Sérialise le noeud au format JSON.
     *
     * La méthode ne générère que les propriétés du noeud. La méthode appelante doit
     * générer les accolades ouvrantes et fermantes.
     *
     * @param int $indent indentation à générer.
     * @param string $currentIndent indentation en cours.
     * @param string $colon chaine à utiliser pour générer le signe ":".
     */
    protected abstract function _toJson($indent = 0, $currentIndent = '', $colon = ':');

    public function validate(array & $errors = array())
    {
        return true;
    }

    public function dump()
    {
        $data = $this->getData();
        foreach($data as $name=>& $item)
        {
            if (substr($name, 0, 1) === '_')
                unset($data[$name]);
            elseif ($item instanceof BaseNode)
                $item = self::dumpNode($item);
            elseif (is_string($item) && strlen($item)>50)
                $item=substr($item, 0, 50) . '...';
        }
        return $data;
    }
}