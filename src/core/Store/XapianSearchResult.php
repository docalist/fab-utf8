<?php
/**
 * This file is part of the Fab package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fab
 * @subpackage  Store
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fab\Store;

use Fab\Document\Document;

use \XapianQueryParser;
use \XapianQuery;
use \XapianEnquire;
use \XapianMSet;
use \XapianMSetIterator;

use \BadMethodCallException;

/**
 * Représente le résultat d'une recherche dans une base Xapian.
 *
 */
class XapianSearchResult extends SearchResult
{
    /**
     * La requête xapian générée à partir de searchRequest.
     *
     * @var \XapianQuery
     */
    protected $xapianQuery;

    /**
     * @var \XapianEnquire
     */
    protected $xapianEnquire;

    /**
     * @var \XapianQueryParser
     */
    protected $xapianQueryParser;

    /**
     * @var \XapianMSet
     */
    protected $xapianMSet;

    /**
     * Une estimation du nombre de réponses obtenues.
     *
     * (XapianMSet::get_matches_estimated())
     *
     * @var int
     */
    protected $count;

    /**
     * L'objet XapianMSetIterator permettant de parcourir les réponses obtenues
     *
     * @var \XapianMSetIterator
     */
    protected $xapianMSetIterator;

    /**
     * La version corrigée (correcteur orthographique) de l'équation de recherche ou
     * une chaine vide si aucune correction n'est disponible.
     *
     * Initialisé par {@link spellCheckQuery()} lorsqu'on appelle {@link getCorrectedQuery()}
     * pour la prmière fois.
     *
     * @var string
     */
    protected $correctedQuery;

    /**
     * Exécute la requête passée en paramètre sur la base de données indiquée et
     * stocke les résultats obtenus.
     *
     * @param XapianStore $store la base de données Xapian sur laquelle la recherche
     * sera exécutée.
     *
     * @param SearchRequest $searchRequest la requête à exécuter.
     */
    public function __construct(XapianStore $store, SearchRequest $searchRequest)
    {
        //if (! $store instanceof XapianStore) throw new \InvalidArgumentException();

        parent::__construct($store, $searchRequest);

//        echo "Paramètres de la recherche :";
//        $searchRequest->dump();

        $this->xapianQueryParser = $store->getQueryParser();

        $this->xapianQuery = $this->createXapianQuery($searchRequest);
        // echo "Xapian Query: <code>", $this->xapianQuery->get_description(), "</code><br />";

        // Initialise l'environnement de recherche
        $this->xapianEnquire = new XapianEnquire($store->getXapianDatabase());

        // Définit la requête à exécuter
        $this->xapianEnquire->set_query($this->xapianQuery);

        // Lance la recherche
        $this->xapianMSet = $this->xapianEnquire->get_MSet($searchRequest->start()-1, $searchRequest->max(), $searchRequest->checkatleast());

        // Détermine le nombre de réponses obtenues
        $this->count = $this->xapianMSet->get_matches_estimated();

        // Si on n'a aucune réponse parce que start était "trop grand", ré-essaie en ajustant start
        if ($this->xapianMSet->is_empty() && $this->count > 1 && $searchRequest->start() > $this->count)
        {
            // le mset est vide, mais on a des réponses (count > 0) et le start demandé était
            // supérieur au count obtenu. Fait pointer start sur la 1ère réponse de la dernière page
            $searchRequest->start($this->count-(($this->count-1) % $searchRequest->max()));

            // Relance la recherche
            $this->xapianMSet = $this->xapianEnquire->get_MSet($searchRequest->start(), $searchRequest->max(), $searchRequest->checkatleast());
        }

        // On n'a réellement aucune réponse
        if ($this->xapianMSet->is_empty())
        {
            $this->count = 0;
        }
    }

    public function isEmpty()
    {
        return $this->xapianMSet->is_empty();
    }

