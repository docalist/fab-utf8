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

use DOMDocument;
use SimpleXmlElement;
use DOMElement;
use XMLWriter;

/**
 * Représente un schéma.
 *
 *
 * @property string $format Format du schéma (dans quel format est le schéma).
 * @property string $label Un libellé court décrivant le schéma.
 * @property string $description Description, notes, historique des modifications.
 * @property string $stopwords Liste des mots-vides à ignorer lors d'une recherche.
 * @property string $creation Date de création du schéma.
 * @property string $lastupdate Date de dernière mise à jour du schéma.
 * @property string $version Version du format.
 * @property string $document Nom de la classe utilisée pour représenter les documents de la base.
 * @property string $docid Nom du champ utilisé comme identifiant unique des documents.
 * @property string $notes Notes et remarques internes.
 * @property string $defaultindex Nom de l'index par défaut.
 *
 * @property-read Fab\Schema\Fields $fields Liste des champs du schéma.
 * @property-read Fab\Schema\Indices $indices Liste des index du schéma.
 * @property-read Fab\Schema\Aliases $aliases Liste des alias du schéma.
 */
class Schema extends Node
{
    /**
     * Propriétés par défaut du schéma.
     *
     * @var array
     */
    protected static $defaults = array
    (
        'format' => '2',
    	'label' => '',
        'description' => '',
        'stopwords' => '',
        '_stopwords' => '',
        'creation' => '',
        'lastupdate' => '',
        'version' => '1',
        'document' => '\\Fab\\Document\\Document',
        'docid' => '',
        'notes' => '',
        'defaultindex' => '',
    );

    /**
     * Liste des collections de noeuds dont dispose une collection.
     *
     * @var array un tableau de la forme "nom de la propriété" => "classe utilisée".
     */
    protected static $nodes = array
    (
    	'fields' => 'Fab\\Schema\\Fields',
    	'indices' => 'Fab\\Schema\\Indices',
        'aliases' => 'Fab\\Schema\\Aliases',
    );

    /**
     * Liste des propriétés à ignorer lorsqu'un schéma est sérialisé en xml ou en json.
     *
     * @var array un tableau de la forme "nom de la propriété" => true|false.
     */
    protected static $ignore = array('_stopwords' => true);

    /**
     * Crée un schéma depuis un source xml.
     *
     * @param string|DOMDocument|SimpleXmlElement $xml la méthode peut prendre en entrée :
     * - une chaine de caractères contenant le code source xml du schéma
     * - un objet DOMDocument
     * - un objet SimpleXmlElement
     *
     * @return Schema
     *
     * @throws \Exception si le code source contient des erreurs.
     */
    public static function fromXml($xml)
    {
        // Source XML
        if (is_string($xml))
        {
            // Crée un document XML
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;

            libxml_use_internal_errors(true);

            // Charge le document
            if (! $dom->loadXML($xml, defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0))
            {
                $message = "Schéma incorrect :\n";
                foreach (libxml_get_errors() as $error)
                {
                    $message .= "- ligne $error->line : $error->message<br />\n";
                }
                libxml_clear_errors();
                throw new \Exception($message);
            }
        }

        // Un objet DOMDocument existant
        elseif ($xml instanceof DOMDocument)
        {
            $dom = $xml;
        }

        // Un objet SimpleXmlElement existant
        elseif ($xml instanceof SimpleXmlElement)
        {
            $dom = dom_import_simplexml($xml)->ownerDocument;
        }
        else
        {
            throw new \Exception('Paramètre incorrect');
        }

        // Teste la version du schéma et convertit le DOM en tableau de données en conséquence
        $version = self::getXmlVersion($dom);
        switch($version)
        {
            case 1: // Version 1, le schéma doit être converti
                $converter = new SchemaConverter();
                return $converter->convert($dom->documentElement);
                break;

            case 2: // Version 2, ok
                $data = self::domToArray($dom->documentElement);
                break;

            default: // Version inconnue
                throw new \Exception("Le code xml de ce schéma n'est pas reconnu (version $version)");
        }

        // Crée le schéma
        return new self($data);
    }

