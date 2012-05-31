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
namespace Fooltext\Schema\Exception;

/**
 * Exception générique utilisé à chaque fois qu'un objet demandé n'a pas
 * été trouvé (champ non trouvé, collection non trouvée, etc.)
 */
class NotFound extends SchemaException
{

}