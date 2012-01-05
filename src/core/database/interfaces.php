<?php


use fab\Schema;

// Une base de données
abstract class Database
{
    const READONLY = 1;
    const READWRITE = 2;
    const CREATE = 3;
    const OVERWRITE = 4;

    /**
     * Ouvre une base de données
     *
     * @param string $name
     * @param string $driver
     * @param bool $readonly
     *
     * @return Database
     */
    public static function open($path, $driver, $readonly = true)
    {
        self::checkDriver($driver);
        return new $driver($path, $readonly ? self::READONLY : self::READWRITE);
    }

    /**
     * Crée une nouvelle base de données
     *
     * @param string $path path de la base de données à créer
     * @param string $driver driver à utiliser
     * @param Schema $schema schéma de la base
     *
     * @return Database
     */
    public static function create($path, $driver, Schema $schema, $overwrite = false)
    {
        self::checkDriver($driver);
        return new $driver($path, $overwrite ? self::OVERWRITE :self::CREATE, $schema);
    }

    /**
     * Vérifie que le nom de classe passé en paramètre est un driver valide.
     *
     * @param string $driver
     * @throws Exception
     */
    private static function checkDriver($driver)
    {
        if (! class_exists($driver))
            throw new Exception("Classe $driver non trouvée");

        if (! is_subclass_of($driver, __CLASS__))
            throw new Exception("La classe $driver n'est pas un driver de base de données");
    }

    /**
     * Réouvre la base de données en cours
     *
     * @param bool|null mode de réouverture de la base de données :
     * - null : ouvre la base de données dans le même mode
     * - true : ouvre en lecture seule
     * - false : ouvre en lecture/écriture.
     */
    public function reopen()
    {

    }

    /**
     * Retourne le schéma de la base
     *
     * @return Schema
     */
    public function getSchema()
    {

    }


    /**
     * Modifie le schéma de la base
     *
     * @return Schema
     */
    public function setSchema(Schema $schema)
    {

    }


    /**
     * Ajoute un nouvel enregistrement dans la base.
     *
     * @param Record $record
     * @return Database
     */
    public function add(Record $record)
    {

    }


    /**
     * Retourne un enregistrement unique dont on connaît le numéro de référence.
     *
     * @param int $ref
     *
     * @return Record
     */
    public function get($ref)
    {

    }


    /**
     * Modifie un enregistrement existant de la base.
     *
     * @param Tecord $record
     */
    public function update(Record $record)
    {

    }


    /**
     * Supprime un enregistrement existant de la base.
     *
     * @param Record $record
     */
    public function delete(Record $record)
    {

    }


    /**
     * Sélectionne des enregistrements
     * @param string $query
     * @param array $options
     * @return Recordset
     */
    public function search($query, array $options=array())
    {

    }
}


// Un enregistrement de la base
class Record extends Multimap
{

}

// Une liste d'enregistrement (foreach, count)
class Recordset //implements ArrayAccess, Iterator
{

}



/**
* Principe de l'indextion :
*
* Lors de l'indexation, les champs vont passer dans un "pipeline" constitué
* de {@link Mapper mappers}, d'un {@link Tokenizer tokenizer} et de
* {@link TokenFilter filtres}.
*
* Les mappers "préparent" le contenu du champ à l'indexation (suppression
* des accents, minusculisation, suppression des balises html, etc.)
*
* A partir de ce contenu préparé, les tokenizers génèrent une liste de
* tokens qui constituent les termes de recherche qui pourront être utilisés
* lors des recherches.
*
* Les filtres examinent la liste des tokens générés et ont la possibilité
* de modifier, ajouter ou supprimer des tokens (suppression des mots vides,
* des termes trop long, conversion de certains termes, ajout de synonymes, etc.)
*
* Lors de l'indexation, tous les champs sont gérés comme des champs multivalués,
* même si ce n'est pas le cas. Par exemple, pour un simple champ texte, l'indexeur
* fournira le contenu du champ sous la forme array('xyz') et non pas comme une
* simple chaine.
*
* Cela simplifie l'implémentation, parce qu'il n'y a pas besoin de tester en
* permanence si on a affaire à un scalaire ou à un tableau.
*
* Cela signifie que les mappers travaillent toujours sur un tableau de valeurs.
* Les tokenizers reçoivent en entrée un tableau de valeurs et doivent retourner un
* tableau de tokens (éventuellement vide) pour chacune des valeurs (i.e. ils
* retournent un tableau de tableaux de tokens)
*
* De même, les filtres travaillent sur des tableaux de tableaux de tokens.
*
* Propriété à conserver le nombre de flux de tokens correspond toujours au nombre d'articles
* Nécessaire, par exemple, pour count.
*
*
*/


