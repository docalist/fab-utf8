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
 * Une liste de noms de champs.
 *
 * Cette classe est utilisée par {@link Index::$fields} pour stocker
 * la liste des champs qui composent l'index.
 *
 * @package     Fab
 * @subpackage  Schema
 */
class FieldNames extends NodeNames
{
    protected static $refNodes = 'fields';
}