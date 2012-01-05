<?php
/**
 * @package     fab
 * @subpackage  core
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe repr�sentant une collection dans laquelle chaque cl� est associ�e � une
 * ou plusieurs valeurs.
 *
 * Les cl�s du Multimap peuvent �tre des entiers ou des chaines. Elles doivent �tre
 * uniques et sont stock�es dans l'ordre dans lequel elles sont ins�r�es dans la
 * collection.
 *
 * Les donn�es associ�es aux cl�s peuvent �tre de n'importe quel type. Lorsque
 * plusieurs donn�es sont associ�es � une m�me cl�, celles-ci sont stock�es et
 * retourn�es � l'utilisateur sous la forme d'un tableau php.
 *
 *
 * <h2>Vue d'ensemble :</h2>
 *
 * La classe Multimap contient des m�thodes permettant :
 * - de cr�er et de dupliquer une collection : {@link __construct()}, {@link create()},
 *   {@link copy()} ;
 * - de d�finir les options de la collection : {@link compareMode()},
 *   {@link keyDelimiter()} ;
 * - d'ajouter des donn�es dans la collection : {@link add()}, {@link addMany()} ;
 * - de r�cup�rer les donn�es associ�es � une cl� : {@link get()} ;
 * - de modifier les donn�es d'une cl� : {@link set()} ;
 * - de supprimer une valeur, une cl� ou la totalit� de la collection : {@link clear()} ;
 * - d'obtenir des informations sur une collection : {@link count()}, {@link isEmpty()},
 *   {@link emptyValue()}, {@link has()} ;
 * - de filtrer les donn�es d'une collection : {@link keepOnly()}, {@link filter()} ;
 * - de parcourir ou de modifier les donn�es en utilisant un callback : {@link apply()},
 *   {@link run()} ;
 * - d'obtenir une repr�sentation de la collection : {@link __toString()},
 *   {@link toArray()}, {@link __toJson()}.
 *
 * La plupart des m�thodes retournent <code>$this</code> ce qui permet d'enchainer
 * plusieurs appels de m�thode en une seule �tape :
 *
 * <code>
 * $map->add('query', 'health')->set('max', 10)->clear('format');
 * </code>
 *
 *
 * <h2>Traitement des valeurs vides : </h2>
 * La classe Multimap supprime automatiquement les valeurs vides. Lorsqu'une donn�e
 * vide est ajout�e ou lorsqu'une donn�e existante est {@link apply() transform�e}
 * en une donn�e vide, celle-ci est automatiquement supprim�e. C'est la m�thode
 * {@link emptyValue()} qui d�finit ce qu'est une valeur vide. Par d�faut les
 * valeurs <code>null</code>, <code>''</code> (chaine vide) et <code>array()</code>
 * (tableau ne contenant aucun �l�ment) sont consid�r�es comme des donn�es vides,
 * mais les classes descendantes peuvent surcharger cette m�thode si n�cessaire.
 *
 * Exemple :
 * <code>
 * $map->add('item', ''); // ne fait rien
 * $map->add('item', 'aaa')->apply('trim', 'item', 'a'); // la cl� item est supprim�e apr�s apply
 * </code>
 *
 * Aucun d�doublonnage n'est effectu� sur les donn�es associ�es � une cl� :
 * <code>
 * $map->add('item', 'data')->add('item', 'data')->get('item'); // array('data','data')
 * </code>
 *
 * <h2>D�signation des cl�s : </h2>
 * Plusieurs m�thodes acceptent en param�tre un argument <code>$key</code> qui permet
 * d'indiquer la ou les cl�s de la collection auxquelles le traitement s'applique.
 *
 * A chaque fois, vous pouvez indiquer, au choix :
 * - le nom d'une cl� unique,
 * - un tableau contenant les noms des cl�s,
 * - plusieurs cl�s s�par�es par le {@link keyDelimiter() d�limiteur de cl�} d�finit
 *   pour la collection (par d�faut il s'agit de la virgule),
 * - la chaine <code>'*'</code> pour d�signer la totalit� des cl�s existantes.
 *
 * Exemples d'utilisation :
 *
 * <code>
 * $map->add('key1,key2', 'data'); // ajoute la donn�e 'data' dans les cl�s 'key1' et 'key2'
 * $map->add(array('key1','key2'), 'data'); // idem
 * $map->clear('key1,key2'); // supprime les cl�s 'key1' et 'key2'
 * $map->get('key1,key2'); // retourne la premi�re des cl�s qui existent
 * $map->keepOnly('key1,key2'); // supprime tout sauf 'key1' et 'key2'
 * $map->apply('trim', 'key1,key2'); // applique la fonction trim() au donn�es des cl�s 'key1' et 'key2'
 * $map->apply('trim', '*'); // applique la fonction trim() � toutes les donn�es
 * $map->has('*', 'N/A'); // la donn�e 'N/A' est-elle pr�sente dans l'une des cl�s ?
 * </code>
 *
 * <h2>Comparaison des donn�es :</h2>
 *
 * Les m�thodes {@link has()} et {@link clear()} permettent de tester l'existence ou
 * de supprimer certaines donn�es. Pour fonctionner, elles utilisent une fonction de
 * comparaison qui testent si l'argument pass� � la fonction est �gal � la donn�e
 * test�e.
 *
 * Par d�faut, la collection utilise la m�thode de comparaison {@link CMP_EQUAL} qui
 * effectue une comparaison siple, non typ�e, de ses arguments (<code>==</code>).
 *
 * Vous pouvez changer la m�thode de comparaison utilis�e avec la m�thode
 * {@link compareMode()} en lui passant en param�tre l'une des constantes
 * {@link CMP_EQUAL CMP_*} ou le nom de votre propre fonction de comparaison.
 *
 * {@link has()} et {@link clear()} acceptent �galement un param�tre
 * <code>$compareMode</code> qui permet d'utiliser ponctuellement une autre m�thode de
 * comparaison que celle d�finie par d�faut pour la collection.
 *
 * <h2>Utilisation d'un multimap comme un objet :</h2>
 *
 * Multimap d�finit les m�thodes magiques __get(), __set(), __isset() et __unset() qui vous
 * permettent de l'utiliser comme un objet et de manipuler les cl�s comme s'il s'agissait de
 * propri�t�s :
 *
 * <code>
 * $map = new Multimap();
 * $map->key = $value; // appelle {@link __set()}
 * echo $map->key; // appelle {@link __get()}
 * if (isset($map->key)) // appelle {@link __isset()}
 *     unset($map->key); // appelle {@link __unset()}
 * </code>
 *
 * <h2>Utilisation comme un tableau :</h2>
 *
 * Multimap impl�mente les interfaces ArrayObject, IteratorAggregate et Countable, qui vous
 * permettent de l'utiliser comme un tableau php standard :
 *
 * <code>
 * $map = new Multimap();
 * $map['key'] = $value; // appelle {@link offsetSet()}
 * echo $map['key']; // appelle {@link offsetGet()}
 * if (isset($map['key'])) // appelle {@link offsetExists()}
 *     unset($map['key']); // appelle {@link offsetUnset()}
 * echo count($map); // appelle {@link count()}
 * foreach($map as $key=>$value) // appelle {@link getIterator()}
 *    echo "$key=$value\n";
 * </code>
 *
 * Le d�limiteur de cl� est pris en compte lorsque vous utilisez la syntaxe tableau :
 * <code>
 * $map['a,b,c'] = 'item'; // 'a' = 'b' = 'c' = 'item'
 * echo $map['e,f,a']; // retourne la premi�re cl� renseign�e : 'a' => 'item'
 * </code>
 *
 * @package     fab
 * @subpackage  core
 */
