<?php
/**
 * @charset 	UTF-8
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
 * PHP. La méthode {@link register()} permet de définir la classe PHP à utiliser
 * pour un nom symbolique donné.
 *
 * Par défaut, des classes sont fournies pour tous les types de noeud. Vous pouvez
 * utiliser {@link register()} pour remplacer une classe prédéfinie par votre
 * propre classe. Cela peut être utile pour introduire de nouvelles méthodes, pour
 * définir de nouvelles propriétés ou encore pour modifier les valeurs par défaut
 * d'un noeud.
 *
 * Pour cela, il suffit de définir une nouvelle classe descendante de
 * {@link fab\Schema\Node}, de surcharger sa propriété statique $defaultProperties et
 * d'appeller la méthode {@link register()} en indiquant le nom symbolique
 * correspondant au type de noeud que vous voulez surcharger.
 *
 * @package     fab
 * @subpackage  Schema
 */
use fab\Schema;

abstract class NodesTypes
{
    /**
     * Tableau de conversion entre les noms symboliques et les noms de classes.
     *
     * @var array
     */
    protected static $typemap = array
    (
    	'schema'               => 'fab\Schema',
        'fields'               => 'fab\Schema\Fields',
            'field'            => 'fab\Schema\Field',
            'groupfield'       => 'fab\Schema\GroupField',
    	'indices'              => 'fab\Schema\Indices',
    		'index'            => 'fab\Schema\Index',
        	'indexfield'       => 'fab\Schema\IndexField',
    	'aliases'              => 'fab\Schema\Aliases',
        	'alias'	           => 'fab\Schema\Alias',
    		'aliasindex'       => 'fab\Schema\AliasIndex',
    	'lookuptables'         => 'fab\Schema\LookupTables',
        	'lookuptable'      => 'fab\Schema\LookupTable',
        	'lookuptablefield' => 'fab\Schema\LookupTableField',
        'sortkeys'	           => 'fab\Schema\Sortkeys',
    		'sortkey'	       => 'fab\Schema\Sortkey',
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
        return self::$typemap;
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
    public static function register($nodetype, $class)
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
 * Un noeud est un objet qui peut contenir des propriétés (cf. {@link get()},
 * {@link set()}, {@link has()}, {@link remove()} et {@link getProperties()}).
 *
 * Un noeud dispose de propriétés par défaut (cf. {@link getDefaultProperties()})
 * qui sont créées automatiquement et sont toujours disponibles.
 *
 * Un noeud peut être créé via {link __construct() son constructeur} ou en
 * utilisant les méthodes statiques disponibles (cf. {@link create()} et
 * {@link fromArray()}).
 *
 * Un noeud est toujours d'un type donné (cf. {@link getType()}. Lorsqu'un
 * noeud est sérialisé sous forme de tableau (format json, tableau php),
 * le type du noeud figure dans une propriété supplémentaire "_nodetype".
 *
 * Un noeud peut être ajouté dans une {@link NodesCollection collection de noeuds}.
 * Pour cela il faut qu'il ait une propriété "name" indiquant son nom.
 *
 * Une fois qu'un noeud a été ajouté à une collection, il dispose d'un parent
 * (cf {@link getParent()}) qui permet d'accèder au schéma ({@link getSchema()}.
 *
 * @package     fab
 * @subpackage  Schema
 */
abstract class Node
{
    /**
     * Propriétés prédéfinies et valeurs par défaut des propriétés de ce type de noeud.
     *
     * Cette propriété est destinée à être surchargée par les classes descendantes.
     *
     * $defaultProperties indique à la fois :
     * - la liste des propriétés prédéfinies
     * - le type de chacune des propriétés
     * - la valeur par défaut de chacune des propriétés
     * - éventuellement, la liste des valeurs autorisées.
     * - par inférence, le type de contrôle utilisé dans l'éditeur de schéma pour
     *   modifier cette propriété.
     *
     * Pour cela, un codage est utilisé. La valeur associé à chaque propriété par
     * défaut peut être :
     *
     * - STRING : type par défaut, une textarea fullwidth autoheight sera utilisée
     *   dans l'éditeur de schéma pour éditer la propriété. La chaine contient la
     *   valeur par défaut de la propriété.
     * - INT : une zone de texte de type "number" (html5) d'une longueur maximale
     *   de 5 caractères maximum sera utilisée. L'entier contient la valeur par
     *   défaut de la propriété.
     * - BOOLEAN : la propriété sera représentée par une case à cocher. Le booléen
     *   indique la valeur par défaut de la propriété (true : la case à cocher sera
     *   cochée par défaut, false, elle sera décochée par défaut).
     * - ARRAY : la propriété sera représentée par un select dans lequel l'utilisateur
     *   peut sélectionner l'une des valeurs qui figurent dans le tableau. Le premier
     *   élément du tableau représente la valeur par défaut de la propriété.
     * - NULL : propriété en lecture seule. Une textarea "disabled" sera utilisée pour afficher
     *   la propriété. L'utilisateur peut voir la valeur de la propriété mais ne peut pas la
     *   modifier. Il n'est pas possible d'indiquer dans ce cas une valeur par défaut.
     *
     * @var array
     */
    protected static $defaultProperties = array();


    /**
     * Définit les libellés à utiliser pour ce type de noeud.
     *
     * $labels est un tableau avec les clés suivantes :
     * - 'main' : libellé utilisé pour un noeud de ce type (par exemple pour
     *   un noeud de type field, le libellé indiqué serait "Champ".
     * - 'add' : libellé utilisé pour ajouter un noeud de ce type
     *   (exemple : "Ajouter un champ").
     * - 'remove' : libellé utilisé pour supprimer un noeud de ce type
     *   (exemple : "Supprimer ce champ").
     *
     * @var array
     */
    protected static $labels = array
    (
        'main' => 'Noeud',
        'add' => 'Nouveau noeud de type %1', // %1 : type
        'remove' => 'Supprimer le noeud %2', // %1 : type, %2 : name
    );


    /**
     * Définit les icones à utiliser pour ce type de noeud.
     *
     * $icons est un tableau avec les clés suivantes :
     * - 'image' : icone utilisée pour représenter un noeud de ce type.
     * - 'add' : icone utilisée pour signifier "ajouter un noeud de ce type".
     * - 'remove' : icone utilisée pour indiquer "supprimer un noeud de ce type".
     *
     * Toutes les icones sont relatives au répertoire /web/modules/AdminSchemas/images.
     *
     * @var array
     */
    protected static $icons = array
    (
        'image' => 'zone.png',
        'add' => 'zone--plus.png',
        'remove' => 'zone--minus.png',
    );


    /**
     * Propriétés actuelles du noeud.
     *
     * @var array
     */
    protected $properties = null;


    /**
     * Noeud parent de ce noeud.
     *
     * Cette propriété est initialisée automatiquement lorsqu'un noeud
     * est ajouté dans une {@link NodesCollection collection}.
     *
     * @var Node
     */
    protected $parent = null;


    /**
     * Crée un nouveau noeud.
     *
     * Un noeud contient automatiquement toutes les propriétés par défaut définies
     * pour ce type de noeud et celles-ci apparaissent en premier.
     *
     * @param array $properties propriétés du noeud.
     */
    public function __construct(array $properties = array())
    {
        // on commence par les propriétés par défaut pour qu'elles apparaissent
        // en premier et dans l'ordre indiqué dans la classe.
        $this->properties = array_merge(self::getDefaultValue(), $properties);
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
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
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
            $this->properties[$name] = $value;

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
        return isset($this->properties[$name]);
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
            $this->properties[$name] = self::getDefaultValue($name);
        else
            unset($this->$name);

        return $this;
    }


    /**
     * Retourne le type du noeud.
     *
     * La méthode retourne le {@link NodeTypes nom symbolique} associé au noeud.
     *
     * @return string
     */
    public function getType()
    {
        return NodesTypes::classToNodetype(get_class($this));
    }


    /**
     * Retourne le noeud parent de ce noeud ou <code>null</code> si le noeud
     * n'a pas encore été ajouté comme fils d'un noeud existant.
     *
     * @return \fab\Schema\Node
     */
    public function getParent()
    {
        return $this->parent;
    }


    /**
     * Modifie le parent de ce noeud.
     *
     * @return $this
     */
    protected function setParent(NodesCollection $parent)
    {
        $this->parent = $parent;
        return $this;
    }


    /**
     * Retourne le schéma dont fait partie ce noeud ou <code>null</code> si
     * le noeud n'a pas encore été ajouté à un schéma.
     *
     * @return \fab\Schema
     */
    public function getSchema()
    {
        return is_null($this->parent) ? null : $this->parent->getSchema();
    }


    /**
     * Retourne un tableau contenant toutes les propriétés du noeud.
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }


    /**
     * Contrairement à un objet {@link NodesCollection}, un noeud de base ne contient pas de fils.
     *
     * @return array
     */
    public function hasChildren()
    {
        return false;
    }


    /**
     * Construit un noeud à partir d'un tableau contenant ses propriétés.
     *
     * @param string $nodetype le type du noeud à créer.
     * @param array $properties un tableau contenant les propriétés du noeud créé.
     *
     * @return Node
     */
    public static function create($nodetype, array $properties = array())
    {
        $class = NodesTypes::nodetypeToClass($nodetype);
        if (! isset($properties['name'])) $properties['name'] = $nodetype;
        return new $class($properties);
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
     * Retourne la valeur par défaut d'une propriété ou de l'ensemble
     * des propriétés prédéfinies si aucun nom n'est indiqué.
     *
     *
     * @param string $name
     * @return null|mixed|array Si $name a été indiqué, la méthode retourne la
     * valeur par défaut de la propriété, ou null si la propriété demandée n'est
     * pas prédéfinie.
     * Si $name est absent, la méthode retourne un tableau contenant les valeurs
     * par défaut de toutes les propriétés prédéfinies.
     */
    public static function getDefaultValue($name = null)
    {
        if ($name)
        {
            if (! isset(static::$defaultProperties[$name])) return null;
            $value = static::$defaultProperties[$name];
            if (is_array($value)) return $value[0];
            if (is_string($value) && substr($value, 0,1) ==='@') return null; // référence
            return $value;
        }

        $result = static::$defaultProperties;
        foreach($result as $name => $value)
        {
            if (is_array($value))
                $result[$name] = $value[0];
            elseif (is_string($value) && substr($value, 0,1) ==='@')
                $value = null; // référence
        }
        return $result;
    }


    /**
     * Retourne un libellé ou tous les libellés définis pour un type
     * de noeud donné.
     *
     * @param null|string $type quand type est null, un tableau contenant
     * tous les libellés définis pour ce type de noeud est retourné.
     * Quand $type est une chaine, le libellé correspondant est retourné.
     *
     * @return string
     */
    public static function getLabels($type = null)
    {
        if (is_null($type))
            return array_merge(self::$labels, static::$labels);

        if (isset(static::$labels[$type]))
            return static::$labels[$type];

        if (isset(self::$labels[$type]))
            return static::$labels[$type];

        return $type;
    }


    /**
     * Retourne une icone ou toutes les icones définies pour un type
     * de noeud donné.
     *
     * @param null|string $type quand type est null, un tableau contenant
     * toutes les icones définies pour ce type de noeud est retourné.
     * Quand $type est une chaine, l'icone correspondante est retourné.
     *
     * @return string
     */
    public static function getIcons($type = null)
    {
        if (is_null($type))
            return array_merge(self::$icons, static::$icons);

        if (isset(static::$icons[$type]))
            return static::$icons[$type];

        if (isset(self::$icons[$type]))
            return static::$icons[$type];

        return null;
    }


    /**
     * Construit un noeud à partir d'un tableau contenant ses propriétés.
     *
     * @param array $properties un tableau contenant les propriétés du noeud.
     *
     * Le tableau doit contenir une propriété '_nodetype' qui indique le
     * type du noeud à créer.
     *
     * @throws Exception si la propriété _nodetype ne figure pas dans le
     * tableau.
     *
     * @return Node
     */
    public static function fromArray(array $properties)
    {
        if (! isset($properties['_nodetype']))
            throw new Exception('Le tableau ne contient pas de clé "_nodetype".');

        $nodetype = $properties['_nodetype'];
        unset($properties['_nodetype']);

        return self::create($nodetype, $properties);
    }


    /**
     * Convertit le noeud en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return array_merge(array('_nodetype', $this->getType()), $this->properties);
    }


    /**
     * Méthode utilitaire utilisée par {@link \fab\Schema::fromXml()} pour
     * charger un chéma Xml.
     *
     * @param \DOMNode $node
     * @param string $path
     * @param string $nodetype
     * @throws \Exception
     */
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
     * Méthode utilitaire utilisée par {@link \fab\Schema}. Ajoute les propriétés du
     * noeud dans le XMLWriter passé en paramètre.
     *
     * La méthode ne générère que les propriétés du noeud. Le tag englobant doit
     * avoir été généré par l'appellant.
     *
     * @param \XMLWriter $xml
     */
    protected function _toXml(\XMLWriter $xml)
    {
        foreach($this->properties as $name=>$value)
        {
            if (isset(static::$defaultProperties[$name]))
            {
                if (self::getDefaultValue($name) === $value)
                {
                    continue;
                }
            }
            if (is_bool($value)) $value = $value ? 'true' : 'false';
            $xml->writeElement($name, $value);
        }
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
    protected static function _xmlToValue($value)
    {
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if (is_int($value) || ctype_digit($value)) return (int) $value;
        if (is_numeric($value)) return (float)$value;
        return $value;
    }



    /**
     * Méthode utilitaire utilisée par {@link \fab\Schema}. Sérialise le noeud
     * au format JSON.
     *
     * La méthode ne générère que les propriétés du noeud. La méthode appelante doit générer
     * les accolades ouvrantes et fermantes.
     *
     * @param \XMLWriter $xml
     */
    protected function _toJson($indent = false, $currentIndent = '', $colon = ':')
    {
        $h = $currentIndent . json_encode('_nodetype') . $colon . json_encode($this->getType()) . ',';
        foreach($this->properties as $name=>$value)
            $h .= $currentIndent . json_encode($name) . $colon . json_encode($value) . ',';

        return rtrim($h, ',');
    }
}


/**
 * Classe abstraite représentant une collection de noeuds.
 *
 * Une collection de noeud est un type particulier de {@link Node noeud} qui
 * peut contenir d'autres noeuds.
 *
 * Chaque collection définit les types des noeuds qui sont autorisés comme fils
 * (cf {@link getValidChildren()} et {@link isValidChildren()}).
 *
 * Certains noeuds sont prédéfinis et existent toujours au sein de la collection
 * (cf. {@link getDefaultChildren()}.
 *
 * La collection peut être manipulée en utilisant les méthodes {@link addChild()},
 * {@link getChildren()}, {@link getChild()}, {@link hasChildren()},
 * {@link hasChild()}, {@link removeChildren()} et {@link removeChild()}.
 *
 * @package     fab
 * @subpackage  Schema
 */
abstract class NodesCollection extends Node
{
    /**
     * Fils par défaut du noeud.
     *
     * Cette propriété est destinée à être surchargée par les classes descendantes.
     *
     * @var array
     */
    protected static $defaultChildren = array();


    /**
     * Types des noeuds pouvant être ajoutés comme fils.
     *
     * Cette propriété est destinée à être surchargée par les classes descendantes.
     *
     * @var array
    */
    protected static $validChildren = array();


    /**
     * Liste des fils actuels de la collection.
     *
     * @var array
     */
    protected $children = null;


    /**
     * Crée un nouveau noeud.
     *
     * Un noeud contient automatiquement toutes les propriétés par défaut définies
     * pour ce type de noeud.
     *
     * @param array $properties propriétés du noeud.
     */
    public function __construct(array $properties = array())
    {
        $children = isset($properties['children']) ? $properties['children'] : array();
        unset($properties['children']);

        parent::__construct($properties);

        foreach($children as $child)
            $this->addChild(self::fromArray($child));

        foreach(static::$defaultChildren as $name)
            if (! $this->hasChild($name)) $this->addChild(self::create($name));
    }


    /**
     * Ajoute un noeud fils à la propriété children du noeud.
     *
     * @param Node $child le noeud fils à ajouter
     * @throws Exception si le noeud n'a pas de nom ou si ce nom existe déjà.
     * @return $this
     */
    public function addChild(Node $child)
    {
        if (! self::isValidChild($child))
            throw new \Exception
            (
        		'Un noeud de type "' . $this->getType() .
        		'" ne peut pas contenir des noeuds de type "' . $child->getType() . '"'
            );

        $name = $child->get('name');

        if (empty($name))
            throw new \Exception('propriété "name" non trouvée'.var_export($child,true));

        if ($this->has($name))
            throw new \Exception('un objet existe déjà avec ce nom');

        $child->setParent($this);

        if (is_null($this->children)) $this->children = array();
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
        if (count($this->children) === 0) unset($this->children);
        return $this;
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
     * Retourne les types de noeuds autorisés comme fils de ce noeud.
     *
     * @return array()
    */
    public static function getValidChildren()
    {
        return static::$validChildren;
    }


    /**
     * Indique si le noeud passé en paramètre peut être ajouté comme fils
     * au noeud en cours.
     *
     * @return bool
     */
    public static function isValidChild(Node $child)
    {
        return in_array($child->getType(), static::$validChildren); // todo: utiliser un isset ?
    }


    /**
     * Convertit la collection de noeuds en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();
        if (isset($this->children))
        {
            $children = array();
            foreach($this->children as $child)
            $children[] = $child->toArray();
            $array['children'] = $children;
        }

        return $array;
    }


    /**
     * Méthode utilitaire utilisée par {@link \fab\Schema}. Ajoute les propriétés
     * et les fils du noeud dans le XMLWriter passé en paramètre.
     *
     * Le tag englobant doit avoir été généré par l'appellant.
     *
     * @param \XMLWriter $xml
     */
    protected function _toXml(\XMLWriter $xml)
    {
        parent::_toXml($xml);

        if (isset($this->children))
        {
            $xml->startElement('children');
            foreach($this->children as $child)
            {
                $xml->startElement(NodesTypes::classToNodetype(get_class($child)));
                $child->_toXml($xml);
                $xml->endElement();
            }
            $xml->endElement();
        }

        return $this;
    }

    protected function _toJson($indent = false, $currentIndent = '', $colon = ':')
    {
        $h = parent::_toJson($indent, $currentIndent, $colon);
        if (isset($this->children))
        {
            $h .= ',' . $currentIndent;
            $h .= json_encode('children') . $colon;
            $h .= $currentIndent . "[";
            $currentIndent = $currentIndent . str_repeat(' ', $indent);
            $childIndent = $currentIndent . str_repeat(' ', $indent);
            foreach($this->children as $child)
            {
                $h .= $currentIndent . '{';
                $h .= $child->_toJson($indent, $childIndent, $colon);
                $h .= $currentIndent. '},';
            }
            $h = rtrim($h, ',');
            $h .= substr($currentIndent, 0, -$indent) . "]";
        }
//        $h = rtrim($h, ',');

        return $h;
    }


}


/**
 * Liste des champs. Collection d'objets {@link Field}.
 */
class Fields extends NodesCollection
{
    protected static $defaultProperties = array
    (
        '_lastid' => null,
    );

    protected static $validChildren = array('field', 'groupfield');

    protected static $labels = array
    (
        'main' => 'Liste des champs',
    );

    protected static $icons = array
    (
        'image' => 'zone--arrow.png',
    );
}

/**
 * Un champ simple.
 */
class Field extends Node
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) du champ
		'_id' => null,

        // Nom du champ, d'autres noms peuvent être définis via des alias
        'name' => '',

        // Type du champ
        'type' => array('text','bool','int','autonumber'),

        // Traduction de la propriété type en entier
        '_type' => null,

        // Libellé du champ
        'label' => '',

        // Description
        'description' => '',

        // Faut-il utiliser les mots-vides de la base
//         'defaultstopwords' => true,

        // Liste spécifique de mots-vides à appliquer à ce champ
//         'stopwords' => '',

//        'widget' => array('display'),

//     	'widget' => array('textbox', 'textarea', 'checklist', 'radiolist', 'select'),
//     	'datasource' => array('pays','langues','typdocs'),

//     	'mapper' => array('DefaultMapper', 'HtmlMapper'),
//     	'tokenizer' => array('', 'StandardTokenizer', 'DateTokenizer'),
//     	'tokenhandler' => '',
//     	'weight' => 1,
    );

    protected static $labels = array
    (
        'main' => 'Champ',
        'add' => 'Nouveau champ',
        'remove' => 'Supprimer le champ %2', // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'zone.png',
        'add' => 'zone--plus.png',
        'remove' => 'zone--minus.png',
    );
}

/**
 * Un champ comportant plusieurs sous zones. Collection d'objets {@link Field}.
 */
class GroupField extends NodesCollection
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) du champ
		'_id' => null,

