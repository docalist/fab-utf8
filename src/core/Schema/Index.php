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
 * Un index du schéma.
 *
 * @property int $_id Identifiant (numéro unique) de l'index.
 * @property string $name Nom de l'index.
 * @property string $label Libellé de l'index.
 * @property string $description Description de l'index.
 * @property array $analyzer Liste des analyseurs utilisés pour indexer les champs de cet index.
 * @property int $weight Poids attribués aux termes de recherche générés par cet index.
 * @property string $widget Widget utilisé pour représenter cet index dans un formulaire de recherche.
 * @property string $datasource Table utilisée pour les widgets comme radiolist, checklist ou select.
 * @property string $notes Notes et remarques internes.
 * @property string $_field Nom du champ principal si l'index porte sur des sous-zones, vide sinon.
 * @property-read Fooltext\Schema\FieldNames $fields Liste des champs de cet index.
 * @property int $_slot Numéro du slot utilisé pour stocker les attributs (clés de tri...) de cet index.
 */
class Index extends Node
{
    protected static $defaults = array
    (
        'name' => '',
        'label' => '',
        'description' => '',
        'analyzer' => array(),
        'weight' => 1,
        'widget' => 'textbox',
        'datasource' => '',
        'notes' => '',
        '_field' => '',
        '_id' => null,
        '_slot' => null,
    );

    protected static $nodes = array
    (
        'fields' => 'Fooltext\\Schema\\FieldNames',
    );

    protected static $ignore = array('_field' => true);

    /**
     * Setter pour la propriété 'analyzer' du champ.
     *
     * Vérifie que les analyseurs indiqués existent et stocke le résultat sous
     * forme de tableau. Le nom de classe d'un analyseur peut être indiqué avec ou
     * sans namespace. Si aucune namespace ne figure dans le nom de la classe, la
     * méthode ajoute le namespace Fooltext\Indexing\.
     *
     * @param string|array $value le nom de l'analyseur (ou un tableau d'analyseurs)
     * à utiliser pour ce champ.
     *
     * @throws \Exception Si l'analyseur indiqué n'existe pas ou s'il n'implémente
     * pas l'interface {@link Fooltext\Indexing\AnalyzerInterface}.
     */
    public function setAnalyzer($value)
    {
        if (is_scalar($value) || is_null($value))
        {
            $value = (array) $value;
        }

        foreach($value as & $analyzer)
        {
            if (false === strpos($analyzer, '\\'))
            {
                $analyzer = 'Fooltext\\Indexing\\' . $analyzer;
            }

            if (! class_exists($analyzer))
            {
                throw new \Exception("Classe $analyzer non trouvée");
            }

            $interfaces = class_implements($analyzer);
            if (! isset($interfaces['Fooltext\\Indexing\\AnalyzerInterface']))
            {
                throw new \Exception("La classe $analyzer n'est pas un analyseur");
            }
        }
        $this->data['analyzer'] = $value;
    }

    public function validate(array & $errors = array())
    {
        $result = parent::validate($errors);

        // Vérifie que l'index contient au moins un champ
        if (count($this->fields->getData()) === 0)
        {
            $errors[] = "L'index $this->name doit contenir au moins un champ";
            $result = false;
        }

        // Vérifie que l'index contient au moins un analyseur
        if (count($this->analyzer) === 0)
        {
            $errors[] = "Aucun analyseur indiqué pour l'index $this->name";
            $result = false;
        }

        // Vérifie que l'index ne contient que des champs OU que des zones issues du même champ.
        // si l'index porte sur des zones, initialise la propriété _field qui contient le nom
        // du champ dont sont issues les zones.
        $mainField = null;
        $hasZones = false;
        $hasFields = false;
        foreach($this->fields as $field)
        {
            $pt = strpos($field, '.');
            if ($pt === false)
            {
                $hasFields = true;
            }
            else
            {
                $hasZones = true;

                $h = substr($field, 0, $pt);
                if ($mainField && $h !== $mainField)
                {
                    $errors[] = "L'index $this->name contient des zones provenant de champs différents ($mainField, $h)";
                    return false;
                }
                $mainField = $h;
            }

            if ($hasFields && $hasZones)
            {
                $errors[] = "L'index $this->name contient à la fois des champs et des sous-champs";
                return false;
            }
        }
        $this->_field = $mainField;
        return $result;
    }
}