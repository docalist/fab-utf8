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

use \Iterator;
use Fooltext\Document\Document;
use Fab\Schema\Schema;
use Fab\Schema\Fields;

/**
 * Représente le résultat d'une recherche c'est-à-dire la liste des réponses obtenues.
 *
 * La liste des hits est itérable avec une boucle foreach
 *
 * Au sein de la boucle, les champs du document en cours sont accessibles comme des propriétés
 *
 * Exemple :
 * echo 'Votre recherche : ', $search->getQuery(), "\n"
 * if ($search->isEmpty())
 * {
 *     echo "Aucune réponse\n";
 * }
 * else
 * {
 *     echo $search->count(), " réponses :\n"
 *     foreach ($search as $rank=>$document)
 *     {
 *         echo $document->REF, $document->titre;
 *     }
 * }
 */
abstract class SearchResult implements Iterator
{
    /**
     * La base de données sur laquelle porte la recherche en cours.
     *
     * @var StoreInterface
     */
    protected $store;

    /**
     * La requête en cours.
     *
     * @var SearchRequest
     */
    protected $searchRequest;

    /**
     * Le document en cours.
     *
     * @var Document
     */
    protected $document;

    /**
     * Le schéma de la base en cours.
     *
     * @var Schema
     */
    protected $schema;

    /**
     * Les champs du schéma.
     *
     * @var Fields;
     */
    protected $fields;


    /**
     * Exécute la requête passée en paramètre sur la base de données indiquée et
     * stocke les résultats obtenus.
     *
     * @param StoreInterface $store la base de données sur laquelle la recherche
     * sera exécutée.
     *
     * @param SearchRequest $searchRequest la requête à exécuter.
     */
    public function __construct(StoreInterface $store, SearchRequest $searchRequest)
    {
        $this->store = $store;
        $this->searchRequest = $searchRequest;
        $this->schema = $this->store->getSchema();
        $this->fields = $this->schema->fields;
    }

    /**
     * Retourne la base de données sur laquelle porte la recherche.
     *
     * @return StoreInterface
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * Retourne la requête exécutée.
     *
     * @return SearchRequest
     */
    public function getSearchRequest()
    {
        return $this->searchRequest;
    }

    /**
     * Indique si la liste des réponses obtenues est vide.
     *
     * @return bool
     */
    abstract public function isEmpty();

    /**
     * Retourne le nombre de réponses obtenues.
     *
     * La méthode peut retourner :
     * - $type === null : le nombre de réponses obtenues estimé.
     * - $type === 'min' : le nombre minimum de réponses obtenues.
     * - $type === 'max' : le nombre maximum de réponses obtenues.
     * - $type === 'round' : une version arrondie du nombre de réponses obtenues.
     *
     * @param null|string $type
     *
     * @return int
     */
    abstract public function count($type = null);


    // Accès aux champs du document en cours comme s'il s'agissait de propriétés de l'objet SearchResult

    /**
     * Indique si le champ indiqué existe.
     *
     * Lé méthode retourne vrai pour tous les champs qui existent dans le schéma,
     * que ceux-ci soient présents ou non dans le document en cours.
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return $this->fields->has($name);
    }

    /**
     * Retourne le contenu du champ indiqué.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (! $this->fields->has($name)) return "fields ! has $name"; //return null;
        return $this->document[$name];
    }

    /**
     * Retourne la liste des mots-vides qui ont été ignorés lors de la recherche.
     *
     * getStopWords() retourne la liste des mots de la requête qui ont été ignorés
     * parce qu'ils figuraient dans la liste des mots-vides déinis dans la base.
     *
     * Par exemple, pour la recherche <code>outil pour le web, pour internet</code>
     * la méthode pourrait retourner <code>array('pour', 'le')</code>.
     *
     * Par défaut, les mots-vides retournés sont dédoublonnés, mais vous pouvez
     * passer <code>false</code> en paramètre pour obtenir la liste brute (dans
     * l'exemple ci-dessus, on obtiendrait <code>array('pour', 'le', 'pour')</code>
     *
     * @param bool $removeDuplicates flag indiquant s'il faut dédoublonner ou non la
     * liste des mots-vides (true par défaut).
     *
     * @return array un tableau contenant la liste des mots vides.
     */
    abstract public function getStopwords($removeDuplicates = true);