    /**
     * Détermine la version d'un schéma XML.
     *
     * Les anciens formats ont la version 1, les formats actuels ont
     * (pour le moment) la version 2.
     *
     * La méthode retourne le contenu du noeud /version ou 1 si ce noeud
     * ne figure pas dans le schéma.
     *
     * @param DOMDocument $xml le schéma xml à examiner.
     *
     * @return int
     */
    protected static function getXmlVersion(DOMDocument $xml)
    {
        $nodes = $xml->documentElement->getElementsByTagName('format');

        if ($nodes->length === 0)
        {
            return 1;
        }

        return (int) $nodes->item(0)->nodeValue;
    }

    /**
     * Méthode récursive utilisée par {@link fromXml()} pour charger un schéma
     * au format XML.
     *
     * @param DOMElement $node
     * @throws \Exception
     */
    protected static function domToArray(DOMElement $node)
    {
        // Les attributs ne sont pas autorisés dans les noeuds
        if ($node->hasAttributes())
        {
            throw new \Exception(sprintf(
                'Erreur ligne %d, tag %s : attribut interdit',
                $node->getLineNo(), $node->tagName
            ));
        }

        // Détermine la valeur du noeud en parcourant les noeuds fils
        $value = null;
        $hasTags = $hasItems = false;
        foreach($node->childNodes as $child)
        {
            switch ($child->nodeType)
            {
                // Texte ou section cdata : value sera une chaine
                case XML_TEXT_NODE:
                case XML_CDATA_SECTION_NODE:
                    // Vérifie que la config ne mélange pas à la fois des noeuds et du texte
                    if (is_array($value))
                    {
                        throw new \Exception(sprintf(
                            'Erreur ligne %d : le noeud %s contient à la fois des tags et du texte',
                            $child->getLineNo(), $node->tagName
                        ));
                    }

                    // Stocke la valeur
                    $value = is_null($value) ? $child->data : ($value . $child->data);
                    break;

                // Un tag : value sera un tableau
                case XML_ELEMENT_NODE:
                    // Vérifie que la config ne mélange pas à la fois des noeuds et du texte
                    if (is_string($value))
                    {
                        throw new \Exception(sprintf(
                            'Erreur ligne %d : le noeud %s contient à la fois des tags et du texte',
                            $child->getLineNo(), $node->tagName
                        ));
                    }

                    if (is_null($value)) $value = array();

                    // Récupère le nom du noeud
                    $name = $child->tagName;

                    // Récupère le contenu du noeud
                    $item = self::domToArray($child);

                    // Cas particulier : la valeur de la clé est un tableau d'items
                    if ($child->tagName === 'item')
                    {
                        $value[] = $item;
                        $hasItems = true;
                    }
                    else
                    {
                        if (isset($value[$name]))
                        {
                            throw new \Exception(sprintf(
                                'Erreur ligne %d, clé répétée : %s',
                                $child->getLineNo(), $node->tagName
                            ));
                        }
                        $value[$name] = $item;
                        $hasTags = true;
                    }

                    // Vérifie qu'on ne mélange pas des tags et des items
                    if ($hasTags && $hasItems)
                    {
                        throw new \Exception(sprintf(
                            'Erreur ligne %d : le noeud %s contient à la fois des tags et des items',
                            $child->getLineNo(), $node->tagName
                        ));
                    }

                    break;

                // Les commentaires sont autorisés mais sont ignorés
                case XML_COMMENT_NODE:
                    break;

                // Les autres types de noeuds interdits (PI, etc.)
                default:
                    throw new \Exception(sprintf(
                        'Erreur ligne %d : type de noeud interdit.',
                        $child->getLineNo()
                    ));
            }
        }

        // Convertit les chaines en entiers ou en booléens
        if (is_string($value))
        {
            $h = trim($value);
            if (is_numeric($h))
            {
                $value = ctype_digit($h) ? (int)$h : (float)$h;
            }
            elseif ($h === 'true')
            {
                $value = true;
            }
            elseif($h ==='false')
            {
                $value = false;
            }
        }

        // Retourne le résultat
        return $value;
    }