//////////////////////// MAPPERS

/**
 * Convertit les caractères d'un texte.
 *
 * Par défaut, les mappers prennent du texte utf-8 et génère des minus non accentuées.
 * Si ça change, créer de nouveau mappers (RichMapper : conserve les accents, uppercaseMapper : génère des majus, etc.)
 * usages :
 * - convertir un texte (utf-8) en minuscules non accentuées : DefaultMapper
 * - convertir du html (enlever les tags, les entités, générer des minus non accentuées) HtmlMapper
 * - attachmentMapper : le champ contient un path de fichier, on génère les tokens correspondant au contenu du fichier (txt, pdf, doc...)
 * -
 *
 */
interface Mapper
{
    public function map(array & $values);
}

/**
 * Mapper par défaut : convertit toutes les lettres en minuscules non accentuées.
 */
class DefaultMapper implements Mapper
{
    public static $map = array
    (
        // U0000 - Latin de base (http://fr.wikipedia.org/wiki/Table_des_caractères_Unicode/U0000)
        'A' => 'a',    'B' => 'b',    'C' => 'c',    'D' => 'd',    'E' => 'e',    'F' => 'f',
        'G' => 'g',    'H' => 'h',    'I' => 'i',    'J' => 'j',    'K' => 'k',    'L' => 'l',
        'M' => 'm',    'N' => 'n',    'O' => 'o',    'P' => 'p',    'Q' => 'q',    'R' => 'r',
        'S' => 's',    'T' => 't',    'U' => 'u',    'V' => 'v',    'W' => 'w',    'X' => 'x',
        'Y' => 'y',    'Z' => 'z',

        // U0080 - Supplément Latin-1 (http://fr.wikipedia.org/wiki/Table_des_caractères_Unicode/U0080)
        'À' => 'a',    'Á' => 'a',    'Â' => 'a',    'Ã' => 'a',    'Ä' => 'a',    'Å' => 'a',
        'Æ' => 'ae',   'Ç' => 'c',    'È' => 'e',    'É' => 'e',    'Ê' => 'e',    'Ë' => 'e',
        'Ì' => 'i',    'Í' => 'i',    'Î' => 'i',    'Ï' => 'i',    'Ð' => 'd',    'Ñ' => 'n',
        'Ò' => 'o',    'Ó' => 'o',    'Ô' => 'o',    'Õ' => 'o',    'Ö' => 'o',    'Ø' => 'o',
        'Ù' => 'u',
        'Ú' => 'u',    'Û' => 'u',    'Ü' => 'u',    'Ý' => 'y',    'Þ' => 'th',   'ß' => 'ss',
        'à' => 'a',    'á' => 'a',    'â' => 'a',    'ã' => 'a',    'ä' => 'a',    'å' => 'a',
        'æ' => 'ae',   'ç' => 'c',    'è' => 'e',    'é' => 'e',    'ê' => 'e',    'ë' => 'e',
        'ì' => 'i',    'í' => 'i',    'î' => 'i',    'ï' => 'i',    'ð' => 'd',    'ñ' => 'n',
        'ò' => 'o',    'ó' => 'o',    'ô' => 'o',    'õ' => 'o',    'ö' => 'o',    'ø' => 'o',
        'ù' => 'u',    'ú' => 'u',    'û' => 'u',    'ü' => 'u',    'ý' => 'y',    'þ' => 'th',
        'ÿ' => 'y',

        // U0100 - Latin étendu A (http://fr.wikipedia.org/wiki/Table_des_caractères_Unicode/U0100)
        'Ā' => 'a',    'ā' => 'a',    'Ă' => 'a',    'ă' => 'a',    'Ą' => 'a',    'ą' => 'a',
        'Ć' => 'c',    'ć' => 'c',    'Ĉ' => 'c',    'ĉ' => 'c',    'Ċ' => 'c',    'ċ' => 'c',
        'Č' => 'c',    'č' => 'c',    'Ď' => 'd',    'ď' => 'd',    'Đ' => 'd',    'đ' => 'd',
        'Ē' => 'e',    'ē' => 'e',    'Ĕ' => 'e',    'ĕ' => 'e',    'Ė' => 'e',    'ė' => 'e',
        'Ę' => 'e',    'ę' => 'e',    'Ě' => 'e',    'ě' => 'e',    'Ĝ' => 'g',    'ĝ' => 'g',
        'Ğ' => 'g',    'ğ' => 'g',    'Ġ' => 'g',    'ġ' => 'g',    'Ģ' => 'g',    'ģ' => 'g',
        'Ĥ' => 'h',    'ĥ' => 'h',    'Ħ' => 'h',    'ħ' => 'h',    'Ĩ' => 'i',    'ĩ' => 'i',
        'Ī' => 'i',    'ī' => 'i',    'Ĭ' => 'i',    'ĭ' => 'i',    'Į' => 'i',    'į' => 'i',
        'İ' => 'i',    'ı' => 'i',    'Ĳ' => 'ij',   'ĳ' => 'ij',   'Ĵ' => 'j',    'ĵ' => 'j',
        'Ķ' => 'k',    'ķ' => 'k',    'ĸ' => 'k',    'Ĺ' => 'l',    'ĺ' => 'l',    'Ļ' => 'l',
        'ļ' => 'l',    'Ľ' => 'L',    'ľ' => 'l',    'Ŀ' => 'l',    'ŀ' => 'l',    'Ł' => 'l',
        'ł' => 'l',    'Ń' => 'n',    'ń' => 'n',    'Ņ' => 'n',    'ņ' => 'n',    'Ň' => 'n',
        'ň' => 'n',    'ŉ' => 'n',    'Ŋ' => 'n',    'ŋ' => 'n',    'Ō' => 'O',    'ō' => 'o',
        'Ŏ' => 'o',    'ŏ' => 'o',    'Ő' => 'o',    'ő' => 'o',    'Œ' => 'oe',   'œ' => 'oe',
        'Ŕ' => 'r',    'ŕ' => 'r',    'Ŗ' => 'r',    'ŗ' => 'r',    'Ř' => 'r',    'ř' => 'r',
        'Ś' => 's',    'ś' => 's',    'Ŝ' => 's',    'ŝ' => 's',    'Ş' => 's',    'ş' => 's',
        'Š' => 's',    'š' => 's',    'Ţ' => 't',    'ţ' => 't',    'Ť' => 't',    'ť' => 't',
        'Ŧ' => 't',    'ŧ' => 't',    'Ũ' => 'u',    'ũ' => 'u',    'Ū' => 'u',    'ū' => 'u',
        'Ŭ' => 'u',    'ŭ' => 'u',    'Ů' => 'u',    'ů' => 'u',    'Ű' => 'u',    'ű' => 'u',
        'Ų' => 'u',    'ų' => 'u',    'Ŵ' => 'w',    'ŵ' => 'w',    'Ŷ' => 'y',    'ŷ' => 'y',
        'Ÿ' => 'y',    'Ź' => 'Z',    'ź' => 'z',    'Ż' => 'Z',    'ż' => 'z',    'Ž' => 'Z',
        'ž' => 'z',    'ſ' => 's',

        // U0180 - Latin étendu B (http://fr.wikipedia.org/wiki/Table_des_caractères_Unicode/U0180)
        // Voir ce qu'il faut garder : slovène/croate, roumain,
        // 'Ș' => 's',    'ș' => 's',    'Ț' => 't',    'ț' => 't',   // Supplément pour le roumain

        // U20A0 - Symboles monétaires (http://fr.wikipedia.org/wiki/Table_des_caractères_Unicode/U20A0)
        // '€' => 'E',

        // autres symboles monétaires : Livre : 00A3 £, dollar 0024 $, etc.

        // Caractères dont on ne veut pas dans les mots
        // str_word_count inclut dans les mots les caractères a-z, l'apostrophe et le tiret.
        // on ne veut conserver que les caractères a-z. Neutralise les deux autres
        "'" => ' ',    '-' => ' ',
    );