    public function count($type = null)
    {
        if (is_null($type) || $this->count === 0) return $this->count;

        if ($type === 'min')
        {
            return $this->xapianMSet->get_matches_lower_bound();
        }

        if ($type === 'max')
        {
            return $this->xapianMSet->get_matches_upper_bound();
        }

        if ($type !== 'round')
        {
            throw new BadMethodCallException('count : type incorrect, min, max, round ou null attendu');
        }

        $min = $this->xapianMSet->get_matches_lower_bound();
        $max = $this->xapianMSet->get_matches_upper_bound();

        // Si min==max, c'est qu'on a le nombre exact de réponses, pas d'évaluation
        if ($min === $max) return $min;

        $unit = pow(10, floor(log10($max - $min)));
        $round = max(1, round($this->count / $unit)) * $unit;

        // Dans certains cas, on peut se retrouver avec une évaluation inférieure à start, ce
        // qui génère un pager de la forme "Réponses 2461 à 2470 sur environ 2000".
        // Quand on détecte ce cas, passe à l'unité supérieure.
        // Cas trouvé avec la requête "prise en +charge du +patient diabétique"
        // dans la base documentaire bdsp.
        if ($round < $this->searchRequest->start())
        {
            $round = max(1, round($count / $unit) + 1) * $unit;
        }

        return (int) $round;
    }

    /* Interface Iterator */

    /**
     * Interface Iterator : va sur la première réponse obtenue.
     */
    public function rewind()
    {
        $this->xapianMSetIterator = $this->xapianMSet->begin();
    }

    /**
     * Interface Iterator : retourne le rang (le numéro d'ordre) de la
     * réponse en cours.
     *
     * @return int
     */
    public function key()
    {
        return $this->xapianMSetIterator->get_rank() + 1;
    }

    /**
     * Interface Iterator : retourne le document correspondant à la réponse en cours.
     *
     * @return Document
     */
    public function current()
    {
        $docid = $this->xapianMSetIterator->get_docid();
        return $this->document = $this->store->get($docid);
    }

    /**
     * Interface Iterator : va sur la réponse suivante.
     */
    public function next()
    {
        $this->xapianMSetIterator->next();
    }

    /**
     * Interface Iterator : indique s'il y a une réponse en cours.
     *
     * @return bool
     */
    public function valid()
    {
        return ! $this->xapianMSetIterator->equals($this->xapianMSet->end());
    }

    /* Fin Iterator */


// -------------------------------

    /**
     * Crée la requête xapian à partir de la requête passée en paramètre.
     *
     * @param SearchRequest $request
     *
     * @return XapianQuery
     */
    protected function createXapianQuery(SearchRequest $request)
    {
        $defaultop = $request->defaultop() === 'and' ? XapianQuery::OP_AND : XapianQuery::OP_OR;
        $query = $this->parseQuery($this->getQuery(), $defaultop);
        return $query;

        // @todo :
        // XapianDatabase:1997
        // filter
        // docset
        // default equation
        // default filter
        // boost
        // création de la "version affichée à l'utilisateur" de la requête


        $query = $request->equation();
        if ($query)
        {
            $query = $this->parseQuery($query, $defaultop, XapianQuery::OP_AND);
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
            'prob'  => array(XapianQuery::OP_AND, XapianQuery::OP_AND), //
            'bool'  => array(XapianQuery::OP_OR , XapianQuery::OP_AND), //
            'love'  => array(XapianQuery::OP_AND, XapianQuery::OP_AND), // Tous les mots sont requis, donc on parse en "ET"
            'hate'  => array(XapianQuery::OP_OR , XapianQuery::OP_OR) , // Le résultat sera combiné en "AND_NOT hate", donc on parse en "OU"
        );

        foreach ($types as $type => $op)
        {
            $queries = array();
            foreach($request->$type() as $index => $equation)
            {
                $queries[] = $this->parseQuery($equation, $defaultop, $op[0], $index);
            }

            switch (count($queries))
            {
                case 0: // Aucune requête de type $type, rien à faire
                    $$type = null;
                    break;

                case 1: // Une seule requête de type $type, on l'utilise telle quelle
                    $$type = $queries[0];
                    break;

                default: // Plusieurs requêtes de type $type : on les combine ensemble
                    $$type = new XapianQuery($op[1], $queries);
                    break;
            }
        }

        // Crée la partie principale de la requête sous la forme :
        // ((query AND love AND_MAYBE prob) AND_NOT hate) FILTER bool
        // Si defaultop=AND, le AND_MAYBE devient OP_AND
        if ($love || $query)
        {
            if ($love)
            {
                $query = (is_null($query) || $this->isMatchAll($query))
                ? $love
                : new XapianQuery(XapianQuery::OP_AND, $query, $love);
            }

            if ($prob)
            {
                if (is_null($query) || $this->isMatchAll($query))
                {
                    $query = $prob;
                }
                elseif ($defaultop === XapianQuery::OP_OR)
                {
                    $query=new XapianQuery(XapianQuery::OP_AND_MAYBE, $query, $prob);
                }
                else
                {
                    // todo : AND ou AND_MAYBE quand defaultop = AND ???
                    //$query=new XapianQuery(XapianQuery::OP_AND_MAYBE, $query, $prob);
                    $query=new XapianQuery(XapianQuery::OP_AND, $query, $prob);
                }
            }
        }
        else
        {
            $query = $prob;
        }

        if ($hate)
        {
            // on ne peut pas faire null AND_NOT xxx. Si query est null, crée une query '*'
            if (is_null($query)) $query = new XapianQuery('');
            $query = new XapianQuery(XapianQuery::OP_AND_NOT, $query, $hate);
        }

        if ($bool)
        {
            if (is_null($query) || $this->isMatchAll($query))
                $query = $bool;
            else
                $query = new XapianQuery(XapianQuery::OP_FILTER, $query, $bool);
        }

// XapianDatabase:1997
// filter
// docset
// default equation
// default filter
// boost
// création de la "version affichée à l'utilisateur" de la requête
        return $query;
    }

