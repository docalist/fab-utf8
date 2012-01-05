<?php
/**
 * @package     fab
 * @subpackage  Schema
 * @author      Daniel M�nard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id
 */
namespace fab\Schema;


/**
 * Classe statique utilis�e pour d�finir les classes PHP utilis�es pour repr�senter
 * les noeuds qui composent un sch�ma.
 *
 * Dans un sch�ma, les noeuds sont d�finis par un nom symbolique qui indique leur type
 * (field, index, etc.)
 *
 * Lorsqu'un sch�ma est charg� en m�moire, chaque noeud est repr�sent� par un objet
 * PHP. La m�thode {@link registerNodetype()} permet de d�finir la classe PHP � utiliser
 * pour un nom symbolique donn�.
 *
 * Par d�faut, des classes sont fournies pour tous les types de noeud. Vous pouvez
 * utiliser {@link registerNodetype()} pour remplacer une classe pr�d�finie par votre
 * propre classe. Cela peut �tre utile pour introduire de nouvelles m�thodes, pour
 * d�finir de nouvelles propri�t�s ou encore pour modifier les valeurs par d�faut
 * d'un noeud.
 *
 * Pour cela, il suffit de d�finir une nouvelle classe descendante de
 * {@link fab\Schema\Node}, de surcharger sa propri�t� statique $defaultProperties et
 * d'appeller la m�thode {@link registerNodetype()} en indiquant le nom symbolique
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
     * Tableau inverse de {@link $typemap}, indique le nom symbolique associ�
     * � chaque classe.
     *
     * Utilis� et construit automatiquement par {@link nodetypeToClass()}.
     *
     * @var array
     */
    private static $nodetype;


    /**
     * Retourne un tableau contenant toutes les associations actuellement d�finies.
     *
     * Le tableau retourn� est sous la forme nom symbolique => nom de classe php.
     *
     * @return array
     */
    public static function all()
    {
        return self::$nodetype;
    }


    /**
     * D�finit le nom de la classe PHP � utiliser pour repr�senter un type de noeud
     * donn� au sein d'un sch�ma.
     *
     * @param string $nodetype le type de noeud � surcharger (nom symbolique).
     * @param string $class le nom de la classe PHP � utiliser pour ce type de noeud.
     * @throws \Exception si la classe indiqu�e n'est pas correcte (classe inexistante
     * ou qui n'h�rite pas de fab\Schema\Node).
     */
    public static function registerNodetype($nodetype, $class)
    {
        if (! class_exists($class))
            throw new \Exception("Classe $class non trouv�e");

        if (! is_subclass_of($class, 'fab\Schema\Node'))
            throw new \Exception("Classe incorrecte");

        self::$typemap[$nodetype] = $class;

        unset(self::$nodetype); // le tableau inverse doit �tre recr��
    }


    /**
     * Retourne la classe PHP � utiliser pour repr�senter un noeud d'un type donn�.
     *
     * @param string $nodetype type de noeud (nom symbolique).
     * @throws \Exception si le nom symbolique indiqu� n'est pas r�f�renc�.
     * @return string
     */
    public static function nodetypeToClass($nodetype)
    {
        if (! isset(self::$typemap[$nodetype]))
            throw new \Exception("Type de noeud inconnu : '$nodetype'");

        return self::$typemap[$nodetype];
    }


    /**
     * Retourne le nom symbolique associ� � une classe PHP donn�e.
     *
     * @param string $class
     * @throws \Exception si la classe indiqu�e n'est pas r�f�renc�e.
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
 * Classe de base (abstraite) repr�sentant un noeud dans un sch�ma.
 *
 * Un noeud est un objet qui peut contenir des propri�t�s (cf {@link get()},
 * {@link set()}, {@link has()}, {@link remove()} et {@link all()}) et des
 * noeuds enfants (cf. {@link addChild()}, {@link getChildren()},
 * {@link getChild()}, {@link hasChildren()}, {@link hasChild()},
 * {@link removeChildren()} et {@link removeChild()}).
 *
 * Un noeud dispose de propri�t�s par d�faut (cf {@link getDefaultProperties()})
 * et de fils par d�faut (cf {@link getDefaultChildren()}).
 *
 * @package     fab
 * @subpackage  Schema
 */
abstract class Node
{
    /**
     * Propri�t�s pr�d�finies et valeur par d�faut de ce type de noeud.
     *
     * Cette propri�t� est destin�e � �tre surcharg�e par les classes descendantes.
     *
     * @var array
     */
    protected static $defaultProperties = array();