class Multimap implements Countable, ArrayAccess, IteratorAggregate
{

    // --------------------------------------------------------------------------------
    // Propri�t�s
    // --------------------------------------------------------------------------------

    /**
     * Les donn�es pr�sentes dans la collection.
     *
     * @var array
     */
    protected $data = array();


    /**
     * Caract�re utilis� comme d�limiteur pour s�parer les noms de cl�s.
     *
     * @var string
     */
    protected $keyDelimiter = ',';


    // --------------------------------------------------------------------------------
    // Modes de comparaison
    // --------------------------------------------------------------------------------


    /**
     * {@link compareMode() CompareMode} : teste si les valeurs sont �gales (==).
     *
     * @var int
     */
    const CMP_EQUAL = 1;

    /**
     * {@link compareMode() CompareMode} : teste si les valeurs sont identiques (===).
     *
     * @var int
     */
    const CMP_IDENTICAL = 2;

    /**
     * {@link compareMode() CompareMode} : convertit les valeurs en chaines puis
     * teste si elles sont �gales en ignorant la casse des caract�res.
     * strcasecmp().
     *
     * @var int
     */
    const CMP_IGNORE_CASE = 3;

    /**
     * {@link compareMode() CompareMode} : convertit les valeurs en chaines, applique
     * trim() et teste si elles sont �gales.
     *
     * @var int
     */
    const CMP_TRIM = 4;

    /**
     * {@link compareMode() CompareMode} : convertit les valeurs en chaines, applique
     * tokenize() et teste si elles sont �gales
     *
     * @var int
     */
    const CMP_TOKENIZE = 5;


    /**
     * Le mode de comparaison en cours.
     *
     * @var int une des constantes CMP_XXX
     */
    protected $compareMode;


    /**
     * Le callback correspondant au mode de comparaison en cours.
     *
     * @var callback
     */
    protected $compare;


    // --------------------------------------------------------------------------------
    // Cr�ation, construction, clonage
    // --------------------------------------------------------------------------------

    /**
     * M�thode statique permettant de cr�er une nouvelle collection.
     *
     * Php ne permet pas de chainer des m�thodes apr�s un appel � new :
     * <code>$map = new Multimap()->add('max', 10);</code> g�n�re une erreur.
     *
     * La m�thode create permet de contourner le probl�me en �crivant :
     * <code>$map = Multimap::create($_GET, $_POST)->add('max', 10);</code>
     *
     * @param mixed $data ... optionnel, un ou plusieurs tableaux (ou objets it�rables)
     * repr�sentant les donn�es initiales de la collection. Chaque param�tre est ajout�
     * au multimap en utilisant la m�thode {@link add()}.
     *
     * @return $this
     */
    public static function create($data = null)
    {
        $map = new self();

        $args = func_get_args();
        foreach($args as $arg)
            $map->addMany($arg);

        return $map;
    }


    /**
     * Cr�e un multimap.
     *
     * @param mixed $data ... optionnel, un ou plusieurs tableaux (ou objets it�rables)
     * repr�sentant les donn�es initiales de la collection. Chaque param�tre est ajout�
     * au multimap en utilisant la m�thode {@link addMany()}.
     */
    public function __construct($data = null)
    {
        $this->compareMode(self::CMP_EQUAL);
        $args = func_get_args();
        foreach($args as $arg)
            $this->addMany($arg);
    }


    /**
     * Clone la collection en cours.
     *
     * @return $this
     */
    public function copy()
    {
        return clone $this;
    }


    // --------------------------------------------------------------------------------
    // Fonctions de comparaison
    // --------------------------------------------------------------------------------