    public function map(array & $values)
    {
        foreach ($values as & $value)
            $value = strtr($value, self::$map);
    }
}

/**
 * Remplace les entités html (&acirc; &quot; &#039; ...) par le caractère correspondant
 * Supprime tous les tags html (ou xml) présents.
 * Supprime les commentaires (<!-- -->) et les directives (<?xxx >) présents dans le texte.
 * Applique {@link DefautMapper mapper par défaut} au résultat obtenu.
*/
class HtmlMapper extends DefaultMapper // implements MapperInterface utile ou pas ? déjà dans DefaultMapper.
{
    public function map(array & $values)
    {
        foreach ($values as & $value)
        {
            $value = strip_tags($value); // supprime aussi les commentaires (<!-- -->) et les directives (<?xxx >)
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }
        parent::map($values);
    }
}


//////////////////////// Tokenizers

/**
 * Interface de base pour les tokenizers.
 *
 * Un {@link Tokenizer tokenizer} analyse le contenu d'un champ et va générer la
 * liste des termes de recherche (les tokens) qui vont être ajoutés dans les index
 * de la base de données.
 *
 * Les tokens sont retournés soit sous la forme d'un tableau contenant les termes
 * de recherche à ajouter à l'index, soit sous la forme d'un tableau de tableaux
 * de tokens (par exemple si la valeur à tokenizer est un tableau, certains
 * tokenizers vont retourner un tableau de tokens pour chaque élément).
 *
 * Pour les tokenizers de base (ceux qui implémentent juste cette interface),
 * les clés des tableaux de tokens retournés n'ont aucune signification
 * particulière et sont ignorées par l'indexeur.
 *
 * Cependant, certains indexeurs implémentent également l'interface
 * {@link WithPositions}. Dans ce cas, les clés des tableaux de tokens représentent
 * la position de chacun des tokens. Dans ce cas, les clés des tokens vont être
 * prise en compte par l'indexeur pour permettre, entres autres, la recherche
 * par phrases.
 *
 */