    /**
     * Indique si la requête xapian passée en paramètre est Xapian::MatchAll.
     *
     * @param XapianQuery $query
     * @return bool
     */
    private function isMatchAll(XapianQuery $query)
    {
        return $query->get_description() === 'Xapian::Query(<alldocuments>)';
    }

    /**
     * Transforme l'équation en minuscules non accentuées et convertit
     * les acronymes en mots.
     *
     * @param string $equation
     */
    private function lowercaseEquation($equation)
    {
        static $map = null;

        if (is_null($map))
        {
            // Pour tokenizer l'équation, on utilise la même que l'analyseur Lowercase
            $map = \Fab\Indexing\Lowercase::$map;

            // Sauf qu'on veut conserver le tiret pour pouvoir gérer la syntaxe -hate
            unset($map['-']);
        }

        // Transforme l'équation en minus non accentuées en conservant les autres caractères
        $equation = strtr($equation, $map);

        // Transforme les sigles de 2 à 9 lettres en mots
        $equation = preg_replace_callback
        (
            '~(?:[a-z0-9]\.){2,9}~i',
            function ($matches) { return str_replace('.', '', $matches[0]); },
            $equation
        );

        return $equation;
    }

    /**
     * Constuit une requête Xapian à partir des équations de recherche passées en paramètre.
     *
     * @param string|array $equations une ou plusieurs équations de recherche à analyser.
     *
     * Au sein d'une équation, l'opérateur booléen indiqué par $intraOpCode est utilisé
     * comme opérateur par défaut.
     *
     * Les équations de recherche sont combinées entres elles avec l'opérateur booléen
     * indiqué par le paramètre $interOpCode.
     *
     * @param int $intraOpCode opérateur booléen par défaut utilisé pour combiner entres
     * eux les termes qui figurent au sein d'un équation.
     *
     * @param int $interOpCode opérateur booléen utilisé pour combiner entres elles des
     * équations de recherche différentes.
     *
     * @param string $index optionnel, nom de l'index ou de l'alias sur lequel porte la
     * recherche.
     *
     * @return \XapianQuery la requête Xapian obtenue suite après analyse.
     */
    private function parseQuery($equations, $intraOpCode=XapianQuery::OP_OR, $interOpCode=XapianQuery::OP_OR, $index=null)
    {
        // Paramètre l'opérateur par défaut du Query Parser
        $this->xapianQueryParser->set_default_op($intraOpCode);

        // Détermine les flags du Query Parser
        $flags = XapianQueryParser::FLAG_BOOLEAN
               | XapianQueryParser::FLAG_PHRASE
               | XapianQueryParser::FLAG_LOVEHATE
               | XapianQueryParser::FLAG_WILDCARD
               | XapianQueryParser::FLAG_PURE_NOT
               | XapianQueryParser::FLAG_BOOLEAN_ANY_CASE;

//         if ($this->searchRequest->opanycase())
//         {
//             $flags |= XapianQueryParser::FLAG_BOOLEAN_ANY_CASE;
//         }

        // Analyse toutes les équations de recherche en utilisant $intraOpCode comme opérateur
        // par défaut et construit un tableau contenant les objets XapianQuery correspondants.
        $query = array();
        foreach ((array)$equations as $equation)
        {
            $equation = trim($equation);
            if ($equation === '') continue;

            if ($equation === '*') // @todo : prendre en compte $index
            {
                $query[] = new XapianQuery(''); // Match all
                continue;
            }

            // Pré-traitement de l'équation pour que xapian l'interprête comme on souhaite
            $equation = $this->lowercaseEquation($equation);

            // Convertit les recherches à l'article en termes Xapian : [doe john] -> _doe_john_
            $equation = preg_replace_callback
            (
                '~\[\s*(.*?)\s*\]~',
                function($matches)
                {
                    $term = $matches[1];
                    return '_' . implode('_', str_word_count($term, 1, '0123456789')) . (substr($term, -1) === '*' ? '*' : '_');
                },
                $equation
            );

            // Elimine les espaces éventuels après les noms d'index (par exemple "Index= xxx")
            $equation = preg_replace('~\b(\w+)\s*[:]\s*~', '$1:', $equation);

            // Convertit les opérateurs booléens français en anglais, sauf dans les phrases
            $t = explode('"', $equation);  //   a ET b ET "c ET d"
            foreach($t as $i => & $h)
            {
                if ($i % 2 === 1) continue;
                $h = preg_replace
                (
                    array('~\bET\b~','~\bOU\b~','~\b(?:SAUF|BUT)\b~'),
                    array('and', 'or', 'not'),
                    $h
                );
            }
            $equation = implode('"', $t);

            // Ajoute le nom de l'index par défaut sur lequel porte la recherche
            if (! is_null($index))
            {
                $equation = strtolower($index) . ':(' . $equation . ')';
            }

            // Construit la requête
            $query[] = $this->xapianQueryParser->parse_Query($equation, $flags);
        }

        // Retourne la requête obtenue. Si plusieurs équations ont été analysées,
        // combine les objets XapianQuery obtenus avec l'opérateur $interOpCode.
        switch(count($query))
        {
            case 0: return null;
            case 1: return $query[0];
            default: return new XapianQuery($interOpCode, $query);
        }
    }

