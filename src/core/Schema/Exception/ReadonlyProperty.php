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
namespace Fab\Schema\Exception;

class ReadonlyProperty extends SchemaException
{
    public function __construct($property = null, $code = 0, Exception $previous = null)
    {
        parent::__construct("Property $property is read-only", $code, $previous);
    }
}