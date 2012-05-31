<?php
/**
 * This file is part of the Fooltext package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fooltext
 * @subpackage  Store
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fooltext\Store;

use Fab\Indexing\AnalyzerData;

use Fooltext\Document\Document;
use Fab\Schema\Schema;
use Fab\Schema\FieldNames;
use Fab\Schema\Field;
use Fab\Schema\Group;
use Fab\Schema\Index;
use Fab\Schema\Exception\NotFound;

use Fooltext\Store\SearchRequest;
use \XapianQueryParser;
use \XapianSimpleStopper;

/**
 * Une base de données Xapian.
 */
class XapianStore implements StoreInterface
{
    /**
     * Longueur maximale d'un terme dans une base xapian.
     *
     * @var int
     */
    const MAX_TERM = 236;

    /**
     * La base de données xapian en cours.
     *
     * @var \XapianDatabase
     */
    protected $db;

    /**
     * Le schéma de la base en cours.
     *
     * @var Fab\Schema\Schema
     */
    protected $schema;

    /**
     * @var XapianQueryParser
     */
    protected $xapianQueryParser;

    /**
     *
     * @var XapianSimpleStopper
     */
    protected $xapianStopper;


    /**
     * Cache des analyseurs déjà créés pour l'indexation.
     *
     * @var array className => Analyzer
     */
    static protected $analyzerCache = array();

    /**
     * Ouvre ou crée une base de données.
     *
     * @param array $options les options suivantes sont reconnues :
     * - path : string, le path complet de la base de données. Obligatoire.
     * - readonly : boolean, indique si la base doit être ouverte en mode
     *   "lecture seule" ou en mode "lecture/écriture".
     *   Optionnel, valeur par défaut : true.
     * - create : boolean, indique que la base de données doit être créée si
     *   elle n'existe pas déjà. Dans ce cas, la base est obligatoirement
     *   ouverte en mode "lecture/écriture". Optionnel. Valeur par défaut : false.
     * - overwrite : indique que la base de données doit être écrasée si elle
     *   existe déjà. Optionnel, valeur par défaut : false.
     * - schéma : le schema à utiliser pour créer la base. Obligatoire si
     *   create ou overwrite sont à true. Optionnel sinon.
     *
     * Exemples :
     * - Ouverture en lecture seule d'une base existante :
     * array('path'=>'...')
     *
     * - Ouverture en lecture/écriture d'une base existante :
     * array('path'=>'...', 'readonly'=>false)
     *
     * - Créer une nouvelle base de données (exception si elle existe déjà)
     * array('path'=>'...', 'create'=>true, 'schema'=>$schema)
     *
     * - Créer une base et écraser la base existante :
     * array('path'=>'...', 'overwrite'=>true, 'schema'=>$schema)
     */
    public function __construct(array $options = array())
    {
        // Options d'ouverture par défaut
        $defaultOptions = array
        (
            'path' => null,
            'readonly' => true,
            'create' => false,
            'overwrite' => false,
            'schema' => null,
        );

        // Détermine les options de la base
        $options = (object) ($options + $defaultOptions);

        // Le path de la base doit toujours être indiqué
        if (empty($options->path)) throw new \BadMethodCallException('option path manquante');

        // Création d'une nouvelle base
        if ($options->create || $options->overwrite)
        {
            if (empty($options->schema)) throw new \BadMethodCallException('option schema manquante');
            if (! $options->schema instanceof Schema) throw new \BadMethodCallException('schéma invalide');

            $mode = $options->overwrite ? \Xapian::DB_CREATE_OR_OVERWRITE : \Xapian::DB_CREATE;
            $this->db = new \XapianWritableDatabase($options->path, $mode);
            $this->setSchema($options->schema);
        }

        // Ouverture d'une base existante en readonly
        elseif ($options->readonly)
        {
            $this->db = new \XapianDatabase($options->path);
            $this->loadSchema();
        }

        // Ouverture d'une base existante en read/write
        else
        {
            $this->db = new \XapianWritableDatabase($options->path, \Xapian::DB_OPEN);
            $this->loadSchema();
        }
    }

    public function isReadonly()
    {
        return ! ($this->db instanceof \XapianWritableDatabase);
    }

    public function setSchema(Schema $schema)
    {
        $errors =array();
        if (! $schema->validate())
        {
            throw new \InvalidArgumentException("Schéma incorrect : " . implode("\n", $errors));
        }

        $this->schema = $schema;
        $this->db->set_metadata('schema_object', serialize($schema));
        return $this;
    }

