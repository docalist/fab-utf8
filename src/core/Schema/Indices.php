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
 * Liste des index définis dans une collection.
 *
 * C'est une collection d'objets {@link Index}.
 */
class Indices extends Nodes
{
    protected static $class = 'Fab\\Schema\\Index';
    protected static $initialID = 'A';
}