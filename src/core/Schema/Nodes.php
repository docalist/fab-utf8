<?php
/**
 * This file is part of the Fooltext package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fooltext
 * @subpackage  Schema
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fooltext\Schema;

use Fooltext\Schema\Exception\NotFound;

/**
 * Classe abstraite représentant une collection de noeuds.
 *
 * Seul des objets Node peuvent être stockés dans cette collection.
 * Chaque objet est indexé à la fois par son nom et par son ID.
 *
 * La collection se charge d'attribuer un ID aux objets qui sont
 * ajoutés et stocke le dernier ID utilisé.
 *
 * @package     Fooltext
 * @subpackage  Schema
 */
abstract class Nodes extends BaseNode
{
    /**
     * Type des noeuds que contient cette collection.
     *
     * Tous les noeuds ajoutés à la collection doivent descendre de la
     * classe indiquée (cf {@link add()}).
     *
     * @var string
     */
    protected static $class = null;

    /**
     * ID qui sera atribué au prochain noeud ajouté dans la collection.
     *
     * Par défaut, les ID sont numériques et commencent à 1. Les classes descendantes
     * peuvent changer ça en surchargeant la valeur par défaut de la propriété.
     * Par exemple, on peut obtenir des ID composés de lettres
     * (a, b, .., z, aa, ab, .., az, etc.) en initialisant la propriété à 'a'.
     *
     * @var int|string
     */
     protected static $initialID = 1;

    /**
     * Liste des noms des noeuds présents dans la collection, indexés par ID.
     *
     * @var array Name => ID
     */
     protected $id = array();

    /**
     * Construit une nouvelle collection de noeuds.
     *
     * @param array $data un tableau d'objets {@link Node} (ou de tableaux
     * contenant les propriétés des objets Node) à ajouter à la collection.
     */
    public function __construct(array $data = array())
    {
        foreach ($data as $child)
        {
            $this->add($child);
        }
    }

    /**
     * Ajoute un noeud dans la collection.
     *
     * @param Node|array $child le noeud fils à ajouter, soit sous la forme d'un
     * objet {@link Node}, soit sous la forme d'un tableau de propriétés qui sera
     * utilisé pour créer un nouvel objet {@link Node}.
     *
     * Le noeud à ajouter doit obligatoirement avoir un nom (propriété name).
     * Un identifiant unique (propriété _id) sera attribué au noeud si celui-ci
     * n'en a pas encore.
     *
     * @return Nodes $this
     *
     * @throws Exception Si le noeud n'a pas le bon type (cf {@link Nodes::$class}),
     * n'a pas de nom ou si un noeud portant ce nom figure déjà dans la collection.
     */
    public function add($child)
    {
        // Si c'est un tableau, crée un nouveau Node
        if (is_array($child))
        {
            $child = new static::$class($child);
        }

        // Sinon, vérifie que c'est un objet Node du type indiqué
        elseif (! $child instanceof static::$class)
        {
            throw new \InvalidArgumentException("Type incorrect : $name");
        }

        // Vérifie que le noeud a un nom
        $name = strtolower($child->name);
        if (empty($name))
        {
            throw new \Exception('Le noeud à ajouter doit avoir un nom');
        }

        // Vérifie qu'il n'existe pas déjà un noeud avec ce nom
        if (isset($this->data[$name]))
        {
            throw new \Exception("Il existe déjà un noeud avec le nom $name");
        }

        // Attribue un ID au noeud si nécessaire
//         if (is_null($child->_id) || $child->_id === '')
//         {
//             $child->_id = $this->nextid++;
//         }

        // Ajoute le noeud
        $child->setParent($this);
        $this->data[$name] = $child;
//         $this->id[$child->_id] = $name;

        return $this;
    }

    /**
     * Retourne le noeud de la collection ayant le nom ou l'ID indiqué.
     *
     * @param string|int $name le nom ou l'ID du noeud recherché.
     * @return Node
     */
    public function get($name)
    {
        return isset($this->id[$name]) ? $this->data[$this->id[$name]] : parent::get($name);
    }

    public function has($name)
    {
        return isset($this->id[$name]) || parent::has($name);
    }

    public function delete($name)
    {
        if (isset($this->id[$name]))
        {
            unset($this->data[$this->id[$name]]);
            unset($this->id[$name]);
        }
        else
        {
            $name = strtolower($name);
            if (isset($this->data[$name]))
            {
                unset($this->id[$this->data[$name]->_id]);
                unset($this->data[$name]);
            }
        }
        return $this;
    }

    protected function _toXml(\XMLWriter $xml)
    {
        foreach($this->data as $child)
        {
            $xml->startElement('item');
            $child->_toXml($xml);
            $xml->endElement();
        }
    }

    protected function _toJson($indent = 0, $currentIndent = '', $colon = ':')
    {
        $h = '';
        foreach($this->data as $child)
        {
            $h .= $currentIndent . '{';
            $h .= $child->_toJson($indent, $currentIndent . str_repeat(' ', $indent), $colon);
            $h .= $currentIndent. '},';
        }

        return rtrim($h, ',');
    }

//     public function getNextId()
//     {
//         return $this->nextid;
//     }

    /**
     * Attribue un ID à tous les noeuds fils qui n'en n'ont pas encore.
     */
    protected function allocateNodesId()
    {
        // Détermine le plus grand des ID déjà utilisé au sein de la collection
        $max = null;
        foreach($this->data as $node)
        {
            if (! is_null($id = $node->_id)) $max = max($max, ++$id);
        }

        // Aucun noeud n'a d'ID, on commence avec l'ID de départ
        if (is_null($max)) $max = static::$initialID;

        // Attribue un ID à tous les noeuds qui n'en n'ont pas encore
        // Initialise la propriété $this->$id pour permettre un accès aux noeuds par ID.
        foreach($this->data as $name=>$node)
        {
            if (is_null($node->_id)) $node->_id = $max++;
            $this->id[$node->_id] = $name;
        }
    }

    public function validate(array & $errors = array())
    {
        $result = parent::validate($errors);
        $this->allocateNodesId();
        foreach($this->data as $child)
        {
            $result &= $child->validate($errors);
        }

        return $result;
    }
}