interface Tokenizer
{
    /**
     * Tokenize le champ passé en paramètre.
     *
     * @param mixed $value la valeur à tokenizer. Il peut s'agir d'un scalaire ou
     * d'un tableau (par exemple dans le cas d'un champ articles).
     *
     * @param fab\Schema\Field $field le champ en cours d'indexation. En général,
     * ce paramètre est ignoré, mais certains tokenizers ont besoin de connaître les
     * propriétés du champ en cours d'indexation pour faire leur travail.
     *
     * @return array soit un tableau de tokens, soit un tableau de tableaux de tokens.
     */
    public function tokenize(array $values, fab\Schema\Field $field = null);
}

/**
 * Interface permettant d'indiquer qu'un tokenizer gère la position des tokens.
 *
 * Lorsqu'un tokenizer implémente cette interface, l'indexeur tient compte des positions
 * de chacun des tokens retournés pour permettre des recherches par phrase.
 */
interface WithPositions
{

}

/*
abstract class AbstractTokenizer implements Tokenizer
{
    abstract protected function tokenizeScalar($value, fab\Schema\Field $field = null);

    public function tokenize($value, fab\Schema\Field $field = null)
    {
        // Cas d'un scalaire
        if (is_scalar($value)) return $this->tokenizeScalar($value, $field);

        // Cas d'un tableau de scalaires
        $result = array();
        foreach($value as $item)
            $result[] = $this->tokenizeScalar($item, $field);
    }

}
*/
/**
 * Extrait les mots du texte en considérant que les caractères [a-z0-9@_] sont les seuls pouvant
 * constituer un mot.
 *
 * Tous les autres caractères sont ignorés.
 *
 * Ce tokenizer ne fonctionne que sur du texte en minu non accentuée (DefaultMapper, HtmlMapper)
 *
 * Les sigles de 2 à 9 lettres sont convertis en mots.
 */