    /**
     * Sérialise le schéma au format xml.
     *
     * @param true|false|int $indent
     * - false : aucune indentation, le xml généré est compact.
     * - true : le xml est généré de façon lisible, avec une indentation de 4 espaces.
     * - int : xml lisible, avec une indentation de int espaces.
     *
     * @return string
     */
    public function toXml($indent = false)
    {
        $xml = new XMLWriter();
        $xml->openMemory();

        if ($indent === true) $indent = 4; else $indent=(int) $indent;
        if ($indent > 0)
        {
            $xml->setIndent(true);
            $xml->setIndentString(str_repeat(' ', $indent));
        }
        $xml->startDocument('1.0', 'utf-8', 'yes');

        $xml->startElement('schema');
        $this->_toXml($xml);
        $xml->endElement();

        $xml->endDocument();

        return $xml->outputMemory(true);
    }

    /**
     * Crée un schéma à partir d'une chaine au format JSON.
     *
     * @param string $json
     * @return Schema
     */
    public static function fromJson($json)
    {
        $array = json_decode($json, true);

        if (is_null($array))
        {
            throw new \Exception('JSON invalide');
        }

        return new self($array);
    }

    /**
     * Sérialise le schéma au format Json.
     *
     * @param true|false|int $indent
     * - false : aucune indentation, le json généré est compact
     * - true : le json est généré de façon lisible, avec une indentation de 4 espaces.
     * - x (int) : json lisible, avec une indentation de x espaces.
     *
     * @return string
     */
    public function toJson($indent = false)
    {
        if (! $indent) return '{' . $this->_toJson() . '}';

        if ($indent === true) $indent = 4; else $indent=(int) $indent;
        $indentString = "\n" . str_repeat(' ', $indent);

        $h = "{";
        $h .= $this->_toJson($indent, $indentString, ': ');
        if ($indent) $h .= "\n";
        $h .= '}';

        return $h;
    }

    /**
     * Retourne le schéma en cours.
     *
     * Pour un Schéma, getSchema() n'a pas trop d'utilité, mais ça permet
     * d'interrompre la chaine getSchema() des noeuds qui font tous
     * return parent::getSchema().
     *
     * @return $this
     */
    public function getSchema()
    {
        return $this;
    }

    /**
     * Setter pour la propriété 'stopwords'.
     *
     * A chaque fois qu'on modifie la propriété 'stopwords', cela modifie également
     * la propriété cachée 'stopwords' qui est une version tableau (les mots-vides sont
     * tokenisés et sont indexés dans les clés du tableau) de la chaine 'stopwords'.
     *
     * @param string $stopwords
     */
    protected function setStopwords($stopwords)
    {
        $this->data['stopwords'] = $stopwords;

        if (is_null($stopwords))
        {
            $stopwords = array();
        }
        elseif (is_string($stopwords))
        {
            $stopwords = str_word_count($stopwords, 1, '0123456789@_');
            // @todo : utiliser l'analyseur lowerCase.
        }
        elseif (is_array($stopwords))
        {
            $stopwords = array_values($stopwords);
        }
        $stopwords = array_fill_keys($stopwords, true);

        $this->data['_stopwords'] = $stopwords;
    }

    protected function getSlot(Index $index)
    {
        $h = $index->_id;

        $slot = 0;
        for ($i= 0; $i < strlen($h); $i++)
        {
            $slot = $slot * 26 + ord($h[$i]) - ord('A') + 1;
        }
        return $slot;
    }

    public function validate(array & $errors = array())
    {
        $result = parent::validate($errors);
        if (empty($this->format)) $this->format = self::$defaults['format'];

        if (empty($this->defaultindex))
        {
            $errors[] = "Vous devez indiquer le nom de l'index par défaut (propriété defaultindex du schéma)";
            return false;
        }
        if (! $this->indices->has($this->defaultindex) && ! $this->aliases->has($this->defaultindex))
        {
            $errors[] = "L'index par défaut indiqué ($this->defaultindex) n'existe pas (propriété defaultindex du schéma)";
            return false;
        }

        // Attribue un ID aux champs, aux sous-champs et aux index
//         $this->setId($this->fields, 'a');
//         foreach($this->fields as $field)
//         {
//             if ($field instanceof Group) $this->setId($field->fields, 'a');
//         }
//         $this->setId($this->indices, 'A');
//         foreach($this->indices as $index)
//         {
//             $index->_slot = $this->getSlot($index);
//         }

        return $result;
    }

//     public function setLastUpdate()
//     {
//         return true;
//     }
}