        // Nom du champ, d'autres noms peuvent être définis via des alias
        'name' => '',

        // Libellé du champ
        'label' => '',

        // Description
        'description' => '',
    );

    protected static $validChildren = array('field');

    protected static $labels = array
    (
        'main' => 'Groupe de champs',
        'add' => 'Nouveau groupe de champs',
        'remove' => 'Supprimer le groupe de champs %2', // %1=name, %2=type
    );


    protected static $icons = array
    (
        'image' => 'folde-open-document-text.png',
        'add' => 'zone--plus.png', // todo
        'remove' => 'zone--minus.png', // todo
    );
}

/**
 * Liste des index. Collection d'objets {@link Index}.
 */
class Indices extends NodesCollection
{
    protected static $defaultProperties = array
    (
        '_lastid' => null,
    );

    protected static $validChildren = array('index');

    protected static $labels = array
    (
        'main' => 'Liste des index',
    );

    protected static $icons = array
    (
        'image' => 'lightning--arrow.png',
    );
}

/**
 * Un index. Collection d'objets {@link IndexField}.
 */
class Index extends NodesCollection
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) de l'index
        '_id' => null,

        // Nom de l'index
        'name' => '',

        // Libellé de l'index
        'label' => '',

        // Description de l'index
        'description' => '',

        // Type d'index : 'probabilistic' ou 'boolean'
        'type' => array('probabilistic', 'boolean'),

        // Traduction de la propriété type en entier
        '_type' => null,

        // Ajouter les termes de cet index dans le correcteur orthographique
        'spelling' => false,
    );
    protected static $validChildren = array('indexfield');

    protected static $labels = array
    (
    	'main' => 'Index',
        'add' => 'Nouvel index',
        'remove' => "Supprimer l'index %2", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'lightning.png',
        'add' => 'lightning--plus.png',
        'remove' => 'lightning--minus.png',
    );
}