class WordTokenizer implements Tokenizer
{
    public function tokenize(array $values, fab\Schema\Field $field = null)
    {
        $tokens = array(); // new SplFixedArray(count($values)) ?
        foreach ($values as $value)
        {
            // Convertit les sigles en mots
            $value = preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array(__CLASS__, 'acronymToTerm'), $value);

            // Génère les tokens
            $tokens[] = str_word_count($value, 1, '0123456789@_'); // 0..9@_
        }
        return $tokens;
    }

    /**
    * Fonction utilitaire utilisée par {@link tokenize()} pour convertir
    * les acronymes en mots
    *
    * @param array $matches
    * @return string
    */
    protected static function acronymToTerm($matches)
    {
        return str_replace('.', '', $matches[0]);
    }
}

/**
 * Extrait les mots du texte comme le fait {@link WordTokenizer} mais permet
 * les recherches par phrase et par proximité.
 */
class PhraseTokenizer extends WordTokenizer implements WithPositions { }

class DateTokenizer extends PhraseTokenizer
{
    // 20111117 -> 2011, nov, novembre, 17 + noms de mois en anglais ?
}
class BooleanTokenizer implements Tokenizer
{
    public function tokenize(array $values, fab\Schema\Field $field = null)
    {
        $tokens = array(); // new SplFixedArray(count($values)) ?
        foreach ($values as $value)
        {
            $tokens[] = array($value ? 'true' : 'false'); // ajouter on/off, vrai/faux, 0/1 ?
        }
        return $tokens;
    }
}
class IntegerTokenizer implements Tokenizer
{
    public function tokenize(array $values, fab\Schema\Field $field = null)
    {
        $tokens = array(); // new SplFixedArray(count($values)) ?
        foreach ($values as $value)
        {
            $tokens[] = array($value===0 ? '0' : (string)$value);
        }
        return $tokens;
    }
}

// floatMapper -> représentation textuelle d'un float
// referenceTableMapper ? ou tokenhandler ?

//////////////////////// TokenFilter(s)

interface Filter
{
    public function filter(array & $streams);
}

/**
 * Filtre qui remplace tous les tokens par un terme unique obtenu en concaténant
 * les termes de départ.
 *
 * Ce filtre est utile lorsqu'on veut normaliser les tokens générés pour un code, une date, etc.
 * - exemple : cote pouvant être saisie sous plusieurs formes "oa10", "oa 10", "oa/10" -> oa10
 * - date "2011/11/19", "20111119" -> 20111119
 */
class ConcatFilter implements Filter
{
    public function filter(array & $streams)
    {
        foreach($streams as & $tokens)
        {
            if (! empty($tokens)) $tokens = array(implode('', $tokens));
        }
    }
}

/**
 * comme ConcatFilter, mais en plus, gère les isbn10 et 13
 * - un isb13 est indexé tel quel :
 *   "978-2-1234-5680-3" -> array('9872123456803')
 * - un isbn10 et indexé comme isbn10 ET comme isbn13 :
 * 	"2-1234-5680-2" -> array('2123456802', '9872123456803')
 * TODO
 */
class IsbnFilter extends ConcatFilter
{
    public function filter(array & $streams)
    {
        parent::filter($streams);
        foreach($streams as & $tokens)
        {
            if (! empty($tokens))
            {
                // chaque flux ne contient plus qu'un token unique
                $token = reset($tokens);

                if (strlen($token) === 10)
                    $tokens[] = "Version13ChiffresDe$token";
            }
         }
    }
}