    public function getStopwords($removeDuplicates = true)
    {
        if (is_null($this->xapianQueryParser)) return array();

        $stopwords = array();
        $begin = $this->xapianQueryParser->stoplist_begin();
        $end = $this->xapianQueryParser->stoplist_end();

        if ($removeDuplicates)
        {
            while(! $begin->equals($end))
            {
                $stopwords[$begin->get_term()] = true;
                $begin->next();
            }

            return array_keys($stopwords);
        }

        while(! $begin->equals($end))
        {
            $stopwords[] = $begin->get_term(); // pas de dédoublonnage
            $begin->next();
        }

        return $stopwords;
    }

    public function getQueryTerms($internal = false)
    {
        // Si aucune requête n'a été exécutée, retourne un tableau vide
        if (is_null($this->xapianQuery)) return array();

        $terms = array();
        $begin = $this->xapianQuery->get_terms_begin();
        $end = $this->xapianQuery->get_terms_end();
        if ($internal)
        {
            while (! $begin->equals($end))
            {
                $terms[] = $begin->get_term();
                $begin->next();
            }

            return $terms;
        }

        while (! $begin->equals($end))
        {
            $term = $begin->get_term();

            // Supprime le préfixe de l'index
            if (false !== $pt=strpos($term, ':')) $term = substr($term,$pt+1);

            // Pour les articles, supprime les underscores
            $term = strtr(trim($term, '_'), '_', ' ');

            // Dédoublonnage
            $terms[$term]=true;

            $begin->next();
        }
        return array_keys($terms);

    }