/**
 * Un champ indexé.
 */
class IndexField extends Node
{
    protected static $defaultProperties = array
    (
        // Identifiant du champ
        '_id' => null,

        // Nom du champ
        'name' => '@field',

        // Indexer les mots
        'words' => true,

        // Indexer les phrases
        'phrases' => false,

        // Indexer les valeurs
        'values' => false,

        // Compter le nombre de valeurs (empty, has1, has2...)
        'count' => false,

        // DEPRECATED : n'est plus utilisé, conservé pour compatibilité
        'global' => false,

        // Position ou chaine indiquant le début du texte à indexer
        'start' => '',

        // Position ou chain indiquant la fin du texte à indexer
        'end' => '',

        // Poids des tokens ajoutés à cet index
        'weight' => 1
    );

    protected static $labels = array
    (
    	'main' => 'Champ indexé',
        'add' => "Ajouter un champ dans l'index",
        'remove' => "Supprimer le champ %2 de l'index", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'zone.png',
        'add' => 'zone--plus.png',
        'remove' => 'zone--minus.png',
    );
}

/**
 * Liste des alias. Collection d'objets {@link Alias}.
 */
class Aliases extends NodesCollection
{
    protected static $defaultProperties = array
    (
        '_lastid' => null,
    );

    protected static $validChildren = array('alias');