    /**
     * Retourne la liste des termes de recherche générés par la requête.
     *
     * getQueryTerms() construit la liste des termes qui ont été pris en compte
     * lors de la recherche.
     *
     * La liste comprend tous les termes présents dans la requête (mais pas les
     * mots vides) et tous les termes générés par les troncatures.
     *
     * Par exemple, la requête <code>éduc* pour la santé</code> pourrait
     * retourner <code>array('educateur', 'education', 'sante')</code>.
     *
     * Par défaut, les termes retournés sont filtrés de manière à pouvoir être
     * présentés à l'utilisateur (dédoublonnage des termes, suppression des
     * préfixes internes, etc.), mais vous pouvez passer <code>false</code>
     * en paramètre pour obtenir la liste brute.
     *
     * @param bool $internal flag indiquant s'il faut filtrer ou non la liste
     * des termes.
     *
     * @return array un tableau contenant la liste des termes de recherche.
     */
    abstract public function getQueryTerms($internal = false);

    /**
     * Retourne la liste des termes de la requête qui figurent dans le document
     * en cours.
     *
     * getMatchingTerms() construit l'intersection entre la liste des termes
     * générés par la requête et la liste de termes du document en cours.
     *
     * Cela permet, entre autres, de comprendre pourquoi un document apparaît
     * dans la liste des réponses.
     *
     * Par défaut, les termes retournés sont filtrés de manière à pouvoir être
     * présentés à l'utilisateur (dédoublonnage des termes, suppression des
     * préfixes internes utilisés dans les index, etc.), mais vous pouvez
     * passer <code>false</code> en paramètre pour obtenir la liste brute.
     *
     * @param bool $internal flag indiquant s'il faut filtrer ou non la liste
     * des termes.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    abstract public function getMatchingTerms($internal = false);

    /**
     * Retourne la liste des termes qui ont été stockés dans la base pour le
     * document en cours.
     *
     * getIndexTerms() permet de "voir" comment un document a été indexé.
     *
     * La méthode retourne un tableau qui indique les mots, les mots-clés, les
     * entrées de table de lookup et les attributs qui ont été générés lors de
     * l'indexation. Si un terme est indexé à la phrase, la liste des positions
     * est également retournée.
     *
     * @return array()
     */
    abstract public function getIndexTerms();

    /**
     * Retourne une chaine expliquant comment le moteur de recherche
     * interprété la requête en cours.
     *
     * @return string
     */
    abstract public function explainQuery();

    /**
     * Applique un correcteur orthographique à la requête en cours
     * et retourne une équation de recherche corrigée.
     *
     * @return string
     */
    abstract public function getCorrectedQuery();