    public function getMatchingTerms($internal = false)
    {
        if (is_null($this->xapianMSetIterator)) return array();

        $terms = $this->xapianEnquire->get_matching_terms($this->xapianMSetIterator);
        if ($internal) return $terms;

        $unique = array();
        foreach($terms as $term)
        {
            // Supprime le préfixe de l'index
            if (false !== $pt=strpos($term, ':')) $term = substr($term, $pt+1);

            // Pour les articles, supprime les underscores
            $term = strtr(trim($term, '_'), '_', ' ');

            // Dédoublonnage
            $unique[$term] = true;
        }
        return array_keys($unique);
    }

    public function getIndexTerms()
    {
        $doc = $this->xapianMSetIterator->get_document();
        $result = array();

        // Construit la liste des termes (mots, mots-clés, lookups)
        $begin = $doc->termlist_begin();
        $end = $doc->termlist_end();
        while (! $begin->equals($end))
        {
            $term = $begin->get_term();
            $type = 'term';
            if (false === $pt=strpos($term,':'))
            {
                $index = '';
            }
            else
            {
                $prefix = substr($term, 0, $pt);
                $term = substr($term, $pt+1);
                if ($prefix[0] === 'T')
                {
                    $type = 'lookup';
                    $prefix = substr($prefix, 1);
                }
                elseif ($term[0] === '_')
                {
                    $type = 'keyword';
                }
                $index = $this->schema->indices->get($prefix)->name;
            }

            // Liste des positions pour le terme en cours
            $posBegin = $begin->positionlist_begin();
            $posEnd = $begin->positionlist_end();

            $pos = array();
            while(! $posBegin->equals($posEnd))
            {
                $pos[] = $posBegin->get_termpos();
                $posBegin->next();
            }

            if (! isset($result[$index])) $result[$index] = array();
            $result[$index][$term]=array
            (
                'type' => $type,
                'weight' => $begin->get_wdf(),
                'total' => $begin->get_termfreq(),
                'pos' => count($pos) ? $pos : null,
            );

            $begin->next();
        }

        // Liste des attributs (values)
        $begin = $doc->values_begin();
        $end = $doc->values_end();
        while (! $begin->equals($end))
        {
            $slot = $begin->get_valueno();
            $term = $begin->get_value();
            if (ord($term) < 31 || ord($term) > 230) $term = '0x' . strtoupper(dechex(ord($term)));

            $index = $this->schema->indices->get($slot)->name;
            if (! isset($result[$index])) $result[$index] = array();
            $result[$index][$term]=array
            (
                'type' => 'attribute',
                'weight' => null,
                'total' => null,
                'pos' => null,
            );
            $begin->next();
        }

        // Trie par nom d'index (xapian les retourne triés par ID)
        ksort($result);

        return $result;
    }

