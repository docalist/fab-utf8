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

use DOMElement;

/**
 * Classe statique contenant des méthodes permettant de convertir des
 * schémas XML d'ancienne génération vers le format actuel.
 */
class SchemaConverter
{
    /**
     * Convertit un schéma en version 1 vers la version actuelle.
     *
     * Important : la conversion n'est pas parfaite car la logique est trop
     * différente entre les anciens schémas et les nouveaux. Le convertisseur
     * permet de récupérer la majorité des informations, mais il faut ensuite
     * vérifier pour chaque champ la chaine d'analyse et les propriétés.
     *
     * @param DOMDocument $xml
     * @return Schema
     */
    public function convert(DOMElement $node)
    {
        $data = $this->domToArray($node);
        $schema = new Schema();
        foreach($data as $name=>$value)
        {
            switch ($name)
            {
                case 'fields':
                case 'indices':
                case 'aliases':
                case 'lookuptables':
                case 'sortkeys':
                    $this->$name($schema, $value);
                    break;
                case '_lastid':
                    unset($data[$name]);
                    break;
                default:
                    $schema->set($name, $value);
            }
        }
        return $schema;
    }

    public function domToArray(DOMElement $node)
    {
        $data = array();

        foreach ($node->attributes as $attribute)
        {
            $data[$attribute->nodeName] = $attribute->nodeValue;
        }

        foreach($node->childNodes as $child)
        {
            switch ($child->nodeName)
            {
                case 'fields':
                case 'indices':
                case 'lookuptables':
                case 'aliases':
                case 'indices':
                case 'sortkeys':
                    foreach($child->childNodes as $item)
                    {
                        $data[$child->nodeName][] = $this->domToArray($item);
                    }
                    break;
                case 'description':  // to remove
                    break;
                default:
                    $data[$child->nodeName] = $child->nodeValue;
            }
        }
        return $data;
    }

    protected function fields(Schema $schema, array $data)
    {
        foreach($data as $name=>$field)
        {
            unset($field['_type']);
            unset($field['defaultstopwords']);
            unset($field['description']); // to remove
            $schema->fields->add($field);
        }
    }

    protected function indices(Schema $schema, array $data)
    {
        $props = array
        (
            'words' => 'Fooltext\\Indexing\\Words',
            'phrases' => 'Fooltext\\Indexing\\Phrases',
            'values' => 'Fooltext\\Indexing\\Keywords',
            'count' => 'Fooltext\\Indexing\\Countable',
        );

        foreach($data as $oldindex)
        {
            $indexname = $oldindex['name'];
//            echo "Conversion de l'index $indexname<br />";
            $index = new Index();
            $index->name = $indexname;
            $t = array();
            $defaultstopwords = true;
            foreach($oldindex['fields'] as $field)
            {
                $name = $field['name'];
                $index->fields->add($name);
                foreach($props as $prop=>$class)
                {
                    $value = isset($field[$prop]);
                    if (isset($t[$prop]) && $t[$prop] !== $value)
                    {
                        $warning = "Les champs de l'index $indexname n'ont pas tous la même valeur pour $prop.";
                    }
                    if ($value) $t[$prop] = true;
                }

                $newfield = $schema->fields->get($name);
                if ($newfield->get('defaultstopwords') === 'false')
                {
                    $defaultstopwords = false;
                    unset($newfield->defaultstopwords);
                }

                foreach(array('weight', 'start', 'end') as $prop)
                {
                    if (isset($field[$prop]))
                    {
                        $index->set($prop, $field[$prop]);
                    }
                }
            }

            if (isset($t['phrases']) && isset($t['words'])) unset($t['words']);

            $analyzer = array();
            foreach($t as $prop=>$nu)
            {
                if (empty($analyzer)) $analyzer[] = 'Fooltext\\Indexing\\Lowercase';
                $analyzer[] = $props[$prop];
            }

            if (! $defaultstopwords) $analyzer[] = 'Fooltext\\Indexing\\RemoveStopwords';

            if (isset($oldindex['spelling'])) $analyzer[] = 'Fooltext\\Indexing\\Spellings';

            $index->analyzer = $analyzer;


            $schema->indices->add($index);
        }
    }

    protected function aliases(Schema $schema, array $data)
    {

    }

    protected function lookuptables(Schema $schema, array $data)
    {

    }

    protected function sortkeys(Schema $schema, array $data)
    {

    }
}