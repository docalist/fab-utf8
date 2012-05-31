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
 * Un champ au sein d'une collection.
 *
 * @property int $_id Identifiant (numéro unique) du champ.
 * @property string $name Nom du champ.
 * @property string $type Type du champ (sous forme de chaine).
 * @property int $_type Type du champ (sous forme de constante).
 * @property bool $repeatable Indique si le champ est répétable.
 * @property string $label Libellé du champ.
 * @property string $description Description du champ.
 * @property string $widget Widget utilisé pour représenter ce champ dans un formulaire de saisie.
 * @property string $datasource Table utilisée pour les widgets comme radiolist, checklist ou select.
 * @property string $notes Notes et remarques internes.
 */
class Field extends Node
{
    const TEXT = 1;
    const INT = 2;
    const AUTONUMBER = 3;
    const BOOL = 4;
    const DATE = 5;

    /**
     * Propriétés par défaut d'un champ.
     *
     * @var array
     */
    protected static $defaults = array
    (
        'name' => '',
        'type' => 'text',
        'repeatable' => false,
        '_type' => Field::TEXT,
        'label' => '',
        'description' => '',
    	'widget' => 'textbox',
    	'datasource' => '',
        'notes' => '',
		'_id' => null,
    );

    protected static $ignore = array('_type' => true);

    protected function setType($value)
    {
        $value = strtolower($value);
        $map = array
        (
            'text' => self::TEXT,
            'int' => self::INT,
            'autonumber' => self::AUTONUMBER,
            'bool' => self::BOOL,
            'date' => self::DATE,
        );

        if (! isset($map[$value]))
        {
            throw new \Exception('Type de champ incorrect : $value');
        }

        $this->data['type'] = $value;
        $this->data['_type'] = $map[$value];
    }
}