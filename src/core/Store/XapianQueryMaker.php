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

use Fooltext\Query\Query;
use Fooltext\Query\TermQuery;
use Fooltext\Query\MatchNothingQuery;
use Fooltext\Query\MatchAllQuery;
use Fooltext\Query\WildcardQuery;
use Fooltext\Query\PositionalQuery;

use \XapianQuery;

/**
 * Convertit un objet Fooltext\Query en requête Xapian.
 */
class XapianQueryMaker
{
    /**
     * La base sur laquelle porte la recherche.
     *
     * @var XapianStore
     */
    protected $store;

    public function __construct(XapianStore $store)
    {
        $this->store = $store;
    }

    /**
     * Convertit une équation de recherche ou un objet Fooltext\Query
     * en requête Xapian.
     *
     * @param Query $query
     * @return XapianQuery
     */
    public function convert(Query $query, $prefixes = array())
    {
        $xop = array
        (
            Query::QUERY_OR => XapianQuery::OP_OR,
            Query::QUERY_AND => XapianQuery::OP_AND,
            Query::QUERY_NOT => XapianQuery::OP_AND_NOT,
            Query::QUERY_AND_MAYBE => XapianQuery::OP_AND_MAYBE,

            Query::QUERY_NEAR => XapianQuery::OP_NEAR,
            Query::QUERY_PHRASE => XapianQuery::OP_PHRASE,
//            Query::QUERY_TERM => XapianQuery::OP_OR
        );

        $type = $query->getType();
        $args = $query->getArgs();
        $field = $query->getField();
        if ($field) $prefixes = $this->getPrefixes($field);

        // near, phrase
        if ($query instanceof PositionalQuery)
        {
            // on a, par exemple, Mots="a b". On veut convertir en (Titre="a b" OR Resum="a b")
            // i.e. on a Mots:a PHRASE 2 Mots:b et on veut obtenir (Titre:a PHRASE 2 Titre:b) OR (Resum:a PHRASE 2 Resum:b)
            // on le fait nous même, car sinon, xapina génère toutes les combinaisons possibles :
            // (Titre:a PHRASE 2 Titre:b) OR (Titre:a PHRASE 2 Resum:b) OR (Resum:a PHRASE 2 Titre:b) (Resum:a PHRASE 2 Resum:b)

            $queries = array();
            foreach ($prefixes as $prefix)
            {
                $terms = array();
                foreach($args as $arg)
                {
                    $terms[] = $prefix . $arg;
                }
                $queries[] = new XapianQuery($xop[$type], $terms, count($terms) + $query->getGap());
            }
            if (count($queries) === 1) return $queries[0];
            return new XapianQuery(XapianQuery::OP_OR, $queries);
        }


        foreach ($args as & $arg)
        {
            if ($arg instanceof Query)
            {
                $arg = $this->convert($arg, $prefixes);
            }
        }

        if ($query instanceof WildcardQuery)
        {
            return $this->wildcardQuery($query, $prefixes);
        }

        if ($query instanceof TermQuery)
        {
            return $this->termQuery($query, $prefixes);
        }

        if ($query instanceof MatchAllQuery)
        {
            return new XapianQuery('');
        }

        if ($query instanceof MatchNothingQuery)
        {
            return new XapianQuery();
        }

        // or, and, not, and_maybe
        return new XapianQuery($xop[$type], $args);
    }

    protected function termQuery(TermQuery $query, $prefixes)
    {
        $term = $query->getTerm();

        foreach ($prefixes as & $prefix)
        {
            $prefix .= $term;
        }

        return new XapianQuery(XapianQuery::OP_OR, $prefixes);
    }

    protected function wildcardQuery(WildcardQuery $query, $prefixes)
    {
        $term = $query->getTerm();
        foreach ($prefixes as $prefix)
        {
            $terms[] = $this->expandWildcard($prefix, $term);
        }

        return new XapianQuery(XapianQuery::OP_OR, $terms);
    }

    protected function expandWildcard($prefix, $mask)
    {
        $start = substr($mask, 0, strcspn($mask, '?*'));
        $terms = array($start.'1', $start.'2', $start.'3'); // consulter la base et extraire tout ce qui commence par start et qui respecte le masque
        foreach($terms as & $term)
        {
            $term = $prefix . $term;
        }
        return new XapianQuery(XapianQuery::OP_SYNONYM, $terms);
    }

    protected function getPrefixes($field)
    {
        return array($this->store->getSchema()->indices->get($field)->_id.':');

        return array($field.'1:');
        return array($field.'1:', $field.'2:');
        return array('f1:', 'f2:', 'f3:');
        return array($this->store->getField($field)->_id . ':', '4:');
    }

}