    public function explainQuery()
    {
        $query = $this->xapianQuery;

        if (is_null($query)) return '';

        // Récupère la description de la requête Xapian
        $h = $query->get_description();

        // Supprime le libellé "XapianQuery()" et le premier niveau de parenthèses
        if (substr($h, 0, 14) === 'Xapian::Query(') $h=substr($h, 14, -1);

        // Supprime les mentions "(pos=n)" présentes dans la requête
        $h = preg_replace('~:\(pos=\d+?\)~', '', $h);

        // Reconstruit les expressions entre guillemets
        $h = preg_replace_callback
        (
            '~\((\d+:)[a-z0-9@_]+(?: (PHRASE \d+ )\1[a-z0-9@_]+)+\)~',
            function ($matches)
            {
                $query = substr($matches[0], 1, -1); // l'expression est toujours entourée de parenthèses (inutiles)
                $id = $matches[1];
                $op = $matches[2];
                return $id
                    . '<span class="punctuation">"</span>'
                    . '<span class="phrase">' . strtr($query, array($id => '', $op => '')) . '</span>'
                    . '<span class="punctuation">"</span>';
            },
            $h
        );

        // Reconstruit les recherches à l'article
        $h = preg_replace_callback
        (
            '~_[a-z0-9@_]+_~',
            function ($matches)
            {
                return '<span class="punctuation">[</span>'
                     . '<span class="keyword">' . trim(strtr($matches[0], '_', ' ')) . '</span>'
                     . '<span class="punctuation">]</span>';
            },
            $h
        );

        // Traduits les préfixes utilisés en noms de champs
        $schema = $this->schema;
        $h = preg_replace_callback
        (
            '~(\d+):~',
            function ($matches) use($schema)
            {
                $index = $schema->indices->get((int)$matches[1])->name;
                return '<span class="index">'.$index.'</span><span class="punctuation">=</span>';
            },
            $h
        );

        // cas particulier : requête match all
        $h = str_replace('<alldocuments>', '<span class="punctuation">&lt;tous les documents&gt;</span>', $h);

        // Met les opérateurs booléens en gras
        $h = preg_replace('~AND_MAYBE|AND_NOT|FILTER|AND|OR|PHRASE \d+|SYNONYM~', '<span class="operator">$0</span>', $h);

        // Si l'expression obtenue commence par une parenthèse, c'est qu'on a un niveau de parenthèses en trop
        if (substr($h, 0, 1)==='(' && substr($h, -1)===')')
        {
            $h = substr($h, 1, -1);
        }

        // Va à la ligne et indente à chaque niveau de parenthèse
        $h=strtr
        (
            $h,
            array
            (
                '('=>'<br /><span class="punctuation">(</span><div style="margin-left: 2em;">',
                ')'=>'</div><span class="punctuation">)</span><br />',
            )
        );

        // Supprime les <br /> superflus
        $h = str_replace('<br /><br />', '<br />', $h);
        $h = preg_replace('~^<br />|<br />$~', '', $h);
        $h = preg_replace('~(<div[^>]*>)<br />(\(<div)~', '$1$2', $h);

        // Retourne le résultat
        return $h;
    }

    /**
     * Applique un correcteur orthographique à la requête en cours
     * et retourne une équation de recherche corrigée.
     *
     * La méthode extrait de la requête en cours tous les termes qui n'existent
     * pas dans la base.
     *
     * Elle appelle ensuite pour chaque mot la méthode
     * XapianDatabase::get_spelling_suggestion().
     *
     * Si xapian propose une suggestion, le mot d'origine est remplacé par
     * celle-ci dans la requête en cours en utilisant le format passé en
     * paramètre.
     *
     * Lors de ce remplacement, la méthode essaie de donner à la suggestion
     * trouvée la même casse de caractères que le mot d'origine (si le mot était
     * en majuscules, la suggestion sera en majuscules, s'il était en minuscules
     * avec une initiale, la suggestion aura la même casse, etc.)
     *
     * Les sigle sont également gérés : ils sont transformés en termes puis
     * réintroduits sous forme de sigles dans l'équation d'origine.
     *
     * Remarque : les suggestions faites sont toujours non accentuées.
     *
     * @param string $format le format (façon sprintf) à utiliser. Doit
     * obligatoirement contenir la chaine '%s'.
     */
    public function getCorrectedQuery($format = '<strong>%s</strong>')
    {
        // Si c'est le premier appel, corrige l'équation en cours
        if (is_null($this->correctedQuery))
        {
            $this->spellcheckQuery('^^^%s$$$');
        }

        // Coupe le format indiqué en "avant"/"après"
        $delim = explode('%s', $format, 2);
        if (! isset($delim[1])) $delim[1]='';

        // Insère les délimiteurs demandés dans l'équation
        return strtr
        (
            $this->correctedQuery,
            array
            (
                '^^^' => $delim[0],
                '$$$' => $delim[1],
            )
        );
    }

