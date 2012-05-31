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
 * Liste des alias définis dans une collection.
 *
 * C'est une collection d'objets {@link Alias}.
 */
class Aliases extends Nodes
{
    protected static $class = 'Fab\\Schema\\Alias';
}