    protected static $labels = array
    (
    	'main' => 'Liste des alias',
    );

    protected static $icons = array
    (
        'image' => 'key--arrow.png',
    );
}

/**
 * Un alias. Collection d'objets {@link AliasIndex}.
 */
class Alias extends NodesCollection
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) de l'alias (non utilisé)
    	'_id' => null,

        // Nom de l'alias
        'name' => '',

        // Libellé de l'index
        'label' => '',

        // Description de l'index
        'description' => '',

        // Type d'index : 'probabilistic' ou 'boolean'
        'type' => array('probabilistic', 'boolean'),

        // Traduction de la propriété type en entier
        '_type' => null,
    );

    protected static $validChildren = array('aliasindex');

    protected static $labels = array
    (
    	'main' => 'Alias',
        'add' => "Nouvel alias",
        'remove' => "Supprimer l'alias %2", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'key.png',
        'add' => 'key--plus.png',
        'remove' => 'key--minus.png',
    );
}

/**
 * Un index dans un alias.
 */
class AliasIndex extends Node
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) du champ
        '_id' => null,

        // Nom de l'index
        'name' => '@index',
    );

    protected static $labels = array
    (
    	'main' => 'Index',
        'add' => "Ajouter un index à l'alias",
        'remove' => "Supprimer l'index %2 de l'alias", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'lightning.png',
        'add' => 'lightning--plus.png',
        'remove' => 'lightning--minus.png',
    );
}