    /**
     * Retourne ou modifie le mode de comparaison par d�faut utilis� pour tester si deux
     * valeurs sont �gales.
     *
     * Le mode de comparaison est utilis� par la m�thode {@link has()} et {@link clear()}pour
     * tester si une cl� contient une valeur donn�e.
     *
     * @param null|int|callback $mode
     * @return int|callback|$this retourne le mode de comparaison en cours si $mode vaut null,
     * <code>$this</code> sinon.
     */
    public function compareMode($mode = null)
    {
        if (is_null($mode)) return $this->compareMode ===0 ? $this->compare : $this->compareMode;

        switch ($mode)
        {
            case self::CMP_EQUAL:
                $callback = array($this, 'cmpEqual');
                break;

            case self::CMP_IDENTICAL:
                $callback = array($this, 'cmpIdentical');
                break;

            case self::CMP_IGNORE_CASE:
                $callback = array($this, 'cmpIgnoreCase');
                break;

            case self::CMP_TRIM:
                $callback = array($this, 'cmpTrim');
                break;

            case self::CMP_TOKENIZE:
                $callback = array($this, 'cmpTokenize');
                break;

            default: // $mode est un callback
                if (! is_callable($mode)) throw new Exception('CompareMode : Callback invalide.');
                $callback = $mode;
                $mode = 0;
        }
        $this->compareMode = $mode;
        $this->compare = $callback;

        return $this;
    }


    /**
     * Compare deux valeurs et retourne vrai si elles sont �gales.
     *
     * @param mixed $a premi�re valeur � comparer.
     * @param mixed $b seconde valeur � comparer
     *
     * @param int|callback $compareMode mode de comparaison � utiliser. Lorsque $compareMode
     * vaut <code>null</code>, le {@link compareMode() mode de comparaison par d�faut}
     * d�finit pour la collection est utilis�.
     *
     * @return bool <code>true</code> si les deux valeurs sont �gales, <code>false</code>
     * sinon.
     */
    protected function compare($a, $b, $compareMode = null)
    {
        if (! is_null($compareMode))
        {
            $oldMode = $this->compareMode();
            $this->compareMode($compareMode);
        }
        $result = call_user_func($this->compare, $a, $b);
        if (! is_null($compareMode)) $oldMode = $this->compareMode($compareMode);
        return $result;
    }


    /**
     * Chaine source utilis�e par {@link tokenize()} et par {@link cmpIgnoreCase()}.
     *
     * @var string
     */
    private static $charFroms = '\'-ABCDEFGHIJKLMNOPQRSTUVWXYZ���������������������������������������������������������������';


    /**
     * Chaine r�sultat utilis�e par {@link tokenize()} et par {@link cmpIgnoreCase()}.
     *
     * @var string
     */
    private static $charTo    =  '  abcdefghijklmnopqrstuvwxyz��aaaaaa�ceeeeiiiidnooooo�uuuuytsaaaaaa�ceeeeiiiidnooooouuuuyty';


    /**
     * Retourne la version tokenis�e du texte pass� en param�tre.
     *
     * @param string $text
     * @return string
     */
    protected static function tokenize($text)
    {
        // Convertit les caract�res
        $text = strtr($text, self::$charFroms, self::$charTo);

        // G�re les lettres doubles
        $text = strtr($text, array('�'=>'ae', '�'=>'oe'));

        // Retourne un tableau contenant tous les mots pr�sents
        return implode(' ', str_word_count($text, 1, '0123456789@_'));
    }


    /**
     * Callback utilis� pour {@link CMP_EQUAL}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpEqual($a, $b)
    {
        return $a == $b;
    }


    /**
     * Callback utilis� pour {@link CMP_IDENTICAL}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpIdentical($a, $b)
    {
        return $a === $b;
    }


    /**
     * Callback utilis� pour {@link CMP_TRIM}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpTrim($a, $b)
    {
        return trim($a) === trim($b);
    }


    /**
     * Callback utilis� pour {@link CMP_IGNORE_CASE}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpIgnoreCase($a, $b)
    {
        return
            trim(strtr($a, self::$charFroms, self::$charTo))
            ===
            trim(strtr($b, self::$charFroms, self::$charTo));
    }


    /**
     * Callback utilis� pour {@link CMP_TOKENIZE}.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function cmpTokenize($a, $b)
    {
        return self::tokenize($a) === self::tokenize($b);
    }


    // --------------------------------------------------------------------------------
    // D�limiteur de cl�
    // --------------------------------------------------------------------------------


    /**
     * Retourne ou modifie le d�limiteur utilis� pour s�parer les noms de cl�s.
     *
     * Par d�faut, le d�limiteur utilis� est la virgule.
     * Toutes les m�thodes qui acceptent une cl� en param�tre utilisent ce d�limiteur.
     *
     * Exemple :
     * <code>
     * // La ligne :
     * $map->add('cl�1,cl�2', 'value');
     *
     * // Est �quivalente � :
     * $map->add('cl�1', 'value')->add('cl�2', 'value');
     * </code>
     *
     * @param null|string $delimiter le nouveau d�limiteur � utiliser
     *
     * @return string|$this retourne le d�limiteur en cours si <code>$delimiter</code>
     * vaut null, <code>$this</code> sinon.
     */
    public function keyDelimiter($delimiter = null)
    {
        if (is_null($delimiter)) return $this->keyDelimiter;
        $this->keyDelimiter = trim($delimiter);
        return $this;
    }


    // --------------------------------------------------------------------------------
    // Gestion des valeurs vides
    // --------------------------------------------------------------------------------


