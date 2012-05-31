<?php
/**
 * This file is part of the Fab package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fab
 * @subpackage  Store
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fab\Store\Exception;

/**
 * Exception générée si on demande à charger un document qui ne figure pas
 * dans la base.
 *
 */
class DocumentNotFound extends \OutOfBoundsException
{

}