/**
 * Liste des tables de lookup. Collection d'objets {@link LookupTable}.
 */
class LookupTables extends NodesCollection
{
    protected static $defaultProperties = array
    (
        '_lastid' => null,
    );
    protected static $validChildren = array('lookuptable');

    protected static $labels = array
    (
    	'main' => 'Tables de lookup',
    );

    protected static $icons = array
    (
        'image' => 'magnifier--arrow.png',
    );
}

/**
 * Une table de lookup. Collection d'objets {@link LookupTableField}.
 */
class LookupTable extends NodesCollection
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) de la table
    	'_id' => null,

        // Nom de la table
        'name' => '',

        // Libellé de l'index
        'label' => '',

        // Description de l'index
        'description' => '',

        // type de table : "simple" ou "inversée"
        'type' => array('simple'), // 'inverted' n'est plus utilisé

        // Traduction de type en entier
        // '_type'=>self::LOOKUP_SIMPLE,
    );

    protected static $validChildren = array('lookuptablefield');

    protected static $labels = array
    (
    	'main' => 'Table de lookup',
        'add' => "Nouvelle table de lookup",
        'remove' => "Supprimer la table de lookup", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'magnifier.png',
        'add' => 'magnifier--plus.png',
        'remove' => 'magnifier--minus.png',
    );
}