    /**
     * Indique si la valeur pass�e en param�tre est consid�r�e comme vide par le multimap.
     *
     * Les valeurs vides sont automatiquement supprim�es de la liste des valeurs associ�es
     * � une cl�.
     *
     * Par d�faut, la m�thode retourne <code>true</code> pour les valeurs null,
     * '' (chaine vide), array() et <code>false</code> pour toutes les autres.
     *
     * Les classes descendantes peuvent surcharger cette m�thode pour changer la s�mantique
     * du mot "vide".
     *
     * @param mixed $value la valeur � tester.
     *
     * @return bool true si la valeur est vide.
     */
    public function emptyValue($value)
    {
        return $value === '' || count($value) === 0;
    }


    // --------------------------------------------------------------------------------
    // Ajout, modification, r�cup�ration, suppression de donn�es
    // --------------------------------------------------------------------------------


    /**
     * Analyse le param�tre <code>$key</code> pass� � une m�thode et retourne un
     * tableau contenant les noms r��ls des cl�s � utiliser.
     *
     * @param mixed $key la chaine ou le tableau � analyser.
     * @return array un tableau contenant les noms des cl�s.
     */
    protected function parseKey($key)
    {
        if (is_string($key)) $key = trim($key);

        if (is_null($key) || $key === '' || $key === array() || $key === '*')
            return array_keys($this->data);

        if (is_array($key)) return $key;

        return array_map('trim', explode($this->keyDelimiter, $key));
    }


    /**
     * Ajoute une valeur unique � une cl� de la collection.
     *
     * La m�thode add() ajoute une nouvelle valeur dans les donn�es associ�es aux
     * cl�s indiqu�es. Pour remplacer compl�tement les donn�es associ�es aux cl�s,
     * utilisez la m�thode {@link set()}.
     *
     * Les donn�es {@link emptyValue() vides} sont ignor�es.
     *
     * @param scalar $key les cl�s auxquelles ajouter la valeur.
     * @param mixed $value la valeur � ajouter.
     * @return $this
     */
    public function add($key, $value = null)
    {
        if (! (is_string($key) || is_int($key)))
            throw new Exception('Cl� incorrecte');

        if ($this->emptyValue($value)) return $this;

        foreach($this->parseKey($key) as $key)
        {
            if (array_key_exists($key, $this->data))
                $this->data[$key][] = $value;
            else
                $this->data[$key] = array($value);
        }

        return $this;

    }


    /**
     * Ajoute plusieurs cl�s � la collection ou plusieurs valeurs � une cl�.
     *
     *
     * La m�thode <code>addMany()</code> peut �tre appell�e avec un ou plusieurs
     * param�tres.
     *
     * Lorsqu'elle est appell�e avec un seul param�tre, celui-ci doit �tre tableau
     * ou un objet it�rable contenant des cl�s et des valeurs qui seront ajout�es
     * � la collection.
     *
     * Exemples :
     *
     * - add($array) : ajoute les donn�es du tableau pass� en param�tre
     *   Equivalent � : foreach(array as key=>value) add(key, value)
     *
     * - add($multimap) : ajoute les donn�es de la collection pass�e en param�tre
     *   Equivalent � : foreach(Multimap->toArray() as key=>value) add(key, value)
     *
     * - add($object) : ajoute les propri�t�s de l'objet pass� en param�tre
     *   Equivalent � : foreach((array)$object as key=>value) add(key, value)
     *
     * Lorsque <code>addMany()</code> est appell�e avec plusieurs param�tres, le
     * premier param�tre d�signe la ou les cl�s auxquelles il faut ajouter des
     * valeurs et les autres param�tres doivent �tre des tableaux ou des objets
     * it�rables contenant des donn�es qui seront ajout�es aux cl�s sp�cifi�es.
     *
     * Exemples :
     *
     * - add(key, array) : ajoute toutes les donn�es pr�sentes de array � la cl� key.
     *   Equivalent de : foreach(array as value) add(key, value)
     *   Les cl�s de array sont ignor�es.
     *
     * - add(key, multimap) : ajoute toutes les valeurs pr�sentes dans le multimap comme valeurs
     *   associ�es � la cl� key.
     *   Equivalent de : foreach(Multimap->toArray() as value) add(key, value)
     *   Les cl�s existantes du multimap sont ignor�es.
     *
     * - Tout autre type de valeur g�n�re une exception. Une exception est �galement g�n�r�e si
     *   la cl� pass�e en param�tre n'est pas un scalaire.
     *
     * @param mixed $key (optionnel) la ou les cl�s � modifier.
     * @param array|object|Traversable $data un ou plusieurs tableaux contenant les donn�es ou les
     * cl�s � ajouter.
     *
     * @return this
     */
    public function addMany($key, $data=null)
    {
        // Premier cas : une cl� a �t� indiqu�e
        if (is_string($key) || is_int($key))
        {
            $args = func_get_args();
            array_shift($args);
            foreach($args as $data)
            {
                if (! (is_array($data) || $data instanceof Traversable || is_object($data)))
                    throw new BadMethodCallException('Tableau ou objet it�rable attendu.');

                if ($data instanceof Multimap)
                    $data = $data->toArray();

                foreach($data as $value)
                    $this->add($key, $value);
            }
        }

        // Second cas : pas de cl�, que des tableaux
        else
        {
            $args = func_get_args();
            foreach($args as $data)
            {
                if (! (is_array($data) || $data instanceof Traversable || is_object($data)))
                    throw new BadMethodCallException('Tableau ou objet it�rable attendu.');

                if ($data instanceof Multimap)
                {
                    foreach($data->toArray() as $key=>$value)
                        if (is_array($value))
                            $this->addMany($key, $value);
                        else
                            $this->add($key, $value);
                }
                else
                    foreach($data as $key=>$value)
                        $this->add($key, $value);
            }

        }

        return $this;
    }