    /**
    * Fils par d�faut du noeud.
    *
    * Cette propri�t� est destin�e � �tre surcharg�e par les classes descendantes.
    *
    * @var array
    */
    protected static $defaultChildren = array();


    protected $parent = null;
    protected $properties = null;
    protected $children = null;

    /**
     * Cr�e un nouveau noeud.
     *
     * Un noeud contient automatiquement toutes les propri�t�s par d�faut d�finies
     * pour ce type de noeud.
     *
     * @param array $parameters propri�t�s du noeud.
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
     * Construit un noeud � partir d'un tableau contenant ses propri�t�s.
     *
     * @param array $node un tableau contenant les propri�t�s du noeud.
     * Le tableau doit contenir une propri�t� 'nodetype' qui indique le
     * type du noeud � cr�er.
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
     * Construit un noeud � partir d'un tableau contenant ses propri�t�s.
     *
     * @param array $node un tableau contenant les propri�t�s du noeud.
     * Le tableau doit contenir une propri�t� 'nodetype' qui indique le
     * type du noeud � cr�er.
     *
     * @return Node
     */
    public static function fromArray(array $parameters)
    {
        return self::create($parameters['nodetype'], $parameters);
    }


    /**
     * Retourne les propri�t�s par d�faut du noeud.
     *
     * @return array()
     */
    public static function getDefaultProperties()
    {
        return static::$defaultProperties;
    }


    /**
     * Retourne les fils par d�faut du noeud.
     *
     * @return array()
     */
    public static function getDefaultChildren()
    {
        return static::$defaultChildren;
    }


    /**
     * Retourne un tableau contenant toutes les propri�t�s du noeud.
     *
     * @return array
     */
    public function all()
    {
        return get_object_vars($this);
    }


    /**
     * Retourne la propri�t� dont le nom est indiqu� ou null si la propri�t�
     * demand�e n'existe pas.
     *
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        return isset($this->$name) ? $this->name : null;
    }


    /**
     * Ajoute ou modifie une propri�t�.
     *
     * Si la valeur indiqu�e est <code>null</code>, la propri�t� est supprim�e de
     * l'objet ou revient � sa valeur par d�faut si c'est une propri�t� pr�d�finie.
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
     * Indique si une propri�t� existe.
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
     * Supprime la propri�t� indiqu�e ou la r�initialise � sa valeur par d�faut
     * s'il s'agit d'une propri�t� pr�d�finie.
     *
     * Sans effet si la propri�t� n'existe pas.
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
     * Ajoute un noeud fils � la propri�t� children du noeud.

     * @param Node $child le noeud fils � ajouter
     * @throws Exception si le noeud n'a pas de nom ou si ce nom existe d�j�.
     * @return $this
     */
    public function addChild(Node $child)
    {
        $name = $child->get('name');

        if (empty($name))
            throw new \Exception('propri�t� "name" non trouv�e'.var_export($child,true));

        if ($this->has($name))
            throw new \Exception('un objet existe d�j� avec ce nom');

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
     * Retourne le noeud fils dont le nom est indiqu� ou null si le noeud demand� n'existe pas.
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
     * Indique si le noeud fils dont le nom est indiqu� existe.
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
     * Supprime le noeud fils dont le nom est indiqu�.
     *
     * Sans effet si le noeud indiqu� n'existe pas.
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
         * Tableau utilis� pour convertir les sch�mas xml de la version 1 � la version 2.
         *
         * Ce tableau contient toutes les "collections" qui existaient dans l'ancien format.
         * Pour chaque collection (on indique le path depuis la racine), on peut indiquer :
         * - true : cette collection existe toujours dans le nouveau format.
         *   Il faut donc la cr�er et ajouter dedans les noeuds fils.
         * - une chaine contenant un type de noeud symbolique : cela signifie que cette
         *   collection n'existe plus dans le nouveau format. Les noeuds fils doivent �tre
         *   ajout�s directement dans la cl� children du noeud parent et doivent �tre cr��
         *   en utilisant le type indiqu�.
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

        // Les attributs du tag sont des propri�t�s de l'objet
        if ($node->hasAttributes())
            foreach ($node->attributes as $attribute)
                $result->set($attribute->nodeName, self::_xmlToValue($attribute->nodeValue));

        // Les noeuds fils du tag sont soit des propri�t�s, soit des objets enfants
        foreach ($node->childNodes as $child)
        {
            $childpath = "$path/$child->tagName";

            switch ($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    // Le nom de l'�l�ment va devenir le nom de la propri�t�
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

                    // Propri�t�
                    else
                    {
                        // V�rifie qu'on n'a pas � la fois un attribut et un �l�ment de m�me nom (<database label="xxx"><label>yyy...)
                        if ($node->attributes->getNamedItem($name))
                            throw new \Exception("Erreur dans le source xml : la propri�t� '$name' appara�t � la fois comme attribut et comme �l�ment");

                        // Stocke la propri�t�
                        $result->set($name, self::_xmlToValue($child->nodeValue)); // si plusieurs fois le m�me tag, c'est le dernier qui gagne
                    }
                    break;

                    // Types de noeud autoris�s mais ignor�s
                case XML_COMMENT_NODE:
                    break;

                    // Types de noeud interdits
                default:
                    throw new \Exception("les noeuds de type '".$child->nodeName . "' ne sont pas autoris�s");
            }
        }

        return $result;
    }