/**
 * Un champ dans un table de lookup.
 */
class LookupTableField extends Node
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) du champ
    	'_id' => null,

        // Nom du champ
        'name' => '@field',

        // Indice du premier article à prendre en compte (1-based)
        'startvalue' => 1,

        // Indice du dernier article à prendre en compte (0=jusqu'à la fin)
        'endvalue' => 0,

        // Position de début ou chaine délimitant le début de la valeur à ajouter à la table
        'start' => '',

        // Longueur ou chaine délimitant la fin de la valeur à ajouter à la table
        'end' => ''
    );

    protected static $labels = array
    (
    	'main' => 'Champ de table',
        'add' => "Ajouter un champ à la table",
        'remove' => "Supprimer le champ %2 de la table", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'zone.png',
        'add' => 'zone--plus.png',
        'remove' => 'zone--minus.png',
    );
}

/**
 * Liste des clés de tri. Collection d'objets {@link Sortkey}.
 */
class Sortkeys extends NodesCollection
{
    protected static $defaultProperties = array
    (
        '_lastid' => null,
    );

    protected static $validChildren = array('sortkey');

    protected static $labels = array
    (
    	'main' => 'Clés de tri',
    );

    protected static $icons = array
    (
        'image' => 'sort--arrow.png',
    );
}

