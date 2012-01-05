<?php
/**
 * @package     fab
 * @subpackage  Schema
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id
 */
namespace fab\Schema;


/**
 * Classe statique utilisée pour définir les classes PHP utilisées pour représenter
 * les noeuds qui composent un schéma.
 *
 * Dans un schéma, les noeuds sont définis par un nom symbolique qui indique leur type
 * (field, index, etc.)
 *
 * Lorsqu'un schéma est chargé en mémoire, chaque noeud est représenté par un objet
 * PHP. La méthode {@link registerNodetype()} permet de définir la classe PHP à utiliser
 * pour un nom symbolique donné.
 *
 * Par défaut, des classes sont fournies pour tous les types de noeud. Vous pouvez
 * utiliser {@link registerNodetype()} pour remplacer une classe prédéfinie par votre
 * propre classe. Cela peut être utile pour introduire de nouvelles méthodes, pour
 * définir de nouvelles propriétés ou encore pour modifier les valeurs par défaut
 * d'un noeud.
 *
 * Pour cela, il suffit de définir une nouvelle classe descendante de
 * {@link fab\Schema\Node}, de surcharger sa propriété statique $defaultProperties et
 * d'appeller la méthode {@link registerNodetype()} en indiquant le nom symbolique
 * correspondant au type de noeud que vous voulez surcharger.
 *
 * @package     fab
 * @subpackage  Schema
 */
abstract class NodesTypes
{
    /**
     * Tableau de conversion entre les noms symboliques et les noms de classes.
     *
     * @var array
     */
    protected static $typemap = array
    (
    	'schema'           => 'fab\Schema',
        'fields'           => 'fab\Schema\Fields',
        'field'            => 'fab\Schema\Field',
        'groupfield'       => 'fab\Schema\GroupField',
    	'indices'          => 'fab\Schema\Indices',
        'index'            => 'fab\Schema\Index',
    	'aliases'          => 'fab\Schema\Aliases',
        'alias'	           => 'fab\Schema\Alias',
    	'aliasindex'       => 'fab\Schema\AliasIndex',
    	'lookuptables'     => 'fab\Schema\LookupTables',
        'lookuptable'      => 'fab\Schema\LookupTable',
        'sortkeys'	       => 'fab\Schema\Sortkeys',
    	'sortkey'	       => 'fab\Schema\Sortkey',
        'indexfield'       => 'fab\Schema\IndexField',
        'aliasfield'       => 'fab\Schema\AliasField',
        'lookuptablefield' => 'fab\Schema\LookupTableField',
        'sortkeyfield'     => 'fab\Schema\SortkeyField',
    );


    /**
     * Tableau inverse de {@link $typemap}, indique le nom symbolique associé
     * à chaque classe.
     *
     * Utilisé et construit automatiquement par {@link nodetypeToClass()}.
     *
     * @var array
     */
    private static $nodetype;


    /**
     * Retourne un tableau contenant toutes les associations actuellement définies.
     *
     * Le tableau retourné est sous la forme nom symbolique => nom de classe php.
     *
     * @return array
     */
    public static function all()
    {
        return self::$nodetype;
    }


    /**
     * Définit le nom de la classe PHP à utiliser pour représenter un type de noeud
     * donné au sein d'un schéma.
     *
     * @param string $nodetype le type de noeud à surcharger (nom symbolique).
     * @param string $class le nom de la classe PHP à utiliser pour ce type de noeud.
     * @throws \Exception si la classe indiquée n'est pas correcte (classe inexistante
     * ou qui n'hérite pas de fab\Schema\Node).
     */
    public static function registerNodetype($nodetype, $class)
    {
        if (! class_exists($class))
            throw new \Exception("Classe $class non trouvée");

        if (! is_subclass_of($class, 'fab\Schema\Node'))
            throw new \Exception("Classe incorrecte");

        self::$typemap[$nodetype] = $class;

        unset(self::$nodetype); // le tableau inverse doit être recréé
    }


    /**
     * Retourne la classe PHP à utiliser pour représenter un noeud d'un type donné.
     *
     * @param string $nodetype type de noeud (nom symbolique).
     * @throws \Exception si le nom symbolique indiqué n'est pas référencé.
     * @return string
     */
    public static function nodetypeToClass($nodetype)
    {
        if (! isset(self::$typemap[$nodetype]))
            throw new \Exception("Type de noeud inconnu : '$nodetype'");

        return self::$typemap[$nodetype];
    }