    /**
     * Retourne les donn�es associ�es � la cl� indiqu�e ou la valeur par d�faut
     * si la cl� demand�e ne figure pas dans la collection.
     *
     * Exemples :
     * <code>
     * $map->get('key'); // retourne la donn�e associ�e � 'key' ou null si elle n'existe pas
     * $map->get('key', 'n/a'); // retourne la donn�e associ�e � 'key' ou 'n/a' si elle n'existe pas
     * $map->get('key1,key2'); // retourne le contenu de la premi�re cl� non-vide ou null
     * </code>
     *
     * get est similaire � {@link __get()} mais permet d'indiquer une valeur par
     * d�faut (par exemple <code>$map->get('item', 'abc')</code>)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default=null)
    {
        foreach($this->parseKey($key) as $key)
            if (isset($this->data[$key]))
                return count($this->data[$key])===1 ? reset($this->data[$key]) : $this->data[$key];

        return $default;
    }


    /**
     * Remplace les donn�es associ�es � une cl�.
     *
     * Si la valeur indiqu�e est {@link emptyValue() vide}, la cl� est supprim�e.
     *
     * Exemples :
     * <code>
     * $map->set('item', 12); // remplace le contenu existant de 'item' par la valeur 12
     * $map->set('item'); // Equivalent � $map->clear('item')
     * $map->set('item', array(1,2)); // remplace le contenu existant de 'item' par la valeur array(1,2)
     * $map->set('key1,key2', 12); // Initialise les cl�s key1 et key2 � 12
     * </code>
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function set($key, $value = null)
    {
        if ($this->emptyValue($value)) return $this->clear($key);

        foreach($this->parseKey($key) as $key)
            $this->data[$key] = is_array($value) ? $value : array($value);

        return $this;
    }


    /**
     * Supprime des cl�s ou des donn�es de la collection.
     *
     * La m�thode <code>clear()</code> permet de supprimer :
     * - toutes les cl�s qui figure dans la collection : <code>$map->clear();</code>
     * - une cl� unique : <code>$map->clear('max');</code>
     * - plusieurs cl�s : <code>$map->clear('start,max,format');</code>
     * - une donn�e particuli�re associ�e � une ou plusieurs cl�s :
     * <code>$map->clear('TypDoc,TypDocB', 'Article');</code>
     *
     * @param mixed $key le ou les cl�s � supprimer.
     * @param mixed $value la valeur � supprimer
     * @param mixed $compareMode m�thode de comparaison � utiliser pour comparer $value aux
     * donn�es de la cl�.
     *
     * @return $this
     */
    public function clear($key=null, $value=null, $compareMode = null)
    {
        // Aucune cl� indiqu�e, on vide toute la collection
        if (is_null($key))
        {
            $this->data = array();
            return $this;
        }

        // Aucune valeur indiqu�e : supprime toutes les cl�s indiqu�es
        if (is_null($value))
        {
            foreach($this->parseKey($key) as $key)
                unset($this->data[$key]);

            return $this;
        }

        // Supprime les valeurs indiqu�es dans les cl�s indiqu�es
        foreach($this->parseKey($key) as $key)
        {
            if (! array_key_exists($key, $this->data)) continue;

            $v = $this->data[$key];

            foreach($this->data[$key] as $k => $v)
                if ($this->compare($v, $value, $compareMode)) unset($this->data[$key][$k]);

            if (count($this->data[$key]) === 0)
                unset($this->data[$key]);
        }

        return $this;
    }


    /**
     * Transf�re le contenu d'une ou plusieurs cl�s vers d'autres cl�s.
     *
     * La m�thode <code>move()</code> permet de d�placer, de concat�ner ou de dupliquer des champs.
     * Le contenu existant des cl�s destination est �cras�.
     *
     * Exemples :
     * <code>
     * // Transf�re TITFRAN Dans TitOrigA
     * $map->move('TITFRAN', 'TitOrigA');
     *
     * // Transf�re tous les champ mots-cl�s dans le champ MotsCles
     * $map->move('MOTSCLE1,MOTSCLE2,MOTSCLE3,MOTSCLE4,PERIODE', 'MotsCles');
     *
     * // Recopie MotsCles dans NouvDesc
     * $map->move('MotsCles', 'MotsCles,NouvDesc');
     *
     * // Ajoute NouvDesc � MotsCles
     * $map->move('MotsCles,NouvDesc', 'MotsCles');
     * </code>
     *
     * @param string $from une ou plusieurs cl�s sources.
     * @param string $to une ou plusieurs cl�s destination.
     * @return $this
     */
    public function move($from, $to)
    {
        // R�cup�re toutes les donn�es
        $data = $this->getAll($from);

        // Vide les cl�s de $from qui ne figurent pas dans $to
        // On pourrait faire directement clear($from) mais dans ce cas, cela changerait l'ordre
        //  des cl�s pour un appel comme move('a,b', 'a')
        $from = $this->parseKey($from);
        $to = $this->parseKey($to);

        $diff = array_diff($from, $to);
        if ($diff) $this->clear($diff);

        // Aucune donn�e : supprime les cl�s destination
        if (count($data)===0)
            return $this->clear($to);

        // Stocke les donn�es dans les cl�s destination
        foreach($to as $to)
            $this->data[$to] = $data;

        return $this;
    }

    /**
     * @return array
     *
     * @param $key
     */
    public function getAll($key)
    {
        $args = func_get_args();
        $t = array();
        foreach($args as $key)
        {
            foreach($this->parseKey($key) as $key)
            {
                if (! isset($this->data[$key])) continue;
                $t = array_merge($t, $this->data[$key]);
            }
        }
        return $t;
    }

