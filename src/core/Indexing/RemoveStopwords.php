<?php
/**
 * This file is part of the Fab package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fab
 * @subpackage  Indexing
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fab\Indexing;

/**
 * Supprime les mots vides présents dans les termes ou dans les postings.
 *
 * Remarques :
 * - les mots-vides sont stockés en minuscules non accentuées. Pour utiliser
 *   RemoveStopwords, vous devez donc utiliser au préalable un tokenizer tel
 *   que {@link Lowercase} ou {@link StripTags}.
 * - RemoveStopwords doit être exécuté avant Stemming.
 */
class RemoveStopwords implements AnalyzerInterface
{
    public function analyze(AnalyzerData $data)
    {
        // Si on n'a pas de champ, pas de schéma, donc pas de mots vides
        if (is_null($data->index)) return;

        // Récupère la liste des mots vides
        $stopwords = $data->index->getSchema()->stopwords;

        $data->map(array('terms', 'postings'), function($term) use($stopwords) {
            return isset($stopwords[$term]) ? null : $term;
        });
/*
        // Supprime les mots vides présents dans $data->terms et $data->postings
        foreach(array('terms', 'postings') as $property)
        {
            foreach($data->$property as $i => & $term)
            {
                if (is_array($term))
                {
                    foreach($term as $j => $t)
                    {
                        if (isset($stopwords[$t])) unset($term[$j]);
                    }
                }
                else
                {
                    if (isset($stopwords[$term])) unset($data->$property[$i]);
                }
            }
        }
*/
    }
}