    /**
     * Retourne le nom symbolique associé à une classe PHP donnée.
     *
     * @param string $class
     * @throws \Exception si la classe indiquée n'est pas référencée.
     * @return string
     */
    public static function classToNodetype($class)
    {
        if (is_null(self::$nodetype)) self::$nodetype = array_flip(self::$typemap);

        if (! isset(self::$nodetype[$class]))
            throw new \Exception('Nom de classe inconnue');

        return self::$nodetype[$class];
    }
}


/**
 * Classe de base (abstraite) représentant un noeud dans un schéma.
 *
 * Un noeud est un objet qui peut contenir des propriétés (cf {@link get()},
 * {@link set()}, {@link has()}, {@link remove()} et {@link all()}) et des
 * noeuds enfants (cf. {@link addChild()}, {@link getChildren()},
 * {@link getChild()}, {@link hasChildren()}, {@link hasChild()},
 * {@link removeChildren()} et {@link removeChild()}).
 *
 * Un noeud dispose de propriétés par défaut (cf {@link getDefaultProperties()})
 * et de fils par défaut (cf {@link getDefaultChildren()}).
 *
 * @package     fab
 * @subpackage  Schema
 */
abstract class Node
{
    /**
     * Propriétés prédéfinies et valeur par défaut de ce type de noeud.
     *
     * Cette propriété est destinée à être surchargée par les classes descendantes.
     *
     * @var array
     */
    protected static $defaultProperties = array();


    /**
    * Fils par défaut du noeud.
    *
    * Cette propriété est destinée à être surchargée par les classes descendantes.
    *
    * @var array
    */
    protected static $defaultChildren = array();


    protected $parent = null;
    protected $properties = null;
    protected $children = null;

    /**
     * Crée un nouveau noeud.
     *
     * Un noeud contient automatiquement toutes les propriétés par défaut définies
     * pour ce type de noeud.
     *
     * @param array $parameters propriétés du noeud.
     */
    public function __construct(array $parameters = null)
    {
        $parameters = array_merge(static::$defaultProperties, $parameters);

        $children = isset($parameters['children']) ? $parameters['children'] : array();
        unset($parameters['children']);

        foreach ($parameters as $name => $value)
            $this->$name = $value;

        foreach($children as $child)
            $this->addChild(self::fromArray($child));

        foreach(static::$defaultChildren as $name)
            if (! $this->hasChild($name)) $this->addChild(self::create($name));
    }


    /**
     * Construit un noeud à partir d'un tableau contenant ses propriétés.
     *
     * @param array $node un tableau contenant les propriétés du noeud.
     * Le tableau doit contenir une propriété 'nodetype' qui indique le
     * type du noeud à créer.
     *
     * @return Node
     */
    public static function create($nodetype, array $parameters = array())
    {
        $class = NodesTypes::nodetypeToClass($nodetype);
        if (! isset($parameters['name'])) $parameters['name'] = $nodetype;
        return new $class($parameters);
    }

    /**
     * Construit un noeud à partir d'un tableau contenant ses propriétés.
     *
     * @param array $node un tableau contenant les propriétés du noeud.
     * Le tableau doit contenir une propriété 'nodetype' qui indique le
     * type du noeud à créer.
     *
     * @return Node
     */
    public static function fromArray(array $parameters)
    {
        return self::create($parameters['nodetype'], $parameters);
    }


    /**
     * Retourne les propriétés par défaut du noeud.
     *
     * @return array()
     */
    public static function getDefaultProperties()
    {
        return static::$defaultProperties;
    }


    /**
     * Retourne les fils par défaut du noeud.
     *
     * @return array()
     */
    public static function getDefaultChildren()
    {
        return static::$defaultChildren;
    }


    /**
     * Retourne un tableau contenant toutes les propriétés du noeud.
     *
     * @return array
     */
    public function all()
    {
        return get_object_vars($this);
    }


    /**
     * Retourne la propriété dont le nom est indiqué ou null si la propriété
     * demandée n'existe pas.
     *
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        return isset($this->$name) ? $this->name : null;
    }


    /**
     * Ajoute ou modifie une propriété.
     *
     * Si la valeur indiquée est <code>null</code>, la propriété est supprimée de
     * l'objet ou revient à sa valeur par défaut si c'est une propriété prédéfinie.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return $this
     */
    public function set($name, $value = null)
    {
        if (is_null($value))
            $this->remove($name);
        else
            $this->$name = $value;

        return $this;
    }