    /**
     * Supprime toutes les cl�s sauf celles indiqu�es.
     *
     * Exemple :
     * <code>
     * $map->keepOnly('start,max', 'format'); // supprime tous sauf start, max et format
     * </code>
     *
     * @param mixed $key un ou plusieurs param�tres indiquant le ou les noms des cl�s
     * � conserver.
     *
     * @return $this
     */
    public function keepOnly($key)
    {
        $keys = array();
        $args = func_get_args();
        foreach ($args as & $key)
            $keys += array_flip($this->parseKey($key));

        $this->data = array_intersect_key($this->data, $keys);

        return $this;
    }

    // --------------------------------------------------------------------------------
    // Information
    // --------------------------------------------------------------------------------

    /**
     * Indique si la collection ou la cl� indiqu�e est vide.
     *
     * Lorsque isEmpty() est appell�e sans param�tres, la m�thode retourne true si la
     * collection est vide.
     *
     * Si $key est indiqu�e, la m�thode retourne true si aucune des cl�s indiqu�es
     * n'existe.
     *
     * Exemples :
     * <code>
     * Multimap::create()->isEmpty(); // aucun �l�ment dans la collection retourne true
     *
     * $map = Multimap::create(array('a'=>1, 'z'=>26));
     * $map->isEmpty('a'); // false
     * $map->isEmpty('b'); // true
     * $map->isEmpty('p,z'); // false
     * $map->isEmpty('p,q,r,s'); // true
     * $map->isEmpty('*'); // identique � $map->isEmpty() : false
     * </code>
     *
     * @param mixed $key la ou les cl�s � tester.
     *
     * @return bool
     */
    public function isEmpty($key = null)
    {
        if (is_null($key))
            return count($this->data) === 0;

        foreach ($this->parseKey($key) as $key)
            if (array_key_exists($key, $this->data)) return false;

        return true;
    }


    /**
     * D�termine si la collection contient la cl� ou la valeur indiqu�es.
     *
     * Lorsque has() est appell�e avec un seul param�tre, la m�thode retourne true
     * si la collection contient au moins l'une des cl�s indiqu�es.
     *
     * Lorsque has() est appell�e avec une cl� et une valeur, la m�thode retourne
     * true si au moins l'un des cl�s indiqu�es contient la valeur indiqu�e.
     *
     * Exemples :
     * <code>
     * $map = Multimap::create(array('a'=>'A', 'b' => array('BB','BC')));
     * $map->has('a'); // true
     * $map->has('c'); // false
     * $map->has('b', 'BC'); // true
     * $map->has('b', 'BD'); // false
     * </code>
     *
     * @param mixed $key la ou les cl�s recherch�es.
     * @param mixed $value optionnel, la ou les valeurs � tester.
     * @param mixed $compareMode m�thode de comparaison � utiliser pour comparer $value aux
     * donn�es de la cl�.
     *
     * @return bool
     */
    public function has($key, $value=null, $compareMode = null)
    {
        foreach ($this->parseKey($key) as $key)
        {
            if (! array_key_exists($key, $this->data)) continue;
            if (is_null($value)) return true;
            foreach ($this->data[$key] as $v)
                foreach((array)$value as $item)
                    if ($this->compare($v, $item, $compareMode)) return true;
        }
        return false;
    }


    // --------------------------------------------------------------------------------
    // Conversion
    // --------------------------------------------------------------------------------


    /**
     * Retourne une repr�sentation textuelle de la collection.
     *
     * __toString est une m�thode magique de php qui est appell�e lorsque PHP
     * a besoin de convertir un objet en chaine de caract�res.
     *
     * @return string La m�thode retourne une chaine qui contient le nom de la classe,
     * le nombre d'�l�ments dans la collection et un var_export() des donn�es.
     */
    public function __toString()
    {
        $cli = php_sapi_name()=='cli';

        ob_start();
        echo $cli ? "\n" : '<pre>';

        echo get_class($this);

        if ($this->isEmpty())
            echo " vide\n";
        else
        {
            echo  ' : ' , count($this->data) , " item(s) = \n";
            foreach ($this->data as $key => $data)
            {
                echo $key, "\n";
                if (count($data) === 1)
                {
                    $data = reset($data);
                    echo '  ', var_export($data, true), "\n";
                }
                else
                {
                    foreach($data as $i=>$value)
                        echo '  ', $i, '. ', var_export($value, true), "\n";
                }
            }
        }

        echo $cli ? "\n" : '</pre>';
        return ob_get_clean();
    }


    /**
     * Retourne un tableau contenant les donn�es pr�sentes dans la collection.
     *
     * @return array
     */
    public function toArray()
    {
        $result = $this->data;
        foreach ($result as & $data)
            if (count($data)===1) $data = reset($data);

        return $result;
    }


