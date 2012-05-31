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

/**
 * Une liste de noms d'index.
 *
 * Cette classe est utilisée par {@link Alias::$indices} pour stocker
 * la liste des index qui composent l'alias.
 *
 * @package     Fooltext
 * @subpackage  Schema
 */
class IndexNames extends NodeNames
{
    protected static $refNodes = 'indices';
}