/**
 * Une clé de tri. Collection d'objets {@link SortkeyField}.
 */
class Sortkey extends NodesCollection
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) de la clé de tri
    	'_id' => null,

        // Nom de la clé de tri
        'name' => '',

        // Libellé de l'index
        'label' => '',

        // Description de l'index
        'description' => '',

        // Type de la clé à créer ('string' ou 'number')
        'type' => array('string', 'number'),
    );

    protected static $validChildren = array('sortkeyfield');

    protected static $labels = array
    (
    	'main' => 'Clé de tri',
        'add' => "Nouvelle clé de tri",
        'remove' => "Supprimer la clé de tri %2", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'sort.png',
        'add' => 'sort--plus.png',
        'remove' => 'sort--minus.png',
    );
}

/**
 * Un champ dans une clé de tri.
 */
class SortkeyField extends Node
{
    protected static $defaultProperties = array
    (
        // Identifiant (numéro unique) du champ
        '_id' => null,

        // Nom du champ
        'name' => '@field',

        // Position de début ou chaine délimitant le début de la valeur à ajouter à la clé
        'start' => '',

        // Longueur ou chaine délimitant la fin de la valeur à ajouter à la clé
        'end' => '',

        // Longueur totale de la partie de clé (tronquée ou paddée à cette taille)
        'length' => 0,
    );