    protected function loadSchema()
    {
        $this->schema = unserialize($this->db->get_metadata('schema_object'));
        return $this;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    protected function handleException(\Exception $e)
    {
        $message = $e->getMessage();
        if (false !== $pt = strpos($message, ':'))
        {
            $code = rtrim(substr($message, 0, $pt));
            $message = ltrim(substr($message, $pt+1));
            switch ($code)
            {
                case 'DocNotFoundError':
                    throw new Exception\DocumentNotFound($message, $e->getCode());
            }
        }

        throw $e;
    }

    // Convertit l'id au sein de la collection en id global pour la base
    protected function userIdToXapianId($id)
    {
        $term = $this->id . $id;

        $postlist = $this->db->postlist_begin($term);
        if ($postlist->equals($this->db->postlist_end($term)))
        {
            throw new Exception\DocumentNotFound("Le document $id n'existe pas dans cette collection");
        }

        return $postlist->get_docid();
    }

    /*

    ID global attribué par xapian (int)
    ID attribué par fooltext (lettre + int)
    ID popre à une collection (int)

    */
    public function get($id)
    {
        // version actuelle : $id doit être l'ID global attribué par xapian
        //$id = $this->userIdToXapianId($id);

        // Charge le document Xapian
        try
        {
            $data = json_decode($this->db->get_document($id)->get_data(), true);
        }
        catch (\Exception $e)
        {
            $this->handleException($e);
        }

        // Crée un objet Document du type indiqué dans la collection
        $class = $this->schema->document;
        return new $class($this->schema, $data);
    }

    /**
     * Retourne une instance de l'analyseur dont le nom de classe
     * est passé en paramètre.
     *
     * @param string $class
     * @return Fab\Indexing\AnalyzerInterface
     */
    protected function getAnalyzer($class)
    {
        if (! isset(self::$analyzerCache[$class]))
        {
            self::$analyzerCache[$class] = new $class();
        }
        return self::$analyzerCache[$class];
    }

    protected static $data = null;

    public function put($document)
    {
        $dump = false;

        if ($dump) echo "<pre>";

        // Si on nous a passé un tableau, crée un objet Document pour valider les données
        if (! $document instanceof Document)
        {
            $class = $this->schema->get('document');
            $document = new $class($this->schema, $document);
        }

        // Convertit le document en tableau tel qu'il sera sérialisé dans la base
        $document = $document->getData(false); // todo à changer + dans schéma : _docid contient l'id du doc, index._fields contient les id des champs, index._field aussi
        if ($dump) echo "Document : <pre>", var_export($document, true), "</pre>";

        // Crée le document xapian qu'on va stocker dans la base
        $doc = new \XapianDocument();

//         $termGenerator = new \XapianTermGenerator();
//         $termGenerator->set_document($doc);
//        $termGenerator->set_database($this->db);

        // Enregistre les données
        $doc->set_data(json_encode($document));

        $docId = (int) $document['Ref']; // @todo allouer un id au doc + utiliser la propriété docid du schéma
        $doc->add_boolean_term($docId);

        foreach($this->schema->get('indices') as $index)
        {
            // Récupère la liste des analyseurs
            // remarque : Schema::validate impose qu'il y ait au moins 1 analyseur/index
            $data = $index->getData();
            $classes = $data['analyzer'];
            $id = $data['_id'];
            $prefix = $id . ':';
            $weight = $data['weight'];
            $field = $data['_field'];
            $fields = $data['fields']->getData();

            $data = null;

            // Cas 1. L'index porte sur les zones d'un groupe de champs
            if ($field)
            {
                $start = strlen($field) + 1;
                $data = array();
                if (isset($document[$field]))
                {
                    $t = $document[$field];
                    if (key($t)!==0) $t = array($t);
                    foreach($t as $item)
                    {
                        $value = null;
                        foreach($fields as $i=>$zone)
                        {
                            $zone = substr($zone, $start);
                            if (isset($item[$zone]))
                            {
                                if (is_null($value))
                                {
                                    $value = $item[$zone];
                                }
                                elseif (is_scalar($value))
                                {
                                    $value .= '|' . $item[$zone];
                                }
                                else
                                {
                                    if (! is_array($item[$zone]))
                                    {
                                        echo "PROBLEME SUR L'INDEX ", $index->name, "<br />";
                                        echo "Actuellement, value contient", print_r($value,true), "<br />";
                                        echo "Zone=$zone et contenu = ", var_export($item[$zone],true), "<br />";
                                    }
                                    $value = array_merge($value, $item[$zone]);
                                }
                            }
                        }

                        if (! is_null($value))
                        {
                            if (is_array($value))
                            {
                                $data = array_merge($data, $value);
                            }
                            else
                            {
                                $data[] = $value;
                            }
                        }
                    }
                }
            }

            // Cas2. L'index porte sur des champs simples
            else
            {
//                 if (count($fields) === 1)
//                 {
//                     $data = isset($document[$fields[0]]) ? $document[$field[0]] : null;
//                 }
//                 else
                {
                    $data = array();
                    foreach($fields as $field)
                    {
                        if (isset($document[$field])) $data = array_merge($data, (array)$document[$field]);
                    }
                }
            }

            // todo : value = startEnd(value). Créer un "StartEndAnalyzer" ?
            $data = new AnalyzerData($index, $data);

            foreach((array)$classes as $class)
            {
//                 self::getAnalyzer($class)->analyze($data);
                if (! isset(self::$analyzerCache[$class]))
                {
                    self::$analyzerCache[$class] = new $class();
                }

                self::$analyzerCache[$class]->analyze($data);
            }

            if ($dump) $data->dump("Index $index->name (" . implode(', ', $classes) . ")");

            foreach($data->terms as $term)
            {
                foreach((array)$term as $term)
                {
                    if (strlen($term) > self::MAX_TERM) continue;
                    $doc->add_term($prefix . $term, $weight);
                }
            }

            $start = 0;
            foreach($data->postings as $position => $term)
            {
                foreach((array)$term as $position => $term)
                {
                    if (strlen($term) > self::MAX_TERM) continue;
                    $doc->add_posting($prefix . $term, $start + $position, $weight);
                }

                $start += 100;
                $start -= $start % 100;
            }

            foreach($data->keywords as $term)
            {
                foreach((array)$term as $term)
                {
                    if (strlen($term) > self::MAX_TERM) continue;
                    $doc->add_boolean_term($prefix . $term);
                }
            }

            foreach($data->spellings as $term)
            {
                foreach((array)$term as $term)
                {
                    if (strlen($term) > self::MAX_TERM) continue;
                    $this->db->add_spelling($term, 1);
                }
            }

            foreach($data->lookups as $term)
            {
                $p = 'T' . $prefix;
                foreach((array)$term as $term)
                {
                    if (strlen($term) > self::MAX_TERM) continue;
                    $doc->add_boolean_term($p. $term);
                }
            }

            foreach($data->sortkeys as $term)
            {
                foreach((array)$term as $term)
                {
                    $doc->add_value($id, $term);
                }
            }

        }

        if ($dump) echo "Appelle replace_document($docId)\n";
        $docId = $this->db->replace_document($docId, $doc);
        if ($dump) echo "ID ATTRIBUE PAR XAPIAN : ", var_export($docId, true), "\n";
        if ($dump) echo "</pre>";
        return $this;
    }

    public function delete($id)
    {
        $id = $this->id . $id;
        try
        {
            $this->db->delete_document($id);
        }
        catch (\Exception $e)
        {
            $this->handleException($e);
        }
        return $this;
    }

    public function find(SearchRequest $request)
    {
        return new XapianSearchResult($this, $request);
    }

    /**
     *
     * @return XapianDatabase
     */
    public function getXapianDatabase()
    {
        return $this->db;
    }

    /**
     * @return XapianQueryParser
     * @throws Exception
     */
    public function getQueryParser()
    {
        if (isset($this->xapianQueryParser)) return $this->xapianQueryParser;

        // Récupère le schéma de la base
        $schema = $this->schema;

        // Crée le QueryParser
        $parser = new XapianQueryParser();

        // Indique au queryParser la base de donnée sutilisée (pour FLAG_WILDCARD)
        $parser->set_database($this->db);

        // Définit l'index par défaut
        $default = $schema->defaultindex;
        if (! is_null($index = $schema->indices->get($default)))
        {
            $parser->add_prefix('', $index->_id . ':');
        }
        else // schema::validate garantit que defaultindex existe
        {
            $alias = $schema->aliases->get($default);
            foreach($alias->indices as $index)
            {
                $parser->add_prefix('', $schema->indices->get($index)->_id . ':');
            }
        }

        // Indique au QueryParser la liste des index de base disponibles
        foreach($schema->indices as $name => $index)
        {
            $parser->add_prefix($name, $index->_id . ':');
        }

        // Indique au QueryParser la liste des alias disponibles
        foreach($schema->aliases as $name => $alias)
        {
            foreach($alias->indices as $index)
            {
                $parser->add_prefix($name, $schema->indices->get($index)->_id . ':');
            }
        }

        // Initialise le stopper (suppression des mots-vides)
        $stopper = new XapianSimpleStopper();
        foreach ($schema->_stopwords as $stopword=>$i)
        {
            $stopper->add($stopword);
        }
        $parser->set_stopper($stopper);

        $this->xapianQueryParser = $parser;
        $this->xapianStopper = $stopper; // fixme : il faut garder une référence sur le stopper sinon segfault

        return $parser;
        /*
         // Expérimental : autorise un value range sur le champ REF s'il existe une clé de tri nommée REF
        foreach($this->schema->sortkeys as $name=>$sortkey)
        {
        if (!isset($sortkey->type)) $sortkey->type='string'; // FIXME: juste en attendant que les bases asco soient recréées
        if ($sortkey->type==='string')
        {
        // todo: xapian ne supporte pas de préfixe pour les stringValueRangeProcessor
        // $this->vrp=new XapianStringValueRangeProcessor($this->schema->sortkeys['ref']->_id);
        }
        else
        {
        $this->vrp=new XapianNumberValueRangeProcessor($sortkey->_id, $name.':', true);
        $this->xapianQueryParser->add_valuerangeprocessor($this->vrp);
        }
        // todo: date
        }
        */
    }
}