    /**
     * Indique si une propriété existe.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return isset($this->$name);
    }


    /**
     * Supprime la propriété indiquée ou la réinitialise à sa valeur par défaut
     * s'il s'agit d'une propriété prédéfinie.
     *
     * Sans effet si la propriété n'existe pas.
     *
     * @param string $name
     *
     * @return $this
     */
    public function remove($name)
    {
        if (isset(static::$defaultProperties[$name]))
            $this->name = static::$defaultProperties[$name];
        else
            unset($this->$name);

        return $this;
    }


    /**
     * Ajoute un noeud fils à la propriété children du noeud.

     * @param Node $child le noeud fils à ajouter
     * @throws Exception si le noeud n'a pas de nom ou si ce nom existe déjà.
     * @return $this
     */
    public function addChild(Node $child)
    {
        $name = $child->get('name');

        if (empty($name))
            throw new \Exception('propriété "name" non trouvée'.var_export($child,true));

        if ($this->has($name))
            throw new \Exception('un objet existe déjà avec ce nom');

        $child->_parent = $this;

        $this->children[$name] = $child;
        return $this;
    }


    /**
     * Retourne tous les noeuds fils.
     *
     * @return array()
     */
    public function getChildren()
    {
        return isset($this->children) ? $this->children : array();
    }


    /**
     * Retourne le noeud fils dont le nom est indiqué ou null si le noeud demandé n'existe pas.
     *
     * @param string $name
     * @return null|Node
     */
    public function getChild($name)
    {
        return isset($this->children[$name]) ? $this->children[$name] : null;
    }


    /**
     * Indique si le noeud contient des noeuds enfants.
     *
     * @return bool
     */
    public function hasChildren()
    {
        return isset($this->children);
    }


    /**
     * Indique si le noeud fils dont le nom est indiqué existe.
     *
     * @param string $name
     */
    public function hasChild($name)
    {
        return isset($this->children[$name]);
    }


    /**
     * Supprime tous les noeuds enfants.
     *
     * Sans effet si le noeud ne contient aucun fils.
     *
     * @return $this
     */
    public function removeChildren()
    {
        unset($this->children);
        return $this;
    }


    /**
     * Supprime le noeud fils dont le nom est indiqué.
     *
     * Sans effet si le noeud indiqué n'existe pas.
     *
     * @param string $name
     * @return $this
     */
    public function removeChild($name)
    {
        unset($this->children[$name]);
        return $this;
    }

    protected function _toXml($indent='')
    {
        $tag = NodesTypes::classToNodetype(get_class($this));

        $h = "$indent<$tag>\n";
        $indent .= '    ';
        foreach($this as $name=>$value)
        {
            $h .= "$indent<$name>";
            if (is_array($value))
            {
                $h .= "\n";
                foreach($value as $item)
                {
                    $h .= $item->_toXml($indent . '    ');
                }
                $h .= $indent;
            }
            else
            {
                $h .= $value;
            }
            $h .= "</$name>\n";
        }
        $indent = substr($indent, 0, -4);
        $h .= "$indent</$tag>\n";
        return $h;
    }