// article -> array('article')
// article, document papier -> array(array('article'), array('document','papier')) -> __has2
// COmpt
/**
 * Compte le nombre d'articles présents dans le champ.
 *
 * Pour un champ vide, ajoute le token "__empty"
 * Pour un champ scalaire, ajoute le token "__has1"
 * Pour un champ multivalué, ajoute un token de la forme "__hasX" ou x représente le nombre
 * d'articles présents dans le champ.
 *
 * Le dénombrement est fait en comptant le nombre de flux de tokens.
 * Attention, ajoute un flux de tokens. A utiliser en dernier dans la liste des filtres.
 */
class CountableFilter implements Filter
{
    public function filter(array & $streams)
    {
        // champ vide : __empty
        if (empty($streams))
        {
            $streams[] = array('__empty');
        }

        // Au moins un article : __hasN
        else
        {
            $streams[] = array('__has' . count($tokens));
        }
    }
}

/**
 * Indexation à l'article.
 *
 * Combine ensemble tous les tokens pour créer un nouveau token permettant de recherche par article.
 * Par exemple si le tableau de tokens contient array('document', 'papier'), le filtre ajoute le token
 * '_document_papier_'.
 */
class KeywordFilter implements Filter
{
    public function filter(array & $streams)
    {
        foreach($streams as & $tokens)
        {
            $tokens[] = '_' . implode('_', $tokens) . '_';
        }
    }
}

class XapianDatabaseDriver extends Database
{
    protected $db;

    static protected $objectCache = array();

    protected function __construct($path, $mode = self::READONLY, fab\Schema $schema = null)
    {
        switch($mode)
        {
            case self::READONLY:
                $this->db = new XapianDatabase($path);
                $this->loadSchema();
                break;

            case self::READWRITE:
                $this->db = new XapianWritableDatabase($path, Xapian::DB_OPEN);
                $this->loadSchema();
                break;

            case self::CREATE:
                $this->db = new XapianWritableDatabase($path, Xapian::DB_CREATE);
                $this->setSchema($schema);
                break;

            case self::OVERWRITE:
                $this->db = new XapianWritableDatabase($path, Xapian::DB_CREATE_OR_OVERWRITE);
                $this->setSchema($schema);
                break;

            default:
                throw new Exception('Mode non géré.');
        }
    }

    public function reopen()
    {
        $this->db->reopen();
        return $this;
    }

