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
 * Classe abstraite représentant une liste de noms de noeuds
 * (liste de noms de champ pour un index, liste d'index pour un alias).
 *
 * @package     Fooltext
 * @subpackage  Schema
 */
abstract class NodeNames extends BaseNode
{
    /**
     * nom de la propriété du schema (fields ou indices) qui contient les
     * noeuds qui sont référencés.
     *
     * @var string
     */
    protected static $refNodes = null;

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
     * Ajoute un noeud dans la liste.
     *
     * @param string $child Nom du noeud à ajouter.
     */
    public function add($child)
    {
        $name = strtolower($child);

        // Vérifie qu'il n'existe pas déjà un noeud avec ce nom
        if (isset($this->data[$name]))
        {
            throw new \Exception("Il existe déjà un noeud avec le nom $name");
        }

        // Ajoute le noeud
        $this->data[$name] = $child;

        return $this;
    }

    public function get($name)
    {
        $name = strtolower($name);

        // Si le champ demandé ne figure pas dans l'index, null
        if (! array_key_exists($name, $this->data))
        {
            return null;
        }

        // Le champ peut être de la forme autphys.name
        $level = explode('.', $name);

        // Si le champ indiqué dans l'index n'existe pas, null
        $node = $this->getSchema()->get(static::refNodes)->get($level[0]);
        if (is_null($node))
        {
            return null;
        }

        // Si le sous champ indiqué n'existe pas dans le groupe, null
        if (count($level) > 1) return $node->fields->get($level[1]);

        // Ok
        return $node;
    }

    protected function _toXml(\XMLWriter $xml)
    {
        foreach($this->data as $name)
        {
            $xml->writeElement('item', $name);
        }
    }

    protected function _toJson($indent = 0, $currentIndent = '', $colon = ':')
    {
        $h = '';
        foreach($this->data as $name)
        {
            $h .= $currentIndent . json_encode($name) . ',';
        }

        return rtrim($h, ',');
    }
}