    protected static function _fromXml(\DOMNode $node, $path='', $nodetype=null)
    {
        /**
         * Tableau utilisé pour convertir les schémas xml de la version 1 à la version 2.
         *
         * Ce tableau contient toutes les "collections" qui existaient dans l'ancien format.
         * Pour chaque collection (on indique le path depuis la racine), on peut indiquer :
         * - true : cette collection existe toujours dans le nouveau format.
         *   Il faut donc la créer et ajouter dedans les noeuds fils.
         * - une chaine contenant un type de noeud symbolique : cela signifie que cette
         *   collection n'existe plus dans le nouveau format. Les noeuds fils doivent être
         *   ajoutés directement dans la clé children du noeud parent et doivent être créé
         *   en utilisant le type indiqué.
         */
        static $oldnodes = array
        (
        	'/schema/fields' => true,
        	'/schema/indices' => true,
        	'/schema/indices/index/fields' => 'indexfield',
        	'/schema/lookuptables' => true,
        	'/schema/lookuptables/lookuptable/fields' => 'lookuptablefield',
        	'/schema/aliases' => true,
        	'/schema/aliases/alias/indices' => 'aliasindex',
        	'/schema/sortkeys' => true,
        	'/schema/sortkeys/sortkey/fields' => 'sortkeyfield',
    	);

        // Stocke le type de noeud
        $result = self::create(is_null($nodetype) ? $node->tagName : $nodetype);
        $path .= "/$node->tagName";

        // Les attributs du tag sont des propriétés de l'objet
        if ($node->hasAttributes())
            foreach ($node->attributes as $attribute)
                $result->set($attribute->nodeName, self::_xmlToValue($attribute->nodeValue));

        // Les noeuds fils du tag sont soit des propriétés, soit des objets enfants
        foreach ($node->childNodes as $child)
        {
            $childpath = "$path/$child->tagName";

            switch ($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    // Le nom de l'élément va devenir le nom de la propriété
                    $name = $child->tagName;

                    // Collection (children ou, pour les anciens formats, fields, indices, etc.)
                    if ($name === 'children')
                    {
                        foreach($child->childNodes as $child)
                            $result->addChild(self::_fromXml($child, $path));
                    }

                    elseif (isset($oldnodes[$childpath]))
                    {
                        if ($oldnodes[$childpath] === true)
                        {
                            $collection = Node::create($name);
                            foreach($child->childNodes as $child)
                                $collection->addChild(self::_fromXml($child, $childpath));

                            $result->addChild($collection);
                        }
                        else
                        {
                            foreach($child->childNodes as $child)
                                $result->addChild(self::_fromXml($child, $childpath, $oldnodes[$childpath]));
                        }
                    }

                    // Propriété
                    else
                    {
                        // Vérifie qu'on n'a pas à la fois un attribut et un élément de même nom (<database label="xxx"><label>yyy...)
                        if ($node->attributes->getNamedItem($name))
                            throw new \Exception("Erreur dans le source xml : la propriété '$name' apparaît à la fois comme attribut et comme élément");

                        // Stocke la propriété
                        $result->set($name, self::_xmlToValue($child->nodeValue)); // si plusieurs fois le même tag, c'est le dernier qui gagne
                    }
                    break;

                    // Types de noeud autorisés mais ignorés
                case XML_COMMENT_NODE:
                    break;

                    // Types de noeud interdits
                default:
                    throw new \Exception("les noeuds de type '".$child->nodeName . "' ne sont pas autorisés");
            }
        }

        return $result;
    }


    /**
    * Fonction utilitaire utilisée par {@link xmlToObject()} pour convertir la
    * valeur d'un attribut ou le contenu d'un tag.
    *
    * Pour les booléens, la fonction reconnait les valeurs 'true' ou 'false'.
    * Pour les autres types scalaires, la fonction encode les caractères '<',
    * '>', '&' et '"' par l'entité xml correspondante.
    *
    * @param scalar $value
    * @return string
    */
    protected static function _xmlToValue($xml)
    {
        return $xml;
        if (is_bool($dtdValue))
        {
            if($xml==='true') return true;
            if($xml==='false') return false;
            throw new DatabaseSchemaXmlNodeException($node, 'booléen attendu');
        }

        if (is_int($dtdValue))
        {
            if (! ctype_digit($xml))
            throw new DatabaseSchemaXmlNodeException($node, 'entier attendu');
            return (int) $xml;
        }
        return $xml;
    }

    public function getType()
    {
        return NodesTypes::classToNodetype(get_class($this));
    }

}


/**
 * Liste des champs.
 */
class Fields extends Node
{
    protected static $defaultProperties = array
    (
        '_lastid' => 0,
    );
}

/**
 * Un champ simple.
 */
class Field extends Node
{
    protected static $defaultProperties = array
    (
		'_id'=>0,                   // Identifiant (numéro unique) du champ
        'name'=>'',                 // Nom du champ, d'autres noms peuvent être définis via des alias
        'type'=>'text',             // Type du champ (juste à titre d'information, non utilisé pour l'instant)
//         '_type'=>self::FIELD_TEXT,  // Traduction de la propriété type en entier
        'label'=>'',                // Libellé du champ
        'description'=>'',          // Description
        'defaultstopwords'=>true,   // Utiliser les mots-vides de la base
        'stopwords'=>'',            // Liste spécifique de mots-vides à appliquer à ce champ
    );
}

