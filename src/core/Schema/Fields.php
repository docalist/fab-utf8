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

/**
 * Liste des champs définis dans une collection.
 *
 * C'est une collection d'objets {@link Field}.
 */
use Fab\Schema\Field;
use Fab\Schema\Group;

class Fields extends Nodes
{
    protected static $class = 'Fab\\Schema\\Field';
    protected static $initialID = 'a';

    public function add($child)
    {
        // Crée soit un Field soit un Group selon que la clé fields existe
        if (is_array($child))
        {
            $child = isset($child['fields']) ? new Group($child) : new Field($child);
        }
        return parent::add($child);
    }
}