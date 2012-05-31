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
 * Analyseur standard pour les champs texte (titre, résumé, etc.)
 *
 * Exécute dans l'ordre les analyseurs suivants :
 * - {@link \Fab\Indexing\Lowercase}
 * - {@link \Fab\Indexing\Phrase}
 * - {@link \Fab\Indexing\Spelling}
 */
class StandardTextAnalyzer extends MetaAnalyzer
{
    public function __construct()
    {
        parent::__construct(array
        (
        	'Fab\Indexing\Lowercase',
        	'Fab\Indexing\Phrases',
        	'Fab\Indexing\Spellings'
        ));
    }
}