    public function getQuery()
    {
//echo "<br />APPEL DE GetQuery()<br />";
        /*
         L'équation de recherche qu'on va construire sera de la forme suivante :

         (
             +(tous les _equation croisés en AND)
             tous les champs prob/love/hate concaténés avec des espaces
         )
         AND
         (
             tous les champs bool croisés en defaultOp (occurences croisées en OR)
         )
         AND
         (
             tous les _filter croisés en AND
         )

        */

        $request = $this->searchRequest;

        /*
            Par défaut, la requête générée va être de la forme :
            +(_equation _equation) autres_critères

            Quand defaultop===AND, le + de début est inutile et il alourdit la lecture
            de l'équation. Du coup, on ne le génère que si on est en "OU".
        */
        $or = $request->defaultop() === 'or';
        $plus = $or ? '+' : '';


        $result = array();

        // tous les _equation croisés en AND
        $equations = $request->equation();
        if (count($equations))
        {
            $t = array();
            foreach($equations as $equation)
            {
                if (count($equations) > 1) $this->addBrackets($equation);
                $t[] = $equation;
            }
            $result[0] = implode(' AND ', $t);
        }

        /*
            Combinatoire utilisée pour construire l'équation de recherche :
            +----------+-----------------+---------------------+-------------------+
            | Type de  |    Opérateur    | Opérateur entre les |  Opérateur entre  |
            | requête  | entre les mots  |  valeurs d'un champ | champs différents |
            +----------+-----------------+---------------------+-------------------+
            |   PROB   |    default op   |        AND          |        AND        |
            +----------+-----------------+---------------------+-------------------+
            |   BOOL   |    default op   |        OR           |        AND        |
            +----------+-----------------+---------------------+-------------------+
            |   LOVE   |    default op   |        AND          |        AND        |
            +----------+-----------------+---------------------+-------------------+
            |   HATE   |    default op   |        OR           |        OR         |
            +----------+-----------------+---------------------+-------------------+
         */
        $types = array
        (
            'prob'  => array($or ? ' ' : ' ', ' AND '), //
            'bool'  => array(' OR ' , ' AND '), //
            'love'  => array(' +', ' AND '), // Tous les mots sont requis, donc on parse en "ET"
            'hate'  => array(' -' , ' bOR ' ), // Le résultat sera combiné en "AND_NOT hate", donc on parse en "OU"
        );

        foreach ($types as $type => $op)
        {
            $$type = array();
            foreach($request->$type() as $name => $value)
            {
                $t = array();
                foreach((array)$value as $value)
                {
                    $this->addBrackets($value);
                    $t[] = "$name:$value";
                }
                array_push($$type, implode($op[0], $t));
            }
            $$type = implode($op[1], $$type);
        }
        if ($bool)
        {
            $result[] = $bool;
        }

        if ($prob || $love)
        {
            if ($love) $love = "+$love";
            $h = rtrim("$prob $love");
            if (isset($result[0]))
            {
                //$this->addBrackets($result[0]);
                $result[0] = $result[0] . ' ' . $h;
            }
            else
            {
                $result[0] = $h;
            }
        }

        if ($hate)
        {
            if (isset($result[0]))
            {
                //$this->addBrackets($result[0]);
                $result[0] = $result[0] . ' -' . $hate;
            }
            else
                $result[0] = $h;
        }



        // AND (tous les _filter croisés en AND)
        if (false && $this->filter)
        {
            $t2=array();
            foreach((array) $this->filter as $equation)
            {
                $this->addBrackets($equation);
                $t2[]=$equation;
            }
            $h=implode(' AND ', $t2);
            $result[] = $h;
        }

        if (count($result) > 1) array_walk($result, array($this, 'addBrackets'));
        $result = implode(' AND ', $result);
        return $result;
    }

    /**
     * Ajoute des parenthèses autour de l'équation passée au paramètre si c'est
     * nécessaire.
     *
     * La méthode considère que l'équation passée en paramètre est destinée à
     * être combinée en "ET" avec d'autres équations.
     *
     * Dans sa version actuelle, la méthode supprime de l'équation les blocs
     * parenthésés, les phrases et les articles et ajoute des parenthèses si
     * ce qui reste contient un ou plusieurs espaces.
     *
     * Idéalement, il faudrait faire un traitement beaucoup plus compliqué, mais
     * ça revient quasiment à ré-écrire un query parser.
     *
     * Le traitement actuel est plus simple mais semble fonctionner.
     *
     * @param string $equation l'équation à tester.
     */
    private function addBrackets(& $equation)
    {
        static $re='~\((?:(?>[^()]+)|(?R))*\)|"[^"]*"|\[[^]]*\]~';

        if (false !== strpos(preg_replace($re, '', $equation), ' '))
        {
            $equation = '('.$equation.')';
        }

        /*
            Explications sur l'expression régulière utilisée.

            On veut éliminer de l'équation les expressions parenthèsées, les phrases
            et les articles.

            Une expression parenthèsée est définie par l'expression régulière
            récursive suivante (source : manuel php, rechercher "masques récursifs"
            dans la page http://docs.php.net/manual/fr/regexp.reference.php) :

            $parent='
                \(                  # une parenthèse ouvrante
                (?:                 # début du groupe qui définit une expression parenthésée
                    (?>             # "atomic grouping", supprime le backtracing (plus rapide)
                        [^()]+      # une suite quelconque de caractères, hormis des parenthèses
                    )
                    |
                    (?R)            # ou un expression parenthésée (appel récursif : groupe en cours)
                )*
                \)                  # une parenthèse fermante
            ';

            Une phrase, avec le bloc suivant :
            $phrase='
                "                   # un guillemet ouvrant
                [^"]*               # une suite quelconque de caractères, sauf des guillemets
                "                   # un guillemet fermant
            ';

            Et une recherche à l'article avec l'expression suivante :
            $value='
                \[                  # un crochet ouvrant
                [^]]*               # une suite quelconque de caractères, sauf un crochet fermant
                \]                  # un crochet fermant
            ';

            Si on veut les trois, il suffit de les combiner :
            $re="~$parent|$phrase|$value~x";

            Ce qui donne l'expression régulière utilisée dans le code :
        */
    }
}