    /**
    * Fonction utilitaire utilis�e par {@link xmlToObject()} pour convertir la
    * valeur d'un attribut ou le contenu d'un tag.
    *
    * Pour les bool�ens, la fonction reconnait les valeurs 'true' ou 'false'.
    * Pour les autres types scalaires, la fonction encode les caract�res '<',
    * '>', '&' et '"' par l'entit� xml correspondante.
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
            throw new DatabaseSchemaXmlNodeException($node, 'bool�en attendu');
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
		'_id'=>0,                   // Identifiant (num�ro unique) du champ
        'name'=>'',                 // Nom du champ, d'autres noms peuvent �tre d�finis via des alias
        'type'=>'text',             // Type du champ (juste � titre d'information, non utilis� pour l'instant)
//         '_type'=>self::FIELD_TEXT,  // Traduction de la propri�t� type en entier
        'label'=>'',                // Libell� du champ
        'description'=>'',          // Description
        'defaultstopwords'=>true,   // Utiliser les mots-vides de la base
        'stopwords'=>'',            // Liste sp�cifique de mots-vides � appliquer � ce champ
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
    	'_id'=>0,                   // Identifiant (num�ro unique) de l'index
        'name'=>'',                 // Nom de l'index
        'label'=>'',                // Libell� de l'index
        'description'=>'',          // Description de l'index
        'type'=>'probabilistic',    // Type d'index : 'probabilistic' ou 'boolean'
        'spelling'=>false,          // Ajouter les termes de cet index dans le correcteur orthographique
//         '_type'=>self::INDEX_PROBABILISTIC, // Traduction de la propri�t� type en entier
    );
}

/**
 * Un champ index�
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
        'global'=>false,    // DEPRECATED : n'est plus utilis�, conserv� pour compatibilit�
        'start'=>'',        // Position ou chaine indiquant le d�but du texte � indexer
        'end'=>'',          // Position ou chain indquant la fin du texte � indexer
        'weight'=>1         // Poids des tokens ajout�s � cet index
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
    	'_id'=>0,                // Identifiant (num�ro unique) de l'alias (non utilis�)
        'name'=>'',              // Nom de l'alias
        'label'=>'',             // Libell� de l'index
        'description'=>'',       // Description de l'index
        'type'=>'probabilistic', // Type d'index : 'probabilistic' ou 'boolean'
//         '_type'=>self::INDEX_PROBABILISTIC, // Traduction de la propri�t� type en entier
    );
}

/**
 * Un index dans un alias
 */
class AliasIndex extends Node
{
    protected static $defaultProperties = array
    (
        '_id'=>0,      // Identifiant (num�ro unique) du champ
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
    	'_id'=>0,                        // Identifiant (num�ro unique) de la table
        'name'=>'',                      // Nom de la table
        'label'=>'',                     // Libell� de l'index
        'description'=>'',               // Description de l'index
        'type'=>'simple',                // type de table : "simple" ou "invers�e"
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
    	'_id'=>0,       // Identifiant (num�ro unique) du champ
        'name'=>'',     // Nom du champ
        'startvalue'=>1,// Indice du premier article � prendre en compte (1-based)
        'endvalue'=>0,  // Indice du dernier article � prendre en compte (0=jusqu'� la fin)
        'start'=>'',    // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la table
        'end'=>''       // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la table
    );
}

/**
 * Liste des cl�s de tri.
 */
class Sortkeys extends Node
{
    protected static $defaultProperties = array
    (
        '_lastid' => 0,
    );
}

/**
 * Une cl� de tri
 */
class Sortkey extends Node
{
    protected static $defaultProperties = array
    (
    	'_id'=>0,             // Identifiant (num�ro unique) de la cl� de tri
        'name'=>'',           // Nom de la cl� de tri
        'label'=>'',          // Libell� de l'index
        'description'=>'',    // Description de l'index
        'type'=>'string',     // Type de la cl� � cr�er ('string' ou 'number')
    );
}

/**
 * Un champ dans une cl� de tri.
 */
class SortkeyField extends Node
{
    protected static $defaultProperties = array
    (
        '_id'=>0,       // Identifiant (num�ro unique) du champ
        'name'=>'',     // Nom du champ
        'start'=>'',    // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la cl�
        'end'=>'',      // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la cl�
        'length'=>0,    // Longueur totale de la partie de cl� (tronqu�e ou padd�e � cette taille)
    );
}


namespace fab;

/**
 * Repr�sente un sch�ma.
 */
class Schema extends Schema\Node
{
    /**
     * Propri�t�s par d�faut du sch�ma.
     *
     * @var array
     */
    protected static $defaultProperties = array
    (
        'version' => '2.0',        // Version du sch�ma
    	'label' => '',             // Un libell� court d�crivant la base
        'description' => '',       // Description, notes, historique des modifs...
        'stopwords' => '',         // Liste par d�faut des mots-vides � ignorer lors de l'indexation
        'indexstopwords' => false, // Faut-il indexer les mots vides ?
        'creation' => '',          // Date de cr�ation du sch�ma
        'lastupdate' => '',        // Date de derni�re modification du sch�ma
    );

