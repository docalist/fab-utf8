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
 * Un alias au sein d'une collection.
 *
 * @property string $name Nom de l'alias.
 * @property string $label Libellé de l'alias.
 * @property string $description Description de l'alias.
 * @property string $notes Notes et remarques internes.
 *
 * @property-read Fab\Schema\IndexNames $indices Liste des index qui composent cet alias.
 */
class Alias extends Node
{
    protected static $defaults = array
    (
        'name' => '',
        'label' => '',
        'description' => '',
        'notes' => '',
    );

    protected static $nodes = array
    (
    	'indices' => 'Fab\\Schema\\IndexNames',
    );
}