/**
 * Un champ comportant plusieurs sous zones.
 */
class GroupField extends Node{}

/**
 * Liste des index.
 */
class Indices extends Node
{
    protected static $defaultProperties = array
    (
        '_lastid' => 0,
    );
}

/**
 * Un index.
 */
class Index extends Node
{
    protected static $defaultProperties = array
    (
    	'_id'=>0,                   // Identifiant (numéro unique) de l'index
        'name'=>'',                 // Nom de l'index
        'label'=>'',                // Libellé de l'index
        'description'=>'',          // Description de l'index
        'type'=>'probabilistic',    // Type d'index : 'probabilistic' ou 'boolean'
        'spelling'=>false,          // Ajouter les termes de cet index dans le correcteur orthographique
//         '_type'=>self::INDEX_PROBABILISTIC, // Traduction de la propriété type en entier
    );
}

/**
 * Un champ indexé
 */
class IndexField extends Node
{
    protected static $defaultProperties = array
    (
        '_id'=>0,           // Identifiant du champ
        'name'=>'',         // Nom du champ
        'words'=>false,     // Indexer les mots
        'phrases'=>false,   // Indexer les phrases
        'values'=>false,    // Indexer les valeurs
        'count'=>false,     // Compter le nombre de valeurs (empty, has1, has2...)
        'global'=>false,    // DEPRECATED : n'est plus utilisé, conservé pour compatibilité
        'start'=>'',        // Position ou chaine indiquant le début du texte à indexer
        'end'=>'',          // Position ou chain indquant la fin du texte à indexer
        'weight'=>1         // Poids des tokens ajoutés à cet index
    );
}

/**
 * Liste des alias.
 */
class Aliases extends Node
{
    protected static $defaultProperties = array
    (
        '_lastid' => 0,
    );
}

/**
 * Un alias.
 */
class Alias extends Node
{
    protected static $defaultProperties = array
    (
    	'_id'=>0,                // Identifiant (numéro unique) de l'alias (non utilisé)
        'name'=>'',              // Nom de l'alias
        'label'=>'',             // Libellé de l'index
        'description'=>'',       // Description de l'index
        'type'=>'probabilistic', // Type d'index : 'probabilistic' ou 'boolean'
//         '_type'=>self::INDEX_PROBABILISTIC, // Traduction de la propriété type en entier
    );
}

/**
 * Un index dans un alias
 */
class AliasIndex extends Node
{
    protected static $defaultProperties = array
    (
        '_id'=>0,      // Identifiant (numéro unique) du champ
        'name'=>'',    // Nom de l'index
    );
}

/**
 * Liste des tables de lookup.
 */
class LookupTables extends Node
{
    protected static $defaultProperties = array
    (
        '_lastid' => 0,
    );
}

/**
 * Une table de lookup.
 */
class LookupTable extends Node
{
    protected static $defaultProperties = array
    (
    	'_id'=>0,                        // Identifiant (numéro unique) de la table
        'name'=>'',                      // Nom de la table
        'label'=>'',                     // Libellé de l'index
        'description'=>'',               // Description de l'index
        'type'=>'simple',                // type de table : "simple" ou "inversée"
//         '_type'=>self::LOOKUP_SIMPLE,    // Traduction de type en entier
    );
}

/**
 * Un champ dans un table de lookup.
 */
class LookupTableField extends Node
{
    protected static $defaultProperties = array
    (
    	'_id'=>0,       // Identifiant (numéro unique) du champ
        'name'=>'',     // Nom du champ
        'startvalue'=>1,// Indice du premier article à prendre en compte (1-based)
        'endvalue'=>0,  // Indice du dernier article à prendre en compte (0=jusqu'à la fin)
        'start'=>'',    // Position de début ou chaine délimitant le début de la valeur à ajouter à la table
        'end'=>''       // Longueur ou chaine délimitant la fin de la valeur à ajouter à la table
    );
}

/**
 * Liste des clés de tri.
 */
class Sortkeys extends Node
{
    protected static $defaultProperties = array
    (
        '_lastid' => 0,
    );
}

/**
 * Une clé de tri
 */
class Sortkey extends Node
{
    protected static $defaultProperties = array
    (
    	'_id'=>0,             // Identifiant (numéro unique) de la clé de tri
        'name'=>'',           // Nom de la clé de tri
        'label'=>'',          // Libellé de l'index
        'description'=>'',    // Description de l'index
        'type'=>'string',     // Type de la clé à créer ('string' ou 'number')
    );
}