    protected static $labels = array
    (
    	'main' => 'Champ de clé',
        'add' => "Ajouter un champ à la clé",
        'remove' => "Supprimer le champ %2 de la clé", // %1=name, %2=type
    );

    protected static $icons = array
    (
        'image' => 'zone.png',
        'add' => 'zone--plus.png',
        'remove' => 'zone--minus.png',
    );
}


namespace fab;

/**
 * Représente un schéma.
 *
 *
 */
class Schema extends Schema\NodesCollection
{
    /**
     * Propriétés par défaut du schéma.
     *
     * @var array
     */
    protected static $defaultProperties = array
    (
        // Version du format. Initialisé dans le constructeur pour qu'on la voit dans le xml
        'version' => null, // lecture seule

        // Un libellé court décrivant la base
    	'label' => '',

        // Description, notes, historique des modifs...
        'description' => '',

        // Liste par défaut des mots-vides à ignorer lors de l'indexation
        'stopwords' => '',

        // Faut-il indexer les mots vides ?
        'indexstopwords' => true,

        // Date de création du schéma
        'creation' => null,

        // Date de dernière modification du schéma
        'lastupdate' => null,
    );


    /**
     * Liste des collections autorisées dans un schéma.
     *
     * @var array
     */
    protected static $validChildren = array('fields','indices','aliases','lookuptables','sortkeys');


    /**
    * Liste des collections présentes dans un schéma.
    *
    * @var array
    */
    protected static $defaultChildren = array('fields','indices','aliases','lookuptables','sortkeys');

    protected static $labels = array
    (
        'main' => 'Schéma',
        'add' => 'Nouvelle propriété',
        'remove' => 'Supprimer la propriété', // %1=name, %2=type
    );


    protected static $icons = array
    (
        'image' => 'gear.png',
        'add' => 'gear--plus.png',
        'remove' => 'gear--minus.png',
    );

    /**
     * Crée un nouveau noeud.
     *
     * Un noeud contient automatiquement toutes les propriétés par défaut définies
     * pour ce type de noeud et celles-ci apparaissent en premier.
     *
     * @param array $properties propriétés du noeud.
     */
    public function __construct(array $properties = array())
    {
        parent::__construct($properties);
        $this->set('version', 2);
    }


    /**
     * Crée un schéma depuis un source xml.
     *
     * @param string $xmlSource
     * @return Schema
     * @throws \Exception
     */
    public static function fromXml($xmlSource)
    {
        // Crée un document XML
        $xml=new \domDocument();
        $xml->preserveWhiteSpace = false;

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
        return self::_fromXml($xml->documentElement);
    }


    /**
     * Sérialise le schéma au format xml.
     *
     * @param true|false|int $indent
     * - false : aucune indentation, le xml généré est compact
     * - true : le xml est généré de façon lisible, avec une indentation de 4 espaces.
     * - x (int) : xml lisible, avec une indentation de x espaces.
     *
     * @return string
     */
    public function toXml($indent = false)
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        if ($indent === true) $indent = 4; else $indent=(int) $indent;
        if ($indent > 0)
        {
            $xml->setIndent(true);
            $xml->setIndentString(str_repeat(' ', $indent));
        }
        $xml->startDocument('1.0', 'utf-8', 'yes');

        $xml->startElement(Schema\NodesTypes::classToNodetype(get_class($this)));
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
            throw new \Exception('JSON invalide');

        return self::fromArray($array);
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
     * @return $this
     */
    public function getSchema()
    {
        return $this;
    }
}