    /**
     * Retourne une repr�sentation JSON des donn�es de la collection.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }


    // --------------------------------------------------------------------------------
    // Parcourt et application de callbacks
    // --------------------------------------------------------------------------------

    /**
     * Applique un callback aux donn�es qui figurent dans une ou plusieurs cl�s.
     *
     * La m�thode apply() permet d'appliquer un callback (fonction, m�thode, closure...) � toutes
     * les donn�es associ�es � une ou plusieurs des cl�s de la collection.
     *
     * Exemples d'utilisation :
     * <code>
     * // Faire un trim sur tous les champs et sur tous les articles
     * $map->apply('trim');
     *
     * // Transformer des dates en format "aaa-mm-jj" en format Bdsp
     * $map->apply('strtr', 'DatEdit,DatOrig', '-', '/'); // 2011-02-02 -> 2011/02/02
     *
     * // Supprimer la mention "pp." qui figure au d�but d'une pagination
     * $map->apply('pregReplace', 'PageColl', '~p+\.?\s*(\d+)-(\d+)~', '$1-$2')
     * </code>
     *
     * @param callback $callback le nom du callback � appeller pour chacune des valeurs associ�es
     * aux cl�s indiqu�es dans $key. Il peut s'agir du nom d'une m�thode de la classe en cours,
     * du nom d'une fonction globale, d'un tableau ou d'une closure.
     *
     * Le callback recevra en param�tres la valeur � transformer et les �ventuels arguments
     * suppl�mentaires pass�s � apply(). Il doit retourner la valeur modifi�e.
     *
     * Le callback doit avoir la signature suivante :
     * <code>protected function callback(mixed $value) returns string</code>
     *
     * ou, si vous utilisez les arguments optionnels :
     * <code>protected function callback(mixed $value, $arg1, ...) returns string</code>
     *
     * @param mixed $key la ou les cl�s pour lesquelles le callback sera appell�.
     *
     * @param mixed $args ... optionnel, des argument suppl�mentaires � passer au callback.
     *
     * @return $this
     */
    public function apply($callback, $key=null, $args=null)
    {
        // D�termine si le callback est une m�thode de la classe ou une fonction globale
        if (is_string($callback) && method_exists($this, $callback))
            $callback = array($this, $callback);

        if (! is_callable($callback))
            throw new Exception('Callback non trouv� : ' . var_export($callback, true));

        // D�termine les arguments � passer au callback
        $args = func_get_args();
        $args = array_slice($args, 1);

        // Transforme tous les champs
        foreach($this->parseKey($key) as $key)
        {
            if (! isset($this->data[$key])) continue;

            foreach($this->data[$key] as $i => & $value)
            {
                $args[0] = $value;
                if ($this->emptyValue($value = call_user_func_array($callback, $args)))
                    unset($this->data[$key][$i]);
            }
            if (count($this->data[$key]) === 0)
                unset($this->data[$key]);
        }
        return $this;
    }


    /**
     * Ex�cute un callback sur les donn�es qui figurent dans une ou plusieurs cl�s.
     *
     * La m�thode run() permet d'ex�cuter un callback (fonction, m�thode, closure...)
     * pour toutes les donn�es associ�es � une ou plusieurs des cl�s de la collection.
     *
     * Exemple d'utilisation :
     * <code>
     * $map->run('dump', '*', "%s = %s\n");
     *
     * function dump($key, $value, $format)
     * {
     *     printf($format, $key, $value);
     * }
     * </code>
     *
     * @param callback $callback le nom du callback � appeller pour chacune des valeurs
     * associ�es aux cl�s indiqu�es dans $key. Il peut s'agir du nom d'une m�thode de la
     * classe en cours, du nom d'une fonction globale, d'un tableau ou d'une closure.
     *
     * Le callback recevra en param�tres :
     * - la cl� en cours,
     * - la valeur,
     * - les �ventuels arguments suppl�mentaires pass�s run().
     *
     * Le callback doit avoir la signature suivante :
     * protected function callback(scalar $key, mixed $value) returns boolean
     *
     * ou, si vous utilisez les arguments optionnels :
     * protected function callback(scalar $key, mixed $value, ...) returns boolean
     *
     * Si le callback retourne false, le parcourt des cl�s est interrompu.
     *
     * @param mixed $key la ou les cl�s pour lesquelles le callback sera appell�.
     *
     * @param mixed $args ... optionnel, des argument suppl�mentaires � passer au callback.
     *
     * @return $this
     */
    public function run($callback, $key=null, $args=null)
    {
        // D�termine si le callback est une m�thode de la classe ou une fonction globale
        if (is_string($callback) && method_exists($this, $callback))
            $callback = array($this, $callback);

        if (! is_callable($callback))
            throw new Exception('Callback non trouv� : ' . var_export($callback, true));

        // D�termine les arguments � passer au callback
        $args = func_get_args();

        // Parcourt toutes les cl�s
        foreach($this->parseKey($key) as $key)
        {
            if (! isset($this->data[$key])) continue;

            $args[0] = $key;

            foreach($this->data[$key] as & $value)
            {
                $args[1] = $value;
                if (false === call_user_func_array($callback, $args)) break;
            }
        }
        return $this;
    }


    /**
     * Filtre les donn�es et ne conserve qui celles qui passent le filtre indiqu�.
     *
     * La m�thode filter() permet d'ex�cuter un callback (fonction, m�thode, closure...)
     * pour toutes les donn�es associ�es � une ou plusieurs des cl�s de la collection.
     *
     * Seules les donn�es pour lesquelles le filtre retourne <code>true</code> sont
     * conserv�es dans la collection et la m�thode retourne un tableau contenant les
     * donn�es supprim�es.
     *
     * Exemple d'utilisation :
     * <code>
     * // ne conserve que les entiers et retourne un tableau avec toutes les cl�s qui contenaient
     * // autre chose qu'un entier.
     * $bad = $map->filter('is_int');
     * </code>
     *
     * @param callback $callback le nom du callback � appeller pour chacune des valeurs
     * associ�es aux cl�s indiqu�es dans $key. Il peut s'agir du nom d'une m�thode de la
     * classe en cours, du nom d'une fonction globale, d'un tableau ou d'une closure.
     *
     * Le callback recevra en param�tres :
     * - la valeur,
     * - la cl� en cours,
     * - les �ventuels arguments suppl�mentaires pass�s � filter().
     *
     * Le callback doit avoir la signature suivante :
     * protected function callback(mixed $value, scalar $key) returns boolean
     *
     * ou, si vous utilisez les arguments optionnels :
     * protected function callback(mixed $value, scalar $key, ...) returns boolean
     *
     * @param mixed $key la ou les cl�s pour lesquelles le callback sera appell�.
     *
     * @param mixed $args ... optionnel, des argument suppl�mentaires � passer au filtre.
     *
     * @return array
     */
    public function filter($callback, $key=null, $args=null)
    {
        // D�termine si le callback est une m�thode de la classe ou une fonction globale
        if (is_string($callback) && method_exists($this, $callback))
            $callback = array($this, $callback);

        if (! is_callable($callback))
            throw new Exception('Callback non trouv� : ' . var_export($callback, true));

        // D�termine les arguments � passer au callback
        $args = func_get_args();

        // Parcourt toutes les cl�s
        $result = array();
        foreach($this->parseKey($key) as $key)
        {
            if (! isset($this->data[$key])) continue;

            $args[1] = $key;
            foreach($this->data[$key] as $i=>$value)
            {
                $args[0] = $value;
                if (! call_user_func_array($callback, $args))
                {
                    $result[] = $value;
                    unset($this->data[$key][$i]);
                }
            }
            if (count($this->data[$key]) === 0)
                unset($this->data[$key]);
        }

        // Retourne un tableau (�ventuellement vide) contenant les valeurs filtr�es
        return $result;
    }


