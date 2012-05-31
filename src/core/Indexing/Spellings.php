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

use Fab\Indexing\AnalyzerInterface;

/**
 * Ajoute les termes dans le correcteur orthographique.
 *
 * Pour utiliser cet analyseur, vous devez au préalable indexer le texte
 * au mot ou à la phrase (i.e. votre chaine d'analyse doit contenir un
 * analyseur tel que {@link Words} ou {@link Phrases}.
 */
class Spellings implements AnalyzerInterface
{
    public function analyze(AnalyzerData $data)
    {
        $data->spellings = array_merge($data->spellings, $data->terms, $data->postings);
    }
}