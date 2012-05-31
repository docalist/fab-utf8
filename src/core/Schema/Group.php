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
 * Un groupe de champ (champ structuré).
 *
 * @property int $_id Identifiant (numéro unique) du groupe.
 * @property string $name Nom du groupe.
 * @property bool $repeatable Indique si le groupe est répétable.
 * @property string $label Libellé du groupe.
 * @property string $description Description du groupe.
 * @property string $notes Notes et remarques internes.
 *
 * @property-read Fab\Schema\Fields $fields Liste des champs qui composent ce groupe.
 */
class Group extends Field
{
    /**
     * Propriétés par défaut d'une groupe.
     *
     * @var array
     */
    protected static $defaults = array
    (
        'name' => '',
        'repeatable' => false,
        'label' => '',
        'description' => '',
        'notes' => '',
        '_id' => null,
    );

    /**
     * Un groupe contient une collection de champs.
     *
     * @var array un tableau de la forme "nom de la propriété" => "classe utilisée".
     */
    protected static $nodes = array
    (
    	'fields' => 'Fab\\Schema\\Fields',
    );
}