    // --------------------------------------------------------------------------------
    // M�thodes magiques de php, traitement des cl�s comme des propri�t�s de l'objet
    // --------------------------------------------------------------------------------


    /**
     * D�termine si la cl� indiqu�e existe.
     *
     * __isset() est une m�thode magique de php qui permet de tester l'existence
     * d'une cl� comme s'il s'agissait d'une propri�t� de l'objet Multimap.
     *
     * Exemple :
     * <code>$map = new Multimap('item'); echo isset($map->key); // true </code>
     *
     * La fonction {@link has()} peut faire la m�me chose mais prend le nom de
     * l'argument en param�tre.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->has($key);
    }


    /**
     * Retourne les donn�es associ�es � la cl� indiqu�e ou null
     * si la cl� demand�e ne figure pas dans la collection.
     *
     * __get est une m�thode magique de php qui permet d'acc�der aux param�tres
     * de la collection comme s'il s'agissait de propri�t�s de l'objet
     * Multimap (par exemple <code>$map->max</code>)
     *
     * La m�thode {@link get()} est similaire mais permet d'indiquer une valeur
     * par d�faut.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }


    /**
     * Modifie les donn�es associ�es � une cl�.
     *
     * __set est une m�thode magique de php qui permet de modifier une
     * cl� comme s'il s'agissait d'une propri�t� de l'objet Multimap
     * (par exemple <code>$map->max = 10</code>)
     *
     * Set remplace compl�tement les donn�es associ�es � la cl�. Pour ajouter une valeur
     * � une cl� existant, utilisez {@link add()}
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }


    /**
     * Supprime la cl� indiqu�e.
     *
     * __unset est une m�thode magique de php qui permet de supprimer une
     * cl� de la collection comme s'il s'agissait d'une propri�t� de l'objet Multimap
     * (par exemple <code>unset($map->max)</code>)
     *
     * @param string $key
     */
    public function __unset($key)
    {
        $this->clear($key);
    }


    // --------------------------------------------------------------------------------
    // Interface Countable
    // --------------------------------------------------------------------------------


    /**
     * Retourne le nombre de cl�s pr�sentes dans la collection ou le nombre de donn�es
     * associ�es � la cl� ou aux cl�s indiqu�es.
     *
     * @implements Countable
     *
     * @param mixed $key la ou les cl�s � compter.
     *
     * @return int
     */
    public function count($key = null)
    {
        if (is_null($key)) return count($this->data);

        $count = 0;
        foreach($this->parseKey($key) as $key)
            if (isset($this->data[$key])) $count += count($this->data[$key]);

        return $count;
    }


    // --------------------------------------------------------------------------------
    // Interface ArrayAccess
    // --------------------------------------------------------------------------------

    /**
     * Indique si la cl� indiqu�e existe.
     *
     * @implements ArrayAccess
     *
     * @param scalar $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }


    /**
     * Retourne les donn�es associ�es � la cl� indiqu�e.
     *
     * @implements ArrayAccess
     *
     * @param scalar $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }


    /**
     * Modifie les donn�es associ�es � la cl� indiqu�e.
     *
     * @implements ArrayAccess
     *
     * @param scalar $key
     * @param mixed $value
     * @return $this
     */
    public function offsetSet($key, $value)
    {
        return $this->set($key, $value);
    }


    /**
     * Supprime les donn�es associ�es � la cl� indiqu�e.
     *
     * @implements ArrayAccess
     *
     * @param mixed $key
     * @return $this
     */
    public function offsetUnset($key)
    {
        return $this->clear($key);
    }


    // --------------------------------------------------------------------------------
    // Interface IteratorAggregate
    // --------------------------------------------------------------------------------


    /**
     * Retourne un it�rateur permettant d'utiliser un multimap dans une boucle foreach.
     *
     *
     * @implements IteratorAggregate
     *
     * @return object L'it�rateur obtenu n'est utilisable qu'en lecture. Une boucle de la forme
     * <code>foreach($map as & $value)</code> provoquera une erreur.
     */
    public function getIterator()
    {
        return new ArrayIterator($this->toArray());
    }

    // todo
    // has() retourne true si on a l'une des cl�s/valeurs indiqu�es (OU).
    // has All retournerait true si on les a toutes (ET).
    //
    // hasAll($key) : retourne true si la collection contient toutes les cl�s indiqu�es
    // hasAll($key, $value) : retourne true si toutes les cl�s existent et qu'elles contiennent toutes value
    // si value est un tableau : retourne true si toutes les cl�s existent et qu'elles contiennent toutes les value indiqu�es
    /*
    public function hasAll($key)
    {
        hasAll('a,b,c', array('ITEM1','ITEM2'));
    }
    */
}