    /**
    * Fils par d�faut du sch�ma.
    *
    * @var array
    */
    protected static $defaultChildren = array('fields','indices','aliases','lookuptables','sortkeys');


    /**
     * Cr�e un sch�ma � partir d'une chaine au format JSON.
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
        // Cr�e un document XML
        $xml=new \domDocument();
        $xml->preserveWhiteSpace=false;

        // gestion des erreurs : voir comment 1 � http://fr.php.net/manual/en/function.dom-domdocument-loadxml.php
        libxml_clear_errors(); // >PHP5.1
        libxml_use_internal_errors(true);// >PHP5.1

        // Charge le document
        if (! $xml->loadXML($xmlSource))
        {
            $h="Sch�ma incorrect, ce n'est pas un fichier xml valide :<br />\n";
            foreach (libxml_get_errors() as $error)
                $h.= "- ligne $error->line : $error->message<br />\n";
            libxml_clear_errors(); // lib�re la m�moire utilis�e par les erreurs
            throw new \Exception($h);
        }

        // Convertit le sch�ma xml en objet
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

        // Les attributs du tag sont des propri�t�s de l'objet
        if ($node->hasAttributes())
        {
            foreach ($node->attributes as $attribute)
            {
                // Le nom de l'attribut va devenir le nom de la propri�t�
                $name = $attribute->nodeName;

                // D�finit la propri�t�
                $result[$name] = self::_xmlToValue($attribute->nodeValue);
            }
        }

        // Les noeuds fils du tag sont soit des propri�t�s, soit des objets enfants
        foreach ($node->childNodes as $child)
        {
            switch ($child->nodeType)
            {
                case XML_ELEMENT_NODE:
                    // Le nom de l'�l�ment va devenir le nom de la propri�t�
                    $name = $child->tagName;

                    // Une propri�t�
                    if (!isset($isCollection[$name]))
                    {
                        // V�rifie qu'on n'a pas � la fois un attribut et un �l�ment de m�me nom (<database label="xxx"><label>yyy...)
                        if (isset($result[$name]))
                            throw new \Exception("'$name' appara�t � la fois comme attribut et comme �l�ment");

                        // Stocke la propri�t�
                        $result[$name] = self::_xmlToValue($child->nodeValue); // si plusieurs fois le m�me tag, c'est le dernier qui gagne
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

                    // Types de noeud autoris�s mais ignor�s
                case XML_COMMENT_NODE:
                    break;

                    // Types de noeud interdits
                default:
                    throw new \Exception("les noeuds de type '".$child->nodeName . "' ne sont pas autoris�s");
            }
        }
        return $result;
    }
}