    /**
     *
     * Version str_word_count qui fonctionne sur de l'utf-8.
     *
     * Adapté de http://www.php.net/manual/fr/function.str-word-count.php#85592
     *
     * Important : quand $format=2, les positions retournées sont en octets, pas en caractères.
     */
    private function str_word_count_utf8($string, $format = 0)
    {
        // static $mask = "/\p{L}[\p{L}\p{Mn}\p{Pd}'\x{2019}]*/u"; // version d'origine
        static $mask = "/\p{L}[\p{L}\p{Mn}\p{Pd}]*/u";    // version modifiée : l'apostrophe ne fait pas partie du mot

        switch ($format)
        {
            case 0:
                return preg_match_all($mask, $string);
            case 1:
                preg_match_all($mask, $string, $matches);
                return $matches[0];
            case 2:
                preg_match_all($mask, $string, $matches, PREG_OFFSET_CAPTURE);
                $result = array();
                foreach ($matches[0] as $match)
                {
                    $result[$match[1]] = $match[0];
                }
                return $result;
        }

    }

    protected function spellcheckQuery($format = '<strong>%s</strong>')
    {
        // Crée la liste des termes de la requête qui n'existent pas dans la base
        // Chaque terme peut apparaître dans plusieurs index (12:test, 15:test, etc.)
        // On considère qu'un terme n'existe pas s'il ne figure dans aucun des index
        $corrections = $found = array();
        $db = $this->store->getXapianDatabase();
        foreach($this->getQueryTerms(true) as $term)
        {
            // Extrait le préfixe du terme
            $prefix = '';
            if (false !== $pt=strpos($term, ':'))
            {
                $prefix = substr($term, 0, $pt + 1);
                $term = substr($term, $pt + 1);
            }

            // Extrait les mots présents dans le terme (essentiellement pour les articles)
            $words = str_word_count($term, 1, '0123456789@');
            foreach($words as $word)
            {
                // Si on a déjà rencontré ce mot, terminé
                if (isset($found[$word]) && $found[$word]) continue;

                // Si c'est un nombre, terminé
                if (ctype_digit($word)) continue;  // évite un warning xapian : no overloaded function get_spelling_suggestion(int))

                if ($db->term_exists($prefix.$word))
                {
                    $found[$word] = true;
                }
                elseif(! isset($found[$word]))
                {
                    $found[$word] = false;
                }
            }
        }

        // Recherche une suggestion pour chacun des mots obtenus
        foreach($found as $word => $exists)
        {
            if (! $exists && $correction = $db->get_spelling_suggestion($word, 2))
            {
                $corrections[$word] = $correction;
            }
        }

        // Récupère l'équation de recherche de l'utilisateur
        $string = $this->getQuery();

        $words = $this->str_word_count_utf8($string, 2);
        // @todo : intégrer le code de str_word_count ici pour éviter de recopier les
        // matches et de créer un tableau intermédiaire.

        // offset enregistre le décalage des positions dûs aux remplacements déjà effectués
        $offset = 0;

        // Corrige les mots
        foreach($words as $position => $word)
        {
            $lower = $this->lowercaseEquation($word);
            if (isset($corrections[$lower]))
            {
                // Récupère la correction
                $correction = $corrections[$lower];

                // Essaie de donner à la suggestion la même "casse" que le mot d'origine
                if (preg_match('~^\p{Lu}[\p{Lu}\p{Mn}]*$~u', $word)) // équivalent de ctype_upper utf-8
                {
                    $correction = strtoupper($correction);
                }
                elseif (preg_match('~^\p{Lu}\p{Mn}*~u', $word)) // initiale en maju
                {
                    $correction = ucfirst($correction);
                }

                $correction = sprintf($format, $correction);

                $string = substr_replace($string, $correction, $position + $offset, strlen($word));
                $offset += (strlen($correction) - strlen($word));
            }
        }

        $this->correctedQuery = $offset ? $string : '';
    }
}