/**
 * Un champ dans une clé de tri.
 */
class SortkeyField extends Node
{
    protected static $defaultProperties = array
    (
        '_id'=>0,       // Identifiant (numéro unique) du champ
        'name'=>'',     // Nom du champ
        'start'=>'',    // Position de début ou chaine délimitant le début de la valeur à ajouter à la clé
        'end'=>'',      // Longueur ou chaine délimitant la fin de la valeur à ajouter à la clé
        'length'=>0,    // Longueur totale de la partie de clé (tronquée ou paddée à cette taille)
    );
}


namespace fab;

/**
 * Représente un schéma.
 */
class Schema extends Schema\Node
{
    /**
     * Propriétés par défaut du schéma.
     *
     * @var array
     */
    protected static $defaultProperties = array
    (
        'version' => '2.0',        // Version du schéma
    	'label' => '',             // Un libellé court décrivant la base
        'description' => '',       // Description, notes, historique des modifs...
        'stopwords' => '',         // Liste par défaut des mots-vides à ignorer lors de l'indexation
        'indexstopwords' => false, // Faut-il indexer les mots vides ?
        'creation' => '',          // Date de création du schéma
        'lastupdate' => '',        // Date de dernière modification du schéma
    );

    /**
    * Fils par défaut du schéma.
    *
    * @var array
    */
    protected static $defaultChildren = array('fields','indices','aliases','lookuptables','sortkeys');


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
            throw new \Exception('JSON invalide');

        return self::fromArray($array);
    }

    public function toXml($indent='')
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $this->_toXml();
    }

    public static function fromXml($xmlSource)
    {
        // Crée un document XML
        $xml=new \domDocument();
        $xml->preserveWhiteSpace=false;

        // gestion des erreurs : voir comment 1 à http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1

        // Charge le document
        if (! $xml->loadXML($xmlSource))
        {
            $h="Schéma incorrect, ce n'est pas un fichier xml valide :<br />\n";
            foreach (libxml_get_errors() as $error)
                $h.= "- ligne $error->line : $error->message<br />\n";
            libxml_clear_errors(); // libère la mémoire utilisée par les erreurs
            throw new \Exception($h);
        }

        // Convertit le schéma xml en objet
        $o=self::_fromXml($xml->documentElement);

        return $o;

    }


    protected static function NU_xmlToArray(\DOMNode $node)
    {
        static $isCollection = null;

        if (is_null($isCollection))
            $isCollection = array_flip(array('children', 'fields', 'indices','lookuptables','aliases','sortkeys'));

        // Stocke le type de noeud
        $result = array('nodetype' => $node->tagName);

        // Les attributs du tag sont des propriétés de l'objet
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $attribute)
            {
                // Le nom de l'attribut va devenir le nom de la propriété
                $name = $attribute->nodeName;

                // Définit la propriété
                $result[$name] = self::_xmlToValue($attribute->nodeValue);
            }
        }

        // Les noeuds fils du tag sont soit des propriétés, soit des objets enfants
        foreach ($node->childNodes as $child)
        {
            switch ($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    // Le nom de l'élément va devenir le nom de la propriété
                    $name = $child->tagName;

                    // Une propriété
                    if (!isset($isCollection[$name]))
                    {
                        // Vérifie qu'on n'a pas à la fois un attribut et un élément de même nom (<database label="xxx"><label>yyy...)
                        if (isset($result[$name]))
                            throw new \Exception("'$name' apparaît à la fois comme attribut et comme élément");

                        // Stocke la propriété
                        $result[$name] = self::_xmlToValue($child->nodeValue); // si plusieurs fois le même tag, c'est le dernier qui gagne
                    }

                    // Cas d'un tableau
                    else
                    {
                        $children = array();
                        foreach($child->childNodes as $child)
                            $children[] = self::_xmlToArray($child);

                        $result['children'] = $children;
                    }
                    break;

                    // Types de noeud autorisés mais ignorés
                case XML_COMMENT_NODE:
                    break;

                    // Types de noeud interdits
                default:
                    throw new \Exception("les noeuds de type '".$child->nodeName . "' ne sont pas autorisés");
            }
        }
        return $result;
    }
}