    public function setSchema(Schema $schema)
    {
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

    public function add(Record $record)
    {
        $doc = new XapianDocument();

        $fields = $this->schema->getChild('fields');
        $data = array();
        foreach($record as $name=>$value)
        {
            echo "$name : \n";
            // Vérifie que le champ indiqué existe dans la base
            $field = $fields->getChild($name);
            if (is_null($field))
                throw new Exception("Le champ $name n'existe pas dans la base");

            // Stocke les données du champ s'il n'est pas vide
            $id = $field->get('_id');
            $weight = $field->get('weight');
            if (count($value)) $data[$id] = $value; // Ignore null et array()
            $value = (array) $value;
            echo "- value=", json_encode($value), "\n";

            // Tokenize le champ
            $tokenizer = $field->get('tokenizer');
            if (! empty($tokenizer))
            {
                // Mapping des caractères (0, 1 ou plusieurs)
                $mappers = $field->get('mapper');
                if (! empty($mappers))
                {
                    foreach((array) $mappers as $mapper)
                    {
                        $this->getObject($mapper)->map($value);
                        echo "- $mapper=", json_encode($value), "\n";
                    }
                }

                // Tokenize le texte (un seul tokenizer possible)
                echo "- $tokenizer=";
                $tokenizer = $this->getObject($field->get('tokenizer'));
                $streams = $tokenizer->tokenize($value);
                echo json_encode($streams), "\n";

                // Filtre les tokens (0, 1 ou plusieurs)
                $tokenFilter = $field->get('tokenfilter');
                if (! empty($tokenFilter))
                {
                    foreach((array) $tokenFilter as $filter)
                    {
                        $this->getObject($filter)->filter($streams);
                        echo "- $filter=", json_encode($streams), "\n";
                    }
                }
/*
 Comme tokens, on peut avoir :
 						préfixe		poids		position
 - des mots				id:			champ		oui ou non...
 - des articles			id:_		0			non
 - des lookups			L:			0			non
 - des clés de tri   	V:			0			non
*/
                // Stocke les tokens
                $withPosition = ($tokenizer instanceof PhraseTokenizerInterface);
                foreach($streams as $tokens)
                {
                    foreach($tokens as $position => $token)
                    {
                        if ($token[0] === '_')
                        {
                            echo "- add_term($token, 0)\n";
                            $doc->add_term($token, 0);
                        }
                        elseif($withPosition)
                        {
                            echo "- add_posting($token, $position, $weight)\n";
                            $doc->add_posting($token, $position, $weight);
                        }
                        else
                        {
                            echo "- add_term($token, $weight)\n";
                            $doc->add_term($token, $weight);
                        }
                    }
                }
            }
        }
var_export($data);
        $doc->set_data(json_encode($data));
        $docId = $this->db->add_document($doc);
        return $this;
    }

    /*
     T : terme de table de lookup
     S : clé de tri

     */
    protected function getObject($className)
    {
        if (! isset(self::$objectCache[$className]))
        {
            self::$objectCache[$className] = new $className;
        }
        return self::$objectCache[$className];
    }

}
//header('content-type: text/plain; charset=iso-8859-1');
header('content-type: text/plain; charset=utf-8');
$m = new DefaultMapper();
$s = new WordTokenizer();
$values = array("Le 21 juillet, c'est la fête de l'été. Signé D.M. Date : 16/11/2011. ");
$m->map($values);
$tokens=$s->tokenize($values);
var_dump($tokens);

$m = new HtmlMapper();
$s = new PhraseTokenizer();
$values = array('<p style="color:red">Le 21, c&#039;est l&#x0041; &quot;<strong>f&ecirc;te</strong>&quot;');
$m->map($values);
$tokens=$s->tokenize($values);
var_dump($tokens);
//die();

echo "creation du schéma\n";
$schema = new Schema;
$schema->getChild('fields')
    ->addChild($schema::create('field', array('_id'=>1, 'name'=>'REF'    , 'mapper'=>''             , 'tokenizer'=>'IntegerTokenizer' , 'tokenfilter'=>'')))
    ->addChild($schema::create('field', array('_id'=>2, 'name'=>'Type'   , 'mapper'=>'DefaultMapper', 'tokenizer'=>'WordTokenizer'    , 'tokenfilter'=>'KeywordFilter')))
    ->addChild($schema::create('field', array('_id'=>3, 'name'=>'Titre'  , 'mapper'=>'HtmlMapper'   , 'tokenizer'=>'WordTokenizer'    , 'tokenfilter'=>'')))
    ->addChild($schema::create('field', array('_id'=>4, 'name'=>'Aut'    , 'mapper'=>'DefaultMapper', 'tokenizer'=>'WordTokenizer'    , 'tokenfilter'=>'KeywordFilter')))
    ->addChild($schema::create('field', array('_id'=>5, 'name'=>'ISBN'   , 'mapper'=>'DefaultMapper', 'tokenizer'=>'WordTokenizer'    , 'tokenfilter'=>'IsbnFilter')))
    ->addChild($schema::create('field', array('_id'=>6, 'name'=>'Visible', 'mapper'=>'DefaultMapper', 'tokenizer'=>'BooleanTokenizer' , 'tokenfilter'=>'')))
;

echo $schema->toXml(true);
echo "creation de la base\n";
$db = Database::create('f:/temp/test', 'XapianDatabaseDriver', $schema, true);

echo "Ajout d'un enreg\n";
$db->add(new Record(array(
	'REF'=>123,
	'Type'=>array('Article','Document électronique'),
	'Titre'=>'Premier essai <i>(sous-titre en italique)</i>',
	'Aut'=>'Ménard (D.)',
	'ISBN'=>array("978-2-1234-5680-3", "2-1234-5680-2"),
	'Visible'=>true,
)));


// foreach($record as $name=>$field)
//     echo $field->get('label'), ':', $field->get('value');
/*
foreach($fields as $field)
{
    if ($field->tokenizer)
    {

    }
}
*/
